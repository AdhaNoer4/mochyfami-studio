<?php

namespace App\Models;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use Database\Factories\AssetRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRequirement extends Model
{
    /** @use HasFactory<AssetRequirementFactory> */
    use HasFactory;

    protected $fillable = [
        'visual_plan_item_id',
        'requirement_type',
        'search_query',
        'description',
        'target_duration_seconds',
        'aspect_ratio',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requirement_type' => AssetRequirementType::class,
            'aspect_ratio' => AssetRequirementAspectRatio::class,
            'status' => AssetRequirementStatus::class,
            'target_duration_seconds' => 'integer',
        ];
    }

    public function visualPlanItem(): BelongsTo
    {
        return $this->belongsTo(VisualPlanItem::class, 'visual_plan_item_id');
    }
}
