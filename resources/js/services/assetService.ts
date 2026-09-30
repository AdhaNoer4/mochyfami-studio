import { apiClient } from '../lib/api';
import { ApiResponse, Asset, AssetFormData } from '../types';

const basePath = (projectId: number) => `/projects/${projectId}/assets`;

/**
 * Metadata only. Nothing here uploads a file, fetches a source_url, or moves
 * an asset through its lifecycle.
 */
export const assetService = {
  async list(projectId: number): Promise<Asset[]> {
    const response = await apiClient.get<ApiResponse<{ items: Asset[] }>>(
      `${basePath(projectId)}`,
    );
    return response.data.data.items;
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
