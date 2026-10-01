<?php

namespace App\Enums;

/**
 * The state of technical metadata analysis for an asset's stored file.
 *
 * This is a separate vocabulary from AssetStatus on purpose. AssetStatus is
 * where a human has put an asset in the production workflow; this is only
 * whether the server has successfully read the file's dimensions and duration.
 * Conflating them would let "we could not read this file" start looking like
 * "this asset was rejected", which is a claim about content that nobody made.
 *
 * Null is also a meaningful state and is not an enum case: an asset that has
 * no file at all has never been analysed, which is different from an asset
 * whose analysis failed.
 */
enum AssetMetadataStatus: string
{
    /**
     * A file is attached and nothing has tried to read it yet.
     */
    case Pending = 'pending';

    /**
     * The file has been read and every applicable value was extracted.
     */
    case Completed = 'completed';

    /**
     * The file could not be read. metadata_error says why, in wording that is
     * safe to show a user.
     */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
