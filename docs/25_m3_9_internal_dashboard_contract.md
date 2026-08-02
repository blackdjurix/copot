# M3.9 Internal Dashboard Preparation Contract

## Purpose and status

This document locks the preparation direction for M3.9 Internal Dashboard. It
refines the approved M3 sequence and four-domain-work-unit envelope without
implementing M3.9 runtime behavior.

```text
M3.8:
COMPLETE AND CLOSED

M3.9 preparation:
LOCKED

M3.9 WU1 implementation:
IN PROGRESS

M3.9 WU2–WU4:
NOT STARTED

M3.9 implementation branch:
`feature/m3.9-internal-dashboard`
```

M3.8 WU1–WU7 are complete and accepted. Full M3.8 was fast-forward
integrated into `main`, post-merge documentation closure was recorded, and
`feature/m3.8-media-library` was deleted locally and remotely after verified
containment. The authoritative branch is `main`.

## Objective

Build a useful internal management overview on the completed Admin Shell and
existing manager capabilities. M3.9 will aggregate real, bounded management
data through explicit public contribution boundaries while preserving module
ownership and the existing authorization model.

The exact first-wave widget inventory, minimal contribution payload shape, and
composition rules are intentionally finalized in WU1. WU1 may refine those
details only within this contract's ownership, permission, failure, and
exclusion boundaries; it does not authorize speculative analytics or generic
platform infrastructure.

## Accepted existing baseline

The following capabilities are complete and reusable:

* configured Admin route and URL ownership;
* `admin.access` authorization entry point;
* centralized Admin Shell and page renderer;
* request-scoped permission-aware Admin navigation;
* responsive and accessible Admin layout, panels, cards, and empty states;
* minimal `AdminDashboardRegistry` with stable IDs, permission filtering,
  deterministic ordering, duplicate rejection, and controlled root-relative
  links;
* existing Content and Taxonomy shortcut contributions;
* current dashboard status and user/application overview rendering.

This baseline is not treated as missing merely because M3.9 has not begun. The
existing registry is a completed M2 extension contract, not the full M3.9
product capability.

## WU1 locked first-wave widget inventory

WU1 selects the following exact M3.9 baseline widgets. This inventory is based
on the current Core dashboard status surface, the existing Content and
Taxonomy registry contributions, and the public Content workspace contract in
`modules/content/routes.php` and `modules/content/Services/ContentRepository.php`.

| Stable identity | Owner | Purpose | Permission | Destination/action | Default priority | Preferred footprint |
|---|---|---|---|---|---:|---|
| `core.system-overview` | Core/Admin Shell | Application, configured Admin path, current user, and safe framework status | `admin.access` | None; information/status only | 100 | `wide` |
| `content.drafts` | Content Manager | Count of draft Content entries; a status surface, not analytics | `content.read` | Contextual navigation to the existing Content workspace with `status=draft` | 120 | `standard` |
| `content.recent` | Content Manager | Bounded list of recently updated Content entries using the existing deterministic `updated_at`/`id` ordering | `content.read` | Normal navigation to the Content workspace | 140 | `wide` |
| `content.overview` | Content Manager | Existing Content management shortcut | `content.read` | Normal navigation to the Content workspace | 200 | `compact` |
| `taxonomy.overview` | Taxonomy Manager | Existing classification-management shortcut | Any of `taxonomy.create`, `taxonomy.update`, or `taxonomy.delete` | Normal navigation to the Taxonomy workspace | 300 | `compact` |

No first-wave widget is selected for Users & Access, Settings Manager, Module
Manager, Navigation Manager, Theme Manager, or Media Library. Their absence
is an evidence-based scope decision, not a requirement that every manager
appear on Dashboard. A contributor may provide zero widgets, one widget, or
multiple materially distinct widgets.

The Content widgets are bounded management information and navigation surfaces.
`content.recent` is not an activity feed, notification stream, or reporting
system. WU2 will implement the selected manager contributions through the
existing public Content contract; WU1 does not implement those providers.

## Ownership and architecture

M3.9 Internal Dashboard remains Core/Admin Shell-owned. No dedicated Dashboard
module is created by this preparation contract.

Core/Admin Shell retains:

* the route and request boundary;
* the `admin.access` authorization entry point;
* Admin navigation integration;
* shell and page rendering;
* shared Admin presentation infrastructure;
* request-scoped dashboard composition.

Manager modules retain ownership of their repositories, permissions, domain
calculations, persistence, and source data. Dashboard composition must use
explicit public contribution or service boundaries. Dashboard must not read
private manager repositories, private module files, or manager-owned database
tables directly.

Whether a standalone widget could become a future module/package type is an
explicit non-decision. M3.9 permits module/manager and Core contributions but
does not adopt, defer, or create an installable-widget taxonomy or
infrastructure.

## Widget composition, permissions, and failure behavior

The Internal Dashboard is a dynamic, permission-aware widget composition
surface. Dashboard layout must not assume a fixed set or fixed number of
modules. Each contributor may provide zero or more widgets, and a single
manager or module may provide multiple widgets when they serve materially
distinct purposes. Core/Webcore-owned capability may also contribute widgets.

A widget may provide information or status, navigation to an appropriate
management surface, contextual navigation with an applicable filter or state,
or a bounded quick administrative action. Complex management workflows remain
owned by their manager surface and must not be recreated as mini-applications
inside Dashboard widgets. M3.9 is not an analytics or reporting platform.

The Dashboard uses a responsive bounded grid. Each widget has a bounded
footprint: the contributor defines a default or preferred footprint, and
Dashboard validates it against supported minimum, maximum, and allowed sizing
rules. WU1 locks the representation as a semantic footprint with bounded
`inline_span` and `block_span` units. The M3.9 baseline permits only the
semantic presets `compact` `(1,1)`, `standard` `(2,1)`, and `wide` `(2,2)`;
these are abstract spans, not a desktop CSS column count. Missing legacy
footprint metadata maps to `compact` for compatibility. Invalid, duplicate,
non-integer, zero, negative, or out-of-range spans are rejected and the
affected contribution is not composed. Exact CSS mapping, supported desktop
grid column count, and placement algorithm are finalized in WU3 from existing
Admin Shell layout evidence. WU1 does not lock an exact desktop column count.
This contract does not permit arbitrary
unrestricted width or height values.

For the M3.9 baseline, layout is system-controlled. Widget priority determines
the deterministic placement sequence, widget footprint determines occupied
grid area, and stable registration/order remains the tie-breaker where needed.
Packing must not silently destroy the intended logical priority hierarchy merely
to fill every visual gap.

On narrower supported widths, the system preserves logical priority and
registration order, reflows widgets into the available flow, and collapses
multi-span footprints to the available single-column presentation when needed.
Exact breakpoint thresholds and CSS details remain WU3 decisions.

User drag-and-drop repositioning, bounded user resizing, per-user layout
persistence, default/reset behavior, and responsive reconciliation are
deliberately deferred from the M3.9 baseline; see `DI-M3.9-01`.

## Contribution, permissions, and failure behavior

Dashboard contributions are:

* request-scoped;
* explicitly registered or exposed through a public service contract;
* cardinality `0..n` per contributor;
* permission-aware using the existing runtime permission source of truth;
* identified by stable unique identities;
* ordered deterministically through explicit priority/order rules;
* bounded by validated footprint rules; and
* limited to controlled presentation data, approved internal links, contextual
  navigation, or bounded quick actions.

The smallest compatible evolution is to evolve `AdminDashboardRegistry` in
place. Its existing stable ID, registration, permission, priority, duplicate,
root-relative URL, and `itemsFor()` behavior remain the foundation. The WU2/WU3
implementation may add owner identity, widget purpose/content metadata,
validated footprint, contextual-navigation data, and bounded-action metadata
to that same registry contract with compatibility defaults for the current
shortcut entries. No parallel dashboard registry is authorized.

M3.9 introduces no second dashboard permission system. A contribution is
hidden when its existing required permission is unavailable. A contribution
failure must not take down unrelated dashboard contributions or expose raw
exception, path, SQL, storage, or module details. The implementation must use
the existing safe failure and diagnostics boundaries where applicable.

## Explicit exclusions

M3.9 does not authorize:

* analytics, charts, reporting, KPIs, observability, or external monitoring;
* notifications, activity feeds, queues, workers, schedulers, or event
  infrastructure without a separately proven requirement;
* user-selectable widgets, drag-and-drop layout, per-user layouts, bounded
  user resizing, or a dashboard builder in the M3.9 baseline;
* a database-backed dashboard layout or widget configuration model;
* direct repository or database access across manager boundaries;
* a dedicated Dashboard module;
* treating `widget` as a Module Manager module type or installable package;
* a new generic Core abstraction without a concrete reusable requirement;
* frontend Theme integration or public dashboard behavior;
* schema, provisioning, package, installer, or dependency changes unless a
  later approved implementation decision proves them necessary;
* reopening or expanding M3.8.

## Four domain work units

The approved M3.9 structure contains exactly four domain work units.

### WU1 — Dashboard Contract and Information Architecture

Objective: finalize the first-wave widget inventory, hierarchy, density intent,
widget purpose and interaction boundary, `0..n` contribution cardinality,
stable contribution identity/order rules, bounded footprint rules,
grid/composition semantics, responsive collapse/reflow rules, and minimal
contribution payload/service boundary.

Scope: contract-level decisions and focused boundary tests within the accepted
Core/Admin Shell ownership model, including priority semantics, contributor
default/preferred footprint validation, permission and failure behavior, and
the boundary for information, navigation, contextual navigation, and bounded
quick actions. WU1 must not pretend that concrete data fields, manager
adapters, exact grid column count, placement algorithm, or view implementation
details are already fixed.

Dependencies: the completed Admin Shell, authorization model, existing
dashboard registry, and public manager boundaries.

Exclusions: implementation of manager contributions, user-customizable layout,
analytics, schema, and generic platform expansion.

Validation and completion evidence: an approved first-wave widget inventory,
contribution boundary record, cardinality/purpose/priority/footprint/grid and
responsive rules, permission/failure rules, and focused contract tests or
source evidence.

### WU2 — Existing Manager Contributions

Objective: connect only approved, useful widgets from existing manager and
Core capabilities through explicit public contribution/service boundaries.

Scope: bounded `0..n` widget contributions from proven manager and Core
capabilities selected by WU1. Each contribution retains its owner-defined
calculation, authorization meaning, purpose, priority, and preferred footprint.

Dependencies: WU1 and the public APIs of the selected manager modules.

Exclusions: private repository access, new manager capabilities, speculative
metrics, complex workflows inside widgets, user layout persistence, and
cross-module schema ownership.

Validation and completion evidence: contribution tests, permission matrices,
stable ordering checks, disabled/unavailable-module behavior, and failure
isolation evidence.

### WU3 — Dashboard Presentation and Shell Integration

Objective: render the approved widget composition and dashboard hierarchy
through the existing Admin Shell.

Scope: bounded responsive grid, widget footprints, system-controlled priority
placement, sections/panels/cards, density, headings, empty states, safe
failure states, navigation placement, escaping, and responsive
collapse/reflow using existing Admin UI patterns.

Dependencies: WU1, WU2, and the completed Admin Shell presentation contract.

Exclusions: broad Admin redesign, frontend Theme assets, user-customizable
layouts, exact grid sizing beyond the approved rules, and new shared UI
infrastructure without concrete reuse evidence.

Validation and completion evidence: focused rendering and accessibility tests,
configured-path checks, responsive markup/source review, and exact-page
browser evidence where required.

### WU4 — Hardening, Regression, and Closure

Objective: validate the integrated M3.9 behavior and record complete closure
evidence.

Scope: authorization, contribution failure isolation, empty and partial
availability states, affected Admin regressions, documentation, and final
containment review.

Dependencies: WU1–WU3 and the separate Admin Shell design-adjustment
checkpoint.

Exclusions: unrelated milestone validation, release, tag, publication, and a
full M3.8 rerun without a concrete regression signal.

Validation and completion evidence: focused M3.9 gate, affected regression
results, responsive/accessibility acceptance, documented limitations, and
closure record.

## Separate Admin Shell design-adjustment checkpoint

The required Admin Shell design-adjustment checkpoint is horizontal governance
work and is separate from the four domain work units. It is not a fifth domain
work unit and must not be silently counted inside WU1–WU4.

The checkpoint reviews dashboard hierarchy, density, placement, responsive
behavior, accessibility, permission-aware navigation, active state, empty
states, and failure presentation. It may conclude `NO CHANGE REQUIRED`,
`review only`, `retouch required`, or `redesign required`.

The locked execution sequence is:

```text
WU1
-> WU2
-> WU3
-> Admin Shell design-adjustment checkpoint
-> WU4
```

The checkpoint does not authorize route, permission, schema, ownership, Core
architecture, or manager behavior changes.

## Deferred Item dispositions

No Deferred Item is adopted for M3.9 preparation. Registry status and target
remain unchanged.

| Item | Disposition | M3.9 finding |
|---|---|---|
| `DI-M3-ADMIN-UX-01` | KEEP DEFERRED | The M3.9 checkpoint is narrower and does not adopt broad Admin UX follow-up work. |
| `DI-M3.8-WU6-01` | KEEP DEFERRED | M3.9 presentation work does not reopen Media refinement. |
| `DI-M3.7-WU5-01` | NOT APPLICABLE | No M3.9 dependency. |
| `DI-M3.6-01` | NOT APPLICABLE | No dashboard dependency. |
| `DI-M3.5-01` | NOT APPLICABLE | No dashboard dependency. |
| M2.3 branding/theme presentation deferrals | NOT APPLICABLE | Dashboard remains independent of frontend Theme and branding expansion. |

### Deferred Item — DI-M3.9-01

- **Title:** User-customizable Dashboard layout
- **Status:** Deferred
- **Target:** Unscheduled
- **Source:** M3.9 Internal Dashboard preparation contract
- **Detail:** Drag-and-drop placement, bounded user resizing, per-user layout
  persistence, default/reset behavior, and responsive reconciliation.
- **Reason:** The baseline composition and widget contracts should stabilize
  before persistent user customization is introduced.
- **Impact:** Non-blocking for the M3.9 baseline.
- **Revisit trigger:** A stable baseline Dashboard composition plus a concrete
  requirement for persistent user customization.

## Validation and acceptance strategy

Focused automated validation will cover dashboard authorization, zero-to-many
widget contribution cardinality, permission filtering, stable identity,
duplicate handling, deterministic priority/order, bounded footprint validation,
escaping, safe links, permitted widget purposes, empty states, contributor
failure isolation, disabled-module behavior, configured Admin paths, and
affected Admin Shell regressions.

Browser and human review will cover authenticated representative permission
states, empty and partial-availability states, desktop and narrow mobile
layouts, keyboard/focus behavior, readable density, touch targets, and
consistency with the approved Admin Shell. Existing project conventions,
including narrow mobile widths, the 720px breakpoint, normal desktop widths,
and 200% zoom where available, remain applicable.

No runtime, browser, package, install, provisioning, or full M3.8 validation
is required for this documentation-only preparation task.

## Implementation branch strategy

If preparation is accepted for implementation, create:

```text
feature/m3.9-internal-dashboard
```

The WU1 implementation branch is
`feature/m3.9-internal-dashboard`, created from the verified preparation
anchor `d39957fa0e8f3704649c32b1e64dbd3b28cdf0c4`. WU2–WU4 have not started.
Any later implementation branch must branch from the then-current verified
preparation-complete `main` anchor; this contract does not authorize branching
from an obsolete historical commit.

## Documentation and closure

Implementation must preserve the four-domain-unit accounting and the separate
Admin Shell checkpoint. Any discovered requirement for schema, provisioning,
generic Core capability, new permission semantics, or a materially different
ownership boundary requires a separately reviewed decision before scope is
expanded.
