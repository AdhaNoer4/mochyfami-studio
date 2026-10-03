# Phase 4 — Part 9
# Production Handoff Readiness

## 1. Purpose

Phase 4 Part 9 introduces a **read-only Production Handoff Readiness projection**.

Its purpose is to determine whether the current Visual Plan can be handed from the asset/planning stage to the future production/rendering pipeline.

Part 9 is a **handoff/readiness layer**, not a rendering implementation.

Intended pipeline:

```text
Content Project
→ Script Version
→ Visual Plan
→ Visual Plan Items
→ Asset Requirements
→ Candidate Assets
→ Selected / Fulfilled Assets
→ Production Asset Manifest
→ Production Handoff Readiness
→ Future Production / Rendering
```

Part 9 must sit **after Phase 4 Part 8**.

---

## 2. Architectural Boundary

Phase 4 separates several concerns:

- **Planning Readiness** — whether the Visual Plan is structurally usable.
- **Asset Readiness** — whether asset requirements have valid fulfilled assets.
- **Production Asset Manifest** — production-facing projection of explicitly selected, fulfilled, and valid assets.
- **Production Handoff Readiness** — combines those existing readiness layers into one handoff decision.

Conceptually:

```text
Planning Readiness
        +
Asset Readiness
        +
Production Asset Manifest
        =
Production Handoff Readiness
```

Part 9 must **consume existing authorities** rather than recreate their validation logic.

---

## 3. Existing Authorities

Part 9 MUST reuse these existing authorities where applicable:

### AssetCompatibilityEvaluator

`app/Services/Assets/AssetCompatibilityEvaluator.php`

Remains the technical compatibility authority. Part 9 must not implement another compatibility evaluator.

### AssetRequirementProductionValidator

Shared structural validator introduced during Part 8 and already shared by Part 7 and Part 8.

Part 9 must not duplicate its structural rules.

### AssetFulfillmentQualityService

Phase 4 Part 7 asset readiness gate.

Part 9 should consume its result rather than reproduce its asset-readiness rules.

### AssetProductionManifestService

Phase 4 Part 8 production-facing asset projection.

Part 9 must consume its result rather than rebuild the production asset manifest from raw database records.

---

## 4. Core Behavior

Create a read-only Production Handoff Readiness projection.

The result should expose:

```text
ready
summary
issues
```

No subjective score should be introduced.

Part 9 must not rank assets or recommend which asset should be selected.

---

## 5. Ready Semantics

Production Handoff Readiness is `ready = true` only when:

1. The Visual Plan exists and is accessible within the current project/version scope.
2. Planning readiness has no blockers.
3. Asset readiness has no blockers.
4. The Production Asset Manifest is ready.
5. The Production Asset Manifest contains at least one valid production entry.
6. No blocking issue remains in the aggregated handoff result.

An empty/no-requirement plan must **not** become ready through vacuous truth.

Existing upstream issue semantics must be preserved. Part 9 must not weaken an upstream blocker.

---

## 6. Issue Handling

Part 9 should expose a stable and deterministic issue list.

Where practical, identify the source layer:

- `planning`
- `asset_readiness`
- `production_manifest`

Part 9 may aggregate existing blockers and warnings from upstream services. It must not invent duplicate versions of existing validation rules.

Upstream blockers remain blockers.

Warnings remain warnings unless the originating upstream layer already treats them as blockers. Warnings alone should not force `ready = false`.

---

## 7. Summary

Expose deterministic summary information sufficient for the frontend to explain the handoff state.

At minimum support:

- planning blocker count
- asset blocker count
- manifest blocker count
- total blocker count
- total warning count
- total production entry count

Additional concise aggregate fields are acceptable when useful and non-sensitive.

No subjective quality score.

---

## 8. Read-Only Requirement

Part 9 MUST be read-only.

It must NOT:

- select or clear assets
- fulfill or reopen requirements
- modify visual plans/items
- modify assets
- change project/workflow status
- create render or production jobs
- queue FFmpeg/rendering
- download/upload assets
- call external services
- automatically repair invalid state

No database migration is expected for the intended projection-only implementation.

If implementation discovers that a migration is unavoidable, stop and document the reason before proceeding.

---

## 9. No Rendering or Production Execution

Part 9 MUST NOT introduce:

- FFmpeg
- FFprobe
- transcoding
- video/audio rendering
- subtitle generation
- thumbnail generation
- timeline generation
- export
- publishing

Those belong to later production phases.

Part 9 only determines whether the current state is ready for a future production pipeline.

---

## 10. API

Expose:

```text
GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/production-handoff
```

Requirements:

- `auth:sanctum`
- preserve project → script version → visual plan hierarchy
- existing authorization conventions
- cross-project access returns `404`
- no global/unscoped endpoint

The controller should remain thin and delegate computation to a dedicated service.

---

## 11. Backend Architecture

Expected shape:

```text
AssetProductionHandoffService
AssetProductionHandoffResource
AssetProductionHandoffController
```

Naming may follow existing repository conventions if equivalent.

The service should compose existing readiness/manifest services.

Do NOT create another copy of:

- AssetCompatibilityEvaluator logic
- AssetRequirementProductionValidator logic
- AssetFulfillmentQualityService logic
- AssetProductionManifestService logic

Part 9 is an orchestration/projection layer.

---

## 12. Frontend

Add:

- `visualPlanService.getProductionHandoff(...)`
- TypeScript types for the handoff result
- Production Handoff card/section in `VisualPlanPanel.tsx`

Clearly distinguish:

```text
READY
```

from:

```text
BLOCKED
```

and display relevant blocker/warning information.

The UI remains read-only. No mutation buttons.

Do not create a separate production page in Part 9.

Reuse existing visual patterns from Planning Readiness, Asset Readiness, and Production Asset Manifest.

---

## 13. Security and Data Exposure

Preserve existing project/version/visual-plan authorization and scoping.

Cross-project access must not reveal whether another project's resources exist; use the established `404` behavior.

The response MUST NOT expose:

- `file_path`
- private filesystem paths
- storage paths
- `Storage::url()`
- unrestricted/private asset URLs

Only safe production-facing metadata already appropriate for the readiness/manifest layers should be exposed.

---

## 14. Performance

Avoid N+1 queries.

Prefer consuming upstream projections instead of independently traversing:

```text
VisualPlan
→ Items
→ Requirements
→ Assets
```

again.

Do not load the entire asset library or scan unrelated projects.

If additional queries are necessary, keep them scoped to the current project/version/visual plan.

Add query-count protection where practical.

---

## 15. Determinism

The Production Handoff response must be deterministic.

Issue ordering and summary counts must be stable.

Production entry counts must come from the Production Asset Manifest rather than independently counting raw records.

Do not use random, timestamp-based, database-default, or otherwise unstable ordering.

When aggregating multiple issue sources, use a stable source order such as:

```text
planning
asset_readiness
production_manifest
```

and preserve upstream issue ordering within each source.

---

## 16. Suggested Response Shape

Follow existing Laravel Resource conventions, but conceptually:

```json
{
  "ready": true,
  "summary": {
    "planning_blockers": 0,
    "asset_blockers": 0,
    "manifest_blockers": 0,
    "total_blockers": 0,
    "total_warnings": 0,
    "production_entries": 3
  },
  "issues": []
}
```

Blocked example:

```json
{
  "ready": false,
  "summary": {
    "planning_blockers": 0,
    "asset_blockers": 1,
    "manifest_blockers": 1,
    "total_blockers": 2,
    "total_warnings": 0,
    "production_entries": 0
  },
  "issues": [
    {
      "source": "asset_readiness",
      "code": "...",
      "severity": "blocker",
      "message": "..."
    }
  ]
}
```

These examples are conceptual; follow existing resource and issue conventions during implementation.

---

## 17. Testing Requirements

Add focused service and API coverage.

### Service tests

At minimum verify:

1. Empty/no requirements → `ready = false`.
2. Planning blockers → `ready = false`.
3. Asset readiness blockers → `ready = false`.
4. Manifest not ready → `ready = false`.
5. Fully valid production handoff → `ready = true`.
6. Warnings without blockers → ready when upstream layers consider the state ready.
7. Production entry count comes from the manifest.
8. Issue aggregation is deterministic.
9. Summary counts are deterministic.
10. No duplicate validation logic is introduced.

### API tests

At minimum verify:

1. Unauthenticated request → `401`.
2. Same-project valid request → successful response.
3. Cross-project access → `404`.
4. Correct response structure.
5. Ready/blocked semantics.
6. No private file path leakage.
7. No `Storage::url()`.
8. Deterministic issue ordering.
9. Correct summary counts.

### Regression

Existing Phase 4 Part 7 and Part 8 tests must remain green.

---

## 18. Mutation / Negative Testing

Before declaring Part 9 complete, perform targeted negative checks.

The implementation should fail an appropriate test if intentionally changed to:

- ignore planning blockers
- ignore asset readiness blockers
- bypass manifest readiness
- treat empty requirements as ready
- weaken cross-project scoping
- expose `file_path` or a storage URL

Each mutation must be restored before final verification.

---

## 19. Files Expected to Remain Stable

Unless implementation proves a concrete need otherwise, do not modify:

```text
docs/PHASE_4_PART_8.md
docs/PHASE_4_PART_8_IMPLEMENTATION.md

app/Services/Assets/AssetCompatibilityEvaluator.php
app/Services/Assets/AssetRequirementProductionValidator.php
app/Services/Assets/AssetProductionManifestService.php
app/Http/Resources/AssetProductionManifestResource.php
app/Services/Assets/AssetFulfillmentQualityService.php
```

Part 7 and Part 8 authorities should remain stable.

If an unavoidable modification is discovered, document why before implementation.

Do not perform unrelated cleanup.

---

## 20. Documentation

This file is the official Phase 4 Part 9 specification.

Do not turn it into an implementation report.

After implementation, create:

```text
docs/PHASE_4_PART_9_IMPLEMENTATION.md
```

The implementation document should record:

- implementation summary
- architecture
- files created/modified
- API changes
- frontend changes
- tests added
- verification results
- mutation checks
- security checks
- commit hash
- known limitations

---

## 21. Explicit Scope Exclusions

The following are OUT OF SCOPE for Part 9:

- FFmpeg / FFprobe
- rendering / transcoding
- timeline or shot generation
- subtitles / TTS
- thumbnail generation
- publishing
- asset downloading/uploading
- automatic asset selection or fulfillment
- asset replacement
- asset tagging
- license workflow redesign
- new asset search providers
- production/render job queues
- render job tables
- production status mutations
- project status transitions
- external service calls
- AI generation

Part 9 is intentionally a read-only handoff/readiness projection.

---

## 22. Completion Criteria

Part 9 is complete only when:

- Production Handoff Readiness service exists.
- It composes existing readiness/manifest authorities.
- No duplicate compatibility/fulfillment/manifest validation exists.
- API endpoint exists and is properly scoped.
- Frontend displays the handoff state.
- Ready/blocked semantics are deterministic.
- Empty/no-requirement state is not incorrectly treated as ready.
- Cross-project access is rejected.
- Private paths/URLs are not exposed.
- No mutation is performed.
- No rendering/FFmpeg functionality is introduced.
- Focused tests pass.
- Full PHPUnit suite passes.
- TypeScript passes.
- Production build passes.
- Pint passes.
- PHP syntax checks pass.
- Debug/security sweeps pass.
- Mutation checks pass and are restored.
- Implementation documentation exists.
- One focused commit is created.
- No push is performed unless explicitly requested.

---

## 23. Implementation Sequence

Once implementation begins:

1. Re-inspect Part 7 and Part 8 services/tests.
2. Implement the Production Handoff service by composing existing authorities.
3. Add service tests.
4. Add Resource and API controller.
5. Add nested API route.
6. Add API/security/scoping tests.
7. Add frontend types and service method.
8. Add the Production Handoff card/section to `VisualPlanPanel`.
9. Run focused tests.
10. Run full PHPUnit.
11. Run TypeScript check.
12. Run production build.
13. Run Pint and PHP syntax checks.
14. Run debug/security sweeps.
15. Run mutation/negative checks and restore all mutations.
16. Create `docs/PHASE_4_PART_9_IMPLEMENTATION.md`.
17. Review the final diff for unrelated changes.
18. Create one focused commit.
19. Do NOT push.

---

## 24. Final Principle

Part 9 creates a clean boundary:

```text
Planning + Assets
        ↓
Production Handoff Readiness
        ↓
Future Production / Rendering
```

Part 9 should make the current state easy for a future production system to consume without becoming that production system itself.

> AI assists production. Human controls publication.
