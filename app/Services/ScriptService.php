<?php

namespace App\Services;

use App\Enums\ScriptStatus;
use App\Exceptions\DuplicateScriptException;
use App\Exceptions\InvalidScriptStatusTransitionException;
use App\Exceptions\ScriptNotReadyForCreationException;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\ScriptVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ScriptService
{
    public function __construct(
        protected ResearchQualityService $qualityService
    ) {}

    /**
     * Get the project script with its current version, if present.
     */
    public function getScript(ContentProject $project): ?Script
    {
        return $project->script()
            ->with(['project', 'currentVersion'])
            ->withCount('versions as version_count')
            ->first();
    }

    /**
     * Get the project script or fail when missing.
     *
     * @throws ModelNotFoundException
     */
    public function getScriptOrFail(ContentProject $project): Script
    {
        $script = $this->getScript($project);

        if (! $script) {
            throw new ModelNotFoundException('Script not found for this project.');
        }

        return $script;
    }

    /**
     * Create the project Script aggregate with its initial version.
     *
     * Requires research readiness: a research report must exist and its
     * quality gate must be ready before a script may be created.
     *
     * @throws DuplicateScriptException
     * @throws ScriptNotReadyForCreationException
     */
    public function createScript(ContentProject $project, array $data): Script
    {
        return DB::transaction(function () use ($project, $data) {
            if ($project->script()->exists()) {
                throw new DuplicateScriptException('This project already has a script.');
            }

            $this->assertResearchReady($project);

            $script = $project->script()->create(['status' => ScriptStatus::Draft]);

            $version = $script->versions()->create([
                ...$data,
                'version' => 1,
            ]);

            $script->update(['current_version_id' => $version->id]);

            return $this->freshScript($script);
        });
    }

    /**
     * List the script versions ordered deterministically by version number.
     *
     * @throws ModelNotFoundException
     */
    public function getVersions(ContentProject $project): Collection
    {
        return $this->listVersions($this->getScriptOrFail($project));
    }

    /**
     * Get a specific script version by its version number.
     *
     * @throws ModelNotFoundException
     */
    public function getVersion(ContentProject $project, int $versionNumber): ScriptVersion
    {
        return $this->findVersion($this->getScriptOrFail($project), $versionNumber);
    }

    /**
     * List versions for an already-resolved script.
     */
    public function listVersions(Script $script): Collection
    {
        return $script->versions()
            ->orderBy('version')
            ->get();
    }

    /**
     * Find a version for an already-resolved script.
     *
     * @throws ModelNotFoundException
     */
    public function findVersion(Script $script, int $versionNumber): ScriptVersion
    {
        $version = $script->versions()->where('version', $versionNumber)->first();

        if (! $version) {
            throw new ModelNotFoundException('Script version not found for this project.');
        }

        return $version;
    }

    /**
     * Append the next script version and make it the current one.
     *
     * The version number is always generated server-side; the highest
     * existing version plus one. Previous versions stay untouched.
     *
     * @throws ModelNotFoundException
     */
    public function createVersion(ContentProject $project, array $data): Script
    {
        return DB::transaction(function () use ($project, $data) {
            $script = $this->getScriptOrFail($project);

            $version = $script->versions()->create([
                ...$data,
                'version' => (int) $script->versions()->max('version') + 1,
            ]);

            $script->update(['current_version_id' => $version->id]);

            return $this->freshScript($script);
        });
    }

    /**
     * Edit the current version by creating a new version from its content.
     *
     * The current version is immutable; editing produces a NEW version whose
     * unspecified fields inherit the values of the edited version.
     *
     * @throws ModelNotFoundException
     */
    public function updateCurrentVersion(ContentProject $project, array $data): Script
    {
        return DB::transaction(function () use ($project, $data) {
            $script = $this->getScriptOrFail($project);

            $current = $script->currentVersion;

            if (! $current) {
                throw new ModelNotFoundException('Script version not found for this project.');
            }

            $merged = array_merge([
                'title' => $current->title,
                'hook' => $current->hook,
                'body' => $current->body,
                'closing' => $current->closing,
                'duration_seconds' => $current->duration_seconds,
                'notes' => $current->notes,
            ], $data);

            $version = $script->versions()->create([
                ...$merged,
                'version' => (int) $script->versions()->max('version') + 1,
            ]);

            $script->update(['current_version_id' => $version->id]);

            return $this->freshScript($script);
        });
    }

    /**
     * Fields the revision workflow may replace. Everything else is rejected
     * or inherited from the source version.
     */
    public const REVISION_EDITABLE_FIELDS = [
        'hook' => 'hook',
        'body' => 'body',
        'closing' => 'closing',
    ];

    /**
     * Create a NEW script version from an explicit source version.
     *
     * Revision is the safe, explicit editing workflow: the source version is
     * any version of the script (current or historical/archived) and is never
     * mutated. Only the editable fields passed in $changes are replaced; all
     * other content fields are inherited from the source. The version number
     * is always max(existing) + 1, the new version becomes current, and the
     * script is forced back to draft.
     *
     * Traceability mappings are NEVER copied: a new version is a new document
     * and starts with zero mapped claims.
     *
     * @throws ModelNotFoundException
     */
    public function createRevision(ContentProject $project, int $sourceVersionNumber, array $changes): Script
    {
        return DB::transaction(function () use ($project, $sourceVersionNumber, $changes) {
            $script = $this->getScriptOrFail($project);

            $source = $this->findVersion($script, $sourceVersionNumber);

            $edits = array_intersect_key($changes, self::REVISION_EDITABLE_FIELDS);

            $merged = array_merge([
                'title' => $source->title,
                'hook' => $source->hook,
                'body' => $source->body,
                'closing' => $source->closing,
                'duration_seconds' => $source->duration_seconds,
                'notes' => $source->notes,
            ], $edits);

            $version = $script->versions()->create([
                ...$merged,
                'version' => (int) $script->versions()->max('version') + 1,
            ]);

            $script->update([
                'current_version_id' => $version->id,
                'status' => ScriptStatus::Draft,
            ]);

            return $this->freshScript($script);
        });
    }

    /**
     * Transition the script status through the workflow.
     *
     * @throws InvalidScriptStatusTransitionException
     * @throws ModelNotFoundException
     */
    public function transitionStatus(ContentProject $project, ScriptStatus $target): Script
    {
        $script = $this->getScriptOrFail($project);

        if (! $script->status->canTransitionTo($target)) {
            throw new InvalidScriptStatusTransitionException(
                "Cannot transition script status from '{$script->status->value}' to '{$target->value}'."
            );
        }

        $script->update(['status' => $target]);

        return $this->freshScript($script);
    }

    /**
     * Reload a script with its relationships and version count.
     */
    private function freshScript(Script $script): Script
    {
        return $script
            ->fresh(['project', 'currentVersion'])
            ->loadCount('versions as version_count');
    }

    /**
     * Ensure research is ready before a script may be created.
     *
     * @throws ScriptNotReadyForCreationException
     */
    private function assertResearchReady(ContentProject $project): void
    {
        $report = $project->researchReport()->first();

        if (! $report || ! $this->qualityService->evaluateReport($report)['ready']) {
            throw new ScriptNotReadyForCreationException('Research must be ready before creating a script.');
        }
    }
}
