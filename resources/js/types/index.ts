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
  asset_requirements?: AssetRequirement[];
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

export type AssetRequirementType =
  | 'video'
  | 'image'
  | 'audio'
  | 'graphic'
  | 'screen_recording'
  | 'other';

export type AssetRequirementStatus = 'pending' | 'searching' | 'fulfilled' | 'skipped';

export type AssetRequirementAspectRatio = '9:16' | '16:9' | '1:1' | '4:5';

export interface AssetRequirementTransition {
  status: AssetRequirementStatus;
  label: string;
  action: string;
  destructive: boolean;
}

export interface AssetRequirement {
  id: number;
  visual_plan_item_id: number;
  requirement_type: AssetRequirementType;
  requirement_type_label: string;
  search_query?: string | null;
  description: string;
  target_duration_seconds?: number | null;
  aspect_ratio?: AssetRequirementAspectRatio | null;
  status: AssetRequirementStatus;
  status_label: string;
  allowed_transitions: AssetRequirementTransition[];
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface AssetRequirementFormData {
  requirement_type: AssetRequirementType;
  search_query?: string | null;
  description: string;
  target_duration_seconds?: number | null;
  aspect_ratio?: AssetRequirementAspectRatio | null;
  notes?: string | null;
}

export interface AssetRequirementGenerationSummary {
  created: number;
  existing: number;
  skipped: number;
}

export type VisualPlanQualitySeverity = 'blocker' | 'warning' | 'info';

/**
 * One readiness issue. Blockers and warnings only ever contain failures, so
 * there is no `passed` flag: the list itself is the problem.
 */
export interface VisualPlanQualityIssue {
  code: string;
  severity: VisualPlanQualitySeverity;
  message: string;
  visual_plan_item_id: number | null;
  asset_requirement_id: number | null;
}

export interface VisualPlanQualitySummary {
  total_items: number;
  items_with_requirements: number;
  items_without_requirements: number;
  total_requirements: number;
  valid_requirements: number;
  invalid_requirements: number;
  pending_requirements: number;
  searching_requirements: number;
  fulfilled_requirements: number;
  skipped_requirements: number;
  blocker_count: number;
  warning_count: number;
}

export interface VisualPlanQualityResult {
  visual_plan_id: number;
  script_version_id: number;
  status: VisualPlanStatus;
  ready: boolean;
  score: number;
  summary: VisualPlanQualitySummary;
  blockers: VisualPlanQualityIssue[];
  warnings: VisualPlanQualityIssue[];
  info: VisualPlanQualityIssue[];
}

export interface AssetFulfillmentQualitySummary {
  total_requirements: number;
  fulfilled_requirements: number;
  unfulfilled_requirements: number;
  invalid_fulfillments: number;
  blocker_count: number;
  warning_count: number;
}

export interface AssetFulfillmentQualityItem {
  id: number;
  order: number;
  narration_text: string;
  visual_prompt: string;
  requirement_count: number;
  fulfilled_count: number;
  blockers: VisualPlanQualityIssue[];
  ready: boolean;
}

export interface AssetFulfillmentQualityResult {
  visual_plan_id: number;
  script_version_id: number;
  status: VisualPlanStatus;
  ready: boolean;
  score: number;
  summary: AssetFulfillmentQualitySummary;
  items: AssetFulfillmentQualityItem[];
  blockers: VisualPlanQualityIssue[];
  warnings: VisualPlanQualityIssue[];
  issues: VisualPlanQualityIssue[];
}

/**
 * One production manifest entry: the exact selected asset a future production
 * phase would consume for one requirement. There is no file_path and no URL
 * here on purpose; production resolves the file from asset_id.
 */
export interface AssetManifestEntry {
  visual_plan_item_id: number;
  visual_plan_item_order: number;
  asset_requirement_id: number;
  requirement_type: AssetRequirementType;
  asset_id: number;
  asset_type: AssetType;
  file_name: string | null;
  mime_type: string | null;
  duration_seconds: number | null;
  width: number | null;
  height: number | null;
  aspect_ratio: number | null;
  target_duration_seconds: number | null;
  status: AssetRequirementStatus;
}

export interface AssetManifestSummary {
  total_requirements: number;
  valid_entries: number;
  invalid_requirements: number;
}

export interface AssetProductionManifestResult {
  visual_plan_id: number;
  script_version_id: number;
  status: VisualPlanStatus;
  ready: boolean;
  summary: AssetManifestSummary;
  entries: AssetManifestEntry[];
  issues: VisualPlanQualityIssue[];
}

/**
 * The readiness layer an aggregated handoff issue came from. The handoff does
 * not invent issues; every one is a verbatim copy of an upstream blocker or
 * warning, tagged so the UI can say which authority to fix.
 */
export type ProductionHandoffSource = 'planning' | 'asset_readiness' | 'production_manifest';

export interface ProductionHandoffIssue extends VisualPlanQualityIssue {
  source: ProductionHandoffSource;
}

export interface ProductionHandoffSummary {
  planning_blockers: number;
  asset_blockers: number;
  manifest_blockers: number;
  total_blockers: number;
  total_warnings: number;
  production_entries: number;
}

/**
 * The single production handoff decision: planning readiness, asset
 * readiness, and the production asset manifest composed into one answer.
 * `ready` means a future production phase has everything it needs; it never
 * means anything has been rendered, queued, or published.
 */
export interface ProductionHandoffResult {
  visual_plan_id: number;
  script_version_id: number;
  status: VisualPlanStatus;
  ready: boolean;
  summary: ProductionHandoffSummary;
  issues: ProductionHandoffIssue[];
}

/**
 * One production segment: a valid manifest entry enriched with the planned
 * duration of the item it belongs to. It carries ids, types, and durations
 * only - never a file path or URL. There is deliberately no timeline start,
 * offset, transition, or render setting; those belong to a later phase.
 */
export interface ProductionSpecificationSegment {
  visual_plan_item_id: number;
  visual_plan_item_order: number;
  asset_requirement_id: number;
  requirement_type: AssetRequirementType;
  asset_id: number;
  asset_type: AssetType;
  duration_seconds: number | null;
  target_duration_seconds: number | null;
  planned_duration_seconds: number | null;
}

export interface ProductionSpecificationSummary {
  segment_count: number;
  total_target_duration_seconds: number;
  total_planned_duration_seconds: number;
}

/**
 * The read-only production specification: the ordered production inputs a
 * future timeline/render phase will consume. `ready` and `issues` are copied
 * from the handoff; the segments mirror the manifest. Nothing is persisted and
 * nothing is rendered.
 */
export interface ProductionSpecificationResult {
  visual_plan_id: number;
  script_version_id: number;
  status: VisualPlanStatus;
  ready: boolean;
  specification: ProductionSpecificationSummary;
  segments: ProductionSpecificationSegment[];
  issues: ProductionHandoffIssue[];
}

export type AssetType = 'video' | 'image' | 'audio' | 'other';

export type AssetStatus = 'pending' | 'available' | 'processing' | 'approved' | 'rejected' | 'archived';

/** One transition the server will accept, with the label to put on its button. */
export interface AssetStatusTransition {
  status: AssetStatus;
  label: string;
}

/**
 * Asset metadata only. There is no file behind any of these fields yet: a null
 * file_name or mime_type is the normal state, and `status` describes where the
 * asset sits in the media pipeline rather than a verified file.
 */
/**
 * Whether the server has been able to measure the file.
 *
 * `null` means no analysis has run yet, which is a different thing from a
 * failed one and is kept distinct so the UI can suggest the Analyze action
 * for the first and report the reason for the second.
 */
export type AssetMetadataStatus = 'pending' | 'completed' | 'failed';

export interface Asset {
  id: number;
  content_project_id: number;
  type: AssetType;
  type_label: string;
  status: AssetStatus;
  status_label: string;
  /**
   * The transitions the server's state machine permits from the current status.
   *
   * This is the list the UI offers, not the list the server will accept: the
   * server re-checks every transition, so a stale list can only cost the user a
   * rejected click. An empty list means the asset has nowhere left to go.
   */
  allowed_transitions?: AssetStatusTransition[];
  title?: string | null;
  description?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  duration_seconds?: number | null;
  width?: number | null;
  height?: number | null;
  aspect_ratio?: number | null;
  metadata_status?: AssetMetadataStatus | null;
  metadata_status_label?: string | null;
  metadata_error?: string | null;
  metadata_analyzed_at?: string | null;
  has_file?: boolean;
  preview_url?: string | null;
  source_url?: string | null;
  source_name?: string | null;
  license_type?: string | null;
  attribution?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * `status` is absent on purpose. The server decides it, and an update can
 * never move an asset along its lifecycle.
 *
 * `aspect_ratio` and every `metadata_*` field are absent for the same reason:
 * they are only ever measured, never declared.
 *
 * The file fields below are different. They are how an asset is catalogued
 * before anything has been uploaded to it, and they carry a declared
 * description of a file that may not exist here yet. Once a file is attached
 * the server measures these itself, and the form stops sending them.
 */
export interface AssetFormData {
  type: AssetType;
  title?: string | null;
  description?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  duration_seconds?: number | null;
  width?: number | null;
  height?: number | null;
  source_url?: string | null;
  source_name?: string | null;
  license_type?: string | null;
  attribution?: string | null;
  notes?: string | null;
}

export type AssetSortField =
  | 'created_at'
  | 'updated_at'
  | 'title'
  | 'file_size'
  | 'duration_seconds';

export type AssetSortDirection = 'asc' | 'desc';

/**
 * Mirrors GetAssetsRequest. Every field is optional and the backend rejects an
 * unknown type, status, or sort field, so these unions are the whole contract:
 * a value outside them is a type error here and a 422 there.
 *
 * `''` is the "All" choice in a filter select. It is not a valid enum value and
 * never reaches the API, because the service maps it to undefined.
 */
export interface AssetFilterParams {
  page?: number;
  per_page?: number;
  search?: string;
  type?: AssetType | '';
  status?: AssetStatus | '';
  sort?: AssetSortField;
  direction?: AssetSortDirection;
}

/**
 * The lifecycle of a TTS track, mirroring the backend enum. Pending and
 * generating are transient; completed has a stored asset; failed keeps a safe,
 * displayable error; archived keeps its asset and its file but is no longer a
 * candidate for current audio.
 */
export type TtsTrackStatus = 'pending' | 'generating' | 'completed' | 'failed' | 'archived';

/**
 * One synthesis history entry for a visual plan item.
 *
 * narration_snapshot is the exact narration the track was spoken from and
 * narration_hash is its fingerprint; neither reveals a storage path. Audio is
 * reached only through preview_url, which is the same authenticated asset
 * preview route the asset library uses. is_stale is null when the owning plan
 * item is not loaded (a track viewed outside its plan), otherwise true when the
 * item's current narration differs from what this track was spoken from.
 */
export interface TtsTrack {
  id: number;
  visual_plan_item_id: number;
  asset_id: number | null;
  provider: string;
  model: string | null;
  voice: string;
  language: string | null;
  speed: number | null;
  status: TtsTrackStatus;
  status_label: string;
  duration_seconds: number | null;
  narration_snapshot: string;
  narration_hash: string;
  is_stale: boolean | null;
  error: string | null;
  preview_url: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface TtsTrackList {
  tracks: TtsTrack[];
  current_narration_hash: string | null;
}

/**
 * Only the fields a user can meaningfully choose about a synthesis. provider
 * and model are deliberately absent: the backend answers for them, so the
 * client never overrides the registered default provider.
 */
export interface TtsGeneratePayload {
  voice: string;
  speed?: number;
  language?: string;
}

/**
 * One readiness/manifest issue. Blockers and warnings only ever contain
 * failures, so there is no passed flag: the presence of an entry is the
 * problem. visual_plan_item_id and order are only present on item-scoped issues.
 */
export interface TtsIssue {
  code: string;
  message: string;
  visual_plan_item_id?: number | null;
  order?: number | null;
}

export interface TtsProviderInfo {
  name: string;
  available: boolean;
}

export interface TtsReadinessInfo {
  total_items: number;
  ready_items: number;
  items_without_current_audio: number;
  completed_tracks: number;
  provider: TtsProviderInfo;
}

/**
 * The readiness report for one script version. The backend owns every rule:
 * this is a display-only projection and the UI never re-derives a decision
 * from it.
 */
export interface TtsReadiness {
  ready: boolean;
  blockers: TtsIssue[];
  warnings: TtsIssue[];
  info: TtsReadinessInfo;
}

/**
 * The current-audio segment of a manifest item.
 *
 * available mirrors the readiness per-item flag. When not available the rest of
 * the fields are absent; when available but the stored metadata is unverified,
 * duration_seconds and mime_type stay null and METADATA_NOT_VERIFIED surfaces
 * as a warning. Asset id and the verified-only fields are never a storage path.
 */
export interface TtsManifestAudio {
  available: boolean;
  tts_track_id?: number | null;
  asset_id?: number | null;
  duration_seconds?: number | null;
  mime_type?: string | null;
  provider?: string;
  model?: string | null;
  voice?: string;
  language?: string | null;
  speed?: number | null;
}

export interface TtsManifestItem {
  order: number;
  visual_plan_item_id: number;
  narration: {
    hash: string;
  };
  audio: TtsManifestAudio;
  blockers: TtsIssue[];
  warnings: TtsIssue[];
}

export interface TtsManifestInfo {
  total_items: number;
  items_with_audio: number;
  items_without_audio: number;
}

/**
 * The TTS production manifest for one script version. visual_plan_id is null
 * while the version has no visual plan, which surfaces as a NO_VISUAL_PLAN
 * blocker and an empty item list rather than an error.
 */
export interface TtsManifest {
  ready: boolean;
  script_version_id: number;
  visual_plan_id: number | null;
  items: TtsManifestItem[];
  blockers: TtsIssue[];
  warnings: TtsIssue[];
  info: TtsManifestInfo;
}

