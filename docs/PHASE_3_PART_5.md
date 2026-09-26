You are continuing development of the existing MochyFami Content Studio project.

IMPORTANT:
This is PHASE 3 — PART 5.

Do NOT rewrite existing working architecture.
Do NOT redesign the Research UI.
Do NOT implement automatic AI fact-checking.
Do NOT implement automatic claim generation.
Do NOT automatically trust search results as factual evidence.

This part builds the Research Intelligence / Source Discovery FOUNDATION.

==================================================
PHASE 3 — PART 5
RESEARCH INTELLIGENCE FOUNDATION
==================================================

## OBJECTIVE

Build a provider-agnostic Research Intelligence foundation that
can discover external sources for a research topic.

The architecture must separate:

1. Research domain
2. Search provider contract
3. Search provider implementation
4. Search result normalization
5. Source discovery
6. Existing ResearchSource persistence

The goal is to make external research providers replaceable.

Example future providers could be:

- Tavily
- Brave Search
- Serper
- Bing
- custom internal provider

BUT:

Do NOT integrate a real external provider in this part.

Do NOT make real network requests.

Do NOT add an API key requirement.

This part establishes the contracts and a deterministic
fake/in-memory provider for testing.

==================================================

1. # INSPECT THE REPOSITORY FIRST

Before modifying anything inspect:

- app/Services/ResearchService.php
- app/Services/ResearchQualityService.php
- app/Models/ResearchReport.php
- app/Models/ResearchClaim.php
- app/Models/Source.php
- app/Http/Controllers/Api/V1/ResearchController.php
- app/Http/Controllers/Api/V1/ResearchSourceController.php
- app/Http/Resources/ResearchResource.php
- app/Http/Resources/ResearchSourceResource.php
- resources/js/services/researchService.ts
- resources/js/components/projects/ResearchPanel.tsx
- resources/js/types/index.ts
- routes/api.php
- AppServiceProvider
- existing exception handling
- existing service registration patterns
- existing tests
- existing enums
- existing configuration structure

The repository is the source of truth.

Do NOT assume names or fields from the original specification
if the repository has evolved.

================================================== 2. IMPORTANT DOMAIN RULE
==================================================

Existing Source records are research evidence metadata.

A discovered search result MUST NOT automatically become a
verified source.

A search result only represents:

"candidate source discovered by research intelligence."

The user/research workflow must still decide whether it should
be persisted/used as evidence.

Do not automatically attach discovered results to claims.

Do not automatically mark claims as supported.

================================================== 3. CREATE SEARCH PROVIDER CONTRACT
==================================================

Create a provider interface.

Suggested location:

app/Services/Research/Contracts/SearchProvider.php

Use the project's existing namespace conventions if different.

The interface should expose a method conceptually similar to:

search(SearchQuery $query): SearchResponse

Do not use raw arrays everywhere if a small DTO/value-object
structure is practical.

The contract must be provider-agnostic.

The caller should NOT know whether the implementation is:

- Tavily
- Brave
- Serper
- Bing
- mock
- internal provider

================================================== 4. SEARCH QUERY DTO
==================================================

Create a small immutable structure representing a search request.

Suggested:

SearchQuery

Minimum fields:

- query
- max_results

Optional fields only if genuinely useful:

- language
- country
- freshness

Do not overengineer.

The query must be validated at the service boundary.

Rules:

- query must not be empty
- trim whitespace
- reasonable maximum length
- max_results must have a safe upper bound

Do not allow arbitrary provider-specific options
to leak into the core domain.

================================================== 5. SEARCH RESULT DTO
==================================================

Create a normalized search result structure.

Suggested:

SearchResult

Minimum fields:

- title
- url
- snippet
- domain

Optional:

- published_at

The result must represent a normalized provider-independent result.

Do not expose raw provider JSON to the rest of the application.

Example conceptual object:

SearchResult {
title: string,
url: string,
snippet: ?string,
domain: string,
published_at: ?Carbon
}

Use appropriate PHP types.

================================================== 6. SEARCH RESPONSE DTO
==================================================

Create:

SearchResponse

Minimum:

- results
- provider
- query
- metadata if useful

Example conceptual result:

{
"provider": "fake",
"query": "why cats purr",
"results": [...]
}

Do not persist this response automatically.

It is an intelligence-layer response.

================================================== 7. PROVIDER EXCEPTION
==================================================

Create a dedicated exception for provider failures.

Suggested:

SearchProviderException

It should represent failures such as:

- provider unavailable
- invalid provider response
- provider timeout
- provider authentication failure
- provider rate limit

Do not expose sensitive provider details to the frontend.

Do not include API keys in exception messages.

Do not log secrets.

================================================== 8. FAKE / IN-MEMORY PROVIDER
==================================================

Create a deterministic fake provider for testing.

Suggested:

FakeSearchProvider

It must implement SearchProvider.

It should allow tests to configure results.

Example conceptual behavior:

$provider = new FakeSearchProvider([
SearchResult(...)
]);

$response = $provider->search(
new SearchQuery('why cats purr', 5)
);

No network request must occur.

This provider is NOT production search.

It exists only to validate the architecture.

================================================== 9. PROVIDER RESOLUTION
==================================================

Create a small provider resolver/registry.

Suggested concept:

SearchProviderManager

or

SearchProviderRegistry

Responsibilities:

- register providers
- resolve provider by name
- expose available provider names

Example:

fake → FakeSearchProvider

Do not build a giant plugin system.

Keep the abstraction small.

If the project already has a service/provider registration
pattern, follow it.

================================================== 10. CONFIGURATION
==================================================

Create a configuration boundary for research intelligence.

Suggested:

config/research.php

Possible configuration:

'research' => [
'search' => [
'default_provider' => 'fake',
'providers' => [
'fake' => [
'enabled' => true,
],
],
],
],

However:

Do not add fake API keys.

Do not require environment variables for this part.

Do not put secrets in config.

The fake provider may be the default for now.

================================================== 11. SOURCE NORMALIZATION
==================================================

Create a small normalizer that converts SearchResult
into the existing Source model's compatible data.

IMPORTANT:

Do not save automatically.

The normalizer should produce data such as:

- title
- url
- domain
- source_type if safely determinable

But do NOT guess source_type from weak signals.

If source_type cannot be reliably determined:

use the existing safe/default convention,
or leave it unset if the schema allows.

Inspect the existing Source model and enum before deciding.

================================================== 12. DOMAIN EXTRACTION
==================================================

SearchResult should expose a normalized domain.

For example:

https://www.example.com/article/test

→ example.com

Handle:

- https
- http
- www
- paths
- query strings

Do not make network requests just to resolve domains.

Do not follow redirects.

Do not fetch the URL.

Domain extraction must be local/deterministic.

================================================== 13. URL SAFETY
==================================================

Search results contain external URLs.

Do not fetch them.

Validate basic URL structure.

Do not allow:

- javascript:
- data:
- file:
- arbitrary local filesystem URLs

Accept only normal HTTP/HTTPS URLs for discovered web sources.

Do not introduce SSRF-prone URL fetching.

There must be ZERO outbound network traffic
from the fake provider and tests.

================================================== 14. SOURCE DISCOVERY SERVICE
==================================================

Create a dedicated service.

Suggested:

ResearchIntelligenceService

Responsibilities:

- accept a ResearchReport
- accept a SearchQuery
- resolve the selected SearchProvider
- execute search
- normalize results
- return normalized candidate sources

It must NOT:

- create Source records automatically
- attach sources to claims
- mark claims supported
- modify ResearchStatus
- modify ResearchQuality
- generate claims
- call AI

Conceptually:

discoverSources(
ResearchReport $report,
SearchQuery $query
): SearchResponse

The ResearchReport parameter exists primarily for
authorization/domain context and future extensibility.

================================================== 15. OWNERSHIP / DOMAIN SCOPE
==================================================

ResearchIntelligenceService must not bypass existing
ownership rules.

The API endpoint must enforce:

authenticated user
↓
project ownership
↓
research ownership

Follow the existing project/research authorization conventions.

Do not create a separate ownership system.

================================================== 16. API ENDPOINT
==================================================

Add a read-only search/discovery endpoint.

Suggested:

POST

/api/v1/projects/{project}/research/discover

Why POST?

Because the request contains structured search parameters
and we do not want search queries unnecessarily encoded
into URLs.

Request:

{
"query": "why cats purr",
"max_results": 5
}

Optional:

{
"provider": "fake"
}

However provider selection should be optional.

If omitted:

use configured default provider.

IMPORTANT:

This endpoint returns candidate search results only.

It must NOT create database Source records.

It must NOT create claims.

It must NOT create evidence relations.

================================================== 17. REQUEST VALIDATION
==================================================

Create a dedicated Form Request.

Suggested:

ResearchDiscoveryRequest

Rules:

query:

- required
- string
- trimmed
- reasonable max length

max_results:

- optional
- integer
- minimum 1
- safe maximum

provider:

- optional
- string
- must exist in provider registry

Do not allow arbitrary provider class names.

Never accept a PHP class name from the client.

================================================== 18. API RESPONSE
==================================================

Follow the existing ApiResponse convention.

Conceptual:

{
"data": {
"provider": "fake",
"query": "why cats purr",
"results": [
{
"title": "...",
"url": "https://example.com/...",
"snippet": "...",
"domain": "example.com"
}
]
}
}

Do not expose internal provider implementation details.

Do not expose stack traces.

Do not expose API credentials.

================================================== 19. FRONTEND
==================================================

Extend ResearchPanel minimally.

Add a small:

"Discover Sources"

section.

UI should contain:

- query input
- max results
- search button
- loading state
- error state
- results list

Each result displays:

- title
- domain
- snippet
- external link

IMPORTANT:

The result is a CANDIDATE SOURCE.

Do NOT automatically save it.

Add an explicit action:

"Add Source"

when appropriate.

If implementing Add Source in this part:

It should create a normal Source record through the existing
Source CRUD API.

It must NOT attach the source to a claim automatically.

The user can later attach it through the existing Evidence UI.

================================================== 20. FRONTEND TYPES
==================================================

Add explicit TypeScript types:

- SearchQuery
- SearchResult
- SearchResponse
- ResearchDiscoveryRequest

No any.

Do not weaken existing types.

================================================== 21. FRONTEND SERVICE
==================================================

Extend researchService.ts with something like:

discoverSources(
projectId,
payload
)

Follow existing API service conventions.

Do not put fetch logic directly into ResearchPanel if the
project already centralizes API calls in services.

================================================== 22. EXTERNAL LINK SAFETY
==================================================

When displaying discovered URLs:

- use normal anchor behavior
- target="\_blank" if consistent with existing UI
- use rel="noopener noreferrer"

Do not render provider snippets as raw HTML.

Treat title/snippet as plain text.

Prevent XSS.

================================================== 23. TEST — SEARCH CONTRACT
==================================================

Create unit tests for:

1. valid query
2. empty query rejected
3. max_results validation
4. normalized result structure
5. provider name
6. fake provider returns configured results
7. fake provider makes zero network requests

================================================== 24. TEST — PROVIDER REGISTRY
==================================================

Test:

- fake provider resolves
- unknown provider rejected
- configured default provider resolves
- client cannot instantiate arbitrary class names

================================================== 25. TEST — DOMAIN / URL NORMALIZATION
==================================================

Test URLs such as:

https://example.com/article

https://www.example.com/article

http://example.com/test?q=1

Expected normalized domain:

example.com

Reject or ignore invalid protocols:

javascript:
data:
file:

No HTTP request should be made.

================================================== 26. TEST — RESEARCH INTELLIGENCE SERVICE
==================================================

Test:

1. discovery returns normalized results

2. report is respected

3. no Source record is created

4. no ResearchClaim is created

5. no evidence relation is created

6. no ResearchStatus mutation

7. provider failure becomes controlled exception

8. unknown provider fails safely

9. no network request from fake provider

================================================== 27. TEST — API
==================================================

Add API tests for:

- authenticated owner → 200
- unauthenticated → 401
- foreign project → 404
- invalid query → 422
- invalid max_results → 422
- unknown provider → 422
- successful discovery
- empty result set
- provider failure
- response structure
- database remains unchanged

Do not make real network requests.

================================================== 28. NETWORK SAFETY TESTING
==================================================

This is critical.

The entire test suite for this feature must not call
real external websites.

If Laravel HTTP Client is used in future provider adapters,
tests must use Http::fake() or equivalent.

Also consider using:

Http::preventStrayRequests()

in relevant tests if HTTP Client is introduced.

At this stage the FakeSearchProvider should require
ZERO HTTP calls.

Laravel's HTTP client officially supports request faking,
request assertions, and preventing stray requests, which
should be used once an actual HTTP provider exists.

================================================== 29. NO DATABASE PERSISTENCE OF DISCOVERY RESULTS
==================================================

Do NOT create a discovery_results table.

Do NOT create search_history.

Do NOT persist every search query.

Do NOT persist provider responses.

Do NOT create source records automatically.

The discovery endpoint is intentionally read-only
with respect to the database.

The explicit "Add Source" action may use the existing
Source CRUD flow.

================================================== 30. NO AI
==================================================

Absolutely do NOT implement:

- LLM calls
- prompt generation
- automatic claim extraction
- automatic summarization
- AI fact checking
- AI source ranking
- AI source credibility scoring
- AI-written research reports

Part 5 is provider/discovery infrastructure only.

================================================== 31. NO AUTOMATIC SOURCE RANKING
==================================================

Do not invent a credibility score.

Do not label sources:

- trustworthy
- authoritative
- reliable
- best

unless the provider explicitly supplies such metadata.

For this part, results are simply:

candidate sources.

================================================== 32. SECURITY
==================================================

Verify:

- auth:sanctum
- project ownership
- research ownership
- no arbitrary class resolution
- no SSRF
- no URL fetching
- no shell execution
- no secrets
- no API keys
- no raw HTML rendering
- no mass assignment issue
- no cross-user data access

================================================== 33. PERFORMANCE
==================================================

Do not introduce unnecessary database queries.

Discovery itself should not need database writes.

If the report is resolved only for authorization/context,
avoid loading unrelated claims/sources.

================================================== 34. FILE SCOPE
==================================================

Possible new files:

app/Services/Research/Contracts/SearchProvider.php

app/Services/Research/DTO/SearchQuery.php

app/Services/Research/DTO/SearchResult.php

app/Services/Research/DTO/SearchResponse.php

app/Services/Research/FakeSearchProvider.php

app/Services/Research/SearchProviderRegistry.php

app/Services/ResearchIntelligenceService.php

app/Exceptions/SearchProviderException.php

app/Http/Requests/ResearchDiscoveryRequest.php

app/Http/Controllers/Api/V1/ResearchDiscoveryController.php

tests/Unit/ResearchSearchProviderTest.php

tests/Feature/ResearchDiscoveryApiTest.php

Possibly:

config/research.php

Only create files that are genuinely needed.

Modified:

routes/api.php

resources/js/types/index.ts

resources/js/services/researchService.ts

resources/js/components/projects/ResearchPanel.tsx

Possibly AppServiceProvider if provider registration requires it.

Do not modify unrelated files.

================================================== 35. ARCHITECTURE QUALITY
==================================================

Avoid these anti-patterns:

BAD:

Controller → Http::get() → raw JSON → database

BAD:

Controller → SearchProvider → create Source automatically

BAD:

ResearchPanel → fetch() directly → provider API

BAD:

Client sends PHP class name

BAD:

Search result automatically becomes verified evidence

GOOD:

Controller
↓
ResearchIntelligenceService
↓
SearchProviderRegistry
↓
SearchProvider interface
↓
FakeSearchProvider
↓
SearchResponse
↓
Frontend

Later:

SearchProvider interface
↓
Real provider adapter

================================================== 36. TESTING / VALIDATION
==================================================

Run:

php artisan test

npx tsc --noEmit

npm run build

vendor/bin/pint --dirty --format agent

Also inspect:

git diff

git status --short

Check:

- no .env changed
- no secrets
- no API keys
- no debug statements
- no console.log
- no dump/dd/var_dump
- no TODO/FIXME introduced
- no unrelated changes

================================================== 37. REGRESSION EXPECTATION
==================================================

All existing Phase 1–3 tests must remain green.

Part 4 baseline:

252 tests
936 assertions

Part 5 should increase test count without breaking
existing tests.

Report the exact final count.

================================================== 38. GIT CHECKPOINT
==================================================

If everything passes:

Create exactly one commit:

feat: add research intelligence foundation

Do NOT push to GitHub.

Verify:

git status

Working tree must be clean.

================================================== 39. FINAL REPORT
==================================================

When finished, STOP.

Do NOT continue to Part 6.

Return a concise but complete report:

1. Search Provider Contract — PASS/FAIL
2. Search DTOs — PASS/FAIL
3. Fake Provider — PASS/FAIL
4. Provider Registry — PASS/FAIL
5. URL/Domain Normalization — PASS/FAIL
6. Research Intelligence Service — PASS/FAIL
7. Discovery API — PASS/FAIL
8. Frontend Discovery UI — PASS/FAIL
9. Source Persistence Safety — PASS/FAIL
10. Authorization — PASS/FAIL
11. Network Safety — PASS/FAIL
12. Tests — PASS/FAIL
13. Regression — PASS/FAIL
14. TypeScript — PASS/FAIL
15. Build — PASS/FAIL
16. Pint — PASS/FAIL
17. Security — PASS/FAIL
18. Files Created
19. Files Modified
20. Test count + assertions
21. Git commit hash
22. Git status
23. PHASE 3 PART 5 STATUS

Only report:

PHASE 3 PART 5 STATUS: READY FOR PART 6

if every required check passes.

STOP.
