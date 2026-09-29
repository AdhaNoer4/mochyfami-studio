<?php

namespace App\Services;

use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Enums\VisualPlanStatus;
use App\Exceptions\DuplicateVisualPlanException;
use App\Exceptions\DuplicateVisualPlanItemOrderException;
use App\Exceptions\InvalidVisualPlanStatusTransitionException;
use App\Exceptions\VisualPlanNotReadyException;
use App\Models\ContentProject;
use App\Models\ScriptVersion;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\Script\ScriptQualityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VisualPlanService
{
    /**
     * Safe default planned visual duration used when seeding items from a
     * script. It is a starting value, not an audio/TTS estimate.
     */
    public const DEFAULT_ITEM_DURATION_SECONDS = 5;

    /**
     * Neutral placeholder used for the required visual_prompt when items are
     * seeded from script text. The UI lets the user edit it immediately.
     */
    public const PLACEHOLDER_VISUAL_PROMPT = 'Define visual for this narration.';

    /**
     * Temporary shift used during reorder two-pass writes. Because order is
     * unsigned, orders are moved to a high positive band instead of becoming
     * negative while in-place swaps resolve the (visual_plan_id, order) unique
     * constraint.
     */
    private const REORDER_TEMP_OFFSET = 1_000_000;

    public function __construct(
        protected ScriptService $scriptService,
        protected ScriptQualityService $qualityService
    ) {}

    /**
     * Get the visual plan bound to an exact script version, if it exists.
     *
     * A missing script or version is a 404 condition; a missing plan is
     * simply "no plan yet".
     *
     * @throws ModelNotFoundException
     */
    public function getPlan(ContentProject $project, int $versionNumber): ?VisualPlan
    {
        $version = $this->resolveVersion($project, $versionNumber);

        return $version->visualPlan()
            ->with(['scriptVersion', 'items'])
            ->first();
    }

    /**
     * Get the visual plan bound to an exact script version or fail.
     *
     * @throws ModelNotFoundException
     */
    public function getPlanOrFail(ContentProject $project, int $versionNumber): VisualPlan
    {
        $plan = $this->getPlan($project, $versionNumber);

        if (! $plan) {
            throw new ModelNotFoundException('Visual plan not found for this script version.');
        }

        return $plan;
    }

    /**
     * Create an empty draft visual plan for an explicit script version.
     *
     * The version must belong to the project's script, and the script must
     * pass the existing readiness gate. Duplicate plans for the same
     * project + version are rejected.
     *
     * @throws DuplicateVisualPlanException
     * @throws ModelNotFoundException
     * @throws VisualPlanNotReadyException
     */
    public function createVisualPlan(ContentProject $project, int $versionNumber, array $data): VisualPlan
    {
        return DB::transaction(function () use ($project, $versionNumber, $data) {
            $version = $this->resolveVersion($project, $versionNumber);

            if ($version->visualPlan()->exists()) {
                throw new DuplicateVisualPlanException('A visual plan already exists for this script version.');
            }

            $this->assertScriptReady($project);

            return VisualPlan::create([
                'content_project_id' => $project->id,
                'script_version_id' => $version->id,
                'status' => VisualPlanStatus::Draft,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Create a visual plan pre-populated from the script version content.
     *
     * Does NOT invent visual descriptions and never calls AI: one editable
     * planning row per non-empty script section (hook/body/closing), with a
     * neutral placeholder prompt and a safe default duration.
     */
    public function createVisualPlanFromScript(ContentProject $project, int $versionNumber, array $data = []): VisualPlan
    {
        return DB::transaction(function () use ($project, $versionNumber, $data) {
            $plan = $this->createVisualPlan($project, $versionNumber, $data);

            $this->seedPlanFromScript($plan);

            return $plan;
        });
    }

    /**
     * List the plan items, always ordered by their explicit order field.
     *
     * @throws ModelNotFoundException
     */
    public function listItems(ContentProject $project, int $versionNumber): Collection
    {
        $plan = $this->getPlanOrFail($project, $versionNumber);

        return $plan->items()->with('assetRequirements')->get();
    }

    /**
     * Append an item to the plan.
     *
     * The order defaults to max(existing) + 1 when not provided. A provided
     * order that is already used is rejected.
     *
     * @throws DuplicateVisualPlanItemOrderException
     * @throws ModelNotFoundException
     */
    public function createItem(ContentProject $project, int $versionNumber, array $data): VisualPlanItem
    {
        $plan = $this->getPlanOrFail($project, $versionNumber);

        $order = $data['order'] ?? ((int) $plan->items()->max('order') + 1);

        $this->assertOrderAvailable($plan, $order);

        return $plan->items()->create([
            'order' => $order,
            'section' => $data['section'],
            'narration_text' => $data['narration_text'],
            'visual_type' => $data['visual_type'],
            'visual_prompt' => $data['visual_prompt'],
            'duration_seconds' => $data['duration_seconds'],
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update an item that belongs to the plan of the given version.
     *
     * @throws DuplicateVisualPlanItemOrderException
     * @throws ModelNotFoundException
     */
    public function updateItem(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        array $data
    ): VisualPlanItem {
        return DB::transaction(function () use ($project, $versionNumber, $itemId, $data) {
            $plan = $this->getPlanOrFail($project, $versionNumber);

            $item = $this->findItem($plan, $itemId);

            $order = $data['order'] ?? $item->order;

            if ($order !== $item->order) {
                $this->assertOrderAvailable($plan, $order, $itemId);
            }

            $item->fill([
                'order' => $order,
                'section' => $data['section'] ?? $item->section->value,
                'narration_text' => $data['narration_text'] ?? $item->narration_text,
                'visual_type' => $data['visual_type'] ?? $item->visual_type->value,
                'visual_prompt' => $data['visual_prompt'] ?? $item->visual_prompt,
                'duration_seconds' => $data['duration_seconds'] ?? $item->duration_seconds,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $item->notes,
            ])->save();

            return $item->fresh();
        });
    }

    /**
     * Delete an item that belongs to the plan of the given version.
     *
     * @throws ModelNotFoundException
     */
    public function deleteItem(ContentProject $project, int $versionNumber, int $itemId): void
    {
        $plan = $this->getPlanOrFail($project, $versionNumber);

        $this->findItem($plan, $itemId)->delete();
    }

    /**
     * Deterministically reorder every item of the plan.
     *
     * All submitted item IDs must belong to the given plan, must cover the
     * whole plan with no duplicates, and the orders must form a contiguous
     * sequence 1..n. Runs inside a transaction so a failure rolls back every
     * change.
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    public function reorderItems(ContentProject $project, int $versionNumber, array $items): void
    {
        DB::transaction(function () use ($project, $versionNumber, $items) {
            $plan = $this->getPlanOrFail($project, $versionNumber);

            $ids = array_column($items, 'id');
            $orders = array_column($items, 'order');

            if (count($ids) !== $plan->items()->count()) {
                throw new InvalidArgumentException('Every visual plan item must be supplied exactly once.');
            }

            if (count($ids) !== count(array_unique($ids))) {
                throw new InvalidArgumentException('Every visual plan item must be supplied exactly once.');
            }

            $existing = $plan->items()->whereKey($ids)->get();

            if ($existing->count() !== count($ids)) {
                throw new InvalidArgumentException('Every item must belong to this visual plan.');
            }

            if (count($orders) !== count(array_unique($orders))) {
                throw new InvalidArgumentException('Item orders must be unique.');
            }

            sort($orders);

            if ($orders !== range(1, count($orders))) {
                throw new InvalidArgumentException('Item orders must form a contiguous sequence starting at 1.');
            }

            // Shift existing orders out of the way so in-place swaps cannot
            // violate the (visual_plan_id, order) uniqueness constraint.
            foreach ($existing as $item) {
                $item->update(['order' => (int) $item->order + self::REORDER_TEMP_OFFSET]);
            }

            foreach ($items as $input) {
                $existing->firstWhere('id', $input['id'])->update(['order' => $input['order']]);
            }
        });
    }

    /**
     * Transition the visual plan status through the workflow.
     *
     * @throws InvalidVisualPlanStatusTransitionException
     */
    public function transitionStatus(VisualPlan $plan, VisualPlanStatus $target): VisualPlan
    {
        if (! $plan->status->canTransitionTo($target)) {
            throw new InvalidVisualPlanStatusTransitionException(
                "Cannot transition visual plan status from '{$plan->status->value}' to '{$target->value}'."
            );
        }

        $plan->update(['status' => $target]);

        return $plan->fresh(['scriptVersion', 'items']);
    }

    private function resolveVersion(ContentProject $project, int $versionNumber): ScriptVersion
    {
        $script = $this->scriptService->getScriptOrFail($project);

        return $this->scriptService->findVersion($script, $versionNumber);
    }

    private function findItem(VisualPlan $plan, int $itemId): VisualPlanItem
    {
        $item = $plan->items()->whereKey($itemId)->first();

        if (! $item) {
            throw new ModelNotFoundException('Visual plan item not found for this script version.');
        }

        return $item;
    }

    private function assertOrderAvailable(VisualPlan $plan, int $order, ?int $exceptItemId = null): void
    {
        $query = $plan->items()->where('order', $order);

        if ($exceptItemId !== null) {
            $query->whereKeyNot($exceptItemId);
        }

        if ($query->exists()) {
            throw new DuplicateVisualPlanItemOrderException(
                "Order {$order} is already used within this visual plan."
            );
        }
    }

    /**
     * Reuse the existing script quality architecture as a read-only gate.
     *
     * @throws VisualPlanNotReadyException
     */
    private function assertScriptReady(ContentProject $project): void
    {
        $script = $this->scriptService->getScriptOrFail($project);

        if (! $this->qualityService->evaluateScript($script)['ready']) {
            throw new VisualPlanNotReadyException(
                'The script is not ready for a visual plan yet. It must be reviewable and pass the quality gate.'
            );
        }
    }

    private function seedPlanFromScript(VisualPlan $plan): void
    {
        $version = $plan->scriptVersion()->first();

        $sections = [
            VisualPlanSection::Hook->value => $version->hook,
            VisualPlanSection::Body->value => $version->body,
            VisualPlanSection::Closing->value => $version->closing,
        ];

        $order = 1;

        foreach ($sections as $section => $narrationText) {
            if (trim((string) $narrationText) === '') {
                continue;
            }

            $plan->items()->create([
                'order' => $order++,
                'section' => $section,
                'narration_text' => $narrationText,
                'visual_type' => VisualPlanItemType::Other->value,
                'visual_prompt' => self::PLACEHOLDER_VISUAL_PROMPT,
                'duration_seconds' => self::DEFAULT_ITEM_DURATION_SECONDS,
                'notes' => 'Placeholder visual: define the visual for this narration.',
            ]);
        }
    }
}
