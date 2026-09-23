import { apiClient } from '../lib/api';
import { ApiResponse, DashboardData } from '../types';

export const dashboardService = {
  async getDashboardData(): Promise<DashboardData> {
    const response = await apiClient.get<ApiResponse<DashboardData>>('/dashboard');
    return response.data.data;
  },
};
