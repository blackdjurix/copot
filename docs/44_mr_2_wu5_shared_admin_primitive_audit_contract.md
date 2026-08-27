# MR.2 WU5 — Shared Admin Primitive Audit & Contract

## Status and authority

```text
MR.2 WU1: COMPLETE AND CLOSED
MR.2 WU2: COMPLETE AND CLOSED
MR.2 WU3: COMPLETE AND CLOSED
MR.2 WU4: COMPLETE AND CLOSED
WU5 scope: Shared Admin Primitive Audit & Contract
WU5 preparation/audit: COMPLETE
WU5 contract: PROMOTED / CONTRACT LOCKED
WU5 implementation: NOT AUTHORIZED
WU5 lifecycle state: CONTRACT LOCKED / WU6 HOLD
```

This contract promotes the reviewed WU5 pre-contract into authoritative
repository documentation. The pre-contract remains preserved as historical
provenance. This contract defines the bounded shared-presentation boundary
for later WU6 planning and does not authorize WU6 implementation.

The historical Media Manager contract at
`docs/43_mr_2_wu5_media_manager_refinement_contract.md` remains intact and is
authority/evidence for the old Media WU5 topology only. It is not the current
forward WU5 identity and does not authorize Media-specific continuation.

## Objective

Audit and lock a bounded shared Admin presentation boundary that distinguishes
single-source primitives from local equivalents, legitimate domain
specializations, accepted surface conflicts, and product/architecture
questions. WU5 preserves existing Admin, Webcore, Core, Bundled Module,
authorization, lifecycle, workflow, state, schema, and persistence ownership.

## Preserved authority and semantics

The accepted WU1–WU4 contracts and the Post-M3 Webcore & Extension Architecture
Reconciliation contract remain authoritative. WU5 does not move ownership or
behavior into shared presentation artifacts.

In particular:

- the WU1 Admin Page Frame remains the page-level frame authority;
- WU2 System Manager baseline and WU3 System Manager refinement remain
  accepted, with System Manager ownership and lifecycle semantics unchanged;
- WU4 Content remains Webcore-owned baseline Content plus a retained Content
  Manager extension, including its existing list, form, lifecycle, Taxonomy,
  Media, authorization, transaction, stale-write, and error boundaries;
- Media remains governed by the Webcore Media boundary and historical Media
  WU5 contract, without forward Media refinement in this WU5; and
- Dashboard/Admin Shell, retained Bundled Module workflows, retired-manager
  boundaries, and Deferred Items remain outside WU5.

Shared presentation primitives must not acquire domain behavior, workflow,
data state, authorization, lifecycle, migration, schema, persistence, or
consumer ownership.

## Authoritative primitive classifications

The following classifications are locked from the accepted audit. Each pattern
has exactly one primary classification from the approved model.

| Primitive/pattern | Current source and consumers | Classification | Bounded disposition |
|---|---|---|---|
| Color, spacing, radius, control-height, and typography tokens | `public/admin-assets/css/admin.css` `:root`; all Admin surfaces | already-canonical shared primitive | Retain the token source; replace only directly equivalent literals. |
| Buttons and primary/secondary/link sizing | `.admin-button*`; Content, Media, Users, Taxonomy, Settings, System Manager | already-canonical shared primitive | Normalize equivalent markup without changing action meaning. |
| Panels and Page Frame surfaces | `.admin-panel*`, `.admin-page-frame*`; Media, Content, Settings, Users, Taxonomy, Navigation, Theme, Module Manager | already-canonical shared primitive | Consolidate equivalent wrappers only; preserve domain cards and status surfaces. |
| Page title, optional eyebrow/kicker, and header composition | Content `.admin-content-header*`/`.admin-content-eyebrow`; Form Manager and Redirects | duplicated/local equivalent suitable for consolidation | WU6 may define an optional page-heading composition; an eyebrow is not mandatory. |
| Search/filter toolbar and result context | Content `.admin-content-filters*`; Media and Form submissions reuse portions | duplicated/local equivalent suitable for consolidation | WU6 may share layout and result-context presentation; consumers retain query/filter semantics. |
| Control-to-result spacing and section rhythm | Content layout gaps, Media filter separation, System Manager workspace gaps | duplicated/local equivalent suitable for consolidation | A bounded stack/section-spacing artifact may be introduced without changing flow semantics. |
| Fields, labels, helper/error text | `.admin-field*`, `.admin-form*`; Content, Taxonomy, Settings, Users, Navigation, Theme | already-canonical shared primitive | Preserve semantic inline/detail-row exceptions; do not replace field semantics. |
| Action rows and paired actions | `.admin-actions`, `.admin-form__actions`, `.admin-row-actions` | already-canonical shared primitive | Normalize equivalent spacing while preserving ordering and destructive context. |
| Modal/dialog action rhythm | Media preview and Content Featured Media picker | legitimate domain-specific specialization that should remain local | No generic dialog framework; neutral token reuse is permitted. |
| Typography scale and grid | Shared font/tokens and headings; Content/System Manager grids | already-canonical shared primitive | Preserve domain metadata density and information architecture. |
| Radius versus flat/square surfaces | Root radius tokens; intentionally flat System Manager update bar; local Media picker values | conflicting accepted surface requiring a bounded decision | WU6 must decide at selector level from semantic intent; no global flattening. |
| Responsive and accessibility behavior | Shared focus/overflow/table/Page Frame rules plus consumer-owned JS | legitimate domain-specific specialization that should remain local | Prove propagation without flattening interaction, focus, or state behavior. |
| Shared JavaScript/artifact ownership | Owning scripts: `admin-shell.js`, `admin-form-capabilities.js`, `admin-settings.js`, `admin-media.js`, `system-manager.js`, `content-media-picker.js` | product/architecture/capability question outside WU5 | No generic component registry or shared behavior authority. |

Similarity alone is not sufficient evidence for consolidation. A local variant
may remain when semantic responsibility, interaction behavior, accessibility,
state model, workflow, authorization, or domain ownership differs.

## Reference surfaces and exceptions

- WU1 Page Frame is the reference for page-level frame ownership and optional
  surface/spacing intent.
- WU4 Content is the reference for list hierarchy, filter composition, form
  rhythm, and responsive table behavior, not universal design authority.
- WU2/WU3 System Manager is the reference for responsive state/detail grids
  and lifecycle-action comprehension.
- Media is historical evidence for the old WU5 only and is not a forward WU5
  adoption target.

System Manager status/module cards, Media cards/previews/picker, Content
consumer controls, Dashboard composition, and dialog focus/state behavior are
legitimate domain-specific surfaces unless a later contract proves exact
semantic equivalence.

The accepted radius/flat-surface difference is a bounded selector-level
decision. It does not authorize Admin Shell redesign or a new visual authority.

## Old Media WU5 historical disposition

The old Media WU5 implementation and contract remain historical authority for
their own accepted old topology/evidence. For current WU5 purposes:

- the `$mediaUrl` capture correction in the historical Content context-picker
  seam is a valid correctness fix; the deletion-guidance ordering correction
  is a local correctness/presentation fix;
- reuse of `.admin-panel`, `.admin-actions`, `.admin-button*`, root tokens,
  `.admin-content-filters`, overflow rules, and focus rules is reusable
  shared-artifact evidence, not new Media ownership;
- Media card/grid, preview metadata/navigation, upload intake, controlled
  preview dialog, and consumer crop/pending preparation remain future
  Media-specific surfaces; and
- Media-only thumbnail/list mode, renamed upload destination, new destructive
  confirmation workflow, and hardcoded Media picker/layout values treated as
  universal Admin rules are rejected from shared WU5 scope.

No Media-specific refinement is resumed by this contract.

## Exact WU6 implementation boundary

After separate WU6 implementation authorization, WU6 may implement only:

1. an optional page-heading composition for title, optional kicker,
   description, and action regions;
2. a shared filter-toolbar layout with optional search/filter fields,
   active-filter/result context, and recovery presentation, while consumers
   retain query and filtering semantics;
3. bounded stack/section spacing and optional inline-field layout using the
   existing tokens;
4. normalization of equivalent action-row/button composition using existing
   button semantics;
5. selector-level radius/flat-surface dispositions with explicit intent; and
6. focused representative adoption proving propagation, responsive behavior,
   accessibility, and documented exceptions.

WU6 must not introduce a component registry, generic search/indexing, dialog,
form, workflow, or lifecycle engine; new JavaScript behavior authority;
schema/persistence; domain state or authorization ownership; per-Module
refinement; Dashboard/Admin Shell redesign; retired-manager restoration; or
Deferred capability adoption.

## Acceptance criteria for WU6 promotion and implementation

Any later WU6 implementation must demonstrate that:

- each adopted primitive has one identifiable CSS, token, class, or artifact
  owner;
- equivalent consumers use the shared artifact without changing domain
  semantics;
- local variants are retired, documented as exceptions, or explicitly left
  outside the proof;
- page headers, filters, fields/helpers/errors, action rows, spacing, radius
  policy, responsive behavior, and accessibility are checked against concrete
  representative consumers;
- WU1–WU4 accepted semantics and all Webcore/Core, Bundled Module,
  System Manager, Media, Content, Dashboard, and retired-manager ownership
  boundaries remain unchanged;
- no old Media-specific refinement, Deferred capability, generic framework,
  product decision, or architecture expansion is introduced; and
- focused validation proves desktop/mobile readability, keyboard/focus
  behavior, accessible labels/errors, no unintended overflow, and no
  source-of-truth duplication.

## Focused validation direction

Validation for later WU6 work should be limited to directly affected evidence:

- shared CSS/token/class/artifact ownership and representative consumer
  rendering;
- page heading, filter/result, field/helper/error, action-row, spacing, and
  radius dispositions;
- responsive and accessibility behavior, including focus and no-overflow
  checks;
- WU1–WU4 regressions only where touched artifacts create an impact signal;
- PHP/JavaScript/CSS/static checks as applicable;
- `git diff --check`; and
- final scope and ownership review.

Broad historical runtime/regression suites are not required without a concrete
impact or regression signal.

## Explicit exclusions

This contract does not authorize:

- WU6 implementation;
- shared CSS/markup/token migration;
- broad Admin Shell or Dashboard redesign;
- per-Bundled-Module refinement or Media-specific continuation;
- domain workflow, lifecycle, state, authorization, schema, persistence,
  migration, or ownership changes;
- generic search/indexing, component registry, dialog/form/workflow engine, or
  new JavaScript behavior authority;
- Concept creation or semantic revision;
- Deferred Item adoption;
- production reconciliation;
- branch changes, release, tag, publication, or distribution; or
- unrelated cleanup.

WU5 authoritative promotion is complete when this contract is durably pushed
to `main` and independently verified. WU6 remains HOLD and NOT AUTHORIZED.

## Source and provenance references

- `precontracts/mr_2_wu5_shared_admin_primitive_audit_precontract.md` —
  reviewed promoted provenance;
- `workplan.md` — MR.2 registry and WU5/WU6 planning state;
- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`;
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`;
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`;
- `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`;
- `docs/42_mr_2_wu4_content_manager_refinement_contract.md`; and
- `docs/43_mr_2_wu5_media_manager_refinement_contract.md` — historical old
  Media WU5 authority/evidence, unchanged.
