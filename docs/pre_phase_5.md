# Pre-Phase 5 Stabilization

## Status

**PLANNING SPECIFICATION — DO NOT IMPLEMENT YET**

This stabilization task exists between Phase 4 and Phase 5.

It is NOT a new Phase 4 part.

The purpose is to fix concrete integration blockers discovered during the Phase 1–4 End-to-End Smoke Test so the existing application workflow can be used through the UI/API without database workarounds.

The coding agent MUST first inspect and plan the work.

Do not implement until the planning report has been reviewed and explicitly approved.

---

# 1. Background

The Phase 1–4 End-to-End Smoke Test successfully reached:

```text
Idea
→ Research
→ Fact Check
→ Script
→ Visual Plan
→ Asset Requirement
→ Asset Upload
→ Asset Metadata
→ Asset Fulfillment
→ Asset Readiness
→ Production Manifest
→ Production Handoff
→ Production Specification
```

The final Production Specification reached:

```text
ready = true
```

However, the test required directly modifying the database to change an Asset from its initial status to `available`.

This revealed a real application integration blocker.

Additional issues were discovered:

1. Asset status cannot currently be changed through API/UI.
2. SPA deep links return 404 on refresh.
3. Unauthenticated API requests without `Accept: application/json` can produce 500 because the default login route does not exist.
4. Development setup documentation is incomplete.

---

# 2. Primary Objective

Make the existing Phase 1–4 workflow usable through the actual application without manual database modification.

The stabilization must remain small and focused.

Do NOT redesign Phase 4.

Do NOT start Phase 5.

Do NOT introduce unrelated features.

---

# 3. Required Scope

## P0 — Asset Status Management

Add an explicit, project-scoped Asset Status transition mechanism.

Preferred contract:

```text
PATCH /api/v1/projects/{project}/assets/{asset}/status
```

The implementation must reuse the existing AssetStatus transition model.

Inspect and reuse:

```text
AssetStatus
AssetStatus::allowedTransitions()
AssetPolicy
AssetService
AssetResource
AssetController
```

Do NOT introduce a parallel status system.

---

# 4. Asset Status Requirements

The implementation must determine the exact current state machine from the repository.

Do not invent transitions.

The API must:

* authenticate the user
* enforce project scoping
* enforce Asset authorization
* validate requested status
* validate that the transition is allowed
* reject invalid transitions
* return the updated asset resource
* expose the currently allowed transitions

The response should allow the frontend to understand:

```text
current status
allowed transitions
```

Do not expose internal storage paths.

---

# 5. Asset Status Frontend

Add the minimum UI necessary to change an Asset status.

The UI should:

* show current status
* show valid transitions
* allow only valid transitions
* show loading state
* show success/error state
* refresh the asset state after mutation

Do not redesign the Asset Library.

Do not introduce a new page.

Integrate with the existing Asset card/detail/modal workflow.

---

# 6. Important Asset Status Decision

Do NOT automatically change an asset to `available` merely because upload or metadata analysis succeeds.

The status transition must remain explicit unless repository inspection proves that the existing domain model defines automatic availability.

Preserve the existing state machine.

The objective is to expose the existing domain capability, not change its semantics.

---

# 7. P1 — SPA Deep-Link Support

Inspect the current Laravel + React SPA routing architecture.

Current observed problem:

```text
/dashboard → 404 on direct browser request
/projects/1 → 404 on direct browser request
```

Determine the correct existing SPA fallback mechanism.

The fix must allow valid frontend routes to be loaded directly or refreshed.

Do not create individual Laravel routes for every React route.

Do not interfere with:

```text
/api/*
```

API routes must continue to be handled by Laravel API routing.

The implementation must preserve normal Laravel/backend routes where applicable.

Verify at minimum:

```text
/dashboard
/projects/{id}
```

and any other currently registered frontend routes.

---

# 8. P2 — Unauthenticated API Response

Fix the current behavior where an unauthenticated API request without:

```text
Accept: application/json
```

can produce HTTP 500 because Laravel attempts to redirect to a non-existent `login` route.

Desired API behavior:

```text
Unauthenticated API request
→ HTTP 401
```

The fix must be implemented at the appropriate Laravel authentication/exception configuration layer.

Do not create a fake login route merely to suppress the error.

Do not alter frontend authentication behavior unnecessarily.

Verify both:

```text
Accept: application/json
```

and requests without that header.

Do not expose stack traces.

---

# 9. P2 — Development Documentation

Improve the project development documentation enough for a fresh developer to run the application.

Inspect the current README before modifying it.

Document:

* prerequisites
* PHP version
* MySQL requirement
* Node/npm requirement
* ffprobe requirement
* environment setup
* `.env` creation
* application key generation
* database configuration
* migrations
* seeding
* frontend build/dev command
* Laravel development server
* storage/private asset behavior
* development seed credentials

Clearly mark development-only credentials.

Do not expose production secrets.

Do not document test database credentials as production credentials.

If `README.md` is intentionally kept separate from project documentation, explain why and identify the correct documentation location.

---

# 10. Explicitly OUT OF SCOPE

Do NOT implement:

* Phase 5
* TTS
* subtitle generation
* audio generation
* FFmpeg rendering
* video rendering
* thumbnail generation
* publishing
* analytics
* production jobs
* timeline system
* asset auto-selection
* AI asset selection
* new Asset statuses unless required by the existing state machine
* automatic `available` status after upload
* redesign of Asset Library
* redesign of Visual Plan
* fake search improvements
* visual prompt generation improvements
* requirement-type redesign
* analyze-without-file behavior change

The following remain known limitations unless the repository proves they are already required:

```text
FakeSearchProvider returning zero discovery results
placeholder visual prompts
default "other" requirement type
analyze without file returning metadata failure
```

Do not silently expand the scope to fix them.

---

# 11. Security Requirements

Preserve:

* Sanctum authentication
* project isolation
* AssetPolicy authorization
* 404 behavior for cross-project resources where already established
* private asset storage
* no public storage URLs
* no `file_path` exposure
* no absolute filesystem paths
* no credentials in API responses

Asset status mutation must be project-scoped.

Test:

```text
Project A asset
→ Project B status mutation
→ must fail according to existing authorization/scoping contract
```

---

# 12. API Contract

If the existing API conventions support the proposed endpoint:

```text
PATCH /api/v1/projects/{project}/assets/{asset}/status
```

Request:

```json
{
  "status": "available"
}
```

Do not assume `"available"` is always the only valid target.

The implementation must derive valid transitions from the existing domain state machine.

Response should use the existing AssetResource or equivalent established resource contract.

The resource must expose:

```text
status
allowed_transitions
```

without exposing private storage information.

---

# 13. Testing Requirements

## Asset Status

Add tests for:

* authenticated success
* valid transition
* invalid transition
* invalid status value
* missing asset
* missing project
* cross-project asset
* unauthorized user
* allowed transitions in resource
* read-only fields cannot be modified
* no file path leakage

## SPA

Test or manually verify:

* `/dashboard`
* `/projects/{id}`
* browser refresh/direct navigation
* API routes remain unaffected

## Authentication

Test:

* unauthenticated API request with JSON Accept
* unauthenticated API request without JSON Accept
* expected 401
* no 500
* no stack trace

## Documentation

Verify documented setup commands actually work in a clean development environment where practical.

---

# 14. Regression Requirements

The existing test suite must remain green.

The implementation must not break:

```text
Phase 4 Part 6
Phase 4 Part 7
Phase 4 Part 8
Phase 4 Part 9
Phase 4 Part 10
```

In particular, verify the complete readiness chain still works:

```text
Asset Readiness
→ Production Manifest
→ Production Handoff
→ Production Specification
```

---

# 15. End-to-End Acceptance Test

After implementation, reproduce the previous smoke test.

The workflow must reach:

```text
Production Specification
ready = true
```

without manually modifying the database.

The Asset status must be changed through the actual application API/UI.

The test must demonstrate:

```text
Asset upload
→ Asset status transition through API/UI
→ Candidate association
→ Selection
→ Fulfillment
→ Asset Readiness
→ Manifest
→ Handoff
→ Production Specification
```

No database workaround is allowed.

---

# 16. Performance

Inspect for obvious N+1 queries introduced by the new status endpoint or frontend.

Do not introduce unnecessary repeated asset queries.

Use existing project-scoped relationships and policies.

Do not prematurely optimize unrelated code.

---

# 17. Mutation Checks

For Asset Status:

At minimum perform mutation checks for:

1. bypassing transition validation
2. bypassing project scoping
3. accepting an invalid status
4. bypassing authorization

Each mutation must cause an appropriate test to fail.

Restore the implementation exactly afterward.

Confirm byte-identical restoration.

---

# 18. Verification

After implementation, run:

```text
php artisan test --compact
npx tsc --noEmit
npm run build
vendor/bin/pint --dirty --format agent
```

Also run PHP syntax checks:

```text
php -l
```

Perform security/debug sweeps for:

* file_path
* storage paths
* Storage::url
* credentials
* debug output
* arbitrary shell execution

---

# 19. Documentation

Create:

```text
docs/PRE_PHASE_5_STABILIZATION_IMPLEMENTATION.md
```

Do NOT modify this specification:

```text
docs/PRE_PHASE_5_STABILIZATION.md
```

The implementation document must contain:

* scope
* files changed
* Asset Status design
* SPA fallback design
* API authentication fix
* documentation changes
* tests
* security checks
* mutation checks
* end-to-end acceptance result
* final verification
* commit hash

---

# 20. Commit

Implementation must use focused commit(s).

Prefer one commit if the changes remain cohesive:

```text
fix: stabilize pre-phase-5 workflow
```

Do NOT push.

The user will decide when to push.

---

# 21. Planning Stage Rules

The first agent invocation must be PLAN ONLY.

During planning:

* inspect repository
* inspect current AssetStatus state machine
* inspect AssetController
* inspect AssetResource
* inspect AssetPolicy
* inspect API routes
* inspect Laravel exception/auth configuration
* inspect React routing
* inspect README
* inspect existing tests
* determine exact files requiring changes

Do NOT modify files.

Do NOT create migrations.

Do NOT commit.

Do NOT push.

---

# 22. Required Planning Report

Return:

## 1. Repository Findings

## 2. Current Asset Status State Machine

List the actual statuses and allowed transitions found in code.

## 3. Proposed Asset Status API

Route, authorization, request, response, validation.

## 4. Proposed Frontend Change

Exact existing component to modify.

## 5. SPA Routing Fix

Explain the correct fallback architecture.

## 6. API Authentication Fix

Explain where the 401 behavior should be corrected.

## 7. Documentation Fix

Explain which documentation file will be updated.

## 8. Security Analysis

## 9. Test Plan

## 10. Mutation Plan

## 11. End-to-End Acceptance Plan

## 12. Exact Files To Change

## 13. Files That Must Remain Untouched

## 14. Open Questions

Only list genuine architectural questions.

## 15. Recommendation

End with exactly one:

```text
READY FOR IMPLEMENTATION
```

or:

```text
REQUIRES ARCHITECTURAL DECISION
```

Do not implement until the planning report has been reviewed and explicitly approved.
# Pre-Phase 5 Stabilization

## Status

**PLANNING SPECIFICATION — DO NOT IMPLEMENT YET**

This stabilization task exists between Phase 4 and Phase 5.

It is NOT a new Phase 4 part.

The purpose is to fix concrete integration blockers discovered during the Phase 1–4 End-to-End Smoke Test so the existing application workflow can be used through the UI/API without database workarounds.

The coding agent MUST first inspect and plan the work.

Do not implement until the planning report has been reviewed and explicitly approved.

---

# 1. Background

The Phase 1–4 End-to-End Smoke Test successfully reached:

```text
Idea
→ Research
→ Fact Check
→ Script
→ Visual Plan
→ Asset Requirement
→ Asset Upload
→ Asset Metadata
→ Asset Fulfillment
→ Asset Readiness
→ Production Manifest
→ Production Handoff
→ Production Specification
```

The final Production Specification reached:

```text
ready = true
```

However, the test required directly modifying the database to change an Asset from its initial status to `available`.

This revealed a real application integration blocker.

Additional issues were discovered:

1. Asset status cannot currently be changed through API/UI.
2. SPA deep links return 404 on refresh.
3. Unauthenticated API requests without `Accept: application/json` can produce 500 because the default login route does not exist.
4. Development setup documentation is incomplete.

---

# 2. Primary Objective

Make the existing Phase 1–4 workflow usable through the actual application without manual database modification.

The stabilization must remain small and focused.

Do NOT redesign Phase 4.

Do NOT start Phase 5.

Do NOT introduce unrelated features.

---

# 3. Required Scope

## P0 — Asset Status Management

Add an explicit, project-scoped Asset Status transition mechanism.

Preferred contract:

```text
PATCH /api/v1/projects/{project}/assets/{asset}/status
```

The implementation must reuse the existing AssetStatus transition model.

Inspect and reuse:

```text
AssetStatus
AssetStatus::allowedTransitions()
AssetPolicy
AssetService
AssetResource
AssetController
```

Do NOT introduce a parallel status system.

---

# 4. Asset Status Requirements

The implementation must determine the exact current state machine from the repository.

Do not invent transitions.

The API must:

* authenticate the user
* enforce project scoping
* enforce Asset authorization
* validate requested status
* validate that the transition is allowed
* reject invalid transitions
* return the updated asset resource
* expose the currently allowed transitions

The response should allow the frontend to understand:

```text
current status
allowed transitions
```

Do not expose internal storage paths.

---

# 5. Asset Status Frontend

Add the minimum UI necessary to change an Asset status.

The UI should:

* show current status
* show valid transitions
* allow only valid transitions
* show loading state
* show success/error state
* refresh the asset state after mutation

Do not redesign the Asset Library.

Do not introduce a new page.

Integrate with the existing Asset card/detail/modal workflow.

---

# 6. Important Asset Status Decision

Do NOT automatically change an asset to `available` merely because upload or metadata analysis succeeds.

The status transition must remain explicit unless repository inspection proves that the existing domain model defines automatic availability.

Preserve the existing state machine.

The objective is to expose the existing domain capability, not change its semantics.

---

# 7. P1 — SPA Deep-Link Support

Inspect the current Laravel + React SPA routing architecture.

Current observed problem:

```text
/dashboard → 404 on direct browser request
/projects/1 → 404 on direct browser request
```

Determine the correct existing SPA fallback mechanism.

The fix must allow valid frontend routes to be loaded directly or refreshed.

Do not create individual Laravel routes for every React route.

Do not interfere with:

```text
/api/*
```

API routes must continue to be handled by Laravel API routing.

The implementation must preserve normal Laravel/backend routes where applicable.

Verify at minimum:

```text
/dashboard
/projects/{id}
```

and any other currently registered frontend routes.

---

# 8. P2 — Unauthenticated API Response

Fix the current behavior where an unauthenticated API request without:

```text
Accept: application/json
```

can produce HTTP 500 because Laravel attempts to redirect to a non-existent `login` route.

Desired API behavior:

```text
Unauthenticated API request
→ HTTP 401
```

The fix must be implemented at the appropriate Laravel authentication/exception configuration layer.

Do not create a fake login route merely to suppress the error.

Do not alter frontend authentication behavior unnecessarily.

Verify both:

```text
Accept: application/json
```

and requests without that header.

Do not expose stack traces.

---

# 9. P2 — Development Documentation

Improve the project development documentation enough for a fresh developer to run the application.

Inspect the current README before modifying it.

Document:

* prerequisites
* PHP version
* MySQL requirement
* Node/npm requirement
* ffprobe requirement
* environment setup
* `.env` creation
* application key generation
* database configuration
* migrations
* seeding
* frontend build/dev command
* Laravel development server
* storage/private asset behavior
* development seed credentials

Clearly mark development-only credentials.

Do not expose production secrets.

Do not document test database credentials as production credentials.

If `README.md` is intentionally kept separate from project documentation, explain why and identify the correct documentation location.

---

# 10. Explicitly OUT OF SCOPE

Do NOT implement:

* Phase 5
* TTS
* subtitle generation
* audio generation
* FFmpeg rendering
* video rendering
* thumbnail generation
* publishing
* analytics
* production jobs
* timeline system
* asset auto-selection
* AI asset selection
* new Asset statuses unless required by the existing state machine
* automatic `available` status after upload
* redesign of Asset Library
* redesign of Visual Plan
* fake search improvements
* visual prompt generation improvements
* requirement-type redesign
* analyze-without-file behavior change

The following remain known limitations unless the repository proves they are already required:

```text
FakeSearchProvider returning zero discovery results
placeholder visual prompts
default "other" requirement type
analyze without file returning metadata failure
```

Do not silently expand the scope to fix them.

---

# 11. Security Requirements

Preserve:

* Sanctum authentication
* project isolation
* AssetPolicy authorization
* 404 behavior for cross-project resources where already established
* private asset storage
* no public storage URLs
* no `file_path` exposure
* no absolute filesystem paths
* no credentials in API responses

Asset status mutation must be project-scoped.

Test:

```text
Project A asset
→ Project B status mutation
→ must fail according to existing authorization/scoping contract
```

---

# 12. API Contract

If the existing API conventions support the proposed endpoint:

```text
PATCH /api/v1/projects/{project}/assets/{asset}/status
```

Request:

```json
{
  "status": "available"
}
```

Do not assume `"available"` is always the only valid target.

The implementation must derive valid transitions from the existing domain state machine.

Response should use the existing AssetResource or equivalent established resource contract.

The resource must expose:

```text
status
allowed_transitions
```

without exposing private storage information.

---

# 13. Testing Requirements

## Asset Status

Add tests for:

* authenticated success
* valid transition
* invalid transition
* invalid status value
* missing asset
* missing project
* cross-project asset
* unauthorized user
* allowed transitions in resource
* read-only fields cannot be modified
* no file path leakage

## SPA

Test or manually verify:

* `/dashboard`
* `/projects/{id}`
* browser refresh/direct navigation
* API routes remain unaffected

## Authentication

Test:

* unauthenticated API request with JSON Accept
* unauthenticated API request without JSON Accept
* expected 401
* no 500
* no stack trace

## Documentation

Verify documented setup commands actually work in a clean development environment where practical.

---

# 14. Regression Requirements

The existing test suite must remain green.

The implementation must not break:

```text
Phase 4 Part 6
Phase 4 Part 7
Phase 4 Part 8
Phase 4 Part 9
Phase 4 Part 10
```

In particular, verify the complete readiness chain still works:

```text
Asset Readiness
→ Production Manifest
→ Production Handoff
→ Production Specification
```

---

# 15. End-to-End Acceptance Test

After implementation, reproduce the previous smoke test.

The workflow must reach:

```text
Production Specification
ready = true
```

without manually modifying the database.

The Asset status must be changed through the actual application API/UI.

The test must demonstrate:

```text
Asset upload
→ Asset status transition through API/UI
→ Candidate association
→ Selection
→ Fulfillment
→ Asset Readiness
→ Manifest
→ Handoff
→ Production Specification
```

No database workaround is allowed.

---

# 16. Performance

Inspect for obvious N+1 queries introduced by the new status endpoint or frontend.

Do not introduce unnecessary repeated asset queries.

Use existing project-scoped relationships and policies.

Do not prematurely optimize unrelated code.

---

# 17. Mutation Checks

For Asset Status:

At minimum perform mutation checks for:

1. bypassing transition validation
2. bypassing project scoping
3. accepting an invalid status
4. bypassing authorization

Each mutation must cause an appropriate test to fail.

Restore the implementation exactly afterward.

Confirm byte-identical restoration.

---

# 18. Verification

After implementation, run:

```text
php artisan test --compact
npx tsc --noEmit
npm run build
vendor/bin/pint --dirty --format agent
```

Also run PHP syntax checks:

```text
php -l
```

Perform security/debug sweeps for:

* file_path
* storage paths
* Storage::url
* credentials
* debug output
* arbitrary shell execution

---

# 19. Documentation

Create:

```text
docs/PRE_PHASE_5_STABILIZATION_IMPLEMENTATION.md
```

Do NOT modify this specification:

```text
docs/PRE_PHASE_5_STABILIZATION.md
```

The implementation document must contain:

* scope
* files changed
* Asset Status design
* SPA fallback design
* API authentication fix
* documentation changes
* tests
* security checks
* mutation checks
* end-to-end acceptance result
* final verification
* commit hash

---

# 20. Commit

Implementation must use focused commit(s).

Prefer one commit if the changes remain cohesive:

```text
fix: stabilize pre-phase-5 workflow
```

Do NOT push.

The user will decide when to push.

---

# 21. Planning Stage Rules

The first agent invocation must be PLAN ONLY.

During planning:

* inspect repository
* inspect current AssetStatus state machine
* inspect AssetController
* inspect AssetResource
* inspect AssetPolicy
* inspect API routes
* inspect Laravel exception/auth configuration
* inspect React routing
* inspect README
* inspect existing tests
* determine exact files requiring changes

Do NOT modify files.

Do NOT create migrations.

Do NOT commit.

Do NOT push.

---

# 22. Required Planning Report

Return:

## 1. Repository Findings

## 2. Current Asset Status State Machine

List the actual statuses and allowed transitions found in code.

## 3. Proposed Asset Status API

Route, authorization, request, response, validation.

## 4. Proposed Frontend Change

Exact existing component to modify.

## 5. SPA Routing Fix

Explain the correct fallback architecture.

## 6. API Authentication Fix

Explain where the 401 behavior should be corrected.

## 7. Documentation Fix

Explain which documentation file will be updated.

## 8. Security Analysis

## 9. Test Plan

## 10. Mutation Plan

## 11. End-to-End Acceptance Plan

## 12. Exact Files To Change

## 13. Files That Must Remain Untouched

## 14. Open Questions

Only list genuine architectural questions.

## 15. Recommendation

End with exactly one:

```text
READY FOR IMPLEMENTATION
```

or:

```text
REQUIRES ARCHITECTURAL DECISION
```

Do not implement until the planning report has been reviewed and explicitly approved.
