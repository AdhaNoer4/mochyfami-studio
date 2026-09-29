# MochyFami Content Studio

# PHASE 3 — PART 14

# Script → Visual Plan Foundation

You are continuing development of the existing MochyFami Content Studio repository.

IMPORTANT:

- Do NOT start Phase 4.
- This task is ONLY Phase 3 Part 14.
- Inspect the repository and existing implementation before changing anything.
- Preserve all existing working behavior.
- Do NOT rewrite existing architecture unnecessarily.
- Do NOT push to GitHub.
- Create exactly ONE focused commit.
- Stop after verification and commit.
- Never claim completion unless implementation and verification actually pass.

==================================================

1. # CURRENT PROJECT STATE

Existing completed phases/features:

Phase 1:

- Laravel + React + TypeScript foundation
- authentication
- dashboard
- API foundation

Phase 2:

- categories
- content ideas
- search/filter/sort/pagination
- CSV import
- projects
- idea → project conversion
- project status workflow
- dashboard

Phase 3 Parts 1–13:

Research:

- ResearchReport
- ResearchClaim
- Source
- claim/source evidence
- ResearchQualityService
- ResearchPipelineService
- SearchProvider abstraction
- source discovery
- AI research generation
- ResearchToScriptContext

Script:

- Script
- ScriptVersion
- immutable versioning
- script status workflow
- ScriptQualityService
- research alignment
- AI script generation
- research claim traceability
- version-aware script revision

Latest Part 13:

- commit:
  6e811b5 feat: add version-aware script revision workflow
- 642 tests
- 2297 assertions
- all passing
- TypeScript clean
- Vite build successful
- Pint clean
- working tree clean
- no push

================================================== 2. PART 14 OBJECTIVE
==================================================

Build the FOUNDATION for:

SCRIPT VERSION
↓
VISUAL PLAN
↓
VISUAL PLAN ITEMS

The purpose is NOT to render video.

The purpose is to create a deterministic, editable visual plan that describes what visual content should appear for each part of the selected script.

Example:

Script Version:

- Hook: "Jangan coba-coba bikin masalah dengan gagak..."
- Body: ...
- Closing: ...

Visual Plan:

Item 1

- section: hook
- narration_text: "Jangan coba-coba..."
- visual_type: animal_clip
- visual_prompt: "close-up black crow looking at camera"
- duration_seconds: 3

Item 2

- section: body
- narration_text: "Penelitian menunjukkan..."
- visual_type: research_visual
- visual_prompt: "crow recognizing human face"
- duration_seconds: 5

Item 3

- section: closing
- narration_text: "Ternyata..."
- visual_type: animal_clip
- visual_prompt: "crow flying away"
- duration_seconds: 4

This becomes the bridge between script writing and future asset collection.

================================================== 3. IMPORTANT ARCHITECTURAL RULE
==================================================

Inspect the existing repository first.

Phase 0 already described these entities:

- visual_plans
- visual_plan_items

If migrations/models/services do not yet exist, implement them.

Do NOT blindly assume exact schema names.

Follow the existing project's conventions for:

- UUID/integer IDs
- timestamps
- foreign keys
- API Resources
- Form Requests
- Services
- Policies
- React types
- ApiResponse

Do not introduce a new architecture.

================================================== 4. VISUAL PLAN DATA MODEL
==================================================

Create the minimum schema necessary.

VISUAL PLAN

Suggested fields:

- id
- content_project_id
- script_version_id
- status
- title or name if consistent with existing conventions
- notes if useful
- timestamps

VISUAL PLAN STATUS:

Use a small explicit enum.

Suggested:

draft
review
approved
archived

Do not invent many production statuses.

Visual plan status is separate from project status.

One project may eventually have multiple visual plans across script versions.

IMPORTANT:

A visual plan MUST belong to exactly one ScriptVersion.

A visual plan MUST NOT silently follow the script's current version.

Example:

Project
├── Script v1
│ └── Visual Plan v1
│
└── Script v2
└── Visual Plan v2

================================================== 5. VISUAL PLAN ITEM DATA MODEL
==================================================

Create VisualPlanItem.

Minimum fields should cover:

- id
- visual_plan_id
- order/index
- section
- narration_text
- visual_type
- visual_prompt
- duration_seconds
- notes
- timestamps

Inspect naming conventions in existing models before choosing exact column names.

Recommended:

section:

- hook
- body
- closing
- other

visual_type:

- animal_clip
- stock_video
- photo
- screen_recording
- graphic
- text
- b_roll
- other

Do NOT over-engineer asset references yet.

Part 14 is about planning.

Actual asset attachment belongs to the later Asset Manager phase.

================================================== 6. DURATION

duration_seconds should be numeric and validated.

Recommended:

- minimum: 1
- maximum: 60

Do not allow zero or negative durations.

Do not attempt automatic timing based on TTS yet.

The duration here represents the planned visual duration.

================================================== 7. ORDERING

Each VisualPlanItem has an explicit order.

Example:

1
2
3
4

The API must return items ordered by this field.

Do not rely on database insertion order.

Prevent duplicate ordering within the same visual plan if the schema architecture supports a unique constraint.

If reordering is implemented, keep it simple and deterministic.

Do NOT build drag-and-drop in this part unless the existing architecture makes it trivial.

================================================== 8. VISUAL PLAN CREATION

Add service-level functionality:

createVisualPlan(project, scriptVersion)

Requirements:

1. Project must exist.
2. ScriptVersion must belong to the project's script.
3. ScriptVersion must belong to the same project.
4. Source script version must exist.
5. Do not silently use the current version if another version was requested.
6. Create visual plan linked to that exact version.
7. Default status = draft.
8. Empty item list initially.

If a visual plan already exists for the same project + script version:

Return a controlled conflict according to existing project conventions.

Do not create duplicate plans accidentally.

================================================== 9. SCRIPT READINESS GATE

Visual plan creation must use the existing ScriptQualityService.

Before creating a visual plan:

- evaluate the selected script version using existing quality architecture.

The script must be reviewable/ready according to the existing project's semantics.

IMPORTANT:

Do NOT duplicate ScriptQualityService logic.

Reuse the existing service.

If the selected version is not ready:

Return controlled 422.

Do NOT mutate the script status.

Do NOT mutate research status.

Do NOT mutate quality state.

This is a read-only gate.

================================================== 10. VISUAL PLAN ITEM CRUD

Implement:

GET visual plan
POST item
PATCH item
DELETE item

Suggested routes:

GET
/api/v1/projects/{project}/visual-plan

POST
/api/v1/projects/{project}/visual-plan

GET
/api/v1/projects/{project}/visual-plan/items

POST
/api/v1/projects/{project}/visual-plan/items

PATCH
/api/v1/projects/{project}/visual-plan/items/{item}

DELETE
/api/v1/projects/{project}/visual-plan/items/{item}

However:

Inspect existing route conventions first.

If the existing architecture supports nested resource routes differently, follow that convention.

================================================== 11. SCOPE VALIDATION

This is critical.

Validate:

project
↓
visual plan
↓
script version
↓
item

Cross-project access must not leak information.

Expected behavior:

404

Examples:

Project A

- Script A
- Visual Plan A
- Item A

Project B must NOT be able to manipulate Item A.

Return 404 according to the project's existing scope convention.

================================================== 12. VISUAL PLAN ITEM VALIDATION

Required:

section:

- enum/string whitelist

narration_text:

- required
- string
- reasonable maximum length
- use project conventions

visual_type:

- enum/string whitelist

visual_prompt:

- required
- string
- reasonable maximum length

duration_seconds:

- integer/numeric
- min 1
- max 60

notes:

- optional
- string
- reasonable maximum

order:

- integer
- min 1

Do not allow arbitrary database fields.

================================================== 13. REORDERING

Provide a lightweight reorder capability only if it fits cleanly.

Suggested endpoint:

PATCH
/api/v1/projects/{project}/visual-plan/items/reorder

Request:

{
"items": [
{"id": 1, "order": 1},
{"id": 2, "order": 2},
{"id": 3, "order": 3}
]
}

Requirements:

- all items must belong to the same visual plan
- no duplicate order
- transaction
- rollback on failure
- cross-project IDs must fail safely
- do not reorder items from another plan

If implementing reorder adds unnecessary complexity, it is acceptable to defer it.

If deferred, document it explicitly.

================================================== 14. VISUAL PLAN STATUS WORKFLOW

Implement:

draft → review
review → approved
approved → archived

Also allow sensible backwards transitions if consistent with existing ScriptStatus architecture.

Same-status transitions should be rejected.

Use:

- enum
- centralized transition logic
- controlled exception
- 422 response

Do not duplicate ScriptStatus classes.

VisualPlan status is independent of Script status.

================================================== 15. RESOURCE RESPONSE

Create resources consistent with the existing project.

VisualPlanResource should expose:

- id
- project id
- script version id
- status
- status label
- created_at
- updated_at
- items when loaded

VisualPlanItemResource:

- id
- visual plan id
- order
- section
- section label if existing resource style supports it
- narration_text
- visual_type
- visual_type label
- visual_prompt
- duration_seconds
- notes
- timestamps

Do not expose internal-only fields.

================================================== 16. FRONTEND

Inspect current project tabs.

Add:

Visual Plan

to:

ProjectDetailPage

The tab should display the visual plan associated with the selected/current script version.

IMPORTANT:

Do not silently switch the selected script version.

The UI must show:

Script Version: vN

and make it obvious which version the visual plan belongs to.

================================================== 17. FRONTEND VISUAL PLAN PANEL

Create:

VisualPlanPanel.tsx

Minimum UI:

- Visual Plan header
- script version indicator
- status
- create plan button if missing
- item list
- add item
- edit item
- delete item
- empty state
- loading state
- error state
- success feedback

Each item should display:

#1
Section: Hook
Duration: 3s
Narration: ...
Visual Type: Animal Clip
Visual Prompt: ...

Keep UI simple.

No drag-and-drop required.

================================================== 18. ITEM FORM

Create a simple modal/form.

Fields:

- section
- narration text
- visual type
- visual prompt
- duration
- notes
- order

Use controlled state consistent with existing project patterns.

No external UI framework should be introduced.

================================================== 19. SCRIPT → VISUAL PLAN HELPER

Add a lightweight convenience action:

"Create from Script"

When creating a visual plan from a script version:

Do NOT automatically generate AI content.

Instead, pre-populate plan items from the script sections if this can be done deterministically.

Example:

Hook
→ narration_text = script.hook

Body
→ narration_text = script.body

Closing
→ narration_text = script.closing

For each generated item:

- visual_prompt = empty or a neutral placeholder only if the schema requires it
- duration_seconds = a safe default
- visual_type = other

IMPORTANT:

Do NOT invent visual descriptions.

The goal is to create editable planning rows from known script text.

If the current schema requires visual_prompt to be non-empty, use a neutral value such as:

"Define visual for this narration."

Clearly mark this as a placeholder.

The UI should allow the user to edit it immediately.

If automatic section splitting would require complex parsing, keep it to:

1 Hook item
1 Body item
1 Closing item

================================================== 20. NO AI / NO NETWORK

Part 14 MUST NOT:

- call an AI provider
- call a search provider
- download media
- call external APIs
- generate actual assets
- render video
- generate TTS

This part is purely deterministic database + API + frontend work.

Use Http::preventStrayRequests() in tests where appropriate.

================================================== 21. TRACEABILITY

Visual Plan must reference a specific ScriptVersion.

Do NOT copy research claim mappings.

Do NOT create a new research relationship.

The relationship is:

VisualPlan
↓
ScriptVersion
↓
Research Claims

This allows future asset selection to understand which script version is being produced.

Do not duplicate research claims into visual_plan_items.

================================================== 22. TESTS — REQUIRED

Add focused tests.

### Model/database

1. visual plan belongs to project
2. visual plan belongs to script version
3. script version belongs to same project
4. visual plan item belongs to plan
5. cascade behavior works appropriately

### Service/API

6. create visual plan successfully
7. duplicate visual plan rejected
8. missing project → 404
9. missing script version → 404
10. cross-project script version → 404
11. non-ready script rejected with 422
12. script status unchanged
13. research status unchanged
14. quality evaluation unchanged
15. create item
16. update item
17. delete item
18. list ordered by order
19. invalid section rejected
20. invalid visual type rejected
21. invalid duration rejected
22. invalid order rejected
23. cross-project item rejected
24. status transition works
25. invalid status transition rejected

### Create-from-script

26. hook item created from hook
27. body item created from body
28. closing item created from closing
29. correct order 1/2/3
30. source script version unchanged
31. no research mapping created

### Reorder, if implemented

32. valid reorder works
33. duplicate order rejected
34. cross-plan item rejected
35. transaction rollback works

Do not artificially inflate tests.

Use factories where appropriate.

================================================== 23. FRONTEND TESTING

If frontend testing infrastructure already exists:

Add focused tests.

If it does not exist:

Do NOT introduce a new testing framework.

Verify:

- TypeScript
- production build

================================================== 24. DATABASE MIGRATION

Migration is expected unless these tables already exist.

Before creating migrations:

Inspect:

- database/migrations
- existing models
- existing Phase 0 schema documentation

If visual_plans and visual_plan_items do not exist:

Create migrations.

Recommended constraints:

visual_plans:

- content_project_id FK
- script_version_id FK
- unique(content_project_id, script_version_id)

visual_plan_items:

- visual_plan_id FK
- order
- appropriate indexes
- optional unique(visual_plan_id, order)

Use the project's existing foreign-key conventions.

Do not add unnecessary columns.

================================================== 25. POLICIES / AUTHORIZATION

Follow existing project authorization architecture.

Add:

VisualPlanPolicy

only if needed by current architecture.

Do not create a parallel authorization mechanism.

Use:

Gate::authorize(...)

as existing Research/Script APIs do.

================================================== 26. NO N+1

Ensure:

GET visual plan

does not cause unnecessary repeated queries for:

- script version
- items
- project

Use eager loading where appropriate.

Add query-bound tests if the project already uses that pattern.

================================================== 27. DOCUMENTATION

Create:

docs/PHASE_3_PART_14.md

Include:

- objective
- architecture
- database schema
- API endpoints
- script readiness gate
- visual plan/item workflow
- script version relationship
- create-from-script behavior
- status workflow
- authorization
- tests
- verification
- known limitations

================================================== 28. VERIFICATION

Run:

Backend:

php artisan test --compact

Relevant Part 14 tests individually.

If migrations changed:

- fresh migration
- rollback
- migrate again
- verify status

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

Do not modify unrelated existing code merely because of search results.

================================================== 29. REGRESSION REQUIREMENT

Baseline:

642 tests
2297 assertions

All previous tests must remain green.

Report actual final count.

Do not delete or weaken existing tests.

================================================== 30. COMMIT

After successful verification:

Create exactly one commit:

feat: add script visual plan foundation

Do NOT push.

Then verify:

git status

Working tree must be clean.

================================================== 31. FINAL REPORT

When finished, report ONLY:

1. What was implemented
2. Files/modules changed
3. Database migrations
4. API endpoints
5. Script → Visual Plan relationship
6. Test result
7. TypeScript/build/Pint result
8. Commit hash
9. Git working tree status
10. Known limitations

Then STOP.

Do NOT start Part 15 automatically.

================================================== APPENDIX A. IMPLEMENTATION RECORD
==================================================

Status: implemented and committed.

--------------------------------------------------
A.1. OBJECTIVE
--------------------------------------------------

Provide the Script → Visual Plan foundation: a version-bound, human-controlled
storyboard that pairs each narration line with the visual that must cover it.

This part deliberately does not generate visuals, does not estimate timing from
audio, and does not persist source asset selections. Those belong to the Asset
Manager, which will read this plan.

A visual plan is bound to exactly one script version. Creating a plan never
mutates the script, its research, or its quality state.

--------------------------------------------------
A.2. ARCHITECTURE
--------------------------------------------------

app/Enums/VisualPlanStatus.php
    draft -> review -> approved -> archived, plus draft from archived.
    Single authoritative transition map, independent of script status.

app/Enums/VisualPlanSection.php
    hook, body, closing, other.

app/Enums/VisualPlanItemType.php
    animal_clip, stock_video, photo, screen_recording, graphic, text, b_roll,
    other.

app/Models/VisualPlan.php
    belongsTo ContentProject, belongsTo ScriptVersion,
    hasMany VisualPlanItem ordered by `order`.

app/Models/VisualPlanItem.php
    belongsTo VisualPlan.

app/Services/VisualPlanService.php
    Owns the readiness gate, plan creation (empty and seeded), item CRUD,
    deterministic reorder, and status transitions. No AI, no network calls.

app/Http/Controllers/Api/V1/VisualPlanController.php
    show, store, transitionStatus.

app/Http/Controllers/Api/V1/VisualPlanItemController.php
    index, store, update, destroy, reorder.

app/Policies/VisualPlanPolicy.php
    view, create, update, updateStatus, delete.

Requests in app/Http/Requests/VisualPlan/:
    StoreVisualPlanRequest, StoreVisualPlanItemRequest,
    UpdateVisualPlanItemRequest, UpdateVisualPlanStatusRequest,
    ReorderVisualPlanItemsRequest.

Resources:
    VisualPlanResource (includes allowed_transitions),
    VisualPlanItemResource (includes section_label, visual_type_label).

Frontend:
    resources/js/services/visualPlanService.ts,
    resources/js/components/projects/VisualPlanPanel.tsx,
    resources/js/types/index.ts,
    Visual Plan tab in ProjectDetailPage.tsx.

--------------------------------------------------
A.3. DATABASE SCHEMA
--------------------------------------------------

visual_plans

    id
    content_project_id      FK -> content_projects, cascade on delete
    script_version_id       FK -> script_versions, cascade on delete
    status                  string, default 'draft'
    title                   string nullable
    notes                   text nullable
    timestamps
    UNIQUE (content_project_id, script_version_id)

visual_plan_items

    id
    visual_plan_id          FK -> visual_plans, cascade on delete
    order                   unsigned integer
    section                 string(20)
    narration_text          text
    visual_type             string(50)
    visual_prompt           text
    duration_seconds        unsigned integer
    notes                   text nullable
    timestamps
    UNIQUE (visual_plan_id, order)

Both migrations ran cleanly. `order` is a plain unsigned integer, not a range
column; contiguity is enforced in the reorder service, not the schema.

No asset, timing-source, or TTS columns exist yet. The Phase 0 schema notes
describe a wider asset-oriented shape; the schema above is the Part 14 scope.

--------------------------------------------------
A.4. API ENDPOINTS
--------------------------------------------------

All routes require Sanctum authentication and are nested under an exact script
version number.

GET    /api/v1/projects/{project}/script/versions/{version}/visual-plan
    200 with the plan, or 200 with data: null and the message
    "No visual plan yet for this script version." when the version exists but
    has no plan. 404 when the script or version does not belong to the project.

POST   /api/v1/projects/{project}/script/versions/{version}/visual-plan
    Body: { title?, notes?, create_from_script? }
    201 with the plan. 409 when a plan already exists for that version.
    422 when the script fails the readiness gate.

PATCH  /api/v1/projects/{project}/script/versions/{version}/visual-plan/status
    Body: { status }
    200 with the plan. 422 for a transition the workflow does not allow,
    including a transition to the current status.

GET    /api/v1/projects/{project}/script/versions/{version}/visual-plan/items
    200 with { items: [...] } ordered by `order`. 404 when no plan exists.

POST   /api/v1/projects/{project}/script/versions/{version}/visual-plan/items
    201 with the item. 422 on validation failure or a duplicate order.

PATCH  /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}
    Partial update. 422 when no updatable field is supplied or the order is
    taken. 404 when the item does not belong to this plan.

DELETE /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}
    200 with a success message. 404 when the item is not in this plan.

PATCH  /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/reorder
    Body: { items: [{ id, order }, ...] }
    200 with a success message. 422 when the payload is not the complete plan.

Deleting an item does not renumber the remaining items. Orders may therefore
contain gaps after a delete; the reorder endpoint accepts a contiguous 1..n
payload built from the current item set.

--------------------------------------------------
A.5. SCRIPT READINESS GATE
--------------------------------------------------

Plan creation reuses the existing read-only evaluation:

    ScriptQualityService::evaluateScript($script)['ready']

The script must be in review or approved, have a current version, and pass the
deterministic quality gate, including research readiness and important-claim
alignment. A failure raises VisualPlanNotReadyException, which the controller
maps to 422.

The evaluation is never persisted and never mutates state. Nothing in this part
writes a score.

Known limitation: the gate always evaluates the script's *current* version,
even when the request targets a historical version. Creating a plan for an old
version therefore reflects current script readiness, not that version's.

--------------------------------------------------
A.6. SCRIPT VERSION RELATIONSHIP
--------------------------------------------------

A plan belongs to one exact script version, never to the script itself. The
unique constraint on (content_project_id, script_version_id) makes duplication
impossible at the database level, and the service raises
DuplicateVisualPlanException (409) first so the message is meaningful.

Resolving a version requires that version number to exist inside that
project's own script. A version belonging to another project, or to another
script, produces a 404. This is enforced in the service, not by route binding,
so the check cannot be bypassed by calling the service directly.

Revising a script creates a new version and returns the script to draft. An
existing plan is not migrated, copied, or invalidated; it stays attached to the
version it was built from. This is intentional: a storyboard is an editorial
decision, and silently rewriting it would lose that decision.

--------------------------------------------------
A.7. CREATE-FROM-SCRIPT
--------------------------------------------------

With create_from_script=true, the service creates the plan and seeds exactly
three items in order:

    order 1  section hook     narration from the version hook
    order 2  section body     narration from the version body
    order 3  section closing  narration from the version closing

Seeded items use visual_type "other", the placeholder visual prompt
"Define visual for this narration.", and a duration of 5 seconds.

The 5-second duration is a safe starting value for manual review, not an audio
or TTS estimate. No timing measurement happens in this part.

Seeding is a copy of text only. It never creates research claim mappings, never
touches the source version, and never changes script status.

--------------------------------------------------
A.8. VISUAL PLAN / ITEM WORKFLOW
--------------------------------------------------

Item fields: order, section, narration_text, visual_type, visual_prompt,
duration_seconds, and optional notes.

Validation:

    order             integer, min 1, unique per plan
    section           required, one of the VisualPlanSection values
    narration_text    required, string, max 5000
    visual_type       required, one of the VisualPlanItemType values
    visual_prompt     required, string, max 2000
    duration_seconds  required, integer, 1..60
    notes             nullable, string, max 2000

A partial update must change at least one field. Passing only nulls is rejected
with a 422 on `section`, matching the existing at-least-one-field convention in
the script revision request.

--------------------------------------------------
A.9. REORDERING
--------------------------------------------------

The reorder endpoint replaces the whole ordering, not a single item move.

Validation, all mapped to 422:

    - every item in the plan must appear exactly once
    - every id must belong to this plan
    - orders must be unique
    - orders must form a contiguous sequence starting at 1

The write runs in a transaction. To satisfy the unique (visual_plan_id, order)
constraint, existing orders are first shifted into a high temporary band, then
the final orders are written. Because `order` is unsigned, the temporary values
are positive (current order + 1,000,000) rather than negative.

A failure partway through rolls back every change, so the plan never keeps a
partial reorder.

--------------------------------------------------
A.10. STATUS WORKFLOW
--------------------------------------------------

    draft     -> review, archived
    review    -> draft, approved, archived
    approved  -> archived
    archived  -> draft

Visual plan status is independent of script status. The resource exposes
allowed_transitions with label, action, and a destructive flag, so the frontend
never hardcodes the graph. The frontend asks the API; the API asks the enum.

No transition implies item validation. Approving a plan does not verify that
items exist, and this is a known limitation.

--------------------------------------------------
A.11. AUTHORIZATION
--------------------------------------------------

VisualPlanPolicy covers view, create, update, updateStatus, and delete. Item
routes authorize against the parent plan's update ability, so item writes are
gated once, at the plan level.

Ownership scope is enforced in the service: the project, the script, the
version, the plan, and the item must all resolve within the same project. This
is the same defense-in-depth pattern used by the script revision and research
claim endpoints, and it is covered by cross-project and cross-plan tests.

--------------------------------------------------
A.12. TRACEABILITY
--------------------------------------------------

The plan references its script version, and nothing else. Research claims are
not copied into visual_plan_items. Traceability from a plan back to research
stays available through the script version it is bound to.

--------------------------------------------------
A.13. NO AI / NO NETWORK
--------------------------------------------------

This part is fully deterministic. No AI provider, no HTTP client call, no
external service. The frontend tab performs only the documented API calls.

--------------------------------------------------
A.14. TESTS
--------------------------------------------------

tests/Feature/VisualPlanTest.php — 32 tests covering the model and service.
tests/Feature/VisualPlanApiTest.php — 27 tests covering HTTP behavior.

Model and relations: ownership, version binding, item binding, and cascade
behavior on project, script version, and plan deletion.

Service: empty and seeded creation, duplicate rejection, historical version
creation, item create/update/delete, default and explicit order, duplicate order
rejection, cross-project and cross-plan isolation, status transitions including
invalid and same-status cases, create-from-script seeding and order, source
version immutability, and absence of research mappings.

Reorder: valid reorder, duplicate orders, an item from another plan, and
transaction rollback verified through a model event that throws mid-update.

API: authentication on all eight routes, 404 for a missing project, script,
version, or item, 409 duplicate, 422 for a non-ready script, invalid enum
values, out-of-range duration, duplicate order, empty partial update, and
invalid status transitions, plus the success shape of every endpoint.

The test database is the configured MySQL test database, not SQLite. Tests use
the existing readyProject-style helper, with claim text deliberately represented
in the script body, because the quality gate treats an important claim that is
not found in the script text as a blocker. That is a real constraint of the
gate, not a test artifact.

--------------------------------------------------
A.15. VERIFICATION
--------------------------------------------------

    php artisan test --compact        701 tests, 2470 assertions, all passing
    npx tsc --noEmit                 no type errors
    npm run build                    production build succeeds
    vendor/bin/pint                  clean
    migrations                       up and rollback verified

No frontend test framework was introduced. The project has none, and Part 14
did not add one.

--------------------------------------------------
A.16. KNOWN LIMITATIONS
--------------------------------------------------

1. The readiness gate evaluates the script's current version, not the targeted
   historical version.

2. Deleting an item leaves a gap in the ordering. Reorder normalizes it.

3. Status transitions do not validate item completeness. An empty plan can be
   approved.

4. `order` is not a range column. Non-contiguous stored orders are possible
   after a delete; only reorder enforces contiguity.

5. The seeded 5-second duration is a placeholder, not an audio-derived
   estimate. Per-item timing from TTS is deferred.

6. No visual plan deletion endpoint exists. Archiving is the terminal
   operation; the policy method and the enum transition are in place.

7. No N+1 exposure in the panel: the plan is loaded with its script version and
   the item list is fetched once, separately from the plan payload.

8. The visual plan panel is a single tab on the project detail page. The
   pipeline step diagram already had a Visual Plan step, which remains a
   placeholder indicator.
