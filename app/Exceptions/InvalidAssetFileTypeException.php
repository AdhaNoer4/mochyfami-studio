<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The uploaded file is not one this asset's declared type accepts.
 *
 * This is a client error rather than a server fault, so it becomes a 422. The
 * message names the detected MIME type and the accepted ones: saying only
 * "invalid file" leaves the user guessing whether the problem is the file,
 * the asset, or the upload itself.
 *
 * The asset's type is never changed to make the mismatch go away. A video
 * asset stays a video asset whether or not it currently has a file.
 */
class InvalidAssetFileTypeException extends RuntimeException {}
