<?php

namespace App\Services\TTS;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetType;
use App\Enums\ManualDubbingApprovalStatus;
use App\Enums\TtsTrackSource;
use App\Enums\TtsTrackStatus;
use App\Models\ContentProject;
use App\Models\TtsTrack;
use App\Models\User;
use App\Models\VisualPlanItem;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\AssetService;
use App\Services\VisualPlanService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Accepts a human recording for one visual plan item and records it as a
 * manual TtsTrack backed by an ordinary private Asset.
 *
 * The same history model as synthesis applies: every upload writes a new row
 * and nothing is ever overwritten, so replacing a recording means uploading
 * another one and the old track, asset, and file all stay. Approval is kept
 * entirely apart from the lifecycle status: a recording can be a completed,
 * playable file while still waiting for a review decision.
 */
class ManualDubbingService
{
    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected AssetService $assetService,
        protected AssetFileStorageService $fileStorage,
        protected AssetMetadataService $metadata
    ) {}

    /**
     * List the manual dubbing history of one plan item, newest first.
     *
     * Only source = manual tracks appear here; automated synthesis history
     * lives on TtsGenerationService::listTracks() and is filtered the other
     * way. The item's current narration and the asset are eager loaded so
     * staleness and the preview URL never fire a query per row.
     *
     * @return Collection<int, TtsTrack>
     *
     * @throws ModelNotFoundException
     */
    public function listManualTracks(ContentProject $project, int $versionNumber, int $itemId): Collection
    {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        return $item->ttsTracks()
            ->where('source', TtsTrackSource::Manual)
            ->with(['visualPlanItem:id,narration_text', 'asset', 'uploader:id,name', 'approver:id,name'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Store an uploaded recording as a new manual track and a private audio
     * asset on the project's library.
     *
     * The order of operations is the failure story. The asset row is created
     * first so the file has a home; the compatibility check and the write can
     * then refuse or fail without anything half-made pointing at a track.
     * Every failure after the asset exists deletes it again, so a refused or
     * crashed upload never leaves an orphaned asset in the library. A database
     * transaction cannot roll back a filesystem write, so cleanup is explicit
     * rather than delegated to the database.
     *
     * recorded_at is the moment the server received the upload. A recording's
     * true capture time is not recoverable from the file, so the upload time
     * is the honest, server-generated value.
     *
     * provider and voice keep the documented strings 'manual': the columns
     * are NOT NULL, a human source has no synthesis provider, and inventing a
     * schema change for them would touch every automated path for no gain.
     *
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function upload(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        UploadedFile $file,
        ?string $notes,
        User $uploader
    ): TtsTrack {
        $item = $this->resolveItem($project, $versionNumber, $itemId);
        $narration = (string) $item->narration_text;

        $asset = $this->assetService->createAsset($project, [
            'type' => AssetType::Audio,
            'title' => 'Manual dubbing audio',
            'description' => 'A human recording uploaded for this visual plan item.',
            'source_name' => 'manual-dubbing',
        ]);

        try {
            $this->fileStorage->assertCompatible($asset, $file);
            $this->fileStorage->replace($asset, $file);

            $asset = $this->metadata->analyse($asset->fresh() ?? $asset);

            // Only a completed measurement produces a duration, matching the
            // synthesis pipeline: a duration nobody verified is a claim
            // without evidence.
            $duration = $asset->metadata_status === AssetMetadataStatus::Completed
                ? $asset->duration_seconds
                : null;

            return $project->ttsTracks()->create([
                'visual_plan_item_id' => $item->getKey(),
                'asset_id' => $asset->getKey(),
                'provider' => 'manual',
                'model' => null,
                'voice' => 'manual',
                'language' => null,
                'speed' => 1.000,
                // A recording arrives finished: there is no generating phase,
                // so the track is created as a completed lifecycle state and
                // the approval graph carries the review separately.
                'status' => TtsTrackStatus::Completed,
                'duration_seconds' => $duration,
                'narration_snapshot' => $narration,
                'narration_hash' => NarrationHasher::hash($narration),
                'error' => null,
                'source' => TtsTrackSource::Manual,
                'uploader_id' => $uploader->getKey(),
                'recorded_at' => now(),
                'approval_status' => ManualDubbingApprovalStatus::Draft,
                'notes' => $notes,
            ])->fresh();
        } catch (Throwable $e) {
            // The asset now has a row and possibly a file, but no track can
            // ever point at it, so it can only sit there as an orphan.
            // Removing it keeps the library free of half-made uploads.
            $this->assetService->deleteAsset($asset);

            throw $e;
        }
    }

    /**
     * Submit a draft recording for review.
     *
     * @throws InvalidManualApprovalStatusTransitionException
     * @throws ModelNotFoundException
     */
    public function submit(ContentProject $project, int $versionNumber, int $itemId, int $trackId): TtsTrack
    {
        $track = $this->resolveManualTrack($project, $versionNumber, $itemId, $trackId);

        $track->transitionApprovalTo(ManualDubbingApprovalStatus::ReadyForReview);

        return $track->fresh();
    }

    /**
     * Approve a submitted recording and stamp the review provenance.
     *
     * approved_at and approved_by are written here, after the graph has
     * accepted the transition, and never taken from the request: approval
     * metadata is server-side state, so it cannot be forged by a client.
     *
     * @throws InvalidManualApprovalStatusTransitionException
     * @throws ModelNotFoundException
     */
    public function approve(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $trackId,
        User $approver
    ): TtsTrack {
        $track = $this->resolveManualTrack($project, $versionNumber, $itemId, $trackId);

        $track->transitionApprovalTo(ManualDubbingApprovalStatus::Approved);

        $track->forceFill([
            'approved_at' => now(),
            'approved_by' => $approver->getKey(),
        ])->save();

        return $track->fresh();
    }

    /**
     * Reject a submitted recording.
     *
     * The graph only lets a ready-for-review track be rejected, and approval
     * provenance can only be written on the transition into approved, so the
     * columns are already null here. They are cleared explicitly anyway: a
     * rejected track that carried approval metadata would be a contradiction
     * the next reader would have to interpret.
     *
     * @throws InvalidManualApprovalStatusTransitionException
     * @throws ModelNotFoundException
     */
    public function reject(ContentProject $project, int $versionNumber, int $itemId, int $trackId): TtsTrack
    {
        $track = $this->resolveManualTrack($project, $versionNumber, $itemId, $trackId);

        $track->transitionApprovalTo(ManualDubbingApprovalStatus::Rejected);

        $track->forceFill([
            'approved_at' => null,
            'approved_by' => null,
        ])->save();

        return $track->fresh();
    }

    /**
     * Resolve a plan item through the full project -> script -> version ->
     * plan -> item chain, so an item from another project or another version
     * is a 404 rather than a cross-scope write.
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
     * Resolve a manual dubbing track through the same chain, scoped to
     * source = manual, so a track from another item, another project, or an
     * automated TTS track is a 404 rather than something reviewable here.
     *
     * @throws ModelNotFoundException
     */
    private function resolveManualTrack(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $trackId
    ): TtsTrack {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        $track = $item->ttsTracks()
            ->whereKey($trackId)
            ->where('source', TtsTrackSource::Manual)
            ->first();

        if (! $track) {
            throw new ModelNotFoundException('Manual dubbing track not found for this visual plan item.');
        }

        return $track;
    }
}
