# COPOT — Canonical Target Inventory & Fingerprint Coordinated Promotion Proposal
Date version: 2026-10-11 08:20:00 WIB

Status: MATERIALIZED / COORDINATED PROMOTION PROPOSAL / NON-AUTHORITATIVE / NOT ACCEPTED / NOT PROMOTED / NO IMPLEMENTATION AUTHORITY
Source anchor: main @ ea75f407049a9a747810dbda356dd6f3ffd3e4ac
Source candidate: precontracts/canonical_target_inventory_fingerprint_contract_candidate.md
Affected accepted contracts: docs/28_package_lifecycle_migration_foundation_contract.md; docs/31_backup_recovery_foundation_contract.md

## 1. Scope and authority
This artifact is an integrated promotion **proposal**, not an amendment to authoritative docs. Prior user acceptance covers eight bounded offline fingerprint semantics, but neither coordinated promotion form nor public-status projection has been explicitly accepted as a whole. Review all three intended normative surfaces together. Implementation, destructive runtime actions, backup restoration, release and deployment remain unauthorized.

## 2. Proposed clean normative fingerprint contract
Create a new concise docs/ contract that consolidates the CURRENT proposed normative specification from sections 14–16 of the source candidate and uses previous sections as provenance only.

### 2.1 Package and source/target proof
- Offline operator-provided local ZIP; no publisher authenticity assertion from SHA-256, no network lookup, signing or registry dependency.
- v1/v2 manifest and delivered-payload semantics unchanged. New v3 is explicit, old readers reject it.
- v3 complete ordered top-level field sequence: package_type; manifest_contract_version; target_webcore_version; release_identity; source_tree_identity; source_compatibility; runtime_compatibility; inventory; migration_declaration; target_requirements; coverage; target_inventory; source_state_requirements; removals; renames.
- Strict ordered nested objects; duplicate/unknown JSON keys, floats, numeric strings, overflow and invalid encodings rejected. Existing inventory means physically delivered files, target_inventory means all resulting package-owned files including verified retained files; package identity binds complete manifest.
- Coverage full/partial: full delivers all target-owned bytes; partial requires verified committed complete source inventory, compatible source fingerprint and actual live-file rehash. No second source ZIP is intrinsic.
- Initially APP_ROOT and PUBLIC_ROOT; validated ownership, guarded destinations and cross-platform canonical paths. Unknown legacy files are never silently owned, cleaned or deleted.
- Canonical target inventory SHA-256 binary encoding v1 as in source candidate §16 and fixtures: empty inventory hash 77171ef0d605ac0f272046e606b204d74c330842b8113b7246f46fa2de46030b; single APP_ROOT/package-owned/app/a.txt (size 3, SHA256 abc) hash 5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3. Webcore rejects empty target file set.
- CommittedLifecycleStateStore owns authoritative reference; protected SourceInventoryArtifactStore owns content. Write immutable artifact, flush/read-back/hash, commit verified reference, re-read linkage; fail closed on missing/mismatch. Legacy scan-only cannot create release authority. New-format full transition can establish baseline only after complete resulting-state proof.
- Target fingerprint equality and existing migration, health, and lifecycle finalization gates are mandatory before release identity advancement. Same resulting owned file state produces identical target fingerprint for full and partial packages regardless of archive identity.

### 2.2 State/result safety
- One active lifecycle operation per installation, serialized through existing mutex.
- Immutable action/recovery identity and source/target fingerprint preserved across preflight, apply and interrupted recovery.
- Pre-mutation failures are non-mutating; post-mutation integrity failure leaves non-finalized or indeterminate operation awaiting reconciliation, not ordinary invalid_package.
- No domain-generalized independent Database/Module or composite implementation is authorized by this bounded contract.

## 3. Exact proposed authority amendment to docs/28 (NOT YET APPLIED)
Append a bounded prospective section titled "Manifest v3 Target Inventory and Explicit Owned-File Removal":
1. Preserve all v1/v2 behavior and the existing prohibition on implicit stale-file deletion. Only manifest v3 and an explicit verified action plan can authorize the new removal capability.
2. Package Lifecycle owns source/target verification, scoped package-owned removal/rename planning, destructive operator confirmation binding, serialized apply orchestration and terminal lifecycle disposition. It **does not** own physical Backup & Recovery restoration.
3. Require immutable plan identity covering action kind, ordered operations, roots/paths, expected before/after size/hash, source/target fingerprints and recovery identity. Stale plan invalidates authorization and confirmation.
4. An explicit removal requires authoritative source ownership, current file hash/size match, protected-path/containment checks, operator confirmation distinct from ordinary apply authorization and verified pre-removal recovery bytes.
5. Rename is an auditable relation executed as verified add then verified remove; absence in target inventory is never deletion authority.
6. Deletion authorization is contingent on accepted docs/31 recovery evidence availability; interruption fails closed. Final release commit requires complete resulting-state fingerprint plus existing migration and health gates.
7. No implicit cleanup of unknown legacy files and no cross-domain atomic rollback promise.

Existing docs/28 wording that says stale package-owned deletion is unavailable must be qualified specifically for this prospective manifest-v3 explicit operator-confirmed pathway, rather than silently deleted or globally inverted.

## 4. Exact proposed authority amendment to docs/31 (NOT YET APPLIED)
Append a bounded prospective section titled "Recovery Evidence for Manifest v3 Explicit File Removal":
1. Backup & Recovery remains sole authority for recovery artifact capture/integrity and physical restoration; Package Lifecycle may plan and coordinate but does not substitute its own rollback mechanism.
2. Before removal, snapshot original verified bytes to an integrity-linked recovery artifact and verify successful persistence and read-back. Bind artifact ID, origin logical root/path, action ID, source/target fingerprints, operation identity and journal cursor.
3. On interruption, reconcile actual filesystem state, recovery evidence and immutable action plan before retry or recovery. Cursor alone is not proof of action completion.
4. Retain removed-file recovery artifacts through BOTH lifecycle finalization and recovery closure; subsequent cleanup requires separately accepted retention and cleanup rules.
5. Any post-mutation artifact corruption/missing evidence is recovery-required/indeterminate, never clean invalid-package rejection.
6. Physical restoration and cross-domain state transitions are not promised atomic; no second recovery engine or ownership transfer is created.

## 5. Proposed public status/reason-code choice (PENDING USER ACCEPTANCE)
Prefer reuse of existing accepted top-level public statuses without adding a new externally visible status. Internal reason codes distinguish source_inventory_missing, source_inventory_integrity_failure, source_state_drift, source_ownership_unresolved, target_inventory_invalid, target_fingerprint_mismatch, removal_confirmation_required and recovery_evidence_unavailable.
- Before mutation: typed rejected or blocked/unavailable as supported by the exact accepted public status grammar; no runtime change.
- After mutation: preserve an indeterminate, recovery-required outcome. Never collapse into invalid_package.
- CLI and System Manager use the same typed service result. If externally visible machine-readable reason fields are not already accepted, introduce them only through a separately accepted public/API contract amendment. This section does NOT itself authorize an API wire change.
- Exact spelling/compatibility of public statuses must be verified from current accepted API/operator contracts before final promotion.

## 6. Coordinated promotion and preservation gate
All three normative surfaces are reviewed and accepted as one bounded authority change; no intermediate status may claim explicit removal is authorized without matching recovery guarantees. The candidate remains in precontracts/ as historical proposal provenance.

Acceptance checklist:
- Consolidated docs/ fingerprint contract with non-duplicative normative sections, including precise nested v3 grammar and binary fixtures;
- exact non-conflicting edits to docs/28 and docs/31, preserving extant v1/v2 and Backup & Recovery boundaries;
- confirmed existing public result vocabulary, plus separately accepted API delta only if unavoidable;
- cross-contract text consistency and safe explicit operator confirmation;
- explicit user coordinated-promotion acceptance;
- repository verification and devlog provenance once promotion actually occurs.

Until all checks are met: NOT PROMOTED / NO IMPLEMENTATION AUTHORITY.
