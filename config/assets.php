<?php

use App\Enums\AssetType;

return [
    /*
    |--------------------------------------------------------------------------
    | Asset File Disk
    |--------------------------------------------------------------------------
    |
    | The disk uploaded asset files are written to. It is a private local disk
    | and must stay one: there is no public URL for an uploaded file, and no
    | part of this application is allowed to publish one.
    |
    */

    'disk' => 'assets',

    'upload' => [

        /*
        |--------------------------------------------------------------------------
        | Maximum Upload Size
        |--------------------------------------------------------------------------
        |
        | Hard ceiling for one uploaded file, in kilobytes, which is the unit
        | Laravel's file size rules use. This is the limit that actually
        | applies in practice: the application's own 512 MB ceiling is reached
        | long before PHP's upload_max_filesize and post_max_size, which sit at
        | 2G in development.
        |
        | Raising this number past the server's limits does not make bigger
        | uploads work, it only makes the error message wrong. A request that
        | exceeds post_max_size is discarded by PHP before it reaches Laravel,
        | so it arrives with no file at all and is reported as a missing file
        | rather than as an oversized one. Keep this at or below the server's
        | effective post_max_size, and raise the server value deliberately if
        | that ever needs to change; this config never does it automatically.
        |
        */

        'max_size_kb' => 524288,

        /*
        |--------------------------------------------------------------------------
        | Storage Directory
        |--------------------------------------------------------------------------
        |
        | Root directory for stored files, relative to the disk root. A stored
        | path is always this directory, then the project id, then a server
        | generated filename. No part of a path is ever taken from a request.
        |
        */

        'directory' => 'assets',

        /*
        |--------------------------------------------------------------------------
        | Allowed MIME Types Per Asset Type
        |--------------------------------------------------------------------------
        |
        | The single source of truth for which file may be uploaded to which
        | kind of asset. Each entry maps a MIME type to the extension the file
        | is stored under.
        |
        | The keys are the MIME types, not the extensions, because the MIME
        | type is what the server detects from the file's contents. The stored
        | extension is read out of this map, so a file called evil.mp4 whose
        | bytes are actually a PNG is stored as a .png, not as a .mp4.
        |
        | An asset's declared type is never changed to match its file. An
        | upload that does not fit the declared type is a validation error, and
        | a list of formats being absent means that type accepts no uploads at
        | all rather than accepting anything.
        |
        */

        'mimes' => [

            AssetType::Video->value => [
                'video/mp4' => 'mp4',
                'video/quicktime' => 'mov',
                'video/webm' => 'webm',
                'video/x-matroska' => 'mkv',
                'video/x-msvideo' => 'avi',
                'video/x-m4v' => 'm4v',
            ],

            AssetType::Image->value => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ],

            /*
            | WAV is listed twice on purpose. finfo reports a .wav file as
            | audio/x-wav on most systems and as audio/wav on others, so a
            | single spelling would reject a real file on half of them. Both
            | spellings map to the same stored extension.
            */
            AssetType::Audio->value => [
                'audio/mpeg' => 'mp3',
                'audio/mp4' => 'm4a',
                'audio/x-m4a' => 'm4a',
                'audio/wav' => 'wav',
                'audio/x-wav' => 'wav',
                'audio/ogg' => 'ogg',
                'audio/aac' => 'aac',
                'audio/flac' => 'flac',
            ],

            /*
            | Other is a deliberate dead end. It exists so metadata can be
            | catalogued before anyone knows what a file is, not as a bucket
            | for arbitrary uploads. An empty list means "nothing is accepted
            | here": a .pdf or a .zip would be accepted by any catch all
            | allowlist, and then be stored under a path that implies it is
            | media this application can reason about.
            */
            AssetType::Other->value => [],

        ],

    ],
];
