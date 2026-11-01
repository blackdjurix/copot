# CR-1 — docs/28 Package Lifecycle V3 Coordinated Amendment Candidate

Status: REFINED CANDIDATE / NON-NORMATIVE / NOT PROMOTED
Technical disposition: CR-1 SLICE 4 SEMANTICALLY RECONCILED / MATERIALIZED; IMPLEMENTATION VALIDATION PENDING
Target authority: docs/28_package_lifecycle_migration_foundation_contract.md
Basis: CD-V3-HTACCESS-20261026-01, LCR-01/02/04/05/08/09, Codex Slice 4 proposal, GPT semantic refinements and refined CR-1 Slice 1–3 candidates.
Authority boundary: No implementation, repository/runtime mutation, deployment, Alternative C continuation, release, or contract-promotion authority is conferred by this candidate.

Historical WU1–WU7 acceptance stays historically accurate and is not rewritten.

## 1. Ownership and cross-contract boundaries

Package Lifecycle owns package intake, manifest, package-owned inventory, source-relative plan and apply, lifecycle operation/disposition, typed destructive confirmation, migration coordination, WU6 and applicable Package Lifecycle settlement proof. Backup & Recovery owns recovery identity, immutable evidence, capture, restore, verification, cleanup and recovery-specific settlement. Maintenance Guard owns shared COPOT writer protection and consumer-neutral release eligibility. Deployment owns physical routing resources and supplies routing-readiness evidence. Injection consumes existing authorities without creating another engine.

No consumer acquires another's recovery, Guard, deployment or lifecycle authority.

## 2. Absolutely protected deployment-owned resource

The exact physical `PUBLIC_ROOT/.htaccess` is deployment-owned and absolutely immutable to COPOT. The package-relative `public/.htaccess` designation maps to that resource **only through verified canonical deployment root/path resolution**; string matching alone is not sufficient. All builder, manifest, planner, inventory, add/replace/remove/rename, restore, rollback, cleanup and finalization paths must reject a mutation that resolves to the protected physical resource before side effects. Prohibited work cannot be skipped silently or counted as successful.

Exclude the protected physical resource from delivered package-owned inventory, complete resulting target inventory, committed source inventory and all package-owned fingerprints, actions/removal/rename declarations. A generic runtime-owned preservation policy never overrides absolute immutability or grants package authority over this resource.

Historical ZIPs/manifests and installed, source and recovery evidence remain immutable historical records. Historical package ownership claims cannot confer present authority to mutate physical `.htaccess`; no silent migration/re-identification.

## 3. Full and partial V3 inventory semantics

Canonical V3 `target_inventory` describes **all resulting package-owned files**, with canonical root, owner, relative path, size and SHA-256. Its fingerprint differs from raw manifest digest, delivered payload identity, package evidence identity, committed source inventory fingerprint and deployment routing evidence.

`full` coverage declares and delivers every resulting package-owned target byte; it does not include deployment-owned, operator-owned, runtime-generated, recovery-owned, or other non-package resources. Full coverage does not authorize implicit stale deletion. Explicit removals/renames require independently verified source evidence and destructive/recovery authorization.

`partial` coverage may deliver only a subset, but omitted resulting target files must be proven retained from the committed immutable `SourceInventoryReference` with exact ownership, size and hash. The source inventory fingerprint must meet `source_state_requirements`. Removals and rename-source actions require explicit matching committed source evidence. Unknown/colliding/legacy resources are not removal authority. Without a compatible committed source inventory, partial mutation admission fails closed.

The V3 contract can support partial semantics even if the current builder produces only full packages; partial producer implementation/validation stays separately pending, not implicitly accepted.

## 4. Identity hierarchy and verifiable sequencing

Keep independently verifiable, distinct identities for raw manifest, delivered payload, package evidence, target inventory, committed source inventory and its immutable artifact, lifecycle operation, execution attempt, apply plan, applicable ordered actions, and recovery/manifest.

Package evidence identity binds the exact raw manifest and delivered payload under existing package contract semantics, **not future recovery or lifecycle identities**. Each downstream operation, attempt, plan, action and recovery cross-reference must be verifiable using identities available at its creation stage. No circular dependency and no synthetic recovery identity to complete an inapplicable field. Opaque digests without reconstructable canonical inputs are insufficient for applicable authority decisions.

## 5. Source-relative planning and action obligations

The immutable plan reconciles verified source inventory, canonical resulting target inventory, delivered inventory, and current target pre-state. Semantic actions include DELIVER, RETAIN, REPLACE, REMOVE and RENAME; they require exact applicable root/path, pre-state identity, authorized desired postcondition, operation/plan and recovery bindings when applicable.

RETAIN means exact package-owned source bytes are preserved when omitted from a partial payload. REMOVE requires explicit source ownership and matching pre-state size/hash; no implicit stale-file pruning. RENAME requires an explicitly authorized source removal plus verified target identity and enforceable recovery/atomicity obligations, **not a prescribed low-level executor ordering** if another implementation proves equivalent safety and postconditions. Reject target collisions, duplicate/conflicting actions, changed/missing pre-state, unsafe action substitutions and protected-resource claims.

## 6. Attempt-aware lifecycle authority

Every current operation using attempt-aware recovery, READY resume, source-relative destructive apply or attempt-bound lifecycle evidence must durably persist an immutable attempt identity before its first applicable capture or mutation. Bind exact operation/type, package evidence, applicable archive/payload, source/target inventories, immutable apply plan, installation/database scope, and recovery/action/Guard evidence when these subsequently exist.

Resume of the same attempt rehydrates its persisted identity; a new attempt requires a distinct identity and fresh applicable evidence. Missing, stale, null, foreign, contradictory or cross-attempt identity blocks current attempt-aware mutation. Historical nullable attempts remain historical evidence and require verified bounded compatibility, never silent normalization.

## 7. PRE_CAPTURE_ABANDONED

This bounded terminal disposition requires verified persisted PREPARING state, cursor zero, no verified mutation or migration outcome, unchanged applicable operation/package/plan/attempt/installation identities, exclusive installation ownership, and durable idempotent terminal evidence read back **before** clearing the active operation.

Package Lifecycle must consume an authoritative **Recovery-owned negative-proof result** covering exact operation/attempt and a complete verified applicable recovery boundary. Backup & Recovery classifies evidence as target-bound, independently foreign/irrelevant, or unresolved ambiguous. Independently proven foreign recovery sets do not automatically block; target-bound obligations or relevant ambiguous residue block. Package Lifecycle may not enumerate or reinterpret recovery-store artifacts itself.

If the mutation boundary was crossed or cannot be ruled out, this disposition is unavailable and existing recovery-required/indeterminate handling applies.

## 8. First-capture coordination

Persist and reread the exact operation/attempt, package/plan and applicable installation/database scope before first capture. Use **one already-held InstallationLock object** across preflight and Guard/capture handoff, with explicit adoption, never a competing nested mutex. Establish scoped COPOT write protection; separately verify database identity/namespace/owned tableset and independent pre/post evidence-composition database consistency. Capture and read back verified recovery evidence before mutation authorization.

Guard exclusion does not establish database quiescence. Pre-mutation conflicts fail closed; post-mutation uncertainty retains the existing recovery-required/indeterminate route, never artificial abandonment.

## 9. READY resume, WU6 and committed source authority

READY recovery means verified capture readiness, not mutation permission or a committed lifecycle state. Resumption requires matching applicable installation, operation/attempt, package/archive, source/target fingerprints, plan, recovery identity/manifest, Guard and lock ownership, database consistency and existing confirmation/mutation permit. It must not invent recovery state, borrow cross-attempt evidence, overwrite uncertain capture or bypass deployment/recovery gates.

For a successful committed V3 operation, WU6 retains exclusive terminal authority for target fingerprint, database/migration settlement, health/integrity, installed-state advancement and committed `SourceInventoryReference`. Publish that reference only after actual successful WU6 and independently verified applicable package/target/source/operation/attempt/installation/database evidence **plus recovery linkage only when recovery evidence applies**. No synthetic recovery proof and no READY-to-commit shortcut.

## 10. Deployment routing coordination

Before mutation-bearing fresh installation or existing-runtime package update, Package Lifecycle consumes the scope-bound, fresh, provider-neutral routing-readiness result defined by docs/32; it does not implement server configuration or repair `.htaccess`. The result verifies applicable target/existing installation binding, roots, entrypoint, base path, routes, Admin/login routing, assets and observed provider/server behavior. Missing, stale, ambiguous, incompatible or foreign proof blocks pre-mutation admission. Routing failure is deployment-readiness failure, not automatically package corruption or recovery completion.

Historical deployments lacking the newest evidence format are not invalid solely for that reason, but current mutation requires valid current or bounded-equivalent routing proof.

## 11. Historical package and injection compatibility

Historical v1/v2 ZIPs, manifests, identities, source, installed and recovery evidence remain immutable and interpreted according to their original contracts. Do not normalize them into V3, fabricate `SourceInventoryReference` or invent source ownership. V1/v2 implicit stale deletion remains prohibited.

Without a committed `SourceInventoryReference`, ordinary V3 update authority cannot be inferred: use the separately authorized bounded legacy bootstrap or `COMMITTED_SOURCE_AUTHORITY_RECONCILIATION` route as applicable. Injection reuses existing Package Lifecycle, Recovery, Guard, database consistency and WU6 authority, not a parallel engine or synthetic historical migration/capture.

## 12. Consumer-specific settlement and Guard release

Package Lifecycle supplies terminal settlement evidence for **all obligations applicable to the operation's actual terminal disposition**. Successful committed operations require WU6 health/integrity/metadata and source authority settlement. Valid pre-mutation terminal dispositions, including eligible `PRE_CAPTURE_ABANDONED`, require their own authoritative durable terminal evidence **without fabricated WU6 or recovery completion**. Unsettled apply/migration/staging/cleanup obligations remain blockers as applicable.

Backup & Recovery supplies its own recovery-specific settlement when recovery obligations exist. Guard evaluates consumer-neutral release eligibility using only the relevant consumer proofs and reconciled writer leases. Neither Package Lifecycle nor RecoveryLifecycleCoordinator is universal Guard release owner. Failed/uncertain operations cannot masquerade as safe NORMAL merely by closing a process/session.

## 13. Contract and implementation acceptance

Normative candidate requirements: physical protected-resource resolution/immutability; full/partial target/source/delivered inventories; sequenced verifiable identities; source-relative authorized postconditions; attempt-bound capture and resume; Recovery-owned abandonment negative proof; single adopted installation lock; independent database consistency; READY vs WU6 separation; routing readiness consumption; historical v1/v2/Injection compatibility; per-disposition settlement.

Implementation-validation obligations remain separately pending for partial package builder or bounded producer; all current attempt-aware apply entrypoints; Recovery-owned negative-proof interface and ambiguous/foreign residue cases; first-capture interruption/lock adoption; READY/WU6; protected `.htaccess` negatives; historical fixtures; deployment routing admission; settlement and Guard release. No source inspection here constitutes an operational test result.

## 14. Explicit exclusions

No second Guard, recovery engine, universal routing engine, automatic `.htaccess` correction, implicit stale deletion, generic downgrade, fabricated historical source or recovery authority, synthetic completion, Server-Empty Bootstrap, runtime/deployment mutation, Alternative C continuation, or contract promotion. These require separate authority and gates.

## 15. Historical supersession and coordinated promotion (pre-promotion clarification)

For **new independently identified V3 packages**, the revised package-owned inventory/target/source fingerprints exclude exact physical `PUBLIC_ROOT/.htaccess` after verified canonical path mapping. This supersedes only the incompatible prior package-owned protected-file assumption within the explicitly recorded `CD-V3-HTACCESS-20261026-01` transition. Historical v1/v2 and old-V3 manifests, ZIPs, fingerprints, installation evidence and accepted WU statuses retain their original identities and provenance, without being silently eligible for new-V3 mutation or rehashed into current packages.

Package Lifecycle consumes, rather than owns, three independently authoritative results: Guard admission/release eligibility, Recovery-owned negative proof and applicable settlement, and provider-neutral deployment routing readiness. These must be adopted as a **coordinated normative set**, with corresponding standalone Guard authority and docs/31/docs/32 amendments, not through unilateral docs/28 promotion. Accepted Legacy-to-V3 Injection remains a bounded consumer and no second engine is introduced.

The Workplan's recorded controlled deviation explicitly requires verified implementation evidence before normative amendment promotion. Record exact evidence and a separately authorized disposition of that prerequisite; this candidate does not silently waive it. New package identity, SDR verification, operational E4 eligibility and deviation closure retain their separate conditions. Promotion is not implementation acceptance, and implementation acceptance is not implied by this text.

## 16. Package-to-recovery identity bridge (F-05)

For a new attempt-aware V3 operation, Package Lifecycle must create or verify an **immutable deterministic identity bridge** between the canonical package evidence identity and recovery's `targetPackageIdentity` before any recovery capture or mutation that depends on the target identity. It must disclose verifiable canonical source inputs, transformation/domain distinction (or an exact immutable cross-reference artifact), and bind the exact operation, execution attempt, applicable archive/payload/plan and installation scope using only identities already available before capture. Persist and independently read back this pre-capture bridge before a dependent recovery capture or mutation. When the recovery identity/manifest is subsequently created, append or publish a separately immutable, verified extension that binds it to the existing bridge without rewriting prior immutable evidence. Independently verify the complete bridge and extension at capture completion, READY resume, WU6 and applicable settlement; no circular identity formation or opaque unverifiable extra hash. Identity equality is not required. Missing/stale/foreign/ambiguous bridges block current mutation/resume/commit; old records retain historical identity and may only use a bounded verified compatibility route.

## 17. Package Lifecycle release-evidence producer (F-03)

For each applicable terminal route, Package Lifecycle supplies a domain-owned settlement result bound to the Guard release-evidence envelope: canonical installation/database scope as applicable, protection session, operation/attempt, package and plan identity, actual terminal disposition, discharged/outstanding lifecycle and migration/commit obligations, result integrity/provenance and freshness. Committed-success proofs include WU6 and committed SourceInventoryReference; valid pre-mutation terminal routes instead carry their own authoritative terminal proof and cannot claim synthetic WU6. The consumer result references applicable Recovery-owned settlement without converting recovery state into Package Lifecycle authority. Guard independently verifies the common envelope and reconciled leases, and releases only after every applicable consumer obligation is settled. Missing consumer evidence fails closed.
