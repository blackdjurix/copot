# M3.6 Navigation Manager Preparation Contract

## Purpose and Status

M3.6 prepares the existing platform for a Navigation Manager that owns
navigation data and management behavior without moving presentation ownership
into Navigation. This is a preparation contract and scope lock; implementation
has not started.

M3.5 Taxonomy Manager remains closed and `NRP CONFIRMED`. M3.6 preparation is
`NRP CANDIDATE`. Full M3.6 is `NRP NOT REACHED`. No implementation branch,
production change, schema implementation, upgrade SQL, runtime work, release,
tag, or publication is authorized by this document.

The contract preserves the approved M3 sequence and the ownership decisions in
`docs/16_m3_core_freeze_and_module_contract.md`. It records the accepted
direction, boundaries, compatibility expectations, six responsibility-level
work units, validation plan, and lifecycle gates. Exact implementation files
and internal tasks remain just-in-time decisions within an approved work unit.

## Milestone Position

```text
M3.5 Taxonomy Manager (NRP CONFIRMED)
->
M3.6 Navigation Manager preparation (NRP CANDIDATE)
->
M3.6 Navigation Manager implementation (NRP NOT REACHED)
->
M3.7 Theme Manager
```

M3.6 follows M3.5 so Navigation can consume proven domain-owned target
contracts. M3.5 remains closed and is not reopened by this preparation.

## Authority and Ownership

Authority is applied in this order:

1. project instructions and this accepted M3.6 contract;
2. `docs/16_m3_core_freeze_and_module_contract.md`;
3. existing public Core, Admin Shell, Content, Taxonomy, and Theme contracts;
4. committed implementation behavior and compatibility evidence;
5. `docs/19_m3_admin_shell_design_adjustment_contract.md` for presentation;
6. approved implementation decisions made within the locked work units.

The future Navigation module owns navigation items, menu structures, menu
locations as data, ordering metadata, visibility metadata, target references,
resolution orchestration, and Navigation management behavior. Navigation must
not reach into another module's private repository or schema to resolve a
target.

Themes declare supported `navigation_locations` and own presentation,
composition, markup, styling, responsive behavior, and design adjustments.
Navigation supplies a resolved, ordered contract for a declared location; it
does not render frontend navigation or own Theme presentation.

Content is the baseline optional target provider. Content may contribute an
explicit public target contract or resolver for existing Content targets.
Navigation must remain usable when Content is disabled or unavailable.

Production Taxonomy targets are deferred. M3.6 preparation must not make
Taxonomy a required provider, add Taxonomy target resolution, or change the
closed M3.5 contract. Future providers must opt in through an explicit public
contract owned by the provider.

`AdminNavigation` remains the existing separate Admin Shell navigation
contract. It is not replaced by public Navigation data, and M3.6 must not
silently merge Admin management navigation with frontend menu data.

No Core change is currently justified. M3.6 must use existing public Core and
Admin Shell contracts unless a concrete compatibility defect is demonstrated
and separately approved; speculative abstractions and framework-wide changes
are excluded.

## Product and Compatibility Contract

The preparation establishes these requirements for later implementation:

* Navigation data has a clear module-owned boundary and a stable public read
  contract.
* A menu can be associated with a Theme-declared location and returns a
  deterministic order.
* Ordering, visibility, and target references are explicit data concerns;
  presentation remains Theme-owned.
* Target resolution is provider-contributed, fail-closed, and unavailable
  targets do not expose private storage details or create broken unsafe links.
* Optional Content integration is additive and must preserve behavior when
  Content is disabled.
* Production Taxonomy target integration remains deferred until a later
  approved contract.
* Existing `AdminNavigation` behavior, permission filtering, active-state
  handling, configured Admin paths, and module lifecycle behavior remain
  compatible.
* Authorization, CSRF, validation, escaping, request boundaries, and controlled
  failure handling follow established Admin and module contracts.

The baseline does not include a generic content editor, arbitrary provider
introspection, public APIs, search indexing, analytics, personalization,
multilingual routing, drag-and-drop requirements, frontend Theme rendering,
production Taxonomy targets, or a new Core navigation abstraction.

## Locked Work Units

The six work units are locked at responsibility level. They are preparation
planning units, not evidence that implementation has begun.

### Work Unit 1 — Contract, Provisioning, and Compatibility Foundation

Define the module boundary, public contracts, data invariants, compatibility
matrix, lifecycle expectations, and any narrowly justified provisioning work.
Do not introduce schema implementation or upgrade SQL during preparation.

### Work Unit 2 — Navigation Domain Service and Hierarchy

Implement only after separate authorization: the Navigation-owned domain
service, menu/item hierarchy, deterministic ordering, visibility metadata, and
safe lifecycle behavior required by the approved contract.

### Work Unit 3 — Content Target Resolver Integration

Add the baseline optional Content provider through an explicit public resolver
contract. Preserve Content ownership, disabled-Content compatibility, safe
unavailable-target behavior, and the deferral of production Taxonomy targets.

### Work Unit 4 — Admin Management Workspace

Provide the Navigation-owned Admin management workspace, with established
permission, CSRF, validation, escaping, PRG, configured-path, responsive, and
accessibility contracts. Keep `AdminNavigation` separate from managed public
navigation data.

### Work Unit 5 — Theme Consumption and Design-Adjustment Checkpoint

Consume Theme-declared `navigation_locations` through the public contract.
Themes own rendering and presentation. Review the Navigation workspace and
placement under the reusable Admin Shell design-adjustment governance; this
unit does not authorize a frontend Theme redesign.

### Work Unit 6 — Security, Milestone Regression, Documentation, and Lifecycle Closure

Complete focused security and compatibility validation, applicable Content and
Theme regressions, documentation closure, containment review, Git verification,
and the independent NRP decision. Release, tag, publication, and merge remain
separate authorizations.

## Validation and Lifecycle Gates

Preparation validation is documentation and contract review only. It requires
scope and ownership review, dependency and compatibility review, confirmation
of the six work units, confirmation that production Taxonomy targets remain
deferred, confirmation that no Core change is justified, and `git diff --check`.
No PHP, database, runtime, or browser tests are required for preparation.

Preparation may be classified `NRP CANDIDATE` only after this contract and the
exact work-unit breakdown are accepted. Full M3.6 remains `NRP NOT REACHED`
until separately authorized implementation, focused validation, applicable
runtime/browser evidence, documentation closure, Git delivery, and final NRP
evaluation are complete.

The preparation boundary is documentation-only. It does not authorize changes
to production code, `modules/navigation`, database schema or upgrade SQL,
Content, Taxonomy, Theme, Core, runtime state, release artifacts, tags, or
publication.
