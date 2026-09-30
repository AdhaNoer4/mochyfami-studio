PHASE 4 — PART 4
Asset File Upload & Storage
Context

We are building MochyFami Content Studio, an internal AI-assisted faceless YouTube Shorts production workstation.

The project is a Laravel + React + TypeScript application.

Current Phase 4 progress:

Part 1 — Asset Domain Foundation ✅
Part 2 — Asset Library / CRUD ✅
Part 3 — Asset ↔ AssetRequirement Association ✅
Part 4 — Asset File Upload & Storage ← CURRENT
Part 5 — Asset Preview & Technical Metadata
Part 6 — Asset Fulfillment / Requirement Matching
etc.

Do NOT implement future parts.

The repository already contains:

Asset model
AssetType
AssetStatus
AssetPolicy
AssetService
AssetController
Asset CRUD API
Asset Library frontend
AssetRequirement ↔ Asset many-to-many relationship
project-scoped asset queries
existing file_path, file_name, mime_type, and file_size metadata fields if already present

The previous parts intentionally implemented metadata management only.

Part 4 introduces actual physical file storage.

PRIMARY GOAL

Implement a secure, project-scoped Asset File Upload & Storage workflow.

The user should be able to:

Select an existing Asset metadata record.
Upload a local file for that asset.
Store the file through Laravel's filesystem abstraction.
Generate a server-controlled storage path.
Update the Asset metadata with the stored file information.
Replace an existing asset file safely.
Delete the physical file when the Asset metadata record is hard-deleted.
Prevent path traversal and client-controlled storage paths.
Keep storage private/internal for now.

This is NOT an asset download system.

IMPORTANT SCOPE BOUNDARY
IN SCOPE
Multipart file upload
Laravel Storage abstraction
File validation
MIME/type validation
File size validation
Server-generated filenames
Project-scoped storage paths
Updating Asset metadata
Replacing existing files
Physical file cleanup
Storage failure cleanup
Authorization
Security tests
Frontend upload UI
Automated tests using Storage::fake()
Documentation
OUT OF SCOPE

Do NOT implement:

External URL downloading
Pexels API
Pixabay API
YouTube downloading
yt-dlp
FFmpeg
Video transcoding
Video thumbnail generation
Image thumbnail generation
Video duration extraction
Video dimension extraction
Audio duration extraction
Preview streaming
Public file URLs
CDN
S3 integration
Asset fulfillment
Automatic requirement matching
AI asset search
AI vision
Asset quality scoring
Asset approval workflow
Publishing
Background processing / queue unless already required by existing architecture

Keep Part 4 focused strictly on upload and storage.

STEP 1 — INSPECT THE REPOSITORY FIRST

Before modifying anything:

Inspect the existing implementation of:

app/Models/Asset.php
app/Enums/AssetType.php
app/Enums/AssetStatus.php
app/Policies/AssetPolicy.php
app/Services/AssetService.php
app/Http/Controllers/Api/V1/AssetController.php
app/Http/Requests/Asset/*
app/Http/Resources/AssetResource.php
database/migrations/*assets*
database/factories/AssetFactory.php
resources/js/types/index.ts
resources/js/services/assetService.ts
resources/js/components/*
resources/js/pages/\*
routes/api.php
config/filesystems.php
.env.example

Also inspect:

existing storage conventions
existing upload implementations elsewhere in the repository
existing tests involving filesystem/storage
existing authorization patterns
Laravel version and filesystem configuration

Do NOT assume the repository structure.

Follow existing project conventions wherever they are appropriate.

STEP 2 — CONFIRM CURRENT ASSET SCHEMA

Before writing migration code, determine whether the current Asset table already contains:

file_path
file_name
mime_type
file_size

If those fields already exist and are sufficient:

DO NOT create a migration just to recreate them.

Reuse the existing schema.

Only create a migration if a genuinely necessary schema change is discovered.

Do not duplicate columns.

STEP 3 — STORAGE ARCHITECTURE

Use Laravel's filesystem abstraction.

Do NOT directly manipulate files using:

file_put_contents()
fopen()
unlink()
move_uploaded_file()

unless an existing project abstraction specifically requires it.

Prefer:

Storage::disk(...)

or an injected filesystem abstraction consistent with the repository.

STEP 4 — STORAGE DISK

Inspect config/filesystems.php.

For the MVP, use an appropriate private/local filesystem disk.

Do NOT make uploaded assets publicly accessible by default.

Do NOT introduce:

php artisan storage:link

unless the existing application architecture explicitly requires it.

The goal is:

Application
↓
Laravel Storage abstraction
↓
Private local storage

Future S3/CDN/public delivery can be implemented later.

STEP 5 — SERVER-CONTROLLED STORAGE PATH

The client must NEVER control:

file_path
storage directory
absolute filesystem path

Do not accept a request such as:

{
"file_path": "../../../something"
}

and never use client-provided paths.

Generate the storage path on the server.

Recommended conceptual structure:

assets/{project_id}/{uuid}.{extension}

Example:

assets/17/550e8400-e29b-41d4-a716-446655440000.mp4

The exact directory structure may follow repository conventions, but it must:

be project-scoped
use a server-generated unique filename
never use raw user input as the storage path
avoid collisions
avoid path traversal

Use a UUID or another secure server-generated identifier.

STEP 6 — ORIGINAL FILE NAME

The user's original filename may be stored as metadata:

file_name

But it must NOT become the storage filename.

For example:

Original:
my funny cat video!!.mp4

Stored:
assets/17/<uuid>.mp4

Metadata:
file_name = "my funny cat video!!.mp4"

Sanitize or safely normalize the metadata value if necessary.

Do not allow the original filename to determine the directory.

STEP 7 — FILE VALIDATION

Create a dedicated upload request if that matches the repository architecture.

Example conceptual request:

UploadAssetFileRequest

Validation must include:

Required
file
File type

Validate the actual uploaded file using Laravel's file validation mechanisms.

Do NOT rely only on the filename extension.

The allowed types should correspond to the existing AssetType domain.

At minimum consider:

image
video
audio

Use a deterministic mapping between allowed MIME types and AssetType.

Do not silently accept arbitrary files.

STEP 8 — MIME TYPE

After validation, record the detected MIME type.

For example:

video/mp4
image/jpeg
image/png
audio/mpeg

Do not blindly trust:

$request->file->getClientMimeType()

if the repository provides a safer detected MIME mechanism.

Use Laravel/Symfony's uploaded-file metadata appropriately.

The stored metadata should represent the server-detected file type as reliably as practical for this stage.

STEP 9 — FILE SIZE

Record the actual uploaded file size.

Example:

file_size = 12345678

Do not trust a client-provided file size field.

The client must not be allowed to submit:

{
"file_size": 1
}

and have that value stored.

The server must derive file size from the uploaded file.

STEP 10 — FILE SIZE LIMIT

Define a reasonable configurable maximum upload size.

Do not hard-code the same magic number throughout the codebase.

Prefer configuration such as:

config/assets.php

if appropriate.

Example conceptual configuration:

'upload' => [
'max_size_kb' => ...,
],

Choose a sensible initial value based on the project's Shorts asset use case and Laravel/PHP upload constraints.

Also inspect:

upload_max_filesize
post_max_size

and document that application validation cannot exceed the effective PHP/server request limits.

Do not modify server configuration automatically as part of this task.

STEP 11 — ASSET TYPE COMPATIBILITY

An Asset already has a domain type.

For example:

image
video
audio

Uploading a file should not silently change the Asset's declared type.

Define deterministic compatibility rules.

For example:

AssetType::Image
→ image/\*

AssetType::Video
→ video/\*

AssetType::Audio
→ audio/\*

If the uploaded file is incompatible:

Return a validation error.

Do not automatically mutate:

asset.type

based on the uploaded file.

STEP 12 — UPLOAD ENDPOINT

Add a dedicated endpoint rather than mixing binary upload logic into metadata CRUD if that matches the current architecture.

Suggested shape:

POST /api/v1/projects/{project}/assets/{asset}/file

Use:

multipart/form-data

with:

file

The endpoint should:

Authenticate user.
Resolve the project.
Resolve the asset within the project.
Authorize access.
Validate the uploaded file.
Store the file.
Update Asset metadata.
Clean up correctly if anything fails.
Return the updated Asset resource.
STEP 13 — PROJECT SCOPING

Never resolve the asset globally.

Do NOT do:

Asset::findOrFail($assetId)

for this workflow if that bypasses project scoping.

The operation must conceptually enforce:

Project
↓
Asset belonging to Project

Cross-project asset IDs should return the same appropriate not-found behavior already used by the existing Asset API.

Preserve existing security conventions.

STEP 14 — AUTHORIZATION

Use the existing AssetPolicy.

Do not create a second unrelated authorization system.

Inspect the existing policy before modifying it.

Important:

AssetPolicy currently contains ownership enforcement through created_by.

Preserve that behavior.

Do not weaken authorization to make uploads work.

Do not introduce broad true authorization rules.

Also test:

unauthenticated user
unauthorized user
authorized owner
cross-project asset
STEP 15 — INITIAL UPLOAD

For an Asset with no existing file:

Asset metadata
↓
Upload
↓
Physical file stored
↓
Asset updated

Update:

file_path
file_name
mime_type
file_size

Only fields that already exist in the schema should be updated.

Do not introduce technical metadata such as:

duration
width
height
codec
fps
bitrate

Those belong to a later technical metadata phase.

STEP 16 — REPLACEMENT UPLOAD

If an Asset already has a file:

old file
↓
replacement upload

must be handled safely.

Do NOT delete the old file before the new file has been successfully stored.

Safe conceptual order:

1. Validate new file
2. Store new file
3. Update database metadata
4. Delete old physical file

If step 2 fails:

old file remains intact
database remains unchanged

If database update fails after step 2:

delete newly stored file
old file remains intact
database remains unchanged

Do not leave orphaned replacement files.

STEP 17 — DATABASE FAILURE CLEANUP

This is important.

The upload workflow spans two systems:

Filesystem
Database

They are not automatically transactional together.

Implement explicit cleanup logic.

Conceptually:

$newPath = storeNewFile();

try {
updateAssetMetadata();

    deleteOldFile();

} catch (\Throwable $e) {
deleteNewFile();

    throw $e;

}

Adapt the implementation to existing service architecture.

Do not leave an orphaned file when the database update fails.

STEP 18 — ASSET DELETE CLEANUP

Existing Asset deletion currently removes metadata.

Part 4 must ensure that deleting an Asset also cleans up its physical file.

Expected behavior:

DELETE Asset
↓
Delete physical file if present
↓
Delete Asset database record

Consider failure ordering carefully.

Do not leave database metadata pointing to a file that no longer exists.

Do not accidentally delete a file belonging to another asset.

Only delete the path associated with the asset being deleted.

STEP 19 — PATH SAFETY

Explicitly protect against:

../
../../
absolute paths
Windows drive paths
UNC paths
null bytes

The safest architecture is to never derive the stored path from client input.

The final stored path should always be generated by the server.

Add regression tests demonstrating that malicious filename/path input cannot escape the asset storage directory.

Remember this repository runs on Windows during development, so test assumptions should not depend exclusively on POSIX path semantics.

STEP 20 — STORAGE FAILURES

Handle filesystem failures appropriately.

Examples:

disk unavailable
write failure
permission failure
unexpected filesystem exception

Do not report success if the file was not stored.

Do not update:

file_path

when storage failed.

Use appropriate application-level exception handling consistent with the repository.

Do not expose raw filesystem internals to the API response.

STEP 21 — FRONTEND

Update the existing Asset Library UI.

Do NOT redesign the entire Asset Library.

Add an upload action to the existing asset UI.

Possible flow:

Asset Card
↓
Upload File
↓
Native file picker
↓
Upload
↓
Success
↓
Refresh asset metadata

Use:

FormData

for the upload request.

Do not send the binary file as JSON.

STEP 22 — FRONTEND VALIDATION

Frontend validation can provide user feedback, but backend validation remains authoritative.

At minimum handle:

invalid file
wrong asset type
file too large
upload failed
network/server error
successful upload

Show useful error messages.

Do not expose internal storage paths to the user.

For example, do NOT display:

storage/app/private/assets/17/uuid.mp4

Instead show:

File uploaded successfully

and metadata such as:

filename
size
type

if useful.

STEP 23 — DO NOT ADD PREVIEW YET

Even after successful upload, do NOT add:

video player
audio player
image preview
thumbnail
streaming URL
download button

Those belong to later parts.

The UI only needs to indicate that a file exists and show metadata.

STEP 24 — TESTING

Add comprehensive automated tests.

Use:

Storage::fake(...)

Do NOT write tests that depend on the real filesystem.

Test group A — Upload success

Test:

image upload
video upload
audio upload

depending on the existing supported AssetTypes.

Verify:

HTTP success
Asset file_path populated
Asset file_name populated
Asset mime_type populated
Asset file_size populated
physical fake storage contains file
Test group B — Asset type mismatch

Examples:

Image Asset + video file
Video Asset + image file
Audio Asset + image file

Expected:

422
database unchanged
no stored file
Test group C — File size

Test:

valid size
too large

Expected:

422
no database mutation
no stored file
Test group D — Security

Test:

unauthenticated
unauthorized owner
cross-project asset

Expected behavior must follow the existing API conventions.

Also test malicious filename/path input.

Verify:

no traversal
no arbitrary path
no overwrite of unrelated asset
STEP 25 — REPLACEMENT TESTS

Test:

existing file

- new valid file

Verify:

new file exists
old file removed
database points to new path

Also test failure:

new storage fails

Verify:

old file remains
database unchanged

And:

database update fails

Verify:

new file cleaned
old file remains
database unchanged

Use appropriate test doubles/mocks to simulate failure rather than depending on real filesystem failures.

STEP 26 — DELETE CLEANUP TEST

Create:

Asset

- stored physical file

Delete the Asset.

Verify:

database record deleted
physical file deleted

Also test an Asset without a file:

delete asset

Expected:

no exception
database record deleted
STEP 27 — CROSS-PROJECT SECURITY REGRESSION

Create:

User A
Project A
Asset A

User A
Project B
Asset B

Attempt:

Project A + Asset B

Expected:

404

Also test an asset owned by another user if compatible with existing project ownership behavior.

The test must prove that upload cannot be used as a cross-project file write primitive.

STEP 28 — STORAGE PATH TEST

Verify generated paths look conceptually like:

assets/{project_id}/{server-generated-name}.{extension}

Do not assert an exact UUID.

Assert properties:

starts with expected asset directory
contains correct project scope
extension is appropriate
does not contain traversal
does not equal original filename
STEP 29 — API RESPONSE

Return the existing:

AssetResource

rather than inventing a second asset representation.

The response should reflect the updated metadata.

Do not expose:

absolute filesystem path
server root
storage internals

Only expose the existing API-safe representation.

STEP 30 — DOCUMENTATION

Create:

docs/PHASE_4_PART_4.md

Document:

Purpose

Asset file upload and storage.

API
POST /api/v1/projects/{project}/assets/{asset}/file
Request
multipart/form-data
file=<binary>
Storage architecture

Explain:

private Laravel filesystem
server-generated path
project-scoped directory
Security

Explain:

authorization
project scoping
MIME validation
file size validation
server-generated paths
traversal protection
replacement cleanup
deletion cleanup
Explicit limitations

Document that Part 4 does NOT implement:

download
streaming
preview
thumbnail
duration extraction
dimensions
FFmpeg
external source downloading
asset fulfillment
STEP 31 — VERIFICATION

Run the appropriate checks used by the repository.

At minimum:

php artisan test
npx tsc --noEmit
npm run build

Run Pint according to the repository's existing convention.

Also perform a debug/security sweep for:

dd(
dump(
ray(
var_dump(
print_r(
console.log(
TODO
FIXME

Do not modify unrelated existing code merely to clean unrelated technical debt.

STEP 32 — REGRESSION CHECK

Before declaring completion, verify existing Phase 4 behavior still works:

Part 1

Asset CRUD remains functional.

Part 2

Asset search/filter/sort/pagination remains functional.

Part 3

Asset ↔ AssetRequirement association remains functional.

Especially verify that adding upload functionality did not accidentally:

change AssetType
change AssetStatus
break project scoping
break requirement associations
expose file paths
change existing API response contracts
STEP 33 — NO OPPORTUNISTIC SECURITY REFACTOR

There are known broader authorization limitations in the repository involving some policies outside AssetPolicy.

Do NOT use Part 4 as an excuse to redesign:

ProjectPolicy
VisualPlanPolicy
AssetRequirementPolicy

unless the upload workflow genuinely requires a minimal change.

Keep unrelated security hardening as a separate task.

The important requirement for this part is that Asset upload must respect the existing AssetPolicy and project scoping.

STEP 34 — GIT CHECKPOINT

When everything is complete:

Run:

git status

Confirm only intended files changed.

Create one focused commit:

feat: add asset file upload and storage

Do NOT push to GitHub.

The repository should end this task with:

working tree clean
REQUIRED COMPLETION REPORT

At the end, report:

PHASE 4 PART 4 — COMPLETED

Implementation:

- ...
- ...
- ...

API:

- ...

Storage:

- ...

Security:

- ...

Tests:

- X tests
- Y assertions
- all passing

Frontend:

- ...

Verification:

- tsc: PASS
- build: PASS
- Pint: PASS
- debug sweep: PASS

Migration:

- created / not created

Commit:

- <hash> feat: add asset file upload and storage

Git:

- working tree clean
- NOT pushed

Known limitations:

- ...

Do not claim completion unless the implementation and verification actually succeeded.

If something fails, report the exact failure and stop rather than hiding it.

FINAL ARCHITECTURE AFTER PART 4

The intended flow should now be:

Content Project
│
├── Assets
│ │
│ ├── Asset metadata
│ │
│ └── Physical file
│
└── Visual Plan
│
└── Asset Requirements
│
└── Candidate Assets

And importantly:

Asset Requirement
≠
Asset Fulfillment

An uploaded file is merely an actual stored asset.

It is not automatically considered fulfilled, approved, selected, or production-ready.

Those semantics belong to later Phase 4 parts.

Checkpoint

Stop after Part 4.

Do not continue automatically to Part 5.

Wait for the user to review the implementation and provide the completion result.

COMPLETION REPORT — PHASE 4 PART 4
==================================================

1. SUMMARY

An asset can now carry a real file. A new dedicated endpoint uploads one file
for an existing asset, the bytes land on a private disk under a server-chosen
path, and the asset's existing file_name, mime_type, and file_size columns are
filled in from the upload itself. Replacing a file is ordered so that a
failure at any step leaves the previous file and the database exactly as they
were. Nothing here previews, plays, downloads, or serves the file, and nothing
here changes an asset's type, its status, or its requirement associations.

2. REPOSITORY INSPECTION FINDINGS

- The assets table already had file_path, file_name, mime_type, and file_size,
  so no migration was needed. They were write-only columns before this part:
  nothing in the application had ever put a value in them.
- AssetService::findAsset() already resolved an asset through
  $project->assets(), which is the scoping primitive the upload path reuses
  rather than a new lookup.
- AssetResource already documented file_path as intentionally absent. That
  decision was left exactly as it was, and needed no change at all.
- AssetPolicy is the only policy in this area that resolves ownership, so
  upload authorizes against it with the same 'update' ability the metadata
  PATCH already uses.
- The only existing multipart request in the repository is ideaService's CSV
  import, which sets 'Content-Type': 'multipart/form-data' on the axios
  instance that already defaults to JSON. The frontend service follows that
  pattern.
- AssetCard already exported formatFileSize, so the upload modal and the
  requirement rows reuse it rather than adding a second formatter.
- VisualPlanPanel was already 1403 lines and RequirementAssetsSection already
  existed as a separate component, so neither was grown here.
- There is no existing helper for breaking a filesystem write, and a real
  write failure cannot be provoked portably: on Windows the permission model
  does not behave the way it does on Linux. The failure paths are therefore
  driven through a mocked disk, with the real fake disk handed back to the
  test so the on-disk assertions are still made against files that exist.

3. NEW CONFIGURATION

config/assets.php holds every decision that would otherwise become a magic
number scattered through the code:

- disk: 'assets'
- upload.max_size_kb: 524288 (512 MB, the selected ceiling)
- upload.directory: 'assets'
- upload.mimes: an explicit MIME-to-extension map per asset type

upload.mimes is keyed by MIME type rather than by extension because the MIME
type is what the server detects from the contents. That is what makes the
stored extension trustworthy: a file named evil.mp4 whose bytes are a PNG is
detected as image/png and stored as a .png. The extension is read out of the
map, never from the request.

The file extension map lists audio/wav and audio/x-wav separately, because
finfo reports the same .wav file under one spelling or the other depending on
the platform, and a single spelling would reject a real file on half of them.

AssetType::Other maps to an empty list, meaning "nothing is accepted here"
rather than "anything is accepted". A permissive catch-all would have let a
.pdf or a .zip into a path that implies it is media this application can
reason about.

The 512 MB application ceiling sits below this machine's upload_max_filesize
and post_max_size, which are both 2G, so the application limit is the one
that actually applies. The config comment records that a request exceeding
post_max_size is discarded by PHP before Laravel sees it and would therefore
be reported as a missing file rather than an oversized one, and that no server
configuration is changed automatically.

4. NEW DISK

config/filesystems.php gained a dedicated 'assets' local disk rooted at
storage/app/private, with serve => false, throw => false, and report => false.

It is separate from the default 'local' disk even though the root is
identical, so that asset media has a named boundary of its own:
Storage::fake('assets') is unambiguous, a future S3 swap is one line, and this
disk has no url and cannot be served even by accident. Nothing sets it to
public visibility, and no storage:link covers its root.

throw => false is deliberate and is why store() checks the return value of
putFileAs() explicitly. Under the default throw => true a write failure would
surface as a raw Filesystem exception before the service could report it as
the domain failure it is.

5. FILES CREATED

- app/Exceptions/AssetFileStorageException.php
- app/Exceptions/InvalidAssetFileTypeException.php
- app/Http/Requests/Asset/UploadAssetFileRequest.php
- app/Services/AssetFileStorageService.php
- config/assets.php
- resources/js/components/assets/AssetUploadModal.tsx
- tests/Feature/AssetFileUploadApiTest.php
- tests/Unit/AssetFileTypeCompatibilityTest.php

6. FILES CHANGED

- app/Http/Controllers/Api/V1/AssetController.php: added uploadFile().
- app/Services/AssetService.php: takes AssetFileStorageService, and
  deleteAsset() now removes the row first and then the file best-effort.
- config/filesystems.php: the 'assets' disk.
- routes/api.php: the upload route.
- resources/js/services/assetService.ts: uploadFile() over FormData.
- resources/js/components/assets/AssetCard.tsx: upload/replace action, attached
  file indicator, and a required onUploadFile prop.
- resources/js/components/projects/AssetLibraryPanel.tsx: wires the modal,
  reloads after a successful upload, and its delete copy corrected.
- resources/js/components/assets/AssetDetailModal.tsx: File group and comment
  corrected, which had claimed no file exists behind the metadata.
- resources/js/components/assets/AssetFormModal.tsx: header copy corrected,
  which had claimed no file is ever uploaded.
- resources/js/components/assets/RequirementAssetsSection.tsx: copy corrected
  and an attached-file indicator added to candidate rows.
- tests/Feature/AssetApiTest.php: the aggregate authentication test now
  covers the upload route too, which it would otherwise have missed.

7. API

- POST /api/v1/projects/{project}/assets/{asset}/file
  multipart/form-data, single part named "file", returns 200 with an
  AssetResource.

The route is authenticated, project-scoped, and authorized with
Gate::authorize('update', $asset) through the existing AssetPolicy. 404 for an
asset outside the project, 403 for a project the caller does not own, 401
unauthenticated, 422 for an incompatible or oversized file, 500 for a storage
or database failure.

AssetResource is reused unchanged, so the response contract of every other
asset endpoint is untouched. A request sent without an Accept:
application/json header is redirected rather than answered with 401, because
that is how every route in this application behaves and it was not changed
here; the frontend always sends the header.

8. STORAGE

A stored path is always:

  assets/{content_project_id}/{uuid}.{extension}

No part of it comes from the request. The directory comes from
config('assets.directory') followed by the resolved project's id, and the
filename is a fresh UUID with an extension read from the MIME map. The
client's filename is kept only as the display name in file_name, after control
characters are stripped and it is truncated to 255 bytes on a UTF-8 boundary.

The service records only file_path, file_name, mime_type, and file_size. It
does not write duration, width, or height, and it never changes the asset's
type or status.

Replacement order:

1. Validate the new file.
2. Store the new file.
3. Update the database.
4. Delete the old file.

If step 2 fails, nothing has been written and the old file is untouched. If
step 3 fails, the new file is deleted and the old file and row are left
exactly as they were. The old file is only removed after the database has
committed the new path, so there is no window in which the row points at a
file that has already been deleted.

A cleanup that fails is reported rather than swallowed: it raises an
AssetFileStorageException, which deleteQuietly catches and logs, because a
failed cleanup is a real operational problem. Deleting a path outside the
configured asset directory is refused outright.

Asset deletion was changed to match the spec's ordering: the database row goes
first, then the file. If the file cannot be deleted the row still goes,
because leaving a row that references a file which may be orphaned forever is
worse than leaving an unreferenced file an operator can sweep. The response
does not claim the file was removed when it was not.

9. SECURITY

- The asset is resolved through the requested project's own relation, so
  another project's asset is unreachable rather than rejected afterwards, and
  it fails exactly like an id that does not exist: 404.
- Authorization goes through AssetPolicy, not through the permissive policies
  elsewhere in the repository. Those were left alone, as instructed.
- file_path is never returned. AssetResource omits it, and a test asserts that
  the stored path, the string "storage/app", and the project directory segment
  are all absent from the response body.
- The frontend never renders a storage path; the only server wording it
  surfaces is the API's own message.
- The extension of a stored file is derived from the detected MIME type, so a
  disguised file cannot be given a misleading extension.
- Filenames are sanitized of control characters and traversal segments, and
  bounded in length.
- Nothing is written before validation passes, and nothing is served back.
- Uploading does not alter requirement associations, type, or status; a test
  asserts the association row and both fields are untouched afterwards.

10. FRONTEND

- assetService.uploadFile() posts a FormData with one part, mirroring the
  existing CSV import service.
- New AssetUploadModal: native file picker, the accepted kind and the size
  ceiling, a client-side pre-check, a submit state, and error display for an
  empty file, a too-large file, a wrong type, and a server or network
  failure.
- The client's size ceiling and accept list mirror the server's config and are
  labelled in the code as mirrors. The server is the authority; the client
  check only avoids sending a file that is certain to be refused.
- AssetCard gained an Upload/Replace action, hidden for an Other asset
  because it could only ever be refused, and a "File attached" indicator.
- AssetLibraryPanel wires the modal, reloads the list after a successful
  upload rather than patching one row, and refreshes the detail panel from
  the server's own response.
- Copy in AssetDetailModal, AssetFormModal, RequirementAssetsSection, and the
  delete confirmation was corrected, because all four claimed that no file was
  ever stored. Leaving that copy in place would have made the new feature
  contradict the interface around it.
- No preview, player, thumbnail, download button, or streaming URL was added.

11. TESTING

tests/Unit/AssetFileTypeCompatibilityTest.php, 46 tests: the full MIME matrix
in both directions, the empty map for Other, the UUID path shape, the
directory being derived from the project, extension selection from the map, a
false putFileAs being treated as a failure, filename sanitizing of traversal
segments, control characters, and a 255-byte name on a UTF-8 boundary, and
delete refusing a path outside the asset directory.

tests/Feature/AssetFileUploadApiTest.php, 44 tests: success for image, video,
and audio; the mismatch cases in both directions and for Other; real PNG bytes
uploaded under a .mp4 name being stored as .png; an oversized file; a text file
rejected by content; a missing file; authentication; project ownership; an
asset of another project owned by the same user; an asset of another user's
project; hostile filenames; a successful replacement; a replacement whose
write fails leaving the old file and row; a database failure after a
successful write leaving no orphan; delete removing the file; a delete whose
storage fails still removing the row; the response never exposing the storage
path; and an upload leaving type, status, and requirement association
untouched.

Two bugs were found by these tests rather than by inspection, and both are
fixed. The request originally called File::max() statically, which is not a
static method, so every upload returned 500. The controller's catch block
named its exception classes without importing them, so an incompatible file
fell through to the generic 500 instead of the intended 422. Neither could
have been caught without the tests.

12. MUTATION-CHECK RESULT

Three mutations were applied, each reverted from a backup and re-verified.

(a) The project-scoped findAsset() was replaced with an unscoped
Asset::findOrFail(). Result: 2 of 44 tests failed, the two cross-project
tests. The second is the more interesting one: without scoping, an asset
belonging to another user returned 403 instead of 404, because the policy
found it and refused it. That answers "does this exist" to a user who has no
business asking, and only the scoping makes it a 404.

(b) The new-file cleanup on a database failure and the old-file deletion after
a successful replacement were both removed. Result: 2 of 44 tests failed, the
two replacement tests. The orphan check failed on the exact path left behind,
which is the failure mode this part exists to prevent.

(c) The stored extension was taken from the client's filename instead of the
MIME map. Result: 2 of 90 tests failed across the feature and unit files, one
in each, both asserting the extension the detected type implies.

All three mutations were caught by the test that names the behaviour. Nothing
from them remains, and the three files were re-verified at 136/136 afterwards.

13. FULL TEST RESULT

1104 tests, 3498 assertions, 1104 passing, 0 failing, 0 skipped.
(Part 3 closed at 1014 tests / 3233 assertions; this part adds 90 tests and
265 assertions.)

Regression check for the earlier parts, run explicitly: 339 tests / 839
assertions across AssetApiTest, AssetLibraryApiTest, AssetRequirementApiTest,
AssetTest, AssetRequirementTest, AssetFileUploadApiTest, AssetStatusTest,
AssetTypeTest, and AssetFileTypeCompatibilityTest, all passing. CRUD, search,
filter, sort, pagination, and the requirement association all still pass
unchanged, and AssetResource is not in the diff.

14. TYPESCRIPT RESULT

npx tsc --noEmit — clean, no errors.

15. BUILD RESULT

npm run build — succeeded in 2.95s. The chunk-size warning above 500 kB is
pre-existing and unrelated.

16. PINT RESULT

vendor/bin/pint --dirty --format agent — passed. One file was reformatted on
the first run (import order and brace placement in the new unit test) and a
second run reported passed with no changes.

17. SECURITY / DEBUG SWEEP

Scanned every changed and new file for dd, dump, ray, var_dump, print_r,
console.log, TODO, and FIXME: no hits. Also checked that file_path appears
nowhere in the API surface, that the assets disk has no url and serve is
false, that the pre-existing serve => true belongs to the untouched default
'local' disk, and that no DB::raw, whereRaw, or unscoped query was introduced.
The only file_put_contents in this part sits inside a test that writes real
PNG bytes to a temporary file, because a faked upload does not go through
finfo and the test needs genuine content-based detection to mean anything.

18. MIGRATION

Not created. Every column the upload writes already existed.

19. GIT COMMIT HASH

See the commit created for this part: feat: add asset file upload and storage.

20. GIT STATUS

Clean after commit.

21. KNOWN LIMITATIONS

- No preview, player, thumbnail, download, or streaming URL. The stored file
  is private and is not served, so there is no URL to render from. The UI
  shows metadata only, as instructed.
- No technical metadata is extracted. duration, width, and height remain
  whatever they were, and the upload does not populate them.
- An upload does not mark an asset fulfilled, approved, selected, or
  production-ready, and does not change AssetStatus or AssetRequirement
  status. An uploaded file is only a stored asset.
- An asset's type cannot be changed by uploading a different kind of file. The
  mismatch is refused, and the user must edit the type first.
- If a delete's file removal fails, the row is gone and an unreferenced file
  remains on disk. This is logged but not surfaced to the client, and there is
  no sweeper for orphaned files. Writing one needs a real reason to decide
  how unreferenced files should be treated, so it was left out of this part.
- The client mirrors the 512 MB ceiling in TypeScript because the endpoint
  does not publish the limit. If the config changes, the mirror has to change
  with it; the server stays correct either way, since it is the authority.
- A request larger than the server's post_max_size is discarded by PHP before
  Laravel sees it and is reported as a missing file rather than an oversized
  one. No server configuration was changed, as instructed.
- Only one file per asset, and one asset per request. There is no multi-file
  or batch upload.
- The size rule reads the file's contents, so a very large upload is
  inspected by the validator before it is written anywhere. This is not a
  streaming check.
- The pre-existing permissive-policy gap outside AssetPolicy is not fixed
  here.

22. PHASE 4 PART 5 HANDOFF

The next part starts from a real file behind the metadata and an Asset
Resource that still refuses to name it. The open decisions it inherits are all
about serving rather than storing: whether a file is ever made reachable, and
under what authorization, since everything here was built so that it does not
have to be. It can also assume the four file columns are now populated by a
real write rather than by hand, that a replacement is atomic from the
client's point of view, and that a delete is honest about a file it could not
remove.

==================================================
END COMPLETION REPORT
==================================================
