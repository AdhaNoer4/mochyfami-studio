<?php

namespace App\Models;

use App\Enums\ContentProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_idea_id',
        'category_id',
        'title',
        'slug',
        'status',
        'priority',
        'target_duration_seconds',
        'language',
        'tone',
        'hook',
        'description',
        'current_step',
        'progress_percent',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentProjectStatus::class,
            'target_duration_seconds' => 'integer',
            'progress_percent' => 'integer',
        ];
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(ContentIdea::class, 'content_idea_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function researchReport(): HasOne
    {
        return $this->hasOne(ResearchReport::class, 'content_project_id');
    }

    public function script(): HasOne
    {
        return $this->hasOne(Script::class, 'content_project_id');
    }
}
