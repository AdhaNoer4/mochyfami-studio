<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'script_id',
        'version',
        'title',
        'hook',
        'body',
        'closing',
        'duration_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class, 'script_id');
    }
}
