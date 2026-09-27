PHASE 3 — PART 12
SCRIPT RESEARCH TRACEABILITY & CLAIM MAPPING

Project: MochyFami Content Studio

==================================================
CONTEXT
==================================================

Phase 3 Part 1–11 are completed.

Latest completed commit:

99a0c65 feat: add research to script context bridge

Latest verified baseline:

- 570 tests
- 2063 assertions
- TypeScript clean
- Vite build successful
- Pint passes
- working tree clean
- no push yet

Part 11 introduced:

ResearchToScriptContext
ResearchToScriptContextService

with deterministic claim classification:

- usable
- requires_verification
- contradicted
- unsupported

and integrated:

ResearchQualityService
ResearchPipelineService
ScriptGenerationService

Part 11 also introduced:

GET /api/v1/projects/{project}/research/script-context

The current architecture is:

Research
↓
Research Quality
↓
ResearchToScriptContext
↓
AI Script Generation
↓
Script Version
↓
Script Quality

==================================================
PRIMARY GOAL
==================================================

Build a deterministic:

SCRIPT ↔ RESEARCH CLAIM TRACEABILITY SYSTEM

The purpose is to make it possible to answer:

"Which research claims support this script?"

and:

"Which research claims are represented in this script?"

The system must allow the application to maintain a mapping between:

Script Version
↕
Research Claim

without pretending that AI has proven the relationship.

This feature is for:

- factual traceability
- review
- auditability
- human fact-checking
- future citation/reference UI
- future script revision
- future video review

It is NOT intended to automatically prove that a script is factually correct.

==================================================
IMPORTANT ARCHITECTURE RULE
==================================================

Before coding:

1. Inspect the entire current repository.
2. Inspect Script model.
3. Inspect ScriptVersion model.
4. Inspect ResearchClaim model.
5. Inspect ResearchToScriptContextService.
6. Inspect ScriptGenerationService.
7. Inspect ScriptQualityService.
8. Inspect existing migrations.
9. Inspect existing Resources.
10. Inspect existing frontend ScriptPanel.
11. Inspect existing frontend ResearchPanel.
12. Inspect all relevant tests.

Do NOT assume the exact schema from previous prompts.

Reuse current project conventions.

Do NOT redesign the Research or Script architecture.

==================================================
PART A — TRACEABILITY DATA MODEL
==================================================

Introduce a mapping between:

script_versions
and
research_claims

Suggested conceptual table:

script_version_research_claim

Fields should be minimal.

At minimum:

- id
- script_version_id
- research_claim_id
- relationship/type if actually needed
- timestamps

IMPORTANT:

Do not add unnecessary fields.

Before adding a relationship/type field, determine whether the existing architecture genuinely requires it.

If relationship type is useful, possible values may conceptually be:

- supports
- informs
- caveat

But do NOT create these blindly.

The minimum viable requirement is:

ScriptVersion ↔ ResearchClaim

with a unique constraint preventing duplicate mappings.

Foreign keys should follow existing project conventions.

Deletion behavior must be safe.

==================================================
PART B — MODEL RELATIONSHIPS
==================================================

Add appropriate relationships:

ScriptVersion
→ researchClaims()

ResearchClaim
→ scriptVersions()

Use belongsToMany or the project's existing relationship convention.

Ensure:

- eager loading works
- no N+1
- pivot timestamps if consistent with existing conventions

Do not expose unnecessary pivot internals.

==================================================
PART C — TRACEABILITY SERVICE
==================================================

Create a dedicated service.

Suggested:

ScriptResearchTraceabilityService

Responsibilities:

1. Get mappings for a script version.
2. Attach a research claim to a script version.
3. Detach a research claim.
4. Validate project ownership/scope.
5. Validate script version belongs to project.
6. Validate research claim belongs to project's research report.
7. Prevent duplicate mapping.
8. Prevent cross-project mapping.
9. Return deterministic traceability information.

This service must NOT:

- generate AI
- mutate research claims
- change research status
- change script status
- modify script text
- modify script quality
- automatically mark anything verified

==================================================
PART D — SCOPE VALIDATION
==================================================

This is critical.

A mapping is valid only when:

script version
↓
belongs to script
↓
belongs to project

AND

research claim
↓
belongs to research report
↓
belongs to same project

Cross-project mapping MUST fail.

Follow the existing application behavior for authorization/errors.

Do not introduce a separate ownership system.

For invalid cross-scope resources, follow the existing convention, likely 404 rather than leaking resource existence.

==================================================
PART E — DUPLICATE PROTECTION
==================================================

The same pair:

script_version_id + research_claim_id

must not be attachable twice.

Protect at:

1. service layer
2. database unique constraint

Use a controlled domain exception for duplicate relations if that matches existing evidence-relation conventions.

Expected API behavior should be consistent with existing duplicate evidence relation behavior.

==================================================
PART F — API
==================================================

Create endpoints following existing route conventions.

Suggested:

GET

/api/v1/projects/{project}/script/versions/{version}/research-claims

POST

/api/v1/projects/{project}/script/versions/{version}/research-claims/{claim}

DELETE

/api/v1/projects/{project}/script/versions/{version}/research-claims/{claim}

If the existing route style suggests a better equivalent, follow the existing convention.

API must support:

- list mapped claims
- attach claim
- detach claim

All operations must be scoped to the project.

Do not expose internal pivot details unnecessarily.

==================================================
PART G — API RESOURCES
==================================================

Return research claim information useful for review.

Suggested response:

{
"id": 123,
"claim": "...",
"importance": "high",
"status": "supported",
"has_evidence": true,
"sources": [...]
}

If sources are needed for traceability, reuse existing SourceResource conventions.

Do not duplicate resource serialization logic.

==================================================
PART H — SCRIPT GENERATION INTEGRATION
==================================================

This part must carefully integrate with Part 11.

When a new AI-generated ScriptVersion is created:

DO NOT automatically create research mappings unless there is a deterministic and explicit mechanism that can safely identify the relationship.

The default behavior should be:

generated script version
↓
NO AUTOMATIC TRACEABILITY CLAIMS

unless the existing provider output explicitly returns claim IDs and the architecture already supports safe mapping.

Why?

Because matching text does not prove factual provenance.

A generated sentence may be:

- based on a research claim
- partially based on a claim
- synthesized from multiple claims
- generated from model knowledge
- unsupported

Do not pretend otherwise.

Therefore the MVP should favor:

EXPLICIT HUMAN ATTACHMENT

through the UI/API.

==================================================
PART I — OPTIONAL AI SUGGESTION
==================================================

Do NOT implement automatic AI claim mapping in this part.

If useful, document as a future feature:

"AI-assisted traceability suggestions"

Future behavior could suggest:

Script sentence → Research Claim

but a human would confirm the mapping.

Do not implement it now.

==================================================
PART J — SCRIPT QUALITY INTEGRATION
==================================================

Inspect ScriptQualityService.

Do NOT replace or duplicate its current alignment algorithm.

However, expose traceability information as an additional review signal if appropriate.

Important distinction:

SCRIPT QUALITY ALIGNMENT

answers:

"Does the script text appear aligned with research?"

TRACEABILITY

answers:

"Which specific research claims has the reviewer linked to this script version?"

These are NOT the same thing.

Do not make traceability automatically increase or decrease ScriptQualityService score unless the existing architecture clearly supports that behavior.

Prefer keeping it as separate review information.

==================================================
PART K — TRACEABILITY SUMMARY
==================================================

Create a deterministic summary for a ScriptVersion.

Suggested fields:

- total_claims
- supported_claims
- unverified_claims
- uncertain_claims
- contradicted_claims
- claims_with_evidence
- claims_without_evidence

Also expose:

- traceability_complete
- warnings

However:

Do not invent a score unless there is a strong architectural reason.

Prefer boolean/status information over another arbitrary numeric score.

Possible rule:

traceability_complete = true

only if every mapped claim:

- exists
- belongs to same project
- is not contradicted
- satisfies the chosen traceability requirement

Do NOT equate traceability_complete with factual correctness.

==================================================
PART L — FRONTEND SCRIPT PANEL
==================================================

Update ScriptPanel.

Add a section:

"Research Traceability"

For the current script version show:

- mapped research claims
- claim status
- importance
- evidence availability
- source count
- source domains
- traceability summary

Provide:

- "Add Research Claim"
- remove/detach action
- loading states
- empty state
- error state
- success feedback

When adding a claim:

Show only claims from the current project's research report.

Do not allow cross-project IDs through the UI.

==================================================
PART M — CLAIM PICKER
==================================================

Create a simple claim selection UI.

It should show:

Claim text
Status
Importance
Evidence count

Recommended grouping:

Supported
Requires Verification
Contradicted

The UI should make status visible.

Do not hide contradicted claims.

If a contradicted claim is selected, display a warning before attaching it.

However:

Do not automatically prevent attachment unless the backend domain rules explicitly require it.

The purpose of traceability is auditability.

A reviewer may intentionally attach a contradicted claim to document that the script contains or discusses a disputed point.

==================================================
PART N — SCRIPT VERSION SWITCHING
==================================================

This is important.

Traceability belongs to:

SCRIPT VERSION

not:

SCRIPT

Therefore:

Version 1
→ its own research mappings

Version 2
→ its own research mappings

Version 3
→ its own research mappings

When the user switches script versions:

load the mappings for that version.

Do NOT copy mappings automatically to a new version.

A new script version must start with its own traceability state.

This preserves historical auditability.

==================================================
PART O — IMMUTABILITY / HISTORY
==================================================

Existing script versions are intended to remain immutable in content.

Attaching/detaching research mappings must NOT alter:

- hook
- body
- closing
- duration
- script text
- version number

It only modifies traceability metadata.

Do not create a new ScriptVersion merely because a research claim is attached.

==================================================
PART P — API RESPONSE CONVENTIONS
==================================================

Follow existing ApiResponse conventions.

Expected:

GET:
200

POST attach:
201 or existing relation convention

DELETE:
204 or existing deletion convention

Duplicate:
409

Cross-project:
404 according to existing convention

Validation:
422

Do not invent a new error format.

==================================================
PART Q — TESTING
==================================================

Add comprehensive tests.

Database/model tests:

- mapping can be created
- duplicate blocked
- relationships work
- cascade behavior works if applicable

Service tests:

- list mappings
- attach mapping
- detach mapping
- duplicate mapping
- cross-project claim rejected
- cross-project script rejected
- invalid version rejected
- invalid claim rejected
- no research report rejected appropriately
- deterministic summary

API tests:

- authentication
- list
- attach
- detach
- duplicate
- cross-project isolation
- validation
- response shape

Script version tests:

- version 1 mappings isolated from version 2
- version switching loads correct mappings
- creating new script version does not copy mappings automatically
- existing mappings survive script content version operations

Integration tests:

- ScriptGenerationService still works
- ResearchToScriptContext still works
- ScriptQualityService still works
- traceability does not mutate research
- traceability does not change script status
- traceability does not change research status

Frontend:

At minimum:

- TypeScript clean
- build clean

Follow existing frontend test conventions if present.

==================================================
PART R — QUERY PERFORMANCE
==================================================

Avoid N+1.

When loading traceability:

Prefer eager loading:

research claim
→ sources

where required by the UI.

Do not repeatedly query sources for every claim.

Add query-count coverage where useful.

==================================================
PART S — SECURITY
==================================================

Do not trust IDs supplied by the client.

Always validate:

project
script
script version
research report
research claim

relationships server-side.

Never permit:

project A script version

- project B research claim

Do not expose resources across project scope.

==================================================
PART T — MIGRATION
==================================================

This part is expected to require a migration.

Create the smallest migration necessary.

Suggested:

create_script_version_research_claim_table

Use:

- foreign keys
- cascade behavior where safe
- unique(script_version_id, research_claim_id)
- timestamps if consistent with existing pivot conventions

Verify:

1. fresh migration
2. rollback
3. migrate again

Do not modify unrelated migrations.

==================================================
PART U — DOCUMENTATION
==================================================

Create:

docs/PHASE_3_PART_12.md

Document:

1. Goal
2. Data model
3. Relationships
4. Traceability service
5. Scope rules
6. Duplicate protection
7. API
8. Frontend
9. Version isolation
10. Script generation behavior
11. Quality integration
12. Security
13. Testing
14. Migration
15. Known limitations
16. Future AI-assisted mapping

Explicitly state:

"Traceability is provenance metadata, not proof of factual correctness."

Also state:

"AI-generated script versions are not automatically assigned research claims."

==================================================
PART V — CODE QUALITY
==================================================

Follow existing conventions.

Do NOT:

- redesign ScriptVersion
- redesign ResearchClaim
- redesign ScriptQualityService
- redesign ResearchQualityService
- redesign ResearchToScriptContext
- implement AI claim matching
- introduce automatic provenance
- introduce arbitrary scoring
- add unrelated dependencies
- add network calls
- add shell execution
- add TODO/FIXME
- add debug output
- weaken existing tests

Keep the change focused.

==================================================
PART W — VERIFICATION
==================================================

Run:

php artisan test --compact

npx tsc --noEmit

npm run build

vendor/bin/pint --test

If Pint changes files:

run Pint and re-run tests.

Migration:

- fresh
- rollback
- migrate
- migrate:status

Inspect:

git diff

git status

Do not claim success without actual verification.

==================================================
PART X — FINAL REPORT
==================================================

Report:

PHASE 3 PART 12 STATUS

1. Implementation summary
2. Data model
3. Files created
4. Files modified
5. API endpoints
6. Traceability rules
7. Version isolation
8. Script generation behavior
9. Script quality integration
10. Frontend changes
11. Tests added
12. Full test count/assertion count
13. TypeScript result
14. Build result
15. Pint result
16. Migration result
17. Git status
18. Commit hash
19. Known limitations

==================================================
GIT CHECKPOINT
==================================================

If all verification passes:

Create exactly one focused commit.

Suggested commit:

feat: add script research traceability

Do NOT push automatically.

After commit:

- verify git status
- report commit hash
- STOP

==================================================
STOP CONDITION
==================================================

Part 12 is complete only when:

- ScriptVersion ↔ ResearchClaim mapping exists
- mappings are version-specific
- cross-project mapping is impossible
- duplicate mappings are prevented
- API works
- frontend review UI works
- source/evidence traceability is visible
- no automatic AI provenance is claimed
- existing research is not mutated
- existing script content is not mutated
- ScriptQualityService remains independent
- ResearchQualityService remains independent
- tests pass
- TypeScript passes
- build passes
- Pint passes
- migration passes
- focused commit created
- working tree clean

Then STOP.

DO NOT IMPLEMENT PART 13.
