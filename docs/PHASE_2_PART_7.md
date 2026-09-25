# PHASE 2 — PART 7

# Content Project Status Workflow

Read:

docs/AI_AGENT_SPEC.md

Also inspect:

- ContentProject
- ContentIdea
- ContentProjectService
- ProjectController
- Project API Resources
- Project Form Requests
- Project React pages
- Project detail page
- Existing Project status implementation
- Existing enums/constants/configuration
- Existing authorization/policy architecture
- Existing API error response format

==================================================
IMPORTANT
==================================================

Phase 2 Part 1–6 are already completed.

Do NOT rebuild them.

Do NOT redesign the Project CRUD.

Do NOT implement AI, Research, Script, Assets, TTS, FFmpeg, Publishing, or Analytics.

This task only introduces controlled Project status transitions.

==================================================
GOAL
==================================================

Implement a controlled status workflow for ContentProject.

The backend must prevent invalid status transitions.

The frontend must only present valid next-status actions.

The backend is the final authority.

==================================================
PROJECT STATUSES
==================================================

Existing statuses:

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

Preserve the existing database values.

Do not rename them.

==================================================
VALID TRANSITIONS
==================================================

Allowed transitions:

draft
-> researching
-> archived
-> failed

researching
-> research_review
-> revision
-> failed
-> archived

research_review
-> scripting
-> revision
-> failed
-> archived

scripting
-> script_review
-> revision
-> failed
-> archived

script_review
-> asset_collection
-> revision
-> failed
-> archived

asset_collection
-> production
-> revision
-> failed
-> archived

production
-> video_review
-> revision
-> failed
-> archived

video_review
-> approved
-> revision
-> failed
-> archived

approved
-> published
-> revision
-> archived

published
-> archived

revision
-> researching
-> scripting
-> production
-> failed
-> archived

archived
-> no transitions

failed
-> no transitions

==================================================
IMPORTANT REVISION RULE
==================================================

The revision status can return to different stages depending on where revision is required.

For MVP:

The frontend should allow the user to choose the appropriate return stage.

Examples:

revision -> researching

revision -> scripting

revision -> production

The backend must validate that the selected destination is one of the allowed revision destinations.

Do not allow:

revision -> published

revision -> approved

==================================================
TRANSITION ENDPOINT
==================================================

Create a dedicated endpoint.

Preferred:

PATCH /api/v1/projects/{project}/status

Request:

{
"status": "researching"
}

Do not use the generic project update endpoint for status transitions.

The transition is a business operation and should have its own endpoint.

==================================================
BACKEND VALIDATION
==================================================

The backend must:

1. Load the project.
2. Read the current status.
3. Validate the requested destination status.
4. Check whether the transition is allowed.
5. Reject invalid transitions.
6. Update the project only when valid.

Do not trust the frontend.

==================================================
INVALID TRANSITION
==================================================

Example:

Current:

draft

Requested:

published

Return:

422

Message:

"Project cannot transition from draft to published."

Use the application's existing API error format.

Do not expose stack traces.

==================================================
SAME STATUS
==================================================

If:

current = researching

requested = researching

Do not treat this as a meaningful transition.

Return a clear validation/business error.

Example:

"Project is already in researching status."

==================================================
WORKFLOW IMPLEMENTATION
==================================================

Prefer a centralized workflow definition.

Possible approach:

ContentProjectStatus enum

or

ContentProjectWorkflow service/configuration

Use whichever approach matches the existing architecture.

Do NOT scatter transition rules across:

controllers
frontend components
models
and services.

There must be one authoritative backend definition.

==================================================
STATUS LABELS
==================================================

Keep database values unchanged.

Frontend display labels:

draft
researching
research review
scripting
script review
asset collection
production
video review
revision
approved
published
archived
failed

==================================================
STATUS ACTIONS
==================================================

On Project Detail:

Display:

Current Status

Then:

Available Actions

Example:

Current:

draft

Actions:

[Start Research]

[Archive]

[Mark Failed]

==================================================
ACTION LABELS
==================================================

Map transitions to human-readable actions.

Examples:

draft -> researching

"Start Research"

researching -> research_review

"Submit Research for Review"

research_review -> scripting

"Start Scripting"

scripting -> script_review

"Submit Script for Review"

script_review -> asset_collection

"Start Asset Collection"

asset_collection -> production

"Start Production"

production -> video_review

"Submit Video for Review"

video_review -> approved

"Approve Video"

approved -> published

"Mark as Published"

==================================================
REVISION UI
==================================================

When current status allows revision:

Show:

"Send to Revision"

When revision is selected:

ask:

"Return project to which stage?"

Options:

Research
Script
Production

Map to:

researching
scripting
production

Do not allow arbitrary status selection.

==================================================
ARCHIVE
==================================================

If archiving is allowed from the current status:

show:

"Archive Project"

Require confirmation.

Example:

"Are you sure you want to archive this project?"

==================================================
FAILED
==================================================

If marking a project as failed:

require confirmation.

Example:

"Mark this project as failed?"

Do not delete the project.

==================================================
FRONTEND STATE
==================================================

After successful transition:

- update project data
- update status badge
- update available actions
- show success notification

Do not manually assume the new state.

Use the API response as the source of truth.

React state should represent the current UI state without unnecessary duplicated state. :contentReference[oaicite:1]{index=1}

==================================================
LOADING STATE
==================================================

While transition is processing:

- disable status action buttons
- show loading state
- prevent duplicate requests

After completion:

restore interaction.

==================================================
ERROR STATE
==================================================

If transition fails:

- preserve current project state
- show backend error
- do not change the status locally

If appropriate:

allow retry.

==================================================
PROJECT LIST
==================================================

After a successful status change:

The Project list must reflect the new status when refreshed.

If the existing architecture supports optimistic updates, do not use optimistic status changes unless rollback is properly implemented.

Prefer server-confirmed updates for this workflow.

==================================================
API RESPONSE
==================================================

Successful response should contain:

message
project

Example:

{
"message": "Project status updated successfully.",
"project": {...}
}

Use the existing ContentProjectResource.

==================================================
AUTHORIZATION
==================================================

Require authentication.

Use existing Project Policy / authorization architecture.

Do not create a separate permission system.

==================================================
TESTS — BACKEND
==================================================

Test every important transition.

At minimum:

draft -> researching
researching -> research_review
research_review -> scripting
scripting -> script_review
script_review -> asset_collection
asset_collection -> production
production -> video_review
video_review -> approved
approved -> published

Test revision:

researching -> revision
scripting -> revision
production -> revision

Test revision return:

revision -> researching
revision -> scripting
revision -> production

Test special statuses:

published -> archived
draft -> archived
failed has no transition
archived has no transition

==================================================
INVALID TRANSITION TESTS
==================================================

Test:

draft -> published

draft -> approved

draft -> scripting

researching -> published

script_review -> published

approved -> researching

published -> researching

archived -> draft

failed -> researching

revision -> published

All must be rejected.

==================================================
SAME STATUS TEST
==================================================

Test:

draft -> draft

researching -> researching

Must be rejected.

==================================================
AUTH TESTS
==================================================

Unauthenticated requests must be rejected.

Unauthorized users must be rejected according to the existing policy architecture.

==================================================
FRONTEND TESTS
==================================================

If frontend testing infrastructure already exists:

test:

- available actions
- transition success
- transition failure
- loading state
- revision destination selection

Do not introduce a large testing framework solely for this task.

==================================================
REGRESSION
==================================================

Verify:

- Ideas CRUD
- Ideas search
- Ideas filters
- CSV import
- Idea → Project conversion
- Projects CRUD
- Project search
- Project filters
- Project pagination
- Project detail
- Authentication
- Dashboard

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

If the project contains API documentation:

document:

PATCH /api/v1/projects/{project}/status

including:

- allowed statuses
- invalid transition behavior

==================================================
GIT
==================================================

Do not automatically commit unless the repository workflow explicitly requires it.

==================================================
COMPLETION REPORT
==================================================

Report:

1. Files created
2. Files modified
3. Workflow implementation
4. Valid transitions
5. Invalid transitions
6. API endpoint
7. Frontend changes
8. Tests added
9. Test results
10. Build results
11. Known limitations

==================================================
STOP CONDITION
==================================================

STOP after Phase 2 Part 7.

Do NOT start Part 8.
