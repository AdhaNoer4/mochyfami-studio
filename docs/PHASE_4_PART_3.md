# MochyFami Content Studio

# Phase 4 — Part 3: Asset ↔ Asset Requirement

You are continuing work on the existing MochyFami Content Studio repository.

Previous checkpoints:

Phase 4 Part 1 — Asset Domain Foundation
Commit: 8f94f05

Phase 4 Part 2 — Asset Library / CRUD
Commit: 268d56f

Both are completed locally and have NOT been pushed.

==================================================
CURRENT ARCHITECTURE
==================================================

The current production flow is:

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
ASSET LIBRARY
↓
[ CURRENT TASK ]
ASSET ↔ ASSET REQUIREMENT
↓
UPLOAD / STORAGE
↓
ASSET SOURCING
↓
PRODUCTION READINESS
↓
TTS / SUBTITLE
↓
VIDEO ENGINE

Phase 3 already established:

VisualPlan
VisualPlanItem
AssetRequirement

Phase 4 Part 1 established:

Asset

Phase 4 Part 2 established:

Asset Library / metadata CRUD

There is currently NO relationship between Asset and AssetRequirement.

This task establishes that relationship.

==================================================
OBJECTIVE
==================================================

Implement:

Phase 4 Part 3 — Asset ↔ Asset Requirement

The purpose of this part is to allow users to associate one or more
existing Assets with an AssetRequirement.

The relationship should answer:

"Which assets are candidates for satisfying this visual requirement?"

Example:

AssetRequirement:
"Video kucing berjalan"

may have:

Asset #31 — cat-walking-01.mp4
Asset #42 — cat-walking-02.mp4

The relationship is many-to-many:

AssetRequirement
↕
Asset

A requirement may have multiple assets.

An asset may be associated with multiple requirements.

IMPORTANT:

This task establishes CANDIDATE ASSOCIATIONS only.

It does NOT determine which asset is finally selected.

It does NOT mark a requirement fulfilled.

It does NOT introduce an asset selection winner.

It does NOT implement production readiness.

==================================================
NON-GOALS
==================================================

DO NOT implement:

- file upload
- filesystem storage
- S3/cloud storage
- asset download
- external asset search
- Pexels integration
- Pixabay integration
- YouTube integration
- AI asset generation
- asset processing
- FFmpeg
- media transcoding
- actual playback
- asset fulfillment
- final asset selection
- production readiness gate
- asset scoring/ranking
- automatic asset matching
- AI-based asset matching
- automatic requirement generation

Those belong to later parts.

==================================================
STEP 0 — INSPECT THE REPOSITORY FIRST
==================================================

Before modifying anything:

1. Inspect Phase 4 Part 1 Asset implementation.
2. Inspect Phase 4 Part 2 Asset Library implementation.
3. Inspect Phase 3 Part 15 AssetRequirement implementation.
4. Inspect Phase 3 Part 16 VisualPlanQualityService.
5. Inspect VisualPlanItem relationships.
6. Inspect existing pivot-table implementations.
7. Inspect existing many-to-many relationships.
8. Inspect existing attach/detach APIs.
9. Inspect existing duplicate relationship handling.
10. Inspect authorization/policy patterns.
11. Inspect current frontend Asset Library.
12. Inspect current VisualPlanPanel / AssetRequirement UI.
13. Inspect API response conventions.
14. Inspect test conventions.
15. Inspect migration naming conventions.

Do NOT assume the repository matches this prompt exactly.

Reuse existing architecture.

Do not introduce a new relationship pattern if the repository already has
an established one.

==================================================

1. # RELATIONSHIP MODEL

Implement a many-to-many relationship:

Asset
↕
AssetRequirement

Use a pivot table.

Recommended conceptual pivot:

asset_requirement_asset

However:

FIRST inspect repository naming conventions.

If the repository consistently uses another naming convention, follow it.

The pivot should contain at minimum:

- asset_requirement_id
- asset_id
- timestamps

The exact foreign-key column names must follow existing model naming.

================================================== 2. RELATIONSHIP SEMANTICS
==================================================

The relationship means:

"This Asset is a candidate associated with this AssetRequirement."

It does NOT mean:

"This Asset has fulfilled the requirement."

It does NOT mean:

"This Asset is selected as the final asset."

It does NOT mean:

"This Asset has passed production validation."

Therefore:

DO NOT add:

selected
fulfilled
primary
winner
approved_for_requirement

to the pivot in this part.

Keep the relationship intentionally simple.

================================================== 3. CARDINALITY
==================================================

The relationship must support:

Requirement A
├── Asset 1
├── Asset 2
└── Asset 3

and:

Asset 1
├── Requirement A
├── Requirement B
└── Requirement C

Therefore:

AssetRequirement belongsToMany Asset

Asset belongsToMany AssetRequirement

Do not enforce one-to-one.

Do not enforce one-to-many.

================================================== 4. PIVOT DATABASE DESIGN
==================================================

Create a migration for the pivot table.

Use:

- asset_requirement_id
- asset_id
- timestamps

Foreign keys should reference the appropriate tables.

Use cascading behavior consistent with the repository.

A strong default is:

AssetRequirement deletion
↓
remove pivot rows

Asset deletion
↓
remove pivot rows

Do NOT delete the other domain entity.

Add a UNIQUE constraint across:

asset_requirement_id + asset_id

This is critical.

The same Asset must not be attached to the same AssetRequirement twice.

Add indexes appropriate for:

- asset_requirement_id
- asset_id
- composite uniqueness

Do not add unnecessary indexes.

================================================== 5. MODEL RELATIONSHIPS
==================================================

Update AssetRequirement:

assets()

Update Asset:

assetRequirements()

Expected conceptual implementation:

AssetRequirement::assets()
belongsToMany(Asset::class)

Asset::assetRequirements()
belongsToMany(AssetRequirement::class)

Use pivot timestamps if the schema includes timestamps.

Do not add unrelated relationships.

================================================== 6. DUPLICATE ATTACH PROTECTION
==================================================

Duplicate association must be rejected cleanly.

Example:

Requirement #10
Asset #25

First attach:
SUCCESS

Second attach:
REJECT

Do NOT silently create duplicate pivot rows.

Prefer a domain-specific exception if that matches existing repository
patterns.

For example:

DuplicateAssetRequirementAssetException

However:

FIRST inspect whether the repository already has a generic duplicate
relationship exception pattern.

Reuse existing conventions where possible.

Expected API behavior should follow existing duplicate relationship
semantics.

If the repository convention is HTTP 409 Conflict, use 409.

Do not return a generic 500.

================================================== 7. CROSS-SCOPE SECURITY
==================================================

This is one of the MOST IMPORTANT parts of this task.

An AssetRequirement and Asset must belong to the same ContentProject.

Conceptually:

AssetRequirement
→ VisualPlanItem
→ VisualPlan
→ ScriptVersion
→ ContentProject

Asset
→ ContentProject

Before attaching:

requirement.content_project_id
must equal
asset.content_project_id

If they belong to different projects:

REJECT.

The API must not allow:

Project A Requirement +
Project B Asset

This must be tested explicitly.

IMPORTANT:

Do not rely only on frontend filtering.

The backend MUST enforce this.

================================================== 8. USER AUTHORIZATION
==================================================

Cross-project ownership must remain protected.

Example:

User A owns Project A
User B owns Project B

User A must not attach:

Project B Asset
to
Project A Requirement

Even if the asset ID is manually supplied.

Test both:

- same user but different projects
- different users / different projects

The first case is especially important because it catches scope bugs
that permission-only tests may miss.

================================================== 9. API DESIGN
==================================================

Follow existing repository route conventions.

The API should support:

LIST ASSOCIATED ASSETS

GET:

/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/asset-requirements/{requirement}/assets

This returns Assets associated with the requirement.

ATTACH

POST:

/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/asset-requirements/{requirement}/assets

Body:

{
"asset_id": 123
}

DETACH

DELETE:

/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/asset-requirements/{requirement}/assets/{asset}

However:

FIRST inspect existing nested-resource route conventions.

If a shorter or more consistent route already exists, follow the existing
architecture.

Do not duplicate route hierarchies unnecessarily.

================================================== 10. ROUTE SCOPE
==================================================

The route must validate the entire chain:

project
↓
script version
↓
visual plan
↓
visual plan item
↓
asset requirement
↓
asset

If any resource does not belong to the expected parent:

return the repository's established 404 behavior.

Do not allow a valid ID from another project to bypass parent scope.

Example attack:

POST

/projects/1/.../requirements/10/assets

with:

asset_id=999

where Asset 999 belongs to Project 2.

Must fail.

================================================== 11. SERVICE LAYER
==================================================

Create or extend an AssetRequirement/Asset relationship service according
to the existing architecture.

Possible name:

AssetRequirementAssetService

or another name consistent with the repository.

Responsibilities:

- list associated assets
- attach asset
- detach asset
- validate project scope
- prevent duplicates

The service should NOT:

- upload files
- download files
- search internet
- determine fulfillment
- select winners
- mutate AssetRequirement status
- mutate Asset status

Keep the service deterministic.

================================================== 12. ATTACH BEHAVIOR
==================================================

Attach should:

1. Resolve the project.
2. Resolve the script version.
3. Resolve the visual plan.
4. Resolve the visual plan item.
5. Resolve the AssetRequirement.
6. Resolve the Asset.
7. Verify requirement belongs to project.
8. Verify asset belongs to project.
9. Verify asset isn't already attached.
10. Create pivot row.
11. Return updated relationship information.

Use a transaction only if it is consistent with existing service patterns
and useful for the operation.

Do not create unnecessary transactions around a single pivot insert.

================================================== 13. DETACH BEHAVIOR
==================================================

Detach should:

1. Validate project scope.
2. Validate requirement scope.
3. Validate asset scope.
4. Remove the pivot association.

If the relationship does not exist:

follow the repository's existing behavior.

Prefer a deterministic 404/422 rather than silently hiding an unexpected
domain error.

Do not delete the Asset.

Do not delete the AssetRequirement.

================================================== 14. LIST BEHAVIOR
==================================================

List associated assets for one requirement.

Return:

- asset metadata
- association context if useful
- timestamps if available

Do NOT return the entire Asset Library.

Only return assets attached to that specific requirement.

Ensure project scope remains enforced.

================================================== 15. RESPONSE RESOURCE
==================================================

Reuse AssetResource from Phase 4 Part 1/2.

Do not create a duplicate Asset response format.

If relationship metadata is needed, create the smallest appropriate
resource/transformer extension.

Do not expose internal pivot implementation details unless useful.

================================================== 16. ASSET LIBRARY INTEGRATION
==================================================

Update the existing Asset Library only where necessary.

Possible UI behavior:

Asset Library asset card
↓
"Attach to requirement"

However:

DO NOT redesign the Asset Library.

Do not introduce a large new workflow here if the existing VisualPlan UI
is a better location.

================================================== 17. VISUAL PLAN / REQUIREMENT UI
==================================================

The most natural UI may be:

Visual Plan
↓
Asset Requirement
↓
Associated Assets

Example:

Asset Requirement
"Video kucing berjalan"

Associated Assets:

[Cat Walking #01] [Detach]
[Cat Walking #02] [Detach]

[Attach Asset]

If this fits the existing UI architecture, implement it.

The user should be able to:

- see associated assets
- attach an existing asset
- detach an associated asset

Do NOT allow file upload from this UI yet.

Do NOT show fake previews.

Use metadata cards/list consistent with Asset Library.

================================================== 18. ASSET PICKER
==================================================

If an asset picker is needed, it must use the existing Asset Library API.

The picker should support:

- current project only
- search
- type filter if useful
- status filter if useful

Do not make a second asset search implementation.

Do not query all assets into the browser if pagination already exists.

Do not expose assets from other projects.

================================================== 19. DUPLICATE UI PROTECTION
==================================================

If an Asset is already attached to a requirement:

- it should appear as already attached
- the UI should not offer a misleading duplicate attach action
- backend must still protect against duplicates

Frontend protection is UX.

Backend protection is security/integrity.

Both are required.

================================================== 20. ASSET TYPE / REQUIREMENT TYPE
==================================================

Do NOT automatically reject an Asset based on type mismatch in this part.

Example:

Requirement type:
VIDEO

Asset type:
IMAGE

The backend may allow the association.

Why?

Because compatibility/fulfillment validation belongs to a later quality or
fulfillment layer.

If the repository already has an explicit compatibility rule, inspect it
and preserve it.

Otherwise:

Association ≠ fulfillment.

Do not add speculative business rules.

================================================== 21. STATUS SEMANTICS
==================================================

Do NOT change AssetStatus when attaching.

Example:

Asset:
AVAILABLE

After attachment:

Asset:
AVAILABLE

Do not automatically make it:

APPROVED
PROCESSING
FULFILLED

Likewise:

Do not change AssetRequirement status.

Part 3 is relationship management only.

================================================== 22. TESTING — CORE
==================================================

Add comprehensive tests.

At minimum:

RELATIONSHIP:

1. AssetRequirement can have multiple Assets.
2. Asset can belong to multiple AssetRequirements.
3. Pivot timestamps work if enabled.
4. Duplicate pair is prevented.

ATTACH:

5. valid same-project asset can attach.
6. returned response contains asset.
7. requirement relationship updates.
8. asset relationship updates.

DETACH:

9. attached asset can detach.
10. Asset remains after detach.
11. AssetRequirement remains after detach.

LIST:

12. only associated assets are returned.
13. unrelated project assets are never returned.

================================================== 23. TESTING — SECURITY
==================================================

This is REQUIRED.

Test:

A. Same user, different projects:

User A owns Project A and Project B.

Requirement from Project A.

Asset from Project B.

Attempt attach.

MUST FAIL.

B. Different users:

User A owns Project A.

User B owns Project B.

Requirement from Project A.

Asset from Project B.

Attempt attach.

MUST FAIL.

C. Cross-project list:

Project A requirement must not return Project B assets.

D. Cross-project detach:

Cannot detach an asset association through a foreign project scope.

E. ID substitution:

Use valid IDs from another project manually.

MUST FAIL.

================================================== 24. MUTATION / REGRESSION CHECK
==================================================

Perform a deliberate scope mutation check similar to Phase 4 Part 2.

The goal is to prove that tests fail if project scope validation is removed.

For example, temporarily change the relevant scoped query or validation
to a global Asset::query() / unscoped lookup.

Verify that the cross-project security tests fail.

Then RESTORE the correct implementation.

This is an internal verification step.

Do NOT leave the mutation in the final code.

Document:

- what was mutated
- which tests failed
- that the original implementation was restored

This is important because the repository has already demonstrated the
value of this technique in Part 2.

================================================== 25. TESTING — DUPLICATES
==================================================

Explicitly test:

First attach:
201 / success

Second attach:
409 Conflict or repository-equivalent duplicate error

There must be:

- application-level protection
- database-level unique constraint

The database constraint is the final safety net.

================================================== 26. TESTING — AUTHORIZATION
==================================================

Test:

- authorized user can list
- authorized user can attach
- authorized user can detach
- unauthorized project access fails
- cross-project asset access fails
- cross-project requirement access fails

Follow existing 404/403 conventions.

Do not weaken security for convenience.

================================================== 27. DATABASE INTEGRITY TEST
==================================================

Verify:

- foreign keys work
- cascade behavior works
- unique pair constraint works
- duplicate association cannot exist

Test deletion behavior:

Delete Asset:
pivot row disappears
AssetRequirement remains

Delete AssetRequirement:
pivot row disappears
Asset remains

Do NOT accidentally cascade-delete the opposite domain entity.

================================================== 28. FRONTEND TYPES
==================================================

Extend frontend Asset/AssetRequirement types only as needed.

Avoid duplicate type definitions.

Possible additions:

AssetRequirementWithAssets

or simply:

assets?: Asset[]

depending on the current API response architecture.

Follow existing patterns.

================================================== 29. FRONTEND SERVICE
==================================================

Extend the appropriate existing service.

Potential methods:

listRequirementAssets(...)
attachAssetToRequirement(...)
detachAssetFromRequirement(...)

Do NOT create another HTTP client.

Do NOT duplicate Asset Library fetching logic.

================================================== 30. FRONTEND ERROR HANDLING
==================================================

Handle:

- duplicate attach
- cross-scope failure
- missing asset
- missing requirement
- generic API error

Do not silently swallow errors.

Display useful user-facing feedback consistent with the existing UI.

================================================== 31. REFRESH / STATE CONSISTENCY
==================================================

After:

- attach
- detach

the UI must refresh the associated asset list.

Follow existing refresh/data-fetch patterns.

Avoid unnecessary global page reloads.

Do not create stale UI where an asset appears attached after it was
successfully detached.

================================================== 32. NO AUTOMATIC FULFILLMENT
==================================================

This rule is critical.

After attaching:

AssetRequirement:
status = unchanged

Asset:
status = unchanged

No quality score changes.

No readiness gate changes.

No "fulfilled" flag.

No final asset selection.

The system only records:

"This asset is associated with this requirement."

================================================== 33. DOCUMENTATION
==================================================

Create:

docs/PHASE_4_PART_3.md

Document:

1. Objective
2. Existing architecture inspected
3. Relationship model
4. Pivot schema
5. Cardinality
6. Attach behavior
7. Detach behavior
8. List behavior
9. Duplicate handling
10. Cross-project security
11. Authorization
12. API endpoints
13. Frontend changes
14. Tests
15. Mutation-check
16. Verification
17. Known limitations
18. Phase 4 Part 4 handoff

Explicitly document:

Association does NOT mean fulfillment.

Association does NOT mean final selection.

Association does NOT mean approval.

Association does NOT change AssetStatus.

Association does NOT change AssetRequirement status.

================================================== 34. MIGRATION VERIFICATION
==================================================

If a migration is created:

Run:

migration up
migration rollback
migration up

Verify:

- foreign keys
- cascade behavior
- unique constraint
- indexes

Do not skip this.

================================================== 35. FULL VERIFICATION
==================================================

Run focused tests first.

Then:

php artisan test

npx tsc --noEmit

npm run build

Pint / repository formatter

Also run relevant frontend checks if the repository has them.

Do not ignore failures.

Report:

- total tests
- total assertions
- newly added tests
- pre-existing failures, if any

================================================== 36. SECURITY / DEBUG SWEEP
==================================================

Search changed files for:

- dd()
- dump()
- var_dump()
- console.log()
- debug code
- hardcoded credentials
- API keys
- unsafe SQL
- arbitrary ORDER BY
- unscoped Asset::query()
- unscoped AssetRequirement queries
- client-controlled project ownership
- filesystem calls
- network calls

Pay special attention to accidental global queries.

================================================== 37. GIT CHECKPOINT
==================================================

Before commit:

1. git status
2. git diff
3. verify no unrelated changes
4. verify migration files
5. verify tests
6. verify frontend build
7. verify documentation

Commit:

feat: link assets to asset requirements

DO NOT push to GitHub.

Working tree must be clean after commit.

================================================== 38. FINAL REPORT
==================================================

Return a completion report containing:

1. Summary
2. Repository inspection findings
3. Relationship design
4. Pivot table
5. Models changed
6. Services created/changed
7. API endpoints
8. Frontend changes
9. Duplicate protection
10. Cross-project security
11. Authorization behavior
12. Tests added
13. Mutation-check result
14. Full test result
15. TypeScript result
16. Build result
17. Pint result
18. Migration verification
19. Security/debug sweep
20. Git commit hash
21. Git status
22. Known limitations
23. Phase 4 Part 4 handoff

Do not claim completion unless all verification actually succeeds.

==================================================
CORE ARCHITECTURAL PRINCIPLE
==================================================

Keep these concepts separate:

# AssetRequirement

"What media do we need?"

# Asset

"What media resource do we have?"

# Asset ↔ AssetRequirement

"This asset is a candidate associated with this requirement."

# Fulfillment

"Does this requirement have a usable asset?"

# Final Selection

"Which asset will actually be used?"

# Production Readiness

"Is the complete project ready to enter production?"

These are DIFFERENT concepts.

Part 3 implements ONLY:

Asset ↔ AssetRequirement association.

Do not collapse these concepts into one status or boolean.

==================================================
END OF TASK
==================================================

==================================================
COMPLETION REPORT — PHASE 4 PART 3
==================================================

1. SUMMARY

Assets can now be associated with asset requirements as candidates. Three
endpoints list, attach, and detach them, the association is enforced inside a
single project on both sides, a repeat attach is a 409 rather than a second
pivot row, and the Visual Plan shows each requirement's candidates with an
attach picker that reuses the existing Asset Library API. Nothing about
fulfillment, selection, or production readiness is implemented or implied.

2. REPOSITORY INSPECTION FINDINGS

- The controller is VisualPlanAssetRequirementController, not
  AssetRequirementController.
- Routes are nested under script/versions/{version}/visual-plan, so the
  spec's paths dropped in unchanged.
- The existing duplicate convention is
  DuplicateScriptResearchClaimException extends InvalidArgumentException,
  caught in the controller and returned as 409. It was reused rather than
  reinvented.
- Scoped asset lookup already existed as AssetService::findAsset(), which
  resolves through $project->assets(). That is the security primitive the
  attach path uses.
- VisualPlanPolicy, ProjectPolicy, and AssetRequirementPolicy are all
  permissive (every ability returns true), so they cannot answer "does this
  user own the project". AssetPolicy is the only policy here that resolves
  ownership, and it is what the new endpoints authorize against.
- Pivot convention: surrogate id, both foreign keys cascading, timestamps,
  and a composite unique pair, as in research_claim_sources and
  script_version_research_claim.
- Laravel's default pivot name for these two models would be
  asset_asset_requirement, because it sorts the class names alphabetically.
  The repository names its pivots parent-first instead and passes the table
  name explicitly on both sides of every existing relation, so
  asset_requirement_asset is named explicitly here too.

3. RELATIONSHIP DESIGN

Many-to-many, both directions:

- AssetRequirement::assets() belongsToMany Asset
- Asset::assetRequirements() belongsToMany AssetRequirement

One requirement may have many candidate assets and one asset may be a
candidate for many requirements. The relation means only "this asset is a
candidate associated with this requirement". No selected, fulfilled, primary,
winner, or approved flag exists on the pivot.

4. PIVOT TABLE

asset_requirement_asset: id, asset_requirement_id (cascade), asset_id
(cascade), timestamps, unique(asset_requirement_id, asset_id). Verified
through a live up -> rollback -> up cycle; both foreign keys and the unique
constraint are present in the database, and the foreign keys supply the
indexes, so no additional index was added.

5. MODELS CHANGED

- Asset: added assetRequirements().
- AssetRequirement: added assets().

6. SERVICES CREATED/CHANGED

AssetRequirementService gained listRequirementAssets(), attachAsset(), and
detachAsset(), and now takes AssetService. The existing service was extended
rather than replaced by a new one, because it already owns the
project -> script -> version -> plan -> item resolution chain this part needs;
a separate class would have duplicated that scoping logic.

The list query is started from $project->assets() and then narrowed to the
requirement rather than from the requirement's relation alone. A pivot row
pairing a requirement with another project's asset would otherwise be followed
straight through and return that project's metadata. This was caught by a
test, not by inspection: the first implementation used the requirement's
relation and leaked.

7. API ENDPOINTS

- GET    .../visual-plan/items/{item}/asset-requirements/{requirement}/assets
- POST   .../visual-plan/items/{item}/asset-requirements/{requirement}/assets
- DELETE .../visual-plan/items/{item}/asset-requirements/{requirement}/assets/{asset}

All under /api/v1/projects/{project}/script/versions/{version}/. The POST body
is {"asset_id": 123} and returns 201 with an AssetResource. DELETE returns 200.
AssetResource is reused unchanged; no duplicate response format was created
and no pivot internals are exposed.

8. FRONTEND CHANGES

- assetRequirementService: listAssets, attachAsset, detachAsset, reusing the
  existing basePath helper and apiClient.
- New RequirementAssetsSection component holding the candidate list, attach
  and detach actions, and the picker. It was a separate component because
  VisualPlanPanel had already reached 1403 lines and the picker carries its
  own paging state.
- VisualPlanPanel renders the section inside renderRequirementRow, guarded on
  a non-null version.
- The picker calls assetService.list() for search and paging rather than
  adding a second search implementation, and nothing is fetched unfiltered
  into the browser.
- No new frontend types: Asset and AssetRequirement already existed, and
  candidates are held in component state rather than added to every
  requirement payload.

9. DUPLICATE PROTECTION

Two layers. The service checks for an existing pair and throws
DuplicateAssetRequirementAssetException, which the controller maps to 409. The
unique constraint is the safety net behind it, verified by a test that
inserts a duplicate row directly and expects the database to refuse. A
rejected duplicate was also confirmed to leave exactly one pivot row.

10. CROSS-PROJECT SECURITY

An asset is resolved through the requested project's own relation, so an
asset from another project is unreachable rather than rejected afterwards.
A valid asset id belonging to a different project fails exactly like an id
that does not exist: both are 404. This is also why the request validates
asset_id's shape only and omits exists:assets,id — an unscoped existence rule
would return 422 for a nonexistent id and 404 for another project's id, which
is enough to confirm which ids are real without owning that project.

The list endpoint is scoped the same way, from the project outward.

Covered: same user owning two projects, two different users, id substitution,
cross-project list, cross-project detach, and a requirement from another
script version.

11. AUTHORIZATION BEHAVIOR

The new endpoints authorize through AssetPolicy, which enforces
project.created_by === user.id, using the [Asset::class, $project] form. A
stranger reading, attaching, or detaching gets 403. The permissive plan
policy is still checked first, matching the surrounding methods.

Note: the ability used for the writes is AssetPolicy::create, because that is
the policy's only project-scoped write ability. It is a reuse of the
ownership check, not a claim that attaching creates an asset. This is
documented in the controller.

Pre-existing and left alone: the other visual plan and asset requirement
endpoints authorize through VisualPlanPolicy and
AssetRequirementPolicy, whose abilities all return true. They are outside
this part's scope, but a stranger can currently reach them with a valid
project id. Worth a follow-up.

12. TESTS ADDED

27 tests in AssetRequirementApiTest, plus the new endpoints added to the
existing authentication test:

- relationship: multiple assets per requirement, multiple requirements per
  asset, pivot timestamps, database-level unique constraint
- attach: 201 with the asset, both relationship directions updated,
  validation of a missing or non-integer id, 404 for an unknown asset
- semantics: neither status changes on attach, and a type mismatch is allowed
- duplicate: 201 then 409, no second pivot row, the same asset attaching to
  two different requirements
- list: empty, only the requirement's own assets, never another project's,
  404 for a requirement of another item
- detach: association removed with both entities intact, 404 when not
  associated, 404 for another project's asset
- security: same user with two projects, two users, id substitution,
  cross-project detach, stranger 403 on all three endpoints, requirement from
  another version
- integrity: deleting an asset removes the pivot and keeps the requirement,
  and the reverse

One existing test in AssetTest asserted that Asset had no requirement
relationship at all, which this part makes false by design. It was rewritten
to assert the relation exists, is a BelongsToMany, and that no shorter
`requirements` alias was introduced.

13. MUTATION-CHECK RESULT

The project-scoped asset lookup in attachAsset and detachAsset was
temporarily replaced with an unscoped Asset::query()->whereKey().

Result: 2 of 59 tests failed — test_attach_rejects_an_asset_from_another_project_owned_by_the_same_user
and test_attach_rejects_an_asset_from_another_users_project. Both are the
cross-project security tests, and both failed exactly as intended: the
mutation let a foreign asset through a project the caller did own, which no
permission check would have caught.

The original implementation was restored from a backup and the file was
re-verified: 59/59 passing. Nothing from the mutation remains.

14. FULL TEST RESULT

1014 tests, 3233 assertions, 1014 passing, 0 failing, 0 skipped.
(Part 2 closed at 987 tests / 3157 assertions; this part adds 27 tests.)

15. TYPESCRIPT RESULT

npx tsc --noEmit — clean, no errors.

16. BUILD RESULT

npm run build — succeeded in 3.96s. The chunk-size warning above 500 kB is
pre-existing and unrelated.

17. PINT RESULT

vendor/bin/pint --dirty --format agent — passed.

18. MIGRATION VERIFICATION

up -> rollback -> up, all three succeeded. Confirmed in the schema: both
foreign keys ON DELETE CASCADE, the composite unique index present, foreign
key indexes auto-created, no redundant indexes.

19. SECURITY / DEBUG SWEEP

Scanned every changed and new file for dd, dump, var_dump, console.log,
print_r, debug leftovers, credentials, API keys, DB::raw, whereRaw,
orderByRaw, unscoped queries, filesystem calls, and network calls. The only
hit was the word "unscoped" inside a comment explaining why exists:assets,id
was deliberately not used. No unscoped Asset query remains: the one that
existed during the mutation check was reverted.

20. GIT COMMIT HASH

See the commit created for this part: feat: link assets to asset requirements.

21. GIT STATUS

Clean after commit.

22. KNOWN LIMITATIONS

- No file upload, storage, download, or preview. The picker says so in the
  UI rather than implying media exists.
- No automatic matching, scoring, or ranking between requirements and assets.
- No compatibility or fulfillment validation; a type mismatch is allowed by
  design, since that belongs to a later layer.
- Attaching never changes AssetStatus or AssetRequirement status, adds no
  quality signal, and affects no readiness gate.
- The pivot is not exposed with association timestamps in the API response,
  even though the columns exist, because nothing consumes them yet.
- The pre-existing permissive-policy gap described in section 11 is not
  fixed here.
- Candidates are fetched per requirement on render. For a plan with many
  requirements this is one request per requirement; batching them into the
  requirement payload was left for a later part, when there is a reason to
  decide how many candidates a real project carries.

23. PHASE 4 PART 4 HANDOFF

The next part starts from upload and storage. It inherits a relationship
that is deliberately dumb: a symmetric many-to-many with a unique pair, both
foreign keys cascading, and no status or selection state on it. Storage will
need to decide what file_path and file_name mean now that assets can actually
be candidates for real requirements, and it can safely assume the association
table will not need a migration to record a winner later — that flag was
deliberately not added, so a future selection column is additive.

==================================================
END COMPLETION REPORT
==================================================
