# Phase 1–4 End-to-End Smoke Test

## Objective

Perform a real end-to-end smoke test of the current MochyFami Content Studio application before Phase 5 begins.

This is NOT a new implementation task.

The goal is to determine whether the application can actually be used through the complete Phase 1–4 workflow, not merely whether individual automated tests pass.

Do not modify source code.

Do not create migrations.

Do not modify environment files.

Do not commit.

Do not push.

Do not silently fix bugs.

---

# 1. Workflow to Test

Use one realistic test content:

**Title:**
"Kenapa kucing suka menggosokkan tubuhnya ke manusia?"

Follow this workflow as far as the current application supports:

```text
Login
→ Category
→ Content Idea
→ Project
→ Research
→ Research Claims / Sources
→ Fact Check / Quality Gate
→ Script
→ Script Quality
→ Script Version
→ Visual Plan
→ Asset Requirement
→ Asset Upload
→ Asset Metadata
→ Candidate Association
→ Asset Selection
→ Asset Fulfillment
→ Asset Readiness
→ Production Asset Manifest
→ Production Handoff
→ Production Specification
```

Use development/test data only.

---

# 2. Start the Application

Actually verify the development application can start.

Check:

* backend startup
* frontend startup
* database connection
* authentication
* API connectivity
* frontend API base URL
* storage configuration

Record any required environment variables.

Do not expose secret values.

---

# 3. Manual / Browser Workflow

Actually interact with the application UI wherever possible.

Do not merely inspect source code.

For every major step record:

* action performed
* expected result
* actual result
* PASS / FAIL
* screenshot/evidence if available
* blocker if applicable

---

# 4. Content Creation

Create the test category if necessary.

Create the test idea:

```text
Kenapa kucing suka menggosokkan tubuhnya ke manusia?
```

Convert the idea into a project.

Verify that:

* project appears in the project list
* project detail opens
* project state is correct
* project ID is correctly propagated to subsequent requests

---

# 5. Research

Execute the existing research workflow.

If external research providers are unavailable because API keys are not configured:

DO NOT fake successful external research.

Instead:

* determine whether the application supports manual/fake/test research data
* use only an existing supported development/test mechanism
* clearly report any external dependency

Verify:

* research exists
* claims exist
* sources exist
* claims can be fact-checked
* quality gate behaves correctly

---

# 6. Script

Create or generate a script using the existing supported workflow.

Verify:

* script version exists
* script content is visible
* quality gate works
* revision/version workflow works
* current version is correctly associated with the project

Do not configure external AI services merely to make the test pass.

If AI generation requires a missing API key, report it and continue with any supported manual/test workflow.

---

# 7. Visual Plan

Create a Visual Plan for the current script version.

Verify:

* visual plan opens
* visual plan items can be created
* item ordering works
* asset requirements can be generated/created
* script version relationship is correct

---

# 8. Asset Workflow

Use local test media.

Test at least:

* one image
* one video if supported by the current asset types

For the asset workflow verify:

```text
Asset creation
→ File upload
→ Metadata analysis
→ Candidate association
→ Compatibility
→ Selection
→ Fulfillment
```

Verify private asset behavior.

Do not expose private storage paths.

---

# 9. Production Readiness Chain

Run all current readiness layers:

```text
Planning Readiness
Asset Readiness
Production Asset Manifest
Production Handoff
Production Specification
```

Verify that they agree logically.

Specifically verify:

### Asset Readiness

Incorrect/unfulfilled assets should block readiness.

### Production Manifest

Only valid selected + fulfilled assets should appear.

### Production Handoff

Should compose the existing readiness authorities.

### Production Specification

Should:

* be read-only
* contain valid production segments
* preserve deterministic ordering
* include planned duration
* contain aggregate duration values
* contain no private file paths
* contain no storage URLs

---

# 10. Negative Test

Perform at least one intentional failure.

Example:

Remove/clear the selected asset or make one requirement unfulfilled.

Then verify:

```text
Asset Readiness = BLOCKED
Production Manifest = affected
Production Handoff = BLOCKED
Production Specification = reflects the blocked state
```

Restore the test data afterward.

---

# 11. Cross-Project Security Smoke Test

Use two projects if practical.

Verify that an asset or visual plan from Project A cannot be accessed through Project B's endpoints.

Expected behavior:

```text
404
```

or the existing project-scoped behavior defined by the application.

Do not weaken authorization to make the test pass.

---

# 12. UI Integration Check

Pay particular attention to:

* stale data after mutations
* missing refresh
* incorrect loading states
* empty states
* blocked states
* error states
* broken navigation
* incorrect project/script version context
* buttons that appear functional but fail API requests
* frontend TypeScript/runtime mismatches

---

# 13. Data Integrity

At the end of the test verify:

* no orphaned records caused by the workflow
* no unexpected duplicate records
* selected assets remain correctly associated
* project relationships remain correct
* script version remains correct
* visual plan remains bound to the correct script version
* asset requirements remain bound to the correct visual plan

---

# 14. Cleanup

Use only safe test-data cleanup if necessary.

Do not delete real user data.

Do not modify production data.

If cleanup cannot safely be performed, report the test records that remain.

---

# 15. Required Final Report

Return exactly:

## 1. Overall Result

One of:

```text
READY FOR PHASE 5
READY WITH LIMITATIONS
NOT READY
```

## 2. Environment

Report:

* backend
* frontend
* database
* storage
* required environment variables
* external services required

Never print secrets.

## 3. End-to-End Results

Table:

| Stage                    | Result | Evidence / Notes |
| ------------------------ | ------ | ---------------- |
| Login                    |        |                  |
| Category                 |        |                  |
| Idea                     |        |                  |
| Project                  |        |                  |
| Research                 |        |                  |
| Fact Check               |        |                  |
| Script                   |        |                  |
| Script Version           |        |                  |
| Visual Plan              |        |                  |
| Asset Requirement        |        |                  |
| Asset Upload             |        |                  |
| Asset Metadata           |        |                  |
| Candidate Association    |        |                  |
| Asset Selection          |        |                  |
| Asset Fulfillment        |        |                  |
| Asset Readiness          |        |                  |
| Production Manifest      |        |                  |
| Production Handoff       |        |                  |
| Production Specification |        |                  |

## 4. Negative Test

Describe the intentional failure and whether the expected blocking behavior occurred.

## 5. Security Result

Report project isolation and private asset behavior.

## 6. Bugs / Blockers

Separate:

* Critical
* High
* Medium
* Low

Do not hide known issues.

## 7. External Dependencies

Clearly distinguish:

* required now
* required only for Phase 5
* required only for future rendering

## 8. Recommended Actions

If everything is usable:

```text
RECOMMENDATION:
Proceed to Phase 5 planning.
```

If something blocks real usage:

```text
RECOMMENDATION:
Fix blockers before Phase 5.
```

Do not implement fixes during this audit.
