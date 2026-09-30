<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A file could not be written to or removed from storage.
 *
 * The message on this exception is for the log, never for the API response: it
 * can carry a filesystem path or a driver level failure, and neither belongs
 * in a response body. The controller answers with a fixed generic message
 * instead, following the same pattern as the AI provider failures.
 */
class AssetFileStorageException extends RuntimeException {}
