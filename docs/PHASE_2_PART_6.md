# PHASE 2 — PART 6

# Idea → Project Conversion

Read:

docs/AI_AGENT_SPEC.md

Also inspect the existing implementation of:

- ContentIdea
- ContentProject
- ContentCategory
- Ideas API
- Projects API
- Ideas React pages
- Projects React pages
- Existing API client
- Existing TypeScript types
- Existing UI components
- Existing authentication and authorization

==================================================
IMPORTANT
==================================================

Phase 2 Part 1–5 are already completed.

Do NOT rebuild existing functionality.

Do NOT redesign the existing Ideas or Projects architecture.

Extend the existing implementation.

==================================================
GOAL
==================================================

Implement the real:

Content Idea → Content Project

conversion workflow.

The existing "Convert to Project" action on the Ideas page must become functional.

==================================================
WORKFLOW
==================================================

User opens:

/ideas

↓

Selects an eligible Content Idea

↓

Clicks:

Convert to Project

↓

Confirmation dialog/modal appears

↓

User confirms

↓

Backend creates ContentProject

↓

Backend updates ContentIdea status to:

converted

↓

Frontend displays success

↓

User may open the newly created Project

==================================================
ELIGIBLE IDEAS
==================================================

Only ideas with status:

idea
selected

may be converted.

Ideas with status:

converted
archived

must not be converted.

If an ineligible idea attempts conversion:

return a proper business validation error.

Do not rely only on frontend button disabling.

The backend must enforce this rule.

==================================================
API
==================================================

Create a dedicated conversion endpoint.

Preferred:

POST /api/v1/ideas/{idea}/convert-to-project

Do not overload the generic:

POST /api/v1/projects

endpoint with hidden conversion behavior.

The conversion action is a distinct business operation.

==================================================
REQUEST
==================================================

The conversion request may accept optional:

title
priority
notes

If title is omitted:

use the Content Idea title.

If priority is omitted:

use the existing default:

medium

If notes are omitted:

leave empty/null according to the existing schema.

==================================================
PROJECT DEFAULTS
==================================================

New project:

status = draft

priority = requested priority
or
medium

content_idea_id = source idea ID

title = requested title
or
idea title

==================================================
DATABASE TRANSACTION
==================================================

The conversion must run inside a database transaction.

Operations:

1. Create ContentProject
2. Update ContentIdea status to converted

Both must succeed.

If either operation fails:

rollback the entire operation.

Do not leave:

- project created + idea not converted
- idea converted + project not created

Use Laravel database transaction support.

==================================================
DUPLICATE CONVERSION PROTECTION
==================================================

If an idea has already been converted:

do not create another project.

Return a clear business error.

Example:

"This idea has already been converted into a project."

Do not silently return success.

==================================================
RELATIONSHIP
==================================================

The created project must reference:

content_idea_id

The project detail page must be able to display:

Original Idea

with a link back to the Idea detail page if the existing router supports it.

==================================================
API RESPONSE
==================================================

On success return:

message

project

idea

Example concept:

{
"message": "Idea converted to project successfully.",
"project": {...},
"idea": {...}
}

Use the existing API Resource structure.

Do not create an inconsistent response format.

==================================================
ERROR RESPONSES
==================================================

Handle:

404:

Idea does not exist.

422:

Idea is not eligible for conversion.

409:

Idea has already been converted.

403:

User is not authorized.

500:

Unexpected server/database failure.

Use the existing application's error response conventions.

Do not expose stack traces.

==================================================
BACKEND ARCHITECTURE
==================================================

Create a dedicated business operation.

Recommended:

ContentProjectService

or another service location consistent with the existing architecture.

Example responsibility:

convertIdeaToProject()

The controller should remain thin.

Do not put transaction logic directly into the controller if the existing architecture uses Services.

==================================================
AUTHORIZATION
==================================================

The conversion endpoint must require authentication.

Apply the existing authorization/policy architecture.

Do not create a new authorization mechanism.

==================================================
FRONTEND
==================================================

Update the existing:

/ideas

page.

The existing:

"Convert to Project"

action must now call the conversion endpoint.

==================================================
CONVERSION MODAL
==================================================

Before conversion, display a confirmation dialog.

Show:

Idea title
Category
Format
Current status

Then provide optional:

Project Title
Priority
Notes

Defaults:

Project Title = Idea title

Priority = medium

Notes = empty

==================================================
CONFIRMATION
==================================================

Button:

Create Project

While request is running:

- disable submit button
- show loading state
- prevent duplicate submission

Do not allow multiple conversion requests from repeated clicks.

==================================================
SUCCESS
==================================================

After successful conversion:

Show success notification.

Example:

"Content project created successfully."

Then update the idea UI.

The idea status should become:

converted

Provide action:

"Open Project"

which navigates to:

/projects/{id}

==================================================
FAILURE
==================================================

If conversion fails:

Keep the modal open where appropriate.

Show the backend error.

Do not pretend conversion succeeded.

If the backend reports that the idea was already converted:

refresh the idea data.

==================================================
IDEA LIST
==================================================

After successful conversion:

The converted idea should display:

Status: Converted

The action:

Convert to Project

must no longer be available.

Instead show:

Open Project

if the related project ID is available.

==================================================
PROJECT DETAIL
==================================================

On the newly created project detail page:

Display:

Original Idea

Example:

Original Idea:
Kenapa Gagak Bisa Mengingat Wajah Manusia?

[View Idea]

==================================================
IDEA DETAIL
==================================================

If an Idea detail page already exists:

When status is converted:

show:

Converted Project

[Open Project]

If no Idea detail page exists yet:

do not create an unrelated redesign.

Only add the relationship information where appropriate.

==================================================
PROJECT LIST
==================================================

After conversion:

The new project must appear in:

/projects

with:

status = draft

==================================================
URL NAVIGATION
==================================================

Use the existing router.

Do not introduce another routing library.

==================================================
TYPESCRIPT
==================================================

Create/update types for:

ConvertIdeaPayload
ConvertIdeaResponse

Avoid:

any

==================================================
STATE MANAGEMENT
==================================================

Follow the existing React state architecture.

Do not introduce a global state library for this feature.

Keep conversion modal state local unless existing architecture requires otherwise.

Avoid redundant state.

==================================================
DOUBLE SUBMISSION PROTECTION
==================================================

The frontend must disable the confirmation button while conversion is processing.

The backend must independently prevent duplicate conversion.

Both layers are required.

==================================================
TESTS — BACKEND
==================================================

Create tests for:

1. Successful conversion
2. Idea status becomes converted
3. Project is created
4. Project references correct idea
5. Default project title
6. Custom project title
7. Default priority
8. Custom priority
9. Custom notes
10. Idea already converted
11. Archived idea cannot convert
12. Invalid idea ID
13. Unauthorized conversion
14. Transaction rollback
15. Newly created project has draft status

==================================================
TRANSACTION TEST
==================================================

Explicitly test that if project creation fails:

the idea remains unchanged.

Also test that if idea update fails:

the project creation is rolled back.

Do not fake this test.

Use a realistic database-level test strategy appropriate to the existing application.

==================================================
REGRESSION
==================================================

Verify:

- Ideas CRUD
- Ideas search
- Ideas filtering
- Ideas sorting
- Ideas pagination
- CSV import
- Projects CRUD
- Project search
- Project filters
- Project detail
- Categories
- Authentication
- Dashboard

==================================================
FRONTEND VALIDATION
==================================================

Run:

npm run build

If frontend tests exist:

run them.

==================================================
BACKEND VALIDATION
==================================================

Run:

php artisan test

==================================================
UX REQUIREMENTS
==================================================

The user must always know:

- what idea is being converted
- what project will be created
- whether conversion is processing
- whether conversion succeeded
- whether conversion failed

Never silently change the idea status.

==================================================
IMPORTANT ARCHITECTURE RULE
==================================================

Do NOT implement:

- Research
- AI research
- Fact checking
- Script generation
- Visual planning
- Asset management
- TTS
- Subtitle generation
- FFmpeg
- Thumbnail generation
- YouTube publishing
- Analytics

These belong to later phases.

==================================================
COMPLETION REPORT
==================================================

Report:

1. Files created
2. Files modified
3. API endpoint
4. Conversion workflow
5. Transaction implementation
6. Duplicate protection
7. Frontend UX
8. Tests added
9. Test results
10. Build results
11. Known limitations

==================================================
STOP CONDITION
==================================================

STOP after Phase 2 Part 6.

Do not start Part 7 automatically.
