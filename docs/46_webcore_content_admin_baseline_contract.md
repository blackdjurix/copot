# Webcore Content Admin Baseline Contract

## Status and authority

```text
Workstream: Separate corrective Webcore baseline
Classification: Architecture-to-runtime conformance correction
Placement: Prerequisite before MR.2 WU7 resumes
Contract status: PROMOTED / CONTRACT LOCKED
Implementation status: COMPLETE / ACCEPTED
Objective validation: PASS
Repository lifecycle: COMPLETE / CLOSED
```

This contract is the authoritative promotion of the reviewed Webcore Content
Admin Baseline Pre-contract. It does not create an MR.2 Work Unit, redefine
MR.2 WU7 or WU8, or authorize work outside the boundary below.

## Objective

Provide an always-available, Core-owned Content Admin projection that remains
usable when Content Manager is disabled, while preserving Webcore Content
persistence, lifecycle, authorization, transaction, stale-write, and public
delivery authority.

Content Manager remains a retained Bundled Module that **EXTENDS** Webcore
Content. It must not replace, fork, or become a dependency of this baseline.

## Authoritative baseline

Webcore Content owns:

- Page and Article baseline types;
- persistence;
- slug and public identity;
- draft, published, and archived lifecycle;
- basic authorship;
- basic Admin list, create, edit, publish, and archive operations;
- basic Media reference;
- public delivery; and
- normalized render data.

## Bounded implementation scope

The implementation may provide only:

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

The required direction is:

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
adapted carefully, but the final baseline must not import the Module or create
a reverse dependency. Enabling Content Manager must extend the Core baseline.

## Baseline and extension split

Baseline requirements are:

- list;
- create;
- edit;
- publish;
- archive;
- basic authorship;
- basic Media reference; and
- required authorization, CSRF, validation, transaction, stale-write, and
  sanitized-error behavior.

The following remain extension-only:

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

The baseline must not absorb these capabilities merely because Content Manager
currently implements them.

## Presentation boundary

The projection must reuse the accepted Webcore Admin grammar where applicable:

- Page Frame/page-heading baseline;
- semantic breadcrumb rendering;
- existing Admin access-denied handling; and
- existing shared field, action, and error presentation primitives.

Shared artifacts own presentation only. Content routes and services retain
workflow, lifecycle state, permissions, validation meaning, and domain
semantics. This contract does not authorize Content Manager visual redesign.

## Acceptance criteria

The implementation is acceptable only when evidence demonstrates:

1. With Content Manager disabled, `/admin/content` remains available through
   Webcore-owned routing.
2. Core list, create, edit, publish, and archive operations work.
3. Webcore Content remains the sole persistence and lifecycle authority.
4. No optional Module is required for the baseline projection.
5. Enabling Content Manager extends rather than replaces or forks the baseline.
6. Breadcrumbs, page headings, and shared Admin presentation remain consistent
   with accepted Webcore baselines.
7. Taxonomy, rich Media workflows, editor capability, and other excluded
   Content Manager features do not leak into the baseline.
8. Existing public Content delivery and normalized render data remain
   compatible.

## Focused validation

Validation must cover, as applicable:

- route availability with Content Manager disabled;
- list rendering and Core-owned data access;
- create/edit validation and sanitized failures;
- publish/archive authorization and state transitions;
- CSRF rejection;
- transaction and stale-write behavior;
- basic Media reference compatibility;
- semantic breadcrumb and Page Frame output;
- public Content delivery after baseline changes; and
- optional Content Manager coexistence without a route or authority fork.

Run PHP/static checks and `git diff --check` for the implementation delta.
Broad historical Content suites are not implied unless an impact or regression
signal warrants them.

## Explicit exclusions

This contract does not authorize:

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

## Sequencing and governance

This separate corrective workstream is a prerequisite before MR.2 WU7
Representative Adoption & Propagation Proof resumes. WU7 remains a
shared-artifact propagation proof unit, and WU8 remains cross-surface
verification and MR.2 closure. Neither absorbs missing Webcore domain
capability.

This promotion establishes implementation authority only for this bounded
baseline. It does not authorize implementation of MR.2 WU7/WU8 or any excluded
capability.

## Closure record

The Core-owned Content Admin baseline is implemented and accepted. With
Content Manager disabled, `/admin/content` remains available through Core-owned
routing and provides the baseline list, create, edit, publish, and archive
operations. Content Manager remains an extending Bundled Module; the
extension-only exclusions above are unchanged.

The accepted implementation is anchored at
`595e9a3e81b61793c439bd0f6688e4f32b58c989`. This closure does not claim that
MR.2 WU7 implementation is authorized by Workplan state alone.

## Provenance and references

- `precontracts/webcore_content_admin_baseline_precontract.md` — promoted
  historical planning provenance;
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`
  — primary ownership authority;
- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md` — accepted
  Page Frame foundation;
- `docs/44_mr_2_wu5_shared_admin_primitive_audit_contract.md` — shared
  presentation boundary;
- `docs/45_mr_2_wu6_shared_artifact_consolidation_implementation_contract.md`
  — shared-artifact boundary;
- `app/Core/Content.php`;
- `app/Core/ContentRepository.php`;
- `app/Core/ContentService.php`;
- `routes/admin.php`; and
- `modules/content/routes.php` — extension evidence only.
