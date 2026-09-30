<?php

namespace App\Services;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Exceptions\DuplicateAssetRequirementAssetException;
use App\Exceptions\InvalidAssetRequirementStatusTransitionException;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\VisualPlanItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AssetRequirementService
{
    /**
     * The generated search query is the plan's own visual prompt, cleaned up.
     * It is never rewritten by a model: no NLP, no keyword extraction, no
     * provider call. A 255 character cap matches the column and a visible
     * prompt reads better than a truncated one.
     */
    private const MAX_SEARCH_QUERY_LENGTH = 255;

    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected AssetService $assetService
    ) {}

    /**
     * List the requirements of one plan item.
     *
     * @throws ModelNotFoundException
     */
    public function listRequirements(ContentProject $project, int $versionNumber, int $itemId): Collection
    {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        return $item->assetRequirements()->orderBy('id')->get();
    }

    /**
     * Add a single requirement to a plan item.
     *
     * The status is always pending: it describes the asset pipeline, not user
     * intent, so it is decided server-side and never accepted from the client.
     *
     * @throws ModelNotFoundException
     */
    public function createRequirement(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        array $data
    ): AssetRequirement {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        return $item->assetRequirements()->create([
            'requirement_type' => $data['requirement_type'],
            'search_query' => $data['search_query'] ?? null,
            'description' => $data['description'],
            'target_duration_seconds' => $data['target_duration_seconds'] ?? null,
            'aspect_ratio' => $data['aspect_ratio'] ?? null,
            'status' => AssetRequirementStatus::Pending,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update a requirement without ever touching its status; the dedicated
     * transition endpoint owns status changes.
     *
     * @throws ModelNotFoundException
     */
    public function updateRequirement(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId,
        array $data
    ): AssetRequirement {
        $requirement = $this->findRequirement($project, $versionNumber, $itemId, $requirementId);

        $requirement->fill([
            'requirement_type' => $data['requirement_type'] ?? $requirement->requirement_type->value,
            'search_query' => array_key_exists('search_query', $data) ? $data['search_query'] : $requirement->search_query,
            'description' => $data['description'] ?? $requirement->description,
            'target_duration_seconds' => array_key_exists('target_duration_seconds', $data)
                ? $data['target_duration_seconds']
                : $requirement->target_duration_seconds,
            'aspect_ratio' => array_key_exists('aspect_ratio', $data) ? $data['aspect_ratio'] : $requirement->aspect_ratio,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $requirement->notes,
        ])->save();

        return $requirement->fresh();
    }

    /**
     * Delete a requirement.
     *
     * @throws ModelNotFoundException
     */
    public function deleteRequirement(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId
    ): void {
        $this->findRequirement($project, $versionNumber, $itemId, $requirementId)->delete();
    }

    /**
     * Deterministically create a pending requirement for every plan item that
     * has none yet, without calling AI, any provider, or the network.
     *
     * An item that already has any requirement is left untouched, which makes
     * the whole operation idempotent and guarantees user edits are preserved.
     * Either every item is processed or none is, so a partial generation can
     * never be left behind.
     *
     * @return array{created: int, existing: int, skipped: int}
     *
     * @throws ModelNotFoundException
     */
    public function generateFromVisualPlan(ContentProject $project, int $versionNumber): array
    {
        $plan = $this->visualPlanService->getPlanOrFail($project, $versionNumber);

        return DB::transaction(function () use ($plan) {
            $created = 0;
            $existing = 0;
            $skipped = 0;

            foreach ($plan->items()->orderBy('order')->get() as $item) {
                if ($item->assetRequirements()->exists()) {
                    $existing++;

                    continue;
                }

                if (trim($item->narration_text) === '' && trim($item->visual_prompt) === '') {
                    $skipped++;

                    continue;
                }

                $item->assetRequirements()->create([
                    'requirement_type' => AssetRequirementType::fromVisualType($item->visual_type),
                    'search_query' => $this->deriveSearchQuery($item),
                    'description' => $item->visual_prompt,
                    'target_duration_seconds' => $item->duration_seconds,
                    'aspect_ratio' => AssetRequirementAspectRatio::DEFAULT,
                    'status' => AssetRequirementStatus::Pending,
                    'notes' => null,
                ]);

                $created++;
            }

            return [
                'created' => $created,
                'existing' => $existing,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Move a requirement along the status pipeline.
     *
     * The allowed transitions live on the enum, so the graph is defined once
     * and shared by the API, the resource payload, and the UI.
     *
     * @throws InvalidAssetRequirementStatusTransitionException
     * @throws ModelNotFoundException
     */
    public function transitionStatus(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId,
        AssetRequirementStatus $target
    ): AssetRequirement {
        $requirement = $this->findRequirement($project, $versionNumber, $itemId, $requirementId);

        if (! $requirement->status->canTransitionTo($target)) {
            throw new InvalidAssetRequirementStatusTransitionException(
                "Cannot transition asset requirement from '{$requirement->status->value}' to '{$target->value}'."
            );
        }

        $requirement->update(['status' => $target]);

        return $requirement->fresh();
    }

    /**
     * List the assets associated with one requirement.
     *
     * This is the requirement's own candidate list, not the project's asset
     * library: only assets attached to this specific requirement come back.
     *
     * The query is started from the project's own assets relation and then
     * narrowed to the requirement, rather than from the requirement's relation
     * alone. A pivot row that somehow paired this requirement with another
     * project's asset would otherwise be followed straight through and hand
     * back that project's metadata, so the project constraint has to be part of
     * the query's construction instead of something checked afterwards.
     *
     * @throws ModelNotFoundException
     */
    public function listRequirementAssets(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId
    ): Collection {
        $requirement = $this->findRequirement($project, $versionNumber, $itemId, $requirementId);

        return $project->assets()
            ->whereHas('assetRequirements', fn ($query) => $query->whereKey($requirement->getKey()))
            ->orderBy('assets.id')
            ->get();
    }

    /**
     * Associate an existing asset with a requirement as a candidate.
     *
     * Both sides are resolved inside the requested project before anything is
     * written: the requirement through the project -> script version -> plan ->
     * item chain, the asset through the project's own assets relation. An
     * asset belonging to a different project is unreachable from here rather
     * than rejected afterwards, so a valid asset id from somewhere else fails
     * exactly like an id that does not exist at all.
     *
     * Nothing but the pivot row is written. The requirement status and the
     * asset status are both left exactly as they were: attaching records a
     * candidate, and a candidate is not a fulfillment, a selection, or an
     * approval.
     *
     * @throws DuplicateAssetRequirementAssetException
     * @throws ModelNotFoundException
     */
    public function attachAsset(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId,
        int $assetId
    ): Asset {
        $requirement = $this->findRequirement($project, $versionNumber, $itemId, $requirementId);
        $asset = $this->assetService->findAsset($project, $assetId);

        if ($requirement->assets()->whereKey($asset->getKey())->exists()) {
            throw new DuplicateAssetRequirementAssetException(
                'This asset is already associated with the asset requirement.'
            );
        }

        $requirement->assets()->attach($asset->getKey());

        return $asset;
    }

    /**
     * Remove an asset from a requirement's candidates.
     *
     * Detaching an asset that was never associated is a 404 rather than a
     * quiet success, so a client that removed the wrong row is told so instead
     * of being handed a false confirmation. Only the pivot row goes away:
     * neither the asset nor the requirement is deleted.
     *
     * @throws ModelNotFoundException
     */
    public function detachAsset(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId,
        int $assetId
    ): void {
        $requirement = $this->findRequirement($project, $versionNumber, $itemId, $requirementId);
        $asset = $this->assetService->findAsset($project, $assetId);

        if (! $requirement->assets()->whereKey($asset->getKey())->exists()) {
            throw new ModelNotFoundException('Asset is not associated with this asset requirement.');
        }

        $requirement->assets()->detach($asset->getKey());
    }

    /**
     * Resolve a plan item through the full project -> script -> version -> plan
     * -> item chain, so an item from another project or another version is a
     * 404 rather than a cross-scope write.
     *
     * @throws ModelNotFoundException
     */
    private function resolveItem(ContentProject $project, int $versionNumber, int $itemId): VisualPlanItem
    {
        $plan = $this->visualPlanService->getPlanOrFail($project, $versionNumber);

        $item = $plan->items()->whereKey($itemId)->first();

        if (! $item) {
            throw new ModelNotFoundException('Visual plan item not found for this script version.');
        }

        return $item;
    }

    /**
     * @throws ModelNotFoundException
     */
    private function findRequirement(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId
    ): AssetRequirement {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        $requirement = $item->assetRequirements()->whereKey($requirementId)->first();

        if (! $requirement) {
            throw new ModelNotFoundException('Asset requirement not found for this visual plan item.');
        }

        return $requirement;
    }

    /**
     * Reuse the plan's visual prompt as the search query.
     *
     * The seeded placeholder prompt is not a real visual, so it becomes no
     * query at all rather than a search for the words "Define visual for this
     * narration".
     */
    private function deriveSearchQuery(VisualPlanItem $item): ?string
    {
        $prompt = trim($item->visual_prompt);

        if ($prompt === '' || $prompt === VisualPlanService::PLACEHOLDER_VISUAL_PROMPT) {
            return null;
        }

        return mb_substr($prompt, 0, self::MAX_SEARCH_QUERY_LENGTH);
    }
}
