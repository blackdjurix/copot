# M3.8 Media Library Preparation Contract

## Purpose and status

This document is the preparation contract for M3.8 Media Library. The
clarification brief adds no new roadmap capability; it strengthens and
clarifies the existing M3.8 direction already reserved in the M3 sequence.

Current preparation status:

```text
M3.7:
COMPLETE

M3.8 initial clarification:
COMPLETE

M3.8 preparation audit:
COMPLETE

M3.8 preparation contract:
AUTHORED

M3.8 preparation documentation:
LOCALLY VALIDATED

Implementation:
WU2 IMPLEMENTED, VALIDATED, AND DURABLY DELIVERED

WU3:
IMPLEMENTATION AND FOCUSED VALIDATION COMPLETE; DURABLY DELIVERED

WU4:
IMPLEMENTATION AND FOCUSED VALIDATION COMPLETE; DURABLY DELIVERED; CLOSED

WU5:
PRESENTATION REFINEMENT IMPLEMENTED; FOCUSED VALIDATION COMPLETE
AI ACCEPTANCE PASS
HUMAN VISUAL/BROWSER ACCEPTANCE PASS; FINALLY CLOSED

WU6:
ACCEPTED PREDECESSOR: IMPLEMENTATION, FOCUSED VALIDATION, AND HUMAN
FUNCTIONAL/BROWSER ACCEPTANCE COMPLETE FOR APPROVED CONTENT FEATURED-MEDIA
PICKER, USAGE SYNCHRONIZATION, UNUSED-ONLY DELETION SAFETY, CONSUMER-SCOPED
PREPARATION/CROP, AND SINGLE PUBLIC CONTENT-VIEW RENDERING

WU7:
IMPLEMENTED AND VALIDATED: PRE-M3.8 UPGRADE, FIVE-ACTION AUTHORIZATION,
PACKAGE INCLUSION, AND PACKAGED CLEAN-INSTALL PROVISIONING

Implementation branch:
`feature/m3.8-media-library` REMAINS ACTIVE; FULL M3.8 IS NOT COMPLETE
```

The branch is `feature/m3.8-media-library`. WU2 and WU3 are accepted
predecessors and are durably delivered. WU4 implementation and focused
validation are complete and durably delivered. WU5
presentation refinement is implemented and focused-validated on the active
feature branch. AI acceptance and human visual/browser acceptance are `PASS`;
WU5 is finally closed. WU6 delivers the approved Content
featured-Media picker, usage synchronization, unused-only deletion safety, the
consumer-requested `content.featured` 16:9 preparation/crop profile, and
processed featured-image rendering on the single public Content view. Focused
validation and human functional/browser acceptance are complete. WU7 adds
closure evidence without expanding Media production scope. Full M3.8 is not
complete.

## Locked architecture and ownership

Media Library is a first-party module. It owns its routes, permissions,
services, repositories, schema, storage lifecycle, Admin workspace, picker,
consumer contracts, usage visibility, and module tests. Webcore remains
maintenance-only and must not absorb Media-specific business logic, schema,
Admin UI, storage behavior, or workflow.

The existing `SiteAssetStorage` boundary remains branding-specific for the
fixed Logo and Favicon lifecycle. It is not generalized into a Media engine.
Its validation and filesystem-safety behavior may inform Media implementation,
but its Settings descriptors, fixed slots, stable branding URLs, and ownership
remain separate.

M3.8 provides a dedicated Admin Media workspace and reusable selection/picker
surface. The picker may select existing Media and upload/process an item
without leaving a consumer form. Upload and processing remain owned by Media;
Content, Theme, Settings, and other consumers must not create duplicate upload
pipelines.

The M3.8 implementation envelope is exactly seven work units. The separate
Admin Shell design-adjustment checkpoint covers Media management and selection
surfaces when implemented; it does not add an eighth domain work unit.

## Data, storage, and identity contract

The candidate schema topology is:

```text
media
media_variants
media_usages
```

The exact column and index design remains an implementation-level decision
within this topology, subject to the following contract:

- every managed item has a stable database Media identity;
- binary content is stored in Media-owned filesystem storage, never as a
  database BLOB;
- consumers store stable Media identity, not physical paths;
- original filename, display title, storage key, MIME type, and Media identity
  are distinct concerns;
- embedded file metadata is not the application source of truth;
- manually copied files are unmanaged until registered through Media;
- originals are preserved without revision history;
- generated outputs are represented by variant records or equivalent bounded
  descriptors and are not separate user-visible Media items;
- physical paths are never exposed to consumers;
- managed files remain outside the public document root unless a controlled
  delivery implementation explicitly mediates access.

Media storage must use generated opaque keys, path containment, symlink
guards, complete temporary writes before activation, controlled cleanup, and
sanitized diagnostics. Database and filesystem transitions must define
rollback, orphan detection, and recovery behavior explicitly.

## Supported formats and image behavior

The editable image baseline is JPEG, PNG, and WebP.

GIF and ICO are managed with format-specific restrictions and no assumption of
general processing. GIF processing must not silently destroy animation. ICO
handling must define accepted structures before implementation.

PDF is a managed document format. M3.8 includes upload, validation, listing,
metadata/title, picker selection, controlled view URL, controlled download URL,
usage visibility, and deletion safety. PDF generation, editing, form filling,
extraction, page previews, page thumbnails, and signatures are excluded.

SVG is excluded unless a separately justified security contract is approved.
Video and audio processing are excluded.

Supported image operations are crop, resize, rotate, and aspect-ratio
constraint. Responsive outputs may be requested by bounded width, aspect ratio,
format, quality, or variant key. Generated variants must not be unbounded or
automatically promoted to user-visible Media.

Synchronous bounded processing is the M3.8 baseline. Queue, worker, daemon,
retry-service, CDN, cloud storage, and background processing infrastructure
are excluded.

Image processing must define dimension and decompression limits, preserve the
original, normalize EXIF orientation for generated outputs, and strip sensitive
metadata from generated outputs. Advanced editing, Photoshop-like workflows,
filters, AI editing, and revision history are excluded.

## Delivery and consumer contracts

Consumers receive controlled Media descriptors and URLs. They do not receive
physical paths or implement storage, upload, validation, processing, or
deletion logic.

Delivery topology remains an implementation-level contract choice among
route-backed controlled delivery, controlled generated-public cache delivery,
or a hybrid of the two. The selected topology must define MIME handling,
content disposition, caching, authorization, missing/orphan behavior, path
containment, and URL stability.

Private Media is not part of the baseline; M3.8 does not promise private-media
authorization semantics.

M3.8 delivers reusable consumer contracts and the picker. Content featured
Media is the first active production consumer; Theme and Site Settings field
adoption remain post-M3.8 unless a later approved scope decision changes that
boundary. This preserves the roadmap direction that consumer integration follows
Media through explicit contracts.

The consumer contract must support, where requested, stable Media reference,
aspect ratio, minimum dimensions, bounded responsive widths, output format,
quality or variant key, `srcset`, `sizes`, `<picture>`, controlled `object-fit`,
and optional art-direction/focal-point data.

## Authorization and deletion

Media uses the existing Role and Permission system. Preparation locks action
boundaries based on:

```text
view
upload
use
edit
delete
```

Exact permission identifiers and display names must follow the existing
permission vocabulary and be provisioned through the normal module and
fresh-install/existing-install paths. No separate authorization, approval, or
media-review system is introduced.

Referenced Media cannot be deleted. Usage rows must make references visible,
and deletion checks must be atomic with the catalogue decision. Editorial
review of selected Media belongs to the owning Content workflow when such a
workflow exists.

## Explicit exclusions

M3.8 excludes folder tree, drag-and-drop folders, breadcrumbs,
Explorer-style navigation, SVG without a separately approved security
contract, video/audio processing, advanced editing, filters, AI editing,
design-project files, revision history, PDF generation/editing/extraction/
thumbnails/previews/form filling/signatures, cloud storage, CDN, queues,
workers, daemons, background cleanup, private Media capability, Media approval
workflows, duplicate consumer upload pipelines, automatic production Content/
Theme/Site Settings field adoption, broad document-management workflows, and
unrelated Core expansion.

## Exactly seven work units

### WU1 — Continuity, baseline fixtures, and compatibility evidence

**Objective:** Lock M3.8 scope, ownership, format policy, permission direction,
consumer assumptions, and baseline fixtures.

**Production scope:** Preparation evidence, compatibility inventory, fixture
strategy, and contract decisions only.

**Validation scope:** Repository continuity, existing install/package/Admin
compatibility evidence, and fixture coverage plan.

**Dependencies:** Completed M3.7 and the verified M3.8 preparation audit.

**Exclusions:** Media implementation, schema, runtime, and production tests.

**Acceptance direction:** Scope and compatibility assumptions are approved and
the seven-unit plan is unchanged.

**Documentation impact:** Contract, baseline evidence, and decision record.

**Human review:** Required for architecture, scope, and compatibility boundary.

### WU1 evidence record

WU1 is a tests-and-documentation-only compatibility baseline. The focused
evidence is `tests/m3_8_work_unit1_compatibility.php`.

The evidence establishes that the current schema installs successfully, the
existing baseline modules can be discovered, installed, enabled, and their
manifest permissions remain registered, and enabled module state and module
permission state survive a reconnect. It also boots the current Application
compatibility through the required clean-install regression and confirms from
the application boundary that `Application::siteAssets()` continues to expose
the existing `SiteAssetStorage` branding boundary.

The test confirms that `SiteAssetStorage` remains limited to the fixed Logo and
Favicon slots and that the pre-Media baseline contains no `media`,
`media_variants`, or `media_usages` tables, Media module registration, Media
upgrade artifact, Core Media implementation, generic Media storage API, or
speculative package entry.

WU1 records only the accepted action categories `view`, `upload`, `use`,
`edit`, and `delete`. Concrete permission identifiers and display names are
deferred to WU2, where the Media module and domain boundaries exist.

No executable fixtures were added for upload validation, MIME or extension
enforcement, dimension or decompression limits, traversal or symlink handling,
partial-write recovery, or Media catalogue/reference lifecycle. Later units
may add test-generated temporary fixtures for those contracts after their
implementation decisions are approved. WU1 adds no production, schema,
installer, upgrade, module, permission, package, route, UI, storage, or
runtime changes.

Validation recorded for this baseline: `tests/m3_8_work_unit1_compatibility.php`
passed 44 assertions; `tests/m3_7_work_unit1_compatibility.php` passed 16;
`tests/post_m2_package_builder_smoke.php` passed 927;
`tests/post_m2_clean_install_verification.php` passed 64; and
`tests/minimal_site_capabilities_batch4_smoke.php` passed 54. The required
`tests/minimal_site_capabilities_batch5_smoke.php` regression did not pass: it
stopped at its existing assertion that the Settings UI contain exactly two
upload forms. No WU1 file changes the Settings UI, so this is recorded as an
unrelated pre-existing regression signal rather than WU1 evidence.

### WU2 — Media domain, schema, repository, and lifecycle

**Objective:** Establish the Media domain, candidate `media`,
`media_variants`, and `media_usages` topology, repositories, and lifecycle.

**Production scope:** Catalogue identity, metadata, usage records, variant
descriptors, transaction boundaries, fresh schema, and idempotent upgrade.

**Validation scope:** Schema shape, constraints, provisioning, rollback,
orphan detection, and lifecycle atomicity.

**Dependencies:** WU1.

**Exclusions:** Binary upload, image processing, Admin workspace, and picker UI.

**Acceptance direction:** Catalogue and usage lifecycle are deterministic,
transaction-safe, and compatible with fresh and existing installations.

**Documentation impact:** Schema, upgrade, lifecycle, and repository sections.

**Human review:** Required for schema and deletion/usage semantics.

#### WU2 implementation decision record

WU2 establishes the module-local Media catalogue with `BIGINT UNSIGNED`
auto-increment identities, a `MediaId` value object, and the locked tables
`media`, `media_variants`, and `media_usages`. Media kind is limited to
`image` and `document`; images require positive dimensions, documents require
null dimensions, byte size is positive, and storage keys are opaque
application values rather than paths or URLs. No lifecycle status, soft
deletion, force deletion, checksum, generic metadata JSON, delivery URL,
processing settings, crop data, or focal-point data is part of WU2.

`media_usages` has no `updated_at` column. Its identity is the composite key
`(media_id, consumer_type, consumer_id, usage_key)`, with numeric
`BIGINT UNSIGNED` consumer IDs as the accepted baseline. Variant ownership is
enforced by a cascading Media foreign key; usage ownership is restricted.
Media deletion locks the Media row, rejects active usages, deletes the Media
row, and relies on the variant foreign-key cascade. WU2 does not expose
variant generation or processing.

Repositories remain separate for Media, variant descriptors, and usage rows.
The module lifecycle service owns transactions and nested savepoints. WU2
does not use optimistic concurrency through `updated_at`; timestamps are
maintained for audit ordering only. Fresh schema provisioning and the
idempotent existing-install upgrade provision tables and permissions. Module
registration and activation remain application-lifecycle operations through
the existing ModuleManager and installer baseline entrypoint.

### WU3 — Secure upload, original storage, and controlled delivery

**Objective:** Implement secure original-file registration, storage, cleanup,
and controlled image/PDF delivery.

**Production scope:** Upload adapters, content MIME validation, canonical
extensions, opaque storage keys, atomic writes, path containment, symlink
guards, original preservation, view/download behavior, and sanitized errors.

**Validation scope:** Malformed MIME/extensions, upload failures, traversal,
symlinks, partial writes, cleanup failures, URL/path exposure, and PDF delivery.

**Dependencies:** WU2.

**Exclusions:** Derivative processing, folder management, private Media, and
consumer-specific upload pipelines.

**Acceptance direction:** Only validated managed files enter storage and every
delivery path is controlled, bounded, and path-safe.

**Documentation impact:** Storage, upload, delivery, failure, and security
rules.

**Human review:** Required for security and filesystem behavior.

#### WU3 implementation decision record

WU3 is implemented entirely inside `modules/media`. The upload boundary
accepts an internal normalized source object and deliberately does not claim
that `is_uploaded_file()` was checked; a future HTTP adapter owns that
boundary. `MediaFileInspector` trusts neither browser MIME nor filename
extension, fails closed when file inspection is unavailable, and validates
JPEG, PNG, WebP, GIF, ICO, and PDF with the locked size, dimension, structure,
and byte-preservation rules. PDF handling is signature/EOF validation only;
there is no parser, renderer, sanitizer, or malware scanner.

`MediaFilesystemStorage` owns `.tmp` staging, bounded copying, symlink and
containment checks, exclusive temporary creation, same-filesystem activation,
and cleanup. Final originals use the locked
`originals/ab/cd/<32-lowercase-hex>.<extension>` layout. The upload service
reinspects the staged copy before activation and creates the WU2 row only
after the final original exists; database failure removes the activated
original. Delivery is public baseline behavior through the module-owned
`/media/{id}` and `/media/{id}/download` routes, with strict positive IDs,
stable identity checks, bounded full responses, sanitized disposition names,
and the locked security/cache headers. No Admin, picker, consumer, variant,
EXIF, derivative, queue, or generic storage work is included.

WU3 limitations are explicit: no checksum is stored, so a same-size,
same-MIME replacement cannot be detected; delivery is buffered and bounded to
16 MiB; Range and conditional-request support is intentionally absent; a
cleanup failure can leave an unreachable orphan file; and real concurrent
filesystem stress was not performed. Deterministic exclusive creation,
collision exhaustion, no-overwrite behavior, failed staging, activation
failure, database compensation, sanitized diagnostics, grammar, and
containment paths are covered by the focused WU3 suite. Symlink rejection is
implemented and source-reviewed, but symlink creation was unavailable in the
current Windows test environment.

### WU4 — Image processing and generated responsive outputs

**Objective:** Provide bounded image operations and generated responsive
outputs while preserving originals.

**Production scope:** Crop, resize, rotate, ratio constraints, bounded widths,
format/quality variants, EXIF orientation normalization, sensitive metadata
stripping, and stale-output handling.

**Validation scope:** Pixel/dimension limits, decompression limits, GIF/ICO
restrictions, output determinism, derivative bounds, invalidation, and original
preservation.

**Dependencies:** WU2 and WU3.

**Exclusions:** Advanced editing, AI, filters, video/audio, queues, and
unbounded arbitrary variant generation.

**Acceptance direction:** Requested outputs are bounded, reproducible, safe,
and never become separate user-visible Media items automatically.

**Documentation impact:** Processing, variants, responsive contract, and
format policy.

**Human review:** Required for image quality, animation behavior, privacy, and
performance limits.

#### WU4 implementation decision record

WU4 uses native GD only. PHP GD must be enabled in the PHP runtime/SAPI that
serves image processing (and in the CLI runtime when processing tests run);
image processing fails closed when GD is unavailable, and JPEG processing also
fails closed when EXIF support is unavailable. Imagick and third-party
dependencies are not introduced.

The module-local processing boundary uses typed requests, deterministic
semantic keys, deterministic physical keys, a GD processor, and a separate
generated-variant filesystem namespace. Editable JPEG, PNG, and WebP sources
are decoded, normalized for EXIF orientation, cropped or aspect-cropped,
rotated, resized, and re-encoded in that order. PNG/WebP alpha is preserved;
JPEG output composites transparency on white. GIF, ICO, PDF, SVG, video, and
audio are rejected from generic processing. Originals remain byte-identical.

Responsive widths are limited to `320, 640, 960, 1280, 1920, 2560`, with at
most six outputs per call; upscaling is disabled by default and may be enabled
only by a bounded consumer profile. Generated output is bounded to
4096 by 4096 pixels, 16,777,216 pixels, and 16 MiB per variant. Variant
descriptors use the existing `media_variants` table, deterministic semantic
keys, and a maximum of 24 semantic descriptors per Media. No schema or
upgrade change was required. No public variant route or Media deletion
orchestration was added; physical variant deletion during Media deletion
remains a WU6 boundary.

Focused WU4 validation passes 52 assertions with GD enabled only through the
CLI override. Limitations remain: output bytes may vary across GD/libjpeg/
libwebp runtime versions; real concurrent processing stress was not required;
cleanup failure may leave unreachable generated files; GD or JPEG EXIF may be
unavailable on a deployment; and no public variant delivery contract exists
until WU6.

### WU5 — Admin Media workspace and management workflows

**Objective:** Provide the dedicated Admin Media management surface.

**Production scope:** Configured-path navigation, listing, metadata/title
management, upload, controlled processing actions, PDF management, view/
download actions, deletion safety, empty/error states, and responsive Admin
presentation.

**Validation scope:** Permission matrix, CSRF ordering, PRG, escaping,
sanitized errors, malformed identifiers, configured Admin paths, responsive
behavior, and accessibility evidence.

**Dependencies:** WU2 through WU4.

**Exclusions:** Folder UI, approval workflows, consumer field adoption, and
private Media.

**Acceptance direction:** Authorized users can manage supported Media safely in
the existing Admin Shell without exposing physical paths.

**Documentation impact:** Admin routes, views, navigation, workflow, and design
checkpoint evidence.

**Human review:** Required for Admin hierarchy, picker entry points, responsive
behavior, and accessibility.

#### WU5 implementation decision record

WU5 adds the module-local Admin workspace at the configured Admin path with
`GET /admin/media`, `GET /admin/media/upload`, `POST /admin/media/upload`,
`POST /admin/media/{id}/title`, and `POST /admin/media/{id}/process`. Media is
registered after Content and before Taxonomy; no dashboard item is added.
The workspace uses a fixed page size of 24, deterministic updated/id ordering,
bounded title/original-filename search, kind and editable/manage-only filters,
and controlled original delivery for image previews. The default presentation is
a responsive visual grid of bounded-width cards: title is the primary identity,
original filename is secondary metadata, image previews preserve aspect ratio on
a neutral surface, and PDFs/documents use a clear generic document visual. The
card is the primary interactive target and opens an accessible Admin
preview/details overlay with previous/next navigation limited to the current
filtered page. The current overlay provides title editing and navigation;
consumer-specific transforms and the public delivery link are not Media Manager
actions. Download is intentionally not an Admin Media Manager action and
remains part of public delivery. Documents use no processing controls.

Upload and title updates reuse the existing Media services through a module-local
Admin orchestration boundary. The WU5-era fixed Square/Landscape/Contain
controls were removed in WU6 so transform requirements remain consumer-owned;
arbitrary global transform inputs are not accepted. No storage key, physical
path, temporary path, or generated-file location is rendered. WU5 adds no
delete route and does not call the Media deletion lifecycle. Generated variants
remain internal; no public variant delivery, picker, or consumer integration is
added by WU5 itself.

Upload titles are optional. An explicit non-empty title is preserved; a blank
title derives centrally from the original filename by stripping its final
extension, replacing underscores with spaces, collapsing whitespace, and
trimming. An unusable explicit title and filename are rejected, and persisted
Media titles are always non-empty. This boundary is shared by Admin upload and
future picker upload.

The current manual upload workflow accepts one file per submission. It does not
provide drag-and-drop upload or multiple-file selection/batch upload. These are
future Media upload enhancements, not WU5 defects or acceptance blockers.

The accepted WU6 direction is implemented on the active branch: a
consumer-triggered picker with a visual grid, consumer-specific type support,
inline upload, selection, bounded Media descriptors, Media-owned preparation
and crop, and an optional link to `/admin/media`. Content stores only the Media
ID; Media owns profile validation, variants, controlled delivery, and the
original-file boundary. Archive/index/card rendering, galleries, arbitrary
profiles, drag-and-drop, batch upload, and video remain excluded.

Focused AI acceptance is `PASS`, and the evidence is recorded in
`tests/m3_8_work_unit5_admin_media_workspace.php`, including bounded grid and
preview structure, keyboard/inert interaction hooks, title fallback,
explicit-title preservation, unusable fallback rejection, and existing
security boundaries.
Human visual/browser acceptance is `PASS` for bounded cards, grid presentation,
card-to-preview interaction, preview overlay usability, and the Admin/public
action boundary. WU5 is finally closed. No additional
human-required criterion remains for WU5.

### WU6 — Media picker, consumer contracts, usage, and deletion safety

**Objective:** Deliver reusable selection and consumer contracts owned by
Media.

**Production scope:** Existing selection, inline upload, the locked Content
`content.featured` profile (JPEG/PNG/WebP, 16:9 crop, 640/960/1280 responsive
widths, and allowed upscale), stable-reference return contract, processed
single-view rendering, descriptor/URL contract, usage visibility, and
referenced-deletion blocking. There is no quality-based source or crop minimum:
any technically valid in-bounds crop may be prepared.

**Validation scope:** Picker selection/upload flows, unauthorized use, stale
references, usage updates, deletion blocking, consumer contract compatibility,
and duplicate-pipeline prevention.

**Dependencies:** WU3 through WU5.

**Exclusions:** Theme and Site Settings field adoption, archive/index/card
featured-image rendering, galleries, multiple selection, arbitrary consumer
profiles, video/audio/GIF/ICO/document processing, batch upload, and new
processing engines.

**Acceptance direction:** A consumer can request and receive a stable Media
reference and controlled descriptor without owning Media internals.

**Documentation impact:** Picker API, consumer contracts, usage model, and
integration boundary.

**Human review:** Required for cross-module contracts and picker usability.

#### WU6 implementation decision record

WU6 is complete with Content as the first active Media consumer. Content stores
only `featured_media_id`; the consumer requests the `content.featured` profile,
while Media owns picker selection, upload, preparation, variants, controlled
delivery, and deletion safety. The Media Manager does not define global
Square/Landscape/Contain modes: consumer requirements drive preparation.

Content preparation is consumer/content-scoped. Confirming a crop creates a
bounded authenticated pending preparation and updates only the edit-form
preview. Before Content save, it does not switch `media_usages` or the public
descriptor. On successful Content save, Media validates and promotes that
pending preparation into stable committed slots for
`media_id + content + content.id + featured_media + output width`; public
resolution uses those slots. Re-crop replacement keeps one active committed
output per slot, removes safely unreferenced replaced files, and prevents stale
or another Content record's variants from competing with the active consumer
slot. Pending artifacts are not public delivery identities and have bounded
cleanup.

The active Content profile supplies its own locked 16:9 crop and responsive
640/960/1280 output requirements, including allowed upscale. Generic Media
processing receives the request/profile and must not hardcode Content-specific
ratio, widths, or crop policy. PHP GD is a runtime/deployment requirement for
this image-processing path.

Focused validation and human functional/browser acceptance are complete for the
picker, crop/save boundary, same-Media re-crop, shared-Media isolation, public
rendering, and deletion blocking. Further Media Manager visual/presentation
refinement remains Deferred / Unscheduled and non-blocking. This record does
not claim full M3.8 completion.

### WU7 — Security, install/upgrade/package regression, acceptance, and closure

**Objective:** Complete M3.8 hardening, compatibility, documentation, and
closure evidence.

**Production scope:** Security hardening, package inclusion, clean-install and
upgrade support, focused regressions, acceptance record, and documentation
closure.

**Validation scope:** Full focused Media evidence, module provisioning,
package/build checks, filesystem safety, permission matrix, Admin checkpoint,
and closure review.

**Dependencies:** WU1 through WU6.

**Exclusions:** Release, tag, publication, implementation branch merge, and
unrelated Core changes.

**Acceptance direction:** All locked contracts are evidenced, documentation
matches implementation, and no unresolved architecture blocker remains.

**Documentation impact:** Final acceptance, limitations, risks, and lifecycle
status.

**Human review:** No new human functional, browser, or visual review is
required: WU5/WU6 acceptance passed, and WU7 criteria are deterministic.

#### WU7 implementation decision record

WU7 adds no production-code change. Its closure test starts from a minimal
pre-M3.8 installation fixture, applies the controlled upgrade twice, verifies
the Media schema, Content integration, five permissions, seeded administrator
grants, and normal ModuleManager install/enable continuation. It also proves
the five-action route matrix, including `media.use` picker access, inline
picker upload requiring both `media.use` and `media.upload`, and
authorization-before-CSRF behavior. Package and packaged clean-install checks
now inventory Media runtime files, exclude `storage/media/`, and verify Media
schema, activation, manifest permissions, runtime permissions, and admin
grants.

## Open decisions and risks

Implemented policy is a three-table Media catalogue with `content.featured_media_id`,
five `media.*` permissions seeded to administrators, route-backed controlled
original/variant delivery, bounded buffered responses, consumer-owned Content
featured preparation, and usage rows that block deletion while referenced.
Theme and Site Settings adoption remain outside M3.8.

Known limitations and residual operational risks are: no checksum-based
same-size/same-MIME replacement detection; 16 MiB buffered delivery; no Range
or conditional delivery; cleanup failure can leave unreachable original or
generated files; output bytes may vary by GD/runtime library version; GD may
be unavailable on a deployment; and synchronous processing remains subject to
shared-hosting resource limits. These are documented operational boundaries,
not unresolved architecture decisions.

## Completion boundary

Preparation documentation is locally validated when this contract and its
directly affected status documentation pass documentation-focused review. This
does not authorize implementation or determine milestone closure.

WU1–WU6 are accepted predecessors. WU7 is implemented and validated within
the locked closure scope: it adds no production change and records upgrade,
authorization, package, and clean-install evidence. Full M3.8 is not complete;
milestone closure is not implied.
