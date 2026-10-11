# COPOT — docs/31 Bounded Amendment Review: Removed-File Recovery
Date version: 2026-10-11 11:47:58 WIB
Status: PROPOSED DELTA / NOT APPLIED / NOT PROMOTED
Target: docs/31_backup_recovery_foundation_contract.md (verified base sha 85420c0393d8a2ae43b1bc196ebc0e4785849713)
Dependency: fingerprint normative contract plus prospective docs/28 amendment co-promoted.

## Proposed new prospective normative section
### Recovery ownership, action artifact, interruption and closure for manifest v3
Backup & Recovery retains exclusive ownership of recovery identity/manifest, physical filesystem and DB restoration, recovery-domain verification and cleanup. Package Lifecycle requests capture and consults recovery evidence for its plan and terminal disposition, but does not restore bytes itself.

For each explicit manifest-v3 removal/rename-as-add-and-remove action, before source deletion durable evidence must identify operation ID, immutable plan ID, action ordinal and kind, source logical root/path, verified original byte size/hash, recovery artifact ID and verified artifact SHA256/size, source and target canonical fingerprints, confirmed removal-set digest, recovery domain and recovery manifest identity. The recovery artifact must be captured in protected storage, durably published and read back/rehash-verified before any delete attempt.

The durable journal tracks action-by-action intent and completion state; before each mutation persist cursor plus action identity and intent; on success verify actual bytes then persist completion and next cursor. Cursor alone must never prove action completion. If interruption occurs after a removal but before fingerprint proof, lifecycle remains nonfinalized/INDETERMINATE. Recovery reconciles source/target actual bytes, planned actions, journal, recovery artifacts and the immutable operation identity before any restore or resume decision. Never blindly replay deletion.

**Recovery closure:** a durable terminal recovery record proving that all required recovery-domain obligations for the operation identity, including verification and authoritative disposition, have been discharged. Lifecycle finalization alone does not create recovery closure. Artifact retention continues until BOTH lifecycle finalization and recovery closure are verified. Cleanup afterward requires accepted explicit retention/cleanup policy, not inferred permission to delete evidence.
If recovery artifact is missing/invalid after mutation, surface indeterminate/recovery-required. If primary transition is completed and verified but separate artifact cleanup is pending, use existing cleanup_pending status if its accepted conditions hold. Preserve existing no cross-domain atomic rollback stance.

## Review requirements
Cross-check existing docs/31 recovery-domain identity and net-zero retirement rules, especially operator consent and cleanup authority; do not introduce an independent recovery engine, do not change completed historical work-unit outcomes, and do not change authoritative docs until coordinated user promotion authorization.
