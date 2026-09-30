# MochyFami Content Studio

# Phase 4 — Part 1: Asset Domain Foundation

You are working on the existing MochyFami Content Studio repository.

IMPORTANT:
This is an existing production-oriented codebase. Do NOT redesign the architecture.
Do NOT implement future Phase 4 features unless explicitly required by this task.

==================================================
OBJECTIVE
==================================================

Implement Phase 4 Part 1: Asset Domain Foundation.

The purpose of this part is to introduce the core Asset domain model and
database foundation that later Phase 4 parts can build upon.

At the end of this task, the application should have a well-defined Asset
entity with:

- database persistence
- enums for asset type/status
- project ownership/scope
- metadata fields needed by future asset management
- model relationships
- service layer foundation
- policy/authorization foundation
- API resource foundation
- migrations/factories if appropriate
- automated tests

DO NOT implement actual file upload/storage yet.

DO NOT implement external asset search/download yet.

DO NOT implement asset-to-requirement attachment yet.

DO NOT implement asset processing/transcoding yet.

==================================================
NON-GOALS
==================================================

Explicitly DO NOT implement:

- file upload UI
- drag-and-drop upload
- filesystem storage workflows
- S3/cloud storage
- Pexels/Pixabay/YouTube/other provider integrations
- web searching
- automatic downloading
- AI asset generation
- asset-to-AssetRequirement mapping
- asset fulfillment workflow
- video rendering
- FFmpeg processing
- thumbnail generation
- asset quality gate
- production readiness gate

Those belong to later Phase 4 parts.

==================================================
STEP 0 — INSPECT THE REPOSITORY FIRST
==================================================

Before changing anything:

1. Inspect the current repository structure.
2. Inspect existing models, migrations, enums, services, policies,
   controllers, resources, requests, routes, frontend types, and tests.
3. Inspect how Project ownership/scoping is currently implemented.
4. Inspect existing naming conventions.
5. Inspect the current AssetRequirement implementation from Phase 3 Part 15.
6. Inspect the current VisualPlan / VisualPlanItem relationships.
7. Inspect existing API response conventions.
8. Inspect existing authorization conventions.
9. Inspect existing factories and test conventions.
10. Inspect the latest Phase 3 documentation/checkpoint if available.

Do not assume the architecture from this prompt is identical to the repository.

Reuse existing patterns wherever possible.

If an existing structure already solves part of the problem, extend it
instead of creating a duplicate abstraction.

==================================================
DOMAIN CONTEXT
==================================================

Current production flow:

IDEA
↓
RESEARCH
↓
SCRIPT
↓
VISUAL PLAN
↓
ASSET REQUIREMENTS
↓
VISUAL PRODUCTION READINESS
↓
[ CURRENT TASK ]
ASSET MANAGER
↓
TTS / SUBTITLE
↓
VIDEO ENGINE

Phase 3 currently has:

VisualPlan
VisualPlanItem
AssetRequirement

An AssetRequirement describes what asset is needed.

An Asset is the actual media resource that will eventually satisfy one or
more requirements.

Important distinction:

AssetRequirement ≠ Asset

Example:

AssetRequirement:
"Video kucing berjalan di lantai"

Asset:
"cat-walking-01.mp4"

This task introduces the Asset side only.

Do NOT connect Asset directly to AssetRequirement yet.
That will be handled in a later Phase 4 part.

==================================================

1. # ASSET DOMAIN MODEL

Introduce an Asset model following the repository's existing conventions.

The Asset must belong to a Project.

Expected conceptual relationship:

Project
└── hasMany Assets

Asset
└── belongsTo Project

Do not introduce unnecessary polymorphic ownership unless the existing
architecture requires it.

Project ownership must follow the existing project-scoping model.

An Asset must never accidentally become globally accessible if the existing
application scopes resources by project/user.

================================================== 2. ASSET TYPE ENUM
==================================================

Create an AssetType enum if the repository does not already have an
equivalent.

Initial conceptual values:

- VIDEO
- IMAGE
- AUDIO
- OTHER

Use the repository's existing enum naming/value conventions.

Do not add unnecessary asset types.

The enum should provide useful helpers only if consistent with existing
project conventions.

For example, a helper such as:

fromMimeType(...)

may be useful later, but DO NOT implement speculative MIME detection logic
unless it is actually needed by this part.

Keep this enum intentionally small.

================================================== 3. ASSET STATUS ENUM
==================================================

Create an AssetStatus enum if no equivalent exists.

Initial conceptual lifecycle:

- PENDING
- AVAILABLE
- PROCESSING
- APPROVED
- REJECTED
- ARCHIVED

However:

FIRST inspect existing domain conventions.

If the repository's enum/value naming style differs, follow the repository.

Define an explicit transition graph only if the project already uses
transition graphs for similar lifecycle enums.

If implemented, keep transitions deterministic.

A reasonable conceptual lifecycle is:

pending
→ available
→ processing
→ approved

with rejection/archive paths as appropriate.

Do NOT build a large workflow engine.

Do NOT implement status transition API in this part unless the existing
architecture strongly suggests it belongs in the initial Asset foundation.

================================================== 4. DATABASE SCHEMA
==================================================

Create an assets table.

The exact column definitions must be adapted to the existing project
conventions after inspection.

The schema should support the following conceptual information:

Identity:

- id
- project_id

Classification:

- type
- status

Human-readable metadata:

- title
- description

File metadata:

- file_path
- file_name
- mime_type
- file_size

Media metadata:

- duration_seconds
- width
- height

Source metadata:

- source_url
- source_name
- license_type
- attribution

Additional:

- notes
- timestamps

Important:

These fields describe metadata.

This task does NOT implement actual file storage.

file_path may exist as metadata only.

Do not write files to disk in this task.

================================================== 5. DATABASE DESIGN RULES
==================================================

Follow the repository's existing conventions for:

- UUID/integer primary keys
- foreign keys
- nullable columns
- string lengths
- decimal/integer types
- timestamps
- indexes

project_id must have a proper foreign key relationship.

Use cascade behavior only if consistent with existing project relationships.

Consider indexes where they are clearly useful, especially:

- project_id
- status
- type

Do not add speculative indexes everywhere.

Avoid over-normalizing source/license metadata at this stage.

For example, do NOT create separate:

sources
licenses
asset_files
media_metadata

tables unless repository inspection proves they are already part of the
architecture.

Keep the Asset foundation simple.

================================================== 6. MODEL
==================================================

Create:

app/Models/Asset.php

(or the repository-equivalent location)

The model should:

- belong to Project
- expose appropriate fillable fields according to project conventions
- cast type to AssetType
- cast status to AssetStatus
- cast numeric metadata appropriately
- define useful relationships only
- use project conventions for factories/scopes if applicable

Potential relationship:

Asset
belongsTo Project

Do NOT add:

AssetRequirement relationship

yet.

That belongs to a later part.

================================================== 7. PROJECT RELATIONSHIP
==================================================

Update Project with:

assets()

if this follows the current project relationship pattern.

Expected conceptual result:

Project::assets()
-> HasMany Asset

Make sure this does not break existing Project relationships.

================================================== 8. SERVICE FOUNDATION
==================================================

Create an AssetService following the existing service architecture.

For example:

app/Services/AssetService.php

or the repository-equivalent structure.

The service should provide the minimum domain foundation required for
future CRUD work.

Possible operations:

- list assets within a project
- find asset within project scope
- create asset metadata
- update asset metadata
- delete/archive asset if repository conventions require it

IMPORTANT:

Do not implement actual file upload.

The create/update operations are metadata-only for this part.

Do not implement external provider calls.

Do not implement AssetRequirement linking.

Keep the service small.

If existing services use repositories, DTOs, actions, or another pattern,
follow that architecture instead of introducing a new pattern.

================================================== 9. AUTHORIZATION / POLICY
==================================================

Create AssetPolicy following the existing authorization model.

The policy must respect:

User
↓
Project ownership/access
↓
Asset belonging to Project

A user must not be able to access or mutate an Asset belonging to a project
they cannot access.

Cross-project access must not leak information.

Follow existing application behavior:

If the current API convention returns 404 for cross-scope resources,
preserve that behavior.

Do not invent a different authorization response convention.

================================================== 10. API RESOURCE FOUNDATION
==================================================

Create AssetResource following the existing API resource conventions.

Expose appropriate metadata such as:

- id
- project_id
- type
- status
- title
- description
- file_name
- mime_type
- file_size
- duration_seconds
- width
- height
- source_url
- source_name
- license_type
- attribution
- notes
- created_at
- updated_at

Do not expose internal implementation details unnecessarily.

Do not expose raw filesystem secrets or server paths if the existing API
architecture treats them as internal.

If file_path is exposed, follow existing conventions.

Otherwise keep it internal.

================================================== 11. REQUEST VALIDATION
==================================================

If the existing API architecture uses FormRequest classes for metadata
creation/update, introduce appropriate requests.

Validation should be conservative.

Examples:

title:

- nullable/string

description:

- nullable/string

type:

- valid AssetType

status:

- do not allow arbitrary client status changes unless the repository
  convention explicitly allows it

file_name:

- nullable/string

mime_type:

- nullable/string

file_size:

- nullable/non-negative integer

duration_seconds:

- nullable/non-negative numeric

width:

- nullable/positive integer

height:

- nullable/positive integer

source_url:

- nullable/valid URL

source_name:

- nullable/string

license_type:

- nullable/string

attribution:

- nullable/string

notes:

- nullable/string

Do not overvalidate metadata that is not actually required.

================================================== 12. API ROUTES
==================================================

Only introduce routes that are justified by this foundation.

If the existing architecture expects CRUD APIs to be created together,
you may add metadata CRUD endpoints.

For example:

GET /api/v1/projects/{project}/assets
POST /api/v1/projects/{project}/assets
GET /api/v1/projects/{project}/assets/{asset}
PATCH /api/v1/projects/{project}/assets/{asset}
DELETE /api/v1/projects/{project}/assets/{asset}

However:

FIRST inspect existing route conventions.

Do not introduce a second routing pattern.

The endpoints must operate on metadata only.

DELETE behavior must follow repository conventions.

If the domain prefers archive over physical deletion, implement that only
if it matches existing lifecycle patterns.

Do not create a public download endpoint.

Do not create an upload endpoint.

================================================== 13. FRONTEND FOUNDATION
==================================================

Keep frontend scope intentionally small.

If the existing application architecture expects TypeScript API types for
new backend resources, introduce:

Asset type definitions

and

assetService.ts

only as much as required to keep the frontend architecture consistent.

Do NOT build a complete Asset Manager UI yet.

Do NOT build:

- asset browser
- upload UI
- drag-and-drop
- preview player
- search interface
- filters
- requirement mapping UI

Those belong to later parts.

If a minimal API integration is necessary for type/service consistency,
implement only the foundation.

================================================== 14. TESTING
==================================================

Add comprehensive automated tests following existing conventions.

At minimum cover:

A. Asset creation

- valid metadata creates asset
- asset belongs to correct project

B. Asset type

- valid enum values accepted
- invalid type rejected

C. Asset status

- default status behaves correctly
- invalid status cannot be persisted through validated API

D. Metadata validation

- invalid file size rejected
- invalid dimensions rejected
- invalid duration rejected
- invalid source URL rejected

E. Project relationship

- project assets relationship works

F. Authorization

- authorized user can access own project's assets
- unauthorized user cannot access another project's assets
- cross-project asset access does not leak data

G. CRUD

- list
- create
- show
- update
- delete/archive according to implemented behavior

H. Serialization

- AssetResource contains expected fields

I. Database integrity

- project foreign key
- appropriate nullable behavior
- casts/enums behave correctly

Follow the repository's established testing style.

Do not weaken existing tests.

================================================== 15. SECURITY
==================================================

Pay particular attention to:

- project scoping
- authorization
- mass assignment
- user-controlled paths
- arbitrary filesystem access
- arbitrary URL fetching

This task must NOT fetch source_url.

source_url is metadata only.

Never download anything from source_url.

Never execute a path supplied by the client.

Never treat file_path as a command.

================================================== 16. MIGRATION VERIFICATION
==================================================

Run:

- migration up
- migration rollback
- migration up again

using the repository's established process.

Ensure the schema is reversible.

Do not leave migration artifacts or temporary files.

================================================== 17. QUALITY VERIFICATION
==================================================

After implementation run the relevant checks.

At minimum:

Backend:

- php artisan test

Frontend:

- npx tsc --noEmit
- npm run build

Formatting:

- Laravel Pint or the repository's formatter

Also run relevant focused tests before the full suite.

Do not ignore failures.

If there are pre-existing failures, clearly distinguish:

- newly introduced failures
- pre-existing failures

Do not modify unrelated files merely to make the checks pass.

================================================== 18. DEBUG / SECURITY SWEEP
==================================================

Before completion inspect changed files for:

- dd()
- dump()
- var_dump()
- console.log()
- temporary debug code
- hardcoded credentials
- hardcoded API keys
- unsafe filesystem access
- arbitrary shell execution
- accidental network requests

Remove anything introduced by this task.

================================================== 19. DOCUMENTATION
==================================================

Create:

docs/PHASE_4_PART_1.md

Document:

1. Objective
2. Existing architecture inspected
3. Asset domain model
4. AssetType
5. AssetStatus
6. Database schema
7. Relationships
8. Service layer
9. Authorization
10. API endpoints
11. Validation
12. Tests
13. Verification results
14. Known limitations
15. Phase 4 Part 2 handoff

Clearly state:

- no file upload exists yet
- no external asset search exists
- no external download exists
- no Asset ↔ AssetRequirement mapping exists yet
- asset readiness is NOT implemented yet

================================================== 20. PHASE 4 PART 2 HANDOFF
==================================================

The foundation should prepare for the next part:

Phase 4 Part 2 — Asset Library / CRUD.

Part 2 will build a usable Asset Library on top of this foundation.

It may introduce:

- asset list
- search/filter
- asset detail
- metadata editing
- status handling
- basic asset management UI

But DO NOT implement those features now unless required by the existing
repository architecture.

================================================== 21. GIT CHECKPOINT
==================================================

After all implementation and verification:

1. Inspect git diff.
2. Inspect git status.
3. Ensure no unrelated changes are included.
4. Commit the completed work.

Suggested commit message:

feat: add asset domain foundation

DO NOT push to GitHub.

================================================== 22. FINAL REPORT
==================================================

When finished, report:

1. Summary
2. Repository inspection findings
3. Files created
4. Files modified
5. Database changes
6. AssetType design
7. AssetStatus design
8. API endpoints
9. Authorization behavior
10. Tests added
11. Full test result
12. TypeScript result
13. Build result
14. Formatter result
15. Migration verification
16. Security/debug sweep
17. Git commit hash
18. Git status
19. Known limitations
20. Phase 4 Part 2 handoff

IMPORTANT:
Do not claim completion unless the implementation and verification actually
succeeded.

If something cannot be completed, report it explicitly.

==================================================
CORE PRINCIPLE
==================================================

Keep this part small.

The goal is NOT to build the Asset Manager.

The goal is to establish a clean, secure, testable Asset domain foundation
that later Phase 4 parts can safely build upon.

Do not prematurely implement future functionality.

==================================================
PART 1 COMPLETION REPORT
==================================================

1. SUMMARY

Phase 4 Part 1 introduced the Asset domain foundation: a project-owned
Asset entity with metadata persistence, type and status enums, a scoped
service layer, an ownership-enforcing policy, an API resource, a factory,
metadata CRUD endpoints, TypeScript types, and 121 automated tests.

No file was uploaded, stored, read, or deleted. No URL was fetched. No
provider was contacted. No AssetRequirement was linked. No status
transition endpoint exists. There is no Asset Manager UI.

The whole part is metadata only, which is the point: a later part can add
real files on top of a domain that already knows what an asset is, who
owns it, and which project it belongs to.

2. REPOSITORY INSPECTION FINDINGS

Architecture observed and reused, not redesigned:

- Enums use PascalCase cases with lowercase string values and a label()
  helper. Lifecycle enums additionally expose allowedTransitions() and
  canTransitionTo(). AssetStatus follows that shape; AssetType does not,
  because a type has no lifecycle.
- Project owned tables all use content_project_id, not project_id:
  scripts, research_reports, and visual_plans all do this. The assets
  table follows them so it can use constrained('content_projects').
- Every existing policy returned true unconditionally. There was no
  user level authorization anywhere in the application, and not one test
  in the suite asserted a 403.
- Cross scope protection is implemented in services, not policies.
  ResearchService::assertSourceBelongsToReport compares the child's
  foreign key against the parent's and throws ModelNotFoundException,
  which controllers turn into a 404. AssetService follows that pattern.
- Nested project resources use explicit routes inside a
  Route::prefix('projects/{project}/...') group with whereNumber on the
  child parameter. apiResource is reserved for top level resources.
- Responses use the ApiResponse trait shape: success, data, message,
  errors. Collections are wrapped as data.items.
- Form requests live in a per domain folder under app/Http/Requests and
  use Illuminate\Validation\Rules\Enum with sometimes on update.
- Lists are ordered latest('id').
- Factories use @extends Factory<Model> docblocks. Only UserFactory
  declares states, so AssetFactory declares none.

Three decisions were taken explicitly rather than inherited, because
the repository offered no precedent:

a) Foreign key name. The spec said project_id. The repository said
   content_project_id in three places. The repository won, so the API
   field is content_project_id.

b) Authorization. The spec required that a user cannot reach an asset in
   a project they cannot access, but the codebase had no notion of that.
   AssetPolicy is therefore the first policy in the repository that
   actually denies anything, and it authorizes against
   content_projects.created_by. See section 9.

c) file_path ownership. The spec's validation list pointedly omits
   file_path, so it is never read from a request on create or update,
   and is not mass assignable from a controller. The column exists for a
   later part that genuinely stores a file.

3. FILES CREATED

- app/Enums/AssetType.php
- app/Enums/AssetStatus.php
- app/Models/Asset.php
- app/Policies/AssetPolicy.php
- app/Services/AssetService.php
- app/Http/Resources/AssetResource.php
- app/Http/Requests/Asset/StoreAssetRequest.php
- app/Http/Requests/Asset/UpdateAssetRequest.php
- app/Http/Controllers/Api/V1/AssetController.php
- database/factories/AssetFactory.php
- database/migrations/2026_09_30_023654_create_assets_table.php
- resources/js/services/assetService.ts
- tests/Unit/AssetStatusTest.php
- tests/Unit/AssetTypeTest.php
- tests/Feature/AssetTest.php
- tests/Feature/AssetApiTest.php

4. FILES MODIFIED

- app/Models/ContentProject.php      added assets() hasMany relation
- routes/api.php                     added the projects/{project}/assets group
- resources/js/types/index.ts        added AssetType, AssetStatus, Asset, AssetFormData

No existing test was weakened, renamed, or deleted.

5. DATABASE CHANGES

One new table, assets, with 20 columns:

  id                   bigint unsigned, primary key
  content_project_id   bigint unsigned, FK to content_projects, ON DELETE CASCADE
  type                 varchar(50), required
  status               varchar(20), required, default 'pending'
  title                varchar(255), nullable
  description          text, nullable
  file_path            varchar(255), nullable, never client supplied, never exposed
  file_name            varchar(255), nullable
  mime_type            varchar(255), nullable
  file_size            bigint unsigned, nullable
  duration_seconds     int unsigned, nullable
  width                int unsigned, nullable
  height               int unsigned, nullable
  source_url           varchar(2048), nullable, metadata only
  source_name          varchar(255), nullable
  license_type         varchar(100), nullable
  attribution          varchar(255), nullable
  notes                text, nullable
  created_at           timestamp, nullable
  updated_at           timestamp, nullable

Indexes:

  assets_content_project_id_status_index  (content_project_id, status)
  assets_content_project_id_type_index    (content_project_id, type)

Both exist because Part 2 will filter a project's own assets by status
and by type, and the foreign key's own index cannot serve either query.
No other speculative index was added.

The numeric metadata columns are unsigned at the database level, not only
in validation, so a negative size, duration, or dimension is refused even
if validation is ever bypassed.

Cascade delete matches scripts, research reports, visual plans, and
asset requirements: removing a project removes its assets, because an
asset is meaningless without the project it was collected for.

6. ASSETTYPE DESIGN

Four cases, matching the spec exactly and deliberately no more:

  Video  = 'video'
  Image  = 'image'
  Audio  = 'audio'
  Other  = 'other'

It exposes only label(), consistent with the other non-lifecycle enums
such as SourceType.

There is no fromMimeType() helper. The spec named one as a possible
future convenience and explicitly forbade speculative MIME detection
logic, so it was not added. Inferring a type from a MIME string is a
guess, and a wrong guess writes bad data that later parts would trust.

7. ASSETSTATUS DESIGN

Six cases:

  Pending     = 'pending'
  Available   = 'available'
  Processing  = 'processing'
  Approved    = 'approved'
  Rejected    = 'rejected'
  Archived    = 'archived'

A deterministic transition graph is defined, because this repository
already uses transition graphs for ScriptStatus, VisualPlanStatus, and
AssetRequirementStatus:

  pending     -> available, archived
  available   -> processing, approved, rejected, archived
  processing  -> available, approved, rejected
  approved    -> processing, archived
  rejected    -> pending, archived
  archived    -> pending

The shape is a directed lifecycle, not a symmetric one. Available
reaches Approved, but Approved does not fall back to Available: it goes
back through Processing if it needs rework. Processing can return to
Available because a failed job leaves nothing to show. Archived returns
to Pending only, because restoring an archived asset is a retry.

No status is a dead end, and no status can transition to itself.

This graph is currently documentation rather than a reachable state
machine. Part 1 only ever creates Pending rows and exposes no transition
endpoint, so the other five statuses cannot yet be entered through the
API. That is a deliberate consequence of the spec's instruction not to
build a status transition API in this part.

8. API ENDPOINTS

Five endpoints, all under the existing auth:sanctum group:

  GET    /api/v1/projects/{project}/assets
  POST   /api/v1/projects/{project}/assets
  GET    /api/v1/projects/{project}/assets/{asset}
  PATCH  /api/v1/projects/{project}/assets/{asset}
  DELETE /api/v1/projects/{project}/assets/{asset}

{asset} is constrained with whereNumber, matching the visual plan and
asset requirement routes.

There is deliberately no upload endpoint, no download endpoint, no
status transition endpoint, and no file streaming route. Two tests
assert those routes do not exist, so a later part cannot add one by
accident.

9. AUTHORIZATION

AssetPolicy is the first policy in this repository that denies
anything. Every pre-existing policy returns true.

The rule is a single one, applied by viewAny, view, create, update, and
delete:

    the user must be the creator of the project that owns the asset

viewAny and create receive the project as an explicit policy argument,
because those two abilities are decided by the project rather than by an
existing row. Authorizing the bare model class, as the rest of the
repository does, would let any authenticated user read or write any
project's assets.

Responses:

  asset in a project the caller does not own, requested through its
  real project     403

  asset requested through a project it does not belong to
                      404, identical message to a genuinely
                      non-existent asset

The 404 comes from AssetService::findAsset, which reads through
$project->assets() rather than Asset::find(). The same message is used
for both cases, so a caller cannot tell "does not exist" from "belongs
to someone else" and cannot enumerate ids across projects.

A project whose created_by is null is owned by nobody and denies
everyone. This is fail closed on purpose: a missing owner is a data
problem, and inventing an owner would be worse than refusing access.
ContentProjectFactory always sets created_by, so this only affects rows
created outside the factory.

One test asserts that a 403 body contains no asset title, description,
source url, or file path, and no data key at all.

10. VALIDATION

Conservative metadata rules on both requests:

  type                required on store, sometimes on update, Enum AssetType
  title               nullable string, max 255
  description         nullable string
  file_name           nullable string, max 255
  mime_type           nullable string, max 255
  file_size           nullable integer, min 0
  duration_seconds    nullable integer, min 0
  width               nullable integer, min 1
  height              nullable integer, min 1
  source_url          nullable string, url, max 2048
  source_name         nullable string, max 255
  license_type        nullable string, max 100
  attribution         nullable string, max 255
  notes               nullable string

Zero is accepted for file_size and duration_seconds, because a
legitimately empty file is zero bytes, not an error. Zero is rejected
for width and height, because a zero dimension is never a real
measurement.

status is not a validated field on either request. It is not on store,
because the service always writes Pending, and not on update, because
moving an asset through its lifecycle is a different operation. A
client supplied status is silently ignored rather than rejected, which
matches how AssetRequirementService already handles it. Two tests assert
that a client supplied Approved or Archived status is never persisted.

file_path is not validated because it is not accepted. It is absent
from both rule sets and from the service payload, so a path traversal
string in a request is dropped before it reaches the model.

11. TESTS

121 new tests, 274 new assertions.

tests/Unit/AssetStatusTest.php
  Every case and label, the full transition graph case by case, all
  valid and invalid pairs, self transitions refused, and no dead ends.

tests/Unit/AssetTypeTest.php
  The four cases and their labels.

tests/Feature/AssetTest.php
  Project relationship both directions, an empty project, factory
  defaults, enum and integer casts, all nullable columns, foreign key
  enforcement, cascade delete, selective delete, project scoped listing,
  create forcing Pending, file_path refused on create and on update,
  partial update, nulling an optional field, update cannot change
  status, cross project find throws, unknown find throws, delete
  through the service, no disk write and no HTTP call, every type and
  every status persistable, content_project_id not mass assignable,
  and the absence of any AssetRequirement relationship.

tests/Feature/AssetApiTest.php
  Authentication on all five endpoints, list, show, store, update,
  destroy, empty list, per project list isolation, create with full
  metadata, create with only a type, client status ignored, client
  file_path never persisted, no disk or network call, all four types
  accepted, invalid type rejected, missing type rejected, negative and
  non integer file size rejected, zero file size accepted, negative
  duration rejected, zero and negative dimensions rejected, invalid
  source url rejected, over long title rejected, partial update, update
  cannot change status, update cannot change file_path, update
  validation, 404 for cross project update and destroy, owner can do
  all five, a non owner is forbidden on all five, a 403 body leaks
  nothing, cross project read is a 404, an ownerless project denies
  everyone, resource structure and the absence of file_path in both
  show and index, enum labels, newest first ordering, and the absence of
  upload, download, and status transition endpoints.

12. VERIFICATION RESULTS

  Focused tests      121 passed, 0 failed
  Full suite         963 passed, 0 failed, 3082 assertions
                     baseline before this part was 842 passed,
                     2808 assertions, so 121 tests were added and
                     nothing pre-existing broke
  npx tsc --noEmit   clean, no errors
  npm run build      built in 2.08s, 1975 modules transformed
  Pint               one fix applied, an unused import in AssetTest

13. MIGRATION VERIFICATION

Run against the local development database:

  up       create_assets_table DONE in 119.13ms
  schema   inspected, 20 columns, one cascade foreign key, two
           composite indexes, unsigned numerics confirmed
  rollback create_assets_table DONE in 31.43ms
           Schema::hasTable('assets') reported DROPPED
  up       create_assets_table DONE in 145.00ms
           20 columns present again

The migration is fully reversible and left no artifacts.

14. SECURITY AND DEBUG SWEEP

Confirmed by reading the diff and by tests:

  No dd, dump, var_dump, console.log, ray, TODO, or FIXME in any new or
  modified file.

  No filesystem access. No Storage call, no file write, no read, no
  unlink. A test asserts Storage::disk() is never called while creating
  an asset.

  No network access. Every test that touches the asset code paths calls
  Http::preventStrayRequests(), so a stray request would fail the test
  rather than pass silently. source_url is stored as text and never
  fetched; a test posts a plausible provider URL and asserts no request
  happens.

  No user controlled path. file_path is not a request field, is not in
  either rule set, is not in the service payload, and cannot be set
  through mass assignment. A test posts a traversal string on both
  create and update and asserts it is not persisted.

  No mass assignment of ownership. content_project_id was removed from
  fillable, deviating from sibling models on purpose, so ownership
  cannot be reassigned by an update.

  Project scoping. Every read goes through $project->assets(). There is
  no code path that resolves an Asset globally.

  Status cannot be set by a client on create or update.

  No credentials, keys, or secrets were added. No shell execution, no
  process invocation, no eval.

15. GIT

Committed as feat: add asset domain foundation, and not pushed.

16. KNOWN LIMITATIONS

These are intentional, not gaps to be quietly patched later:

  - No file exists behind any asset. Every file metadata column is
    normally null, and status is always Pending.
  - There is no file upload, storage driver, S3, or filesystem
    workflow.
  - There is no external asset search, no provider integration, and no
    download. source_url is never fetched.
  - There is no Asset to AssetRequirement mapping, so an asset satisfies
    nothing yet.
  - There is no asset fulfillment workflow and no asset quality or
    readiness gate.
  - Five of the six statuses cannot be reached through the API, because
    the transition graph is defined but no transition endpoint exists.
  - There is no frontend UI. Only TypeScript types and an API service
    were added, so the endpoints are not yet reachable from the app.
  - AssetPolicy enforces project creator ownership, which the rest of
    the application does not. Asset is consequently the only resource
    with real per user authorization. Other policies still return true,
    so this part is stricter than its neighbours by design, and a later
    part may want to extend ownership enforcement to the rest of the
    domain rather than leave the codebase inconsistent.
  - DELETE removes the row rather than archiving it. No file exists to
    keep, and the repository's other resources delete outright, so
    archive-on-delete was not introduced for one resource.
  - No pagination on the asset list, consistent with the other list
    endpoints in this repository.

17. PHASE 4 PART 2 HANDOFF

Part 2 builds the Asset Library on this foundation. What it inherits:

  - A project scoped Asset with a fixed metadata contract.
  - An AssetType of four values and an AssetStatus of six values with a
    decided transition graph, so the lifecycle does not have to be
    re-invented.
  - Ownership enforced through content_projects.created_by, and
    cross project reads answered with a 404 that leaks nothing.
  - A service whose writes are already safe by construction: the status
    is server decided and no request can reach a filesystem path.

What Part 2 is likely to add, and where to be careful:

  - The status transition endpoint the graph is already waiting for. It
    must validate against AssetStatus::canTransitionTo, and must not
    become a free status setter.
  - Upload, which is the first thing that will want to write file_path.
    That write belongs in the storage layer, never in a request payload,
    and this part's tests are the guarantee that it currently cannot be
    reached from the client.
  - Search and filters over a project's assets. The two composite
    indexes added here are sized for exactly that.
  - The Asset Manager UI. No component, panel, or tab was built, and
    the frontend work is limited to types and one service.
  - Pagination, if the library ever lists more assets than fit on a
    screen.

One distinction to carry forward. Planning Ready from Part 16 means the
requirements are specified. It says nothing about assets. An asset row
in Part 1 is metadata, and a Pending asset with a null file_name is the
honest default state of this part, not a usable media file.
