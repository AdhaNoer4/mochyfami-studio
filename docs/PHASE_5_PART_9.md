# Phase 5 Part 9 — Automated TTS Isolation & Readiness Integrity

## Project context

Mochyfami Studio uses Laravel + React/Vite/Tailwind.

Phase 5 already implements automated TTS, including:

- `TtsTrack` history records related to `VisualPlanItem` and `Asset`.
- TTS generation and storage.
- TTS readiness reporting.
- Production manifest generation.
- Authenticated asset preview.
- Existing TTS API resources, frontend UI, and regression tests.

Phase 5 Part 8 has added:

- `App\Enums\TtsTrackSource` with `tts` and `manual`.
- `App\Enums\ManualDubbingApprovalStatus`.
- Additive `tts_tracks` migration for `source`, uploader, recording, approval, and notes metadata.
- `TtsTrack` enum casts, relations, and source default compatibility.
- Manual state support in `TtsTrackFactory`.

Part 8's reported tests passed, including 1624 full-suite tests. Do not assume the report proves the current working tree is unchanged; inspect the repository before modifying it.

## Objective

Ensure that automated TTS readiness and production manifest logic consider only tracks whose source is `tts`. Ensure manual audio assets are not incorrectly classified as automated TTS-generated assets.

This part is a focused isolation and regression-hardening task. It is NOT the Manual Dubbing upload/API/UI implementation.

## Step 1 — Read-only preflight

Before making changes:

1. Inspect `git status --short`, current branch, and HEAD.
2. Inspect the current diff and untracked files without modifying them.
3. Read the Part 7 audit and Part 8 implementation report if available.
4. Inspect:
    - `TtsReadinessService`
    - `TtsManifestService`
    - `Asset::isTtsGenerated()` and every call site
    - TTS track queries/scopes
    - relevant resources and tests
5. Identify whether readiness and manifest share query logic or independently select tracks.

Treat user-owned changes and untracked files as protected. Do not discard, overwrite, stage, or delete them.

## Step 2 — Explicit source filtering

Update the automated TTS selection logic so that only `source = tts` can contribute to:

- TTS readiness
- production manifest entries
- any automated-TTS-specific track selection involved in those two flows

Keep the existing requirements for lifecycle status, current narration hash, asset availability, and any other existing readiness/manifest rules.

Do not weaken current readiness validation merely to make tests pass.

Use the existing enum and project conventions. Prefer explicit source filtering in the domain/query layer rather than relying on the fact that current manual tracks may have a different provider or voice value.

Ensure the filtering is applied consistently, even if readiness and manifest use separate query paths.

## Step 3 — Audit `Asset::isTtsGenerated()`

Inspect its current implementation and all usages before changing it.

If it currently returns true whenever any related `TtsTrack` exists, make the minimum safe change so a manual track alone does not classify its asset as TTS-generated.

Requirements:

- Only tracks with source `tts` should establish TTS-generated provenance.
- Preserve existing behavior for actual TTS-generated assets.
- Handle nullable/deleted asset relations and existing legacy records safely.
- Do not change unrelated asset classification, upload validation, private storage, or authenticated preview behavior.
- Add focused tests for manual-only assets and genuine TTS-generated assets.

If the method's semantics are broader than its name suggests, document the finding and avoid changing unrelated callers without evidence.

## Step 4 — Regression tests

Add or update tests that prove:

1. A completed manual track with a narration hash matching the current narration does not satisfy automated TTS readiness.
2. A completed TTS track with a matching narration hash still satisfies readiness when all existing readiness conditions are met.
3. A manual track does not appear in the automated TTS production manifest.
4. A valid TTS track still appears in the manifest.
5. A manual-only track does not cause `Asset::isTtsGenerated()` to return true.
6. An actual TTS-generated asset remains classified correctly.
7. Multiple tracks and historical tracks continue to follow existing current-hash and lifecycle rules.
8. Existing TTS API behavior and resource contracts remain unchanged.

Use factories and test conventions already present. Avoid brittle assertions tied only to internal query syntax.

## Scope boundaries

Do NOT:

- Implement manual upload or microphone recording.
- Add Manual Dubbing endpoints, approval routes, or services.
- Change React components or frontend types.
- Change the lifecycle or approval state machines.
- Change `provider` or `voice` nullability in this part.
- Alter narration hashing rules.
- Change database schema or add a migration unless a genuine blocking schema issue is discovered. If one is found, stop and report the evidence before expanding scope.
- Modify existing TTS generation behavior beyond the required source isolation.
- Change `.env`, API credentials, provider configuration, or unrelated user files.
- Run destructive database operations or migrations against development/production.
- Run `git add`, create commits, or push.
- Use `git add .`, `git add -A`, `git clean`, or `git reset`.

## Verification

Run focused tests for readiness, manifest, and asset classification, then the relevant TTS regression suite.

If practical and consistent with the repository's test setup, run the full test suite. Report actual results, not assumed results.

Run:

- PHP syntax checks for changed PHP files.
- `git diff --check`.
- TypeScript/build checks only if frontend files unexpectedly need to change; normally this part should not touch frontend files.
- `git status --short` and `git diff --stat` at the end.

Do not run a formatter that rewrites unrelated files. If using Pint, use `--test`.

## Final report

Return:

1. Summary of behavior changes.
2. Exact files added/modified.
3. How source filtering is enforced in readiness and manifest.
4. Findings and changes for `Asset::isTtsGenerated()`.
5. Tests executed with actual pass/fail counts.
6. Any remaining risks or deferred work.
7. Final `git status --short`, including pre-existing user changes.
8. Confirmation that no files were staged, no commit/push was performed, and no development/production migration was run.
9. A proposed Part 10 scope, but do not implement Part 10.

Definition of done: automated TTS readiness and manifest ignore manual tracks; genuine TTS continues working; manual assets are not misclassified; regression tests pass; unrelated work remains untouched.
