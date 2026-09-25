<?php

namespace App\Models;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_report_id',
        'claim',
        'status',
        'importance',
    ];

    protected function casts(): array
    {
        return [
            'status' => ResearchClaimStatus::class,
            'importance' => ResearchClaimImportance::class,
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ResearchReport::class, 'research_report_id');
    }
}
