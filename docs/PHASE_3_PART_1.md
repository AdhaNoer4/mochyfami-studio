PHASE 3 — PART 1
RESEARCH FOUNDATION

Context:
MochyFami Content Studio has completed Phase 1 and Phase 2.

Phase 2 is already completed and pushed to GitHub.

Current architecture includes:

- Laravel 13
- PHP 8.3+
- MySQL
- Sanctum
- React
- Vite
- TypeScript
- Tailwind
- Laravel API architecture
- Content Ideas
- Content Projects
- Project Status Workflow
- Real Dashboard
- Automated tests

Phase 2 checkpoint is already committed and pushed.

DO NOT modify or redesign completed Phase 2 functionality unless a genuine compatibility issue requires it.

==================================================
GOAL
==================================================

Start Phase 3: Research & Script AI.

This PART 1 is ONLY the Research Foundation.

We are NOT integrating a real AI provider yet.

We are NOT implementing web search yet.

We are NOT generating scripts yet.

We are NOT implementing TTS.

We are NOT implementing asset collection.

We are only building the database/domain foundation required for research.

==================================================

1. # INSPECT THE EXISTING REPOSITORY FIRST

Before coding:

- inspect existing migrations
- inspect ContentProject model
- inspect ContentIdea model
- inspect project relationships
- inspect existing enums
- inspect existing services
- inspect existing API controller patterns
- inspect API Resource patterns
- inspect validation/request patterns
- inspect frontend API service patterns
- inspect test patterns
- inspect naming conventions

Do not invent a parallel architecture.

Reuse the existing project conventions.

================================================== 2. DESIGN RESEARCH DOMAIN
==================================================

Introduce these core entities:

1. ResearchReport
2. ResearchClaim
3. Source

Conceptual relationship:

ContentProject
1 : 1
ResearchReport
1 : many
ResearchClaim

ResearchReport
1 : many
Source

The exact implementation should follow the repository's existing conventions.

================================================== 3. RESEARCH REPORT
==================================================

Create the database/model foundation for a research report.

Minimum conceptual fields:

- content_project_id
- status
- summary
- researched_at
- timestamps

Research status:

- pending
- researching
- completed
- needs_review
- failed

Rules:

- one project should have at most one active/current research report for the MVP
- project relationship must be explicit
- foreign key behavior must be intentional
- do not silently delete important research data

Do not add unnecessary fields just because they might be useful someday.

================================================== 4. RESEARCH CLAIM
==================================================

Create ResearchClaim.

Minimum conceptual fields:

- research_report_id
- claim
- status
- importance
- timestamps

Claim status:

- unverified
- supported
- contradicted
- uncertain

Importance:

- low
- medium
- high

The design must allow future association between claims and sources.

Do NOT implement a complicated many-to-many claim/source system unless the existing architecture clearly requires it at this stage.

If a pivot is genuinely necessary for future fact-checking, document why before implementing it.

================================================== 5. SOURCE
==================================================

Create Source.

Minimum conceptual fields:

- research_report_id
- title
- url
- domain
- source_type
- published_at
- timestamps

Source types should be controlled rather than arbitrary strings.

Initial source types can include:

- article
- academic
- official
- news
- documentation
- other

Do not overengineer source metadata yet.

The URL must be validated.

================================================== 6. RELATIONSHIPS
==================================================

Implement clear Eloquent relationships.

Expected conceptual relationships:

ContentProject
hasOne ResearchReport

ResearchReport
belongsTo ContentProject
hasMany ResearchClaims
hasMany Sources

ResearchClaim
belongsTo ResearchReport

Source
belongsTo ResearchReport

Use the existing model relationship conventions.

================================================== 7. ENUMS / CONSTANTS
==================================================

If Phase 2 uses PHP enums, follow the same pattern.

Create enums for:

- ResearchStatus
- ResearchClaimStatus
- ResearchClaimImportance
- SourceType

Do not duplicate string definitions throughout the codebase.

If the existing project uses another established convention instead of enums, follow that convention.

================================================== 8. DATABASE MIGRATIONS
==================================================

Create clean migrations.

Verify:

- foreign keys
- indexes
- unique constraints
- nullable fields
- timestamps
- appropriate deletion behavior

Research data is valuable.

Do not use cascading deletes blindly.

The relationship between project and research report should prevent accidental orphaned research data.

Run migration tests on a clean testing database.

================================================== 9. MODEL CASTS
==================================================

Use enum casts where appropriate.

Example conceptual behavior:

ResearchReport.status
→ ResearchStatus

ResearchClaim.status
→ ResearchClaimStatus

ResearchClaim.importance
→ ResearchClaimImportance

Source.source_type
→ SourceType

Follow existing project conventions.

================================================== 10. NO AI PROVIDER YET
==================================================

IMPORTANT:

Do NOT implement:

- OpenAI API
- Gemini API
- Claude API
- web search API
- scraping
- browser automation
- AI-generated research
- AI-generated claims
- AI-generated scripts

Those belong to later parts.

Part 1 is domain/database foundation only.

================================================== 11. NO UI YET
==================================================

Do not build a full Research UI in this part.

A frontend page is NOT required unless the existing architecture makes a minimal placeholder necessary.

Do not create a fake research dashboard.

================================================== 12. TESTS
==================================================

Create focused tests for the new domain.

At minimum test:

A. ResearchReport can belong to a project

B. Project can access its research report

C. ResearchReport can have claims

D. ResearchReport can have sources

E. Enum casts work correctly

F. Duplicate research report for the same project is prevented according to the chosen design

G. Foreign key constraints work

H. Invalid source URL cannot pass validation if a request layer is implemented

Do not create API endpoints merely to test the models if the project architecture does not require them yet.

Prefer model/database tests for this part.

================================================== 13. TEST DATABASE
==================================================

Run:

php artisan migrate:fresh --env=testing

Then:

php artisan test

Do not touch development/production data.

================================================== 14. CODE QUALITY
==================================================

Follow existing code style.

Run:

vendor/bin/pint --dirty --format=agent

Fix formatting issues if any.

Do not perform unrelated refactoring.

================================================== 15. VERIFY PHASE 2 REGRESSION
==================================================

After implementing Part 1, verify that existing Phase 2 tests still pass.

Run:

php artisan test

The existing Phase 2 functionality must remain intact.

================================================== 16. GIT REVIEW
==================================================

Run:

git status
git diff --stat
git diff

Review every changed file.

Do not commit:

- .env
- secrets
- API keys
- node_modules
- vendor
- generated media
- temporary files

================================================== 17. GIT CHECKPOINT
==================================================

If all tests pass and the implementation is clean:

Create a checkpoint commit.

Suggested commit message:

feat: add research domain foundation

Do NOT push automatically.

Stop after the commit.

================================================== 18. FINAL REPORT
==================================================

Return:

PART 1 RESULT

1. Repository inspection
2. ResearchReport
3. ResearchClaim
4. Source
5. Relationships
6. Enums
7. Migrations
8. Tests
9. Phase 2 regression
10. Code quality
11. Git status
12. Commit hash

For each item:

PASS / FAIL

Then:

FILES CREATED
FILES MODIFIED

TEST RESULTS

Include exact numbers.

GIT CHECKPOINT

Include:

- branch
- commit hash
- git status

PHASE 3 PART 1 STATUS

Choose exactly one:

READY FOR PART 2

or

NOT READY — ISSUES REMAIN

IMPORTANT:
STOP HERE.

Do NOT start Part 2.
Do NOT integrate an AI provider.
Do NOT implement web research.
Do NOT generate scripts.
