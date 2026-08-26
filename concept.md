# COPOT Concept Registry — Date version: 2026-08-26 22:04:00 WIB

Status: CURRENT / CONSOLIDATED CONCEPT REGISTRY
Project: COPOT
Canonical durable Concept file: `concept.md`

## 1. Purpose, Identity, and Maintenance

This file is the canonical durable registry for COPOT Concept identities and their current dispositions.

A Concept is identified by its stable semantic subject, not by physical filename, timestamp, alternative wording, originating workstream, or old vocabulary. Multiple historical files may belong to one logical Concept identity. One historical consolidated file may contain several logical Concept identities.

This file does not:
- authorize implementation;
- promote a Concept into repository capability by itself;
- adopt Deferred Items;
- reopen accepted closures;
- override repository contracts/source/tests;
- authorize production reconciliation;
- authorize release/tag/publication/external distribution.

Maintenance rule from this revision forward:
- canonical filename remains exactly `concept.md`;
- the date version is embedded in the first line and changes on each material revision;
- do not create new timestamped Concept files merely for routine registry maintenance;
- historical timestamped Concept files remain provenance and must not be deleted or rewritten merely to normalize naming;
- future routine maintenance should be performed by Codex against the authoritative repository after comparing current registry, relevant historical Concept sources, current `workplan.md`, and repository authority;
- when a logical Concept changes materially, update its canonical entry here and preserve source lineage instead of silently dropping or replacing the old identity.

## 2. Lineage Audit Basis

This registry was reconstructed from historical Concept files, consolidated Concept revisions, old Concept disposition/index files, and Workplan Concept registries.

Exact distinct source inventory recovered: **29 filenames**, after duplicate uploads with the same filename/content are deduplicated as one historical source artifact.

### Package / Lifecycle lineage — 7
- `copot_update_upgrade_migration_concept.md`
- `copot_release_update_migration_concept_260803_171900.md`
- `copot_package_lifecycle_migration_concept_260803_220452.md`
- `copot_package_lifecycle_migration_concept_260804_161738.md`
- `copot_backup_and_recovery_concept_260803_220452.md`
- `copot_module_package_identity_and_capability_provider_concept_260803_220452.md`
- `copot_module_package_identity_and_capability_provider_concept.md`

### Multi-installation / database ownership lineage — 5
- `multi_installation_isolation_foundation_concept_260804_092611.md`
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260813_131300.md`
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260814_151800.md`
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260814_172800.md`
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260816_194431.md`

### System / authorization / widget lineage — 6
- `copot_system_health_status_concept_260805_083932.md`
- `copot_system_health_status_concept_260809_210842.md`
- `copot_module_permission_dependency_base_access_concept_260809_210842.md`
- `concept_future_widget_layout_260810_060139.md`
- `Future_Widget_Layout_Contract_260804_161738.md`
- `Future_Widget_Layout_Contract_260716_111602.md`

### Refinement lineage — 8
- `copot_refinement_milestone_governance_concept_260803_220452.md`
- `core_modules_dashboard_refinement_concept_260804_161738.md`
- `concept_mrx_session_refinement_backlog_260809_093844.md`
- `concept_mrx_session_refinement_backlog_260809_201113.md`
- `copot_consolidated_refinement_concepts_260810_020950.md`
- `copot_core_module_refinement_concept_260810_184600.md`
- `copot_consolidated_refinement_concepts_260814_151800.md`
- `copot_consolidated_refinement_concepts_260816_194432.md`

### Architecture reconciliation lineage — 2
- `concept_webcore_extension_architecture_reconciliation_260820_120500.md`
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md`

### Historical index lineage — 1
- `copot_concept_disposition_index_260803_220452.md`

Inventory total: `7 + 5 + 6 + 8 + 2 + 1 = 29` distinct source filenames.

Duplicate uploads of identical historical files are one Concept source, not separate Concept identities. This exact inventory replaces the earlier shorthand `and earlier revisions` so source-level reconciliation can be checked deterministically.

## 3. Promoted / Incorporated Concept Provenance

### Package Lifecycle & Migration
Class: ARCHITECTURE / PLATFORM CONCEPT
Status: INCORPORATED / PROMOTED / DELIVERED PROVENANCE

Primary historical source:
- `copot_package_lifecycle_migration_concept_260804_161738.md`

Earlier lineage:
- `copot_package_lifecycle_migration_concept_260803_220452.md`
- `copot_update_upgrade_migration_concept.md`
- `copot_release_update_migration_concept_260803_171900.md`

Repository authority:
- `docs/28_package_lifecycle_migration_foundation_contract.md`
- related delivered Module package authority: `docs/29_module_package_lifecycle_contract.md`
- related existing-runtime authority: `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`

Current semantic disposition:
- local/operator-provided package lifecycle, forward transition, repair, migration, staging, integrity, health, and state machinery are delivered according to repository authority;
- destructive recovery remains Backup & Recovery-owned;
- generic downgrade/reverse migration remains unsupported unless future authority changes it;
- remote discovery/download, official update service, signing/trust, channels, and broader distribution ecosystem remain future concepts, not implied by delivered local package lifecycle.

Planning action: KEEP PROVENANCE; preserve future ecosystem sub-concepts separately.

### Backup & Recovery Foundation
Class: PLATFORM CONCEPT
Status: INCORPORATED / PROMOTED / COMPLETE / CLOSED PROVENANCE

Source:
- `copot_backup_and_recovery_concept_260803_220452.md`

Repository authority:
- `docs/31_backup_recovery_foundation_contract.md`

Current semantic disposition:
- accepted recovery foundation is delivered according to repository authority;
- broader scheduled backup policy, remote/cloud backup providers, and later storage integrations remain separate future decisions rather than automatically open parts of the closed foundation.

Planning action: KEEP PROVENANCE.

### Multi-Installation Isolation Foundation
Class: PLATFORM CONCEPT
Status: INCORPORATED / PROMOTED / COMPLETE / CLOSED PROVENANCE

Source:
- `multi_installation_isolation_foundation_concept_260804_092611.md`

Repository authority:
- `docs/34_multi_installation_isolation_foundation_contract.md`

Current semantic disposition:
- installation identity, namespace/table-prefix isolation, runtime coordination isolation, installer occupancy/routing, Module compatibility, and cross-subsystem boundaries are delivered according to repository authority;
- later refinements must not reopen accepted isolation semantics without concrete evidence.

Planning action: KEEP PROVENANCE.

### System Health & Status
Class: PLATFORM CONCEPT
Status: INCORPORATED / PROMOTED / COMPLETE / CLOSED PROVENANCE

Primary source:
- `copot_system_health_status_concept_260809_210842.md`

Earlier source:
- `copot_system_health_status_concept_260805_083932.md`

Repository authority:
- `docs/35_system_health_status_contract.md`

Current semantic disposition:
- System Health owner = Webcore/shared platform;
- reporting unit/domain owns diagnosis and optional remediation recommendation;
- System Health normalizes, aggregates, prioritizes, sanitizes, authorizes, and reports;
- Dashboard is a consumer only;
- generic update availability is not health;
- missing reporter does not imply healthy;
- Module permission dependency/base-access remains a separate capability.

Planning action: KEEP CLOSED PROVENANCE; presentation refinement remains a separate future Concept.

### Webcore & Module Database Table Ownership Separation
Class: ARCHITECTURE CONCEPT
Status: INCORPORATED / PROMOTED / CLOSED AS FOUNDATION INPUT

Current reconciled source:
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260816_194431.md`

Historical revisions:
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260814_172800.md`
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260814_151800.md`
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260813_131300.md`

Repository authority:
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`

Current semantic disposition:
- one table has exactly one authoritative owner;
- shared use does not imply shared ownership;
- Webcore/Module migration lineages remain independent;
- extension provenance is separate from ownership;
- private cross-Module schema writes fail closed unless explicitly authorized;
- compatibility is multi-lineage and transition-aware;
- unsupported/reverse transitions fail closed;
- exact future historical release-support window remains a separate product/release policy question.

Planning action: KEEP PROVENANCE; do not reopen delivered foundation from this Concept alone.

### Copot Webcore & Extension Architecture Reconciliation
Class: ARCHITECTURE CONCEPT
Status: INCORPORATED / PROMOTED / COMPLETE / CLOSED PROVENANCE

Current historical source:
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md`

Earlier revision:
- `concept_webcore_extension_architecture_reconciliation_260820_120500.md`

Repository authority:
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`

Current semantic disposition:
- Webcore owns complete minimum viability;
- valid installation works with zero optional Modules and zero Themes;
- Built-in Public View is always-available no-Theme fallback;
- Platform is reusable machinery, not ownership by reuse;
- Bundled Module means distributed with COPOT, not mandatory/enabled;
- ownership remains singular across EXTENDS/PROVIDES/CONSUMES/ENHANCES/CONTRIBUTES/INTEROPERATES/AGGREGATES relationships;
- Content Manager, Media Manager, Navigation Manager, Theme Manager, Users & Access, and Form Manager remain retained under current architecture;
- Module Manager, Settings Manager, and Redirect Manager standalone product-facing dispositions are retired as defined by repository authority;
- Taxonomy remains Module-owned by default and Taxonomy Manager is a high-confidence retirement candidate;
- System Manager remains product-facing parent/aggregator.

Planning action: KEEP CLOSED PROVENANCE; current architecture authority is repository contract, not this Concept.

## 4. Refinement Concept Family

### Refinement Milestone Governance
Class: GOVERNANCE CONCEPT
Status: INCORPORATED AS PLANNING RULESET / PROVENANCE

Source:
- `copot_refinement_milestone_governance_concept_260803_220452.md`

Current semantic direction:
- MR work refines an accepted baseline;
- typical scope includes presentation hierarchy, workflow clarity, responsive behavior, accessibility, interaction quality, consistency, and bounded consumption of already-defined capability;
- architecture, schema/lifecycle authority, new execution machinery, and unrelated new capability are not automatically MR work.

Current relationship:
- project governance now controls execution rules;
- this Concept remains semantic provenance for refinement classification.

### MR.x Session-Level Refinement Backlog
Class: BACKLOG CONCEPT
Status: DECOMPOSED / PARTLY INCORPORATED / PROVENANCE

Current source:
- `concept_mrx_session_refinement_backlog_260809_201113.md`

Earlier revision:
- `concept_mrx_session_refinement_backlog_260809_093844.md`

Disposition:
- installer refinement portions were incorporated into MR.1 and closed;
- module/admin refinement portions were consolidated into later refinement Concepts;
- unresolved items survive only through explicit entries in this registry/workplan, not because the old backlog file stays generically active.

### Core Modules & Dashboard Refinement
Class: UMBRELLA REFINEMENT CONCEPT
Status: DECOMPOSED / PROVENANCE

Primary source:
- `core_modules_dashboard_refinement_concept_260804_161738.md`

Supporting sources:
- Refinement Milestone Governance;
- MR.x Session-Level Refinement Backlog;
- consolidated refinement lineage.

Current semantic disposition:
- old `Core Module` vocabulary is historical and superseded by current Webcore + Bundled Module architecture;
- Dashboard remains a separate special-case refinement identity;
- shared Admin presentation concerns feed current MR.2 shared-primitives work;
- per-retained-Bundled-Module refinement now belongs in dedicated future workstreams.

### Core Module / Bundled Module Consistency
Class: PLANNING CONCEPT
Status: RECONCILED / SPLIT ACROSS CURRENT MR.2 AND FUTURE DOMAIN WORKSTREAMS

Primary historical sources:
- `copot_consolidated_refinement_concepts_260816_194432.md` — Core Module Consistency
- `copot_core_module_refinement_concept_260810_184600.md`
- `core_modules_dashboard_refinement_concept_260804_161738.md`

Locked surviving refinement direction:
- coherent shared page headers;
- compact rectangular actions;
- higher-density forms/lists;
- flat/square-corner treatment by default;
- shared typography/spacing/action hierarchy;
- consistent toolbar/search/form/helper/modal behavior;
- responsive/accessibility consistency;
- do not implement each Module as a visually isolated redesign.

Current architecture reconciliation:
- shared presentation primitives belong to MR.2 forward scope;
- each retained Bundled Module receives a future dedicated refinement workstream;
- Content/current accepted surfaces may serve as reference evidence where historical Concept explicitly named them, without transferring ownership.

### Core Module Refinement Detailed Delta
Class: DETAILED REFINEMENT SOURCE
Status: HISTORICAL SEMANTIC SOURCE / PARTLY SUPERSEDED BY ARCHITECTURE, STILL MATERIAL FOR FUTURE DOMAIN WORKSTREAMS

Source:
- `copot_core_module_refinement_concept_260810_184600.md`

Surviving shared direction:
- page-header pattern comparable to accepted Content surface where specified;
- Media search-to-inventory spacing referenced Content;
- flat square/rectangular surfaces by default;
- cross-module shared design system for page header, toolbar, search, action card, list/table, detail/form, action hierarchy, modal spacing, destructive confirmation, density, typography, spacing/grid, responsive behavior, brand color, accessibility.

Historical detailed domain deltas remain future domain-workstream input where still compatible with current architecture. Examples include Media thumbnail/list view, `Upload Media` → `Upload`, action placement, destructive confirmation, and Content Featured Media control/modal simplification.

Do not auto-implement stale detailed deltas. Re-audit them against current source and shared foundation before adoption.

### Consolidated Refinement Concepts
Class: MULTI-IDENTITY CONCEPT REGISTRY SOURCE
Status: RECONCILED / PROVENANCE

Current historical source:
- `copot_consolidated_refinement_concepts_260816_194432.md`

Earlier revisions:
- `copot_consolidated_refinement_concepts_260814_151800.md`
- `copot_consolidated_refinement_concepts_260810_020950.md`

Logical identities retained from this family:
- Core/Bundled Module Consistency;
- Dashboard Composition;
- System Health Presentation;
- Widget Foundation / Dashboard Widget;
- Admin UX Refinement;
- Installer refinement identities;
- Module lifecycle UX lineage;
- Webcore lifecycle/settings/admin UX lineage.

## 5. Dashboard and Widget Concepts

### Dashboard Composition
Class: PLANNING CONCEPT
Status: FUTURE / SEPARATE

Sources:
- consolidated refinement lineage;
- `core_modules_dashboard_refinement_concept_260804_161738.md`.

Boundary:
- consume existing Dashboard registry/foundation where it already satisfies needs;
- do not create a second Dashboard registry/framework;
- Dashboard composition, sidebar/topbar redesign, and Dashboard-specific behavior remain outside current MR.2 shared-primitives scope unless explicitly reclassified.

### System Health Presentation
Class: PLANNING CONCEPT
Status: FUTURE PRESENTATION REFINEMENT

Sources:
- consolidated refinement lineage;
- `copot_system_health_status_concept_260809_210842.md`;
- Core Modules & Dashboard refinement lineage.

Repository authority for facts/diagnosis:
- `docs/35_system_health_status_contract.md`

Boundary:
- presentation may improve without moving diagnosis, aggregation, authorization, or remediation ownership into Dashboard.

### Widget Foundation / Dashboard Widget
Class: PLANNING CONCEPT
Status: PARTLY DELIVERED / FUTURE EVOLUTION

Sources:
- `concept_future_widget_layout_260810_060139.md`
- `Future_Widget_Layout_Contract_260804_161738.md`
- older `Future_Widget_Layout_Contract_260716_111602.md`

Current delivered baseline authority:
- `docs/25_m3_9_internal_dashboard_contract.md` for delivered Dashboard baseline.

Conceptual stages:
1. Widget Foundation + Auto Layout;
2. Layout Persistence + Management;
3. Android-style Interactive Grid.

Current boundary:
- existing Stage-1-like foundation must be audited before new framework work;
- current accepted Dashboard layout remains authoritative;
- denser desktop logical-column ideas such as 6–8 columns are candidate/not locked;
- drag/drop, resize, persistence, arbitrary page-layout editing, and universal Admin-grid conversion remain future unless separately promoted.

## 6. Authorization and Ecosystem Concepts

### Module Permission Dependency & Canonical Base Access
Class: SECURITY / AUTHORIZATION CONCEPT
Status: FUTURE / PREPARATION CANDIDATE

Source:
- `copot_module_permission_dependency_base_access_concept_260809_210842.md`

Current semantic decision:
- a Module may declare a canonical base access permission where applicable;
- permission prerequisites may be deterministic/transitive;
- direct grant does not automatically mean effective permission;
- dependencies do not grant privileges;
- cycles/revocation inconsistencies must fail closed;
- declaring Module owns dependency metadata;
- shared authorization / Users & Access owns effective enforcement;
- third-party Modules cannot redefine another Module's permission graph;
- System Health is a consumer only.

Boundary:
- requires independent preparation before adoption;
- must not be silently implemented as System Health work or broad MR.x refinement.

### Module Package Identity & Capability Provider
Class: ECOSYSTEM / PACKAGE CONCEPT
Status: INDEXED / FUTURE

Current source:
- `copot_module_package_identity_and_capability_provider_concept.md`

Historical source:
- `copot_module_package_identity_and_capability_provider_concept_260803_220452.md`

Ancestor:
- Update/Upgrade/Migration concept lineage.

Current direction:
- stable package identity distinct from local slug/display title;
- publisher provenance/metadata;
- capability declarations and contract versions;
- provider conflict/resolution models;
- bounded contribution over arbitrary override.

Boundary:
- do not infer a generic provider framework without concrete consumers and explicit preparation;
- candidate home remains future distribution/ecosystem work.

### Official Remote Update / Distribution Ecosystem
Class: ECOSYSTEM CONCEPT
Status: FUTURE / DEFERRED / NOT ADOPTED

Source lineage:
- Package Lifecycle & Migration Concept;
- historical Concept Disposition Index.

Candidate scope:
- official update service;
- remote discovery/download;
- signed metadata/package trust;
- update channels;
- external/private distribution feeds.

Boundary:
- delivered local/operator-provided Package Lifecycle does not authorize this capability;
- revisit only through future ecosystem preparation.

### External Simulation Gate / Ecosystem Lineage
Class: FUTURE VALIDATION CONCEPT
Status: KEEP REGISTERED / UNSCHEDULED

Current direction:
- preserve external/representative ecosystem validation as a distinct future planning identity where third-party/module ecosystem behavior requires proof;
- do not create an automatic implementation sequence from this registry entry.

## 7. Installer / Lifecycle Refinement Concepts

### Installer Finalize Installation UI
Class: REFINEMENT CONCEPT
Status: INCORPORATED / PROMOTED / CLOSED PROVENANCE

Sources:
- MR.x Session-Level Refinement Backlog;
- consolidated refinement lineage.

Repository authority:
- `docs/36_mr_1_installation_refinement_contract.md`

Planning action: NONE / PROVENANCE.

### Installer Database Selection & Multi-Installation UX
Class: REFINEMENT CONCEPT
Status: INCORPORATED / PROMOTED / CLOSED PROVENANCE

Sources:
- MR.x Session-Level Refinement Backlog;
- `multi_installation_isolation_foundation_concept_260804_092611.md`.

Repository authority:
- `docs/36_mr_1_installation_refinement_contract.md`
- `docs/34_multi_installation_isolation_foundation_contract.md` for isolation semantics.

Current Installer intent baseline:
- Fresh;
- Coexist;
- Adopt.

Normal existing-install Update/Upgrade/Repair belongs to System Manager/Webcore lifecycle, not Installer refinement.

### Database Historical Release-Support Window
Class: RELEASE / COMPATIBILITY POLICY CONCEPT
Status: FUTURE POLICY DETAIL OPEN

Source:
- database ownership Concept lineage.

Delivered foundation already provides:
- multi-lineage compatibility;
- transition-aware compatibility;
- declared compatibility plus authorized forward transition;
- fail-closed unsupported state;
- no generic downgrade/reverse migration.

Open only:
- exact historical release-support window;
- policy/product-facing syntax if later required.

### Installation & Runtime Identity Exposure
Class: PRODUCT/PRESENTATION CONCEPT
Status: UNRESOLVED / SEPARATE

Historical direction:
- possible About/System exposure;
- historical proposed permission `system.runtime.manage`.

Boundary:
- not adopted by current MR.2 or architecture work;
- requires fresh product/security preparation before implementation.

### Cross-Fileset Upgrade Ownership Proof Gap
Class: ARCHITECTURE / PRODUCT INVESTIGATION
Status: UNRESOLVED / SEPARATE / NON-BLOCKING BY DEFAULT

Boundary:
- preserve as planning concern;
- do not silently fold into MR.2, Package Lifecycle, or Database lifecycle without concrete evidence and explicit adoption.

### Stale Package-Owned File Reconciliation
Class: LIFECYCLE REFINEMENT / AUDIT CONCEPT
Status: UNRESOLVED / SEPARATE

Candidate questions:
- authoritative ownership proof for stale paths;
- safe detection/deletion boundary;
- interaction with Repair/Recovery;
- integrity verification;
- rollback/restore expectation;
- compatibility with package inventory and operator-owned paths.

Boundary:
- audit before any implementation;
- no destructive cleanup authorization follows from this Concept.

## 8. Current Shared Admin Concepts

### Shared Admin Visual Primitive Single-Source Rule
Class: REFINEMENT / SHARED PRESENTATION CONCEPT
Status: ADOPTED INTO CURRENT MR.2 PLANNING

Current semantic intent:
- shared Admin visual elements should come from canonical shared primitives/tokens/classes;
- consumers should not locally recreate equivalent title, eyebrow, header, card, search/filter, form/helper, action, modal, typography, spacing, or radius behavior without a semantic reason;
- changing one canonical primitive should propagate to intended consumers;
- Content/current accepted surfaces may serve as reference evidence where historical refinement Concept explicitly named them;
- reference does not transfer ownership;
- flat/square treatment remains default unless a specific component requires an exception.

Implementation authority: NONE from this Concept alone.

Current planning home:
- MR.2 WU5 Shared Admin Primitive Audit & Contract, then later shared-artifact WUs after separate authorization.

### Shared Admin Action Placement & Button Sizing Pattern
Class: REFINEMENT / SHARED PRESENTATION CONCEPT
Status: CANDIDATE SHARED PRIMITIVE / ADOPTED FOR MR.2 AUDIT

Current direction:
- semantically paired primary/secondary actions should use consistent sizing where appropriate;
- sibling actions need explicit spacing;
- form/card action placement should be canonical where semantics permit, with responsive fallback;
- domain-specific interactions may justify explicit exceptions;
- shared primitive ownership is preferred over page-specific CSS duplication.

Implementation authority: NONE from this Concept alone.

### Shared File Intake Interaction Pattern
Class: INTERACTION CONCEPT
Status: THREAD-LEVEL SAVED / UNRECONCILED / NOT ADOPTED

Current direction:
- selecting a file through the native picker may eventually trigger upload/intake automatically when no additional pre-upload choice exists;
- flow should continue directly to the next meaningful state such as validation, preview, crop, attachment, or confirmation;
- avoid redundant second Upload click and duplicate submission;
- candidate consumers include Media, Content Featured Media, Module/package ZIP intake, and future Admin upload surfaces;
- ownership/lifecycle services remain domain-owned; this Concept concerns shared interaction, not a new upload engine.

Boundary:
- not part of current MR.2 implementation;
- not retroactively added to Media WU5;
- requires product/shared-interaction preparation before adoption;
- reconcile at MR.2 closure or earlier only if a concrete dependency requires it.

## 9. Current MR.2 / Future Bundled Module Concept Disposition

Current architecture makes per-Bundled-Module refinement distinct from shared Admin primitive refinement.

Current MR.2 planning home:
- shared Admin primitive audit, consolidation, representative adoption, propagation proof, cross-surface verification.

Dedicated future workstream Concepts retained:
- Media Manager Refinement;
- Navigation Manager Refinement;
- Theme Manager Refinement;
- Users & Access Refinement;
- Form Manager Refinement.

Each future workstream must:
- start from current repository source and accepted architecture;
- consume shared Admin primitives;
- re-audit historical module-specific Concept deltas before adoption;
- classify findings as refinement, defect, new capability, architecture/product expansion, Deferred Item, or not applicable;
- avoid reintroducing retired manager ownership or stale Core Module assumptions.

### Media Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM CONCEPT
Status: FUTURE / PREPARATION REQUIRED

Historical semantic sources:
- `copot_core_module_refinement_concept_260810_184600.md` Media section;
- M3.8 Media contract lineage;
- current source/architecture.

Must preserve:
- Webcore minimum Media viability;
- Media Manager advanced processing/variant ownership;
- consumer-driven integration;
- generic processing free from hardcoded Content-specific assumptions;
- existing crop/consumer-profile capability as Module-level advanced capability where authoritative current architecture places it.

Re-audit historical desired deltas such as Thumbnail/List views, upload copy/layout, action placement, preview/detail, picker/crop UX, deletion comprehension, and responsive behavior rather than automatically implementing old design notes.

Explicit expansion/deferred candidates remain separate unless adopted: folders, galleries, bulk workflow, drag-and-drop, expanded media types, arbitrary profiles, advanced editing/optimization, CDN/cloud/external storage.

### Navigation Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM CONCEPT
Status: FUTURE / PREPARATION REQUIRED
Current architecture: retained Bundled Module EXTENDS Webcore Navigation.

### Theme Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM CONCEPT
Status: FUTURE / PREPARATION REQUIRED
Current architecture: retained Bundled Module manages optional Theme presentation.

### Users & Access Refinement
Class: FUTURE DEDICATED WORKSTREAM CONCEPT
Status: FUTURE / PREPARATION REQUIRED
Relationship: future Module Permission Dependency & Base Access may be relevant but is not automatically adopted.

### Form Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM CONCEPT
Status: FUTURE / PREPARATION REQUIRED / CORRECTNESS AUDIT BEFORE SCOPE LOCK
Historical functional questions such as validation meaning/field-key semantics must not be disguised as styling.

## 10. Historical Manager / Presentation Lineage

### Module Manager Lifecycle UX
Class: HISTORICAL REFINEMENT CONCEPT
Status: RE-HOMED / PROVENANCE

Historical delivered semantics remain in Module Package Lifecycle authority.
Current architecture retires standalone Module Manager as product-facing destination; System Manager is canonical lifecycle surface.

Planning action:
- preserve useful interaction/evidence lineage;
- do not recreate standalone Module Manager ownership.

### Webcore Lifecycle Settings/Admin UX
Class: HISTORICAL / RECONCILED REFINEMENT CONCEPT
Status: PARTLY INCORPORATED / CURRENT-OWNER DEPENDENT

Relevant authority:
- Package Lifecycle;
- Existing-Runtime Webcore Lifecycle;
- System Manager contracts;
- current architecture.

Boundary:
- future presentation refinement follows current owner, not old Settings Manager assumptions.

### What's New Presentation
Class: REFINEMENT CONCEPT
Status: HISTORICAL LINEAGE / FUTURE CONSUMER RE-AUDIT REQUIRED

Locked semantic direction:
- release/package metadata is source authority;
- presentation consumer must not invent release history or own a duplicate changelog store.

Current architecture caveat:
- historical Webcore consumer = System Manager remains plausible;
- historical standalone Module Manager consumer is superseded by current architecture and must be re-audited for any future Module detail surface.

## 11. Taxonomy Disposition

### Taxonomy Manager
Class: ARCHITECTURE / PRODUCT DISPOSITION
Status: HIGH-CONFIDENCE RETIREMENT CANDIDATE

Current architecture:
- Taxonomy remains Module-owned by default;
- shared taxonomy identity is exceptional and requires concrete semantic need.

Planning action:
- no automatic dedicated refinement workstream;
- revisit only if accumulated evidence materially changes the retirement decision.

## 12. Historical Concept Disposition Index

Source:
- `copot_concept_disposition_index_260803_220452.md`

Status: HISTORICAL INDEX / SUPERSEDED IN FUNCTION BY `workplan.md` + `concept.md`

Important identities recovered from it and preserved here:
- Module Package Identity & Capability Provider;
- Official Remote Update / Distribution Ecosystem;
- Package Lifecycle & Migration;
- Backup & Recovery;
- Refinement Milestone namespace/governance.

The old candidate homes/statuses are historical planning evidence, not current authority.

## 13. Lineage-Loss Findings Incorporated by This Revision

The Concept audit found that later compressed Workplans and the absence of a canonical repository Concept index risked silently losing or obscuring logical identities. This registry explicitly preserves:
- promoted Package Lifecycle, Backup/Recovery, Multi-Installation, System Health, Database Ownership, and Architecture reconciliation provenance;
- Module Permission Dependency & Base Access;
- Module Package Identity & Capability Provider;
- Official Remote Update / Distribution Ecosystem;
- Future Widget Layout stages and candidate status;
- Refinement Milestone Governance;
- MR.x backlog provenance;
- Core Modules & Dashboard umbrella decomposition;
- Core/Bundled Module consistency principles;
- Dashboard Composition;
- System Health Presentation;
- Widget Foundation evolution;
- installer refinement identities now closed through MR.1;
- Database historical release-support policy question;
- Installation & Runtime Identity Exposure;
- Cross-Fileset Upgrade Ownership Proof Gap;
- Stale Package-Owned File Reconciliation;
- What's New lineage;
- Shared Admin primitive/button concepts;
- Shared File Intake Interaction Pattern;
- future dedicated Bundled Module refinement identities;
- Taxonomy Manager retirement-candidate disposition.

Source-level reconciliation in this revision additionally makes all 29 distinct historical Concept/source filenames explicit, including the previously shorthand System Health earlier revision.

Resolved concepts are preserved with their current resolved/provenance status rather than resurrected as active work.

## 14. Authorization Boundary

This Concept registry is planning context only.

A Concept may become implementation scope only after the applicable preparation, explicit adoption/promotion decision, durable repository authority, and separately authorized execution boundary.

Do not instruct an executor to implement a Concept merely because it appears here or is marked future/candidate/current-planning input.
