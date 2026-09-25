PHASE 3 — PART 3
RESEARCH INTELLIGENCE FOUNDATION
CLAIM ↔ SOURCE EVIDENCE

Context:
MochyFami Content Studio has completed:

- Phase 1 Foundation
- Phase 2 Content Manager
- Phase 3 Part 1 Research Foundation
- Phase 3 Part 2 Research API + Manual Research Workflow

Current research domain:

ContentProject
↓
ResearchReport
├── ResearchClaims
└── Sources

Current enums and definitions from Part 1 are authoritative.

Important existing values:

ResearchClaimStatus:

- unverified
- supported
- contradicted
- uncertain

SourceType:

- article
- academic
- official
- news
- documentation
- other

Part 2 already provides:

- ResearchReport CRUD
- Source CRUD
- Claim CRUD
- Research status workflow
- Authentication
- Authorization
- Research frontend panel
- Automated tests

DO NOT redesign these systems.

==================================================
GOAL
==================================================

Introduce an evidence relationship between ResearchClaim and Source.

The goal is to represent:

Claim A
├── supported by Source 1
├── supported by Source 2
└── supported by Source 3

And:

Source 1
├── supports Claim A
├── supports Claim B
└── supports Claim C

This is a many-to-many relationship.

==================================================

1. # INSPECT FIRST

Before coding:

Inspect:

- ResearchClaim model
- Source model
- ResearchReport model
- ResearchService
- ResearchClaimResource
- ResearchSourceResource
- ResearchPanel
- existing migrations
- existing API conventions
- existing pivot-table conventions if any
- existing tests

Do not invent a separate architecture if the repository already has a convention.

# ================================================== 2. DATABASE DESIGN

Create a pivot table for:

research_claim_sources

Minimum fields:

- research_claim_id
- source_id
- timestamps

Add:

- foreign keys
- indexes
- unique constraint preventing duplicate claim/source pairs

Use the project's existing migration conventions.

Deletion behavior must be intentional.

If a claim is deleted, its evidence relationships should not become orphaned.

If a source is deleted, its evidence relationships should not become orphaned.

Do NOT blindly cascade unrelated research data.

# ================================================== 3. ELOQUENT RELATIONSHIPS

ResearchClaim:

belongsToMany(Source::class)

Source:

belongsToMany(ResearchClaim::class)

Use the existing Laravel relationship conventions.

Relationship name should be clear and consistent.

For example:

ResearchClaim:
sources()

Source:
claims()

Use a pivot table:

research_claim_sources

# ================================================== 4. EVIDENCE SEMANTICS

For MVP, the relationship itself means:

"This source is evidence associated with this claim."

Do NOT yet introduce complicated evidence classifications.

Do NOT add:

- evidence_strength
- sentiment
- confidence_score
- relevance_score
- quote extraction
- semantic similarity
- AI confidence

Those belong to later stages if actually needed.

Keep the MVP simple.

# ================================================== 5. API

Add API operations for attaching and detaching sources from claims.

Conceptual endpoints:

POST /api/v1/claims/{claim}/sources/{source}

DELETE /api/v1/claims/{claim}/sources/{source}

And optionally:

GET /api/v1/claims/{claim}/sources

Only implement endpoints that fit the existing API conventions.

The backend must verify:

- claim exists
- source exists
- claim and source belong to the same research report

A claim from Research A must NOT be allowed to attach a source from Research B.

If they belong to different research reports:

return the project's standard business/validation error.

Do NOT silently allow cross-research evidence.

# ================================================== 6. IDEMPOTENCY

Attaching the same source to the same claim twice must not create duplicate pivot records.

Return the project's standard conflict or validation response.

Do not rely only on frontend prevention.

The database unique constraint must also protect the relationship.

# ================================================== 7. DETACH

Detaching an evidence relationship should:

- remove only the pivot record
- never delete the claim
- never delete the source

Verify this with tests.

# ================================================== 8. API RESOURCE

Update claim responses so that associated sources can be returned when explicitly loaded.

Avoid forcing sources into every claim response if that would create unnecessary queries.

Follow the existing whenLoaded() convention.

For example:

Claim:

{
id,
claim,
status,
importance,
sources: [...]
}

only when sources are loaded.

Do not introduce N+1 queries.

# ================================================== 9. SOURCE RESOURCE

Similarly, allow associated claims to be returned when explicitly loaded.

Do not automatically load claims everywhere.

# ================================================== 10. RESEARCH SERVICE

Extend ResearchService or the appropriate domain service with methods for:

- attach source to claim
- detach source from claim
- retrieve claim evidence

Keep business logic centralized.

Controllers should remain thin.

Do not put relationship business rules directly into controllers.

# ================================================== 11. AUTHORIZATION / OWNERSHIP

Reuse existing authorization conventions.

Important:

A user must not be able to create:

Claim A from Research A +
Source B from Research B

even if both records individually exist.

Verify that claim and source belong to the same ResearchReport.

Also ensure the user has access according to the existing authorization model.

# ================================================== 12. FRONTEND

Extend ResearchPanel.

For each claim, provide an evidence section.

Example:

Claim:
"Gagak dapat mengenali wajah manusia."

Status:
Supported

Evidence:

- University of Washington
- Royal Society

Actions:

[+ Add Source]

For Add Source:

Show sources belonging to the current research report.

Allow:

- attach source
- detach source

Do NOT allow selecting sources belonging to another research report.

# ================================================== 13. FRONTEND UX

Handle:

- no evidence
- attach success
- attach error
- duplicate attach
- detach confirmation if consistent with existing UI patterns
- loading state
- empty source list

Keep the UI compact.

Do not redesign ResearchPanel.

# ================================================== 14. VALIDATION

Backend must validate:

- claim exists
- source exists
- same research report
- authenticated access
- duplicate relationship

Frontend validation is only UX assistance.

Backend remains authoritative.

# ================================================== 15. TESTS

Add comprehensive tests.

At minimum:

DATABASE:

1. claim can belong to many sources
2. source can belong to many claims
3. duplicate pivot is prevented
4. deleting claim cleans evidence relationship appropriately
5. deleting source cleans evidence relationship appropriately

API:

6. attach source to claim
7. detach source from claim
8. list claim sources
9. attach duplicate source rejected
10. cross-research claim/source rejected
11. unauthenticated attach rejected
12. unauthorized access rejected according to existing conventions
13. missing claim returns 404
14. missing source returns 404

RESOURCE:

15. claim returns sources when eager loaded
16. claim does not force-load sources unnecessarily
17. source returns claims when eager loaded

FRONTEND:

If existing frontend testing infrastructure exists, add focused coverage.

Do not introduce a new testing framework solely for this task.

# ================================================== 16. QUERY / PERFORMANCE CHECK

Avoid N+1 queries.

When displaying research with claims and sources:

Use appropriate eager loading.

Do not automatically eager-load both directions globally.

Do not fetch unrelated claims/sources.

If practical, add a focused query-count regression test for the main claim/evidence response.

Do not prematurely optimize beyond this.

# ================================================== 17. DATABASE TESTING

Use the existing testing database setup.

Laravel's RefreshDatabase / existing test infrastructure should be used according to repository conventions.

Verify the migration on a clean testing database.

Do not touch production data.

# ================================================== 18. SECURITY

Verify:

- no cross-research evidence
- no authorization bypass
- no mass-assignment vulnerability
- no public evidence endpoints
- no secrets
- no arbitrary URL fetching

Remember:

Source URLs remain DATA.

Do not fetch them in Part 3.

# ================================================== 19. REGRESSION

Run:

php artisan test

All previous Phase 1, Phase 2, Part 1, and Part 2 tests must remain green.

Then run:

npx tsc --noEmit

npm run build

vendor/bin/pint --dirty --format=agent

Fix only genuine issues.

# ================================================== 20. GIT REVIEW

Run:

git status
git diff --stat
git diff

Review all changes.

Do not include:

- .env
- API keys
- secrets
- node_modules
- vendor
- generated media
- temporary files

# ================================================== 21. GIT CHECKPOINT

If everything passes:

Create checkpoint commit:

feat: add research claim source evidence

Do NOT push automatically.

# ================================================== 22. FINAL REPORT

Return:

PART 3 RESULT

1. Database / Pivot
2. Eloquent Relationships
3. Evidence API
4. Research Scope Validation
5. Idempotency
6. Detach Behavior
7. Research Service
8. API Resources
9. Frontend Evidence UI
10. Authorization
11. Tests
12. Regression
13. TypeScript
14. Build
15. Pint
16. Security
17. Git

For each:

PASS / FAIL

Then provide:

FILES CREATED
FILES MODIFIED

TEST RESULTS

- exact number of tests
- exact assertions
- build result
- TypeScript result
- Pint result

GIT CHECKPOINT

- branch
- commit hash
- git status

PHASE 3 PART 3 STATUS

Choose exactly one:

READY FOR PART 4

or

NOT READY — ISSUES REMAIN

IMPORTANT:
STOP HERE.

Do NOT implement AI.
Do NOT implement web search.
Do NOT scrape URLs.
Do NOT generate scripts.
Do NOT start Part 4.
