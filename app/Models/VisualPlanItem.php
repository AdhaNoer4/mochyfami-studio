<?php

namespace App\Models;

use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use Database\Factories\VisualPlanItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
