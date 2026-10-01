# PHASE 4 — PART 6: ASSET FULFILLMENT & REQUIREMENT MATCHING

## Project Context

You are working on MochyFami Content Studio, a Laravel + React + TypeScript application for managing faceless YouTube Shorts production.

Phase 4 progress:

- Part 1: Asset Domain Foundation — completed.
- Part 2: Asset Library & CRUD — completed.
- Part 3: Asset ↔ AssetRequirement Candidate Association — completed.
- Part 4: Asset File Upload & Private Storage — completed.
- Part 5: Asset Preview & Technical Metadata — completed.

Latest known commit: `b4d2a2a feat: add asset preview and technical metadata`.

The repository may contain additional commits after this point. Inspect the actual working tree and current implementation before making changes. Do not assume the commit history or implementation is unchanged.

## Main Objective

Implement a deterministic Asset Fulfillment & Requirement Matching workflow.

The application must distinguish between:

1. **Candidate** — an asset associated with an AssetRequirement.
2. **Selected** — a user explicitly chooses one candidate for the requirement.
3. **Fulfilled** — the selected asset satisfies the requirement's fulfillment rules.
4. **Approved** — not part of this task. Do not introduce asset approval functionality.

Candidate association does not imply selection. Selection does not automatically imply fulfillment.

This task must not implement AI matching, external asset searching, automatic downloads, video rendering, or automatic publication.

## Mandatory First Step: Inspect the Repository

Before coding, inspect:

- `AssetRequirement`, `Asset`, `VisualPlanItem`, `VisualPlan`, and `ContentProject` models.
- `AssetRequirementType`, `AssetRequirementStatus`, `AssetRequirementAspectRatio`, `AssetType`, `AssetStatus`, and `AssetMetadataStatus`.
- Existing asset-requirement association routes, controllers, services, requests, resources, and policies.
- `AssetService`, `AssetMetadataService`, `VisualPlanQualityService`, and existing quality-gate tests.
- Existing frontend `RequirementAssetsSection`, `VisualPlanPanel`, asset services, and related types.
- Current migrations and the database conventions used by this repository.
- Existing tests for cross-project isolation, authorization, private file storage, and requirement status transitions.

Read the actual enum cases and transition graph. Do not invent enum values or assume behavior from documentation alone.

Write a short implementation plan before modifying files. Then implement the smallest coherent solution that fits the existing architecture.

## Scope A — Deterministic Compatibility Evaluation

Introduce a dedicated, provider-independent service that evaluates an already-associated candidate asset against an AssetRequirement.

Use a structured result with:

- `compatible`
- `reasons` or structured issue codes
- `warnings`
- relevant comparison details, where available

The evaluation must be read-only. It must not update the asset, requirement, candidate association, file, metadata, or workflow status.

At minimum, evaluate:

1. **Project scope**
    - The asset must belong to the same content project as the requirement.
    - Cross-project assets must never be exposed through candidate results.

2. **File availability**
    - An asset without an attached file cannot fulfill a requirement.
    - Never expose internal storage paths.

3. **Media type**
    - Compare the requirement type against the asset type using existing enum semantics.
    - Inspect the current mapping before implementing the comparison.
    - Do not silently reinterpret unsupported media types as compatible.

4. **Aspect ratio**
    - If the requirement specifies an aspect ratio, compare it with measured asset metadata.
    - Use the existing aspect-ratio enum and documented project conventions.
    - Handle missing or failed metadata explicitly.
    - Avoid exact floating-point equality. Use a documented deterministic tolerance or canonical-ratio comparison.
    - If the requirement does not specify an aspect ratio, do not reject an asset solely for its ratio.

5. **Duration**
    - If a target duration is specified, compare it with available duration metadata.
    - A source longer than the target may be trimmed during later editing.
    - A source shorter than the target must not be treated as fully satisfying the duration requirement without an explicit existing rule allowing it.
    - Handle missing duration metadata explicitly.

6. **Metadata state**
    - Distinguish incompatible assets from assets whose compatibility cannot yet be confirmed because metadata is pending, failed, or missing.
    - Do not claim compatibility when required evidence is unavailable.
    - Return stable issue codes that the frontend can display without parsing human-readable messages.

Do not perform network requests, FFprobe execution, filesystem writes, or metadata analysis during compatibility evaluation.

Do not automatically search every project asset or attach candidates. Evaluate candidates that are already associated with the requirement.

## Scope B — Explicit Asset Selection

Introduce a persistent, explicit selection mechanism for a requirement.

Recommended design: a nullable `selected_asset_id` on `asset_requirements`, referencing `assets.id`. Confirm this design against the existing schema before implementation. If the repository has a better established pattern, document and use it instead.

Requirements:

- At most one selected asset per requirement.
- The selected asset must already be associated with that requirement as a candidate.
- The selected asset must belong to the same project as the requirement.
- Selection must be a deliberate user action.
- A missing candidate association must not be silently created during selection.
- Selection must not automatically mark the requirement fulfilled.
- Allow a user to clear the selection explicitly.
- Do not allow client mass assignment of protected ownership, project, or workflow fields.
- Use a transaction where necessary to keep selection changes consistent.
- Define foreign-key deletion behavior deliberately and document the consequences.
- If the selected asset is deleted, the system must not silently claim that the requirement remains validly fulfilled. Use an explicit, testable integrity rule.

Use the existing service/controller/request/resource conventions. Avoid duplicate implementations of project scoping.

## Scope C — Fulfillment Workflow

Inspect the existing `AssetRequirementStatus` transition graph before designing endpoints.

Implement an explicit fulfillment action that is separate from candidate association and selection.

A requirement may become fulfilled only if:

- It has a selected asset.
- The selected asset is still associated with the requirement.
- The selected asset belongs to the same project.
- The asset has a file attached.
- The deterministic compatibility evaluator confirms that all mandatory constraints are satisfied.
- No required technical metadata is missing or unverified.

If any condition fails:

- Return a structured validation response, preferably HTTP 422 consistent with the existing API conventions.
- Include stable issue codes and useful field-level details.
- Do not partially update the requirement.

Do not bypass the existing status transition graph. If the existing graph does not permit a required transition, do not silently mutate the status or remove transition protections. Explain the conflict and implement the smallest justified domain change, with regression tests.

Do not add an asset approval workflow.

## Scope D — API Design

Follow the existing route naming and nesting conventions. Inspect current routes before adding new ones.

Add the minimum endpoints needed for:

- Reading candidate compatibility details.
- Selecting a candidate asset.
- Clearing the selected asset.
- Explicitly fulfilling a requirement.

Prefer extending the existing requirement-assets endpoints and resources rather than creating duplicate APIs.

Every endpoint must:

- Require authentication through the existing API middleware.
- Resolve the project, visual plan, script version, visual plan item, requirement, and asset through the existing ownership/scope chain.
- Return 404 for missing or cross-scope resources where that matches current conventions.
- Validate request data using Form Requests.
- Use existing resource and exception conventions.
- Avoid exposing `file_path`, private storage paths, internal filesystem details, or secrets.
- Avoid leaking candidates from another project, even when the authenticated user owns both projects.

Do not add global asset discovery endpoints or weaken project scoping.

## Scope E — Frontend

Extend the existing `RequirementAssetsSection` and related components.

The interface should clearly show:

- Candidate assets.
- Compatibility state and reason codes translated into readable Indonesian labels.
- Which asset is currently selected.
- A clear Select action.
- A Clear Selection action when an asset is selected.
- A Fulfill action only when appropriate.
- The current requirement status.
- Useful loading, validation-error, and empty states.

Use the existing asset list and preview functionality where possible.

Do not create a second independent candidate-search implementation. Do not add a global asset search endpoint.

The interface must never imply that an asset is fulfilled merely because it is attached or selected.

Do not implement asset approval, video rendering, or automatic matching.

## Scope F — Quality and Architecture

Keep the following responsibilities separate:

- Candidate association: identifies possible assets.
- Compatibility evaluator: read-only deterministic checks.
- Selection workflow: records the user's chosen candidate.
- Fulfillment workflow: validates the selected asset and transitions requirement status.
- Visual-plan quality gate: continues to represent planning readiness unless a specific, justified integration is required.

Do not redefine the existing `VisualPlanQualityService` so that its existing `ready` flag suddenly means an asset exists or has been fulfilled. If fulfillment readiness needs to be surfaced, expose it separately and document the distinction.

Do not add AI providers, external search providers, background jobs, rendering, thumbnails, or publishing functionality.

Avoid unrelated refactors and opportunistic policy changes outside this task.

## Scope G — Tests

Add comprehensive unit and feature tests.

### Compatibility tests

- Compatible asset with a file and valid required metadata.
- Asset without a file.
- Incompatible asset type.
- Matching and mismatching aspect ratios.
- Aspect-ratio tolerance boundaries.
- Asset duration equal to target.
- Asset duration greater than target.
- Asset duration shorter than target.
- Missing duration metadata.
- Pending metadata.
- Failed metadata.
- Requirement without aspect-ratio or duration constraints.
- Read-only evaluation does not mutate database records.

### Selection tests

- Select an associated, compatible candidate.
- Reject an asset that is not associated with the requirement.
- Reject a cross-project asset.
- Reject a missing asset.
- Clear a selection.
- Switching selection updates the selected asset consistently.
- Selection does not mark the requirement fulfilled.
- A deleted selected asset cannot remain silently valid.
- Unauthorized and unauthenticated requests follow existing conventions.

### Fulfillment tests

- Fulfill a valid selected asset.
- Reject fulfillment without a selection.
- Reject fulfillment without a file.
- Reject incompatible type.
- Reject mismatching aspect ratio.
- Reject insufficient duration.
- Reject missing required metadata.
- Reject a selected asset no longer associated with the requirement.
- Invalid fulfillment leaves requirement status unchanged.
- Invalid fulfillment leaves selection and candidate associations unchanged.
- Valid fulfillment respects the existing status transition graph.
- Repeated fulfillment behavior is explicit and tested.
- Cross-project and cross-scope isolation is preserved.

### Regression tests

- Existing asset upload and private preview tests still pass.
- Existing candidate attach/detach behavior still passes.
- Existing Visual Plan Quality Gate behavior remains unchanged unless an explicitly documented integration requires otherwise.
- No private file paths are exposed.
- No network calls, downloads, or filesystem analysis occur during compatibility evaluation.

Include migration up/rollback/up verification if a migration is introduced.

## Scope H — Verification

Run the checks appropriate to this repository, including:

- Focused new tests.
- Full PHPUnit test suite.
- `npx tsc --noEmit`
- `npm run build`
- Pint on changed PHP files.
- PHP syntax checks for changed PHP files.
- Migration forward/rollback/forward verification.
- Security/debug scan for accidental secrets, debug output, unsafe paths, or public file serving.

Do not claim that a command passed unless it was actually executed and its result was inspected.

Do not modify pre-existing Pint violations outside this task unless necessary and explicitly justified.

## Scope I — Documentation

Create:

`docs/PHASE_4_PART_6.md`

Document:

- Data model changes.
- Compatibility rules and tolerance.
- Stable issue codes.
- API endpoints.
- Selection versus fulfillment semantics.
- Authorization and project-scoping behavior.
- Test and verification results.
- Known limitations and deferred work.

## Git and Completion Report

Create one focused commit after all checks pass.

Suggested commit message:

`feat: add asset requirement fulfillment workflow`

Do not push to GitHub unless explicitly requested.

Before committing, inspect `git diff`, `git status`, and the staged changes. Do not include unrelated files.

At completion, report:

1. Summary of implementation.
2. Files created/modified.
3. API endpoints added or changed.
4. Tests and commands actually executed, including their results.
5. Migration verification results, if applicable.
6. Commit hash.
7. Working-tree status.
8. Known limitations.
9. Explicit confirmation that Phase 4 Part 7 has not started.

## Working Rules

- Inspect before modifying.
- Make small, verifiable changes.
- Preserve existing behavior.
- Do not invent successful test results.
- Do not push.
- Stop after Part 6.
