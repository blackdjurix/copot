# Webcore Product Completeness & Stabilization — WU1 Scope Reconciliation Contract

## Status and authority

Workstream: Post-M3 — Webcore Product Completeness & Stabilization
Work Unit: WU1 — Webcore Completeness Contract & Scope Reconciliation
WU1 status: COMPLETE / CONTRACT LOCKED
Technical implementation performed by WU1: NONE
WU2-WU6 implementation: NOT STARTED
Release / tag / publication authorization: NONE

This contract closes WU1 as the authoritative scope-reconciliation unit under
`docs/49_webcore_product_completeness_stabilization_contract.md`, incorporating
the accepted product-facing information-architecture clarification in
`docs/50_webcore_site_settings_information_architecture_clarification.md` and
current repository source evidence.

WU1 is a planning/scope contract. It does not implement Media, Navigation, Site
Settings, schema, configuration, package lifecycle, or release behavior.

## WU1 verdict

WU1 concludes:

- the three-root-gap model is CONFIRMED;
- no fourth root product-completeness gap is currently justified;
- the six-WU workstream topology is RETAINED;
- accepted Webcore ownership does not require reopening;
- no schema migration is currently justified;
- no settings migration is currently justified;
- existing Bundled Module implementations are implementation evidence and reuse
  sources, not automatic Webcore implementation targets;
- Site Settings is the evolved product-facing successor to the current System
  Manager parent surface; and
- technical implementation begins no earlier than a separately authorized WU2
  execution slice.

## Confirmed root gaps

### Gap 1 — Core Media Admin baseline

Core Media capability and authority already exist, but the currently available
rich Admin projection remains inside the optional `media` Bundled Module
boundary.

The missing baseline is therefore an always-available Webcore-owned Media Admin
projection over existing Core Media authority.

This is not authorization to move the complete Media Manager implementation into
Webcore.

### Gap 2 — Core primary Navigation Admin baseline

Core primary Navigation capability and authority already exist, while the
currently available management projection remains inside the optional
`navigation` Bundled Module boundary.

The missing baseline is therefore an always-available Webcore-owned management
projection for one complete primary Navigation capability.

This is not authorization to move the complete Navigation Manager implementation
into Webcore.

### Gap 3 — Webcore Site Settings product projection

Settings, Site Asset, Branding, Localization, System lifecycle, Module lifecycle,
and System Health capabilities already exist across accepted Webcore/Platform
boundaries, but their product-facing operator projections are fragmented or
historically split.

The missing baseline is one coherent Webcore-owned Site Settings parent surface
that evolves the current System Manager product surface while preserving all
underlying authorities.

## No fourth root gap

Current source evidence does not justify a separate corrective Work Unit for:

- Content;
- Redirects;
- Built-in Public View;
- Admin Shell;
- Dashboard; or
- a second architecture reconciliation.

Content already has an accepted Webcore Admin baseline. Redirect Core CRUD is
not presently required. Built-in Public View is already delivered. Admin Shell
and Dashboard have no concrete root-completeness regression evidence requiring a
new Work Unit.

A later concrete regression may justify a bounded correction but does not alter
this WU1 verdict automatically.

## Baseline-versus-extension invariant

Existing Bundled Module implementations are evidence and potential reuse
sources. They are not automatic implementation targets for the Webcore baseline.

For every extraction/materialization Work Unit:

- preserve the accepted singular Webcore/Core/Platform authority;
- reuse existing implementation only where behavior belongs to the baseline;
- separate extension-only behavior instead of copying a Bundled Manager
  wholesale;
- do not make an optional Bundled Module a hidden baseline dependency; and
- preserve the retained Manager as an extension where its advanced capability
  remains valid.

This invariant is mandatory for WU2 Media and WU3 Navigation.

## Locked WU2 boundary — Core Media Admin Baseline

WU2 must materialize a Webcore-owned baseline Media Admin projection over the
existing Core Media authority.

Baseline target:

- inventory/listing sufficient for minimum operation;
- upload;
- authoritative basic identity and metadata presentation;
- simple selection/reference behavior needed by baseline Webcore consumers;
- usage/reference awareness needed for safe lifecycle actions;
- reference-safe deletion;
- existing permissions;
- CSRF protection;
- existing validation; and
- sanitized failure behavior.

Not automatic baseline scope:

- advanced metadata editing;
- processing variants;
- derivative management;
- crop, resize, rotate, or advanced image editing;
- folders or galleries;
- bulk workflows;
- advanced organization;
- richer Media Manager workspace behavior merely because it already exists.

Search, filters, pagination, cards, and preview behavior may be included only
when source/runtime evidence proves them necessary for practical baseline
operation without collapsing the Media Manager extension boundary.

## Locked WU3 boundary — Core Primary Navigation Admin Baseline

WU3 must materialize a Webcore-owned management projection for one complete
primary Navigation capability.

Baseline target:

- one primary menu;
- add, edit, and remove item;
- reorder;
- bounded hierarchy;
- custom URL targets;
- Webcore Content targets;
- minimum validation;
- safe lifecycle behavior; and
- operation with Navigation Manager absent.

The contract requires reorder capability, not drag-and-drop as a specific
interaction technique.

Not automatic baseline scope:

- multiple independent menus;
- Theme location assignment;
- broader assignment management;
- provider browsing or generic provider capability;
- advanced visibility;
- advanced target types;
- bulk actions;
- analytics; or
- import/export.

Redirect management remains separate.

## Locked WU4 boundary — Site Settings evolution and consolidation

WU4 must evolve the current Webcore System Manager product-facing parent surface
into **Site Settings**.

This is product-facing identity and information-architecture succession. It is
not a transfer of underlying Settings, Site Asset, Branding, System lifecycle,
Module lifecycle, System Health, security, or persistence authority.

The locked top-level Site Settings areas are:

1. Site Identity
2. System
3. Security
4. Email
5. Modules
6. System Health

The old candidate top-level `Site` area is superseded by `Site Identity` under
the evolved Site Settings parent and must not remain as a competing peer area.

### Site Identity internal grouping

Site Identity owns the baseline operator grouping for site-facing identity and
presentation controls. It may organize the accepted controls as:

- General:
  - Site Name;
  - Site Tagline;
  - Logo;
  - Favicon.
- Localization:
  - Locale;
  - Timezone;
  - Date Format;
  - Time Format.
- Appearance:
  - accepted Webcore Branding baseline;
  - bounded Site Color Scheme.

Localization and Appearance are therefore subordinate Site Identity groups, not
peer top-level Site Settings areas.

Exact field layout and micro-interaction mechanics remain WU4 implementation and
human/product acceptance details.

### Existing System Manager capability preservation

The evolution from System Manager to Site Settings must preserve accepted
Webcore operational capability. System, Modules, and System Health must not be
removed merely because the parent surface is renamed/evolved.

Security and Email are locked as target top-level areas, but WU1 does not claim
that their complete operator implementations already exist. WU4 must inspect
current source and contract evidence and implement only the accepted baseline
required for the Site Settings product surface. It must not invent unrelated
security/email subsystems or cross an unlocked architecture/product boundary.

### Projection supersession

Where Site Settings supersedes product-facing locations previously established
by System Manager or historical Settings surfaces:

- record the supersession at the projection layer;
- preserve historical contracts as historical accepted evidence;
- preserve underlying authorities;
- avoid competing canonical editors for the same setting family; and
- retain contextual/recovery/lifecycle-specific projections only when concrete
  source evidence proves a distinct justified function.

## Locked WU5 boundary — Zero-Optional Product Acceptance

WU5 is integrated acceptance, not a redesign unit.

With zero optional Modules and zero Themes, acceptance must prove:

- Content remains operable through its accepted Webcore baseline;
- Media is operable through Core Media authority plus WU2 projection;
- primary Navigation is operable through Core Navigation authority plus WU3
  projection;
- Site Settings is operable without Settings Manager or other optional Manager
  dependency;
- Built-in Public View remains usable;
- Site Identity remains operable;
- supported Appearance remains available; and
- retained Managers extend rather than replace or take over Webcore baseline
  authority.

## Locked WU6 boundary — Stabilization and readiness closure

WU6 may perform only directly justified stabilization, focused regressions,
cross-capability acceptance reconciliation, documentation consistency,
unresolved-finding disposition, zero-optional re-verification, and final
v0.14.0 readiness reporting.

WU6 does not authorize:

- version bump;
- package creation;
- tag;
- GitHub Release;
- publication; or
- external distribution.

## Preserved exclusions

WU1 does not adopt or authorize:

- Bundled Module refinement;
- Content Manager refinement;
- Media Manager refinement;
- Navigation Manager refinement;
- Theme Manager refinement;
- Users & Access refinement;
- Form Manager refinement;
- rich-text/editor advancement;
- taxonomy ownership transfer;
- Redirect Core CRUD without new evidence;
- generic Settings Manager replacement framework;
- generic provider framework;
- generic color engine;
- arbitrary semantic color mapping;
- advanced Brand Kit;
- multi-brand;
- white-label system;
- broad Admin Shell redesign;
- Dashboard redesign;
- destructive cleanup;
- Deferred Item adoption;
- production reconciliation; or
- release/tag/publication/distribution.

## WU1 closure state

WU1 is COMPLETE / CONTRACT LOCKED.

No runtime or source implementation was required for WU1 because its task was
scope reconciliation against accepted architecture, current source evidence,
and the locked Site Settings information architecture.

The next workstream unit is WU2 — Core Media Admin Baseline.

WU2 is the next technical execution candidate only. This WU1 closure does not
by itself authorize WU2 implementation or direct transfer to an executor.

## Authority and provenance

Primary authority:

- `docs/49_webcore_product_completeness_stabilization_contract.md`;
- `docs/50_webcore_site_settings_information_architecture_clarification.md`;
- this WU1 contract.

Accepted architecture and historical delivery evidence:

- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`;
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`;
- `docs/46_webcore_content_admin_baseline_contract.md`;
- `docs/08_settings_system.md`;
- `docs/11_branding_foundation.md`.

Current repository source remains implementation evidence for later Work Units.
Historical Concept and Pre-contract material remains provenance and does not
override this promoted authority.