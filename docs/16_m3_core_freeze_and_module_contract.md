# M3 Core Freeze and Module Contract

## Purpose

This document defines the governance and architecture boundary for Copot after the v0.12.0 Webcore release and before M3 Core Module implementation begins.

M3 Prep exists to prevent module development from silently widening Webcore responsibilities. The default development direction after v0.12.0 is module-first.

This document owns:

* Webcore maintenance-only policy;
* Core-change escalation rules;
* module ownership rules;
* cross-module interaction rules;
* dependency direction;
* Theme/Module boundaries;
* Navigation ownership direction;
* Media ownership direction;
* official and external module repository strategy;
* M3 Prep entry and exit rules.

M3 Prep has three stages:

```text
Stage 1
Governance + Architecture Lock
->
Stage 2
M3 Sequencing Lock
->
Stage 3
Final Review + Entry Audit
->
M3.1 Implementation
```

Stage 1 is documentation and architecture work only.

---

## Stable Webcore Baseline

Copot v0.12.0 is the stable Webcore baseline.

Webcore is maintenance-only by default after this release. M3 must build on the released platform instead of treating every module requirement as permission to expand Core.

Allowed Webcore changes are limited to:

* bug fixes;
* security fixes;
* compatibility fixes;
* runtime upgrades;
* performance improvements;
* extension-point corrections;
* architectural corrections;
* explicitly approved generic platform capabilities backed by concrete reusable requirements;
* future versioned maintenance, update, upgrade, or migration work.

The existence of a new module requirement does not by itself justify a Core change.

---

## Forbidden Webcore Expansion

Webcore must not absorb:

* module-specific business logic;
* module-specific database schema;
* module-specific Admin UI;
* module-specific workflow;
* module-specific repository or persistence behavior;
* storage behavior needed by only one module;
* hardcoded knowledge of a business domain;
* direct dependencies on M3 manager modules;
* convenience shortcuts that bypass module boundaries.

A requirement that belongs to one module remains in that module unless a generic reusable need is demonstrated.

---

## Core-Change Escalation Rule

Module needs must be evaluated in this order:

```text
1. Module-local design
2. Existing public Core service
3. Registry contribution
4. Event/listener pair with a real caller and consumer
5. Existing extension point
6. Explicit Core-change proposal
```

A Core-change proposal is valid only when all of the following are true:

* the requirement is concrete, not hypothetical;
* the solution is generic;
* the capability is reusable beyond one module;
* existing public contracts are insufficient;
* the change preserves dependency direction;
* the change is reviewed separately from the module implementation that discovered it;
* the change is classified as maintenance, correction, compatibility, security, performance, runtime, extension-point, or explicit platform capability work.

A module milestone must not hide a Core expansion inside its implementation scope.

---

## Module Ownership Contract

A module should own its own concerns wherever applicable:

* routes;
* permissions;
* services;
* repositories;
* migrations and schema ownership;
* Admin views;
* module assets;
* module tests;
* module metadata;
* module lifecycle behavior;
* module-specific version and changelog metadata when an independent lifecycle is introduced.

Modules must not:

* access private Webcore internals;
* read another module's private files directly;
* depend on another module's filesystem path;
* write directly into schema owned by another module without an approved shared contract;
* move business logic into themes;
* move module-specific behavior into Webcore for implementation convenience.

Existing Content and Taxonomy modules remain the same modules as their M3 management capabilities evolve. M3 does not create replacement duplicate modules merely because management surfaces expand.

### Module Classification Axes

Module classification uses two separate axes and the terms must not be treated as synonyms.

Functional classification describes architectural responsibility:

* Core Modules provide reusable first-party management capabilities used across site types.
* Business/Application Modules implement specific domains or use cases.

Distribution and ownership classification describes provenance and repository direction:

* Official first-party modules are maintained as part of the Copot project and remain in the monorepo during early M3.
* External, community, and client-specific modules should target independent repositories.

A module may therefore be both a Core Module and an official first-party module. An official first-party module is not automatically a Core Module, and a Business/Application Module is not automatically external.

---

## Cross-Module Interaction Contract

Cross-module interaction must use an explicit boundary.

Approved forms include:

* public service contracts;
* registry contributions;
* event/listener integration with real callers and consumers;
* documented module APIs;
* declared dependencies;
* controlled data contracts.

A module may depend on another module only when the dependency is explicit and the required capability is public.

Hidden coupling through paths, private classes, private tables, or incidental implementation details is prohibited.

---

## Dependency Direction

The high-level architecture remains:

```text
Core Infrastructure
->
Platform Capabilities
->
Core Modules
->
Business / Application Modules
```

The consumption direction is:

```text
Modules
consume
Webcore public contracts
```

```text
Themes
consume
controlled platform contracts
and
module-provided render data
```

```text
Module A
interacts with
Module B
only through explicit public boundaries
```

Forbidden directions include:

```text
Webcore
->
Content-specific logic
```

```text
Webcore
->
Media Library workflow
```

```text
Webcore
->
Navigation Manager UI
```

```text
Theme
->
Module repositories or private storage
```

Shared platform services must not depend on user-facing manager modules or business domains.

---

## Theme and Module Boundary

Themes own frontend presentation.

Themes may:

* declare presentation locations;
* render controlled platform data;
* render module-provided data;
* provide view overrides through approved namespace resolution;
* use documented asset and branding contracts.

Themes must not:

* contain module business logic;
* query module repositories directly;
* access databases directly;
* act as service containers;
* own module lifecycle behavior;
* write module state.

A module may provide frontend data or renderable content, but the Theme retains presentation ownership.

---

## Navigation Ownership Decision

Navigation is module-owned by default.

The future Navigation boundary owns:

* navigation data;
* menu structures;
* menu locations;
* menu-item references;
* item ordering;
* navigation visibility metadata;
* management UI.

The Navigation boundary must not require built-in domain knowledge of every possible target type. Domain-owned targets may contribute resolution behavior through explicit public contracts, registries, or resolvers owned by the modules that understand those targets. For example, Content may resolve Content targets and Taxonomy may resolve taxonomy targets without Navigation reaching into their private repositories or schema.

Themes own:

* declaration of navigation locations;
* visual rendering;
* layout and presentation;
* responsive behavior.

The Theme/Navigation integration must use an explicit documented consumption contract.

A generic Webcore Navigation capability may be proposed only when a concrete reusable need cannot be solved through module ownership and existing extension points.

M3 Prep Stage 2 determines the final sequence position of Navigation Manager. Stage 1 does not implement Navigation runtime behavior.

---

## Media Ownership Decision

Media Library is module-owned.

The existing `SiteAssetStorage` boundary remains branding-specific and limited to fixed Logo/Favicon lifecycle behavior. It must not be silently widened into a generic media engine.

Media Library may own:

* general upload workflows;
* browsing and selection;
* media metadata;
* media management UI;
* media references;
* usage visibility;
* module-level media picker behavior.

Generic media or image-processing infrastructure may enter Webcore only when multiple concrete consumers prove a reusable platform requirement.

Possible future generic infrastructure may include storage contracts, image processing, delivery abstraction, or variants, but none of these become active Webcore commitments during M3 Prep Stage 1.

M3 Prep Stage 2 determines the final sequence position of Media Library.

---

## Official and External Module Repository Strategy

During early M3:

```text
copot monorepo
->
official first-party modules remain under modules/
```

This keeps contract development, regression, and integration verification close while module boundaries are still being proven.

Official modules should still be structured so later independent packaging remains possible.

Repository extraction may be considered only after:

* module lifecycle is stable;
* dependency declarations are mature;
* versioning can be independent;
* standalone module testing is practical;
* release cycles genuinely differ;
* package composition is defined.

External, community, client-specific, and non-core modules should target independent repositories.

M3 Prep Stage 1 does not split repositories or create a marketplace/package repository.

---

## M3 Prep Non-Goals

M3 Prep Stage 1 does not:

* implement M3.1;
* add a new module;
* add database schema;
* change runtime code;
* refactor Webcore;
* redesign Router;
* redesign Authentication or Permissions;
* redesign Module lifecycle;
* implement Navigation Manager;
* implement Media Library;
* implement generic media infrastructure;
* implement an updater;
* create a marketplace;
* split official modules into separate repositories;
* add dependencies;
* add frontend build tooling.

If Stage 1 reveals a required runtime or Core change, document the decision and schedule separate approved work. Do not implement it inside Stage 1.

---

## Stage 2 Sequencing Lock

Stage 2 owns M3 Sequencing Lock and is documentation and planning work only.

The approved sequence is:

| Milestone | Capability | Planning Batch Envelope | Risk |
|---|---|---:|---|
| M3.1 | Users & Access | 5 | High |
| M3.2 | Settings Manager | 4 | Medium |
| M3.3 | Module Manager | 5 | High |
| M3.4 | Content Manager | 6 | High |
| M3.5 | Taxonomy Manager | 5 | Medium-High |
| M3.6 | Navigation Manager | 6 | High |
| M3.7 | Theme Manager | 6 | High |
| M3.8 | Media Library | 7 | Very High |
| M3.9 | Internal Dashboard | 4 | Medium |
| M3.10 | Redirect Manager | 4 | Medium |
| M3.11 | Form Manager | 7 | Very High |

Total planning envelope: 59 batches.

The planning envelope is not an immutable implementation count. Exact batch structure is locked just-in-time before each milestone begins.

### Sequencing Rationale

The initial management foundation is:

```text
Users & Access
->
Settings Manager
->
Module Manager
```

The domain and presentation progression is:

```text
Content Manager
->
Taxonomy Manager
->
Navigation Manager
->
Theme Manager
```

Content and Taxonomy provide real domain owners. Navigation can then prove target resolver contributions through explicit boundaries, and Theme Manager can consume stable Navigation plus module-provided render contracts.

Media Library follows Content, Navigation, and Theme progression so general media behavior is driven by proven consumers instead of hypothetical Core expansion.

Internal Dashboard follows the major management surfaces so it aggregates real data. Redirect Manager remains an operational capability after core content and routing consumers are mature. Form Manager remains last because public input, validation, submission persistence, notification, uploads, spam, privacy, and security create the broadest late-M3 operational surface.

### Sequence Change Rule

The approved order may change only when concrete evidence proves:

* a hidden hard dependency;
* a reusable consumer requirement;
* an architecture prerequisite;
* a security prerequisite;
* a migration constraint;
* a concrete integration dependency.

A sequence change must:

1. document the reason;
2. identify affected milestones;
3. review dependency and risk impact;
4. update `docs/03_roadmap.md`;
5. update the active target in `AGENTS.md`;
6. avoid silent reordering.

Milestones must not be reordered merely because another feature appears more attractive, easier, or convenient.

### Parallelization Rule

M3 milestones are sequential by default.

Parallel execution requires explicit approval and proof of:

* no unresolved dependency;
* no shared mutable contract;
* no overlapping schema ownership;
* no overlapping Core touchpoint;
* regression coverage capable of validating parallel integration.

Early M3 remains serial through at least M3.1-M3.3. Later parallelization may be reconsidered using actual milestone evidence.

### Just-in-Time Batch Lock Rule

Before each milestone begins:

1. audit current repository state;
2. review completed dependency evidence;
3. identify newly proven consumers and integration requirements;
4. reassess Core-change and migration risk;
5. lock exact batch breakdown and milestone acceptance criteria.

This rule allows batch details to evolve without silently widening milestone scope.

### High Core-Risk Milestones

The following milestones require heightened Core-boundary review:

* M3.1 Users & Access;
* M3.3 Module Manager;
* M3.6 Navigation Manager;
* M3.7 Theme Manager;
* M3.8 Media Library;
* M3.11 Form Manager.

When one of these milestones discovers a possible Core requirement, module implementation must pause at that boundary and use the Stage 1 Core-Change Escalation Rule. The Core change must be reviewed separately before module work resumes.

---

## Stage 3 Handoff

Stage 3 owns Final Review + Entry Audit.

It must verify:

* documentation consistency;
* no stale active-M2 or active-Stage-1 wording in authoritative docs;
* Stage 1 governance and architecture rules remain consistent;
* Stage 2 approved sequence is consistent across authoritative docs;
* batch envelopes and risk labels are consistent;
* Sequence Change, Parallelization, and Just-in-Time Batch Lock rules are consistent;
* no unresolved architecture blocker;
* M3.1 target is Users & Access;
* M3.1 scope and acceptance criteria are explicit;
* allowed and forbidden Core touchpoints are explicit;
* test strategy is explicit;
* branch strategy is explicit;
* repository and worktree are ready.

M3.1 implementation must not start until Stage 3 passes.

---

## M3 Entry Criteria

M3 implementation may begin only when:

* Webcore freeze policy is documented;
* Core-change escalation rules are documented;
* module ownership boundaries are documented;
* cross-module interaction rules are documented;
* Navigation ownership direction is documented;
* Media ownership direction is documented;
* Theme/Module boundary is documented;
* repository strategy is documented;
* Stage 2 approved sequence is documented;
* Stage 2 sequence change control is documented;
* M3.1 target is confirmed as Users & Access;
* Stage 3 final review passes;
* M3.1 scope and acceptance criteria are explicit;
* no unresolved architecture blocker remains.

---

## Stage 2 Acceptance Criteria

Stage 2 is complete when:

* final M3.1-M3.11 milestone order is documented;
* dependency rationale is documented;
* planning batch envelope is documented;
* risk level is documented;
* Sequence Change Rule is documented;
* Parallelization Rule is documented;
* Just-in-Time Batch Lock Rule is documented;
* high Core-risk milestones are identified;
* Users & Access is confirmed as M3.1;
* Stage 3 audit targets are updated;
* no runtime code is changed;
* no schema is changed;
* no dependency is added;
* documentation remains internally consistent;
* `git diff --check` passes.

Stage 2 does not authorize M3 implementation.

---

## Stage 1 Acceptance Criteria

Stage 1 is complete when:

* authoritative project status points to M3 Prep;
* v0.12.0 is recorded as the stable Webcore baseline;
* Webcore maintenance-only policy is explicit;
* Core-change escalation is explicit;
* module ownership is explicit;
* cross-module interaction rules are explicit;
* dependency direction is explicit;
* Navigation ownership direction is explicit;
* Media ownership direction is explicit;
* official/external repository strategy is explicit;
* non-goals are explicit;
* no runtime code is changed;
* no schema is changed;
* no dependency is added;
* documentation remains internally consistent;
* `git diff --check` passes.

Stage 1 does not authorize M3 implementation.
