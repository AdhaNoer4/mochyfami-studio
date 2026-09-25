# PHASE 2 — PART 8

# Dashboard Real Data

Read:

docs/AI_AGENT_SPEC.md

Also inspect:

- Existing Dashboard page
- Existing Dashboard API
- ContentIdea
- ContentProject
- ContentCategory
- Existing Ideas API
- Existing Projects API
- Existing API Resources
- Existing API client
- Existing TypeScript types
- Existing UI components
- Existing authentication
- Existing project architecture

==================================================
IMPORTANT
==================================================

Phase 2 Part 1–7 are already completed.

Do NOT rebuild them.

Do NOT redesign the entire dashboard.

Extend the existing dashboard shell.

Do NOT implement:

- AI
- Research
- Script generation
- Asset management
- TTS
- Subtitles
- FFmpeg
- Thumbnail generation
- YouTube publishing
- Analytics

Those belong to later phases.

==================================================
GOAL
==================================================

Replace placeholder/mock dashboard data with real database-backed data.

The dashboard should provide a concise overview of the MochyFami content production system.

==================================================
API
==================================================

Create or update:

GET /api/v1/dashboard

Use the existing API architecture.

The endpoint should return a single dashboard payload.

Do not make the frontend call many unrelated endpoints just to construct the dashboard.

==================================================
RESPONSE STRUCTURE
==================================================

Use a stable response structure similar to:

{
"data": {
"overview": {
"total_ideas": 0,
"total_projects": 0,
"active_projects": 0,
"published_projects": 0
},

        "ideas": {
            "total": 0,
            "idea": 0,
            "selected": 0,
            "converted": 0,
            "archived": 0
        },

        "projects": {
            "total": 0,
            "by_status": {
                "draft": 0,
                "researching": 0,
                "research_review": 0,
                "scripting": 0,
                "script_review": 0,
                "asset_collection": 0,
                "production": 0,
                "video_review": 0,
                "revision": 0,
                "approved": 0,
                "published": 0,
                "archived": 0,
                "failed": 0
            }
        },

        "recent_ideas": [],
        "recent_projects": [],
        "production_queue": []
    }

}

Adapt this structure to the existing API response convention if one already exists.

==================================================
OVERVIEW DEFINITIONS
==================================================

total_ideas:

Count all ContentIdea records.

total_projects:

Count all ContentProject records.

active_projects:

Projects that are currently in active production workflow.

Include:

researching
research_review
scripting
script_review
asset_collection
production
video_review
revision
approved

Exclude:

draft
published
archived
failed

published_projects:

Projects with:

status = published

==================================================
IDEA STATUS COUNTS
==================================================

Return counts for:

idea
selected
converted
archived

Use the existing ContentIdea status values.

Do not invent additional statuses.

==================================================
PROJECT STATUS COUNTS
==================================================

Return counts for every existing ContentProject status:

draft
researching
research_review
scripting
script_review
asset_collection
production
video_review
revision
approved
published
archived
failed

Even if the count is zero.

This ensures the frontend has a stable structure.

==================================================
RECENT IDEAS
==================================================

Return:

5 most recently created ideas.

Sort:

created_at DESC

Each item should contain only the fields needed by the dashboard.

Example:

id
title
status
format
category
created_at

Use the existing ContentIdeaResource if appropriate.

Do not expose unnecessary database fields.

==================================================
RECENT PROJECTS
==================================================

Return:

5 most recently updated projects.

Sort:

updated_at DESC

Each item:

id
title
status
priority
updated_at

Include original idea information if already available through the existing relationship.

Avoid N+1 queries.

==================================================
PRODUCTION QUEUE
==================================================

Return active projects only.

Statuses:

researching
research_review
scripting
script_review
asset_collection
production
video_review
revision

Sort by:

updated_at ASC

The oldest active project should appear first.

Return:

id
title
status
priority
updated_at

Limit:

10

==================================================
DATABASE PERFORMANCE
==================================================

Dashboard queries should be performed server-side.

Do NOT:

- fetch all ideas into PHP
- fetch all projects into PHP
- count arrays in React
- calculate project statuses in React

Use database aggregation/counting where appropriate.

Avoid N+1 relationship queries.

Use eager loading where relationships are required.

Do not introduce caching yet unless the existing architecture already uses it.

==================================================
SERVICE ARCHITECTURE
==================================================

If the application uses Services:

create/use:

DashboardService

The service may be responsible for:

- overview statistics
- idea statistics
- project statistics
- recent ideas
- recent projects
- production queue

Keep the DashboardController thin.

Do not put all dashboard query logic directly inside the controller.

==================================================
FRONTEND
==================================================

Update the existing:

/dashboard

page.

Remove placeholder/mock statistics.

Use the real dashboard API.

==================================================
SUMMARY CARDS
==================================================

Display:

Total Ideas
Total Projects
Active Projects
Published Projects

Each card should display:

- label
- number
- appropriate visual indicator/icon if the existing UI system supports it

Do not introduce a new icon library if one already exists.

==================================================
IDEA STATUS SECTION
==================================================

Display the idea status distribution.

Example:

Idea
Selected
Converted
Archived

Show:

- label
- count

A simple list/bar representation is sufficient.

Do NOT introduce a charting library unless one already exists in the project.

==================================================
PROJECT STATUS SECTION
==================================================

Display project status distribution.

Because there are many statuses, avoid creating a visually overwhelming chart.

A grouped list/table is acceptable.

Group conceptually:

Planning:
draft

Research:
researching
research_review

Script:
scripting
script_review

Assets:
asset_collection

Production:
production

Review:
video_review
revision

Publishing:
approved
published

Other:
archived
failed

Important:

This grouping is only for presentation.

The actual status values must remain unchanged.

==================================================
RECENT IDEAS
==================================================

Display up to 5 recent ideas.

Each item:

Title
Status
Category
Created time

Provide:

"View All Ideas"

which navigates to:

/ideas

==================================================
RECENT PROJECTS
==================================================

Display up to 5 recent projects.

Each item:

Title
Status
Priority
Updated time

Provide:

"View All Projects"

which navigates to:

/projects

==================================================
PRODUCTION QUEUE
==================================================

Display active projects that require work.

Each row:

Project title
Current status
Priority
Last updated

Clicking a project should navigate to:

/projects/{id}

==================================================
EMPTY STATES
==================================================

Dashboard must work when database is empty.

If:

total_ideas = 0

show:

"No content ideas yet."

Provide:

"Create Idea"

If:

total_projects = 0

show:

"No content projects yet."

Provide:

"Create Project"

If production queue is empty:

"No active projects in production."

==================================================
LOADING STATE
==================================================

While dashboard data is loading:

show appropriate skeleton/loading states.

Do not display fake numbers such as:

0

while the request is still loading if this would be misleading.

==================================================
ERROR STATE
==================================================

If dashboard API fails:

show a clear error state.

Example:

"Unable to load dashboard data."

Provide:

"Try Again"

Do not expose stack traces.

==================================================
REFRESH
==================================================

Add a refresh action if the existing UI pattern supports it.

When clicked:

reload dashboard data.

Do not reload the entire browser page unless the existing architecture requires it.

==================================================
AUTO REFRESH
==================================================

Do NOT implement polling or automatic refresh yet.

Dashboard refresh can be manual.

Real-time production monitoring belongs to a later phase.

==================================================
TYPESCRIPT
==================================================

Create/update:

DashboardData
DashboardOverview
DashboardIdeaStats
DashboardProjectStats
DashboardRecentIdea
DashboardRecentProject
DashboardQueueItem

Avoid:

any

==================================================
API CLIENT
==================================================

Reuse the existing API client.

Do not create another HTTP client.

==================================================
AUTHORIZATION
==================================================

Dashboard endpoint requires authentication.

Use existing authentication middleware.

If the application already has a policy/permission architecture:

reuse it.

Do not create a new permission system.

==================================================
TESTS — BACKEND
==================================================

Test:

1. Dashboard endpoint requires authentication
2. Empty database
3. Total ideas
4. Total projects
5. Active project count
6. Published project count
7. Idea status counts
8. Project status counts
9. Recent ideas
10. Recent projects
11. Production queue
12. Production queue excludes draft
13. Production queue excludes published
14. Production queue excludes archived
15. Production queue excludes failed
16. Production queue ordering
17. Production queue limit
18. Correct category relationship
19. No unexpected N+1 queries if query-count testing infrastructure exists

==================================================
FRONTEND
==================================================

If frontend testing infrastructure already exists:

test:

- dashboard loading state
- dashboard data rendering
- empty state
- error state
- refresh behavior
- navigation to Ideas
- navigation to Projects

Do not introduce a large testing framework solely for this task.

==================================================
REGRESSION
==================================================

Verify:

- Categories
- Ideas CRUD
- Ideas search/filter/sort
- CSV import
- Projects CRUD
- Idea → Project conversion
- Project status workflow
- Authentication

==================================================
VALIDATION
==================================================

Run:

php artisan test

Run:

npm run build

If frontend tests exist:

run them.

==================================================
DOCUMENTATION
==================================================

If API documentation exists:

document:

GET /api/v1/dashboard

==================================================
GIT
==================================================

Do not commit automatically unless repository workflow explicitly requires it.

==================================================
COMPLETION REPORT
==================================================

Report:

1. Files created
2. Files modified
3. Dashboard endpoint
4. Dashboard data structure
5. Database queries/aggregations
6. Frontend sections
7. Tests added
8. Test results
9. Build result
10. Known limitations

==================================================
STOP CONDITION
==================================================

STOP after Phase 2 Part 8.

Do NOT start Part 9 automatically.
