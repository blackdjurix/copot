# Copot — Core Modules & Dashboard Refinement Concept

Status: Concept / Future Architecture  
Project: Copot  
Generated: 2026-08-04 16:17:38 WIB  
Scope: Post-M3 refinement umbrella for completed Core Modules and the Internal Dashboard

## 1. Purpose

This concept defines a future refinement program for already-complete Core Modules and the Internal Dashboard.

It coordinates intentional improvements that should not reopen valid M3 closures.

Primary themes:

1. Module Manager consumes Module Package Lifecycle for package-based Module management.
2. Existing Core Modules are reviewed for meaningful module-owned Dashboard widgets.
3. Core Module Admin surfaces receive intentional quality/UI/interaction refinement.
4. Dashboard evolves as the first consumer of the reusable Widget Layout capability.

This concept does not authorize implementation or assign an MR.x number.

## 2. Refinement Classification

This work is refinement when:

- the original Core Module baseline remains valid;
- no original acceptance promise is proven false;
- the requested change intentionally improves capability or interaction;
- the originating milestone is complete/closed.

If future evidence proves an original milestone closure invalid, that issue must be classified separately.

## 3. Architectural Dependencies

```text
Package Lifecycle Foundation
→ Module Package Lifecycle

Future Widget Layout Contract
→ Module widget contribution
→ Dashboard layout behavior

Multi-Installation Isolation Foundation
→ stable installation/database/runtime boundaries

Core Modules & Dashboard Refinement
→ consumes the above
```

A refinement milestone must not hide a major platform capability simply because its first UI consumer is a Core Module.

## 4. Track A — Module Manager Package Management

Module Manager should become the Admin/operator surface for Module Package Lifecycle after that lifecycle target exists.

Potential capability:

- Install Module from package;
- package identity/version display;
- compatibility result;
- dependency/conflict result;
- install result;
- repair;
- patch/update/upgrade;
- package-source diagnostics;
- module migration outcome;
- enable/disable state where applicable.

Conceptually:

```text
Module Manager UI
→ Module Package Lifecycle service
→ shared staging / compatibility / migration / health foundation
```

Module Manager must not own generic ZIP extraction, staging security, lifecycle locking, or generic migration infrastructure.

## 5. Track B — Module-Owned Dashboard Widgets

Every Core Module should be reviewed for Dashboard widget applicability.

A module may provide:

```text
0..n widgets
```

No widget is mandatory.

A widget exists only when the module has information, status, contextual navigation, or a bounded action that is materially useful at Dashboard overview level.

Multiple widgets from one module must serve materially distinct purposes.

Examples remain illustrative only:

```text
Content Manager
→ possible content-status summary
→ possible recent-content widget

Users & Access
→ possible access/users summary

Media Library
→ possible media/storage overview

Redirect Manager
→ possibly no widget

Form Manager
→ possible submission/operational widget if justified
```

Exact inventory is not locked here.

## 6. Widget Ownership

Each module owns:

- data meaning;
- data provider;
- permission requirements;
- action semantics;
- empty/unavailable/error behavior;
- initial semantic size.

Dashboard owns composition/rendering, not business meaning.

Initial rule:

```text
widget size = module-defined
user resize = unavailable
```

## 7. Track C — Core Module Admin Refinement

Core Module surfaces may be intentionally refined after M3 closure.

Review dimensions may include:

- information hierarchy;
- table/list readability;
- form grouping;
- action placement;
- state clarity;
- empty/error states;
- responsive behavior;
- Admin Shell consistency;
- permission-aware interaction;
- accessibility;
- navigation/context clarity.

Refinement preserves module ownership and valid behavior unless a separately approved functional change is required.

## 8. Track D — Dashboard Refinement

Dashboard evolves from a quick-navigation/status surface toward a composable operational overview.

Initial refinement may include:

- registry/provider contract adoption;
- module-owned widgets;
- permission-aware visibility;
- semantic fixed sizes;
- responsive auto-arrangement;
- controlled widget states;
- clearer operational framing.

It does not immediately include the full interactive layout editor.

## 9. Dashboard Layout Evolution

### Stage 1 — Widget Foundation + Auto Layout

- module `0..n` contribution;
- module-defined size;
- permission-aware resolution;
- priority;
- responsive auto-layout;
- no drag/drop;
- no user resize;
- no persistence.

### Stage 2 — Layout Persistence + Management

Potential scope:

- persisted position;
- authorized show/hide;
- reset/default;
- deterministic reconciliation when widgets appear/disappear;
- persistence ownership decision.

### Stage 3 — Android-Style Interactive Grid

Potential scope:

- drag/drop;
- grid snapping;
- collision/reflow;
- accessible keyboard movement;
- breakpoint behavior;
- optional constrained resize if later justified.

These stages may become separate MR.x milestones after preparation.

## 10. Why Widget Layout Is Separate

`Future Widget Layout Contract` defines how the reusable widget/grid system works.

`Core Modules & Dashboard Refinement Concept` defines where that system is applied and which completed surfaces are refined.

```text
Widget Layout
= platform/layout contract

Core Modules & Dashboard Refinement
= adoption/refinement program
```

They are related but should remain separate concepts.

## 11. Why Module Package Lifecycle Is Separate

Module Manager is the natural operator surface for Module packages, but Module Package Lifecycle is a package-target capability.

Separating them prevents lifecycle infrastructure from becoming UI-local logic.

```text
Module Package Lifecycle
→ implemented first

Module Manager package-management refinement
→ consumes it later
```

## 12. Relationship to Multi-Installation Isolation

Core Modules refinement should occur after cross-cutting installation namespace/isolation boundaries are stable where they affect module persistence, migrations, routing, sessions, storage, or base paths.

Exact ordering remains roadmap-controlled.

## 13. Candidate Refinement Inventory

Preparation should inspect all completed Core Modules:

- Users & Access;
- Settings Manager;
- Module Manager;
- Content Manager;
- Taxonomy Manager;
- Navigation Manager;
- Theme Manager;
- Media Library;
- Internal Dashboard;
- Redirect Manager;
- Form Manager.

For each surface, classify:

```text
UI refinement required?
functional refinement required?
widget applicability?
cross-module dependency?
platform capability dependency?
deferred-item applicability?
```

## 14. Widget Applicability Record

For each Core Module, preparation should record one disposition:

```text
REQUIRED
OPTIONAL / JUSTIFIED
NOT APPLICABLE
DEFERRED
```

If widgets are adopted, record:

- widget ID;
- purpose;
- permission;
- data source;
- fixed initial semantic size;
- priority;
- state behavior;
- Dashboard action/navigation if any.

No fake metric is permitted merely to fill space.

## 15. Module Manager Refinement Boundary

Potential adopted capability after Module Package Lifecycle exists:

```text
Install from package
Repair
Patch
Update
Upgrade
Compatibility/dependency feedback
Lifecycle result/history summary
```

Potentially excluded until separately adopted:

- remote marketplace;
- automatic remote updates;
- package signing/trust store;
- multi-provider capability arbitration;
- paid package licensing;
- generic ecosystem browser.

## 16. Core Module Refinement Boundary

Existing modules remain the same modules.

Do not create replacement modules merely because Admin management surfaces evolve.

Changes must preserve:

- public ownership;
- permissions;
- schema ownership;
- declared dependencies;
- cross-module boundaries;
- route ownership.

## 17. Dashboard Refinement Boundary

Dashboard should:

- resolve contributions;
- enforce module/permission state;
- compose widgets;
- isolate provider failure;
- render responsive layout.

Dashboard should not:

- own module repositories;
- own module business rules;
- fabricate data;
- expose unauthorized widgets;
- become a universal page builder.

## 18. Candidate Milestone Shape

Likely conceptual shape:

```text
MR-A
Core Module UI + Widget Contribution Foundation
+ Dashboard auto-layout adoption

MR-B
Dashboard Layout Persistence + Management

MR-C
Android-style Dashboard Grid Editor
```

Module Package Lifecycle should be available before the Module Manager package-management portion of MR-A.

If dependencies are not ready, preparation must resolve them explicitly instead of hiding missing infrastructure inside refinement.

## 19. Acceptance Direction

A future refinement program should prove:

- valid M3 baselines are not silently reopened;
- Module Manager consumes lifecycle through a public boundary;
- module package handling is not UI-local ZIP logic;
- each Core Module has an explicit widget applicability decision;
- modules may contribute `0..n` widgets;
- widget permissions and module state are enforced;
- initial widget size is module-defined;
- Dashboard auto-arranges visible widgets;
- missing widgets leave no placeholder;
- provider failures are isolated;
- Core Module UI refinements preserve module contracts;
- Dashboard works without user layout editing in Stage 1.

## 20. Open Decisions

Preparation still needs to decide:

- exact first MR.x scope;
- whether Core Module UI refinement and first widget adoption fit one milestone;
- concrete widget inventory per module;
- exact semantic size set;
- exact Dashboard grid mapping;
- Stage-2 persistence ownership;
- site-wide vs per-user persistence;
- whether Stage 3 supports resizing;
- whether Dashboard needs another refinement after Core Module rollout;
- exact Module Manager package actions adopted in the first refinement.

## 21. Non-Authorization

This concept does not:

- assign an MR.x number;
- alter the roadmap;
- reopen M3 milestones;
- adopt any specific widget;
- authorize Module Package Lifecycle implementation;
- authorize Dashboard drag/drop;
- authorize layout persistence;
- authorize UI changes;
- authorize branch creation.