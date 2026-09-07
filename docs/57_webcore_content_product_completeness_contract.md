# Webcore Content Product Completeness Contract (WU5)

## Status and authority

```text
Workstream: Post-M3 — Webcore Product Completeness & Stabilization
Work Unit: WU5 — Webcore Content Product Completeness
Contract status: PROMOTED / CONTRACT LOCKED
Implementation status: NEW / NOT STARTED
Human/product acceptance: MANDATORY
Technical implementation authorization: NONE
Release / tag / publication authorization: NONE
```

This is the dedicated authoritative scope contract for the inserted WU5
corrective unit. It records later accepted product-completeness evidence that
supersedes the historical WU1 no-fourth-root conclusion for Content without
rewriting that historical conclusion or reopening MR.2.

## Objective

Make the existing Webcore-owned Content Admin fallback adequate for bounded
product/operator use when optional Modules are absent. WU5 improves the Core
projection; it does not restore a historical implementation or import Content
Manager wholesale.

## Accepted minimum product boundary

WU5 must provide:

- a bounded Page / Article selection control instead of arbitrary free-text
  Content type entry; and
- a proper bounded Webcore/Core Media selection and reference interaction for
  Featured Media instead of a raw numeric Media ID field.

The exact route, view, client interaction, permission, availability, error,
and reference-lifecycle mechanics remain subject to dedicated WU5 source audit
and separately authorized implementation. This contract does not prescribe a
Media Manager picker, processing workflow, or a particular UI mechanism.

## Authority and extension boundary

Webcore Content remains authoritative for Content types, persistence, lifecycle,
public identity, and delivery. Core Media remains authoritative for Media
identity, storage, selection/reference semantics, usage, delivery, and deletion
safety. WU5 must preserve those authorities and existing authorization, CSRF,
validation, transaction, stale-write, and public-delivery boundaries.

Content Manager remains a Bundled Module that **EXTENDS** Webcore Content. Its
richer workspace and workflow are implementation evidence only; WU5 must not
make it a dependency, duplicate its authority, or copy it wholesale.

## Explicit exclusions

WU5 does not automatically include:

- Taxonomy;
- rich-text/editor capability;
- revisions/history;
- scheduling/workflow;
- advanced search, filter, or workspace conveniences;
- bulk actions;
- Media Manager processing;
- crop, resize, rotate, derivative/variant management, or advanced Media
  preparation; or
- Content Manager-specific workflow or presentation.

MR.2 remains COMPLETE / CLOSED. WU5 is not a Content Manager refinement and
does not reopen accepted historical work.

## Acceptance boundary

Before closure, evidence must show that the zero-optional Core Content surface
provides both bounded interactions above, preserves singular Content and Media
authority, has no competing canonical editor, and remains compatible with the
retained Content Manager extension. Focused technical validation and controlled
runtime validation are required. Human/product acceptance is mandatory because
the accepted gap concerns operator adequacy not previously accepted for the
Core fallback.

## Dependency disposition

WU4 Batch 4 cross-surface acceptance and closure is paused pending WU5
acceptance. WU5 does not implement WU4 and WU4 does not absorb WU5 scope.
After WU5, renumbered WU6 — Zero-Optional Product Acceptance and WU7 —
Stabilization & v0.14.0 Readiness Closure remain separately gated.

## Provenance

- `docs/49_webcore_product_completeness_stabilization_contract.md` — parent
  workstream authority;
- `docs/51_webcore_product_completeness_wu1_scope_reconciliation_contract.md`
  — historical WU1 finding and explicit later supersession;
- `docs/46_webcore_content_admin_baseline_contract.md` — existing Core
  baseline boundary;
- `docs/42_mr_2_wu4_content_manager_refinement_contract.md` — closed
  historical extension evidence only; and
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md` — WU4
  Batch 4 dependency disposition.
