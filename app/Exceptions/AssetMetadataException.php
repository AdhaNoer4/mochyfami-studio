<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A stored file could not be read for its technical metadata.
 *
 * This is a domain failure, not a bug: a truncated upload, a file that is not
 * really the media it claims to be, and a machine with no FFprobe all land
 * here. The message is written to be safe to show a user, so it must never
 * carry an absolute path, a shell command, or a stack trace; anything
 * sensitive goes to the log through report() instead.
 */
class AssetMetadataException extends RuntimeException {}
