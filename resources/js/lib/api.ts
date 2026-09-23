import axios, { AxiosError, AxiosInstance, AxiosRequestConfig } from 'axios';
import { ApiError, ApiResponse } from '../types';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

export const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
});

// Interceptor to handle Bearer Token if present in localStorage
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('mochyfami_token');
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export async function apiGet<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
  try {
    const response = await apiClient.get<ApiResponse<T>>(url, config);
    return response.data.data;
  } catch (error) {
    throw handleApiError(error);
  }
}

export async function apiPost<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
  try {
    const response = await apiClient.post<ApiResponse<T>>(url, data, config);
    return response.data.data;
  } catch (error) {
    throw handleApiError(error);
  }
}

export async function apiPut<T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> {
  try {
    const response = await apiClient.put<ApiResponse<T>>(url, data, config);
    return response.data.data;
  } catch (error) {
    throw handleApiError(error);
  }
}

export async function apiDelete<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
  try {
    const response = await apiClient.delete<ApiResponse<T>>(url, config);
    return response.data.data;
  } catch (error) {
    throw handleApiError(error);
  }
}

function handleApiError(error: unknown): ApiError {
  if (axios.isAxiosError(error)) {
    const axiosError = error as AxiosError<ApiError>;
    if (axiosError.response && axiosError.response.data) {
      return {
        success: false,
        data: null,
        message: axiosError.response.data.message || 'An error occurred.',
        errors: axiosError.response.data.errors,
      };
    }
  }

  return {
    success: false,
    data: null,
    message: error instanceof Error ? error.message : 'Network error or unknown failure.',
  };
}
