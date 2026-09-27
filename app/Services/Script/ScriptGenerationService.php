<?php

namespace App\Services\Script;

use App\Enums\AiGenerationStatus;
use App\Enums\ResearchClaimImportance;
use App\Exceptions\ScriptGenerationNotReadyException;
use App\Exceptions\ScriptGenerationProviderException;
use App\Models\AiGeneration;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Services\AI\Contracts\ScriptGenerationProvider;
use App\Services\AI\DTO\ScriptGenerationRequest;
use App\Services\AI\DTO\ScriptGenerationResponse;
use App\Services\AI\Prompt\MochyFamiScriptProfile;
use App\Services\AI\ScriptGenerationProviderRegistry;
use App\Services\ResearchQualityService;
use App\Services\ScriptService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Coordinates a full AI script generation request.
 *
 * The provider abstraction lives outside the Script domain; this service
 * wires discovery, readiness, provider resolution, versioning and audit
 * without ever coupling the domain to a concrete vendor.
 */
class ScriptGenerationService
{
    public const MAX_TOTAL_OUTPUT_LENGTH = 20000;

    public const MAX_FIELD_LENGTH = 255;

    public const MAX_DURATION_SECONDS = 600;

    public const MIN_DURATION_SECONDS = 1;

    public function __construct(
        protected ScriptService $scriptService,
        protected ScriptGenerationProviderRegistry $registry,
        protected ResearchQualityService $researchQualityService,
        protected ScriptQualityService $scriptQualityService,
    ) {}

    /**
     * Generate a new script version from the project research.
     *
     * Requires the project to have a script (404 upstream) and research that
     * passes the existing research quality gate. Generation NEVER modifies
     * the script status and NEVER overwrites an existing version.
     *
     * @param  array{provider?: string|null, topic: string, language?: string|null, tone?: string|null, format?: string|null, target_duration_seconds?: int|null, hook_style?: string|null, instructions?: string|null}  $input
     * @return array{
     *     script: Script,
     *     generated_version: ScriptVersion,
     *     generation: array{provider: string, model: string|null, prompt_profile: string, prompt_version: string, status: string, source_version_id: int|null, generated_version_id: int|null},
     *     quality: array
     * }
     *
     * @throws ScriptGenerationNotReadyException
     * @throws ScriptGenerationProviderException
     * @throws ModelNotFoundException
     */
    public function generate(ContentProject $project, array $input): array
    {
        $script = $this->scriptService->getScriptOrFail($project);

        $report = $project->researchReport()->first();

        if (! $report || ! $this->researchQualityService->evaluateReport($report)['ready']) {
            throw new ScriptGenerationNotReadyException;
        }

        $claimRows = $report->claims()->select(['id', 'importance', 'status', 'claim'])->get();

        $importantClaims = $claimRows
            ->filter(fn ($claim) => $claim->importance === ResearchClaimImportance::High)
            ->pluck('claim')
            ->values()
            ->all();

        $generationRequest = new ScriptGenerationRequest(
            topic: $input['topic'],
            language: $input['language'] ?? 'id',
            tone: $input['tone'] ?? 'casual',
            format: $input['format'] ?? 'educational',
            targetDurationSeconds: (int) ($input['target_duration_seconds'] ?? ScriptGenerationRequest::DEFAULT_DURATION_SECONDS),
            hookStyle: $input['hook_style'] ?? null,
            instructions: $input['instructions'] ?? null,
            importantClaims: $importantClaims,
            researchContext: [
                'research_ready' => true,
                'claims' => $claimRows->map(fn ($claim) => [
                    'id' => $claim->id,
                    'importance' => $claim->importance->value,
                    'status' => $claim->status->value,
                    'claim' => $claim->claim,
                ])->values()->all(),
            ],
            promptProfile: MochyFamiScriptProfile::NAME,
        );

        $provider = $this->registry->resolve($input['provider'] ?? config('ai.default_script_generation_provider', 'fake'));

        $response = $this->callProvider($provider, $generationRequest, $script);

        $this->validateOutput($provider, $script, $response);

        return DB::transaction(function () use ($project, $script, $response) {
            $sourceVersionId = $script->currentVersion?->id;

            $updatedScript = $this->scriptService->createVersion($project, [
                'title' => $response->title,
                'hook' => $response->hook,
                'body' => $response->body,
                'closing' => $response->closing,
                'duration_seconds' => $response->durationSeconds,
                'notes' => $response->notes,
            ]);

            $generatedVersion = $updatedScript->currentVersion;

            AiGeneration::create([
                'script_id' => $updatedScript->id,
                'provider' => $response->provider,
                'model' => $response->model,
                'prompt_profile' => MochyFamiScriptProfile::PROFILE,
                'prompt_version' => MochyFamiScriptProfile::VERSION,
                'source_version_id' => $sourceVersionId,
                'generated_version_id' => $generatedVersion->id,
                'status' => AiGenerationStatus::Completed,
            ]);

            return [
                'script' => $updatedScript,
                'generated_version' => $generatedVersion,
                'generation' => [
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'prompt_profile' => MochyFamiScriptProfile::PROFILE,
                    'prompt_version' => MochyFamiScriptProfile::VERSION,
                    'status' => AiGenerationStatus::Completed->value,
                    'source_version_id' => $sourceVersionId,
                    'generated_version_id' => $generatedVersion->id,
                ],
                'quality' => $this->scriptQualityService->evaluateScript($updatedScript),
            ];
        });
    }

    /**
     * Call the provider and record failure without touching script state.
     *
     * @param  ScriptGenerationProvider  $provider
     *
     * @throws ScriptGenerationProviderException
     */
    private function callProvider($provider, ScriptGenerationRequest $request, Script $script): ScriptGenerationResponse
    {
        try {
            return $provider->generate($request);
        } catch (ScriptGenerationProviderException $e) {
            $this->recordFailure($script, $provider->name(), $e->getMessage());
            throw $e;
        } catch (Throwable $e) {
            $message = 'The script generation provider returned an invalid response.';
            $this->recordFailure($script, $provider->name(), $message);

            throw new ScriptGenerationProviderException(
                $message,
                ScriptGenerationProviderException::INVALID_RESPONSE,
                $e
            );
        }
    }

    /**
     * Validate generated output and record an audit failure when invalid.
     *
     * @param  ScriptGenerationProvider  $provider
     *
     * @throws ScriptGenerationProviderException
     */
    private function validateOutput($provider, Script $script, ScriptGenerationResponse $response): void
    {
        try {
            $this->assertValidOutput($response);
        } catch (ScriptGenerationProviderException $e) {
            $this->recordFailure($script, $provider->name(), $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate generated output before it becomes a new version.
     *
     * @throws ScriptGenerationProviderException
     */
    private function assertValidOutput(ScriptGenerationResponse $response): void
    {
        $invalid = $this->failsRequiredChecks($response) || $this->failsLengthChecks($response)
            || ($response->durationSeconds !== null && ($response->durationSeconds < self::MIN_DURATION_SECONDS || $response->durationSeconds > self::MAX_DURATION_SECONDS));

        if ($invalid) {
            throw new ScriptGenerationProviderException(
                'The script generation provider returned invalid script content.',
                ScriptGenerationProviderException::INVALID_RESPONSE
            );
        }
    }

    private function failsRequiredChecks(ScriptGenerationResponse $response): bool
    {
        return $response->hook === '' || $response->body === '';
    }

    private function failsLengthChecks(ScriptGenerationResponse $response): bool
    {
        $total = mb_strlen($response->hook)
            + mb_strlen($response->body)
            + mb_strlen((string) $response->title)
            + mb_strlen((string) $response->closing)
            + mb_strlen((string) $response->notes);

        return mb_strlen($response->hook) > self::MAX_FIELD_LENGTH
            || ($response->title !== null && mb_strlen($response->title) > self::MAX_FIELD_LENGTH)
            || ($response->closing !== null && mb_strlen($response->closing) > self::MAX_FIELD_LENGTH)
            || $total > self::MAX_TOTAL_OUTPUT_LENGTH;
    }

    private function recordFailure(Script $script, string $provider, string $message): void
    {
        AiGeneration::create([
            'script_id' => $script->id,
            'provider' => $provider,
            'model' => null,
            'prompt_profile' => MochyFamiScriptProfile::PROFILE,
            'prompt_version' => MochyFamiScriptProfile::VERSION,
            'source_version_id' => $script->currentVersion?->id,
            'generated_version_id' => null,
            'status' => AiGenerationStatus::Failed,
        ]);

        report($message);
    }
}
