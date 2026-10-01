/**
 * Formatting for asset file and media values.
 *
 * These live in one module rather than inside a component because the card,
 * the detail modal and the requirement list all show the same numbers. A copy
 * of this logic in each of them is how two places end up disagreeing about
 * what 0 bytes or a missing duration means.
 *
 * Every function returns null when there is nothing to show, rather than a
 * zero or a placeholder. The callers omit the row entirely, so a blank cell
 * never has to stand for "not recorded" and imply a real measurement.
 */

/**
 * Human readable file size, from bytes.
 */
export function formatFileSize(bytes?: number | null): string | null {
  if (bytes === null || bytes === undefined) {
    return null;
  }

  if (bytes < 1024) {
    return `${bytes} B`;
  }

  const units = ['KB', 'MB', 'GB', 'TB'];
  let value = bytes / 1024;
  let unitIndex = 0;

  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }

  return `${value.toFixed(1)} ${units[unitIndex]}`;
}

/**
 * Pixel dimensions, when both sides are known.
 *
 * Either number alone is not a dimension, so one of them is treated as absent.
 */
export function formatDimensions(width?: number | null, height?: number | null): string | null {
  if (!width || !height) {
    return null;
  }

  return `${width} x ${height}`;
}

/**
 * A duration in seconds as minutes and seconds, e.g. 125 becomes "2:05".
 *
 * An audio file of an hour and a half is not 5400 seconds on screen. The exact
 * count is still available on the row underneath for anyone who needs it.
 */
export function formatDuration(seconds?: number | null): string | null {
  if (seconds === null || seconds === undefined) {
    return null;
  }

  if (seconds < 60) {
    return `${seconds}s`;
  }

  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remainder = seconds % 60;

  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
  }

  return `${minutes}:${String(remainder).padStart(2, '0')}`;
}

/**
 * Aspect ratio for display.
 *
 * The stored value is a plain number, and the closest familiar ratio is shown
 * beside it because 1.7778 is not something people read at a glance. The
 * number is kept alongside it rather than replaced: the label is a
 * convenience, the number is the measurement.
 */
export function formatAspectRatio(aspectRatio?: number | null): string | null {
  if (aspectRatio === null || aspectRatio === undefined || aspectRatio <= 0) {
    return null;
  }

  const known: Array<[number, string]> = [
    [1, '1:1'],
    [4 / 3, '4:3'],
    [16 / 9, '16:9'],
    [3 / 2, '3:2'],
    [9 / 16, '9:16'],
    [2.39, '2.39:1'],
    [21 / 9, '21:9'],
  ];

  // A stored ratio is rounded to four decimals, so the comparison has to
  // tolerate that much error or nothing would ever match.
  const tolerance = 0.01;

  for (const [value, label] of known) {
    if (Math.abs(aspectRatio - value) <= tolerance) {
      return label;
    }
  }

  return aspectRatio.toFixed(4);
}

/**
 * Aspect ratio for display, with the numeric value retained.
 */
export function formatAspectRatioLabel(aspectRatio?: number | null): string | null {
  const label = formatAspectRatio(aspectRatio);

  if (label === null || aspectRatio === null || aspectRatio === undefined) {
    return null;
  }

  return `${label} (${aspectRatio.toFixed(4)})`;
}