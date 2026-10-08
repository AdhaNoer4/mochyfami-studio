<?php

namespace App\Models;

use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use Database\Factories\VisualPlanItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisualPlanItem extends Model
{
    /** @use HasFactory<VisualPlanItemFactory> */
    use HasFactory;

    protected $fillable = [
        'visual_plan_id',
        'order',
        'section',
        'narration_text',
        'visual_type',
        'visual_prompt',
        'duration_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'section' => VisualPlanSection::class,
            'visual_type' => VisualPlanItemType::class,
            'duration_seconds' => 'integer',
        ];
    }

    public function visualPlan(): BelongsTo
    {
        return $this->belongsTo(VisualPlan::class, 'visual_plan_id');
    }

    public function assetRequirements(): HasMany
    {
        return $this->hasMany(AssetRequirement::class, 'visual_plan_item_id');
    }

    /**
     * Every TTS synthesis ever requested for this item, oldest first.
     *
     * A track is a history entry: regeneration never replaces an earlier
     * track, so this relation grows in place and stays fully auditable.
     */
    public function ttsTracks(): HasMany
    {
        return $this->hasMany(TtsTrack::class, 'visual_plan_item_id');
    }
}
