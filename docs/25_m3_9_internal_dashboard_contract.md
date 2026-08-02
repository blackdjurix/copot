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

M3.9 implementation:
NOT STARTED

M3.9 implementation branch:
NOT CREATED
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

The exact first-wave information surfaces and the minimal contribution payload
shape are intentionally finalized in WU1. WU1 may refine those details only
within this contract's ownership, permission, failure, and exclusion
boundaries; it does not authorize speculative analytics or generic platform
infrastructure.

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

## Contribution, permissions, and failure behavior

Dashboard contributions are:

* request-scoped;
* explicitly registered or exposed through a public service contract;
* permission-aware using the existing runtime permission source of truth;
* identified by stable unique identities;
* ordered deterministically through explicit priority/order rules;
* limited to controlled presentation data and approved internal links.

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
* user-selectable widgets, drag-and-drop layout, per-user layouts, or a
  dashboard builder;
* a database-backed dashboard layout or widget configuration model;
* direct repository or database access across manager boundaries;
* a dedicated Dashboard module;
* a new generic Core abstraction without a concrete reusable requirement;
* frontend Theme integration or public dashboard behavior;
* schema, provisioning, package, installer, or dependency changes unless a
  later approved implementation decision proves them necessary;
* reopening or expanding M3.8.

## Four domain work units

The approved M3.9 structure contains exactly four domain work units.

### WU1 — Dashboard Contract and Information Architecture

Objective: finalize the first-wave information surfaces, hierarchy, density
intent, stable contribution identity/order rules, and minimal contribution
payload shape.

Scope: contract-level decisions and focused boundary tests within the accepted
Core/Admin Shell ownership model. WU1 must not pretend that concrete data
fields, manager adapters, or view implementation details are already fixed.

Dependencies: the completed Admin Shell, authorization model, existing
dashboard registry, and public manager boundaries.

Exclusions: implementation of manager contributions, analytics, schema, and
generic platform expansion.

Validation and completion evidence: an approved first-wave surface inventory,
contribution boundary record, permission/failure rules, and focused contract
tests or source evidence.

### WU2 — Existing Manager Contributions

Objective: connect only approved, useful data from existing manager modules
through explicit public contribution/service boundaries.

Scope: bounded contributions from proven manager capabilities selected by WU1.
Each contribution retains its module-owned calculation and authorization
meaning.

Dependencies: WU1 and the public APIs of the selected manager modules.

Exclusions: private repository access, new manager capabilities, speculative
metrics, and cross-module schema ownership.

Validation and completion evidence: contribution tests, permission matrices,
stable ordering checks, disabled/unavailable-module behavior, and failure
isolation evidence.

### WU3 — Dashboard Presentation and Shell Integration

Objective: render the approved dashboard hierarchy through the existing Admin
Shell.

Scope: sections, panels/cards, density, headings, empty states, safe failure
states, navigation placement, escaping, and responsive presentation using
existing Admin UI patterns.

Dependencies: WU1, WU2, and the completed Admin Shell presentation contract.

Exclusions: broad Admin redesign, frontend Theme assets, configurable layouts,
and new shared UI infrastructure without concrete reuse evidence.

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

## Validation and acceptance strategy

Focused automated validation will cover dashboard authorization, contribution
permission filtering, stable identity, duplicate handling, deterministic
ordering, escaping, safe links, empty states, contributor failure isolation,
disabled-module behavior, configured Admin paths, and affected Admin Shell
regressions.

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

The branch point is `main` at
`e21e7b281fecdb7619022be0457381a5ce31ce85`. No implementation branch exists
and no M3.9 implementation has started.

## Documentation and closure

Implementation must preserve the four-domain-unit accounting and the separate
Admin Shell checkpoint. Any discovered requirement for schema, provisioning,
generic Core capability, new permission semantics, or a materially different
ownership boundary requires a separately reviewed decision before scope is
expanded.
