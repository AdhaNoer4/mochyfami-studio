import { TtsIssue, TtsTrackStatus } from '../types';

/**
 * Indonesian display labels for backend TTS concepts.
 *
 * These are presentation-only: every decision still lives in the backend
 * services and this file only speaks their vocabulary in Indonesian. Unknown
 * statuses fall back to the raw value and unknown issue codes to the backend's
 * own message, so a future code never renders blank.
 */

const STATUS_LABELS: Record<TtsTrackStatus, string> = {
  pending: 'Menunggu',
  generating: 'Sedang dibuat',
  completed: 'Selesai',
  failed: 'Gagal',
  archived: 'Diarsipkan',
};

const ISSUE_LABELS: Record<string, string> = {
  NO_VISUAL_PLAN: 'Belum ada visual plan untuk versi skrip ini.',
  NO_VISUAL_PLAN_ITEMS: 'Visual plan belum memiliki item.',
  PROVIDER_UNAVAILABLE: 'Provider TTS sedang tidak tersedia.',
  ITEM_WITHOUT_NARRATION: 'Narasi item kosong, belum bisa dibuatkan audio.',
  TTS_GENERATION_FAILED: 'Pembuatan audio gagal untuk narasi saat ini.',
  ITEM_WITHOUT_TRACK: 'Belum ada audio untuk narasi saat ini.',
  NARRATION_CHANGED_SINCE_SYNTHESIS: 'Narasi berubah sejak audio terakhir dibuat.',
  TRACK_DURATION_MISMATCH: 'Durasi audio tidak sesuai perkiraan durasi item.',
  METADATA_NOT_VERIFIED: 'Metadata audio belum terverifikasi.',
  LONG_NARRATION: 'Narasi lebih panjang dari panduan 60 detik.',
};

export function ttsStatusLabel(status: TtsTrackStatus): string {
  return STATUS_LABELS[status] ?? status;
}

export function ttsIssueLabel(issue: TtsIssue): string {
  return ISSUE_LABELS[issue.code] ?? issue.message;
}