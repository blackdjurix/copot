# Copot — Consolidated Refinement Concepts
Date version: 2026-08-16 19:44:32 WIB

Status: Concept / Future Refinement Registry Source / RECONCILED
Project: COPOT
Supersedes: `copot_consolidated_refinement_concepts_260814_151800.md`

## 1. Purpose

This file consolidates stable refinement Concept identities used across COPOT planning.

Each heading is an independent logical Concept identity. This file does not:
- create repository implementation authority;
- authorize implementation;
- adopt Deferred Items;
- reopen accepted closures;
- authorize release/tag/publication;
- turn architecture/new capability into MR work merely because an Admin screen presents it.

## 2. Refinement Classification Rule

MR is intentional refinement of an accepted baseline.

Typical MR scope:
- presentation hierarchy;
- workflow clarity;
- responsive behavior;
- accessibility;
- interaction quality;
- consistency;
- operator-facing consumption of already-defined capability.

Architecture, lifecycle authority, schema authority, migration authority, and new execution machinery are not automatically MR work.

## 3. Current Accepted Baselines

Repository authority now establishes:

- MR.1 Installation Refinement: COMPLETE AND CLOSED;
- Database Ownership & Lifecycle Management Foundation WU1–WU6: COMPLETE AND CLOSED;
- System Manager Webcore lifecycle capability: accepted functional baseline;
- Installer intents: Fresh / Coexist / Adopt;
- existing-install Update / Upgrade / Repair: System Manager / Webcore Lifecycle;
- Database-only Update Case C semantics: accepted;
- shared-database/same-prefix/full-shared/partial-sharing verdict: accepted;
- mandatory WU6 persisted Retry acceptance: PASS.

Therefore MR.2 no longer waits on the Database Ownership foundation as an unfinished dependency. It consumes that closed baseline.

## 4. Core Module Consistency

Intent: refine accepted Core Module Admin surfaces toward coherent presentation and interaction quality without reopening valid functional closures.

Supporting source:
- `core_modules_dashboard_refinement_concept_260804_161738.md`

Boundary:
- refinement-only unless fresh evidence proves a defect or new capability requirement.

Implementation authorization: NONE.

## 5. Dashboard Composition

Dashboard remains separate from MR.2 unless explicitly reclassified.

Sources:
- `core_modules_dashboard_refinement_concept_260804_161738.md`
- Future Widget Layout Concept lineage

Boundary:
- do not create a second Dashboard registry/framework.

Implementation authorization: NONE.

## 6. System Health Presentation

Intent: refine Dashboard presentation of accepted System Health facts without moving diagnosis, aggregation, authorization, or remediation ownership into Dashboard.

Authority:
- `docs/35_system_health_status_contract.md`

Implementation authorization: NONE.

## 7. Widget Foundation / Dashboard Widget

Preserve and evolve reusable Dashboard widget presentation without rebuilding capabilities already delivered by `AdminDashboardRegistry`.

Future stages may include:
1. Widget Foundation + Auto Layout;
2. Layout Persistence + Management;
3. Android-Style Interactive Grid.

Current delivered baseline must be audited before expansion.

Implementation authorization: NONE.

## 8. Installer Refinement

MR.1 is closed.

Current semantic authority after Database Ownership WU5:

- Fresh;
- Coexist;
- Adopt.

Normal existing-install Update / Upgrade / Repair belongs to System Manager / Webcore Lifecycle.

Future Installer work belongs in MR scope only when it is presentation refinement such as:
- wording;
- hierarchy;
- comprehension;
- visual consistency;
- accessibility;
- responsive behavior.

Do not reopen lifecycle routing as MR work.

Implementation authorization: NONE.

## 9. Module Manager Lifecycle UX

Intent: refine Module Manager lifecycle presentation while preserving accepted Module Package Lifecycle semantics.

Candidate MR scope:
- version/status;
- lifecycle action hierarchy;
- Update / Upgrade / Repair presentation;
- post-action feedback;
- Module Detail information architecture;
- Module What's New;
- shared Admin interaction patterns.

Authority for lifecycle semantics remains repository contracts/source.

Implementation authorization: NONE.

## 10. Webcore Lifecycle / System Manager UX

System Manager functional capability is now an accepted baseline delivered by the Database Ownership & Lifecycle Management Foundation.

MR.2 may refine:
- lifecycle action hierarchy;
- Webcore version/status presentation;
- compatibility/migration status;
- package-selection workflow;
- Update / Upgrade / Repair presentation;
- Database-only Update presentation where eligible;
- error/recovery states;
- persisted Retry/reconciliation presentation;
- result and next-action clarity;
- shared Admin consistency.

MR.2 must not recreate:
- Package Lifecycle engine;
- migration engine;
- ownership catalog;
- migration authority;
- Installer intent semantics;
- database lifecycle classifications.

Implementation authorization: NONE.

## 11. What's New Admin Surfaces

What's New remains package-owned release metadata consumed by Admin surfaces.

Webcore target:
`System Manager → Webcore lifecycle/detail → What's New`

Module target:
`Module Manager → Module Detail → What's New`

Presentation is MR refinement. Data/release authority remains package/release metadata.

Implementation authorization: NONE.

## 12. MR.2 — System Manager & Core Modules UI Refinement

Canonical target identity:

**MR.2 — System Manager & Core Modules UI Refinement**

Status:
SELECTED NEXT PLANNING TARGET / PREPARATION PENDING / NOT PROMOTED / IMPLEMENTATION NOT AUTHORIZED

Candidate scope families:
- System Manager lifecycle UX;
- Webcore What's New;
- Module Manager lifecycle UX;
- Module What's New;
- Core Module consistency;
- related shared Admin patterns;
- optional later Installer presentation refinement only where semantics are already settled.

Dashboard remains separate unless explicitly reclassified.

### Preparation requirement

Before MR.2 contract promotion:
- inspect current delivered System Manager;
- inspect current Module Manager and relevant Core Module surfaces;
- identify duplicated/shared Admin patterns;
- classify findings as refinement vs defect vs new capability;
- determine whether one MR.2 topology remains coherent or needs bounded sub-workstreams/WUs;
- identify human visual/UX acceptance requirements;
- preserve all accepted lifecycle/ownership boundaries.

## 13. Reconciled Adjacent Decisions

### Database Version Compatibility Window
Status: ARCHITECTURE RESOLVED / FUTURE RELEASE-SUPPORT POLICY DETAIL OPEN

Foundation already resolved transition-aware compatibility and fail-closed unsupported transitions.

Exact historical support-window product policy remains separate.

### Shared Database / Same-Prefix / Shared-Tableset / Partial-Sharing
Status: RESOLVED / PROMOTED

- distinct namespaces supported;
- same prefix is not independent coexistence;
- full shared tableset represents one legitimate installation/state topology;
- partial sharing rejected/fail closed.

No longer an unresolved MR.2 dependency.

### Database-only Lifecycle Case C
Status: RESOLVED / PROMOTED

Accepted action:
Database-only Update

MR.2 owns only presentation refinement.

### Installation & Runtime Identity Exposure
Status: UNRESOLVED / SEPARATE
Possible About/System surface; proposed `system.runtime.manage`.
Do not auto-adopt into MR.2.

### Admin Shell CSS Single-Source
Status: UNRESOLVED / SEPARATE
Audit if a concrete MR.2 shared-style ownership problem requires it.

### Cross-Fileset Upgrade Ownership Proof Gap
Status: UNRESOLVED / SEPARATE / NON-BLOCKING BY DEFAULT

### Stale Package-Owned File Reconciliation
Status: UNRESOLVED / AUDIT CANDIDATE / SEPARATE

## 14. Managerial Sequence

1. MR.1 Installation Refinement
   COMPLETE AND CLOSED / PROVENANCE

2. Database Ownership & Lifecycle Management Foundation
   COMPLETE AND CLOSED / PROVENANCE

3. MR.2 — System Manager & Core Modules UI Refinement
   SELECTED NEXT PLANNING TARGET / PREPARATION PENDING

4. Admin Shell / Dashboard Refinement
   separate future planning identity unless later explicitly reclassified

This sequence is planning direction only.

## 15. Current Disposition

Durable planning direction now records:
- Database Ownership foundation is no longer a future dependency; it is the accepted baseline;
- MR.2 is the immediate next planning/preparation target;
- Case C and shared-tableset decisions are no longer unresolved;
- Installer semantics are settled and outside MR.2 functional ownership;
- unresolved adjacent items remain separate unless explicit evidence/authorization brings them in;
- no MR.2 implementation is authorized by this Concept.

Repository closure anchor used for this reconciliation:
`main @ 31276a74cbb6818cfdaa839d07793dee6d2200ae`