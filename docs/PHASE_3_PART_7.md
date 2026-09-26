# MOCHYFAMI CONTENT STUDIO

# PHASE 3 — PART 7

# SCRIPT FOUNDATION & VERSION WORKFLOW

You are continuing development of the existing MochyFami Content Studio repository.

IMPORTANT:
This is an existing working codebase.
Do NOT rebuild or redesign the application from scratch.

==================================================

1. # PRIMARY OBJECTIVE

Implement PHASE 3 — PART 7:

"Script Foundation & Version Workflow"

The goal is to create the foundational Script domain that will later support:

Research → Script → Visual Plan → Assets → TTS → Video Production

This part must establish:

- Script entity
- Script versions
- Script lifecycle/status
- Script version management
- Project ↔ Script relationship
- API endpoints
- Backend validation
- Frontend Script panel
- Deterministic workflow rules
- Tests

DO NOT implement AI script generation yet.

DO NOT call external AI providers.

DO NOT implement TTS.

DO NOT implement subtitle generation.

DO NOT implement visual planning.

Those will be implemented in later parts.

================================================== 2. FIRST STEP — INSPECT THE REPOSITORY
==================================================

Before changing anything:

1. Inspect the existing repository structure.
2. Inspect current:
    - ContentProject model
    - ResearchReport model
    - ResearchClaim model
    - Source model
    - ResearchService
    - ResearchQualityService
    - ResearchPipelineService
    - API Resources
    - Form Requests
    - Policies
    - routes
    - React ProjectDetailPage
    - ResearchPanel
    - frontend API/service patterns
    - existing enums
    - existing tests
    - database migration conventions
3. Read the actual implementation of Phase 3 Parts 1–6.
4. Reuse existing architecture and conventions.

Do NOT assume the Phase 0 specification is identical to the current codebase.

The actual repository implementation is authoritative.

If the repository already contains partial Script-related implementation,
inspect it carefully and extend it instead of duplicating it.

================================================== 3. ARCHITECTURE PRINCIPLES
==================================================

Follow these principles:

- Backend is authoritative.
- Frontend must not duplicate business rules as the only enforcement.
- Project ownership must remain enforced through the existing project scope.
- Use existing ApiResponse conventions.
- Use existing Resource conventions.
- Use existing Form Request conventions.
- Use existing policy conventions.
- Use enums for finite states.
- Use services for workflow/domain logic.
- Avoid unnecessary abstraction.
- No AI.
- No external HTTP requests.
- No automatic script generation.
- No hidden mutations.
- No mock production data.
- No hardcoded UI statistics.
- Keep the implementation deterministic and testable.

================================================== 4. SCRIPT DOMAIN MODEL
==================================================

Introduce a Script model associated with a ContentProject.

Expected conceptual relationship:

ContentProject
hasOne Script

Script
belongsTo ContentProject
hasMany ScriptVersions

ScriptVersion
belongsTo Script

The exact implementation must follow the repository's conventions.

A project should have at most ONE active Script aggregate.

The Script aggregate represents the project's current script workflow.

ScriptVersion represents historical versions of the script.

================================================== 5. SCRIPT TABLE
==================================================

Create the required migration for the scripts table.

Use appropriate Laravel conventions.

Suggested fields:

scripts

- id
- content_project_id
- status
- current_version_id nullable
- created_at
- updated_at

Requirements:

- content_project_id foreign key
- cascade delete according to existing project relationship conventions
- unique content_project_id
- status stored through enum-compatible value
- current_version_id nullable

IMPORTANT:

Avoid circular foreign-key migration problems.

If current_version_id creates a migration dependency problem,
create the scripts table first and add the current_version_id foreign key
in a separate migration.

Do not blindly copy this schema if repository conventions indicate a
better implementation.

================================================== 6. SCRIPT VERSIONS TABLE
==================================================

Create script_versions table.

Suggested fields:

script_versions

- id
- script_id
- version
- title nullable
- hook
- body
- closing nullable
- duration_seconds nullable
- notes nullable
- created_at
- updated_at

Requirements:

- script_id foreign key
- cascade delete
- version integer
- unique(script_id, version)

The version number must be deterministic.

Example:

Version 1
Version 2
Version 3

Do NOT use timestamps as the version number.

================================================== 7. SCRIPT STATUS ENUM
==================================================

Create a ScriptStatus enum.

Use a small, explicit workflow.

Suggested statuses:

draft
review
approved
archived

Meaning:

draft

- script is being created or edited

review

- script exists and is ready for human review

approved

- human has approved the script for downstream production

archived

- script is no longer active

Do NOT introduce unnecessary statuses such as:
generating,
generated,
processing,
publishing,
rendering, etc.

AI generation will be introduced later.

================================================== 8. SCRIPT STATUS TRANSITIONS
==================================================

Centralize status transition rules.

Allowed transitions:

draft → review
draft → archived

review → draft
review → approved
review → archived

approved → archived

archived → draft

Same-status transition must be rejected.

Backend must be authoritative.

Use the same exception / validation pattern already established for
ResearchStatus workflow.

If the repository already has a reusable transition architecture,
reuse it.

Do not duplicate workflow logic unnecessarily.

================================================== 9. SCRIPT CREATION
==================================================

A project can create its Script aggregate only once.

Endpoint concept:

POST
/api/v1/projects/{project}/script

Behavior:

- project must belong to authenticated user
- project must not already have a Script
- create Script with status=draft
- optionally create initial ScriptVersion in the same transaction

Recommended behavior:

Creating a Script should create Version 1 immediately.

Initial version fields may be:

title
hook
body
closing
duration_seconds
notes

All should be validated according to the actual domain requirements.

The initial version may be mostly empty if the existing architecture
requires creation before writing content.

However, avoid allowing meaningless completely empty versions unless
there is a clear reason.

Use a transaction.

Duplicate Script creation must return a controlled 409 response.

================================================== 10. SCRIPT VERSION CREATION
==================================================

Implement version creation.

Endpoint concept:

POST
/api/v1/projects/{project}/script/versions

Creating a new version must:

1. Verify project ownership.
2. Verify Script exists.
3. Determine next version number safely.
4. Create the new ScriptVersion.
5. Update current_version_id to the new version.
6. Keep previous versions immutable.

Example:

Current:
Version 1

Create new version:

Version 2 becomes current.

Version 1 remains available as history.

Do NOT overwrite old versions.

Use a transaction.

The version number must be generated server-side.

Never trust a client-provided version number.

================================================== 11. VERSION IMMUTABILITY
==================================================

Historical ScriptVersions should be immutable.

Do NOT implement arbitrary update of old versions.

Instead:

- current version can be edited through a dedicated update operation
- editing the current version should create a NEW version OR follow a
  clearly defined repository convention

Preferred behavior for this part:

Editing script content creates a new ScriptVersion.

Therefore:

Version 1
↓ edit
Version 2

Version 1 remains unchanged.

This is important because ScriptVersion is a historical record.

If this architecture becomes unnecessarily complex with the current
repository, document the chosen alternative and keep version history
safe.

================================================== 12. CURRENT VERSION
==================================================

The Script aggregate must expose its current version.

Implement relationship:

Script
currentVersion()

and:

Script
versions()

The API Resource should expose:

- script id
- project id
- status
- status_label
- allowed_transitions
- current_version
- version count if useful
- created_at
- updated_at

Do NOT expose internal implementation details unnecessarily.

================================================== 13. SCRIPT VERSION RESOURCE
==================================================

Create ScriptVersionResource.

Expose appropriate fields:

- id
- script_id
- version
- title
- hook
- body
- closing
- duration_seconds
- notes
- created_at
- updated_at

Do not expose unnecessary internal fields.

================================================== 14. SCRIPT API
==================================================

Implement endpoints following existing route conventions.

Suggested endpoints:

GET
/api/v1/projects/{project}/script

POST
/api/v1/projects/{project}/script

PATCH
/api/v1/projects/{project}/script/status

GET
/api/v1/projects/{project}/script/versions

GET
/api/v1/projects/{project}/script/versions/{version}

POST
/api/v1/projects/{project}/script/versions

PATCH
/api/v1/projects/{project}/script/versions/current

POST/PATCH behavior must follow the chosen versioning architecture.

IMPORTANT:

Do not create arbitrary endpoints just for convenience.

Keep the API small and coherent.

================================================== 15. SCRIPT SERVICE
==================================================

Create a dedicated service, for example:

ScriptService

Responsibilities:

- getScript()
- createScript()
- getVersions()
- getVersion()
- createVersion()
- updateCurrentVersion()
- transitionStatus()
- archiveScript()

Use transactions where multiple records change.

The service must enforce:

- ownership scope
- one Script per project
- version sequencing
- current version consistency
- status transitions

Controllers should remain thin.

================================================== 16. FORM REQUESTS
==================================================

Create dedicated Form Requests.

Examples:

StoreScriptRequest
StoreScriptVersionRequest
UpdateCurrentScriptVersionRequest
TransitionScriptStatusRequest

Follow existing validation style.

Suggested validation:

title:

- nullable|string|max appropriate length

hook:

- required|string|max appropriate length

body:

- required|string

closing:

- nullable|string|max appropriate length

duration_seconds:

- nullable|integer|min:1|max reasonable Shorts duration

notes:

- nullable|string

status:

- enum validation

Do not blindly copy these limits if the repository already has
established conventions.

================================================== 17. SCRIPT WORKFLOW + RESEARCH PIPELINE
==================================================

Integrate Script with the existing Research Pipeline.

Important:

Do NOT automatically create a Script when Research becomes
ready_for_script.

The Research Pipeline remains read-only and deterministic.

However, the Script creation UI should clearly reflect:

Research ready
→ Script can be created

Research not ready
→ Script creation may be disabled or show an explanation

Backend should decide whether script creation is allowed based on the
current project/research state.

Preferred rule:

A Script may be created when:

- no ResearchReport exists only if the current repository architecture
  intentionally permits manual script drafting

OR

- ResearchReport exists and ResearchQuality is ready

Choose the rule that best fits the existing Phase 3 architecture.

IMPORTANT:
Do not make this assumption silently.

Inspect Parts 4–6 and document the actual chosen rule.

If the system requires research readiness, return a controlled 422
domain error when attempting to create a Script too early.

================================================== 18. FRONTEND — SCRIPT PANEL
==================================================

Extend ProjectDetailPage.

Add:

Overview | Research | Script

Do not redesign existing tabs.

Create:

ScriptPanel

The panel should support:

1. No Script state
2. Script loading
3. Script error
4. Script creation
5. Current version display
6. Version history
7. Create new version
8. Edit current version
9. Status transition
10. Archive confirmation

Keep UI consistent with ResearchPanel.

================================================== 19. SCRIPT EDITOR
==================================================

For this part, use a simple form.

Fields:

- Title
- Hook
- Body
- Closing
- Duration
- Notes

Do NOT build a rich text editor.

Do NOT build AI generation UI.

Do NOT add prompt configuration UI.

Do NOT add TTS controls.

A normal textarea-based editor is sufficient.

================================================== 20. VERSION HISTORY UI
==================================================

Display:

Version 1
Version 2
Version 3
...

Mark the current version clearly.

Allow opening older versions read-only.

Older versions must NOT have an edit button.

Only the current version may be edited.

When current version is edited:

Create a new version.

Example:

Current Version 2

User edits Hook

System creates Version 3.

Version 2 remains unchanged.

================================================== 21. STATUS UI
==================================================

Show:

- current status
- allowed actions

Use the backend's:

allowed_transitions

Do not hardcode transition availability in the frontend.

Example:

draft:
"Send to Review"
"Archive"

review:
"Back to Draft"
"Approve"
"Archive"

approved:
"Archive"

archived:
"Restore to Draft"

Destructive actions require confirmation.

================================================== 22. RESEARCH → SCRIPT UX
==================================================

At the top of ScriptPanel, show a small readiness indicator.

Example states:

Research:
READY FOR SCRIPT

or:

Research:
NOT READY

If not ready, explain using existing Research Pipeline / Quality data.

Do NOT duplicate the quality algorithm in React.

Use the existing pipeline/quality API.

The frontend should consume backend results.

================================================== 23. TYPESCRIPT TYPES
==================================================

Add/update frontend types:

ScriptStatus
Script
ScriptVersion
ScriptTransition
ScriptResponse

Follow existing API type conventions.

Avoid `any`.

Do not use `as any` to bypass typing.

================================================== 24. API SERVICE
==================================================

Create:

scriptService.ts

Follow the same pattern as:

researchService.ts

Methods should include only required operations.

Example:

getScript()
createScript()
transitionStatus()
getVersions()
getVersion()
createVersion()
updateCurrentVersion()

Handle API errors using existing application conventions.

================================================== 25. AUTHORIZATION
==================================================

Follow the existing project ownership model.

Every Script operation must be scoped to:

authenticated user
→ project
→ script

Cross-user access must return the same controlled response pattern
already used by Research.

Test:

User A cannot access User B's project Script.

User A cannot access User B's Script versions.

================================================== 26. DATABASE CONSTRAINTS
==================================================

Use database constraints where appropriate.

At minimum:

unique project_id for scripts

unique(script_id, version) for script_versions

foreign keys with appropriate cascade behavior

Do not rely only on frontend validation.

================================================== 27. ERROR HANDLING
==================================================

Use controlled domain exceptions where the project already does so.

Examples:

DuplicateScriptException
ScriptNotFoundException if needed
ScriptVersionNotFoundException if needed
InvalidScriptStatusTransitionException
ScriptNotReadyForCreationException if research readiness is required

Do NOT expose stack traces or internal implementation details through
the API.

Follow the project's existing exception handler mapping.

================================================== 28. TESTING
==================================================

Add comprehensive tests.

Minimum areas:

SCRIPT CRUD / FOUNDATION

- create Script
- duplicate Script rejected
- get Script
- missing Script behavior
- project ownership

VERSIONING

- initial version = 1
- create version 2
- create version 3
- versions ordered correctly
- current_version updates
- old versions remain unchanged
- version number cannot be client-controlled

EDITING

- editing current version creates new version
- old version remains immutable
- current version changes correctly

STATUS

- valid transitions succeed
- invalid transitions rejected
- same-status rejected
- allowed_transitions correct

AUTHORIZATION

- cross-user project access rejected
- cross-user Script access rejected
- cross-user version access rejected

RESEARCH INTEGRATION

- if research readiness is required:
    - ready research allows Script creation
    - non-ready research rejects Script creation
- if manual drafting is intentionally allowed:
    - document and test that behavior

API RESPONSE

- Resource structure
- validation errors
- controlled domain errors
- correct HTTP status codes

DATABASE

- migration works
- rollback works
- unique constraints work

================================================== 29. TEST QUALITY
==================================================

Do NOT write tests merely to increase the test count.

Tests must verify actual behavior.

Prefer:

- feature tests for API behavior
- unit tests for status transitions / deterministic domain logic

Use:

Http::preventStrayRequests()

if external HTTP could accidentally be triggered.

There should be NO external AI or web calls in this part.

================================================== 30. SECURITY CHECK
==================================================

Before completion verify:

- no API keys committed
- no secrets
- no arbitrary shell execution
- no raw SQL unless justified
- no unscoped project queries
- no user-controlled version numbers
- no mass assignment vulnerability
- no `any` used to bypass TypeScript safety
- no debug statements
- no fake/mock production data
- no TODO/FIXME left accidentally

================================================== 31. CODE QUALITY
==================================================

Run the project's normal checks.

At minimum:

Backend:

- php artisan test --compact
- php artisan migrate:fresh --seed if appropriate
- php artisan migrate:rollback
- ./vendor/bin/pint --test

Frontend:

- npx tsc --noEmit
- npm run build

If the repository has additional established checks,
run them too.

Do not modify unrelated code just to make checks pass.

================================================== 32. GIT SAFETY
==================================================

Before starting:

git status

After implementation:

git status

Review changed files.

Do NOT commit unrelated modifications.

Create ONE focused commit only after everything passes.

Suggested commit:

feat: add script foundation and version workflow

Do not push automatically unless the existing workflow explicitly
requires it.

================================================== 33. DOCUMENTATION
==================================================

Add or update a concise implementation note if the project already
keeps Phase 3 documentation.

Document:

- Script domain
- ScriptVersion architecture
- status workflow
- version immutability
- current_version behavior
- Research → Script readiness rule
- API endpoints
- tests
- verification results

Do not create excessive documentation.

================================================== 34. IMPORTANT NON-GOALS
==================================================

DO NOT implement:

- OpenAI integration
- Gemini integration
- Claude integration
- AI script generation
- AI rewriting
- prompt management
- TTS
- subtitle generation
- visual planning
- asset collection
- video rendering
- publishing
- analytics

Those belong to later parts.

================================================== 35. COMPLETION REPORT
==================================================

When finished, report exactly:

PHASE 3 PART 7 STATUS: PASS / BLOCKED

Implementation:

- Script model:
- ScriptVersion model:
- ScriptStatus:
- Status workflow:
- Versioning:
- Current version:
- Research readiness integration:
- API:
- Frontend:
- Tests:

Verification:

- Tests:
- Assertions:
- TypeScript:
- Build:
- Pint:
- Migration:
- Rollback:
- Security/debug cleanup:

Git:

- Commit:
- Working tree:

Known limitations:

- ...

IMPORTANT:
If anything is not actually verified, say so explicitly.

Never claim PASS unless the implementation and verification actually
succeeded.

================================================== 36. STOP CONDITION
==================================================

After completing Part 7:

STOP.

Do NOT start Part 8 automatically.

Wait for the user to explicitly request:

"Lanjut Part 8"

==================================================

FINAL PRINCIPLE:

The goal of Part 7 is a stable, deterministic Script foundation.

Research produces evidence.
Research Quality determines readiness.
Research Pipeline explains progress.
Script stores human-controlled script versions.
AI generation comes later.

Keep this part simple, testable, and production-safe.
