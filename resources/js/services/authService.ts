import { apiGet, apiPost } from '../lib/api';
import { User } from '../types';

export interface LoginPayload {
  email: string;
  password: string;
}

export interface LoginResponseData {
  user: User;
  token: string;
}

export const authService = {
  async login(payload: LoginPayload): Promise<LoginResponseData> {
    const data = await apiPost<LoginResponseData>('/auth/login', payload);
    if (data.token) {
      localStorage.setItem('mochyfami_token', data.token);
    }
    return data;
  },

  async logout(): Promise<void> {
    try {
      await apiPost<null>('/auth/logout');
    } finally {
      localStorage.removeItem('mochyfami_token');
    }
  },

  async getCurrentUser(): Promise<User> {
    return await apiGet<User>('/auth/me');
  },
};
