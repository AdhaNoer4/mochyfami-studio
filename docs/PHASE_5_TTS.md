# Phase 5 — TTS Foundation

## 1. Purpose

Phase 5 introduces the Text-to-Speech (TTS) production layer for MochyFami Content Studio.

The purpose of this phase is to allow a human operator to convert each `VisualPlanItem.narration_text` into a private, measured, traceable audio track while preserving the existing production architecture.

Phase 5 establishes:

- TTS domain persistence
- TTS provider abstraction
- deterministic Fake TTS Provider
- private audio generation
- Asset-based audio storage
- TTS provenance
- narration staleness detection
- TTS readiness evaluation
- TTS production manifest
- Audio production UI
- documentation and verification

Phase 5 does NOT implement:

- subtitles
- timeline editing
- video rendering
- FFmpeg rendering
- audio mixing
- BGM
- automatic narration segmentation
- automatic regeneration
- queue/job infrastructure
- real external TTS providers
- Production Handoff integration

---

# 2. Current Repository Baseline

Phase 1–4 and Pre-Phase 5 Stabilization are complete.

Baseline commit:

`f8de70e`

Current verified baseline:

- 1424 tests
- 4503 assertions
- Pint clean
- TypeScript clean
- Vite production build clean
- Asset status transition available through API/UI
- SPA deep links working
- unauthenticated API consistently returns 401
- Production Specification reaches `ready: true`
- no database workaround required for the production chain

The Phase 5 implementation must preserve this baseline.

---

# 3. Core Architectural Decision

The smallest addressable narration unit is:

`VisualPlanItem`

A `VisualPlanItem` already contains:

- `narration_text`
- `order`
- `duration_seconds`
- `visual_type`
- visual metadata

There is no `ScriptSection` entity.

Therefore:

> TTS MUST attach to `VisualPlanItem`, not directly to `ScriptVersion`.

The relationship is:

```text
ScriptVersion
     │
     ▼
VisualPlan
     │
     ▼
VisualPlanItem
     │
     ├── narration_text
     ├── order
     ├── duration_seconds
     │
     ▼
TtsTrack
     │
     ▼
Asset
     │
     ├── private audio file
     ├── metadata
     └── preview
```

---

# 4. TTS and Asset Boundary

TTS audio is stored through the existing Asset system.

This intentionally reuses:

- `Asset`
- `AssetType::Audio`
- `AssetFileStorageService`
- private `assets` disk
- `AssetMetadataService`
- `AudioMetadataInspector`
- `AssetPolicy`
- asset preview
- project scoping
- MIME validation
- path containment

No second audio-storage system may be introduced.

Storage remains:

```text
assets/{project_id}/{uuid}.{extension}
```

The previously proposed `audio/{project-id}/` structure is rejected because it would duplicate or conflict with existing Asset storage/security behavior.

---

# 5. TTS Asset Candidate Rule

Generated TTS Assets MUST NOT automatically become candidates for normal `AssetRequirement` fulfillment.

Reason:

A generated TTS track is a production artifact, not a user-supplied visual/audio asset intended for Asset Requirement selection.

Therefore:

```text
TTS generation
      │
      ▼
Asset(type=audio)
      │
      ├── available for TTS provenance/preview
      │
      └── NOT an AssetRequirement candidate
```

Existing Asset Requirement readiness and Production Manifest behavior must not change as a side effect of generating TTS.

This is a critical regression boundary.

---

# 6. TTS Domain Model

Phase 5 introduces:

```text
tts_tracks
```

Relationship:

```text
VisualPlanItem 1 ──── * TtsTrack
TtsTrack       * ──── 1 Asset
```

A TTS track stores provenance that cannot be represented by `Asset`.

Recommended fields:

```text
id
content_project_id
visual_plan_item_id
asset_id
provider
model
voice
language
speed
status
duration_seconds
narration_snapshot
narration_hash
error
created_at
updated_at
```

Notes:

- `content_project_id` exists for efficient project scoping.
- `visual_plan_item_id` identifies the narration unit.
- `asset_id` points to the generated audio Asset.
- `provider` records provider identity.
- `model` records provider model when applicable.
- `voice` records the selected voice.
- `language` records language/locale.
- `speed` records synthesis speed.
- `duration_seconds` records measured audio duration.
- `narration_snapshot` records the exact narration used during synthesis.
- `narration_hash` is SHA-256 of the narration snapshot.
- `error` contains safe failure information only.

No API key or secret may ever be stored in this table.

---

# 7. TTS History Policy

TTS generation retains history.

A regeneration creates a new TTS track rather than silently overwriting the previous track.

Example:

```text
Track #1
narration hash A
voice A
completed

Track #2
narration hash A
voice B
completed

Track #3
narration hash B
voice B
completed
```

Do NOT create:

```text
UNIQUE(visual_plan_item_id, narration_hash)
```

because the same narration may legitimately be synthesized again with a different voice/model or as a manual regeneration.

History is valuable for provenance and auditing.

---

# 8. Active TTS Track Rule

The active/current track is determined without introducing an arbitrary mutable `is_active` flag.

For a `VisualPlanItem`:

1. Calculate SHA-256 of current `narration_text`.
2. Find the latest completed TTS track associated with that item whose `narration_hash` equals the current narration hash.
3. That track is the current usable TTS track.
4. If no such track exists, the item has no current TTS track.

This prevents stale audio from silently becoming production audio.

Example:

```text
Current narration hash = ABC

Track 1 → hash ABC → completed
Track 2 → hash XYZ → completed
Track 3 → hash ABC → failed

Current usable track = Track 1
```

If Track 3 is completed later:

```text
Current usable track = Track 3
```

---

# 9. Narration Staleness

Narration changes MUST NOT automatically regenerate audio.

When:

```text
stored narration_hash != SHA256(current narration_text)
```

the existing track is stale.

The system reports:

```text
NARRATION_CHANGED_SINCE_SYNTHESIS
```

as a readiness warning.

The human must explicitly trigger regeneration.

This rule exists to:

- prevent unexpected provider costs
- preserve human control
- preserve auditability
- prevent silent content changes

---

# 10. TTS Status Machine

TTS tracks use an internal status machine.

Recommended states:

```text
pending
generating
completed
failed
archived
```

Expected lifecycle:

```text
pending
   │
   ▼
generating
   │
   ├──────────────► failed
   │
   ▼
completed
   │
   ▼
archived
```

Status transitions are controlled by the TTS generation domain service.

Phase 5 does NOT expose a generic public PATCH status endpoint for TTS.

Manual retry/regeneration uses the explicit regeneration operation instead.

---

# 11. Provider Architecture

The TTS provider architecture follows the existing Research and Script provider patterns.

Proposed structure:

```text
app/Services/TTS/
├── Contracts/
│   └── TtsProvider.php
├── FakeTtsProvider.php
├── TtsProviderRegistry.php
├── TtsGenerationService.php
└── ...
```

Contract:

```text
TtsProvider
├── name()
└── synthesize(TtsRequest): TtsResult
```

Provider resolution must be configuration-driven.

Proposed configuration:

```text
config/tts.php
```

It must follow the existing provider architecture.

Default provider:

```text
fake
```

No provider API key may be required for Phase 5.

No `env()`-based secret should be introduced into the committed provider configuration.

---

# 12. Provider Error Handling

TTS provider failures use a dedicated exception:

```text
TtsProviderException
```

The TTS exception range begins at:

```text
4000+
```

Provider failures exposed through the API map to an appropriate 5xx response, following the existing provider conventions.

The response MUST NOT expose:

- API keys
- authorization headers
- provider secrets
- filesystem paths
- internal stack traces

---

# 13. Fake TTS Provider

Phase 5 MUST use a deterministic Fake TTS Provider.

Requirements:

- no network
- no external API
- no API key
- deterministic output
- identical input produces byte-identical output
- produces a real playable audio file
- output can be inspected by FFprobe

Recommended output:

```text
WAV
PCM
```

The provider may synthesize a minimal RIFF/WAV file directly in PHP.

The fake duration should be deterministic based on narration length.

Recommended approximation:

```text
ceil(word_count / 3)
```

seconds.

This gives approximately 180 words per minute.

The exact algorithm MUST remain deterministic and documented in the implementation.

---

# 14. TTS Request

TTS generation input should include:

```text
visual_plan_item_id
narration_text
provider
model
voice
language
speed
```

The service MUST snapshot the actual narration text before generation.

The provider must never read mutable database state independently.

---

# 15. TTS Result

Provider output should contain enough information for storage:

```text
audio bytes
mime type
extension
provider
model
voice
language
speed
```

The provider does not decide:

- Asset ID
- TtsTrack ID
- storage path
- project authorization
- readiness
- production eligibility

Those remain domain responsibilities.

---

# 16. Generation Workflow

The intended synchronous workflow is:

```text
VisualPlanItem
      │
      ▼
Validate project/version/item scope
      │
      ▼
Validate narration_text
      │
      ▼
Resolve provider
      │
      ▼
Create generating context
      │
      ▼
Provider::synthesize()
      │
      ▼
Create Asset(type=audio)
      │
      ▼
Store private audio file
      │
      ▼
Run AudioMetadataInspector
      │
      ▼
Persist measured duration
      │
      ▼
Create completed TtsTrack
```

Failure:

```text
Provider failure
      │
      ▼
TtsTrack failed
      │
      ▼
safe error
```

No orphaned generated file should remain after a failed generation where cleanup is possible.

---

# 17. Queue Decision

Phase 5 remains synchronous.

Reason:

- current architecture has no active Job/Event/Listener implementation
- script generation is synchronous
- research generation is synchronous
- metadata analysis is synchronous
- Fake TTS is deterministic and lightweight
- introducing queues would add an untested operational architecture

The service should nevertheless be structured so a future queue job can call it without redesigning the domain.

Queue infrastructure is explicitly deferred.

---

# 18. TTS API

The API follows the existing nested project/version/visual-plan conventions.

### Generate

```text
POST
/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts
```

Creates a new TTS track.

Expected successful response:

```text
201
```

### List tracks

```text
GET
/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts
```

Returns tracks for the item.

### Regenerate

```text
POST
/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts/{track}/regenerate
```

Explicitly generates a new track.

It MUST NOT silently mutate the previous track into a new synthesis.

### Delete

```text
DELETE
/api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts/{track}
```

Deletes/archives the TTS track according to the finalized service behavior.

Deletion must not violate Asset ownership or leave unauthorized access.

No public status PATCH endpoint is required.

---

# 19. Authorization

TTS endpoints must enforce:

1. authentication
2. project scoping
3. script version scoping
4. visual plan scoping
5. visual plan item scoping
6. appropriate update/view authorization

Cross-project access must resolve to:

```text
404
```

rather than exposing resource existence.

No endpoint may accept a TTS track ID and then retrieve it globally.

---

# 20. TTS Resource

The TTS resource may expose:

```text
id
visual_plan_item_id
asset_id
provider
model
voice
language
speed
status
duration_seconds
narration_hash
narration_snapshot
created_at
updated_at
```

It must NOT expose:

```text
file_path
storage_path
absolute_path
filesystem location
provider credentials
```

Audio access continues through the existing authenticated Asset preview mechanism.

---

# 21. Audio Storage

TTS audio uses:

```text
AssetType::Audio
```

and the existing private Asset storage.

No new public storage disk is introduced.

No `Storage::url()` may be introduced for TTS files.

The existing guarded preview endpoint remains the access mechanism.

---

# 22. Audio Metadata

Phase 5 only depends on:

```text
duration_seconds
```

Do NOT extend the shared metadata contract with:

- sample rate
- channels
- bitrate
- codec
- volume

Those may be introduced later only when a concrete downstream requirement exists.

Existing:

```text
AudioMetadataInspector
FfprobeMetadataInspector
AssetMetadataService
```

must be reused.

---

# 23. TTS Readiness

TTS gets its own standalone readiness service.

It does NOT modify:

```text
AssetProductionHandoffService
```

in Phase 5.

Proposed service:

```text
TtsReadinessService
```

Endpoint:

```text
GET
/api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-readiness
```

The result is read-only.

---

# 24. TTS Readiness Blockers

Initial blocker vocabulary:

```text
NO_VISUAL_PLAN
NO_VISUAL_PLAN_ITEMS
ITEM_WITHOUT_NARRATION
TTS_GENERATION_FAILED
PROVIDER_UNAVAILABLE
```

Additional blocker codes may only be introduced when required by an actual implementation rule.

No subjective AI quality score is introduced.

---

# 25. TTS Readiness Warnings

Initial warnings:

```text
NARRATION_CHANGED_SINCE_SYNTHESIS
TRACK_DURATION_MISMATCH
ITEM_WITHOUT_TRACK
METADATA_NOT_VERIFIED
LONG_NARRATION
```

Warnings do not automatically make the TTS readiness result false unless the finalized implementation explicitly defines otherwise.

---

# 26. TTS Readiness Rule

Core rule:

```text
ready = blockers === []
```

The readiness service is a projection.

It must not:

- generate audio
- mutate tracks
- modify assets
- regenerate narration
- modify VisualPlan
- modify Production Specification
- modify Production Handoff

---

# 27. TTS Manifest

Phase 5 introduces a separate TTS production manifest.

Proposed endpoint:

```text
GET
/api/v1/projects/{project}/script/versions/{version}/visual-plan/tts-manifest
```

It remains a read-only projection.

The manifest contains only current usable TTS tracks.

Suggested segment structure:

```text
{
    visual_plan_item_id,
    visual_plan_item_order,
    tts_track_id,
    asset_id,
    duration_seconds,
    narration_hash,
    provider,
    model,
    voice,
    language,
    speed
}
```

Ordering:

```text
visual_plan_item.order ASC
```

No timeline offsets are generated.

No FFmpeg configuration is generated.

No subtitle timing is generated.

---

# 28. Production Specification Boundary

Phase 5 MUST NOT modify:

```text
AssetProductionSpecificationService
```

to add audio.

Production Specification currently represents visual production specification and intentionally has no audio/timeline concerns.

TTS gets its own manifest.

Future rendering phases may compose:

```text
Production Specification
+
TTS Manifest
+
Subtitle Manifest
```

without retroactively changing the meaning of existing Phase 4 projections.

---

# 29. Visual Plan Granularity

The default seed currently creates approximately three VisualPlanItems:

```text
hook
body
closing
```

The body may contain a large amount of narration.

Phase 5 does NOT introduce an automatic narration splitter.

The human operator remains responsible for splitting VisualPlanItems.

TTS may emit:

```text
LONG_NARRATION
```

as a warning.

A future phase may introduce dedicated narration segmentation/editing.

---

# 30. Frontend Architecture

Add:

```text
Audio
```

to the Project Detail tab system.

Existing pipeline configuration already contains an Audio step.

Create:

```text
components/projects/AudioPanel.tsx
services/ttsService.ts
```

Update:

```text
types/index.ts
ProjectDetailPage.tsx
VisualPlanPanel.tsx
ProductionPage.tsx
```

Minimal UI requirements:

- narration item list
- order
- narration excerpt
- current TTS status
- measured duration
- Generate
- Regenerate
- Play/preview
- loading state
- error state
- success feedback

Do NOT redesign:

- Visual Plan
- Asset Library
- Project Detail layout

VisualPlanItem may receive only a compact TTS status indicator.

---

# 31. Frontend Error Handling

Reuse the existing error extraction conventions.

Do not introduce a third independent API error parsing implementation.

AudioPanel should follow the existing panel conventions:

- loading
- error
- success
- cancellation-safe async handling

No new global toast architecture is introduced.

---

# 32. Security Requirements

Every TTS feature must preserve:

- project isolation
- authenticated access
- private storage
- MIME validation
- path containment
- no file path leakage
- no public URL
- no provider secret persistence
- safe provider errors
- no arbitrary shell execution

TTS must not create a second file-serving mechanism.

---

# 33. Testing Requirements

Every Phase 5 Part must include focused tests.

Required areas:

### Domain

- status transitions
- narration hashing
- active-track resolution
- provider resolution

### Provider

- deterministic output
- byte-identical output
- duration calculation
- invalid provider handling

### Generation

- successful generation
- Asset creation
- private file creation
- metadata measurement
- track creation
- provider failure
- no orphan file
- cross-project rejection
- authorization

### Staleness

- unchanged narration → usable
- changed narration → warning
- regenerated track → new current track

### Readiness

Every blocker and warning code must have explicit tests.

### Manifest

- correct ordering
- current track selection
- no stale track
- no cross-project data
- read-only behavior

### Frontend

At minimum:

```text
tsc --noEmit
npm run build
```

Manual UI smoke test is required because the repository currently has no browser E2E framework.

---

# 34. Mutation Testing

Mutation testing follows the existing manual project procedure.

Required mutation categories include:

1. remove narration hash comparison
2. remove project scoping
3. relax provider resolution
4. remove authorization
5. bypass current-track selection
6. remove provider failure handling
7. remove TTS Asset candidate exclusion

Every mutation must:

1. fail the relevant test
2. be restored
3. return repository to byte-identical state

---

# 35. Phase Breakdown

## Part 1 — TTS Domain & Provider Foundation

Purpose:

Establish TTS domain and provider abstraction.

Includes:

- `tts_tracks` migration
- `TtsTrackStatus`
- `TtsTrack`
- relationships
- `TtsProvider`
- `TtsRequest`
- `TtsResult`
- `FakeTtsProvider`
- `TtsProviderRegistry`
- `config/tts.php`
- `TtsProviderException`
- service provider binding

No API.

No frontend.

Tests required.

---

## Part 2 — TTS Generation & Asset Storage

Purpose:

Generate real audio and persist it as a private Asset.

Includes:

- `TtsGenerationService`
- generation endpoint
- list endpoint
- regeneration endpoint
- delete/archive endpoint
- Asset creation
- private storage
- FFprobe metadata
- TtsTrack persistence
- failure cleanup

No frontend yet.

---

## Part 3 — TTS Readiness

Purpose:

Create standalone TTS production readiness.

Includes:

- `TtsReadinessService`
- readiness Resource
- readiness Controller
- readiness route
- blocker/warning codes
- deterministic score if retained
- tests

Must not modify Production Handoff.

---

## Part 4 — TTS Manifest

Purpose:

Create the production-facing TTS projection.

Includes:

- `TtsManifestService`
- Resource
- Controller
- endpoint
- deterministic ordering
- current-track filtering
- tests

Must not modify Production Specification.

---

## Part 5 — Audio Frontend

Purpose:

Make the TTS workflow usable through the application UI.

Includes:

- Audio tab
- AudioPanel
- TTS service
- TypeScript types
- Generate
- Regenerate
- Play
- status display
- error/loading/success handling
- compact Visual Plan status
- ProductionPage copy correction

---

## Part 6 — Documentation & Final Verification

Purpose:

Close Phase 5 using the same verification standard as Phase 4.

Includes:

- Phase 5 documentation
- implementation documentation
- testing checklist updates
- README architecture/setup updates where appropriate
- full test suite
- Pint
- TypeScript
- build
- mutation testing
- security review
- live API smoke
- live UI smoke
- production-chain regression

---

# 36. Explicitly Out of Scope

The following MUST NOT be implemented during Phase 5:

- subtitles
- SRT generation
- ASS generation
- VTT generation
- subtitle timing
- video rendering
- FFmpeg rendering
- audio mixing
- BGM
- waveform editing
- timeline editor
- automatic narration splitting
- automatic regeneration
- real TTS providers
- API key management
- queue workers
- Redis
- background jobs
- Production Handoff TTS integration
- Production Specification audio integration
- new general-purpose Asset architecture
- redesign of Asset Library
- changes to Script revision architecture

---

# 37. Files That Must Remain Untouched Unless Strictly Required

Phase 5 should not modify:

```text
app/Enums/AssetStatus.php
app/Models/Script.php
app/Models/ScriptVersion.php
app/Services/ScriptService.php
app/Services/AssetCompatibilityEvaluator.php
app/Services/AssetFulfillmentQualityService.php
app/Services/AssetProductionManifestService.php
app/Services/AssetProductionHandoffService.php
app/Services/AssetProductionSpecificationService.php
app/Services/VisualPlanQualityService.php
```

and existing Phase 4 migrations.

No migration may alter existing Phase 4 tables merely to simplify TTS.

---

# 38. Migration Rules

Part 1 may introduce:

```text
create_tts_tracks_table
```

No Phase 5 migration may modify existing production-domain tables unless a later architectural decision proves it unavoidable.

Prefer relationships and services over retroactive schema changes.

---

# 39. API Conventions

Follow existing project conventions:

- `/api/v1`
- `auth:sanctum`
- nested project routes
- `whereNumber()`
- FormRequest validation
- Gate authorization
- Services for domain logic
- Resources for response formatting
- ApiResponse envelope
- 404 for cross-project resources
- 422 for domain validation/transition errors
- 5xx only for provider/system failures

No global API architecture rewrite.

---

# 40. Performance

Phase 5 must avoid obvious N+1 queries.

For readiness and manifest:

- eager-load required relations
- do not resolve each TTS track independently
- avoid provider calls during read-only readiness
- avoid filesystem scans

Readiness and manifest must remain deterministic and read-only.

---

# 41. E2E Acceptance

At the end of Phase 5, the following workflow must work without database manipulation:

```text
Approved Script Version
        ↓
Visual Plan
        ↓
Visual Plan Items
        ↓
Human narration text
        ↓
Audio Panel
        ↓
Generate TTS
        ↓
Private Audio Asset
        ↓
FFprobe duration
        ↓
Completed TtsTrack
        ↓
TTS Readiness
        ↓
TTS Manifest
        ↓
Authenticated Audio Preview
```

Required checks:

1. Generate audio through application API/UI.
2. Audio file exists privately.
3. Asset does not expose file path.
4. Audio preview works.
5. Duration is measured.
6. TTS track contains provenance.
7. Cross-project access returns 404.
8. Unauthenticated access returns 401.
9. Changing narration creates a stale warning.
10. Regeneration is explicitly human-triggered.
11. New regeneration creates a new history record.
12. Old track remains auditable.
13. TTS Asset does not alter existing Asset Requirement readiness.
14. TTS Manifest contains only the current usable track.
15. Existing Production Specification remains unchanged.
16. Existing Production Handoff remains unchanged.
17. Full regression suite remains green.

---

# 42. Verification Baseline

Before implementation:

```text
1424 tests
4503 assertions
```

After each Part:

- focused tests
- full regression where appropriate
- Pint
- TypeScript
- build

At Phase 5 completion, the final report must explicitly compare against the baseline.

No regression may be hidden by updating expected values without architectural justification.

---

# 43. Commit Policy

Each Part should use one focused commit.

Do not push unless explicitly requested.

Commit messages should clearly identify the Phase 5 Part.

At final Phase 5 completion, provide:

- commit hashes
- test counts
- assertion counts
- Pint result
- TypeScript result
- build result
- mutation results
- E2E results
- remaining limitations

---

# 44. Final Architectural Principle

Phase 5 follows the project's existing production philosophy:

> AI assists production; humans control production.

TTS generation is an explicit production action.

The system must never silently:

- regenerate narration
- replace audio
- modify script text
- change VisualPlan
- fulfill Asset Requirements
- change Production Handoff
- publish content

The TTS layer is a deterministic, auditable production capability that prepares audio for future Subtitle and Video Rendering phases.
