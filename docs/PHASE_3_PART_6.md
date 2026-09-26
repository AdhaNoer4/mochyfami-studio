You are continuing development of MochyFami Content Studio.

IMPORTANT:
This is PHASE 3 — PART 6.

Do NOT redesign existing architecture.

Do NOT implement:

- AI
- LLM
- prompt generation
- automatic claim extraction
- automatic source analysis
- automatic evidence creation
- automatic claim verification
- real search providers

This part builds a deterministic RESEARCH EXECUTION PIPELINE.

==================================================
PHASE 3 — PART 6
RESEARCH EXECUTION PIPELINE
==================================================

## OBJECTIVE

Build a deterministic workflow layer that describes:

- current research progress
- next recommended actions
- completion state

This layer sits above:

- ResearchReport
- Claims
- Sources
- Evidence
- Quality Gate

It must NOT replace existing ResearchStatus.

It is an orchestration layer.

==================================================

1. # INSPECT REPOSITORY FIRST

Inspect:

- ResearchReport
- ResearchClaim
- Source
- ResearchQualityService
- ResearchIntelligenceService
- ResearchStatus
- ResearchPanel
- existing APIs
- existing routes
- existing tests

Repository is source of truth.

================================================== 2. DO NOT ADD DATABASE TABLES
==================================================

Pipeline state should be computed dynamically.

Do NOT create:

- pipeline table
- workflow table
- execution table

Everything must derive from existing data.

================================================== 3. CREATE PIPELINE SERVICE
==================================================

Create:

ResearchPipelineService

Responsibilities:

- evaluate current stage
- calculate completion
- determine next actions

================================================== 4. PIPELINE STAGES
==================================================

Suggested stages:

1. empty
2. discovering
3. collecting_sources
4. drafting_claims
5. linking_evidence
6. reviewing
7. ready_for_script

Use actual data.

Example:

0 source
→ collecting_sources

Sources exist
No claims
→ drafting_claims

Claims exist
No evidence
→ linking_evidence

Quality.ready=true
→ ready_for_script

Do not overcomplicate.

================================================== 5. NEXT ACTIONS
==================================================

Return:

[
{
code: "ADD_SOURCE",
priority: "high",
message: "Add at least one source."
}
]

Possible actions:

ADD_SOURCE
ADD_CLAIM
LINK_EVIDENCE
VERIFY_CLAIMS
RESOLVE_CONTRADICTIONS
IMPROVE_QUALITY

Only return meaningful actions.

================================================== 6. COMPLETION
==================================================

Add simple progress:

0-100

Example:

sources: 25%
claims: 25%
evidence: 25%
quality: 25%

Keep deterministic.

Do not make hidden calculations.

================================================== 7. PIPELINE RESULT
==================================================

Return:

{
stage: "linking_evidence",
progress: 65,
ready_for_script: false,
next_actions: [],
summary: {}
}

================================================== 8. API
==================================================

GET:

/projects/{project}/research/pipeline

Read only.

No DB mutations.

================================================== 9. FRONTEND
==================================================

Add compact card:

Research Pipeline

Show:

- stage
- progress bar
- ready badge
- next actions

No redesign.

================================================== 10. TESTS
==================================================

Test:

- empty
- source only
- claims only
- evidence
- quality ready
- progress
- actions

================================================== 11. SECURITY
==================================================

Auth + ownership.

No network.

No AI.

================================================== 12. GIT
==================================================

Single commit:

feat: add research execution pipeline

Do not push.

================================================== 13. FINAL REPORT
==================================================

Report:

1. Pipeline Service
2. Stages
3. Progress
4. Actions
5. API
6. Frontend
7. Tests
8. Regression
9. TypeScript
10. Build
11. Security
12. Commit hash
13. Test count
14. PHASE 3 PART 6 STATUS

Use:

PHASE 3 PART 6 STATUS: READY FOR PART 7

only if everything passes.

STOP.
