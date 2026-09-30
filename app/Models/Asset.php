<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    /**
     * content_project_id is deliberately absent. An asset is always created
     * through a project's own relation, which sets the foreign key itself, so
     * leaving it mass assignable would only ever be a way to move an asset
     * into somebody else's project.
     *
     * file_path is fillable so a later part can record where a stored file
     * lives, but it is never read from a request and never exposed through
     * the API resource: it is a server side location, not client facing
     * metadata.
     */
    protected $fillable = [
        'type',
        'status',
        'title',
        'description',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'duration_seconds',
        'width',
        'height',
        'source_url',
        'source_name',
        'license_type',
        'attribution',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'status' => AssetStatus::class,
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ContentProject::class, 'content_project_id');
    }
}
