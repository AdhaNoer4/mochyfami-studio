# Phase 5 — Part 4: TTS Production Manifest (Implementation)

This document describes the implementation of the **TTS Production Manifest**.
It is a completion record, not the original task brief. The task brief remains at
`docs/PHASE_5_PART_4.md` and was not modified, following the `_IMPLEMENTATION`
suffix precedent from Parts 2 and 3.

## 1. Scope

Part 4 adds a single read-only endpoint that projects one script version's visual
plan into a production manifest: `GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-manifest`.

It is the read-only data contract for future production work (Phase 6 audio
handoff). It never synthesizes audio, never writes a track or asset, never scans
the filesystem, and never returns file paths or internal storage locations.

Every readiness and current-track decision is delegated to
`TtsReadinessService` so the manifest and the Part 3 readiness report can never
disagree: the manifest reuses the exact single pass and only reshapes it into the
segment contract.

## 2. Semantics

`ready`, top-level `blockers`, and top-level `warnings` are byte-for-byte the
Part 3 readiness projection (identical `ready = blockers === []`). A per-item
entry carries the *scoped subset* of that item's blockers/warnings; global and
provider issues stay top-level only. `info` mirrors the Part 3 counts where the
concepts line up:

| Key | Meaning |
|---|---|
| `ready` | identical to the Part 3 readiness `ready` for the same state |
| `script_version_id` | the version's own id; when the plan is absent it is resolved through the same project-scoped script chain |
| `visual_plan_id` | the plan's id, `null` when no plan exists |
| `items` | **all** items, ordered by `visual_plan_item.order ASC` (+ `id` tie-break), one entry per item |
| `blockers` / `warnings` | the Part 3 flattened lists, verbatim |
| `info.total_items` | number of items |
| `info.items_with_audio` | items whose selected current track exists (`audio.available === true`) |
| `info.items_without_audio` | `total_items - items_with_audio` |

Per-item shape:

```json
{
  "order": 1,
  "visual_plan_item_id": 1,
  "narration": { "hash": "sha256-of-exact-current-text" },
  "audio": {
    "available": true,
    "tts_track_id": 1,
    "asset_id": 1,
    "duration_seconds": 5,
    "mime_type": "audio/wav",
    "provider": "fake",
    "model": null,
    "voice": "neutral",
    "language": "id",
    "speed": 1
  },
  "blockers": [],
  "warnings": []
}
```

Decision rules:

- `audio.available` mirrors the readiness per-item ready flag exactly: true iff
  `currentUsableTrack` (latest `Completed` track whose `narration_hash` matches
  the item's current narration) exists. Failed/archived/stale tracks are never
  available, identical to Part 3.
- Verified-only fields (`duration_seconds`, `mime_type`) are populated only when
  the asset is verified — the same predicate Part 3 uses for
  `METADATA_NOT_VERIFIED` (`asset !== null && metadata_status === Completed && duration_seconds !== null`). Otherwise they are `null` and the entry stays
  available (the warning surfaces it).
- `narration.hash` is always the current narration hash
  (`NarrationHasher::hash((string) $item->narration_text)`), even for a
  no-audio item.
- `speed` is exposed as a float; `provider`/`voice` come from the selected track.
- `file_size` and any storage path are deliberately **not** exposed.

Missing/edge states (all 200): no visual plan → `NO_VISUAL_PLAN` blocker,
`visual_plan_id: null`, `items: []`; empty plan → `NO_VISUAL_PLAN_ITEMS` blocker;
unknown default provider → `PROVIDER_UNAVAILABLE` blocker (never a 500). A
missing or foreign script version is a 404, exactly like Parts 2/3.

## 3. Architecture

```text
TtsManifestController (Api/V1)
  -> Gate::authorize('view', $plan ?? $project)      (open policy like readiness)
  -> VisualPlanService::getPlan()                    (missing/foreign version -> 404)
  -> TtsManifestService::build(project, version)
       -> TtsReadinessService::evaluateWithItems()   (the SAME single pass as readiness)
       -> manifestItem() reshapes each projected item (no rule re-implementation)
       -> resolveScriptVersionId() fallback for the no-plan branch (script chain)
  -> TtsManifestResource -> ApiResponse envelope     ({success, data: {...}, message})
```

`TtsReadinessService::evaluate()` now delegates to a private `buildReport()`;
the new `evaluateWithItems()` exposes the same projection plus per-item
`usable`/`narration_hash`/scoped lists. Both callers share one code path, so the
manifest and readiness cannot diverge. The manifest service holds no provider
reference and never calls `synthesize`; provider availability is resolved only as
a registry probe inside the readiness pass.

## 4. API

`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-manifest`

- 401 unauthenticated; 404 missing/foreign project-version; every manifest
  outcome (including missing plan) is 200.
- Response envelope is the shared `ApiResponse::successResponse`
  (`{success, data, message}`).
- Deterministic: identical persisted state + config produce an identical
  manifest (issue ordering, item ordering, and payload) across repeated calls.

```json
{
  "success": true,
  "data": {
    "ready": true,
    "script_version_id": 1,
    "visual_plan_id": 1,
    "items": [
      {
        "order": 1,
        "visual_plan_item_id": 1,
        "narration": {"hash": "3c0d..."},
        "audio": {"available": true, "tts_track_id": 1, "asset_id": 1, "duration_seconds": 5, "mime_type": "audio/wav", "provider": "fake", "model": null, "voice": "neutral", "language": "id", "speed": 1},
        "blockers": [],
        "warnings": []
      }
    ],
    "blockers": [],
    "warnings": [],
    "info": {"total_items": 1, "items_with_audio": 1, "items_without_audio": 0}
  },
  "message": null
}
```

## 5. Tests

- `tests/Feature/TtsManifestServiceTest.php` — 23 tests. Every §8 track-selection
  scenario as a manifest outcome: missing/empty plan; no-history warning; fresh
  matching completed → available with verified fields; stale completed never
  selected; newer stale narration does not supersede a matching track; newest
  failed does not hide an older matching completed; failed-current blocker;
  archived-only never available; matching track wins over archived history;
  newest-of-multiple matching selected; missing-asset / unverified-asset (all
  verified fields stay null, still available); exact-duration no-warning;
  duration-mismatch and long-narration warn without blocking; empty narration
  blocker; **explicit-order ordering**; mixed readiness info counts; unknown
  provider blocks manifest but keeps item segments; cross-project isolation;
  manifest agrees with readiness for the same state; deterministic, read-only,
  never-synthesizes.
- `tests/Feature/TtsManifestApiTest.php` — 15 tests: 401; 404 missing project;
  404 foreign version; open-policy viewer 200; missing-plan 200 +
  `NO_VISUAL_PLAN` with `script_version_id` resolved; empty-plan 200 +
  `NO_VISUAL_PLAN_ITEMS`; ready full structure/segment fields; unknown provider →
  200 + `PROVIDER_UNAVAILABLE` (never 500); **no private data leak**
  (`file_path`/`file_name`/storage paths never appear); stale audio never
  reported current; manifest and readiness endpoints agree (ready, codes, info);
  **manifest scoped to the requested project's own version**; cross-project asset
  substitution/serialization guard; read-only + repeatable (DB row counts,
  asset fields, and filesystem file list unchanged); spy provider proving no
  `synthesize`.

Per user decision, `items[]` lists **all** plan items with an explicit
`audio.available` flag (not only usable tracks), so consumers can present gaps.

## 6. Verification

- `php artisan test`: **1608 passed / 5039 assertions** (Part 3 baseline
  1570/4857, +38 tests, +182 assertions).
- `vendor/bin/pint --dirty`: all new/changed files fixed. `pint --test`: still
  only the two pre-existing, unrelated files (`Auth/LoginRequest.php`,
  `Traits/ApiResponse.php`).
- `npx tsc --noEmit`: no errors.
- `npm run build`: succeeds (chunk-size warning only, pre-existing).
- `git diff --check`: clean.

## 7. Mutation Testing

Ten mutations were run; each non-survivor was killed by a targeted test and then
restored byte-exact (verified with `cmp`):

| # | Mutation | Killed by |
|---|---|---|
| 1 | Remove narration-hash condition (stale becomes usable) | `a_stale_completed_track_is_never_selected` |
| 2 | Failed track counts as usable | `a_failed_current_attempt_is_a_blocker_and_not_available` |
| 3 | Archived track counts as usable | `only_archived_tracks_are_never_available` |
| 4 | Manifest `ready` also gated on warnings (diverges from Part 3) | `an_item_without_any_track_is_not_available_with_a_warning` |
| 5 | Metadata always treated as verified | `an_unverified_asset_keeps_verified_fields_null` |
| 6 | Project/version scoping dropped in `resolveVersion` | `the_manifest_is_scoped_to_the_requested_projects_own_version` (added specifically) |
| 7 | `file_path` leaked into the audio segment | `the_manifest_never_leaks_private_asset_data` |
| 8 | Manifest calls `synthesize` | `the_manifest_never_invokes_synthesis` |
| 9 | DB write during manifest generation | `the_manifest_is_read_only_and_repeatable` |
| 10 | Item ordering made incidental/reversed | `items_are_ordered_by_the_explicit_plan_order` |

Notes on two partially-surviving variants, both of which drove a stronger guard:

- **Mutation 6**: the "drop scoping" variant alone is masked by the no-plan
  fallback in the manifest (`resolveScriptVersionId` legitimately 404s on a
  project with no script via the same scrambled branch), so the existing
  foreign-version test could not observe it. A dedicated scoping test — the
  *other* project's version is created first so an unscoped `where('version', 1)`
  resolves to it, while the requested project still has its own plan — was added.
  It kills the escape and is itself a stronger cross-project regression guard.
- **Mutation 10**: the "drop the explicit sort" variant is behavior-neutral on
  MySQL because the `unique(['visual_plan_id', 'order'])` composite index forces
  index-ordered scans (incidental order already equals `order ASC`). The test
  does discriminate real ordering regressions: the reversed-sort variant was
  killed (`items_are_ordered_by_the_explicit_plan_order` failed with `[3,2,1]`
  vs `[1,2,3]`). The explicit `sortBy(order, id)` remains as the documented
  ordering guarantee for other engines, and the composite index plus the
  relationship `->orderBy('order')` keep ordering deterministic regardless.

## 8. Security

- Strictly read-only: no track/asset writes and no `synthesize` anywhere in the
  manifest path (proven by mutations 8 and 9); repeated calls return identical
  responses without touching the filesystem (asserted file list unchanged).
- Project/version scope resolved through the project chain (proven by
  mutation 6); a foreign or missing version is a 404.
- No `file_path`, `file_name`, storage path, asset URL, or provider secret is
  returned (proven by mutation 7 and the no-leak API test).

## 9. Scope Check

- No migration, no config change, no composer/npm dependency change.
- Part 1/2/3 files are untouched except `TtsReadinessService` (read-only
  refactor exposing `evaluateWithItems()`; `evaluate()` output unchanged and
  still covered by all 27 Part 3 readiness tests).
- The task brief `docs/PHASE_5_PART_4.md` was not modified; untracked docs
  (`PHASE_5_PART_2.md`, `PHASE_5_PART_3.md`) were not staged or touched.

## 10. Files Changed

Modified:
- `routes/api.php` (1 route: `tts-manifest`)
- `app/Services/TTS/TtsReadinessService.php` (added `evaluateWithItems()`; single
  shared `buildReport()`; `evaluate()` unchanged)

Added:
- `app/Services/TTS/TtsManifestService.php`
- `app/Http/Controllers/Api/V1/TtsManifestController.php`
- `app/Http/Resources/TtsManifestResource.php`
- `tests/Feature/TtsManifestServiceTest.php`
- `tests/Feature/TtsManifestApiTest.php`
- `docs/PHASE_5_PART_4_IMPLEMENTATION.md`