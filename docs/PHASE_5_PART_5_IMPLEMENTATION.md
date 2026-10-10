# Phase 5 — Part 5: TTS Audio UI (Implementation)

This document describes the implementation of the **TTS Audio UI**. It is a
completion record, not the original task brief. The task brief remains at
`docs/PHASE_5_PART_5.md` and was not modified, following the `_IMPLEMENTATION`
suffix precedent from Parts 2, 3 and 4.

## 1. Summary

Part 5 adds an **Audio tab** to `ProjectDetailPage`, alongside the existing
Research, Script, Visual Plan and Assets tabs. It consumes only the endpoints
built in Parts 2–4 and never calls a TTS provider or writes a track from the UI.

The panel is a projection of the backend's own decisions:

- The **readiness summary** renders `GET .../tts-readiness` verbatim (ready,
  blockers, warnings, info counts, provider availability).
- The **item list** renders visual plan items in the backend's canonical order
  (`order ASC`, enforced by the `VisualPlan::items()` relationship default) and
  derives each item's audio status from `GET .../tts-manifest`
  (`audio.available`, current `tts_track_id`, segment metadata). No readiness
  rule is re-implemented in React.
- **Generate / regenerate / archive** are explicit user actions backed by
  `POST .../items/{item}/tts`, `POST .../items/{item}/tts/{track}/regenerate`,
  and `DELETE .../items/{item}/tts/{track}`. Every mutation writes a fresh track
  row on the backend; the UI stores nothing about tracks itself and refreshes
  the affected data only after the server confirms success.
- The **manifest section** is a collapsible read-only projection of
  `GET .../tts-manifest` (info counts, issues, segment table) with no re-created
  manifest and no file paths.
- **Playback** uses the existing authenticated preview route `assets.preview`
  through `assetService.fetchPreview(projectId, assetId)` (bearer token + blob +
  object URL, exactly like the Asset Library). `preview_url` from the API is
  used only as a capability flag, never rendered as a URL.

## 2. Git state before the change

- Branch: `main`
- HEAD: `5ab9664 feat: add TTS production manifest`
- Tracked tree: clean (no tracked modifications before this change).
- Untracked user-owned files (left untouched, never staged):
  `docs/PHASE_5_PART_2.md`, `docs/PHASE_5_PART_3.md`,
  `docs/PHASE_5_PART_4.md`, `docs/PHASE_5_PART_5.md`.
- Baseline test checkpoint: **1.608 backend tests / 5.039 assertions**
  (verified by a full run after implementation: `php artisan test --compact`
  → `1.608 passed, 5.039 assertions`).

## 3. Files changed

New frontend files:

| File | Purpose |
|---|---|
| `resources/js/types/index.ts` (modified) | Appends the TTS contract types: `TtsTrack`, `TtsTrackList`, `TtsGeneratePayload`, `TtsIssue`, `TtsReadiness`, `TtsManifest`, `TtsManifestItem`, `TtsManifestAudio`, `TtsManifestInfo`, `TtsTrackStatus`, `TtsProviderInfo`, `TtsReadinessInfo`. Field names mirror the PHP resources exactly (verified from source). |
| `resources/js/services/ttsService.ts` | Single central API service for the TTS endpoints so no component builds TTS URLs. Includes `readTtsErrorMessage` mapping 401/403/404/422/502/network/unexpected to Indonesian, user-actionable messages. |
| `resources/js/lib/ttsLabels.ts` | Display-only Indonesian labels for backend statuses and issue codes. Unknown codes fall back to the backend message, never a blank. |
| `resources/js/components/projects/AudioPanel.tsx` | Panel container: load lifecycle (script → version → readiness/manifest/items), guarded refresh after mutations, reload button, single-flight action guard, error/success banners, and modal wiring. |
| `resources/js/components/projects/AudioItemRow.tsx` | One visual plan item: collapsed status derived from the manifest + lazily expanded track history with player and per-track actions. |
| `resources/js/components/projects/AudioManifestSection.tsx` | Collapsible read-only manifest table and issue lists. |
| `resources/js/components/projects/AudioGenerateModal.tsx` | Shared form for generate and regenerate (voice required, speed 0.5–2.0, optional language). Regenerate mode states the new-track/kept-history contract. |
| `resources/js/components/projects/AudioConfirmDialog.tsx` | Generic confirmation dialog used for archive, stating the file is kept. |
| `resources/js/components/projects/AudioTrackPlayer.tsx` | Plays one track through the authenticated preview route with object-URL revoke lifecycle, mirroring the existing `AssetPreview` component. |
| `resources/js/pages/projects/ProjectDetailPage.tsx` (modified) | Adds `'audio'` to the tab union, an Audio tab button, and the panel render branch. No routing or pipeline changes. |

No backend PHP or database files were touched.

## 4. Endpoint integration (verified from source)

| Endpoint | Used in | Purpose |
|---|---|---|
| `GET /projects/{p}/script/versions/{v}/visual-plan/items/{item}/tts` | `ttsService.listTracks` | Track history (oldest first) + `current_narration_hash`; eager-loads `asset` so `preview_url`/`asset_id` are present. |
| `POST .../items/{item}/tts` | `ttsService.generate` | Explicit generate. Payload: `voice` (required), `speed`, `language`. |
| `POST .../items/{item}/tts/{track}/regenerate` | `ttsService.regenerate` | New track from current narration; old track untouched. |
| `DELETE .../items/{item}/tts/{track}` | `ttsService.archive` | Archive (keeps asset + file); double-archive → 422 with server message. |
| `GET .../tts-readiness` | `ttsService.getReadiness` | Source of truth for ready/blockers/warnings/info. |
| `GET .../tts-manifest` | `ttsService.getManifest` | Source of truth for per-item audio availability and segment metadata. |
| `GET /projects/{p}/assets/{asset}/preview` (`assets.preview`) | `assetService.fetchPreview` (existing) | Authorized playback bytes for a completed track's audio asset. |

Response envelope is the existing `{success, data, message}` via the API
client (`lib/api.ts`), same as every other panel.

## 5. Behavior: generate, regenerate, archive, readiness, manifest

- **Readyness**: summary card shows "Siap"/"Belum siap", red blocker list,
  amber warning list, and the `info` grid (total items, ready items, items
  without current audio, completed tracks) plus provider name/availability.
  All values come from the endpoint; nothing is computed client-side. Empty
  states are explicit: no script, no version, or no visual plan
  (`visual_plan_id === null`).
- **Generate** only after an explicit click: opens the generate modal (voice
  required locally; backend stays authoritative), and disabled when the
  narration is empty or the provider is reported unavailable. A single in-flight
  `pendingAction` disables every action button, and the modal's submit button
  shows a spinner. Success message appears only after the server confirms; then
  `refreshAfterMutation` re-reads that item's history, readiness, and manifest.
- **Regenerate** opens the same modal in regenerate mode; the explanation states
  a new track is created and history is kept, satisfying the confirmation
  requirement. No optimistic replacement; history updated only after `201`.
- **Archive** requires a confirm dialog that states the file stays in the
  library and remains playable from history. Uses the DELETE endpoint only.
  422 (already archived) is surfaced via the server message through
  `readTtsErrorMessage`.
- **Manifest** is shown as a collapsible `<details>` section: `info` counts,
  top-level issues, and the segment table (order, narration hash, availability,
  provider/voice, duration, mime type) following the exact response order. No
  `file_path`, storage key, or private URL is rendered.

## 6. Playback status

**Supported**, using an existing authenticated/authorized mechanism — no new
endpoint was created.

- Evidence: `resources/js/services/assetService.ts:139` `fetchPreview` fetches
  `GET /projects/{project}/assets/{asset}/preview` with the authenticated API
  client (`Bearer` token, `withCredentials`), returns a blob object URL, and
  `revokeAssetPreview` frees it (same pattern as the Asset Library's
  `AssetPreview`).
- The TTS audio asset belongs to the project (`content_project_id = project`)
  and `assets.preview` authorizes through the asset policy, so playback of a
  completed track is scoped to the correct project.
- `preview_url` from the track resource is treated as a capability flag only
  (`Boolean(track.preview_url && track.asset_id)`); bytes always come from
  `fetchPreview`, so no private URL is ever stored or shared in the DOM.
- The player is only mounted for tracks that carry an asset file
  (completed tracks; archived tracks keep their file and remain playable).
  Loading and failure states are handled; a failed load shows an actionable
  message, never a stack trace.
- Pending/generating/failed tracks never render a player (`asset_id` and
  `preview_url` are null there).

## 7. Error handling and security review

- Handled: initial loading, empty states, refresh, success, `422`, `401`,
  `404`, `502`, network, and unexpected errors via `readTtsErrorMessage`
  (network → "connection/unknown" message; `422` passes the server's own
  message through).
- No stack trace, secret, private path, or raw exception is ever rendered; the
  backend's only displayed text is the hand-written `message` string.
- A read failure keeps whatever data is already on screen; it never clears the
  panel or leaves a permanent loading state (`finally` guarantees setters reset).
- Race conditions on project/version switch are guarded by an `active` flag in
  the mount effect, so a slow response from an old project can't overwrite the
  current one.
- Buttons are disabled while a request is in flight; single-flight
  `pendingAction` prevents double-submit from any row.
- Static scan of all new files found **no `file_path`, storage key,
  `Storage::url`, or private-URL usage**, and `preview_url` is only a boolean
  guard (see section 10).

## 8. Tests and commands actually run

| Check | Command | Result |
|---|---|---|
| TypeScript check | `npx tsc --noEmit` | **PASS** (exit 0) |
| Production build | `npm run build` | **PASS** (exit 0; 1,993 modules; pre-existing chunk-size warning only) |
| Backend full suite | `php artisan test --compact` | **PASS** — 1,608 tests / 5,039 assertions |
| Whitespace/conflict check | `git diff --check` | **PASS** (empty) |

### Frontend tests

There is **no frontend test runner** in this repository. `package.json` exposes
only `build` and `dev` (no Vitest/Jest/testing-library, no test scripts, no
test config files). Per brief §10.1, no heavy dependency was added; the
limitation is reported here and static interaction/security checks (§10) were
performed instead. The UI cannot be claimed to be covered by automated tests.

## 9. Test/check results (actual)

- Backend: `1.608` tests passed, `5.039` assertions, `0` failures (matches the
  baseline exactly — no regression).
- TypeScript: clean.
- Production build: success. The only diagnostic is Vite's chunk-size advisory,
  which pre-dates this change.
- `git diff --check`: clean.

## 10. Interaction and security checks (static evidence)

Because no browser automation or frontend test runner exists, these were
verified by code inspection and grep, and are reported as such (not as runtime
proof):

1. **Generate only via user action** — all calls to `ttsService.generate`
   live inside the `submitGenerate` handler reachable only through the modal's
   submit button; the mount effect performs only GETs
   (`getScript`/`getReadiness`/`getManifest`/`listItems`/`listTracks`).
2. **No double-submit** — `pendingAction` is set synchronously before a request
   and every action button receives `disabled={pendingAction !== null}`.
3. **Regenerate creates a new track** — the UI calls the regenerate endpoint and
   never mutates/removes an existing track optimistically; history refreshes
   only after the `201` response.
4. **Archive does not delete the file** — the DELETE endpoint is the documented
   "archive" action (`TtsTrackController::destroy` → `ttsService->archive`,
   which keeps the asset and its file); the confirm dialog states this.
5. **Readiness/manifest refreshed after a successful mutation** —
   `refreshAfterMutation` re-reads the item's history plus readiness and
   manifest after the awaited action resolves.
6. **Errors do not leave permanent loading** — `runAction`, the load effect,
   and `fetchHistory` all reset their loading flags in `finally`.
7. **No file path or private URL exposure** — grep of all new files for
   `file_path`, `Storage::url`, `storage`: 0 matches. `preview_url` appears only
   as a boolean guard; audio bytes come from `assetService.fetchPreview`.
8. **No invented playback endpoint** — playback reuses the existing
   `assets.preview` route through `fetchPreview`; no new backend endpoint.
9. **No TTS provider call from read-only flows** — the only TTS calls are the
   three user-triggered mutations (verified by grep); all load-time requests are
   GETs.

## 11. Schema/backend change verification

- `git status` shows changes only in `resources/js/**` and
  `docs/PHASE_5_PART_5_IMPLEMENTATION.md`; no migrations, no `app/` PHP files,
  no `routes/`, no `config/` changes.
- The full backend suite still matches the baseline (section 8), confirming the
  Parts 2–4 TTS contract is intact.

## 12. Git state after the change

- One commit created for Part 5: message `feat: add TTS audio UI` (hash
  reported to the user in the final report).
- Untracked user-owned files (`docs/PHASE_5_PART_*.md`) were not staged.
- No push to remote.

## 13. Known limitations

- No automated frontend tests (no runner exists; §10.1 limitation).
- Playback and interaction behavior were verified statically, not through a
  browser; a human browser pass is recommended.
- Provider/model are not offered in the generate form by design: the backend
  resolves the registered default provider, and a bad provider name would be a
  502. Voice is a free-text field because the backend publishes no voice
  catalog.