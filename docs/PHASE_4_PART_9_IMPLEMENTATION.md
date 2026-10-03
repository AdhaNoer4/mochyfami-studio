# Phase 4 — Part 9: Production Handoff Readiness (Implementation)

This document describes the implementation of **Production Handoff Readiness**.
It is a completion record, not the original task brief. The task brief remains at
`docs/PHASE_4_PART_9.md`.

## 1. Purpose

Production Handoff Readiness is the single decision point that says whether a
Visual Plan has everything a future production phase will need. It answers
exactly one question:

> Taken together, do planning readiness, asset readiness, and the production
> asset manifest agree that this Visual Plan is ready to hand off?

It does **not** render video, run FFmpeg/FFprobe, transcode, trim, mix audio,
generate subtitles or thumbnails, publish, search, match, select, fulfill,
repair, transition a status, or mutate any row. It is a composition of the
layers that already answer those questions, not a new one.

## 2. State Flow

```text
Planning Readiness        Part 6 gate
        |
Asset Readiness           Part 7 gate
        |
Production Asset Manifest Part 8 projection
        |
        v
Production Handoff Readiness   <-- this part
        |
        v
Future Production (rendering, FFmpeg, ...)
```

The handoff exists so that later production work has one authoritative "go / no
go" answer instead of re-deriving it from three separate payloads.

## 3. Architecture

```text
AssetProductionHandoffService (Part 9)
        |
        +--> VisualPlanQualityService::evaluateVisualPlan()      (planning)
        |
        +--> AssetFulfillmentQualityService::evaluateVisualPlan() (asset readiness)
        |
        +--> AssetProductionManifestService::build()             (manifest)
```

Part 9 owns **no** validation rules. It calls the three authorities that already
exist and copies their output. This is deliberate: if the handoff implemented
its own compatibility or structural checks, it could disagree with the layers it
summarizes. A test asserts that every issue code in the handoff already exists in
one of the upstream results and that the counts match, so a re-implemented rule
would be caught.

None of the upstream services (`VisualPlanQualityService`,
`AssetFulfillmentQualityService`, `AssetProductionManifestService`,
`AssetRequirementProductionValidator`, `AssetCompatibilityEvaluator`, or
`AssetRequirementFulfillmentService`) were modified.

## 4. Eligibility

The handoff is `ready` only when **all** of the following hold:

1. Planning readiness reports `ready`.
2. Asset readiness reports `ready`.
3. The production asset manifest reports `ready`.
4. There is at least one valid manifest entry (`production_entries > 0`).
5. No blocker exists in any layer (`total_blockers === 0`).

Any unmet condition yields `ready: false`. An empty Visual Plan (no requirements)
is **not** ready; the layers report their usual blockers and the handoff reports
`ready: false` rather than a vacuous `true`.

## 5. Handoff Contract

Result shape (in `data` of the API envelope):

```json
{
  "visual_plan_id": 12,
  "script_version_id": 4,
  "status": "draft",
  "ready": false,
  "summary": {
    "planning_blockers": 0,
    "asset_blockers": 1,
    "manifest_blockers": 1,
    "total_blockers": 2,
    "total_warnings": 1,
    "production_entries": 0
  },
  "issues": [
    {
      "source": "asset_readiness",
      "code": "REQUIREMENT_NOT_FULFILLED",
      "severity": "blocker",
      "message": "Requirement is not fulfilled.",
      "visual_plan_item_id": 12,
      "asset_requirement_id": 45
    }
  ]
}
```

### Sources

Every issue is a verbatim copy of an upstream blocker or warning, tagged with the
layer it came from:

* `planning` — from `VisualPlanQualityService`.
* `asset_readiness` — from `AssetFulfillmentQualityService`.
* `production_manifest` — from `AssetProductionManifestService`.

Info-severity issues are deliberately excluded: the handoff reports what blocks
production and what a future phase should watch, not passing checks.

### Issue codes

No new codes are introduced. The full set is exactly the union of the upstream
codes from Parts 6, 7, and 8, and each issue keeps its original `code`,
`severity`, `message`, `visual_plan_item_id`, and `asset_requirement_id` (plus
`selected_asset_id` and `field` when the upstream issue carries them).

## 6. Ordering

Issues are aggregated in a stable source order, and within each source the
upstream ordering is preserved:

1. `planning` — blockers, then warnings.
2. `asset_readiness` — blockers, then warnings.
3. `production_manifest` — issues exactly as produced.

Two runs over the same data produce the same list.

## 7. Security

* The response contains no `file_path`, private filesystem path, public URL, or
  `Storage::url()` output.
* Issues carry only metadata the upstream layers already exposed.
* The endpoint is authenticated and scoped to the project, script version, and
  visual plan. A plan belonging to another project returns `404`.
* Cross-project selected assets are reported by the underlying layers, but the
  foreign asset's metadata never appears in the response body.

## 8. API

```text
GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/production-handoff
```

* Auth required.
* Resolves project → script version → visual plan, enforcing project scope.
* Missing project / version / plan returns `404`.
* Read-only; performs no mutation and enqueues no work.

Controller: `App\Http\Controllers\Api\V1\AssetProductionHandoffController`.
Resource: `App\Http\Resources\AssetProductionHandoffResource`.

## 9. Service

`App\Services\Assets\AssetProductionHandoffService::evaluate(VisualPlan $plan)`.

Query discipline: the service loads `scriptVersion.script`, ordered `items`,
ordered `items.assetRequirements`, each `selectedAsset`, and the candidate
`assets` relation once, then wires the loaded item and plan models into each
requirement. Because the upstream services use `loadMissing()`, preloading with
explicit ordering fixes the order for all three and prevents any of them from
lazy-loading per requirement. A test asserts the handoff stays at or below ten
queries with five requirements.

Preloading matters beyond performance here: `AssetFulfillmentQualityService`
would otherwise run before the manifest and, without the inverse relations wired
up, issue a query per requirement while the manifest later loads with an order.
Doing it first makes the manifest's ordering the ordering everyone sees.

## 10. Readiness

```text
ready = planning.ready
    AND assets.ready
    AND manifest.ready
    AND production_entries > 0
    AND total_blockers = 0
```

There is deliberately no score: the handoff answers a go / no-go question, not a
subjective quality question.

## 11. Frontend

* `visualPlanService.getProductionHandoff(projectId, version)`.
* Types `ProductionHandoffSource`, `ProductionHandoffIssue`,
  `ProductionHandoffSummary`, `ProductionHandoffResult`.
* A **Production Handoff** card in `VisualPlanPanel`, placed after the
  Production Asset Manifest card. It shows a `READY` / `BLOCKED` badge, the
  production entry, blocker, and warning counts, and the aggregated issues. It
  offers no rendering, export, or publish actions.

## 12. Limitations

This part explicitly does **not** implement:

* video rendering
* FFmpeg / FFprobe
* transcoding, trimming, cropping, compositing
* subtitle rendering
* audio mixing
* thumbnail generation
* publishing or export
* automatic asset selection, automatic fulfillment, or AI matching
* media analysis or network requests

Rendering and the timeline remain a later phase that will consume this handoff
decision.

## 13. Verification

* PHPUnit: `1301` tests, `4070` assertions — all passing.
* Part 6/7/8 regression tests: green (no upstream service was modified).
* `npx tsc --noEmit`: clean.
* `npm run build`: successful.
* `vendor/bin/pint --dirty --format agent`: clean.
* PHP syntax checks on all changed PHP files: clean.
* Debug sweep (`dd(`, `dump(`, `ray(`): clean.
* Security sweep: no `file_path`, private path, `Storage::url()`, or public URL.
* Mutation checks (each confirmed to fail the relevant test, then restored):
  * A — dropped planning blockers from the aggregate → planning blocker test
    failed.
  * B — made `ready` unconditional → empty-plan test failed.
  * C — removed the `source` tag → ordering test errored on the missing key.
  * D — removed the inverse-relation hydration → query-count test failed
    (16 queries for five requirements).
* No migration was introduced, so no migrate/rollback cycle was required.
