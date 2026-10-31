# CR-1 — docs/28 Package Lifecycle V3 Coordinated Amendment Candidate

Status: CANDIDATE / NON-NORMATIVE / NOT PROMOTED
Target authority: docs/28_package_lifecycle_migration_foundation_contract.md
Basis: controlled deviation CD-V3-HTACCESS-20261026-01 and LCR-01/02/04/05/08/09.
Historical WU acceptance is not rewritten. No runtime, implementation or promotion authority.

## Proposed addendum A — Deployment-owned protected routing resource

The exact physical PUBLIC_ROOT/.htaccess has effective owner DEPLOYMENT_ENVIRONMENT, subject to COPOT-wide ABSOLUTE_IMMUTABILITY. Webcore owns routing requirements but cannot mutate the deployed file. All COPOT-controlled add, replace, remove, rename-source/target, restore and rollback attempts against the exact protected resource fail closed before mutation. Admission, manifest grammar, inventory, planner, execution and recovery interfaces must agree; prohibited entries cannot be silently skipped.

A revised V3 package, whether full or partial, excludes this physical file from package-owned delivered and resulting target inventories and package-owned fingerprints. Full coverage means all resulting package-owned bytes, not deployment-owned bytes. Routing compatibility is a separately verified fresh-install/update admission prerequisite; failure does not authorize COPOT to repair physical .htaccess.

Historical manifest/ZIP, package fingerprint, and committed installed-state evidence remain immutable historical records. Historical PACKAGE_OWNED classification cannot override current protected-resource restrictions; no silent re-identity or reinterpretation. New packages require independent identities and verified source/inventory migration disposition. Existing controlled deviation closes only through technical evidence, acceptance and separately authorized coordinated promotion.

## Proposed addendum B — Attempt-aware source-authority operations

For new attempt-aware source-authority operations, execution attempt identity is immutable, durable and bound to operation, plan and corresponding recovery capture. Retry/resume cannot borrow capture or manifest from a different attempt. Legacy nullable attempt evidence is historical and must not be silently normalized.

PRE_CAPTURE_ABANDONED is a bounded terminal lifecycle disposition only for verified PREPARING operations with cursor zero, no verified mutation path, no migration outcome and no recovery identity/manifest/state; require exclusive ownership, durable evidence, idempotent continuation and negative proof of any capture/recovery obligation. It cannot dispose of an actual post-capture or uncertain mutation.

First-capture continuation must use persisted canonical operation and attempt identity, preserve one installation mutex ownership session across preflight and Guard/capture handoff, and independently validate scoped database consistency. Inconsistency before mutation fails closed; uncertainty after mutation enters the existing INDETERMINATE/recovery-required route.

A READY recovery rehydration does not by itself authorize mutation; prior package/plan/attempt bindings, recovery capture completeness, current protection and consistency evidence and existing confirmation rules remain mandatory. Committed SourceInventoryReference and lifecycle state advance only after genuine WU6 fingerprint, database/migration, health and integrity gates. Existing v1/v2 history and WU completion statements remain historically accurate.

## Required contract coordination

docs/31 must implement matching attempt-bound recovery capture/manifest and protected-resource restrictions. The standalone Guard candidate governs cross-consumer writer admission but is not yet normative. Injection retains its accepted bounded authority without a parallel engine.

## Verification gates

Negative protected-resource tests; revised package full/partial builder, identity and source inventory checks; cross-attempt capture prohibition; pre-capture abandonment with orphan-artifact probes; first-capture interruption, lock ownership and scoped consistency; READY resume/WU6; old v1/v2 and recovery regressions. NO TEST EXECUTION CLAIMED.
