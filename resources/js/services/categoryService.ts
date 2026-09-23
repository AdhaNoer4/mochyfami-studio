import { apiClient } from '../lib/api';
import { ApiResponse, ContentCategory, PaginatedData } from '../types';

export interface CategoryFormData {
  name: string;
  slug: string;
  description?: string;
  color?: string;
  is_active: boolean;
}

export const categoryService = {
  async getCategories(page = 1, search = '', perPage = 10): Promise<PaginatedData<ContentCategory>> {
    const response = await apiClient.get<ApiResponse<PaginatedData<ContentCategory>>>('/categories', {
      params: {
        page,
        search: search || undefined,
        per_page: perPage,
      },
    });
    return response.data.data;
  },

  async getCategory(id: number): Promise<ContentCategory> {
    const response = await apiClient.get<ApiResponse<ContentCategory>>(`/categories/${id}`);
    return response.data.data;
  },

  async createCategory(data: CategoryFormData): Promise<ContentCategory> {
    const response = await apiClient.post<ApiResponse<ContentCategory>>('/categories', data);
    return response.data.data;
  },

  async updateCategory(id: number, data: CategoryFormData): Promise<ContentCategory> {
    const response = await apiClient.put<ApiResponse<ContentCategory>>(`/categories/${id}`, data);
    return response.data.data;
  },

  async deleteCategory(id: number): Promise<void> {
    await apiClient.delete(`/categories/${id}`);
  },
};
