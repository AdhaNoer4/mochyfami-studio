# MochyFami Content Studio

# PHASE 3 — PART 15

# Visual Plan → Asset Requirement Foundation

You are continuing development of the existing MochyFami Content Studio repository.

IMPORTANT:

- Do NOT start Phase 4.
- This task is ONLY Phase 3 Part 15.
- Inspect the repository and existing implementation before changing anything.
- Preserve all existing working behavior.
- Do NOT rewrite existing architecture unnecessarily.
- Do NOT introduce actual asset downloading/search providers yet.
- Do NOT call external APIs.
- Do NOT use AI.
- Do NOT render video.
- Do NOT generate TTS.
- Do NOT push to GitHub.
- Create exactly ONE focused commit.
- Stop after verification and commit.
- Never claim completion unless implementation and verification actually pass.

==================================================

1. # CURRENT PROJECT STATE

The project currently contains:

Research:

- ResearchReport
- ResearchClaim
- Source
- Research evidence
- ResearchQualityService
- ResearchPipelineService
- SearchProvider abstraction
- Source discovery
- AI research generation
- ResearchToScriptContext

Script:

- Script
- ScriptVersion
- immutable versions
- script revision
- ScriptQualityService
- AI script generation
- research claim traceability

Visual Planning:

- VisualPlan
- VisualPlanItem
- VisualPlanStatus
- VisualPlanSection
- VisualPlanItemType
- VisualPlanService
- Visual Plan API
- VisualPlanPanel
- Visual Plan tab
- plan bound to exact ScriptVersion
- create-from-script helper
- item CRUD
- item reorder
- plan status workflow

Latest known state:

Commit:
caf2433 feat: add script visual plan foundation

Tests:
701 tests
2470 assertions
all passing

TypeScript:
clean

Vite build:
successful

Pint:
clean for Part 14 files

Working tree:
clean

No push.

================================================== 2. PART 15 OBJECTIVE
==================================================

Build:

VISUAL PLAN
↓
ASSET REQUIREMENTS

The goal is to convert each VisualPlanItem into one or more explicit asset requirements.

An Asset Requirement is NOT an actual asset.

It is a production requirement describing what asset must be found later.

Example:

Visual Plan Item:

- section: hook
- narration: "Jangan coba-coba..."
- visual_type: animal_clip
- visual_prompt: "close-up black crow looking at camera"
- duration: 4

Asset Requirement:

- type: video
- search_query: "black crow close up"
- description: "Close-up black crow looking toward camera"
- target_duration_seconds: 4
- status: pending

Later Phase 4 will satisfy this requirement with an actual Asset.

================================================== 3. CORE ARCHITECTURAL PRINCIPLE
==================================================

Do NOT create the actual Asset Manager yet.

Part 15 creates only:

- AssetRequirement model
- database schema
- service
- API
- frontend
- requirement status
- VisualPlan → AssetRequirement relationship

Actual assets belong to Phase 4.

The relationship must be:

VisualPlanItem
↓
AssetRequirement
↓
future Asset

Do NOT directly attach Asset records yet.

================================================== 4. INSPECT EXISTING ASSET ARCHITECTURE
==================================================

Before implementing:

Inspect:

- existing `Asset` model if it exists
- existing `assets` migration if it exists
- Phase 0 technical specification
- existing project relationships
- existing naming conventions

Do NOT duplicate an existing Asset table.

If `assets` already exists from the foundation but is not yet actively used, leave it untouched unless a relationship is required.

AssetRequirement is a separate concept.

================================================== 5. ASSET REQUIREMENT DATA MODEL
==================================================

Create AssetRequirement.

Suggested fields:

- id
- visual_plan_item_id
- requirement_type
- search_query
- description
- target_duration_seconds
- aspect_ratio
- status
- notes
- timestamps

Inspect project conventions and adjust exact names if necessary.

================================================== 6. REQUIREMENT TYPE

Create an enum.

Suggested:

video
image
audio
graphic
screen_recording
other

Do not include actual asset source/provider information yet.

This enum describes WHAT KIND OF ASSET is required.

================================================== 7. REQUIREMENT STATUS

Create:

AssetRequirementStatus

Suggested:

pending
searching
fulfilled
skipped

Keep this simple.

Meaning:

pending:

- requirement exists
- no asset has been assigned

searching:

- future Phase 4 workflow is currently looking for an asset

fulfilled:

- future Asset Manager has satisfied the requirement

skipped:

- requirement intentionally does not need an asset

Part 15 itself should normally create requirements as:

pending

Do not implement searching/fulfilled automation yet.

================================================== 8. VISUAL PLAN ITEM RELATIONSHIP
==================================================

Each requirement MUST belong to exactly one VisualPlanItem.

Add:

VisualPlanItem
hasMany AssetRequirements

AssetRequirement
belongsTo VisualPlanItem

Do not attach requirement directly to ScriptVersion.

The chain should remain:

Project
↓
ScriptVersion
↓
VisualPlan
↓
VisualPlanItem
↓
AssetRequirement

================================================== 9. DUPLICATE REQUIREMENTS

A VisualPlanItem may need more than one asset.

Example:

Visual item:
"Show a crow flying while narration explains recognition."

Requirements:

1. Crow flying video
2. Crow close-up image

Therefore:

DO NOT make `visual_plan_item_id` unique.

Multiple requirements per item are valid.

================================================== 10. REQUIREMENT CREATION

Add service-level functionality:

createRequirement(visualPlanItem, data)

Validate:

- item exists
- item belongs to a valid VisualPlan
- visual plan belongs to project
- requirement type valid
- status controlled by server
- target duration valid
- search query valid
- description valid

Default:

status = pending

Do not allow client to arbitrarily set fulfilled.

================================================== 11. AUTO-GENERATE REQUIREMENTS FROM VISUAL PLAN

Implement a deterministic convenience operation:

generateRequirements(visualPlan)

This does NOT use AI.

It converts each VisualPlanItem into ONE initial AssetRequirement.

Rules:

visual_type → requirement_type:

animal_clip
stock_video
b_roll
screen_recording
→ video

photo
→ image

graphic
text
→ graphic

other
→ other

For every VisualPlanItem:

Create exactly one initial requirement.

Populate:

search_query:

- derived deterministically from `visual_prompt`
- do NOT use AI
- do NOT invent facts

description:

- visual_prompt

target_duration_seconds:

- visual_plan_item.duration_seconds

status:

- pending

If visual_prompt is empty or is the known placeholder:

"Define visual for this narration."

then:

search_query = null

description should still reflect the planning state.

Do NOT invent a search query.

================================================== 12. IDEMPOTENCY

Running:

generateRequirements()

multiple times MUST NOT create duplicates.

Define deterministic behavior.

Recommended:

For each VisualPlanItem:

If it already has an AssetRequirement generated from that item:

- leave it unchanged
- do not duplicate

If no requirement exists:

- create one

This makes the operation safe to repeat.

IMPORTANT:

Do not overwrite user-edited requirements.

Example:

Requirement originally generated:

search_query = "black crow close up"

User changes it to:

search_query = "crow looking at human"

Running generation again MUST preserve:

"crow looking at human"

================================================== 13. MANUAL REQUIREMENT CREATION

The API must also support manually adding requirements.

Example:

POST:

/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/asset-requirements

Payload:

{
"requirement_type": "video",
"search_query": "black crow flying",
"description": "Crow flying in the sky",
"target_duration_seconds": 5,
"aspect_ratio": "9:16",
"notes": "Prefer vertical footage"
}

Follow existing API conventions.

================================================== 14. REQUIREMENT CRUD

Implement:

GET requirements for item
POST requirement
PATCH requirement
DELETE requirement

Suggested:

GET
.../visual-plan/items/{item}/asset-requirements

POST
.../visual-plan/items/{item}/asset-requirements

PATCH
.../asset-requirements/{requirement}

DELETE
.../asset-requirements/{requirement}

Inspect route conventions and use the project's established style.

================================================== 15. REQUIREMENT STATUS

Implement status transition logic.

Suggested:

pending → searching
searching → fulfilled
searching → pending
pending → skipped
searching → skipped
skipped → pending

Do NOT allow:

fulfilled → pending

unless there is an explicit future asset-unassignment workflow.

For Part 15, fulfilled is mostly a future state.

Same-status transitions should be rejected.

Use:

- enum
- centralized transition logic
- controlled exception
- 422 response

================================================== 16. NO REAL ASSET ATTACHMENT

IMPORTANT:

Do NOT implement:

- Asset upload
- Asset download
- Asset search provider
- Pexels API
- Pixabay API
- YouTube API
- web scraping
- file downloading
- thumbnail downloading
- asset preview generation

Those belong to Phase 4.

Part 15 only creates the requirement.

================================================== 17. API RESPONSE

Create:

AssetRequirementResource

Expose:

- id
- visual_plan_item_id
- requirement_type
- requirement_type_label
- search_query
- description
- target_duration_seconds
- aspect_ratio
- status
- status_label
- notes
- created_at
- updated_at

Do not expose internal fields unnecessarily.

================================================== 18. VISUAL PLAN RESOURCE

Update VisualPlanItemResource if appropriate so that:

asset_requirements

are returned when loaded.

Avoid N+1.

Example:

{
"id": 1,
"order": 1,
"section": "hook",
"narration_text": "...",
"visual_type": "animal_clip",
"visual_prompt": "...",
"duration_seconds": 4,
"asset_requirements": [...]
}

Only include the relationship when loaded, following existing resource conventions.

================================================== 19. ASSET REQUIREMENT QUALITY

Part 15 should NOT have an AI quality gate.

However, implement deterministic validation:

A requirement should have:

- valid type
- description
- sensible duration when video/audio
- valid aspect ratio if provided

Do not introduce scoring.

Do not block the Visual Plan itself based on requirements yet.

================================================== 20. ASPECT RATIO

Support an optional aspect_ratio.

Suggested allowed values:

9:16
16:9
1:1
4:5

Default:

9:16

if that matches the existing project's Shorts production assumptions.

However:

Inspect existing configuration before hardcoding.

If the project already has a default output aspect ratio, reuse it.

Do not create a new global configuration just for this part unless necessary.

================================================== 21. SEARCH QUERY RULES

`search_query` is a production helper, NOT a factual claim.

It should be:

- short
- searchable
- based only on visual_prompt
- no invented details

Do not attempt NLP.

For Part 15, deterministic behavior can simply be:

search_query = visual_prompt

after trimming and length normalization.

If visual_prompt is a known placeholder:

search_query = null

This is intentionally simple.

Phase 4 may later add provider-specific query refinement.

================================================== 22. FRONTEND

Extend VisualPlanPanel.

Each VisualPlanItem should show:

Asset Requirements

Example:

Visual #1
Hook
Narration: ...

Asset Requirements:
┌─────────────────────────────────┐
│ VIDEO │
│ black crow close up │
│ 4 seconds · 9:16 │
│ Pending │
└─────────────────────────────────┘

Actions:

- Add Requirement
- Edit
- Delete

Also add:

"Generate Requirements"

button at Visual Plan level.

================================================== 23. GENERATE REQUIREMENTS UX

When user clicks:

Generate Requirements

Show confirmation:

"This will create missing asset requirements from the current visual plan. Existing requirements will not be overwritten."

After success:

- refresh plan
- show created/skipped count
- preserve manually edited requirements

Example result:

Created: 3
Existing: 2
Skipped: 0

Do not call AI.

Do not make network requests.

================================================== 24. REQUIREMENT FORM

Fields:

- requirement type
- search query
- description
- target duration
- aspect ratio
- notes

Status should NOT be freely editable from the normal creation form.

Status changes should use dedicated workflow endpoints if implemented.

================================================== 25. STATUS UI

Display:

Pending
Searching
Fulfilled
Skipped

Use existing badge/status conventions.

Do not imply that fulfilled means an actual asset exists unless an Asset relationship exists.

A fulfilled requirement is a future state.

================================================== 26. PROJECT DETAIL

Visual Plan remains under the Script/production planning context.

Do NOT create a separate top-level Asset Manager page yet.

Part 15 should stay focused.

================================================== 27. AUTHORIZATION / SCOPE

Follow existing policy conventions.

Required scope:

Project
↓
Script
↓
ScriptVersion
↓
VisualPlan
↓
VisualPlanItem
↓
AssetRequirement

Foreign/cross-project access:

404

Do not leak resource existence.

Use:

Gate::authorize(...)

where appropriate.

================================================== 28. TRANSACTIONS

`generateRequirements()` MUST run in a transaction.

Reason:

If multiple requirements are created and one fails:

- rollback all newly created requirements

Existing requirements must remain unchanged.

Manual CRUD does not necessarily need a multi-record transaction.

================================================== 29. TESTS — REQUIRED

Add focused tests.

### Model / relationship tests

1. requirement belongs to visual plan item
2. visual plan item has many requirements
3. multiple requirements allowed for one item

### Manual CRUD

4. create requirement
5. list requirements
6. update requirement
7. delete requirement
8. invalid type rejected
9. invalid duration rejected
10. invalid aspect ratio rejected
11. cross-project item rejected
12. cross-project requirement rejected

### Auto-generation

13. generate one requirement per visual plan item
14. animal_clip maps to video
15. stock_video maps to video
16. b_roll maps to video
17. photo maps to image
18. graphic maps to graphic
19. text maps to graphic
20. other maps to other
21. duration inherited
22. description inherited from visual_prompt
23. search_query derived from visual_prompt
24. placeholder visual_prompt creates null search_query
25. repeated generation does not duplicate
26. existing manually edited requirement is preserved
27. transaction rollback works

### Status

28. pending → searching
29. searching → fulfilled
30. searching → pending
31. pending → skipped
32. skipped → pending
33. invalid transition rejected
34. fulfilled cannot return to pending

### Isolation

35. requirement from another project → 404
36. item from another project → 404
37. plan from another project → 404

### Regression

38. visual plan remains functional
39. script version remains unchanged
40. research remains unchanged
41. traceability remains unchanged

Use deterministic tests.

Use `Http::preventStrayRequests()` where appropriate.

================================================== 30. FRONTEND TESTING

If frontend test infrastructure already exists:

Add tests for:

- requirement list
- add/edit/delete
- generate requirements
- preserve existing requirements
- status display

If no frontend test framework exists:

Do NOT introduce one.

Use:

npx tsc --noEmit
npm run build

================================================== 31. DATABASE

Create migration for:

asset_requirements

unless an equivalent table already exists.

Suggested:

asset_requirements:

- id
- visual_plan_item_id FK
- requirement_type
- search_query nullable
- description
- target_duration_seconds nullable
- aspect_ratio nullable
- status
- notes nullable
- timestamps

Indexes:

- visual_plan_item_id
- status

Do NOT add Asset foreign key yet.

Do NOT modify the existing `assets` table.

================================================== 32. MODEL / FACTORY

Create:

AssetRequirement
AssetRequirementFactory

Use project conventions.

Add relationships:

VisualPlanItem::assetRequirements()

AssetRequirement::visualPlanItem()

================================================== 33. SERVICE

Prefer:

AssetRequirementService

Responsibilities:

- list
- create
- update
- delete
- generateFromVisualPlan
- transitionStatus

Keep controllers thin.

Do not place business logic in React.

================================================== 34. CONTROLLER STRUCTURE

Follow existing API controller organization.

Potential:

VisualPlanAssetRequirementController

Methods:

- index
- store
- update
- destroy
- generate
- transitionStatus

Do not split into unnecessary controllers.

================================================== 35. STATUS ENDPOINT

If using dedicated status workflow:

PATCH

.../asset-requirements/{requirement}/status

Payload:

{
"status": "searching"
}

Backend is authoritative.

Frontend should only expose valid transitions.

================================================== 36. DOCUMENTATION

Create:

docs/PHASE_3_PART_15.md

Document:

- objective
- AssetRequirement concept
- relationship with VisualPlanItem
- manual CRUD
- deterministic generation
- idempotency
- status workflow
- no actual assets yet
- authorization
- transaction behavior
- API endpoints
- tests
- verification
- known limitations

================================================== 37. VERIFICATION

Run:

Backend:

php artisan test --compact

Relevant Part 15 tests individually.

If migration created:

- fresh
- rollback
- migrate
- migration status

Frontend:

npx tsc --noEmit

npm run build

Style:

vendor/bin/pint --test

Also inspect:

git diff
git status

Search for accidental debug code:

TODO
FIXME
console.log
dump(
dd(
var_dump(

Do not alter unrelated pre-existing violations.

================================================== 38. REGRESSION REQUIREMENT

Baseline:

701 tests
2470 assertions

All previous tests must remain green.

Report the actual final count.

Do not weaken or delete tests.

================================================== 39. COMMIT

After everything passes:

Create exactly one commit:

feat: add visual plan asset requirements foundation

Do NOT push.

Verify:

git status

Working tree must be clean.

================================================== 40. FINAL REPORT

When finished, report ONLY:

1. What was implemented
2. Files/modules changed
3. Database migration
4. API endpoints
5. Visual Plan → Asset Requirement behavior
6. Auto-generation/idempotency behavior
7. Test result
8. TypeScript/build/Pint result
9. Commit hash
10. Git working tree status
11. Known limitations

Then STOP.

Do NOT start Part 16 automatically.

================================================== APPENDIX A. IMPLEMENTATION RECORD
==================================================

Status: implemented and committed.

--------------------------------------------------
A.1. OBJECTIVE
--------------------------------------------------

Turn the Part 14 visual plan into an actionable asset list. Each planned shot
gains one or more AssetRequirement rows describing the media that must be
sourced to cover it.

This part is a planning foundation only. It does not download media, does not
call any provider, does not call AI, and does not touch the network. Searching,
downloading, and attaching assets belong to the Asset Manager in Phase 4.

--------------------------------------------------
A.2. ARCHITECTURE
--------------------------------------------------

app/Enums/AssetRequirementType.php
    video, image, audio, graphic, screen_recording, other.
    fromVisualType() is the single deterministic mapping from a visual plan
    item type to the asset type it needs.

app/Enums/AssetRequirementStatus.php
    pending, searching, fulfilled, skipped.
    Single authoritative transition map. fulfilled is deliberately terminal: no
    asset unassignment workflow exists yet, so there is no way back to pending.

app/Enums/AssetRequirementAspectRatio.php
    9:16, 16:9, 1:1, 4:5, with a DEFAULT constant of 9:16 because the studio
    produces vertical short-form content.

app/Models/AssetRequirement.php
    belongsTo VisualPlanItem. Casts the three enums plus the integer duration.

app/Models/VisualPlanItem.php
    hasMany AssetRequirement. A single item may hold several requirements, so
    the foreign key is not unique.

app/Services/AssetRequirementService.php
    listRequirements, createRequirement, updateRequirement, deleteRequirement,
    generateFromVisualPlan, transitionStatus.
    The controller stays thin; all logic and all scope checks live here.

--------------------------------------------------
A.3. DATABASE MIGRATION
--------------------------------------------------

database/migrations/2026_09_29_134657_create_asset_requirements_table.php

    id
    visual_plan_item_id      FK -> visual_plan_items, cascade on delete
    requirement_type         varchar(50)
    search_query             varchar(255) nullable
    description              text
    target_duration_seconds  unsigned int nullable
    aspect_ratio             varchar(10) nullable
    status                   varchar(20), default pending
    notes                    text nullable
    created_at, updated_at

No assets table exists in this codebase yet, so there is no Asset foreign key.
Phase 0 describes assets and project_assets, but those tables belong to the
Phase 4 Asset Manager. Nothing existing was modified.

--------------------------------------------------
A.4. API ENDPOINTS
--------------------------------------------------

All routes are nested under the existing visual plan group and sit behind the
same authentication, where ... is
/api/v1/projects/{project}/script/versions/{version}:

    GET    .../visual-plan/items/{item}/asset-requirements
    POST   .../visual-plan/items/{item}/asset-requirements
    PATCH  .../visual-plan/items/{item}/asset-requirements/{requirement}
    DELETE .../visual-plan/items/{item}/asset-requirements/{requirement}
    PATCH  .../visual-plan/items/{item}/asset-requirements/{requirement}/status
    POST   .../visual-plan/asset-requirements/generate

app/Http/Controllers/Api/V1/VisualPlanAssetRequirementController.php
    One controller for all six actions, mirroring how Part 14 handled the plan
    and its items.

app/Policies/AssetRequirementPolicy.php
    view, create, update, transitionStatus, delete.

app/Http/Requests/VisualPlan/
    StoreAssetRequirementRequest, UpdateAssetRequirementRequest,
    UpdateAssetRequirementStatusRequest.
    Neither the store nor the update request accepts status; status is only
    reachable through the dedicated status endpoint.

app/Http/Resources/AssetRequirementResource.php
    Exposes allowed_transitions so the UI never hardcodes the status graph.

app/Http/Resources/VisualPlanItemResource.php
    Adds asset_requirements via whenLoaded.

--------------------------------------------------
A.5. VISUAL PLAN TO ASSET REQUIREMENT BEHAVIOR
--------------------------------------------------

Requirements always inherit the full scope chain: project -> script -> version
-> visual plan -> plan item. An item belonging to another project, another
script version, or another plan resolves to 404 rather than being read or
written across the boundary. This matches the Part 14 convention of resolving
scope in the service rather than through route model binding.

A new requirement always starts as pending, decided server-side. A client
sending status fulfilled on create is ignored.

search_query is derived from the plan item's own visual_prompt: trimmed and
clamped to 255 characters. There is no NLP, no keyword extraction, and no
model call. The seeded placeholder prompt is not a real visual, so it produces
no query at all rather than a search for the placeholder text.

Auto-generation maps item types as follows:

    animal_clip       -> video
    stock_video       -> video
    b_roll            -> video
    screen_recording  -> video
    photo             -> image
    graphic           -> graphic
    text              -> graphic
    other             -> other

Generated requirements copy the item's duration as target_duration_seconds and
default aspect_ratio to 9:16.

--------------------------------------------------
A.6. AUTO-GENERATION AND IDEMPOTENCY
--------------------------------------------------

generateFromVisualPlan() runs inside a single database transaction:

  - An item that already has any requirement is skipped, counted as existing,
    and left untouched. This is what makes the operation idempotent and
    guarantees a user's manual edits are never overwritten.
  - An item with neither narration text nor a visual prompt is skipped and
    counted as skipped.
  - Everything else gets exactly one pending requirement, counted as created.

The endpoint returns created, existing, and skipped counts so the UI can report
precisely what happened instead of implying that all items were generated.

Because the work is transactional, a failure part way through leaves no partial
generation behind.

--------------------------------------------------
A.7. FRONTEND
--------------------------------------------------

resources/js/types/index.ts
    AssetRequirement, AssetRequirementType, AssetRequirementStatus,
    AssetRequirementAspectRatio, AssetRequirementTransition,
    AssetRequirementFormData, AssetRequirementGenerationSummary.
    VisualPlanItem gains an optional asset_requirements array.

resources/js/services/assetRequirementService.ts
    list, create, update, transitionStatus, remove, generate.

resources/js/components/projects/VisualPlanPanel.tsx
    Each plan item gains an Asset Requirements block with a type badge, a
    status badge, duration, aspect ratio, and search query, plus add, edit, and
    delete actions. Status changes are driven by the allowed_transitions
    payload, so a terminal requirement simply shows no action buttons.
    A plan-level Generate Requirements button opens a confirmation dialog that
    states plainly that existing requirements are left alone and that nothing is
    downloaded in this step.

No new top-level page was added; asset requirements stay inside the existing
Visual Plan tab.

--------------------------------------------------
A.8. TESTS
--------------------------------------------------

tests/Feature/AssetRequirementTest.php
    45 tests, 61 assertions. Model relations, cascade behaviour, service
    methods, cross-project and cross-version scoping, all eight generation
    mapping cases, placeholder handling, repeated generation, user-edit
    preservation, transaction rollback via a throwing model event, and the full
    status transition graph including fulfilled being terminal.

tests/Feature/AssetRequirementApiTest.php
    32 tests, 88 assertions. Authentication on all six endpoints, 404
    behaviour, validation, the several-requirements-per-item case, the
    client-supplied status and id cases, resource payloads, and generation
    idempotency over HTTP.

--------------------------------------------------
A.9. KNOWN LIMITATIONS
--------------------------------------------------

  - No real assets exist yet. A requirement is a text description, not a link
    to a stored file.
  - Nothing is searched for or downloaded. searching and fulfilled are manually
    driven states until the Asset Manager exists.
  - search_query is a plain copy of the visual prompt, not a query engineered
    for a stock provider.
  - Generation fills gaps only. Deleting a generated requirement and
    regenerating recreates it from the item, discarding manual edits made to
    that one requirement.
  - Reordering plan items does not reorder requirements; requirements have no
    order of their own.
  - Authorization is permissive, consistent with the rest of the project. Real
    per-user rules are not implemented yet.
