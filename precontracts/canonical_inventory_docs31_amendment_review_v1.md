# COPOT — docs/31 Bounded Amendment Review: Removed-File Recovery
Date version: 2026-10-11 12:28:26 WIB
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

## Final corrective proposal: interrupted-removal recovery

Recovery artifact identities and published manifests are immutable once durably persisted. Cursor, action intent, and completion records are mutable only through the authorized durable lifecycle/recovery journal with verified write and readback; the cursor by itself cannot prove an action completed.

If removal succeeds but final target fingerprint verification fails, the operation remains non-finalized and INDETERMINATE with recovery-required disposition. Reconciliation must inspect actual source/target file state, immutable plan and action identity, journal progression, and verified recovery artifacts before any restore or retry. Deletion must not be blindly replayed.

RESTORED may only be reported following actual canonical restoration and independent verification of all applicable recovery domains. Recovery artifact availability alone never implies restoration. cleanup_pending is reserved for a verified completed operation with outstanding permitted cleanup.

Recovery closure is a durable authoritative record that the exact operation's recovery obligations are discharged. Removed-file artifacts are retained until BOTH lifecycle finalization and recovery closure; further cleanup follows separately accepted retention authority. Missing publication durability or evidence fails closed. No second recovery engine or cross-domain atomicity is implied.

## Final Reconciliation Patchset — cross-record identity consistency

The Package Lifecycle operation record and the Backup & Recovery action journal MUST durably cross-reference the exact same immutable `operation_identity`, `plan_identity`, action ordinal, and `recovery_identity` for each destructive file action. Both must bind the same scheme-versioned package evidence identity. The authoritative journal alone may advance mutable action intent/cursor/completion fields; neither record alone proves successful action completion when its counterpart is missing, inconsistent, ambiguous, or unreadable. Such disagreement blocks completion, prevents release identity advancement, and after mutation requires INDETERMINATE/recovery reconciliation. The immutable published recovery manifest/artifact references are never rewritten to conceal mismatch.
