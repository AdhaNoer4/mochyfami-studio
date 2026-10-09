# Phase 5 — Part 3: TTS Readiness (Implementation)

This document describes the implementation of the **TTS Readiness** report.
It is a completion record, not the original task brief. The task brief remains at
`docs/PHASE_5_PART_3.md` and was not modified. The brief itself is an untracked
working file, so this record uses the `_IMPLEMENTATION` suffix (matching the
Part 2 precedent) instead of a new section inside the brief.

## 1. Scope

Part 3 adds a single read-only endpoint that reports whether a script version's
visual plan has usable audio for every item's current narration, which blockers
prevent readiness, which warnings need attention, and whether the configured TTS
provider resolves from the registry.

It does **not** synthesize audio, does not write or archive tracks, does not
touch assets, and returns no preview or file paths. Readiness is a projection
computed on demand and never persisted.

## 2. Semantics

`ready` is true exactly when `blockers === []` (spec-literal). Warnings never
block. Every outcome is reporter-ordered deterministically.

| Code | Kind | Condition |
|---|---|---|
| `NO_VISUAL_PLAN` | blocker | the version has no visual plan yet (200, not 404) |
| `NO_VISUAL_PLAN_ITEMS` | blocker | the plan exists but has no items |
| `ITEM_WITHOUT_NARRATION` | blocker (per item) | narration is empty/whitespace; no other checks run |
| `TTS_GENERATION_FAILED` | blocker (per item) | a `Failed` track matches the current narration hash and nothing usable supersedes it |
| `PROVIDER_UNAVAILABLE` | blocker | the default provider does not resolve from the registry (always last) |
| `ITEM_WITHOUT_TRACK` | warning (per item) | no usable audio and no failed current attempt; never emitted when `TTS_GENERATION_FAILED` is |
| `NARRATION_CHANGED_SINCE_SYNTHESIS` | warning (per item) | a Completed/Failed/Archived track carries a different hash than the current narration and nothing usable supersedes it |
| `TRACK_DURATION_MISMATCH` | warning (per item) | both item and usable-track duration are non-null and not strictly equal; nothing is reported when comparison is impossible |
| `METADATA_NOT_VERIFIED` | warning (per item) | the usable track's asset is missing, or `metadata_status !== Completed`, or `duration_seconds` is null |
| `LONG_NARRATION` | warning (per item) | `ceil(words / 3) > 60` (180 words → none, 181 → warning) |

Usable audio (`currentUsableTrack`): the latest (`max id`) `Completed` track whose
`narration_hash` equals the hash of the item's exact current narration text.
Failed and archived tracks are never usable; a stale Completed track stays
invisible until its hash matches again. Tracks are history, not state — the same
fixed rules power Part 3 and will power the Phase 6 audio panel.

`info`: `total_items`, `ready_items` (items with usable current audio),
`items_without_current_audio` (= `total_items - ready_items`), `completed_tracks`
(count of Completed tracks across the version's items), and
`provider: {name, available}`. `available` means the default provider resolves:
resolution builds the registered provider object only and never calls
`synthesize`.

Ordering: blockers emit `NO_VISUAL_PLAN` → `NO_VISUAL_PLAN_ITEMS` → per-item
(`order`, then `id`) → `PROVIDER_UNAVAILABLE` last. Per-item warnings emit in the
fixed order `ITEM_WITHOUT_TRACK | TTS_GENERATION_FAILED` (mutually exclusive),
`NARRATION_CHANGED_SINCE_SYNTHESIS`, `TRACK_DURATION_MISMATCH`,
`METADATA_NOT_VERIFIED`, `LONG_NARRATION`.

## 3. Architecture

```text
TtsReadinessController (Api/V1)
  -> Gate::authorize('view', $plan ?? $project)     (open policy, 403 only if auth missing)
  -> VisualPlanService::getPlan()                   (missing version -> ModelNotFoundException -> 404)
  -> TtsReadinessService::evaluate(project, version) (read-only projection)
       -> TtsProviderRegistry::resolve()            (availability probe, no synthesis)
       -> NarrationHasher::hash()                   (§8 current-narration hash, same as Part 1/2)
  -> TtsReadinessResource -> ApiResponse envelope   ({success, data: {ready, blockers, warnings, info}, message})
```

Status mapping: unauthenticated → 401; version missing or not owned by the
project → 404; every readiness outcome (including a missing plan) → 200. The
contract mirrors the Part 2 generation flow: scope is resolved through the
project chain before any data is read, so a foreign project or version can never
influence a report.

## 4. API

`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-readiness`

200 response shape:

```json
{
  "success": true,
  "data": {
    "ready": true,
    "blockers": [],
    "warnings": [{"code": "...", "message": "...", "visual_plan_item_id": 1, "order": 1}],
    "info": {
      "total_items": 1,
      "ready_items": 0,
      "items_without_current_audio": 1,
      "completed_tracks": 0,
      "provider": {"name": "mock", "available": true}
    }
  },
  "message": "TTS readiness evaluated."
}
```

Item-level issues carry `visual_plan_item_id` and `order`; global and provider
issues carry only `code`/`message`. No `file_path`, `storage_path`, asset URL, or
internal secret ever appears in the response.

## 5. Tests

- `tests/Feature/TtsReadinessServiceTest.php` — 27 tests, every rule of §2,
  including: no-plan and empty-plan blockers, empty/whitespace narration,
  no-history warning-or-ready, matching completed → ready, stale completed
  invisible, failed-current blocker vs old failed + matching completed,
  newest-failed + older-matching, archived-only, exact/±1/null duration cases,
  metadata states (missing asset, failed metadata, verified), the 180/181
  `LONG_NARRATION` boundary, unknown-provider blocker + availability, deterministic
  ordering (all five blockers), cross-project isolation, read-only + repeatable
  identical calls, and a spy provider proving readiness never invokes
  `synthesize`.
- `tests/Feature/TtsReadinessApiTest.php` — 10 tests: 401 unauth; 404 missing
  project and 404 foreign-version; open-policy viewer 200; missing-plan 200 +
  `NO_VISUAL_PLAN`; empty-plan 200 + `NO_VISUAL_PLAN_ITEMS`; ready structure via
  `assertJsonStructure`; unknown provider → 200 + `PROVIDER_UNAVAILABLE` (never
  500); no private asset/`file_path` leak; read-only + identical double call.

## 6. Verification

- `php artisan test`: **1570 passed / 4857 assertions** (baseline 1533/4726,
  +37 tests, +131 assertions).
- `vendor/bin/pint --dirty`: all new/changed files fixed. `pint --test`: clean
  apart from the two pre-existing, unrelated files (`Auth/LoginRequest.php`,
  `Traits/ApiResponse.php`) already failing before this part.
- `npx tsc --noEmit`: no errors.
- `npm run build`: succeeds (chunk-size warning only, pre-existing).

## 7. Mutation Testing

Nine mutations, each killed by a targeted test and then restored byte-exact
(verified with `cmp`):

| # | Mutation | Killed by |
|---|---|---|
| 1 | Warnings also make `ready` false | `an_item_with_no_history_warns_not_blocks` |
| 2 | Remove narration-hash comparison (stale becomes usable) | `a_stale_completed_track_never_counts_as_usable_audio` |
| 3 | Failed track counts as usable | `a_failed_attempt_for_the_current_narration_is_a_blocker` |
| 4 | Archived track counts as usable | `archived_tracks_are_never_usable_audio` |
| 5 | Remove the exact duration mismatch check | `an_exact_duration_match_reports_no_mismatch` |
| 6 | `LONG_NARRATION` threshold becomes `>= 60` | `a_narration_of_exactly_180_words_is_within_the_guidance` |
| 7 | Unknown provider reported available | `an_unknown_provider_is_a_blocker_and_is_reported_unavailable` |
| 8 | Readiness calls `synthesize` (spy provider) | `readiness_never_invokes_synthesis` |
| 9 | Remove project/version scoping (foreign version resolves) | `a_version_from_another_project_returns_404` |

Mutation 8 isolated a real trap (the provider DTO lives in
`App\Services\TTS\DTO\TtsRequest`, not the `TTS` root) and confirmed the spy
proves the no-synthesis invariant. Mutation 9 temporarily removed the project
scope inside `VisualPlanService::resolveVersion()`; the foreign-version 404 test
killed it and the file was restored byte-exact.

## 8. Security

- The endpoint is strictly read-only: no track/asset writes, no synthesis, no
  `synthesize` call anywhere in the readiness path (proven by mutation 8).
- Project/version scope is resolved through the project chain; a foreign or
  missing version is a 404 and can never contribute data to a report (proven by
  mutation 9 and the cross-project service test).
- No `file_path`, storage path, or asset URL is ever returned; no provider
  secrets or error text leak from the availability probe.

## 9. Scope Check

- No migration, no config change, no composer/npm dependency change.
- Part 1/2 files (providers, registry, DTOs, `TtsTrack`, `config/tts.php`,
  generation service, asset guard) are untouched.
- Phase 4 services/policies were not modified; `VisualPlanService` and
  `ScriptService` (read path) are used as-is.
- The task brief `docs/PHASE_5_PART_3.md` was not modified, and the other
  untracked docs (`PHASE_5_PART_2.md`, `Plan.md`, `pre_phase_5.md`, `testing.md`,
  `PHASE_5_TTS.md`, `PHASE_5_REPO_DISCOVERY.md`) were not staged or touched.

## 10. Files Changed

Modified:
- `routes/api.php` (1 route: `tts-readiness`)

Added:
- `app/Services/TTS/TtsReadinessService.php`
- `app/Http/Controllers/Api/V1/TtsReadinessController.php`
- `app/Http/Resources/TtsReadinessResource.php`
- `tests/Feature/TtsReadinessServiceTest.php`
- `tests/Feature/TtsReadinessApiTest.php`
- `docs/PHASE_5_PART_3_IMPLEMENTATION.md`