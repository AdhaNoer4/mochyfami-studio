import { apiClient } from '../lib/api';
import {
  ApiResponse,
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
};
