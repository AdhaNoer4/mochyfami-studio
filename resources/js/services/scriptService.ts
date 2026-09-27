import { apiClient } from '../lib/api';
import {
  ApiResponse,
  Script,
  ScriptFormData,
  ScriptGenerationRequest,
  ScriptGenerationResult,
  ScriptQualityResult,
  ScriptStatus,
  ScriptTraceabilityData,
  ScriptVersion,
  ScriptVersionResearchClaim,
} from '../types';

export const scriptService = {
  async getScript(projectId: number): Promise<Script | null> {
    const response = await apiClient.get<ApiResponse<Script | null>>(`/projects/${projectId}/script`);
    return response.data.data;
  },

  async getQuality(projectId: number): Promise<ScriptQualityResult> {
    const response = await apiClient.get<ApiResponse<ScriptQualityResult>>(
      `/projects/${projectId}/script/quality`,
    );
    return response.data.data;
  },

  async createScript(projectId: number, data: ScriptFormData): Promise<Script> {
    const response = await apiClient.post<ApiResponse<Script>>(`/projects/${projectId}/script`, data);
    return response.data.data;
  },

  async transitionStatus(projectId: number, status: ScriptStatus): Promise<Script> {
    const response = await apiClient.patch<ApiResponse<Script>>(`/projects/${projectId}/script/status`, {
      status,
    });
    return response.data.data;
  },

  async getVersions(projectId: number): Promise<ScriptVersion[]> {
    const response = await apiClient.get<ApiResponse<ScriptVersion[]>>(`/projects/${projectId}/script/versions`);
    return response.data.data;
  },

  async getVersion(projectId: number, version: number): Promise<ScriptVersion> {
    const response = await apiClient.get<ApiResponse<ScriptVersion>>(
      `/projects/${projectId}/script/versions/${version}`,
    );
    return response.data.data;
  },

  async createVersion(projectId: number, data: ScriptFormData): Promise<Script> {
    const response = await apiClient.post<ApiResponse<Script>>(`/projects/${projectId}/script/versions`, data);
    return response.data.data;
  },

  async generateScript(projectId: number, data: ScriptGenerationRequest): Promise<ScriptGenerationResult> {
    const response = await apiClient.post<ApiResponse<ScriptGenerationResult>>(
      `/projects/${projectId}/script/generate`,
      data,
    );
    return response.data.data;
  },

  async updateCurrentVersion(projectId: number, data: ScriptFormData): Promise<Script> {
    const response = await apiClient.patch<ApiResponse<Script>>(
      `/projects/${projectId}/script/versions/current`,
      data,
    );
    return response.data.data;
  },

  async listResearchClaims(
    projectId: number,
    version: number,
  ): Promise<ScriptTraceabilityData> {
    const response = await apiClient.get<ApiResponse<ScriptTraceabilityData>>(
      `/projects/${projectId}/script/versions/${version}/research-claims`,
    );
    return response.data.data;
  },

  async attachResearchClaim(
    projectId: number,
    version: number,
    claimId: number,
  ): Promise<ScriptVersionResearchClaim> {
    const response = await apiClient.post<ApiResponse<ScriptVersionResearchClaim>>(
      `/projects/${projectId}/script/versions/${version}/research-claims/${claimId}`,
    );
    return response.data.data;
  },

  async detachResearchClaim(projectId: number, version: number, claimId: number): Promise<void> {
    await apiClient.delete(`/projects/${projectId}/script/versions/${version}/research-claims/${claimId}`);
  },
};