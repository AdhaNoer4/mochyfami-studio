# MOCHYFAMI CONTENT STUDIO

# PHASE 3 — PART 9

# AI SCRIPT GENERATION FOUNDATION

You are continuing development of the existing MochyFami Content Studio repository.

IMPORTANT:
This is an existing working codebase.

DO NOT rebuild the application.
DO NOT redesign unrelated areas.
DO NOT modify completed Phase 3 Parts 1–8 unless absolutely required.

==================================================

1. # PRIMARY OBJECTIVE

Implement:

PHASE 3 — PART 9
"AI Script Generation Foundation"

The objective is to introduce a provider-agnostic AI generation layer
for MochyFami scripts.

This part establishes the architecture required for:

Research
↓
Script Generation Request
↓
AI Provider
↓
Structured Script Output
↓
ScriptVersion
↓
Script Quality
↓
Human Review

IMPORTANT:

This part is about the FOUNDATION of AI generation.

Do NOT tightly couple the application to a specific AI vendor.

Do NOT make OpenAI/Gemini/Claude-specific logic part of the core domain.

Do NOT allow AI output to automatically approve, publish, or overwrite
existing scripts.

Every AI-generated result must remain human-reviewable.

================================================== 2. FIRST STEP — INSPECT THE REPOSITORY
==================================================

Before changing anything:

Inspect the actual implementation of:

- Script
- ScriptVersion
- ScriptService
- ScriptQualityService
- ScriptTextNormalizer
- ResearchReport
- ResearchClaim
- ResearchQualityService
- ResearchPipelineService
- existing AI-related code, if any
- existing service interfaces/contracts
- existing DTO conventions
- existing Resource conventions
- existing exception conventions
- existing config conventions
- existing frontend ScriptPanel
- scriptService.ts
- ProjectDetailPage
- existing tests
- existing documentation

Read:

docs/PHASE_3_PART_7.md
docs/PHASE_3_PART_8.md

The repository implementation is authoritative.

Do NOT blindly implement the Phase 0 specification if the actual
repository has evolved.

================================================== 3. CORE ARCHITECTURE PRINCIPLE
==================================================

The core application must depend on an abstraction:

AI Script Generation Provider

NOT on:

OpenAI
Gemini
Claude
etc.

Conceptually:

Application
↓
ScriptGenerationService
↓
ScriptGenerationProvider
↓
Provider Registry
↓
Concrete Provider

The concrete provider can later be:

OpenAI
Gemini
Anthropic
Local model
Fake provider

Part 9 should provide a Fake provider for tests.

================================================== 4. NON-GOALS
==================================================

DO NOT implement:

- real OpenAI API calls
- real Gemini API calls
- real Claude API calls
- TTS
- subtitle generation
- visual planning
- asset collection
- video rendering
- publishing
- analytics
- automatic approval
- automatic publication

Do not require an external API key to run the test suite.

================================================== 5. AI GENERATION CONTRACT
==================================================

Create a provider contract.

Suggested location:

app/Contracts/AI/ScriptGenerationProvider.php

The exact namespace should follow existing repository conventions.

The contract should expose enough information for the application to
use a provider without knowing vendor-specific details.

Suggested methods:

- name()
- generate(ScriptGenerationRequest $request): ScriptGenerationResponse

If the repository uses interfaces under Services/AI instead of
Contracts, follow the existing convention.

================================================== 6. REQUEST DTO
==================================================

Create an immutable DTO:

ScriptGenerationRequest

Suggested fields:

- topic
- language
- tone
- format
- target_duration_seconds
- hook_style
- research_context
- important_claims
- instructions

Do NOT expose vendor-specific request structures here.

The DTO represents what MochyFami wants generated.

It does NOT represent an OpenAI/Gemini request.

Use the repository's existing DTO conventions.

The DTO must:

- normalize strings where appropriate
- validate reasonable maximum lengths
- remain deterministic
- be immutable if repository conventions support it

================================================== 7. RESPONSE DTO
==================================================

Create:

ScriptGenerationResponse

Suggested fields:

- provider
- model nullable
- title
- hook
- body
- closing
- duration_seconds nullable
- notes nullable
- raw_metadata optional

IMPORTANT:

The core application should not depend on arbitrary raw provider data.

If raw metadata is included, keep it clearly optional and sanitized.

The response must represent a normalized script result.

================================================== 8. STRUCTURED SCRIPT OUTPUT
==================================================

The provider must return structured data.

Conceptual output:

{
"title": "...",
"hook": "...",
"body": "...",
"closing": "...",
"duration_seconds": 30,
"notes": "..."
}

Do NOT allow providers to return a random text blob that the core
application has to parse heuristically.

The provider adapter is responsible for converting provider-specific
output into ScriptGenerationResponse.

================================================== 9. FAKE PROVIDER
==================================================

Create:

FakeScriptGenerationProvider

Purpose:

- deterministic tests
- local development
- no network
- no API key
- predictable output

Example behavior:

Input topic:

"Kenapa kucing suka kardus?"

Output should be deterministic based on the request.

It does not need to produce a high-quality real script.

It only needs to prove the architecture works.

IMPORTANT:

Do not use random data.

Do not use timestamps in generated content.

Do not make HTTP calls.

================================================== 10. PROVIDER REGISTRY
==================================================

Create:

ScriptGenerationProviderRegistry

Responsibilities:

- register provider
- resolve provider by name
- list provider names
- reject unknown providers

Follow the same pattern used by:

SearchProviderRegistry

from Phase 3 Part 5.

If that registry pattern can be reused safely, reuse its conventions
without creating unnecessary duplication.

Unknown provider should produce a controlled domain exception.

Example:

ScriptGenerationProviderException

HTTP mapping should follow existing exception patterns.

================================================== 11. CONFIGURATION
==================================================

Introduce configuration for the default script generation provider.

For example:

config/ai.php

or extend an existing AI configuration file if one exists.

Conceptually:

default_script_generation_provider=fake

IMPORTANT:

Default must be the Fake provider.

The application must work immediately after cloning without an external
AI API key.

Provider registration should be centralized.

Do NOT put provider selection logic inside controllers.

================================================== 12. PROMPT PROFILE
==================================================

Create a provider-independent MochyFami script profile.

Suggested abstraction:

MochyFamiScriptProfile

or an appropriate configuration/service.

It should encode the established channel style:

- Indonesian
- casual
- educational
- funny when appropriate
- TTS-friendly
- short sentences
- approximately 25–35 seconds by default
- one main idea
- no unnecessary "Halo guys"
- avoid excessive rhetorical filler
- avoid invented facts
- do not contradict research
- concise closing
- human review remains required

IMPORTANT:

Do NOT create a giant prompt framework.

Keep this profile small and versionable.

If the repository already has prompt configuration infrastructure,
reuse it.

================================================== 13. PROMPT VERSION
==================================================

The generation request should carry a prompt/profile version.

Example:

mochyfami_script_v1

The exact naming can follow repository conventions.

This is important for future reproducibility.

The system should be able to identify which generation profile was used.

Do NOT build a full prompt management UI yet.

================================================== 14. RESEARCH CONTEXT
==================================================

AI generation must be able to receive research context.

The application should derive research context from the project's
ResearchReport.

Use existing:

- ResearchClaims
- claim status
- claim importance
- Sources where appropriate

IMPORTANT:

Do not blindly send every database column to the provider.

Create a normalized research context DTO or structured array.

Conceptually:

{
"claims": [
{
"id": 1,
"importance": "high",
"status": "supported",
"claim": "..."
}
],
"research_ready": true
}

Only include information appropriate for generation.

================================================== 15. RESEARCH READINESS
==================================================

Before generation:

Use the existing:

ResearchQualityService

and/or:

ResearchPipelineService

Do NOT duplicate their algorithms.

Generation should normally require:

research exists
AND
research quality is ready

If not ready:

return a controlled 422 domain error.

Example:

ScriptGenerationNotReadyException

The error should explain that research must pass the existing research
quality gate before AI generation.

IMPORTANT:

Do not silently generate from incomplete research.

================================================== 16. SCRIPT GENERATION SERVICE
==================================================

Create:

ScriptGenerationService

Responsibilities:

1. Validate project/script context.
2. Load research.
3. Check research readiness.
4. Build ScriptGenerationRequest.
5. Resolve provider.
6. Call provider.
7. Validate ScriptGenerationResponse.
8. Create a NEW ScriptVersion.
9. Return the generated version/result.

Do NOT put this logic in the controller.

================================================== 17. AI OUTPUT MUST BECOME A NEW VERSION
==================================================

This is critical.

AI generation must NEVER overwrite the current ScriptVersion.

Example:

Current:

Version 3

AI generation:

Version 4

Version 3 remains unchanged.

The generated Version 4 becomes current only if the application's
existing ScriptService version architecture supports this behavior.

However:

DO NOT automatically change ScriptStatus to approved.

Preferred behavior:

Script remains:

draft

or:

review

depending on the existing workflow semantics.

Inspect Part 7 and choose the state that best preserves human review.

Document the choice.

================================================== 18. AI GENERATION AUDIT
==================================================

We need enough information to understand where a generated version came
from.

Inspect whether the repository already has:

ai_generations

or another AI audit model/table.

If it exists:

reuse it.

If it does NOT exist, determine whether a minimal migration is required.

Preferred conceptual record:

AI Generation

- id
- project/script relation as appropriate
- provider
- model nullable
- prompt_profile
- prompt_version
- source_version_id nullable
- generated_version_id nullable
- status
- created_at

IMPORTANT:

Do NOT create a giant observability system.

Only store enough metadata to reproduce/understand generation origin.

Do NOT store API secrets.

Do NOT store authorization headers.

Do NOT store sensitive credentials.

If repository architecture already has `ai_generations` from Phase 0,
reuse the existing schema rather than creating a duplicate.

================================================== 19. GENERATION STATUS
==================================================

If an AI generation audit record is introduced, use explicit status.

Suggested:

pending
completed
failed

Do NOT invent unnecessary states.

The generation operation should be synchronous for this MVP unless the
existing architecture already requires queues.

Do not introduce Laravel Queue complexity solely for this part.

================================================== 20. FAILURE SAFETY
==================================================

If provider generation fails:

- do not create a ScriptVersion
- do not modify current version
- do not mark Script approved
- return controlled error
- record failed generation if audit architecture exists

Use a transaction where appropriate.

IMPORTANT:

Provider failure must not corrupt script state.

================================================== 21. GENERATED CONTENT VALIDATION
==================================================

Before creating a ScriptVersion, validate:

- title reasonable
- hook present
- body present
- closing optional
- duration reasonable if provided
- no malformed required fields
- no giant output
- no unexpected provider structure

The provider response must already be normalized.

Do not blindly trust provider output.

================================================== 22. SCRIPT QUALITY AFTER GENERATION
==================================================

After generating a new ScriptVersion:

DO NOT automatically modify the script based on ScriptQuality.

However, the API response may include the result of:

ScriptQualityService

for the new current version.

This makes the workflow:

Generate
↓
Create Version
↓
Evaluate Quality
↓
Human reviews

Do NOT automatically retry generation because quality is poor.

================================================== 23. API ENDPOINT
==================================================

Add an endpoint following existing project/script conventions.

Suggested:

POST

/api/v1/projects/{project}/script/generate

Request body:

{
"provider": "fake",
"topic": "...",
"language": "id",
"tone": "casual",
"format": "educational",
"target_duration_seconds": 30,
"hook_style": "...",
"instructions": "..."
}

Important:

Provider may be optional.

If omitted:

use configured default provider.

Do not expose provider-specific fields.

================================================== 24. GENERATION REQUEST VALIDATION
==================================================

Create:

GenerateScriptRequest

Validate:

provider:

- nullable|string|max reasonable length

topic:

- required|string|max reasonable length

language:

- nullable|string|max reasonable length

tone:

- nullable|string|max reasonable length

format:

- nullable|string|max reasonable length

target_duration_seconds:

- nullable|integer|min reasonable|max reasonable

hook_style:

- nullable|string|max reasonable length

instructions:

- nullable|string|max reasonable length

Do not accept arbitrary JSON for provider-specific settings.

================================================== 25. API RESPONSE
==================================================

Return:

- generated ScriptVersion
- provider
- model if available
- prompt profile/version
- generation metadata if appropriate
- ScriptQuality result for the new version

Conceptual response:

{
"script": {...},
"generated_version": {...},
"generation": {
"provider": "fake",
"model": null,
"prompt_profile": "mochyfami_script_v1"
},
"quality": {...}
}

Follow existing ApiResponse conventions.

================================================== 26. FRONTEND — AI GENERATE UI
==================================================

Update:

ScriptPanel.tsx

Add:

"Generate with AI"

section/button.

Do NOT create a giant AI settings page.

MVP UI fields:

- Topic
- Language
- Tone
- Format
- Target Duration
- Hook Style
- Additional Instructions
- Provider (optional if useful)

Default:

Fake provider.

The UI must make it clear this creates a NEW script version.

Example helper text:

"AI akan membuat versi script baru. Versi sebelumnya tidak diubah."

================================================== 27. GENERATION CONFIRMATION
==================================================

Before generation:

show a confirmation if generation creates a new version.

Example:

"Generate script baru dari research saat ini?"

Explain:

- current version remains unchanged
- new version will be created
- human review is still required

Do not make the confirmation overly complicated.

================================================== 28. GENERATION LOADING STATE
==================================================

During generation:

- disable duplicate submit
- show loading state
- prevent accidental repeated requests
- show success/error result

Do NOT implement polling.

Do NOT implement streaming.

================================================== 29. QUALITY AFTER GENERATION
==================================================

After successful generation:

Refresh:

- current Script
- version list
- Script Quality

The newly generated version should be visible.

Do not refresh the entire application unnecessarily.

================================================== 30. FRONTEND TYPES
==================================================

Add types for:

ScriptGenerationRequest
ScriptGenerationResponse
ScriptGenerationMetadata

Avoid:

any

Do not use type assertions to bypass errors.

================================================== 31. FRONTEND API SERVICE
==================================================

Update:

scriptService.ts

Add:

generate()

or:

generateScript()

Follow existing service conventions.

================================================== 32. AUTHORIZATION
==================================================

Follow the exact same ownership/authentication convention already used
in Parts 1–8.

Do NOT introduce a new ownership model.

If the current studio intentionally permits any authenticated user to
access project data, preserve that behavior.

Do not silently change authorization in Part 9.

================================================== 33. TESTING — PROVIDER
==================================================

Unit tests:

- Fake provider deterministic
- request normalization
- response normalization
- registry registration
- registry resolution
- unknown provider
- provider name

================================================== 34. TESTING — GENERATION SERVICE
==================================================

Test:

1. research missing
2. research not ready
3. research ready
4. provider resolution
5. generation success
6. new ScriptVersion created
7. previous version unchanged
8. current version changes correctly
9. provider failure
10. provider failure does not create version
11. generated output validation
12. quality evaluated after generation
13. no automatic approval
14. deterministic fake generation
15. no external HTTP

================================================== 35. TESTING — AI AUDIT
==================================================

If AI generation audit persistence exists:

Test:

- successful generation recorded
- provider recorded
- prompt profile/version recorded
- generated version linked
- failure recorded
- secrets are never persisted

If no audit table is introduced because the repository already has a
suitable alternative, document that decision.

================================================== 36. TESTING — API
==================================================

Feature tests:

- POST generate
- validation failure
- research not ready
- provider not found
- successful generation
- correct response structure
- new version created
- old version preserved
- quality included
- authorization follows existing convention

================================================== 37. TESTING — FRONTEND
==================================================

At minimum:

- TypeScript passes
- build passes

If the repository already has frontend test infrastructure, add focused
tests for:

- generate form
- loading state
- error state
- successful generation
- version refresh

Do NOT introduce a new frontend test framework just for this part.

================================================== 38. NO REAL AI NETWORK
==================================================

Part 9 MUST pass with:

ZERO external AI API calls.

Use:

FakeScriptGenerationProvider

for all automated tests.

If any provider-specific code is introduced for future use, it must
remain unregistered or disabled unless explicitly configured.

Do not require API keys.

================================================== 39. SECURITY
==================================================

Verify:

- no API keys in repository
- no provider secrets in database
- no authorization headers stored
- no raw credentials in logs
- no arbitrary shell execution
- no arbitrary HTTP URLs from user input
- no mass assignment vulnerabilities
- no provider-specific arbitrary payload accepted from frontend
- generated output validated before persistence

================================================== 40. PROMPT / CONTENT SAFETY
==================================================

The generation profile should explicitly instruct the provider:

- use research context
- do not invent facts
- do not contradict supported research
- flag uncertainty rather than fabricate
- keep script TTS-friendly
- keep one main idea
- use Indonesian by default
- avoid excessive filler
- do not automatically claim certainty beyond research

This is guidance to the future provider.

The system must still treat AI output as untrusted draft content.

================================================== 41. DOCUMENTATION
==================================================

Create:

docs/PHASE_3_PART_9.md

Document:

- AI provider abstraction
- request/response DTOs
- provider registry
- fake provider
- configuration
- MochyFami prompt profile
- research readiness gate
- version creation behavior
- AI generation audit
- failure behavior
- API endpoint
- frontend
- testing
- known limitations

Explicitly state:

"AI-generated scripts are drafts and require human review."

Also state:

"The Fake provider exists for deterministic local development and
testing; it is not a production AI provider."

================================================== 42. MIGRATION SAFETY
==================================================

Only create migrations if the repository genuinely needs persistence
for AI generation audit.

Before migration:

- inspect existing ai_generations implementation if present
- avoid duplicate tables
- follow Laravel conventions

After migration:

php artisan migrate
php artisan migrate:rollback
php artisan migrate

If migration is not required, explicitly report:

"No migration required."

================================================== 43. CODE QUALITY
==================================================

Run:

php artisan test --compact

npx tsc --noEmit

npm run build

./vendor/bin/pint --dirty --format agent

Also run migration checks if schema changed.

Run the relevant focused tests first, then full suite.

Do not ignore failures.

================================================== 44. GIT SAFETY
==================================================

Before implementation:

git status

After implementation:

git diff --stat
git status

Review all changed files.

Create ONE focused commit:

feat: add ai script generation foundation

Do NOT push automatically.

Do NOT include unrelated changes.

================================================== 45. COMPLETION REPORT
==================================================

When finished, report exactly:

PHASE 3 PART 9 REPORT

Status:
PASS / BLOCKED

Implementation:

- Provider contract:
- Request DTO:
- Response DTO:
- Fake provider:
- Provider registry:
- Configuration:
- Prompt profile:
- Research context:
- Research readiness:
- ScriptGenerationService:
- Version creation:
- AI generation audit:
- API:
- Frontend:
- Quality integration:
- Documentation:

Verification:

- Tests:
- Assertions:
- TypeScript:
- Build:
- Pint:
- Migration:
- External HTTP:
- Security:
- Determinism:

Git:

- Commit:
- Working tree:

Known limitations:

- ...

IMPORTANT:

Never claim PASS unless the implementation and verification actually
succeeded.

================================================== 46. STOP CONDITION
==================================================

After completing Part 9:

STOP.

Do NOT start Part 10.

Wait for explicit instruction:

"Lanjut Part 10"

==================================================
FINAL ARCHITECTURE PRINCIPLE
==================================================

AI is a production assistant, not the final authority.

Research
↓
Research Quality
↓
Research Pipeline
↓
Script
↓
AI Generation
↓
NEW ScriptVersion
↓
Script Quality
↓
Human Review
↓
Approval

The AI must never:

- overwrite historical versions
- approve its own output
- publish its own output
- bypass research readiness
- bypass Script Quality
- become a hard dependency of the application core.

Keep the provider abstraction clean so real AI providers can be added
later without rewriting the Script domain.
