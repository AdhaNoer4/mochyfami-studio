import { apiClient } from '../lib/api';
import {
  ApiError,
  ApiResponse,
  TtsGeneratePayload,
  TtsManifest,
  TtsReadiness,
  TtsTrack,
  TtsTrackList,
} from '../types';

const basePath = (projectId: number, version: number) =>
  `/projects/${projectId}/script/versions/${version}/visual-plan`;

/**
 * The read-only reports and the synthesis history of one script version.
 *
 * Every mutation here always writes a new track row and never touches an old
 * one, so nothing in this service is destructive: archiving is the closest
 * thing to it and even that keeps the audio asset and its file.
 */
export const ttsService = {
  async getReadiness(projectId: number, version: number): Promise<TtsReadiness> {
    const response = await apiClient.get<ApiResponse<TtsReadiness>>(
      `${basePath(projectId, version)}/tts-readiness`,
    );
    return response.data.data;
  },

  async getManifest(projectId: number, version: number): Promise<TtsManifest> {
    const response = await apiClient.get<ApiResponse<TtsManifest>>(
      `${basePath(projectId, version)}/tts-manifest`,
    );
    return response.data.data;
  },

  async listTracks(projectId: number, version: number, itemId: number): Promise<TtsTrackList> {
    const response = await apiClient.get<ApiResponse<TtsTrackList>>(
      `${basePath(projectId, version)}/items/${itemId}/tts`,
    );
    return response.data.data;
  },

  async generate(
    projectId: number,
    version: number,
    itemId: number,
    payload: TtsGeneratePayload,
  ): Promise<TtsTrack> {
    const response = await apiClient.post<ApiResponse<TtsTrack>>(
      `${basePath(projectId, version)}/items/${itemId}/tts`,
      payload,
    );
    return response.data.data;
  },

  async regenerate(
    projectId: number,
    version: number,
    itemId: number,
    trackId: number,
    payload: TtsGeneratePayload,
  ): Promise<TtsTrack> {
    const response = await apiClient.post<ApiResponse<TtsTrack>>(
      `${basePath(projectId, version)}/items/${itemId}/tts/${trackId}/regenerate`,
      payload,
    );
    return response.data.data;
  },

  async archive(
    projectId: number,
    version: number,
    itemId: number,
    trackId: number,
  ): Promise<TtsTrack> {
    const response = await apiClient.delete<ApiResponse<TtsTrack>>(
      `${basePath(projectId, version)}/items/${itemId}/tts/${trackId}`,
    );
    return response.data.data;
  },
};

/**
 * Maps an API failure to an Indonesian, actionable message for the Audio panel.
 *
 * 422 messages come from the server already and carry the real reason (for
 * example a narration that is empty), so they are passed through instead of
 * being replaced by a code.
 */
export function readTtsErrorMessage(
  error: unknown,
  fallback = 'Terjadi kesalahan yang tidak terduga.',
): string {
  const apiError = error as ApiError | null;

  if (!apiError || typeof apiError.status !== 'number') {
    return fallback;
  }

  switch (apiError.status) {
    case 401:
      return 'Sesi Anda telah berakhir. Silakan masuk kembali.';
    case 403:
      return 'Anda tidak memiliki izin untuk tindakan ini.';
    case 404:
      return apiError.message || 'Data tidak ditemukan.';
    case 422:
      return apiError.message || 'Permintaan tidak valid.';
    case 502:
      return 'Layanan TTS sedang tidak tersedia. Silakan coba lagi.';
    default:
      return apiError.message || fallback;
  }
}