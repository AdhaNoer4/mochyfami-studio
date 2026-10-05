import { apiClient } from '../lib/api';
import {
  ApiResponse,
  Asset,
  AssetFilterParams,
  AssetFormData,
  AssetStatus,
  PaginatedData,
} from '../types';

const basePath = (projectId: number) => `/projects/${projectId}/assets`;

/**
 * Metadata plus file attachment, with one deliberate exception: transitionStatus
 * below is the only call here that moves an asset through its lifecycle.
 */
export const assetService = {
  async list(
    projectId: number,
    params: AssetFilterParams = {},
  ): Promise<PaginatedData<Asset>> {
    const response = await apiClient.get<ApiResponse<PaginatedData<Asset>>>(
      `${basePath(projectId)}`,
      {
        params: {
          page: params.page || 1,
          per_page: params.per_page || 10,
          search: params.search || undefined,
          type: params.type || undefined,
          status: params.status || undefined,
          sort: params.sort || 'created_at',
          direction: params.direction || 'desc',
        },
      },
    );
    return response.data.data;
  },

  async get(projectId: number, assetId: number): Promise<Asset> {
    const response = await apiClient.get<ApiResponse<Asset>>(
      `${basePath(projectId)}/${assetId}`,
    );
    return response.data.data;
  },

  async create(projectId: number, data: AssetFormData): Promise<Asset> {
    const response = await apiClient.post<ApiResponse<Asset>>(
      `${basePath(projectId)}`,
      data,
    );
    return response.data.data;
  },

  async update(
    projectId: number,
    assetId: number,
    data: Partial<AssetFormData>,
  ): Promise<Asset> {
    const response = await apiClient.patch<ApiResponse<Asset>>(
      `${basePath(projectId)}/${assetId}`,
      data,
    );
    return response.data.data;
  },

  async remove(projectId: number, assetId: number): Promise<void> {
    await apiClient.delete(`${basePath(projectId)}/${assetId}`);
  },

  /**
   * Uploads a file for an existing asset, replacing any file it already had.
   *
   * The browser sends the file and nothing else. The server decides whether the
   * file suits the asset's type, what it is called on disk, and where it goes,
   * so there is no client-supplied path or size to disagree about.
   */
  async uploadFile(projectId: number, assetId: number, file: File): Promise<Asset> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await apiClient.post<ApiResponse<Asset>>(
      `${basePath(projectId)}/${assetId}/file`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
    );
    return response.data.data;
  },

  /**
   * Asks the server to measure the attached file again.
   *
   * The endpoint reads only the stored file. It cannot change the asset's type,
   * its lifecycle status, its requirements or the file itself, so this is safe
   * to offer as a plain retry for a failed measurement.
   */
  async analyze(projectId: number, assetId: number): Promise<Asset> {
    const response = await apiClient.post<ApiResponse<Asset>>(
      `${basePath(projectId)}/${assetId}/analyze`,
    );
    return response.data.data;
  },

  /**
   * Asks the server to move an asset along its lifecycle.
   *
   * This has its own endpoint rather than riding on update because the two are
   * not the same decision: update edits metadata, while this is the one path
   * that writes status. The server enforces the state machine and answers 422
   * with a message naming both states when a transition is not allowed, which
   * is why the status is not filtered here first.
   */
  async transitionStatus(
    projectId: number,
    assetId: number,
    status: AssetStatus,
  ): Promise<Asset> {
    const response = await apiClient.patch<ApiResponse<Asset>>(
      `${basePath(projectId)}/${assetId}/status`,
      { status },
    );
    return response.data.data;
  },

  /**
   * Loads a preview of the attached file and returns an object URL for it.
   *
   * The bytes are fetched through the authenticated client rather than handed
   * to an <img> or <video> as a plain src. Both of those can only send cookies,
   * and this API authenticates with a bearer token held in localStorage, so a
   * direct src would come back 401. Fetching here also means the caller
   * revokes the URL when it is done instead of leaving one alive per asset.
   *
   * The caller owns the returned URL and must call revokeAssetPreview on it.
   */
  async fetchPreview(projectId: number, assetId: number): Promise<string> {
    const response = await apiClient.get<Blob>(
      `${basePath(projectId)}/${assetId}/preview`,
      { responseType: 'blob' },
    );

    return URL.createObjectURL(response.data);
  },
};

/**
 * Releases an object URL created by fetchPreview.
 *
 * A blob URL holds its blob alive until it is revoked, so a detail panel
 * opened and closed repeatedly would otherwise keep every file it ever loaded
 * in memory for the rest of the session.
 */
export function revokeAssetPreview(objectUrl: string | null): void {
  if (objectUrl) {
    URL.revokeObjectURL(objectUrl);
  }
}
