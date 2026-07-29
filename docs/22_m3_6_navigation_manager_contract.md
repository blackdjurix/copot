# M3.6 Navigation Manager Preparation Contract

## Purpose and Status

M3.6 prepares the existing platform for a Navigation Manager that owns
navigation data and management behavior without moving presentation ownership
into Navigation. This is a preparation contract and scope lock; implementation
has not started.

M3.5 Taxonomy Manager remains closed and `NRP CONFIRMED`. M3.6 preparation is
`NRP CONFIRMED`. Work Unit 1 — Contract, Provisioning, and Compatibility
Foundation implementation and primary validation are complete on
`feature/m3.6-navigation-manager` at
`38d75fa1d676371b5c78e392871649a663e573db`
(`feat(m3.6): establish navigation WU1 foundation`). Focused WU1 compatibility
passed 23 assertions. WU1 is `NRP CANDIDATE`; WU2 is not started and is the
next separately authorized gate. Full M3.6 is `NRP NOT REACHED`. Release, tag,
and publication remain separately authorized.

The contract preserves the approved M3 sequence and the ownership decisions in
`docs/16_m3_core_freeze_and_module_contract.md`. It records the accepted
direction, boundaries, compatibility expectations, six responsibility-level
work units, validation plan, and lifecycle gates. Exact implementation files
and internal tasks remain just-in-time decisions within an approved work unit.

## Milestone Position

```text
M3.5 Taxonomy Manager (NRP CONFIRMED)
->
M3.6 Navigation Manager preparation (NRP CONFIRMED)
->
M3.6 Work Unit 1 (NRP CANDIDATE)
->
M3.6 Work Unit 2 (not started; next separately authorized gate)
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

The approved WU1 Core touch is limited to adding `navigation` to the existing
`InstallerFinalizer::BASELINE_MODULES` lifecycle so fresh installs use the
existing ModuleManager install-and-enable path. No generic Core navigation
abstraction, migration framework, or broader Installer redesign is introduced.
Any further Core change requires a concrete compatibility defect and separate
approval.

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

Complete: WU1 established the Navigation manifest, provider-neutral resolver
interface, registry, resolved render-item contract, three-table persistence
foundation, `navigation.manage` provisioning, idempotent existing-install
upgrade artifact, fresh-install baseline ModuleManager activation, package
inclusion, and clean-install expectation update. The hierarchy-capable schema
records the locked five-level invariant; traversal enforcement, move/reorder
behavior, and custom-URL validation remain deferred. Focused compatibility
passed 23 assertions, and WU1 is `NRP CANDIDATE` pending independent NRP
evaluation.

### Work Unit 2 — Navigation Domain Service and Hierarchy

Not started. WU2 is the next separately authorized gate for the
Navigation-owned domain service, menu/item hierarchy, deterministic ordering,
visibility metadata, and safe lifecycle behavior required by the approved
contract.

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

Preparation is `NRP CONFIRMED`. WU1 primary validation passed: focused WU1
compatibility (23 assertions), direct Module Manager provisioning, Content
provisioning, M3.5 WU1 Taxonomy compatibility, package-builder, distribution,
and clean-install checks, PHP lint, and `git diff --check`. Production Taxonomy
targets remain deferred. No runtime or browser validation was required for WU1.

Preparation is `NRP CONFIRMED`. WU1 is `NRP CANDIDATE` after implementation,
primary validation, and documentation closure. Full M3.6 remains `NRP NOT
REACHED` until separately authorized later work units, focused validation,
applicable runtime/browser evidence, Git delivery, and final NRP evaluation are
complete.

WU1 is closed to further scope expansion. It does not authorize Content resolver
implementation, production Taxonomy targets, Theme `navigation_locations`
declaration or consumption, Admin routes/views, hierarchy domain services,
five-level traversal enforcement, custom-URL validation, `AdminNavigation`
changes, generic Core work, runtime work, release artifacts, tags, or
publication.
