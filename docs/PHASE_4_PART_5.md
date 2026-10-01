PHASE 4 — PART 5
Asset Preview & Technical Metadata
Context

We are building MochyFami Content Studio, an internal AI-assisted faceless YouTube Shorts production workstation.

The project is a Laravel + React + TypeScript application.

Current Phase 4 progress:

Part 1 — Asset Domain Foundation ✅
Part 2 — Asset Library / CRUD ✅
Part 3 — Asset ↔ AssetRequirement Association ✅
Part 4 — Asset File Upload & Storage ✅
Part 5 — Asset Preview & Technical Metadata ← CURRENT
Part 6 — Asset Fulfillment / Requirement Matching
etc.

Do NOT implement future parts.

PRIMARY GOAL

Extend the Asset system so that an uploaded asset can expose useful technical metadata and a safe preview representation.

The main purpose of this part is to allow the Asset Library and Visual Plan workflow to answer questions such as:

What kind of file is this?
What is its duration?
What are its dimensions?
What aspect ratio does it have?
Can the frontend display a preview?

This part should make the stored asset technically inspectable without yet introducing asset fulfillment or automatic requirement matching.

IMPORTANT SCOPE BOUNDARY
IN SCOPE
Technical metadata extraction
Image dimensions
Video dimensions
Video duration
Audio duration
Aspect ratio calculation
Safe preview capability
Private storage access through controlled application endpoints
Asset metadata API updates
Frontend preview for supported media
Metadata display
Re-analysis / refresh metadata
Tests
Documentation
OUT OF SCOPE

Do NOT implement:

Asset fulfillment
Automatic requirement matching
AI asset selection
AI vision
Pexels/Pixabay search
External downloading
yt-dlp
FFmpeg transcoding
Video editing
Video rendering
Thumbnail generation
Sprite extraction
Video frame extraction
Asset approval workflow
Asset quality scoring
Publishing
CDN
Public storage
S3 migration
Background queues unless absolutely required by the existing architecture
STEP 1 — INSPECT THE REPOSITORY FIRST

Before changing anything, inspect:

app/Models/Asset.php
app/Enums/AssetType.php
app/Enums/AssetStatus.php
app/Services/AssetService.php
app/Services/AssetFileStorageService.php
app/Http/Controllers/Api/V1/AssetController.php
app/Http/Resources/AssetResource.php
app/Http/Requests/Asset/*
database/migrations/*assets*
database/factories/AssetFactory.php
resources/js/types/index.ts
resources/js/services/assetService.ts
resources/js/components/*
resources/js/pages/_
routes/api.php
config/assets.php
config/filesystems.php
tests/Unit/_
tests/Feature/\*

Also search the repository for existing:

FFprobe
ffprobe
FFmpeg
getID3
Intervention Image
ImageMagick
Spatie Media Library
Symfony Mime
UploadedFile
Storage
streamDownload
response()->file()
BinaryFileResponse

Do not introduce a new media-processing library if an existing suitable implementation is already present.

STEP 2 — DETERMINE THE TECHNICAL METADATA SCHEMA

First inspect the current assets table.

Determine whether technical metadata columns already exist.

We need, where appropriate:

width
height
duration_seconds
aspect_ratio

Potential additional fields:

metadata_status
metadata_error
metadata_analyzed_at

Only add fields that are justified by the existing architecture.

Prefer a small explicit schema rather than storing arbitrary JSON unless the repository already uses JSON metadata for assets.

STEP 3 — MIGRATION

If technical metadata columns do not already exist, create one focused migration.

Possible fields:

width
height
duration_seconds
aspect_ratio
metadata_status
metadata_error
metadata_analyzed_at

Choose appropriate nullable types.

Important:

Dimensions

Use unsigned integer types where appropriate:

width
height
Duration

Use a numeric representation appropriate for media precision.

Do NOT unnecessarily reduce duration to an integer if the production workflow may benefit from sub-second precision.

For example:

decimal

may be appropriate.

Follow existing repository conventions.

Aspect Ratio

Prefer storing a normalized numeric representation if that fits the architecture.

For example:

1.7778

rather than relying exclusively on a string like:

16:9

The frontend can format the value later.

Do not introduce redundant representations without a clear reason.

STEP 4 — METADATA STATUS

Technical metadata extraction can fail.

Therefore, avoid treating:

metadata = null

as automatically meaning:

file is invalid

Introduce a small state model if the repository architecture supports it.

For example:

pending
processing
completed
failed

However:

do not introduce queues just for the sake of using queues
do not create a large state machine
keep the state model focused on technical metadata analysis

If the repository's current architecture strongly favors synchronous analysis, synchronous processing is acceptable for this part.

STEP 5 — MEDIA INSPECTION

Use an appropriate media inspection mechanism.

For video/audio, prefer FFprobe if the repository already has FFmpeg tooling or if FFprobe is available in the project environment.

Important:

FFprobe is for inspection only.

Do NOT use FFmpeg to transcode or modify the media.

Conceptually:

uploaded file
↓
FFprobe
↓
technical metadata

Expected information may include:

duration
width
height
codec
mime/container information

For Part 5, only persist the metadata actually required by the Asset domain.

Do not dump the entire FFprobe response into the database unless the existing architecture explicitly calls for it.

STEP 6 — IMAGE INSPECTION

For images, use the safest existing image inspection mechanism available in the repository.

Possible approaches include:

PHP image metadata functions
existing image library
ImageMagick if already available

Do not introduce a heavyweight dependency unnecessarily.

Extract:

width
height

Duration does not apply to images.

STEP 7 — AUDIO INSPECTION

For audio:

duration_seconds

is useful.

Width and height should remain:

null

Do not invent visual dimensions for audio.

STEP 8 — VIDEO INSPECTION

For video:

width
height
duration_seconds

should be extracted where available.

Aspect ratio should be derived from:

width / height

not from the filename.

Example:

1920 × 1080
→ aspect ratio ≈ 1.7778

Do not hard-code:

16:9

based only on the asset type.

STEP 9 — ASPECT RATIO

Create a deterministic aspect ratio calculation.

Example:

width = 1080
height = 1920

aspect_ratio = 0.5625

This will later be useful when comparing an Asset against:

AssetRequirement.aspect_ratio

But do not implement matching yet.

Part 5 only calculates and stores the technical value.

STEP 10 — SAFE METADATA EXTRACTION

Never execute a path supplied directly by the user as a shell command.

Do NOT build commands like:

ffprobe <client-provided-path>

without strict server-side path resolution.

The application should obtain the file path from:

Asset.file_path

and resolve it through the configured Laravel filesystem.

The client must not be able to provide:

../../../secret.file

as the inspection target.

STEP 11 — SERVER PATH VALIDATION

Before inspecting a file:

Resolve Asset through project scope.
Confirm Asset ownership/authorization.
Obtain file_path from the Asset record.
Resolve the file through the configured storage disk.
Confirm it exists.
Only then inspect it.

Never accept a raw arbitrary filesystem path from the API request.

STEP 12 — METADATA SERVICE

Create a focused service, for example:

AssetMetadataService

Responsibilities:

inspect(asset)
extract metadata
calculate aspect ratio
persist metadata
return updated asset

Do NOT put all extraction logic inside:

AssetController

Keep controllers thin.

STEP 13 — PROVIDER / INSPECTOR ABSTRACTION

If the repository architecture benefits from it, separate the inspection implementations:

AssetMetadataInspector

with implementations such as:

ImageMetadataInspector
VideoMetadataInspector
AudioMetadataInspector

Do not over-engineer this.

The abstraction is useful if it makes testing deterministic.

A good design could be:

AssetMetadataService
│
├── ImageInspector
├── VideoInspector
└── AudioInspector

The service decides which inspector to use from:

AssetType
STEP 14 — FAILURE HANDLING

Metadata extraction may fail.

Examples:

file missing
corrupted image
corrupted video
invalid audio
FFprobe unavailable
unsupported media format
malformed media stream

The API must not falsely report:

metadata completed

when extraction failed.

Use a deterministic failure state.

Store a safe error message if the schema supports it.

Do NOT expose:

server paths
shell commands
stack traces
environment details

to the frontend.

STEP 15 — MISSING FILE

If:

Asset.file_path != null

but the physical file no longer exists:

return an appropriate application error or metadata failure state.

Do not crash with an unhandled filesystem exception.

Do not silently erase the Asset metadata.

This situation should be observable.

STEP 16 — RE-ANALYZE ENDPOINT

Provide an explicit endpoint to calculate or refresh metadata.

Suggested:

POST /api/v1/projects/{project}/assets/{asset}/analyze

or an equivalent route consistent with the repository.

The operation should:

validate access
resolve asset
confirm file exists
inspect media
calculate metadata
persist metadata
return AssetResource

This endpoint is for technical inspection only.

It must NOT:

change AssetType
change AssetStatus
attach requirements
mark fulfillment
approve asset
select asset
modify the physical file
STEP 17 — UPLOAD INTEGRATION

After Part 4 upload, decide whether metadata analysis should happen automatically.

Preferred initial behavior:

Upload
↓
Store file
↓
Persist basic file metadata
↓
Analyze technical metadata

However, if synchronous media inspection makes uploads too fragile or slow, keep upload and analysis separate:

Upload
↓
File attached
↓
User triggers Analyze

Choose based on existing architecture and practical runtime constraints.

Do not introduce a queue just because it sounds architecturally cleaner.

Document the decision.

STEP 18 — REPLACEMENT BEHAVIOR

When a file is replaced:

Old technical metadata must NOT remain if it no longer describes the new file.

Example:

Old video:
1920x1080
30 sec

New video:
1080x1920
12 sec

After replacement, the Asset must not temporarily expose:

1080x1920
30 sec

with mismatched metadata.

Choose a safe lifecycle:

replace file
↓
invalidate old technical metadata
↓
analyze new file
↓
persist new metadata

If analysis is separate from upload, the metadata status should clearly indicate that analysis is pending/stale.

STEP 19 — DELETE BEHAVIOR

When Asset is deleted:

technical metadata disappears together with the Asset row.

Do not create separate metadata records requiring cleanup unless the existing architecture already uses such records.

STEP 20 — PREVIEW ACCESS

The files remain private.

Do NOT:

storage:link

Do NOT make the asset disk public.

Do NOT return the physical storage path.

Instead, implement a controlled preview endpoint if the repository and browser workflow support it.

Suggested:

GET /api/v1/projects/{project}/assets/{asset}/preview

The endpoint must:

Authenticate.
Resolve project.
Resolve Asset through project scope.
Authorize Asset access.
Confirm file exists.
Return the file through a controlled Laravel response.

For example:

response()->file(...)

or an equivalent safe response.

Do not expose the underlying storage path in JSON.

STEP 21 — PREVIEW SECURITY

The preview endpoint must NOT accept:

?path=
?file=
?filename=

as the source of the file.

The only source of truth is:

Asset.file_path

resolved server-side.

Test traversal attempts explicitly.

STEP 22 — MIME / RESPONSE HEADERS

Preview responses should use the stored/detected MIME type where appropriate.

Do not allow the client to choose:

Content-Type

for another file.

The server determines the content type.

Do not add dangerous inline HTML interpretation for arbitrary uploaded files.

Only preview supported media types.

For example:

image/_
video/_
audio/\*

Do not automatically render arbitrary:

text/html
application/javascript

as browser content.

STEP 23 — FRONTEND PREVIEW

Update the existing Asset Library UI.

When an Asset has an attached file:

Image

Display:

<img>

using the controlled preview endpoint.

Video

Display:

<video controls>

using the controlled preview endpoint.

Audio

Display:

<audio controls>

using the controlled preview endpoint.

Do not generate thumbnails.

Use the actual asset file through the controlled endpoint.

STEP 24 — NO PUBLIC URL STORAGE

Do NOT add:

public_url
url
cdn_url

to the Asset database.

The frontend can construct/use the application endpoint according to existing API conventions.

The physical storage path remains an internal implementation detail.

STEP 25 — TECHNICAL METADATA UI

Add a compact metadata section to the existing Asset UI.

Display applicable fields:

File
MIME
Size
Width
Height
Duration
Aspect Ratio
Metadata Status

Do not display irrelevant fields.

For example:

Image
1920 × 1080
16:9
Video
1080 × 1920
0.5625
12.4 seconds
Audio
32.1 seconds

Do not show:

Width: null
Height: null

to the user.

STEP 26 — FORMATTING

Create frontend formatting helpers where appropriate.

For example:

formatFileSize()
formatDuration()
formatAspectRatio()

Keep these deterministic and reusable.

Do not duplicate formatting logic across multiple components.

STEP 27 — TESTING STRATEGY

Do not rely on real external media binaries for every unit test.

Prefer deterministic test doubles for metadata inspectors.

For example:

FakeImageMetadataInspector
FakeVideoMetadataInspector
FakeAudioMetadataInspector

or mock the inspector abstraction.

Integration tests can separately verify the preview endpoint using Storage::fake().

STEP 28 — METADATA TESTS

Test at minimum:

Image
width
height
aspect_ratio
duration = null
Video
width
height
duration
aspect_ratio
Audio
duration
width = null
height = null
aspect_ratio = null
STEP 29 — FAILURE TESTS

Test:

missing file
corrupt file
unsupported media
inspector failure

Expected:

metadata status = failed

or the equivalent domain behavior.

Verify no fake metadata is persisted.

STEP 30 — REPLACEMENT REGRESSION TEST

Create an Asset with:

old technical metadata

Replace its file.

Verify old metadata does not survive incorrectly.

Example:

old:
1920x1080 / 30s

new:
1080x1920 / 10s

Final state must describe the new file.

STEP 31 — PREVIEW TESTS

Using:

Storage::fake(...)

test:

authenticated owner → preview succeeds
cross-project asset → 404
unauthorized user → existing authorization behavior
unauthenticated → 401
missing physical file → appropriate error

Also verify:

response does not expose physical storage path
STEP 32 — PREVIEW MIME SECURITY TESTS

Verify unsupported dangerous MIME types cannot be rendered through the preview endpoint.

At minimum test that arbitrary:

text/html
application/javascript

is not treated as a normal media preview.

Do not over-expand this into a complete malware scanner.

STEP 33 — PROJECT SCOPING REGRESSION

Maintain the same security invariant from Part 4:

Project A + Asset B

must not provide access to Asset B.

Prefer:

404

when that is the established scoped-resource convention.

Do not regress the Part 4 security fix.

STEP 34 — API CONTRACT

Keep:

AssetResource

as the canonical asset representation.

Extend it only if technical metadata and preview information genuinely belong in the existing resource contract.

Do not create multiple conflicting Asset JSON formats.

If adding a preview URL, ensure it is an application route and does not expose:

storage/app/private

or any physical path.

STEP 35 — PERFORMANCE

Do not repeatedly inspect media every time the Asset Library loads.

Metadata should be persisted.

The normal flow should be:

Asset Library
↓
read persisted metadata

not:

Asset Library
↓
run FFprobe for every asset

The explicit Analyze operation is what refreshes metadata.

STEP 36 — FRONTEND ERROR STATES

Handle:

metadata pending
metadata completed
metadata failed
file missing
preview unavailable

Do not make a failed preview break the entire Asset Library.

One broken asset should not prevent other assets from rendering.

STEP 37 — DOCUMENTATION

Create:

docs/PHASE_4_PART_5.md

Document:

Purpose

Technical metadata + controlled preview.

Metadata

Explain:

width
height
duration_seconds
aspect_ratio
metadata status
Analysis

Explain how technical metadata is extracted.

Preview

Explain:

private storage
authenticated endpoint
project-scoped authorization
Replacement

Explain metadata invalidation/re-analysis.

Security

Document:

project scoping
AssetPolicy
no public storage
no raw filesystem paths
no client-controlled preview path
MIME restrictions
Explicit limitations

Part 5 does NOT implement:

asset fulfillment
requirement matching
AI selection
thumbnail generation
FFmpeg rendering
transcoding
external downloading
asset approval
STEP 38 — VERIFICATION

Run:

php artisan test
npx tsc --noEmit
npm run build

Run Pint according to the repository convention.

Run a debug sweep:

dd(
dump(
ray(
var_dump(
print_r(
console.log(
TODO
FIXME

Do not modify unrelated pre-existing issues.

STEP 39 — MIGRATION VERIFICATION

If a migration is created:

Test:

migrate
rollback
migrate

and verify the resulting schema.

If no migration is needed, explicitly report:

Migration: not created

Do not claim migration verification when no migration exists.

STEP 40 — MUTATION CHECK

Perform targeted mutation checks for the most important invariants.

At minimum verify that intentionally removing:

project-scoped Asset lookup

causes the relevant cross-project tests to fail.

Also verify that bypassing:

AssetPolicy

causes authorization tests to fail.

Restore the correct implementation afterward.

STEP 41 — GIT CHECKPOINT

When all implementation and verification are complete:

git status

Confirm only intended files changed.

Create one focused commit:

feat: add asset preview and technical metadata

Do NOT push to GitHub.

Expected final state:

working tree clean
NOT pushed
REQUIRED COMPLETION REPORT

At the end report exactly this structure:

PHASE 4 PART 5 — COMPLETED

Implementation:

- ...
- ...
- ...

Technical Metadata:

- ...
- ...
- ...

Preview:

- ...
- ...

API:

- ...

Security:

- ...

Frontend:

- ...

Tests:

- X tests
- Y assertions
- all passing

Verification:

- tsc: PASS
- build: PASS
- Pint: PASS
- debug sweep: PASS

Migration:

- created / not created

Commit:

- <hash> feat: add asset preview and technical metadata

Git:

- working tree clean
- NOT pushed

Known limitations:

- ...

Do not claim completion unless the implementation and verification actually succeeded.

If something fails, report the exact failure and stop.

FINAL ARCHITECTURE AFTER PART 5

The intended flow becomes:

                 Content Project
                       │
             ┌─────────┴─────────┐
             │                   │
          Assets              Visual Plan
             │                   │
       ┌─────┴─────┐             │
       │           │             │

Metadata File Asset Requirement
│ │ │
│ Private Storage │
│ │ │
└───── Technical ─────────┘
Metadata

Asset lifecycle at this point:

Create Asset
↓
Upload File
↓
Analyze Technical Metadata
↓
Preview
↓
[Later Part]
Requirement Matching / Fulfillment

And keep this invariant:

Technical metadata
≠
Requirement fulfillment

A file being:

1080 × 1920
12 seconds

does not automatically mean it satisfies a:

9:16
10 seconds
cat close-up

Asset Requirement.

That belongs to Part 6.

STOP CONDITION

Stop after Phase 4 Part 5.

Do not continue automatically to Part 6.

Wait for the implementation/completion report before proceeding.
