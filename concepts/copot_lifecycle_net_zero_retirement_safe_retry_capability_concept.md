# Lifecycle Net-Zero Retirement & Safe-Retry Capability Continuation

Status: CONCEPT / IMPLEMENTATION-STATE INVENTORY / PLANNING ONLY

Project: COPOT

## Purpose and boundary

This Concept materializes the previously saved thread-level continuity payload for the net-zero retirement capability family and its related lifecycle safety mechanisms. It distinguishes durable implementation from incomplete or future capability. It does not introduce a new workstream, pre-contract, dedicated contract, or implementation authorization.

Repository source and tests are the authority for implemented behavior. `docs/28_package_lifecycle_migration_foundation_contract.md` is the existing authoritative amendment for net-zero retirement; this Concept does not replace or broaden it. Backup & Recovery and database quiescence retain their existing authorities, including `docs/31_backup_recovery_foundation_contract.md`.

## Implemented and durable on main

### Net-zero retirement

Status: IMPLEMENTED / DURABLE BACKEND CAPABILITY.

- A distinct `RETIRED_NET_ZERO` terminal, non-success disposition exists for an operation whose effective mutation is proven net-zero.
- The implementation verifies bounded operation, archive, staged payload, apply-plan, migration-plan, progress, and state evidence; it fails closed on divergent or unproven state.
- Durable forensic retirement evidence is published before canonical maintenance clearance; the original operation identity is retained.
- Net-zero retirement is not successful package completion, rollback, restore, retry, or installed-state advancement.
- Evidence: `app/Core/NetZeroRetirementService.php`, `app/Core/NetZeroRetirementVerifier.php`, `app/Core/NetZeroRetirementEvidenceStore.php`, `tests/net_zero_retirement.php`.
- Durable implementation anchor: `fe6d789b4345bb61a58a0fe011de1312e1331c01`.

### Protected pre-mutation retry bootstrap

Status: IMPLEMENTED / DURABLE BOUNDED BACKEND PATH.

- Retry eligibility is constrained to the same operation and a pre-mutation state; non-zero file progress, a last-verified path, migration outcome, or existing recovery evidence disqualifies the special bootstrap path.
- The retry path preserves operation identity and uses the existing lifecycle coordinator rather than treating the attempt as an unrelated new operation.
- This does not prove that every blocked/partially mutated lifecycle state is automatically retryable.
- Evidence: `app/Core/PackageLifecycleService.php`, `tests/package_lifecycle_pre_mutation_retry.php`, `tests/package_lifecycle_wu6_retry_e2e.php`.
- Durable implementation anchor: `9437576198d270653ab38242168e637baa956fa2`.

### Designated migration connection

Status: IMPLEMENTED / DURABLE INTERNAL EXECUTION PLUMBING.

- Migration execution requiring a privileged designated connection is separated from the ordinary runtime database connection.
- When migration execution requires the designated connection and it is unavailable or invalid, the lifecycle path fails closed.
- This is not an operator-facing database migration action.
- Evidence: `app/Core/PackageLifecycleFactory.php`, `tests/designated_migration_connection.php`.
- Durable implementation anchor: `9437576198d270653ab38242168e637baa956fa2`.

### Recovery and database quiescence boundary

Status: EXISTING BACKEND FOUNDATION / INTEGRATED DEPENDENCY.

- The Package Lifecycle consumes existing Backup & Recovery capabilities and a database quiescence boundary rather than defining a new recovery engine.
- The designated migration connection and protected mutation boundary build on that existing composition.
- Evidence: `app/Core/BackupRecovery/`, `app/Core/PackageLifecycleFactory.php`, `docs/31_backup_recovery_foundation_contract.md`.
- This entry records the relationship; it does not reclassify the whole Backup & Recovery foundation as newly implemented by this Concept.

## Not yet delivered as an accepted product capability

### Operator recovery/repair experience

Status: NOT IMPLEMENTED / FUTURE PRODUCT PROJECTION.

- No separately accepted, dedicated recovery wizard or `/recover` / `/repair` product flow is established by the net-zero and safe-retry backend work.
- Any future recovery surface would require its own applicable semantic and authorization decisions. This Concept does not require one now.

### Automated recovery/disposition routing

Status: NOT ESTABLISHED AS A COMPLETE ACCEPTED CAPABILITY / FUTURE.

- No general automatic decision engine is established here for selecting net-zero retirement, retry, restore, or other dispositions across all failure states.
- Existing bounded backend paths must not be represented as universal automatic recovery.

### End-to-end operator productization of the capability family

Status: PARTIAL / NOT ACCEPTED AS A COMPLETE USER-FACING FEATURE.

- Backend safeguards are present, but their existence alone does not establish an accepted operator UX or an end-to-end product acceptance for all recovery situations.
- Retain these mechanisms as internal lifecycle safety capabilities without inventing new buttons or exposing technical disposition names to operators.

## Retained distinctions

- Net-zero retirement: proven no effective mutation, distinct non-success terminal disposition.
- Pre-mutation retry: bounded continuation of an eligible existing operation.
- Recovery/restore: separate recovery authority, not an alias for net-zero retirement.
- Designated migration connection: internal execution plumbing, not an operator intent.
- Quiescence: safety boundary, not a product surface.
- Operator-facing intents, when separately authorized, should remain understandable without exposing backend mechanism names.

## Continuity and authorization

This Concept is a durable planning record of what exists and what does not. It neither modifies existing authoritative contracts nor opens a corrective lifecycle workstream. The separately confirmed forward-update bootstrap authority gap belongs to the next-session Handoff/corrective lifecycle reconciliation, not to this saved Concept. Runtime environment cleanup belongs to operational continuity, not to this Concept.

No implementation, schema/database/runtime mutation, pre-contract, contract promotion, release, deployment, or productization is authorized by this file.
