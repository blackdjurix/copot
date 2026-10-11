# COPOT — Canonical Target Inventory & Fingerprint Contract
Date version: 2026-10-11 13:48:08 WIB

Status: ACCEPTED / COORDINATED CONTRACT PROMOTION
Authority: prospective normative Package Lifecycle v3 specification. Promotion is **not** implementation, runtime mutation, recovery execution, release or deployment authorization.
Companion authority: docs/28 Package Lifecycle, docs/31 Backup & Recovery and docs/shared_destructive_plan_confirmation_operator_input_contract.md.
Provenance: accepted four-surface promotion model; reviewed precontracts/canonical_inventory_normative_contract_review_v1.md. Historical candidate/draft appendix language is not normative.

## 1. Boundaries
Offline local-operator-supplied Webcore ZIP only. v1 and v2 manifest readers, semantics and public result statuses remain unchanged; legacy readers reject v3. SHA-256 establishes consistency, not publisher authenticity. A source inventory represents committed local installed-state metadata, never a required second source ZIP. One active lifecycle operation per installation.

## 2. Strict manifest-v3 grammar
Top-level keys, all required, in exact order:
`package_type`, `manifest_contract_version`, `target_webcore_version`, `release_identity`, `source_tree_identity`, `source_compatibility`, `runtime_compatibility`, `inventory`, `migration_declaration`, `target_requirements`, `coverage`, `target_inventory`, `source_state_requirements`, `removals`, `renames`.

- package_type: exact string `copot-webcore`; manifest_contract_version: exact JSON integer 3; target_webcore_version: valid PackageVersion string; release_identity: valid nonempty opaque identity string; source_tree_identity: null or valid opaque identity string.
- source_compatibility: ordered object `minimum_source_version` (valid version string), `maximum_source_version` (valid version string or null; when set it must be strictly greater and is exclusive).
- runtime_compatibility: ordered object `minimum_php_version` (valid existing PHP version string, two components normalized to three), `minimum_database_versions` (nonempty object of unique nonempty vendor strings to accepted version strings; no invented vendor enum), `required_extensions` (nonempty contiguous JSON list of nonempty extension strings, case-insensitive deduplicated as accepted runtime semantics).
- inventory: nonempty contiguous JSON list of delivered entries, each ordered `path` (canonical payload-relative path), `byte_size` (nonnegative safe integer), `sha256` (64 lowercase hex), `ownership` (accepted delivered-payload ownership). This excludes `.copot/package.json`; it is NOT the resulting complete target inventory.
- migration_declaration: ordered `declares_core_migrations` (boolean), `declaration_identity` (null when false; required nonempty identity when true).
- target_requirements: contiguous JSON list (may be empty), each ordered `kind`, `owner`, `identity`, `operator`, `value`, `mandatory`. kind enum database|schema|capability; operator enum present|minimum_version|exact_identity; owner and identity nonempty trimmed tokens without control characters; mandatory boolean. Uniqueness by tuple (kind,owner,identity). present requires value null; valued operators require nonempty trimmed string, and minimum_version obeys PackageVersion. Database kind requires minimum_version; schema kind forbids minimum_version. Existing target-requirement domain restrictions remain effective.
- coverage: exactly `full` or `partial`.
- target_inventory: ordered object `serialization_version` (integer 1), `fingerprint` (64 lowercase hex), `entries` (nonempty contiguous JSON list). Each entry ordered `root`, `ownership`, `path`, `byte_size`, `sha256`; root APP_ROOT or PUBLIC_ROOT, ownership exactly package-owned, canonical relative path, nonnegative safe integer size, SHA-256 lowercase hex.
- source_state_requirements: ordered object `allowed_inventory_fingerprints` (unique contiguous list of lowercase 64-hex fingerprints; required nonempty for partial; full may use empty without bypassing source compatibility).
- removals: contiguous array, each ordered object `root`, `path`, `ownership`, `expected_source_byte_size`, `expected_source_sha256`, `reason`, with reason obsolete|replaced|renamed, trusted source ownership and digest.
- renames: contiguous array, each ordered object `source_root`, `source_path`, `target_root`, `target_path`. Each rename maps an explicit source removal to exactly one delivered target addition. Rename collision with unrelated existing target is invalid.

Reject unknown, duplicate or reordered keys where ordered; duplicate JSON object keys at every level require duplicate-aware parsing beyond associative json_decode. Reject wrong object/list shape, noncanonical Unicode, floats/exponents/signed numeric strings for byte sizes, negative or overflowing integers, malformed hashes and dangerous paths. Integer values must fit exact runtime safe integer and serialization bounds. Root/path portability rules reject traversal, absolute/drive/UNC/ADS paths, Windows reserved/trailing dot-space names, controls, symlink/reparse escapes, duplicate (root,path) even with different owner and portable same-root casefold collisions. UTF-8 NFC mandatory, fail closed if it cannot be established.

## 3. Coverage, ownership and local source proof
target_inventory represents **all resulting Webcore package-owned files**, both delivered and retained. `full` must physically deliver every target-owned byte. `partial` must prove a committed complete source inventory, allowlisted fingerprint, compatible release identity and actual rehash of every retained source-owned file. Missing/stale/unverifiable source or owner fails closed; arbitrary directory scan does not confer provenance. Unknown legacy files cannot be silently owned/deleted. Complete full-v3 target verification may establish initial source authority after successful finalization without silently claiming unrelated files.

## 4. Target inventory fingerprint v1
`SHA256(ASCII("COPOT-TARGET-INVENTORY") || 0x00 || u8(1) || u8(1 for SHA-256) || u32BE(entry_count) || entries)`. Sort entries by raw UTF-8 bytewise (root,ownership,path). Each entry:
`u32BE(root_utf8_length)||root_UTF8_NFC||u32BE(owner_length)||owner_UTF8_NFC||u32BE(path_length)||path_UTF8_NFC||u64BE(byte_size)||raw_SHA256_32`.
Lengths are bytes. Empty entry serialization is defined but invalid for Webcore target. Empty fixture SHA256 `77171ef0d605ac0f272046e606b204d74c330842b8113b7246f46fa2de46030b`. Single APP_ROOT/package-owned/app/a.txt, size 3, bytes abc fixture SHA256 `5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3`.

## 5. Manifest and package artifact identities
For v3, `M` is exact raw UTF-8 manifest bytes in verified private staging at `.copot/package.json`. `manifest_sha256=SHA256(M)` without reserialization. Manifest is not included in delivered inventory, avoiding self-reference. Delivered payload identity is existing sorted delivery-file inventory integrity digest, independently checked against staged content. The combined v3 evidence digest is `SHA256(ASCII("COPOT-V3-PACKAGE") || 0x00 || raw32(manifest_sha256) || raw32(delivered_payload_integrity_digest))`.
Persist package identity as canonical `v3-sha256:<lowercase_hex_digest>` in operation records, plans, recovery manifests and terminal evidence. v1/v2 opaque identities retain historical interpretation only within verified legacy manifest-contract context; no unversioned digest may be interpreted as v3. Package evidence identity, inventory fingerprint, release identity, operation identity and apply-plan identity are distinct.

## 6. Source inventory persistence
CommittedLifecycleStateStore holds exact reference plus release, manifest version, owner policy, root mapping, encoding and fingerprint context; a separate private protected SourceInventoryArtifactStore holds complete immutable inventory content. RecoveryArtifactStore is operation-specific, never the committed-source authority. Write artifact to same-filesystem temporary location, obtain positive write/sync durability, atomically publish, reread/re-hash, commit authoritative reference durably and reread both for linkage. If required atomicity or durability is unavailable, fail closed. A published inventory artifact without a durably linked committed reference is a non-authoritative orphan: never bootstrap authority and never automatically clean without separately accepted retention policy.

## 7. Removal-set binary serialization v1
`removal_set_digest=SHA256(ASCII("COPOT-REMOVAL-SET") || 0x00 || u8(1) || framed(plan_identity) || u32BE(action_count) || actions)`.
`framed(s)=u32BE(length UTF8_NFC bytes)||UTF8_NFC bytes`. Plan identity is canonical scheme-prefixed immutable identity computed **without** removal_set_digest. Encode actions in immutable ordinal order, zero-based contiguous. Per action:
`u32BE(ordinal)||u8(action_kind)||u8(reason)||framed(source_root)||framed(source_path)||framed(target_root)||framed(target_path)||u64BE(expected_source_byte_size)||raw32(expected_source_sha256)`.
Enums: action_kind 0x01 explicit removal, 0x02 source removal of declared rename; reasons 0x01 obsolete, 0x02 replaced, 0x03 renamed. 0x02 requires reason 0x03 and nonempty target root/path; 0x01 requires obsolete/replaced and empty target root/path. Rename addition must be separately delivered and collision-checked. Reject duplicate ordinals, source actions, noncanonical paths, enums, hash, integer bounds or mismatch against manifest/immutable plan.
Fixture: plan identity `plan-v1:example`, ordinal 0, kind 0x01, reason 0x01, source APP_ROOT/app/old.txt, size 3, bytes abc, empty target. Serialization hex:
`434f504f542d52454d4f56414c2d53455400010000000f706c616e2d76313a6578616d706c6500000001000000000101000000084150505f524f4f540000000b6170702f6f6c642e74787400000000000000000000000000000003ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad`
Length 123 bytes, SHA256 `619db7c1495f73674fb81f4603b4e486efc2042ded6a26dfe6f3b1272003272d`.

## 8. Apply, confirmation, recovery and status
Explicit remove/rename requires source ownership/hash proof, immutable plan, exact removal-set digest, plan-bound typed shared operator-input confirmation per companion contract, and positively verified original-byte recovery capture before deletion. Presence/absence in target inventory is not inferred deletion authority. Rename is auditable verified add then verified remove. Cross-record lifecycle/recovery identity agreement required. Post-mutation interruptions or failed fingerprint proof stay INDETERMINATE/recovery-required; only actual verified canonical restore permits RESTORED. Retain removal evidence through lifecycle finalization AND recovery closure, then clean only by separately accepted policy. No cross-domain atomicity claim. Target fingerprint equality, migration and health verification precede committed release advance.
Existing public result statuses only: invalid_package for malformed preflight manifest/inventory; blocked for missing source/ownership/recovery prerequisites; rejected for absent or stale consent; indeterminate for post-mutation proof/evidence failure; cleanup_pending for verified completion with outstanding authorized housekeeping; unavailable for unavailable capability. No new output status or public reason field without separate authority.

## 9. Effective boundary
This is a normative future-capability contract coordinated with docs/28, docs/31 and operator-input contract. Actual code changes, CLI routes, runtime operations, file removal, deployment, tags and releases require distinct authorization.
