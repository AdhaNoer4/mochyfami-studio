<?php

namespace App\Models;

use App\Enums\VisualPlanStatus;
use Database\Factories\VisualPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisualPlan extends Model
{
    /** @use HasFactory<VisualPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'content_project_id',
        'script_version_id',
        'status',
        'title',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisualPlanStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ContentProject::class, 'content_project_id');
    }

    public function scriptVersion(): BelongsTo
    {
        return $this->belongsTo(ScriptVersion::class, 'script_version_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VisualPlanItem::class)->orderBy('order');
    }
}
