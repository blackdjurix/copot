# MR.2 WU2 — Webcore System Manager Baseline Contract

## Status and authority

```text
MR.2 preparation: APPROVED
WU1: COMPLETE AND CLOSED at docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md
WU2 scope: Webcore System Manager Baseline
WU2 contract: PROMOTED / CONTRACT LOCKED
WU2 runtime implementation: COMPLETE
WU2 focused validation: PASSED
Human UI acceptance: PASS / APPROVED
WU2 closure: COMPLETE AND CLOSED on the integrated `main` baseline
```

This is the authoritative contract and closure record for MR.2 WU2. It promotes
the accepted WU2 proposal into a bounded implementation and acceptance scope.
The implementation, focused validation, and human UI acceptance are complete in
the accepted committed `main` state. This contract does not authorize Settings
Manager refinement,
Module Manager refinement, production reconciliation, release work, or later
MR.2 work.

## Objective

Establish an always-available Webcore-owned System Manager baseline that keeps
a Webcore-only COPOT installation operational without requiring Core Modules.
System Manager is the minimum system-administration fallback; it is
not a replacement for the richer Core Module operators that may be present.

The accepted implementation in `main` is limited to the bounded runtime
capabilities classified below. Human UI acceptance passed.

The System Manager areas are:

1. **System** — Webcore lifecycle, compatibility, localization, and release
   information.
2. **Branding** — Webcore Basic Branding and Admin identity.
3. **Modules** — conditional minimum Module lifecycle fallback.
4. **System Health** — the existing authorized System Health report.

System, Branding, and System Health are baseline areas. Modules is shown only
when Module Manager is not operationally available as the preferred Module
lifecycle operator.

## Authority and source reconciliation

### Webcore lifecycle

System Manager consumes the existing `PackageLifecycleService` and
`SystemManagerLifecycleService`. It does not become a package, migration,
recovery, schema, or release-metadata engine.

The existing lifecycle pipeline remains authoritative for intake, isolated
staging, package validation, installed-state inspection, transition planning,
compatibility, maintenance/recovery boundaries, package-owned application,
Core migrations, health/integrity gates, commit, and cleanup.

`PATCH`, `UPDATE`, `UPGRADE`, `REPAIR`, and `DATABASE_UPDATE` remain planner-
derived classifications. WU2 must never provide an operator-selected
classification picker. The operator-facing umbrella is **Update**; the
engine-derived eligible action is presented beneath it as Patch, Update,
Upgrade, Database-only Update, or Repair.

`REPAIR` remains same-version reconciliation or retry of incomplete forward
work. It is not reset, rollback, destructive cleanup, or reverse migration.
`DATABASE_UPDATE` is presented as **Database-only Update** only when the
existing database lifecycle classifier and migration planner make it eligible;
WU2 must not add a generic globally available “Update Database” operation.

### Settings, site assets, and branding

`SettingsRegistry` and `SettingsService` remain the singular authority for
registered setting definitions, defaults, validation, typed serialization,
effective-value fallback, and persistence. Database rows remain overrides;
unknown keys and arbitrary namespace creation remain forbidden.

The existing Core definitions are authoritative for Site Name, Site Tagline,
Locale, Timezone, Date Format, Time Format, and controlled Logo/Favicon
descriptors. `SiteAssetStorage` remains the authority for Logo and Favicon
file validation, private storage, activation, cleanup, and public serving.

WU2 introduces Webcore Basic Branding values and Admin identity as a bounded
Webcore-owned capability. Their persistence and validation must use the
existing registered Settings and Site Asset boundaries or a narrowly scoped
extension of those boundaries. WU2 must not turn Settings into a generic
editor or create a universal branding-token/provider framework.

The Core palette terminology is fixed as **Main**, **Accent**, **Neutral
Dark**, and **Neutral Light**. These are global Webcore branding baseline
values for public/Webcore consumers, not Admin-shell theme tokens. The locked
default semantic direction remains authoritative: Main for navigation and
identity, Neutral Light for page backgrounds and surfaces, Neutral Dark for
body text, and Accent for primary actions, links, and focus.

### Module lifecycle

Module Manager remains the preferred richer Module lifecycle operator and is
not modified by WU2. The underlying generic Module lifecycle authority remains
the existing discovery, repository, dependency, permission, and lifecycle
services, together with the accepted Module Package Lifecycle contracts.

The System Manager fallback is a bounded consumer of those authorities. It
must not create a second Module lifecycle engine, duplicate Module Manager
state, or mutate Module Manager implementation.

### System Health

System Manager consumes the existing authorized System Health report and
producer model. It may present Overall, Webcore, Modules, Runtime, Findings,
and advisory approved links. It must not diagnose privately, create a health
engine, create new severity semantics, persist a duplicate report, or execute
remediation. Viewer-scoped authorization and producer-owned visibility remain
mandatory.

## WU1 presentation boundary

All WU2 pages are Webcore consumers of the closed-but-evolvable WU1 Admin Page
Frame inside the existing `admin-main` boundary. WU2 may request only WU1's
bounded semantic presentation intent and must preserve consumer-owned internal
content.

Tabs may be extracted as a shared capability only when concrete WU2 reuse
justifies it. If extracted, tabs remain consumer-selected: each consumer owns
tab existence, count, labels, order, content, and conditional visibility. No
generic component registry, page schema, DSL, dynamic discovery, plugin
framework, or generic asset-registration subsystem is authorized.

System Manager presentation should provide coherent tabs or equivalent area
navigation, information hierarchy, aligned controls, explicit action
hierarchy, spacing, status/error/result presentation, responsive desktop/mobile
behavior, accessibility, lifecycle comprehension, and proportionate visual
polish. Admin Shell, topbar, breadcrumb, Dashboard, and unrelated module
refinement remain outside WU2.

## Area contracts

### 1. System

The System area must provide a human-operable projection of existing
Webcore-owned lifecycle evidence:

- current Webcore version and committed/installed lifecycle state where
  authoritative evidence exists;
- Core/database schema state, migration state, and compatibility where the
  existing lifecycle and database authorities provide evidence;
- released Webcore ZIP intake using the existing private upload/staging
  boundary;
- preflight results before mutation;
- **Update** as the operator-facing umbrella;
- engine-derived eligible action/classification: Patch, Update, Upgrade,
  Database-only Update, or Repair;
- Retry and Reconciliation only when the existing lifecycle evidence makes
  them eligible;
- operation result, recovery/maintenance state, and the next valid action;
- **What's New** from existing authoritative release/package metadata, without
  inventing release notes or treating UI copy as release authority.

The surface must show controlled reasons for rejected, blocked, unavailable,
indeterminate, cleanup-pending, or recovery-required states. It must not expose
raw exceptions, SQL, filesystem paths, package internals, recovery identities,
or arbitrary operator-owned paths.

The current `routes/system_manager.php` is an always-loaded Webcore route and
the existing `system.webcore.manage` permission remains the authorization
boundary. WU2 replaces its diagnostic/raw status presentation with the
human-operable System area while preserving the existing service calls and
CSRF/error boundaries.

### 2. Localization baseline

The System area includes the existing registered settings:

- Locale;
- Timezone;
- Date Format;
- Time Format.

The existing controlled values and validation remain authoritative. WU2 does
not implement Language or Admin UI translation. If useful for family
terminology, Language may be recorded only as a future member of Localization.

### 3. Branding

Webcore Basic Branding includes editable:

- Site Name;
- Site Tagline;
- Logo;
- Favicon;
- Main;
- Accent;
- Neutral Dark;
- Neutral Light.

Logo and Favicon use the existing specialized Site Asset workflows and exact
accepted MIME, size, dimension, storage, activation, removal, and serving
boundaries. They are not arbitrary JSON or generic file fields.

The four palette values are editable Webcore baseline values. WU2 preserves
the existing semantic direction and adds a bounded Contrast-Aware Neutral
Resolution rule:

- for required foreground-on-Main and foreground-on-Accent relationships,
  Webcore selects Neutral Dark or Neutral Light using actual contrast;
- validation rejects a palette that makes a required relationship unusable;
- the effective palette always resolves to a complete safe contract or safe
  Webcore defaults;
- component-level hover, inactive saturation, border, gradient, and arbitrary
  color transformations remain outside WU2.

Admin identity is independently bounded:

- display mode is **Logo** or **Text**;
- Text uses Site Name;
- text color selects exactly one of Main, Accent, Neutral Dark, or Neutral
  Light;
- no arbitrary text-color picker;
- no Alternate Site Name.

Admin identity may consume the Webcore baseline through an explicit safe
mapping, but WU2 must not turn Admin-shell tokens into frontend branding or
make Admin presentation depend on a Theme.

If richer branding providers exist, any fallback rule remains narrowly
branding-specific: exactly one effective provider, Webcore Basic Branding
always available, inactive providers have no effect, and unavailable or
incompatible active providers fall back deterministically. No generic
capability-provider arbitration is introduced, and advanced provider
management UI is excluded.

### 4. Conditional Modules fallback

The Modules area is presented only when Module Manager is not operationally
available as the preferred operator surface. Filesystem presence alone is not
enough to suppress the fallback.

For WU2, operational availability is the narrowest source-supported state:

1. `module-manager` is discovered as valid Module metadata;
2. it has an installed repository row with status `enabled`;
3. its declared route contribution is available and loadable through the
   existing Module Loader boundary; and
4. the preferred operator surface is therefore registered and reachable under
   its existing authorization rules.

If any required evidence is absent, invalid, disabled, or unavailable, the
fallback remains available. WU2 must not infer availability from a directory
name or manifest presence alone.

When shown, the fallback provides only the minimum recoverable capability
supported by existing Core APIs:

- discovered/installed Module inventory;
- install/add where an accepted local Module package or discovered Module
  source is available;
- enable;
- disable;
- uninstall;
- basic lifecycle/status;
- concise dependency or conflict blocking reasons; and
- enough capability to install or restore `module-manager`.

When Module Manager is operationally available, it remains the canonical
Module lifecycle operator, the fallback is not presented, and underlying
Webcore Module lifecycle authority is unchanged.

### 5. System Health

System Manager presents the existing authorized report with a readable
baseline detail structure:

- Overall;
- Webcore;
- Modules;
- Runtime;
- Findings;
- advisory recommendations and approved links.

The report is derived/read-only for WU2. No new health persistence, schema,
producer, severity, diagnosis, or executable remediation is introduced.

## Explicit ownership boundaries

| Concern | Authoritative owner | WU2 role |
|---|---|---|
| Webcore package/migration lifecycle | Package Lifecycle & Migration Foundation | Human operator consumer |
| Database ownership/schema/migration authority | Database Ownership & Lifecycle Foundation | Evidence and action presentation |
| Settings definitions and persistence | SettingsRegistry / SettingsService | Controlled Webcore fields only |
| Logo/Favicon file lifecycle | SiteAssetStorage | Existing specialized workflow consumer |
| Core palette and Webcore Basic Branding | WU2 bounded Webcore capability | Define and deliver baseline values/resolution |
| Module lifecycle | Core Module lifecycle services / Module Manager | Conditional fallback consumer |
| Health production/aggregation/authorization | System Health producers and report model | Authorized report consumer |
| Admin Shell and navigation framework | Existing Admin Shell/Webcore | Reuse only |

Settings Manager may later provide richer delivery for overlapping settings,
branding, or system-management capability. It must not duplicate or take over
Webcore baseline authority established here. Module Manager likewise remains
unchanged and preferred when operational.

## Explicit new-capability classification

The following are new bounded WU2 delivery capability, not existing runtime
behavior to be misreported as complete:

- System Manager human-operable area presentation and lifecycle result
  comprehension over existing services;
- Webcore Basic Branding palette persistence, validation, contrast-aware
  neutral resolution, and safe effective-palette fallback;
- Webcore Admin identity mode/color selection over existing Site Name and
  controlled Logo capability;
- conditional System Manager Modules fallback delivery over existing Module
  lifecycle authority;
- System Manager-specific read-only projections needed to present authoritative
  lifecycle, schema, release metadata, and System Health evidence safely.

These do not authorize a new lifecycle engine, schema/migration engine,
generic provider framework, generic settings editor, generic color engine,
health engine, Installer change, or Module Manager mutation.

## Human acceptance record

Human UI acceptance passed for WU2. The primary acceptance environment was
a disposable Webcore-only runtime derived from accepted WU2 source. Settings
Manager and Module Manager must be absent or inactive, and ideally no Core
Module is installed/enabled. The normal `main` runtime must not be mutated for
this acceptance.

Acceptance covered:

- Webcore-only System Manager navigation and the System / Branding / Modules /
  System Health information architecture;
- lifecycle comprehension, action eligibility, blocked/recovery/result states,
  and What's New presentation;
- Localization controls and validation;
- one opt-in System Manager workspace `Save Changes` action that validates and
  persists dirty Localization and Branding capabilities through their existing
  authorities, while preserving capability-local drafts across sibling tabs;
- Branding controls, specialized Logo/Favicon flows, palette validation, and
  contrast-aware behavior;
- Logo/Text Admin identity behavior;
- public-view branding sanity where WU2 changes effective palette consumption;
- System Health readability and viewer-scoped visibility;
- desktop/mobile usability, accessibility, responsive behavior, and visual
  refinement;
- Modules fallback inventory and lifecycle availability.

Where technically feasible without expanding WU2, acceptance also proved:

1. Webcore-only state exposes the Modules fallback;
2. the fallback installs or restores Module Manager through the existing Module
   lifecycle authority;
3. once Module Manager is operationally available, the fallback is withdrawn;
4. underlying Module lifecycle state remains authoritative and consistent.

## Objective acceptance criteria

WU2 is accepted only when:

- the contract boundaries above are implemented without duplicate authority;
- System Manager is always available under the existing Webcore permission
  boundary;
- all four areas follow their conditional presentation rules;
- lifecycle actions and classifications are engine-derived and eligibility-
  bounded;
- localization, Branding, Logo/Favicon, palette, contrast, and Admin identity
  behavior pass focused validation;
- Modules fallback suppression uses operational availability rather than
  filesystem presence;
- System Health remains authorized, sanitized, derived/read-only, and
  non-executable;
- WU1 Page Frame is adopted without Admin Shell or consumer-content regression;
- no generic registry, DSL, provider framework, color engine, health engine,
  Installer change, or Module Manager refinement is introduced;
- PHP lint, focused tests, directly impacted regressions, documentation/link
  checks, disposable-runtime acceptance, and final diff review pass; and
- implementation and human acceptance are complete in the accepted committed
  `main` state; this document remains the authoritative WU2 boundary and
  closure record.

## Explicit exclusions

- Settings Manager enhanced implementation or authority takeover;
- Module Manager refinement or mutation;
- Content, Navigation, Theme, Users/Roles, or other Core Module fallback;
- Alternate Site Name, Language, typography, Custom CSS, Brand Kit, or
  advanced Theme-specific branding;
- generic provider/capability framework, arbitrary branding token system, or
  advanced derived-color engine;
- safe SVG expansion unless already supported by the accepted exact capability;
- online update discovery/download, release server/channel, or automatic
  updates;
- new lifecycle transition semantics, new System Health producer/engine, or
  executable health remediation;
- Installer changes, production reconciliation, generic identity exposure,
  Dashboard/Admin Shell redesign, release, tag, publication, or distribution.
