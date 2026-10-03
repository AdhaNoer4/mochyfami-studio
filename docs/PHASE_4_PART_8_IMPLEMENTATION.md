# Phase 4 — Part 8: Production Asset Manifest (Implementation)

This document describes the implementation of the **Production Asset Manifest**.
It is a completion record, not the original task brief. The task brief remains at
`docs/PHASE_4_PART_8.md`.

## 1. Purpose

The Production Asset Manifest is a deterministic, read-only projection of the
assets that have been explicitly selected and fulfilled for a Visual Plan. It
answers exactly one production-facing question:

> For each visual requirement in this Visual Plan, which actual asset file has
> been explicitly selected and fulfilled?

It does **not** render video, does not run FFmpeg/FFprobe, does not analyze
files, does not search or match assets, and never mutates any row. It is the
stable boundary that future production work will consume.

## 2. State Flow

```text
Candidate
    |
    v
Selected              AssetRequirement.selected_asset_id
    |
    v
Fulfilled             status = fulfilled
    |
    v
Asset Readiness       Part 7 gate
    |
    v
Production Asset Manifest   <-- this part
    |
    v
Future Production (rendering, FFmpeg, ...)
```

Selection is not fulfillment, and fulfillment is not manifest readiness until
every structural and compatibility check passes.

## 3. Architecture

```text
AssetFulfillmentQualityService (Part 7)
        |
        v
AssetRequirementProductionValidator
        |
        v
AssetCompatibilityEvaluator

AssetProductionManifestService (Part 8)
        |
        v
AssetRequirementProductionValidator
        |
        v
AssetCompatibilityEvaluator
```

The shared structural rules live in
`app/Services/Assets/AssetRequirementProductionValidator.php`. Both Part 7 and
Part 8 call it, so the two cannot drift apart. Technical compatibility remains
the sole responsibility of `AssetCompatibilityEvaluator`.

`AssetFulfillmentQualityService` was refactored to delegate its former
`checkFulfilledClaim()` logic to the validator. Its public result shape, issue
codes, messages, and severity are unchanged; its regression tests stay green.

## 4. Eligibility

A requirement becomes one manifest entry only when all of the following hold:

1. It belongs to the requested Visual Plan.
2. Its status is `fulfilled`.
3. It has a `selected_asset_id`.
4. The selected asset still exists.
5. The selected asset belongs to the same ContentProject.
6. The selected asset is still a candidate for the requirement (when the
   requirement has any candidates at all).
7. The selected asset has a physical file attached.
8. `AssetCompatibilityEvaluator` reports no blocker.

Anything else yields a blocker issue and is omitted from `entries`.

## 5. Manifest Contract

Result shape (in `data` of the API envelope):

```json
{
  "visual_plan_id": 12,
  "script_version_id": 4,
  "status": "draft",
  "ready": false,
  "summary": {
    "total_requirements": 5,
    "valid_entries": 4,
    "invalid_requirements": 1
  },
  "entries": [
    {
      "visual_plan_item_id": 12,
      "visual_plan_item_order": 1,
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
  ],
  "issues": [
    {
      "code": "SELECTED_ASSET_HAS_NO_FILE",
      "severity": "blocker",
      "message": "Selected asset does not have an uploaded file.",
      "visual_plan_item_id": 12,
      "asset_requirement_id": 45,
      "selected_asset_id": 91
    }
  ]
}
```

### Issue codes

Structural codes come from the shared validator:

`REQUIREMENT_NOT_FULFILLED`, `REQUIREMENT_SELECTED_BUT_NOT_FULFILLED`,
`FULFILLED_REQUIREMENT_WITHOUT_SELECTION`, `SELECTED_ASSET_MISSING`,
`SELECTED_ASSET_CROSS_PROJECT`, `SELECTED_ASSET_HAS_NO_FILE`,
`SELECTED_ASSET_NOT_CANDIDATE`, `SELECTED_ASSET_INCOMPATIBLE`, plus
`NO_ASSET_REQUIREMENTS` for a plan with no requirements.

When a selection is incompatible, the individual evaluator issues
(`TYPE_MISMATCH`, `ASPECT_RATIO_MISMATCH`, `DURATION_TOO_SHORT`, and so on) are
included too. Non-blocking evaluator warnings (for example
`DURATION_EXCEEDS_TARGET`) are reported as `severity: "warning"` and do not
stop an entry.

## 6. Ordering

Entries are deterministic:

* Primary: `VisualPlanItem.order` ascending.
* Secondary: `AssetRequirement.id` ascending, because `AssetRequirement` has no
  order column.

Sorting is never by asset id, file name, or creation date.

## 7. Security

* `file_path` is never exposed, and neither are private filesystem paths.
* No public URL is generated, `Storage::url()` is never called, and
  `storage:link` is not created.
* `preview_url` is deliberately omitted; the manifest contract does not require
  it.
* The endpoint is authenticated and scoped to the project, script version, and
  visual plan. Cross-project selected assets produce an invalid entry and their
  metadata never appears in a valid entry or in the response body.
* A deleted selected asset makes the requirement invalid; nothing is silently
  repaired.

## 8. API

```text
GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/asset-manifest
```

* Auth required.
* Resolves project → script version → visual plan, enforcing project scope.
* Missing project / version / plan returns `404`.
* Read-only; performs no mutation.

Controller: `App\Http\Controllers\Api\V1\AssetProductionManifestController`.
Resource: `App\Http\Resources\AssetProductionManifestResource`.

## 9. Service

`App\Services\Assets\AssetProductionManifestService::build(VisualPlan $plan)`.

Query discipline: items, requirements (ordered), selected assets, and candidate
pivot are eager loaded, and the already loaded item/plan models are wired into
each requirement so the validator never lazy-loads them. The manifest is a small
fixed number of queries regardless of requirement count; a test asserts this.

## 10. Readiness

```text
ready = total_requirements > 0 AND invalid_requirements = 0
```

An empty Visual Plan (no requirements) is **not** ready and reports
`NO_ASSET_REQUIREMENTS`, matching Part 7's behavior. There is deliberately no
score: the manifest answers completeness, not a subjective quality question.

## 11. Frontend

* `visualPlanService.getAssetManifest(projectId, version)`.
* Types `AssetManifestEntry`, `AssetManifestSummary`,
  `AssetProductionManifestResult`.
* A **Production Asset Manifest** card in `VisualPlanPanel`, placed after the
  Asset Fulfillment Readiness card. It shows the ready/incomplete badge, entry
  and invalid counts, the selected file per scene, and any issues. It offers no
  selection, fulfillment, rendering, export, or publish actions.

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

Rendering and the timeline are a later phase that will consume this manifest.

## 13. Verification

* PHPUnit: `1282` tests, `3991` assertions — all passing.
* Part 7 regression tests: green after the validator extraction.
* `npx tsc --noEmit`: clean.
* `npm run build`: successful.
* `vendor/bin/pint --dirty --format agent`: clean.
* PHP syntax checks on all changed PHP files: clean.
* Debug sweep (`dd(`, `dump(`, `ray(`): clean.
* Security sweep: no `file_path`, private path, `Storage::url()`, or public URL.
* Mutation checks (each confirmed to fail the relevant test, then restored):
  * A — removed cross-project check → cross-project test failed.
  * B — emitted all candidates instead of the selected asset → selection test
    failed.
  * C — treated selected-but-unfulfilled as acceptable → failure-state test
    failed.
  * D — exposed `file_path` → security API test failed.
* No migration was introduced, so no migrate/rollback cycle was required.
