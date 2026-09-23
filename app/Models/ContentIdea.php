<?php

namespace App\Models;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentIdea extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'hook',
        'concept',
        'format',
        'status',
        'priority',
        'notes',
        'source_idea',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'format' => ContentFormat::class,
            'status' => ContentIdeaStatus::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project(): HasOne
    {
        return $this->hasOne(ContentProject::class, 'content_idea_id');
    }
}
