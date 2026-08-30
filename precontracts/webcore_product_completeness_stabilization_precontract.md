# Webcore Product Completeness & Stabilization — Pre-contract

Pre-contract lifecycle: MATERIALIZED / PRE-PROMOTION PLANNING
Workstream class: CORRECTIVE WEBCORE PRODUCT-COMPLETENESS / STABILIZATION WORKSTREAM
Placement: Post-M3
Milestone relationship: Post-M3 corrective workstream; not MR.2, not a new MR milestone, and not a release milestone
Promotion status: NOT PROMOTED
Implementation authorization: NONE
Release / tag / publication authorization: NONE

## 1. Purpose

Define the bounded corrective workstream required to complete COPOT's already-accepted Webcore product baseline so that a valid installation remains usable as a site and Admin product with zero optional Modules and zero Themes.

This workstream does not reopen the accepted Webcore architecture. It corrects missing, fragmented, duplicated, or incomplete product-facing operator projections over already-authoritative Webcore capabilities and authorities.

The workstream is a HARD predecessor to Bundled Module Refinement Preparation & Reconciliation. Bundled Module refinement must not proceed on the assumption that optional Managers are required to operate baseline Webcore capability.

This Pre-contract is a planning artifact only. It does not authorize implementation, repository mutation beyond its own approved materialization/reconciliation, branch creation, schema migration, destructive cleanup, release, tag, publication, or direct transfer to Codex or another executor.

## 2. Problem Statement

The accepted Webcore architecture already requires complete minimum viability with zero optional Modules and zero Themes.

The underlying Webcore capability and ownership architecture is materially complete, but zero-optional product operability is still incomplete because three required operator-facing projections are missing or fragmented:

1. Core Media Admin baseline is missing.
2. Core primary Navigation management projection is missing.
3. A dedicated Webcore Site Settings projection is missing.

The Site Settings gap additionally includes:

- duplicated or overlapping product-facing Localization projections;
- split Site Name and Site Tagline management;
- split Logo and Favicon management;
- fragmented Branding/Appearance management;
- product-facing operation that still depends partly on historical or overlapping Settings surfaces.

These are product-completeness gaps, not evidence of a new architecture conflict.

## 3. Authoritative Baseline

### 3.1 Webcore minimum viability

A valid COPOT installation must remain usable with:

- zero optional Modules; and
- zero Themes.

Webcore owns the minimum viable baseline. Optional Modules may extend that baseline but must not be prerequisites for baseline operation.

### 3.2 Already-delivered Webcore capabilities

The following accepted capabilities are not reopened by this workstream unless concrete regression evidence requires a bounded correction:

- Webcore Content capability;
- Webcore Content Admin baseline;
- Webcore Media capability and persistence/service authority;
- Webcore primary Navigation capability and persistence/service authority;
- Webcore Redirects capability;
- Built-in Public View;
- Webcore Site Identity;
- Settings Platform;
- Branding Foundation;
- System Manager baseline;
- Admin Shell and accepted shared Admin presentation foundations.

### 3.3 Existing authority preservation

The workstream must preserve existing singular authorities.

Settings definitions, defaults, validation, typed serialization, effective-value fallback, and persistence remain under the accepted Settings Platform authority.

Logo and Favicon file validation, storage, activation, cleanup, and public serving remain under the accepted Site Asset authority.

Webcore Branding remains the upstream durable authority for accepted baseline Branding data and palette behavior.

Core Media services and persistence remain authoritative for baseline Media.

Core Navigation services and persistence remain authoritative for the primary Navigation baseline.

This workstream must not create competing state, persistence, lifecycle, provider, or ownership authorities merely to support new product-facing projections.

## 4. Baseline Capability vs Missing Product Projection Classification

| Area | Capability / authority state | Product-facing projection state | Corrective need |
| --- | --- | --- | --- |
| Content | Delivered / authoritative | Delivered / accepted | None without regression evidence |
| Media | Delivered / authoritative | Missing Core Admin baseline | Materialize baseline Admin projection |
| Primary Navigation | Delivered / authoritative | Missing Core Admin management projection | Materialize baseline Admin projection |
| Redirects | Delivered / authoritative | No Core CRUD currently required | None |
| Built-in Public View | Delivered / authoritative | Delivered / accepted | None |
| Site Identity | Delivered through existing authorities | Fragmented | Consolidate product-facing projection |
| Localization | Delivered through Settings authority | Duplicated / overlapping | Reconcile canonical product-facing projection |
| Webcore Branding | Delivered / authoritative | Fragmented | Consolidate bounded Appearance projection |
| Site Color Scheme | Planning projection over existing Branding | Not yet materialized as a bounded product projection | WU4 contract work only |

This classification is a scope-control boundary.

“Webcore Product Completeness” must not be interpreted as permission to reopen every Webcore capability.

## 5. Root Completeness Gaps

### 5.1 Core Media Admin baseline

Webcore already owns baseline Media capability.

The missing capability is an always-available Webcore-owned Admin projection over existing Core Media authority.

The Core Media Admin baseline must support the minimum operator capability required to keep baseline Media product-operable without Media Manager.

Media Manager remains a retained Bundled Module that EXTENDS baseline Media. It must not become a dependency of the Core Media Admin baseline.

### 5.2 Core primary Navigation Admin baseline

Webcore already owns one usable primary Navigation capability.

The missing capability is an always-available Webcore-owned Admin management projection over that capability.

Navigation Manager remains a retained Bundled Module that EXTENDS Webcore Navigation with advanced multi-menu, Theme-location, assignment, provider, visibility, targeting, and richer management capability.

### 5.3 Webcore Site Settings projection

Webcore already has the underlying settings, site identity, asset, localization, and branding authorities required for baseline site configuration.

The corrective need is a coherent product-facing Site Settings projection that consolidates operator experience without consolidating or replacing underlying ownership.

The intended product groups are:

- Site Identity;
- Localization;
- Appearance.

Exact presentation mechanics remain WU4 contract work.

## 6. Site Settings Ownership and Projection Model

The required direction is:

Settings / Site Asset / Branding authorities
→ Webcore Site Settings product projection
→ supported Webcore consumers

Product projection does not imply ownership transfer.

The invariant is:

Consolidate presentation and operator access.
Preserve underlying authority separation.

WU4 is authorized, after promotion and separate execution authorization, to reconcile the currently accepted System Manager and historical Settings product-facing projections into a canonical Webcore Site Settings operator path for Site Identity, Localization, and bounded Appearance. This changes product-facing projection ownership only. It does not transfer Settings, Site Asset, or Branding state, persistence, validation, security, or lifecycle authority.

Existing System Manager or historical Settings projections may remain only where source inspection proves a distinct lifecycle-specific, contextual, compatibility, or recovery purpose. They must not remain competing canonical editors for the same baseline setting family.

Where WU4 supersedes an accepted product-facing delivery location established by `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md` or described by `docs/11_branding_foundation.md`, the WU4 contract and closure documentation must explicitly record that projection-level supersession. Historical accepted authority records remain historical evidence and must not be rewritten to imply that the earlier delivery location never existed.

### 6.1 Site Identity

Site Settings may expose accepted Site Identity controls such as:

- Site Name;
- Site Tagline;
- Logo;
- Favicon.

Existing setting definitions, validation rules, and specialized asset lifecycle authorities remain authoritative.

### 6.2 Localization

Site Settings may expose accepted registered Localization controls:

- Locale;
- Timezone;
- Date Format;
- Time Format.

The corrective target is one canonical product-facing operator path.

Existing contextual or lifecycle-specific projections may remain only where source inspection proves that they serve a distinct justified purpose.

The workstream must not blindly delete overlapping controls merely because duplication exists.

The workstream does not introduce:

- Admin UI translation;
- multilingual Content;
- Language Packs;
- new localization architecture;
- per-user localization preferences.

### 6.3 Appearance

Site Settings may expose the accepted Webcore Branding baseline through a bounded Appearance projection.

The workstream must not:

- create an arbitrary token editor;
- expose arbitrary semantic role mapping;
- create a generic color engine;
- create competing appearance persistence;
- turn Theme-specific advanced appearance into Webcore baseline configuration.

## 7. Site Color Scheme Planning Boundary

Site Color Scheme is a bounded site-level resolved projection over existing Webcore Branding data.

The intended relationship is:

Brand Colors / Webcore Branding data
→ bounded resolved Site Color Scheme
→ Built-in Public View / Theme / permitted bounded consumers

The existing Branding data remains upstream durable authority.

This Pre-contract does not authorize:

- new `site_color_scheme.*` persistence;
- destructive migration from existing Branding keys;
- parallel Site Color Scheme ownership;
- generic provider arbitration;
- arbitrary semantic color mapping;
- generic derived-color infrastructure.

Theme may consume the accepted site-level appearance contract and may provide active-Theme-scoped appearance overrides where separately supported.

Theme-scoped overrides must not write back to or take ownership of Webcore site-level Branding data.

Semantic operational colors remain separate, including:

- warning;
- danger;
- success;
- information;
- validation;
- error;
- destructive-action states;
- other accessibility-critical operational states.

Advanced Brand Kit, multi-brand, white-label, per-user Admin Appearance, and unrelated Theme customization remain outside this workstream.

Exact projection and resolution mechanics remain WU4 contract work.

## 8. Preserved Negative Findings and Boundaries

The following findings remain preserved unless concrete new evidence contradicts them:

- no major Webcore architecture conflict is currently known;
- no schema migration is currently required;
- no settings migration is currently required;
- existing Settings, Branding, Site Asset, Media, Navigation, and other Core authorities must be preserved;
- Redirect Core Admin CRUD is not currently required;
- Content baseline is already delivered;
- Built-in Public View is already delivered;
- System Manager must not be wholesale reopened;
- Admin Shell does not require a new Work Unit without concrete evidence;
- Dashboard does not require a new Work Unit without concrete evidence;
- Bundled Module refinement is not part of this corrective workstream;
- package retirement is not automatically part of this workstream;
- Deferred Items are not automatically adopted.

## 9. Work Unit Topology

All Work Units remain NOT STARTED until authoritative promotion and the required execution authorization exist.

### WU1 — Webcore Completeness Contract & Scope Reconciliation

Purpose:

Lock the corrective workstream boundary against the accepted architecture and delivered repository state.

WU1 must:

- preserve existing ownership;
- lock the three root completeness gaps;
- classify delivered baseline versus missing product projection;
- define baseline-versus-extension boundaries;
- define zero-optional product acceptance;
- confirm exclusions;
- establish WU2-WU6 execution boundaries.

WU1 must not become a second Webcore architecture reconciliation.

### WU2 — Core Media Admin Baseline

Purpose:

Provide an always-available Webcore-owned Media Admin projection over existing Core Media authority.

Required baseline capability is limited to what is necessary for baseline Media product operability.

The baseline must support, subject to exact source evidence:

- Media inventory or equivalent baseline listing;
- upload;
- authoritative basic Media identity and metadata presentation;
- simple selection/reference behavior required by baseline Webcore consumers;
- usage/reference awareness required for safe lifecycle action;
- reference-safe deletion;
- existing permission boundaries;
- existing CSRF protection;
- existing validation;
- sanitized failure behavior.

Metadata editing is not automatically part of the baseline.

Editing may be included only where existing Core Media authority and source evidence already support it without importing Media Manager semantics.

Search, filters, pagination, card systems, advanced preview, or other workspace conveniences are not automatically baseline requirements. They may be included only when exact source/runtime evidence shows they are required for practical baseline usability without turning Core Media Admin into a miniature Media Manager.

Media Manager extension-only behavior remains outside baseline, including:

- processing variants;
- derivative management;
- crop;
- resize;
- rotate;
- advanced image editing;
- richer processing workflows;
- richer workspace conveniences;
- folders;
- galleries;
- bulk workflows;
- advanced organizational behavior.

Exact UI mechanics remain WU2 contract work.

### WU3 — Core Primary Navigation Admin Baseline

Purpose:

Provide an always-available Webcore-owned management projection for the accepted primary Navigation baseline.

Required baseline capability includes:

- one primary menu;
- add item;
- edit item;
- remove item;
- reorder items;
- bounded hierarchy;
- custom URL targets;
- Webcore Content targets;
- minimum validation;
- safe lifecycle behavior;
- operation without Navigation Manager.

The Pre-contract locks capability, not interaction technique.

For example:

Reordering is required.
Drag-and-drop is not required.

The following are not automatically part of the Core baseline:

- multiple independent menus;
- Theme location assignment;
- assignment management;
- provider browsing;
- advanced provider capability;
- per-item advanced visibility rules;
- advanced target types;
- bulk actions;
- navigation analytics;
- menu import/export.

Navigation Manager remains the extension boundary for advanced capability.

Redirect management remains separate and must not be silently absorbed into WU3.

Exact UI mechanics remain WU3 contract work.

### WU4 — Webcore Site Settings & Appearance Consolidation

Purpose:

Provide one coherent Webcore Site Settings operator projection while preserving existing Settings, Site Asset, and Branding ownership.

Internal scope classification:

Site Settings Projection Consolidation
+
Bounded Appearance Resolution

The WU may organize controls into:

- Site Identity;
- Localization;
- Appearance.

The WU must reconcile duplicated, overlapping, and fragmented product-facing surfaces.

The target is one canonical Site Settings operator path. WU4 may supersede System Manager or historical Settings as the canonical product-facing delivery location for the affected baseline setting family, but only at the projection layer. It must not supersede or duplicate the underlying Settings, Site Asset, or Branding authorities.

However, overlapping controls must not be removed blindly.

A contextual, compatibility, recovery, or lifecycle-specific projection may remain when source inspection proves that it serves a distinct justified function. Any retained projection must not remain a competing canonical editor for the same baseline setting family.

The WU must:

- preserve existing Settings authority;
- preserve Site Asset authority;
- preserve Branding authority;
- explicitly record any projection-level supersession of previously accepted System Manager or historical Settings delivery locations;
- avoid competing persistence;
- avoid new provider arbitration;
- avoid generic settings-editor behavior;
- avoid wholesale System Manager reopening.

Site Color Scheme mechanics may be materialized only as a bounded resolved projection over accepted Branding lineage.

Subjective information architecture, grouping, Appearance behavior, and usability require human/product review before relevant acceptance.

### WU5 — Zero-Optional Product Acceptance

Purpose:

Prove the accepted minimum-viability promise as integrated product behavior.

Acceptance must demonstrate that with zero optional Modules and zero Themes:

- Content remains operable through the accepted Core baseline;
- Media is operable through Core authority and Core Admin projection;
- primary Navigation is operable through Core authority and Core Admin projection;
- Site Settings are operable without requiring an optional or retired product-facing Settings Manager;
- Built-in Public View remains usable;
- required Site Identity remains operable;
- supported Appearance behavior remains available;
- optional retained Managers are not baseline dependencies.

Where retained Bundled Managers are enabled, acceptance must confirm that they extend rather than replace, fork, or take over Webcore baseline authority.

WU5 is an integrated product acceptance unit.

It is not authorization to redesign every surface being tested.

### WU6 — Stabilization & v0.14.0 Readiness Closure

Purpose:

Stabilize the completed corrective workstream and determine whether the resulting Webcore state is suitable as a future v0.14.0 readiness baseline.

WU6 may include:

- focused directly impacted regressions;
- cross-capability stabilization;
- zero-optional dependency re-verification;
- documentation consistency;
- unresolved-finding disposition;
- final product-completeness acceptance evidence;
- readiness reporting.

WU6 does not authorize:

- version bump;
- release package creation;
- release tag;
- GitHub Release;
- publication;
- external distribution.

v0.14.0 remains a future release-planning boundary and a separate explicit gate.

## 10. Dependency and Downstream Topology

This workstream is a HARD predecessor to:

Bundled Module Refinement Preparation & Reconciliation

The planned downstream sequence remains:

1. Webcore Product Completeness & Stabilization
2. Bundled Module Refinement Preparation & Reconciliation
3. Content Manager Refinement
4. Media Manager Refinement
5. Navigation Manager Refinement
6. Theme Manager Refinement
7. Users & Access Refinement
8. Form Manager Refinement

This sequence is planning topology only.

It does not authorize downstream implementation.

The HARD dependency exists because retained Bundled Modules must be refined against a complete zero-optional Webcore baseline rather than against product gaps that force optional Modules to behave as accidental prerequisites.

## 11. Acceptance Model

### 11.1 Objective / AI-verifiable acceptance

Use objective or sufficiently deterministic validation for:

- authority preservation;
- optional-Module independence;
- route/service behavior;
- validation;
- error boundaries;
- CRUD/lifecycle correctness;
- coexistence with retained Bundled Managers;
- public consumption compatibility;
- accessibility criteria that are deterministic;
- responsive and technical presentation checks;
- directly impacted regressions;
- final diff and unintended-change review.

### 11.2 Human/product acceptance

Human/product review is required only where subjective judgment is materially necessary.

Expected human/product gates include:

- Core Media Admin usability when materially new presentation is introduced;
- Core primary Navigation management usability when materially new presentation is introduced;
- Site Settings information architecture;
- Site Settings grouping and comprehension;
- bounded Appearance behavior and visual result.

Accepted Content baseline must not be reopened for subjective redesign without concrete regression evidence.

## 12. Runtime and Verification Requirements

Implementation Work Units must use a freshly verified runtime derived from authoritative implementation source.

The historical `C:\xampp\htdocs\copot.test` runtime must not be treated as current authority without fresh verification.

Validation must begin closest to each change and expand only according to actual impact.

Broad historical suites must not be rerun merely for ceremony when accepted evidence remains valid and no regression signal exists.

For zero-optional acceptance, the runtime must prove the intended baseline state with optional Modules and Themes absent, disabled, or otherwise non-required according to the exact acceptance contract.

Human/product review should use a disposable or safely controlled runtime where required and must not silently mutate an unrelated authoritative development instance.

## 13. Explicit Exclusions

This workstream does not authorize:

- a second Webcore architecture reconciliation;
- Content Manager refinement;
- Media Manager refinement;
- Navigation Manager refinement;
- Theme Manager refinement;
- Users & Access refinement;
- Form Manager refinement;
- rich-text/editor implementation;
- taxonomy ownership transfer;
- advanced Media processing;
- advanced Navigation multi-menu/provider/location capability;
- Redirect Core Admin CRUD without new evidence;
- generic Settings Manager replacement framework;
- generic provider/capability framework;
- generic color engine;
- arbitrary branding token system;
- editable semantic operational-color mapping;
- advanced Brand Kit;
- multi-brand;
- white-label system;
- per-user Admin Appearance;
- broad Admin Shell redesign;
- Dashboard redesign;
- new schema/settings migration without concrete technical evidence;
- package retirement merely because an old product-facing Manager is no longer preferred;
- destructive cleanup;
- Deferred Item adoption;
- production reconciliation;
- release;
- tag;
- publication;
- external distribution.

## 14. v0.14.0 Readiness Boundary

Successful closure of this workstream is intended to establish a product-completeness and stabilization boundary suitable for future v0.14.0 planning.

The intended post-condition is:

- Webcore minimum viability is not only architecturally owned but product-operable;
- zero optional Modules and zero Themes remain a valid baseline;
- major Webcore surgery should become exceptional rather than a normal prerequisite of Bundled Module refinement;
- later refinement may still introduce bounded corrections or extension seams when justified.

This does not mean v0.14.0 must be released immediately after workstream closure.

Release planning, version advancement, packaging, tag, publication, and distribution remain separate explicit gates.

## 15. Promotion and Authorization Boundary

Repository materialization of this Pre-contract is a planning-document action only.

It does not authorize implementation.

Promotion into an authoritative repository contract requires separate explicit authorization.

After promotion, execution of Work Units remains governed by the promoted scope and explicit executor-facing authorization boundaries.

Do not infer implementation authorization from:

- Workplan state;
- this Pre-contract;
- Handoff state;
- NRP state;
- previous workstream closure;
- future release intent.

## 16. Source and Provenance References

Primary authoritative and planning sources:

- `workplan.md`;
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`;
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`;
- `docs/46_webcore_content_admin_baseline_contract.md`;
- `docs/08_settings_system.md`;
- `docs/11_branding_foundation.md`;
- `concepts/copot_site_color_scheme_concept.md`;
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md`;
- relevant accepted Admin presentation contracts only where later implementation needs them.

Existing source code and tests are implementation evidence, not planning authority, and should be inspected progressively per Work Unit rather than copied wholesale into this planning artifact.

## 17. Current Disposition

Planning verdict: MATERIALIZED / READY FOR PROMOTION REVIEW

Authoritative promotion: NOT AUTHORIZED

Technical implementation: NOT AUTHORIZED

Codex / executor transfer: NOT REQUESTED

Release / tag / publication: NOT AUTHORIZED
