# COPOT — Non-Linear Workplan
Date version: 2026-08-27 05:56:56 WIB
Workplan lifecycle: CURRENT
Project: COPOT

## 1. Purpose and Authority

This Workplan is the living COPOT planning, sequencing, lifecycle-index, and provenance registry.

It does not:
- override committed repository truth;
- authorize implementation;
- auto-adopt Deferred Items;
- create repository lifecycle state;
- automatically promote Concept scope;
- authorize production reconciliation;
- authorize release/tag/publication/external distribution.

Repository contracts, committed source/tests, and independently verified remote Git state remain authoritative for delivered/current lifecycle truth.

Registry rules:
- Workplan stores logical Concept/work-item registration and minimal lifecycle/provenance metadata;
- detailed semantic content stays in individual Concept source files or authoritative repository contracts;
- `Sources` refers directly to individual Concept files, never to a consolidated Concept registry intermediary;
- complete/closed items remain registered as provenance;
- a valid planning identity is not erased merely because it is completed, promoted, incorporated, decomposed, superseded, retired, rejected, or re-homed;
- future, deferred, excluded, unresolved, and operational-gate states remain distinct;
- Workplan planning state is not repository implementation authority;
- promotion requires explicit decision plus durable repository authority.

## 2. Current Authoritative State Anchor

Authoritative repository: `https://github.com/blackdjurix/copot.git`
Authoritative branch: `main`

Current durable planning state:
- Post-M3 — Webcore & Extension Architecture Reconciliation: COMPLETE / CLOSED;
- MR.2: OPEN;
- MR.2 WU1–WU4: COMPLETE AND CLOSED;
- old MR.2 Media WU5 implementation: EXISTS / NOT ACCEPTED;
- current forward target: MR.2 WU5 — Shared Admin Primitive Audit & Contract;
- per-Bundled-Module refinement: redistributed to dedicated future workstreams;
- Dashboard: separate from MR.2;
- `DI-PACKAGE-LIFECYCLE-WU7-01`: KEEP DEFERRED / UNSCHEDULED;
- Shared File Intake Interaction Pattern: unresolved thread-level planning payload / not adopted.

Current architecture vocabulary is `Module` / `Bundled Module`; historical `Core Module` wording remains provenance only where it appears in historical Concept sources.

## 3. Registry Entry Format

Each logical entry uses only relevant fields:
- `Class`
- `Status`
- `Sources`
- `Relations`
- `Authority`
- `Planning action`

Source tags:
- `[PRIMARY]`
- `[SUPPORTING]`
- `[HISTORICAL]`
- `[ANCESTOR]`

`Sources` must name individual Concept files. Repository contracts and other delivered truth belong under `Authority`, not `Sources`.

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
- `copot_system_health_status_concept_260809_210842.md` [PRIMARY]
- `copot_system_health_status_concept_260805_083932.md` [HISTORICAL]
- `concept_future_widget_layout_260810_060139.md` [SUPPORTING]
- `Future_Widget_Layout_Contract_260804_161738.md` [HISTORICAL / SUPPORTING]

Authority:
- `docs/35_system_health_status_contract.md`

Planning action: NONE / PROVENANCE

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

Authority:
- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`

Planning action: NONE / PROVENANCE

### MR.2 WU2 — Webcore System Manager Baseline
Class: PROMOTED / MR.2 WORK UNIT
Status: COMPLETE AND CLOSED

Sources:
- `copot_consolidated_refinement_concepts_260816_194432.md` [PRIMARY PLANNING LINEAGE]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [SUPPORTING ARCHITECTURE]

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

Authority:
- `docs/42_mr_2_wu4_content_manager_refinement_contract.md`

Final accepted anchor: `086f53452ff029933d0dff08cfee75ee98407230`
Planning action: NONE / PROVENANCE

## 5. Active / Future / Deferred / Operational Registry

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
- `copot_module_package_identity_and_capability_provider_concept.md` [PRIMARY]
- `copot_module_package_identity_and_capability_provider_concept_260803_220452.md` [HISTORICAL]
- `copot_update_upgrade_migration_concept.md` [ANCESTOR]

Relations:
- candidate home: M6 — Distribution & Ecosystem;
- related to Package Lifecycle & Migration;
- preserves independent module repository/governance, stable package identity, publisher provenance, capability/provider and conflict direction.

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
Status: OPEN

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

Planning action: CONTINUE THROUGH CURRENT WU5–WU8 TOPOLOGY

### Rejected Historical Media WU5 Attempt
Class: HISTORICAL MR.2 IMPLEMENTATION ATTEMPT
Status: IMPLEMENTATION EXISTS / NOT ACCEPTED / FORWARD SCOPE SUPERSEDED

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` — Media refinement section [PRIMARY HISTORICAL INTENT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]

Authority / repository evidence:
- `docs/43_mr_2_wu5_media_manager_refinement_contract.md`

Relations:
- objective/correctness evidence may be reused where valid;
- implementation must be classified into correctness fixes, shared-artifact candidates, future Media-specific refinement, and local redesign that should not survive;
- commit/contract existence does not establish accepted WU5 closure.

Planning action: HOLD MEDIA-SPECIFIC CONTINUATION / AUDIT AGAINST SHARED BASELINE

### WU5 — Shared Admin Primitive Audit & Contract
Class: MR.2 WORK UNIT / PREPARATION TARGET
Status: NEXT

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [PRIMARY SHARED REFINEMENT INPUT]
- `copot_consolidated_refinement_concepts_260814_151800.md` [HISTORICAL]
- `core_modules_dashboard_refinement_concept_260804_161738.md` [SUPPORTING]

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

Planning action: PREPARATION NEXT / NO IMPLEMENTATION AUTHORIZED BY WORKPLAN

### WU6 — Shared Artifact Consolidation & Implementation
Class: MR.2 WORK UNIT
Status: PLANNED / FUTURE

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]

Objective:
Implement bounded canonical shared primitives using single-source Admin artifacts/tokens/classes while preserving domain behavior.

Planning action: FUTURE / REQUIRES SEPARATE AUTHORIZATION

### WU7 — Representative Adoption & Propagation Proof
Class: MR.2 WORK UNIT
Status: PLANNED / FUTURE

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]

Objective:
Normalize a small representative set of accepted Admin consumers sufficient to prove shared primitive propagation, responsive/accessibility compatibility, and explicit exception handling.

Planning action: FUTURE / REPRESENTATIVE PROOF ONLY, NOT PER-MODULE REFINEMENT PASS

### WU8 — Cross-Surface Verification & MR.2 Closure
Class: MR.2 WORK UNIT / CLOSURE GATE
Status: PLANNED / FUTURE

Sources:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY HISTORICAL DESIGN INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [SUPPORTING CURRENT ARCHITECTURE]

Must verify shared primitive single-source behavior, consistency, explicit exceptions, System Manager shared presentation without domain takeover, no retired-manager restoration, truthful unsupported states, no domain capability expansion, no Dashboard redesign, documentation consistency, and disposition of unresolved MR.2 thread-level planning payload.

Planning action: FUTURE / MR.2 CLOSURE GATE

## 7. Dedicated Future Bundled Module Refinement Workstreams

Historical per-module refinement intent survives as separate future workstream identities rather than MR.2 WUs.

Shared sources for this family:
- `copot_core_module_refinement_concept_260810_184600.md` [PRIMARY DETAILED HISTORICAL INPUT]
- `copot_consolidated_refinement_concepts_260816_194432.md` [SUPPORTING]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [CURRENT ARCHITECTURE INPUT]

### Media Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED
Planning action: audit old rejected Media delta plus current source before scope lock.

### Navigation Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED
Planning action: KEEP.

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
- Shared Admin Action Placement & Button Sizing Pattern.

Still unresolved:
- Shared File Intake Interaction Pattern: native picker selection may eventually trigger upload/intake immediately and continue to the next meaningful validation/preview/crop/attach/confirm state;
- candidate consumers include Media, Content Featured Media, Module/package ZIP intake, and future Admin upload surfaces;
- not adopted into current MR.2 implementation;
- requires durable Concept disposition before MR.2 closure if it remains material.

No individual Concept file currently owns this thread-level payload, so this section records continuity without fabricating a `Sources` reference.

## 9. Immediate Next Planning Target

### MR.2 WU5 — Shared Admin Primitive Audit & Contract

Classification:
- MR.2 CONTINUATION;
- PREPARATION NEXT;
- NO TECHNICAL IMPLEMENTATION AUTHORIZED BY THIS WORKPLAN;
- old rejected Media WU5 is evidence only;
- no Deferred adoption;
- repository-side contract promotion/amendment remains separately authorized.

Preparation questions:
1. Which shared Admin visual primitives already have a real single-source implementation?
2. Which consumers locally recreate equivalent primitives?
3. Which accepted surfaces are valid reference evidence for each primitive?
4. Which differences are semantic exceptions versus accidental inconsistency?
5. Which primitive changes can be introduced without domain redesign?
6. How should the old Media WU5 delta be classified against the shared baseline?
7. Which contracts/docs require forward-authoritative amendment without rewriting accepted historical records?

## 10. Retention and Planning Freshness

Retention rule:
- once a planning identity is validly registered, completion, promotion, incorporation, decomposition, supersession, retirement, rejection, or architecture re-homing changes its disposition but does not erase the identity;
- closed execution detail may be shortened, but identity, latest disposition, and enough source/authority linkage to reconstruct why it existed must remain;
- removal is allowed only when evidence proves duplicate identity, erroneous/non-valid registration, or explicit invalidation with no independent historical planning value.

Planning freshness:
- repository truth remains authoritative for delivered/current implementation state;
- Workplan does not require continuous synchronization after every repository commit;
- Workplan must be reconciled when material planning, sequencing, lifecycle disposition, Concept provenance, or next-target selection changes;
- detailed semantics are read from the individual Concept files named under each entry.

## 11. Authorization Boundary

This Workplan authorizes no technical implementation by itself.

Not authorized by this Workplan alone:
- source/runtime/test/schema/config mutation;
- contract promotion/amendment;
- old Media WU5 continuation;
- broad CSS migration;
- per-Bundled-Module implementation;
- Dashboard/Admin Shell redesign;
- Deferred adoption;
- destructive rollback/revert/reset;
- production reconciliation;
- branch deletion;
- release/tag/publication/external distribution.
