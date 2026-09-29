import { apiClient } from '../lib/api';
import {
  ApiResponse,
  VisualPlan,
  VisualPlanFormData,
  VisualPlanItem,
  VisualPlanItemFormData,
  VisualPlanItemOrder,
  VisualPlanQualityResult,
  VisualPlanStatus,
} from '../types';

const basePath = (projectId: number, version: number) =>
  `/projects/${projectId}/script/versions/${version}/visual-plan`;

export const visualPlanService = {
  async getPlan(projectId: number, version: number): Promise<VisualPlan | null> {
    const response = await apiClient.get<ApiResponse<VisualPlan | null>>(
      `/projects/${projectId}/script/versions/${version}/visual-plan`,
    );
    return response.data.data;
  },

  /**
   * Evaluate the deterministic production readiness gate. Read-only: the
   * endpoint never mutates the plan, its items, or its requirements.
   */
  async getQuality(projectId: number, version: number): Promise<VisualPlanQualityResult> {
    const response = await apiClient.get<ApiResponse<VisualPlanQualityResult>>(
      `${basePath(projectId, version)}/quality`,
    );
    return response.data.data;
  },

  async createPlan(projectId: number, version: number, data: VisualPlanFormData): Promise<VisualPlan> {
    const response = await apiClient.post<ApiResponse<VisualPlan>>(
      `/projects/${projectId}/script/versions/${version}/visual-plan`,
      data,
    );
    return response.data.data;
  },

  async transitionStatus(projectId: number, version: number, status: VisualPlanStatus): Promise<VisualPlan> {
    const response = await apiClient.patch<ApiResponse<VisualPlan>>(
      `/projects/${projectId}/script/versions/${version}/visual-plan/status`,
      { status },
    );
    return response.data.data;
  },

  async listItems(projectId: number, version: number): Promise<VisualPlanItem[]> {
    const response = await apiClient.get<ApiResponse<{ items: VisualPlanItem[] }>>(
      `/projects/${projectId}/script/versions/${version}/visual-plan/items`,
    );
    return response.data.data.items;
  },

  async createItem(
    projectId: number,
    version: number,
    data: VisualPlanItemFormData,
  ): Promise<VisualPlanItem> {
    const response = await apiClient.post<ApiResponse<VisualPlanItem>>(
      `/projects/${projectId}/script/versions/${version}/visual-plan/items`,
      data,
    );
    return response.data.data;
  },

  async updateItem(
    projectId: number,
    version: number,
    itemId: number,
    data: Partial<VisualPlanItemFormData>,
  ): Promise<VisualPlanItem> {
    const response = await apiClient.patch<ApiResponse<VisualPlanItem>>(
      `/projects/${projectId}/script/versions/${version}/visual-plan/items/${itemId}`,
      data,
    );
    return response.data.data;
  },

  async deleteItem(projectId: number, version: number, itemId: number): Promise<void> {
    await apiClient.delete(`/projects/${projectId}/script/versions/${version}/visual-plan/items/${itemId}`);
  },

  async reorderItems(
    projectId: number,
    version: number,
    items: VisualPlanItemOrder[],
  ): Promise<void> {
    await apiClient.patch(`/projects/${projectId}/script/versions/${version}/visual-plan/items/reorder`, {
      items,
    });
  },
};
