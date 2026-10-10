# COPOT — Canonical Target Inventory & Fingerprint Contract Candidate
Date version: 2026-10-10 17:55:41 WIB

Status: MATERIALIZED / CONTRACT CANDIDATE / NOT PROMOTED / NOT IMPLEMENTATION AUTHORITY
Project: copot
Source: `concepts/copot_adaptive_package_lifecycle_independent_domain_compatibility_concept.md`
Related accepted foundation: `docs/28_package_lifecycle_migration_foundation_contract.md`
Provenance: User acceptance of bounded Adaptive Package Contract & Identity semantics and OFFLINE-ONLY canonical target inventory fingerprint; Codex technical review reported TECHNICAL REVIEW COMPLETE; user authorized materialization of this candidate on 2026-10-10.

## 1. Authority boundary
This candidate records accepted bounded semantics and proposes implementation-grade fingerprint rules. It is not a promoted contract, cannot override current Package Lifecycle/Module Lifecycle contracts, and does not authorize source implementation, runtime/database changes, or release actions. Broader Adaptive Package Lifecycle domain/package changes remain unpromoted. Distinguish user-accepted offline fingerprint *semantics* from proposed technical *encoding*.

## 2. Accepted semantic invariants
- Package declares payload, identity, compatibility and target state; Lifecycle determines eligibility, operation classification and execution.
- One lifecycle package operation executes per installation at a time. Subsequent operations re-evaluate the latest committed state.
- Full or partial Webcore package may target patch, minor or major release transitions when compatibility and complete resulting-state proof pass.
- Package artifact identity differs from target Webcore release identity, source-state identity, target inventory fingerprint and migration identity.
- A single operator-provided update package is sufficient when source-state evidence is already available locally; source inventory is local installed-state evidence, **not** a second source package.
- Verification is **OFFLINE-ONLY**: no GitHub Releases lookup, remote service, publisher signature or network dependency. Structural and content integrity do not establish publisher authenticity.
- Installed release identity cannot advance without verified source compatibility, complete final file state, health, applicable migration/ledger and finalization gates.

## 3. Inventory coverage and separation
New-format packages distinguish **delivered payload inventory** (files physically contained in archive) from **complete canonical target inventory** (all required target package-owned files including retained files). A separate **committed source inventory** represents the expected pre-operation owned file set and is verified against actual live files.

Target records contain logical destination root, ownership domain, normalized relative path, unsigned file size in bytes, and file content SHA-256. Package identity, release/version identity, manifest version, requirements, migration/ledger declarations, recovery operation identity and publisher provenance are separately bound/validated and **not** included in the inventory fingerprint itself.

Excluded from Webcore target inventory: runtime/operator-owned configuration and storage, unowned paths, and independently owned Module/Database material unless a separately accepted domain-specific contract establishes otherwise.

## 4. Proposed canonical serialization (technical candidate; not yet accepted)
Proposed digest: `SHA256(ASCII("COPOT-TARGET-INVENTORY\\0") || u8(version) || u8(hash_algorithm=1) || u32_be(entry_count) || encoded_entries)`; the delimiter `\\0` denotes one literal NUL byte, not two printed characters. All entries sorted lexicographically by raw UTF-8 bytes of the normalized tuple (root, ownership, path).

Each entry: `u32_be(root_byte_length) || root_utf8_nfc || u32_be(ownership_byte_length) || ownership_utf8_nfc || u32_be(path_byte_length) || relative_path_utf8_nfc || u64_be(byte_size) || sha256_raw_32_bytes`.

All counts/lengths are unsigned and bounded by implementation-approved limits; reject overflow, invalid byte sequences, field-boundary ambiguity, duplicates and inconsistent metadata. Empty inventory has a deterministic serialization but is invalid for Webcore until explicitly permitted by its contract. Fingerprint presentation may be lowercase hex; comparison operates on decoded digest bytes.

The exact format version, formal identifier registry, canonical JSON manifest field names and fixture digest vectors remain subject to technical validation and user acceptance. Existing `PackageContract::integrityIdentity()` is **not** a substitute: it represents delivered inventory and omits root-aware complete target state.

## 5. Proposed portable path policy
- Logical authorized roots initially proposed: `APP_ROOT` and `PUBLIC_ROOT` for Webcore files; actual physical mapping is deployment-specific and must be verified independently.
- Inventory paths are relative, slash-separated and canonical; reject absolute/drive/UNC paths, empty or dot segments, traversal, NUL/control characters, ADS syntax, Windows device names, trailing dots/spaces, symlinks/reparse-based containment bypass and any unauthorized or protected-path overwrite.
- Require valid UTF-8 NFC; if NFC validation is unavailable, fail closed on non-ASCII paths rather than accepting ambiguous normalization.
- Reject duplicate (root, relative path) even across ownership claims and reject portable case-fold collisions within a root. Equal relative paths in distinct roots remain distinct.
- Canonical roots, ownership and paths must be validated before physical write. No cross-root alias may bypass containment or ownership policy.

## 6. Source verification and target planning
Preflight remains non-mutating:
1. Validate package archive, manifest contract, declared delivered inventory and hashes, owner, target root mapping and bounded resources.
2. Load source inventory from committed, integrity-linked local evidence. Verify installed-state/release/source fingerprint association and compare actual source files against claimed hashes; stored fingerprint alone does not prove current bytes.
3. Validate package applicability to source identity and declared target requirements.
4. Build the complete planned target map: delivered files replace or add the declared targets; retained files must be covered by complete target inventory and verified live content; absent source paths do not authorize deletion.
5. Reject missing/unverifiable retained files, unexpected payload records, ownership mismatch, conflicting target destinations and unauthorized removals or renames.
6. Calculate expected canonical target fingerprint and compare with package-declared digest before authorizing application.
7. Persist immutable operation plan and sufficient source/target/recovery evidence before mutation.

## 7. Application and resulting-state verification
Apply only the authorized serialized plan through existing mutex, maintenance, ownership, migration, health and recovery machinery. After apply, enumerate and hash the complete actual target-owned file state, reject missing or unexpected owned paths according to accepted path/ownership rules, recompute the fingerprint, and require equality with the expected target fingerprint. Commit target release and installed-state identity only after all applicable lifecycle gates pass. A fingerprint match alone does not establish database or module compatibility.

A full and partial delivery targeting the same authoritative file state must yield the same target fingerprint; their package artifact identities may differ. The same release identity with different file-state fingerprints is a conflict unless an explicitly accepted release-identity equivalence policy applies. Different release identities may legitimately refer to identical file bytes.

## 8. Proposed state and recovery evidence
Persist the complete current source/target inventory as a protected, integrity-linked auxiliary artifact referenced by committed installed state; persist the fingerprint alongside release and migration/schema identities. During an incomplete operation, preserve original committed inventory, intended target inventory/fingerprint, operation journal and recovery requirements. Never treat filesystem/database transitions as atomically rollbackable without proof. Retry/recovery must revalidate actual content.

Existing manifest v1/v2 and installed-state contracts remain unchanged. New inventory requirements require a separately versioned manifest contract; **v3 is a candidate label only**, not a retroactive v2 extension.

## 9. Legacy baseline establishment (UNRESOLVED)
Older installations without complete committed source inventories cannot be assumed valid from a scan of current files alone. Define a separately accepted source-inventory establishment route using defensible local release evidence, with explicit provenance, compatibility checks, drift detection and safe rejection. A full package or verified local baseline may be relevant, but no second package input is automatically mandatory. Until such a route is accepted, partial application lacking authoritative source evidence fails closed.

## 10. Removal and rename (UNRESOLVED)
Missing from target inventory never implies delete. Removal must be explicitly declared and source ownership proven; rename is explicit add-plus-remove. The final grammar, live reference checks, backup/recovery semantics and handling of stale owned paths require separate accepted rules before enabling these mutations.

## 11. Acceptance evidence required
- Deterministic fixed test vectors for canonical serialization, field boundaries, lengths, ordering, domain/version separator and hash equality.
- Cross-platform equivalence, Unicode policy, split-root mapping and collision/path rejection.
- Full/partial target equivalence with different package IDs.
- Retained-file drift, absent source evidence, unexpected files, unauthorized removal/rename and tampered fingerprint rejection.
- Proper lifecycle operation mutex, preflight non-mutation, recovery evidence and installed-state commit gating.
- v1/v2 unchanged; new manifest version explicitly rejected by old readers; offline-only behavior.
- CLI and System Manager yield equivalent verification decisions.

## 12. Promotion blockers and next gate
Before promotion, reconcile (a) exact manifest v3-or-later grammar and canonical digest vectors, (b) authoritative legacy source-inventory establishment, and (c) explicit removal/rename and recovery behavior. Confirm persistence location/authority and operator error mapping as part of focused technical review. No implementation or domain expansion follows merely from materializing this candidate.

Next: focused GPT/user semantic review with technical feasibility evidence; then explicit acceptance/promotion of the bounded contract. Seven-Tab WU5 and historical forward-update remain blocked unless their own accepted dependencies are satisfied.
