PHASE 3 — PART 2
RESEARCH API + MANUAL RESEARCH WORKFLOW

Context:
MochyFami Content Studio has completed:

- Phase 1 Foundation
- Phase 2 Content Manager
- Phase 3 Part 1 Research Foundation

Phase 3 Part 1 introduced the Research domain:

- ResearchReport
- ResearchClaim
- Source
- ResearchStatus
- ResearchClaimStatus
- ResearchClaimImportance
- SourceType

The Phase 2 checkpoint has already been pushed to GitHub.

Part 2 must build the API and manual research workflow on top of the existing Research domain.

IMPORTANT:
Do NOT integrate an AI provider yet.
Do NOT implement web search.
Do NOT implement scraping.
Do NOT generate scripts.
Do NOT implement TTS.
Do NOT redesign Phase 2.

==================================================

1. # INSPECT FIRST

Before making changes:

Inspect:

- ResearchReport model
- ResearchClaim model
- Source model
- Research enums
- migrations
- ContentProject model
- existing API controllers
- existing API Resources
- existing Form Requests
- existing authorization/policies
- existing route conventions
- existing frontend API service conventions
- existing test conventions

Reuse existing architecture.

Do not create parallel patterns.

================================================== 2. RESEARCH REPORT API
==================================================

Implement the minimum API required to manage a project's research report.

Conceptual operations:

Create research report for project
Get project research
Update research report
Delete research report if permitted by current domain rules

Use the repository's existing route/controller/resource/request conventions.

Expected behavior:

A project may have at most one current research report.

Creating a second report must be rejected cleanly.

A missing project must return the existing API's standard 404 response.

Unauthorized access must return 401.

Invalid authenticated access must follow the existing authorization convention.

================================================== 3. RESEARCH STATUS WORKFLOW
==================================================

Research statuses:

pending
researching
completed
needs_review
failed

Do NOT allow arbitrary status mutation.

Create a small centralized transition mechanism if the project already uses centralized workflow logic for ProjectStatus.

Expected conceptual transitions:

pending
→ researching
→ completed

researching
→ needs_review
→ failed

completed
→ needs_review

needs_review
→ completed
→ researching
→ failed

failed
→ pending
→ researching

IMPORTANT:

Before implementing, inspect Part 1 and the existing ProjectStatusWorkflow implementation.

Follow the same architectural style.

The backend must be authoritative.

Invalid transitions must return a validation/business-rule error rather than silently changing status.

Same-status transitions should be rejected if that matches the existing project workflow behavior.

================================================== 4. RESEARCH REPORT UPDATE
==================================================

Allow controlled updates to:

- summary
- researched_at
- status through the workflow mechanism

Do not allow clients to arbitrarily modify:

- id
- content_project_id
- timestamps

Validate summary length and researched_at format according to existing project conventions.

================================================== 5. SOURCE API
==================================================

Implement CRUD operations for sources belonging to a research report.

Conceptual operations:

Create source
List sources
Update source
Delete source

Fields:

- title
- url
- domain
- source_type
- published_at

Source types are controlled by SourceType.

Validate URL.

If domain is supplied by the client, validate it.

Do NOT yet implement automatic domain extraction unless it is trivial and consistent with the current architecture.

Do NOT fetch the URL.

Do NOT scrape the URL.

Do NOT perform web search.

This is a manual source management API.

================================================== 6. CLAIM API
==================================================

Implement CRUD operations for claims belonging to a research report.

Fields:

- claim
- status
- importance

Create claim:

default status:
unverified

Update claim:

allow controlled changes to:

- claim
- status
- importance

Do not allow changing:

- id
- research_report_id
- timestamps

Status must use ResearchClaimStatus.

Importance must use ResearchClaimImportance.

================================================== 7. RESOURCE / RESPONSE FORMAT
==================================================

Use API Resources if that is already the project's convention.

Keep response structure consistent.

A research report response should make it easy for the frontend to consume:

- research report
- project reference
- status
- summary
- researched_at
- claims
- sources

Avoid returning unnecessary database fields.

Do not expose sensitive model internals.

================================================== 8. N+1 / QUERY BEHAVIOR
==================================================

When returning a research report with claims and sources:

Use appropriate eager loading.

Do not create an obvious N+1 query problem.

Do not fetch unrelated projects or all research reports.

Keep queries scoped to the requested project/research report.

================================================== 9. AUTHORIZATION
==================================================

All research endpoints must use the existing authentication mechanism.

Follow the current:

auth:sanctum

pattern where applicable.

Do not introduce a second authentication mechanism.

If policies exist for ContentProject, ResearchReport, Source, or Claim, use them.

If policies are not yet needed because the application currently has a single authenticated-user ownership model, follow the existing Phase 2 authorization convention instead of inventing a complex RBAC system.

================================================== 10. FRONTEND
==================================================

Create the minimum frontend support needed to manually inspect and manage research.

Preferred location:

Project Detail → Research tab

If the existing Project Detail page already has tabs, add:

Research

The Research tab should show:

- research status
- summary
- researched date
- claims
- sources

Allow:

- create research
- edit summary
- change valid research status
- add source
- edit source
- delete source
- add claim
- edit claim
- delete claim

Use existing UI patterns.

Do NOT create a large research dashboard.

Do NOT create AI controls yet.

================================================== 11. LOADING / EMPTY / ERROR STATES
==================================================

The Research tab must handle:

- loading
- no research yet
- API error
- empty claims
- empty sources
- mutation success
- mutation error

Do not leave blank screens.

================================================== 12. VALIDATION
==================================================

Backend validation is authoritative.

Frontend validation is for UX only.

Test invalid:

- missing title
- invalid URL
- invalid source type
- invalid claim status
- invalid importance
- oversized fields
- invalid dates
- invalid research status transition

Use the project's existing 422 response conventions.

================================================== 13. TESTS
==================================================

Create comprehensive API/feature tests.

At minimum:

ResearchReport:

1. authenticated user can create research for project
2. unauthenticated request returns 401
3. missing project returns 404
4. duplicate research report is rejected
5. research can be retrieved
6. research can be updated
7. research can be deleted when allowed

Research workflow:

8. valid status transition succeeds
9. invalid status transition is rejected
10. same-status transition is rejected if consistent with ProjectStatus workflow

Sources:

11. source can be created
12. source can be listed
13. source can be updated
14. source can be deleted
15. invalid URL is rejected
16. invalid source type is rejected

Claims:

17. claim can be created
18. default claim status is unverified
19. claim can be updated
20. claim can be deleted
21. invalid claim status is rejected
22. invalid importance is rejected

Relationships:

23. research returns claims
24. research returns sources
25. responses do not expose sensitive fields

Use RefreshDatabase / existing database test conventions.

================================================== 14. REGRESSION
==================================================

Run the full existing test suite:

php artisan test

Phase 2 must remain green.

Do not weaken or delete existing tests.

================================================== 15. FRONTEND VALIDATION
==================================================

Run:

npx tsc --noEmit

npm run build

Fix only genuine errors introduced by Part 2.

================================================== 16. CODE STYLE
==================================================

Run:

vendor/bin/pint --dirty --format=agent

Follow existing project conventions.

Do not perform unrelated refactoring.

================================================== 17. DATABASE VERIFICATION
==================================================

Verify the new API does not require destructive database changes.

Run the relevant testing migration workflow.

If migrations were not changed in Part 2, do not create unnecessary migrations.

================================================== 18. SECURITY
==================================================

Verify:

- no secrets
- no API keys
- no arbitrary URL fetching
- no shell execution
- no unrestricted model mass assignment
- no public research endpoints
- no authorization bypass

Important:

At this stage a Source URL is DATA ONLY.

The application must NOT fetch or execute it.

================================================== 19. GIT CHECKPOINT
==================================================

After all tests pass:

Run:

git status
git diff --stat
git diff

Review all changes.

Create a checkpoint commit:

feat: add research and source claim api

Do NOT push automatically.

================================================== 20. FINAL REPORT
==================================================

Return:

PART 2 RESULT

1. Research API
2. Research Status Workflow
3. Source API
4. Claim API
5. API Resources / Response Format
6. Authorization
7. Frontend Research Tab
8. Validation
9. Tests
10. Phase 2 Regression
11. TypeScript
12. Production Build
13. Pint
14. Security
15. Git

For each:

PASS / FAIL

Then provide:

FILES CREATED
FILES MODIFIED

TEST RESULTS

- exact test count
- exact assertion count
- exact build result

GIT CHECKPOINT

- branch
- commit hash
- git status

PHASE 3 PART 2 STATUS

Choose exactly one:

READY FOR PART 3

or

NOT READY — ISSUES REMAIN

IMPORTANT:
STOP HERE.

Do NOT integrate AI.
Do NOT implement web search.
Do NOT generate scripts.
Do NOT start Part 3.
