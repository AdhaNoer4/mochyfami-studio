# CODING AGENT PROMPT — PHASE 5 PART 4: TTS PRODUCTION MANIFEST

## 0. PROJECT CONTEXT

You are working on the existing MochyFami Content Studio repository.

Technology stack:

- Laravel 13
- PHP 8.4
- MySQL
- Laravel Sanctum
- React + Vite + TypeScript + Tailwind CSS
- Private local asset storage
- FFmpeg/ffprobe for media inspection
- Existing TTS provider abstraction and synchronous generation flow

Content workflow:

IDEA → SELECTED → RESEARCH → FACT CHECK → SCRIPT → VISUAL PLAN → ASSET COLLECTION → TTS → SUBTITLE → VIDEO RENDER → THUMBNAIL → QUALITY REVIEW → APPROVED → PUBLISHED → ANALYTICS

This task implements **Phase 5 Part 4: TTS Production Manifest** only.

Do not start Part 5 or any later part.

## 1. CURRENT BASELINE

Phase 5 Part 1:
`8283fa2 feat: add TTS domain and provider foundation`

Phase 5 Part 2:
`ed6febf feat: add TTS synthesis and storage API`

Phase 5 Part 3:
`4e4c43f` — TTS Readiness implementation.

Reported verification after Part 3:

- Full backend suite: 1,570 tests passed
- Assertions: 4,857
- Pint dirty-file check: clean, with two known pre-existing failures when checking the whole repository
- TypeScript check: passed
- Frontend production build: passed
- Mutation tests: 9/9 caught and reverted byte-exactly

Part 3 implementation report:
`docs/PHASE_5_PART_3_IMPLEMENTATION.md`

The raw Part 3 brief may exist as an untracked user file:
`docs/PHASE_5_PART_3.md`

Other pre-existing user documents may include:

- `docs/Plan.md`
- `docs/pre_phase_5.md`
- `docs/testing.md`
- `docs/PHASE_5_REPO_DISCOVERY.md`
- `docs/PHASE_5_TTS.md`
- `docs/PHASE_5_PART_2.md`

Do not overwrite, delete, stage, or modify pre-existing user documents without explicit authorization.

The reported test counts are a baseline, not permission to assume the repository is unchanged. Verify the actual checkout before proceeding.

## 2. MANDATORY PRE-FLIGHT INSPECTION

Before editing any files:

1. Run `git status --short`.

2. Record branch name and current HEAD.

3. Confirm the Part 3 commit is present in history.

4. Inspect:
    - `docs/PHASE_5_TTS.md`
    - `docs/PHASE_5_PART_3_IMPLEMENTATION.md`
    - existing Part 1 and Part 2 implementation reports, if present
    - `TtsReadinessService`
    - `TtsReadinessController`
    - `TtsReadinessResource`
    - the Part 3 route and its response contract
    - `TtsTrack` and `TtsTrackStatus`
    - `NarrationHasher`
    - `VisualPlan`
    - `VisualPlanItem`
    - `Asset`
    - `AssetType::Audio`
    - asset metadata persistence and audio inspection
    - `TtsGenerationService`
    - project/version ownership and authorization
    - existing production-manifest, asset-readiness, or handoff implementations
    - existing response envelope, API resources, and test helpers

5. Inspect the actual database schema and relationships for:
    - script version to Visual Plan;
    - Visual Plan to ordered items;
    - Visual Plan item to TTS tracks;
    - TTS track to Asset;
    - track status, narration hash, duration, and timestamps;
    - asset metadata and verification status.

6. Run focused existing TTS and readiness tests to confirm the starting behavior.

7. Identify the precise fields and status semantics needed by the manifest.

Do not edit during pre-flight.

If the specification, existing schema, or implementation conflicts with this prompt in a way that affects correctness, stop and report the discrepancy before implementing the conflicting behavior.

Do not infer database field names or metadata-verification semantics without inspecting the actual code.

## 3. LOCKED ARCHITECTURAL DECISIONS

These decisions remain authoritative:

### 3.1 Manifest is read-only

The manifest endpoint must not:

- call `TtsProvider::synthesize()`;
- create, update, or archive TTS tracks;
- create, update, or delete assets;
- write audio or other files;
- mutate Visual Plan items;
- update narration or script versions;
- change workflow status;
- trigger jobs, queues, events, or listeners;
- regenerate stale or failed audio;
- perform FFmpeg/ffprobe work that writes or refreshes persisted metadata;
- alter asset readiness or TTS readiness state.

It must be safe to call repeatedly.

### 3.2 Standalone TTS domain

The TTS manifest remains independent of:

- Production Specification;
- `HandoffService`;
- general asset readiness;
- asset requirement selection;
- subtitle generation;
- video rendering.

Do not integrate or modify these systems in Part 4.

### 3.3 Private storage

All TTS audio assets remain private.

Never return:

- `storage_path`;
- `file_path`;
- absolute filesystem paths;
- private storage directories;
- internal storage implementation details;
- `Storage::url()` links for private files;
- credentials or provider exception details.

The manifest describes persisted audio metadata and references through safe application-level identifiers only.

### 3.4 History retention

TTS generation preserves historical tracks. Never introduce a uniqueness constraint or update-in-place behavior that destroys this history.

A manifest entry must refer to a valid, completed audio track matching the current narration, not simply whichever track happens to be newest.

### 3.5 Scope discipline

Do not add frontend components, queues, real external TTS providers, subtitles, rendering, background music, automatic segmentation, or automatic regeneration.

## 4. OBJECTIVE

Implement a deterministic, read-only **TTS Production Manifest** for a specific script version's Visual Plan.

The manifest should provide a structured, production-oriented view of:

- the target script version and Visual Plan context;
- ordered Visual Plan items;
- each item's narration reference or safe narration information;
- whether usable audio is available for that item;
- the selected current TTS track when valid;
- verified audio metadata necessary for downstream production;
- readiness blockers and warnings, using the existing Part 3 readiness rules;
- an overall manifest readiness result.

The manifest is a data contract for future production work. It does not produce media.

Read `docs/PHASE_5_TTS.md` and the Part 3 implementation before finalizing the schema. Follow the exact documented contract if one exists.

If the locked specification does not fully define a field, choose the smallest consistent design that follows the existing API conventions and record the decision in the implementation report. Do not create an unnecessarily elaborate schema.

## 5. EXPECTED DELIVERABLES

Implement only the necessary components for:

1. A dedicated TTS manifest builder/service.
2. A read-only GET API endpoint.
3. A stable response representation.
4. Reuse of Part 3 readiness semantics.
5. Comprehensive service/API tests.
6. Focused mutation testing.
7. A new implementation report.

Do not implement a frontend UI.

Do not change Part 3's endpoint or semantics unless a demonstrated defect makes that unavoidable. If a defect is found, stop and report it before broadening the scope.

## 6. API CONTRACT

Inspect the existing route conventions and the locked specification first.

The expected endpoint is:

`GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-manifest`

If the specification defines a different exact path, use the documented path and report it.

### API requirements

- Must use the existing API version and response envelope.
- Must require Sanctum authentication.
- Must preserve project/version authorization and scoping.
- A script version belonging to a different project must not be accessible through the requested project.
- Unauthenticated requests must follow the existing 401 convention.
- Cross-project or invalid project/version combinations must follow the existing 404 convention.
- Do not bypass the policy or authorization checks used by existing script and Visual Plan endpoints.

A missing Visual Plan or empty Visual Plan should be represented according to the manifest specification. If the manifest is designed as a readiness report, return a valid manifest with the relevant blockers rather than creating a Visual Plan or failing solely because the plan is absent.

Do not invent a second response envelope.

## 7. MANIFEST STRUCTURE

The exact structure must be grounded in the locked specification and existing project conventions.

A conceptual shape might be:

{
"ready": false,
"script_version_id": 123,
"visual_plan_id": 456,
"items": [
{
"order": 1,
"visual_plan_item_id": 789,
"narration": {
"hash": "..."
},
"audio": {
"available": true,
"tts_track_id": 321,
"asset_id": 654,
"duration_seconds": 12,
"mime_type": "audio/wav"
},
"blockers": [],
"warnings": []
}
],
"blockers": [],
"warnings": [],
"info": {
"total_items": 3,
"items_with_audio": 2,
"items_without_audio": 1
}
}

This is an example for discussion, not a mandate to copy these field names blindly.

Before implementation, decide from the spec whether item-level details, narration hashes, identifiers, or metadata belong in the public contract.

### Required design principles

- Use deterministic item ordering based on the Visual Plan's explicit order field.
- Do not rely on incidental database ordering.
- Reuse the current narration hash generated by `NarrationHasher`.
- Use the same current-audio selection rules as Part 3.
- Do not duplicate readiness rules in multiple divergent implementations.
- Do not expose storage paths.
- Do not return private URLs.
- Do not claim an audio file is usable if the required verification metadata is absent.
- Do not expose raw provider errors.
- Keep the response compact and stable.

If the Part 3 service already returns a structured readiness result, reuse its service or a shared internal representation instead of reimplementing its logic in the controller.

Avoid coupling the manifest to internal response-resource JSON if a service-level result can be reused more safely.

## 8. CURRENT AUDIO SELECTION

This is a critical correctness requirement.

The Part 3 report describes the selection rule as:

**Select the latest `Completed` track whose narration hash matches the current narration.**

Inspect the actual Part 3 implementation and specification to confirm the exact rule, including how archived tracks and unverified assets are handled.

The manifest must not simply select the newest track overall.

For each item, test:

1. No TTS tracks exist.
2. A valid completed track matches current narration.
3. A completed track exists with a stale narration hash.
4. The newest track is failed but an older completed track matches current narration.
5. Only failed tracks exist.
6. Only archived tracks exist.
7. Both valid completed and archived tracks exist.
8. Multiple completed tracks match current narration.
9. Current narration differs from the most recently synthesized narration.
10. A completed track points to missing or unusable asset metadata.

Use a deterministic tie-breaking rule if multiple valid tracks match the current narration. Follow the specification and actual timestamp fields. Document the rule.

Do not allow an archived or failed track to become selected current audio.

Do not overwrite history.

If Part 3's current selection behavior contradicts the locked specification, stop and report the discrepancy instead of silently creating a different rule in Part 4.

## 9. REUSE PART 3 READINESS

The manifest and readiness report must agree.

Examples:

- If Part 3 says the Visual Plan is ready and an item has valid current audio, the manifest must not mark that same item as lacking audio.
- If Part 3 identifies stale narration, the manifest must not represent that stale audio as current.
- If Part 3 identifies a failed current generation and no usable current audio exists, the manifest must not silently claim the item has valid audio.
- Warnings alone must not make readiness false if the Part 3 specification says `ready = blockers === []`.
- Missing or unverified audio metadata must be handled according to the same locked semantics in both outputs.

Use a single source of truth for current-track selection and readiness logic wherever practical.

Do not make Part 3's existing endpoint dependent on the manifest.

Do not call a provider to fill missing information.

## 10. AUDIO METADATA

Inspect the existing `Asset` metadata contract and Part 2 storage flow.

Include only persisted, verified metadata necessary for future production, such as:

- verified duration in seconds;
- MIME type;
- file size, if already reliably persisted and approved for the contract;
- safe asset and track identifiers.

Only include metadata fields that actually exist and have trustworthy semantics.

### Duration

- Prefer verified audio duration persisted from the actual audio file.
- Never trust a provider's claimed duration as the verified media duration.
- Do not run FFprobe during a GET request just to populate a missing duration.
- Do not synthesize or modify metadata during manifest generation.
- Preserve the exact duration mismatch rule defined in Part 3.

### Missing metadata

If required metadata is missing or unverified:

- report the relevant warning/blocker according to the locked specification;
- do not fabricate a value;
- do not treat missing metadata as verified;
- do not mutate the Asset or TtsTrack to fix it.

### Asset validation

Before marking audio usable, confirm the track and associated Asset meet the existing completed-track, current-narration, and metadata requirements.

Do not weaken existing asset privacy or TTS exclusion protections.

## 11. READINESS SEMANTICS

The manifest should include an overall `ready` result if required by the specification.

Use Part 3 as the source of truth.

Expected core rule, subject to the locked spec:

`ready = blockers.isEmpty()`

Warnings alone do not make readiness false.

If item-level blockers and warnings are exposed, define how top-level issues are derived. Avoid inconsistent duplicate issues.

For example, an item-level issue may also appear in the top-level issue list, but the contract should make it clear whether these are summaries or complete duplicated lists.

Use stable issue codes already defined by Part 3 wherever applicable.

Do not invent alternative codes for the same condition without justification.

If the manifest requires manifest-specific codes, document why they differ from readiness codes.

## 12. READ-ONLY GUARANTEE

The manifest must be entirely read-only.

It must not:

- call `synthesize()`;
- create, update, or archive tracks;
- create, update, or delete assets;
- create or update Visual Plans or items;
- run metadata inspection that writes persisted values;
- change Handoff or Production Specification;
- write files;
- trigger queues or events;
- perform network calls;
- regenerate audio;
- mutate readiness-related state.

Implement tests that compare relevant state before and after multiple manifest requests.

Compare at minimum:

- `tts_tracks` rows and relevant fields;
- `assets` rows and relevant fields;
- Visual Plan and Visual Plan item rows;
- persisted metadata;
- workflow/Handoff state where practical.

Prove that the provider's synthesis method was not called.

Do not treat a successful GET status as sufficient proof of read-only behavior.

## 13. SECURITY AND DATA ISOLATION

Verify:

- all data is scoped to the requested project and script version;
- cross-project Visual Plans, items, tracks, and assets cannot leak into the manifest;
- missing or inaccessible project/version combinations follow existing 404 behavior;
- no private filesystem path is exposed;
- no private storage URL is exposed;
- no provider exception details or credentials are exposed;
- manifest identifiers cannot be used to access another project's assets;
- the manifest does not make private files publicly accessible;
- the Asset Requirement TTS exclusion guard remains intact.

Do not create a new public file-download route in this part.

Do not add signed URLs unless explicitly required by the locked specification and approved existing architecture.

## 14. PERFORMANCE AND DETERMINISM

A Visual Plan may contain multiple items and multiple historical tracks per item.

Avoid obvious N+1 queries:

- eager-load items and required relations where appropriate;
- load relevant TTS track history in a scoped query when practical;
- load associated assets efficiently;
- avoid querying each item's tracks separately if a suitable batch query is available.

Do not introduce caching that risks stale manifests.

The same unchanged persisted state and configuration must produce the same manifest structure, readiness, issues, and item ordering.

Sort:

- items by explicit Visual Plan order;
- issues by a documented deterministic rule;
- any collections exposed in the response by a stable order.

Do not expose database query order as part of the API contract.

## 15. TEST REQUIREMENTS

Add tests following the repository's existing conventions.

### A. Endpoint and authorization

Test:

- unauthenticated request returns 401;
- valid authorized request returns the manifest;
- invalid project/version combinations return 404;
- cross-project script versions return 404;
- no unauthorized asset or track information leaks;
- missing Visual Plan follows the locked semantics;
- empty Visual Plan follows the locked semantics.

### B. Manifest structure

Test:

- stable top-level fields;
- correct script version and Visual Plan association;
- item order follows the explicit order field;
- identifiers refer to the correct item, track, and asset;
- issue codes are correct and deterministic;
- aggregate info counts are correct;
- the manifest response uses the existing API envelope;
- no private paths or URLs appear.

### C. Track selection

Test every scenario in Section 8, including:

- newest failed, older valid completed;
- current narration changed;
- multiple valid completed tracks;
- archived-only history;
- missing Asset;
- missing or unverified metadata.

Verify that selected track and readiness agree with Part 3.

### D. Readiness consistency

For the same project/version state, call the Part 3 readiness endpoint and the new manifest endpoint.

Assert that:

- both agree on overall readiness;
- blockers are consistent;
- warnings are consistent for shared conditions;
- current usable-audio decisions agree;
- the manifest never marks stale narration as current audio.

Avoid brittle assertions against irrelevant message wording.

### E. Read-only behavior

Snapshot relevant database state before and after manifest requests.

Call the manifest endpoint multiple times.

Assert:

- no track mutation;
- no asset mutation;
- no Visual Plan/item mutation;
- no persisted metadata mutation;
- no Handoff/workflow mutation;
- no synthesis invocation;
- no file creation, deletion, or modification caused by the request.

### F. Isolation and privacy

Test:

- another project's tracks do not influence readiness or manifest entries;
- another project's Asset cannot be substituted;
- no `file_path` or `storage_path` appears;
- no private URL is returned;
- TTS Asset Requirement exclusion still works.

### G. Edge cases

Test:

- no tracks;
- stale tracks;
- failed tracks;
- archived tracks;
- metadata not verified;
- exact duration match;
- duration mismatch;
- long narration warning;
- several items with mixed readiness;
- deterministic issue ordering;
- repeated requests produce equivalent results.

Do not add artificial database records that violate actual schema constraints merely to test impossible states. Use realistic nullable fields or unit tests for defensive branches.

## 16. MUTATION TESTING

Perform focused manual mutation checks for at least:

1. Change the manifest so it selects the newest track regardless of narration hash.
2. Allow a failed track to be selected as current audio.
3. Allow an archived track to be selected as current audio.
4. Make manifest readiness disagree with Part 3 readiness.
5. Remove the required metadata verification condition.
6. Remove project/version scoping.
7. Include a private storage path in the response.
8. Introduce a synthesis call into manifest generation.
9. Introduce a database or file mutation into manifest generation.
10. Change item ordering to incidental database order.

For each mutation:

- introduce it temporarily;
- run the smallest relevant test;
- verify that the test fails;
- revert the mutation immediately;
- confirm byte-exact restoration where applicable;
- verify no mutation residue remains.

If a mutation survives, strengthen the test or report it honestly.

Do not claim a mutation was caught unless the relevant test actually failed.

Never commit a deliberate mutation.

## 17. DOCUMENTATION

Create a new implementation report:

`docs/PHASE_5_PART_4_IMPLEMENTATION.md`

Document:

- endpoint and response contract;
- manifest fields and rationale;
- current-track selection rules;
- metadata validation behavior;
- readiness consistency with Part 3;
- read-only guarantees;
- security and project scoping;
- performance considerations;
- files changed;
- tests and assertions;
- mutation results;
- known limitations.

Do not overwrite:

- `docs/PHASE_5_TTS.md`;
- `docs/PHASE_5_PART_3.md`;
- `docs/PHASE_5_PART_3_IMPLEMENTATION.md`;
- existing user documents.

If the locked specification must change, stop and request approval instead of silently editing it.

## 18. VERIFICATION

Before finalizing:

1. Run the new manifest tests.
2. Run relevant Part 3 readiness tests.
3. Run all TTS tests.
4. Run the full backend test suite.
5. Run formatter/linter checks for changed PHP files.
6. Run `git diff --check`.
7. Review the complete diff.
8. Confirm no unrelated files changed.
9. Confirm all mutation experiments were reverted.
10. Verify read-only behavior and synthesis spy results.
11. Verify Part 3 and Part 4 readiness consistency.
12. Record actual final test and assertion counts.

Run TypeScript checks and frontend production build only if necessary to verify that no shared API contract/type regressions occurred. Do not change the frontend as part of this task.

Known pre-existing whole-repository Pint failures:

- `LoginRequest.php`
- `ApiResponse.php`

Do not modify these files merely to make the whole-repository formatter pass. Report the actual results accurately.

If any critical test fails, investigate and fix in-scope defects before declaring PASS.

## 19. GIT RULES

Before committing:

- inspect `git status --short`;
- inspect the complete diff;
- stage only the intended Part 4 implementation files, tests, and `docs/PHASE_5_PART_4_IMPLEMENTATION.md`;
- preserve all pre-existing untracked user documents;
- do not stage everything with `git add -A`;
- do not amend Part 3;
- do not push.

If the implementation and verification pass, create exactly one commit:

`feat: add TTS production manifest`

If unexpected changes, failed critical tests, or unresolved specification conflicts remain, do not commit. Report the blocker.

After a successful commit:

- report the exact commit hash;
- run `git status --short`;
- confirm that no push occurred.

## 20. FINAL REPORT

Return a concise but complete report in Indonesian.

Include:

### Status

PASS / PARTIAL / BLOCKED

### Implementasi

- service and endpoint;
- manifest contract;
- track-selection behavior;
- metadata verification;
- consistency with Part 3 readiness;
- read-only guarantees.

### API

- exact route;
- example response using the actual implementation.

### Verifikasi

- starting test/assertion counts;
- final test/assertion counts;
- focused test results;
- formatter results;
- read-only side-effect results;
- synthesis spy results;
- mutation caught/total;
- cross-project isolation results.

### Git

- commit hash and message;
- final `git status --short`;
- confirmation that no push occurred.

### Changed files and limitations

- principal changed files;
- known issues;
- any deviation from the locked spec.

### Next step

Stop after Part 4. Do not start Part 5.

Never report PASS for a critical behavior that was not verified.
