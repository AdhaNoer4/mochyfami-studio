# MochyFami Content Studio

# Phase 4 — Part 2: Asset Library / CRUD

You are continuing work on the existing MochyFami Content Studio repository.

Previous checkpoint:

Phase 4 Part 1 — Asset Domain Foundation
Commit: 8f94f05
Status: completed locally, not pushed

Part 1 established:

- Asset model
- AssetType enum
- AssetStatus enum
- AssetPolicy
- AssetService foundation
- AssetResource
- StoreAssetRequest
- UpdateAssetRequest
- AssetController
- AssetFactory
- assets migration
- Project → assets relationship
- frontend Asset types
- frontend assetService foundation
- metadata-only Asset CRUD API

Verification from Part 1:

- 963 tests passed
- 3082 assertions
- TypeScript clean
- build clean
- Pint clean
- migration reversible
- security/debug sweep clean

==================================================
OBJECTIVE
==================================================

Implement:

Phase 4 Part 2 — Asset Library / CRUD

The goal is to turn the Asset foundation from Part 1 into a usable
metadata-based Asset Library.

Users should be able to:

- view assets belonging to a project
- search assets
- filter assets
- sort assets
- paginate assets
- view asset details
- create asset metadata
- edit asset metadata
- archive/delete assets according to the existing domain rules

The Asset Library must be project-scoped and authorization-safe.

IMPORTANT:

This part is STILL metadata-only.

==================================================
NON-GOALS
==================================================

DO NOT implement:

- file upload
- drag-and-drop upload
- filesystem storage
- S3/cloud storage
- file download
- public file URLs
- Pexels integration
- Pixabay integration
- YouTube integration
- web searching
- automatic downloading
- AI asset generation
- asset processing
- FFmpeg
- video preview from actual files
- thumbnail generation
- Asset ↔ AssetRequirement mapping
- asset fulfillment
- production readiness gate

Those belong to later Phase 4 parts.

==================================================
STEP 0 — INSPECT THE REPOSITORY
==================================================

Before modifying anything:

1. Inspect the implementation from Phase 4 Part 1.
2. Inspect existing CRUD pages and panels in the React application.
3. Inspect existing list/search/filter/pagination implementations.
4. Inspect Content Ideas CRUD.
5. Inspect Projects CRUD.
6. Inspect Research UI patterns.
7. Inspect Script UI patterns.
8. Inspect VisualPlan UI patterns.
9. Inspect existing API pagination conventions.
10. Inspect existing query/filter conventions.
11. Inspect existing empty/loading/error states.
12. Inspect existing modal/form patterns.
13. Inspect existing authorization patterns.
14. Inspect existing test conventions.

Reuse existing patterns.

Do NOT create a new UI architecture if an existing reusable pattern
already exists.

==================================================

1. # ASSET LIBRARY API

Expand the existing Asset API to support a proper list endpoint.

Expected conceptual endpoint:

GET /api/v1/projects/{project}/assets

The endpoint must support:

- pagination
- search
- type filtering
- status filtering
- sorting

Follow existing repository conventions for parameter names.

Do not blindly use the parameter names below if the repository already
has established conventions.

Possible query parameters:

search
type
status
sort
direction
per_page

Example:

GET /api/v1/projects/1/assets?search=cat&type=video&status=available

================================================== 2. SEARCH
==================================================

Implement server-side asset search.

Search should be deterministic.

Search should cover useful human-readable fields.

Potential fields:

- title
- description
- file_name
- source_name
- license_type
- attribution
- notes

Use only fields that make sense for the existing schema.

Do NOT search file contents.

Do NOT perform fuzzy AI search.

Do NOT search external websites.

Do NOT search source URLs remotely.

Search must remain inside the current project's assets.

Example:

search=cat

must NEVER return an Asset from another project.

================================================== 3. FILTERING
==================================================

Support useful filters.

At minimum:

type
status

Potential values must be validated against the existing enums.

Invalid filter values should be handled according to existing API
validation conventions.

Do not silently accept arbitrary values.

Do not add speculative filters.

================================================== 4. SORTING
==================================================

Support deterministic sorting.

At minimum provide useful sorting such as:

- created_at
- updated_at
- title
- file_size
- duration_seconds

However:

FIRST inspect existing list APIs.

If the application already has a standard whitelist of sortable fields,
reuse it.

Never interpolate arbitrary client input directly into SQL ORDER BY.

Use an explicit whitelist.

Provide deterministic secondary ordering when useful.

For example:

ORDER BY created_at DESC, id DESC

This prevents unstable pagination when records share timestamps.

================================================== 5. PAGINATION
==================================================

Use the repository's existing pagination convention.

Do not create a custom pagination response if one already exists.

The response should contain the normal metadata required by the frontend,
such as:

- current page
- per page
- total
- last page

Follow the existing API Resource/ResourceCollection pattern.

================================================== 6. ASSET DETAIL
==================================================

Ensure the existing show endpoint provides a complete Asset metadata view.

Conceptual:

GET /api/v1/projects/{project}/assets/{asset}

It should return:

- id
- project
- type
- status
- title
- description
- file metadata
- media metadata
- source metadata
- notes
- timestamps

Do not expose unsafe internal implementation details.

Do not generate download URLs.

Do not expose server filesystem paths unless the existing architecture
explicitly treats them as safe public metadata.

================================================== 7. CREATE ASSET
==================================================

Keep the existing metadata-only creation workflow.

The frontend should be able to create an Asset record without a file.

Example conceptual data:

title:
"Cat Walking #01"

type:
video

description:
"Cat walking across the floor"

source_name:
"Pexels"

source_url:
"https://example.com/..."

license_type:
"Free to use"

duration_seconds:
8.4

width:
1080

height:
1920

IMPORTANT:

Do NOT add file upload.

file_path remains unavailable unless explicitly provided by an internal
trusted mechanism.

Do not allow users to use this endpoint to access arbitrary filesystem
paths.

================================================== 8. UPDATE ASSET
==================================================

The frontend must be able to edit metadata.

Editable fields should follow the Part 1 request validation.

Typical fields:

- title
- description
- type
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

IMPORTANT:

Do not allow the client to change:

- project ownership
- created_by
- file_path
- other security-sensitive fields

unless the existing architecture explicitly requires it.

Status must remain controlled according to Part 1 rules.

Do not introduce arbitrary client-controlled status changes.

================================================== 9. ARCHIVE / DELETE BEHAVIOR
==================================================

Inspect the Part 1 implementation.

Follow the established AssetStatus lifecycle.

If the current architecture uses archive semantics:

- provide an archive action
- archived assets remain stored as records
- archived assets should not appear in default active library results

If the current architecture uses physical deletion:

follow that convention instead.

Do NOT introduce both archive and hard-delete behavior unless justified
by the existing architecture.

If adding an archive endpoint, use an explicit route such as:

PATCH /api/v1/projects/{project}/assets/{asset}/archive

Only add this if it fits the existing domain conventions.

================================================== 10. DEFAULT LIBRARY BEHAVIOR
==================================================

The default Asset Library should show active/useful assets.

Determine from the Part 1 status design whether archived assets should:

- be excluded by default
- be included only with status=archived
- or follow another established project convention

Document the decision.

Do not silently hide data without a documented reason.

================================================== 11. FRONTEND ASSET LIBRARY
==================================================

Create the Asset Library UI following the existing React architecture.

Find the appropriate location in the existing application.

Potential page:

/projects/:id/assets

OR an existing project Assets tab/page if the application architecture
already has a natural location.

Do NOT create duplicate navigation structures.

The library should include:

- page/header
- asset count
- search
- type filter
- status filter
- sorting
- pagination
- asset list/grid
- create asset action
- edit asset action
- asset detail view
- empty state
- loading state
- error state

================================================== 12. ASSET CARD / ROW
==================================================

Create a reusable Asset presentation component if consistent with the
existing component architecture.

Since actual files do not exist yet, the UI must NOT pretend there is
a working media preview.

For example, the card may show:

[VIDEO]

Cat Walking #01

VIDEO
AVAILABLE

1080 × 1920
8.4 sec

Pexels

Created:
30 Sep 2026

Do NOT show a fake video thumbnail.

Do NOT use placeholder media that could be mistaken for the actual asset.

A type icon/badge is acceptable.

================================================== 13. ASSET DETAIL UI
==================================================

Provide a detail view/modal/page consistent with existing UI patterns.

Show metadata grouped logically.

Suggested groups:

Basic:

- title
- description
- type
- status

File:

- file name
- MIME type
- file size

Media:

- duration
- width
- height
- aspect ratio if useful

Source:

- source name
- source URL
- license
- attribution

Notes:

- notes

Do not create actual playback.

Do not create a download button.

Do not generate fake preview URLs.

================================================== 14. CREATE / EDIT FORM
==================================================

Build a reusable metadata form if consistent with existing patterns.

Validation errors should be displayed clearly.

The form must:

- prevent invalid enum values
- validate numeric values
- validate URL format
- prevent negative dimensions
- prevent negative file sizes
- prevent negative durations
- handle nullable fields correctly

Status should NOT be an unrestricted form field.

If archive functionality exists, expose it as a deliberate action rather
than allowing arbitrary status editing.

================================================== 15. SEARCH UX
==================================================

Search should be debounced only if the existing project already uses
debounced search patterns.

Otherwise follow existing list behavior.

Important:

Do not trigger a request on every keystroke if that would conflict with
existing UX conventions.

Search state must be reflected in the URL/query state if the existing
application uses URL-synchronized filters.

If the repository does not use URL state, follow its existing convention.

================================================== 16. FILTER UX
==================================================

Provide:

Type:

- All
- Video
- Image
- Audio
- Other

Status:

- All
- Pending
- Available
- Processing
- Approved
- Rejected
- Archived

Only show statuses that actually exist in AssetStatus.

Do not hardcode labels independently from the Type/Status definitions if
the frontend already has a shared enum/label mapping convention.

================================================== 17. SORT UX
==================================================

Provide a simple sort control.

Possible choices:

- Newest
- Oldest
- Title A–Z
- Title Z–A
- Largest file
- Longest duration

Only expose sorting options supported by the backend.

Do not build an overly complicated advanced query builder.

================================================== 18. FRONTEND SERVICE
==================================================

Expand:

resources/js/services/assetService.ts

to support:

- listAssets
- getAsset
- createAsset
- updateAsset
- archive/delete according to implementation

Use the project's existing API client conventions.

Types must match backend resources.

Do not introduce a second HTTP client.

================================================== 19. FRONTEND TYPES
==================================================

Expand the existing Asset types only as required.

Include:

Asset
AssetType
AssetStatus
AssetListQuery
AssetListResponse

if consistent with the existing frontend type architecture.

Avoid duplicate definitions.

================================================== 20. AUTHORIZATION TESTING
==================================================

Add tests proving:

1. User can list assets from an accessible project.
2. User cannot list assets from another user's project.
3. User can view their own project's asset.
4. User cannot view another project's asset.
5. User cannot update another project's asset.
6. User cannot delete/archive another project's asset.
7. Search never escapes project scope.
8. Filters never escape project scope.
9. Sorting never escapes project scope.

Cross-project behavior must preserve the existing 404/403 conventions.

================================================== 21. API TESTING
==================================================

Add tests for:

LIST:

- default pagination
- custom per_page
- search
- type filter
- status filter
- sorting
- invalid filter
- invalid sort field
- combined filters

CREATE:

- valid metadata
- validation errors
- server-controlled ownership
- server-controlled status

SHOW:

- correct asset
- missing asset
- cross-project asset

UPDATE:

- valid metadata update
- invalid metadata
- forbidden ownership changes
- forbidden project changes
- status remains protected

DELETE/ARCHIVE:

- expected lifecycle behavior
- cross-project protection

================================================== 22. FRONTEND TESTING
==================================================

Inspect whether the repository already has a frontend testing framework.

If one exists, add appropriate tests.

If one does NOT exist:

DO NOT install a large new testing stack merely for this part.

Document that frontend behavior was verified through:

- TypeScript
- production build
- manual component inspection
- existing backend feature tests

Do not create unnecessary infrastructure.

================================================== 23. PERFORMANCE / QUERY SAFETY
==================================================

Asset list queries must remain efficient.

Avoid:

- N+1 queries
- loading all assets before filtering
- filtering in PHP when SQL can do it
- arbitrary ORDER BY
- unnecessary relationships

Use database-level:

- WHERE
- LIKE / appropriate search
- filtering
- ordering
- pagination

where appropriate.

Do not prematurely optimize.

================================================== 24. DATABASE INDEXES
==================================================

Inspect the Part 1 migration.

Only add indexes if the new search/filter/sort behavior clearly benefits
from them.

Potential useful indexes:

- content_project_id + status
- content_project_id + type
- content_project_id + created_at

Do not blindly add indexes for every searchable text column.

If no migration is necessary, do not create one.

If a migration is necessary, verify:

- migrate
- rollback
- migrate again

================================================== 25. API RESPONSE CONSISTENCY
==================================================

The Asset API must behave consistently with:

- Ideas API
- Projects API
- Research API
- Script API
- VisualPlan API

Reuse existing response envelopes.

Do not invent:

{
"success": true,
...
}

if the project does not use that pattern.

Follow the repository.

================================================== 26. SECURITY SWEEP
==================================================

Inspect all changed files for:

- mass assignment vulnerabilities
- project ID manipulation
- ownership manipulation
- arbitrary SQL order clauses
- unsafe query construction
- unvalidated enum values
- filesystem access
- network calls
- debug statements
- hardcoded secrets
- accidental cross-project queries

Especially verify that:

GET /projects/A/assets?search=...

cannot return Project B assets.

================================================== 27. DOCUMENTATION
==================================================

Create or update:

docs/PHASE_4_PART_2.md

Document:

1. Objective
2. Existing architecture reused
3. Asset Library API
4. Search
5. Filters
6. Sorting
7. Pagination
8. CRUD behavior
9. Archive/delete behavior
10. Frontend implementation
11. Authorization
12. Tests
13. Verification
14. Known limitations
15. Phase 4 Part 3 handoff

Clearly state:

- Asset Library is metadata-only.
- No file upload exists yet.
- No actual media preview exists yet.
- No external asset provider exists yet.
- No Asset ↔ AssetRequirement mapping exists yet.

================================================== 28. VERIFICATION
==================================================

Run focused Asset tests first.

Then run:

php artisan test

npx tsc --noEmit

npm run build

Laravel Pint or the repository formatter.

Also verify:

- migration up
- migration rollback
- migration up

if migrations changed.

Do not ignore failures.

Clearly distinguish pre-existing failures from new failures.

================================================== 29. GIT CHECKPOINT
==================================================

Before committing:

- inspect git diff
- inspect git status
- verify only intended files changed
- ensure no temporary files
- ensure no generated build artifacts are accidentally committed

Commit:

feat: add asset library and crud

DO NOT push.

================================================== 30. FINAL REPORT
==================================================

Return a completion report containing:

1. Summary
2. Repository inspection findings
3. Backend files created
4. Backend files modified
5. Frontend files created
6. Frontend files modified
7. API endpoints
8. Search behavior
9. Filter behavior
10. Sort behavior
11. Pagination behavior
12. CRUD behavior
13. Archive/delete behavior
14. Authorization behavior
15. Tests added
16. Full test result
17. TypeScript result
18. Build result
19. Pint result
20. Migration verification
21. Security/debug sweep
22. Git commit hash
23. Git status
24. Known limitations
25. Phase 4 Part 3 handoff

Do not claim completion unless verification actually succeeded.

==================================================
CORE PRINCIPLE
==================================================

Part 2 should make Asset management usable,
but should NOT prematurely implement asset files.

The separation must remain:

AssetRequirement
=
"What media do we need?"

Asset
=
"What media resource do we have?"

Part 3 will establish the relationship between those two concepts.

Keep this part focused, testable, and consistent with the existing
MochyFami Content Studio architecture.




==================================================
PART 2 IMPLEMENTATION REPORT
==================================================

================================================== 1. SUMMARY
==================================================

Phase 4 Part 2 makes the Asset resource usable: a searchable, filterable,
sortable, paginated list API plus a project-scoped Asset Library UI with
create, view, edit, and delete for metadata.

The resource remains metadata-only. No file is uploaded, stored, downloaded,
or previewed, and no external provider is contacted. The whole feature is
built on the Part 1 foundation without changing its invariants.

================================================== 2. EXISTING ARCHITECTURE REUSED
==================================================

Backend:

- `ApiResponse` trait and the `{success, data, message}` envelope.
- The canonical paginated shape
  `{items, pagination: {total, per_page, current_page, last_page}}`,
  copied from `IdeaController::index`.
- `GetIdeasRequest` for query string validation: `Rule::in` for the sort
  allowlist, `new Enum(...)` for enum filters, `Rule::in([10, 25, 50])` for
  `per_page`.
- `IdeaService::paginateIdeas` for the allowlist plus secondary-order
  pagination shape.
- `AssetPolicy` and the project's own relation for scoping, both unchanged.
- The five Part 1 routes, unchanged. Part 2 changed response shape only.

Frontend:

- `apiClient` from `resources/js/lib/api.ts`. No second HTTP client.
- `PaginatedData<T>` and `PaginationMeta` already present in
  `resources/js/types/index.ts`; reused rather than redefined.
- `IdeaFilterBar` for the debounce, `DEFAULT_*_FILTERS`, clear button, and
  `page: 1` reset pattern.
- `IdeasListPage` for the skeleton grid, error card with retry, dual empty
  states, and inline pagination controls.
- `VisualPlanPanel` for `inputClasses` / `labelClasses`, the `let active`
  fetch guard, and the `getStatusBadgeVariant` switch.
- The existing `Button`, `Card`, and `Badge` primitives. No new UI library.
- The `ProjectDetailPage` tab strip, alongside Research, Script, and Visual
  Plan. The Assets tab sits next to the `assets` pipeline step that was
  already a placeholder.

Rejected: a dedicated `/projects/:id/assets` route. `router.tsx` matches
`startsWith('/projects/')` before the list branch, so that path would have
fallen through to `ProjectsListPage`. A tab needed no router change and no
second navigation entry, and it matches how every other project-scoped
resource is already presented.

================================================== 3. ASSET LIBRARY API
==================================================

No new endpoints. The Part 1 CRUD surface is now fully functional.

    GET    /api/v1/projects/{project}/assets
    POST   /api/v1/projects/{project}/assets
    GET    /api/v1/projects/{project}/assets/{asset}
    PATCH  /api/v1/projects/{project}/assets/{asset}
    DELETE /api/v1/projects/{project}/assets/{asset}

The index response gained a `pagination` object alongside `items`. Because
`data.items` is unchanged, the two Part 1 index tests kept passing
unmodified.

    {
      "success": true,
      "data": {
        "items": [ ... ],
        "pagination": {
          "total": 30,
          "per_page": 25,
          "current_page": 1,
          "last_page": 2
        }
      },
      "message": null
    }

================================================== 4. SEARCH
==================================================

`?search=` runs a single `LIKE` across `title`, `file_name`, `source_name`,
and `notes`, OR-ed inside one `where` closure.

The term is passed as a bound parameter by Eloquent, so a value containing
quotes or SQL wildcards cannot alter the query. The same term is bound
separately per column rather than interpolated.

================================================== 5. FILTERS
==================================================

- `type`: `video`, `image`, `audio`, `other`. Validated with
  `new Enum(AssetType::class)`.
- `status`: `pending`, `available`, `processing`, `approved`, `rejected`,
  `archived`. Validated with `new Enum(AssetStatus::class)`.

An unknown value returns 422 and never reaches the service. Filters are
plain equality comparisons, so they combine with search and sort without
special cases.

================================================== 6. SORTING
==================================================

Allowlist: `created_at`, `updated_at`, `title`, `file_size`,
`duration_seconds`. Direction: `asc` or `desc`. Default: `created_at desc`.

Anything else returns 422. The field is checked with a strict `in_array`
against the same allowlist, both in the request and again in the service, so
a future caller bypassing the request class still cannot pass a column name
through to `ORDER BY`.

A secondary `orderBy('id', 'desc')` follows the primary sort. Without it,
rows that tie on the sorted column can swap between pages, so an asset the
caller already saw reappears on the next page while another never appears.
`test_index_walks_the_pages_without_repeating_or_dropping_a_row` covers this
by sorting twelve rows that all tie on `duration_seconds` and `created_at`.

================================================== 7. PAGINATION
==================================================

`per_page` accepts 10, 25, or 50 and defaults to 10. `page` defaults to 1.
Any other `per_page` returns 422. The row limit is applied by the database
via `LengthAwarePaginator`, so the page is never loaded in full and then
narrowed.

================================================== 8. CRUD BEHAVIOR
==================================================

Create: the client sends metadata only. `status` is server-forced to
`pending`, `content_project_id` comes from the route, and `file_path` is
never accepted.

Update: only sent fields change. `status` is never touched and cannot be
changed. `content_project_id` is not fillable, so an asset cannot be moved
to another project.

Show: scoped through the project's relation. An asset id belonging to
another project returns 404.

================================================== 9. ARCHIVE / DELETE BEHAVIOR
==================================================

Delete only. No archive endpoint was added.

`AssetStatus::Archived` and `AssetStatus::allowedTransitions()` remain an
unreachable state machine, exactly as Part 1 documented them. `status` is
server-controlled on create and immutable on update, so the UI has no status
control and cannot offer "archive" as a deliberate action. Wiring the
transitions would mean a new endpoint, a service method, a policy decision,
and a form change, none of which the metadata library needs to be usable.

Destroy is a hard delete of the metadata row. Since no file is stored there
is nothing on disk to clean up and no storage driver to call. The
confirmation dialog states this explicitly so the wording does not imply
otherwise.

================================================== 10. FRONTEND IMPLEMENTATION
==================================================

Created:

- `components/assets/AssetFilterBar.tsx` - debounced search, type, status,
  sort, per-page, clear. Only backend-supported options are offered.
- `components/assets/AssetCard.tsx` - type glyph, type and status badges,
  size, dimensions, duration, source, created date, and the action row.
- `components/assets/AssetFormModal.tsx` - grouped metadata form.
- `components/assets/AssetDetailModal.tsx` - read-only grouped view.
- `components/projects/AssetLibraryPanel.tsx` - list orchestration.

Modified:

- `types/index.ts` - `AssetSortField`, `AssetSortDirection`,
  `AssetFilterParams`.
- `services/assetService.ts` - `list` now takes filters and returns
  `PaginatedData<Asset>`.
- `pages/projects/ProjectDetailPage.tsx` - the Assets tab.

Deliberate omissions:

- No thumbnail or media placeholder. There is no file, so any image would be
  a placeholder a user could mistake for their asset. A type glyph only.
- No playback, no download button, no generated preview URL.
- Absent metadata rows are omitted rather than rendered as "N/A", so a blank
  reads as "not recorded" instead of implying a known value.
- Numeric inputs are held as strings while editing and converted once on
  submit. Parsing per keystroke would rewrite "1." to "1" and fight the
  cursor. An empty field becomes `null`, not `0`: a zero file size is a
  different claim about a file than no claim at all.

================================================== 11. AUTHORIZATION
==================================================

Unchanged from Part 1. Listing is gated by
`Gate::authorize('viewAny', [Asset::class, $project])`, which resolves
ownership through `ContentProject.created_by`.

`GetAssetsRequest::authorize()` returns `true`. That only means the query
string is well formed; the gate stays in the controller, where the project is
available.

The list query is built from `$project->assets()->getQuery()`, so the project
constraint is part of how the query is constructed rather than a condition
applied afterwards. Search, filters, sort, and page cannot widen it.

================================================== 12. TESTS
==================================================

Created `tests/Feature/AssetLibraryApiTest.php`, 24 tests:

- Pagination envelope, custom `per_page`, stable multi-page walk, page past
  the end.
- Search on title, then on file name, source, and notes; unmatched search.
- Type filter, status filter.
- Title sort both directions, file size sort, default newest-first.
- Combined search plus type plus status plus sort.
- Pagination totals tracking the active filter.
- 422 for unknown type, unknown status, unlisted `per_page`, a sort field
  outside the allowlist (including `file_path`), and an unknown direction.
- 403 for another user's project.
- Project-scope escapes: search, filters, and sorting, each proven not to
  reach another project's assets.
- `file_path` absent from the listed payload.

The three scope tests were mutation-checked. Replacing
`$project->assets()->getQuery()` with `Asset::query()` makes exactly those
three fail, which confirms they test the scoping and not something else.
They deliberately use two projects owned by the same user, so a failure can
only mean a scope leak and never a permissions difference.

Modified `tests/Feature/AssetTest.php`: one Part 1 service test now calls
`paginateAssets()` instead of the removed `listAssets()`.

================================================== 13. VERIFICATION
==================================================

    php artisan test --filter=Asset          229 passed, 533 assertions
    php artisan test --filter=AssetLibrary   24 passed, 74 assertions
    php artisan test                         987 passed, 3157 assertions
    npx tsc --noEmit                         clean
    npm run build                            clean
    vendor/bin/pint --dirty --format agent   passed

No pre-existing test was weakened or removed. The one Part 1 test that
changed was a rename to follow the service method, not a relaxation.

No migration was added, so no up/rollback/up cycle applies. See section 14.

================================================== 14. KNOWN LIMITATIONS
==================================================

- The Asset Library is metadata-only.
- No file upload exists yet.
- No actual media preview exists yet.
- No external asset provider exists yet.
- No Asset to AssetRequirement mapping exists yet.
- No archive action; `AssetStatus::Archived` is still unreachable.
- `duration_seconds` is an integer column, so a fractional duration such as
  `8.4` is rejected. This is a Part 1 schema decision, not a Part 2 one.
- No new index was added. The Part 1 migration already indexes
  `content_project_id` with `status` and with `type`, which covers both
  filters. Sorting on `created_at` over an already project-scoped and
  paginated row set does not justify a third index, and the spec asks not to
  add indexes speculatively.
- The global `/assets` sidebar entry still points at the old "Phase 3"
  placeholder shell. Assets are project scoped and have no global endpoint,
  so that shell remains a dead end. It was left alone as out of scope.
- The bundle exceeds Vite's 500 kB warning. This predates Part 2: 545.57 kB
  before, 572.05 kB after.

================================================== 15. PHASE 4 PART 3 HANDOFF
==================================================

Part 3 can relate the two concepts without either having to change shape:

- AssetRequirement asks what media is needed.
- Asset records what media resource is on hand.
- The join is a separate table, so adding it does not require a lifecycle
  change on either side.

Ready for Part 3:

- A stable `assets.id` and `content_project_id` to join against.
- `AssetStatus` values already describe where an asset sits in the pipeline.
- `duration_seconds`, `width`, `height`, and `type` are present to match a
  requirement's target duration and aspect ratio.
- `source_name`, `source_url`, `license_type`, and `attribution` are present
  for provenance.

Still to decide in Part 3:

- Whether matching a requirement auto-advances asset status. Doing so needs a
  status transition endpoint, which Part 2 deliberately did not add.
- Where a fulfilled requirement stores the chosen asset.

Separation held: this part made asset management usable and did not
prematurely implement asset files.
