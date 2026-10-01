<?php

namespace App\Models;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use Database\Factories\AssetRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'selected_asset_id',
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

    /**
     * The assets that are candidates for satisfying this requirement.
     *
     * Being in this list means nothing more than "associated". It does not
     * mean the requirement is fulfilled, that this asset was selected, or that
     * the asset passed any production check. Those are separate concepts and
     * this relation is not allowed to stand in for any of them.
     *
     * The pivot table is named explicitly because Laravel's default would sort
     * the two models alphabetically into asset_asset_requirement. The other
     * pivots in this repository are all named parent first, so the name is
     * stated rather than inferred.
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_requirement_asset')->withTimestamps();
    }

    /**
     * The one candidate the user chose, if any.
     *
     * This is a decision, not an association. The assets() pivot above answers
     * "which assets could work", and this answers "which one was chosen", and
     * the two are allowed to disagree: a selected asset may be one of several
     * candidates, and a requirement may have candidates but no selection.
     *
     * Selection also does not mean fulfilled. A requirement becomes fulfilled
     * only by an explicit fulfill action that first re-runs the compatibility
     * checks, because picking the closest candidate is not the same as
     * declaring it good enough.
     *
     * There is no inverse "selectedByRequirements" relation to add here. When
     * the asset is deleted the foreign key nulls and this requirement needs to
     * stop claiming a fulfillment, so AssetService looks requirements up from
     * the asset side directly instead.
     */
    public function selectedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'selected_asset_id');
    }
}
