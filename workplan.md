# COPOT — Non-Linear Workplan
Date version: 2026-08-27 07:15:00 WIB
Workplan lifecycle: CURRENT
Project: COPOT

Current Workplan source: this repository file, `workplan.md`. GPT/File Library
Workplan versions are historical evidence and correction sources only; they do
not override the current Git Workplan. Neither this Workplan nor historical GPT
Workplans are implementation or repository-lifecycle authority.

## 1. Purpose and Authority

This Workplan is the living COPOT planning, sequencing, lifecycle-index, and provenance registry.

It does not:
- override committed repository truth;
- authorize implementation;
- auto-adopt Deferred Items;
- create repository lifecycle state;
- assert or establish repository lifecycle truth;
- automatically promote Concept or Pre-contract scope;
- authorize production reconciliation;
- authorize release/tag/publication/external distribution.

Repository contracts, committed source/tests, and independently verified remote Git state remain authoritative for delivered/current lifecycle truth.

Registry rules:
- Workplan stores logical Concept/work-item registration and minimal lifecycle/provenance metadata;
- detailed semantic content stays in individual Concept source files, target-specific Pre-contracts, or authoritative repository contracts according to artifact role;
- `Sources` refers directly to individual Concept files, never to a consolidated Concept registry intermediary;
- `Pre-contract` identifies a target-specific pre-promotion planning artifact and is indexed directly by Workplan;
- Pre-contract is not repository authority and does not authorize implementation or promotion by itself;
- Concept files do not need to index the Pre-contracts that consume them;
- after promotion, repository contract authority belongs under `Authority` while the reviewed Pre-contract remains provenance;
- complete/closed items remain registered as provenance;
- a valid planning identity is not erased merely because it is completed, promoted, incorporated, decomposed, superseded, retired, rejected, or re-homed;
- future, deferred, excluded, unresolved, and operational-gate states remain distinct;
- Workplan planning state is not repository implementation authority;
- promotion requires explicit decision plus durable repository authority.

The Workplan + Concept + Pre-contract model is the retained current planning
and provenance model. Its earlier trial/use within MR.2 is historical and does
not make MR.2 active. Trial materialization does not itself amend GPT-side
governance. Direct individual Concept-file references remain the semantic
source model; no consolidated Concept registry is a substitute for those
references.

## 2. Current Authoritative State Anchor

Authoritative repository: `https://github.com/blackdjurix/copot.git`
Authoritative branch: `main`

Current durable planning state:
- Post-M3 — Webcore & Extension Architecture Reconciliation: COMPLETE / CLOSED;
- MR.2: COMPLETE / CLOSED;
- MR.2 WU1–WU8: COMPLETE AND CLOSED;
- Webcore Content Admin Baseline: COMPLETE / CLOSED; corrective prerequisite
  for MR.2 WU7 is satisfied;
- old MR.2 Media WU5 implementation: EXISTS / NOT ACCEPTED;
- current MR.2 repository-side closure: COMPLETE / PASS;
- MR.2 Pre-contract trial layer: MATERIALIZED for historical/promoted WU1–WU4,
  old Media WU5 evidence, and promoted WU5–WU8 provenance;
- per-Bundled-Module refinement: redistributed to dedicated future workstreams;
- Dashboard: separate from MR.2;
- `DI-PACKAGE-LIFECYCLE-WU7-01`: KEEP DEFERRED / UNSCHEDULED;
- Shared File Intake Interaction Pattern: ADOPTED FUTURE CROSS-SURFACE CONCEPT
  at GPT/user planning layer; outside MR.2 and not implementation-authorized.

Current architecture vocabulary is `Module` / `Bundled Module`; historical `Core Module` wording remains provenance only where it appears in historical Concept sources.

## 3. Registry Entry Format

Each logical entry uses only relevant fields:
- `Class`
- `Status`
- `Sources`
- `Pre-contract`
- `Pre-contract status`
- `Relations`
- `Authority`
- `Planning action`

Source tags:
- `[PRIMARY]`
- `[SUPPORTING]`
- `[HISTORICAL]`
- `[ANCESTOR]`

`Sources` must name individual Concept files. `Pre-contract` is a separate planning/provenance field and must not be hidden under `Sources` or `Authority`. Repository contracts and other delivered truth belong under `Authority`, not `Sources` or `Pre-contract`.

## 4. Promoted / Closed Provenance Registry

### Package Lifecycle & Migration Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED

Sources:
- `copot_package_lifecycle_migration_concept_260804_161738.md` [PRIMARY]
- `copot_package_lifecycle_migration_concept_260803_220452.md` [HISTORICAL]
- `copot_release_update_migration_concept_260803_171900.md` [HISTORICAL / ANCESTOR]
- `copot_update_upgrade_migration_concept.md` [HISTORICAL / ANCESTOR]

Authority:
- `docs/28_package_lifecycle_migration_foundation_contract.md`

Planning action: NONE / PROVENANCE

### Module Package Lifecycle
Class: PROMOTED
Status: COMPLETE / CLOSED

Sources:
- `copot_package_lifecycle_migration_concept_260804_161738.md` — Module Package Lifecycle [PRIMARY]
- `copot_module_package_identity_and_capability_provider_concept.md` [SUPPORTING]
- `copot_module_package_identity_and_capability_provider_concept_260803_220452.md` [HISTORICAL]

Authority:
- `docs/29_module_package_lifecycle_contract.md`

Planning action: NONE / PROVENANCE

### Existing-Runtime Webcore Lifecycle Adoption
Class: PROMOTED
Status: TECHNICAL TRACK COMPLETE / CLOSED

Sources:
- `copot_package_lifecycle_migration_concept_260804_161738.md` — Existing Webcore Transition [SUPPORTING]

Relations:
- separate from Production Webcore Reconciliation.

Authority:
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`

Planning action: NONE / PROVENANCE

### Backup & Recovery Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED

Sources:
- `copot_backup_and_recovery_concept_260803_220452.md` [PRIMARY]

Relations:
- Package Lifecycle consumes Backup & Recovery where required; ownership remains separate.

Authority:
- `docs/31_backup_recovery_foundation_contract.md`

Planning action: NONE / PROVENANCE

### Webcore Deployment & Portability Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED

Authority:
- `docs/32_webcore_deployment_portability_foundation_contract.md`

Planning action: NONE / PROVENANCE

### v0.13.0 Release Readiness
Class: RELEASE / PROMOTED
Status: COMPLETE / CLOSED / RELEASED / PUBLICLY VERIFIED

Authority:
- `docs/33_v0_13_0_release_readiness_contract.md`
- GitHub Release `v0.13.0`

Planning action: NONE / PROVENANCE

### Multi-Installation Isolation Foundation
Class: PROMOTED
Status: COMPLETE / CLOSED

Sources:
- `multi_installation_isolation_foundation_concept_260804_092611.md` [PRIMARY]

Authority:
- `docs/34_multi_installation_isolation_foundation_contract.md`

Planning action: NONE / PROVENANCE

### System Health & Status
Class: PROMOTED
Status: COMPLETE / CLOSED / CONTRACT LOCKED

Sources:
- `concepts/copot_system_health_status_concept.md` [PRIMARY GIT CONCEPT]
- `copot_system_health_status_concept_260809_210842.md` [HISTORICAL/CORRECTION INPUT]
- `copot_system_health_status_concept_260805_083932.md` [HISTORICAL]
- `concept_future_widget_layout_260810_060139.md` [SUPPORTING]
- `Future_Widget_Layout_Contract_260804_161738.md` [HISTORICAL / SUPPORTING]

Authority:
- `docs/35_system_health_status_contract.md`

Future semantic extension of the same logical identity remains planning-only:
- hybrid pull + push health model;
- lifecycle-triggered, on-demand, and selectively periodic deterministic checks;
- normalized findings from subsystem/Extension owners;
- capability/provider resolution and provider-transition/migration health;
- missing, disabled, or conflicting provider; degraded or blocked capability;
- migration available, in-progress, failed, incomplete cutover, and stale or
  orphaned provider state;
- remediation and impact reporting.

System Health does not own provider selection or provider migration authority;
this extension does not reopen the completed `docs/35` foundation.

Planning action: KEEP FUTURE SEMANTIC EXTENSION / DO NOT REOPEN FOUNDATION

### MR.1 — Installation Refinement
Class: PROMOTED / MR
Status: COMPLETE AND CLOSED

Sources:
- `copot_consolidated_refinement_concepts_260810_020950.md` — Installer refinement identities [PRIMARY PLANNING INPUT]
- `concept_mrx_session_refinement_backlog_260809_201113.md` — installer sections [SUPPORTING]
- `concept_mrx_session_refinement_backlog_260809_093844.md` [HISTORICAL]
- `multi_installation_isolation_foundation_concept_260804_092611.md` [SUPPORTING]

Authority:
- `docs/36_mr_1_installation_refinement_contract.md`

Planning action: NONE / PROVENANCE

### Database Ownership & Lifecycle Management Foundation
Class: PROMOTED / POST-M3 FOUNDATION
Status: COMPLETE AND CLOSED

Sources:
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260816_194431.md` [PRIMARY / CLOSED PROVENANCE]
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260814_172800.md` [HISTORICAL]
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260814_151800.md` [HISTORICAL]
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260813_131300.md` [HISTORICAL]

Authority:
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`

Accepted outcomes include one authoritative owner per table, owner-aware migration authority, independent Webcore/Module schema lineages, transition-aware compatibility, bounded Database-only Update Case C, Installer intents Fresh / Coexist / Adopt, shared-database topology verdicts, and persisted Retry acceptance.

Planning action: NONE / PROVENANCE

### Post-M3 — Webcore & Extension Architecture Reconciliation
Class: PROMOTED / ARCHITECTURE WORKSTREAM
Status: COMPLETE AND CLOSED

Sources:
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [PRIMARY / CLOSED PROVENANCE]
- `concept_webcore_extension_architecture_reconciliation_260820_120500.md` [HISTORICAL]

Authority:
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`

Planning action: NONE / PROVENANCE

### MR.2 WU1 — Webcore Admin View Foundation
Class: PROMOTED / MR.2 WORK UNIT
Status: COMPLETE AND CLOSED

Sources:
- `copot_refinement_milestone_governance_concept_260803_220452.md` [SUPPORTING]
- `core_modules_dashboard_refinement_concept_260804_161738.md` [SUPPORTING]

Pre-contract:
- `precontracts/38_mr_2_wu1_webcore_admin_view_foundation_precontract.md`

Pre-contract status: MATERIALIZED HISTORICAL SNAPSHOT / TRIAL PROVENANCE

Authority:
- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`

Planning action: NONE / PROVENANCE

### MR.2 WU2 — Webcore System Manager Baseline
Class: PROMOTED / MR.2 WORK UNIT
Status: COMPLETE AND CLOSED

Sources:
- `copot_consolidated_refinement_concepts_260816_194432.md` [PRIMARY PLANNING LINEAGE]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [SUPPORTING ARCHITECTURE]

Pre-contract:
- `precontracts/39_mr_2_wu2_webcore_system_manager_baseline_precontract.md`

Pre-contract status: MATERIALIZED HISTORICAL SNAPSHOT / TRIAL PROVENANCE

Authority:
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`

Planning action: NONE / PROVENANCE

### MR.2 WU3 — System Manager Lifecycle & Modules UX Refinement
Class: PROMOTED / MR.2 WORK UNIT
Status: COMPLETE AND CLOSED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL REFINEMENT INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [SUPPORTING ARCHITECTURE]

Pre-contract:
- `precontracts/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_precontract.md`

Pre-contract status: MATERIALIZED HISTORICAL SNAPSHOT / TRIAL PROVENANCE

Authority:
- `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`

Final accepted anchor: `740fe9abbe3f5b552f041a9ee0dd4b8892907290`
Planning action: NONE / PROVENANCE

### MR.2 WU4 — Content Manager Refinement
Class: PROMOTED / MR.2 WORK UNIT
Status: COMPLETE AND CLOSED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL REFINEMENT INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]

Pre-contract:
- `precontracts/42_mr_2_wu4_content_manager_refinement_precontract.md`

Pre-contract status: MATERIALIZED HISTORICAL SNAPSHOT / TRIAL PROVENANCE

Authority:
- `docs/42_mr_2_wu4_content_manager_refinement_contract.md`

Final accepted anchor: `086f53452ff029933d0dff08cfee75ee98407230`
Planning action: NONE / PROVENANCE

### Webcore Content Admin Baseline
Class: CORRECTIVE WEBCORE BASELINE WORKSTREAM / ARCHITECTURE-TO-RUNTIME CONFORMANCE CORRECTION
Status: COMPLETE / CLOSED

Concept source:
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md`
  [EXISTING ARCHITECTURE PROVENANCE]

Pre-contract:
- `precontracts/webcore_content_admin_baseline_precontract.md`

Pre-contract status: PROMOTED / HISTORICAL PROVENANCE

Authority:
- `docs/46_webcore_content_admin_baseline_contract.md`

Accepted implementation anchor:
- `595e9a3e81b61793c439bd0f6688e4f32b58c989`

Relations:
- prerequisite before MR.2 WU7 resumes;
- separate from Content Manager refinement;
- separate from MR.2 shared-artifact implementation.

Planning action: NONE / PROVENANCE

## 5. Active / Future / Deferred / Operational Registry

### Webcore Product Completeness & Stabilization
Class: CORRECTIVE WEBCORE PRODUCT-COMPLETENESS / STABILIZATION WORKSTREAM
Status: FUTURE / NEXT PREPARATION TARGET / AUDIT COMPLETE / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_site_color_scheme_concept.md` [PRIMARY GIT CONCEPT]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [ARCHITECTURE LINEAGE]

Authority:
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md` for accepted Webcore ownership and boundary evidence only
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md` for accepted System Manager and Branding baseline evidence only

Relations:
- HARD predecessor to Bundled Module Refinement Preparation & Reconciliation;
- separate from Bundled Module implementation;
- separate from release authorization;
- stabilization readiness boundary is intended to inform v0.14.0 planning only.

Confirmed audit-derived planning scope:
- Core Media Admin baseline is missing;
- Core primary Navigation management projection is missing;
- dedicated Webcore Site Settings projection is missing;
- localization has duplicated product-facing projections;
- Site Name, Tagline, Logo, and Favicon operator management is split and
  dependent on the retained Settings Manager surface;
- zero-optional product operability is incomplete until the required baseline
  operator surfaces are corrected.

Preserved negative findings:
- no major Webcore architecture conflict was found;
- no schema/settings migration is currently required;
- existing Core settings and storage authorities should be preserved;
- Redirect Core Admin CRUD is not currently required;
- Content baseline and Built-in Public View are already delivered;
- System Manager is not to be wholesale reopened.

Provisional WU skeleton — all NOT AUTHORIZED / NOT STARTED:
1. WU1 — Webcore Completeness Contract & Scope Reconciliation
2. WU2 — Core Media Admin Baseline
3. WU3 — Core Primary Navigation Admin Baseline
4. WU4 — Webcore Site Settings & Appearance Consolidation
5. WU5 — Zero-Optional Product Acceptance
6. WU6 — Stabilization & v0.14.0 Readiness Closure

Readiness boundary:
- intended post-workstream release-readiness boundary: v0.14.0;
- planning target only, not release authorization;
- release, tag, and publication remain separate explicit gates;
- after this boundary, major Webcore surgery should be exceptional rather
  than a normal consequence of Bundled Module refinement; bounded corrections
  and extension seams remain possible.

Human/product readiness gate:
- Site Settings human/product review occurs inside this Webcore workstream
  before relevant scope acceptance.

Planning action: KEEP / NEXT PREPARATION TARGET / PRE-CONTRACT NOT MATERIALIZED

### Production Webcore Reconciliation
Class: OPERATIONAL GATE
Status: NOT STARTED / SEPARATELY AUTHORIZED

Sources:
- `copot_package_lifecycle_migration_concept_260804_161738.md` — Existing Webcore Transition [SUPPORTING]

Authority:
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md` for technical/operational boundary

Planning action: KEEP / EXPLICIT AUTHORIZATION REQUIRED

### Server-Empty Bootstrap & Package Clean Install
Class: DEFERRED ITEM
Status: KEEP DEFERRED / UNSCHEDULED

Sources:
- `copot_package_lifecycle_migration_concept_260804_161738.md` — Webcore Clean Install Is Part of Webcore Lifecycle [PRIMARY]
- `copot_release_update_migration_concept_260803_171900.md` — compressed-package/fresh-install direction [HISTORICAL]

Relations:
- Deferred ID: `DI-PACKAGE-LIFECYCLE-WU7-01`.

Planning action: KEEP DEFERRED

### Module Permission Dependency & Base Access
Class: CONCEPT / FUTURE CAPABILITY
Status: FUTURE / PREPARATION CANDIDATE

Sources:
- `copot_module_permission_dependency_base_access_concept_260809_210842.md` [PRIMARY]

Relations:
- related to Users & Access;
- related to Roles Permission Accordion;
- System Health may consume findings but does not own this capability.

Planning action: KEEP / NO AUTOMATIC MR.2 ADOPTION

### Module Package Identity & Capability Provider
Class: CONCEPT / FUTURE ECOSYSTEM
Status: INDEXED / FUTURE

Sources:
- `concepts/copot_module_package_identity_and_capability_provider_concept.md` [PRIMARY GIT CONCEPT]
- `copot_module_package_identity_and_capability_provider_concept.md` [HISTORICAL/CORRECTION INPUT]
- `copot_module_package_identity_and_capability_provider_concept_260803_220452.md` [HISTORICAL]
- `copot_update_upgrade_migration_concept.md` [ANCESTOR]

Relations:
- candidate home: M6 — Distribution & Ecosystem;
- related to Package Lifecycle & Migration;
- preserves independent module repository/governance, stable package identity,
  publisher provenance, capability/provider and conflict direction;
- future direction includes consumer capability requirements, mandatory,
  optional, and fallback-capable semantics, provider discovery distinct from
  provider selection, and installed-provider state distinct from an active or
  enabled compatible provider;
- future runtime outcomes include FULL, DEGRADED, BLOCKED, provider
  unavailable, provider selection required, and migration available;
- one authoritative provider per consumer-capability is the default unless an
  explicit capability contract supports aggregation or multi-provider use;
- future provider transition and capability-state migration includes explicit
  preflight, state mapping/migration, verification, controlled cutover,
  rollback/fallback preservation, and no automatic takeover merely because a
  new provider appears;
- future developer-facing capability/provider declaration and lifecycle
  guidance remains planning-only.

Planning action: KEEP REGISTERED / FUTURE

### Future Widget Layout
Class: CONCEPT / FUTURE ARCHITECTURE
Status: FUTURE

Sources:
- `concept_future_widget_layout_260810_060139.md` [PRIMARY]
- `Future_Widget_Layout_Contract_260804_161738.md` [HISTORICAL]
- `Future_Widget_Layout_Contract_260716_111602.md` [HISTORICAL]

Relations:
- current accepted Dashboard baseline remains 4 columns;
- denser desktop logical-grid exploration remains candidate / not locked;
- Dashboard Composition and System Health Presentation are consumers/relations, not owners.

Authority:
- `docs/25_m3_9_internal_dashboard_contract.md` only for current delivered Dashboard baseline

Planning action: KEEP / FUTURE

### Widget Stage 1 — Widget Foundation + Auto Layout
Class: HISTORICAL / FUTURE WIDGET STAGE
Status: PARTLY DELIVERED / AUDIT AGAINST CURRENT DASHBOARD BASELINE

Sources:
- `concept_future_widget_layout_260810_060139.md` [PRIMARY]
- `Future_Widget_Layout_Contract_260804_161738.md` [HISTORICAL]

Authority:
- `docs/25_m3_9_internal_dashboard_contract.md` for delivered Dashboard baseline only

Planning action: KEEP REGISTERED / DO NOT REBUILD WITHOUT GAP AUDIT

### Widget Stage 2 — Layout Persistence + Management
Class: FUTURE WIDGET STAGE
Status: FUTURE / NOT PROMOTED

Sources:
- `concept_future_widget_layout_260810_060139.md` [PRIMARY]
- `Future_Widget_Layout_Contract_260804_161738.md` [HISTORICAL]

Planning action: KEEP REGISTERED / FUTURE

### Widget Stage 3 — Android-Style Interactive Grid
Class: FUTURE WIDGET STAGE
Status: FUTURE / NOT PROMOTED

Sources:
- `concept_future_widget_layout_260810_060139.md` [PRIMARY]
- `Future_Widget_Layout_Contract_260804_161738.md` [HISTORICAL]

Planning action: KEEP REGISTERED / FUTURE

### Core Modules & Dashboard Refinement
Class: CONCEPT / UMBRELLA / PROVENANCE
Status: DECOMPOSED / PROVENANCE

Sources:
- `core_modules_dashboard_refinement_concept_260804_161738.md` [PRIMARY]
- `copot_refinement_milestone_governance_concept_260803_220452.md` [SUPPORTING]
- `concept_mrx_session_refinement_backlog_260809_201113.md` [SUPPORTING]
- `copot_consolidated_refinement_concepts_260810_020950.md` [SUPPORTING]
- `copot_core_module_refinement_concept_260810_184600.md` [SUPPORTING]

Relations:
- shared Admin presentation lineage → current MR.2;
- per-Bundled-Module refinement → dedicated future workstreams;
- Dashboard/shared-shell refinement → Admin Shell / Dashboard Refinement;
- widget architecture remains separate.

Planning action: KEEP REGISTERED FOR LINEAGE / DO NOT PROMOTE AS ONE EXECUTION WORKSTREAM

### Refinement Milestone Governance
Class: GOVERNANCE CONCEPT / PROVENANCE
Status: INCORPORATED / RETAINED

Sources:
- `copot_refinement_milestone_governance_concept_260803_220452.md` [PRIMARY]

Planning action: KEEP REGISTERED

### MR.x Session-Level Refinement Backlog
Class: BACKLOG CONCEPT / PROVENANCE
Status: DECOMPOSED / PARTLY INCORPORATED

Sources:
- `concept_mrx_session_refinement_backlog_260809_201113.md` [PRIMARY]
- `concept_mrx_session_refinement_backlog_260809_093844.md` [HISTORICAL]

Planning action: KEEP REGISTERED FOR LINEAGE

### Admin UX Refinement
Class: HISTORICAL REFINEMENT CONCEPT
Status: DECOMPOSED / PROVENANCE

Sources:
- `copot_consolidated_refinement_concepts_260810_020950.md` — Admin UX Refinement [PRIMARY]
- `copot_refinement_milestone_governance_concept_260803_220452.md` [SUPPORTING]

Relations:
- shared presentation concerns feed current MR.2;
- Dashboard/shared-shell concerns remain separate;
- domain-specific refinement follows current owner workstreams.

Planning action: KEEP REGISTERED / DECOMPOSED PROVENANCE

### Dashboard Composition / Admin Shell & Dashboard Refinement
Class: FUTURE / SEPARATE WORKSTREAM
Status: KEEP / NOT CURRENT EXECUTION TARGET

Sources:
- `copot_consolidated_refinement_concepts_260810_020950.md` — Dashboard Composition and Admin UX Refinement [PRIMARY]
- `core_modules_dashboard_refinement_concept_260804_161738.md` [SUPPORTING]
- `concept_future_widget_layout_260810_060139.md` [RELATED ARCHITECTURE]

Relations:
- separate from MR.2;
- may consume shared Admin primitives after MR.2;
- Dashboard composition, widget layout, sidebar/topbar redesign, and Dashboard-specific behavior remain outside MR.2 unless separately reclassified.

Planning action: KEEP / FUTURE

### System Health Presentation / Dashboard Integration
Class: FUTURE REFINEMENT / HISTORICAL DERIVED IDENTITY
Status: FUNCTIONAL BASELINE DELIVERED / PRESENTATION REFINEMENT REMAINS

Sources:
- `copot_consolidated_refinement_concepts_260810_020950.md` — System Health Presentation [PRIMARY]
- `copot_system_health_status_concept_260809_210842.md` [SUPPORTING]
- `core_modules_dashboard_refinement_concept_260804_161738.md` [SUPPORTING]
- `concept_future_widget_layout_260810_060139.md` [SUPPORTING]

Authority:
- `docs/35_system_health_status_contract.md` for functional boundary

Planning action: KEEP PRESENTATION IDENTITY / DO NOT REOPEN FUNCTIONAL FOUNDATION

### Roles Permission Accordion
Class: EMBEDDED REFINEMENT CONCEPT
Status: FUTURE / RE-HOMED / NOT ADOPTED

Sources:
- `copot_refinement_milestone_governance_concept_260803_220452.md` — Roles Permission Refinement Candidate [PRIMARY]
- `copot_concept_disposition_index_260803_220452.md` — Roles Permission Accordion [HISTORICAL INDEX]

Relations:
- related to Module Permission Dependency & Base Access;
- future ownership must be reconciled against current Users & Access architecture.

Planning action: KEEP REGISTERED / REVALIDATE BEFORE ADOPTION

### M6 — Distribution & Ecosystem
Class: FUTURE ROADMAP CONCEPT
Status: LATER / FUTURE

Sources:
- `copot_module_package_identity_and_capability_provider_concept.md` [PRIMARY ECOSYSTEM INPUT]
- `copot_module_package_identity_and_capability_provider_concept_260803_220452.md` [HISTORICAL]
- `copot_release_update_migration_concept_260803_171900.md` — Official Update Service / M6 direction [SUPPORTING]
- `copot_package_lifecycle_migration_concept_260803_220452.md` — future package-source direction [SUPPORTING]
- `copot_concept_disposition_index_260803_220452.md` [HISTORICAL DISPOSITION]

Relations:
- includes broader distribution/ecosystem direction behind remote update, package identity/provider, publisher provenance, signing/trust, channels, and external/private sources.

Planning action: KEEP REGISTERED / NO AUTOMATIC SEQUENCING

### Official Remote Update / Distribution Ecosystem
Class: FUTURE ECOSYSTEM CONCEPT
Status: DEFERRED / NOT ADOPTED

Sources:
- `copot_release_update_migration_concept_260803_171900.md` [PRIMARY HISTORICAL]
- `copot_package_lifecycle_migration_concept_260803_220452.md` [SUPPORTING]
- `copot_concept_disposition_index_260803_220452.md` [HISTORICAL DISPOSITION]

Planning action: KEEP / NO AUTOMATIC REMOTE-UPDATE IMPLEMENTATION

### External Simulation Gate Preparation / External Simulation #1 — Custom Theme
Class: FUTURE VALIDATION / ECOSYSTEM LINEAGE
Status: KEEP REGISTERED / UNSCHEDULED

Sources:
- `copot_release_update_migration_concept_260803_171900.md` — Production-Readiness Gate for First External Simulation [PRIMARY]

Planning action: KEEP / NO AUTOMATIC SEQUENCING

### Database Historical Release-Support Window
Class: FUTURE POLICY QUESTION
Status: OPEN

Sources:
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260816_194431.md` — Compatibility Reconciliation [PRIMARY]

Planning action: KEEP SEPARATE UNTIL CONCRETE PRODUCT/RELEASE POLICY REQUIRES IT

### Installation & Runtime Identity Exposure
Class: PRODUCT / PRESENTATION CONCERN
Status: UNRESOLVED / SEPARATE

Sources:
- `multi_installation_isolation_foundation_concept_260804_092611.md` [PRIMARY ARCHITECTURE LINEAGE]

Relations:
- possible About/System exposure;
- historical proposed permission `system.runtime.manage` requires fresh security/product preparation.

Planning action: KEEP / NO AUTOMATIC PRODUCT DECISION

### Cross-Fileset Upgrade Ownership Proof Gap
Class: ARCHITECTURE / PRODUCT INVESTIGATION
Status: UNRESOLVED / SEPARATE / NON-BLOCKING BY DEFAULT

Sources:
- `copot_package_lifecycle_migration_concept_260804_161738.md` [PRIMARY LIFECYCLE LINEAGE]

Planning action: KEEP SEPARATE / DO NOT AUTO-ADOPT INTO MR.2

### Stale Package-Owned File Reconciliation
Class: LIFECYCLE REFINEMENT / AUDIT CANDIDATE
Status: UNRESOLVED / SEPARATE

Sources:
- `copot_package_lifecycle_migration_concept_260804_161738.md` [PRIMARY LIFECYCLE LINEAGE]

Relations:
- future audit must prove ownership, safe deletion, Repair/Recovery interaction, integrity verification, and rollback/restore expectations.

Planning action: KEEP / NO DESTRUCTIVE CLEANUP WITHOUT INDEPENDENT SCOPE

### Taxonomy Manager
Class: ARCHITECTURE / PRODUCT DISPOSITION
Status: HIGH-CONFIDENCE RETIREMENT CANDIDATE

Sources:
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [PRIMARY CURRENT ARCHITECTURE INPUT]
- `copot_core_module_refinement_concept_260810_184600.md` [HISTORICAL REFINEMENT INPUT]

Planning action: NO DEDICATED REFINEMENT WORKSTREAM UNLESS LATER EVIDENCE CHANGES DISPOSITION

### Content Manager Refinement
Class: FUTURE DEDICATED BUNDLED MODULE REFINEMENT
Status: FUTURE / PREPARATION REQUIRED

Sources:
- `concepts/copot_content_manager_refinement_concept.md` [PRIMARY GIT CONCEPT]
- `copot_core_module_refinement_concept_260810_184600.md` [HISTORICAL REFINEMENT INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]
- `docs/42_mr_2_wu4_content_manager_refinement_contract.md` [CLOSED HISTORICAL
  PROVENANCE]

Relations:
- Content Manager consumes capability contracts rather than concrete provider
  package identities;
- Content human/product review is required before future refinement scope lock;
- taxonomy capability may be satisfied by self/fallback or a compatible
  provider, while Taxonomy Manager retirement disposition is not reversed;
- a provider's appearance does not automatically take over existing state;
  provider transition/state migration is required when state ownership changes;
- media capability consumption may use the Webcore Media baseline or richer
  compatible providers;
- future editorial/rich-formatting capability may include bold, italic,
  headings, links, lists, and inline media;
- exact editor, storage, rendering, and sanitization contract remains
  unresolved and is not implementation-authorized.

Planning action: KEEP FUTURE / PREPARATION REQUIRED / DO NOT REOPEN MR.2 WU4

### Site Color Scheme
Class: FUTURE EXTENSION / APPEARANCE RECONCILIATION
Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_site_color_scheme_concept.md` [PRIMARY GIT CONCEPT]

Relations:
- extension/reconciliation of existing Site Identity and appearance lineage;
- Webcore Branding data is the durable upstream appearance lineage;
- the intended relationship is Webcore Branding data → bounded resolved Site
  Color Scheme → Built-in Public View / Theme / permitted bounded Admin
  consumers;
- Site Color Scheme is not a second storage or ownership authority and does
  not destructively replace the existing `branding.*` values;
- Theme-scoped appearance may override within Theme scope but must not write
  back to or take ownership of Webcore site-level Branding data;
- site-level appearance authority is consumed by the public/Built-in View;
- Admin may inherit bounded brand, navigation, or accent appearance;
- semantic operational colors remain independent, including warning, danger,
  success, information, validation, and destructive states;
- no second global Admin color authority is created.

The canonical Git Concept is materialized at
`concepts/copot_site_color_scheme_concept.md`.

Planning action: KEEP FUTURE / PLANNING ONLY

### Per-user Admin Appearance
Class: CONCEPT / FUTURE ADMIN PERSONALIZATION
Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_per_user_admin_appearance_concept.md` [PRIMARY GIT CONCEPT]

Relations:
- user-specific Admin presentation preference with default inheritance from
  Site Color Scheme;
- potential future override family includes color scheme, light/dark
  appearance, density, Dashboard layout/widget placement, and related Admin
  personalization;
- related to Future Widget Layout / Dashboard personalization;
- distinct ownership from site-level Site Color Scheme.

The canonical Git Concept is materialized at
`concepts/copot_per_user_admin_appearance_concept.md`.

Planning action: KEEP FUTURE / SEPARATE FROM FUTURE WIDGET LAYOUT

### Shared File Intake Interaction Pattern
Class: CONCEPT / FUTURE CROSS-SURFACE
Status: ADOPTED / FUTURE / OUTSIDE MR.2 / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_shared_file_intake_interaction_pattern_concept.md` [PRIMARY GIT CONCEPT]

Relations:
- ADOPT / FUTURE CROSS-SURFACE CONCEPT / OUTSIDE MR.2 /
  NOT IMPLEMENTATION-AUTHORIZED;
- candidate consumers include Media, Content Featured Media, Module/package
  ZIP intake, and future Admin upload surfaces;
- likely pattern may include native picker, immediate intake/upload, validation,
  preview or preparation, crop where relevant, attach, and confirmation;
- exact semantics remain bounded by the current Concept and require a future
  authoritative contract before implementation.

Planning action: KEEP FUTURE / NO IMPLEMENTATION

### Copot Update, Upgrade, and Migration
Class: HISTORICAL CONCEPT
Status: INCORPORATED / PROVENANCE

Sources:
- `copot_update_upgrade_migration_concept.md` [PRIMARY HISTORICAL]
- `copot_release_update_migration_concept_260803_171900.md` [SUPPORTING HISTORICAL EVOLUTION]

Relations:
- incorporated into Package Lifecycle & Migration Foundation.

Authority:
- `docs/28_package_lifecycle_migration_foundation_contract.md` for promoted lifecycle scope

Planning action: NONE / PROVENANCE

### What's New Admin Surfaces
Class: HISTORICAL RELEASE-PRESENTATION CONCEPT
Status: INCORPORATED / FUTURE CONSUMER RE-AUDIT REQUIRED

Sources:
- `copot_consolidated_refinement_concepts_260816_194432.md` [PRIMARY REFINEMENT LINEAGE]
- `copot_consolidated_refinement_concepts_260814_151800.md` [HISTORICAL]
- `copot_consolidated_refinement_concepts_260810_020950.md` [HISTORICAL]
- `copot_release_update_migration_concept_260803_171900.md` [SUPPORTING RELEASE-DATA LINEAGE]

Relations:
- release/package metadata remains source authority;
- historical Webcore consumer = System Manager;
- historical Module consumer must be re-audited against current lifecycle architecture;
- presentation must not invent or duplicate release history.

Planning action: KEEP REGISTERED / PROVENANCE

## 6. MR.2 Current Registry

### MR.2 — Shared Admin Refinement Continuation
Class: ACTIVE REFINEMENT MILESTONE
Status: COMPLETE / CLOSED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DETAILED INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [PRIMARY RECONCILED REFINEMENT INPUT]
- `copot_consolidated_refinement_concepts_260814_151800.md` [HISTORICAL]
- `core_modules_dashboard_refinement_concept_260804_161738.md` [SUPPORTING]
- `copot_refinement_milestone_governance_concept_260803_220452.md` [SUPPORTING]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [SUPPORTING CURRENT ARCHITECTURE]

Relations:
- historical WU1–WU4 remain closed provenance;
- forward scope = shared Admin artifacts / visual primitives / cross-surface presentation consistency;
- per-Bundled-Module refinement is redistributed to dedicated future workstreams;
- Dashboard remains separate.

Planning action: NONE / PROVENANCE / NO IMMEDIATE MR.2 IMPLEMENTATION TARGET

### Rejected Historical Media WU5 Attempt
Class: HISTORICAL MR.2 IMPLEMENTATION ATTEMPT
Status: IMPLEMENTATION EXISTS / NOT ACCEPTED / FORWARD SCOPE SUPERSEDED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` — Media refinement section [PRIMARY HISTORICAL INTENT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]

Pre-contract:
- `precontracts/43_mr_2_wu5_media_manager_refinement_precontract.md`

Pre-contract status: MATERIALIZED HISTORICAL SNAPSHOT / SUPERSEDED FORWARD SCOPE / TRIAL PROVENANCE

Authority / repository evidence:
- `docs/43_mr_2_wu5_media_manager_refinement_contract.md`

Relations:
- objective/correctness evidence may be reused where valid;
- implementation must be classified into correctness fixes, shared-artifact candidates, future Media-specific refinement, and local redesign that should not survive;
- commit/contract existence does not establish accepted WU5 closure;
- this historical WU5 identity is distinct from current forward WU5 Shared Admin Primitive Audit & Contract.

Planning action: HOLD MEDIA-SPECIFIC CONTINUATION / AUDIT AGAINST SHARED BASELINE

### WU5 — Shared Admin Primitive Audit & Contract
Class: MR.2 WORK UNIT
Status: COMPLETE / CONTRACT LOCKED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [PRIMARY SHARED REFINEMENT INPUT]
- `copot_consolidated_refinement_concepts_260814_151800.md` [HISTORICAL]
- `core_modules_dashboard_refinement_concept_260804_161738.md` [SUPPORTING]

Pre-contract:
- `precontracts/mr_2_wu5_shared_admin_primitive_audit_precontract.md`

Pre-contract status: PROMOTED / HISTORICAL PROVENANCE

Authority:
- `docs/44_mr_2_wu5_shared_admin_primitive_audit_contract.md`

Objective:
Audit current Admin presentation implementation and accepted refinement lineage, identify canonical shared primitives versus duplicated/local variants, and define a bounded forward shared-primitive contract only through explicit authorization.

Minimum audit surface:
- title / eyebrow / page header;
- section/card/surface treatment;
- toolbar/search/filter and search-to-content spacing;
- form field/helper patterns;
- button sizing and action placement;
- modal/dialog action rhythm;
- typography scale;
- spacing/grid;
- radius/flat-surface policy;
- responsive/accessibility behavior;
- shared CSS/token/artifact ownership.

Planning action: NONE / PROVENANCE

### WU6 — Shared Artifact Consolidation & Implementation
Class: MR.2 WORK UNIT
Status: COMPLETE / IMPLEMENTED AND ACCEPTED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]

Pre-contract:
- `precontracts/mr_2_wu6_shared_artifact_consolidation_implementation_precontract.md`

Pre-contract status: PROMOTED / HISTORICAL PROVENANCE

Authority:
- `docs/45_mr_2_wu6_shared_artifact_consolidation_implementation_contract.md`

Relations:
- accepted implementation of the WU5 shared-artifact boundary;
- representative adoption and propagation proof continued in WU7.

Objective:
Implement bounded canonical shared primitives using single-source Admin artifacts/tokens/classes while preserving domain behavior.

Planning action: NONE / PROVENANCE

### WU7 — Representative Adoption & Propagation Proof
Class: MR.2 WORK UNIT
Status: COMPLETE / CLOSED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]

Pre-contract:
- `precontracts/mr_2_wu7_representative_adoption_propagation_proof_precontract.md`

Pre-contract status: PROMOTED / HISTORICAL PROVENANCE

Authority:
- `docs/47_mr_2_wu7_representative_adoption_propagation_proof_contract.md`

Relations:
- accepted WU6 shared-artifact implementation baseline;
- WU8 cross-surface verification and MR.2 closure followed.

Objective:
Normalize a small representative set of accepted Admin consumers sufficient to prove shared primitive propagation, responsive/accessibility compatibility, and explicit exception handling.

Planning action: NONE / PROVENANCE / REPRESENTATIVE PROOF ONLY, NOT PER-MODULE REFINEMENT PASS

### WU8 — Cross-Surface Verification & MR.2 Closure
Class: MR.2 WORK UNIT / CLOSURE GATE
Status: COMPLETE / CLOSED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [SUPPORTING CURRENT ARCHITECTURE]

Pre-contract:
- `precontracts/mr_2_wu8_cross_surface_verification_closure_precontract.md`

Pre-contract status: PROMOTED / HISTORICAL PROVENANCE

Authority:
- `docs/48_mr_2_wu8_cross_surface_verification_closure_contract.md`

Relations:
- accepted WU7 propagation proof;
- repository-side verification and closure complete;
- closure reconciled current authoritative documentation without rewriting valid
  historical records;
- Shared File Intake planning disposition is resolved at the GPT/user layer as
  a future cross-surface concept outside MR.2 and not implementation-authorized.

Must verify shared primitive single-source behavior, consistency, explicit exceptions, System Manager shared presentation without domain takeover, no retired-manager restoration, truthful unsupported states, no domain capability expansion, no Dashboard redesign, documentation consistency, and truthful preservation of MR.2 thread-level planning payload.

Planning action: NONE / PROVENANCE / NO NEW IMPLEMENTATION TARGET

## 7. Dedicated Future Bundled Module Refinement Workstreams

Historical per-module refinement intent survives as separate future workstream identities rather than MR.2 WUs.

### Bundled Module Refinement Preparation & Reconciliation
Class: FUTURE PREPARATION WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [CURRENT ARCHITECTURE INPUT]
- `concepts/copot_content_manager_refinement_concept.md` [BUNDLED MODULE REFINEMENT INPUT]

Relations:
- hard predecessor to dedicated Bundled Module refinement scope lock;
- depends on completion of the Webcore Product Completeness & Stabilization
  predecessor;
- Content, Media, Navigation, Theme, Users & Access, and Form Manager are
  the six dedicated refinement identities after preparation;
- Taxonomy remains a retirement/reconciliation candidate;
- Module Manager is re-homed to System Manager/Webcore lifecycle;
- Settings Manager is re-homed into Webcore/System/Settings Platform concerns;
- Redirect Manager is re-homed to Webcore Redirect behavior;
- Dashboard remains separate unless later explicitly reconciled.

Human/product readiness gates before relevant scope lock:
- Content human/product review;
- Media human/product review;
- Navigation human/product review.

These are acceptance/readiness gates, not semantic capability Concepts.

Planning action: KEEP FUTURE / PREPARATION ONLY / NOT IMPLEMENTATION-AUTHORIZED

The full visible baseline Bundled Module topology is eight workstreams total:
one Webcore predecessor, one Bundled Module preparation workstream, and six
dedicated refinement workstreams. The existing Content Manager Refinement
entry below is the canonical Content identity in this topology.

Shared sources for this family:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY DETAILED HISTORICAL INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [CURRENT ARCHITECTURE INPUT]

### Media Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED
Planning action: audit old rejected Media delta plus current source before scope lock.

Relations:
- Media human/product review is required before future refinement scope lock.

### Navigation Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED
Planning action: KEEP.

Relations:
- Navigation human/product review is required before future refinement scope lock.

### Theme Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED
Planning action: KEEP.

### Users & Access Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED
Planning action: KEEP; Module Permission Dependency & Base Access remains separate until adopted.

### Form Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED / CORRECTNESS AUDIT BEFORE SCOPE LOCK
Planning action: KEEP; functional findings must not be disguised as styling.

## 8. Thread-Level Continuity Reconciliation

Durably reconciled into MR.2 planning:
- Shared Admin Visual Primitive Single-Source Rule;
- Shared Admin Action Placement & Button Sizing Pattern;
- Workplan Set trial model: Workplan indexes Concept sources and target-specific Pre-contracts; Pre-contract remains non-authoritative until explicit promotion.

Resolved at GPT/user planning layer:
- Shared File Intake Interaction Pattern: ADOPT / FUTURE CROSS-SURFACE CONCEPT /
  OUTSIDE MR.2 / NOT IMPLEMENTATION-AUTHORIZED;
- is now materialized as a Git Concept while the GPT/thread-level planning
  lineage remains provenance;
- candidate consumers include Media, Content Featured Media, Module/package ZIP
  intake, and future Admin upload surfaces;
- not adopted into current MR.2 implementation.

The Git Concept records the current semantic source; no GPT/File Library
Concept file was modified.

## 9. Immediate Next Planning Target

MR.2 is complete and closed. No new MR.2 implementation target is exposed by
this closure. Shared File Intake remains a future cross-surface planning
concept outside MR.2 and is not implementation-authorized; no next
implementation workstream is selected here.

## 10. Retention and Planning Freshness

Retention rule:
- once a planning identity is validly registered, completion, promotion, incorporation, decomposition, supersession, retirement, rejection, or architecture re-homing changes its disposition but does not erase the identity;
- closed execution detail may be shortened, but identity, latest disposition, and enough source/authority linkage to reconstruct why it existed must remain;
- reviewed/promoted Pre-contract snapshots remain provenance and must not be retroactively rewritten to impersonate later authoritative contract revisions;
- removal is allowed only when evidence proves duplicate identity, erroneous/non-valid registration, or explicit invalidation with no independent historical planning value.

Planning freshness:
- repository truth remains authoritative for delivered/current implementation state;
- Workplan does not require continuous synchronization after every repository commit;
- Workplan must be reconciled when material planning, sequencing, lifecycle disposition, Concept provenance, Pre-contract disposition, or next-target selection changes;
- detailed semantics are read from the individual Concept files and target-specific Pre-contracts named under each entry according to their artifact roles.

## 11. Authorization Boundary

This Workplan and every indexed Pre-contract authorize no technical implementation by themselves.

Not authorized by this Workplan or indexed Pre-contracts alone:
- source/runtime/test/schema/config mutation;
- contract promotion/amendment;
- treating a Pre-contract as authoritative repository contract;
- auto-promoting a Pre-contract because review completed;
- old Media WU5 continuation;
- broad CSS migration;
- per-Bundled-Module implementation;
- Dashboard/Admin Shell redesign;
- Deferred adoption;
- destructive rollback/revert/reset;
- production reconciliation;
- branch deletion;
- release/tag/publication/external distribution.
