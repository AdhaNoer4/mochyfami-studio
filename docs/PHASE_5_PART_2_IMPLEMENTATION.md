# Phase 5 — Part 2: TTS Synthesis and Storage API (Implementation)

This document describes the implementation of the **TTS Synthesis and Storage API**.
It is a completion record, not the original task brief. The task brief remains at
`docs/PHASE_5_PART_2.md` and was not modified.

## 1. Scope

Part 2 turns a visual plan item's narration into a real audio file, stored as an
ordinary private asset on the project's library, and records every attempt as a
`TtsTrack`. It exposes four endpoints (list, generate, regenerate, archive) and
guards the asset pipeline so a synthesized asset can never become an asset
requirement candidate or selection.

It does **not** ship a real TTS provider, a queue/job worker, a readiness
manifest, an audio panel, frontend code, or a public status endpoint: those
belong to later parts (provider attachments, Phase 6). Synthesis runs
synchronously through the `TtsProvider` contract from Part 1.

## 2. Architecture

```text
TtsTrackController (Api/V1)
  -> TtsGenerationService (App\Services\TTS)
       -> TtsProviderRegistry -> TtsProvider::synthesize(TtsRequest) -> TtsResult
       -> AssetService::createAsset           (AssetType::Audio, source 'tts')
       -> AssetFileStorageService::replace    (uploaded-file boundary)
       -> AssetMetadataService::analyse       (measured duration, ffprobe)
  -> TtsTrackResource -> ApiResponse envelope
Guard: AssetRequirementService::attachAsset() and
       AssetRequirementFulfillmentService::select() refuse isTtsGenerated() assets.
```

Every track is history, not state: each generate/regenerate request writes a new
row, nothing is overwritten, and snapshots carry the narration byte for byte.

## 3. API

Base: `/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts`

| Method | Path | Purpose | Success | Errors |
|---|---|---|---|---|
| GET | `/tts` | history, oldest first | 200 | 404 |
| POST | `/tts` | synthesize + store | 201 | 422, 404, 502, 500 |
| POST | `/tts/{track}/regenerate` | new track from current narration | 201 | 422, 404, 502, 500 |
| DELETE | `/tts/{track}` | archive (keeps asset + file) | 200 | 422, 404, 500 |

All endpoints are auth-gated (`sanctum`, project ownership via the plan + asset
policies). The list is `view`; generate/regenerate are `update` + asset
`create`; archive is `update`. Responses use the `{success,data,message,errors}`
envelope.

Mapping: `InvalidTtsNarrationException` → 422, missing item/track → 404,
`TtsProviderException` → 502 (safe message only, never the provider's own text),
unexpected → 500 (`report()`). No track/asset is written for an unusable
narration (empty or over the DTO's 5000-character limit) or an unknown provider.

## 4. Storage & Data

- `tts_tracks` rows are created through `$project->ttsTracks()->create(...)`:
  `content_project_id` is not fillable, so it can only ever come from the project
  the request is scoped to.
- The asset is `AssetType::Audio`, private on the assets disk, stored via the
  normal upload boundary (`UploadedFile`) so every asset rule applies. Title
  `TTS-generated audio`, source `tts`. The WAV stream of a `TtsResult` is
  written to a temp file first, then `replace()`d, then measured.
- `duration_seconds` comes from the **measurement** (`AssetMetadataService`),
  never from the provider's claim; it stays `null` when the file cannot be
  measured (asset keeps its own failed `metadata_status` for retry via Analyze).
- `error` only ever carries the safe constant `TTS synthesis failed.`
- Archive keeps the asset, its file, and the row's provenance; `asset_id` is
  preserved so the asset can still be previewed.
- Regenerate synthesizes the item's **current** narration into a new track; the
  referenced track is used only for scoping (a foreign track is a 404) and is
  never modified.

## 5. TTS Asset Guard

`Asset::isTtsGenerated()` is true when the asset is the audio of some `tts_tracks`
row. `AssetRequirementService::attachAsset()` and
`AssetRequirementFulfillmentService::select()` throw
`TtsAssetRequirementCandidateException` (→ 422) before any pivot or selection is
written. Ordinary audio uploads are unaffected.

## 6. Tests

- `tests/Feature/TtsGenerationTest.php` — 21 tests: auth 401s, list (+ staleness
  and current hash, empty and populated), validation (`voice`, `speed` range),
  empty narration refused without leaving a track, full store pipeline (asset
  created, exact narration bytes, file stored, `isTtsGenerated`), measured vs
  provider duration, null duration on measurement failure, provider throw → 502 +
  failed track + safe error + no leftover asset, unknown provider → 502, foreign
  project/item → 404, no `file_path` in the resource + preview URL, regenerate
  (new track, old untouched), archive (keeps asset + file, double-archive 422,
  foreign/unknown 404).
- `tests/Feature/TtsAssetExclusionTest.php` — 4 tests: attach TTS asset → 422,
  select TTS asset → 422, ordinary audio attach → 201, generated asset via the
  real pipeline → attach 422.
- Existing `TtsTrackModelTest` (Part 1) still passes.

## 7. Verification

- `php artisan test`: **1533 passed / 4726 assertions** (baseline 1508/4627).
  One pre-existing unit test flakes (duplicate faker `content_categories` slug);
  it passes in isolation and was not caused by this change.
- `vendor/bin/pint --test`: clean apart from two pre-existing, unrelated files
  (`Auth/LoginRequest.php`, `Traits/ApiResponse.php`) that were already failing
  before this part.
- `npx tsc --noEmit`: no errors.
- `npm run build`: succeeds (chunk-size warning only, pre-existing).

## 8. Mutation Testing

Eight mutations, each restored byte-exact after being killed:

| # | Mutation | Killed by |
|---|---|---|
| 1 | Remove TTS guard in `attachAsset` | `test_attach_rejects_a_tts_generated_asset_with_422` (201 vs 422) |
| 2 | Remove TTS guard in `select` | `test_select_rejects_a_tts_generated_asset_with_422` |
| 3 | `AssetType::Audio` → `Image` | store test (500 vs 201) |
| 4 | Stop persisting `asset_id` | store test (`asset_id` null) |
| 5 | Regenerate snapshots old narration | regenerate test (snapshot mismatch) |
| 6 | Archive deletes asset + file | archive test (`data.asset_id` null) |
| 7 | Unscoped item lookup | foreign-project index/store tests (200/201 vs 404) |
| 8 | Duration from provider claim | measurement test (1 vs 7) |

Two foreign-scope tests were strengthened so the 404 comes from item scoping
rather than a missing plan on the first project; both kill mutation 7.

## 9. Security

- Provider error messages are never stored or returned; only the safe constant
  is written and only a generic 502 message is sent.
- `content_project_id` and `asset_id` are never client-supplied; scope is always
  resolved through the project chain, making cross-project writes unreachable
  (verified by mutation 7).
- No `file_path` is ever exposed; the preview URL is a signed asset route.
- Archive is the only destructive verb and it does not delete data.

## 10. Scope Check

- No migration, no config change, no composer/npm dependency change.
- Part 1 files (providers, registry, `TtsRequest`/`TtsResult`, `TtsTrack`,
  `config/tts.php`) are untouched.
- Phase 4 services/policies were not modified except the two requirement guards.
- The task brief `docs/PHASE_5_PART_2.md` was not modified.

## 11. Files Changed

Modified:
- `app/Models/VisualPlanItem.php` (`ttsTracks()` relation)
- `app/Models/Asset.php` (`ttsTracks()`, `isTtsGenerated()`)
- `app/Models/TtsTrack.php` (`transitionTo()`)
- `app/Services/AssetRequirementService.php` (attach guard)
- `app/Services/Assets/AssetRequirementFulfillmentService.php` (select guard)
- `app/Http/Controllers/Api/V1/VisualPlanAssetRequirementController.php`
  (422 handling for the guard exceptions)
- `routes/api.php` (4 TTS routes)

Added:
- `app/Exceptions/InvalidTtsNarrationException.php`
- `app/Exceptions/InvalidTtsTrackStatusTransitionException.php`
- `app/Exceptions/TtsAssetRequirementCandidateException.php`
- `app/Http/Controllers/Api/V1/TtsTrackController.php`
- `app/Http/Requests/TTS/GenerateTtsRequest.php`
- `app/Http/Resources/TtsTrackResource.php`
- `app/Services/TTS/TtsGenerationService.php`
- `tests/Feature/TtsGenerationTest.php`
- `tests/Feature/TtsAssetExclusionTest.php`
- `docs/PHASE_5_PART_2_IMPLEMENTATION.md`