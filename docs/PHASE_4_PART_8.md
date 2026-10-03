# PHASE 4 — PART 8: PRODUCTION ASSET MANIFEST

## Project Context

You are working on MochyFami Content Studio, a Laravel + React + TypeScript application for managing faceless YouTube Shorts production.

Phase 4 progress:

* Part 1: Asset Domain Foundation — completed.
* Part 2: Asset Library & CRUD — completed.
* Part 3: Asset ↔ AssetRequirement Candidate Association — completed.
* Part 4: Asset File Upload & Private Storage — completed.
* Part 5: Asset Preview & Technical Metadata — completed.
* Part 6: Asset Fulfillment & Requirement Matching — completed.
* Part 7: Asset Fulfillment & Production Readiness Gate — completed.

Latest Part 7 commit has already been created locally.

Known baseline after Part 7:

* 1,253 tests passed
* 3,842 assertions passed
* TypeScript clean
* Vite production build successful
* Pint clean

Do not assume the exact commit hash. Inspect the repository.

---

# MAIN OBJECTIVE

Implement:

**Production Asset Manifest**

The Production Asset Manifest is a deterministic, read-only representation of the assets that have been explicitly selected and fulfilled for a Visual Plan.

Its purpose is to provide a stable production-facing data structure for future rendering/production phases.

The manifest answers:

> "For each visual requirement in this Visual Plan, which actual asset file has been explicitly selected and fulfilled?"

This task does NOT perform video rendering.

---

# IMPORTANT ARCHITECTURAL BOUNDARY

Part 8 is a bridge between:

```text
Asset Management
        ↓
Asset Fulfillment
        ↓
Asset Readiness
        ↓
PRODUCTION ASSET MANIFEST   ← THIS PART
        ↓
Future Video Production
        ↓
FFmpeg / Rendering
```

Do not implement FFmpeg in this task.

Do not implement:

* video rendering
* transcoding
* trimming
* cropping
* compositing
* subtitle rendering
* audio mixing
* thumbnail generation
* publishing
* automatic asset selection

---

# MANDATORY FIRST STEP — INSPECT REPOSITORY

Before coding, inspect:

## Models

* Asset
* AssetRequirement
* VisualPlanItem
* VisualPlan
* ScriptVersion
* ContentProject

## Services

* AssetService
* AssetRequirementService
* AssetRequirementFulfillmentService
* AssetCompatibilityEvaluator
* AssetFulfillmentQualityService
* VisualPlanService
* VisualPlanQualityService

## Resources

* AssetResource
* AssetRequirementResource
* AssetRequirementCandidateResource
* AssetFulfillmentQualityResource
* VisualPlan resources

## Existing APIs

Inspect all routes related to:

* assets
* asset requirements
* candidate assets
* selected assets
* fulfilled requirements
* visual plan quality
* asset readiness

## Frontend

Inspect:

* VisualPlanPanel
* RequirementAssetsSection
* Asset Library
* existing readiness card
* visual plan types
* asset requirement types
* asset service
* visual plan service

## Tests

Inspect:

* Part 6 fulfillment tests
* Part 7 readiness tests
* candidate association tests
* cross-project security tests
* asset preview tests

Do not modify code before understanding the current architecture.

---

# PHASE 1 — DEFINE MANIFEST SEMANTICS

The manifest is computed from existing domain data.

It is NOT a new source of truth.

The existing domain remains:

```text
AssetRequirement
    ↓
selected_asset_id
    ↓
Asset
```

The manifest is simply a production-facing projection.

Do NOT introduce a database table unless inspection proves that the existing architecture absolutely requires persistence.

Prefer a read-only computed service.

Suggested service:

```text
AssetProductionManifestService
```

Adapt the exact name if the repository has a stronger naming convention.

---

# PHASE 2 — MANIFEST ELIGIBILITY

An AssetRequirement can appear as a valid production manifest entry only when:

1. The requirement belongs to the requested Visual Plan.
2. The requirement has a selected asset.
3. The selected asset exists.
4. The selected asset belongs to the same ContentProject.
5. The selected asset is still associated with the requirement.
6. The requirement status is `fulfilled`.
7. The selected asset has a physical file attached.
8. The selected asset passes the existing `AssetCompatibilityEvaluator`.
9. No compatibility blocker exists.

Do NOT duplicate compatibility rules.

Use:

```text
AssetCompatibilityEvaluator
```

as the single source of truth.

---

# PHASE 3 — MANIFEST MUST BE READ-ONLY

The manifest service must not:

* select assets
* clear selections
* fulfill requirements
* reopen requirements
* modify metadata
* analyze files
* modify asset status
* create candidates
* modify VisualPlanItems
* mutate database state

A GET request must remain read-only.

Do not perform automatic repairs.

If invalid data exists, report it.

---

# PHASE 4 — MANIFEST STRUCTURE

Inspect existing API resource conventions and create a structured representation.

Conceptually, each manifest entry should contain enough information for future production code to identify:

```text
VisualPlanItem
    ↓
AssetRequirement
    ↓
Selected Asset
    ↓
Private Asset File
```

A conceptual entry may contain:

```json
{
    "visual_plan_item_id": 12,
    "asset_requirement_id": 44,
    "requirement_type": "video",
    "asset_id": 87,
    "asset_type": "video",
    "file_name": "cat-walking.mp4",
    "mime_type": "video/mp4",
    "duration_seconds": 8,
    "width": 1080,
    "height": 1920,
    "aspect_ratio": 0.5625,
    "target_duration_seconds": 5,
    "status": "fulfilled"
}
```

This is conceptual only.

Use the project's existing Resource/DTO conventions.

---

# SECURITY RULE

Never expose:

```text
file_path
```

or:

```text
storage/app/private/...
```

or any internal filesystem path.

The manifest may expose a safe production identifier such as:

```text
asset_id
```

and existing metadata.

Future production services can resolve the actual private file internally through `AssetService` / storage services.

Do not generate public URLs.

Do not call `Storage::url()`.

Do not create `storage:link`.

---

# PHASE 5 — MANIFEST ORDER

Manifest entries must have deterministic ordering.

The primary order should follow the existing VisualPlanItem order.

Within the same VisualPlanItem, use the existing AssetRequirement ordering/conventions.

Do not sort by:

* asset ID
* filename
* creation date

unless that is already the repository's established ordering.

Inspect current model relationships and database columns before deciding.

Document the ordering rule.

---

# PHASE 6 — REQUIREMENTS WITH MULTIPLE CANDIDATES

A requirement can have multiple candidates.

The manifest must contain ONLY the explicitly selected asset.

Example:

```text
Requirement
├── Candidate A
├── Candidate B
└── Candidate C ← selected
```

Manifest:

```text
Candidate C
```

Do not include:

* Candidate A
* Candidate B

Candidate association is not production selection.

---

# PHASE 7 — UNFULFILLED REQUIREMENTS

The manifest must NOT silently omit invalid requirements and report success.

The response should clearly distinguish:

### Complete Manifest

All requirements are validly fulfilled.

### Incomplete Manifest

One or more requirements are:

* unfulfilled
* missing selection
* missing asset
* missing file
* incompatible
* no longer candidate
* otherwise invalid

Use structured issue codes.

Do not invent a completely separate compatibility system.

---

# PHASE 8 — MANIFEST RESULT

Create a structured result with concepts such as:

```text
ready
entries
summary
issues
```

Possible summary:

```json
{
    "total_requirements": 5,
    "valid_entries": 4,
    "invalid_requirements": 1
}
```

Possible result:

```json
{
    "ready": false,
    "summary": {
        "total_requirements": 5,
        "valid_entries": 4,
        "invalid_requirements": 1
    },
    "entries": [...],
    "issues": [...]
}
```

Adapt this to existing project quality/resource conventions.

Do not create a second unrelated quality scoring system.

The manifest should answer completeness, not provide a subjective score.

---

# PHASE 9 — IMPORTANT DISTINCTION FROM PART 7

Part 7 answers:

> "Are the assets fulfilled and valid?"

Part 8 answers:

> "What exact assets would production consume?"

Therefore:

```text
Asset Fulfillment Readiness
        ↓
Production Asset Manifest
```

Part 7 can expose:

```text
5 / 5 fulfilled
```

Part 8 can expose:

```text
Scene 1 → Asset 21
Scene 2 → Asset 34
Scene 3 → Asset 52
Scene 4 → Asset 77
Scene 5 → Asset 81
```

Do not merge these concepts.

---

# PHASE 10 — API

Add a read-only endpoint following existing route conventions.

Conceptually:

```text
GET
/api/v1/projects/{project}/script/versions/{version}/visual-plan/asset-manifest
```

Inspect existing routes and adapt the exact route structure to the repository.

The endpoint must:

* require authentication
* resolve project
* resolve script version
* resolve visual plan
* enforce project scope
* return structured manifest
* perform no mutation

Cross-project resources must not leak.

Missing visual plan behavior must follow existing Visual Plan API conventions.

Do not create a global manifest endpoint.

---

# PHASE 11 — FRONTEND

Add a lightweight **Production Asset Manifest** section to the existing Visual Plan interface.

Do NOT create a separate large page.

The UI should show:

```text
Production Asset Manifest

Status:
✓ Ready
or
✗ Incomplete

5 / 5 requirements fulfilled

Scene 1
├── Requirement: Cat walking
└── Asset: cat-walking.mp4

Scene 2
├── Requirement: Cat close-up
└── Asset: cat-closeup.mp4
```

For incomplete manifests:

```text
Production Asset Manifest

✗ Incomplete

4 / 5 requirements ready

Scene 5
└── Asset requirement has no valid fulfilled asset
```

The UI must not offer:

* automatic selection
* automatic fulfillment
* rendering
* export
* publish

A future production phase will consume this information.

---

# PHASE 12 — FRONTEND DATA FLOW

Reuse existing services where possible.

Prefer:

```text
visualPlanService.getAssetManifest(...)
```

rather than creating a completely separate API abstraction.

Reuse:

* existing loading states
* existing error handling
* existing issue display patterns
* existing badges/cards

Do not create duplicate asset requirement fetching logic.

---

# PHASE 13 — FUTURE PRODUCTION CONTRACT

The manifest should be designed as a stable boundary for future production.

Future production code should conceptually be able to consume:

```text
ProductionAssetManifest
        ↓
Production Timeline
        ↓
FFmpeg
```

But Part 8 must NOT implement those later stages.

Keep the manifest provider-agnostic.

Do not include:

* FFmpeg commands
* shell commands
* crop filters
* scale filters
* transition instructions
* subtitle instructions
* audio mixing instructions

Those belong to later production phases.

---

# PHASE 14 — NO FILE PROCESSING

Manifest generation must not:

* inspect media using FFprobe
* calculate dimensions
* analyze files
* read file contents
* generate thumbnails
* create previews

Use technical metadata already stored by Part 5.

The manifest is a domain projection, not a media-analysis service.

---

# PHASE 15 — QUERY DISCIPLINE

Avoid N+1 queries.

The service should efficiently load:

* VisualPlanItems
* AssetRequirements
* selectedAsset
* candidate association data required to verify selection
* necessary asset metadata

Do not load the entire Asset Library.

Do not query each requirement individually if eager loading can safely solve it.

If necessary, inspect query counts with existing testing conventions.

Do not optimize prematurely at the expense of project scoping.

---

# PHASE 16 — SECURITY / SCOPING

Test all of these:

### Same user, different projects

Project A must never expose Project B assets.

### Different user

Follow existing authorization conventions.

### Cross-project selected asset

Must result in an invalid manifest issue, not leaked asset metadata.

### Selected asset no longer candidate

Must be invalid.

### Selected asset deleted

Must be invalid.

### Asset has no file

Must be invalid.

### Asset incompatible

Must be invalid.

### Private path

Must never be returned.

### Public URL

Must never be generated.

---

# PHASE 17 — TESTS

Create comprehensive tests.

## Unit tests

Test:

* Empty Visual Plan.
* No AssetRequirements.
* One valid fulfilled requirement.
* Multiple valid requirements.
* Multiple candidates with one selected candidate.
* Selected candidate is the only manifest entry.
* Unfulfilled requirement.
* Fulfilled requirement without selection.
* Selected asset missing.
* Selected asset no longer candidate.
* Selected asset belongs to another project.
* Selected asset has no file.
* Selected asset incompatible.
* Multiple invalid requirements.
* Deterministic ordering.
* Read-only behavior.

## Feature/API tests

Test:

* authenticated request
* unauthenticated request
* correct project scope
* cross-project isolation
* missing project
* missing script version
* missing visual plan
* correct response serialization
* no `file_path`
* no internal storage path
* no public asset URL

## Regression tests

Existing tests must remain green for:

* Asset CRUD
* Asset upload
* Asset preview
* Metadata
* Candidate association
* Selection
* Fulfillment
* Asset readiness
* Visual Plan quality
* Script quality
* Research quality

---

# PHASE 18 — MUTATION CHECKS

Perform at least these mutation checks.

## Mutation A

Remove project scoping from the manifest query.

Expected:

Cross-project security tests fail.

Restore.

## Mutation B

Include all candidate assets rather than only selected asset.

Expected:

Manifest selection test fails.

Restore.

## Mutation C

Treat selected-but-unfulfilled as a valid manifest entry.

Expected:

Fulfillment-state test fails.

Restore.

## Mutation D

Expose `file_path`.

Expected:

Security/API test fails.

Restore.

---

# PHASE 19 — DOCUMENTATION

Create:

```text
docs/PHASE_4_PART_8.md
```

Document:

## Purpose

What the Production Asset Manifest represents.

## State Flow

```text
Candidate
    ↓
Selected
    ↓
Fulfilled
    ↓
Asset Readiness
    ↓
Production Asset Manifest
    ↓
Future Production
```

## Manifest Contract

Document the fields exposed.

## Ordering

Document how manifest entries are ordered.

## Security

Document why private paths are not exposed.

## API

Document the endpoint.

## Readiness

Explain:

```text
ready = all requirements have valid production entries
```

## Limitations

Explicitly state that this task does NOT implement:

* rendering
* FFmpeg
* transcoding
* trimming
* cropping
* subtitles
* audio mixing
* thumbnails
* publishing
* automatic asset selection
* AI matching

---

# PHASE 20 — VERIFICATION

Run:

```bash
php artisan test
```

Then:

```bash
npx tsc --noEmit
```

Then:

```bash
npm run build
```

Then:

```bash
vendor/bin/pint --dirty --format agent
```

Also run:

* PHP syntax checks on changed PHP files.
* Debug sweep:

  * `dd(`
  * `dump(`
  * `ray(`
* Security sweep.
* Check accidental private path exposure.
* Check accidental public URL generation.

If a migration is introduced:

```text
migrate
rollback
migrate
```

must be tested.

Do not claim success unless commands actually passed.

Do not fix unrelated pre-existing violations unless necessary.

---

# PHASE 21 — GIT

Before committing:

```bash
git status
git diff
git diff --stat
```

Review all changes.

Do not include unrelated files.

Create one focused commit.

Suggested message:

```text
feat: add production asset manifest
```

Do NOT push.

---

# FINAL REPORT

Report:

1. Implementation summary.
2. Backend files created/modified.
3. Frontend files created/modified.
4. API endpoint.
5. Manifest structure.
6. Readiness rules.
7. Security/scoping behavior.
8. Tests executed and exact results.
9. TypeScript result.
10. Build result.
11. Pint result.
12. Migration verification if applicable.
13. Debug/security sweep.
14. Commit hash.
15. Git working-tree status.
16. Known limitations.

Explicitly finish with:

```text
Phase 4 Part 8 completed.
Phase 4 Part 9 NOT started.
```

---

# HARD STOP

After completing Part 8:

STOP.

Do not start Part 9.

Do not implement:

* FFmpeg
* video rendering
* transcoding
* timeline rendering
* subtitles
* audio mixing
* thumbnails
* publishing

Wait for the next instruction.

---

# WORKING PRINCIPLES

* Inspect before modifying.
* Keep manifest read-only.
* Reuse existing domain services.
* Do not duplicate compatibility logic.
* Candidate ≠ Selected.
* Selected ≠ Fulfilled.
* Fulfilled ≠ Manifest Ready unless all validation passes.
* Manifest ≠ Rendered Video.
* No automatic asset selection.
* No AI matching.
* No external network.
* No media analysis.
* No public asset URLs.
* No private path exposure.
* Preserve project scoping.
* Preserve existing behavior.
* Test invalid states.
* Never claim a command passed unless actually executed.
* Do not push.
* Stop after Part 8.
