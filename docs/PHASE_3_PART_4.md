You are continuing development of the existing MochyFami Content Studio project.

IMPORTANT:
Do NOT redesign the architecture.
Do NOT rewrite existing working systems.
Do NOT implement AI research, web search, scraping, URL fetching, crawling, or external AI APIs.

This is PHASE 3 — PART 4.

==================================================
PHASE 3 — PART 4
RESEARCH QUALITY & FACT-CHECK GATE
==================================================

OBJECTIVE
---------

Build a deterministic Research Quality / Fact-Check Gate
on top of the existing Research Foundation.

The system must be able to evaluate whether a ResearchReport
is sufficiently verified to proceed toward scripting.

The evaluation must use ONLY existing database data:

- ResearchReport
- ResearchClaim
- Source
- ResearchClaim ↔ Source evidence pivot

No external network access is allowed.

The gate must be deterministic and explainable.

==================================================
1. INSPECT THE EXISTING IMPLEMENTATION FIRST
==================================================

Before changing anything:

Inspect the repository and existing implementation for:

- ResearchReport
- ResearchClaim
- Source
- ResearchStatus
- ResearchClaimStatus
- ResearchClaimImportance
- SourceType
- ResearchService
- ResearchController
- ResearchClaimController
- ResearchSourceController
- ResearchClaimPolicy
- ResearchClaimResource
- ResearchSourceResource
- ResearchResource
- research_claim_sources migration
- ResearchPanel
- existing Research API tests
- existing Research Evidence tests
- route conventions
- ApiResponse conventions
- exception handling conventions
- authentication / ownership conventions

Read the actual implementation.

Do NOT rely on an older specification if the repository differs.

The repository is the source of truth.

==================================================
2. CURRENT DOMAIN MODEL
==================================================

Use the existing implementation.

Known existing claim statuses are:

- unverified
- supported
- contradicted
- uncertain

Known existing claim importance values are:

- inspect the repository and use the actual enum values

Known existing source types are:

- article
- academic
- official
- news
- documentation
- other

Do not rename existing enum values.

Do not introduce incompatible enum values.

==================================================
3. QUALITY GATE CONCEPT
==================================================

Introduce a deterministic quality evaluation for a ResearchReport.

The quality gate should evaluate:

A. Does the research report exist?
B. Does it contain claims?
C. Are important claims verified?
D. Do claims have evidence sources?
E. Are contradicted claims blocking progression?
F. Are there unresolved / uncertain claims?
G. Are there claims without evidence?

The evaluation must return structured information.

Example conceptual result:

{
    "ready": false,
    "score": 72,
    "summary": {
        "total_claims": 5,
        "supported_claims": 3,
        "unverified_claims": 1,
        "uncertain_claims": 1,
        "contradicted_claims": 0,
        "claims_without_evidence": 1
    },
    "issues": [
        {
            "code": "CLAIM_WITHOUT_EVIDENCE",
            "claim_id": 123,
            "message": "This claim has no evidence source."
        }
    ]
}

IMPORTANT:

The exact JSON structure may follow the existing project conventions,
but the result must be deterministic and machine-readable.

==================================================
4. DO NOT INVENT A COMPLEX SCORING SYSTEM
==================================================

Do NOT create an overly complicated weighted scoring algorithm.

The primary output must be:

ready: true/false

The score is optional.

If a score is implemented, keep it simple and explainable.

The readiness decision must NOT depend solely on an arbitrary score.

Prefer explicit blocking rules.

==================================================
5. PROPOSED BLOCKING RULES
==================================================

Use the following rules unless the existing domain model requires
a small adaptation.

A ResearchReport is NOT ready when:

1. It has zero claims.

2. Any IMPORTANT claim is:

   - unverified
   - contradicted
   - uncertain

3. Any IMPORTANT claim has zero evidence sources.

4. Any claim is contradicted.

5. Any claim has no evidence source.

However:

The implementation must distinguish between:

- blocking issues
- warnings

If the existing ResearchClaimImportance enum supports multiple
importance levels, inspect it and use its actual values.

IMPORTANT claims must receive stricter validation.

If an unimportant claim is unresolved, it may become a warning
rather than a blocker.

Do not invent importance semantics without inspecting the enum.

==================================================
6. CREATE A DEDICATED QUALITY SERVICE
==================================================

Introduce a dedicated service.

Suggested name:

ResearchQualityService

Responsibilities:

- evaluateReport(ResearchReport $report)
- getSummary(...)
- getIssues(...)
- determineReadiness(...)

Keep business rules centralized in the service.

Do NOT put the quality logic inside controllers.

Do NOT put the quality logic inside React.

The backend must remain authoritative.

==================================================
7. QUALITY RESULT OBJECT
==================================================

If the existing project architecture supports DTO/value objects,
consider creating a small dedicated result structure.

Otherwise return a structured associative array.

Do not introduce a large abstraction just for this feature.

The result should contain at minimum:

- ready
- summary
- issues

Recommended summary fields:

- total_claims
- supported_claims
- unverified_claims
- contradicted_claims
- uncertain_claims
- claims_with_evidence
- claims_without_evidence
- important_claims
- important_claims_ready

Each issue should contain:

- code
- severity
- claim_id when applicable
- message

Suggested severities:

- blocker
- warning

Suggested issue codes:

- NO_CLAIMS
- CLAIM_WITHOUT_EVIDENCE
- IMPORTANT_CLAIM_UNVERIFIED
- IMPORTANT_CLAIM_UNCERTAIN
- IMPORTANT_CLAIM_CONTRADICTED
- CLAIM_CONTRADICTED
- CLAIM_UNCERTAIN

Only use codes that are actually needed.

Do not create duplicate issue types.

==================================================
8. DATABASE
==================================================

Prefer NOT adding a database table in this part.

The quality result can be calculated dynamically from:

- research_claims
- sources
- research_claim_sources

Do NOT persist quality results unless the existing architecture
clearly requires persistence.

This keeps the quality gate deterministic and avoids stale data.

==================================================
9. QUERY / PERFORMANCE
==================================================

Avoid N+1 queries.

The quality evaluation should load the required relationships
efficiently.

Prefer:

with(['claims.sources'])

or an equivalent efficient query strategy.

Do not individually query sources for every claim.

Add a query-count test if practical.

The quality evaluation should work correctly with:

- zero claims
- one claim
- many claims
- claims with many evidence sources

==================================================
10. API ENDPOINT
==================================================

Add a read-only endpoint for quality evaluation.

Follow the existing route conventions.

Suggested endpoint:

GET

/api/v1/projects/{project}/research/quality

The endpoint must:

- require authentication
- enforce project ownership
- return 404 for unauthorized ownership according to existing conventions
- return the deterministic quality result
- not modify database state

Controller should remain thin:

1. authorize
2. resolve research
3. call ResearchQualityService
4. return ApiResponse

Do not put quality logic in controller.

==================================================
11. API RESPONSE
==================================================

Follow the existing ApiResponse format.

Do NOT introduce a completely different response wrapper.

Example conceptual payload:

{
    "data": {
        "ready": false,
        "summary": {
            "total_claims": 3,
            "supported_claims": 1,
            "unverified_claims": 1,
            "uncertain_claims": 1,
            "contradicted_claims": 0,
            "claims_with_evidence": 2,
            "claims_without_evidence": 1,
            "important_claims": 2,
            "important_claims_ready": 1
        },
        "issues": [
            {
                "code": "IMPORTANT_CLAIM_UNVERIFIED",
                "severity": "blocker",
                "claim_id": 10,
                "message": "Important claim is not verified."
            }
        ]
    }
}

The exact envelope must follow the project's existing conventions.

==================================================
12. API RESOURCE / TRANSFORMATION
==================================================

If the existing project uses API Resources for domain responses,
create a small resource for the quality result only if that is
consistent with the current architecture.

Do not force a Resource if it would be unnecessary.

The output must remain stable and explicit.

==================================================
13. RESEARCH STATUS INTEGRATION
==================================================

Do NOT redesign ResearchStatus.

Do NOT automatically change:

completed
needs_review
failed
researching

based on the quality gate.

The quality endpoint should report readiness only.

Example:

ResearchStatus = completed
Quality.ready = false

This is valid.

The quality gate is an evaluation layer, not a replacement
for the ResearchStatus workflow.

Do NOT automatically transition research status.

==================================================
14. FRONTEND
==================================================

Extend the existing ResearchPanel.

Do NOT redesign the entire panel.

Add a compact:

"Research Quality"

or

"Fact Check"

section.

Display:

- Ready / Not Ready
- total claims
- supported claims
- unresolved claims
- contradicted claims
- claims without evidence
- blockers
- warnings

Use existing project UI patterns.

Do NOT create a new design system.

==================================================
15. FRONTEND BEHAVIOR
==================================================

The frontend should:

- fetch quality data for the current research
- show loading state
- show error state
- show empty/no-research state
- refresh quality after claim changes
- refresh quality after evidence attach
- refresh quality after evidence detach

Important:

Do not trust the frontend as the authority.

The backend endpoint remains authoritative.

Do not add automatic "Approve" or "Continue to Script" actions
in this part.

==================================================
16. TYPESCRIPT
==================================================

Add explicit TypeScript types for:

- ResearchQuality
- ResearchQualitySummary
- ResearchQualityIssue

Avoid:

any

Do not weaken existing TypeScript types.

==================================================
17. TESTS — SERVICE
==================================================

Create focused tests for ResearchQualityService.

At minimum test:

1. zero claims → not ready

2. supported claim + evidence → ready

3. supported claim without evidence → not ready

4. unverified important claim → blocker

5. uncertain important claim → blocker

6. contradicted important claim → blocker

7. contradicted normal claim → blocker if using the rule above

8. uncertain normal claim → warning if the actual importance
   semantics support this

9. multiple claims → correct summary counts

10. multiple evidence sources → counted as evidence, not duplicate claims

11. no database mutation occurs during evaluation

12. no N+1 query behavior

Adapt the exact test matrix to the actual enum values in the repository.

==================================================
18. TESTS — API
==================================================

Add API tests for:

- authenticated owner → 200
- unauthenticated → 401
- another user's project → 404 according to project convention
- project without research → expected project/research behavior
- zero claims
- ready research
- research with blockers
- response structure
- issue structure

Use the existing test infrastructure.

Do not introduce another testing framework.

==================================================
19. REGRESSION TESTS
==================================================

Run:

php artisan test

Expected:

ALL existing tests remain green.

Also run:

npx tsc --noEmit

npm run build

vendor/bin/pint --dirty --format agent

Fix only issues introduced by this part.

Do not modify unrelated formatting.

==================================================
20. SECURITY
==================================================

Verify:

- endpoint is behind auth:sanctum
- project ownership is enforced
- no cross-user research access
- no URL fetching
- no network request
- no shell execution
- no secret/API key
- no mass assignment issue
- no database mutation during quality evaluation

==================================================
21. FILE SCOPE
==================================================

Expected files may include:

NEW:

- app/Services/ResearchQualityService.php
- tests/Feature/ResearchQualityTest.php
- tests/Feature/ResearchQualityApiTest.php

Possibly:

- app/Http/Controllers/Api/V1/ResearchQualityController.php
- app/Http/Resources/ResearchQualityResource.php

MODIFIED:

- routes/api.php
- resources/js/types/index.ts
- resources/js/services/researchService.ts
- resources/js/components/projects/ResearchPanel.tsx

Do not create files unnecessarily.

==================================================
22. NO AI / NO WEB SEARCH
==================================================

This is critical.

Do NOT implement:

- AI fact checker
- AI research agent
- web search
- scraping
- crawler
- URL downloader
- HTTP client calls
- search provider integration
- external source verification

The quality gate only evaluates the data already stored in
the database.

==================================================
23. VALIDATION
==================================================

Before declaring completion:

Run:

php artisan test

npx tsc --noEmit

npm run build

vendor/bin/pint --dirty --format agent

Also inspect:

git status --short

Check that:

- no .env files changed
- no secrets added
- no debug code
- no console.log
- no dump/dd/var_dump
- no TODO/FIXME introduced
- no unrelated files changed

==================================================
24. GIT CHECKPOINT
==================================================

If all checks pass:

Create exactly one commit:

feat: add research quality fact check gate

Do NOT push to GitHub automatically.

Verify:

git status

It must be clean after the commit.

==================================================
25. FINAL REPORT
==================================================

When finished, STOP.

Do not continue to Part 5.

Return a concise but complete report containing:

1. Database — PASS/FAIL
2. Quality Service — PASS/FAIL
3. Quality Rules — PASS/FAIL
4. API Endpoint — PASS/FAIL
5. Authorization — PASS/FAIL
6. Frontend Quality UI — PASS/FAIL
7. Tests — PASS/FAIL
8. Regression — PASS/FAIL
9. TypeScript — PASS/FAIL
10. Build — PASS/FAIL
11. Pint — PASS/FAIL
12. Security — PASS/FAIL
13. Files Created
14. Files Modified
15. Test count + assertions
16. Git commit hash
17. Git status
18. PHASE 3 PART 4 STATUS

Use:

PHASE 3 PART 4 STATUS: READY FOR PART 5

only if all checks pass.

If something fails, report the exact failure.

STOP.