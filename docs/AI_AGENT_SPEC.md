# MochyFami Content Studio --- Phase 0 Technical Specification {#mochyfami-content-studio--phase-0-technical-specification}

> **Project:** MochyFami Content Studio\
> **Phase:** 0 --- Technical Blueprint\
> **Purpose:** Reference document for AI coding agents / vibe coding\
> **Status:** Ready for Phase 1 implementation\
> **Date:** 2026-09-23

------------------------------------------------------------------------

## 1. Document Purpose {#1-document-purpose}

This document is the technical source of truth for AI coding agents
working on **MochyFami Content Studio**.

The application is an internal creator workstation for producing
faceless YouTube Shorts about cats and other animals. It is designed to
automate repetitive work while keeping the human creator responsible for
topic selection, factual review, creative review, asset decisions, and
final approval.

Core principle:

``` text
AI assists production.
Human controls publication.
```

The agent must read this document before making architectural changes.

### Agent rules

1.  Inspect the repository before changing code.
2.  Determine the current project phase before implementing anything.
3.  Make the smallest change that satisfies the requested task.
4.  Do not rewrite working code without a concrete reason.
5.  Do not invent provider APIs, package APIs, database fields, or
    capabilities.
6.  Do not expose secrets.
7.  Validate backend input even when frontend validation exists.
8.  Run relevant tests/build checks after meaningful changes.
9.  Report exactly what changed and what was verified.
10. Do not silently jump to another development phase.

------------------------------------------------------------------------

# 2. Product Vision {#2-product-vision}

## 2.1 Name {#21-name}

**MochyFami Content Studio**

Short name: **MochyFami Studio**

## 2.2 Goal {#22-goal}

Turn the current manual workflow into a controlled production pipeline:

``` text
IDEA
 ↓
RESEARCH
 ↓
FACT CHECK
 ↓
SCRIPT
 ↓
VISUAL PLAN
 ↓
ASSETS
 ↓
TTS
 ↓
SUBTITLE
 ↓
VIDEO RENDER
 ↓
THUMBNAIL
 ↓
HUMAN REVIEW
 ↓
APPROVED
 ↓
PUBLISHED
 ↓
ANALYTICS
```

The goal is **not** to mass-produce generic AI videos. The goal is to
reduce repetitive work so the creator can spend more time on content
quality.

## 2.3 MVP outcome {#23-mvp-outcome}

The first useful version should allow the creator to:

-   manage content ideas;
-   create production projects;
-   research a topic;
-   store sources and claims;
-   generate a TTS-friendly script;
-   plan visuals;
-   manage licensed/permissioned assets;
-   generate TTS;
-   generate subtitles;
-   render a basic 9:16 video with FFmpeg;
-   generate/select a thumbnail;
-   perform a human QC review;
-   approve the project.

Automatic YouTube publishing and advanced analytics are later phases.

------------------------------------------------------------------------

# 3. Product Principles {#3-product-principles}

## 3.1 Human-in-the-loop {#31-human-in-the-loop}

AI generation must never equal publication.

``` text
AI GENERATED
 ↓
HUMAN REVIEW
 ↓
APPROVED
 ↓
PUBLISH
```

## 3.2 Source traceability {#32-source-traceability}

Important factual claims should be traceable to sources. External assets
should record their source and license information when known.

## 3.3 Anti-repetition {#33-anti-repetition}

The system should warn about similar topics, titles, hooks, and scripts.
Similarity is a warning, not an automatic final decision.

## 3.4 Provider independence {#34-provider-independence}

AI, search, TTS, image generation, storage, and publishing integrations
must be behind application-level interfaces. Avoid provider lock-in.

## 3.5 Local-first {#35-local-first}

Build and test locally on Windows first. Cloud deployment comes later.

------------------------------------------------------------------------

# 4. Technology Stack {#4-technology-stack}

  Layer             Technology                       Notes
  ----------------- -------------------------------- -------------------------------------------------
  Backend           Laravel 13                       API backend and application services
  PHP               PHP 8.3+                         Use the currently supported installed version
  Frontend          React + TypeScript               SPA-style creator dashboard
  Build             Vite                             Frontend development/build
  Styling           Tailwind CSS                     UI styling
  Database          MySQL 8.x                        UTF-8 / utf8mb4
  Auth              Laravel Sanctum                  API authentication
  Queue             Laravel Queue                    Database queue initially; Redis later if needed
  Media             FFmpeg                           Local render engine
  AI                Provider abstraction             Do not hard-code provider assumptions
  TTS               Provider abstraction             Voice generation
  Storage           Laravel Filesystem               Local first; cloud later
  Tests             PHPUnit/Pest + frontend checks   Follow project configuration
  Version control   Git                              Frequent small commits

Laravel 13 is the current major Laravel release in this specification.
Check the official Laravel documentation when installation details or
framework APIs may have changed.

Official references:

-   <https://laravel.com/docs>
-   <https://laravel.com/docs/13.x/routing>
-   <https://laravel.com/docs/13.x/structure>
-   <https://laravel.com/docs/13.x/sanctum>
-   <https://laravel.com/docs/13.x/queues>
-   <https://react.dev/>
-   <https://react.dev/learn/managing-state>
-   <https://react.dev/learn/creating-a-react-app>
-   <https://ffmpeg.org/documentation.html>

------------------------------------------------------------------------

# 5. High-Level Architecture {#5-high-level-architecture}

``` text
┌──────────────────────────────────────────────┐
│                React Frontend                │
│                                              │
│ Dashboard / Ideas / Projects / Research      │
│ Scripts / Assets / Production / Review       │
└──────────────────────┬───────────────────────┘
                       │ JSON API
                       ▼
┌──────────────────────────────────────────────┐
│               Laravel Backend                │
│                                              │
│ Controllers / Requests / Resources           │
│ Services / Policies / Jobs / Events          │
└──────────┬─────────────┬─────────────┬───────┘
           │             │             │
           ▼             ▼             ▼
        MySQL        AI Providers   External APIs
           │             │             │
           │             ├─ Research  ├─ Search
           │             ├─ Script    ├─ TTS
           │             ├─ Image     └─ YouTube later
           │             └─ Analysis
           ▼
     Laravel Storage
           │
           ▼
        FFmpeg
           │
           ▼
     Final MP4 / Audio / Captions
```

The **content project** is the center of the domain. AI is a service
used by projects, not the center of the database.

------------------------------------------------------------------------

# 6. Repository / Folder Structure {#6-repository--folder-structure}

Recommended Laravel + React structure:

``` text
mochyfami-studio/
│
├── app/
│   ├── Console/
│   ├── Enums/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Jobs/
│   │   ├── Research/
│   │   ├── Script/
│   │   ├── Media/
│   │   └── Production/
│   ├── Models/
│   ├── Policies/
│   ├── Services/
│   │   ├── AI/
│   │   ├── Research/
│   │   ├── TTS/
│   │   ├── Media/
│   │   ├── Production/
│   │   └── Publishing/
│   └── Providers/
│
├── bootstrap/
├── config/
│   ├── ai.php
│   ├── media.php
│   ├── mochyfami.php
│   └── services.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
│   ├── js/
│   │   ├── app/
│   │   ├── components/
│   │   │   ├── ui/
│   │   │   ├── layout/
│   │   │   ├── forms/
│   │   │   ├── media/
│   │   │   └── feedback/
│   │   ├── features/
│   │   │   ├── dashboard/
│   │   │   ├── ideas/
│   │   │   ├── projects/
│   │   │   ├── research/
│   │   │   ├── scripts/
│   │   │   ├── assets/
│   │   │   ├── production/
│   │   │   ├── review/
│   │   │   ├── thumbnails/
│   │   │   └── analytics/
│   │   ├── hooks/
│   │   ├── layouts/
│   │   ├── lib/
│   │   ├── pages/
│   │   ├── services/
│   │   ├── types/
│   │   └── main.tsx
│   └── css/
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
├── storage/
│   ├── app/
│   │   ├── assets/
│   │   ├── audio/
│   │   ├── subtitles/
│   │   ├── renders/
│   │   ├── thumbnails/
│   │   └── temp/
│   └── logs/
├── tests/
│   ├── Feature/
│   └── Unit/
├── .env
├── .env.example
├── composer.json
├── package.json
├── vite.config.ts
└── README.md
```

Keep the structure close to Laravel conventions. Laravel\'s official
structure includes dedicated locations for HTTP code, models, jobs,
providers, database, routes, storage, and tests.

------------------------------------------------------------------------

# 7. React Page Map {#7-react-page-map}

Main navigation:

``` text
/dashboard
/ideas
/ideas/new
/ideas/:id
/projects
/projects/new
/projects/:id
/projects/:id/research
/projects/:id/script
/projects/:id/assets
/projects/:id/production
/projects/:id/review
/assets
/settings
/settings/ai
/settings/tts
/settings/production
/settings/brand
```

Future:

``` text
/analytics
/projects/:id/publishing
```

## Dashboard

Show:

-   total ideas;
-   active projects;
-   projects needing review;
-   rendered videos;
-   published count;
-   recent projects;
-   production queue;
-   warnings.

## Ideas

Features:

-   search;
-   filter by category/status/format;
-   create;
-   edit;
-   archive;
-   convert to project.

## Project Detail

Use tabs:

``` text
Overview
Research
Script
Visual Plan
Assets
Audio
Video
Thumbnail
Review
Publish
```

The project detail page is the main production workspace.

------------------------------------------------------------------------

# 8. Frontend Architecture {#8-frontend-architecture}

React should use feature-oriented organization.

``` text
resources/js/
├── app/
│   ├── router.tsx
│   └── providers.tsx
├── components/
├── features/
├── hooks/
├── layouts/
├── lib/
│   ├── api.ts
│   ├── utils.ts
│   └── validation.ts
├── pages/
├── services/
├── types/
└── main.tsx
```

### State rules

Prefer:

``` text
Server data → API/query layer
Local UI state → useState/useReducer
Shared UI state → Context only when justified
```

Do not duplicate the same server state in several global stores. Keep
state ownership clear.

------------------------------------------------------------------------

# 9. Database ERD {#9-database-erd}

``` mermaid
erDiagram
    USERS ||--o{ CONTENT_IDEAS : creates
    USERS ||--o{ CONTENT_PROJECTS : owns
    USERS ||--o{ ASSETS : uploads
    USERS ||--o{ QUALITY_CHECKS : performs

    CONTENT_CATEGORIES ||--o{ CONTENT_IDEAS : categorizes
    CONTENT_CATEGORIES ||--o{ CONTENT_PROJECTS : categorizes

    CONTENT_IDEAS ||--o| CONTENT_PROJECTS : becomes

    CONTENT_PROJECTS ||--o| RESEARCH_REPORTS : has
    RESEARCH_REPORTS ||--o{ RESEARCH_CLAIMS : contains
    RESEARCH_CLAIMS }o--o{ SOURCES : supported_by

    CONTENT_PROJECTS ||--o{ SCRIPTS : has
    SCRIPTS ||--o{ SCRIPT_VERSIONS : versions

    CONTENT_PROJECTS ||--o| VISUAL_PLANS : has
    VISUAL_PLANS ||--o{ VISUAL_PLAN_ITEMS : contains

    CONTENT_PROJECTS ||--o{ PROJECT_ASSETS : uses
    ASSETS ||--o{ PROJECT_ASSETS : attached_to

    CONTENT_PROJECTS ||--o{ AUDIO_TRACKS : has
    CONTENT_PROJECTS ||--o{ SUBTITLE_TRACKS : has

    CONTENT_PROJECTS ||--o{ RENDER_JOBS : renders
    CONTENT_PROJECTS ||--o{ VIDEOS : outputs
    CONTENT_PROJECTS ||--o{ THUMBNAILS : has

    CONTENT_PROJECTS ||--o{ QUALITY_CHECKS : reviewed
    CONTENT_PROJECTS ||--o| PUBLISHING_RECORDS : publishes
    CONTENT_PROJECTS ||--o{ ANALYTICS_SNAPSHOTS : measures
    CONTENT_PROJECTS ||--o{ AI_GENERATIONS : generates
```

------------------------------------------------------------------------

# 10. Database Tables {#10-database-tables}

## 10.1 users {#101-users}

Use Laravel\'s standard users table.

``` text
id
name
email
password
remember_token
created_at
updated_at
```

## 10.2 content_categories {#102-content_categories}

``` text
id
name
slug
description
color
is_active
created_at
updated_at
```

Seed categories:

``` text
Cat Behavior
Cat Body Language
Cat Biology
Cat POV
Cat Funny Facts
Other Pets
Birds
Wild Animals
Ocean Animals
Weird Animal Facts
```

## 10.3 content_ideas {#103-content_ideas}

``` text
id
category_id
title
slug
hook
concept
format
status
priority
notes
source_idea
created_by
created_at
updated_at
```

Formats:

``` text
educational
pov
storytelling
funny_fact
comparison
list
```

Idea statuses:

``` text
idea
selected
converted
archived
```

## 10.4 content_projects {#104-content_projects}

Central production entity.

``` text
id
content_idea_id
category_id
title
slug
status
target_duration_seconds
language
tone
hook
description
current_step
progress_percent
created_by
created_at
updated_at
```

Future-only fields should not be added early unless needed:

``` text
scheduled_at
published_at
youtube_video_id
```

## 10.5 research_reports {#105-research_reports}

``` text
id
content_project_id
status
research_question
summary
raw_response
fact_check_status
created_at
updated_at
```

Statuses:

``` text
pending
researching
completed
needs_review
approved
failed
```

## 10.6 research_claims {#106-research_claims}

``` text
id
research_report_id
claim
importance
confidence
status
notes
created_at
updated_at
```

Claim statuses:

``` text
unverified
supported
partially_supported
contradicted
needs_review
approved
```

## 10.7 sources {#107-sources}

``` text
id
url
domain
title
author
published_at
source_type
license_note
retrieved_at
metadata
created_at
updated_at
```

Source types:

``` text
official
scientific
veterinary
university
news
reference
community
other
```

One source can support multiple claims. One claim can have multiple
sources.

## 10.8 research_claim_source {#108-research_claim_source}

``` text
research_claim_id
source_id
relationship
notes
```

Relationships:

``` text
supports
context
contradicts
```

## 10.9 scripts {#109-scripts}

``` text
id
content_project_id
title
hook
body
closing
estimated_duration_seconds
word_count
status
current_version
created_at
updated_at
```

Statuses:

``` text
draft
generated
needs_review
approved
rejected
```

## 10.10 script_versions {#1010-script_versions}

``` text
id
script_id
version
body
generation_method
generation_prompt_version
created_by
created_at
```

Approved versions must remain auditable.

## 10.11 visual_plans {#1011-visual_plans}

``` text
id
content_project_id
status
total_duration_seconds
notes
created_at
updated_at
```

## 10.12 visual_plan_items {#1012-visual_plan_items}

``` text
id
visual_plan_id
sequence
start_time
end_time
narration_text
visual_description
asset_type
transition
text_overlay
created_at
updated_at
```

## 10.13 assets {#1013-assets}

``` text
id
uploaded_by
filename
original_filename
storage_path
mime_type
file_size
width
height
duration_seconds
asset_type
source_name
source_url
license
creator_name
license_verified
checksum
metadata
created_at
updated_at
```

Asset types:

``` text
video
image
audio
music
sfx
font
other
```

## 10.14 project_assets {#1014-project_assets}

``` text
id
content_project_id
asset_id
role
sequence
start_time
end_time
crop_mode
notes
created_at
updated_at
```

Roles:

``` text
main_visual
b_roll
background
overlay
music
sfx
thumbnail_source
```

## 10.15 audio_tracks {#1015-audio_tracks}

``` text
id
content_project_id
type
provider
voice
language
storage_path
duration_seconds
speed
volume
status
metadata
created_at
updated_at
```

Types:

``` text
tts
music
sfx
```

## 10.16 subtitle_tracks {#1016-subtitle_tracks}

``` text
id
content_project_id
format
language
storage_path
style
status
created_at
updated_at
```

Formats:

``` text
srt
ass
vtt
```

## 10.17 render_jobs {#1017-render_jobs}

``` text
id
content_project_id
status
resolution
aspect_ratio
fps
codec
quality_preset
command_hash
started_at
finished_at
error_message
metadata
created_at
updated_at
```

Statuses:

``` text
queued
processing
completed
failed
cancelled
```

## 10.18 videos {#1018-videos}

``` text
id
content_project_id
render_job_id
storage_path
filename
duration_seconds
width
height
file_size
version
is_final
created_at
updated_at
```

## 10.19 thumbnails {#1019-thumbnails}

``` text
id
content_project_id
storage_path
prompt
provider
width
height
version
is_selected
created_at
updated_at
```

## 10.20 quality_checks {#1020-quality_checks}

``` text
id
content_project_id
reviewed_by
status
script_check
source_check
asset_check
audio_check
subtitle_check
video_check
thumbnail_check
notes
reviewed_at
created_at
updated_at
```

## 10.21 publishing_records {#1021-publishing_records}

Future table:

``` text
id
content_project_id
platform
platform_video_id
title
description
visibility
published_at
url
status
metadata
created_at
updated_at
```

## 10.22 analytics_snapshots {#1022-analytics_snapshots}

Future table:

``` text
id
content_project_id
platform
snapshot_at
views
likes
comments
shares
subscribers_gained
average_view_duration
average_percentage_viewed
swiped_away_rate
metadata
created_at
updated_at
```

Analytics should report measurements and calculated metrics separately
from AI interpretations.

## 10.23 ai_generations {#1023-ai_generations}

Traceability table for AI operations:

``` text
id
content_project_id
operation
provider
model
prompt_version
input_hash
input_tokens
output_tokens
estimated_cost
status
response_summary
raw_response_path
created_at
updated_at
```

Operations:

``` text
research
fact_check
script
title
description
visual_plan
thumbnail_prompt
similarity_check
quality_check
```

Never store API secrets in this table.

------------------------------------------------------------------------

# 11. Content Workflow / Status Machine {#11-content-workflow--status-machine}

## Idea status

``` text
IDEA → SELECTED → CONVERTED
              ↘ ARCHIVED
```

## Project status

``` text
DRAFT
 ↓
RESEARCHING
 ↓
RESEARCH_REVIEW
 ↓
SCRIPTING
 ↓
SCRIPT_REVIEW
 ↓
ASSET_COLLECTION
 ↓
PRODUCTION
 ↓
VIDEO_REVIEW
 ↓
APPROVED
 ↓
PUBLISHED
```

Revision path:

``` text
VIDEO_REVIEW → REVISION → PRODUCTION
```

Failure can be reached by background jobs:

``` text
any processing state → FAILED
```

The UI must not allow arbitrary status jumps. Use an enum/service for
allowed transitions.

Suggested Laravel enum:

``` text
ContentProjectStatus
```

with methods such as:

``` text
canTransitionTo()
label()
color()
```

------------------------------------------------------------------------

# 12. API Architecture {#12-api-architecture}

Base prefix:

``` text
/api/v1
```

Authentication:

``` text
Sanctum
```

All application data endpoints require authentication.

Laravel\'s API routing is intended for stateless API endpoints, and
Sanctum can protect SPA/API consumers.

## Authentication

``` http
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

## Dashboard

``` http
GET /api/v1/dashboard
GET /api/v1/dashboard/summary
GET /api/v1/dashboard/recent-projects
GET /api/v1/dashboard/production-queue
```

## Ideas

``` http
GET    /api/v1/ideas
POST   /api/v1/ideas
GET    /api/v1/ideas/{idea}
PUT    /api/v1/ideas/{idea}
DELETE /api/v1/ideas/{idea}
POST   /api/v1/ideas/{idea}/select
POST   /api/v1/ideas/{idea}/convert
POST   /api/v1/ideas/{idea}/archive
```

Filters:

``` text
status
category
search
format
priority
```

## Categories

``` http
GET    /api/v1/categories
POST   /api/v1/categories
GET    /api/v1/categories/{category}
PUT    /api/v1/categories/{category}
DELETE /api/v1/categories/{category}
```

## Projects

``` http
GET    /api/v1/projects
POST   /api/v1/projects
GET    /api/v1/projects/{project}
PUT    /api/v1/projects/{project}
DELETE /api/v1/projects/{project}
POST   /api/v1/projects/{project}/transition
POST   /api/v1/projects/{project}/duplicate
```

## Research

``` http
GET  /api/v1/projects/{project}/research
POST /api/v1/projects/{project}/research
POST /api/v1/projects/{project}/research/generate
POST /api/v1/projects/{project}/research/fact-check
GET  /api/v1/projects/{project}/research/claims
POST /api/v1/projects/{project}/research/claims/{claim}/approve
POST /api/v1/projects/{project}/research/claims/{claim}/reject
GET  /api/v1/projects/{project}/sources
POST /api/v1/projects/{project}/sources
```

Research generation must normally be queued.

## Scripts {#scripts}

``` http
GET  /api/v1/projects/{project}/scripts
POST /api/v1/projects/{project}/scripts
GET  /api/v1/projects/{project}/scripts/{script}
PUT  /api/v1/projects/{project}/scripts/{script}
POST /api/v1/projects/{project}/scripts/generate
POST /api/v1/projects/{project}/scripts/{script}/approve
POST /api/v1/projects/{project}/scripts/{script}/duplicate
```

## Visual plan

``` http
GET  /api/v1/projects/{project}/visual-plan
POST /api/v1/projects/{project}/visual-plan
PUT  /api/v1/projects/{project}/visual-plan
POST /api/v1/projects/{project}/visual-plan/generate
GET  /api/v1/projects/{project}/visual-plan/items
POST /api/v1/projects/{project}/visual-plan/items
PUT  /api/v1/projects/{project}/visual-plan/items/{item}
DELETE /api/v1/projects/{project}/visual-plan/items/{item}
```

## Assets {#assets}

``` http
GET    /api/v1/assets
POST   /api/v1/assets
GET    /api/v1/assets/{asset}
PUT    /api/v1/assets/{asset}
DELETE /api/v1/assets/{asset}
POST   /api/v1/projects/{project}/assets
DELETE /api/v1/projects/{project}/assets/{asset}
```

Uploads use multipart form data.

## Audio

``` http
GET    /api/v1/projects/{project}/audio
POST   /api/v1/projects/{project}/audio/tts
POST   /api/v1/projects/{project}/audio/{audio}/regenerate
DELETE /api/v1/projects/{project}/audio/{audio}
```

## Subtitles

``` http
GET    /api/v1/projects/{project}/subtitles
POST   /api/v1/projects/{project}/subtitles/generate
PUT    /api/v1/projects/{project}/subtitles/{subtitle}
DELETE /api/v1/projects/{project}/subtitles/{subtitle}
```

## Production

``` http
GET  /api/v1/projects/{project}/production
POST /api/v1/projects/{project}/render
GET  /api/v1/projects/{project}/renders
GET  /api/v1/projects/{project}/renders/{render}
POST /api/v1/projects/{project}/renders/{render}/cancel
```

Rendering must be asynchronous.

## Thumbnails {#thumbnails}

``` http
GET    /api/v1/projects/{project}/thumbnails
POST   /api/v1/projects/{project}/thumbnails/generate
POST   /api/v1/projects/{project}/thumbnails/{thumbnail}/select
DELETE /api/v1/projects/{project}/thumbnails/{thumbnail}
```

## Review

``` http
GET  /api/v1/projects/{project}/review
POST /api/v1/projects/{project}/review
POST /api/v1/projects/{project}/review/approve
POST /api/v1/projects/{project}/review/request-revision
```

## Publishing --- future {#publishing--future}

``` http
GET  /api/v1/projects/{project}/publishing
POST /api/v1/projects/{project}/publishing
POST /api/v1/projects/{project}/publishing/publish
```

Publishing must require `APPROVED` status.

## Analytics --- future {#analytics--future}

``` http
GET /api/v1/projects/{project}/analytics
GET /api/v1/analytics/overview
GET /api/v1/analytics/topics
GET /api/v1/analytics/categories
```

------------------------------------------------------------------------

# 13. API Response Convention {#13-api-response-convention}

Success:

``` json
{
  "success": true,
  "data": {},
  "message": null
}
```

Validation error:

``` json
{
  "success": false,
  "data": null,
  "message": "Validation failed.",
  "errors": {}
}
```

Pagination:

``` json
{
  "success": true,
  "data": {
    "items": [],
    "meta": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100
    }
  }
}
```

Use Laravel Form Requests and API Resources instead of ad-hoc
validation/serialization inside controllers.

------------------------------------------------------------------------

# 14. Laravel Service Architecture {#14-laravel-service-architecture}

Controllers should be thin.

Preferred pattern:

``` php
public function generate(
    GenerateScriptRequest $request,
    ScriptGenerationService $service
) {
    $result = $service->generate($request->validated());

    return new ScriptResource($result);
}
```

Suggested services:

``` text
app/Services/
├── AI/
│   ├── AIProviderInterface.php
│   ├── AIManager.php
│   ├── PromptManager.php
│   ├── ResearchGenerationService.php
│   ├── FactCheckService.php
│   ├── ScriptGenerationService.php
│   ├── VisualPlanGenerationService.php
│   └── SimilarityService.php
├── Research/
│   ├── ResearchService.php
│   └── SourceService.php
├── TTS/
│   ├── TTSProviderInterface.php
│   └── TTSService.php
├── Media/
│   ├── AssetService.php
│   ├── SubtitleService.php
│   └── MediaMetadataService.php
├── Production/
│   ├── VideoRenderService.php
│   ├── FFmpegService.php
│   └── RenderPresetService.php
└── Publishing/
    └── PublishingService.php
```

------------------------------------------------------------------------

# 15. Queue / Job Architecture {#15-queue--job-architecture}

Long-running operations must not block normal HTTP requests.

Jobs:

``` text
ResearchProjectJob
FactCheckResearchJob
GenerateScriptJob
GenerateVisualPlanJob
GenerateTTSJob
GenerateSubtitleJob
RenderVideoJob
GenerateThumbnailJob
RunQualityCheckJob
SyncAnalyticsJob
```

Jobs should be retryable and preferably idempotent.

Example:

``` text
POST /scripts/generate
        ↓
create ai_generation record
        ↓
dispatch GenerateScriptJob
        ↓
return 202 Accepted
        ↓
frontend polls project/job status
```

For MVP, Laravel\'s database queue is acceptable. Redis can be
introduced when workload requires it.

------------------------------------------------------------------------

# 16. AI Architecture {#16-ai-architecture}

AI prompts must not be embedded in controllers.

Suggested structure:

``` text
app/Services/AI/
├── Prompts/
│   ├── research/
│   ├── fact-check/
│   ├── script/
│   ├── visual-plan/
│   ├── thumbnail/
│   └── quality/
└── PromptManager.php
```

Prompt versions must be explicit:

``` text
research_v1
research_v2
script_v1
script_v2
visual_plan_v1
```

Each AI generation should record the prompt version and model/provider
used.

------------------------------------------------------------------------

# 17. AI Provider Interfaces {#17-ai-provider-interfaces}

Conceptual interface:

``` php
interface AIProviderInterface
{
    public function generateStructured(
        string $systemPrompt,
        string $userPrompt,
        array $schema = []
    ): array;
}
```

Do not assume every provider supports the same structured-output API.
The adapter is responsible for provider-specific behavior.

TTS:

``` php
interface TTSProviderInterface
{
    public function synthesize(
        string $text,
        string $voice,
        array $options = []
    ): TTSResult;
}
```

------------------------------------------------------------------------

# 18. Research AI Prompt Architecture {#18-research-ai-prompt-architecture}

System prompt concept:

``` text
You are the research assistant for MochyFami.

Produce a concise, evidence-aware research brief for a short-form
Indonesian animal content video.

Rules:
- Separate facts from interpretations.
- Do not invent facts.
- Prefer primary, scientific, veterinary, university, or official
  sources when appropriate.
- Identify uncertainty.
- Do not turn speculation into fact.
- Keep claims concise.
- Important factual claims must be traceable to sources.
```

Input:

``` text
Topic: {{topic}}
Category: {{category}}
Target duration: {{duration}}
Existing similar content: {{similar_content}}
```

Expected structured output:

``` json
{
  "topic": "...",
  "summary": "...",
  "claims": [
    {
      "claim": "...",
      "importance": "high",
      "supporting_source_ids": []
    }
  ],
  "uncertainties": [],
  "suggested_angle": "..."
}
```

The backend must validate the structured response before persistence.

------------------------------------------------------------------------

# 19. Fact Check Prompt {#19-fact-check-prompt}

Purpose: assess whether a proposed claim is supported by the collected
research.

Output:

``` json
{
  "claim": "...",
  "status": "supported",
  "confidence": "high",
  "reason": "...",
  "source_ids": []
}
```

Allowed statuses:

``` text
supported
partially_supported
unsupported
contradicted
needs_human_review
```

AI confidence is an assessment, not proof. The UI must make this
distinction clear.

------------------------------------------------------------------------

# 20. MochyFami Writing Profile {#20-mochyfami-writing-profile}

Default profile:

``` text
Language: Indonesian
Tone: casual + funny + educational
Sentence style: short + direct + TTS-friendly
Hook: immediate curiosity
Typical duration: 25–35 seconds
```

Preferred structure:

``` text
HOOK
→ EXPLANATION
→ INTERESTING FACT
→ SHORT CLOSING
```

Avoid:

``` text
"Halo guys"
long introductions
unnecessary filler
excessive rhetorical questions
repetitive CTA
unsupported scientific claims
```

For POV videos:

``` text
HOOK
→ imagined cat thought
→ real behavioral explanation
→ short humorous ending
```

------------------------------------------------------------------------

# 21. Script Prompt Architecture {#21-script-prompt-architecture}

System prompt:

``` text
You are the MochyFami scriptwriter.

Write in Indonesian.

The style is casual, educational, funny, concise, and TTS-friendly.

Rules:
- Start immediately with a hook.
- Use short sentences.
- Avoid "Halo guys".
- Avoid filler.
- Keep one main idea per video.
- Explain the fact clearly.
- Humor must not obscure the factual explanation.
- Do not invent factual claims.
- Use only approved research claims.
- Avoid excessive CTA.
- Target the requested duration.
```

Input:

``` text
Approved research: {{research}}
Category: {{category}}
Format: {{format}}
Duration: {{duration}}
Style examples: {{style_examples}}
```

Output:

``` json
{
  "title": "...",
  "hook": "...",
  "body": "...",
  "closing": "...",
  "full_script": "...",
  "estimated_duration_seconds": 29
}
```

------------------------------------------------------------------------

# 22. Visual Planner Prompt {#22-visual-planner-prompt}

Input is the approved script.

Output:

``` json
{
  "shots": [
    {
      "sequence": 1,
      "start": 0,
      "end": 3,
      "narration": "...",
      "visual": "cat sleeping on sofa",
      "asset_tags": ["cat", "sleeping"],
      "visual_type": "footage"
    }
  ]
}
```

Allowed visual types:

``` text
footage
image
ai_image
text_only
motion_graphic
```

The planner should create a useful shot list, not randomly generate
footage.

------------------------------------------------------------------------

# 23. Title Generator {#23-title-generator}

Generate candidates without automatically declaring one the winner.

Example:

``` json
{
  "titles": [
    {
      "text": "Kenapa Kucing Tiba-Tiba Lari Seperti Kesurupan?",
      "style": "curiosity"
    },
    {
      "text": "Kucing Tiba-Tiba Ngebut? Ini Alasannya!",
      "style": "direct"
    }
  ]
}
```

The user chooses the final title.

------------------------------------------------------------------------

# 24. Thumbnail Prompt Architecture {#24-thumbnail-prompt-architecture}

Input:

``` text
Topic
Title
Brand style
Visual concept
```

Output:

``` json
{
  "prompt": "...",
  "negative_prompt": "...",
  "text_overlay": "...",
  "aspect_ratio": "9:16"
}
```

Default visual direction:

``` text
simple
high contrast
clear animal subject
close-up when appropriate
minimal background
short bold text
strong visual emotion
```

Avoid overcrowding.

------------------------------------------------------------------------

# 25. Similarity Detection {#25-similarity-detection}

Before production, compare the new project with previous projects.

Dimensions:

``` text
topic similarity
title similarity
hook similarity
script similarity
```

Example warning:

``` text
Potentially repetitive content.

Similar project:
"Kenapa Kucing Tidur Sampai 16 Jam?"

Topic: high
Title: medium
Hook: low
```

This is a warning only.

------------------------------------------------------------------------

# 26. Asset Manager {#26-asset-manager}

Every asset should record provenance when possible.

Required metadata:

``` text
filename
source_name
source_url
license
creator_name
license_verified
checksum
tags
```

Example:

``` text
cat_running_01.mp4
Source: Pexels
License: Pexels License
Tags: cat, running, zoomies
```

Never assume that a video found on social media is automatically
reusable.

------------------------------------------------------------------------

# 27. TTS Pipeline {#27-tts-pipeline}

``` text
Approved Script
 ↓
TTS Provider
 ↓
voice.mp3
 ↓
duration detection
 ↓
Audio Track
```

Store:

``` text
provider
voice
language
speed
volume
duration
storage_path
```

If an existing valid TTS result is available, do not regenerate
automatically without explicit instruction.

------------------------------------------------------------------------

# 28. Subtitle Pipeline {#28-subtitle-pipeline}

``` text
Approved Script
 ↓
TTS timing / segmentation
 ↓
Subtitle generation
 ↓
SRT/ASS/VTT
 ↓
Human preview
```

For the video renderer, ASS may be preferred when styled captions are
required.

------------------------------------------------------------------------

# 29. FFmpeg Video Engine {#29-ffmpeg-video-engine}

Do not put raw FFmpeg commands inside controllers.

Architecture:

``` text
RenderVideoJob
 ↓
VideoRenderService
 ↓
FFmpegService
 ↓
FFmpeg process
 ↓
Output MP4
```

Initial render preset:

``` text
Name: shorts_1080x1920
Width: 1080
Height: 1920
FPS: 30
Video codec: H.264
Audio codec: AAC
```

Exact encoding settings should live in configurable render presets.

Initial pipeline:

``` text
VIDEO CLIPS
 ↓
NORMALIZE
 ↓
CROP / SCALE
 ↓
TIMELINE
 ↓
VOICE
 ↓
MUSIC
 ↓
SFX
 ↓
SUBTITLE
 ↓
EXPORT
```

Do not attempt to reproduce CapCut in V1.

------------------------------------------------------------------------

# 30. File Storage {#30-file-storage}

``` text
storage/app/
├── assets/{asset-id}/
├── audio/{project-id}/
├── subtitles/{project-id}/
├── renders/{project-id}/
├── thumbnails/{project-id}/
└── temp/
```

Rules:

-   Never use raw user filenames as paths.
-   Generate safe internal filenames.
-   Validate MIME types and file size.
-   Keep temporary files separate.
-   Clean temporary render files after successful completion when safe.

------------------------------------------------------------------------

# 31. Quality Control System {#31-quality-control-system}

Before approval:

### Content

``` text
[ ] Strong hook
[ ] Factual claims have source mapping
[ ] No obvious unsupported claim
[ ] Not excessively similar to previous content
```

### Technical

``` text
[ ] 9:16
[ ] 1080×1920 or configured target
[ ] Audio exists
[ ] Subtitle exists
[ ] No black/empty frame
[ ] Duration within target
```

### Assets {#assets-1}

``` text
[ ] Footage source recorded
[ ] License/permission information recorded
[ ] No missing asset
```

### Creative

``` text
[ ] Visual matches narration
[ ] Text is readable
[ ] Audio is clear
[ ] Ending is not unnecessarily repetitive
```

Human approval remains required.

------------------------------------------------------------------------

# 32. Security Rules {#32-security-rules}

Never:

-   commit API keys;
-   log API keys;
-   expose `.env`;
-   trust client-side authorization;
-   execute arbitrary shell commands from user input;
-   construct raw SQL from user input;
-   execute unsanitized FFmpeg arguments;
-   trust uploaded file extensions without MIME validation.

Use:

-   Form Requests;
-   Policies;
-   parameterized queries/Eloquent;
-   controlled FFmpeg presets;
-   safe generated filenames;
-   environment variables for secrets.

------------------------------------------------------------------------

# 33. Environment Configuration {#33-environment-configuration}

Example `.env`:

``` env
APP_NAME="MochyFami Studio"
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mochyfami_studio
DB_USERNAME=root
DB_PASSWORD=

AI_PROVIDER=
AI_API_KEY=

TTS_PROVIDER=
TTS_API_KEY=

SEARCH_PROVIDER=
SEARCH_API_KEY=

FFMPEG_BINARY=
FFPROBE_BINARY=
```

`.env` must never be committed.

------------------------------------------------------------------------

# 34. Testing Strategy {#34-testing-strategy}

## Unit tests

Test:

-   status transitions;
-   duration calculations;
-   prompt construction;
-   service logic;
-   render preset generation;
-   similarity thresholds.

## Feature tests

Test:

-   authentication;
-   idea CRUD;
-   project CRUD;
-   research endpoints;
-   script endpoints;
-   asset upload;
-   review approval;
-   invalid status transitions.

## Integration tests

Later test provider adapters and FFmpeg with controlled fixtures.

External services should be mocked in normal automated tests.

------------------------------------------------------------------------

# 35. API / Backend Coding Rules {#35-api--backend-coding-rules}

1.  Controllers remain thin.
2.  Business logic belongs in services/domain classes.
3.  Validation belongs in Form Requests.
4.  Serialization belongs in API Resources.
5.  Authorization belongs in Policies.
6.  Long-running work belongs in Jobs.
7.  Provider-specific code belongs in adapters.
8.  Database state changes must be covered by migrations.
9.  Important status transitions must be tested.
10. Do not add a package when native Laravel functionality is
    sufficient.

------------------------------------------------------------------------

# 36. Vibe Coding Protocol {#36-vibe-coding-protocol}

This section is specifically for AI coding agents.

## Step A --- Inspect {#step-a--inspect}

Before editing:

``` text
- inspect repository tree;
- inspect package versions;
- inspect relevant existing files;
- inspect database migrations;
- inspect routes;
- inspect current frontend architecture;
- identify existing conventions.
```

## Step B --- Plan {#step-b--plan}

Provide a short plan:

``` text
Task
Files to change
Database changes
API changes
UI changes
Tests
```

## Step C --- Implement {#step-c--implement}

Implement only the requested scope.

## Step D --- Verify {#step-d--verify}

Run relevant commands, for example:

``` bash
php artisan test
npm run build
```

Also run lint/type checks when configured.

## Step E --- Report {#step-e--report}

Use:

``` text
## Implemented
- ...

## Files changed
- ...

## Database
- ...

## API
- ...

## Tests
- ...

## Manual verification
- ...

## Known issues
- ...

## Next step
- ...
```

Never claim completion without verification.

------------------------------------------------------------------------

# 37. AI Agent Task Template {#37-ai-agent-task-template}

Use this template for future prompts:

``` text
PHASE:
Phase 1

TASK:
Create the content ideas module.

GOAL:
Allow the user to create, edit, filter and archive content ideas.

CONSTRAINTS:
- Use the existing Laravel + React architecture.
- Do not add unnecessary packages.
- Use Form Requests.
- Use API Resources.
- Add automated tests.
- Follow the Phase 0 specification.

ACCEPTANCE CRITERIA:
- User can create an idea.
- User can edit an idea.
- User can filter ideas.
- User can archive an idea.
- Validation works on the backend.
- Tests pass.
```

------------------------------------------------------------------------

# 38. Git Workflow {#38-git-workflow}

Recommended commit style:

``` text
feat: add content ideas CRUD
feat: add research generation job
fix: prevent invalid project status transition
refactor: extract script generation service
test: add project workflow tests
docs: update AI agent specification
```

Frequent checkpoints are encouraged.

If using branches:

``` text
main
├── feature/ideas
├── feature/research
├── feature/scripts
├── feature/assets
├── feature/tts
├── feature/video-render
└── feature/review
```

------------------------------------------------------------------------

# 39. Development Phases {#39-development-phases}

## Phase 0 --- Blueprint {#phase-0--blueprint}

Complete:

-   product vision;
-   architecture;
-   database/ERD;
-   pages;
-   API contract;
-   status workflow;
-   AI architecture;
-   prompt architecture;
-   coding-agent rules.

No application feature is required in Phase 0.

## Phase 1 --- Foundation {#phase-1--foundation}

Build:

``` text
Laravel 13
React + TypeScript
Vite
Tailwind
MySQL
Sanctum
Authentication
Base layout
API client
Health check
Dashboard shell
```

Do not build AI/TTS/FFmpeg yet.

## Phase 2 --- Content Manager {#phase-2--content-manager}

Build:

``` text
Categories
Ideas
Projects
Status transitions
Dashboard summary
Import initial 100 ideas
```

## Phase 3 --- Research + Script {#phase-3--research--script}

Build:

``` text
AI abstraction
Research
Sources
Claims
Fact check
Script generation
Script versioning
Prompt versioning
```

## Phase 4 --- Asset Manager {#phase-4--asset-manager}

Build:

``` text
Upload
Preview
Tags
Source/license metadata
Project attachments
Search/filter
```

## Phase 5 --- TTS + Subtitle {#phase-5--tts--subtitle}

Build:

``` text
TTS provider
Audio storage
Subtitle generation
Subtitle editing
```

## Phase 6 --- Video Engine {#phase-6--video-engine}

Build:

``` text
FFmpeg service
Render presets
Timeline
Clips
Voice
Music
SFX
Subtitles
Render queue
Preview
```

## Phase 7 --- Review + Thumbnail {#phase-7--review--thumbnail}

Build:

``` text
Thumbnail generation
Video preview
QC checklist
Revision workflow
Approval workflow
```

## Phase 8 --- Publishing + Analytics {#phase-8--publishing--analytics}

Build later:

``` text
YouTube integration
Publishing records
Analytics sync
Performance dashboard
```

------------------------------------------------------------------------

# 40. MVP Acceptance Scenario {#40-mvp-acceptance-scenario}

The MVP succeeds when this complete scenario works:

``` text
User opens MochyFami Studio
        ↓
Creates idea:
"Kenapa kucing tiba-tiba lari?"
        ↓
Converts idea to project
        ↓
Runs research
        ↓
Reviews sources
        ↓
Generates script
        ↓
Reviews script
        ↓
Selects assets
        ↓
Generates TTS
        ↓
Generates subtitles
        ↓
Renders video
        ↓
Generates/selects thumbnail
        ↓
Runs QC
        ↓
Human approves project
```

Publishing may remain manual at MVP stage.

------------------------------------------------------------------------

# 41. Features Explicitly Out of Scope for V1 {#41-features-explicitly-out-of-scope-for-v1}

Do not build these unless explicitly requested:

-   mobile app;
-   public SaaS;
-   multi-user team management;
-   billing/subscriptions;
-   AI avatar;
-   full AI video generation;
-   browser-based video editor;
-   automatic TikTok publishing;
-   automatic Instagram publishing;
-   cloud rendering;
-   complex motion graphics;
-   advanced recommendation engine;
-   automatic viral prediction.

These can be considered later after the core pipeline is stable.

------------------------------------------------------------------------

# 42. Cost and Idempotency Rules {#42-cost-and-idempotency-rules}

AI and media generation can cost money and time.

Track each generation:

``` text
provider
model
operation
project
prompt_version
input_hash
usage
estimated_cost
status
```

Before generating again, check whether a valid existing output already
exists.

Example:

``` text
If approved script already has valid TTS,
do not regenerate automatically.
```

Generation jobs should be idempotent where possible.

------------------------------------------------------------------------

# 43. Observability {#43-observability}

Long-running jobs should record:

``` text
status
started_at
finished_at
attempt_count
error_message
```

Frontend statuses:

``` text
Queued
Processing
Completed
Failed
Cancelled
```

Failed jobs should provide a safe retry option when appropriate.

------------------------------------------------------------------------

# 44. UI Direction {#44-ui-direction}

The UI should feel like a compact creator workstation, not an enterprise
ERP.

Style:

``` text
clean
minimal
modern
media-focused
clear status badges
large video previews
simple navigation
```

Suggested navigation:

``` text
Dashboard
Ideas
Projects
Assets
Production
Analytics
Settings
```

The project detail page is the primary workspace.

------------------------------------------------------------------------

# 45. Example Project Detail Layout {#45-example-project-detail-layout}

``` text
┌─────────────────────────────────────────────┐
│ Kenapa Kucing Tiba-Tiba Lari?               │
│ Status: VIDEO REVIEW                        │
├─────────────────────────────────────────────┤
│ Overview Research Script Assets Production  │
│ Review                                      │
├─────────────────────────────────────────────┤
│                                             │
│             VIDEO PREVIEW                   │
│                9:16                         │
│                                             │
├─────────────────────────────────────────────┤
│ QC                                          │
│ ✓ Script                                    │
│ ✓ Sources                                   │
│ ✓ Audio                                     │
│ ✓ Subtitle                                  │
│ ⚠ Thumbnail                                 │
│                                             │
│ [REQUEST REVISION]        [APPROVE]         │
└─────────────────────────────────────────────┘
```

------------------------------------------------------------------------

# 46. Phase 1 Starting Specification {#46-phase-1-starting-specification}

After Phase 0, the next implementation task is **Phase 1 ---
Foundation**.

Initial scope:

``` text
1. Create/confirm Laravel 13 application.
2. Configure MySQL.
3. Install/configure API + Sanctum.
4. Configure React + TypeScript + Vite.
5. Configure Tailwind.
6. Create authentication.
7. Create AppLayout.
8. Create sidebar/navigation.
9. Create API client.
10. Create dashboard shell.
11. Create backend health endpoint.
12. Add basic automated tests.
```

Do not implement research, TTS, FFmpeg, YouTube, or analytics during the
first Foundation task.

------------------------------------------------------------------------

# 47. Final Agent Contract {#47-final-agent-contract}

When an AI coding agent works on this repository:

``` text
READ THIS DOCUMENT
       ↓
INSPECT CURRENT REPOSITORY
       ↓
IDENTIFY CURRENT PHASE
       ↓
PLAN SMALL TASK
       ↓
IMPLEMENT
       ↓
TEST
       ↓
VERIFY MANUALLY WHEN NEEDED
       ↓
REPORT
       ↓
WAIT FOR NEXT TASK
```

The agent must not:

``` text
- skip repository inspection;
- make giant unrelated refactors;
- silently change architecture;
- expose secrets;
- fabricate test results;
- claim external API success without actually calling/testing it;
- move to the next phase without instruction.
```

------------------------------------------------------------------------

# 48. Phase 0 Completion Checklist {#48-phase-0-completion-checklist}

``` text
[x] Product vision
[x] Human-in-the-loop principle
[x] Technology stack
[x] High-level architecture
[x] Repository structure
[x] React page map
[x] React architecture
[x] Database entities
[x] ERD
[x] Table definitions
[x] Content/project statuses
[x] API endpoint contract
[x] Laravel service architecture
[x] Queue architecture
[x] AI provider architecture
[x] Research prompt architecture
[x] Script prompt architecture
[x] Visual planning prompt
[x] Thumbnail prompt
[x] Similarity detection concept
[x] Asset provenance model
[x] TTS architecture
[x] Subtitle architecture
[x] FFmpeg architecture
[x] QC system
[x] Security rules
[x] Testing strategy
[x] Vibe coding protocol
[x] Git workflow
[x] Development phases
[x] MVP acceptance criteria
[x] Out-of-scope list
```

------------------------------------------------------------------------

# 49. Official References {#49-official-references}

Use official documentation as the primary reference when implementation
details may have changed.

### Laravel

<https://laravel.com/docs>\
<https://laravel.com/docs/13.x/routing>\
<https://laravel.com/docs/13.x/structure>\
<https://laravel.com/docs/13.x/sanctum>\
<https://laravel.com/docs/13.x/queues>

### React

<https://react.dev/>\
<https://react.dev/learn/managing-state>\
<https://react.dev/learn/creating-a-react-app>

### FFmpeg

<https://ffmpeg.org/documentation.html>

------------------------------------------------------------------------

# 50. Document Status {#50-document-status}

``` text
PHASE 0: COMPLETE

NEXT:
PHASE 1 — FOUNDATION
```

This document should be kept in the repository as the AI-agent
reference, for example:

``` text
/docs/AI_AGENT_SPEC.md
```

When the architecture changes, update this document deliberately and
commit the change.
