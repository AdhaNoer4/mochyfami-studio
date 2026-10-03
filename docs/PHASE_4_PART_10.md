# Phase 4 — Part 10: Production Handoff Contract & Timeline Planning

## Status

**PLANNING SPEC ONLY — DO NOT IMPLEMENT**

This document defines the planning task for Phase 4 Part 10.

The coding agent MUST NOT implement, modify, migrate, refactor, commit, or push anything during the planning stage.

The purpose of this part is to inspect the existing Phase 4 architecture and determine the correct scope for the next production-oriented layer without duplicating or weakening the responsibilities already implemented in Parts 1–9.

---

# 1. Objective

Phase 4 Parts 1–9 have established the following production-readiness chain:

```text
Asset
  ↓
Asset Requirement
  ↓
Candidate Association
  ↓
Asset Upload
  ↓
Technical Metadata
  ↓
Compatibility
  ↓
Asset Fulfillment
  ↓
Asset Readiness
  ↓
Production Asset Manifest
  ↓
Production Handoff Readiness
```

Part 10 must determine the next architectural layer required after:

```text
Production Handoff Readiness
```

The goal is to establish a clear, deterministic, production-facing contract for the next stage of the content-production pipeline.

The system must remain:

- deterministic
- auditable
- project-scoped
- security-conscious
- provider-agnostic
- mutation-safe
- testable
- ready for future rendering/production work

Part 10 must NOT prematurely implement the actual video rendering pipeline.

---

# 2. Mandatory Repository Inspection

Before proposing any implementation, inspect the repository.

At minimum inspect:

## Roadmap

- `docs/Plan.md`
- all existing Phase 4 specifications
- all existing Phase 4 implementation documents

Especially inspect:

```text
docs/PHASE_4_PART_1.md
docs/PHASE_4_PART_2.md
docs/PHASE_4_PART_3.md
docs/PHASE_4_PART_4.md
docs/PHASE_4_PART_5.md
docs/PHASE_4_PART_6.md
docs/PHASE_4_PART_7.md
docs/PHASE_4_PART_8.md
docs/PHASE_4_PART_9.md
```

If some files do not exist, report that fact instead of assuming their contents.

Also inspect:

```text
docs/PHASE_4_PART_7_IMPLEMENTATION.md
docs/PHASE_4_PART_8_IMPLEMENTATION.md
docs/PHASE_4_PART_9_IMPLEMENTATION.md
```

if present.

---

# 3. Inspect Existing Production Authorities

Inspect the actual implementation of the existing production-readiness authorities.

At minimum inspect:

```text
AssetCompatibilityEvaluator
AssetRequirementProductionValidator
AssetFulfillmentQualityService
AssetProductionManifestService
AssetProductionHandoffService
```

Also inspect:

- AssetRequirement model
- Asset model
- VisualPlan model
- VisualPlanItem model
- ScriptVersion model
- relevant policies
- relevant resources
- relevant controllers
- relevant frontend services
- relevant frontend panels/cards
- existing routes
- relevant tests

Do not rely only on documentation.

The implementation currently present in the repository is authoritative.

---

# 4. Inspect Existing Data Model

Determine whether the current data model already contains concepts relevant to:

- production timeline
- visual item duration
- asset duration
- sequence ordering
- transitions
- scene timing
- clip start/end
- asset offsets
- source media ranges
- output duration
- render configuration
- production presets
- output format
- resolution
- frame rate
- aspect ratio
- audio requirements
- subtitle requirements

Do not create new fields merely because they might be useful in the future.

Only propose new persistence if the existing architecture cannot represent the required contract.

---

# 5. Determine Part 10 Scope

Using the repository, roadmap, and existing implementation, determine what Part 10 should actually contain.

The agent MUST answer:

### A. What is the next logical production-domain layer after Production Handoff Readiness?

### B. Is the next layer:

- a timeline/sequence contract,
- a production specification,
- a render preparation layer,
- a production job definition,
- a media assembly plan,
- or another concept already implied by the roadmap?

### C. Which existing services should remain authoritative?

### D. Which responsibilities must explicitly remain OUT of Part 10?

### E. Does Part 10 require database changes?

### F. Does Part 10 require a new domain model?

### G. Does Part 10 require new API endpoints?

### H. Does Part 10 require frontend UI?

### I. What future Part 11+ work would consume the Part 10 output?

The agent MUST NOT invent a new architecture merely because it appears convenient.

Follow the existing roadmap and domain boundaries.

---

# 6. Architectural Principle

Part 10 must build on existing authorities.

Do NOT duplicate:

- asset compatibility rules
- asset fulfillment rules
- production manifest validation
- production handoff validation
- project ownership checks
- script version ownership checks
- visual plan ownership checks

If a new layer needs information from an existing authority, compose/reuse that authority.

Do not copy its rules into another service.

---

# 7. Production Timeline Consideration

If repository inspection confirms that Part 10 is intended to introduce timeline/sequence planning, investigate the following concepts before implementation:

```text
VisualPlanItem
    ↓
Production Segment
    ↓
Selected/Fulfilled Asset
    ↓
Timeline Placement
```

Determine whether the system needs to represent:

- sequence/order
- start time
- duration
- source offset
- source duration
- target duration
- gap
- overlap
- transition
- asset-to-segment mapping

However:

**DO NOT automatically introduce all of these concepts.**

Only include concepts that are required by the roadmap and existing architecture.

---

# 8. Determinism Requirements

Any proposed Part 10 output must have deterministic ordering.

Avoid relying on:

- database default ordering
- insertion order
- relationship iteration order
- filesystem order
- random identifiers for presentation ordering

If multiple records can have the same ordering value, define a stable secondary key.

---

# 9. Security Requirements

The planning proposal MUST preserve the security model established in Parts 1–9.

Part 10 must NOT expose:

- private filesystem paths
- storage root paths
- internal absolute paths
- arbitrary shell commands
- arbitrary FFmpeg command strings
- raw credentials
- API keys
- signed URLs unless explicitly required by an existing contract

Do not use:

```php
Storage::url(...)
```

for private production assets unless the architecture explicitly requires a controlled temporary URL mechanism.

Do not expose:

```text
file_path
storage_path
absolute_path
private_path
```

through public API resources.

All project-scoped data must remain properly authorized.

---

# 10. No Premature Rendering

Part 10 MUST NOT implement actual media rendering unless repository inspection proves that the existing roadmap explicitly defines Part 10 as the rendering implementation stage.

Do not introduce:

- FFmpeg execution
- arbitrary process execution
- transcoding
- video encoding
- audio mixing
- subtitle rendering
- thumbnail rendering
- publishing
- YouTube upload
- background rendering workers

unless explicitly required by the existing roadmap.

If rendering is a future concern, Part 10 should define a clean contract that future rendering code can consume.

---

# 11. No Provider Coupling

Do not couple Part 10 to:

- FFmpeg-specific command syntax
- a specific TTS provider
- a specific AI provider
- a specific storage provider
- YouTube API
- external rendering SaaS

unless the roadmap explicitly requires it.

Prefer domain-level contracts.

Example:

```text
ProductionSpecification
```

rather than:

```text
FFmpegCommandBuilder
```

if the purpose is still production planning.

---

# 12. Readiness vs Production Execution

Maintain the distinction between:

```text
Planning Readiness
Asset Readiness
Production Manifest
Production Handoff Readiness
Production Specification
Production Execution
```

These are different responsibilities.

Do not make:

```text
ready === rendered
```

or:

```text
handoff_ready === render_complete
```

A production-ready project does not imply that rendering has happened.

---

# 13. Mutation Safety

Determine whether Part 10 should be:

### Read-only projection

or

### Persistent production planning state

or

### Explicit production configuration

If mutation is required, identify:

- why persistence is necessary
- who owns the state
- allowed transitions
- authorization requirements
- idempotency requirements
- auditability
- concurrency concerns

Do not introduce mutation merely for convenience.

---

# 14. API Design

If Part 10 requires an API, define:

```text
HTTP method
route
authorization
request payload
response payload
error responses
```

The API should remain scoped under:

```text
/api/v1/projects/{project}/...
```

and should preserve the existing project/script-version/visual-plan hierarchy where appropriate.

Do not create global production endpoints if the existing domain is project-scoped.

---

# 15. Frontend Design

If Part 10 requires frontend work, determine:

- where the UI belongs
- which existing panel should consume the data
- whether it should be read-only or editable
- loading state
- empty state
- blocked state
- ready state
- error state

Do not create a separate page if an existing Visual Plan / Production workflow already owns the information.

Do not redesign unrelated UI.

---

# 16. Testing Plan

The planning report MUST define tests for:

## Domain/service tests

- valid production input
- missing production input
- invalid references
- cross-project references
- deleted assets
- unavailable assets
- incompatible assets
- deterministic ordering
- empty input
- duplicate records
- boundary duration values where applicable

## API tests

If an endpoint is introduced:

- authorization
- project scoping
- successful response
- validation errors
- not-found behavior
- cross-project access
- deterministic response ordering

## Regression

All existing Phase 4 tests must remain green.

---

# 17. Mutation Testing

If Part 10 introduces critical readiness/production rules, the implementation plan MUST include targeted mutation checks.

Examples:

- remove a required production entry
- change an ownership check
- bypass a readiness condition
- alter deterministic ordering
- remove an important validation rule

The corresponding test must fail.

After each mutation:

```text
mutate
→ run targeted test
→ confirm failure
→ restore exact implementation
```

The final repository must be byte-identical to the intended implementation after restoration.

---

# 18. Performance / N+1

Inspect whether the proposed Part 10 output could introduce:

- N+1 relationship queries
- repeated service evaluation
- repeated compatibility checks
- repeated database lookups
- unnecessary frontend requests

Prefer:

- explicit eager loading
- batched queries
- reuse of already computed authoritative results
- deterministic service composition

Do not optimize prematurely, but identify obvious N+1 risks during planning.

---

# 19. Documentation

If Part 10 is implemented, require an implementation document:

```text
docs/PHASE_4_PART_10_IMPLEMENTATION.md
```

The document should contain:

- scope
- files changed
- architecture
- API changes
- frontend changes
- database changes
- tests
- security checks
- mutation checks
- verification results
- commit hash

Do not modify the original:

```text
docs/PHASE_4_PART_10.md
```

during implementation unless explicitly instructed.

---

# 20. Commit Rules

Implementation, if later approved, must produce:

```text
ONE focused commit
```

Suggested format:

```text
feat: <part 10 scope>
```

Do NOT push to remote.

The user will decide when to push.

---

# 21. Hard Stop Rules

During planning:

**DO NOT:**

- modify source code
- create migrations
- create models
- create controllers
- create routes
- create frontend components
- run destructive commands
- commit
- push
- change existing architecture
- silently fix unrelated issues

Planning only.

If an existing issue is discovered, report it separately.

---

# 22. Required Planning Report

After inspection, return a planning report with exactly these sections:

## 1. Repository Findings

What already exists and which files/classes are authoritative.

## 2. Roadmap Alignment

What the roadmap indicates Part 10 should accomplish.

## 3. Proposed Part 10 Scope

A precise definition of what should be implemented.

## 4. Explicit Non-Goals

What Part 10 must NOT implement.

## 5. Architecture

Proposed services/models/resources/controllers/routes/frontend components, only if actually needed.

## 6. Data Model

State clearly:

```text
Migration required: YES/NO
New model required: YES/NO
Existing model changes: YES/NO
```

Explain why.

## 7. API Contract

Only if an API is required.

## 8. Frontend Contract

Only if frontend changes are required.

## 9. Security Review

Explain project scoping, authorization, private asset handling, and path exposure.

## 10. Performance Review

Explain potential N+1/repeated evaluation concerns.

## 11. Test Plan

List unit, feature/API, regression, and mutation tests.

## 12. File Change List

List expected files to add/change/delete.

Do not make those changes yet.

## 13. Commit Plan

Exactly one future commit, but DO NOT create it yet.

## 14. Open Questions

List only questions that genuinely require user/architectural confirmation.

If there are no blockers:

```text
OPEN QUESTIONS: None
```

## 15. Recommendation

End with:

```text
RECOMMENDATION:
READY FOR IMPLEMENTATION
```

or:

```text
RECOMMENDATION:
REQUIRES ARCHITECTURAL DECISION
```

Do not implement anything until the planning report has been reviewed and explicitly approved.
