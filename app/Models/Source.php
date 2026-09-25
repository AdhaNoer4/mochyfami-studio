<?php

namespace App\Models;

use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_report_id',
        'title',
        'url',
        'domain',
        'source_type',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'published_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ResearchReport::class, 'research_report_id');
    }

    protected static function booted(): void
    {
        static::saving(function (Source $source) {
            if (! filter_var($source->url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Invalid source URL.');
            }
        });
    }
}
