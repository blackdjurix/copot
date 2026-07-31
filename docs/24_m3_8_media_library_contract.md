# M3.8 Media Library Preparation Contract

## Purpose and status

This document is the preparation contract for M3.8 Media Library. The
clarification brief adds no new roadmap capability; it strengthens and
clarifies the existing M3.8 direction already reserved in the M3 sequence.

Current preparation status:

```text
M3.7:
NRP CONFIRMED

M3.8 initial clarification:
COMPLETE

M3.8 preparation audit:
COMPLETE

M3.8 preparation contract:
AUTHORED

M3.8 preparation documentation:
LOCALLY VALIDATED

Implementation:
WU2 LOCALLY IMPLEMENTED AND VALIDATED

WU3–WU7:
NOT STARTED

Implementation branch:
WU2 DELIVERY GATE
```

The branch is `feature/m3.8-media-library`. WU2 implementation and focused
validation are complete. Durable Git delivery and final remote verification
are the next closure gate. WU2 remains NRP CANDIDATE until that gate and
post-Git documentation review are complete. WU3–WU7 remain not started and
unauthorized.

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

M3.8 delivers reusable consumer contracts and the picker. Actual production
field adoption in Content, Theme, and Site Settings remains post-M3.8 unless
a later approved scope decision changes that boundary. This preserves the
roadmap direction that consumer integration follows Media through explicit
contracts.

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

### WU6 — Media picker, consumer contracts, usage, and deletion safety

**Objective:** Deliver reusable selection and consumer contracts owned by
Media.

**Production scope:** Existing selection, inline upload, field-specific crop or
processing request, stable-reference return contract, descriptor/URL contract,
usage visibility, and referenced-deletion blocking.

**Validation scope:** Picker selection/upload flows, unauthorized use, stale
references, usage updates, deletion blocking, consumer contract compatibility,
and duplicate-pipeline prevention.

**Dependencies:** WU3 through WU5.

**Exclusions:** Actual production Content, Theme, and Site Settings field
adoption unless separately approved.

**Acceptance direction:** A consumer can request and receive a stable Media
reference and controlled descriptor without owning Media internals.

**Documentation impact:** Picker API, consumer contracts, usage model, and
integration boundary.

**Human review:** Required for cross-module contracts and picker usability.

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

**Human review:** Required for final security, acceptance, documentation, and
preparation-status review.

## Open decisions and risks

The following remain implementation-level or final-review decisions:

- exact column/index design within the locked three-table topology;
- exact permission identifiers and role provisioning details;
- route-backed, generated-public-cache, or hybrid delivery topology;
- MIME/content disposition, caching, and URL-stability policy;
- pixel, byte, and decompression limits;
- GIF animation-preservation policy and ICO structure policy;
- EXIF normalization and metadata-stripping details;
- synchronous processing cost and shared-hosting limits;
- stale-variant regeneration and orphan cleanup behavior;
- usage-row semantics for all approved consumers;
- exact Content/Theme/Site Settings contract adoption timing.

Primary risks are database/filesystem inconsistency, orphaned records/files,
unsafe type handling, decompression bombs, GIF animation loss, metadata
privacy leakage, derivative explosion, stale outputs, referenced deletion,
path exposure, Core ownership leakage, duplicate upload pipelines,
install/upgrade incompatibility, shared-hosting limits, synchronous processing
cost, Admin picker complexity, and responsive-image contract ambiguity.

## Completion boundary

Preparation documentation is locally validated when this contract and its
directly affected status documentation pass documentation-focused review. This
does not authorize implementation or determine milestone closure.

WU2 implementation and focused validation are complete. Durable Git delivery
and final remote verification are the next closure gate. WU2 remains NRP
CANDIDATE until that gate and post-Git documentation review are complete.
WU3–WU7 remain `NOT STARTED` and unauthorized; milestone closure is not
implied.
