import { apiClient } from '../lib/api';
import {
  ApiResponse,
  ContentProject,
  ContentProjectStatus,
  PaginatedData,
  ProjectFilterParams,
} from '../types';

export interface ProjectFormData {
  title: string;
  slug?: string;
  content_idea_id?: number | null;
  category_id?: number | null;
  status?: string;
  target_duration_seconds?: number;
  language?: string;
  tone?: string;
  hook?: string | null;
  description?: string | null;
}

export const projectService = {
  async getProjects(params: ProjectFilterParams = {}): Promise<PaginatedData<ContentProject>> {
    const response = await apiClient.get<ApiResponse<PaginatedData<ContentProject>>>('/projects', {
      params: {
        page: params.page || 1,
        per_page: params.per_page || 10,
        search: params.search || undefined,
        status: params.status || undefined,
        content_idea_id: params.content_idea_id || undefined,
        category_id: params.category_id || undefined,
        sort: params.sort || 'created_at',
        direction: params.direction || 'desc',
      },
    });
    return response.data.data;
  },

  async getProject(id: number): Promise<ContentProject> {
    const response = await apiClient.get<ApiResponse<ContentProject>>(`/projects/${id}`);
    return response.data.data;
  },

  async createProject(data: ProjectFormData): Promise<ContentProject> {
    const response = await apiClient.post<ApiResponse<ContentProject>>('/projects', data);
    return response.data.data;
  },

  async updateProject(id: number, data: Partial<ProjectFormData>): Promise<ContentProject> {
    const response = await apiClient.put<ApiResponse<ContentProject>>(`/projects/${id}`, data);
    return response.data.data;
  },

  async updateProjectStatus(id: number, status: ContentProjectStatus): Promise<ContentProject> {
    const response = await apiClient.patch<ApiResponse<ContentProject>>(`/projects/${id}/status`, { status });
    return response.data.data;
  },

  async deleteProject(id: number): Promise<void> {
    await apiClient.delete(`/projects/${id}`);
  },
};
