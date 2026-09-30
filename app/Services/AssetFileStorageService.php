<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Exceptions\AssetFileStorageException;
use App\Exceptions\InvalidAssetFileTypeException;
use App\Models\Asset;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AssetFileStorageService
{
    /**
     * The single boundary between the application and the filesystem.
     *
     * Everything that writes or removes an asset file goes through here, so
     * there is exactly one place where a path is built and one place where a
     * stored path is trusted. No controller touches Storage, and no path is
     * ever assembled from request input.
     */
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('assets.disk'));
    }

    /**
     * Maximum accepted upload size in kilobytes, the unit Laravel's file size
     * rules use. Read from configuration so the limit is stated once and the
     * request can enforce it without this service.
     */
    public function maxSizeKb(): int
    {
        return (int) config('assets.upload.max_size_kb');
    }

    /**
     * The MIME types this asset's declared type accepts, mapped to the
     * extension each one is stored under.
     *
     * An asset of a type with no configured formats accepts nothing, which is
     * how the "other" type is kept from becoming a bucket for arbitrary files.
     *
     * @return array<string, string>
     */
    public function acceptedMimesFor(AssetType $type): array
    {
        $mimes = config("assets.upload.mimes.{$type->value}", []);

        return is_array($mimes) ? $mimes : [];
    }

    /**
     * Reject a file the asset's declared type cannot hold.
     *
     * The asset's type is never rewritten to match the file. A mismatch is the
     * user's mistake to correct, and silently retyping an asset would change
     * what the rest of the library says about it.
     *
     * @throws InvalidAssetFileTypeException
     */
    public function assertCompatible(Asset $asset, UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        $accepted = $this->acceptedMimesFor($asset->type);

        if ($this->extensionFor($asset->type, $mimeType) !== null) {
            return;
        }

        if ($accepted === []) {
            throw new InvalidAssetFileTypeException(
                "An asset of type \"{$asset->type->label()}\" does not accept file uploads. "
                .'Change its type to Image, Video, or Audio before uploading a file.'
            );
        }

        throw new InvalidAssetFileTypeException(sprintf(
            'The uploaded file is %s, which a %s asset cannot use. Accepted file types: %s.',
            $mimeType,
            $asset->type->label(),
            implode(', ', array_keys($accepted))
        ));
    }

    /**
     * Store a file for an asset and record where it went.
     *
     * The returned path is built entirely on this side of the request: the
     * project's own id, a fresh UUID, and an extension read out of the
     * configured MIME map. No segment of it comes from the client, and the
     * original filename is kept as metadata only.
     *
     * @return array{path: string, file_name: string, mime_type: string, file_size: int}
     *
     * @throws InvalidAssetFileTypeException
     * @throws AssetFileStorageException
     */
    public function store(Asset $asset, UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();
        $extension = $this->extensionFor($asset->type, $mimeType);

        if ($extension === null) {
            $this->assertCompatible($asset, $file);
        }

        $directory = $this->directoryFor($asset);
        $filename = Str::uuid()->toString().'.'.$extension;

        // The disk is configured with throw => false, so a failed write comes
        // back as a false return rather than an exception. Both are handled:
        // a false return is turned into an exception here, and a disk that is
        // later configured to throw is still caught.
        try {
            $stored = $this->disk()->putFileAs($directory, $file, $filename, [
                'visibility' => 'private',
            ]);
        } catch (Throwable $e) {
            throw new AssetFileStorageException(
                "Unable to store the uploaded file for asset {$asset->id}: {$e->getMessage()}",
                0,
                $e
            );
        }

        if ($stored === false) {
            throw new AssetFileStorageException(
                "Storage reported a failed write for asset {$asset->id} on disk \"{$this->diskName()}\"."
            );
        }

        return [
            'path' => $stored,
            'file_name' => $this->normaliseOriginalName($file->getClientOriginalName(), $filename),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
        ];
    }

    /**
     * Store a replacement file and swap the asset's metadata over to it.
     *
     * The old file is the last thing touched and the only thing whose loss is
     * recoverable: it is still on disk for as long as the new one is being
     * written and the database row is being updated. That ordering is the
     * whole point, because a filesystem and a database cannot be committed
     * together and a half finished replacement has to land on one side or the
     * other rather than in the gap between them.
     *
     * @return array{path: string, file_name: string, mime_type: string, file_size: int}
     *
     * @throws InvalidAssetFileTypeException
     * @throws AssetFileStorageException
     */
    public function replace(Asset $asset, UploadedFile $file): array
    {
        $previousPath = $asset->file_path;

        // Still nothing has changed if this throws: no new file, no database
        // write, and the asset still points at the file it always had.
        $stored = $this->store($asset, $file);

        try {
            $asset->fill([
                'file_path' => $stored['path'],
                'file_name' => $stored['file_name'],
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
            ])->save();
        } catch (Throwable $e) {
            // The new file now has no metadata pointing at it, so it is an
            // orphan with nothing that will ever find it again. It is removed
            // here, and a failure to remove it is reported rather than thrown
            // so that it cannot mask the database error that got us here.
            $this->deleteQuietly($stored['path']);

            throw $e;
        }

        if ($previousPath !== null && $previousPath !== $stored['path']) {
            // The row already points at the new file and has done so
            // successfully, so the old file is now unreachable through the
            // asset that owned it. It is removed on a best effort basis: a
            // failure here leaves a file nothing points at, which is a
            // recoverable mess, whereas refusing to record the upload would
            // leave a correct row pointing at a file that had been deleted.
            $this->deleteQuietly($previousPath);
        }

        return $stored;
    }

    /**
     * Remove a stored file, if there is one and if it is ours to remove.
     *
     * A path that is null or blank means the asset never had a file, which is
     * the normal state of a freshly catalogued asset and not an error.
     *
     * @throws AssetFileStorageException
     */
    public function delete(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        // Defence in depth. A stored path is written only by this class and
        // never comes from a request, so this cannot fire in normal operation.
        // It is here so that a hand edited or corrupted row cannot turn a
        // delete into a way of removing an arbitrary file from the server.
        $prefix = $this->directoryPrefix();

        if (! str_starts_with($path, $prefix)) {
            throw new AssetFileStorageException(
                "Refusing to delete \"{$path}\": it is outside the asset storage directory."
            );
        }

        try {
            if (! $this->disk()->exists($path)) {
                return;
            }

            if (! $this->disk()->delete($path)) {
                throw new AssetFileStorageException("Storage reported a failed delete for \"{$path}\".");
            }
        } catch (AssetFileStorageException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new AssetFileStorageException("Unable to delete \"{$path}\": {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete without letting a storage failure interrupt the caller.
     *
     * Used only where the surrounding work has already succeeded and the file
     * is by definition no longer reachable through any row. Reporting the
     * failure is enough: there is nothing left to roll back to.
     */
    public function deleteQuietly(?string $path): void
    {
        try {
            $this->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Turn a client supplied filename into something safe to store as
     * metadata, falling back to the server's own name when nothing survives.
     *
     * This value is a display string and never a path. It is still worth
     * cleaning: it is rendered back into the UI, it is searchable, and a
     * name carrying directory separators, control characters, or a few
     * hundred kilobytes of padding is not something to hand around.
     */
    public function normaliseOriginalName(string $name, string $fallback): string
    {
        // Both separators are collapsed first so a full path sent by a Windows
        // client is reduced to its last segment, and ".." cannot survive as a
        // component of what is later rendered.
        $name = basename(str_replace('\\', '/', $name));

        // Control characters, including the NUL byte, have no place in a name
        // that ends up in a label, a log line, and a search query.
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));

        if ($name === '' || $name === '.' || $name === '..') {
            return $fallback;
        }

        // The column is a varchar(255). Truncating to characters rather than
        // bytes keeps a multi byte name from being cut mid character.
        return mb_substr($name, 0, 255);
    }

    /**
     * Project scoped directory for one asset's files.
     */
    private function directoryFor(Asset $asset): string
    {
        return $this->directoryPrefix().$asset->content_project_id;
    }

    /**
     * The configured root directory plus a trailing separator, which is also
     * the prefix every stored path has to start with.
     */
    private function directoryPrefix(): string
    {
        return rtrim(trim((string) config('assets.upload.directory'), '/'), '/').'/';
    }

    private function extensionFor(AssetType $type, string $mimeType): ?string
    {
        return $this->acceptedMimesFor($type)[$mimeType] ?? null;
    }

    private function diskName(): string
    {
        return (string) config('assets.disk');
    }
}
