# MOCHYFAMI CONTENT STUDIO

# PHASE 3 — PART 8

# SCRIPT QUALITY & RESEARCH ALIGNMENT GATE

You are continuing development of the existing MochyFami Content Studio repository.

IMPORTANT:
This is an existing working codebase.

DO NOT rebuild the application.
DO NOT redesign unrelated areas.
DO NOT modify completed Phase 3 Parts 1–7 unless absolutely required.

==================================================

1. # PRIMARY OBJECTIVE

Implement:

PHASE 3 — PART 8
"Script Quality & Research Alignment Gate"

The purpose of this part is to create a deterministic validation layer
between:

Research
↓
Research Quality
↓
Research Pipeline
↓
Script
↓
Script Quality / Alignment
↓
AI Generation / Refinement later

This part must NOT implement AI generation.

The system should evaluate the CURRENT ScriptVersion against the
project's ResearchReport.

The evaluation must be:

- deterministic
- explainable
- read-only
- database-backed
- testable
- fast
- provider-independent

================================================== 2. FIRST STEP — INSPECT REPOSITORY
==================================================

Before changing anything:

Inspect the actual implementation of:

- ResearchReport
- ResearchClaim
- Source
- ResearchQualityService
- ResearchPipelineService
- Script
- ScriptVersion
- ScriptService
- ScriptStatus
- ScriptPanel
- ResearchPanel
- existing API Resources
- existing exceptions
- existing service patterns
- existing tests
- existing routes
- existing project ownership conventions

Read:

docs/PHASE_3_PART_7.md

Also inspect the implementation from Parts 1–7.

The repository is authoritative.

Do NOT assume the Phase 0 specification is identical to the actual
implementation.

================================================== 3. CORE PRINCIPLE
==================================================

Part 8 must NOT attempt to understand the script semantically using AI.

There is no LLM involved.

Instead, implement deterministic checks that answer:

1. Does the Script have content?
2. Does the Script have a current version?
3. Is the Script status appropriate for review?
4. Is the Research itself ready?
5. Are important research claims represented in the script?
6. Are unsupported factual claims detectable through explicit markers
   or deterministic matching?
7. Does the script contain obvious placeholders?
8. Is the script within reasonable Shorts duration/content limits?

IMPORTANT:

Do not pretend deterministic keyword matching is true semantic
fact-checking.

The API must describe the result as:

"alignment / quality heuristics"

rather than claiming factual correctness.

================================================== 4. DO NOT MUTATE SCRIPT
==================================================

ScriptQualityService must be READ-ONLY.

It must NOT:

- modify Script
- modify ScriptVersion
- modify ResearchClaim
- modify ResearchStatus
- modify ScriptStatus
- create versions
- create claims
- create sources
- call AI
- call external HTTP
- persist quality scores

Evaluation should be derived from current database state.

================================================== 5. CREATE SCRIPT QUALITY SERVICE
==================================================

Create:

app/Services/Script/ScriptQualityService.php

Primary method:

evaluateScript(Script $script): ScriptQualityResult

Use a small immutable DTO/value object if appropriate.

For example:

ScriptQualityResult

containing:

- ready
- score
- summary
- blockers
- warnings
- checks

The exact implementation should follow existing project patterns.

================================================== 6. QUALITY CHECK MODEL
==================================================

Do NOT create a database table for quality results.

Quality is derived dynamically.

A result should contain a list of deterministic checks.

Each check should expose something similar to:

{
"code": "...",
"severity": "blocker|warning|info",
"passed": true,
"message": "...",
"details": {...}
}

Do not expose internal implementation details.

================================================== 7. REQUIRED BLOCKERS
==================================================

Implement at least these blockers.

---

## BLOCKER 1 — NO CURRENT VERSION

Code:

NO_CURRENT_VERSION

Condition:

Script exists but current_version is missing.

Result:

ready = false

---

## BLOCKER 2 — EMPTY SCRIPT

Code:

EMPTY_SCRIPT

Condition:

Current version has no meaningful script content.

Meaningful content should consider:

- hook
- body
- closing

If all are empty/whitespace:

blocker.

---

## BLOCKER 3 — RESEARCH NOT READY

Code:

RESEARCH_NOT_READY

Condition:

ResearchReport does not exist OR ResearchQualityService says ready=false.

The quality evaluation should reuse:

ResearchQualityService

Do NOT duplicate its algorithm.

---

## BLOCKER 4 — SCRIPT STATUS NOT REVIEWABLE

Code:

SCRIPT_STATUS_NOT_REVIEWABLE

For quality evaluation, acceptable statuses should be:

review
approved

A draft can be evaluated but should not be considered ready.

If the project architecture benefits from allowing quality evaluation
for draft scripts, return a warning for draft rather than blocking
evaluation itself.

IMPORTANT:

Separate:

"can evaluate"

from:

"ready for downstream production"

================================================== 8. REQUIRED WARNING CHECKS
==================================================

Implement deterministic warnings for common script problems.

---

## WARNING 1 — PLACEHOLDER CONTENT

Code:

PLACEHOLDER_CONTENT

Detect obvious placeholder patterns such as:

- TODO
- TBC
- TBD
- lorem ipsum
- [insert ...]
- [isi ...]
- <placeholder>
- {{...}}

Case-insensitive.

Do NOT attempt broad natural-language interpretation.

---

## WARNING 2 — VERY SHORT SCRIPT

Code:

SCRIPT_TOO_SHORT

If combined script text is suspiciously short.

Use a reasonable threshold based on the repository.

Do NOT make the threshold arbitrary without documenting it.

---

## WARNING 3 — VERY LONG SCRIPT

Code:

SCRIPT_TOO_LONG

If the script is clearly outside the intended Shorts duration/content
range.

Use duration_seconds when available.

If duration_seconds is not available, do NOT pretend to know the exact
spoken duration.

A character/word-count heuristic may be used as a warning.

Document the heuristic.

---

## WARNING 4 — MISSING HOOK

Code:

MISSING_HOOK

Hook empty.

---

## WARNING 5 — MISSING BODY

Code:

MISSING_BODY

Body empty.

---

## WARNING 6 — MISSING CLOSING

Code:

MISSING_CLOSING

Closing empty.

This should be a WARNING, not a blocker, because some short-form
scripts may intentionally end without a formal closing.

================================================== 9. RESEARCH CLAIM ALIGNMENT
==================================================

This is the most important part of Part 8.

The system should compare important ResearchClaims against the current
script text.

DO NOT use AI.

DO NOT claim semantic understanding.

Use deterministic normalized text matching.

---

## NORMALIZATION

Create a small reusable normalizer if appropriate.

Normalize:

- lowercase
- trim whitespace
- normalize repeated whitespace
- remove obvious punctuation
- optionally normalize simple Indonesian punctuation

Do not implement aggressive stemming unless the repository already has
such infrastructure.

---

## CLAIM TEXT

Determine the text to match from the ResearchClaim.

Inspect the actual ResearchClaim model/schema.

Use the repository's real claim fields.

Do NOT invent fields.

If a claim contains a concise claim statement/text field, use that.

If the claim model has multiple textual fields, document which one is
used.

---

## MATCHING

For each IMPORTANT ResearchClaim:

- determine whether a reasonable textual representation appears in the
  script.

The matching algorithm must be intentionally conservative.

Possible strategy:

1. normalize claim text
2. tokenize
3. remove extremely common Indonesian stopwords if justified
4. compare meaningful tokens
5. require a documented minimum overlap

IMPORTANT:

Do not treat a single matching word as evidence.

The algorithm should produce:

supported_by_text
not_detected
insufficient_text

or a similar explicit state.

Document the heuristic.

================================================== 10. CLAIM ALIGNMENT RESULT
==================================================

For each important ResearchClaim expose:

- claim_id
- importance
- status
- matched
- match_score
- message

Example:

{
"claim_id": 12,
"importance": "high",
"status": "supported",
"matched": true,
"match_score": 0.82,
"message": "Claim text is represented in the current script."
}

The exact scoring method is up to implementation, but it must be:

- deterministic
- documented
- bounded
- reproducible

Do NOT call this a factual verification score.

It is a textual alignment heuristic.

================================================== 11. CLAIM ALIGNMENT BLOCKERS
==================================================

For HIGH importance claims:

If a high-importance ResearchClaim is:

- supported but not represented in script

create blocker:

IMPORTANT_CLAIM_NOT_ALIGNED

If a high-importance claim has:

- uncertain
- contradicted
- unverified

then create blocker:

IMPORTANT_CLAIM_NOT_RESEARCH_READY

Do NOT duplicate ResearchQualityService logic.

Reuse its evaluation where possible.

For non-high claims:

missing textual alignment should be a WARNING rather than blocker.

================================================== 12. CONTRADICTION SAFETY
==================================================

If ResearchQualityService indicates:

IMPORTANT_CLAIM_UNCERTAIN
IMPORTANT_CLAIM_CONTRADICTED
IMPORTANT_CLAIM_UNVERIFIED

the Script Quality result must clearly surface the issue.

Do NOT say:

"the script is false"

Instead use wording such as:

"An important research claim is not sufficiently verified."

This keeps the system factual and explainable.

================================================== 13. SCORE
==================================================

Provide an optional score from 0–100.

The score must NOT replace blockers.

A script with blockers:

ready = false

regardless of score.

Recommended conceptual components:

- research readiness
- content completeness
- script structure
- important claim alignment
- placeholder detection

Document the formula.

Do not create arbitrary precision.

Use integer score 0–100.

Example:

score = round(...)

Do not expose fake decimal precision.

================================================== 14. READY RULE
==================================================

The final:

ready

must be:

true

ONLY when:

- no blockers exist
- current version exists
- script contains meaningful content
- research is ready
- all required high-importance research checks pass
- script status is review or approved

Warnings may exist while:

ready = true

This distinction is important.

================================================== 15. SUMMARY
==================================================

Return a compact summary such as:

{
"total_checks": 12,
"passed_checks": 8,
"failed_checks": 4,
"blocker_count": 0,
"warning_count": 4,
"important_claims": 3,
"aligned_important_claims": 3
}

Use deterministic values.

================================================== 16. API ENDPOINT
==================================================

Add:

GET

/api/v1/projects/{project}/script/quality

Behavior:

- project must be accessible under existing project convention
- Script missing → 404
- current version missing → quality response with blocker
  OR 404 only if repository conventions require it
- no mutation
- no external calls

Use:

ScriptQualityController

or appropriate existing controller architecture.

Response should use the project's standard ApiResponse.

================================================== 17. RESOURCE
==================================================

Create:

ScriptQualityResource

OR an appropriate dedicated quality resource.

Do NOT reuse ScriptResource if doing so makes the response confusing.

Response structure should include:

{
"script_id": ...,
"version_id": ...,
"version": ...,
"status": ...,
"ready": true,
"score": 92,
"summary": {...},
"blockers": [...],
"warnings": [...],
"checks": [...],
"claim_alignment": [...]
}

Keep it stable and frontend-friendly.

================================================== 18. FRONTEND — SCRIPT QUALITY CARD
==================================================

Update:

ScriptPanel.tsx

Add a:

"Script Quality"

card.

It should show:

- Ready / Not Ready
- score
- current version
- blocker count
- warning count
- important claim alignment
- check results

Use the existing visual language from:

Research Quality
Research Pipeline

Do not redesign the entire ScriptPanel.

================================================== 19. QUALITY STATES
==================================================

Use clear states.

Example:

READY
Script quality passed the required checks.

NOT READY
One or more blockers must be resolved.

WARNINGS
Script can be ready while warnings remain.

Do not use misleading labels such as:

"Factually Correct"

because deterministic matching cannot establish factual truth.

================================================== 20. REFRESH BEHAVIOR
==================================================

Quality should refresh after:

- creating Script
- creating new version
- editing current version
- changing Script status

Do NOT poll.

Do NOT auto-refresh continuously.

Follow the existing ResearchPanel refresh conventions.

================================================== 21. FRONTEND TYPES
==================================================

Add types for:

ScriptQualityResult
ScriptQualityCheck
ScriptQualitySummary
ScriptClaimAlignment

Avoid `any`.

Do not bypass typing.

================================================== 22. API SERVICE
==================================================

Update:

scriptService.ts

Add:

getQuality()

or equivalent.

Keep service methods small.

================================================== 23. PERFORMANCE
==================================================

Avoid N+1 queries.

Research claims, evidence and current ScriptVersion should be loaded
efficiently.

Use:

loadMissing()

or equivalent repository conventions.

Quality evaluation should use a bounded number of queries.

Add a query count test if practical.

================================================== 24. NO DATABASE PERSISTENCE
==================================================

Do NOT create:

script_quality_results
script_quality_checks
script_scores

Quality must be derived.

This keeps the system simple and prevents stale quality results.

================================================== 25. TESTING — SERVICE
==================================================

Create comprehensive unit tests for:

1. no Script / invalid input behavior
2. no current version
3. empty script
4. research missing/not ready
5. draft status
6. review status
7. approved status
8. placeholder detection
9. missing hook
10. missing body
11. missing closing
12. important claim alignment
13. important claim not aligned
14. non-important claim not aligned
15. important claim uncertain
16. important claim contradicted
17. important claim unverified
18. score calculation
19. ready calculation
20. deterministic repeated evaluation
21. no database mutation
22. no external HTTP

Use factories/helpers where appropriate.

================================================== 26. TESTING — API
==================================================

Add feature tests for:

- GET quality
- missing Script
- project scope
- correct resource structure
- blockers
- warnings
- ready state
- status behavior
- current version behavior

If existing architecture intentionally allows any authenticated user
to access another user's project, preserve that convention.

Do NOT introduce a new ownership model in Part 8.

================================================== 27. DETERMINISM TEST
==================================================

Important:

Evaluate the same Script multiple times.

The output should be equivalent.

No timestamps should affect quality.

No random score.

No network.

No AI.

No generated IDs inside the result.

================================================== 28. RESEARCH QUALITY REUSE
==================================================

Do NOT duplicate:

ResearchQualityService

logic.

Use:

ResearchQualityService::evaluateReport()

or the actual method exposed by the repository.

If its return DTO is not reusable directly, adapt it without changing
the Research Quality contract unnecessarily.

================================================== 29. RESEARCH PIPELINE REUSE
==================================================

Where useful, use:

ResearchPipelineService

to determine:

ready_for_script

But do not duplicate its stage algorithm.

Prefer the existing source of truth.

The final Script Quality result may use both:

ResearchQualityService
ResearchPipelineService

only where appropriate.

Avoid circular dependencies.

================================================== 30. NO AI / NO EXTERNAL NETWORK
==================================================

This part must have:

ZERO AI provider calls.

ZERO search provider calls.

ZERO HTTP requests.

Use:

Http::preventStrayRequests()

where appropriate in tests.

================================================== 31. SECURITY
==================================================

Verify:

- no secrets
- no debug output
- no shell execution
- no arbitrary SQL
- no unscoped project lookup beyond existing conventions
- no mass assignment vulnerabilities
- no user-controlled score
- no user-controlled readiness
- no user-controlled claim alignment result

Everything must be calculated server-side.

================================================== 32. CODE QUALITY
==================================================

Run:

php artisan test --compact

npx tsc --noEmit

npm run build

./vendor/bin/pint --dirty --format agent

Also run migration verification if the implementation changes schema.

Do NOT add a migration unless actually required.

================================================== 33. DOCUMENTATION
==================================================

Create:

docs/PHASE_3_PART_8.md

Document:

- objective
- deterministic quality philosophy
- blockers
- warnings
- claim alignment heuristic
- score formula
- ready rule
- API
- frontend
- testing
- known limitations

IMPORTANT:

Explicitly document:

"This system does not establish semantic factual truth.
Claim alignment is a deterministic textual heuristic."

================================================== 34. GIT
==================================================

Before implementation:

git status

After implementation:

git diff --stat
git status

Review all modified files.

Create ONE focused commit:

feat: add script quality and research alignment gate

Do NOT push automatically.

Do NOT include unrelated changes.

================================================== 35. COMPLETION REPORT
==================================================

When finished, report exactly:

PHASE 3 PART 8 REPORT

Status:
PASS / BLOCKED

Implementation:

- ScriptQualityService:
- Quality result:
- Blockers:
- Warnings:
- Claim alignment:
- Score:
- Ready rule:
- API:
- Frontend:
- Research integration:
- Documentation:

Verification:

- Tests:
- Assertions:
- TypeScript:
- Build:
- Pint:
- Migration:
- Determinism:
- No external HTTP:
- No mutation:

Git:

- Commit:
- Working tree:

Known limitations:

- ...

IMPORTANT:

Never claim PASS unless all reported checks actually passed.

If something failed, report the exact failure.

================================================== 36. STOP CONDITION
==================================================

After Part 8 is complete:

STOP.

Do NOT start Part 9.

Wait for explicit instruction:

"Lanjut Part 9"

==================================================
FINAL ARCHITECTURE PRINCIPLE
==================================================

ResearchQuality answers:

"Apakah research sudah cukup kuat?"

ResearchPipeline answers:

"Di tahap mana research berada?"

ScriptQuality answers:

"Apakah script saat ini memenuhi pemeriksaan
deterministik dan cukup selaras dengan research?"

None of these systems should pretend to replace human review.

AI generation and refinement will be implemented only after this
foundation is stable.
