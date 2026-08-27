# MR.2 WU6 — Shared Artifact Consolidation & Implementation Contract

## Status and authority

```text
MR.2 WU1: COMPLETE AND CLOSED
MR.2 WU2: COMPLETE AND CLOSED
MR.2 WU3: COMPLETE AND CLOSED
MR.2 WU4: COMPLETE AND CLOSED
MR.2 WU5: COMPLETE / CONTRACT LOCKED
WU6 scope: Shared Artifact Consolidation & Implementation
WU6 preparation/reconciliation: COMPLETE
WU6 contract: PROMOTED / CONTRACT LOCKED
WU6 runtime/source implementation: NOT STARTED
WU6 lifecycle state: CONTRACT LOCKED / IMPLEMENTATION READY
```

This contract is the authoritative promotion of the reviewed and reconciled
WU6 pre-contract. The pre-contract remains preserved as historical provenance.
Promotion defines the bounded implementation scope; it does not itself execute
implementation or authorize work outside that scope.

The predecessor authority is
`docs/44_mr_2_wu5_shared_admin_primitive_audit_contract.md`. WU6 must use its
accepted primitive classifications, exceptions, ownership rules, and exact
implementation boundary. Visual similarity alone does not justify forced
consolidation.

## Objective

Implement the bounded canonical shared Admin presentation artifacts established
by WU5 using single-source CSS, tokens, classes, markup patterns, or equivalent
reusable presentation infrastructure while preserving every consumer's route,
workflow, data, lifecycle, action, authorization, validation, accessibility,
and specialized interaction semantics.

WU6 is shared-foundation implementation and representative adoption. It is not
a per-Bundled-Module redesign program, Admin Shell redesign, Dashboard project,
or Media-specific refinement stream.

## Preserved authority and ownership boundaries

The accepted WU1–WU5 contracts and the Post-M3 Webcore & Extension Architecture
Reconciliation contract remain authoritative. Shared artifacts own
presentation semantics only.

Consumers retain ownership of:

- route and workflow semantics;
- data and lifecycle state;
- action eligibility;
- permissions and authorization;
- validation rules and error meaning;
- domain-specific markup where semantic differences require it; and
- specialized responsive or interaction behavior justified by the domain.

WU6 must not move domain behavior, workflow, state, authorization, lifecycle,
migration, schema, persistence, package, Media, Content, Module, Theme,
Taxonomy, Settings, Redirect, Dashboard, or System Manager ownership into a
shared presentation primitive.

## Bounded implementation scope

WU6 may implement only these six slices established by WU5:

1. **Optional page-heading composition:** title, optional kicker/eyebrow,
   description, and action regions. Optionality is required; no consumer is
   forced to display a kicker.
2. **Shared filter-toolbar presentation:** optional search/filter fields,
   active-filter/result context, and recovery presentation. Consumers retain
   query, filtering, pagination, and data semantics.
3. **Bounded stack/section spacing and optional inline-field layout:** use the
   existing Admin tokens and preserve semantic field grouping and responsive
   behavior.
4. **Equivalent action-row/button normalization:** use existing button
   semantics, sizing, focus behavior, action ordering, and destructive context.
5. **Selector-level radius/flat-surface dispositions:** apply explicit
   semantic intent to accepted rounded or flat/square surfaces; do not perform
   global flattening.
6. **Focused representative adoption:** adopt the bounded artifacts in a
   representative set of existing consumers sufficient to prove propagation,
   responsive behavior, accessibility, and documented exceptions. This is not
   complete per-module normalization.

Implementation must prefer the smallest stable abstraction that removes proven
duplication. Shared artifacts may support accepted exceptions without requiring
consumers to fork or override the entire primitive.

## Canonical sources and consolidation rules

The existing Admin token set in `public/admin-assets/css/admin.css` remains the
single source for shared color, spacing, radius, control-height, and typography
tokens. Existing `.admin-button*`, `.admin-panel*`, `.admin-page-frame*`,
`.admin-field*`, `.admin-form*`, `.admin-actions`, `.admin-row-actions`, empty
state, table-wrapper, focus, and overflow artifacts remain canonical where
their semantics match.

WU6 may consolidate duplicated/local compositions for:

- page title/kicker/header structure;
- filter-toolbar layout and result context;
- control-to-result and section spacing; and
- equivalent inline-field and action-row presentation.

Consumers must retain semantic markup and domain-specific variants when their
state model, workflow, authorization, accessibility, or interaction behavior
differs. System Manager status/module cards, Media cards/previews/picker,
Content consumer controls, Dashboard composition, and dialog focus/state
behavior remain local exceptions unless exact semantic equivalence is proven.

Existing JavaScript files remain owned by their surfaces. WU6 must not create a
generic component registry, shared behavior authority, or new JavaScript
framework merely because visual structures are similar.

## Consumer and reference boundaries

- WU1 Page Frame is the page-level frame reference.
- WU4 Content is a reference for list hierarchy, filter composition, form
  rhythm, and responsive table behavior, not universal design authority.
- WU2/WU3 System Manager is a reference for responsive state/detail grids and
  lifecycle-action comprehension.
- Historical Media WU5 is evidence only and is not resumed as Media-specific
  work.

No forced consolidation may remove legitimate domain differences. A local
variant is retained when semantic responsibility, interaction behavior,
accessibility, state, workflow, authorization, or ownership requires it.

## Explicit exclusions

WU6 does not authorize:

- per-Bundled-Module UX refinement;
- Dashboard composition or widget redesign;
- broad Admin Shell, sidebar, or topbar redesign;
- a generic component registry;
- generic search or indexing infrastructure;
- a dialog framework;
- a form engine;
- a workflow or lifecycle engine;
- new JavaScript behavior authority;
- domain capability expansion;
- schema or persistence changes;
- ownership changes;
- retired-manager restoration;
- old Media WU5 continuation as a Media refinement stream;
- Deferred Item adoption;
- production reconciliation;
- release, tag, publication, or distribution work; or
- unrelated cleanup.

A strictly bounded shared Admin adjustment required by an accepted WU6
primitive is allowed where it remains within the six slices above and does not
redesign the Admin Shell or a consumer domain.

## Authorization and governance boundary

Bounded WU6 execution may proceed through a GPT-framed Agent Instruction under
the accepted and promoted MR.2 workstream authorization. Routine progression
within this contract does not require repetitive fresh user approval merely
because execution moves from WU5 to WU6 or between the six implementation
slices.

Fresh explicit user approval remains required when execution crosses a real
governance boundary, including:

- scope expansion outside accepted/promoted MR.2 scope;
- Deferred Item adoption;
- an unresolved or unlocked product/architecture decision;
- destructive or irreversible action;
- production reconciliation or similarly sensitive operational action;
- release, tag, publication, or external distribution; or
- another approval gate explicitly reserved to the user.

If implementation exposes such a boundary, stop before deciding it in code.

## Acceptance criteria

WU6 may be accepted only when focused implementation evidence demonstrates:

- each adopted primitive has one identifiable CSS, token, class, markup, or
  artifact owner;
- equivalent consumers use the shared artifact without changing domain
  semantics;
- local variants are retired, documented as exceptions, or explicitly left
  outside the representative proof;
- page headings, filter/result presentation, field/helper/error presentation,
  action rows, spacing, and radius dispositions match the accepted boundary;
- responsive behavior, keyboard/focus behavior, accessible labels/errors, and
  no-overflow behavior remain valid;
- WU1–WU5 accepted semantics and Webcore/Core, Bundled Module, System Manager,
  Media, Content, Dashboard, and retired-manager ownership boundaries remain
  unchanged;
- no old Media-specific refinement, Deferred capability, generic framework,
  new behavior authority, or product/architecture decision is introduced; and
- representative adoption proves intended propagation and explicitly
  preserves documented domain exceptions without claiming every consumer is
  normalized.

## Focused validation direction

Validation must be proportional to the shared artifacts and representative
consumers actually touched and should include:

- shared CSS/token/class/artifact ownership and duplicate-base-style review;
- representative page-heading, filter/result, field/helper/error, action-row,
  spacing, and radius rendering;
- responsive desktop/mobile readability and no unintended overflow;
- keyboard, focus, and accessible-label/error behavior;
- directly affected WU1–WU5 regression checks only where touched artifacts
  create an impact signal;
- applicable CSS, JavaScript, PHP, and static checks;
- `git diff --check`; and
- final scope, ownership, and exception review.

Broad historical runtime/regression suites are not required without a concrete
impact or regression signal.

## Promotion and lifecycle

This contract is authoritative for WU6. WU6 runtime/source implementation has
not started. Implementation remains bounded by this contract and must stop if
the six slices cannot be implemented without new authority, a Deferred Item,
or an actual governance approval boundary.

## Source and provenance references

- `precontracts/mr_2_wu6_shared_artifact_consolidation_implementation_precontract.md` — reviewed promoted provenance;
- `workplan.md` — MR.2 registry and WU6 planning state;
- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`;
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`;
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`;
- `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`;
- `docs/42_mr_2_wu4_content_manager_refinement_contract.md`; and
- `docs/44_mr_2_wu5_shared_admin_primitive_audit_contract.md` — predecessor authority.
