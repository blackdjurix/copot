# COPOT Workplan — Date version: 2026-08-26 20:07:08 WIB

Lifecycle: CURRENT / EARLY MATERIAL UPDATE
Project: COPOT
Canonical durable planning file: `workplan.md`
Immediate historical predecessor: `workplan_260826_193300.md`

## 1. Purpose, Authority, and Maintenance

This file is the canonical durable COPOT Workplan.

It is a living planning, sequencing, lifecycle-index, Concept-index, and provenance registry. It does not override committed repository truth, authorize implementation, auto-adopt Deferred Items, create repository lifecycle state, authorize production reconciliation, or authorize release/tag/publication/external distribution.

Repository contracts, committed source/tests, and independently verified remote Git state remain authoritative for delivered/current lifecycle truth.

Registry rules:
- previously valid logical planning or Concept identities must not disappear merely because they were promoted, incorporated, decomposed, superseded, retired, or closed;
- promoted/closed items remain lightweight provenance entries;
- unresolved, future, deferred, excluded, and operational-gate states remain distinct;
- detailed semantic content belongs in `concept.md`, historical Concept sources, or authoritative repository contracts;
- Workplan planning state is not implementation authority;
- a Workplan entry may be promoted only through an explicit decision plus durable repository authority.

Maintenance rule from this revision forward:
- canonical filename remains exactly `workplan.md`;
- the date version is embedded in the first line and must change on every material revision;
- do not create new timestamped Workplan filenames for routine revisions;
- historical timestamped Workplans remain provenance and must not be deleted or rewritten merely to normalize naming;
- future routine maintenance should be performed by Codex against the authoritative repository, with focused lineage comparison before mutation;
- when updating, compare the current file against relevant historical Workplan/Concept lineage and repository authority so logical entries are not silently dropped.

## 2. Lineage Audit Basis

This canonical revision reconciles the File Library Workplan lineage recovered from at least the following versions:

- `workplan_260806_225454.md`
- `workplan_260806_232410.md`
- `workplan_260807_055035.md`
- `workplan_260807_185730.md`
- `workplan_260808_055930.md`
- `workplan_260808_230337.md`
- `workplan_260809_201113.md`
- `workplan_260809_210842.md`
- `workplan_260809_215239.md`
- `workplan_260810_020950.md`
- `workplan_260810_060139.md`
- `workplan_260810_083951.md`
- `workplan_260810_185400.md`
- `workplan_260814_151800.md`
- `workplan_260814_172800.md`
- `workplan_260816_194430.md`
- `workplan_260817_021900.md`
- `workplan_260820_120500.md`
- `workplan_260820_234300.md`
- `workplan_260824_140100.md`
- `workplan_260825_121400.md`
- `workplan_260825_180300.md`
- `workplan_260826_193300.md`

The previous GitHub planning file was also compared directly:
- `workplan_260826_193300.md` at repository root.

Historical versions remain provenance. This file consolidates their still-material logical identities and current dispositions rather than copying stale intermediate execution states wholesale.

## 3. Current Durable Repository State Relevant to Planning

Authoritative repository: `https://github.com/blackdjurix/copot.git`
Authoritative branch: `main`

Current durable planning anchor before this canonical file was created:
- `31997fda41675b703fb75c8072b6361ecc2c638e`
- `docs: add reconciled MR.2 workplan`

Material closed/promoted provenance:

### Package Lifecycle & Migration Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED
Authority: `docs/28_package_lifecycle_migration_foundation_contract.md`
Planning action: NONE / PROVENANCE

### Module Package Lifecycle
Class: PROMOTED
Status: COMPLETE / CLOSED
Authority: `docs/29_module_package_lifecycle_contract.md`
Planning action: NONE / PROVENANCE

### Existing-Runtime Webcore Lifecycle Adoption
Class: PROMOTED
Status: TECHNICAL TRACK COMPLETE / CLOSED
Authority: `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`
Relation: Production Webcore Reconciliation remains separate.
Planning action: NONE / PROVENANCE

### Backup & Recovery Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED
Authority: `docs/31_backup_recovery_foundation_contract.md`
Planning action: NONE / PROVENANCE

### Webcore Deployment & Portability Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED
Authority: `docs/32_webcore_deployment_portability_foundation_contract.md`
Planning action: NONE / PROVENANCE

### v0.13.0 Release Readiness
Class: RELEASE / PROMOTED
Status: COMPLETE / CLOSED / RELEASED / PUBLICLY VERIFIED
Authority: `docs/33_v0_13_0_release_readiness_contract.md` and GitHub Release `v0.13.0`
Planning action: NONE / PROVENANCE

### Multi-Installation Isolation Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED
Authority: `docs/34_multi_installation_isolation_foundation_contract.md`
Planning action: NONE / PROVENANCE

### System Health & Status
Class: PROMOTED
Status: COMPLETE / CLOSED / CONTRACT LOCKED
Authority: `docs/35_system_health_status_contract.md`
Planning action: NONE / PROVENANCE

### MR.1 — Installation Refinement
Class: PROMOTED / MR
Status: COMPLETE AND CLOSED
Authority: `docs/36_mr_1_installation_refinement_contract.md`
Planning action: NONE / PROVENANCE

### Database Ownership & Lifecycle Management Foundation
Class: PROMOTED
Status: COMPLETE AND CLOSED
Authority: `docs/37_database_ownership_lifecycle_management_foundation_contract.md`
Accepted outcomes include singular table ownership, owner-bounded migration authority, multi-lineage compatibility, bounded Database-only Update Case C, Installer intents Fresh / Coexist / Adopt, shared-database topology verdicts, and persisted Retry acceptance.
Planning action: NONE / PROVENANCE

### Post-M3 — Webcore & Extension Architecture Reconciliation
Class: PROMOTED / ARCHITECTURE WORKSTREAM
Status: COMPLETE AND CLOSED
Authority: `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`
Planning action: KEEP CLOSED PROVENANCE

## 4. Accepted Current Architecture Inputs

The Post-M3 Webcore & Extension Architecture Reconciliation remains current architecture authority.

Locked planning inputs:
- Webcore owns complete minimum viability.
- A valid COPOT installation remains usable with zero optional Modules and zero Themes.
- Built-in Public View is the no-Theme fallback.
- Current vocabulary is `Module` / `Bundled Module`, not `Core Module`.
- Content Manager RETAIN / EXTENDS Webcore Content.
- Media Manager RETAIN / EXTENDS Webcore Media.
- Navigation Manager RETAIN / EXTENDS Webcore Navigation.
- Theme Manager RETAIN.
- Users & Access RETAIN.
- Form Manager RETAIN.
- Module Manager RETIRE as standalone product-facing destination; Module lifecycle is Webcore/System Manager-owned.
- Settings Manager RETIRE as standalone product-facing Module; Settings Platform remains reusable internal machinery.
- Redirect Manager RETIRE; Redirects are Webcore-native.
- Taxonomy remains Module-owned by default; Taxonomy Manager remains a high-confidence retirement candidate.
- System Manager remains product-facing parent/aggregator, not a generic ownership bucket.
- Dashboard remains separate from MR.2.

Architecture reconciliation is not reopened by this Workplan.

## 5. MR.2 Historical Accepted State

MR.2 remains open.

Historical accepted WUs remain valid provenance:

### WU1 — Webcore Admin View Foundation
Status: COMPLETE AND CLOSED
Authority: `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`
Disposition: KEEP CLOSED. Admin Page Frame remains valid but is not the complete shared visual primitive system.

### WU2 — Webcore System Manager Baseline
Status: COMPLETE AND CLOSED
Authority: `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`
Disposition: KEEP CLOSED / HISTORICAL BASELINE.

### WU3 — System Manager Lifecycle & Modules UX Refinement
Status: COMPLETE AND CLOSED
Authority: `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`
Final accepted anchor: `740fe9abbe3f5b552f041a9ee0dd4b8892907290`
Objective validation: PASS
Human/product acceptance: PASS
Disposition: KEEP CLOSED.

### WU4 — Content Manager Refinement
Status: COMPLETE AND CLOSED
Authority: `docs/42_mr_2_wu4_content_manager_refinement_contract.md`
Final accepted anchor: `086f53452ff029933d0dff08cfee75ee98407230`
Objective validation: PASS
Human/product acceptance: PASS
Disposition: KEEP CLOSED. Accepted Content surfaces may serve as reference evidence where relevant to shared primitives.

## 6. MR.2 Forward Scope Reconciliation

MR.2 forward scope is narrowed from the historical per-module topology.

Current forward boundary:

**MR.2 = shared Admin artifacts / shared visual primitives / cross-surface presentation consistency.**

Reason:
- architecture changed the old Core Module topology into a Webcore-minimum + optional Bundled Module architecture;
- retained Bundled Modules now extend mature Webcore capability and may own specialized UI/UX;
- per-Bundled-Module refinement can cross domain-specific Webcore/Module seams and is too complex to remain one generic MR.2 surface-by-surface pass;
- shared Admin styling and presentation primitives are a concrete cross-surface concern with repository-wide consumers.

Per-Bundled-Module refinement is redistributed into dedicated future workstreams.

## 7. Shared Admin Primitive Model

Original refinement lineage remains material, including:
- consistent page headers;
- compact rectangular actions;
- higher-density forms and lists;
- flat / square-corner visual language by default;
- shared spacing;
- shared typography;
- shared action hierarchy;
- shared search/filter treatment;
- shared form/helper treatment;
- shared modal/action spacing;
- responsive and accessible behavior;
- no visually isolated per-module redesign.

Candidate canonical shared primitives:
- page title;
- eyebrow/contextual kicker usage;
- page header composition;
- section/card/surface treatment;
- toolbar/search/filter treatment;
- form field layout;
- helper text;
- action row;
- paired button sizing;
- modal/dialog action spacing;
- typography scale;
- spacing/grid;
- surface geometry/radius policy;
- responsive behavior;
- accessibility baseline.

Single-source rule:
- equivalent shared presentation elements consume canonical shared tokens/classes/artifacts;
- page/Module CSS may specialize domain presentation but should not redefine a shared primitive without a material semantic reason;
- a change to one canonical primitive should propagate to intended consumers unless an explicit semantic exception is justified;
- flat/square treatment is the default visual direction unless a specific component requires an exception.

## 8. System Manager Distributed Refinement Model

System Manager is a product-facing parent/aggregator, not a single ownership bucket.

Forward planning rule:
- shared visual primitives consumed by System Manager may be normalized inside MR.2;
- domain-specific System Manager representation remains owned by the workstream that owns or materially reconciles the underlying capability;
- completing one System Manager area does not imply full System Manager completion;
- later domain workstreams may adjust their System Manager representation only when required by the accepted domain boundary;
- MR.2 closure verifies shared cross-surface consistency but does not create missing domain capability.

Current distribution:
- Modules: canonical product-facing lifecycle surface closed through historical WU3.
- System: Webcore lifecycle/update/repair/recovery foundation exists; later Webcore-native concerns belong to relevant owner workstreams.
- Site: identity, branding, localization, and related settings follow underlying capability ownership; Theme-specific presentation remains Theme Manager-owned.
- Security: future Users & Access workstream only when backed by authoritative capability evidence; no placeholder security infrastructure.
- Email: conditional on authoritative outbound-email capability evidence; no standalone Email workstream is created merely for presentation.
- System Health: existing System Health & Status remains evidence authority; later work is presentation refinement unless a separately authorized capability change is proven.

## 9. Current Media WU5 Disposition

Historical repository contract:
`docs/43_mr_2_wu5_media_manager_refinement_contract.md`

Current planning classification:
- contract: PROMOTED HISTORICALLY / FORWARD SCOPE SUPERSEDED PENDING REPOSITORY-SIDE RECONCILIATION;
- implementation: EXISTS / NOT ACCEPTED;
- last human acceptance: INVALIDATED because the implementation diverged from original refinement intent;
- objective/correctness evidence remains reusable where technically valid;
- commit subjects or previous acceptance wording do not establish closure.

Do not blindly revert. First classify existing changes as:
1. valid correctness fixes;
2. shared-artifact candidates;
3. Media-specific refinement for future Media Manager workstream;
4. local redesign that should not survive.

Further Media-specific refinement remains HOLD until shared MR.2 baseline is established and the existing delta is audited against it.

## 10. Reconciled MR.2 Forward Topology

### WU5 — Shared Admin Primitive Audit & Contract
Status: NEXT / PREPARATION TARGET

Objective:
Audit current Admin presentation implementation and accepted refinement lineage, identify canonical shared primitives versus duplicated/local variants, and prepare/promote a forward-authoritative shared refinement contract only through explicit authorization.

Minimum audit surface:
- title and eyebrow;
- page header;
- surface/card composition;
- search/filter and search-to-content spacing;
- form field/helper patterns;
- button sizing and action placement;
- modal/dialog action rhythm;
- typography scale;
- spacing/grid;
- radius/flat-surface policy;
- responsive/accessibility behavior;
- shared CSS/token/artifact ownership.

### WU6 — Shared Artifact Consolidation & Implementation
Status: PLANNED / FUTURE

Objective:
Implement bounded canonical shared primitives using single-source Admin artifacts/tokens/classes.

Constraints:
- preserve domain behavior;
- avoid per-Bundled-Module redesign;
- use minimum necessary consumer adjustments;
- no Dashboard composition redesign;
- no domain workflow expansion;
- no Deferred adoption.

### WU7 — Representative Adoption & Propagation Proof
Status: PLANNED / FUTURE

Objective:
Normalize a small representative set of accepted Admin consumers sufficient to prove propagation, compatibility, responsive behavior, accessibility, and explicit exception handling.

Representative adoption is proof of shared foundation, not a hidden per-Module refinement pass.

### WU8 — Cross-Surface Verification & MR.2 Closure
Status: PLANNED / FUTURE

Must verify:
- shared primitive single-source behavior;
- typography/spacing/action/surface consistency;
- flat/square default treatment;
- explicit exception handling;
- System Manager shared presentation consistency without taking domain ownership;
- no retired-manager restoration;
- truthful empty/unsupported states rather than placeholder infrastructure;
- unresolved System/Site/Email/System Health presentation gaps are resolved by owners or carried forward truthfully;
- Taxonomy Manager disposition is revisited only if accumulated evidence requires a final decision;
- no domain capability expansion;
- no Dashboard-specific redesign;
- documentation consistency;
- unresolved MR.2 thread-level planning payload disposition.

WU8 is not authorization for full per-Bundled-Module UX refinement.

## 11. Dedicated Future Bundled Module Refinement Workstreams

Separate future planning identities, each requiring focused preparation/audit and contract before implementation:
- Media Manager Refinement;
- Navigation Manager Refinement;
- Theme Manager Refinement;
- Users & Access Refinement;
- Form Manager Refinement.

Each dedicated workstream must consume the shared Admin foundation instead of recreating equivalent shared styling.

Media Manager future workstream must audit at minimum:
- advanced library/workspace;
- upload/intake UX;
- thumbnail/list views;
- preview/detail;
- processing/variants;
- Content picker integration;
- crop and consumer-profile UX;
- deletion/usage safety presentation;
- domain-specific correctness;
- maturity against shared Admin foundation.

Media guardrails:
- preserve Webcore minimum Media viability;
- preserve consumer-driven integration contracts;
- keep generic processing free of hardcoded Content-specific assumptions;
- preserve Module-owned advanced state/processing ownership;
- touch System Manager representation only when authoritative ownership evidence requires it.

Before future Media scope adoption classify, rather than auto-adopt:
- folders/organizational grouping;
- galleries;
- bulk workflow;
- drag-and-drop;
- expanded MIME/video/audio/archive capability;
- arbitrary consumer profiles;
- advanced editing/optimization;
- CDN/cloud/external storage;
- comparable architecture/product expansion.

## 12. Active / Future / Deferred / Operational Registry

### Production Webcore Reconciliation
Class: OPERATIONAL GATE
Status: NOT STARTED / SEPARATELY AUTHORIZED
Authority: `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md` for boundary
Planning action: KEEP / EXPLICIT AUTHORIZATION REQUIRED

### Server-Empty Bootstrap & Package Clean Install
Class: DEFERRED ITEM
Status: KEEP DEFERRED / UNSCHEDULED
Deferred ID: `DI-PACKAGE-LIFECYCLE-WU7-01`
Planning action: KEEP DEFERRED

### Module Permission Dependency & Base Access
Class: CONCEPT / FUTURE CAPABILITY
Status: FUTURE / PREPARATION CANDIDATE
Source: `copot_module_permission_dependency_base_access_concept_260809_210842.md`
Planning action: KEEP / NO AUTOMATIC MR.2 ADOPTION

### Module Package Identity & Capability Provider
Class: CONCEPT
Status: INDEXED / FUTURE
Sources: `copot_module_package_identity_and_capability_provider_concept.md` plus historical revision lineage
Candidate home: distribution/ecosystem planning when concrete consumers justify it
Planning action: KEEP

### Future Widget Layout
Class: CONCEPT
Status: FUTURE ARCHITECTURE
Source: `concept_future_widget_layout_260810_060139.md`
Current accepted Dashboard baseline remains separate; denser desktop-grid ideas remain candidate/not locked.
Planning action: KEEP

### Admin Shell / Dashboard Refinement
Class: FUTURE / SEPARATE WORKSTREAM
Status: KEEP
Boundary: Dashboard composition, widget layout, sidebar/topbar redesign, and Dashboard-specific behavior remain outside MR.2 unless explicitly reclassified.

### External Simulation Gate / Ecosystem Lineage
Class: FUTURE VALIDATION / ROADMAP LINEAGE
Status: KEEP REGISTERED
Planning action: NO AUTOMATIC SEQUENCING

### Official Remote Update / Distribution Ecosystem
Class: FUTURE ECOSYSTEM CONCEPT
Status: DEFERRED / NOT ADOPTED
Sources: Package Lifecycle Concept lineage
Planning action: KEEP AS FUTURE; no automatic remote-update implementation.

### Database Historical Release-Support Window
Class: FUTURE POLICY QUESTION
Status: OPEN
Current architecture already supports transition-aware multi-lineage compatibility; only exact historical support-window/product policy remains open.
Planning action: KEEP SEPARATE unless a concrete consumer requires it.

### Installation & Runtime Identity Exposure
Class: PLANNING CONCERN
Status: UNRESOLVED / SEPARATE
Direction: possible About/System exposure; historical proposed permission `system.runtime.manage`.
Planning action: KEEP / NO AUTOMATIC PRODUCT DECISION.

### Cross-Fileset Upgrade Ownership Proof Gap
Class: ARCHITECTURE / PRODUCT INVESTIGATION
Status: UNRESOLVED / SEPARATE / NON-BLOCKING BY DEFAULT
Planning action: KEEP SEPARATE; do not auto-adopt into MR.2.

### Stale Package-Owned File Reconciliation
Class: LIFECYCLE REFINEMENT / AUDIT CANDIDATE
Status: UNRESOLVED / SEPARATE
Need future audit for safe detection/deletion ownership proof, repair/recovery interaction, integrity verification, and rollback expectations.
Planning action: KEEP; no implementation until independent scope is proven.

### Taxonomy Manager
Class: ARCHITECTURE DISPOSITION
Status: HIGH-CONFIDENCE RETIREMENT CANDIDATE
Planning action: no dedicated refinement workstream unless later evidence changes disposition.

## 13. Concept/Refinement Registry Continuity

The following logical refinement identities remain registered even when their original vocabulary or workstream home changed:
- Refinement Milestone Governance;
- MR.x Session-Level Refinement Backlog;
- Core Modules & Dashboard Refinement umbrella, now DECOMPOSED / PROVENANCE;
- Core Module Consistency, now reconciled into shared MR.2 primitives plus dedicated future Bundled Module workstreams;
- Dashboard Composition, future/separate;
- System Health Presentation, future presentation refinement;
- Widget Foundation / Dashboard Widget, partly delivered/future evolution;
- Installer Finalize Installation UI, incorporated/closed through MR.1;
- Installer Database Selection & Multi-Installation UX, incorporated/closed through MR.1 and Multi-Installation foundation;
- Module Manager Lifecycle UX, historical refinement lineage now re-homed under System Manager architecture where applicable;
- Webcore Lifecycle Settings/Admin UX, retained only where current ownership makes it applicable;
- What's New presentation lineage, sourced from authoritative package/release metadata and requiring current-architecture consumer re-audit before future implementation.

Detailed current Concept disposition belongs in `concept.md`.

## 14. Thread-Level Continuity Reconciliation

Durably reconciled:
- `Shared Admin Visual Primitive Single-Source Rule`: adopted as MR.2 forward shared-artifact planning model.
- `Shared Admin Action Placement & Button Sizing Pattern`: candidate shared primitive to audit/lock in MR.2 WU5.

Still unresolved:
- `Shared File Intake Interaction Pattern`: selecting a file in a native picker may eventually trigger upload/intake automatically and continue to the next meaningful step. Potential consumers include Media, Content Featured Media, Module/package ZIP intake, and future Admin upload surfaces. It is not adopted into MR.2 implementation. Reconcile at MR.2 closure or earlier only if a material dependency requires a dedicated decision.

## 15. Current Next Target

### MR.2 WU5 — Shared Admin Primitive Audit & Contract

Classification:
- MR.2 CONTINUATION / RECONCILED FORWARD SCOPE;
- PREPARATION NEXT;
- NO TECHNICAL IMPLEMENTATION AUTHORIZED BY THIS WORKPLAN;
- repository-side contract reconciliation/promotion remains a separate explicit action.

Preparation questions:
1. Which shared Admin visual primitives already have a real single-source implementation?
2. Which consumers locally recreate equivalent primitives?
3. Which accepted surfaces are valid reference evidence for each primitive?
4. Which differences are semantic exceptions versus accidental inconsistency?
5. Which primitive changes can be introduced without domain redesign?
6. How should the old WU5 Media implementation delta be classified against the new baseline?
7. Which contracts/docs require forward-authoritative amendment without rewriting accepted historical records?

## 16. Lineage-Loss Findings Incorporated by This Revision

Compared with the previous GitHub Workplan, this canonical file restores or explicitly carries forward logical entries that earlier compressed revisions risked losing:
- full promoted/closed platform-foundation provenance;
- Refinement Milestone Governance and MR.x backlog provenance;
- Core Modules & Dashboard umbrella decomposition provenance;
- Dashboard Composition, System Health Presentation, Widget Foundation, and Future Widget Layout identities;
- Production Webcore Reconciliation operational gate;
- Server-Empty Bootstrap Deferred Item;
- Module Permission Dependency & Base Access;
- Module Package Identity & Capability Provider;
- External Simulation / ecosystem lineage;
- remote update/distribution future concept;
- Database historical release-support policy question;
- Installation & Runtime Identity Exposure;
- Cross-Fileset Upgrade Ownership Proof Gap;
- Stale Package-Owned File Reconciliation;
- What's New lineage;
- System Manager distributed-refinement rules;
- Site/Security/Email/System Health disposition;
- Media future-workstream ownership/deferred guardrails;
- shared Admin primitive/action patterns and unresolved shared file-intake concept.

Resolved historical items are not resurrected as open work. Their latest disposition is preserved instead.

## 17. Planning Adequacy and Authorization Boundary

Planning audit status: PASS AFTER LINEAGE CONSOLIDATION
Workplan lifecycle: CURRENT / EARLY MATERIAL UPDATE
Continuous repository-to-Workplan synchronization required: NO
Workplan adequate for next-target selection: YES

This file authorizes no technical implementation by itself.

Not authorized by this Workplan alone:
- source/runtime/test/schema/config mutation;
- contract promotion/amendment;
- broad CSS migration;
- old WU5 Media continuation;
- per-Bundled-Module implementation;
- Dashboard/Admin Shell redesign;
- Deferred adoption;
- destructive rollback/revert/reset;
- production reconciliation;
- branch deletion;
- release/tag/publication/external distribution.
