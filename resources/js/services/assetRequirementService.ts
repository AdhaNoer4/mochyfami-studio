import { apiClient } from '../lib/api';
import {
  ApiResponse,
  Asset,
  AssetRequirement,
  AssetRequirementFormData,
  AssetRequirementGenerationSummary,
  AssetRequirementStatus,
} from '../types';

const basePath = (projectId: number, version: number) =>
  `/projects/${projectId}/script/versions/${version}/visual-plan`;

export const assetRequirementService = {
  async list(
    projectId: number,
    version: number,
    itemId: number,
  ): Promise<AssetRequirement[]> {
    const response = await apiClient.get<ApiResponse<{ requirements: AssetRequirement[] }>>(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements`,
    );
    return response.data.data.requirements;
  },

  async create(
    projectId: number,
    version: number,
    itemId: number,
    data: AssetRequirementFormData,
  ): Promise<AssetRequirement> {
    const response = await apiClient.post<ApiResponse<AssetRequirement>>(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements`,
      data,
    );
    return response.data.data;
  },

  async update(
    projectId: number,
    version: number,
    itemId: number,
    requirementId: number,
    data: Partial<AssetRequirementFormData>,
  ): Promise<AssetRequirement> {
    const response = await apiClient.patch<ApiResponse<AssetRequirement>>(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements/${requirementId}`,
      data,
    );
    return response.data.data;
  },

  async transitionStatus(
    projectId: number,
    version: number,
    itemId: number,
    requirementId: number,
    status: AssetRequirementStatus,
  ): Promise<AssetRequirement> {
    const response = await apiClient.patch<ApiResponse<AssetRequirement>>(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements/${requirementId}/status`,
      { status },
    );
    return response.data.data;
  },

  async remove(projectId: number, version: number, itemId: number, requirementId: number): Promise<void> {
    await apiClient.delete(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements/${requirementId}`,
    );
  },

  async generate(
    projectId: number,
    version: number,
  ): Promise<AssetRequirementGenerationSummary> {
    const response = await apiClient.post<ApiResponse<AssetRequirementGenerationSummary>>(
      `${basePath(projectId, version)}/asset-requirements/generate`,
    );
    return response.data.data;
  },

  /**
   * The assets associated with one requirement.
   *
   * This is the requirement's own candidate list, never the project's whole
   * asset library. The project asset library is fetched separately through
   * assetService when the picker needs something to choose from.
   */
  async listAssets(
    projectId: number,
    version: number,
    itemId: number,
    requirementId: number,
  ): Promise<Asset[]> {
    const response = await apiClient.get<ApiResponse<{ assets: Asset[] }>>(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements/${requirementId}/assets`,
    );
    return response.data.data.assets;
  },

  /**
   * Records an existing asset as a candidate for the requirement.
   *
   * Association only. Neither the asset nor the requirement changes status as
   * a result, and the server rejects a repeat of a pair it already holds, so
   * a 409 here is a real conflict rather than a bad request.
   */
  async attachAsset(
    projectId: number,
    version: number,
    itemId: number,
    requirementId: number,
    assetId: number,
  ): Promise<Asset> {
    const response = await apiClient.post<ApiResponse<Asset>>(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements/${requirementId}/assets`,
      { asset_id: assetId },
    );
    return response.data.data;
  },

  /**
   * Removes the association. The asset and the requirement both stay.
   */
  async detachAsset(
    projectId: number,
    version: number,
    itemId: number,
    requirementId: number,
    assetId: number,
  ): Promise<void> {
    await apiClient.delete(
      `${basePath(projectId, version)}/items/${itemId}/asset-requirements/${requirementId}/assets/${assetId}`,
    );
  },
};
