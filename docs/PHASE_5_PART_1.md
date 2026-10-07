# PHASE 5 — PART 1: TTS DOMAIN & PROVIDER FOUNDATION

## MODE

Implementation mode.

Phase 5 specification sudah disetujui dan dikunci di:

`docs/PHASE_5_TTS.md`

Discovery dan seluruh keputusan arsitektur sudah selesai.

**Implement Part 1 ONLY.**

Jangan mengerjakan Part 2–6.
Jangan mengubah scope Phase 5.
Jangan push ke remote.
Jangan commit sampai seluruh verification Part 1 selesai dan hasilnya dilaporkan.

---

# 1. BASELINE

Baseline repository:

- Commit: `f8de70e`
- Commit message: `fix: stabilize pre-phase-5 workflow`
- Tests: 1424 tests / 4503 assertions
- Pint: clean
- TypeScript: clean
- Vite build: clean

Working tree memiliki untracked documentation files milik user:

- `docs/Plan.md`
- `docs/pre_phase_5.md`
- `docs/testing.md`
- `docs/PHASE_5_REPO_DISCOVERY.md`
- `docs/PHASE_5_TTS.md`

**JANGAN commit, modify, delete, rename, atau stage file-file tersebut.**

Sebelum coding:

1. inspect `git status`
2. inspect baseline conventions
3. inspect existing AI provider implementation
4. inspect existing enum/state-machine patterns
5. inspect existing model/migration conventions
6. inspect existing test conventions

Jika ada perbedaan antara prompt ini dengan repository convention, ikuti repository convention selama tidak bertentangan dengan `docs/PHASE_5_TTS.md`.

---

# 2. PART 1 OBJECTIVE

Bangun fondasi domain TTS:

1. `tts_tracks` persistence
2. `TtsTrackStatus`
3. `TtsTrack` model
4. TTS provider contract
5. immutable TTS request/result value objects
6. deterministic `FakeTtsProvider`
7. `TtsProviderRegistry`
8. `config/tts.php`
9. `TtsProviderException`
10. Laravel container binding
11. focused unit tests

Part 1 **belum** membuat:

- TTS generation API
- TTS generation service
- Asset creation/storage
- TTS readiness
- TTS manifest
- frontend AudioPanel
- TTS routes
- TTS policy
- real provider
- queue/job
- production handoff integration

---

# 3. READ-ONLY SPEC

Gunakan:

`docs/PHASE_5_TTS.md`

sebagai source of truth.

Jangan mengubah file specification tersebut.

---

# 4. DATABASE — `tts_tracks`

Buat migration baru:

`create_tts_tracks_table`

Jangan mengubah migration/table existing.

Minimal field domain:

```text
id
content_project_id
visual_plan_item_id
asset_id nullable
provider
model nullable
voice
language nullable
speed
status
duration_seconds nullable
narration_snapshot
narration_hash
error nullable
created_at
updated_at
```

Expected semantics:

### `content_project_id`

Foreign key ke content project.

Harus mengikuti pola FK repository.

Delete behavior harus aman dan konsisten dengan project-owned resources.

### `visual_plan_item_id`

Foreign key ke visual plan item.

TTS Track melekat ke satu VisualPlanItem.

### `asset_id`

Nullable.

Akan menunjuk ke generated Audio Asset pada Part 2.

Karena Asset belum dibuat di Part 1, migration hanya perlu menyediakan FK nullable sesuai convention repository.

Gunakan delete behavior yang tidak membuat historical TTS track kehilangan audit trail secara tidak sengaja.

### `provider`

String provider identifier.

Contoh:

```text
fake
```

### `model`

Nullable provider model identifier.

### `voice`

Provider-aware voice identifier.

**Jangan membuat global Voice enum.**

### `language`

Nullable language identifier.

### `speed`

Decimal sesuai convention repository.

Default:

```text
1.0
```

Jangan membuat volume field pada Part 1.

Volume memang deferred.

### `status`

Disimpan sebagai string dan di-cast ke:

`TtsTrackStatus`

### `duration_seconds`

Nullable unsigned integer.

Durasi final audio.

### `narration_snapshot`

Simpan teks narasi yang benar-benar disintesis.

### `narration_hash`

SHA-256 dari `narration_snapshot`.

Format:

64-character hexadecimal hash.

### `error`

Nullable text/string untuk error generation.

---

# 5. IMPORTANT DATABASE RULES

Jangan membuat unique constraint:

```text
visual_plan_item_id + narration_hash
```

History TTS memang boleh memiliki narration hash yang sama.

Contoh valid:

```text
Track 1 → hash ABC → completed
Track 2 → hash XYZ → completed
Track 3 → hash ABC → failed
```

Jika Track 3 kemudian berhasil, Track 1 dan Track 3 tetap boleh ada.

Regeneration adalah history, bukan replacement.

Boleh membuat index yang membantu query historical/current tracks, tetapi jangan menambahkan constraint bisnis yang tidak ada di specification.

---

# 6. `TtsTrackStatus`

Buat enum:

`app/Enums/TtsTrackStatus.php`

Expected states:

```text
pending
generating
completed
failed
archived
```

Enum harus mengikuti pola enum workflow yang sudah ada di repository.

Implement state transition rules.

Recommended state machine:

```text
pending     → generating
pending     → archived

generating  → completed
generating  → failed

completed   → archived

failed      → archived

archived    → terminal
```

Tidak ada public status endpoint.

Status nantinya dikontrol oleh generation/regeneration service.

Part 1 hanya menyediakan domain state machine.

Tambahkan tests untuk memastikan:

- allowed transition diterima
- invalid transition ditolak
- archived tidak bisa transition kembali
- generating tidak langsung ke archived jika repository state-machine convention tidak mengizinkannya

Jika existing enum menggunakan nama method tertentu seperti:

```php
allowedTransitions()
canTransitionTo()
```

ikuti convention tersebut.

---

# 7. `TtsTrack` MODEL

Buat:

`app/Models/TtsTrack.php`

Relations:

```text
contentProject()
visualPlanItem()
asset()
```

Gunakan relation model existing:

- ContentProject
- VisualPlanItem
- Asset

Ikuti pola model existing untuk:

- `$fillable`
- casts
- enum casts
- decimal handling
- relationship typing
- scopes
- boot methods

Jangan menambahkan business logic generation ke model.

Model harus tetap sebagai persistence/domain representation.

---

# 8. NARRATION HASH

TTS domain membutuhkan:

```text
SHA-256(narration_text)
```

Pastikan implementation memiliki satu deterministic rule.

Jangan menggunakan:

- MD5
- timestamp
- random UUID
- normalized narration
- provider/model/voice sebagai bagian hash

Hash hanya berdasarkan exact narration text.

Contoh:

```php
hash('sha256', $narrationText)
```

Whitespace/case harus tetap sesuai exact input.

Tambahkan unit test untuk memastikan:

- input yang sama → hash sama
- input berbeda → hash berbeda
- hash length = 64
- hash adalah SHA-256 hexadecimal

Jangan melakukan normalization pada Part 1.

---

# 9. TTS REQUEST

Buat immutable value object:

`TtsRequest`

Lokasi ikuti convention DTO/value-object repository.

Request harus mewakili data provider membutuhkan untuk synthesis.

Minimal:

```text
narration_text
model nullable
voice
language nullable
speed
```

Jangan memasukkan Eloquent model ke provider request.

Provider layer harus tetap decoupled dari:

- ContentProject
- VisualPlanItem
- TtsTrack
- Asset

Provider hanya menerima synthesis request.

Ikuti pola immutable DTO yang sudah ada di repository.

Jika project belum memiliki DTO/value-object convention, gunakan PHP 8.4 immutable/read-only design yang sederhana dan explicit.

---

# 10. TTS RESULT

Buat immutable value object:

`TtsResult`

Minimal output:

```text
audio binary/content
duration_seconds
mime_type
extension
```

Tujuannya adalah provider mengembalikan hasil synthesis tanpa mengetahui Laravel Storage atau Asset model.

Jangan menyimpan file dari provider langsung ke disk pada Part 1.

Storage implementation belongs to Part 2.

---

# 11. PROVIDER CONTRACT

Buat:

`app/Services/TTS/Contracts/TtsProvider.php`

Ikuti pola provider yang sudah digunakan oleh:

- Research provider
- Script provider

Contract minimal harus menyediakan:

```php
name(): string
synthesize(TtsRequest $request): TtsResult
```

Jangan menambahkan API/network-specific method yang belum diperlukan.

Provider harus:

- provider-agnostic
- tidak bergantung pada Laravel model
- tidak mengetahui Asset
- tidak mengetahui TtsTrack
- tidak mengetahui VisualPlanItem

---

# 12. `FakeTtsProvider`

Buat deterministic:

`FakeTtsProvider`

Ikuti pola `FakeProvider` existing.

Requirements:

### No network

Tidak boleh:

- HTTP request
- external API
- API key
- environment secret

### Real WAV

Fake provider **harus menghasilkan WAV audio binary yang valid**.

Gunakan PHP murni.

Jangan membuat fake:

```text
"text/plain"
"fake-audio"
"dummy.mp3"
```

Output harus benar-benar WAV.

Gunakan PCM WAV sederhana yang deterministic.

Contoh format yang diperbolehkan:

```text
PCM
mono
16-bit
8kHz atau format PCM sederhana lain yang konsisten
```

Pilih satu format dan dokumentasikan di test.

### Deterministic

Input synthesis yang sama harus menghasilkan output yang sama.

Tidak boleh menggunakan:

- random
- timestamp
- UUID
- random noise

Silence/PCM deterministic diperbolehkan.

### Duration

Gunakan rule Phase 5:

```text
duration_seconds = ceil(word_count / 3)
```

Minimal 1 detik untuk narration yang valid.

Contoh:

```text
1–3 words   → 1 second
4–6 words   → 2 seconds
7–9 words   → 3 seconds
...
```

Pastikan WAV header benar-benar mencerminkan duration tersebut.

---

# 13. FAKE PROVIDER TESTS

Unit test wajib memastikan:

### Determinism

Synthesis dengan request identik:

```text
result1.audio === result2.audio
result1.duration_seconds === result2.duration_seconds
```

### Duration

Contoh:

```text
3 words → 1 sec
4 words → 2 sec
180 words → 60 sec
181 words → 61 sec
```

### WAV validity

Pastikan:

- RIFF header valid
- WAVE header valid
- PCM format valid
- channel count konsisten
- sample rate konsisten
- bit depth konsisten
- data chunk ada
- byte length konsisten dengan duration

Tidak perlu menggunakan FFprobe untuk unit test Part 1 jika WAV header dapat diverifikasi langsung.

FFprobe integration verification akan dilakukan pada Part 2.

### Empty narration

Gunakan behavior yang konsisten dengan existing provider validation convention.

Jangan menambahkan business rule yang tidak diperlukan.

---

# 14. PROVIDER REGISTRY

Buat:

`TtsProviderRegistry`

Ikuti pola registry existing.

Requirements:

- provider class berasal dari `config/tts.php`
- default provider berasal dari config
- provider dapat di-resolve berdasarkan name
- unknown provider menghasilkan domain exception
- registry tidak melakukan network call
- registry tidak menggunakan `env()` langsung

Config harus menjadi source of truth.

---

# 15. `config/tts.php`

Buat config file baru.

Minimal:

```php
return [
    'default' => 'fake',

    'providers' => [
        'fake' => \App\Services\TTS\Providers\FakeTtsProvider::class,
    ],
];
```

Sesuaikan namespace/folder dengan repository convention.

Jangan memasukkan:

```php
env(...)
```

untuk provider selection.

Jangan membuat API key config.

Jangan membuat real provider.

Jika provider config existing menggunakan struktur berbeda, mirror struktur existing sambil mempertahankan semantics di atas.

---

# 16. PROVIDER EXCEPTION

Buat TTS-specific provider exception.

Gunakan range:

```text
4000+
```

Ikuti pola exception:

- Search = 1000+
- Script = 2000+
- Research = 3000+

Jangan mengubah exception existing.

TTS exception harus dapat membedakan setidaknya:

- provider unavailable/configuration problem
- provider synthesis failure
- unknown provider

Gunakan pola error code existing repository daripada menciptakan framework baru.

---

# 17. CONTAINER BINDING

Register provider registry menggunakan pola existing.

Kemungkinan lokasi:

`AppServiceProvider`

Tetapi **inspect existing provider bindings first**.

Requirement:

- registry dapat di-resolve dari Laravel container
- registry menggunakan config
- Fake provider dapat dibuat tanpa network
- binding tidak memanggil provider synthesis saat boot

Jangan melakukan synthesis ketika application boot.

---

# 18. TESTING

Tambah focused tests untuk Part 1.

Minimal coverage:

### TtsTrackStatus

- every valid transition
- every invalid transition
- archived terminal state

### Narration hash

- deterministic
- SHA-256
- exact input semantics

### FakeTtsProvider

- deterministic output
- duration formula
- WAV structure
- empty input behavior

### TtsProviderRegistry

- resolves configured fake provider
- default provider works
- unknown provider fails correctly

### TtsRequest/TtsResult

- construction
- immutable semantics
- validation sesuai convention

### TtsTrack model

- enum cast
- relationships
- relevant casts

Gunakan test style existing.

Jangan membuat integration test API pada Part 1.

Jangan menggunakan RefreshDatabase jika unit test tidak membutuhkannya.

---

# 19. SECURITY / SCOPE GUARD

Part 1 tidak boleh:

- expose file_path
- create public audio URLs
- create Storage::url
- create API routes
- modify AssetResource
- modify AssetRequirementService
- modify AssetCompatibilityEvaluator
- modify AssetProductionManifestService
- modify AssetProductionHandoffService
- modify ProductionSpecificationService
- modify VisualPlanService behavior
- modify Script/ScriptVersion behavior
- add queue/job/event
- call external network
- introduce env secrets
- modify existing migrations
- modify Phase 4 functionality

---

# 20. REQUIRED INSPECTION BEFORE EDITING

Sebelum membuat file, inspect minimal:

```text
app/Services/AI/
config/ai.php
app/Providers/AppServiceProvider.php
app/Enums/
app/Models/
database/migrations/
tests/Unit/
tests/Feature/
```

Cari contoh:

- provider contract
- fake provider
- registry
- exception code
- enum transition
- immutable DTO
- model relationship
- migration FK/index
- service container binding
- unit test conventions

**Jangan mengarang pola baru jika repository sudah memiliki pola yang setara.**

---

# 21. VERIFICATION

Setelah implementasi Part 1:

### Targeted tests

Jalankan test baru/fokus terlebih dahulu.

Pastikan semua Part 1 tests PASS.

### Full backend suite

Jalankan seluruh test suite.

Baseline:

```text
1424 tests
4503 assertions
```

Laporkan:

```text
before
after
delta
```

Jangan menganggap jumlah test harus persis bertambah dengan angka tertentu; fokus pada tidak ada regression dan semua test PASS.

### Formatting

Jalankan:

```bash
vendor/bin/pint --test
```

### Frontend type check

```bash
npm run typecheck
```

atau command repository yang setara.

### Frontend build

```bash
npm run build
```

Part 1 tidak seharusnya mengubah frontend, tetapi tetap lakukan verification baseline sesuai workflow Phase 5.

### Mutation testing

Buat beberapa mutation kecil pada logic Part 1, terutama:

1. narration hash algorithm
2. one TtsTrackStatus transition
3. FakeTtsProvider duration formula
4. registry default provider
5. unknown-provider failure

Untuk setiap mutation:

```text
mutate
run targeted test
verify test catches mutation
restore exact original
```

Jangan meninggalkan mutation.

Jangan mengubah existing mutation tests tanpa alasan.

---

# 22. WORKING TREE CHECK

Sebelum commit:

```bash
git status
git diff
git diff --cached
```

Pastikan:

### Allowed

Hanya perubahan Part 1.

### Forbidden

Jangan stage:

```text
docs/Plan.md
docs/pre_phase_5.md
docs/testing.md
docs/PHASE_5_REPO_DISCOVERY.md
docs/PHASE_5_TTS.md
```

Jangan stage file unrelated.

---

# 23. COMMIT POLICY

Jika semua verification PASS:

buat tepat **satu commit**:

```text
feat: add TTS domain and provider foundation
```

Jangan membuat commit lain untuk Part 1.

**JANGAN PUSH.**

Setelah commit:

```bash
git status
git log -1 --oneline
```

Pastikan working tree hanya berisi untracked documentation milik user yang memang sudah ada sebelumnya.

---

# 24. FINAL REPORT

Setelah selesai, laporkan dengan struktur:

## Part 1 Result

- status: PASS/FAIL
- commit:
- pushed: NO

## Implemented

- migration
- enum
- model
- request/result
- provider contract
- fake provider
- registry
- config
- exception
- container binding

## Tests

```text
before:
after:
assertions before:
assertions after:
```

## Verification

- targeted tests:
- full suite:
- Pint:
- TypeScript:
- build:
- mutation tests:

## Mutation Results

Tabel:

| Mutation | Expected | Result |
| -------- | -------- | ------ |
| ...      | caught   | PASS   |
| ...      | caught   | PASS   |

## Files Changed

List file yang benar-benar diubah/dibuat.

## Scope Check

Konfirmasi:

- Part 2–6 belum dikerjakan
- no API routes
- no frontend
- no Asset storage
- no readiness
- no manifest
- no queue
- no external provider
- no push

## Final Git State

Tampilkan:

```text
git status --short
git log -1 --oneline
```

Jika ada test failure atau verification failure:

**JANGAN COMMIT.**

Berhenti dan laporkan root cause terlebih dahulu.
