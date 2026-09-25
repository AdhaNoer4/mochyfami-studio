<?php

namespace App\Models;

use App\Enums\ResearchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_project_id',
        'status',
        'summary',
        'researched_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ResearchStatus::class,
            'researched_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ContentProject::class, 'content_project_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ResearchClaim::class, 'research_report_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'research_report_id');
    }
}
