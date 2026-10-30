# COPOT — Non-Linear Workplan
Date version: 2026-10-17 09:33:57 WIB
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
- Seven-Tab Site Settings Completion & Hardening: WU2 Security TECHNICALLY
  COMPLETE / durable at `ab2ef3ab254c04513128d9385fb2c57662e52ad9`;
  WU3 System Email COMPLETE / ACCEPTED / durable at
  `fc4bf7754005b85ad890be32749f9c17f218c4e9`; WU4 Redirects COMPLETE /
  ACCEPTED. WU5 Seven-Tab Integration & Hardening is COMPLETE / ACCEPTED / CLOSED
  (BOUNDED): its WU2/WU3/WU4 HARD dependencies were satisfied; accepted
  implementation is integrated at
  `35ff0e050ac485520878bca7832110acd844deb4`.
  Existing Modules test assertion/source-shape mismatch was reproduced at
  pre-WU5 baseline `14a2ff73f7fcc8143a9feabb4a3410dd284e7cf5` and
  current WU5 baseline, and is retained as separate pre-existing test debt.
  Seven-Tab WU6 is now dependency-eligible but NOT STARTED / NOT ACCEPTED.
  Specific legacy runtimes still require per-instance proof and separate
  reconciliation authorization.
- Package Lifecycle Forward-Update Bootstrap Authority Reconciliation:
  former technical authority gap RESOLVED through existing legacy classification,
  exact-match adoption and separately guarded reconciliation services/routes.
  Normal package apply intentionally remains BOOTSTRAP_REQUIRED for LEGACY.
  Unknown/unprovable migration history remains fail-closed; actual existing
  runtime readiness is separately UNRESOLVED per instance. Server-empty
  bootstrap remains deferred. Earlier GAP CONFIRMED findings are historical.
- Lifecycle Net-Zero Retirement & Safe-Retry Capability Continuation:
  previously saved thread-level Concept materialized in
  `concepts/copot_lifecycle_net_zero_retirement_safe_retry_capability_concept.md`;
  backend implementation-state inventory only; no new implementation
  authorization, pre-contract, or dedicated contract.
- Post-M3 — Webcore & Extension Architecture Reconciliation: COMPLETE / CLOSED;
- Webcore Product Completeness & Stabilization: WU1 COMPLETE / CONTRACT
  LOCKED; WU2 COMPLETE / ACCEPTED / INTEGRATED; WU3 COMPLETE / ACCEPTED /
  INTEGRATED / CLOSED; WU4 COMPLETE / ACCEPTED / CLOSED under its locked
  contract (Batches 1–2 complete/closed; Batch 3 accepted/complete/closed;
  Batch 4 technically and human/product accepted/closed); WU5 COMPLETE /
  ACCEPTED / CLOSED;
  WU6 COMPLETE / ACCEPTED / CLOSED; WU7 COMPLETE / ACCEPTED / CLOSED;
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

#### One-owner module package boundary — future reconciliation note
Class: MODULE PACKAGE LIFECYCLE FOLLOW-ON PLANNING / CONTRACT PRESERVATION
Status: FUTURE / NO RETROACTIVE CONTRACT CHANGE

- The closed docs/29 contract already establishes one package -> one technical module and defers multi-module packages. Record future proposal to reject mixed independent Webcore/Module and multi-module artifacts, with Webcore-owned internal components remaining under Webcore ownership.
- Preserve existing complete/closed WU1-WU7 status and shared lifecycle engine; future strictness requires explicit contract/gap evaluation, not amendment by this workplan entry.


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

### Adaptive Package Lifecycle & Independent Domain Compatibility
Class: ACTIVE CONCEPT / BOUNDED CONTRACT-PROMOTED CAPABILITY
Status: FOUR-SURFACE NORMATIVE CONTRACTS PROMOTED / SIX-WU TOPOLOGY LOCKED / WU1–WU6 ACCEPTED / PUBLIC V3 OPERATIONAL COVERAGE CLOSED (BOUNDED)

Sources:
- `concepts/copot_adaptive_package_lifecycle_independent_domain_compatibility_concept.md` [PRIMARY BROADER CONCEPT; NOT PROMOTED]
- `docs/canonical_target_inventory_fingerprint_contract.md` [ACCEPTED BOUNDED WEBCORE FILE-STATE AUTHORITY]
- `docs/shared_destructive_plan_confirmation_operator_input_contract.md` [ACCEPTED SHARED CONFIRMATION INPUT]
- `docs/28_package_lifecycle_migration_foundation_contract.md` [ACCEPTED PROSPECTIVE V3 PACKAGE APPLY AMENDMENT]
- `docs/31_backup_recovery_foundation_contract.md` [ACCEPTED PROSPECTIVE V3 RECOVERY AMENDMENT]

Promotion evidence:
- Coordinated four-surface promotion accepted and materialized at `main @ f7c3987fb6bd8559b09f960bdcd1be1fd758f0ce` on 2026-10-11.
- Manifest v1/v2 acceptance and historical WU5/WU6 no-deletion record retained; offline-only and existing public output statuses preserved.
- These are specification authorities only; manifest-v3 package apply, explicit file removal/rename, operator confirmation, recovery mutations and deployment remain NOT IMPLEMENTED/NOT AUTHORIZED by promotion.

Bounded implementation-gap audit evidence (completed read-only Codex review; no mutation):
- `app/Core/PackageContract.php` still declares `CURRENT_MANIFEST_CONTRACT_VERSION = 2`, with accepted versions 1/2.
- `app/Core/PackageManifestReader.php` validates current keys via existing associative decoding and does not contain v3 scheme-bound package handling.
- `app/Core/PackageTargetRequirements.php` and `PackageTargetRequirement.php` provide existing v2 target-requirement validation to reuse rather than duplicate.
- Codex verified reuse/extension boundaries for existing Package Lifecycle, CommittedLifecycleStateStore, WebcoreApplyPlan, PackageOwnedFileApplier, Backup & Recovery domains/journals, CLI bin/copot, SystemManagerLifecycleService and routes/system_manager.php. No second recovery engine, mutex or independently authoritative confirmation surface is justified.
- Audit verdict: DOCUMENTATION READY / TOPOLOGY READY / IMPLEMENTATION AUTHORIZATION PENDING. Six WUs retain stated order and dependencies; no added WU required.

WU1 completion and governance acceptance (2026-10-11):
- WU1 V3 Manifest & Canonical Identity implementation and fast-forward integration verified at `e67ba93b02d1f481d452c671935381dc5d41a9fc` (10 scoped source/test files).
- Codex reported 257 assertions PASS across focused/regression suites and normative digest fixtures. `package_lifecycle_wu7.php` NOT RUN: requires generated official package and exercises broader E2E, not mandatory WU1 coverage. Zip-disabled branch and Windows symlink cleanup fixture unavailable in this environment; retain these as validation limitations, not passes.
- GPT/user governance acceptance: WU1 COMPLETE / ACCEPTED / CLOSED for bounded WU1 scope, based on verified integration, technical evidence, and non-blocking test classification. This does not assert full v3 apply/recovery completion.
- WU2 HARD dependency on WU1 satisfied. WU2 is next implementation target under accepted contracts; preserve original dirty checkout and verify a clean isolated workspace and current remote baseline before execution.
- Historical pre-implementation audit and former authorization-pending language below are provenance, not current state. In-contract authorization follows canonical Rule Section 7; separately gated actions remain separate.

WU2 completion and bounded governance acceptance (2026-10-11):
- WU2 committed source-inventory implementation is integrated on remote `main` at `8838b6cbe9332b7b2cbd2d438e34de933ab99a19`; six changed source/test files verified within source-inventory scope.
- Codex reported manifest-v3 12, package-contract 73, existing package-lifecycle WU2 67 assertions PASS plus the new focused source-inventory test PASS. Test environment ZIP/symlink capability cases remained skipped. Installer regression NOT PASS/NOT RUN TO COMPLETION due to reported pre-existing installer fixture validation failure; prior origin of failure is not independently verified. This limitation remains explicitly unresolved for broader E2E acceptance, without asserting WU2 regression PASS.
- WU2 COMPLETE / ACCEPTED / CLOSED for its bounded durable artifact, reference-linkage, and fail-closed validation scope. No WU3–WU6 execution inferred. WU3 HARD dependency on WU1+WU2 is satisfied; WU3 next for source-relative planner and immutable action identity. Revalidate any installer-fixture overlap if later cross-lifecycle E2E depends on it.

WU3 completion and bounded governance acceptance (2026-10-11):
- Implementation and fast-forward integration verified at `614650202581cc0542abb322fdcdb36c2541ef90`, with four source/test files confined to WU3 source-relative planning and immutable identities.
- Codex reported PASS for focused WU3, manifest-v3 (12), package contract (73), WU2 inventory, existing package-lifecycle WU2 (67), and runtime lifecycle (10 and 19 assertions); focused WU3 assertion total not supplied. Existing ZipArchive/symlink environment skips remain. Installer fixture failure was outside pure WU3 planner scope and remains broader regression limitation.
- WU3 COMPLETE / ACCEPTED / CLOSED for bounded source-relative planning only, not execution of destructive actions. WU4 HARD dependency on WU3 is satisfied; WU4 is next. WU5–WU6 have not started.

WU4 completion and bounded governance acceptance (2026-10-11):
- WU4 confirmation authority, actor evidence, typed CLI/System Manager adapters, durable receipt and optional operation-record linkage integrated at `43f2c93f3825bd491690081c0059d9467b25237f`; eight scoped source/test files verified.
- Codex reported PASS on focused WU4, WU3, WU1 (12 and 73 assertions), WU2 inventory/lifecycle (67), and pre-mutation retry (8) regressions. Existing Windows ZipArchive-disabled and symlink-fixture skips retained. No unresolved WU4 blocker reported; independent runtime test execution not claimed.
- WU4 COMPLETE / ACCEPTED / CLOSED for shared plan-bound confirmation only. WU5 HARD dependency on WU3+WU4 is satisfied. WU5 is next for explicit apply and existing recovery integration; WU6 has not started.

WU5 completion and bounded governance acceptance (2026-10-11):
- WU5 explicit add/replace/remove/rename apply, coordinator/result integration and focused tests integrated in remote `main` at `739f0368c0027e0fe38727393cb81503da511220`; four scoped source/test files verified.
- Codex reported existing WU5 34, v3 WU5 5, manifest WU1 12, contract WU1 73, Backup & Recovery WU1 25 assertions PASS, and WU4/WU3/WU2 focused suites PASS. Tests independently not rerun by governance side. Mutating scenarios exercised in disposable fixtures only; no live installation changed.
- WU5 COMPLETE / ACCEPTED / CLOSED for bounded implementation and focused fixtures; this does not assert cross-lifecycle recovery/crash/E2E acceptance. WU6 dependency on WU1–WU5 satisfied. WU6 remains the final cross-lifecycle acceptance gate and must assess previous platform/installer fixture limitations and negative/recovery cases.

WU6 validation checkpoint — BLOCKED / NOT ACCEPTED (2026-10-11):
- WU6 code correction integrated at `3a275234384966e9ca0cd375a74c96fb5f8d5457`, changing only `app/Core/WebcoreApplyCoordinator.php` to remove a redundant V3 coordinator mutex adapter and restore the locked two-site mutex topology.
- Codex reported extensive standalone/focused PASS across manifest v3, source-relative planner, confirmation, explicit apply, lifecycle retry/finalization, backup/recovery and negative cases. These are not a substitute for mandatory composed lifecycle/recovery E2E.
- Mandatory `package_lifecycle_wu6_retry_e2e.php` NOT RUN TO COMPLETION: disposable MariaDB setup failed at `C:\xampp\mysql\data\aria_log.00000001` with Errcode 22 Invalid argument. Root cause has not been established; do not assume application regression or reinstall/alter MariaDB data. Resolve in isolated/disposable environment and rerun.
- `package_lifecycle_wu7.php` NOT RUN because official package artifact absent; do not create/publish artifact without separate authorization. ZIP-disabled and Windows symlink capability branch remain environment-limited.
- WU6 acceptance / closure BLOCKED. WU1–WU5 accepted status preserved. Next action: non-destructive disposable MariaDB environment diagnosis, retry E2E execution, evidence classification and WU6 reassessment.

WU6 mandatory E2E resolution and bounded governance acceptance (2026-10-12):
- Codex confirmed `package_lifecycle_wu6_retry_e2e.php` PASS (24 assertions), exercising composed retry, file mutation, migrations, recovery capture/reconciliation, committed-state publication, cleanup and terminal state on an independent MariaDB 10.4.32 instance with temporary datadir/logs, port 13308, and non-empty disposable credential. Root cause of prior blockade: normal XAMPP datadir was inadvertently targeted, then empty disposable credential was converted to null by `Env::get()` and quiescence became unavailable; no normal XAMPP database modification or repository code change was required.
- Affected reported regression PASS: WU6 health 16, retry finalization 14, awaiting-WU6 12, pre-mutation retry 8, WU5 34, V3 WU5 5, recovery topology 33/9, Backup & Recovery WU6 20 assertions. Identity evidence was supplied for migration, recovery and recovery manifest.
- Retained limitations: ZipArchive-disabled branch executor-dependent; Windows symlink fixture unavailable; official-package WU7 NOT RUN without an official artifact; Backup & Recovery WU7 previously 18 PASS, later batch rerun stalled/stopped. These are explicitly NOT counted as new PASS or comprehensive release/deployment acceptance.
- GPT/user governance-side bounded acceptance: WU6 COMPLETE / ACCEPTED / CLOSED for accepted six-WU v3 scope, based on technical E2E result and prior integrated WU1–WU5 evidence. Cross-lifecycle acceptance does not authorize release, official package generation, deployment, or live mutations. Broader E2E/environment limitations retained separately.

Post-closure read-only audit correction (2026-10-12; CURRENT STATE OVERRIDES EARLIER CLOSURE PROJECTION):
- Codex audit at `main @ 132c8e79cf85dceae459dd39262ca69a75e6c618` identified V3 standalone parser/inventory/planner/confirmation/executor primitives but no verified dispatch from public `PackageLifecycleService::apply()` and `PackageLifecycleFactory` into that V3 path. Public CLI/System Manager V3 destructive apply and action-level Backup & Recovery binding are UNVERIFIED / IMPLEMENTATION GAP.
- Previously accepted `package_lifecycle_wu6_retry_e2e.php` PASS (24 assertions) executes `PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION` (V2), so it establishes legacy composed retry/recovery behavior, NOT manifest-V3 public destructive E2E. Historical test results and governance acceptance records remain factually retained, but the current claim of completed public V3 operational acceptance is WITHDRAWN pending evidence.
- The coordinated docs/28 and docs/31 V3 addenda require existing Package Lifecycle coordinated apply and action-bound recovery. Public V3 routing therefore belongs to the already accepted bounded Webcore V3 contract scope, not a newly adopted database/module/composite Concept or a seventh work unit.
- Next gate: bounded technical implementation-gap reconciliation and safe public V3 routing through existing lifecycle/recovery authority; disposable public V3 removal/rename E2E and verified action/recovery linkage MUST PASS before WU6 operational acceptance may be restored. WU1–WU5 integrated primitives and their focused validation remain accepted as such; historical closure not erased.
- No release, deployment, official package publication, or live-runtime mutation is authorized by this correction.

Public V3 operational WU6 acceptance restored (2026-10-12):
- Remote main verified at `daa1426ceab8de2e5ebed18d6980aba569a99e56`. Public V3 dispatch corrections are integrated; final commit contains `app/Core/PackageLifecycleService.php` and new `tests/package_lifecycle_wu6_public_v3_e2e.php`.
- Codex reports actual `PackageLifecycleService::apply()` manifest-V3 E2E PASS (9 assertions): typed confirmation, rename/removal, recovery-backed mutation, committed source/target linkage, replay rejection, and terminal completion. WU1–WU6 focused and affected recovery regressions reported PASS. The legacy V2 retry E2E is not substituted for this public V3 proof.
- Disposable MariaDB 10.4.32 tests used isolated datadir/logs/port/credentials, with CREATE USER and quiescence/read_only acquisition-release/restoration PASS. Normal XAMPP MariaDB remained running and its datadir was not modified. Codex identified normal XAMPP `mysql.global_priv` Aria bitmap corruption; this is a separately unresolved LOCAL ENVIRONMENT HEALTH issue and no repair is authorized or performed.
- Bounded public V3 operational WU6 governance acceptance is RESTORED / ACCEPTED / CLOSED based on reported mandatory direct-entrypoint E2E and verified integrated commit. Preserved limitations: ext-zip-disabled and Windows symlink capability skips; official-package WU7 NOT RUN because artifact absent; IU1 adoption fixture has reported unrelated pre-existing failure. No claim of complete release readiness, production health, or deployment permission.

Accepted and locked sequential Work Units (implementation sequencing; each limited to its accepted contract scope):
1. WU1 — V3 Manifest & Canonical Identity: bounded duplicate-key-aware parser, exact v3 grammar, package evidence identity, target/removal digest and fixture regression; preserve v1/v2.
2. WU2 — Committed Complete Source Inventory: protected artifact store, committed-state references, root/owner policies, full/partial proof and durability/crash handling; HARD WU1.
3. WU3 — Source-Relative Planner & Immutable Action Identity: resulting full/partial inventory, retention/removal/rename constraints, operator-facing prepared plan and collision checks; HARD WU1+WU2.
4. WU4 — Shared Plan-Bound Destructive Confirmation: one service authority with typed CLI/System Manager adapter inputs, actor authorization, persistence and replay/stale handling; HARD WU3.
5. WU5 — Explicit Apply & Existing Recovery Integration: verified add/rename/remove with recovery capture, action journal, interruption reconciliation, terminal-state gates; HARD WU3+WU4. Do NOT build a second recovery engine.
6. WU6 — Cross-Lifecycle Acceptance & Closure: v1/v2 regression, full/partial equality, offline operation, crash/retry/recovery, unsafe path and negative confirmation cases, plus lifecycle/health acceptance; HARD WU1–WU5.

Topology acceptance gates:
- Completed Codex read-only capability/ownership and dependency audit; no documented normative blocker or required additional WU.
- Six-WU topology was explicitly accepted by user in current governance session; LOCKED as implementation planning topology only. Any change to WU decomposition/dependency requires new GPT/user acceptance.
- Preserve Package declares WHAT / Lifecycle determines WHAT CAN/MUST HAPPEN conceptual separation; independently scoped Database/Module/composite lifecycle and forward-update bootstrap remain separate workstreams.
- Topology acceptance is COMPLETE. In-contract implementation follows current Rule Section 7; separately gated actions retain their own authorization boundaries. Existing local dirty/stale checkout must remain untouched; new-session executor must verify a clean isolated worktree or authorized clean remote baseline.

Planning action: WU1–WU6 BOUNDED V3 SCOPE ACCEPTED AND CLOSED / NORMAL XAMPP PRIVILEGE-TABLE CORRUPTION TRACKED SEPARATELY / NO RELEASE OR DEPLOYMENT AUTHORIZATION

### Installation-Scoped Maintenance Guard — Bounded WU6 Support
Class: ACTIVE WORKSTREAM SUPPORTING FOUNDATION / CONCEPT-GATED
Status: USER DIRECTION ACCEPTED / DESIGN HARDENED / CONTRACT AMENDMENT PENDING / NOT IMPLEMENTED

Sources:
- `concepts/copot_installation_scoped_maintenance_management_concept.md` [PRIMARY]

Authority:
- `docs/28_package_lifecycle_migration_foundation_contract.md` [EXISTING PACKAGE LIFECYCLE]
- `docs/31_backup_recovery_foundation_contract.md` [EXISTING RECOVERY/QUIESCENCE]
- `docs/58_seven_tab_site_settings_completion_hardening_contract.md` [ACTIVE WU6 BOUNDARY]

Relations and bounded execution gates:
- Remains supporting scope of existing Seven-Tab WU6, not a newly activated WU, independent bootstrap, or NRP; immediate target remains two-phase Contract-v1-readable engine activation REPAIR then canonical BOOTSTRAP_V3, with normative amendment required before implementation.
- Authenticated Webcore Admin is current manual lock authority; future multi-user delegation is deferred. Default: installation-specific application write guard, not complete Admin Shell lock or shared MariaDB GLOBAL read_only.
- Full normal HTTP/Admin/API/CLI/cron/worker write-path coverage, controlled drain of queued and in-flight writes, maintenance/readiness evidence, bounded lifecycle-executor exception, and interrupted/recovery safety are implementation prerequisites; external MySQL writers remain an explicitly unmitigated technical limitation, even if operational DB access is restricted.
- Package inspection/initial preflight can be accessible before maintenance; authoritative preflight reports prerequisites and stale-plan invalidation; any mutation requires verified execution protection. Exact preflight/apply UI gating requires contract/product reconciliation.
- Existing `InstallationMutex`, maintenance/recovery ownership, `DatabaseQuiescenceCapability`, `MariaDbReadOnlyQuiescence`, and restore consistency gates MUST remain available. Application guard is not an automatic quiescence lease or proof of DB snapshot consistency. No existing Contract-v1 REPAIR limitation is declared solved without E2E evidence.
- System Health/Dashboard may consume maintenance readiness/status information; do not create separate owner or broaden Seven-Tab WU6 to a UI redesign.
- Required acceptance: other installation unaffected, DB writes from COPOT blocked, authorized lifecycle mutation controlled, queue/in-flight semantics, fail-closed unknown/external writer condition, crash/restart/unlock/retry/restore safety, existing quiescence regression, and verified target-specific backup/rollback before disposable runtime mutation.

Planning action: BOUNDED CONTRACT RECONCILIATION NEXT / NO SOURCE, RUNTIME, DATABASE, OR DEPLOYMENT AUTHORIZATION FROM WORKPLAN ALONE

### Maintenance Management & Installation Backup / Portability
Class: FUTURE DEDICATED WORKSTREAM / DEFERRED CONCEPT
Status: REGISTERED / FUTURE / NOT CONTRACT-PROMOTED / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_installation_scoped_maintenance_management_concept.md` [PRIMARY]

Relations:
- Consumes accepted minimum WU6 maintenance foundation, but full implementation is NOT a hard dependency of v3 bootstrap or Seven-Tab WU6 closure.
- Owns extended manual modes, delegated permission (only when multi-user capability exists), full queue/scheduling/health projections and maintenance operator workflow.
- Owns exportable single-ZIP installation backup (APP_ROOT, PUBLIC_ROOT, media/storage, logical DB, installation metadata) with secure secrets, integrity manifest, compatibility, verified restore/import and migration portability; distinct from package ZIP and internal recovery snapshots.
- Future scheduled backup additionally requires access control, retention, integrity verification, failure and restoration evidence; not delivered today.
- Native table locks, DB-scoped privilege isolation and dedicated DB instance remain future options. Existing global MariaDB quiescence capability is retained, not retired.

Planning action: KEEP DEFERRED / SEPARATE FUTURE CONTRACT AND WORKSTREAM ACCEPTANCE REQUIRED

#### Patch delivery, capability-state and removal semantics — future lifecycle gap inventory
Class: ADAPTIVE PACKAGE LIFECYCLE / BACKUP & RECOVERY FOLLOW-ON
Status: FUTURE / OPEN DESIGN / NOT IMPLEMENTATION-AUTHORIZED

- Treat patch artifact as immutable delivery/application unit that may contain multiple changes to capabilities owned by its declared package target. Do not infer a separate lifecycle engine for corrective, feature, retirement, security or structural intents.
- Distinguish dependency on a package revision from dependency on a capability/contract/provider revision; installed/discovered is not necessarily active/selected. Explicitly evaluate conflicts, supersession, target inventory and committed state per runtime.
- Separate full-runtime checkpoint restore, selective patch uninstall, capability deactivation and corrective/compensating transitions. Selective removal is fail-closed unless dependency closure, shared-file ownership, migrations, durable state and reversibility are proven. Never retain arbitrary files as an implicit orphaned capability provider.
- Retirement separates deprecation/replacement, execution-path retirement and physical removal; downstream consumers, persisted data, recovery and provider transfer must be checked before destructive cleanup.
- Open: patch dependency graph/ledger, incremental vs cumulative delivery, safe older prerequisite application on newer runtime, transactional sequencing, forward compatibility, migration reversibility, cross-owner constraints and release labeling. No patch engine, schema extension, or new authorization follows from this inventory.


### Installer Distribution, Automatic Root Deployment & Retained Sources
Class: DEFERRED CONCEPT
Status: MATERIALIZED / DEFERRED / NOT PROMOTED

Sources:
- `concepts/copot_installer_automatic_root_deployment_retained_sources_concept.md` [PRIMARY]

Relations:
- Future installer auto-deploys private APP_ROOT and public document root from a distributable installer package; retained sources and later repair support are subordinate parts of this installer architecture.
- Related to MR.1 Installation Refinement, Webcore Deployment & Portability, Backup & Recovery and System Health; distinct from Seven-Tab WU5.

Planning action: KEEP DEFERRED / NO ADOPTION OR IMPLEMENTATION AUTHORITY

### Versioning Management
Class: DEFERRED CONCEPT
Status: MATERIALIZED / DEFERRED / NOT PROMOTED

Sources:
- `concepts/copot_versioning_management_concept.md` [PRIMARY]

Relations:
- Future consistent version identity, intended release scope, and stable Git release development baseline; possible initiation around v0.14.0 or v0.15.0 is not locked.
- Accepted checkpoints remain distinct and continue to exist.

Planning action: KEEP DEFERRED / NO RELEASE, TAG OR IMPLEMENTATION AUTHORITY

#### Patch-based development and version semantics — future reconciliation
Class: VERSIONING MANAGEMENT SUBSCOPE / PLANNING-ONLY
Status: INVENTORIED / SEMANTICS OPEN / NOT IMPLEMENTATION-AUTHORIZED

- Preserve immutable accepted checkpoint/release baselines while exploring independently developed, validated and published patch artifacts; a workstream, patch artifact, capability revision, release sequence and installed runtime state are distinct identities. No mandatory development patch number is adopted.
- A published patch artifact is immutable; later fixes to earlier behavior are new corrective/superseding transitions, never silent replacement of a published artifact.
- Runtime labels such as v0.13.7 must not imply that every numbered patch is installed unless that is explicitly guaranteed by the eventual release-version contract; separately track effective installed/active capabilities and committed patch evidence.
- Candidate delivery forms are incremental, cumulative and dependency-resolving transitions. A later-numbered patch with a missing prerequisite requires compatibility proof and dependency satisfaction, not automatic downgrade or retroactive runtime-version change.
- Patch change intents (corrective, introduction, evolution, retirement, structural, compatibility and security) may coexist within one owner-bounded artifact; intents are descriptions, not separate mutation engines.
- Open: numbering/publication semantics, cumulative target-state definition, historical patch supersession, patch dependency admission and ledger, atomic application, compatibility at newer target states, and release acceptance. Distinguish minimum engineering patch workflow from advanced runtime patch management.
- Timing: revisit once current Package Lifecycle foundation is accepted, as a candidate workflow supporting later pre-v0.14.0 development; no automatic expansion of Seven-Tab scope, no new workstream and no change to the five-stage dependency sequence.

Relations: Adaptive Package Lifecycle; Package Lifecycle & Migration Foundation; Project Logging checkpoints; Backup & Recovery.




### Project Logging — Project State Checkpoint & Activity Journal Rollover
Class: PROJECT GOVERNANCE CAPABILITY / GOVERNANCE-PROMOTED ACTIVITY JOURNAL POLICY
Status: UNIFIED CONCEPT RECONCILED / TWO INDEPENDENT OBJECT LIFECYCLES / ACTIVITY JOURNAL POLICY PROMOTED / ROLLOVER OPERATIONALLY DEMONSTRATED

Sources:
- `concepts/copot_checkpoint_devlog_project_governance_trial_concept.md` [PRIMARY; UNIFIED CONCEPT RECONCILED 2026-10-11]

Conceptual scope and boundaries:
- Project Logging is proposed as a governance-regulated project capability, a peer of Documentation in responsibility rather than a replacement for authoritative contracts or repository history.
- Object 1 — Project State: a Checkpoint is an annotated Git tag targeting an exact accepted, sufficiently validated stable project baseline commit usable for subsequent workflow. Optional validated installation/distribution package is a separate artifact, not an automatic tag output.
- Object 2 — Activity Journal: `docs/devlog.md` is a running event record and chronological anchor. A Rollover is a separately named annotated Git tag targeting the exact committed complete journal state before cleanup; fresh-cycle journal content is committed normally, with previous tag reference. Git history preserves the earlier journal without needing an additional exported file.
- Both object types share the Git commit timeline but have distinct save-point names, validity checks, retention semantics, and authorization gates. Neither is a release tag by default.
- NRP, Handoff, acceptance, and closure are separate governance/project-flow domains. Their evidence or state may inform save-point validity, but none automatically triggers, defines, authorizes, or proves either Checkpoint or Rollover.
- Checkpoint is not automatically made at NRP. Activity Journal rollover is triggered for evaluation by material retrieval/usability burden or transition into an actually defined Workstream; a trigger does not authorize Git operations. Trial parameters: monitor approximately every 50 lines, provisionally evaluate around 150 lines (100–200 reference only), and consider content size/density, retrieval/append cost, and human usability. A new cycle preferably begins with its first meaningful event after authorized rollover; no automatic NRP/Handoff/WU-closure trigger or fixed-volume limit.
- A Git annotated tag references a commit snapshot, not a file and not an automatic package ZIP. Tag creation, cleanup, artifact packaging, publication, and application release are distinct separately authorized actions.
- Historical tags must be stable and verifiable; no automatic tag replacement, history rewrite, or discard of uncommitted/unverified journal content.
- Current journal writes via GitHub connector are committed on `main`, despite the older Concept's trial-era description. This does not itself grant standing tag/cleanup authorization or promote the Concept into the Rule.

Open decisions:
- Exact checkpoint acceptance/validation gates for different project baseline types.
- Empirical fair-volume optimization and semantic historical retrieval usability; independent recheck of annotated-tag object type and subsequent-commit historical recall evidence where required.
- Checkpoint validity policy remains independently open. Fair-volume optimization and historical retrieval usability remain continuous refinement topics, not automatic promotion blockers.

First rollover evidence: 2026-10-11 approved trial, tag `journal/rollover-20261011-755bfe7`, pre-cleanup commit `755bfe7edbacb948ddada6f950f544513222559e`; new cycle and pre-cleanup retrieval PASS, subsequent-commit historical recall reported PASS in 2026-10-14 evaluation. Historical initial `PENDING` event is retained as originally recorded.

Planning action: ACTIVITY JOURNAL GOVERNANCE PROMOTED / RETAIN CONCEPT AND ROLLOVER EVIDENCE AS PROVENANCE / CONTINUE CHECKPOINT VALIDITY POLICY SEPARATELY / NO STANDING FUTURE TAG OR CLEANUP AUTHORITY

### Lifecycle Net-Zero Retirement & Safe-Retry Capability Continuation
Class: DURABLE CONCEPT / IMPLEMENTATION-STATE INVENTORY
Status: MATERIALIZED / BACKEND CAPABILITIES IMPLEMENTED IN PART /
PRODUCTIZATION NOT ACCEPTED

Sources:
- `concepts/copot_lifecycle_net_zero_retirement_safe_retry_capability_concept.md` [PRIMARY]

Relations:
- net-zero retirement amendment authority remains
  `docs/28_package_lifecycle_migration_foundation_contract.md`;
- Backup & Recovery/quiescence retains existing separate authority under
  `docs/31_backup_recovery_foundation_contract.md`;
- net-zero retirement, protected pre-mutation retry, and designated migration
  connection are durable backend capabilities; general recovery UX and
  automated disposition routing are not established as accepted product
  capabilities;
- corrective forward-update bootstrap authority is separate next-session
  Handoff material, not part of this saved Concept.

Authority:
- `docs/28_package_lifecycle_migration_foundation_contract.md` (net-zero
  amendment only);
- `docs/31_backup_recovery_foundation_contract.md` (existing recovery
  dependency).

Planning action: RETAIN AS CONCEPT / RECORD IMPLEMENTED VS NOT DELIVERED /
NO PRE-CONTRACT OR DEDICATED CONTRACT NOW / NO IMPLEMENTATION AUTHORIZATION


### Webcore Product Completeness & Stabilization
Class: CORRECTIVE WEBCORE PRODUCT-COMPLETENESS / STABILIZATION WORKSTREAM
Status: PROMOTED / CONTRACT LOCKED / COMPLETE / ACCEPTED / CLOSED

Sources:
- `concepts/copot_site_color_scheme_concept.md` [PRIMARY GIT CONCEPT]
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md` [ARCHITECTURE LINEAGE]

Pre-contract:
- `precontracts/webcore_product_completeness_stabilization_precontract.md`

Pre-contract status: PROMOTED / HISTORICAL PROVENANCE

Authority:
- `docs/49_webcore_product_completeness_stabilization_contract.md` [PRIMARY WORKSTREAM AUTHORITY]
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md` [WU4 AUTHORITY]
- `docs/57_webcore_content_product_completeness_contract.md` [WU5 AUTHORITY]
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
- dedicated Webcore Site Settings projection is delivered and accepted under
  WU4; future capability-gated projection architecture remains planning-only;
- Webcore Content Admin requires bounded product-completeness correction;
- localization has duplicated product-facing projections;
- Site Name, Tagline, Logo, and Favicon operator management is split across
  historical/current projections and requires WU4 reconciliation;
- zero-optional product operability is incomplete until the required baseline
  operator surfaces are corrected.

Preserved negative findings:
- no major Webcore architecture conflict was found;
- no schema/settings migration is currently required;
- existing Core settings and storage authorities should be preserved;
- Redirect Core Admin CRUD is not currently required;
- Webcore Content authority and Built-in Public View are already delivered;
- the existing Core Content Admin fallback is not yet product-complete;
- System Manager is not to be wholesale reopened.

Promoted WU topology and current disposition:
1. WU1 — Webcore Completeness Contract & Scope Reconciliation — COMPLETE /
   CONTRACT LOCKED
2. WU2 — Core Media Admin Baseline — COMPLETE / ACCEPTED / INTEGRATED
3. WU3 — Core Primary Navigation Admin Baseline — COMPLETE / ACCEPTED /
   INTEGRATED / CLOSED
4. WU4 — Webcore Site Settings & Appearance Consolidation — COMPLETE / ACCEPTED /
   CLOSED / CONTRACT PROMOTED / CONTRACT LOCKED; Batches 1–2 COMPLETE / CLOSED;
   Batch 3 ACCEPTED / COMPLETE / CLOSED; Batch 4 COMPLETE / ACCEPTED / CLOSED
   after technical and human/product acceptance
5. WU5 — Webcore Content Product Completeness — COMPLETE / ACCEPTED / CLOSED
6. WU6 — Zero-Optional Product Acceptance — COMPLETE / ACCEPTED / CLOSED
7. WU7 — Stabilization & v0.14.0 Readiness Closure — COMPLETE / ACCEPTED / CLOSED

WU4 implementation authority:
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md` is the
  dedicated WU4 implementation authority;
- WU4 Batch 4 — Cross-Surface Acceptance & WU4 Closure — is COMPLETE / ACCEPTED /
  CLOSED after WU5 acceptance and separate technical and human/product review;
- this Workplan entry does not authorize WU4 implementation.

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

Closure action: COMPLETE / ACCEPTED / CLOSED / MAIN-ONLY / NO-OP for current
WU7 execution. Accepted WU2–WU6 evidence remains sufficient; no additional
runtime or broad historical regression rerun was required because no concrete
regression signal or changed dependency was found. The historical downstream
planning target remains Bundled Module Refinement Preparation & Reconciliation,
which is planning topology only and requires separate authorization; the
current pre-v0.14.0 sequence and first target are registered below.

### Site Settings Future Capability Architecture
Class: CONCEPT / FUTURE CAPABILITY-GATED PRODUCT PROJECTION
Status: FUTURE / PRE-V0.14.0 PLATFORM TARGET / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_site_settings_future_capability_architecture_concept.md`
  [PRIMARY GIT CONCEPT]
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md`
  [CURRENT WU4 AUTHORITY]
- `docs/50_webcore_site_settings_information_architecture_clarification.md`
  [HISTORICAL IA LINEAGE]
- `docs/55_webcore_site_settings_system_operational_projection_contract.md`
  [SYSTEM PROJECTION LINEAGE]
- `docs/56_webcore_site_settings_modules_operational_projection_contract.md`
  [MODULES PROJECTION LINEAGE]
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`
  [DATABASE AUTHORITY]
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`
  [ADOPTION AUTHORITY]

Current delivered truth for the established Site Settings baseline includes
Site Identity, System, Security, Modules, Redirects, and System Health. The
Security projection is delivered through WU2 Slices 1–7 on `main` at
`ab2ef3ab254c04513128d9385fb2c57662e52ad9`, with focused Security acceptance
passing 267 assertions and lifecycle/compatibility reconciliation passing 191
assertions with no reconciliation delta required. The Redirects projection
defined by the Seven-Tab Site Settings Completion & Hardening workstream is
implemented and accepted under its WU4 contract. The WU3 System Email
capability baseline is implemented, accepted, and durable at
`fc4bf7754005b85ad890be32749f9c17f218c4e9`; its Site Settings projection is
downstream WU5 work. WU5 remains future work under the accepted contract
boundary, is no longer blocked by WU3, and has not started in this interaction.
Future Site Settings
projection may cover Site / Site Identity, System, Modules, Redirects, System
Health, Security, Email, and Database without asserting that all eight are
current visible areas or must exist simultaneously.

Pre-v0.14.0 target projection: a fully operational and hardened seven-tab
Site Settings surface with Site Identity, System, Security, Email, Modules,
Redirects, and System Health. This is a future target, not current delivered
truth. Database lifecycle and compatibility remain an underlying capability
and are not an eighth Site Settings tab.

Security and Email are required platform capabilities for this target. System
Email must provide at least the platform capability required by Auth/recovery
and later `Users & Access` consumption. Identity/Auth retains authentication
mechanics and recovery semantics; System Email owns outbound transport and
sender identity; Security owns site/system policy and enforcement; Redirects
remains Webcore-native. Existing Site Identity, System, Modules, and System
Health are to be hardened and revalidated rather than unnecessarily rebuilt.

Relations:
- preserves WU4 delivery and singular underlying authority;
- references the canonical Security / Email ownership Concept rather than
  duplicating those capability definitions;
- Database remains a product projection over existing schema, lifecycle, and
  compatibility authority; and
- no automatic implementation sequencing or capability delivery is implied.

Dependency: HARD → Database Adoption Exact-Match Compatibility Reconciliation.

Planning significance: completion of this seven-tab platform target is a HARD
prerequisite before Bundled Module Refinement.

Planning action: KEEP FUTURE / CAPABILITY-GATED / NO AUTOMATIC SEQUENCING

### Database Adoption Exact-Match Compatibility Reconciliation
Class: FUTURE CONTRACT RECONCILIATION / DATABASE COMPATIBILITY
Status: WU1–WU6 COMPLETE / ACCEPTED / INTEGRATED; DATABASE ADOPTION
COMPATIBILITY RECONCILIATION COMPLETE AND CLOSED

Sources:
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`
  [EXACT-MATCH / LEGACY RECONCILIATION AUTHORITY]
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`
  [CURRENT ADOPT AUTHORITY]
- `concepts/copot_site_settings_future_capability_architecture_concept.md`
  [PLANNING MATERIALIZATION]

Pre-contract:
- `precontracts/database_lifecycle_adoption_compatibility_reconciliation_precontract.md`

Pre-contract status: PROMOTED / HISTORICAL PROVENANCE

Contract Proposal:
- `precontracts/database_lifecycle_adoption_compatibility_reconciliation_contract_proposal.md`

Contract Proposal status: CONTRACT PROPOSAL / PROMOTED / HISTORICAL PROVENANCE /
NOT AUTHORITATIVE / NOT IMPLEMENTATION-AUTHORIZED

Authority:
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`

Promotion status: COMPLETE / AUTHORITATIVE TARGETS RECONCILED
Implementation status: WU1–WU6 COMPLETE / ACCEPTED / INTEGRATED on `main` at
`5748a9cd1cb199ea07c6c7776b774f68722ae16c`. WU6 technical acceptance evidence
is complete; environment/toolchain limitations and unrelated historical fixture
findings remain recorded in the evidence history. No unresolved implementation
blocker remains within this workstream.
Post-promotion implementation-delta audit: COMPLETE
Work Unit topology: DERIVED / MATERIALIZED
Work Unit count: 6
Workstream closure: COMPLETE AND CLOSED
Next target: NONE within this workstream. The next pre-v0.14.0 phase remains a
separate future planning target and is not authorized by this entry.

The promoted reconciliation preserves legacy exact-match/current Adopt
semantics while establishing target-relative requirement-driven compatibility.
Adoption remains the broader compatibility-establishment workflow and Adopt
remains the terminal installer/finalization intent after compatibility is
established.

This item does not create a new lifecycle operation, installer intent, status
family, engine, or generalized conflict procedure. NRP evaluation remains a
separate GPT/user-side decision and is not changed by this planning update.

Work Unit topology:

#### WU1 — Target Requirement Contract & Representation
Purpose:
- extend existing package/release metadata so the target can authoritatively
  declare database/schema/capability requirements required for target-relative
  compatibility;
- reuse current PackageContract, PackageManifest, runtime-requirement,
  source-compatibility, and migration-declaration machinery where appropriate;
- preserve current runtime compatibility and lifecycle authority semantics.

Dependency: NONE

Boundary: requirement representation only; no generalized compatibility
evaluation or Adoption orchestration.

#### WU2 — Target-Relative Compatibility Evaluation & Adoption Readiness
Purpose:
- evaluate Satisfied Target Requirements;
- identify Compatible Extra State and positively classified Requirement Gaps;
- distinguish unknown, ambiguous, contradictory, unsafe, and unsupported state;
- support Compatibility Re-Proof;
- produce derived Adoption Readiness and machine-readable compatibility results;
- preserve exact-match as a valid fast path.

Dependency: HARD → WU1

Boundary: no mutation authority, new lifecycle operation, or durable Adoption
lifecycle-state family.

#### WU3 — Legacy Boundary & Route Classification Reconciliation
Purpose:
- ensure database non-exactness alone does not force Legacy Reconciliation;
- route positively classified Requirement Gaps toward normal authorized
  lifecycle resolution;
- preserve Legacy Reconciliation for genuinely legacy, uncommitted,
  unprovable, provenance, filesystem-drift, or broader convergence cases;
- preserve historical exact-match Adoption behavior;
- establish deterministic classification for later Route A/Route B
  orchestration.

Dependency: HARD → WU2

Boundary: preserve existing Legacy Reconciliation authority and applicable
canonical/exact-match paths.

#### WU4 — Adoption Orchestration, Route B & Composite Resolution
Purpose:
- introduce bounded Adoption orchestration around existing lifecycle
  operations;
- support Route B only where target context can legitimately invoke existing
  lifecycle authority;
- preserve underlying lifecycle-operation identity, mutation authority,
  migration authorization, recovery binding, mutex, and Runtime Handoff
  exclusion;
- support Composite Resolution as orchestration of 0..N separately
  authorized operations;
- suspend Adoption on underlying failure and perform fresh compatibility
  evaluation after material resolution steps;
- add orchestration/audit identity only where materially required, without a
  second mutation-operation identity.

Dependency: HARD → WU2 + WU3

Boundary: no new lifecycle engine, migration engine, mutation operation, or
ownership model.

#### WU5 — Installer & Lifecycle Result Integration
Purpose:
- allow Installer/Adoption flow to consume generalized compatibility and
  orchestration results;
- preserve terminal Installer Adopt as non-mutating;
- preserve installation identity, namespace, Administrator/User/Site state,
  and current Adopt safety boundaries;
- expose valid continuation/next action without transferring migration or
  lifecycle authority into Installer.

Dependency: HARD → WU4

Boundary: no broader Installer Refinement I UX or unrelated Installer redesign.

#### WU6 — Cross-Lifecycle Acceptance & Closure
Purpose: validate and close the complete Adoption compatibility implementation
across historical exact-match, Compatible Extra State, Requirement Gaps,
Route A, Route B, Composite Resolution, failure/suspension/recovery/fresh
re-proof, Legacy Reconciliation, terminal Installer Adopt, Runtime Handoff
exclusion/conflict behavior, Case A/B/C, migration authorization,
recovery/retry, documentation consistency, Workplan reconciliation, and NRP
evaluation.

Dependency: HARD → WU1–WU5

Implementation status: WU1–WU6 COMPLETE / ACCEPTED / INTEGRATED; technical
acceptance evidence complete; workstream closure recorded.

Dependency: NONE within the current five-step pre-v0.14.0 sequence.

Planning action: TOPOLOGY MATERIALIZED / WU1–WU6 COMPLETE / ACCEPTED / CLOSED /
INTEGRATED ON MAIN / NO WU7 IMPLIED

Historical planning checkpoint superseded by the completed WU1–WU5 delivery:
the preceding WU1-next-target and implementation-not-authorized wording is
retained as provenance of the original promoted topology, not as current state.

WU6 closure evidence:
- WU1–WU5 focused contract, compatibility, classification, orchestration, and
  Installer integration suites passed;
- the composed WU6 acceptance test passed 10 assertions across exact-match,
  generalized compatible extra state, Route B, fresh re-proof, Legacy
  Reconciliation separation, identity drift, and unknown-state fail-closed
  behavior;
- Route A and Case A/B/C remain owned by the unchanged Package Lifecycle
  migration/classification boundary; prior accepted `package_lifecycle_wu4.php`
  evidence remains valid, while the current PDO-backed rerun is environment-
  limited;
- Runtime Handoff exclusion is reconciled to the unchanged
  `RuntimeRegistry`/`RuntimeTransitionCoordinator` mutex and compatibility
  boundary; accepted `multi_installation_wu4_runtime_isolation.php` evidence
  passed 17 assertions, including stale/incompatible shared-state blocking;
- retry/recovery authority is reconciled to the unchanged Package Lifecycle
  and Backup & Recovery owners; accepted retry-finalization and C2 recovery
  evidence remains valid, while the current PDO-backed retry E2E rerun is
  environment-limited;
- no new mutation, migration, ownership, Installer, or lifecycle authority was
  introduced by WU6.

### Pre-v0.14.0 Platform and Refinement Sequence
Class: FUTURE EXECUTION SEQUENCE / DEPENDENCY REGISTER
Status: PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

The current planned pre-v0.14.0 path is ordered by hard dependency:

1. Database Lifecycle — Adoption Compatibility Reconciliation;
2. Seven-Tab Site Settings Completion & Hardening;
3. Installer Refinement I — Webcore & Adoption Integration;
4. Bundled Module Refinement; and
5. Installer Refinement II — Bundled Module Installation Integration.

These are high-level planning identities and dependency relationships only.
The Database Adoption reconciliation topology is materialized as six planning
Work Units above; no Work Unit is implementation-authorized by this entry.
Completion of all five steps contributes to the intended path toward final
v0.14.0 readiness, while formal release closure remains later, separate, and
unauthorized here.

### Installer Refinement I — Webcore & Adoption Integration
Class: FUTURE INSTALLER REFINEMENT / PLATFORM INTEGRATION
Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`
  [ADOPTION AUTHORITY]
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`
  [DATABASE / INSTALLER AUTHORITY]
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md`
  [CURRENT WU4 AUTHORITY]
- `concepts/copot_site_settings_future_capability_architecture_concept.md`
  [PLATFORM TARGET]
- `copot_consolidated_refinement_concepts_260810_020950.md`
  [HISTORICAL INSTALLER REFINEMENT INPUT]

This identity is distinct from the closed MR.1 Installation Refinement and
does not reopen MR.1. Its purpose is to refine Installer behavior after
Database Adoption semantics and the seven-tab Webcore platform capabilities
are stable.

Scope direction:
- Fresh / Coexist / Adopt behavior against the reconciled Adoption model;
- Adoption compatibility evaluation and operator flow;
- requirement-resolution orchestration only through existing authorized
  lifecycle machinery, followed by compatibility re-proof before terminal
  Adopt/finalization;
- administrator identity/email behavior aligned with current Auth capability;
- optional initial System Email configuration and applicable initial
  Security/platform settings through their owning capabilities; and
- truthful Review & Install and Installation Result behavior.

Installer remains a consumer/orchestrator and does not own Auth, Security,
Email, Settings, database migration, or lifecycle semantics. This phase does
not depend on final Bundled Module refinement.

Dependency: HARD → Seven-Tab Site Settings Completion & Hardening.

Planning significance: proves Webcore platform plus Adoption integration
before Bundled Module Refinement begins.

Planning action: KEEP FUTURE / SEPARATE FROM MR.1 / NO IMPLEMENTATION AUTHORIZATION

### Installer Refinement II — Bundled Module Installation Integration
Class: FUTURE INSTALLER REFINEMENT / BUNDLED MODULE INTEGRATION
Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `workplan.md` — Dedicated Future Bundled Module Refinement Workstreams
  [CURRENT PLANNING REGISTRY]
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`
  [INSTALLER / MODULE AUTHORITY]
- `concepts/copot_site_settings_future_capability_architecture_concept.md`
  [PLATFORM TARGET]

This identity is distinct from Installer Refinement I and begins only after
the retained Bundled Module contracts and behavior are stabilized.

Scope direction may include, as supported by finalized Module contracts:
- package discovery/state required during installation;
- optional/default Module selection or installation policy;
- dependency/conflict handling;
- Module-owned schema materialization through owning Module lifecycle;
- install/enable sequencing;
- Review & Install representation of Module actions; and
- Module lifecycle failure/retry/result behavior and valid Fresh / Coexist /
  Adopt end states for the finalized Bundled Module ecosystem.

Dependency: HARD → Bundled Module Refinement.

Installer Refinement II must not automatically reopen accepted Webcore or
Adoption behavior from Installer Refinement I. Reopening requires concrete
Bundled Module dependency, regression, contract conflict, or other material
evidence.

Planning action: KEEP FUTURE / SEPARATE FROM INSTALLER REFINEMENT I /
NO IMPLEMENTATION AUTHORIZATION

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
- `copot_package_lifecycle_migration_concept_260804_092611.md` — Webcore Clean Install Is Part of Webcore Lifecycle [PRIMARY]
- `copot_release_update_migration_concept_260803_171900.md` — compressed-package/fresh-install direction [HISTORICAL]

Relations:
- Deferred ID: `DI-PACKAGE-LIFECYCLE-WU7-01`.

Planning action: KEEP DEFERRED

#### Bundled Module installer orchestration boundary — future clarification
Class: EXISTING INSTALLER REFINEMENT II SUBSCOPE / PLANNING-ONLY
Status: INVENTORIED / CONTRACT RECONCILIATION REQUIRED

- Installer may orchestrate multiple separately identified Module package operations and dependency sequencing; orchestration does not turn them into a mixed or multi-module artifact.
- Per-package admission, ownership, migration, failure/retry, recovery and commit remain governed by their owning lifecycle. Cross-package atomicity is unresolved, not assumed.
- Must preserve Installer Refinement II's hard dependency on Bundled Module Refinement and accepted Webcore/Adoption boundaries.


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

#### Module package ownership and UI routing — future reconciliation
Class: MODULE PACKAGE IDENTITY & CAPABILITY PROVIDER SUBSCOPE / PLANNING-ONLY
Status: INVENTORIED / EXISTING CONTRACT RECONCILIATION REQUIRED

- Existing docs/29 baseline is one package -> one technical module. Proposed forward strict boundary: one package has one effective lifecycle owner; Webcore-owned internal components may travel inside Webcore packages, while Bundled and Custom Modules each require their own Module package. Reject a package containing Webcore plus independently owned Module, or multiple separately owned Modules.
- Cross-module and Module-to-Webcore capability dependencies are permitted without bundling another owner's mutable source. Package containment, capability dependency, and operational effect are separate.
- Shared Package Lifecycle infrastructure remains common; Webcore- and Module-specific target policies/admission enforce ownership without a second mutation/recovery engine.
- System UI is proposed primary for Webcore packages; Modules UI primary for Bundled/Custom Module packages. Wrong-surface intake should be classified and rejected/redirected or use accepted fallback, never silently run under the wrong owner. MR.2 WU3 remains closed; future UX routing is to be reconciled, not retroactively rewritten.
- Further work: reconcile against docs/29 and docs/28, including historical multi-module deferral and provider selection, and retain Installer Refinement II's distinct multi-package orchestration boundary. No existing contract amendment or implementation authority is conferred.


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

#### Structural codebase consolidation and technical debt — lineage reconciliation
Class: HISTORICAL REFINEMENT LINEAGE / FUTURE AUDIT CANDIDATE
Status: INVENTORIED / EXECUTION OWNER UNRESOLVED / NOT IMPLEMENTATION-AUTHORIZED

- Preserve previously discussed structural consolidation scope: unused methods, dead/unreachable code, obsolete adapters/classes, duplicate implementations, file/class merge-or-split decisions, folder organization, stale references, tests/tooling/documentation residue and architecture-aligned physical module relocation/retirement.
- Core Modules & Dashboard Refinement is DECOMPOSED / PROVENANCE and explicitly must not be promoted as one execution workstream. Map future candidate cleanup to actual owning subsystems and dedicated refinement entries; do not use lineage alone to authorize cross-subsystem cleanup.
- Module-specific physical residue belongs to Bundled Module Refinement Preparation; broader Webcore/lifecycle/installer cleanup requires separate owner and dependency audit. Preserve source authority, package/recovery safety, behavior and regression equivalence.
- Historical August structural consolidation direction is retained as planning lineage, not proof that an active dedicated cleanup workstream already exists. Do not create a duplicate workstream before coverage reconciliation.


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

### Trusted Developer & Package Publisher Trust
Class: DEFERRED ECOSYSTEM / PACKAGE TRUST CONCEPT
Status: DURABLE PLANNING REGISTRATION / DEFERRED / SEMANTIC PARTIAL / NOT CONTRACT-PROMOTED / TRUST ENFORCEMENT NOT IMPLEMENTED

Sources:
- `concepts/copot_adaptive_package_lifecycle_independent_domain_compatibility_concept.md` [PACKAGE TRUST AND EXECUTION-GATE SEPARATION]
- `concepts/copot_module_package_identity_and_capability_provider_concept.md` [PUBLISHER/PROVENANCE IDENTITY AND DEVELOPER-FACING GUIDANCE]

Relations:
- Trusted Developer is distinct from package content integrity, package identity, release identity, runtime compatibility, and operator execution authorization.
- Existing ZIP manifest/hash validation is not publisher authenticity proof, nor a Trusted Developer registry, signature trust anchor, or verified authorization of execution.
- Future scope may address developer/publisher identity, trust establishment, package signing/authenticity, trusted-key lifecycle, revocation, and policy tying publisher trust to package consumption. These are deferred semantic directions; no particular provider, signature scheme, online service, or approval policy is locked.
- The accepted offline-only manifest-v3 Canonical Target Inventory contract, `docs/28`, `docs/29`, and shared operator-input contract remain unchanged. A trusted developer does not automatically gain arbitrary migration, file removal, or runtime modification authority.
- Related but separate: Official Remote Update / Distribution Ecosystem and Module Package Identity & Capability Provider. Do not subsume Trusted Developer into either without future explicit reconciliation.
- Current source inspection has not established any implemented Trusted Developer registry, verified publisher signature enforcement, trust-anchor management, or revocation flow. Existing general package integrity is only reusable foundation, not partial trust enforcement.

Planning action: RETAIN INDEPENDENT DEFERRED CONCEPT ENTRY / NO DEDICATED PRECONTRACT OR CONTRACT NOW / NO IMPLEMENTATION AUTHORIZATION

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
Class: RELEASE / COMPATIBILITY POLICY PROVENANCE
Status: RETAINED / EVOLVED PLANNING DIRECTION / NOT A STANDALONE OPEN QUESTION

Sources:
- `COPOT_Webcore_Module_Database_Table_Ownership_Separation_Concept_260816_194431.md` — Compatibility Reconciliation [PRIMARY]

Evolved planning direction:
- supported historical reach is derived per target release from target
  requirements plus available proven transition paths;
- no universal historical database window or global minimum DB version is
  required;
- a bridge release/version is required only when actual release evidence
  proves a direct transition unsafe or insufficient; and
- unsupported source states fail closed.

The historical identity and source provenance remain intact. Concrete
release-support boundaries emerge from future release-specific
requirement-compatibility analysis rather than this standalone entry.

Planning action: KEEP PROVENANCE / NO AUTOMATIC SEQUENCING

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

### Centralized Resource Protection — Layered Resource Policy
Class: FUTURE ARCHITECTURE CONCEPT / PARTIAL FIRST APPLICATION
Status: CONCEPT MATERIALIZED / FRAMEWORK DEFERRED / HTACCESS-FIRST WITHIN EXISTING CONTROLLED DEVIATION

Sources:
- `concepts/copot_centralized_resource_protection_layered_resource_policy_concept.md` [PRIMARY CONCEPT]

Architecture direction:
- Two policy layers: `Global Resource Policy` (COPOT runtime-level non-overridable restriction) and `Scoped Resource Policy` (module/feature/capability/subsystem-level rules); compose into `Effective Resource Policy`.
- `Policy Scope` and `Resource Target` are independent: the target may be a specific file, folder, or other resource.
- Only policy is layered; layered ownership is NOT adopted. Historical `PACKAGE_OWNED` evidence never creates competing active ownership or overrides current mutation restrictions.
- Selective/partial capability consumption is supported, but no capability can bypass mandatory global prohibitions. Nonmutating declaration conflicts should be diagnosable without globally blocking runtime; prohibited mutation is rejected before side effects.
- First resource is exact `PUBLIC_ROOT/.htaccess`: effective owner `DEPLOYMENT_ENVIRONMENT`, COPOT-scope `ABSOLUTE_IMMUTABILITY`; read/inspect/validate/report remain allowed. Scope excludes external server administration and OS filesystem locks.
- In `CD-V3-HTACCESS-20261026-01`, only bounded shared-protection enforcement and corresponding manifest/planner/apply/recovery/installer admission integration are in current direction. Full generic framework, extra owners/policies, policy UI, and broad provenance/ownership refactor remain deferred.

Relations:
- `CD-V3-HTACCESS-20261026-01` [ACTIVE BOUNDED FIRST APPLICATION]
- `docs/28_package_lifecycle_migration_foundation_contract.md` [PACKAGE LIFECYCLE]
- `docs/31_backup_recovery_foundation_contract.md` [RECOVERY]
- `docs/32_webcore_deployment_portability_foundation_contract.md` [DEPLOYMENT]

Planning action: RETAIN FUTURE ARCHITECTURE CONCEPT / PARTIAL HTACCESS ENFORCEMENT THROUGH EXISTING DEVIATION ONLY / NO IMPLICIT IMPLEMENTATION OR CONTRACT AUTHORITY

### Controlled V3 Contract Deviation — `.htaccess` Ownership Transition
Class: ACTIVE CONTRACT DEVIATION REGISTER / BOUNDED TRANSITION
Status: USER-AUTHORIZED DIRECTION / BREACH OPEN / IMPLEMENTATION NOT YET VERIFIED
Deviation ID: `CD-V3-HTACCESS-20261026-01`
Decision recorded: 2026-10-26 19:02:01 WIB

Current governing contracts remain authoritative until formally reconciled; this entry records an explicit user-directed, narrowly bounded incompatibility to be resolved rather than treating old manifest-v3 artifacts as permanent compatibility obligations.

Authorized direction and affected invariants:
- Physical `PUBLIC_ROOT/.htaccess` becomes deployment-owned; Webcore retains ownership of required routing behavior, not the deployed file's local bytes.
- V3 full and partial packages must exclude `.htaccess` from delivered package-owned inventory and resulting package-owned target inventory/fingerprint. Full coverage continues to mean all resulting package-owned bytes are delivered.
- Fresh-install and package-update admission must verify compatible deployment routing/root/base-path behavior separately; invalid or unverified routing fails closed before mutation.
- Explicitly supersede the old-V3 artifact compatibility expectation for this transition. Historical ZIPs/manifests, fingerprints, and executed installation evidence remain immutable historical records, not automatically eligible under revised semantics.
- Known intentional breach until reconciliation: strict existing manifest-v3 grammar/payload inventory, builder's required `public/.htaccess`, package-owned target assumptions, and existing source-authority/package admission interpretation. Never silently reinterpret an old package as new or claim old/new identities interchangeable.

Required reconciliation and closure:
- Bounded technical contract delta must specify ownership/provisioning, minimum routing compatibility, full/partial behavior, legacy-artifact disposition, source-inventory authority, recovery isolation, and actual SDR acceptance consequences.
- Implementation-first is explicitly authorized as a controlled, recorded deviation: implement and test the bounded source/manifest/builder/installer/planner/recovery implications before normative contract amendment. Contract reconciliation and promotion follow verified implementation evidence; do not treat implementation as contract authority.
- Build a new independently identified package; verify fresh install, HTTP routing, full/partial integrity, recovery, and source-authority path before E4 eligibility is asserted.
- Deviation closes only after authoritative contract reconciliation, implementation evidence, and verified acceptance. Until then `R3-A E4` remains BLOCKED and `SourceInventoryReference=null` is preserved; no rollback of old artifact history, no silent integrity bypass.

Deferred boundary: historical `DI-PACKAGE-LIFECYCLE-HTACCESS-01` wording about COPOT-managed `.htaccess` directive edits/merge is superseded by COPOT-scope absolute immutability and must not be interpreted as deferred mutation permission. Independent external deployment administration remains outside COPOT authority. Baseline ownership/routing compatibility verification is not deferred.

Planning action: CONTROLLED DEVIATION OPEN / BOUNDED IMPLEMENTATION NEXT / CONTRACT RECONCILIATION AFTER VALIDATION / NO IMPLICIT E4 ADVANCEMENT

### R3-A E4 D1–D2 — Attempt-Aware Recovery Continuation
Class: ACTIVE BOUNDED EXECUTION CONTINUITY UNIT / ALTERNATIVE C
Status: IMPLEMENTATION PLAN RECORDED / NEW SESSION HANDOFF PENDING / E4 BLOCKED
Planning recorded: 2026-10-29 18:55:43 WIB
Continuity identity: `E4-D1-D2-ATTEMPT-RECOVERY-20261029`

This is ONE closure-tracked execution group spanning D1 and D2 slices, treated *like* a workstream for session continuity, ordering, evidence, and grouped closure only. It is NOT a new formal project workstream, Work Unit, or accepted canonical contract. Alternative C remains the parent objective. Controlled contract drift is recorded now; formal reconciliation/materialization of docs/28 and docs/31 is deferred until Alternative C completes. Source/test implementation, source publication, runtime disposition, recovery retry, contract amendment, and closure each retain their respective authorization gates.

Objective: correct the source-authority recovery request committed-lifecycle binding (D1), then safely dispose of the existing pre-capture orphan and permit a fresh attempt through a strictly attempt-aware source-authority recovery path (D2). Preserve all other lifecycle behaviors and immutable historical evidence.

Source anchor and D1 dependency:
- Repository `blackdjurix/copot`, existing feature branch `codex/e4-unified-source-authority` published HEAD `98ac7288696e18fe31cb6a6fdf8aa113a17a13bd`.
- D1 is an UNPUBLISHED local three-file diff in `C:\Git\copot-e4-unified-htaccess`: `app/Core/PackageLifecycleService.php`, `tests/committed_source_authority_recovery_binding.php`, `tests/committed_source_authority_runtime_orchestration.php`. Exact adjustment: source-authority capture request uses `$plan->committedLifecycleIdentity()` rather than `$this->authorityDeploymentIdentity`; unrelated deployment identity semantics unchanged.
- D1 focused suites reported PASS by Codex; old routing-test failure is NOT proven equivalent pre/post D1. Do not claim complete baseline regression or overwrite these three local files. Review/publish D1 separately before implementing D2 on a source that depends on it. A separate D2 branch/worktree may be created from the last verified published Correction C commit while D1 remains local; branch creation does not transfer uncommitted D1 changes.
- SDR `473e8df9_htaccess`: orphan `source-authority-reconciliation-sha256:4b9b75f51455ecd5bcf2d56f943c2e865be0a1471c9d52ac1458a16525867ab4`, phase PREPARING, cursor 0, no recovery identity/manifest/state, Guard inactive, staging missing. Leave untouched until a canonical disposition route is implemented and separately authorized.
- Trusted historical candidate ZIP `C:\Git\copot-e4-artifacts\copot-v0.13.0-v3-correction-c.zip`, SHA-256 `c77a031ca6cf4f1b861089538f825d7f1c3f09a1c2c321f455fd3c095c25daf0`; inventory 857/857 match only against pre-D1 source. Rebuild and verify a new candidate after accepted source changes before fresh E4 eligibility; do not reinterpret this ZIP as current D2 package evidence.

Bounded technical direction (Model B candidate, NOT formal contract acceptance):
- Canonical operation and plan identities remain deterministic for identical eligibility inputs; create cryptographically unique immutable `execution_attempt_identity` only at execution-attempt creation, NEVER inside read-only planning. Persist before recovery capture; resume reuses the persisted attempt. Source-authority new operations require attempt identity; historical records without it remain strictly readable and never silently upgraded.
- Attempt identity must bind active-operation save/clear, recovery derivation, manifest/lookup, scoped Guard/database evidence, admission, resume, and finalization. Current persisted record must be reread and verified terminal before clear; cross-attempt reuse must fail closed. Legacy non-source-authority recovery behavior is unchanged.
- Distinct terminal `PRE_CAPTURE_ABANDONED` belongs to Package Lifecycle, not Backup & Recovery, and never implies captured recovery, mutation permit, or WU6 readiness. A single service supports both attempt-aware and historical orphan eligibility. Legacy missing attempt is an observed absence, not a synthetic attempt identity. Immutable durable abandonment evidence, read-back, installation exclusivity, crash-idempotent terminalization, and canonical clearance are mandatory; no manual removal of active operation or retrospective RecoveryManifest.
- Existing `PreMutationSettlementService` recovery-READY invariant remains unchanged. `PUBLIC_ROOT/.htaccess` is deployment-owned and absolutely immutable within COPOT authority.

Grouped execution slices and gate evidence:
- D1 prerequisite: verify original three-file diff and old routing-test baseline equivalence; publish D1 only after separate authorization; preserve published source anchor.
- Slice 1 (attempt persistence): `LifecycleOperationRecord`, `LifecycleOperationStore`, execution boundary/planner. Test strict legacy/new serialization, deterministic plan stability, unique fresh attempts, immutable persisted attempt, attempt-aware save and terminal clear.
- Slice 2 (attempt-aware recovery): request, capture service, recovery identity/manifest/codec/artifact lookup, adapter, admission/resume/finalization. Test distinct recovery identities for repeated canonical plan, fail-closed cross-attempt Guard/DB/manifest bindings, explicit legacy compatibility, no ambiguous operation-only lookup.
- Slice 3 (pre-capture disposition, including historical orphan): dedicated request/evidence store/service, canonical lifecycle integration. Verify PREPARING/cursor-zero/no mutation/no recovery/no Guard/scoped-owner and unchanged installation/source/database/committed state under installation mutex; record actual staging availability; durable immutable evidence before terminal transition/clear, crash-idempotent retry; no synthetic history. The historical orphan is an eligible CANDIDATE only, not yet authorized for settlement.
- Slice 4 (verification and fault injection): focused positive/negative safety tests, evidence-publication, terminal-save, terminal-reread and clear-interruption windows, independent legacy normal recovery and `PreMutationSettlementService` regression. Isolated/source-only validation first; do not modify SDR in a source-validation slice.
- Slice 5 (E4 operational release gate): separately authorize targeted source advancement, preserve orphan and evidence until verified canonical abandonment, create fresh retained staging and fresh attempt, independently rebuild/verify V3 artifact and Gate-1 AUTHORITY_ONLY eligibility, authorize recovery preparation/capture, and only later separately authorize WU6/finalization/publication. Never infer that source test PASS authorizes runtime actions.

Grouped closure requirements: D1 source correction published and differential baseline accounted for; D2 attempt-aware identity and pre-capture abandonment implemented/published and regressions/fault-injection independently verified; old orphan canonically disposed with preserved immutable evidence; new attempt demonstrates isolated recovery capture and no cross-attempt reuse; applicable Alternative C E4 source-authority flow validated through separately authorized gates; runtime/source/DB/Guard and branch states reconciled; all intentional drift enumerated for post-Alternative-C contract reconciliation. Group status remains OPEN until each relevant gate has explicit accepted evidence; it cannot self-declare parent Alternative C, package lifecycle, checkpoint, NRP, or project workstream closure.

Deferred: global operation-ID redesign, unrelated Module/normal lifecycle attempt adoption, mass legacy manifest migration, broad retention UI/cleanup, public CLI terminology, and normative contract materialization before Alternative C closure.

Planning action: RETAIN ONE EXECUTION CONTINUITY UNIT / PRESERVE D1 LOCAL DIFF / D2 SLICES BOUNDED / CONTROLLED DRIFT OPEN / FORMAL WORKSTREAM NOT CREATED / E4 BLOCKED.

### Deployment-Aware `.htaccess` Package Update Reconciliation
Class: HISTORICAL DEFERRED ITEM / SUPERSEDED MUTATION PROPOSAL
Status: HISTORICAL IDENTITY RETAINED / COPOT-MANAGED HTACCESS MUTATION PROHIBITED

Deferred ID: `DI-PACKAGE-LIFECYCLE-HTACCESS-01`.

Sources:
- `docs/canonical_target_inventory_fingerprint_contract.md` [CURRENT FILE-STATE AUTHORITY]
- `docs/28_package_lifecycle_migration_foundation_contract.md` [PACKAGE LIFECYCLE]
- `docs/32_webcore_deployment_portability_foundation_contract.md` [SPLIT-ROOT DEPLOYMENT]

Historical proposal (retained for provenance, NOT actionable under current direction):
- The original deferred entry proposed COPOT-managed `.htaccess` directive additions/changes/removals, directive-level merge, and in-place reconciliation. Those mutation proposals are SUPERSEDED by the current absolute immutability decision.
- Future compatible scope may evaluate read-only routing verification, diagnostics, evidence boundaries, and handoff to independently authorized external deployment administrators. COPOT must not rewrite, rename, remove, restore, or merge physical `PUBLIC_ROOT/.htaccess` content.
- Preserve the identity of this Deferred Item until its final historical disposition is formally reconciled; do not reactivate it as implementation authorization.

Current boundary:
- No implementation, contract amendment, work-unit adoption, or authorization follows from this entry.
- Deferral does not waive actual-file integrity, source-inventory authority, or prerequisite checks for active R3-A E4 / Alternative C.
- Current corrected SDR `.htaccess` mismatch remains an independent operational reconciliation blocker; do not mark it accepted or fixed through this registry entry.

Planning action: RETAIN AS HISTORICAL DEFERRED ID / SUPERSEDED COPOT MUTATION PROPOSALS / ROUTING VERIFICATION REMAINS APPLICABLE

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
Status: FUTURE / PLANNING LINEAGE / SUBORDINATE TO WU4 CONTRACT

Sources:
- `concepts/copot_site_color_scheme_concept.md` [PRIMARY GIT CONCEPT]
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md`
  [CURRENT WU4 AUTHORITY]

Relations:
- extension/reconciliation of existing Site Identity and appearance lineage;
- the Concept remains retained planning lineage;
- its older four-color Webcore Branding upstream model is superseded where it
  conflicts with the WU4 contract;
- WU4 now defines Webcore Color Scheme as one user-selected Main Color with
  system-derived bounded variants and system-owned black/white neutral bases;
- current four-color `branding.*` behavior remains legacy/transitional
  implementation evidence pending WU4 implementation disposition;
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

Planning action: KEEP AS PLANNING LINEAGE / SUBORDINATE TO DOCS/54 / NO
IMPLEMENTATION AUTHORIZATION

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

### User Settings / Current User Settings
Class: CONCEPT / FUTURE WEBCORE CURRENT-USER CAPABILITY
Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_user_settings_security_email_surface_ownership_concept.md`
  [PRIMARY GIT CONCEPT — User Settings / Current User Settings]

Relations:
- current-user self-service only, distinct from `Users & Access Refinement`
  and administrative management of other/all users;
- relates to `concepts/copot_per_user_admin_appearance_concept.md` without
  absorbing appearance ownership; and
- candidate future own identity, email, password, and security controls remain
  dependent on delivered capability.

Planning action: KEEP FUTURE / NO IMPLEMENTATION AUTHORIZATION

### Security Capability & Surface Ownership
Class: CONCEPT / FUTURE CAPABILITY OWNERSHIP
Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_user_settings_security_email_surface_ownership_concept.md`
  [PRIMARY GIT CONCEPT — Security Capability & Surface Ownership]

Relations:
- capability-gated product surfaces;
- current-user security belongs conceptually with User Settings when
  delivered; administrative security management remains with `Users & Access`;
- site/system-wide Security may enter Site Settings only with a delivered or
  explicitly adopted site/system security-policy capability; and
- Security remains absent from the current visible WU4 Site Settings areas.

Planning action: KEEP FUTURE / CAPABILITY-GATED / NO IMPLEMENTATION AUTHORIZATION

### System Email Capability & Surface Ownership
Class: CONCEPT / FUTURE CAPABILITY OWNERSHIP
Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Sources:
- `concepts/copot_user_settings_security_email_surface_ownership_concept.md`
  [PRIMARY GIT CONCEPT — System Email Capability & Surface Ownership]

Relations:
- `users.email` is account identity data, not system email delivery evidence;
- current-user email editing belongs with User Settings when delivered, while
  administrator editing of another user's email remains a `Users & Access`
  concern;
- Site Settings → Email is capability-gated on delivered system-level sender,
  transport, delivery state/policy, or equivalent capability; and
- Email remains absent from the current visible WU4 Site Settings areas.

Planning action: KEEP FUTURE / CAPABILITY-GATED / NO IMPLEMENTATION AUTHORIZATION

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
- begins only after Database Adoption Compatibility Reconciliation,
  Seven-Tab Site Settings Completion & Hardening, and Installer Refinement I
  are complete and accepted;
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

#### Post-ownership physical module cleanup — preparation inventory
Class: BUNDLED MODULE REFINEMENT PREPARATION SUBSCOPE / PLANNING-ONLY
Status: INVENTORIED / READ-ONLY AUDIT BEFORE MUTATION

- Inventory legacy APP_ROOT/modules entries whose ownership evolved to Webcore, Bundled, or retired disposition. Re-homing responsibility does not automatically prove filesystem relocation or deletion safety.
- Audit routes, autoload/bootstrap, module manifests/registry, DB ownership, published inventory, recovery authority, historical compatibility and live references before proposing moves/removals. Retired files and Webcore-owned legacy directory residues are candidate cleanup targets, not automatic deletions.
- Keep physical cleanup separate from capability/contract retirement and module feature refinement; use bounded changes with behavior and recovery regressions after ownership is proven.


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
Planning action: KEEP; consumes stabilized Webcore/Auth/Security/System Email
capabilities without owning those platform capabilities. Module Permission
Dependency & Base Access remains separate until adopted.

### Form Manager Refinement
Class: FUTURE DEDICATED WORKSTREAM
Status: FUTURE / PREPARATION REQUIRED / CORRECTNESS AUDIT BEFORE SCOPE LOCK
Planning action: KEEP; functional findings must not be disguised as styling.

## 8. Thread-Level Continuity Reconciliation

The previously saved Net-Zero Retirement & Safe-Retry capability-family
Thread-Level Saved Concept is now durably materialized at
`concepts/copot_lifecycle_net_zero_retirement_safe_retry_capability_concept.md`
and registered in Section 5. This records delivered backend capability and
not-yet-delivered productization; it does not create a pre-contract, dedicated
contract, or implementation authorization. Runtime cleanup is operational
continuity, and the confirmed forward-update bootstrap authority gap belongs
to the corrective-workstream Handoff, not this Concept.


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

The following thread-level planning concepts are durably materialized into
their canonical Git Concept source and registered separately in this Workplan;
they no longer depend on thread-only continuity:

- User Settings / Current User Settings;
- Security Capability & Surface Ownership; and
- System Email Capability & Surface Ownership; and
- Site Settings Future Capability Architecture.

The User Settings / Security / System Email Concept preserves its three
distinct identities and remains future/planning-only without implementation
authorization. The Site Settings architecture Concept separately records
capability-gated product projection and the bounded Database adoption
reconciliation item.

Seven-Tab WU5 implementation checkpoint (2026-10-12):
- Codex integrated `35ff0e050ac485520878bca7832110acd844deb4` on `main`; five changed view/route/test files verified, implementing seven-tab integration and canonical EmailOperatorService delegation.
- Codex reported WU5 16 assertions PASS, Email suites 28/32/23/24/28/15 PASS, System Health 10 and Redirects 15 PASS; PHP lint and diff check PASS. Existing Modules presentation test failure attributed to older CSS/class expectation remains UNRESOLVED pending focused source/baseline comparison; no claim of full Modules regression PASS.
- WU5 implementation INTEGRATED / ACCEPTANCE PENDING. Seven-Tab WU6 dependency remains unsatisfied until WU5 acceptance/closure. No live runtime, production reconciliation or release action performed.

Seven-Tab WU5 governance acceptance and closure (2026-10-12):
- Bounded WU5 integration at `35ff0e050ac485520878bca7832110acd844deb4` ACCEPTED / CLOSED based on Codex's focused evidence: WU5 16, Email 28/32/23/24/28/15, System Health 10 and Redirects 15 assertions PASS; source integration verified.
- The sole Modules presentation source-string assertion `tests/wu4_batch2_site_settings_modules.php:150` FAIL is reproducible at pre-WU5 `14a2ff73f7fcc8143a9feabb4a3410dd284e7cf5` and current state with identical message. Modules view, CSS and JS blobs are unchanged; test debt is NOT counted as PASS, and is not a WU5-created regression. No Modules code or test correction is implicitly authorized by closure.
- Seven-Tab WU6 dependency on WU5 is now satisfied; cross-capability acceptance, human/product gates and independent operational validation remain WU6 work. No claim of full Seven-Tab workstream closure or production eligibility.

## Main Runtime Acceptance Decision — 2026-10-12

User-approved operational policy: main COPOT XAMPP installation is the development mirror and target for human/operational acceptance; the former immutable restriction is revoked. Required files and database changes are delivered via Package Lifecycle development patch/update/upgrade, not manual direct mutation. Such lifecycle artifacts do not require public repository releases. Separate disposable installation is reserved for mutation testing, debugging, and risk isolation, not final human acceptance. Keep durable repository source, installed main-runtime state, and human-accepted version distinct. Each main-runtime operation still needs its own verified plan, eligibility, recovery safeguards, and applicable authorization; this policy does not grant blanket mutation permission. Seven-Tab WU6 remains ACCEPTANCE BLOCKED pending operational evidence and product review.

## 9. Immediate Next Planning Target

Current immediate target: Seven-Tab Site Settings WU6 Cross-Capability Acceptance & Closure under accepted `docs/58` contract; WU5 accepted/closed and its dependency gate satisfied. Bounded Installation-Scoped Maintenance Guard is a newly reconciled supporting planning foundation, with contract amendment and safety verification still pending. A read-only
dependency audit on 2026-10-12 confirmed existing guarded legacy adoption and
forward-reconciliation authority; the former bootstrap-authority blocker is
superseded. HARD WU2/WU3/WU4 dependencies are satisfied. WU5 is READY for
bounded implementation, not yet implemented or accepted; downstream Seven-Tab
WU6 remains dependent on WU5. Production runtime reconciliation is NOT STARTED,
requires separate authorization and objective per-instance eligibility.
Server-empty bootstrap remains deferred. No new release, tag, publication,
deployment or destructive live-runtime/database action is authorized here.

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
