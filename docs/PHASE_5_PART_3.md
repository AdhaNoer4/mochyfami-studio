# CODING AGENT PROMPT — PHASE 5 PART 3: TTS READINESS

## 0. PROJECT CONTEXT

You are working on the existing MochyFami Content Studio repository.

The application uses:

- Laravel 13
- PHP 8.4
- MySQL
- Laravel Sanctum
- React + Vite + TypeScript + Tailwind CSS
- Private local asset storage
- PHPUnit-based automated tests
- FFmpeg/ffprobe for media inspection

The content workflow includes:

IDEA → SELECTED → RESEARCH → FACT CHECK → SCRIPT → VISUAL PLAN → ASSET COLLECTION → TTS → SUBTITLE → VIDEO RENDER → THUMBNAIL → QUALITY REVIEW → APPROVED → PUBLISHED → ANALYTICS

This task implements **Phase 5 Part 3: TTS Readiness** only.

Do not start Part 4 or any later part.

## 1. CURRENT BASELINE

Phase 5 Part 1 is complete.

Commit:
`8283fa2 feat: add TTS domain and provider foundation`

Phase 5 Part 2 is complete.

Commit:
`ed6febf feat: add TTS synthesis and storage API`

Latest verified baseline after Part 2:

- Full test suite: 1,533 tests
- Assertions: 4,726
- TypeScript check: passed
- Frontend production build: passed
- Mutation checks for Part 2: 8/8 detected

The repository has existing unrelated Pint failures in:

- `LoginRequest.php`
- `ApiResponse.php`

Do not modify those unrelated files merely to make Pint pass.

Existing untracked user documentation may be present, including:

- `docs/Plan.md`
- `docs/pre_phase_5.md`
- `docs/testing.md`
- `docs/PHASE_5_REPO_DISCOVERY.md`
- `docs/PHASE_5_TTS.md`
- `docs/PHASE_5_PART_2.md`

Preserve all pre-existing user changes. Never delete, overwrite, reset, or stage unrelated files.

The latest expected committed application state is `ed6febf`, but verify the actual checkout before doing anything.

## 2. REQUIRED PRE-FLIGHT INSPECTION

Before writing or modifying code:

1. Run `git status --short`.

2. Record the current branch and HEAD commit.

3. Inspect the actual implementations of:
    - `docs/PHASE_5_TTS.md`
    - `docs/PHASE_5_PART_2.md`, if present
    - `TtsTrack`
    - `TtsTrackStatus`
    - `TtsGenerationService`
    - `NarrationHasher`
    - `TtsProvider`
    - `TtsProviderRegistry`
    - `TtsProviderException`
    - TTS API controllers, Form Requests, Resources, routes, and policies
    - `VisualPlan`
    - `VisualPlanItem`
    - `VisualPlanItem` narration and duration fields
    - `Asset`
    - `AssetType::Audio`
    - existing asset metadata inspection and metadata persistence
    - `AssetRequirementService` and its TTS asset exclusion guard
    - existing readiness/quality evaluation services and response conventions
    - project/script-version ownership and scoping
    - relevant API feature tests and factories

4. Identify the exact existing names, fields, relationships, response envelopes, and authorization conventions. Do not assume names from this prompt match every implementation detail.

5. Run a focused subset of existing TTS tests before changing code to confirm the starting behavior.

6. Review the existing readiness specification and settle any ambiguity from the repository's documented decisions before implementation.

If the repository materially differs from the baseline, stop and report the discrepancy instead of blindly applying changes.

Do not edit files during pre-flight inspection.

## 3. LOCKED ARCHITECTURAL DECISIONS

These decisions are authoritative:

### 3.1 Readiness is read-only

Readiness evaluation must not:

- Call `TtsProvider::synthesize()`.
- Generate or regenerate audio.
- Create, update, or archive a `TtsTrack`.
- Create, update, or delete an `Asset`.
- Write files.
- Modify narration text.
- Change Visual Plan items.
- Change script or research data.
- Change any workflow status.
- Modify Production Specification.
- Modify Handoff state or readiness.
- Create a production manifest.
- Trigger queues, jobs, events, listeners, or external requests.

Repeated GET requests with unchanged database and configuration state must produce equivalent readiness results.

Do not introduce a provider network call just to check provider availability.

### 3.2 TTS remains a separate readiness domain

TTS readiness is a standalone report.

Do not integrate it into:

- `HandoffService`
- Production Specification
- existing asset readiness calculations
- asset requirement candidate selection
- the production manifest

Those integrations are outside this part.

### 3.3 Preserve TTS history

TTS tracks are historical records. Do not assume there is only one track per Visual Plan item.

Regeneration remains an explicit action handled by Part 2. Readiness must not automatically regenerate stale or failed tracks.

### 3.4 Keep assets private

Never expose:

- `storage_path`
- `file_path`
- absolute filesystem paths
- private storage locations
- provider credentials
- internal exception traces

Do not use `Storage::url()` for private TTS assets.

Do not weaken the TTS Asset exclusion guard in `AssetRequirementService`.

### 3.5 Preserve existing architecture

Reuse existing services, enums, resources, API response conventions, route patterns, authorization rules, and testing helpers where appropriate.

Do not introduce a new architecture or duplicate an existing general-purpose service.

## 4. OBJECTIVE

Implement a read-only TTS readiness evaluator for a specific script version and its associated Visual Plan.

The evaluator must answer:

1. Is TTS ready for the version's Visual Plan?
2. Which blockers prevent readiness?
3. Which warnings require attention?
4. What informational counts or summary details are useful?
5. Is the configured TTS provider available according to local configuration and registry resolution, without performing synthesis?

The evaluator must use the **current narration text** and existing TTS track history to assess whether generated audio is suitable for the current version of each Visual Plan item.

Read `docs/PHASE_5_TTS.md` first and follow its exact documented readiness codes and response semantics. If this prompt and the repository's locked specification differ, stop and report the discrepancy before implementing the conflicting behavior.

## 5. EXPECTED DELIVERABLES

Implement the smallest coherent set of changes necessary for:

1. A dedicated `TtsReadinessService`.
2. A read-only readiness API endpoint for a script version.
3. Appropriate response representation using existing API conventions.
4. Comprehensive feature and unit tests.
5. Focused mutation checks for the critical readiness rules.
6. Documentation of the implementation and verification results.

Do not implement frontend UI in this part.

## 6. API CONTRACT

The expected endpoint is:

`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-readiness`

Before implementation, inspect the existing route hierarchy and route naming conventions. Adjust only if the locked specification or actual routing architecture requires it.

### Endpoint behavior

- Requires authentication through the existing Sanctum API middleware.
- Uses the existing project and script-version ownership rules.
- Must not allow a script version belonging to another project to be evaluated through the requested project.
- Invalid or cross-project project/version combinations must follow existing API 404 conventions.
- Unauthenticated requests must return the existing API 401 response.
- A readiness report is not a resource lookup for a required existing Visual Plan. If the locked specification confirms report-style semantics, a missing plan must return a normal successful readiness response containing a blocker, rather than a 404 solely because the plan does not exist.
- Do not return a 404 for a missing Visual Plan item when the intended behavior is to report it as a readiness blocker.
- Do not create a Visual Plan as a side effect.

Use the project's existing `ApiResponse` or equivalent response convention. Do not create a competing API envelope.

Use a Resource or DTO only if consistent with existing architecture and useful for keeping the response contract clear.

### Suggested response shape

Treat this as a conceptual example, not permission to introduce incompatible field names:

{
"ready": false,
"blockers": [
{
"code": "ITEM_WITHOUT_TRACK",
"message": "A Visual Plan item does not have usable audio for its current narration."
}
],
"warnings": [],
"info": {
"total_items": 3,
"ready_items": 2,
"items_without_current_audio": 1
}
}

The actual outer envelope must match existing API conventions. Keep the readiness report itself clear and predictable.

Each issue should have a stable machine-readable `code` and a safe, human-readable `message`.

Do not return exception traces, filesystem paths, private asset data, or credentials.

## 7. READINESS RULES

Use the exact issue codes and definitions in `docs/PHASE_5_TTS.md`. The following are the expected categories and concepts; verify them against the specification before implementation.

### 7.1 Blockers

Expected blocker codes:

- `NO_VISUAL_PLAN`
- `NO_VISUAL_PLAN_ITEMS`
- `ITEM_WITHOUT_NARRATION`
- `TTS_GENERATION_FAILED`
- `PROVIDER_UNAVAILABLE`

#### A. NO_VISUAL_PLAN

Report when the target script version has no associated Visual Plan.

Do not create the plan automatically.

#### B. NO_VISUAL_PLAN_ITEMS

Report when a Visual Plan exists but contains no items.

Do not create default items.

#### C. ITEM_WITHOUT_NARRATION

Report when a Visual Plan item has no usable narration text.

Use the same normalization/validation assumptions as the existing TTS generation flow. Inspect Part 2 before defining whitespace, empty string, or other edge-case behavior.

Do not mutate the item to fix it.

#### D. TTS_GENERATION_FAILED

Report an unsuccessful TTS generation when the failure is relevant to the current narration and there is no usable completed track for that current narration, according to the locked specification.

Inspect how Part 2 stores failed track snapshots, narration hashes, statuses, and error codes.

Do not infer failure from an unrelated historical track. Do not let an old failed track incorrectly block an item that already has a usable completed track for the current narration.

Do not expose provider exception details.

#### E. PROVIDER_UNAVAILABLE

Report when the configured TTS provider cannot be resolved or is not available according to the existing provider configuration/registry contract.

This check must be local and read-only.

Do not call synthesis, make a network request, or probe an external provider.

If the existing registry needs a read-only availability method, implement the smallest appropriate addition and test it.

An unknown provider configuration must not cause an uncaught 500.

### 7.2 Warnings

Expected warning codes:

- `NARRATION_CHANGED_SINCE_SYNTHESIS`
- `TRACK_DURATION_MISMATCH`
- `ITEM_WITHOUT_TRACK`
- `METADATA_NOT_VERIFIED`
- `LONG_NARRATION`

#### A. NARRATION_CHANGED_SINCE_SYNTHESIS

Report when a previously generated track exists but its narration hash differs from the current Visual Plan item's narration hash, according to the specification's track-selection rules.

Calculate the current narration hash using the existing `NarrationHasher`. Do not introduce a duplicate hash algorithm.

A historical track for old narration must not be mistaken for audio matching the current narration.

#### B. TRACK_DURATION_MISMATCH

Compare the current usable track's verified audio duration against the Visual Plan item's expected duration.

The locked rule is exact inequality:

`track.duration_seconds != item.duration_seconds`

There is no tolerance window, score, or approximate match.

Do not use the provider's claimed duration as a substitute for verified audio duration.

Handle missing/null duration values according to the locked specification. Do not report a mismatch when a meaningful comparison cannot be made.

#### C. ITEM_WITHOUT_TRACK

Report when an item does not have a usable completed track for its current narration and the locked issue classification calls for this warning.

Distinguish carefully between:

- an item that has never had a track;
- an item with only historical tracks for different narration;
- an item whose current generation attempt failed;
- an item that has a completed track matching its current narration.

Read the locked specification and Part 2 implementation to determine the correct classification and whether multiple issue codes may legitimately apply to the same item.

Do not make up contradictory classifications. Add tests for each distinct scenario.

#### D. METADATA_NOT_VERIFIED

Report when the audio asset's duration or metadata has not been successfully verified according to existing metadata inspection and persistence semantics.

Inspect the existing `AudioMetadataInspector`, the Asset model, and Part 2's actual storage flow before defining the condition.

Do not trust metadata merely because a track status is `completed`.

Do not run FFprobe or rewrite metadata during a GET readiness request unless the existing locked specification explicitly requires it. This part should evaluate persisted verification results, not perform media processing.

#### E. LONG_NARRATION

Use the locked heuristic:

`predicted_duration_seconds = ceil(word_count / 3)`

Emit this warning when the predicted duration is greater than 60 seconds.

This means narration with more than 180 words exceeds the threshold.

The warning must not by itself make readiness false.

Use the existing narration text and the same clearly documented word-count approach consistently. Add boundary tests for 180 and 181 words.

Do not split narration, rewrite it, or trigger regeneration.

### 7.3 Ready calculation

The expected core rule is:

`ready = blockers.isEmpty()`

Warnings alone must not make readiness false.

Verify this rule against `docs/PHASE_5_TTS.md`.

An item may be ready only when a usable completed audio track matches its current narration and the relevant asset is acceptable under the locked rules.

Never treat a stale completed track as valid audio for changed narration.

Never treat a failed track as completed audio.

Never use an archived track as a current usable track.

Respect the existing `TtsTrackStatus` lifecycle and inspect the actual enum before implementing status checks.

## 8. TRACK SELECTION AND HISTORY

This is a critical correctness area.

Multiple historical tracks may exist for one Visual Plan item because regeneration creates a new track rather than overwriting the previous one.

Implement track selection using the documented rules, not incidental database order.

At minimum, test these cases:

1. No tracks exist.
2. A completed track matches the current narration hash.
3. A completed track exists but has a stale narration hash.
4. A failed track exists for the current narration.
5. An old failed track exists, but a completed track matches current narration.
6. A completed matching track exists alongside archived history.
7. Only archived tracks exist.
8. Multiple tracks exist with different statuses and narration hashes.
9. The newest track is failed, but an older valid completed track matches the current narration.
10. The narration changed after the last successful synthesis.

The exact classification of cases 4, 8, and 9 must follow the locked specification. Do not blindly select the latest track if doing so incorrectly invalidates a still-usable completed track for the current narration.

Prefer an explicit, documented selection rule based on status, narration hash, and existing timestamps or versioning fields.

Do not add a database uniqueness constraint that prevents history retention.

Do not add or modify a migration unless the repository inspection reveals a genuinely necessary schema gap and the change is explicitly permitted by the locked specification. If a schema gap is found, stop and report it before expanding scope.

## 9. RESPONSE CONTENT

Keep the response useful but minimal.

Return:

- `ready`: boolean
- `blockers`: list of issues
- `warnings`: list of issues
- `info`: compact summary if appropriate

Useful informational values may include:

- total Visual Plan items;
- items with usable current audio;
- items without usable current audio;
- number of completed tracks;
- provider configuration status, if safe and useful.

Only include fields supported by existing data and the specification.

Do not return a public filesystem path or private asset URL.

Avoid exposing implementation details that clients should not depend on, such as provider exception classes or internal model relation names.

Ensure issue ordering is deterministic so equivalent state produces stable API responses and tests.

Do not add unnecessary per-item payloads. If the specification calls for item-specific context, expose only safe identifiers or order values already considered appropriate by the API conventions.

## 10. PERFORMANCE AND DATABASE ACCESS

Readiness should handle a Visual Plan with multiple items without introducing obvious N+1 queries.

- Eager-load the required Visual Plan items and TTS tracks/assets where appropriate.
- Avoid querying the same tracks separately for every item if a single scoped query can provide the data.
- Do not load unrelated projects' tracks.
- Preserve project/version scoping.
- Do not introduce caching that could return stale readiness results.
- Do not add unnecessary database columns, tables, dependencies, jobs, or services.

Use database queries and relationships consistent with the repository's conventions.

## 11. TEST REQUIREMENTS

Create focused tests for the service and API using existing test conventions and factories.

### A. API access and scoping

Verify:

- unauthenticated request returns 401;
- authorized project/version request succeeds;
- a script version from another project returns 404;
- a user without project access is handled according to existing policy conventions;
- missing Visual Plan follows the locked report semantics;
- missing items follow the locked report semantics;
- no private path or provider error detail is returned.

### B. Readiness calculations

Verify:

- no Visual Plan;
- empty Visual Plan;
- item with empty narration;
- item with whitespace-only narration;
- item with no TTS history;
- matching completed track;
- stale completed track;
- failed current generation;
- failed historical track plus valid matching completed track;
- archived-only history;
- narration changed after synthesis;
- exact duration match;
- duration one second below expected duration;
- duration one second above expected duration;
- missing expected duration;
- unverified/missing audio metadata;
- provider configured and resolvable;
- provider unknown/unresolvable;
- long narration at exactly 180 words;
- long narration at 181 words;
- multiple blockers and warnings together;
- deterministic issue ordering.

### C. Read-only guarantee

Prove that the GET endpoint does not mutate relevant state.

Capture and compare before/after:

- `tts_tracks` rows and relevant fields;
- `assets` rows and relevant fields;
- Visual Plan and item rows;
- relevant persisted metadata;
- relevant workflow/Handoff state.

Call readiness more than once and verify that it produces equivalent results without changing those records.

Also verify that readiness does not invoke `TtsProvider::synthesize()`.

Use a test spy/mock or an equivalent deterministic technique supported by the existing provider architecture. If the Fake provider is used, add a test that proves the synthesis method was not called.

Do not rely only on checking HTTP status to prove read-only behavior.

### D. Asset privacy and isolation

Verify:

- no `file_path` or `storage_path` appears in the response;
- no private storage URL appears;
- another project's TTS tracks cannot influence the report;
- archived tracks are not counted as usable audio;
- TTS readiness does not bypass or weaken the Asset Requirement TTS exclusion guard.

### E. Test quality

Tests must verify actual behavior and side effects, not merely implementation details.

Use descriptive test names.

Avoid brittle assertions tied to irrelevant wording or query counts.

Do not reduce or delete existing tests to make the suite pass.

## 12. MUTATION TESTING

Perform focused manual mutation checks for at least these critical rules:

1. Change readiness calculation so warnings also make `ready` false.
2. Remove the narration-hash comparison.
3. Allow a failed track to count as usable completed audio.
4. Allow an archived track to count as usable audio.
5. Remove the exact duration mismatch check.
6. Change the long-narration threshold so the 180/181 boundary is incorrect.
7. Make unknown provider configuration appear available.
8. Introduce a write or synthesis call into readiness evaluation.
9. Remove project/version scoping so another project's data can affect readiness.

For each mutation:

- introduce the mutation temporarily;
- run the smallest relevant test;
- confirm the test fails;
- revert the mutation immediately;
- verify the working tree contains no mutation residue.

If a mutation survives, strengthen the test or report why the behavior cannot be mutation-tested safely. Do not claim a mutation was caught unless a test actually failed because of it.

Never commit a deliberate mutation.

## 13. CODE QUALITY AND SCOPE

- Follow Laravel and project conventions.
- Use strict and clear status checks.
- Keep readiness evaluation in a dedicated service rather than a large controller method.
- Keep issue codes stable.
- Keep issue ordering deterministic.
- Do not duplicate narration hashing.
- Do not call a provider during readiness.
- Do not change Part 2 synthesis behavior unless a correctness defect makes it unavoidable; if so, stop and report the issue before expanding scope.
- Do not add frontend changes.
- Do not add the TTS production manifest.
- Do not modify Production Specification or Handoff.
- Do not add queue-based processing.
- Do not add real external TTS providers.
- Do not add subtitles, rendering, BGM, automatic segmentation, or automatic regeneration.
- Do not change environment configuration or dependencies unless required by the locked spec and explicitly reported.

Do not run destructive Git commands:

- no `git reset --hard`;
- no `git clean`;
- no blanket checkout/revert of user files;
- no staging all files with `git add -A`;
- no forced commit or push.

## 14. VERIFICATION

Before finalizing:

1. Run all new and focused readiness tests.
2. Run the full backend test suite.
3. Run the relevant PHP formatter/linter checks on changed files.
4. Run TypeScript checking and the frontend production build only if required to verify that no API contract/type regressions occurred. Do not modify frontend just to claim these checks passed.
5. Review `git diff --check`.
6. Review the complete diff and confirm no unrelated files were changed.
7. Confirm all mutation experiments were reverted.
8. Confirm no provider call, asset write, track mutation, or workflow mutation is possible through the readiness endpoint.
9. Record the final test and assertion counts.
10. Record any known failures separately from new failures.

The previously documented unrelated Pint failures in `LoginRequest.php` and `ApiResponse.php` must be reported accurately. Do not fix them as part of this task.

If any required verification fails, investigate and fix in-scope defects before declaring PASS. Never fabricate successful test output.

## 15. DOCUMENTATION

Create a new implementation report:

`docs/PHASE_5_PART_3.md`

Document:

- implemented service and API endpoint;
- response contract and issue codes;
- track-selection rules;
- blocker/warning behavior;
- duration and long-narration rules;
- provider availability check;
- read-only guarantees;
- authorization and project scoping;
- tests executed and actual results;
- mutation checks and outcomes;
- changed files;
- known limitations.

Do not overwrite `docs/PHASE_5_TTS.md` or other existing user documentation.

If the specification must be changed, do not silently edit it. Report the conflict and wait for approval.

## 16. GIT AND COMMIT RULES

Before committing:

- inspect `git status --short`;
- inspect the complete diff;
- stage only explicitly intended Phase 5 Part 3 application files, tests, and `docs/PHASE_5_PART_3.md`;
- do not stage pre-existing untracked user files;
- do not amend the previous Part 2 commit;
- do not push to any remote.

If implementation and verification pass, create exactly one new commit:

`feat: add TTS readiness evaluation`

If the repository has unexpected changes, tests fail, or the implementation cannot be completed safely, do not create a commit. Report the exact blocker instead.

After a successful commit, report the new commit hash and verify the final Git status.

## 17. FINAL REPORT FORMAT

Return a concise but complete Indonesian report containing:

### Status

PASS / PARTIAL / BLOCKED

### Implementasi

- service and endpoint;
- readiness behavior;
- track selection and history handling;
- provider availability behavior;
- read-only guarantees.

### API

- exact endpoint;
- example response using the actual implemented contract.

### Verifikasi

- baseline test count and assertion count;
- final full-suite test count and assertion count;
- focused test results;
- formatter/linter results;
- frontend checks, if run;
- mutation results, with caught/total count;
- read-only side-effect verification.

### Git

- commit hash and message, if committed;
- final `git status --short`;
- confirmation that no push occurred.

### Files and limitations

- principal changed files;
- known issues and any deviations from the locked spec.

### Next step

Stop after Part 3. Do not start Part 4.

Do not report PASS if any required critical behavior is unverified. Be explicit about anything not run, any mutation that survived, and any deviation from the locked specification.
