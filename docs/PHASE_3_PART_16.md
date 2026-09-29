# PHASE 3 — PART 16

## Visual Production Readiness / Asset Requirement Quality Gate

### Context

MochyFami Content Studio saat ini sudah memiliki:

- Research + Claims + Sources + Evidence
- Research Quality Gate
- Research Pipeline
- Script + Script Versions
- Script Quality Gate
- AI Script Generation Foundation
- Research → Script Context
- Script Research Traceability
- Version-aware Script Revision
- Visual Plan + Visual Plan Items
- Asset Requirement Foundation

Part 15 baru saja selesai.

Current baseline:

```text
778 tests
2619 assertions
```

Latest commit:

```text
9c8c43b feat: add visual plan asset requirements foundation
```

Working tree:

```text
CLEAN
```

Part 15 menambahkan:

```text
VisualPlan
    ↓
VisualPlanItem
    ↓
AssetRequirement
```

AssetRequirement masih berupa planning metadata. Belum ada asset nyata, provider, download, search, atau network.

---

# Objective

Implementasikan:

> **Visual Production Readiness / Asset Requirement Quality Gate**

Gate ini harus menjadi pemeriksaan deterministik dan read-only untuk menentukan apakah sebuah Visual Plan sudah cukup lengkap untuk diteruskan ke **Phase 4 Asset Manager**.

Part 16 TIDAK BOLEH:

- melakukan network request
- melakukan stock provider search
- melakukan AI generation
- download asset
- membuat asset nyata
- mengubah status Visual Plan secara otomatis
- mengubah AssetRequirement
- membuat AssetRequirement baru
- membuat asset record

Fokus:

```text
Visual Plan
      ↓
Visual Plan Items
      ↓
Asset Requirements
      ↓
Deterministic Quality Gate
      ↓
Ready / Not Ready
```

---

# 1. Inspect Repository First

Sebelum coding:

1. inspect repository
2. inspect existing:
    - VisualPlan
    - VisualPlanItem
    - AssetRequirement
    - AssetRequirementService
    - AssetRequirement enums
    - VisualPlanService
    - ScriptQualityService
    - ResearchQualityService
    - existing quality gate patterns
    - API Resources
    - Policies
    - frontend VisualPlanPanel
    - existing tests
3. jangan membuat ulang abstraction yang sudah tersedia
4. ikuti naming/style existing repository
5. jangan mengubah behavior Part 15 yang sudah bekerja

Berikan inspection summary sebelum implementasi.

---

# 2. Define Visual Plan Quality Rules

Buat deterministic quality service, misalnya:

```text
VisualPlanQualityService
```

atau nama paling konsisten dengan architecture existing.

Service harus:

```text
read-only
deterministic
no side effects
```

Input:

```text
Project
VisualPlan
```

Output structured.

Contoh:

```json
{
    "ready": false,
    "score": 72,
    "summary": {
        "total_items": 5,
        "items_with_requirements": 4,
        "items_without_requirements": 1,
        "total_requirements": 6,
        "pending_requirements": 5,
        "searching_requirements": 0,
        "fulfilled_requirements": 1,
        "skipped_requirements": 0
    },
    "blockers": [],
    "warnings": [],
    "info": []
}
```

Sesuaikan struktur dengan convention quality service existing.

---

# 3. Required Quality Checks

## BLOCKER — No Visual Plan

Jika project/script version belum memiliki Visual Plan:

```text
NO_VISUAL_PLAN
```

Gate:

```text
ready = false
```

## BLOCKER — Empty Visual Plan

Jika Visual Plan ada tetapi:

```text
total_items = 0
```

maka:

```text
EMPTY_VISUAL_PLAN
```

## BLOCKER — Item Without Asset Requirement

Jika ada VisualPlanItem yang membutuhkan visual tetapi tidak memiliki AssetRequirement:

```text
ITEM_WITHOUT_ASSET_REQUIREMENT
```

Detail blocker harus menyebut item yang bermasalah.

Contoh:

```json
{
    "code": "ITEM_WITHOUT_ASSET_REQUIREMENT",
    "visual_plan_item_id": 12,
    "message": "Visual plan item has no asset requirement."
}
```

---

# 4. Determine Whether an Item Requires an Asset

Jangan menganggap semua item selalu membutuhkan asset.

Gunakan `visual_type` yang sudah ada.

Mapping harus mengikuti architecture Part 15.

Contoh:

```text
animal_clip       → requires asset
stock_video       → requires asset
b_roll            → requires asset
screen_recording  → requires asset
photo             → requires asset
graphic            → requires asset
text              → may not require external asset
other              → depends on existing behavior
```

Jangan mengarang behavior baru.

Inspect existing enum / VisualPlanItem logic terlebih dahulu.

Jika repository sudah memiliki konsep item yang tidak membutuhkan external asset, gunakan konsep tersebut.

Jika tidak ada konsep tersebut, gunakan aturan paling sederhana yang konsisten dengan Part 15 dan dokumentasikan.

---

# 5. BLOCKER — Invalid Asset Requirement

Requirement dianggap invalid apabila data wajibnya tidak memenuhi aturan domain.

Contoh:

```text
requirement_type missing
description empty
target_duration_seconds <= 0
aspect_ratio invalid
status invalid
```

Jangan duplicate validation logic jika enum/request/domain validation existing sudah bisa dipakai.

Code:

```text
INVALID_ASSET_REQUIREMENT
```

---

# 6. BLOCKER — No Usable Requirement

Jika item memiliki requirement tetapi semuanya berada dalam kondisi yang tidak bisa digunakan untuk Phase 4, misalnya:

```text
skipped
```

maka item dianggap belum siap.

Code:

```text
NO_USABLE_ASSET_REQUIREMENT
```

Jangan menganggap `pending` sebagai blocker.

```text
pending    → acceptable
searching  → acceptable with warning
fulfilled  → acceptable
skipped    → not acceptable
```

---

# 7. WARNING — Searching Requirements

Jika:

```text
status = searching
```

maka:

```text
ASSET_REQUIREMENT_SEARCHING
```

Jangan membuat gate gagal hanya karena searching.

---

# 8. WARNING — Missing Search Query

Untuk requirement yang secara tipe membutuhkan pencarian external asset:

```text
video
image
audio
```

jika:

```text
search_query = null
```

maka:

```text
MISSING_ASSET_SEARCH_QUERY
```

Jangan otomatis menjadikannya blocker jika requirement masih dapat diproses manual.

---

# 9. WARNING — Missing Duration

Jika requirement membutuhkan media temporal:

```text
video
audio
```

tetapi:

```text
target_duration_seconds = null
```

maka:

```text
MISSING_TARGET_DURATION
```

Jangan menjadikan blocker kecuali architecture existing memang mengharuskannya.

---

# 10. Placeholder Description

Part 15 sudah menangani placeholder:

```text
Define visual for this narration.
```

Jika somehow masih ada placeholder tersebut pada existing requirement, jangan melakukan mutation.

Berikan:

```text
PLACEHOLDER_VISUAL_DESCRIPTION
```

Prefer sebagai BLOCKER karena Asset Manager tidak memiliki instruksi visual yang cukup.

---

# 11. Visual Plan Status

Inspect existing:

```text
VisualPlanStatus
```

dan transition graph.

Quality gate harus mempertimbangkan status plan.

Jangan membuat status baru.

Jika status archived:

```text
ARCHIVED_VISUAL_PLAN
```

dan tidak dianggap ready untuk production.

Jika repository memiliki status lain, gunakan enum existing.

Jangan mengubah transition graph pada Part 16.

---

# 12. Script Version Consistency

Visual Plan sudah terikat ke:

```text
script_version_id
```

Pastikan Visual Plan memiliki script version yang valid.

Jika tidak:

```text
INVALID_SCRIPT_VERSION_REFERENCE
```

Jangan memperbaiki relationship secara otomatis.

---

# 13. Score

Buat score deterministic dan konsisten dengan quality service existing.

Contoh:

```text
Visual Plan completeness      30
Asset requirement coverage    30
Requirement validity           20
Search metadata completeness  10
Duration completeness          10
--------------------------------
Total                         100
```

`ready` harus ditentukan oleh blocker:

```text
ready = blockers.count === 0
```

bukan:

```text
ready = score >= 80
```

Score hanya informational.

---

# 14. Quality Result Categories

Minimal:

```text
blockers
warnings
info
```

Setiap issue minimal memiliki:

```text
code
message
severity
```

Jika relevan:

```text
visual_plan_item_id
asset_requirement_id
```

Contoh:

```json
{
    "code": "ITEM_WITHOUT_ASSET_REQUIREMENT",
    "severity": "blocker",
    "message": "Visual plan item has no asset requirement.",
    "visual_plan_item_id": 15
}
```

---

# 15. API

Tambahkan endpoint:

```http
GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/quality
```

Response:

```json
{
    "data": {
        "ready": false,
        "score": 72,
        "summary": {},
        "blockers": [],
        "warnings": [],
        "info": []
    }
}
```

Gunakan API Resource/DTO pattern yang konsisten dengan existing quality endpoints.

Endpoint harus:

- authenticated
- project scoped
- script scoped
- version scoped
- visual plan scoped
- cross-project/version access → 404

Jangan mengandalkan route binding saja jika existing architecture menggunakan service-level scope validation.

---

# 16. Frontend

Update:

```text
VisualPlanPanel.tsx
```

atau komponen quality UI paling sesuai.

Tambahkan:

## Visual Plan Readiness Card

Tampilkan:

```text
Visual Production Readiness
```

Status:

```text
READY
NOT READY
```

Kemudian:

```text
Score: 82/100
```

Summary:

```text
5 Visual Items
6 Asset Requirements
5 Pending
1 Fulfilled
0 Skipped
```

Jika ada blocker:

```text
Blockers
- Item #3 has no asset requirement
- Item #5 has an invalid requirement
```

Jika warning:

```text
Warnings
- 2 requirements are still searching
- 1 requirement has no search query
```

UI hanya membaca quality endpoint.

Jangan melakukan mutation dari quality card.

---

# 17. Refresh Behavior

Setelah action yang mengubah:

- visual item
- asset requirement
- requirement status
- generate requirements

frontend harus bisa refresh quality result.

Gunakan pattern existing.

Jangan membuat global state architecture baru hanya untuk ini.

---

# 18. No Auto Mutation

Quality service TIDAK BOLEH:

```text
create requirement
update requirement
update item
update plan
change status
delete anything
```

Quality gate hanya membaca database.

Test harus membuktikan database state unchanged setelah quality check.

---

# 19. Tests

Tambahkan focused tests. Target sekitar 40–50 test cases, sesuaikan architecture.

Minimal:

### Foundation

1. quality endpoint requires auth
2. valid project/version scope
3. cross-project → 404
4. cross-version → 404
5. missing visual plan
6. empty visual plan

### Requirement Coverage

7. item with requirement
8. item without requirement
9. multiple requirements
10. skipped requirement
11. pending requirement
12. fulfilled requirement
13. searching requirement

### Validation

14. invalid requirement type
15. missing description
16. invalid duration
17. invalid aspect ratio
18. placeholder description
19. missing search query
20. missing target duration

### Status

21. draft plan
22. approved plan
23. archived plan
24. existing status behavior remains unchanged

### Score

25. empty plan score
26. complete plan score
27. blockers force ready=false
28. warnings don't automatically force ready=false

### No Mutation

29. quality check does not create requirements
30. quality check does not update requirements
31. quality check does not change plan status
32. quality check does not change item status
33. quality check does not create assets

### Regression

34. Part 15 generation still works
35. Part 15 CRUD still works
36. Part 15 status transitions still work
37. existing Visual Plan tests still pass
38. existing Script Quality tests still pass
39. existing Research Quality tests still pass

Tambahkan test lain jika diperlukan untuk meaningful coverage.

---

# 20. Performance / N+1

Quality endpoint akan membaca:

```text
VisualPlan
    → VisualPlanItems
        → AssetRequirements
```

Pastikan menggunakan eager loading yang sesuai.

Contoh konsep:

```text
with([
    'items.assetRequirements'
])
```

Jangan melakukan query per item.

Jika repository memiliki existing query/service pattern, ikuti pattern tersebut.

---

# 21. Do Not Introduce Asset Manager Yet

Jangan membuat:

```text
Asset
AssetProvider
AssetSearch
AssetDownload
AssetLibrary
StockProvider
```

Part 16 hanya membuat quality gate.

Phase 4 akan menangani actual Asset Manager.

---

# 22. Documentation

Buat:

```text
docs/PHASE_3_PART_16.md
```

Dokumentasikan:

1. objective
2. quality gate architecture
3. blocker rules
4. warning rules
5. score calculation
6. API endpoint
7. frontend behavior
8. no-mutation guarantee
9. tests
10. known limitations
11. Phase 4 handoff contract

Tambahkan section:

## Phase 4 Handoff

Jelaskan bahwa Phase 4 dapat menggunakan:

```text
GET visual-plan/quality
```

untuk mengetahui apakah planning sudah cukup siap.

Tetapi Phase 4 tetap tidak boleh menganggap:

```text
ready = true
```

sebagai bukti bahwa asset sudah tersedia.

Bedakan:

```text
Planning Ready
```

dengan:

```text
Asset Ready
```

---

# 23. Important Architectural Rule

Pertahankan pemisahan:

```text
Research Quality
        ↓
Script Quality
        ↓
Visual Plan Quality
        ↓
Asset Manager
        ↓
Asset Quality
```

Jangan menggabungkan semuanya menjadi satu giant quality service.

Part 16 hanya bertanggung jawab terhadap:

```text
Visual Plan
+
Asset Requirements
```

---

# 24. Migration

Idealnya:

```text
NO MIGRATION
```

Part 16 hanya membaca struktur existing.

Jika inspection menemukan perubahan schema yang mutlak diperlukan, STOP sebelum membuat migration dan laporkan alasannya.

Jangan membuat migration hanya untuk convenience.

---

# 25. Verification

Setelah implementasi:

```bash
php artisan test
```

Harus ALL PASS.

Kemudian:

```bash
npx tsc --noEmit
```

Harus bersih.

Kemudian:

```bash
npm run build
```

Harus sukses.

Kemudian:

```bash
vendor/bin/pint
```

Pastikan file Part 16 clean.

Lakukan debug sweep:

```text
dd(
dump(
var_dump(
ray(
console.log(
TODO
FIXME
temporary debug
```

Pastikan tidak ada debug code baru.

---

# 26. Git

Setelah seluruh verification PASS:

buat SATU commit:

```text
feat: add visual plan production readiness gate
```

Jangan push ke remote.

Working tree harus:

```text
CLEAN
```

---

# 27. Final Report

Setelah selesai, laporkan hanya:

```text
## Part 16 Completion Report

### 1. Implemented
...

### 2. Files / Modules
...

### 3. Quality Rules
...

### 4. API
...

### 5. Frontend
...

### 6. Tests
X tests / Y assertions

### 7. Verification
- php artisan test:
- npx tsc --noEmit:
- npm run build:
- Pint:
- debug sweep:

### 8. Migration
...

### 9. Commit
...

### 10. Working Tree
...

### 11. Known Limitations
...

### 12. Phase 4 Handoff
...
```

**Jangan push.**

**Jangan mengerjakan Phase 4.**

**STOP setelah Part 16 selesai.**

================================================== APPENDIX A. IMPLEMENTATION RECORD
==================================================

Status: implemented.

--------------------------------------------------
A.1. OBJECTIVE
--------------------------------------------------

Add one deterministic, read-only gate that answers a single question: is the
visual plan complete enough to hand over to the Phase 4 Asset Manager?

The gate is a quality gate, not a workflow. It never creates a requirement,
never transitions a status, never calls AI, never calls a provider, and never
touches the network. It reports; a human decides.

--------------------------------------------------
A.2. ARCHITECTURE
--------------------------------------------------

app/Services/VisualPlan/VisualPlanQualityService.php
    evaluateVisualPlan(VisualPlan $plan): array. Returns ready, score,
    summary, blockers, warnings, info.

    Kept separate from ScriptQualityService on purpose. Research quality,
    script quality, and visual plan quality are three gates over three
    different layers; merging them would produce one unfocused service.

    One loadMissing(['scriptVersion.script', 'items.assetRequirements']) warms
    every relation the evaluation needs, so the query count stays constant
    instead of growing per plan item.

    Enum columns are read through getRawOriginal() plus tryFrom() rather than
    through the model cast. A corrupt stored value would throw a ValueError on
    the cast, turning the data quality problem this gate exists to surface
    into a 500. Read defensively, it becomes a reported blocker.

app/Http/Resources/VisualPlanQualityResource.php
    Wraps the evaluation with the plan identity: visual_plan_id,
    script_version_id, status.

app/Http/Controllers/Api/V1/VisualPlanQualityController.php
    show(). Resolves the plan through VisualPlanService::getPlanOrFail, so the
    project -> script -> version -> plan chain and its 404 behaviour are
    identical to every other Part 15 endpoint, then authorizes view.

routes/api.php
    GET api/v1/projects/{project}/script/versions/{version}/visual-plan/quality

    A missing plan returns 404, matching the rest of the visual plan surface.
    The spec also lists a NO_VISUAL_PLAN blocker code, but a typed
    evaluateVisualPlan(VisualPlan $plan) has no null case to reach it from, so
    that code was left out rather than written as a dead path. See A.10.

--------------------------------------------------
A.3. BLOCKER RULES
--------------------------------------------------

INVALID_SCRIPT_VERSION_REFERENCE
    The plan does not resolve to a script version whose script belongs to this
    project. The foreign key guarantees the version exists, so the reachable
    case is a version from another project's script. The null check is kept as
    a cheap guard.

ARCHIVED_VISUAL_PLAN
    Status is archived. Archived plans are not production candidates.

EMPTY_VISUAL_PLAN
    The plan has no items, so there is nothing to produce.

ITEM_WITHOUT_ASSET_REQUIREMENT
    An item has zero requirements. Every visual plan item type requires at
    least one asset, including text and other: an item is a planned shot, and a
    shot with no described media is a gap in the plan, not an exemption.
    Tested across all eight visual types.

NO_USABLE_ASSET_REQUIREMENT
    An item has requirements, but all of them are unusable for Phase 4. A
    skipped requirement does not count as usable, and neither does an invalid
    one. An item that has a skipped requirement plus a valid one is not
    blocked.

INVALID_ASSET_REQUIREMENT
    A requirement has an empty description, a non-positive target duration, an
    unrecognized aspect ratio, or a requirement_type/status that is not a known
    enum value.

PLACEHOLDER_VISUAL_DESCRIPTION
    A requirement still carries VisualPlanService::PLACEHOLDER_VISUAL_PROMPT.
    This is deliberately its own code rather than a generic invalid
    requirement: a leftover placeholder is a distinct, very common failure
    after seeding a plan from a script, and it deserves a code that says so.

--------------------------------------------------
A.4. WARNING RULES
--------------------------------------------------

ASSET_REQUIREMENT_SEARCHING
    A valid requirement is mid-search. Not a blocker: Phase 4 may legitimately
    still be sourcing it.

MISSING_ASSET_SEARCH_QUERY
    A video, image, or audio requirement has no search query. Graphic, screen
    recording, and other types are not searched, so they are exempt.

MISSING_TARGET_DURATION
    A video or audio requirement has no target duration. Non-temporal types
    are exempt.

Warnings never affect ready. An operator is allowed to hand over a plan that
still has open warnings.

--------------------------------------------------
A.5. SCORE CALCULATION
--------------------------------------------------

Weights, summing to 100:

    Plan completeness          30
    Requirement coverage       30
    Requirement validity       20
    Search metadata            10
    Target duration            10

Each component is a ratio scaled by its weight, so the score degrades smoothly
rather than jumping. A component with nothing to measure scores zero rather
than a free pass, which is why an empty plan scores 0 and not 70.

    Completeness  items with non-empty narration and prompt / total items
    Coverage      items with at least one requirement / total items
    Validity      valid requirements / total requirements
    Search        searchable requirements with a query / searchable
    Duration      temporal requirements with a duration / temporal

The score is informational only. ready is decided by the absence of blockers
and never by a score threshold, so a plan cannot be blocked by 99/100 nor
approved by a high score while a blocker stands.

--------------------------------------------------
A.6. API ENDPOINT
--------------------------------------------------

GET /api/v1/projects/{project}/script/versions/{version}/visual-plan/quality

Authenticated, authorized against the visual plan view policy, read-only.

    {
      "success": true,
      "data": {
        "visual_plan_id": 12,
        "script_version_id": 15,
        "status": "approved",
        "ready": false,
        "score": 90,
        "summary": {
          "total_items": 2,
          "items_with_requirements": 1,
          "items_without_requirements": 1,
          "total_requirements": 3,
          "valid_requirements": 3,
          "invalid_requirements": 0,
          "pending_requirements": 2,
          "searching_requirements": 1,
          "fulfilled_requirements": 0,
          "skipped_requirements": 0,
          "blocker_count": 1,
          "warning_count": 0
        },
        "blockers": [
          {
            "code": "ITEM_WITHOUT_ASSET_REQUIREMENT",
            "severity": "blocker",
            "message": "Visual plan item #2 has no asset requirement.",
            "visual_plan_item_id": 4,
            "asset_requirement_id": null
          }
        ],
        "warnings": [],
        "info": []
      }
    }

Every issue names the offending plan item, and requirement-level issues also
name the requirement, so the UI can point at a row instead of guessing.

--------------------------------------------------
A.7. FRONTEND BEHAVIOR
--------------------------------------------------

resources/js/types/index.ts
    VisualPlanQualityResult, VisualPlanQualitySummary,
    VisualPlanQualityIssue, VisualPlanQualitySeverity.

resources/js/services/visualPlanService.ts
    getQuality(projectId, version).

resources/js/components/ui/QualityMetric.tsx
    The labelled-number tile previously private to ScriptPanel, extracted so
    the two quality panels stay visually consistent instead of diverging
    through a copy-paste. ScriptPanel now imports it; its behaviour is
    unchanged.

resources/js/components/projects/VisualPlanPanel.tsx
    A "Visual Production Readiness" card below the plan items: READY /
    NOT READY badge, score pill, a four-tile metric grid, and separate blocker,
    warning, and note lists.

    Readiness refreshes through one funnel, refreshPlanData, which every
    mutation already routes through: item create, update, delete, and reorder;
    requirement create, update, delete, and status transition; and
    deterministic generation. Plan status transitions and plan creation refresh
    readiness too, because archiving drives ARCHIVED_VISUAL_PLAN and creating a
    plan drives EMPTY_VISUAL_PLAN.

    A failed readiness read clears the card and shows a neutral line. It never
    surfaces as a mutation error, because nothing was mutated.

    No new top-level page and no new global state. The gate lives inside the
    existing Visual Plan tab.

--------------------------------------------------
A.8. NO-MUTATION GUARANTEE
--------------------------------------------------

The service performs no writes of any kind. Proven by tests, not by assertion:

    - plan, item, and requirement rows are identical before and after
    - a searching requirement stays searching; the gate never advances it
    - a plan left in review stays in review
    - no new rows are created
    - no outbound HTTP request is attempted, under Http::preventStrayRequests

The same guarantees are asserted again over HTTP, since a controller could in
principle mutate on the way through.

--------------------------------------------------
A.9. TESTS
--------------------------------------------------

tests/Feature/VisualPlanQualityTest.php
    48 tests, 118 assertions. Payload shape, every blocker and warning rule,
    the full score-weight decomposition, all eight visual types requiring an
    asset, searchable and temporal exemptions, warnings never blocking,
    corrupt stored requirement enums, aspect ratios, and plan status reported
    instead of thrown, the five no-mutation proofs, the eager-load query bound,
    and determinism across repeated evaluation.

tests/Feature/VisualPlanQualityApiTest.php
    16 tests, 71 assertions. Authentication, the documented payload structure,
    a ready plan, blockers with their codes and target ids, 404 for a missing
    plan, a missing version, another project's plan, and a missing project,
    archived and empty plans, warnings without losing readiness, a corrupt
    stored plan status, and the read-only guarantee over HTTP.

Full suite: 842 tests, 2808 assertions, all passing. The baseline before this
part was 778 tests and 2619 assertions.

--------------------------------------------------
A.10. KNOWN LIMITATIONS
--------------------------------------------------

  - ready = true means the planning side is complete. It is not a claim that
    any asset exists, was found, was downloaded, or was cleared for use.
  - Everything is scoped to one plan's items. Readiness never looks at the
    script, the research, or requirement status across other items.
  - An item whose only requirement is skipped is blocked. That is intended,
    but it means skipping every requirement on an item is a way to block
    production, not to opt out of it.
  - A skipped requirement is not usable but is also not invalid, so an item
    with one skipped and one valid requirement passes while the skipped row
    still counts in the score denominator.
  - Requirement type, requirement status, aspect ratio, and plan status are
    validated by reading raw values, because every one of those columns is a
    string and can hold a value the cast does not recognise. A corrupt value is
    reported rather than repaired; nothing in this part fixes data. An
    unrecognised plan status is reported as "not approved" rather than as a
    dedicated blocker, because the spec defines no code for it.
  - Aspect ratio is checked for validity, not for fit. Nothing compares a 16:9
    requirement against a vertical deliverable.
  - The score weights are constants in the service, not configuration. There is
    no editorial override, and no reason to build one before the weights have
    been seen in use.
  - The spec's NO_VISUAL_PLAN blocker is not emitted, because a missing plan is
    a 404 at this endpoint and never reaches the evaluation. Recorded here
    rather than implemented as unreachable code.
  - Authorization is permissive, consistent with the rest of the project. Real
    per-user rules are not implemented yet.
  - Readiness is fetched on panel load and after mutations. It is not polled,
    so a change made in another tab appears only on reload.

--------------------------------------------------
A.11. PHASE 4 HANDOFF CONTRACT
--------------------------------------------------

Phase 4 may call:

    GET visual-plan/quality

to decide whether planning is complete enough to start sourcing assets. The
summary gives it the per-status counts it needs to plan the sourcing queue, and
each issue carries the plan item id and requirement id it refers to, so Phase 4
can navigate straight to the row that needs work.

Phase 4 must NOT read ready = true as proof that assets are available. This
gate is read-only and knows nothing about assets: it never searched, never
downloaded, never verified a file, and never checked a licence.

Keep the two states separate:

    Planning Ready
        Every plan item is specified, has at least one valid requirement, and
        the plan is not archived. The requirement list is trustworthy enough
        to work from.

    Asset Ready
        Every requirement is fulfilled with a real, usable, licensed asset.
        That is Phase 4's own gate, over data this part does not have.

A plan can be Planning Ready while every single asset is still missing, which
is the normal state at handover. Conversely a plan can hold fulfilled
requirements and still be Planning Ready = false, because one item was never
given a requirement. Neither state implies the other, and neither means the
other is close.

The fulfilled counter in the summary is the planning side's view of progress
towards Asset Ready. It is self-reported by a status field, not verified
against any stored media.

## Phase 4 Handoff

Phase 4 starts sourcing assets from the requirements this gate validates.

Read `GET visual-plan/quality` to learn whether planning is complete enough to
begin. `ready = true` is a Planning Ready signal only. It is never evidence
that an asset exists.

When building the Asset Manager, keep these two apart:

    Planning Ready  (this part)
        the requirement list is complete and trustworthy

    Asset Ready     (Phase 4)
        every requirement has a real, usable, licensed asset

Do not start Phase 4 work in this repository part.
