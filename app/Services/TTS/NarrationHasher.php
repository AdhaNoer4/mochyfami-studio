<?php

namespace App\Services\TTS;

/**
 * The single deterministic rule for the TTS narration fingerprint.
 *
 * The hash is computed over the exact narration text, byte for byte: no
 * normalization, no case folding, no punctuation stripping. Keeping the rule
 * in one place means the snapshot stored on a track and the hash used to
 * detect staleness can never drift apart.
 */
final class NarrationHasher
{
    public static function hash(string $narrationText): string
    {
        return hash('sha256', $narrationText);
    }
}
