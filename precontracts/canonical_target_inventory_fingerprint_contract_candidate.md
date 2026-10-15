# COPOT — Canonical Target Inventory & Fingerprint Contract Candidate
Date version: 2026-10-10 21:47:00 WIB

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

## 13. Focused technical reconciliation delta (PROPOSED / NOT ACCEPTED)
Codex reported `CHANGES PROPOSED` following read-only feasibility review. The following exact technical details are proposals pending semantic acceptance and do not amend current v1/v2 authority.

### 13.1 Manifest contract v3 candidate
Preserve manifest v1 and v2 serialization, strict-reader behavior, and legacy delivered-payload inventory unchanged. Propose a separately versioned manifest contract (`3` provisional) with:
- `coverage`: `full` or `partial`.
- Existing `inventory`: delivered payload files only.
- `target_inventory`: `serialization_version`, 64-character lowercase-hex `fingerprint`, and complete `entries`, with `root`, `ownership`, `path`, `byte_size`, `sha256`.
- `source_state_requirements.allowed_inventory_fingerprints`: accepted source fingerprint constraints for partial delivery.
- `removals` and `renames`: explicit arrays, empty until enabled under accepted mutation policy.

Field order, schema types, canonical JSON grammar, and compatibility vectors must be finalized separately. Manifest grammar version and target-inventory encoding version are independent.

### 13.2 Canonical byte-level fixtures (TECHNICAL EVIDENCE)
SHA-256 input is the byte concatenation `ASCII("COPOT-TARGET-INVENTORY") || NUL || u8(1) || u8(1) || u32_be(entry_count) || entries`. Each entry is `u32_be(root_byte_length) || UTF8_NFC(root) || u32_be(ownership_byte_length) || UTF8_NFC(ownership) || u32_be(path_byte_length) || UTF8_NFC(path) || u64_be(byte_size) || raw_SHA256_32_bytes`. Sort by bytewise (root, ownership, path); reject duplicate (root, path) and ambiguous or noncanonical encodings.

Vector 1: zero entries; serialized hex:
`434f504f542d5441524745542d494e56454e544f525900010100000000`
SHA-256: `77171ef0d605ac0f272046e606b204d74c330842b8113b7246f46fa2de46030b`.
The encoding accepts the empty set, but Webcore package semantics reject an empty target inventory.

Vector 2: one entry `APP_ROOT / package-owned / app/a.txt / 3 / SHA256("abc")`; serialized hex:
`434f504f542d5441524745542d494e56454e544f525900010100000001000000084150505f524f4f540000000d7061636b6167652d6f776e6564000000096170702f612e7478740000000000000003ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad`
SHA-256: `5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3`.
These are proposed reproducible fixtures, not complete implementation acceptance.

### 13.3 Legacy source inventory establishment (PROPOSED)
- Existing local release inventory is usable only when its protected complete inventory/fingerprint, ownership, committed release and manifest identities, and actual re-hashed files all reconcile.
- A new-format **full target package** may establish a committed target inventory baseline after successful complete resulting-state verification, without requiring a second source package. It cannot silently authorize deletion of unknown legacy files or claim cleanliness while unresolved source-owned drift may remain.
- Without authoritative baseline or a provably valid full transition, partial application must fail closed. Unknown files are unresolved ownership/drift, not automatically operator-owned and not automatically deletable.
- Proposed evidence for subsequent partial operations: committed complete inventory, fingerprint, release identity, manifest identity, logical-root mapping, current live-file rehash, matching package source requirements and explicit drift classification.

### 13.4 Removal and rename (PROPOSED)
- Explicit removal record: `root`, `path`, `ownership`, `expected_source_byte_size`, `expected_source_sha256`, and `reason` (`obsolete|replaced|renamed`).
- Removal requires provenance in authoritative source inventory, verified actual hash/size, ownership, containment and protected-path checks, and durable pre-mutation recovery artifact.
- Rename is an auditable relationship resolved as verified addition then verified removal, not automatic rename inference. Preserve original file before removal; verify new destination is unoccupied by unrelated content.
- Journal action order and cursor, recovery artifacts, source and target fingerprints. Interrupted operations remain non-finalized and require identity-checked reconciliation for safe retry. Reuse existing mutex/maintenance/recovery rather than inventing a second engine.

### 13.5 Open semantic choices before promotion
1. Confirm final manifest contract version and complete ordered JSON schema.
2. Confirm protected auxiliary inventory artifact linked to committed installed state, including persistence location and retention policy.
3. Confirm initial logical root registry `APP_ROOT` and `PUBLIC_ROOT` and portable Unicode/path constraints.
4. Choose unknown-legacy-file policy for full transitions versus partial fail-closed behavior.
5. Choose whether removal requires explicit operator confirmation beyond normal apply authorization.
6. Confirm rename as auditable add-plus-remove.
7. Define retention and safe retry of removed-file recovery artifacts.
8. Define internal and operator-visible classifications for missing source inventory, drift and failed target proof.

Any unresolved choice remains **PROPOSED**; no implementation, promotion, or historical runtime mutation follows from this addition.

## 14. User-accepted semantic decision set (2026-10-10)
The user explicitly accepted the eight bounded recommendations after the technical delta review. This acceptance resolves their **semantic direction**; it is not contract promotion, finished executable grammar, implementation approval for expanded scope, or test acceptance.

1. New manifest contract **v3**; preserve v1/v2 without modifying their grammar. Final ordered field schema and encoding fixtures must undergo implementation-ready specification validation.
2. Committed lifecycle state references a protected auxiliary complete source inventory; its content integrity and release binding are verified. Physical storage path and protection details are technical implementation decisions subject to review.
3. Initial logical root registry: `APP_ROOT` and `PUBLIC_ROOT`, with verified deployment mapping and portable path constraints.
4. Unknown legacy files are never automatically deleted or accepted as clean target state. Partial transition fails closed when source/result proof is impossible; a full transition can proceed only when it independently proves the complete resulting owned-file state without unauthorized cleanup.
5. Destructive removal requires explicit operator confirmation in addition to ordinary transition authorization.
6. Rename is an auditable declaration executed as verified add plus verified remove, not inferred from path differences.
7. Removed-file recovery evidence is retained at least until both finalization and recovery closure. Exact later retention/cleanup policy remains a separate technical/operational specification item; never purge implicitly.
8. Distinct internal diagnoses and safe operator-facing classifications for missing source inventory, source drift and target-proof mismatch; preserve existing public error contracts unless an accepted amendment changes them.

**Remaining promotion readiness checks:** reconcile the complete ordered v3 JSON grammar, verified canonical binary test vectors and exact linkage to package identity; demonstrate workable source inventory storage and legacy establishment; specify removal confirmation, retention, and recovery operation journal integration; reconcile the accepted Package Lifecycle contract dependencies. Technical feasibility findings are not evidence that these tests have run. This artifact stays `CONTRACT CANDIDATE / NOT PROMOTED` pending separate promotion review.

## 15. Bounded implementation-ready technical specification (REVIEW CANDIDATE)
This section concretizes the eight user-accepted semantic decisions and previously audited technical feasibility. It is **a proposed executable specification for review**, not promotion or implementation authorization. When conflicting with earlier proposal sections, this newer section is the review target; the earlier sections retain decision provenance.

### 15.1 Manifest contract version 3, extension and identity binding
- Version `3` is selected for the candidate; existing v1/v2 grammars and reader pathways remain byte-for-byte compatible in semantics. Old readers must reject v3, not misparse it.
- Proposed v3 inventory-related field order after existing v2-compatible prefix: `coverage`, `target_inventory`, `source_state_requirements`, `removals`, `renames`. The existing `inventory` continues describing only archive-delivered entries. Before promotion, derive the **full** ordered JSON schema from exact v2 reader fixtures; do not assume this suffix alone is sufficient.
- `coverage` is required, exact enum `full|partial`.
- `target_inventory` required object with ordered fields `serialization_version` (integer `1`), `fingerprint` (64 lowercase hexadecimal), `entries` (nonempty array for Webcore). Each record has exact ordered fields `root` (registered enum), `ownership` (registered enum), `path` (canonical normalized relative UTF-8 NFC path), `byte_size` (JSON integer, nonnegative and representable exactly within runtime bounds), `sha256` (64 lowercase hex). Reject unknown/duplicate keys and noncanonical representations.
- `source_state_requirements` required object: `allowed_inventory_fingerprints` as a unique list of lowercase 64-hex fingerprints. A partial package must have a nonempty source fingerprint allowlist plus matching source release/compatibility requirements inherited from the manifest. A full package may use an empty allowlist only if its accepted transition/source-state policy authorizes it; no automatic bypass of source compatibility.
- `removals` and `renames` are required arrays (possibly empty); nonempty entries require accepted explicit-destructive-action flow, including operator confirmation. `renames` is a semantic relationship between source and target paths with an unambiguous link to the removal and added delivered target entry; no implicit filesystem rename.
- The enclosing package integrity identity must bind the exact v3 manifest including declared target inventory and fingerprint; this is integrity binding, not publisher-origin attestation. File-state fingerprint itself excludes release identity, manifest contract version, requirements, migrations and package ID.
- Valid full coverage means *all* target package-owned file content is delivered, not that unknown legacy files can be deleted. All supplied payload destinations must map one-to-one to declared target records and be verified independently.

### 15.2 Canonical binary encoding v1 and validation
Version 1 byte stream is exactly the section 13.2 domain-separated encoding: `COPOT-TARGET-INVENTORY` ASCII followed by one `00` byte, `u8(1)`, `u8(1)` algorithm SHA-256, unsigned `u32_be(entry_count)`, then sorted length-prefixed entries. An entry serializes root, ownership, path as three `u32_be(byte_length)+UTF8_NFC_bytes` fields, size as `u64_be`, digest as 32 raw bytes.
- Canonical text must already be UTF-8 NFC; **reject** noncanonical input rather than silently normalizing two distinct declared names to one target. Implementations without dependable NFC validation reject non-ASCII names until Unicode support is validated.
- Root/ownership are registered case-sensitive ASCII tokens (`APP_ROOT`, `PUBLIC_ROOT`, `package-owned` initially). Every entry must be an allowed Webcore-owned logical destination; no protected/operator/module-owned path.
- Sort by unsigned bytewise lexical comparison of the root byte string, then ownership, then path. Reject duplicate (root,path) irrespective of ownership. Reject Windows-portability casefold collisions within each root, even on Linux. Reject traversal, backslashes after separator normalization, absolute/UNC/drive paths, ADS, reserved Windows names, trailing dot/space segments, controls, NUL and symlink/reparse containment bypass.
- Reject length/count/size overflow, invalid digest or hex, ambiguous slash/path mapping, and files not provably contained inside their mapped logical root.
- Hex fixture A (empty) `434f504f542d5441524745542d494e56454e544f525900010100000000` hashes to `77171ef0d605ac0f272046e606b204d74c330842b8113b7246f46fa2de46030b`.
- Hex fixture B (single `APP_ROOT, package-owned, app/a.txt, byte_size=3, sha256(abc)`) `434f504f542d5441524745542d494e56454e544f525900010100000001000000084150505f524f4f540000000d7061636b6167652d6f776e6564000000096170702f612e7478740000000000000003ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad` hashes to `5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3`.
- Independently recomputed: fixture A 29 bytes, fixture B 111 bytes, both SHA-256 matches. These two fixtures are necessary but not sufficient acceptance testing.

### 15.3 Committed source inventory authority and legacy establishment
- Accepted persistence model: protected auxiliary **complete inventory artifact**, referenced by committed installed-state identity (release, manifest contract identity, mapped logical-root identity, inventory serialization/fingerprint, and applicable lifecycle/migration evidence). Exact filesystem location, protection/atomic-write protocol and ownership of the auxiliary artifact are implementation review items; they cannot be guessed.
- Every partial transition must verify the committed artifact's integrity linkage and **re-hash actual relevant live-owned files**, including retained paths, before mutation. Additional files require explicit classification; unknown ownership must not be presumed operator-owned.
- For legacy installation with no committed complete inventory, **no scan-only promotion** is allowed. Existing local release evidence can establish authority only when complete inventory, fingerprint, release binding, ownership and actual bytes independently reconcile.
- Alternative single-package bootstrap: a v3 full package provides all target-owned files, proves target state after successful application, and then persists the new committed complete inventory. This route does **not** inherently prove old unknown files owned, allow their removal, or justify a clean complete target claim when unknown files may affect the owned-set boundary.
- If unresolved additional files overlap with the claimed package-owned state or invalidate completeness, fail closed. A full transition may proceed with unrelated unknown files untouched **only after** classification and post-apply complete target proof under accepted ownership policy. Partial transition without trusted source inventory fails closed.

### 15.4 Explicit deletion, rename, consent and recovery
- Removal record proposes exact fields: `root`, `path`, `ownership`, `expected_source_byte_size`, `expected_source_sha256`, `reason` (`obsolete|replaced|renamed`). The specified file must exist in authoritative committed source inventory with matching live content and package ownership.
- Explicit operator confirmation for destructive actions is separate from package upload/preflight and ordinary apply authorization. Confirmation binds the immutable prepared operation identity and exact removal list; stale plans require re-confirmation.
- Rename is first-class **audit metadata** only: one verified add and one verified remove in the same operation, linked by source/target roots and paths. The added file must be declared/delivered and verified; reject unrelated existing destination and ownership collisions.
- Journal immutable source/target identities, target fingerprint, precise action sequence/cursor, confirmation evidence and recovery artifacts before mutation. Verify recovery copy integrity **before removing** source bytes. Prefer stage/verify new target, capture/verify original recovery, write/verify new target, remove/verify source; finalize only after complete target inventory and downstream health gates pass.
- Interruption leaves non-finalized/INDETERMINATE operation status until existing recovery coordinator reconciles identity and actual files. Retry may not infer success from cursor alone. The protected recovery evidence for removed files must remain at least through **both** finalization and recovery closure. Post-closure disposal is a separate accepted retention/cleanup policy, never implicit.
- Reuse existing lifecycle mutex and recovery domains. No parallel engine, no assertion of atomic cross-filesystem/database rollback.

### 15.5 Error and compatibility classifications
Internal reason classes proposed: `source_inventory_missing`, `source_inventory_integrity_failure`, `source_state_drift`, `source_ownership_unresolved`, `target_inventory_invalid`, `target_fingerprint_mismatch`, `removal_confirmation_required`, `recovery_evidence_unavailable`. Public CLI/System Manager projection must map them safely into existing accepted error contracts unless an explicit API contract change is approved. No leaked sensitive physical paths or arbitrary payload content.
- v1/v2 manifest and delivered-payload semantics remain unchanged; v3 requires new-format resulting-state proof. Existing installations do not automatically inherit v3 authority. Unknown new version is rejected by old readers.
- Offline-only operation uses local staged ZIP, metadata, installed-state/recovery evidence and actual files. Local hashes cannot authenticate a publisher.

### 15.6 Contract promotion review checklist and remaining implementation details
Before promotion, obtain technical confirmation against actual v2 ordered grammar, exact v3 schema including fields inherited from v2, bounded parser number handling and unknown-key rules, non-ASCII path support capability, source-inventory artifact protection/atomicity, existing recovery coordinator interception points, and CLI/System Manager classification compatibility. Execute deterministic fixed-vector tests plus positive/negative cross-platform and full/partial lifecycle tests under authorized validation.
This detailed specification is **materialized for technical review**, not itself technical proof or promotion. No source, runtime, database, package or release mutation is authorized by it.

## 16. Final-readiness correction (CANDIDATE, not promotion)

This section reconciles the read-only Codex final readiness result `CANDIDATE CORRECTIONS REQUIRED`. It resolves proposed contract behavior and establishes acceptance criteria; it does not claim existing implementation supports v3 or that code-level tests have passed. Existing v1/v2 behavior remains unchanged. When an older proposal is less precise, this section is the latest candidate specification.

### 16.1 Manifest v3 ordered grammar and strict interpretation
Exact proposed **top-level order**, with no undocumented fields:
1. `package_type`
2. `manifest_contract_version`
3. `target_webcore_version`
4. `release_identity`
5. `source_tree_identity`
6. `source_compatibility`
7. `runtime_compatibility`
8. `inventory`
9. `migration_declaration`
10. `target_requirements`
11. `coverage`
12. `target_inventory`
13. `source_state_requirements`
14. `removals`
15. `renames`

For the first ten fields, the v3 contract **inherits their currently accepted v2 type, nested field-order and validation semantics**, except that `target_requirements` is required in v3 and must use its already-established v2 structure. No inferred relaxation of v2 fields is permitted. The parser must validate this exact order, including nested objects, and disallow duplicate/unknown keys at every depth; associative `json_decode()` without duplicate-key detection is insufficient.

Added fields: `coverage` is exact lowercase string enum `full|partial`; `target_inventory` is an object ordered `serialization_version` (integer exactly 1), `fingerprint` (lowercase hex string of 64 characters), `entries` (nonempty array). Each entry is an object with ordered `root`, `ownership`, `path`, `byte_size`, `sha256`. The first three are canonical strings constrained by the root/ownership/path policy; `byte_size` is a nonnegative JSON integer whose exact value fits both the u64 inventory grammar and supported PHP integer arithmetic; `sha256` is lowercase 64-character hex.

`source_state_requirements` is an object with exactly one required key, `allowed_inventory_fingerprints`, whose value is an array of unique lowercase 64-character hex strings. `partial` requires at least one source fingerprint and matching independent source compatibility. `full` may use an empty allowlist, but the accepted `source_compatibility`, `source_tree_identity`, migration and relevant installed-state gates **still apply**; empty fingerprint allowlist never means unconditional applicability.

`removals` is an array of ordered objects containing `root`, `path`, `ownership`, `expected_source_byte_size`, `expected_source_sha256`, `reason` (exact enum `obsolete|replaced|renamed`). `renames` is an array of ordered objects containing `source_root`, `source_path`, `target_root`, `target_path`. Every rename must resolve to exactly one verified declared source removal and one delivered target addition; duplicated or contradictory action identities are rejected. Empty arrays mean no destructive actions are requested. Nonempty arrays trigger explicit plan-bound operator confirmation and recovery prerequisites.

Every object must reject additional fields, duplicate keys, invalid string encodings, noncanonical path values and mismatched types; reject JSON floats, negative or nonintegral sizes, numeric strings, values above runtime-safe integers, and parser overflow. JSON key and array order must not silently change the meaning of the fingerprint; field-order adherence is a manifest grammar compatibility rule, while canonical fingerprint ordering is defined independently by binary serialization v1. The v3 package identity must cryptographically bind complete manifest bytes or an unambiguous canonical manifest representation, including `target_inventory` and its fingerprint, plus the existing delivered-payload integrity checks. Exact inherited nested v2 field schema must be evidenced from the reader/fixtures before promotion rather than invented here.

### 16.2 Source Inventory Artifact Store and commit protocol
Proposed bounded ownership: `CommittedLifecycleStateStore` owns a durable, authoritative **reference** to the complete inventory artifact and its associated release, manifest-contract, ownership-policy, canonical serialization, logical-root mapping and fingerprint identities. A separate protected `SourceInventoryArtifactStore` stores the complete inventory bytes. `RecoveryArtifactStore` remains reserved for operation-specific recovery evidence, not the normal committed source inventory.

Artifact locations must be inside an installation-controlled, non-public, non-module-owned protected state area resolved through accepted deployment/storage configuration. Mere placement outside `PUBLIC_ROOT` does not prove protection; enforce denied public routing, path ownership, symlink containment, permissions and read-back checks. Avoid guessing a hard-coded absolute directory in this contract.

Publication protocol: (1) prepare complete inventory and validate canonical digest, (2) write a unique immutable artifact via temporary file within same supported filesystem, flush as supported, atomically publish its name, re-read/rehash, (3) persist committed state atomically with the exact artifact identity/reference and all linkage fields, (4) re-read committed state and artifact and verify linkage and live-tree compatibility, (5) only then report baseline committed. If a failure occurs before the committed reference changes, retain prior authority and clean unreferenced artifact only through accepted recovery/cleanup. If failure occurs after reference commit, fail closed until reconciliation confirms authority; do not silently fall back to an unrelated scan or claim success. Atomicity applies per durability boundary only; no unproven cross-file/DB atomic guarantee.

Legacy baseline: accept independently verified existing authoritative local release inventory, or establish one after successfully completed v3 full-package target proof. Both require explicit ownership/drift classification. Partial package without a valid committed source inventory must fail closed. Unknown legacy files are never automatically deletable or assumed operator-owned.

### 16.3 Immutable action identity, destructive confirmation and recovery
The prepared plan must include a deterministically bound list of all actions (kind, source and target roots/paths, expected source bytes/hash, expected target bytes/hash, ordered dependencies), target/source fingerprints, recovery-domain identity, and applicable installed-state/migration identities. A path-only cursor does not substitute for action identity. Any plan change requires re-preflight and invalidates confirmation.

Destructive consent is an operator-visible explicit confirmation for the exact immutable plan and removal set, recorded separately from ordinary package apply permission. It is mandatory before any deletion or rename-as-add/remove. Confirmed plans must also satisfy existing permission/authority checks.

Before a removal, reconcile source ownership with committed source inventory; rehash actual source bytes; capture immutable recovery content and verify the recovery digest. Journal action identity and progression before mutating. Rename: stage and verify new content, preserve/verify source, add and verify new destination, remove verified source, rehash final target state. Refuse unrelated destination collisions.

Interrupted or post-mutation mismatched operations remain non-finalized/INDETERMINATE and require recovery coordination against actual bytes and action/recovery identity; do not assert atomic filesystem+database rollback. Removed-file recovery artifacts remain at least through both successful finalization and recovery closure. Deletion of recovery artifacts thereafter requires separately accepted cleanup policy.

### 16.4 Typed lifecycle outcomes and safe projections
Pre-mutation checks must return non-mutating typed failure with machine-readable internal reason; map to existing accepted public statuses until a public API change is separately accepted:
- `source_inventory_missing`, `source_state_drift`, `source_ownership_unresolved`, `target_inventory_invalid`, `target_fingerprint_mismatch`: `rejected` where supported.
- `source_inventory_integrity_failure`, `recovery_evidence_unavailable`: `blocked/unavailable` depending on existing public status grammar.
- `removal_confirmation_required`: pre-mutation rejection plus safe confirmation-needed reason.
Post-mutation `target_fingerprint_mismatch` or `recovery_evidence_unavailable`: `INDETERMINATE / recovery-required`, **never** ordinary `invalid_package` or clean preflight rejection. Shared lifecycle service must own typed result; CLI and System Manager must preserve equivalent outcome rather than collapsing it into catch-all exceptions. Exact public status spelling and wire-level error codes remain governed by accepted public contracts; any new externally exposed code needs separate acceptance.

### 16.5 Promotion readiness evidence and non-goals
Promotion review must verify the complete inherited v2 nested field schema and parser-order invariants; exact v3 parser rules (including duplicate-key detection), canonical fixture vectors, source inventory artifact write/read-back/link identity, safe recovery integration points, and accepted public-status mappings. Source implementation and end-to-end runtime acceptance are **subsequent implementation evidence**, not circular prerequisites for writing/promoting a coherent contract. This candidate does not grant destructive-action execution or expand existing Package Lifecycle and Backup & Recovery authority. Contract promotion remains a separate explicit decision.
