# M3.6 Navigation Manager Preparation Contract

## Purpose and Status

M3.6 prepares the existing platform for a Navigation Manager that owns
navigation data and management behavior without moving presentation ownership
into Navigation. This remains a preparation contract and scope lock; Work
Units 1 through 5 implementation is complete on the feature branch.

M3.5 Taxonomy Manager remains closed and `NRP CONFIRMED`. M3.6 preparation is
`NRP CONFIRMED`. Work Unit 1 — Contract, Provisioning, and Compatibility
Foundation implementation and primary validation are complete on
`feature/m3.6-navigation-manager` at
`38d75fa1d676371b5c78e392871649a663e573db`
(`feat(m3.6): establish navigation WU1 foundation`). Focused WU1 compatibility
passed 23 assertions. WU1 is `NRP CONFIRMED`. Work Unit 2 — Navigation
Domain Service and Hierarchy implementation and primary validation are complete
at `7e54d0309c8ca75f0f32200394fd2a271c8a9b83`
(`feat(m3.6): add navigation domain hierarchy service`); focused WU2 domain
validation passed 44 assertions. WU2 is `NRP CONFIRMED`. Work Unit 3 — Content
Target Resolver Integration implementation and primary validation are complete
at `3e387c5756636d08416880a8d8ec5ca1112cad75`
(`feat(m3.6): add content navigation target resolver`); focused WU3 resolver
validation passed 19 assertions. WU3 is `NRP CONFIRMED`. Work Unit 4 — Admin
Management Workspace implementation is complete at
`43085441a82ecc38941ca880b2dbbd6823f61215`
(`feat(m3.6): add navigation admin workspace`). Objective/runtime validation,
provisioning and upgrade rerun safety, source/runtime SHA-256 synchronization,
HTTP/session validation, and human acceptance passed. WU4 is `NRP CONFIRMED`;
full M3.6 is `NRP NOT REACHED`. Release, tag, publication, merge, and branch
cleanup remain separately authorized.

Work Unit 5 — Theme Consumption and Design-Adjustment Checkpoint is complete
at `b9febc49071d8997a26b0488ecde7bedc1748a72` (`feat(m3.6): consume navigation
in frontend themes`). The narrow Core frontend Theme context seam is
request-scoped, deterministic, frozen before dispatch, lazily composed, and
fail-closed. Core and public/Content routes do not depend directly on
Navigation; Themes own markup, styling, composition, and responsive
presentation. `supports.navigation_locations` is validated and used by
Navigation-owned consumption. Focused WU5 validation passed 15 assertions;
package-builder smoke passed 891 assertions. Runtime/browser evidence and
human acceptance passed for public home and Content pages, empty/no-assignment
states, provider disable/restore, console/network inspection, responsive
widths, true `200%` zoom, touch targets, and keyboard/focus usability. The WU4
Admin design-adjustment checkpoint is `NO CHANGE REQUIRED`. WU5 is `NRP
CONFIRMED`; full M3.6 remains `NRP NOT REACHED` pending separately authorized
integration and branch lifecycle closure. Production Taxonomy targets and Theme
Manager functionality remain deferred.

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
M3.6 Work Unit 1 (NRP CONFIRMED)
->
M3.6 Work Unit 2 (NRP CONFIRMED)
->
M3.6 Work Unit 3 (NRP CONFIRMED)
->
M3.6 Work Unit 4 (NRP CONFIRMED)
->
M3.6 Work Unit 5 (NRP CONFIRMED)
->
M3.6 Work Unit 6 (security closure complete; integration gate remains separate)
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

The six work units are locked at responsibility level. WU1 and WU2 have
implementation evidence; later units remain separately authorized gates.

### Work Unit 1 — Contract, Provisioning, and Compatibility Foundation

Complete: WU1 established the Navigation manifest, provider-neutral resolver
interface, registry, resolved render-item contract, three-table persistence
foundation, `navigation.manage` provisioning, idempotent existing-install
upgrade artifact, fresh-install baseline ModuleManager activation, package
inclusion, and clean-install expectation update. The hierarchy-capable schema
records the locked five-level invariant; traversal enforcement, move/reorder
behavior, and custom-URL validation remain deferred. Focused compatibility
passed 23 assertions, and WU1 is `NRP CONFIRMED`.

### Work Unit 2 — Navigation Domain Service and Hierarchy

Complete: WU2 added Navigation menu and item value objects, a repository, and
the Navigation-owned domain service. It enforces a maximum hierarchy depth of
five levels including root; same-menu parenting; self, descendant, and cycle
rejection; moved-subtree depth validation; deterministic sibling ordering; and
exact sibling reorder. Provider/custom target payload invariants and the
accepted custom-URL grammar are enforced. Standalone mutations own their
transaction and caller-owned transactions receive private savepoint isolation.
Focused WU2 domain validation passed 44 assertions. WU2 is `NRP CONFIRMED`.

### Work Unit 3 — Content Target Resolver Integration

Complete: Content owns `ContentNavigationTargetResolver`; Navigation owns
request-scoped registry creation and optional-provider composition. Provider
kind is `content` and its reference is the canonical lowercase globally unique
Content slug. Only published Content resolves to `/content/{slug}` using the
stored title as label. Title, slug, and publication changes are reflected
without cross-request caching. Missing, stale, malformed, draft, archived,
disabled, and unavailable Content targets fail closed. No Core wiring, global
registry, or service locator was introduced. Focused WU3 resolver validation
passed 19 assertions. WU3 is `NRP CONFIRMED`. Navigation item visibility and
Theme rendering remain deferred to WU5; production Taxonomy targets remain
deferred.

### Work Unit 4 — Admin Management Workspace

Complete: WU4 provides the Navigation-owned Admin management workspace for
menu and item management, custom and provider targets, hierarchy, parent
exclusion, exact sibling reorder, visibility, empty and unavailable-provider
states, permission handling, CSRF, validation, escaping, PRG, configured Admin
paths, and active Admin navigation. Objective/runtime validation and human
acceptance passed, including responsive presentation at `1440×900`, `390×844`,
`320×800`, and `200%` zoom, plus console and network inspection. No source or
runtime mismatch or accepted defect remains. Keep `AdminNavigation` separate
from managed public navigation data. WU4 is `NRP CONFIRMED`.

### Work Unit 5 — Theme Consumption and Design-Adjustment Checkpoint

Complete: WU5 consumes Theme-declared `navigation_locations` through the
public Core frontend Theme context contract. The seam is request-scoped,
deterministic, frozen before dispatch, lazily composed, and fail-closed.
Navigation owns contributor orchestration and resolved data; Core owns only
context composition; Themes own rendering, markup, styling, composition, and
responsive presentation. Assigned menus, hierarchy, sibling order, visibility,
custom targets, published Content targets, empty/no-assignment handling, and
unavailable or disabled providers are covered. Focused validation passed 15
assertions, package-builder smoke passed 891 assertions, runtime/browser
validation passed, and human acceptance passed at `1440×900`, `390×844`,
`320×800`, true `200%` zoom, touch targets, and keyboard/focus usability. The
WU4 Admin design-adjustment checkpoint is `NO CHANGE REQUIRED`. WU5 is `NRP
CONFIRMED`.

### Work Unit 6 — Security, Milestone Regression, Documentation, and Lifecycle Closure

Complete: WU6 security closure passed 15 assertions covering frontend context
manifest path containment, fail-closed contributor registration and composition,
safe diagnostics, request/render isolation, deterministic context collisions, and
representative Navigation Admin scalar payload guards. Full-range containment,
forbidden-scope audit, documentation closure, and feature-branch Git
verification passed. WU1–WU5 are `NRP CONFIRMED`; full M3.6 remains `NRP NOT
REACHED` pending separately authorized fast-forward integration, branch
lifecycle closure, and final NRP evaluation. Release, tag, and publication
remain separate and not started.

## Validation and Lifecycle Gates

Preparation is `NRP CONFIRMED`. WU1 primary validation passed: focused WU1
compatibility (23 assertions), direct Module Manager provisioning, Content
provisioning, M3.5 WU1 Taxonomy compatibility, package-builder, distribution,
and clean-install checks, PHP lint, and `git diff --check`. Production Taxonomy
targets remain deferred. No runtime or browser validation was required for WU1.

Preparation is `NRP CONFIRMED`. WU1 is `NRP CONFIRMED` after implementation,
primary validation, and documentation closure. WU2 primary
validation passed: focused WU2 domain validation (44 assertions), WU1
Navigation compatibility, Content transaction and provisioning, and M3.5 WU1
and WU2 Taxonomy regressions, plus PHP lint and `git diff --check`. WU2 is
`NRP CONFIRMED`. WU3 primary validation passed: focused WU3 resolver validation
(19 assertions), WU2 Navigation domain, WU1 Navigation compatibility, and
Content public publication/security regressions, plus PHP lint and `git diff
--check`. WU3 is `NRP CONFIRMED`. WU4 is `NRP CONFIRMED` after implementation,
objective/runtime validation, source/runtime synchronization, HTTP/session
validation, and human acceptance. WU5 is `NRP CONFIRMED` after implementation,
focused validation, package smoke, runtime/browser evidence, and human
acceptance. WU6 security closure passed 15 assertions and Git delivery is
complete. Full M3.6 remains `NRP NOT REACHED` until separately authorized
integration, branch lifecycle, and final NRP evaluation. Production Taxonomy
targets and Theme Manager functionality remain deferred.

WU1 is closed to further scope expansion. WU2 delivered only its approved
Navigation domain behavior; it does not authorize Content resolver
implementation, production Taxonomy targets, Theme `navigation_locations`
declaration or consumption, Admin routes/views, `AdminNavigation` changes,
generic Core work, runtime work, release artifacts, tags, or publication.
