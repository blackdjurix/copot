# COPOT — Installation-Scoped Maintenance & Management Concept
Date version: 2026-10-17 09:33:26 WIB
Status: RECONCILED PLANNING CONCEPT / NOT CONTRACT / NOT IMPLEMENTATION AUTHORITY

## Scope and intent
A reusable, Webcore-owned installation-scoped Maintenance Guard foundation protects authorized operations across COPOT capabilities, including Package Lifecycle, while a dedicated future Maintenance Management workstream owns the complete operator surface, installation backup/portability, and scheduling. This Concept does not supersede existing Package Lifecycle, InstallationMutex, recovery, restore, quiescence, or System Health authorities.

## A. Bounded WU6 supporting foundation
- Active continuity: Seven-Tab Site Settings WU6 / Contract-v3 bootstrap stabilization. Adding bounded maintenance work does not create another WU, NRP, or a release/deployment grant.
- Default operator authority is authenticated Webcore Admin; delegated user authority is FUTURE, conditional on independently established Users & Access permission capability. Do not invent a current multi-user interface/role.
- Installation-scoped application write guard blocks normal COPOT-controlled writes for the target installation; other installations and their databases must remain unaffected. Default policy is write-lock rather than blocking the full Admin Shell. System Manager/lifecycle and health observation remain accessible under explicit authorization.
- Enforce on all relevant HTTP/API/Admin/CLI/cron/worker entry points, with fail-closed policy where a controlled write path cannot be guarded. Explicitly scoped lifecycle executor writes are allowed only for approved operation, package/plan, target database and installation identities, with auditable bounded authority.
- Separate maintenance REQUESTED, DRAINING, READY, lifecycle ACTIVE, RECOVERY_REQUIRED, release eligibility, and ordinary NORMAL status from the durable existing recovery/lifecycle status. These are conceptual state labels, not accepted implementation enums; reconcile with existing machine before adding storage.
- Quiescence and database recovery are NOT retired. MariaDbReadOnlyQuiescence and DatabaseQuiescenceCapability remain available. An application guard neither blocks external MySQL/phpMyAdmin writers nor proves zero in-flight transactions or database snapshot consistency; risk acceptance reflects restricted operational DB access, NOT a technical guarantee or permission to bypass existing mandatory recovery gates.
- Queue-awareness must distinguish known queued jobs, executing jobs, active writes/transactions, and unknown/external writers. Draining cannot be proved by an empty queue alone. A blocked/not-ready preflight reports informative WAITING/UNVERIFIED state; full preflight may resume only on revalidated facts, without trusting stale plan or confirmation.
- Prefer lifecycle-option-2 topology: read-only discovery/initial plan may be accessible without a manually held lock; authoritative preflight reports maintenance and recovery needs; mutation/Apply always requires verified installation-scoped execution protection. Exact manual enablement and visibility remain UI design questions. Never hide safety-relevant status or recovery controls.
- Backend must prevent manual unlock while mutation/recovery is active or terminal cleanup incomplete; interruption/restart/retry must preserve truthful persistent evidence and fail-closed protections.
- System Health/Dashboard are consumers of read-only status, blockers, timestamps, and target installation/operation identity, not independent maintenance owners. Minimal WU6 integration is permitted only if required to observe guard and package lifecycle.
- Bootstrap phase 1 Contract-v1-readable REPAIR still depends on proven existing v1 recovery/finalization; new v3-only guard cannot retroactively make an unmodified v1 engine safe. Phase 2 BOOTSTRAP_V3 has its own normative amendment gate. Both require validation on isolated disposable target, with backup/restore before mutation.
- Exit gates: inventory actual writes and entry points; prove permitted actor and target; show queue/in-flight and external-writer limitations; test other installation unaffected; error/crash/retry/restore; confirm no global DB read_only introduced by guard; validate existing quiescence paths and consistency/health gates; require contract amendment before technical implementation scope expansion.

## B. Future dedicated Maintenance Management workstream
- Independent planning identity: full manual management UI and configurable maintenance policies; possible read-only/whole-admin-shell modes; delegated role/permission; robust queue and scheduling controls; richer health/dashboard presentation.
- Installation-portability backup packages: APP_ROOT, PUBLIC_ROOT, media/storage, schema/data, installation metadata; one compressed ZIP with manifest, checksums, secure secret handling, portability/restore contract and verified restore tests. Separate from rollback/recovery artifacts and official lifecycle packages.
- Scheduled backup is FUTURE: retention, encryption/access control, incremental strategy if adopted, pruning, integrity verification, and job failure/status reporting. No capability is assumed delivered.
- External database writes need separate isolation/quiescence design; native table locks, database privilege isolation, or dedicated MariaDB instance remain future candidates, not replacements already selected.
- Complete future feature is NOT HARD dependency for bounded WU6 and does not auto-open new WU implementation.

## C. Webcore-owned reusable Maintenance Guard foundation — accepted direction

Status: USER DECISION MATERIALIZED AS CONCEPT / STANDALONE CONTRACT PENDING / NO IMPLEMENTATION AUTHORITY

- **Ownership:** Maintenance Guard Foundation belongs to Webcore at installation scope. Package Lifecycle is a consumer, not its owner. Implementing the initial bounded guard during lifecycle work does not confer exclusive ownership on Package Lifecycle.
- **Reusable authority:** Package Lifecycle V3, Backup & Recovery, Legacy-to-V3 bootstrap, and future authorized capabilities may use the common Guard through explicitly scoped operation identity, admission, and protection requirements. Guard availability does not itself grant mutation, recovery, release, or execution authority.
- **Single installation authority:** Shared consumers must reuse the same installation-owned protection boundary, without duplicate guards, competing operation owners, or parallel recovery coordinators.
- **Standalone contract requirement:** Establish a dedicated Webcore Installation-Scoped Maintenance Guard Foundation Contract as the normative authority for installation/operation identity, writer registration, admission, drain, exclusion, executor exceptions, durable state, interruption, retry, safe release, isolation, status observation, and verification. This concept does not claim that contract is already published.
- **Consumer contracts:** Package Lifecycle (docs/28), Backup & Recovery (docs/31), existing-runtime adoption (docs/30), and Legacy-to-V3 bootstrap retain their specific authorizations, recovery, database-consistency and finalization invariants. Reconcile references once the standalone contract is accepted.
- **Database boundary:** Guard excludes COPOT-controlled writers only, not direct external or privileged MariaDB writers. It is not server-wide locking or independent proof of a consistent database snapshot. Keep existing quiescence and independently verified scoped database consistency.
- **Foundation versus Management:** The Webcore technical foundation functions independently of a management UI. Future Maintenance Management remains a separate deferred workstream that consumes/extends it for operator controls, delegated permissions, scheduling and installation backup/portability.
- **Compatibility and governance:** Audit existing code ownership and consumer coupling before any refactor. Do not reopen accepted tests by assumption, alter accepted Work Unit topology, or authorize operational target mutation. Reconcile historical Workplan implementation-status labels separately against verified evidence.
- **Promotion sequence:** Audit existing normative sources and implementation evidence; publish standalone foundation contract; reconcile consumer contract references and Workplan; validate compatibility; continue V3 operational closure through its own approval gates.

## Boundaries, dependencies, authority
Related existing authority: docs/28_package_lifecycle_migration_foundation_contract.md; docs/31_backup_recovery_foundation_contract.md; docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md; docs/58_seven_tab_site_settings_completion_hardening_contract.md. Workplan controls sequencing. This Concept is not a contract, schema migration, release, operational lock activation, or authorization to edit installed runtimes.

## Open issues to resolve before implementation
- Prove complete write-path inventory and reliable drain semantics on actual COPOT runtime.
- Reconcile requested manual maintenance with existing automatic active-operation/maintenance state and exact session/operation identity authority.
- Define credible no-DB-mutation determination and the recovery route for file-only plan, without weakening v1/v2/v3 contract requirements.
- Define preflight staleness, executor exception, durable state, manual release restrictions and System Health projection.
- Ensure existing cross-installation isolation and recovery unaffected. Scope future import/restore and backup credential security separately.
