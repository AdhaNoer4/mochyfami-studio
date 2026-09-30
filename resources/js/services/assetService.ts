import { apiClient } from '../lib/api';
import {
  ApiResponse,
  Asset,
  AssetFilterParams,
  AssetFormData,
  PaginatedData,
} from '../types';

const basePath = (projectId: number) => `/projects/${projectId}/assets`;

/**
 * Metadata plus file attachment. Nothing here fetches a source_url or moves an
 * asset through its lifecycle.
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
};
