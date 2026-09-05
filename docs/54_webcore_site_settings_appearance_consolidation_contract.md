# Webcore Site Settings & Appearance Consolidation Contract (WU4)

## Status and authority

```text
Workstream: Post-M3 — Webcore Product Completeness & Stabilization
Work Unit: WU4 — Webcore Site Settings & Appearance Consolidation
Contract status: PROMOTED / CONTRACT LOCKED
WU4 implementation: NOT STARTED
Technical implementation authorization: NONE
Release / tag / publication authorization: NONE
```

This is the dedicated authoritative WU4 implementation contract. It
materializes the accepted product decisions against the existing Webcore
authority and projection lineage. It authorizes documentation and contract
materialization only; it does not authorize WU4 runtime implementation,
schema/settings migration, runtime mutation, or release activity.

The parent workstream contract remains
`docs/49_webcore_product_completeness_stabilization_contract.md`. Historical
accepted delivery records remain historical evidence and are not rewritten by
this contract.

## Objective and governing invariant

WU4 materializes one coherent Webcore Site Settings product projection while
preserving the existing Settings, Site Asset, Media, lifecycle, Module, and
System Health authorities.

The governing invariant is:

```text
Consolidate product-facing access and presentation.
Preserve singular underlying authority, persistence, validation, and lifecycle.
```

Site Settings is a product projection, not a new ownership layer. WU4 must not
create competing persistence, duplicate canonical editors, provider
arbitration, a generic settings engine, or a wholesale System Manager
rewrite.

## Product surface and routing direction

The current Webcore System Manager product surface evolves into Site Settings
as its successor parent identity. `/admin/settings` is the Webcore-owned
canonical Site Settings path and product surface.

The canonical Site Settings URL is:

```text
/admin/settings
```

`/admin/settings/system-manager` is noncanonical. WU4 does not require a
retained legacy compatibility route for it. Given the pre-live-site development
state and the stable v0.13.0 release boundary, WU4 may remove that route and
reconcile its internal references. The exact route-removal and internal
reference mechanics remain implementation-time details.

The retired Settings Manager does not own `/admin/settings`. A future
reactivated Settings Manager or equivalent Module may consume or extend the
canonical Webcore Site Settings surface, but must not reclaim ownership of that
path. The canonical URL decision does not by itself authorize package deletion
or Module lifecycle work.

## Locked information architecture

Site Settings has exactly these six top-level areas:

1. Site Identity
2. System
3. Security
4. Email
5. Modules
6. System Health

Site Identity contains exactly these bounded groups:

```text
Site Identity
├─ General
│  ├─ Site Name
│  ├─ Site Tagline
│  ├─ Logo
│  └─ Favicon
├─ Homepage
│  ├─ Hero Image
│  └─ Homepage Content
│     ├─ None
│     ├─ Page
│     └─ Article Collection
├─ Localization
│  ├─ Locale
│  ├─ Timezone
│  ├─ Date Format
│  └─ Time Format
└─ Appearance
   └─ Webcore Color Scheme
```

Security and Email remain top-level areas even where the current source only
supports a truthful unsupported or non-configurable state.

## Authority preservation

The following authorities remain singular and unchanged:

| Concern | Authoritative owner | WU4 role |
| --- | --- | --- |
| Definitions, defaults, validation, typed persistence, effective values | Settings Platform (`SettingsRegistry` / `SettingsService`) | Product projection only |
| Logo and Favicon validation, storage, activation, cleanup, serving | Site Asset authority (`SiteAssetStorage`) | Existing workflow consumer |
| Homepage Hero Image selection, reference, usage, and delivery | Core Media authority | Site Settings selection/reference projection only |
| Homepage Content source identity and supplied output | Existing authoritative Page content and canonical Article Collection identities | Site Settings single-assignment projection; Built-in Public View render consumer |
| System lifecycle, adoption, recovery, and lifecycle semantics | Existing Webcore lifecycle authority | Existing operational projection |
| Module discovery, permissions, lifecycle, and package behavior | Existing Module lifecycle authority | Existing operational projection |
| Health production, provider, aggregation, authorization, and sanitization | Existing System Health authority | Existing read-only projection |

Site Settings must not transfer ownership from any listed authority. It must
not create a second settings store, asset lifecycle, media reference model,
lifecycle engine, Module lifecycle engine, or health diagnosis/aggregation
engine.

## Built-in Public View page taxonomy

The Built-in Public View has exactly three page types:

1. Homepage
2. General Page
3. System Page

### Homepage

Homepage is the canonical public surface at `/`. It is distinct from a
General Page, Navigation Item, Article Collection, and provider-specific
content identity.

Homepage may load content into its body/container and has a site-level
adjustable Hero Image and one bounded Homepage Content assignment through Site
Settings. The Homepage Content assignment has exactly one source at a time:
`None`, `Page`, or `Article Collection`. It selects an existing authoritative
content object or canonical Article Collection identity; Site Settings does not
own or author that content. Shared frame elements such as
navigation, logo/site identity, and footer belong to the Built-in Public View
shell, not Homepage-specific settings.

The Homepage Built-in Public View is a consumer only: it may resolve the
selected Homepage Content source and render its supplied output inside the
Homepage `builtin-site-content` container, but it must not own content
selection, assignment persistence, content authoring, or provider arbitration.
Homepage identity, General Page identity, Navigation identity, and the
assigned Page or Article Collection identity remain distinct. Homepage Hero
Image remains a separate Homepage setting.

Homepage identity is independent of Navigation. Navigation may reference the
Homepage, but Navigation item deletion or reordering must not redefine
Homepage identity.

### General Page

General Page provides the normal content container/shell and loads supplied
content. It has no dedicated WU4 Site Settings projection because no baseline
page-level setting is introduced here.

### System Page

System Page does not have a dedicated Site Settings projection and does not
load arbitrary content. Its body is system-controlled/predefined according to
system state. 404, Maintenance, and Under Construction are representative
categories only unless an exact delivered inventory is proven.

WU4 must not create a new system-state engine.

Collection Page is not a separate Built-in Public View page type. Collection,
list, and archive behavior is a content/presentation pattern that may use the
General Page family.

Article Page, Form Page, Product Page, and similar provider/domain concepts are
not automatic Built-in Public View page types.

## Homepage configuration boundary

WU4 owns the product projection for Homepage configuration. It includes the
separate Hero Image setting and one bounded Homepage Content assignment with
exactly these choices: `None`, `Page`, or `Article Collection`.

Site Settings is the only Homepage Content assignment/configuration surface.
It owns only the selected-source projection, not Page or Article Collection
content, authoring, or persistence. The assignment must select an existing
authoritative Page content object or canonical Article Collection identity, and
exactly one source may be assigned at a time. The Homepage rendering layer
only resolves and renders the supplied result.

Site Settings owns only the Homepage Hero Image selection/projection. Settings
Platform owns typed persistence for Site Settings values. Core Media owns
Media identity, storage, selection/reference, usage, delivery, and deletion
safety. Site Settings must not take ownership of Media.

Exact setting key, reference representation, usage-registration mechanics,
transaction/removal behavior, fallback mechanics, and other low-level details
remain implementation-time technical dispositions to be resolved from current
Core Media source.

WU4 is not a page builder, Homepage content builder, or arbitrary layout
editor. Generic or arbitrary content-assignment infrastructure, including
provider arbitration, remains outside WU4; the bounded single Homepage Content
assignment defined above is the specific exception. Navigation mechanics
beyond the Homepage identity separation remain outside WU4.

## Built-in Public View visual direction

`Built-in Public View Guideline.png` is the accepted human/product visual
reference lineage for WU4 acceptance. It is not a pixel-perfect normative
implementation specification. The reference image is not authority for page
taxonomy or historical visual labels.

With zero Themes, Built-in Public View must remain:

- clean, modern, and light;
- spacious, with ample white space;
- image/photo capable and intentionally image-driven where appropriate;
- capable of a strong Homepage hero image;
- editorial/informational and image-capable for General Page; and
- visually consistent but restrained for System Page.

Historical `Post` or `Article` labels in the reference lineage do not become
Built-in page-type authority. Pixel-for-pixel cloning is not required.

## Webcore Color Scheme

WU4 supersedes the target-level model that treats Site Color Scheme as a
projection of a four-color Webcore Branding palette.

Webcore Color Scheme is locked as:

- Webcore-owned;
- exactly one user-selected `Main Color`;
- system-derived shades, highlights, tints, or equivalent bounded functional
  variants from that Main Color;
- canonical black and white as Webcore-owned neutral bases, not user-selected
  colors;
- bounded neutral variants derived from those black and white bases where
  needed.

Modules and Themes may consume Webcore neutral primitives. They may also define
local or theme/module-specific presentation colors and derived tokens, but they
must not mutate or replace canonical Webcore neutral values globally.

Webcore does not own the Branding concept or richer Brand Colors such as
Branding Main or Branding Accent. Webcore Color Scheme is a separate Webcore
concept and its canonical user-configurable value is not ownership of legacy
`branding.main`.

It is not:

- Main plus Accent plus Light Neutral plus Dark Neutral;
- an arbitrary palette builder;
- a generic token editor or generic derived-color infrastructure;
- an editable semantic/status-color system.

Operational semantic colors remain separate, including success, warning,
danger, information, validation, destructive action, focus, and other
accessibility-critical states.

### Existing four-color Branding disposition

The current four-color `branding.*` implementation is transitional
implementation evidence, not canonical Webcore Color Scheme authority. After
separate WU4 implementation authorization, implementation may perform bounded
destructive cleanup or remove obsolete legacy Branding/runtime projections
where required by the accepted ownership model.

Before any cleanup or removal, implementation must identify active consumers
and must not leave broken references. Exact cleanup, compatibility,
deprecation, retention, or migration mechanics remain implementation-time
dispositions. Destructive cleanup is authorized only within this bounded
condition and does not authorize a general data migration.

The richer Brand Colors model—Main, Accent, Light Neutral, and Dark Neutral—
is not Webcore ownership and is outside WU4 implementation scope. Its future
owner may be a Theme Module or separate optional Branding capability; WU4 does
not choose that owner.

## Security and Email boundary

Security and Email remain top-level Site Settings areas. WU4 must expose a
truthful delivered-capability state based on current source.

If no configurable capability exists, the baseline is an explicit
unsupported/non-configurable state. WU4 must not invent or imply delivery of
new infrastructure.

The following are outside WU4:

- MFA or 2FA;
- new authentication or recovery policy;
- verified recovery email or password reset;
- emergency owner recovery;
- SMTP or mail-provider transport;
- test-delivery engines; and
- unrelated account-recovery administration.

## System, Modules, and System Health boundary

WU4 reorganizes accepted System, Modules, and System Health capabilities under
the Site Settings parent without replacing their authorities.

System retains existing lifecycle, adoption, recovery, compatibility, and
commit semantics. Modules retain existing discovery, dependency, permission,
package, and lifecycle semantics. System Health remains a sanitized,
viewer-scoped, derived/read-only report projection.

WU4 must not create a second Webcore lifecycle engine, Module lifecycle
authority, health diagnosis engine, report store, severity model, or executable
remediation path.

Existing permissions remain authoritative unless direct implementation source
evidence requires a bounded correction. Any permission reconciliation must
explicitly preserve the distinction between Site Settings writes,
`system.webcore.manage`, `modules.manage`, and `admin.access`.

## Four-batch implementation topology

WU4 is locked to four implementation batches. This topology is an execution
boundary, not authorization to begin implementation.

### Batch 1 — Site Settings Parent, Site Identity, Homepage & Appearance

Materialize:

- canonical Site Settings parent at `/admin/settings`;
- Site Identity / General;
- Homepage Hero Image projection using the Core Media boundary;
- bounded Homepage Content single-assignment projection with `None`, `Page`,
  or `Article Collection` choices, using existing content identities only;
- Localization consolidation;
- Webcore Color Scheme;
- directly impacted Built-in Public View integration; and
- relevant compatibility, permission, validation, and duplicate-projection
  reconciliation.

Batch 1 must not implement System, Modules, Security, Email, or System Health
beyond parent/navigation scaffolding strictly necessary for the canonical Site
Settings surface.

Acceptance question:

```text
Can an operator configure the site's core identity and baseline appearance from
one canonical Site Settings surface and see the supported result in Built-in
Public View with zero Themes?
```

Hard dependencies: Settings Platform, Site Asset authority, Core Media
selection/reference authority, existing Built-in Public View shell, and the
canonical-route/permission disposition.

Batch 1 is independently implementable after those boundaries are confirmed.
Human/product acceptance is required for grouping, Appearance behavior,
Homepage presentation, accessibility, and the zero-Theme visual result.

### Batch 2 — System & Modules Operational Projection

Reorganize and preserve existing System and Modules capabilities under the
Site Settings parent without replacing lifecycle authorities.

Hard dependencies: Batch 1 parent/navigation boundary, existing Webcore
lifecycle authority, Module lifecycle authority, recovery semantics, and
existing permissions.

Batch 2 is independently implementable after the parent boundary exists.
Human/product acceptance is required for operational comprehension,
permission visibility, and lifecycle/module projection usability.

### Batch 3 — Security, Email & System Health Projection Reconciliation

Materialize truthful Security and Email projections based on delivered
capability and reconcile System Health presentation while preserving existing
health authority.

No new Security or Email infrastructure is authorized by this batch.

Hard dependencies: Batch 1 parent, existing authentication/session/CSRF
boundaries, existing System Health provider/producer/aggregation model, and
the final permission visibility rules.

Batch 3 is independently implementable as projection reconciliation after the
parent exists. Human/product acceptance is required for truthful unsupported
states, visibility, sanitization, and comprehension. New capability delivery
would require a separate authorization.

### Batch 4 — Cross-Surface Acceptance & WU4 Closure

Validate:

- canonical routing and the noncanonical-route reconciliation disposition;
- authority preservation;
- zero-Theme Built-in Public View behavior directly affected by WU4;
- permission boundaries;
- absence of competing canonical editors;
- Homepage/Navigation identity separation;
- truthful Security and Email states; and
- coherent Site Settings UX.

Batch 4 is acceptance and closure only. It is not a license to add adjacent
features.

Hard dependencies: Batches 1–3 and all recorded implementation-time
dispositions that materially affect acceptance.

Batch 4 is not independently implementable before the preceding batches.
Human/product acceptance is mandatory.

## Explicit unresolved implementation dispositions

The following details are deliberately carried into implementation rather than
silently decided by this contract:

1. Exact removal/reconciliation mechanics for noncanonical
   `/admin/settings/system-manager`; a retained compatibility route is not
   required by WU4.
2. Exact disposition of the retired Settings Manager product surface or
   Module where it overlaps canonical Site Settings; it does not own
   `/admin/settings`, and package deletion is not authorized here.
3. Exact compatibility, deprecation, retention, bounded cleanup, or
   migration mechanics for current four-color `branding.*` storage and
   runtime projections after active-consumer identification.
4. Exact Homepage Hero Image setting key, reference, usage-registration,
   transaction/removal, persistence, and fallback mechanics.
5. Exhaustive System Page template inventory.

These are implementation-time/source-evidence dispositions bounded by this
contract. They are not permission to invent a new product or architecture
decision during execution.

## Supersession and historical lineage

This contract records projection/target-level supersession without falsifying
history:

- `docs/50_webcore_site_settings_information_architecture_clarification.md`
  remains authoritative for the six-area parent IA. This contract adds
  Homepage inside Site Identity and supersedes its conflicting target-level
  Appearance/Branding wording.
- `docs/49_webcore_product_completeness_stabilization_contract.md` remains the
  parent workstream contract. Its four-color Webcore Branding to Site Color
  Scheme target relationship is superseded for WU4 by the one-Main-Color
  Webcore Color Scheme model.
- `concepts/copot_site_color_scheme_concept.md` remains planning lineage but is
  superseded where it treats four-color Brand Colors/Webcore Branding as the
  canonical upstream target for Site Color Scheme.
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md` and
  `docs/11_branding_foundation.md` remain historical evidence of delivered
  System Manager and four-color Branding behavior.
- `docs/52_webcore_core_media_admin_baseline_contract.md` remains authority for
  Core Media selection/reference boundaries.
- `docs/53_webcore_core_primary_navigation_admin_baseline_contract.md` remains
  authority for Navigation behavior; WU4 only adds the Homepage identity
  separation described here.

No historical contract is rewritten to imply that earlier delivery locations
never existed.

## Explicit out of scope

WU4 does not include:

- Theme Module implementation or lifecycle work;
- Brand Colors implementation or final future owner selection;
- Brand Kit, multi-brand, or white-label capability;
- page builder, arbitrary layout editor, Homepage content builder, or generic/
  arbitrary content-assignment/provider-arbitration infrastructure;
- multiple independently configurable Homepage sections, arbitrary section
  composition, drag-and-drop ordering, or custom Homepage block authoring;
- Form/Product/provider-specific Homepage sources or new content-authoring
  capability;
- General Page content-management implementation;
- Collection Page as a separate Built-in page type;
- automatic Article, Form, Product, or similar Built-in page types;
- Navigation implementation/refinement beyond Homepage identity separation;
- footer-management system;
- new System Page or system-state engine;
- new Security, MFA, recovery, session/password policy, verified-email login,
  or owner-recovery capability;
- SMTP/mail transport/provider/test-delivery infrastructure;
- auth/account-recovery work;
- per-user Admin Appearance;
- Font/Typography configuration system;
- generic palette/color or derived-color engine;
- arbitrary semantic/status color mapping;
- unbounded Branding migration or cleanup beyond the bounded active-consumer
  and broken-reference safety condition defined above;
- installer first-admin changes;
- immutable username implementation;
- production reconciliation;
- Deferred Item adoption; and
- pixel-perfect recreation of `Built-in Public View Guideline.png`.

## Acceptance and validation boundary

WU4 implementation acceptance must include, as applicable:

- authority and persistence preservation;
- canonical route and compatibility behavior;
- permission and CSRF boundaries;
- duplicate-editor and projection supersession reconciliation;
- Homepage Hero Image reference safety and Core Media ownership;
- one-Main-Color Webcore Color Scheme behavior and semantic-color separation;
- zero-Theme Built-in Public View behavior;
- truthful Security and Email states;
- preservation of System, Module, and System Health authorities; and
- human/product review of information architecture, visual behavior,
  accessibility, responsive behavior, and cross-surface comprehension.

Runtime, unit, regression, and browser validation belong to separately
authorized implementation and acceptance work. This contract itself records
no implementation result and no release readiness.

## Authorization

Authorized by this contract:

- WU4 documentation and implementation-boundary use after separate technical
  execution authorization;
- source-grounded reconciliation of the dispositions listed above; and
- later WU4 validation within the locked scope.

Not authorized by this contract:

- PHP, JavaScript, CSS, schema, settings, data, package, Module, Theme,
  lifecycle, or runtime implementation;
- runtime mutation or unbounded destructive cleanup outside the bounded legacy
  Branding condition defined above;
- release, version bump, tag, package creation, publication, or distribution;
- adoption of unrelated Deferred Items; or
- WU4 Batch 1 implementation.
