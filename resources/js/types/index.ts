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

export interface ResearchGenerationRequest {
  provider?: string;
  topic: string;
  question: string;
  context?: string | null;
  max_claims?: number;
}

export interface ResearchGenerationMetadata {
  provider: string;
  model: string | null;
  prompt_profile: string;
  prompt_version: string;
  status: string;
  research_report_id: number;
}

export interface ResearchGenerationResult {
  report: ResearchReport;
  summary: string | null;
  generated_claims: ResearchClaim[];
  generated_sources: ResearchSource[];
  generation: ResearchGenerationMetadata;
  quality: ResearchQuality;
  warning: string;
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

export type ResearchClaimClassification = 'usable' | 'requires_verification' | 'contradicted' | 'unsupported';

export interface ResearchScriptContextSource {
  id: number;
  title: string;
  domain: string;
  url: string;
  source_type: string;
}

export interface ResearchScriptContextClaim {
  id: number;
  claim: string;
  importance: ResearchClaimImportance;
  status: ResearchClaimStatus;
  classification: ResearchClaimClassification;
  has_evidence: boolean;
  sources: ResearchScriptContextSource[];
}

export interface ResearchScriptContextCounts {
  total_claims: number;
  usable_claims: number;
  claims_requiring_verification: number;
  contradicted_claims: number;
  unsupported_claims: number;
  evidence_backed_claims: number;
}

export interface ResearchScriptContextReport {
  id: number;
  summary: string | null;
  status: ResearchStatus;
  quality_ready: boolean;
  counts: ResearchScriptContextCounts;
}

export interface ResearchScriptContextQuality {
  ready: boolean;
  score: number;
  summary: ResearchQualitySummary;
  blockers: ResearchQualityIssue[];
  warnings: ResearchQualityIssue[];
}

export interface ResearchScriptContextPipeline {
  stage: string;
  stage_label: string;
  progress: number;
  ready_for_script: boolean;
  next_actions: PipelineAction[];
}

export interface ResearchScriptContext {
  report: ResearchScriptContextReport;
  claims: ResearchScriptContextClaim[];
  usable_claims: ResearchScriptContextClaim[];
  claims_requiring_verification: ResearchScriptContextClaim[];
  contradicted_claims: ResearchScriptContextClaim[];
  unsupported_claims: ResearchScriptContextClaim[];
  quality: ResearchScriptContextQuality;
  pipeline: ResearchScriptContextPipeline;
}

export type ScriptStatus = 'draft' | 'review' | 'approved' | 'archived';

export interface ScriptTransition {
  status: ScriptStatus;
  label: string;
  action: string;
  destructive: boolean;
}

export interface ScriptVersion {
  id: number;
  script_id: number;
  version: number;
  title?: string | null;
  hook: string;
  body: string;
  closing?: string | null;
  duration_seconds?: number | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface Script {
  id: number;
  project_id: number;
  project?: { id: number; title: string } | null;
  status: ScriptStatus;
  status_label: string;
  allowed_transitions: ScriptTransition[];
  current_version: ScriptVersion | null;
  version_count: number;
  created_at?: string;
  updated_at?: string;
}

export interface ScriptFormData {
  title?: string | null;
  hook: string;
  body: string;
  closing?: string | null;
  duration_seconds?: number | null;
  notes?: string | null;
}

export type ScriptQualitySeverity = 'blocker' | 'warning' | 'info';

export type ScriptClaimMatchState = 'supported_by_text' | 'not_detected' | 'insufficient_text';

export interface ScriptQualityCheck {
  code: string;
  severity: ScriptQualitySeverity;
  passed: boolean;
  message: string;
  details?: Record<string, unknown>;
}

export interface ScriptQualitySummary {
  total_checks: number;
  passed_checks: number;
  failed_checks: number;
  blocker_count: number;
  warning_count: number;
  important_claims: number;
  aligned_important_claims: number;
}

export interface ScriptClaimAlignment {
  claim_id: number;
  importance: string;
  status: string;
  matched: boolean;
  match_state: ScriptClaimMatchState;
  match_score: number;
  message: string;
}

export interface ScriptQualityResult {
  script_id: number;
  version_id: number | null;
  version: number | null;
  status: string;
  ready: boolean;
  score: number;
  summary: ScriptQualitySummary;
  blockers: ScriptQualityCheck[];
  warnings: ScriptQualityCheck[];
  checks: ScriptQualityCheck[];
  claim_alignment: ScriptClaimAlignment[];
}

export interface ScriptGenerationRequest {
  provider?: string;
  topic: string;
  language?: string;
  tone?: string;
  format?: string;
  target_duration_seconds?: number;
  hook_style?: string;
  instructions?: string;
}

export interface ScriptGenerationMetadata {
  provider: string;
  model: string | null;
  prompt_profile: string;
  prompt_version: string;
  status: string;
  source_version_id: number | null;
  generated_version_id: number | null;
}

export interface ScriptGenerationResult {
  script: Script;
  generated_version: ScriptVersion;
  generation: ScriptGenerationMetadata;
  quality: ScriptQualityResult;
}

export interface ScriptRevisionRequest {
  hook?: string;
  body?: string;
  closing?: string;
}

export interface ScriptRevisionResult {
  script: Script;
  version: ScriptVersion;
  quality: ScriptQualityResult;
  traceability: ScriptTraceabilitySummary;
}

export interface ScriptVersionResearchClaim {
  id: number;
  claim: string;
  status: ResearchClaimStatus;
  status_label: string;
  importance: ResearchClaimImportance;
  importance_label: string;
  has_evidence: boolean;
  sources?: ResearchSource[];
}

export interface ScriptTraceabilitySummary {
  total_claims: number;
  supported_claims: number;
  unverified_claims: number;
  uncertain_claims: number;
  contradicted_claims: number;
  claims_with_evidence: number;
  claims_without_evidence: number;
  traceability_complete: boolean;
  warnings: string[];
}

export interface ScriptTraceabilityData {
  items: ScriptVersionResearchClaim[];
  traceability: ScriptTraceabilitySummary;
}

export type VisualPlanStatus = 'draft' | 'review' | 'approved' | 'archived';

export interface VisualPlanTransition {
  status: VisualPlanStatus;
  label: string;
  action: string;
  destructive: boolean;
}

export type VisualPlanSection = 'hook' | 'body' | 'closing' | 'other';

export type VisualPlanItemType =
  | 'animal_clip'
  | 'stock_video'
  | 'photo'
  | 'screen_recording'
  | 'graphic'
  | 'text'
  | 'b_roll'
  | 'other';

export interface VisualPlanItem {
  id: number;
  visual_plan_id: number;
  order: number;
  section: VisualPlanSection;
  section_label: string;
  narration_text: string;
  visual_type: VisualPlanItemType;
  visual_type_label: string;
  visual_prompt: string;
  duration_seconds: number;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface VisualPlan {
  id: number;
  content_project_id: number;
  script_version_id: number;
  status: VisualPlanStatus;
  status_label: string;
  allowed_transitions: VisualPlanTransition[];
  title?: string | null;
  notes?: string | null;
  items: VisualPlanItem[];
  created_at?: string;
  updated_at?: string;
}

export interface VisualPlanFormData {
  title?: string | null;
  notes?: string | null;
  create_from_script?: boolean;
}

export interface VisualPlanItemFormData {
  section: VisualPlanSection;
  narration_text: string;
  visual_type: VisualPlanItemType;
  visual_prompt: string;
  duration_seconds: number;
  notes?: string | null;
}

export interface VisualPlanItemOrder {
  id: number;
  order: number;
}
