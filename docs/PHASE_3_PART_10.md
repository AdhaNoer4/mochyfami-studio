PHASE 3 — PART 10
AI RESEARCH GENERATION FOUNDATION

Project: MochyFami Content Studio

## CONTEXT

Phase 3 Part 9 sudah selesai dan sudah diverifikasi.

Current status:

- Phase 3 Part 1–9 completed.
- Latest commit:
  f686991 feat: add ai script generation foundation
- Working tree harus tetap clean sebelum mulai.
- Existing full test baseline setelah Part 9:
  480 tests / 1677 assertions PASS
- TypeScript clean.
- Vite build succeeds.
- Pint passes.
- Migration round-trip has been verified.

Part 9 introduced provider-agnostic AI Script Generation:

- ScriptGenerationProvider contract
- ScriptGenerationRequest / Response DTO
- MochyFamiScriptProfile
- FakeScriptGenerationProvider
- ScriptGenerationProviderRegistry
- ScriptGenerationService
- AI generation audit through ai_generations
- POST /api/v1/projects/{project}/script/generate
- Frontend Generate with AI flow
- Human review remains mandatory.
- Fake provider is deterministic and does not use network.

IMPORTANT:
Do NOT assume the current implementation exactly matches the original specification.
Inspect the repository and existing implementation first.
Reuse existing conventions and architecture.

==================================================
PRIMARY GOAL
==================================================

Build the foundation for AI-assisted RESEARCH generation.

The purpose of this part is NOT to create an autonomous fact-finding system.

The AI research feature must:

1. Receive a research topic/question.
2. Use an existing provider abstraction.
3. Produce structured research candidates.
4. Convert provider output into normalized claims/sources only through explicit service logic.
5. Clearly distinguish AI-generated research candidates from verified evidence.
6. Never automatically mark claims as supported merely because AI generated them.
7. Never automatically mark a research report as completed.
8. Preserve existing research data.
9. Create an auditable AI generation record.
10. Remain provider-agnostic.
11. Use a deterministic fake provider for tests.
12. Make future real web/search/LLM providers possible without changing the domain layer.

The human remains responsible for fact-checking.

==================================================
ARCHITECTURE PRINCIPLES
==================================================

Follow the existing architecture from Part 5 and Part 9.

Do NOT create a completely separate AI architecture.

Reuse:

- existing AI provider registry conventions
- existing AiGeneration model
- existing ResearchReport
- existing ResearchClaim
- existing Source
- existing ResearchQualityService
- existing ResearchIntelligenceService where appropriate
- existing API response conventions
- existing policy conventions
- existing frontend service conventions
- existing testing conventions

Before coding:

1. Inspect the current repository.
2. Inspect the Part 5 research discovery implementation.
3. Inspect Part 9 AI script generation implementation.
4. Inspect AiGeneration model/migration/resource.
5. Inspect ResearchClaim and Source models/resources.
6. Inspect existing ResearchService.
7. Inspect existing research routes.
8. Inspect existing frontend ResearchPanel.
9. Inspect all related tests.
10. Identify reusable code before creating new abstractions.

Do not duplicate existing functionality.

==================================================
PART A — RESEARCH GENERATION CONTRACT
==================================================

Create a provider contract similar to ScriptGenerationProvider.

Suggested location:

app/Contracts/AI/ResearchGenerationProvider.php

The contract should expose:

- name()
- generate(ResearchGenerationRequest $request): ResearchGenerationResponse

Follow the naming/style of ScriptGenerationProvider.

Do not invent a radically different abstraction.

==================================================
PART B — REQUEST DTO
==================================================

Create:

ResearchGenerationRequest

It should contain enough information for a provider to generate structured research candidates.

Minimum fields:

- topic
- question
- context
- max_claims

Optional fields may be added only if they are actually useful and consistent with the existing architecture.

Validation/normalization:

- topic required
- question required
- trim strings
- reasonable maximum lengths
- max_claims bounded
- no unbounded input
- no arbitrary nested payload

Use the same DTO conventions as Part 9.

The DTO must be immutable if that is the existing convention.

==================================================
PART C — RESPONSE DTO
==================================================

Create:

ResearchGenerationResponse

The response must be structured.

Suggested conceptual structure:

{
"summary": "...",
"claims": [
{
"claim": "...",
"importance": "high|medium|low",
"status": "unverified"
}
],
"sources": [
{
"title": "...",
"url": "...",
"domain": "...",
"source_type": "other"
}
]
}

IMPORTANT:

Provider output is NOT trusted evidence.

The normalized response should represent:

AI research candidates.

It must not imply that the provider verified the claims.

If the existing Source/Claim enum values differ, use the actual existing enums.

Do not create duplicate enums.

==================================================
PART D — MOCHYFAMI RESEARCH PROFILE
==================================================

Create a research prompt/profile abstraction consistent with:

MochyFamiScriptProfile

Suggested:

MochyFamiResearchProfile

Suggested profile identifier:

mochyfami_research_v1

The profile should encode the project's research philosophy:

- Indonesian language
- concise
- factual
- educational
- useful for YouTube Shorts
- avoid fabricated facts
- separate facts from uncertainty
- identify claims that require verification
- avoid pretending that AI knowledge is a source
- prefer source-backed information
- no unnecessary storytelling
- no clickbait claims
- no unsupported numbers
- no invented citations
- no fake URLs
- no fake publication dates

The profile should be versioned.

Do not hardcode prompt text inside the controller.

==================================================
PART E — FAKE RESEARCH PROVIDER
==================================================

Create a deterministic fake provider.

Suggested:

FakeResearchGenerationProvider

Requirements:

- no network
- no random values
- no timestamps in generated content
- deterministic output for the same request
- implements ResearchGenerationProvider
- useful for automated tests

The fake provider may generate predictable sample claims/sources based on the topic.

IMPORTANT:

Do not make the fake provider appear to perform real research.

It should clearly represent test/demo AI output.

==================================================
PART F — PROVIDER REGISTRY
==================================================

Create:

ResearchGenerationProviderRegistry

Follow the same pattern as:

ScriptGenerationProviderRegistry

Requirements:

- register provider
- resolve provider
- list providers
- unknown provider throws controlled exception
- no controller-level provider branching

Register the fake provider through the existing application provider/bootstrap mechanism.

Use the existing configuration pattern.

Suggested config:

config/ai.php

Do not break existing script generation configuration.

If necessary, extend the existing structure rather than replacing it.

==================================================
PART G — RESEARCH GENERATION SERVICE
==================================================

Create:

ResearchGenerationService

Suggested responsibilities:

1. Validate research readiness/context.
2. Load the project's ResearchReport.
3. Build ResearchGenerationRequest.
4. Resolve provider.
5. Generate structured AI candidates.
6. Normalize provider output.
7. Validate output.
8. Persist audit information.
9. Optionally persist candidates through explicit logic.
10. Re-evaluate research quality.
11. Return structured result.

CRITICAL SAFETY RULE:

AI-generated claims MUST NOT automatically become:

supported

They should remain:

unverified

unless the existing domain explicitly requires another initial state.

Likewise:

AI-generated claims MUST NOT automatically satisfy research evidence requirements.

AI-generated sources MUST NOT automatically count as verified evidence.

If a source is persisted from AI output, it must be clearly treated as a candidate source and not as verified evidence.

==================================================
PART H — PERSISTENCE BEHAVIOR
==================================================

Think carefully before changing existing research data.

Preferred behavior:

AI generation creates research candidates in a controlled way.

It must NOT:

- delete existing claims
- overwrite existing claims
- delete existing sources
- change supported → unverified
- change contradicted → supported
- automatically attach evidence
- automatically mark research completed
- automatically mark quality ready

If persistence of generated claims/sources is implemented:

- use a transaction
- preserve existing records
- create new records
- use unverified/default candidate states
- normalize source type
- normalize domains
- reject malformed URLs
- never fabricate missing URLs

If the current schema does not safely distinguish AI candidate sources from verified sources, inspect the schema first.

DO NOT invent a new database column merely for convenience.

If the schema requires a separate future concept, document it instead of silently introducing a large redesign.

==================================================
PART I — AI GENERATION AUDIT
==================================================

Reuse the existing AiGeneration model.

Do NOT create a second audit table.

The audit record should capture:

- project
- provider
- profile
- profile version
- status
- source/reference context if supported
- generated research record/reference if supported
- error information on failure

Follow Part 9 behavior.

On provider failure:

- record failed AI generation
- do not persist partial research data
- return a controlled 502/provider error
- do not expose internal exception details

On validation failure:

- do not persist invalid research data
- record appropriate failure if consistent with existing Part 9 behavior

==================================================
PART J — API ENDPOINT
==================================================

Add endpoint:

POST

/api/v1/projects/{project}/research/generate

Follow existing route conventions.

Request should support something similar to:

{
"topic": "...",
"question": "...",
"context": "...",
"max_claims": 5
}

Optional provider selection may be supported if consistent with Part 9.

Default provider should come from config.

Authorization:

Use the same research/project authorization convention already used by the application.

Do NOT introduce a new ownership model.

Expected behavior:

201:
successful generation

404:
research report/project context missing where appropriate

422:
validation or research precondition failure

502:
provider failure

Do not leak provider internals.

Response should contain enough information for the frontend to display:

- generation
- summary
- generated claims
- generated source candidates
- current research quality
- warning that human verification is required

Use existing ApiResponse conventions.

==================================================
PART K — IMPORTANT RESEARCH GATE
==================================================

Do NOT require the research report to already be quality-ready before AI research generation.

That would make the feature unusable.

Instead:

AI Research Generation is a tool for improving/increasing research material.

However:

the result of AI generation must remain subject to the existing ResearchQualityService.

After generation:

- quality may still be not ready
- claims may be unverified
- evidence may be missing
- contradictions may remain

The quality gate remains authoritative.

==================================================
PART L — FRONTEND
==================================================

Update the existing ResearchPanel.

Add a section:

"Generate Research with AI"

UI should include:

- Topic
- Research question
- Optional context
- Max claims
- Generate button
- loading state
- validation errors
- provider errors
- success state
- generated summary
- generated claims
- generated source candidates
- explicit "needs human verification" notice

Do NOT automatically attach generated sources to claims.

Do NOT automatically mark claims supported.

Do NOT automatically transition research status.

After successful generation:

refresh:

- Research
- Quality
- Pipeline

if the current architecture supports these refreshes.

Follow the existing UI conventions.

==================================================
PART M — UX SAFETY
==================================================

The frontend must clearly communicate:

"AI-generated research is a starting point and must be verified against reliable sources before being treated as factual evidence."

Do not use language that implies:

- AI has proven the claim
- AI searched the internet
- AI verified the source

unless the actual provider implementation genuinely does that.

For the fake provider, explicitly make it clear that it is demo/test generation.

==================================================
PART N — TESTING
==================================================

Add comprehensive tests.

At minimum:

Provider:

- fake provider deterministic
- registry resolves provider
- unknown provider fails

DTO:

- normalization
- max lengths
- max claims bounds

Service:

- successful generation
- research report missing
- provider failure
- malformed provider response
- too many claims
- invalid claim fields
- invalid source URL
- transaction rollback
- existing claims preserved
- existing sources preserved
- generated claims remain unverified
- no automatic evidence attachment
- no automatic status transition
- quality remains authoritative
- AI audit created
- failed AI audit created on provider failure

API:

- authentication
- validation
- successful 201
- missing research 404
- provider failure 502
- unauthorized behavior according to existing policy convention
- response shape

Frontend:
Follow the project's current testing strategy.
At minimum ensure TypeScript/build remains clean.

IMPORTANT:
Do not weaken existing tests to make the new feature pass.

==================================================
PART O — SECURITY
==================================================

Research generation is AI input.

Therefore:

- validate all user input
- enforce maximum lengths
- never execute provider output
- never trust provider URLs
- never render arbitrary HTML from provider output
- never log API keys
- never log sensitive request payloads unnecessarily
- never expose provider internals
- do not use shell execution
- do not introduce arbitrary network calls
- fake provider must remain offline

If a future real provider is added, it must be implemented behind the provider interface.

==================================================
PART P — NETWORK POLICY
==================================================

This Part MUST NOT introduce real network access.

Use:

Http::preventStrayRequests()

in tests where appropriate.

The fake provider must remain completely offline.

Do not call:

- Google
- Bing
- YouTube
- OpenAI
- Anthropic
- Gemini
- arbitrary websites

The feature is an architecture foundation only.

==================================================
PART Q — DATABASE SAFETY
==================================================

Before creating any migration:

Inspect whether the existing schema can support this feature.

Do not add unnecessary schema.

If no migration is required, explicitly document:

"No migration required."

If a migration is genuinely required:

- create the smallest possible migration
- use existing conventions
- verify fresh migration
- verify rollback
- verify migrate again

==================================================
PART R — CODE QUALITY
==================================================

Follow existing project conventions.

Do not:

- refactor unrelated code
- rename unrelated classes
- change existing API contracts unnecessarily
- redesign research architecture
- redesign AI architecture
- introduce speculative abstractions
- add unnecessary dependencies
- add placeholder TODOs
- leave console.log
- leave debug dumps
- fake test results

Keep the change focused on Part 10.

==================================================
PART S — DOCUMENTATION
==================================================

Create:

docs/PHASE_3_PART_10.md

Document:

1. Goal
2. Architecture
3. Provider contract
4. DTOs
5. Fake provider
6. Registry
7. ResearchGenerationService
8. AI audit behavior
9. Persistence rules
10. API endpoint
11. Frontend behavior
12. Human verification requirement
13. Security considerations
14. Testing
15. Known limitations
16. Future real-provider integration

Explicitly state:

AI-generated research is NOT verified evidence.

==================================================
PART T — VERIFICATION
==================================================

Before declaring completion run the appropriate existing commands.

At minimum:

Backend:

php artisan test --compact

Frontend:

npx tsc --noEmit

npm run build

Formatting:

vendor/bin/pint --test

Also verify:

- migration status if migration exists
- git diff
- git status

If Pint modifies files, run it and re-run tests.

If tests fail:

fix the implementation.

Do NOT modify tests merely to hide implementation problems.

==================================================
PART U — FINAL REPORT
==================================================

When finished, report:

PHASE 3 PART 10 STATUS

1. Implementation summary
2. Files created
3. Files modified
4. API endpoint
5. AI provider architecture
6. Persistence behavior
7. Human verification safeguards
8. Tests added
9. Full test count/assertion count
10. TypeScript result
11. Build result
12. Pint result
13. Migration result if applicable
14. Git status
15. Commit hash

IMPORTANT:

Do NOT claim completion unless all verification commands actually pass.

==================================================
GIT CHECKPOINT
==================================================

If everything passes:

Create exactly one focused commit.

Suggested commit message:

feat: add ai research generation foundation

Do NOT push automatically.

After commit:

- verify git status
- report commit hash
- stop.

Do not start Part 11 automatically.

==================================================
STOP CONDITION
==================================================

Part 10 is complete only when:

- AI research provider architecture exists
- fake provider works deterministically
- research generation service works
- AI audit exists through existing AiGeneration
- API works
- frontend works
- generated claims remain unverified
- generated sources are not treated as verified evidence
- existing research data is preserved
- quality gate remains authoritative
- tests pass
- TypeScript passes
- build passes
- Pint passes
- git checkpoint is created
- working tree is clean

Then STOP.

Do NOT implement Part 11.
