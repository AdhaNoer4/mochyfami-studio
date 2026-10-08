<?php

namespace App\Models;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     *
     * aspect_ratio, metadata_status, metadata_error, and metadata_analyzed_at
     * are absent for the same reason, and for a stronger one: they describe
     * the file rather than the asset, so they are only ever written by
     * inspection of a stored file. Leaving them fillable would let a metadata
     * request claim a video is 1920x1080 without anyone having opened it.
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
            'metadata_status' => AssetMetadataStatus::class,
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            // decimal:4 keeps the value in the resource as a number rather
            // than the "1.7778" string PDO would otherwise hand back, so the
            // frontend can compare and format it without parsing.
            'aspect_ratio' => 'decimal:4',
            'metadata_analyzed_at' => 'datetime',
        ];
    }

    /**
     * True once a file is attached and analysis has not yet produced metadata.
     *
     * This is the stale case: the asset has a file, and whatever dimensions or
     * duration it shows describe an older file or nothing at all.
     */
    public function hasStaleMetadata(): bool
    {
        return $this->file_path !== null
            && $this->metadata_status !== AssetMetadataStatus::Completed;
    }

    /**
     * True when the asset claims a file that is not on the disk.
     *
     * Only the library can actually answer this, since it needs the disk, but
     * the row already carries enough information for the UI to say "this looks
     * wrong" without another request.
     */
    public function hasFileAttached(): bool
    {
        return $this->file_path !== null && trim($this->file_path) !== '';
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

    /**
     * The TTS tracks this asset is the stored audio of.
     *
     * A track can be archived while its asset lives on, so an asset keeps the
     * relation even after every track that pointed at it has moved into its
     * terminal state. The relation is what separates a TTS-generated audio
     * from an ordinary audio upload: the two are indistinguishable by type.
     */
    public function ttsTracks(): HasMany
    {
        return $this->hasMany(TtsTrack::class, 'asset_id');
    }

    /**
     * Whether this asset is the audio of at least one TTS track.
     *
     * Shared by the asset requirement guards, so the definition of "TTS
     * asset" lives in one place: an asset is TTS-generated because a TtsTrack
     * references it, never because its type happens to be audio.
     */
    public function isTtsGenerated(): bool
    {
        return $this->ttsTracks()->exists();
    }
}
