# Pre-Phase 5 Stabilization — Implementation Record

Status: complete
Scope: stabilization only. No Phase 5 work, no redesign of Phase 1–4.

## 1. Summary

Four problems found by `docs/testing.md` made the studio hard to actually use, even
though the underlying features worked. All four are now fixed, with no migration, no
schema change and no change to any existing domain rule.

| # | Problem | Fix |
| --- | --- | --- |
| 1 | An asset could never leave `pending`, so nothing could ever be fulfilled without a direct database write | New `PATCH /api/v1/projects/{project}/assets/{asset}/status` endpoint |
| 2 | Deep links and refreshes on `/dashboard` or `/projects/1` returned 404 | SPA fallback in `routes/web.php` |
| 3 | An unauthenticated API request without `Accept: application/json` returned 500 with a stack trace | `redirectGuestsTo(fn () => null)` in `bootstrap/app.php` |
| 4 | No setup documentation | `README.md` rewritten |

## 2. Asset status endpoint

### Endpoint

```text
PATCH /api/v1/projects/{project}/assets/{asset}/status
```

Body: `{"status": "<one of the six AssetStatus values>"}`

Responses:

| Case | Status | Message |
| --- | --- | --- |
| Transition the state machine allows | 200 | `Asset status updated successfully.` |
| Value that is not an `AssetStatus` | 422 | validation error on `status` |
| `status` absent | 422 | validation error on `status` |
| Transition the state machine forbids | 422 | `Asset cannot transition from X to Y.` |
| Same status as current | 422 | `Asset is already in X status.` |
| Asset belongs to another project, or does not exist | 404 | `Asset not found for this project.` |
| Project does not exist | 404 | |
| Authenticated user does not own the project | 403 | |
| No token | 401 | `Unauthenticated.` |

### Why it is shaped this way

**It is a separate route, not a field on update.** `UpdateAssetRequest` has no
`status` rule, so `$request->validated()` can never carry a status and the metadata
endpoint still cannot move an asset. That separation is what keeps "status is a server
fact" true, and it is now covered by `test_update_cannot_change_the_status`.

**The state machine is re-derived per request.** `AssetService::transitionStatus()`
reads `AssetStatus::allowedTransitions()` through `canTransitionTo()` on every call and
throws `InvalidAssetStatusTransitionException` otherwise. Nothing about the client's
claim is trusted, so a hand-written request cannot skip a step.

**Same-status is a distinct refusal.** `canTransitionTo()` already returns false for
itself, but "you are already there" and "that step is not reachable from here" are
different messages, and `ProjectService::transitionProjectStatus` already draws that
distinction.

**No new statuses and no new transitions.** The graph in `AssetStatus` is used exactly
as it was written in Part 1.

**No automatic `available`.** Uploading a file or analysing it still does not change
status. A file being present is not the same fact as the asset being usable, and Part 6
already defines usable.

**`AssetPolicy` is unchanged.** The endpoint calls `Gate::authorize('update', $model)`,
the same ownership check `update`, `uploadFile` and `analyze` already use.
`ProjectPolicy::updateStatus` is a permissive `return true`; copying that shape into
`AssetPolicy` would have added an ability that refuses nobody.

**The route is unnamed.** The four sibling transition routes in `routes/api.php` —
`projects/{project}/status`, `research/status`, `visual-plan/status` and
`asset-requirements/{requirement}/status` — are all unnamed. Only `assets.preview` is
named, with a comment saying why: it is the one URL published inside a resource, so it
needs a single source. No resource publishes the status URL and the frontend service
builds its path like every other service method, so naming this one route would give the
group two conventions for no gain.

**422, not 400.** The request was well formed. It is the asset's current state that
makes the transition wrong, and the message names both states so a client can correct
itself.

### `allowed_transitions`

`AssetResource` publishes what the state machine permits from the current status:

```json
"allowed_transitions": [
  { "status": "processing", "label": "Processing" },
  { "status": "approved",   "label": "Approved"   },
  { "status": "rejected",   "label": "Rejected"   },
  { "status": "archived",   "label": "Archived"   }
]
```

Shape decisions, agreed before implementation:

- **`{status, label}` only.** `AssetStatus` has no `actionLabelFor()`, unlike
  `ContentProjectStatus` and `AssetRequirementStatus`. Adding one is presentation code
  with copy to maintain, so it was left out rather than invented.
- **No `destructive` flag.** `ProjectResource` and `AssetRequirementResource` publish
  one, but deciding which of `rejected` and `archived` are destructive is a domain
  judgement that does not exist anywhere yet.

The list is a hint about the affordance, not a permission: the server re-checks, so a
stale list can only cost a rejected click. `file_path` is still absent from the
resource.

## 3. Frontend

| File | Change |
| --- | --- |
| `resources/js/types/index.ts` | `AssetStatusTransition` added; `allowed_transitions?` added to `Asset` |
| `resources/js/services/assetService.ts` | `transitionStatus()` added |
| `resources/js/components/assets/AssetDetailModal.tsx` | "Lifecycle" section with one button per allowed transition; local `readErrorMessage` prefers the server's 422 message |
| `resources/js/components/projects/AssetLibraryPanel.tsx` | `handleStatusChanged` updates the open modal and reloads the list |

No new page and no redesign. `AssetCard` already rendered `status_label` and was not
touched.

The transition buttons live in the detail modal because it already owns the pattern
this needs: a mutation, a loading flag, an error banner and a refresh callback. The
server's message is shown rather than a generic string, because for a refused
transition the message is the part that tells the user which state the asset is in.

The list is reloaded after a status change rather than patched, because a status can
decide whether a row matches the current status filter. This matches what an upload
already does.

## 4. SPA fallback

`routes/web.php` returns the shell for any web path; React resolves the path.

Three things this had to get right, each of which was wrong in a first attempt:

1. **`Route::fallback()` is GET-only.** Laravel registers it under the GET verb, so an
   unmatched path called with any other method answers 405 with `Allow: GET`. For the
   API that is a false statement: a mistyped endpoint called with POST would claim a
   read is possible on a path that does not exist. The route is therefore written as
   `Route::any(...)->where(...)->fallback()`, which Laravel stores under every method.
2. **An unmatched web write must not become a page.** The handler answers 405 for any
   non-GET, non-HEAD web method.
3. **The API must stay JSON.** `abort(404)` for `api/*`.

Verified: `/`, `/dashboard`, `/projects/1`, `/ideas`, `/categories`, `/assets`,
`/login`, `/this-route-does-not-exist` and `/dashboard/` all return 200 with a
byte-identical shell; `/up` still answers for the health check; unmatched `/api/*`
returns 404 for GET, HEAD, POST, PUT, PATCH, DELETE and OPTIONS.

### HEAD on a web path was 405

The first version of the handler guarded with `$request->isMethod('GET')`, and a HEAD
request to `/dashboard` came back 405. Symfony had already matched the route for HEAD by
falling back to the GET verbs, so the handler was contradicting the routing decision that
had just accepted it, and a link checker or monitoring probe on a reachable page would
have been told it was not. HEAD now counts as a read.

This was found by reading the guard rather than by a failing test: the suite had 15 web
paths and none of them asked with HEAD, because nothing in the application issues one. The
test now compares the two answers directly instead of restating a number, so it fails if
either side changes.

### CSRF on the fallback

The fallback lives in the web middleware group and so inherits CSRF verification,
which answers 419 before the handler runs. That turned an unmatched API POST into
"Page Expired" instead of "Not Found". The route therefore excludes
`PreventRequestForgery`.

This is invisible to the test suite, and the reason is worth recording:
`PreventRequestForgery::runningUnitTests()` returns true under PHPUnit, so tests never
saw the 419. It was found only by running the server. The behaviour is asserted as
route configuration in `SpaFallbackTest` for that reason.

## 5. Unauthenticated API responses

The fix is one line: `$middleware->redirectGuestsTo(fn () => null)`.

`Authenticate::unauthenticated()` evaluates the redirect callback while it builds the
`AuthenticationException`, as an argument, before anything is thrown. The default
callback is `route('login')`, this application has no login route, and resolving it
threw `RouteNotFoundException`. So the failure happened before an exception existed and
no exception renderer could catch it.

With the redirect target removed the middleware throws the exception it was always
meant to throw, and the existing `shouldRenderJsonWhen` — which already covered
`api/*` and `expectsJson()` — renders it as 401.

**A `render()` handler was tried first and removed.** Mutation testing showed it was
never load-bearing: with `redirectGuestsTo` in place, deleting the renderer changed
nothing, because `shouldRenderJsonWhen` already does that work. Shipping both would
have been two ways to produce the same response.

Verified: `/api/v1/dashboard` and 16 other protected endpoints answer
`{"message":"Unauthenticated."}` with 401 both with and without an `Accept` header,
and for HEAD. No stack trace. `/api/v1/health` and `/api/v1/auth/login` stay reachable.

## 6. Documentation

`README.md` replaced the stock Laravel readme. It states PHP/Composer/MySQL/Node/FFprobe
requirements, the MySQL block `.env.example` does not set, `composer run setup` versus
the manual steps, how to run the app in development and built form, that
`ASSET_FFPROBE_BINARY` exists and that a missing FFprobe degrades metadata to `failed`
rather than breaking uploads, that asset files are private with no `storage:link`, and
that the suite empties `mochyfami_studio_testing` on every run.

`.env.example` was left alone. It still ships Laravel's SQLite default, and the README
says so rather than the file being changed outside this scope.

## 7. Test coverage

Three new files, 108 new tests. The existing `tests/Unit/AssetStatusTest.php` already
owns the full matrix of valid and rejected pairs, so these do not repeat it; they prove
this endpoint applies it.

`tests/Feature/AssetStatusWorkflowTest.php` (23 tests, 85 assertions) — a valid
transition persists and returns the new state; a forbidden transition is 422 and writes
nothing; same-status 422; five invalid values plus a missing `status`; 401; 403 for a
non-owner and for a project with no creator; 404 cross-project, unknown asset, unknown
project; `allowed_transitions` matches the graph for all six statuses and narrows after
a transition; a transition touches no metadata; no `file_path` in the response.

`tests/Feature/SpaFallbackTest.php` (45 tests, 109 assertions) — 15 web paths answer the
shell, and every one is byte-identical to `/`; unmatched API paths are 404 for seven
methods with and without `Accept`; `/up` is not shadowed; a web write is 405 and a web
HEAD is not; the forgery middleware is excluded from the fallback; the fallback answers
all seven methods.

`tests/Feature/UnauthenticatedApiResponseTest.php` (40 tests, 156 assertions) — 17
protected endpoints across GET, POST, PATCH and DELETE, with and without `Accept`, all
401 with the exact body; no debug output in the body; public endpoints stay reachable;
web paths are not answered with 401.

`tests/Feature/AssetApiTest.php` — `allowed_transitions` added to the documented
resource fields.

**One existing test was deleted: `test_there_is_no_status_transition_endpoint`.** It
asserted that `PATCH /assets/{id}/status` answers 404, which is precisely the contract
this work removes. Its coverage now lives in `AssetStatusWorkflowTest`.

Two more tests were deleted while reviewing this work against its own stated intent:

- `test_allows_every_transition_the_state_machine_permits`, which looped every allowed
  pair. `AssetStatusTest::test_can_transition_to_for_valid_pairs` already covers that
  matrix, and the file's own docblock says so; repeating it through HTTP added nothing
  but twenty fixtures.
- A case asserting 404 for a non-numeric asset id. The application answers 405, because
  a `whereNumber` requirement that fails makes Symfony treat the path as a verb
  mismatch. That is framework behaviour, not a project decision, so it is not this
  suite's to assert.

### A test that lied

`test_an_endpoint_without_a_token_answers_401_json_without_an_accept_header` originally
used `$this->json()`, which adds `Accept: application/json` — so it never tested the
case it was named for. It now uses `$this->call()`, and mutation 9 confirms the
difference is real.

## 8. Mutation testing

Each mutation was applied, the relevant tests run, and the file restored from a copy and
verified byte-identical with `sha256sum`.

| # | Mutation | Caught by |
| --- | --- | --- |
| 1 | Remove the `canTransitionTo` guard from `AssetService::transitionStatus` | forbidden-transition test (422 → 200) |
| 2 | Resolve the asset with `Asset::find()` instead of the project's relation | cross-project test (404 → 200) |
| 3 | Relax the status rule from `Enum` to `string` | invalid-value test (422 → 500) |
| 4 | Remove `Gate::authorize` from `transitionStatus` | both 403 tests (403 → 200) |
| 5 | Replace `allowed_transitions` with `[]` | 7 resource tests |
| 6 | Revert the fallback to GET-only `Route::fallback()` | 5 API-404-per-method tests (404 → 405) |
| 7 | Remove the `api/*` guard from the fallback | 10 tests (404 → 200 HTML shell) |
| 8 | Remove `redirectGuestsTo(fn () => null)` | 20 tests (401 → 500 with `Route [login] not defined`) |
| 9 | Remove the `AuthenticationException` renderer | **survived** — see below |
| 10 | Remove `->withoutMiddleware(PreventRequestForgery::class)` | the CSRF-exclusion test |
| 11 | Revert the fallback to GET-only (after the route-config tests existed) | the every-method test plus 5 more |
| 12 | Narrow the write guard back to GET-only, reinstating the HEAD 405 | the HEAD test |

Mutation 9 surviving is what showed the renderer was dead code, and it was deleted.
`redirectGuestsTo(fn () => null)` alone was then re-verified as mutation 9, and it is
caught by 20 tests.

Mutations 10 to 12 exist because the two route-configuration tests are the only way to
cover behaviour that the test suite structurally cannot reach: `PreventRequestForgery`
skips verification under PHPUnit, so the 419 that the fallback caused was invisible
until the server was running. Both mutations were caught, which confirms the assertions
are attached to the route and not to the request path. Mutation 12 covers a guard the
first suite could not see, for the same reason.

## 9. Verification

| Check | Result |
| --- | --- |
| Full suite | 1424 tests, 4503 assertions, all passing |
| Baseline before this work | 1317 tests, 4154 assertions |
| Net new tests | 107 (108 added, 1 obsolete assertion of the old contract removed) |
| `vendor/bin/pint --dirty` | clean |
| `npx tsc --noEmit` | clean |
| `npm run build` | succeeded |
| Mutations | 12 applied, 11 caught, 1 survived and produced dead code that was deleted |

### Live verification against a running server

Run against `http://127.0.0.1:8000` with the seeded account.

**Asset status.** `pending → available` on asset 4 returned 200 with the new status, the
new `allowed_transitions` and no `file_path`. `available → pending` returned 422 naming
both states. `available → available` returned 422. `{"status":"wat"}` returned 422 with a
validation error. A cross-project asset, an unknown asset and an unknown project each
returned 404. No token returned 401. `PATCH /assets/1` with a `status` in the body
changed the title and left the status alone.

**Full chain, no database writes.** `production-spec` for script version 2 of project 1
returned `ready: true`, with `asset-readiness` at 100, `asset-manifest` with 3 entries
and `production-handoff` ready. The asset used in the chain was moved to `available`
through the new endpoint, which is what the audit could not do.

**SPA deep links.** Every path above returned 200 with an identical shell; `/up`
returned 200; unmatched `/api/*` returned 404 for all seven methods.

**Unauthenticated API.** 401 with `{"message":"Unauthenticated."}` with and without
`Accept`, for GET and HEAD, with no stack trace.

**Unchanged.** Preview still requires a token (401 without, `200 video/mp4` with).

## 10. Security

- The asset is resolved through the project's own relation before authorization, so a
  cross-project id answers 404 rather than 403 and never confirms the asset exists.
- `Gate::authorize('update', $model)` runs before any write; the policy fails closed for
  a project with no creator.
- The transition graph is re-derived server-side on every request.
- `Enum` validation rejects unknown values before the controller.
- `file_path` and absolute paths remain unpublished; the preview route is untouched.
- The 401 body is exactly `{"message":"Unauthenticated."}` with no debug payload.
- No automatic status change on upload or analyse.
- Three queries per transition: one scoped select, one update, one re-read. No N+1.
- No secrets added. The README marks the seeded credentials as development-only and
  warns that the test database is emptied on every run.

## 11. Known limitations, unchanged

Accepted per the specification and deliberately not addressed:

- `FakeSearchProvider` returns zero results.
- Generated visual-plan prompts are placeholders that must be edited by hand.
- A generated requirement defaults to the `other` type.
- Analysing an asset with no file records `metadata_status: failed`.
- `.env.example` still points at SQLite.

## 12. Files

New (6):

```text
app/Exceptions/InvalidAssetStatusTransitionException.php
app/Http/Requests/Asset/UpdateAssetStatusRequest.php
tests/Feature/AssetStatusWorkflowTest.php
tests/Feature/SpaFallbackTest.php
tests/Feature/UnauthenticatedApiResponseTest.php
docs/PRE_PHASE_5_STABILIZATION_IMPLEMENTATION.md
```

Modified (12):

```text
README.md
app/Http/Controllers/Api/V1/AssetController.php
app/Http/Resources/AssetResource.php
app/Services/AssetService.php
bootstrap/app.php
resources/js/components/assets/AssetDetailModal.tsx
resources/js/components/projects/AssetLibraryPanel.tsx
resources/js/services/assetService.ts
resources/js/types/index.ts
routes/api.php
routes/web.php
tests/Feature/AssetApiTest.php
```

Untouched, as required: every migration and the schema, `app/Enums/AssetStatus.php`,
`app/Models/Asset.php`, `app/Policies/AssetPolicy.php`, all Part 6–10 services,
resources and tests, `config/assets.php`, `config/filesystems.php`, `.env`,
`.env.example`, `docs/pre_phase_5.md`, `docs/AI_AGENT_SPEC.md`, `docs/Plan.md`,
`resources/js/components/assets/AssetCard.tsx`.