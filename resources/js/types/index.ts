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
  status?: number;
  errors?: Record<string, string[]>;
}

export interface PaginationMeta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface PaginatedData<T> {
  items: T[];
  pagination: PaginationMeta;
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

export interface ContentCategory {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  color?: string | null;
  is_active: boolean;
  ideas_count?: number;
  projects_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface ContentIdea {
  id: number;
  title: string;
  slug: string;
  category_id: number;
  category?: {
    id: number;
    name: string;
    color?: string;
  };
  hook?: string | null;
  concept?: string | null;
  format: ContentFormat;
  format_label: string;
  status: ContentIdeaStatus;
  status_label: string;
  project_id?: number | null;
  priority: number;
  priority_label: string;
  notes?: string | null;
  source_idea?: string | null;
  creator_name?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface ProjectTransition {
  status: ContentProjectStatus;
  label: string;
  action: string;
  destructive: boolean;
}

export interface ContentProject {
  id: number;
  title: string;
  slug: string;
  status: ContentProjectStatus;
  status_label: string;
  allowed_transitions?: ProjectTransition[];
  content_idea_id?: number | null;
  idea?: {
    id: number;
    title: string;
    format?: ContentFormat;
    format_label?: string;
  } | null;
  category_id?: number | null;
  category?: {
    id: number;
    name: string;
    color?: string;
  } | null;
  target_duration_seconds?: number;
  language?: string;
  tone?: string;
  priority?: number;
  hook?: string | null;
  description?: string | null;
  current_step?: string;
  progress_percent?: number;
  creator_name?: string | null;
  created_at?: string;
  updated_at?: string;
}

export type IdeaSortField = 'created_at' | 'updated_at' | 'title' | 'priority';
export type IdeaSortDirection = 'asc' | 'desc';

export interface IdeaFilterParams {
  page?: number;
  search?: string;
  category_id?: number | string;
  format?: string;
  status?: string;
  priority?: number | string;
  sort?: IdeaSortField;
  direction?: IdeaSortDirection;
  per_page?: number;
}

export type ProjectSortField = 'created_at' | 'updated_at' | 'title' | 'status' | 'progress_percent';

export interface ProjectFilterParams {
  page?: number;
  search?: string;
  status?: string;
  content_idea_id?: number | string;
  category_id?: number | string;
  sort?: ProjectSortField;
  direction?: IdeaSortDirection;
  per_page?: number;
}

export interface ImportPreviewRowData {
  title: string;
  slug: string;
  category_id: number | null;
  category_name: string;
  hook: string;
  concept: string;
  format: string;
  status: string;
  priority: number;
  notes?: string;
  source_idea?: string;
}

export interface ImportPreviewRow {
  row_number: number;
  data: ImportPreviewRowData;
  status: 'valid' | 'invalid' | 'duplicate';
  errors: string[];
  warnings: string[];
}

export interface ImportPreviewData {
  total_rows: number;
  valid_rows_count: number;
  invalid_rows_count: number;
  duplicate_rows_count: number;
  rows: ImportPreviewRow[];
}

export interface ImportResultData {
  total_rows: number;
  imported_rows: number;
  skipped_rows: number;
  duplicate_rows: number;
  failed_rows: number;
}

export interface DashboardOverview {
  total_ideas: number;
  total_projects: number;
  active_projects: number;
  published_projects: number;
}

export interface DashboardIdeaStats {
  total: number;
  idea: number;
  selected: number;
  converted: number;
  archived: number;
}

export interface DashboardProjectStats {
  total: number;
  by_status: Record<ContentProjectStatus, number>;
}

export interface DashboardRecentIdea {
  id: number;
  title: string;
  status: ContentIdeaStatus;
  status_label: string;
  format: ContentFormat;
  format_label?: string;
  category: string | null;
  category_color?: string;
  created_at: string;
}

export interface DashboardProjectItem {
  id: number;
  title: string;
  slug: string;
  status: ContentProjectStatus;
  status_label: string;
  priority: number;
  idea?: { id: number; title: string } | null;
  updated_at: string;
}

export interface DashboardData {
  overview: DashboardOverview;
  ideas: DashboardIdeaStats;
  projects: DashboardProjectStats;
  recent_ideas: DashboardRecentIdea[];
  recent_projects: DashboardProjectItem[];
  production_queue: DashboardProjectItem[];
}

export interface ConvertIdeaPayload {
  title?: string;
  priority?: number;
  notes?: string;
}

export interface ConvertIdeaResponse {
  project: ContentProject;
  idea: ContentIdea;
}

export interface UpdateProjectStatusPayload {
  status: ContentProjectStatus;
}

export type ResearchStatus = 'pending' | 'researching' | 'completed' | 'needs_review' | 'failed';

export type ResearchClaimStatus = 'unverified' | 'supported' | 'contradicted' | 'uncertain';

export type ResearchClaimImportance = 'low' | 'medium' | 'high';

export type SourceType = 'article' | 'academic' | 'official' | 'news' | 'documentation' | 'other';

export interface ResearchTransition {
  status: ResearchStatus;
  label: string;
  action: string;
  destructive: boolean;
}

export interface ResearchClaim {
  id: number;
  research_report_id: number;
  claim: string;
  status: ResearchClaimStatus;
  status_label: string;
  importance: ResearchClaimImportance;
  importance_label: string;
  sources?: ResearchSource[];
  created_at?: string;
  updated_at?: string;
}

export interface ResearchSource {
  id: number;
  research_report_id: number;
  title: string;
  url: string;
  domain?: string | null;
  source_type: SourceType;
  source_type_label: string;
  claims?: ResearchClaim[];
  published_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface ResearchReport {
  id: number;
  project_id: number;
  project?: { id: number; title: string } | null;
  status: ResearchStatus;
  status_label: string;
  allowed_transitions: ResearchTransition[];
  summary?: string | null;
  researched_at?: string | null;
  claims: ResearchClaim[];
  sources: ResearchSource[];
  created_at?: string;
  updated_at?: string;
}

export interface UpdateResearchPayload {
  summary?: string | null;
  researched_at?: string | null;
}

export interface SourceFormData {
  title: string;
  url: string;
  domain?: string | null;
  source_type: SourceType;
  published_at?: string | null;
}

export interface ClaimFormData {
  claim: string;
  status?: ResearchClaimStatus;
  importance?: ResearchClaimImportance;
}

export type ResearchQualitySeverity = 'blocker' | 'warning';

export interface ResearchQualityIssue {
  code: string;
  severity: ResearchQualitySeverity;
  claim_id?: number | null;
  message: string;
}

export interface ResearchQualitySummary {
  total_claims: number;
  supported_claims: number;
  unverified_claims: number;
  uncertain_claims: number;
  contradicted_claims: number;
  claims_with_evidence: number;
  claims_without_evidence: number;
  important_claims: number;
  important_claims_ready: number;
}

export interface ResearchQuality {
  ready: boolean;
  score: number;
  summary: ResearchQualitySummary;
  issues: ResearchQualityIssue[];
}

export interface ResearchDiscoveryRequest {
  query: string;
  max_results?: number;
  provider?: string;
}

export interface SearchResult {
  title: string;
  url: string;
  snippet?: string | null;
  domain: string;
  published_at?: string | null;
}

export interface SearchResponse {
  provider: string;
  query: string;
  results: SearchResult[];
}

export type PipelineActionPriority = 'high' | 'medium';

export interface PipelineAction {
  code: string;
  priority: PipelineActionPriority;
  message: string;
}

export interface ResearchPipelineSummary {
  total_sources: number;
  total_claims: number;
  claims_with_evidence: number;
  claims_without_evidence: number;
  unresolved_claims: number;
  progress_components: {
    sources: number;
    claims: number;
    evidence: number;
    quality: number;
  };
}

export interface ResearchPipeline {
  stage: string;
  stage_label: string;
  progress: number;
  ready_for_script: boolean;
  next_actions: PipelineAction[];
  summary: ResearchPipelineSummary;
}
