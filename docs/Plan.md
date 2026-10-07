You are now planning Phase 4 Part 9 only.

IMPORTANT:

- Do NOT modify files yet.
- Do NOT create migrations yet.
- Do NOT write implementation code yet.
- Do NOT commit or push anything.
- Inspect the existing repository and Phase 4 Parts 1–8 first.
- We are continuing from the completed Phase 4 Part 8 commit:
  7abc639 feat: add production asset manifest (part 8)

Read and inspect:

- docs/PHASE_4_PART_9.md
- docs/PHASE_4_PART_8_IMPLEMENTATION.md
- relevant Phase 4 Part 7/8 implementation
- AssetProductionManifestService
- AssetRequirementProductionValidator
- AssetFulfillmentQualityService
- AssetCompatibilityEvaluator
- AssetRequirementFulfillmentService
- AssetRequirement / Asset / VisualPlan / VisualPlanItem models
- relevant controllers/resources/routes/frontend services/components
- existing tests around asset readiness and production manifest

Your task in this step is ONLY to analyze and propose the implementation plan for Phase 4 Part 9.

Determine:

1. Exact purpose and scope of Part 9.
2. What Part 8 already provides and what Part 9 is expected to add.
3. Which existing services/components should be reused.
4. Whether a new service/collaborator is actually necessary.
5. Whether Part 9 should consume AssetProductionManifestService directly.
6. Whether any Part 7/8 refactoring is required.
7. Required backend files.
8. Required frontend files.
9. Required routes/API endpoints.
10. Required database migrations, if any.
11. Required tests and regression coverage.
12. Security/project-scoping considerations.
13. Potential N+1/query/performance concerns.
14. Any risk of duplicating validation logic.
15. Any risk of leaking private file paths or storage URLs.
16. Whether Part 9 introduces FFmpeg/rendering/transcoding or whether those belong to a later phase.
17. Deterministic ordering requirements.
18. Mutation/negative-test strategy.
19. Documentation changes required.

Architecture rules:

- Do not duplicate existing compatibility/fulfillment/production validation logic.
- AssetCompatibilityEvaluator remains the technical compatibility authority.
- AssetRequirementProductionValidator remains the shared structural validation authority.
- AssetProductionManifestService remains the production-facing projection authority unless the Part 9 specification gives a strong reason otherwise.
- Do not expose private file paths.
- Do not expose Storage::url() unless explicitly required by the specification and security has been verified.
- Preserve project scoping on every asset/resource lookup.
- Do not automatically select, fulfill, mutate, or repair assets unless explicitly required by the Part 9 specification.
- Do not introduce FFmpeg, FFprobe, rendering, transcoding, TTS, subtitles, thumbnail generation, or publishing unless explicitly required by Part 9.
- Prefer small, focused changes.
- Do not perform unrelated cleanup.
- Do not remove existing dead code unless Part 9 requires it.
- Do not change the Part 8 task/spec document.
- If implementation documentation is needed, create a separate implementation document.

Pay special attention to the boundary:

Visual Planning
→ Asset Requirements
→ Candidate Assets
→ Selected/Fulfilled Assets
→ Production Asset Manifest
→ Part 9

Do not assume that Part 9 should skip or collapse these boundaries.

At the end, provide:

A. Findings from repository inspection
B. Proposed architecture
C. Files to create
D. Files to modify
E. Files that must remain untouched
F. API changes
G. Database changes
H. Frontend changes
I. Test plan
J. Security/scoping plan
K. Potential risks
L. Exact implementation sequence
M. Whether any clarification is required before implementation

STOP after the planning report.

Do not implement anything.
