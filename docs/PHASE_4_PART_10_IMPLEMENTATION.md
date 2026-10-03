# Phase 4 — Part 10: Production Specification (Implementation)

This document describes the implementation of the **Production Specification**.
It is a completion record, not the original task brief. The task brief remains at
`docs/PHASE_4_PART_10.md` and was not modified.

## 1. Scope

Part 10 adds the read-only **Production Specification**: a deterministic,
project/script-version/visual-plan-scoped projection of the ordered production
inputs a future timeline/render phase will consume. It is the positive
counterpart to the Part 9 handoff's go/no-go.

It answers exactly one question:

> In what order, and with what durations, will a future production phase consume
> the selected and fulfilled assets?

It does **not** render, run FFmpeg/FFprobe, transcode, trim, mix audio, generate
subtitles or thumbnails, publish, place items on a timeline, define offsets,
transitions, or render settings, select/fulfill/repair assets, transition a
status, or persist anything.

## 2. Roadmap Alignment

`docs/AI_AGENT_SPEC.md` places Timeline, Clips, Render presets, and FFmpeg in
**Phase 6 — Video Engine** (`:2377-2392`), and reserves
`GET /api/v1/projects/{project}/production` for that phase (`:1276`). Phase 4 is
the Asset Manager. Part 10 therefore defines a domain-level contract
(`ProductionSpecification`) that Phase 5/6 will consume, rather than a
provider-coupled renderer or a timeline. This matches the ladder in
`docs/PHASE_4_PART_10.md:370-379` and its instruction not to introduce timeline
concepts automatically (`:225-256`).

## 3. Architecture

```text
AssetProductionHandoffService (Part 9)   -> ready + issues (copied verbatim)
        +
AssetProductionManifestService (Part 8)  -> valid entries (reshaped)
        +
VisualPlanItem.duration_seconds          -> planned duration
        v
AssetProductionSpecificationService (Part 10)   [read-only, composes only]
        v
AssetProductionSpecificationResource
        v
AssetProductionSpecificationController -> route
```

Part 10 owns no validation rules and introduces no issue codes. It copies the
handoff verdict and issues unchanged and reshapes the manifest entries. A test
asserts the handoff issues are reproduced exactly, so a re-implemented rule
would be caught. No Parts 6–9 service, model, policy, or migration was modified.

## 4. Contract

Result shape (in `data` of the API envelope):

```json
{
  "visual_plan_id": 12,
  "script_version_id": 4,
  "status": "draft",
  "ready": true,
  "specification": {
    "segment_count": 1,
    "total_target_duration_seconds": 10,
    "total_planned_duration_seconds": 5
  },
  "segments": [
    {
      "visual_plan_item_id": 12,
      "visual_plan_item_order": 1,
      "asset_requirement_id": 44,
      "requirement_type": "video",
      "asset_id": 87,
      "asset_type": "video",
      "duration_seconds": 10,
      "target_duration_seconds": 10,
      "planned_duration_seconds": 5
    }
  ],
  "issues": []
}
```

* `segments` are exactly the manifest's valid entries, enriched with the owning
  item's `planned_duration_seconds`. Ids, types, and durations only — never a
  file path or URL.
* `ready` and `issues` are copied from `AssetProductionHandoffService::evaluate()`
  (sources `planning`, `asset_readiness`, `production_manifest` preserved).
* Envelope totals are sums of the segment values; they make no claim and enforce
  no new rule.

### Determinism

Segments inherit the manifest ordering: `VisualPlanItem.order` ascending, then
`AssetRequirement.id` ascending. The envelope is a sum over that ordered list.

## 5. Security

* No `file_path`, `storage_path`, `absolute_path`, `private_path`, or
  `Storage::url()` output; asserted by an API test.
* The endpoint is authenticated and scoped through
  `VisualPlanService::getPlanOrFail()` plus `Gate::authorize('view', $plan)`.
  A plan belonging to another project returns `404`.
* Cross-project selected assets are already excluded upstream and their metadata
  never reaches a segment.

## 6. API

```text
GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/production-spec
```

* Auth required; project-scoped; missing project/version/plan returns `404`.
* Read-only; no request payload; no mutation.
* Controller: `App\Http\Controllers\Api\V1\AssetProductionSpecificationController`.
* Resource: `App\Http\Resources\AssetProductionSpecificationResource`.
* Route registered at `routes/api.php` immediately after `production-handoff`.

The top-level `GET /api/v1/projects/{project}/production` path is deliberately
left untouched for Phase 6.

## 7. Frontend

* `visualPlanService.getProductionSpecification(projectId, version)`.
* Types `ProductionSpecificationSegment`, `ProductionSpecificationSummary`,
  `ProductionSpecificationResult`.
* A **Production Specification** card in `VisualPlanPanel`, placed after the
  Production Handoff card. It shows a `READY` / `BLOCKED` badge, the segment,
  target-duration, and planned-duration totals, the ordered segments, and the
  aggregated issues. Read-only; no render/export/publish actions.

## 8. Data Model

```
Migration required:        NO
New model required:        NO
Existing model changes:    NO
```

Everything required already exists: `VisualPlanItem.order` and
`duration_seconds`, `AssetRequirement.target_duration_seconds`, and the asset
measured fields surfaced by the manifest. Timeline, offsets, transitions, and
render configuration are absent by design and belong to Phase 6.

## 9. Performance

`AssetProductionSpecificationService::evaluate()` runs the handoff first, which
force-loads every relation the composed authorities need with the manifest
ordering and the inverse relations wired up; the manifest then sees them loaded.
A query-count test asserts the whole specification stays at or below ten queries
with five requirements (it currently issues six).

## 10. Tests

* `tests/Feature/AssetProductionSpecificationTest.php` (10 tests): empty plan;
  fully valid ordered specification; ordering by item order; unfulfilled
  requirement produces no segment; cross-project asset produces no segment and
  leaks nothing; warnings do not block; segments mirror the manifest;
  issues equal the handoff issues; read-only; query-count guard.
* `tests/Feature/AssetProductionSpecificationApiTest.php` (6 tests):
  authentication; 404 missing project; 404 cross-project plan; response
  structure; incomplete plan has no segments and tagged issues; no path/URL
  exposure.

## 11. Verification

* PHPUnit: `1317` tests, `4154` assertions — all passing.
* Phase 4 Parts 6–9 regression tests: green (no upstream change).
* `npx tsc --noEmit`: clean.
* `npm run build`: successful.
* `vendor/bin/pint --dirty --format agent`: clean.
* PHP syntax checks on all changed PHP files: clean.
* Debug sweep: clean (only the known `toArray(` false positive).
* Security sweep: no `file_path`, private path, `Storage::url()`, or URL.
* Mutation checks (each confirmed to fail the relevant test, then restored
  byte-identical):
  * A — dropped all segments → fully-valid specification test failed.
  * B — made `ready` unconditional → empty-plan test failed.
  * C — reversed segment order → ordering test failed.
  * D — dropped issue pass-through → issues-equal-handoff test failed.
  * E — dropped planned-duration enrichment → fully-valid specification test
    failed.
* No migration was introduced, so no migrate/rollback cycle was required.
