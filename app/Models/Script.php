<?php

namespace App\Models;

use App\Enums\ScriptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Script extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_project_id',
        'status',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScriptStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ContentProject::class, 'content_project_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ScriptVersion::class, 'script_id');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(ScriptVersion::class, 'id', 'current_version_id');
    }
}
