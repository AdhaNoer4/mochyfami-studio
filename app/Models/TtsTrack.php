<?php

namespace App\Models;

use App\Enums\TtsTrackStatus;
use Database\Factories\TtsTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TtsTrack extends Model
{
    /** @use HasFactory<TtsTrackFactory> */
    use HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'status' => TtsTrackStatus::class,
            'speed' => 'decimal:3',
            'duration_seconds' => 'integer',
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
}
