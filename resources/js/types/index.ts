export interface User {
  id: number;
  name: string;
  email: string;
  created_at?: string;
  updated_at?: string;
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  data: T;
  message: string | null;
}

export interface ApiError {
  success: false;
  data: null;
  message: string;
  errors?: Record<string, string[]>;
}

export type ContentFormat = 'educational' | 'pov' | 'storytelling' | 'funny_fact' | 'comparison' | 'list';

export type ContentIdeaStatus = 'idea' | 'selected' | 'converted' | 'archived';

export type ContentProjectStatus =
  | 'draft'
  | 'researching'
  | 'research_review'
  | 'scripting'
  | 'script_review'
  | 'asset_collection'
  | 'production'
  | 'video_review'
  | 'revision'
  | 'approved'
  | 'published'
  | 'archived'
  | 'failed';

export interface DashboardProject {
  id: number;
  title: string;
  slug: string;
  status: ContentProjectStatus;
  status_label: string;
  category_name?: string | null;
  category_color?: string | null;
  progress_percent?: number;
  updated_at: string;
}

export interface DashboardData {
  ideas_count: number;
  active_projects_count: number;
  review_count: number;
  published_count: number;
  recent_projects: DashboardProject[];
  production_queue: DashboardProject[];
}
