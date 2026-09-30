<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * The requirements this asset is a candidate for.
     *
     * An asset can be a candidate for several requirements at once, which is
     * why this is many-to-many and not hasMany. Membership here carries no
     * status: neither this asset nor any of these requirements is advanced,
     * approved, or marked fulfilled by being associated.
     *
     * The pivot table is named explicitly, for the same reason as on the other
     * side of the relation: see AssetRequirement::assets().
     */
    public function assetRequirements(): BelongsToMany
    {
        return $this->belongsToMany(AssetRequirement::class, 'asset_requirement_asset')->withTimestamps();
    }
}
