# Phase 4 — Part 7: Asset Fulfillment & Production Readiness Gate Documentation

## 1. Purpose

The **Asset Fulfillment & Production Readiness Gate** evaluates whether a Visual Plan has sufficient, valid, and compatible fulfilled assets to proceed toward future video production.

It provides a deterministic, read-only quality evaluation mechanism that strictly separates:
- **Visual Planning Readiness**: Ensures the Visual Plan's structure, narration lines, prompts, and shot types are valid.
- **Asset Fulfillment Readiness**: Ensures every required visual item has a valid, compatible, selected asset that is marked fulfilled.
- **Future Production Rendering**: FFmpeg execution, audio mixing, subtitles, thumbnail generation, and final video rendering (reserved for later phases).

---

## 2. State Model

```text
Candidate Asset Association (Many-to-Many)
        ↓
Selected Asset (selected_asset_id)
        ↓
Fulfilled Requirement (status = fulfilled)
        ↓
Asset Fulfillment Readiness Evaluation (Read-Only Gate)
```

- **Candidate**: Asset associated with requirement as a potential option.
- **Selected**: Asset explicitly chosen for requirement by a human user.
- **Fulfilled**: Requirement status explicitly set to `fulfilled` after human verification.
- **Asset Readiness**: Evaluated quality score, summary, and issue list across the entire Visual Plan.

---

## 3. Difference from Planning Readiness

`VisualPlanQualityService` and `AssetFulfillmentQualityService` represent two distinct production gates:
1. **Planning Readiness** verifies that the Visual Plan itself is well-formed (prompts defined, durations specified, sections covered).
2. **Asset Readiness** verifies that the physical asset files exist, match required aspect ratio / duration, belong to the project, and are attached to fulfilled requirements.

Combining these two gates would hide structural planning problems under asset sourcing issues or vice versa. They remain separate independent metrics in both backend and frontend UI.

---

## 4. Readiness Rules & Stable Issue Codes

### Deterministic Blocker Codes (`severity = blocker`)

- `NO_ASSET_REQUIREMENTS`: The visual plan has no asset requirements defined.
- `REQUIREMENT_NOT_FULFILLED`: An asset requirement exists but has not been fulfilled.
- `REQUIREMENT_SELECTED_BUT_NOT_FULFILLED`: An asset requirement has a selected asset but its status remains `searching` or `pending`.
- `FULFILLED_REQUIREMENT_WITHOUT_SELECTION`: Requirement status is `fulfilled` but `selected_asset_id` is null.
- `SELECTED_ASSET_MISSING`: Selected asset no longer exists in the database.
- `SELECTED_ASSET_CROSS_PROJECT`: Selected asset belongs to a different project.
- `SELECTED_ASSET_HAS_NO_FILE`: Selected asset does not have a physical file attached.
- `SELECTED_ASSET_NOT_CANDIDATE`: Selected asset is no longer associated as a candidate for the requirement.
- `SELECTED_ASSET_INCOMPATIBLE`: Selected asset violates technical compatibility evaluated by `AssetCompatibilityEvaluator` (type mismatch, unverified metadata, ratio mismatch, duration too short).

### Deterministic Warning Codes (`severity = warning`)

- `DURATION_EXCEEDS_TARGET`: Selected asset is longer than target duration and will require trimming during video assembly.

---

## 5. API Specification

### Endpoint
`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/asset-readiness`

### Headers
`Authorization: Bearer <sanctum_token>`

### Response Payload Schema (`200 OK`)

```json
{
  "success": true,
  "data": {
    "visual_plan_id": 12,
    "script_version_id": 1,
    "status": "draft",
    "ready": false,
    "score": 60,
    "summary": {
      "total_requirements": 5,
      "fulfilled_requirements": 3,
      "unfulfilled_requirements": 2,
      "invalid_fulfillments": 0,
      "blocker_count": 2,
      "warning_count": 0
    },
    "items": [
      {
        "id": 101,
        "order": 1,
        "narration_text": "Cats purr to communicate...",
        "visual_prompt": "Close up cat face",
        "requirement_count": 1,
        "fulfilled_count": 1,
        "blockers": [],
        "ready": true
      }
    ],
    "blockers": [
      {
        "code": "REQUIREMENT_NOT_FULFILLED",
        "severity": "blocker",
        "message": "Asset requirement has not been fulfilled.",
        "visual_plan_item_id": 102,
        "asset_requirement_id": 202
      }
    ],
    "warnings": [],
    "issues": [...]
  },
  "message": null
}
```

---

## 6. Security & Scoping

- **Authentication**: Requires Sanctum bearer token (`auth:sanctum`).
- **Authorization**: Enforces `Gate::authorize('view', $plan)`.
- **Scope Isolation**: Route enforces `$project` -> `$version` -> `$visualPlan` hierarchy. Accessing a script version or visual plan from another project returns `404 Not Found`.
- **No Path Leakage**: Internal server storage paths and private storage URLs are never exposed in readiness payloads.

---

## 7. Verification & Test Coverage

### Automated Test Commands
```bash
# Run unit & feature test suite
vendor/bin/phpunit --filter=AssetFulfillmentQualityTest

# Run full test suite
php artisan test

# Check TypeScript types
cmd /c npx tsc --noEmit

# Build production assets
cmd /c npm run build

# Code formatting
vendor/bin/pint --dirty --format agent
```

### Verified Test Scenarios
- Empty plan / no requirements.
- Fully fulfilled plan (`ready = true`).
- Unfulfilled requirement (`searching` status).
- Selected asset with unfulfilled status.
- Fulfilled requirement missing selection (`FULFILLED_REQUIREMENT_WITHOUT_SELECTION`).
- Deleted or missing selected asset (`SELECTED_ASSET_MISSING`).
- Fileless selected asset (`SELECTED_ASSET_HAS_NO_FILE`).
- Cross-project selected asset (`SELECTED_ASSET_CROSS_PROJECT`).
- Incompatible selected asset (`SELECTED_ASSET_INCOMPATIBLE`).
- Unauthenticated API access (401).
- Cross-project API access (404).

---

## 8. Limitations

Phase 4 Part 7 explicitly does NOT implement:
- Automatic asset matching or auto-selection.
- AI semantic matching.
- Automatic requirement fulfillment.
- Video rendering or FFmpeg pipeline execution.
- Transcoding, audio mixing, subtitles, or thumbnail generation.
- Publishing or export workflows.
