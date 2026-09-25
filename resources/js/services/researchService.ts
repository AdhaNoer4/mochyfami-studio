import { apiClient } from '../lib/api';
import {
  ApiError,
  ApiResponse,
  ClaimFormData,
  ResearchClaim,
  ResearchReport,
  ResearchSource,
  ResearchStatus,
  SourceFormData,
  UpdateResearchPayload,
} from '../types';

export const researchService = {
  async getResearch(projectId: number): Promise<ResearchReport | null> {
    const response = await apiClient.get<ApiResponse<ResearchReport | null>>(`/projects/${projectId}/research`);
    return response.data.data;
  },

  async createResearch(projectId: number): Promise<ResearchReport> {
    const response = await apiClient.post<ApiResponse<ResearchReport>>(`/projects/${projectId}/research`);
    return response.data.data;
  },

  async updateResearch(projectId: number, data: UpdateResearchPayload): Promise<ResearchReport> {
    const response = await apiClient.patch<ApiResponse<ResearchReport>>(`/projects/${projectId}/research`, data);
    return response.data.data;
  },

  async updateResearchStatus(projectId: number, status: ResearchStatus): Promise<ResearchReport> {
    const response = await apiClient.patch<ApiResponse<ResearchReport>>(
      `/projects/${projectId}/research/status`,
      { status },
    );
    return response.data.data;
  },

  async deleteResearch(projectId: number): Promise<void> {
    await apiClient.delete(`/projects/${projectId}/research`);
  },

  async getSources(projectId: number): Promise<ResearchSource[]> {
    const response = await apiClient.get<ApiResponse<{ items: ResearchSource[] }>>(
      `/projects/${projectId}/research/sources`,
    );
    return response.data.data.items;
  },

  async createSource(projectId: number, data: SourceFormData): Promise<ResearchSource> {
    const response = await apiClient.post<ApiResponse<ResearchSource>>(
      `/projects/${projectId}/research/sources`,
      data,
    );
    return response.data.data;
  },

  async updateSource(
    projectId: number,
    sourceId: number,
    data: Partial<SourceFormData>,
  ): Promise<ResearchSource> {
    const response = await apiClient.patch<ApiResponse<ResearchSource>>(
      `/projects/${projectId}/research/sources/${sourceId}`,
      data,
    );
    return response.data.data;
  },

  async deleteSource(projectId: number, sourceId: number): Promise<void> {
    await apiClient.delete(`/projects/${projectId}/research/sources/${sourceId}`);
  },

  async getClaims(projectId: number): Promise<ResearchClaim[]> {
    const response = await apiClient.get<ApiResponse<{ items: ResearchClaim[] }>>(
      `/projects/${projectId}/research/claims`,
    );
    return response.data.data.items;
  },

  async createClaim(projectId: number, data: ClaimFormData): Promise<ResearchClaim> {
    const response = await apiClient.post<ApiResponse<ResearchClaim>>(
      `/projects/${projectId}/research/claims`,
      data,
    );
    return response.data.data;
  },

  async updateClaim(
    projectId: number,
    claimId: number,
    data: Partial<ClaimFormData>,
  ): Promise<ResearchClaim> {
    const response = await apiClient.patch<ApiResponse<ResearchClaim>>(
      `/projects/${projectId}/research/claims/${claimId}`,
      data,
    );
    return response.data.data;
  },

  async deleteClaim(projectId: number, claimId: number): Promise<void> {
    await apiClient.delete(`/projects/${projectId}/research/claims/${claimId}`);
  },
};

export function getApiErrorMessage(error: unknown, fallback: string): string {
  return (error as ApiError | null)?.message ?? fallback;
}