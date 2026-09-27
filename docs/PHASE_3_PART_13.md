# MochyFami Content Studio

# PHASE 3 — PART 13

# Script Revision & Research-Aware Editing

You are continuing development of the existing MochyFami Content Studio repository.

IMPORTANT:

- Do NOT start Phase 4.
- This task is ONLY Phase 3 Part 13.
- Do NOT redesign existing architecture.
- Do NOT rewrite working features.
- Inspect the repository and existing implementation before changing anything.
- Preserve all existing behavior unless this task explicitly requires an extension.
- Do NOT push to GitHub.
- Create exactly ONE focused commit at the end.
- Stop after verification and commit.
- Never claim completion unless the implementation and verification actually pass.

==================================================

1. # CURRENT PROJECT STATE

The project already has:

Phase 1:

- Foundation
- Laravel + React + TypeScript
- Authentication
- Dashboard
- API foundation

Phase 2:

- Category Management
- Content Ideas CRUD
- Search/filter/sort/pagination
- CSV bulk import
- Projects CRUD
- Idea → Project conversion
- Project status workflow
- Real dashboard data

Phase 3 completed Parts:

Part 1:

- Research foundation
- ResearchReport
- ResearchClaim
- Source
- Research enums

Part 2:

- Research lifecycle
- Claims
- Sources
- Status workflow
- API
- Frontend ResearchPanel

Part 3:

- Research claim ↔ source evidence pivot
- Evidence attach/detach API
- Frontend evidence management

Part 4:

- ResearchQualityService
- Research quality gate
- blockers/warnings
- deterministic quality evaluation

Part 5:

- SearchProvider contract
- SearchQuery/SearchResponse DTOs
- FakeSearchProvider
- Provider registry
- DomainExtractor
- ResearchIntelligenceService
- Discover Sources API
- frontend source discovery

Part 6:

- ResearchPipelineService
- deterministic research execution pipeline
- progress
- next actions
- read-only API/frontend pipeline card

Part 7:

- Script foundation
- ScriptStatus
- Script
- ScriptVersion
- current_version_id
- ScriptService
- immutable script versions
- script status workflow
- ScriptPanel

Part 8:

- ScriptTextNormalizer
- ScriptQualityService
- research alignment
- quality blockers/warnings
- deterministic script quality gate

Part 9:

- provider-agnostic AI script generation foundation
- ScriptGenerationProvider
- FakeScriptGenerationProvider
- ScriptGenerationService
- AI generation audit
- Generate with AI frontend

Part 10:

- provider-agnostic AI research generation foundation
- ResearchGenerationProvider
- FakeResearchGenerationProvider
- ResearchGenerationService
- research AI generation audit

Part 11:

- ResearchToScriptContext DTO/service
- research → script context bridge
- generation now consumes ResearchToScriptContextService

Part 12:

- script version ↔ research claim traceability
- script_version_research_claim pivot
- ScriptResearchTraceabilityService
- mapping attach/detach
- traceability summary
- ResearchTraceability frontend
- generation DOES NOT auto-map claims

Latest known commit:
42f3f84 feat: add script research traceability

Latest reported verification:
608 tests
2184 assertions
all passing
TypeScript clean
Vite build successful
Pint clean
migration fresh/rollback/remigrate verified
working tree clean

================================================== 2. PART 13 OBJECTIVE
==================================================

Implement:

SCRIPT REVISION WITH VERSION-AWARE RESEARCH TRACEABILITY

The goal is to make script revision safe and explicit.

Current architecture already has immutable ScriptVersion records.

Part 13 must introduce a proper revision workflow:

Current Version
↓
Edit / Revise
↓
Create NEW ScriptVersion
↓
Preserve old version
↓
Optionally preserve selected research-claim mappings
↓
Re-evaluate Script Quality
↓
Human reviews the new version

The system MUST NOT mutate the text of an existing ScriptVersion.

================================================== 3. CORE REQUIREMENTS
==================================================

A. Create a new revision from an existing version

Add a service-level operation conceptually:

createRevision(
project,
script,
sourceVersion,
changes
)

The implementation should follow the existing service architecture.

The revision must:

1. Validate that the source version belongs to the project's script.
2. Validate ownership/scope using the same conventions already used by ScriptService.
3. Create a new ScriptVersion.
4. Version number must be generated server-side:
   max(existing version) + 1
5. Existing ScriptVersion must remain unchanged.
6. New version inherits unchanged fields from source version.
7. Only explicitly provided editable fields are replaced.
8. The new version becomes the script's current version.
9. Script status should become `draft`.
10. Existing research claim mappings must NOT be blindly copied by default.

IMPORTANT:
Do not silently carry traceability mappings from the old version.

A new version is a new document.

================================================== 4. EDITABLE SCRIPT FIELDS
==================================================

Inspect the existing ScriptVersion schema before implementing.

Use the actual existing fields.

Likely fields include:

- hook
- body
- closing

Do NOT invent fields if the repository differs.

The API must support partial changes.

Example:

{
"hook": "new hook"
}

Result:

- hook = new hook
- body = inherited
- closing = inherited

Another example:

{
"body": "new body",
"closing": "new closing"
}

Result:

- hook = inherited
- body = new body
- closing = new closing

Do not allow arbitrary database fields to be mass-assigned.

================================================== 5. REVISION ENDPOINT
==================================================

Add an endpoint following existing API conventions.

Suggested:

POST
/api/v1/projects/{project}/script/versions/{version}/revise

Request:

{
"hook": "...optional...",
"body": "...optional...",
"closing": "...optional..."
}

Requirements:

- at least one editable field required
- validate string lengths using existing project conventions
- reject unknown fields if current validation architecture supports this
- source version must exist
- cross-project version must return 404 according to existing scope behavior
- successful response should return the new version
- return the new current version state
- return quality evaluation for the new version if the current architecture makes this appropriate

Do not change existing version endpoints unless necessary.

================================================== 6. TRACEABILITY BEHAVIOR
==================================================

This is critical.

When creating a revision:

DO NOT automatically copy:

script_version_research_claim

from the old version to the new version.

Instead:

New version starts with:

mapped claims = 0

traceability_complete = false

unless there is an explicit user action to map claims.

This preserves semantic correctness.

The UI should make this clear.

Example:

Version 1

- claims mapped: 4
- traceability complete

Revise Version 1
↓
Version 2

- claims mapped: 0
- traceability incomplete
- user must explicitly map claims again

================================================== 7. VERSION HISTORY
==================================================

Inspect the existing ScriptPanel.

Add a lightweight version history UI if the current UI architecture allows it without becoming a large redesign.

The UI should show:

Version 1
Version 2
Version 3
...

For each version:

- version number
- created date/time if already exposed
- status
- current indicator
- optionally short preview

User should be able to select/view an older version.

IMPORTANT:

Viewing an old version must NOT make it current automatically.

Only explicit revision should create a new current version.

================================================== 8. REVISE UI

For the current version, add:

"Revise Version"

or equivalent Indonesian UI text consistent with the existing interface.

The revision form should:

- preload current values
- allow editing hook/body/closing
- submit revision
- show loading state
- show success/error state
- refresh script state
- refresh quality
- refresh traceability

After successful revision:

New version:

- becomes current
- status = draft
- quality is recalculated
- traceability is empty
- old version remains unchanged

================================================== 9. QUALITY INTEGRATION
==================================================

Reuse the existing:

ScriptQualityService

Do NOT duplicate quality logic.

After revision:

1. New version becomes current.
2. ScriptQualityService evaluates the new current version.
3. Existing version quality must remain conceptually historical; do not introduce a new historical quality table unless required by the existing architecture.

The API may return:

{
"script": ...,
"version": ...,
"quality": ...
}

Follow existing response/resource conventions.

================================================== 10. STATUS BEHAVIOR
==================================================

Revision should force the script back to:

draft

This is intentional.

Example:

approved
↓ revise
draft

review
↓ revise
draft

archived versions:

- an archived historical version may be used as the source for a revision
- the newly created version becomes draft
- the archived version remains archived

Do NOT mutate the source version's status.

================================================== 11. RESEARCH CONTEXT WARNING
==================================================

A revision may make existing research traceability invalid.

Therefore the UI should communicate:

"Versi baru belum memiliki mapping research claim. Silakan lakukan mapping ulang."

Use the project's existing language/style.

Do not automatically infer or map claims.

================================================== 12. API RESPONSE DESIGN
==================================================

Follow existing ApiResponse and Resource conventions.

Prefer existing resources.

Do not introduce a completely new response envelope if the project already has one.

A successful revision should expose enough information for the frontend to update without a full page reload.

At minimum:

- script
- new version
- current version
- quality
- traceability summary if inexpensive and already available

Avoid N+1 queries.

================================================== 13. SERVICE DESIGN
==================================================

Keep business logic out of controllers.

Prefer:

ScriptService
↓
createRevision()

Potentially reuse existing methods for:

- current version handling
- version number generation
- validation
- status updates

Do not duplicate versioning logic.

Use DB transaction for:

1. create new version
2. set current_version_id
3. update script status

If the transaction fails:

- no partial new version should remain
- current_version_id must remain unchanged
- old version remains intact

================================================== 14. SECURITY / SCOPE
==================================================

Follow existing project conventions.

Validate:

project
→ script
→ source version

All must belong to the same project.

Cross-project version access must NOT leak information.

Expected behavior:

404

Do not reveal whether the foreign version exists.

Follow existing ScriptPolicy behavior.

Do not introduce a new authorization architecture in this part.

================================================== 15. TESTS — REQUIRED
==================================================

Add focused tests.

At minimum:

### Revision service tests

1. creates new version
2. increments version number
3. source version remains unchanged
4. partial hook update inherits body/closing
5. partial body update inherits hook/closing
6. partial closing update inherits hook/body
7. multiple fields can be changed
8. new version becomes current
9. script status becomes draft
10. old version remains unchanged
11. source version may be historical
12. transaction safety
13. cross-project version rejected
14. no traceability mappings copied

### API tests

15. successful revision returns 201
16. missing editable fields → 422
17. invalid field length → 422
18. missing version → 404
19. cross-project version → 404
20. new version returned correctly
21. current version updated
22. script status draft
23. old version still accessible

### Traceability tests

24. old version mappings remain
25. new version starts unmapped
26. mapping new version works through existing endpoint
27. detaching new version does not affect old version
28. traceability summaries are independent

### Quality tests

29. revised version quality recalculates
30. revision does not mutate research data
31. revision does not mutate research quality
32. revision does not mutate old script version

Use deterministic tests.

No network.

Use Http::preventStrayRequests() where appropriate.

================================================== 16. FRONTEND TESTS

If frontend testing infrastructure exists:

Test:

- revision form loads current values
- submit creates new version
- new version becomes selected/current
- old version remains selectable
- traceability becomes incomplete
- quality refreshes
- errors are displayed

If frontend test infrastructure does not exist, do not introduce a large new testing framework in this part.

Backend tests are mandatory.

================================================== 17. DATABASE

Do NOT create a new migration unless absolutely necessary.

Existing ScriptVersion schema should already support this.

If no schema change is required:

- explicitly document "No migration required."

Do not modify the traceability pivot structure.

================================================== 18. DOCUMENTATION

Create:

docs/PHASE_3_PART_13.md

Include:

- objective
- revision workflow
- API endpoint
- versioning behavior
- inheritance behavior
- traceability behavior
- status behavior
- quality behavior
- security/scope behavior
- tests
- verification results
- known limitations

================================================== 19. VERIFICATION

Before claiming completion, run:

Backend:

- php artisan test --compact
- relevant Part 13 tests
- migration/status checks if relevant
- php artisan pint --test

Frontend:

- npx tsc --noEmit
- npm run build

Also inspect:

- git diff
- git status

Search for:

- TODO
- FIXME
- console.log
- dump(
- dd(
- var_dump(
- mock placeholder code

Do not modify unrelated code merely to make the search clean.

================================================== 20. REGRESSION REQUIREMENT

All previous tests must remain green.

The expected baseline before this part is:

608 tests
2184 assertions

Part 13 should add new tests on top of that.

Report the actual final number.

If existing tests fail because of this change:

- investigate
- fix the regression
- do not weaken or delete tests merely to pass

================================================== 21. COMMIT

After everything passes:

Create exactly one commit:

feat: add version-aware script revision workflow

Do NOT push.

Verify:

git status

must be clean after the commit.

================================================== 22. FINAL REPORT

When finished, report ONLY:

1. What was implemented
2. Files/modules changed
3. API endpoint
4. Version/traceability behavior
5. Test result
6. TypeScript/build/Pint result
7. Commit hash
8. Whether git working tree is clean
9. Known limitations

Then STOP.

Do not start Part 14 automatically.

==================================================================
APPENDIX A — IMPLEMENTATION & VERIFICATION
==================================================================

Completed on 2026-09-27.

OBJECTIVE

Implemented the version-aware script revision workflow (this document's
sections 2-14): a human can revise ANY version of the project script —
current, historical, or from an archived script — into a NEW immutable
version without ever mutating the source, while inheriting research-aware
behavior: status returns to draft, research-claim mappings are NOT copied,
and quality is recalculated against the new current version.

REVISION WORKFLOW

1. POST /api/v1/projects/{project}/script/versions/{version}/revise
2. Request contains only the editable fields hook / body / closing
   (at least one must be non-empty).
3. ScriptVersionController resolves the script + source version, then calls
   ScriptService::createRevision within a database transaction.
4. The new version is written as max(existing version) + 1, becomes the
   current version, and the script status is forced back to draft.
5. The response returns { script, version, quality, traceability } (201).

API ENDPOINT

POST /api/v1/projects/{project}/script/versions/{version}/revise

- auth: sanctum, Gate::authorize('update', $script)
- 201  revised
- 404  project has no script / source version does not exist in this script
- 422  no editable field provided / value invalid / unknown fields
- 401  unauthenticated

VERSIONING BEHAVIOR

- Version numbers are always sequential per script: max(existing) + 1.
- The source version is immutable; nothing on it ever changes.
- The new version becomes the current version; the script version_count
  grows by one.

INHERITANCE BEHAVIOR

- Only hook, body, and closing may be replaced.
- title, duration_seconds, and notes are always inherited from the source.
- Non-editable fields in the request are rejected (422) by
  ReviseScriptVersionRequest and additionally stripped by the service
  allowlist (ScriptService::REVISION_EDITABLE_FIELDS).

TRACEABILITY BEHAVIOR

- Mappings are version-specific and NEVER copied to the new version.
- The new version starts with zero mapped claims and
  traceability_complete = false with the "no claims mapped" warning.
- Removing a mapping from the revised version never touches the source
  version's mappings; summaries stay independent per version.

STATUS BEHAVIOR

- Revision forces ScriptStatus::Draft, regardless of the source script's
  status (including approved or archived). Status transitions still follow
  the normal workflow afterwards.

QUALITY BEHAVIOR

- ScriptQualityService::evaluateScript is deterministic, read-only, and is
  re-run against the new current version for the revise response.
- Research data, research quality, and all prior versions are never mutated.

SECURITY / SCOPE

- Version resolution is strictly scoped to the target project's script, so
  a version number that exists only on another project's script is a 404.
- Unknown fields are rejected at the request layer and ignored at the
  service layer; no arbitrary database fields can be mass-assigned.
- Traceability re-validates project/script/version/claim ownership.

TESTS

- tests/Feature/ScriptRevisionTest.php — 20 service-level tests:
  revision creation, version increment, source immutability, partial
  inheritance, multi-field replacement, title/duration/notes inheritance,
  non-editable field stripping, current-version + draft enforcement,
  historical and archived sources, unknown-version 404, per-script scope,
  transaction safety (rollback via a failing creating event), traceability
  isolation (no copy, original intact, independent mapping + summaries),
  quality recalculation, read-only behavior.
- tests/Feature/ScriptRevisionApiTest.php — 14 API tests:
  201 shape (script/version/quality/traceability), 400/404/422 cases,
  unknown-field 422, version isolation, prior versions remain accessible,
  authentication.

VERIFICATION RESULTS

- php artisan test --compact .......... 642 tests, 2297 assertions, all pass
- Prior baseline ........................ 608 tests, 2184 assertions (+34/+113)
- npx tsc --noEmit ...................... clean
- npm run build ......................... successful
- vendor/bin/pint --dirty ............... fixed formatting, no further issues
- git status / git diff ................. inspected; single focused commit
- TODO / FIXME / console.log / dump( / dd( / var_dump( sweep .... clean
- No new database migration required: the existing script_versions schema
  already supports revisions. No pivot table changes.

KNOWN LIMITATIONS

- A revision replaces only provided editable fields; it cannot be used to
  clear a field (omitted fields are inherited by design).
- title, duration_seconds, and notes are not editable through revision;
  use the existing New Version / Edit Current flows for those.
- The frontend has no automated test framework in this project; the revise
  UI was verified via TypeScript + production build only.
- Version history shows date/time and a hook preview; it remains read-only
  and never auto-makes a historical version current.

COMMIT

feat: add version-aware script revision workflow
