# PHASE 5 — PART 2: TTS SYNTHESIS, PRIVATE STORAGE & API

## MODE

Implementation mode.

Phase 5 specification sudah disetujui dan dikunci di:

`docs/PHASE_5_TTS.md`

Phase 5 Part 1 sudah selesai:

```text
8283fa2 feat: add TTS domain and provider foundation
```

Baseline setelah Part 1:

```text
1508 tests
4627 assertions
```

Part 1 berhasil:

- TtsTrack persistence
- TtsTrackStatus
- TtsTrack model
- NarrationHasher
- TtsRequest / TtsResult
- TtsProvider contract
- FakeTtsProvider
- TtsProviderRegistry
- config/tts.php
- TtsProviderException
- container binding
- mutation tests

**Implement Part 2 ONLY.**

Jangan mengerjakan Part 3–6.
Jangan membuat TTS readiness.
Jangan membuat TTS manifest.
Jangan membuat frontend AudioPanel.
Jangan membuat real provider.
Jangan menggunakan queue.
Jangan push.

---

# 1. PART 2 OBJECTIVE

Bangun synchronous TTS generation pipeline:

```text
VisualPlanItem
      ↓
TTS Request
      ↓
TtsProviderRegistry
      ↓
TtsProvider
      ↓
TtsResult
      ↓
private WAV file
      ↓
AssetType::Audio
      ↓
TtsTrack
      ↓
metadata / preview
```

Part 2 harus menyediakan API untuk:

1. generate TTS
2. melihat TTS tracks
3. regenerate TTS
4. archive/delete TTS track
5. attach/select Asset guard agar TTS-generated Assets tidak dapat menjadi AssetRequirement candidate

---

# 2. BASELINE

Current expected baseline:

```text
commit: 8283fa2
tests: 1508
assertions: 4627
```

Sebelum coding:

```bash
git status
git log -1 --oneline
```

Working tree memiliki untracked documentation files milik user.

JANGAN:

- modify
- delete
- rename
- stage
- commit

file berikut:

```text
docs/Plan.md
docs/pre_phase_5.md
docs/testing.md
docs/PHASE_5_REPO_DISCOVERY.md
docs/PHASE_5_TTS.md
docs/PHASE_5_PART_1.md
```

Jika ada `docs/PHASE_5_PART_1_IMPLEMENTATION.md`, perlakukan juga sebagai user/project documentation dan jangan commit kecuali memang sudah tracked dan Part 2 secara eksplisit membutuhkannya.

---

# 3. REQUIRED INSPECTION BEFORE EDITING

Sebelum membuat perubahan, inspect:

```text
app/Services/Assets/
app/Models/Asset.php
app/Models/AssetRequirement.php
app/Services/AssetRequirementService.php
app/Services/Assets/AssetFileStorageService.php
app/Services/Assets/AssetMetadataService.php
app/Services/Assets/AudioMetadataInspector.php
app/Http/Resources/AssetResource.php
app/Http/Controllers/
app/Http/Requests/
app/Policies/
routes/api.php
resources/js/services/
tests/Feature/
tests/Unit/
```

Juga inspect:

```text
VisualPlanService
VisualPlanItem
ContentProject
ScriptVersion
TtsTrack
TtsProviderRegistry
TtsProvider
FakeTtsProvider
```

Cari dan ikuti pola existing untuk:

- project/version/item scoping
- FormRequest
- Gate authorization
- ApiResponse
- Resource
- service exception handling
- Asset creation
- Asset file storage
- metadata inspection
- private preview
- route naming
- nested routes
- cross-project 404
- feature tests
- mutation tests

**Jangan membuat parallel implementation jika existing Asset services sudah menyediakan capability yang dibutuhkan.**

---

# 4. IMPORTANT ARCHITECTURE RULE

TTS memiliki dua domain object:

```text
TtsTrack
Asset
```

`TtsTrack` menyimpan:

- provider
- model
- voice
- language
- speed
- status
- duration
- narration snapshot
- narration hash
- error
- provenance

`Asset` menyimpan:

- generated audio file
- MIME
- storage information
- metadata
- preview capability

Jangan menaruh provider/voice/narration provenance ke Asset.

Jangan membuat audio file storage terpisah dari Asset storage abstraction.

---

# 5. GENERATION SERVICE

Buat service khusus, mengikuti naming/convention service repository.

Contoh:

```text
app/Services/TTS/TtsGenerationService.php
```

Service bertanggung jawab atas orchestration:

```text
resolve project/version/item
→ validate narration
→ calculate narration hash
→ resolve provider
→ create TtsTrack
→ generating
→ synthesize
→ store Asset
→ inspect metadata
→ update TtsTrack completed
```

Jangan menaruh orchestration ini di controller.

Controller harus tipis.

---

# 6. INPUT SCOPE

TTS generation harus selalu terikat pada:

```text
ContentProject
ScriptVersion
VisualPlan
VisualPlanItem
```

Gunakan existing service methods/patterns jika tersedia.

Request:

```text
project
version
visual_plan_item
```

harus menghasilkan **404** apabila item tidak berada dalam requested project/version scope.

Jangan membiarkan:

```text
Project A
+
Project B VisualPlanItem
```

diproses bersama.

Cross-project resource harus tetap 404 sesuai Phase 4 security convention.

---

# 7. VISUAL PLAN REQUIREMENTS

Generation hanya boleh dilakukan untuk valid VisualPlanItem.

Inspect existing VisualPlanItem model/service rules.

Minimal requirement:

- item exists
- item belongs to requested VisualPlan
- visual plan belongs to requested ScriptVersion
- script version belongs to requested ContentProject

Narration:

```text
narration_text
```

harus tersedia.

Jika narration kosong/null/whitespace:

jangan panggil provider.

Kembalikan domain/API validation error sesuai convention existing.

Jangan membuat track `completed` untuk narration kosong.

---

# 8. NARRATION SNAPSHOT

Ketika generation dimulai:

```text
narration_snapshot = exact item.narration_text
```

Jangan normalize.

Jangan trim untuk nilai snapshot.

Jangan mengubah case.

Jangan mengubah punctuation.

`narration_hash` harus:

```text
sha256(exact narration_snapshot)
```

Gunakan `NarrationHasher` dari Part 1.

Jangan menduplikasi hashing logic.

---

# 9. PROVIDER RESOLUTION

Gunakan:

`TtsProviderRegistry`

Provider selection harus mengikuti config/provider convention dari Part 1.

Default provider:

```text
fake
```

Jangan:

- hard-code FakeTtsProvider di generation service
- instantiate provider langsung
- call `new FakeTtsProvider()`
- use `env()` directly
- make HTTP calls

Generation service harus bergantung pada provider abstraction/registry.

---

# 10. PROVIDER FAILURE

Jika provider tidak tersedia:

```text
TtsProviderException
```

harus diterjemahkan menjadi HTTP:

```text
502
```

sesuai Phase 5 specification.

Jika provider synthesis gagal:

- TtsTrack harus menjadi `failed`
- error harus disimpan secara aman
- jangan expose stack trace
- jangan expose API key
- jangan expose internal filesystem path
- jangan expose raw provider credentials

Jangan meninggalkan Track pada status `generating` setelah known failure.

---

# 11. TRACK LIFECYCLE

Generation pertama:

```text
pending
→ generating
→ completed
```

atau:

```text
pending
→ generating
→ failed
```

Gunakan `TtsTrackStatus` state machine dari Part 1.

Jangan bypass state machine.

Jangan langsung assign arbitrary status jika existing domain convention menyediakan transition method.

---

# 12. REGENERATION

Regeneration harus membuat **TtsTrack baru**.

Jangan overwrite historical TtsTrack.

Contoh:

```text
Track 1
hash ABC
completed

Regenerate

Track 2
hash ABC
generating
→ completed
```

Track 1 tetap ada.

Jika narration berubah:

```text
Track 1
hash ABC
completed

VisualPlanItem narration → XYZ

Regenerate

Track 2
hash XYZ
completed
```

Track 1 tetap historical.

---

# 13. NO AUTOMATIC REGENERATION

Tidak boleh ada automatic regeneration.

Jangan:

- regenerate ketika narration berubah
- regenerate saat readiness dibaca
- regenerate saat Asset preview dibuka
- regenerate saat TTS list dibaca
- regenerate saat page load
- regenerate melalui queue

Regeneration hanya terjadi melalui explicit human API request.

---

# 14. TTS ASSET CREATION

Ketika provider menghasilkan:

```text
TtsResult
```

buat Asset baru dengan:

```text
AssetType::Audio
```

Gunakan existing Asset creation pattern.

Jangan membuat custom parallel AudioAsset model.

Jangan membuat TTS-specific storage table.

---

# 15. ASSET BOUNDARY

TTS Asset:

```text
AssetType::Audio
```

harus menjadi Asset biasa dari sisi storage/file metadata.

Tetapi TTS Asset mempunyai special rule:

> TTS-generated Assets TIDAK boleh menjadi candidate AssetRequirement.

Jangan mengubah general Asset type semantics.

Implement guard eksplisit di:

```text
AssetRequirementService::attachAsset()
```

dan:

```text
AssetRequirementService::selectAsset()
```

sesuai decision yang sudah dikunci.

---

# 16. TTS ASSET DETECTION

Jangan mendeteksi TTS Asset hanya berdasarkan:

```text
asset_type === audio
```

Karena Audio Asset biasa harus tetap dapat digunakan sebagai AssetRequirement candidate.

Deteksi harus berdasarkan relasi/reference:

```text
asset referenced by tts_tracks
```

Artinya:

```text
Asset
  ↓
tts_tracks
```

Jika Asset direferensikan oleh satu atau lebih TtsTrack:

```text
NOT eligible as AssetRequirement candidate
```

Jika Asset Audio biasa tidak direferensikan oleh TtsTrack:

```text
candidate eligibility tetap mengikuti existing rules
```

---

# 17. ATTACH GUARD

Pada:

`AssetRequirementService::attachAsset()`

tambahkan explicit guard.

Jika Asset merupakan TTS Asset:

tolak attach.

Gunakan domain exception/error sesuai convention existing.

Jangan silently ignore request.

Jangan attach lalu detach.

Jangan mengubah AssetRequirement.

---

# 18. SELECT GUARD

Pada:

`AssetRequirementService::selectAsset()`

tambahkan guard yang sama.

TTS Asset tidak boleh selected sebagai requirement candidate.

Pastikan guard juga menangkap mutation:

```text
hapus guard
```

dan targeted test harus gagal.

---

# 19. STORAGE

Gunakan existing:

`AssetFileStorageService`

dan private Asset storage.

Storage path:

```text
assets/{content_project_id}/{uuid}.{ext}
```

**JANGAN membuat:**

```text
audio/{project-id}/...
tts/{project-id}/...
public/audio/...
storage/app/public/...
```

Phase 5 discovery sudah menetapkan reuse existing Asset storage.

Jika spec memiliki referensi `audio/{project-id}/`, repository architecture memenangkan keputusan ini.

Dokumentasikan deviasi hanya pada Part 2 implementation documentation jika repository convention memang membutuhkan dokumentasi tersebut.

---

# 20. PRIVATE STORAGE

Audio harus tetap private.

Jangan:

```php
Storage::url(...)
```

Jangan:

```text
public disk
```

Jangan expose:

```text
storage_path
file_path
absolute path
filesystem root
```

di API Resource.

Preview harus reuse existing Asset preview endpoint/capability.

---

# 21. FILE NAMING

Gunakan existing Asset storage naming convention.

Jangan menggunakan:

```text
voice-name.wav
narration.wav
item-1.wav
```

Jika existing storage service menggunakan UUID/random-safe filename, gunakan itu.

Tujuan:

- avoid collisions
- avoid predictable paths
- preserve private storage

---

# 22. MIME / EXTENSION

FakeTtsProvider menghasilkan WAV.

Expected:

```text
mime_type = audio/wav
extension = wav
```

Tetapi jangan blindly trust provider output if existing Asset creation pipeline validates MIME.

Gunakan existing MIME allowlist.

Jangan memperluas MIME allowlist kecuali repository saat ini memang tidak mendukung WAV.

Jika WAV belum ada di allowlist:

STOP dan report before changing the allowlist unless Phase 5 specification explicitly requires the change.

---

# 23. ASSET METADATA

Setelah file disimpan:

jalankan existing metadata pipeline.

Gunakan:

`AssetMetadataService`

dan:

`AudioMetadataInspector`

sesuai architecture existing.

TTS duration harus diverifikasi dari stored audio metadata.

Jangan hanya percaya:

```text
TtsResult.duration_seconds
```

untuk final persisted duration.

Expected flow:

```text
provider result duration
        ↓
store file
        ↓
inspect actual audio
        ↓
metadata duration
        ↓
TtsTrack.duration_seconds
```

---

# 24. FFPROBE

Part 2 adalah tahap pertama yang boleh melakukan integration verification terhadap audio metadata.

Jika existing AudioMetadataInspector menggunakan ffprobe:

gunakan implementation existing.

Jangan membuat custom ffprobe command.

Jangan shell out langsung dari TtsGenerationService.

Jangan build arbitrary shell commands dari user input.

---

# 25. METADATA FAILURE

Jika file berhasil disimpan tetapi metadata inspection gagal:

ikuti existing Asset metadata semantics.

Namun TtsTrack tidak boleh dianggap successfully completed dengan metadata yang tidak sesuai jika Phase 5 contract membutuhkan verified duration.

Inspect existing AssetMetadataService behavior terlebih dahulu.

Jika repository pattern memungkinkan Asset tetap tersimpan dengan:

```text
metadata_status = failed
```

ikuti pattern tersebut.

Tetapi jangan menyembunyikan metadata failure dari TTS track.

Tentukan behavior berdasarkan existing Asset pipeline dan `docs/PHASE_5_TTS.md`.

Jangan invent custom metadata state machine.

---

# 26. TTS TRACK ↔ ASSET RELATION

Setelah Asset berhasil dibuat:

```text
tts_tracks.asset_id = asset.id
```

Track menjadi:

```text
completed
```

hanya setelah generation/storage pipeline berhasil sesuai contract.

Asset harus memiliki:

```text
content_project_id
AssetType::Audio
```

yang sama dengan project TTS.

Cross-project asset association harus ditolak.

---

# 27. TRANSACTION / FAILURE CLEANUP

Inspect existing transaction/storage patterns.

Generation melibatkan:

```text
database
+
filesystem
```

Jangan menganggap DB transaction otomatis rollback filesystem.

Design failure cleanup explicitly.

Example:

```text
provider success
→ file write success
→ DB failure
```

jangan meninggalkan orphaned generated file jika existing storage abstraction memungkinkan cleanup.

Sebaliknya:

```text
DB track created
→ provider failure
```

track harus menjadi failed.

Jangan delete historical failed TtsTrack.

Gunakan existing cleanup abstraction jika tersedia.

Jangan membuat filesystem cleanup logic tersebar di controller.

---

# 28. CURRENT TRACK RULE

Part 2 harus menyediakan data yang nantinya memungkinkan readiness menentukan current usable track.

Current usable track:

```text
latest completed TtsTrack
WHERE
visual_plan_item_id = current item
AND
narration_hash = sha256(current narration_text)
```

Jika tidak ada:

```text
no current usable track
```

Stale completed tracks dengan hash lama:

```text
tidak boleh dianggap current
```

Part 2 tidak perlu membuat ReadinessService.

Tetapi API response harus menyediakan cukup data untuk membedakan:

- current narration hash
- track narration hash
- status
- duration
- provider
- voice
- model
- asset

---

# 29. API ENDPOINTS

Implement:

### Generate

```http
POST /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts
```

### List / inspect tracks

```http
GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts
```

### Regenerate

```http
POST /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts/{track}/regenerate
```

### Archive

```http
DELETE /api/v1/projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts/{track}
```

Follow existing route conventions.

Use:

```text
auth:sanctum
```

and:

```text
whereNumber(...)
```

where repository convention requires it.

---

# 30. NO PUBLIC STATUS ENDPOINT

DO NOT create:

```http
PATCH .../tts/{track}/status
```

There is intentionally no public status mutation endpoint.

Status is controlled internally by generation lifecycle.

---

# 31. GENERATE ENDPOINT SEMANTICS

Generate endpoint must explicitly create a new TtsTrack.

Do not silently reuse existing completed track.

Example:

```text
POST generate
→ Track 1
```

Second explicit generate:

```text
POST generate
→ Track 2
```

Both may exist.

No automatic deduplication.

No unique narration hash constraint.

---

# 32. REGENERATE ENDPOINT SEMANTICS

Regenerate endpoint:

```text
POST .../{track}/regenerate
```

must create a **new TtsTrack**.

Do not mutate the original Track's:

- narration_snapshot
- narration_hash
- provider
- voice
- duration
- asset_id

The original track remains auditable.

---

# 33. REGENERATE SOURCE

Regeneration should use the **current VisualPlanItem narration**.

Do not blindly synthesize:

```text
oldTrack.narration_snapshot
```

if the VisualPlanItem narration has changed.

Example:

```text
Track 1 snapshot = "Hello world"

VisualPlanItem = "Hello Indonesia"

POST regenerate Track 1

New Track snapshot must be:
"Hello Indonesia"
```

This is important for staleness handling.

---

# 34. REGENERATE TRACK VALIDATION

The requested track must:

- belong to requested item
- belong to requested visual plan
- belong to requested version
- belong to requested project

Otherwise:

```text
404
```

Do not return 403 for cross-project resource leakage.

---

# 35. ARCHIVE / DELETE SEMANTICS

The DELETE endpoint does NOT physically delete the TTS Track.

It means:

```text
status → archived
```

Asset remains.

Audio file remains.

Historical provenance remains.

Example:

```text
DELETE Track 1

Track 1.status = archived
Track 1.asset_id remains
Asset remains
file remains
```

Do not call:

```text
Model::delete()
```

for TtsTrack.

Do not delete the Asset.

---

# 36. ARCHIVED TRACK RULES

Archived track:

- remains in database
- remains auditable
- remains linked to Asset
- must not be considered current usable TTS
- cannot be regenerated by changing its status

Regeneration always creates a new track.

---

# 37. DELETE IDEMPOTENCY

Inspect existing API delete/archive conventions.

If already archived:

follow repository convention.

Do not invent a new behavior.

Possible expected behavior:

```text
422 invalid transition
```

or idempotent success.

Use repository/state-machine convention and tests accordingly.

---

# 38. TTS RESOURCE

Create a dedicated Resource, for example:

```text
TtsTrackResource
```

Do not expose:

```text
file_path
storage_path
absolute filesystem path
```

Allowed fields include:

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
status_label
duration_seconds
narration_snapshot
narration_hash
error
created_at
updated_at
```

If useful for frontend:

```text
is_current
is_stale
preview_url
```

may be exposed.

But:

`preview_url`

must reuse authenticated/private Asset preview mechanism.

Do not expose public storage URLs.

---

# 39. ERROR FIELD SECURITY

Never expose raw exception stack traces.

Never expose:

- API key
- authorization header
- filesystem path
- provider credentials
- SQL query
- internal object dump

If existing project sanitizes provider errors, use that mechanism.

---

# 40. AUTHORIZATION

Follow existing Gate convention.

Generation/regeneration/archive are mutations.

Use appropriate:

```text
Gate::authorize('update', ...)
```

or the equivalent project-level policy used by existing VisualPlan/Asset mutation endpoints.

Read/list uses appropriate view authorization.

Do not create a new policy if existing project policy can authorize the operation.

If TtsTrack itself needs policy, inspect repository architecture before creating one.

---

# 41. CROSS-PROJECT SECURITY TESTS

Mandatory:

```text
Project A
VisualPlanItem A
TtsTrack A
Asset A

Project B request
→ 404
```

Test cross-project cases for:

- GET
- POST generate
- POST regenerate
- DELETE/archive

Also test:

```text
unknown item → 404
unknown track → 404
track belonging to another item → 404
track belonging to another project → 404
```

Do not leak existence through 403.

---

# 42. UNAUTHENTICATED API

All new endpoints must preserve stabilized behavior:

```text
401
```

with:

```json
{
    "message": "Unauthenticated."
}
```

Test both:

- normal request
- `Accept: application/json`

Do not regress the pre-Phase-5 auth fix.

---

# 43. API RESPONSE CONVENTION

Follow existing:

```text
ApiResponse
```

and current API envelope.

Do not invent a new response envelope.

Check existing endpoints before implementation.

---

# 44. GENERATION RESPONSE

Generation should return the newly created TtsTrack resource.

Do not return:

```text
raw audio binary
```

from the generation endpoint.

Do not return:

```text
file_path
```

Return metadata/resource representation.

---

# 45. LIST RESPONSE

GET TTS tracks should preserve history.

Example conceptual ordering:

```text
newest first
```

or existing repository resource ordering convention.

Do not hide:

```text
failed
archived
completed
```

tracks.

History is intentional.

---

# 46. PREVIEW

Do NOT create a new TTS preview endpoint in Part 2 if existing:

```text
assets.preview
```

can preview the generated Audio Asset.

Reuse existing authenticated/private preview mechanism.

The TTS Resource may expose `preview_url` pointing to the existing Asset preview route if that is already the repository convention.

Do not duplicate preview authorization/storage logic.

---

# 47. ASSET LIBRARY

Do not build Audio UI in Part 2.

Do not add TTS-specific frontend.

However, inspect whether Asset Library automatically displays all Assets.

If TTS Assets would appear there automatically, **do not remove them or change Asset Library behavior in Part 2 unless required for security**.

The requirement is specifically:

```text
TTS Assets must not become AssetRequirement candidates
```

not necessarily:

```text
TTS Assets must be hidden from Asset Library
```

Do not invent a visibility rule.

---

# 48. ASSET REQUIREMENT REGRESSION

This is critical.

Existing normal Asset behavior must remain intact.

Test:

```text
normal image Asset
→ candidate

normal video Asset
→ candidate when compatible

normal audio Asset
→ candidate when compatible

TTS-generated audio Asset
→ NOT candidate
→ attach rejected
→ select rejected
```

Do not break unrelated candidate filtering.

---

# 49. TTS ASSET DETECTION QUERY

Implement detection efficiently.

Avoid:

```php
foreach every asset:
    query tts_tracks
```

N+1 behavior.

Use an appropriate:

- `exists()`
- relationship query
- `whereHas`
- preload

depending on existing service architecture.

Do not introduce a global Asset scope that hides TTS Assets.

---

# 50. TTS ASSET MUTATION TEST

At minimum mutate:

```text
TTS asset guard removed from attachAsset
```

Expected:

```text
targeted test FAILS
```

Then restore.

Also mutate:

```text
TTS asset guard removed from selectAsset
```

Expected:

```text
targeted test FAILS
```

Restore exact original.

---

# 51. GENERATION TEST MATRIX

Create focused Feature tests.

### Successful generation

```text
valid project
valid version
valid visual plan
valid item
valid narration
```

Expected:

```text
HTTP 200/201 according to repository convention
TtsTrack completed
Asset created
AssetType::Audio
asset_id populated
private storage file exists
duration populated
narration_snapshot correct
narration_hash correct
provider correct
voice correct
```

### Provider failure

Expected:

```text
TtsTrack failed
HTTP 502
safe error
no leaked credentials/path
```

### Empty narration

Expected:

```text
provider not called
validation/domain error
no completed track
```

### Regeneration

Expected:

```text
new Track
old Track preserved
new Asset
old Asset preserved
```

### Narration changed

Expected:

```text
new track narration_snapshot = current narration
new hash != old hash
```

### Archive

Expected:

```text
status archived
Asset preserved
file preserved
```

---

# 52. STORAGE TESTS

Verify:

- file is stored on private disk
- file path follows existing `assets/{project_id}/...`
- file extension `.wav`
- MIME `audio/wav`
- no public URL
- Asset references correct project
- file survives Track archive

Do not assert exact UUID filename.

Assert safe path/containment through existing storage service behavior.

---

# 53. METADATA TESTS

Verify:

```text
Fake provider duration
```

matches actual inspected WAV duration.

For example:

```text
180 words
→ provider = 60 sec
→ stored WAV = 60 sec
→ metadata inspector = 60 sec
→ TtsTrack.duration_seconds = 60
```

Also test another duration to ensure it is not accidentally hard-coded.

---

# 54. CURRENT / STALE TESTS

Part 2 does not implement ReadinessService, but tests should establish data semantics.

Example:

```text
Track A
hash ABC
completed

item narration = ABC
→ Track A is current

item narration changes to XYZ
→ Track A is stale
```

If Resource exposes `is_current`, test it.

If current calculation is intentionally deferred to ReadinessService, do not invent duplicate readiness logic.

At minimum ensure API data exposes both:

```text
track narration_hash
current narration hash
```

in whatever repository-consistent way is needed.

Avoid duplicating Phase 3 readiness logic in Part 2.

---

# 55. NO READINESS IMPLEMENTATION

Do NOT create:

```text
TtsReadinessService
```

Do NOT create:

```text
tts-readiness
```

endpoint.

Do NOT add:

```text
NO_VISUAL_PLAN
ITEM_WITHOUT_NARRATION
TTS_GENERATION_FAILED
PROVIDER_UNAVAILABLE
LONG_NARRATION
TRACK_DURATION_MISMATCH
```

as readiness response in Part 2.

Those belong to Part 3.

---

# 56. NO MANIFEST

Do NOT create:

```text
tts-manifest
```

endpoint.

Do not modify:

```text
ProductionSpecificationService
ProductionHandoffService
AssetProductionManifestService
```

TTS manifest belongs to Part 4.

---

# 57. NO FRONTEND

Do NOT modify:

```text
ProjectDetailPage.tsx
```

Do NOT create:

```text
AudioPanel.tsx
ttsService.ts
TTS frontend types
```

Those belong to Part 5.

---

# 58. NO QUEUE

Everything must remain synchronous.

Do not create:

```text
Job
Queue
Event
Listener
Dispatch
```

Provider synthesis executes during HTTP request.

This is intentional for Phase 5.

---

# 59. TRANSACTION SAFETY

Inspect existing generation services.

Use database transactions where appropriate.

Be careful with:

```text
DB transaction
+
filesystem write
```

Do not assume transaction rollback deletes files.

Tests must detect orphan risk where practical.

If an Asset is created but TtsTrack update fails, cleanup must follow existing Asset storage/service conventions.

---

# 60. ERROR PATH TESTING

At minimum cover:

```text
unknown provider
provider throws
file storage failure if mockable
metadata inspection failure if mockable
cross-project track
invalid track/item relation
archive transition
```

Avoid brittle tests that depend on implementation internals.

---

# 61. API ROUTE CONVENTIONS

Inspect `routes/api.php`.

Follow existing nesting:

```text
projects/{project}/script/versions/{version}/visual-plan/items/{item}/tts
```

Use route model binding only if it preserves existing project/version/item scoping.

If normal Laravel binding could accidentally resolve an item outside the requested project/version, do not rely on naive binding.

Use existing service scoping pattern such as:

```text
VisualPlanService::getPlanOrFail()
resolveVersion()
findItem()
```

or equivalent.

---

# 62. ROUTE ORDERING

Pay attention to route conflicts:

```text
/{track}/regenerate
```

must not conflict with other track routes.

Use route parameter constraints according to repository conventions.

---

# 63. FORM REQUEST

If generation needs request input such as:

```text
voice
language
model
speed
```

create a dedicated FormRequest.

Do not accept arbitrary provider fields.

Validation should respect:

- provider-aware voice
- speed rules from Part 1
- optional model/language

If provider-specific voice validation cannot be performed until provider resolution, keep FormRequest validation structural and perform provider-specific validation in the service/provider layer.

Do not create global Voice enum.

---

# 64. PROVIDER-AWARE VOICE

Voice is provider-aware.

Do NOT create:

```text
enum Voice
```

global application-wide.

The generation request should pass voice to the selected provider.

Fake provider can have a deterministic accepted voice policy if needed.

Do not invent real provider voices.

---

# 65. API INPUT DEFAULTS

Follow Phase 5 specification and repository conventions.

Do not silently invent:

```text
voice = "default"
language = "en"
speed = 1.0
```

unless the existing provider/config design explicitly defines those defaults.

If Fake provider requires a voice, make that requirement explicit and test it.

---

# 66. RESOURCE PERFORMANCE

Avoid N+1 when rendering:

```text
asset
visualPlanItem
```

relations.

Use eager loading where repository resource pattern expects it.

Do not load full binary audio into Resource.

Resource must remain metadata-only.

---

# 67. SECURITY REGRESSION

Mandatory security assertions:

### No file path

API JSON must NOT contain:

```text
file_path
storage_path
absolute_path
```

### No public storage

Generated audio must not be reachable through a public URL.

### Cross project

Returns:

```text
404
```

### Unauthenticated

Returns:

```text
401
```

### Provider failure

Returns:

```text
502
```

without sensitive internals.

---

# 68. TEST COUNT BASELINE

Before Part 2:

```text
1508 tests
4627 assertions
```

After implementation:

report:

```text
before
after
delta
```

Do not fabricate expected test count.

---

# 69. TARGETED TESTING

Run targeted Part 2 tests first.

At minimum:

```text
TtsGenerationService tests
TTS API tests
TtsTrack lifecycle tests
TTS Asset exclusion tests
storage/metadata tests
```

Then run all existing tests.

---

# 70. FULL VERIFICATION

Run:

### Backend

```bash
php artisan test
```

Expected:

```text
0 failures
```

### Pint

```bash
vendor/bin/pint --test
```

If unrelated pre-existing files still fail:

- identify them
- do not modify them
- report them separately

Part 1 already had known unrelated Pint failures:

```text
LoginRequest.php
ApiResponse.php
```

Do not touch them unless they are genuinely changed by Part 2.

### TypeScript

```bash
npx tsc --noEmit
```

### Build

```bash
npm run build
```

Even though Part 2 should not modify frontend.

---

# 71. MUTATION TESTING

At minimum perform mutation tests for:

### Mutation 1

Remove TTS Asset guard from:

```text
attachAsset()
```

Expected:

```text
test fails
```

### Mutation 2

Remove TTS Asset guard from:

```text
selectAsset()
```

Expected:

```text
test fails
```

### Mutation 3

Change:

```text
AssetType::Audio
```

to another type.

Expected:

```text
generation/Asset tests fail
```

### Mutation 4

Stop updating TtsTrack:

```text
asset_id
```

Expected:

```text
generation test fails
```

### Mutation 5

Use old narration snapshot during regeneration instead of current item narration.

Expected:

```text
regeneration narration-change test fails
```

### Mutation 6

Change archive behavior from:

```text
status → archived
```

to physical delete.

Expected:

```text
archive/history test fails
```

### Mutation 7

Remove cross-project scoping.

Expected:

```text
security test fails
```

### Mutation 8

Change metadata duration assignment.

Expected:

```text
metadata/duration test fails
```

For every mutation:

```text
mutate
run targeted test
confirm caught
restore exact original
verify byte-equivalent restoration
```

Do not leave any mutation active.

---

# 72. FILE CHANGE REVIEW

Before commit:

```bash
git status --short
git diff
git diff --cached
```

Classify every changed file.

Expected categories:

```text
TTS service
TTS request/API
TTS resource
TTS routes
Asset guard
tests
documentation if required by repository convention
```

No unrelated Phase 3/4 modifications.

---

# 73. DOCUMENTATION

Create the Part 2 documentation following the established Phase documentation convention.

Expected:

```text
docs/PHASE_5_PART_2.md
docs/PHASE_5_PART_2_IMPLEMENTATION.md
```

However:

**Do not overwrite or modify:**

```text
docs/PHASE_5_TTS.md
docs/PHASE_5_REPO_DISCOVERY.md
```

The implementation document should record:

- architecture decisions implemented
- API endpoints
- storage behavior
- TTS Asset exclusion rule
- regeneration semantics
- archive semantics
- metadata behavior
- security decisions
- deviations from spec, if any
- verification results

Do not document unimplemented Part 3–6 functionality as completed.

---

# 74. COMMIT POLICY

Only after:

- targeted tests PASS
- full test suite PASS
- Pint checked
- TypeScript PASS
- build PASS
- mutation tests PASS
- mutation restoration verified
- working tree reviewed

create exactly ONE commit:

```text
feat: add TTS synthesis and storage API
```

Do NOT create multiple commits.

Do NOT amend Part 1 commit.

Do NOT push.

---

# 75. FINAL GIT CHECK

After commit:

```bash
git status --short
git log -1 --oneline
```

Expected latest commit:

```text
<new-hash> feat: add TTS synthesis and storage API
```

Untracked user documentation files must remain untracked and untouched.

---

# 76. FINAL REPORT FORMAT

Return exactly this structure:

## Part 2 Result

```text
status:
commit:
pushed:
```

## Implemented

- TtsGenerationService
- generation lifecycle
- provider resolution
- Asset creation
- private storage
- metadata inspection
- TtsTrack ↔ Asset
- generate API
- list API
- regenerate API
- archive API
- TTS Asset exclusion
- Resource
- FormRequest
- tests
- documentation

## API

List the implemented routes and HTTP methods.

## Storage

Report:

```text
disk:
path pattern:
MIME:
extension:
private/public:
preview:
```

## TTS Asset Guard

Explain exactly how:

```text
attachAsset()
selectAsset()
```

prevent TTS Assets from becoming AssetRequirement candidates.

## Tests

```text
before:
after:
assertions before:
assertions after:
```

## Verification

```text
targeted tests:
full suite:
Pint:
TypeScript:
build:
```

## Mutation Results

Table:

| Mutation                         | Expected | Result |
| -------------------------------- | -------- | ------ |
| attach guard removed             | caught   | PASS   |
| select guard removed             | caught   | PASS   |
| Asset type changed               | caught   | PASS   |
| asset_id update removed          | caught   | PASS   |
| regeneration uses stale snapshot | caught   | PASS   |
| archive changed to delete        | caught   | PASS   |
| cross-project scope removed      | caught   | PASS   |
| duration assignment changed      | caught   | PASS   |

Adjust table if a mutation is not applicable, but do not claim PASS unless actually executed.

## Security Verification

Confirm:

- unauthenticated → 401
- cross-project → 404
- unknown resources → 404
- provider failure → 502
- no file_path leak
- no public audio URL
- no credential leak

## Scope Check

Explicitly confirm:

- Part 3 readiness NOT implemented
- Part 4 manifest NOT implemented
- Part 5 frontend NOT implemented
- Part 6 final verification NOT implemented
- no queue
- no real provider
- no production handoff integration
- no Production Specification changes
- no push

## Files Changed

List every tracked file added/modified.

## Final Git State

```text
git status --short
git log -1 --oneline
```

---

# 77. STOP CONDITIONS

STOP and report instead of committing if:

1. existing Asset storage abstraction cannot safely store WAV
2. WAV MIME is not supported and fixing it requires broad Asset-domain changes
3. existing Asset deletion/containment behavior conflicts with TTS requirements
4. project/version/item scoping cannot be preserved
5. provider failure cannot be mapped cleanly to 502
6. TTS Asset exclusion requires changing Phase 4 contracts unexpectedly
7. an existing Phase 4 test fails for reasons unrelated to Part 2
8. metadata inspection requires changing the shared metadata contract
9. a required change would modify `AssetProductionHandoffService`
10. a required change would modify `ProductionSpecificationService`
11. a required change would modify existing Phase 4 migrations
12. any Part 3–6 implementation becomes necessary to make Part 2 work

In any of these cases:

**Do not invent a workaround. Do not commit. Report the exact conflict and wait for instruction.**

---

# 78. FINAL SCOPE BOUNDARY

Part 2 ends at:

```text
VisualPlanItem
    ↓
TTS provider
    ↓
WAV
    ↓
private Asset
    ↓
TtsTrack
    ↓
API
```

It does NOT include:

```text
TTS readiness
TTS manifest
AudioPanel
Production Specification
Production Handoff
subtitles
timeline
rendering
BGM
queue
real TTS provider
automatic regeneration
```

Those belong to later Phase 5 parts.

**Do not proceed beyond this boundary.**
