PHASE 3 — PART 11
RESEARCH-TO-SCRIPT CONTEXT BRIDGE

Project: MochyFami Content Studio

==================================================
CONTEXT
==================================================

Phase 3 Part 1–10 sudah selesai.

Latest completed feature:

PHASE 3 PART 10
AI Research Generation Foundation

Latest commit:
3114b09 feat: add ai research generation foundation

Current verified baseline after Part 10:

- 537 tests
- 1873 assertions
- TypeScript clean
- Vite build successful
- Pint passes
- migration fresh/rollback/re-migrate verified
- working tree clean

Part 10 introduced:

- ResearchGenerationProvider
- ResearchGenerationRequest
- ResearchGenerationResponse
- MochyFamiResearchProfile
- FakeResearchGenerationProvider
- ResearchGenerationProviderRegistry
- ResearchGenerationService
- POST /api/v1/projects/{project}/research/generate
- AI research audit through AiGeneration
- generated claims remain unverified
- generated sources remain candidates
- no automatic evidence attachment
- no automatic research status transition
- ResearchQualityService remains authoritative

Earlier parts already provide:

Research:

- ResearchReport
- ResearchClaim
- Source
- claim/source evidence pivot
- ResearchQualityService
- ResearchIntelligenceService
- ResearchPipelineService

Script:

- Script
- ScriptVersion
- ScriptService
- Script quality
- research/script alignment checks
- ScriptGenerationProvider
- ScriptGenerationRequest
- ScriptGenerationResponse
- MochyFamiScriptProfile
- FakeScriptGenerationProvider
- ScriptGenerationProviderRegistry
- ScriptGenerationService
- POST /api/v1/projects/{project}/script/generate

==================================================
PRIMARY GOAL
==================================================

Build a deterministic, read-only:

RESEARCH → SCRIPT CONTEXT BRIDGE

The purpose is to create a structured context object that allows the script generation system to consume the project's existing research safely.

The bridge must:

1. Read existing ResearchReport data.
2. Read claims.
3. Read claim evidence/source relationships.
4. Read ResearchQualityService result.
5. Read ResearchPipelineService result where appropriate.
6. Produce a normalized script-generation context.
7. Clearly distinguish:
    - supported claims
    - unverified claims
    - uncertain claims
    - contradicted claims
    - claims without evidence
8. Never mutate research.
9. Never mutate script.
10. Never mark research ready.
11. Never mark script ready.
12. Never create claims.
13. Never create sources.
14. Never attach evidence.
15. Never automatically approve a script.
16. Never call the network.
17. Never call an AI provider.
18. Be deterministic.
19. Be reusable by future AI script-generation providers.
20. Preserve traceability from script context back to research claims.

This is a CONTEXT/ORCHESTRATION layer, not another AI provider.

==================================================
IMPORTANT ARCHITECTURE RULE
==================================================

Do NOT create a second ResearchQualityService.

Do NOT duplicate:

- claim status logic
- evidence logic
- quality blocker logic
- pipeline stage logic
- script alignment logic

Reuse the existing services.

The bridge should compose existing domain services.

Before coding:

1. Inspect the current repository.
2. Inspect ResearchQualityService.
3. Inspect ResearchPipelineService.
4. Inspect ResearchService.
5. Inspect ResearchClaimResource.
6. Inspect SourceResource.
7. Inspect ScriptGenerationService.
8. Inspect ScriptGenerationRequest.
9. Inspect ScriptGenerationProvider.
10. Inspect Part 10 ResearchGenerationService.
11. Inspect all related tests.

Use the actual implementation rather than assumptions from this prompt.

==================================================
PART A — CONTEXT DTO
==================================================

Create a structured immutable DTO.

Suggested:

ResearchToScriptContext

Possible structure:

{
"report": {
"summary": "...",
"status": "...",
"quality_ready": false
},

    "claims": [
        {
            "id": 123,
            "claim": "...",
            "importance": "high",
            "status": "supported",
            "has_evidence": true,
            "sources": [
                {
                    "id": 10,
                    "title": "...",
                    "domain": "...",
                    "url": "...",
                    "source_type": "official"
                }
            ]
        }
    ],

    "usable_claims": [],
    "claims_requiring_verification": [],
    "contradicted_claims": [],

    "quality": {
        "ready": false,
        "score": 75,
        "blockers": [],
        "warnings": []
    },

    "pipeline": {
        "stage": "...",
        "progress": 75,
        "next_actions": []
    }

}

Do NOT blindly copy this exact structure.

Inspect the existing Resources and DTO conventions first.

Use appropriate naming based on the actual project.

The DTO must be immutable according to the project's current DTO convention.

==================================================
PART B — CLAIM CLASSIFICATION
==================================================

Create deterministic classification.

A claim may be categorized as:

1. usable
2. requires_verification
3. contradicted
4. unsupported

Use the EXISTING claim status and evidence semantics.

Do not invent new database statuses.

Example conceptual rules:

SUPPORTED + evidence
→ usable

UNVERIFIED
→ requires_verification

UNCERTAIN
→ requires_verification

CONTRADICTED
→ contradicted

No evidence
→ requires_verification / unsupported according to existing domain semantics

IMPORTANT:

Do not override ResearchQualityService.

The classification is for context consumption only.

==================================================
PART C — SOURCE TRACEABILITY
==================================================

Every claim included in the context should preserve its research claim ID.

Every attached source should preserve:

- source ID
- title
- domain
- URL
- source type

Do not duplicate full source records unnecessarily.

The purpose is traceability.

Future script generation should be able to determine:

"This sentence is based on ResearchClaim #X."

Do not generate fake citation URLs.

Do not infer sources for claims without evidence.

==================================================
PART D — RESEARCH QUALITY INTEGRATION
==================================================

ResearchToScriptContext must call:

ResearchQualityService

and reuse its result.

Do not recreate the quality formula.

The context should expose:

- ready
- score if available
- blockers
- warnings

If research is not ready:

DO NOT throw an exception merely because quality is false.

The context should still be usable for:

- review
- drafting
- AI assistance
- UI inspection

However, it must clearly expose that the research is not ready.

==================================================
PART E — PIPELINE INTEGRATION
==================================================

Reuse:

ResearchPipelineService

where appropriate.

Expose:

- stage
- progress
- next actions

Do not mutate pipeline state.

Do not transition anything.

The pipeline remains derived/read-only.

==================================================
PART F — USABLE CLAIM RULE
==================================================

Define a deterministic rule for claims that are safe to pass as primary factual material to script generation.

Recommended conceptual rule:

A claim is "usable" only when:

- status is supported
- evidence exists
- the claim is not contradicted
- the research quality logic does not identify a blocker specifically invalidating it

Do NOT use "AI generated" as evidence of support.

A Part 10 generated claim should therefore remain non-usable until independently supported.

This is critical.

==================================================
PART G — SCRIPT GENERATION INTEGRATION
==================================================

Modify the existing ScriptGenerationService carefully.

The current Part 9 service accepts:

ScriptGenerationRequest

Do NOT break existing API contracts unnecessarily.

Introduce research context in a backward-compatible and explicit way.

Possible approach:

ScriptGenerationRequest receives an optional structured research context.

OR:

ScriptGenerationService internally builds ResearchToScriptContext when generating for a project.

Choose the approach that best matches the existing implementation after inspection.

IMPORTANT:

Do not duplicate research context assembly inside multiple classes.

There must be ONE authoritative context builder.

==================================================
PART H — AI SCRIPT GENERATION SAFETY
==================================================

When AI script generation uses research context:

The provider must be instructed conceptually:

- use supported claims as factual basis
- do not present unverified claims as facts
- do not use contradicted claims
- do not invent facts not present in research
- do not invent sources
- do not invent statistics
- do not invent dates
- do not invent URLs
- do not imply that AI generated claims are verified
- preserve uncertainty where appropriate

The exact provider prompt/profile mechanism should follow the existing Part 9 architecture.

Do NOT hardcode provider-specific behavior into the controller.

==================================================
PART I — FAKE SCRIPT PROVIDER
==================================================

Update FakeScriptGenerationProvider only if necessary.

It must remain deterministic.

Do not make the fake provider pretend to perform real research.

If it receives research context, its deterministic output should demonstrate that context can flow through the system.

For example:

- topic remains deterministic
- supported claim can be referenced
- unverified claims should not automatically become factual statements

Do not overcomplicate the fake provider.

==================================================
PART J — SCRIPT GENERATION RESULT
==================================================

After generation, the system should still run:

ScriptQualityService

The quality result remains authoritative.

Research quality and script quality are separate:

ResearchQualityService
→ Is the research sufficiently supported?

ScriptQualityService
→ Is the generated script acceptable and aligned?

Do not merge the two services.

Do not make research readiness automatically equal script readiness.

==================================================
PART K — CONTEXT API
==================================================

Expose the research-to-script context for inspection.

Suggested endpoint:

GET

/api/v1/projects/{project}/research/script-context

Use the project's existing API conventions.

Response should expose:

- report summary/context
- claim classifications
- source traceability
- quality
- pipeline
- readiness information

This endpoint is READ ONLY.

It must not:

- create
- update
- delete
- transition
- attach
- detach

anything.

Use existing authorization conventions.

==================================================
PART L — FRONTEND
==================================================

Update ResearchPanel or ProjectDetailPage with a small section:

"Script Context"

Show:

- research readiness
- quality score if available
- usable claims count
- claims requiring verification count
- contradicted claims count
- evidence-backed claims
- next research actions

Allow the user to inspect which claims are considered usable.

For each claim show:

- claim text
- status
- evidence count
- source domains
- classification

Do NOT add unnecessary editing controls here.

This is a read-only inspection feature.

==================================================
PART M — SCRIPT PANEL
==================================================

Update ScriptPanel so that when AI generation is available, the UI can show:

Research context summary:

- X usable claims
- X claims requiring verification
- X contradicted claims
- Research quality: ready/not ready

Before AI generation:

If research is not ready:

show a warning.

Do NOT necessarily block generation unless the existing business rules explicitly require it.

The purpose is to make the user aware of research quality.

Human review remains mandatory.

==================================================
PART N — TESTING
==================================================

Add focused tests.

ResearchToScriptContext tests:

- empty research
- claims with no evidence
- supported claim with evidence
- unverified claim
- uncertain claim
- contradicted claim
- mixed claims
- multiple sources
- source traceability
- quality integration
- pipeline integration
- deterministic output
- no database mutation

API tests:

- authenticated request
- research missing
- successful context
- ownership/authorization according to current project convention
- correct response shape
- no mutation

Script generation integration tests:

- context is consumed
- supported claim available
- unverified claim not treated as supported evidence
- contradicted claim not treated as supported evidence
- existing ScriptGenerationService behavior preserved
- quality evaluation still runs
- old script versions remain intact

Provider tests:

- fake provider deterministic
- research context accepted
- no network

IMPORTANT:

Do not weaken existing tests.

==================================================
PART O — QUERY PERFORMANCE
==================================================

Avoid N+1 queries.

The context builder should efficiently load:

- research report
- claims
- claim sources
- required source fields

Use eager loading / loadMissing according to existing conventions.

Add query-count tests where appropriate.

Keep the context generation lightweight.

==================================================
PART P — SECURITY
==================================================

Research content may contain external URLs and user-generated text.

Therefore:

- never execute URLs
- never render arbitrary HTML
- sanitize frontend display appropriately
- do not expose internal provider credentials
- do not log sensitive AI payloads unnecessarily
- do not call arbitrary external services
- do not trust AI-generated URLs

==================================================
PART Q — NO DATABASE CHANGES UNLESS NECESSARY
==================================================

Prefer NO migration.

This feature should be a read-only orchestration layer.

Before creating a migration:

inspect the existing schema.

If no migration is required:

document:

"No migration required."

If a migration is genuinely necessary:

keep it minimal and verify:

- fresh migration
- rollback
- migrate again

==================================================
PART R — DOCUMENTATION
==================================================

Create:

docs/PHASE_3_PART_11.md

Document:

1. Goal
2. Architecture
3. ResearchToScriptContext
4. Claim classification
5. Source traceability
6. Quality integration
7. Pipeline integration
8. Script generation integration
9. API
10. Frontend
11. Safety rules
12. Testing
13. Performance
14. Known limitations
15. Future provider integration

Explicitly document:

"AI-generated claims are not automatically considered factual evidence."

==================================================
PART S — CODE QUALITY
==================================================

Follow current repository conventions.

Do NOT:

- redesign the research architecture
- redesign AI provider architecture
- create duplicate quality services
- create duplicate pipeline logic
- create duplicate claim statuses
- refactor unrelated modules
- add speculative database fields
- add arbitrary dependencies
- add network access
- add shell execution
- add TODO/FIXME placeholders
- add console.log/debug statements
- fake verification results

Keep Part 11 focused.

==================================================
PART T — VERIFICATION
==================================================

Run:

php artisan test --compact

npx tsc --noEmit

npm run build

vendor/bin/pint --test

If Pint changes files:

run Pint and then re-run the required verification.

Also inspect:

git diff

git status

If migration exists:

verify fresh migration + rollback + re-migrate.

Do not claim success unless the commands actually pass.

==================================================
PART U — FINAL REPORT
==================================================

Report:

PHASE 3 PART 11 STATUS

1. Implementation summary
2. Files created
3. Files modified
4. Research context structure
5. Claim classification rules
6. Source traceability
7. Quality integration
8. Pipeline integration
9. Script generation integration
10. API endpoint
11. Frontend changes
12. Tests added
13. Full test count/assertion count
14. TypeScript result
15. Build result
16. Pint result
17. Migration result
18. Git status
19. Commit hash
20. Known limitations

==================================================
GIT CHECKPOINT
==================================================

If all verification passes:

Create exactly one focused commit.

Suggested commit:

feat: add research to script context bridge

Do NOT push automatically.

After commit:

- verify git status
- report commit hash
- STOP

==================================================
STOP CONDITION
==================================================

Part 11 is complete only when:

- ResearchToScriptContext exists
- context is deterministic
- research quality is reused
- research pipeline is reused
- claim classification is deterministic
- source traceability exists
- AI-generated claims remain unverified
- contradicted claims are not treated as usable factual evidence
- no research mutation occurs
- ScriptGenerationService can consume research context
- API exists
- frontend inspection exists
- existing behavior remains intact
- tests pass
- TypeScript passes
- build passes
- Pint passes
- migration verified if applicable
- focused git commit created
- working tree is clean

Then STOP.

DO NOT IMPLEMENT PART 12.
