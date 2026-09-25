import { apiClient } from '../lib/api';
import {
  ApiResponse,
  ContentIdea,
  ConvertIdeaPayload,
  ConvertIdeaResponse,
  IdeaFilterParams,
  ImportPreviewData,
  ImportPreviewRow,
  ImportResultData,
  PaginatedData,
} from '../types';

export interface IdeaFormData {
  title: string;
  slug: string;
  category_id: number;
  hook?: string;
  concept?: string;
  format: string;
  status: string;
  priority: number;
  notes?: string;
  source_idea?: string;
}

export const ideaService = {
  async getIdeas(params: IdeaFilterParams = {}): Promise<PaginatedData<ContentIdea>> {
    const response = await apiClient.get<ApiResponse<PaginatedData<ContentIdea>>>('/ideas', {
      params: {
        page: params.page || 1,
        per_page: params.per_page || 10,
        search: params.search || undefined,
        category_id: params.category_id || undefined,
        format: params.format || undefined,
        status: params.status || undefined,
        priority: params.priority || undefined,
        sort: params.sort || 'created_at',
        direction: params.direction || 'desc',
      },
    });
    return response.data.data;
  },

  async getIdea(id: number): Promise<ContentIdea> {
    const response = await apiClient.get<ApiResponse<ContentIdea>>(`/ideas/${id}`);
    return response.data.data;
  },

  async createIdea(data: IdeaFormData): Promise<ContentIdea> {
    const response = await apiClient.post<ApiResponse<ContentIdea>>('/ideas', data);
    return response.data.data;
  },

  async updateIdea(id: number, data: Partial<IdeaFormData>): Promise<ContentIdea> {
    const response = await apiClient.put<ApiResponse<ContentIdea>>(`/ideas/${id}`, data);
    return response.data.data;
  },

  async deleteIdea(id: number): Promise<void> {
    await apiClient.delete(`/ideas/${id}`);
  },

  async previewImportCsv(file: File): Promise<ImportPreviewData> {
    const formData = new FormData();
    formData.append('csv_file', file);

    const response = await apiClient.post<ApiResponse<ImportPreviewData>>('/ideas/import/preview', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data.data;
  },

  async executeImport(rows: ImportPreviewRow[]): Promise<ImportResultData> {
    const response = await apiClient.post<ApiResponse<ImportResultData>>('/ideas/import', { rows });
    return response.data.data;
  },

  async convertIdeaToProject(id: number, payload: ConvertIdeaPayload): Promise<ConvertIdeaResponse> {
    const response = await apiClient.post<ApiResponse<ConvertIdeaResponse>>(
      `/ideas/${id}/convert-to-project`,
      payload,
    );
    return response.data.data;
  },
};
