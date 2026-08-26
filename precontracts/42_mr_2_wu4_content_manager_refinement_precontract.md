# Pre-contract Snapshot — MR.2 WU4 Content Manager Refinement

Pre-contract lifecycle: PROMOTED-HISTORICAL SNAPSHOT / TRIAL
Source authoritative contract: `docs/42_mr_2_wu4_content_manager_refinement_contract.md`
Source blob SHA: `2ae346fae38172e23d101eb40810fe0b5642298b`

This file materializes the existing authoritative MR.2 contract into the proposed Workplan Set pre-contract layer for trial and evaluation. It does not replace or weaken the authoritative source contract, reopen the closed Work Unit, or authorize implementation. The source contract content is preserved below as the materialized snapshot.

---

# MR.2 WU4 — Content Manager Refinement Contract

## Status and authority

```text
MR.2 WU1: COMPLETE AND CLOSED
MR.2 WU2: COMPLETE AND CLOSED
MR.2 WU3: COMPLETE AND CLOSED
WU4 scope: Content Manager Refinement
WU4 preparation/audit: COMPLETE
WU4 contract: PROMOTED / CONTRACT LOCKED
WU4 runtime implementation: COMPLETE
WU4 objective validation: PASS
WU4 human/product acceptance: PASS
WU4 lifecycle state: COMPLETE AND CLOSED
```

This contract promotes the bounded WU4 preparation audit into the authoritative
repository contract. It defines a refinement target around the accepted
Content implementation and records the accepted implementation and closure
result. It does not authorize schema changes, Deferred Item adoption,
production reconciliation, release work, or any later MR.2 work.

## Objective

Refine the retained Content Manager workspace and forms around existing
Webcore Content authority so administrators can find, understand, edit, and
transition Content more efficiently across desktop and mobile while
preserving all current ownership, lifecycle, Taxonomy, Media, authorization,
transaction, stale-write, and error-handling boundaries.

WU4 is a bounded presentation and interaction refinement. It must not create a
new Content authority, alter existing Content semantics, or expand into a
new editor, workflow, search, revision, preview, or publishing subsystem.

## Authoritative ownership and architecture

The Post-M3 Webcore & Extension Architecture Reconciliation contract remains
the architecture authority. It establishes that:

- Webcore owns baseline Content, persistence, public identity, baseline
  lifecycle, authorship, basic Media reference, and public delivery;
- Content Manager remains a retained Bundled Module that **EXTENDS** Webcore
  Content;
- Content Manager owns its Admin routes, workspace presentation, forms,
  lifecycle action presentation, Taxonomy assignment integration, and
  featured-Media integration without owning baseline Content persistence;
- Taxonomy remains optional and Module-owned;
- Media remains optional and consumer-owned through the existing Content
  reference and picker seam; and
- no System Manager representation change is required by WU4.

WU4 must preserve the existing Content Manager routes, the Webcore Content
repository and service boundaries, the Admin Page Frame and Admin Shell
boundaries, and the singular ownership model. It must not recreate retired
Settings, Module, Redirect, or Taxonomy Manager ownership through Content.

## Accepted current Content capability

The current implementation provides the baseline that WU4 refines:

- list, create, and edit Admin routes;
- Page and Article Content types;
- draft, published, and archived lifecycle semantics;
- title and slug search;
- type and status filters;
- bounded pagination with active-query preservation;
- distinct initial-empty and filtered-empty states;
- permission- and state-appropriate lifecycle actions;
- Taxonomy category and tag assignment when the optional Taxonomy Module is
  enabled;
- optional featured-Media reference and the existing consumer-scoped picker;
- authorization, CSRF, stale-write, transaction, sanitized-error, and
  unpublished-content protections; and
- accessible form-level and field-level validation recovery.

WU4 preserves this capability. Search, type/status filters, bounded
pagination, query preservation, list/create/edit routes, and draft/
published/archived semantics remain existing behavior rather than new
product semantics.

## Bounded refinement scope

WU4 may refine only the following directly related Content surfaces:

- Content list hierarchy, density, and scanability;
- search/filter affordance and active-filter comprehension;
- result context and empty/no-match recovery;
- lifecycle status and action presentation;
- responsive table and action behavior;
- create/edit form grouping and hierarchy;
- save, cancel, and primary-action treatment;
- accessible field-level and form-level validation recovery;
- existing Taxonomy assignment presentation;
- existing featured-Media picker and reference presentation;
- bounded CSS, JavaScript, and view refinement required for these areas; and
- directly affected presentation and regression tests.

The refinement must preserve safe escaping, existing authorization boundaries,
CSRF protection, stale-write tokens, transaction behavior, sanitized failures,
and the existing optional-integration behavior when Taxonomy or Media is
disabled or unavailable.

## Content, Taxonomy, and Media integration boundaries

Taxonomy assignment remains an optional consumer integration. WU4 may improve
its presentation in Content forms, but it must not move taxonomy state,
assignment ownership, hierarchy rules, term lifecycle, or Taxonomy routes into
Content.

Featured Media remains an optional consumer integration. WU4 may improve its
presentation in Content forms, but it must reuse the existing Media reference,
usage, picker, preparation, and promotion contracts. It must not move Media
storage, processing, variant, or deletion ownership into Content.

Content may continue to render a plain-text body through the current Webcore
Content contract. Rich-text/editor advancement is not part of WU4.

## Rich-text and editor boundary

Rich-text/editor work requires separate product and architecture planning. The
current authoritative boundary provides plain-text body storage and normalized
render data, but does not define an editor format, sanitization contract,
block model, migration path, or editor/rendering authority.

WU4 therefore does not authorize a rich-text editor, Markdown contract, block
composition system, embedded-content model, editor plugin framework, content
preview engine, or related storage/rendering changes.

## Explicit exclusions

- Webcore Content ownership or persistence redesign;
- schema or database changes;
- new Content lifecycle semantics;
- permission semantic changes;
- rich-text/editor implementation;
- revisions, history, or versioning;
- preview, workflow, or publishing automation;
- new generic search or indexing infrastructure;
- bulk-selection or bulk-workflow systems;
- Taxonomy ownership transfer or Taxonomy Manager redesign;
- Media ownership, storage, or processing redesign;
- System Manager changes;
- Settings, Redirect, Module, Dashboard, or Installer work;
- Deferred Item adoption;
- production Webcore reconciliation;
- release, tag, publication, or distribution work; and
- unrelated documentation or cleanup work.

## Objective acceptance criteria

WU4 may be accepted only when separately authorized implementation and
validation demonstrate that:

- Content Manager remains a retained Bundled Module extending Webcore
  Content;
- list, create, edit, and lifecycle semantics remain unchanged;
- search, type/status filters, bounded pagination, query preservation, and
  empty states remain correct;
- lifecycle actions remain permission- and state-appropriate;
- Taxonomy and Media integrations remain optional, compatible, and
  consumer-owned;
- form and field errors remain associated, accessible, escaped, and
  sanitized;
- stale-write, CSRF, authorization, transaction, and unpublished-content
  boundaries remain intact;
- desktop and mobile presentation remains readable without unintended
  horizontal overflow;
- plain-text Content body remains the accepted current boundary;
- no rich-text/editor subsystem, new ownership authority, generic search or
  indexing system, bulk workflow, revision system, preview engine, or
  unrelated capability is introduced; and
- no System Manager or retired-manager ownership is pulled into WU4.

## Accepted WU4 closure result

WU4 is COMPLETE AND CLOSED for this bounded refinement scope. The accepted
Content list and form refinement improves hierarchy, scanability, filter and
result comprehension, lifecycle action grouping, validation recovery, and
responsive behavior while preserving the existing Webcore Content ownership
boundary and Content Manager extension model.

The accepted implementation also corrects the narrow-screen Featured Media
control overflow by constraining the existing Content-form Media controls to
their available container width. Media picker, upload, reference, and
processing behavior remain unchanged.

Objective validation and human/product acceptance passed. Rich-text/editor,
revisions, preview/workflow, bulk operations, new search infrastructure,
ownership changes, and all other explicit exclusions remain outside WU4. This
closure does not authorize or implement WU5.

## Recommended focused validation surface

Validation should be proportional to the affected files and behavior and
should include, as applicable:

- Content list and presentation tests;
- Content workspace, filtering, pagination, and empty-state tests;
- Content authorization, CSRF, stale-write, lifecycle, and transaction tests;
- Taxonomy compatibility and assignment-boundary tests;
- directly affected Media contextual integration tests;
- Admin Page Frame regression when the shared frame is touched;
- PHP lint for changed PHP files;
- affected JavaScript, CSS, and static checks;
- authenticated desktop and mobile presentation review;
- `git diff --check`; and
- final scope and ownership review.

Broad historical Content regression suites are not required unless an
implementation change produces a concrete impact or regression signal.

## Source and contract references

- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`
- `docs/18_m3_4_content_manager_contract.md`
- `docs/21_m3_5_taxonomy_manager_contract.md`
- `docs/24_m3_8_media_library_contract.md`
- `modules/content/routes.php`
- `modules/content/views/admin/list.php`
- `modules/content/views/admin/form.php`
- `app/Core/Content.php`
- `app/Core/ContentRepository.php`
- `app/Core/ContentService.php`
- `modules/media/Services/MediaContentReferenceService.php`
- `public/admin-assets/css/admin.css`
- `public/admin-assets/js/content-media-picker.js`

This contract promotes preparation only. Runtime/source implementation,
contract expansion, schema work, and later MR.2 selection remain separately
authorized decisions.
