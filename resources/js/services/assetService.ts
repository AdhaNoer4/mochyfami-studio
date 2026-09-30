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
 * Metadata only. Nothing here uploads a file, fetches a source_url, or moves
 * an asset through its lifecycle.
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
};
