<?php

namespace App\Models;

use App\Enums\ManualDubbingApprovalStatus;
use App\Enums\TtsTrackSource;
use App\Enums\TtsTrackStatus;
use App\Exceptions\InvalidTtsTrackStatusTransitionException;
use Database\Factories\TtsTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TtsTrack extends Model
{
    /** @use HasFactory<TtsTrackFactory> */
    use HasFactory;

    /**
     * Mirrors the column default so a freshly created track answers what it
     * is before anyone refreshes it from the database. Kept in sync with the
     * migration that introduced the source column.
     */
    protected $attributes = [
        'source' => 'tts',
    ];

    /**
     * content_project_id is deliberately absent for the same reason it is on
     * Asset: a track is always created through a project's own relation, which
     * sets the foreign key itself, so leaving it mass assignable would only be
     * a way to move history into somebody else's project.
     */
    protected $fillable = [
        'visual_plan_item_id',
        'asset_id',
        'provider',
        'model',
        'voice',
        'language',
        'speed',
        'status',
        'duration_seconds',
        'narration_snapshot',
        'narration_hash',
        'error',
        'source',
        'uploader_id',
        'recorded_at',
        'approval_status',
        'approved_at',
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => TtsTrackStatus::class,
            'speed' => 'decimal:3',
            'duration_seconds' => 'integer',
            'source' => TtsTrackSource::class,
            'approval_status' => ManualDubbingApprovalStatus::class,
            'recorded_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function contentProject(): BelongsTo
    {
        return $this->belongsTo(ContentProject::class, 'content_project_id');
    }

    public function visualPlanItem(): BelongsTo
    {
        return $this->belongsTo(VisualPlanItem::class, 'visual_plan_item_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Move the track through its state machine, refusing anything the graph
     * does not allow.
     *
     * The graph itself lives on TtsTrackStatus; this is the single spot that
     * enforces it, so a track can never be put into an arbitrary status by
     * calling update() directly instead. The lifecycle owner (the generation
     * service and the archive handler) is the only legitimate caller.
     *
     * @throws InvalidTtsTrackStatusTransitionException
     */
    public function transitionTo(TtsTrackStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new InvalidTtsTrackStatusTransitionException(
                "Cannot transition TTS track from '{$this->status->value}' to '{$target->value}'."
            );
        }

        $this->update(['status' => $target]);
    }
}
