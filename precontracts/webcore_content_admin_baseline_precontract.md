# Webcore Content Admin Baseline — Corrective Workstream Pre-contract

Lifecycle: DRAFT / TRIAL / NOT AUTHORITATIVE
Workplan role: separate corrective Webcore baseline workstream
Placement: prerequisite before MR.2 WU7 resumes
Classification: architecture-to-runtime conformance correction
Promotion status: NOT PROMOTED
Implementation authorization: NONE

## Purpose

Define the smallest corrective Webcore Admin projection required to satisfy
the already-authoritative Webcore Content boundary. This is a separate
baseline workstream. It is not Content Manager refinement, MR.2 shared-artifact
implementation, or a redefinition of MR.2 WU7 or WU8.

This file is a planning artifact only. It does not override authoritative
contracts, authorize implementation, or establish a new MR.2 Work Unit number.

## Authoritative basis

`docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`
requires Webcore to own:

- Page and Article baseline types;
- persistence;
- slug and public identity;
- draft, published, and archived lifecycle;
- basic authorship;
- basic Admin list, create, edit, publish, and archive operations;
- basic Media reference;
- public delivery; and
- normalized render data.

Content Manager remains a retained Bundled Module that **EXTENDS** Webcore
Content. Its richer Admin behavior and optional integrations must not become a
reverse dependency of the Webcore baseline.

## Objective

Provide an always-available, Core-owned Content Admin projection that remains
usable when Content Manager is disabled, while preserving the existing Core
Content persistence, lifecycle, authorization, transaction, stale-write, and
public-delivery authorities.

## Minimum implementation boundary

The future implementation may contain only:

- Core-owned Content Admin routes;
- a Core-owned Content list view;
- Core-owned create and edit forms;
- publish and archive POST actions;
- the existing Webcore Admin authorization boundary;
- existing CSRF protection;
- existing Core validation and sanitized error handling;
- existing transaction and stale-write behavior where applicable;
- basic authorship display/use already represented by Core Content;
- basic Media reference using only the Webcore-owned Media identity boundary;
- accepted `AdminPageRenderer` usage;
- accepted semantic breadcrumb hierarchy;
- accepted Page Frame/page-heading composition; and
- existing shared Admin presentation primitives where their semantics match.

The baseline must remain fully functional without `ModuleLoader` registering
Content Manager routes.

## Dependency and ownership boundary

The required dependency direction is:

```text
Core Content Admin routes/views
  -> Core Auth / CSRF / Admin rendering
  -> Core ContentService / ContentRepository
  -> Webcore-owned Content persistence and lifecycle
```

Webcore Content Admin must not depend on:

- `ModuleLoader`;
- `modules/content/routes.php`;
- Content Manager views;
- Taxonomy Module services;
- Media Manager picker or processing services; or
- another optional Module implementation.

Existing Content Manager source may be used as implementation evidence or
adapted carefully, but the final Webcore baseline must not import the Module
or establish a reverse dependency. Enabling Content Manager must extend the
Core baseline rather than replace or fork it.

## Baseline and extension split

The following are baseline requirements:

- list;
- create;
- edit;
- publish;
- archive;
- basic authorship;
- basic Media reference; and
- required authorization, CSRF, validation, transaction, stale-write, and
  sanitized-error behavior.

The following remain extension-only unless stronger authoritative evidence is
accepted before implementation:

- taxonomy assignment;
- restore;
- advanced filtering, search, and workspace conveniences;
- richer Media picker or preparation;
- revisions, history, or versioning;
- preview;
- scheduling;
- workflow or publishing automation;
- rich-text, Markdown, or block editing;
- bulk actions; and
- Module-specific presentation or workflow.

The baseline must not silently absorb any of these capabilities merely
because Content Manager currently implements them.

## Presentation boundary

The projection should reuse the accepted Webcore Admin grammar:

- Page Frame/page-heading baseline;
- semantic breadcrumb rendering;
- existing Admin authorization/access-denied handling;
- existing shared field, action, and error presentation primitives.

Shared artifacts remain presentation-only. Content routes and services retain
workflow, lifecycle state, permissions, validation meaning, and domain
semantics.

No additional Content Manager visual redesign is included. Human visual
acceptance is required only if implementation introduces materially new
subjective presentation beyond the accepted Webcore Admin grammar.

## Acceptance direction

The future implementation must demonstrate:

1. With Content Manager disabled, `/admin/content` remains available through
   Webcore-owned routing.
2. Core list, create, edit, publish, and archive operations work.
3. Webcore Content remains the sole persistence and lifecycle authority.
4. No optional Module is required for the baseline projection.
5. Enabling Content Manager extends rather than replaces or forks the Core
   baseline.
6. Breadcrumbs, page headings, and shared Admin presentation remain
   consistent with accepted Webcore baselines.
7. Taxonomy, rich Media workflows, editor capability, and other excluded
   Content Manager features do not leak into the baseline.
8. Existing public Content delivery and normalized render data remain
   compatible.

## Focused validation direction

Validation should cover, at minimum:

- route availability with Content Manager disabled;
- list rendering and Core-owned data access;
- create/edit validation and sanitized failures;
- publish/archive authorization and state transitions;
- CSRF rejection;
- transaction and stale-write behavior where the touched route path depends
  on it;
- basic Media reference compatibility;
- semantic breadcrumb and Page Frame output;
- public Content delivery after baseline changes;
- optional Content Manager coexistence without route or authority fork; and
- PHP/static checks and `git diff --check` for the implementation delta.

Broad historical Content regression suites are not implied unless an actual
impact or regression signal warrants them.

## Sequencing and governance

This corrective workstream is a prerequisite before MR.2 WU7
Representative Adoption & Propagation Proof resumes. MR.2 WU7 remains a
shared-artifact propagation proof unit; MR.2 WU8 remains cross-surface
verification and closure. Neither unit absorbs missing Webcore domain
capability.

This Pre-contract requires explicit authoritative promotion before it becomes
repository implementation authority. Promotion must preserve the accepted
Webcore/Content Manager ownership boundary and must not invent a new MR.2
number or alter WU7/WU8 semantics.

No Workplan update is made by this Pre-contract framing. A material early
Workplan topology update is expected during planning reconciliation if this
corrective workstream is accepted for promotion.

## Explicit exclusions

This workstream does not authorize:

- Content Manager redesign;
- taxonomy implementation or ownership transfer;
- richer Media capability or Media ownership changes;
- rich-text/editor capability;
- revisions, history, preview, scheduling, or workflow automation;
- bulk actions;
- schema or persistence redesign;
- new lifecycle or permission semantics;
- new search/indexing infrastructure;
- System Manager, Media baseline, Color Scheme, or flat-surface work;
- MR.2 WU6/WU7/WU8 scope changes;
- Deferred Item adoption;
- production reconciliation; or
- release, tag, publication, or distribution work.

## Source and provenance references

- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`
  — primary authority;
- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md` — accepted
  Admin Page Frame foundation;
- `docs/44_mr_2_wu5_shared_admin_primitive_audit_contract.md` — shared
  presentation boundary;
- `docs/45_mr_2_wu6_shared_artifact_consolidation_implementation_contract.md`
  — shared-artifact implementation boundary;
- `workplan.md` — current MR.2 sequencing and provenance state;
- `app/Core/Content.php`;
- `app/Core/ContentRepository.php`;
- `app/Core/ContentService.php`;
- `routes/admin.php`; and
- `modules/content/routes.php` — extension evidence only, not a Webcore
  dependency.
