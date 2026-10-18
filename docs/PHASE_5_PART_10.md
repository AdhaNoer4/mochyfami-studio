# Phase 5 Part 10 — Manual Dubbing Backend API

## Project context

Mochyfami Studio is a Laravel + React/Vite/Tailwind application.

Phase 5 already includes automated TTS generation, TtsTrack history, private Asset storage, authenticated asset preview, readiness checks, production manifest, and TTS API resources.

Part 8 introduced:
- `TtsTrackSource` (`tts`, `manual`)
- `ManualDubbingApprovalStatus` (`draft`, `ready_for_review`, `approved`, `rejected`)
- `tts_tracks` metadata fields: `source`, `uploader_id`, `recorded_at`, `approval_status`, `approved_at`, `approved_by`, and `notes`
- `TtsTrack` enum casts, uploader/approver relationships, and manual factory state.

Part 9 isolated automated TTS:
- Readiness considers only `source = tts`.
- Manifest follows the same readiness evaluation.
- `Asset::isTtsGenerated()` only treats TTS-source tracks as TTS-generated assets.
- Automated TTS generation and resource contracts were intentionally preserved.

Treat prior reports as context, not proof of the current working tree. Inspect the actual code before editing.

## Objective

Implement the Laravel backend for manual audio dubbing:
1. Upload a manual audio file for a valid VisualPlanItem.
2. Create a new `TtsTrack` with `source = manual` and appropriate metadata.
3. Expose manual-track history separately from automated TTS history.
4. Implement an authorized approval workflow.
5. Preserve old tracks when a recording is replaced.
6. Reuse existing private Asset storage and authenticated preview infrastructure.

This part is backend/API only. Do not implement React UI or browser microphone recording.

## Step 1 — Read-only preflight

Before making changes:
- Inspect `git status --short`, branch, HEAD, tracked diffs, staged diffs, and untracked files.
- Read the Part 7 audit and Part 8/9 reports if available.
- Inspect the actual TtsTrack, TtsTrackSource, ManualDubbingApprovalStatus, Asset, VisualPlan, VisualPlanItem, ScriptVersion, User, asset storage service, audio inspection/metadata services, upload requests, policies, routes, and API resources.
- Inspect all usages of `TtsGenerationService::listTracks()` and `Asset::isTtsGenerated()`.
- Inspect existing route authentication and project-level authorization patterns.

Protect all user-owned files and changes. Do not restore, overwrite, delete, stage, or clean pre-existing files.

## Step 2 — Design decisions based on actual code

Before implementation, determine and document:
- How existing assets are created and associated with project ownership.
- Which supported audio MIME types, codecs, extensions, and size limits are configured.
- How duration and metadata are inspected.
- Which authenticated user model and authorization policy apply.
- Whether provider and voice can safely represent manual recordings without schema changes.

Prefer a minimal, additive implementation. Do not make provider/voice nullable or add a migration unless the actual model, validation, or persistence contract proves it is necessary. If a schema change is genuinely required, explain the evidence and keep it additive and backward-compatible.

## Step 3 — Manual dubbing API

Follow the project's route naming, API versioning, authentication, request validation, and resource conventions.

Proposed endpoints, adapt only if necessary to match existing conventions:

- `GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/manual-dubbing`
  - List manual tracks for the item, newest first.
- `POST /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/manual-dubbing`
  - Upload an audio file and create a new manual track.
- `PATCH /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/manual-dubbing/{track}/submit`
  - Submit a draft for review.
- `PATCH .../{track}/approve`
  - Approve a submitted manual track.
- `PATCH .../{track}/reject`
  - Reject a submitted manual track with a reason if the domain requires one.
- `PATCH .../{track}/return-to-draft`
  - Optional; implement only if a genuine workflow need exists and it can be authorized safely.

Do not add redundant routes just to match the proposal. Reuse the project's established REST/action route style where appropriate.

All route-model relationships must be scoped and verified:
- The project belongs to the authenticated user's permitted scope.
- The ScriptVersion belongs to the project.
- The VisualPlan belongs to the correct version.
- The VisualPlanItem belongs to the correct VisualPlan.
- The TtsTrack belongs to the given item and has `source = manual`.

Reject mismatched or unauthorized nested resources without exposing unrelated project data.

## Step 4 — Upload and track creation

Use the existing asset storage abstraction and private storage mechanism. Do not store public URLs or expose storage paths.

Validate:
- File is present and is an allowed audio type.
- MIME type, extension, and size match existing project configuration and conventions.
- Duration/audio metadata is handled using existing inspection services when supported.
- Project/item ownership and permissions are valid.
- Optional notes have appropriate length and validation.

For each successful upload, create a new track:
- `source = manual`
- `asset_id` references the stored private audio Asset
- `uploader_id` is the authenticated user
- `recorded_at` is set appropriately for an uploaded recording, using project conventions and clear semantics
- `approval_status = draft`
- `status` uses an existing lifecycle state that accurately represents a completed, available audio asset; do not invent a new lifecycle value
- `narration_snapshot` and `narration_hash` follow existing snapshot/hash conventions, if required by the model
- `provider` and `voice` use documented, consistent manual values if the schema requires them
- Preserve duration and language metadata only when available and validated.

A successful upload must not overwrite an existing TtsTrack or Asset history record.

Handle failures safely:
- If track creation fails after an asset is stored, prevent or clean up an orphaned asset using existing safe cleanup patterns.
- Use transactions for database consistency where appropriate, but do not assume a database transaction rolls back filesystem writes.
- Do not delete previous tracks or audio files as part of replacement.
- Do not create a track that points to an asset belonging to a different project.

## Step 5 — Manual track history

Manual history must explicitly filter `source = manual`.

The list response should:
- Return manual tracks only.
- Include useful metadata such as track ID, lifecycle status, approval status, uploader/approver display data permitted by the API, recorded/approved timestamps, notes, duration, stale status if meaningful, and authenticated preview URL.
- Avoid leaking filesystem paths, secrets, internal provider credentials, or unrelated user information.
- Preserve track history; newest first unless the established API convention specifies otherwise.

Inspect `TtsGenerationService::listTracks()`:
- Do not allow manual tracks to be confused with automated TTS tracks in the existing TTS history endpoint.
- If it currently returns all sources, add an explicit `source = tts` filter in the TTS-specific listing.
- Do not alter automated generation, regeneration, archive, readiness, or manifest semantics beyond this source isolation.

## Step 6 — Approval workflow and authorization

Keep approval status separate from lifecycle status.

Implement explicit, validated transitions:
- `draft` → `ready_for_review`
- `ready_for_review` → `approved`
- `ready_for_review` → `rejected`

Additional transitions must not be added unless required by existing product rules and implemented with appropriate tests.

For submission:
- Only authorized contributors may submit a draft.
- Only a user with the appropriate reviewer/approver permission may approve or reject.
- Determine permissions from actual existing policies/roles; do not invent a role name or assume every authenticated user is an approver.
- If the project has no reviewer/approver permission model, do not silently grant approval to every user. Implement a safe policy consistent with current project authorization or report the missing product decision.

On approval:
- Set `approval_status = approved`
- Set `approved_at` and `approved_by`
- Ensure metadata cannot be set by arbitrary client input.
- Define and test what happens to approval metadata when a track is rejected or resubmitted. Do not leave contradictory approval metadata.

On rejection:
- Set `approval_status = rejected`
- Clear or preserve approval metadata according to a documented, consistent rule.
- Validate rejection notes/reason if required by the chosen domain contract.

Approval must not mutate or overwrite the lifecycle status to represent the approval decision. Do not allow approval actions on `source = tts` tracks.

## Step 7 — Staleness and narration association

Inspect the existing narration snapshot/hash logic.

- Associate the uploaded recording with the item's current narration snapshot/hash using the existing canonical hashing method.
- Do not invent a second hash algorithm.
- If the narration changes later, history should remain intact and the manual track should be identifiable as stale if the existing domain supports that concept.
- Do not make a manual track satisfy automated TTS readiness or enter the automated TTS manifest.

## Step 8 — Regression tests

Add focused feature tests for:
1. Uploading a valid audio file creates a new manual track and asset.
2. Invalid MIME type, extension, or size is rejected.
3. Unauthenticated requests are rejected.
4. Unauthorized users cannot access another project's items or tracks.
5. Nested route ID mismatches are rejected.
6. Manual history lists only `source = manual`.
7. Automated TTS history lists only `source = tts`.
8. Uploading a replacement creates a second track and preserves the original track and asset.
9. Submit/approve/reject transitions work only for allowed statuses and authorized users.
10. Approval metadata is assigned server-side and cannot be forged through request input.
11. Automated TTS readiness and manifest remain isolated from manual tracks.
12. Authenticated asset preview remains private and functional for manual audio.
13. Failed track creation does not leave an orphaned asset where safe cleanup is supported.
14. Existing automated TTS generation, regeneration, and API tests remain green.

Use existing factories, fake storage, and test helpers. Test actual behavior, not merely route registration.

## Scope boundaries

Do NOT:
- Implement React UI or MediaRecorder microphone capture.
- Change unrelated user files or provider configuration.
- Expose public audio URLs or make the asset storage disk public.
- Delete previous tracks or assets during replacement.
- Add a new track table.
- Change automated TTS readiness or manifest to accept manual audio.
- Weaken authorization or grant approval to every authenticated user without evidence.
- Change database schema unless required by verified blocking evidence; if needed, keep changes additive and document the reason.
- Run destructive migrations or operate on development/production data.
- Run `git add`, commit, or push.
- Use `git add .`, `git add -A`, `git clean`, or `git reset`.

## Verification

Run focused Manual Dubbing feature tests, existing TTS generation/history tests, readiness/manifest regression tests, and the full test suite if practical.

Run PHP syntax checks, `git diff --check`, and Pint in `--test` mode only if used. Report exact results and any environment blockers. Do not claim tests passed without running them.

## Final report

Provide:
1. Summary and exact files added/modified.
2. Routes and authorization behavior.
3. Upload validation, storage, and orphan cleanup strategy.
4. Track metadata and provider/voice decision.
5. Approval transition and authorization rules.
6. History separation between manual and automated TTS.
7. Test results with exact counts.
8. Remaining risks and proposed Part 11 scope.
9. Final `git status --short`, including pre-existing user changes.
10. Confirmation that nothing was staged, committed, or pushed and no development/production migration was run.

Definition of done: Manual Dubbing backend can upload, list, and review audio safely; every replacement preserves history; automated TTS behavior remains compatible and isolated.