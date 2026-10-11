# COPOT — Canonical Target Inventory & Fingerprint Coordinated Normative Review Draft
Date version: 2026-10-11 10:15:56 WIB

Status: REVIEW DRAFT / NON-AUTHORITATIVE / NOT PROMOTED / NO IMPLEMENTATION AUTHORITY
Authority: user accepted coordinated contract promotion *model* and reuse of existing public top-level statuses; authorized preparation of normative changes **for review only**.
Source baseline: main @ c54dfb617ccb6ff9db5d7bc8da70de10ec9ff00e
Source proposal: precontracts/canonical_target_inventory_fingerprint_coordinated_promotion_proposal.md
Source detailed candidate: precontracts/canonical_target_inventory_fingerprint_contract_candidate.md
Target normative surfaces on future separately authorized promotion: new docs/ inventory contract; bounded docs/28 and docs/31 amendments.
No existing authoritative contract is changed by this draft.

## A. Proposed new normative contract: Canonical Target Inventory & Fingerprint

### A1. Authority and limits
This contract governs offline Webcore target-owned file-state proof in the existing Package Lifecycle. It does not grant independent Database/Module/composite orchestration, online provenance validation, publisher signatures, destructive runtime execution, or release authorization. A supplied local ZIP and local trusted installed-state evidence are the only required sources; a digest verifies metadata/content consistency but does not authenticate a publisher. Existing manifest v1/v2 acceptance and package lifecycle permissions are preserved unchanged.

### A2. Manifest v3 grammar
Manifest contract version is the exact integer `3`. Preserve existing v1/v2 readers, delivered inventory and strict validation behavior without retroactive extensions. Old readers reject unknown manifest versions.

The v3 top-level JSON keys are required in this exact order:
`package_type`, `manifest_contract_version`, `target_webcore_version`, `release_identity`, `source_tree_identity`, `source_compatibility`, `runtime_compatibility`, `inventory`, `migration_declaration`, `target_requirements`, `coverage`, `target_inventory`, `source_state_requirements`, `removals`, `renames`.

The inherited fields retain the accepted v2 nested grammar and constraints. `target_requirements` is required in v3 and follows its accepted v2 grammar, not an invented replacement. The new nested keys are exact and ordered:
- `coverage`: string enum `full` or `partial`.
- `target_inventory`: object `serialization_version` (integer 1), `fingerprint` (lowercase 64-hex), `entries` (nonempty array for Webcore). Each entry is ordered object `root` (registered root), `ownership` (`package-owned`), `path` (canonical relative path), `byte_size` (nonnegative exact safe integer), `sha256` (lowercase 64-hex).
- `source_state_requirements`: object `allowed_inventory_fingerprints` (array of unique lowercase 64-hex digests; nonempty for partial).
- `removals`: array of ordered objects `root`, `path`, `ownership`, `expected_source_byte_size`, `expected_source_sha256`, `reason` (`obsolete`, `replaced`, `renamed`).
- `renames`: array of ordered objects `source_root`, `source_path`, `target_root`, `target_path`.

Reject duplicate and unknown JSON keys at all nesting levels, reordered keys where strict grammar requires order, floats, exponent numeric values for size, negative sizes, numeric strings, overflow and noncanonical input. Existing associative `json_decode()` alone does not satisfy duplicate-key rejection: implementation must use a safe duplicate-key-aware mechanism. The artifact's package integrity identity must bind the entire v3 manifest, including complete target inventory and fingerprint, separately from the target-state fingerprint.

The existing `inventory` continues to describe only physically delivered archive files. `target_inventory` describes all required resulting owned files, delivered or retained. A full package supplies all target-owned files. Partial coverage requires verified committed source inventory and fingerprint compatibility. An empty fingerprint allowlist for a full package never bypasses the ordinary source release, tree, migration or runtime compatibility checks.

### A3. Canonical inventory and fingerprint
Each target record consists only of logical root, ownership domain, canonical relative path, unsigned byte size and raw SHA-256 file digest. Initially allow `APP_ROOT` and `PUBLIC_ROOT` for package-owned Webcore files. Reject protected/operator/module-owned paths, traversal, absolute/drive/UNC/ADS paths, reserved Windows names, trailing spaces/dots, controls, empty/dot segments, symlink/reparse containment escapes, duplicates and portable case-fold collisions within a root. Require UTF-8 NFC; reject non-ASCII input if safe NFC validation is unavailable rather than accepting ambiguous paths.

Canonical binary serialization v1 is the exact concatenation:
`ASCII("COPOT-TARGET-INVENTORY") || 0x00 || u8(1) || u8(1 for SHA256) || u32_be(entry_count) || entries`.
Sort by raw UTF-8 bytes of (root, ownership, path). Each entry:
`u32_be(len(root)) || root UTF8_NFC || u32_be(len(ownership)) || ownership UTF8_NFC || u32_be(len(path)) || path UTF8_NFC || u64_be(byte_size) || raw_sha256_32_bytes`.
Lengths refer to bytes, not code points. Reject integer overflow, malformed digest, duplicates of (root,path) even when ownership differs, and encodings that cannot round-trip. Empty entry count is serializable but invalid for a Webcore target.

Reference fixtures:
- Empty serialized hex: `434f504f542d5441524745542d494e56454e544f525900010100000000`; SHA256: `77171ef0d605ac0f272046e606b204d74c330842b8113b7246f46fa2de46030b`.
- One entry: root `APP_ROOT`, owner `package-owned`, path `app/a.txt`, size 3, digest SHA256 of bytes `abc`: `ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad`. Serialization SHA256: `5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3`.
The target fingerprint does not itself encode package/release/migration identity; those must be bound by separate manifest and installed-state verification.

### A4. Source inventory establishment and durability
`CommittedLifecycleStateStore` owns the authority reference to the complete source inventory and its release, manifest-contract, root mapping, ownership policy, encoding and fingerprint identities. The inventory bytes reside in a separate private installation-controlled `SourceInventoryArtifactStore`; ordinary `RecoveryArtifactStore` remains operation-specific. The artifact location must be non-public, permission/containment guarded, and not merely presumed safe because it lies outside a webroot.

Publication: generate immutable inventory bytes, verify fingerprint, write using same-filesystem temporary file, flush where supported, atomically publish, reread/rehash, atomically commit the exact reference, reread both and verify linkage. Fail closed on missing, corrupt, stale or mismatched evidence without reconstructing release authority from arbitrary live files. Partial transition rehashes actual source-owned and retained files; a stored hash alone is insufficient.

Legacy installation without a complete committed source inventory may establish it from independently verifiable protected local release metadata and matching live file hashes, or after a v3 full-package transition has proven *all* required target-owned resulting files and persisted the resulting inventory. Unknown legacy files cannot be silently declared package-owned, deleted or ignored when they prevent complete proof. Partial application without authoritative source evidence is blocked. No second source ZIP is intrinsically required.

### A5. Transition, finalization and results
One package lifecycle operation may be active per installation. Preflight validates archive payload, source compatibility, ownership/root mapping, trusted source evidence, action plan and complete expected target inventory without mutation. During apply, use existing mutex, maintenance, migration and health gates. Rehash complete actual resulting owned-file state and require equality with declared target fingerprint before advancing installed release identity. Full and partial packages yielding the same complete owned-file state produce the same fingerprint despite different archive IDs.

Only actions explicitly declared in manifest and accepted by the prospective docs/28 and docs/31 amendments may remove or rename package-owned files. Missing from target inventory is never deletion permission. Post-mutation mismatch cannot be returned as clean `invalid_package`: operation remains non-finalized/indeterminate pending recovery reconciliation.

Typed internal outcomes distinguish source_inventory_missing, source_inventory_integrity_failure, source_state_drift, source_ownership_unresolved, target_inventory_invalid, target_fingerprint_mismatch, removal_confirmation_required and recovery_evidence_unavailable. Reuse **existing accepted top-level public lifecycle statuses**, preserving CLI and System Manager consistency; do not introduce externally visible fields, reason codes or statuses without separately accepted public API authority. Pre-mutation rejection/blocking and post-mutation indeterminate/recovery-required behavior are distinct even if public wire grammar uses existing names.

## B. Proposed exact bounded addition to docs/28 (not applied)
### Manifest v3 Explicit Package-Owned Removal and Rename Authority
For explicitly confirmed manifest-v3 operations only, Package Lifecycle may plan and coordinate removal of files proven Webcore package-owned by committed source inventory, current size/hash, guarded root/path and protected ownership checks. This is a narrow prospective exception to preexisting **no stale package-owned deletion** language; it does not authorize inferred stale cleanup, operator-owned deletion or v1/v2 destructive behavior.

An immutable plan identity shall bind source/target inventory fingerprints, ordered action kinds, source/target roots and paths, expected pre/post content identities, recovery-domain identity and dependencies. Separately captured explicit operator confirmation shall bind the exact immutable removal set and plan; changed plan invalidates confirmation. Before mutation, the existing Backup & Recovery authority shall verify durable recovery bytes. Rename is an explicit auditable relationship: verified add followed by verified remove. Final release advancement requires resulting fingerprint equality and all existing health/migration gates.

Package Lifecycle owns eligibility, plan, serialized apply coordination and terminal disposition; docs/31 retains physical restoration and recovery artifact authority. Interruption fails closed and does not imply atomic cross-domain rollback. These terms do not rewrite unrelated current docs/28 WU history or other lifecycle contracts.

## C. Proposed exact bounded addition to docs/31 (not applied)
### Manifest v3 Removed-File Recovery and Interruption Reconciliation
Backup & Recovery retains exclusive restoration authority for original package-owned file bytes and operation-specific recovery records. Before any confirmed explicit removal, capture original content, validate digest/size and durable readback, and bind the artifact to source logical root/path, immutable action/operation identity, source/target inventory fingerprints and current journal action identity/cursor.

On interruption or post-mutation mismatch, reconcile actual files, action identities and recovery artifacts before safe retry/recovery. A cursor alone never proves successful completion. Retain removed-file recovery evidence through BOTH lifecycle finalization and recovery closure; any later cleanup requires an independently accepted retention policy. Missing or invalid post-mutation recovery evidence leaves the operation indeterminate/recovery-required. No parallel recovery engine and no claim of cross-domain atomic rollback.

## D. Coordinated review and promotion gate
The three sections above are proposed as **one coordinated authority change**, not three independent promotions. No existing docs/28 or docs/31 restriction is replaced until all normative surfaces are reconciled and explicitly accepted. User acceptance of the promotion model and existing public status reuse authorizes this review draft **only**. Implementation authorization, runtime/package mutation, destructive file operations, database changes, tag/release and deployment remain separate gates.

Required before promotion: compare precise v2 inherited schema and accepted public status vocabulary against code and contracts; cross-check wording against docs/28 and docs/31 to avoid blanket override; verify canonical test vectors and any unresolved public status mapping; prepare clean normative files/diffs; obtain explicit user authorization for **promotion**, then verify resulting authoritative repository state and log provenance.
