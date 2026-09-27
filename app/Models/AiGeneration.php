<?php

namespace App\Models;

use App\Enums\AiGenerationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'script_id',
        'provider',
        'model',
        'prompt_profile',
        'prompt_version',
        'source_version_id',
        'generated_version_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiGenerationStatus::class,
        ];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class, 'script_id');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(ScriptVersion::class, 'source_version_id');
    }

    public function generatedVersion(): BelongsTo
    {
        return $this->belongsTo(ScriptVersion::class, 'generated_version_id');
    }
}
