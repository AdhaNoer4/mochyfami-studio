# PHASE 5 REPOSITORY DISCOVERY — PLANNING ONLY

IMPORTANT:
You are in REPOSITORY DISCOVERY MODE only.

DO NOT:

- modify files
- create files
- delete files
- run migrations
- generate code
- generate tests
- commit
- push
- edit documentation

Your task is ONLY:

1. inspect the repository,
2. understand current architecture,
3. identify reusable components,
4. detect constraints,
5. propose a Phase 5 TTS architecture.

The repository baseline is:

Commit:
f8de70e
fix: stabilize pre-phase-5 workflow

Current verified baseline:

- 1424 tests
- 4503 assertions
- full suite passing
- TypeScript clean
- Vite build clean
- Phase 1–4 complete
- Production Specification complete
- Pre-Phase-5 stabilization complete

====================================================
GOAL
====================================================

Produce a COMPLETE architecture discovery report for:

PHASE 5 — TTS (Text-to-Speech) Foundation

This is NOT implementation.

This is NOT coding.

This is repository analysis and planning only.

====================================================
DISCOVERY SCOPE
====================================================

Inspect:

1. Existing models
2. Existing services
3. Existing enums
4. Existing provider patterns
5. Existing fake providers
6. Existing storage architecture
7. Existing file upload architecture
8. Existing metadata architecture
9. Existing readiness gates
10. Existing manifest pattern
11. Existing project/version scoping
12. Existing authorization patterns
13. Existing controller/resource patterns
14. Existing frontend patterns
15. Existing queue/job/event patterns
16. Existing testing conventions
17. Existing API conventions
18. Existing docs conventions

====================================================
SPECIFIC QUESTIONS TO ANSWER
====================================================

A. SCRIPT DOMAIN

Inspect:

- Script
- ScriptVersion
- ScriptSection
- narration structure
- line structure
- ordering
- revision workflow

Answer:

1. What is the smallest narration unit suitable for TTS?
2. Should TTS attach to:
    - ScriptVersion
    - ScriptSection
    - VisualPlanItem
    - another entity?
3. How are narration lines ordered?
4. Is timing information already present?
5. What reusable identifiers exist?

---

B. VISUAL PLAN / PRODUCTION

Inspect:

- VisualPlan
- AssetRequirement
- Production Manifest
- Production Specification

Answer:

1. How should TTS connect to production?
2. Does Production Specification already provide enough structure?
3. Which entity should consume TTS later?
4. How would subtitles use TTS timing later?

---

C. STORAGE

Inspect:

- Asset storage
- private disk
- metadata
- upload flow

Answer:

1. Can TTS reuse Asset?
2. Should TTS be a new entity?
3. Should TTS audio become an Asset?
4. How should private audio files be stored?
5. Can metadata architecture be reused?

---

D. PROVIDERS

Inspect:

- FakeResearchProvider
- FakeScriptProvider
- provider interfaces
- config patterns

Answer:

1. Best provider architecture?
2. Interface design?
3. Fake provider strategy?
4. Real provider extensibility?

Example:

TtsProviderInterface
FakeTtsProvider
ElevenLabsProvider
GoogleProvider
OpenAIProvider

---

E. AUDIO DOMAIN

Determine:

1. What metadata is needed?

Example:

- duration
- sample_rate
- channels
- format
- bitrate

2. Which metadata can FFprobe provide?
3. Which existing metadata code can be reused?

---

F. WORKFLOW

Design the ideal workflow:

Script
↓
TTS Request
↓
Generate
↓
Audio File
↓
Metadata
↓
Readiness
↓
Manifest
↓
Subtitle
↓
Render

Explain each step.

---

G. READINESS

Inspect:

- Planning readiness
- Asset readiness
- Production handoff
- Production specification

Answer:

1. Should TTS have its own readiness gate?
2. What blockers exist?
3. What warnings exist?
4. What manifest exists?

---

H. FRONTEND

Inspect:

- VisualPlanPanel
- AssetLibrary
- modal patterns
- service patterns

Answer:

1. Best UI location for TTS?
2. Separate panel?
3. New tab?
4. Per narration item?

---

I. QUEUES

Inspect:

- queues
- jobs
- events

Answer:

1. Synchronous?
2. Queue-ready?
3. Retry strategy?

---

J. SECURITY

Determine:

1. Cross-project isolation
2. Private audio access
3. Preview strategy
4. Download strategy
5. Path leakage prevention

---

K. TESTING

Inspect repository testing style.

Design:

1. Unit tests
2. Feature tests
3. API tests
4. Mutation tests
5. E2E tests

---

L. PHASE BREAKDOWN

Propose:

PHASE 5 Part 1
PHASE 5 Part 2
PHASE 5 Part 3
...

For each part explain:

- purpose
- scope
- migrations
- API
- frontend
- tests

====================================================
IMPORTANT CONSTRAINTS
====================================================

Assume:

- No external API keys
- Fake provider first
- Deterministic tests
- No network dependency
- Human-controlled workflow
- Private storage
- No public URLs
- Production pipeline consistency

====================================================
OUTPUT FORMAT
====================================================

Return ONLY:

1. Repository findings
2. Reusable architecture
3. Constraints
4. Risks
5. Recommendations
6. Phase breakdown
7. Open decisions

DO NOT:

- write code
- generate files
- modify repository

STOP after the planning report.

No implementation.
No edits.
No commits.
No push.
