# COPOT — Canonical Target Inventory & Fingerprint — Normative Contract Review
Date version: 2026-10-11 12:10:24 WIB
Status: REVIEW ONLY / NOT PROMOTED / NOT IMPLEMENTATION-AUTHORIZED
Provenance: authoritative main e7ab022b56c40a66cf6118935b576e633646a055, user-accepted coordinated promotion model, precontracts/canonical_target_inventory_fingerprint_coordinated_normative_review_draft.md.
This draft becomes normative only if promoted together with the linked docs/28 and docs/31 deltas.

## Authority
OFFLINE-ONLY. A local operator-supplied ZIP is the only update package. Source inventory is installed-state metadata, not a second ZIP. SHA-256 checks consistency, not publisher authenticity. v1/v2 manifest behavior is unchanged. Preserve one active operation per installation and accepted ownership/migration/health/finalization gates.

## Manifest v3, required ordered top-level keys
1. package_type: string, exactly 'copot-webcore'.
2. manifest_contract_version: JSON integer, exactly 3.
3. target_webcore_version: valid Webcore release-version string under existing PackageVersion rules.
4. release_identity: nonempty, whitespace-trimmed, no control bytes, opaque string.
5. source_tree_identity: nullable opaque string, same existing identity constraints.
6. source_compatibility: ordered object [minimum_source_version, maximum_source_version], existing accepted version-range types and validation.
7. runtime_compatibility: ordered object [minimum_php_version, minimum_database_versions, required_extensions], types and validation match v2 value objects.
8. inventory: delivered-payload nonempty array, each ordered record [path, byte_size, sha256, ownership] with accepted delivered-payload v2 types/ownership; it does not cover retained files.
9. migration_declaration: ordered object [declares_core_migrations, declaration_identity], existing accepted types.
10. target_requirements: required ordered array, each ordered object [kind, owner, identity, operator, value, mandatory] as in PackageTargetRequirements; string fields except nullable string value and boolean mandatory. An empty array is valid if no requirements, but does not bypass applicable migration authority.
11. coverage: exact lowercase string 'full' or 'partial'.
12. target_inventory: ordered object [serialization_version, fingerprint, entries]; version integer 1, fingerprint 64 lowercase hex, entries nonempty array of ordered objects [root, ownership, path, byte_size, sha256].
13. source_state_requirements: ordered object [allowed_inventory_fingerprints], an array of unique lowercase 64-hex; partial requires nonempty and verified committed source identity, full may be empty without bypassing source compatibility.
14. removals: array of ordered objects [root, path, ownership, expected_source_byte_size, expected_source_sha256, reason], with reason enum obsolete|replaced|renamed; empty unless explicit operator-confirmed removal.
15. renames: array of ordered objects [source_root, source_path, target_root, target_path]; each corresponds to exactly one removal and one delivered addition and is auditable, not inferred.

The complete key sequences above are required, including nested order. Reject unknown or duplicate JSON keys at any depth, arrays in place of objects, reordered keys, floats/exponential sizes, numeric strings, negative/overflow values, malformed UTF-8/NFC. Ordinary associative json_decode alone cannot detect duplicate raw keys; a duplicate-aware layer is mandatory. Numeric values must round-trip exactly and fit current safe runtime integer range as well as encoded u64. All constraints of accepted v2 field value objects remain effective, not loosened.

**Grammar completeness review note:** the precise nested schema of minimum_database_versions and required_extensions, together with any v2 builder serialization specifics, must be verified directly against the authoritative value objects before promotion; this text does not invent unsupported nested keys.

## Package identity, inventories and target-state proof
Delivered inventory continues to have existing v2 archive checksum verification. In v3, package artifact identity must bind an unambiguous byte serialization of the complete manifest, including target inventory, fingerprint, compatibility and destructive declarations, plus payload identity; target inventory fingerprint separately hashes only target-owned file-state fields. A concrete manifest canonicalization/binding procedure must be verified before promotion; simply hashing current delivered inventory identity is insufficient.
Target inventory contains every target Webcore package-owned file, changed plus retained. Initially logical roots APP_ROOT and PUBLIC_ROOT, owner package-owned. Reject protected paths, traversal, absolute/drive/UNC/ADS paths, reserved Windows names, trailing dots/spaces, symlink/reparse escapes, duplicate (root,path) and same-root portability casefold collisions. UTF-8 NFC required, non-ASCII fail-closed where NFC validation is unavailable. Root mapping is installation-specific and must be validated before physical writes.
Full coverage delivers all target-owned bytes. Partial delivers changed bytes and requires committed complete local source inventory, fingerprint allowlist match, source identity/ownership proof and live-file rehash. An unknown extra file cannot be silently classified operator-owned or removed; completeness proof must fail closed when ambiguity affects owned state. A full v3 successful transition may establish the first authoritative inventory baseline, but never retroactively legitimizes unknown legacy bytes.

## Binary fingerprint serialization v1
SHA256(ASCII('COPOT-TARGET-INVENTORY') || byte 0x00 || u8(1) || u8(1=SHA256) || u32_be(entry_count) || entries).
Sort entries by raw UTF-8 byte ordering of (root, ownership, path). Each entry is u32_be(root length bytes) + root UTF8 NFC + u32_be(owner length bytes) + owner UTF8 NFC + u32_be(path length bytes) + path UTF8 NFC + u64_be(size) + 32 raw bytes of file SHA256. Empty entry array has reproducible byte encoding but invalid Webcore target.
Fixtures: empty SHA256 77171ef0d605ac0f272046e606b204d74c330842b8113b7246f46fa2de46030b; single APP_ROOT/package-owned/app/a.txt, 3 bytes 'abc' SHA256 5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3.

## Source inventory authority
CommittedLifecycleStateStore records authoritative references and release, manifest, encoding, ownership policy, logical-root-mapping and inventory fingerprint identities. Private protected SourceInventoryArtifactStore contains immutable complete source inventory. Publish artifact first with same-filesystem temporary write, verified flush/readback and integrity, then commit exact reference and read back both; failure must not assert valid committed source identity. RecoveryArtifactStore remains operation-specific. No arbitrary directory scan can elevate legacy source to authority.

## Application and error boundary
Immutable preflight validates source and target fingerprint, payload, roots, inventory coverage, explicit action plan, confirmation and recoverability. Post-apply actual complete owned state is independently rehashed before advancing release identity, alongside existing migration/health/finalization gates.
Use existing public top-level lifecycle statuses and no new public wire fields absent independent authorization. Pre-mutation invalid package inventory or failure to satisfy source/ownership/confirmation is rejected or blocked under existing accepted status grammar; post-mutation fingerprint or missing recovery evidence is indeterminate/recovery-required; verified completed operation with residual housekeeping may be cleanup_pending. Detailed binding must be verified against accepted operator contract. Both CLI and System Manager consume same typed service outcome.

## Coordinated dependency
Explicit removal and rename are available only after corresponding prospective docs/28 and docs/31 amendments are co-promoted, and operator plan-bound confirmation is obtained. No implicit stale-file deletion, no competing recovery engine and no cross-domain atomic rollback promise. Promotion is separate from implementation authorization.


## Final corrective appendix — precise v3 grammar, integrity, durability, confirmation
This appendix is the current **review proposal** wherever earlier review notes leave details open; no promotion or implementation is authorized.

### Inherited field grammar, fully typed
The first ten top-level keys retain exact order already enumerated above. Their nested grammar is:
- `source_compatibility`: ordered object [`minimum_source_version`: valid version string, `maximum_source_version`: null or valid version string strictly greater than minimum, with exclusive upper bound].
- `runtime_compatibility`: ordered object [`minimum_php_version`: nonempty PHP version string accepted by existing version rules (two-component accepted and normalized to three), `minimum_database_versions`: nonempty JSON object mapping unique nonempty vendor strings to valid database version strings, `required_extensions`: nonempty JSON array of nonempty extension-name strings]. Runtime compatibility must preserve existing case-insensitive extension identity/normalization and accepted vendor constraints. **Database vendor object member order is not semantically meaningful for compatibility**, while original manifest bytes are bound by the proposed package identity below. No anonymous list is accepted as database version map.
- `inventory`: nonempty ordered array of entries, each ordered object [`path`: canonical payload relative path string, `byte_size`: nonnegative safe JSON integer, `sha256`: lowercase 64-char hex, `ownership`: accepted package ownership string]. Manifest path `.copot/package.json` itself is excluded from delivered inventory (existing v2 rule).
- `migration_declaration`: ordered object [`declares_core_migrations`: boolean, `declaration_identity`: null or nonempty identity string]. When false, identity MUST be null; when true, identity MUST be nonempty.
- `target_requirements`: ordered array of objects [`kind`: string, `owner`: string, `identity`: string, `operator`: string, `value`: string or null, `mandatory`: boolean] with existing PackageTargetRequirement domain constraints and unique requirement identity.
- `source_tree_identity` is null or valid opaque string. `release_identity` is valid opaque string. `target_webcore_version` obeys existing version validation; `package_type` exactly `copot-webcore`; `manifest_contract_version` integer 3.

Strict object key order, JSON duplicate-key detection at every depth, bounded size and safe integer parsing are mandatory for v3. This expansion does not rewrite v1/v2 parser behavior.

### Exact proposed manifest-byte identity and payload binding
The v3 manifest is located exclusively at `.copot/package.json`. Define `M =` the **exact raw UTF-8 bytes** of that manifest file obtained from verified private staging; no reserialization or whitespace/key normalization takes place for manifest artifact identity. `manifest_sha256 = SHA256(M)`. It binds all declared v3 fields, including target inventory, source allowlist, removals and renames. The manifest is expressly **excluded** from the delivered `inventory` to avoid self-reference.
Separately, the existing delivered-payload inventory must exactly cover delivered package-owned payload files (not the manifest) and each staged file's size/hash/ownership; preserve the existing manifest/payload identity verification behavior. Proposed combined **package evidence identity**: SHA256(ASCII(`COPOT-V3-PACKAGE\0`) || raw 32 bytes manifest_sha256 || raw 32 bytes of the existing delivered-payload integrity identity). Both components are fixed-width SHA-256 digests, preventing delimiter ambiguity. It is an artifact-consistency identity only; not publisher attestation. Exact compatibility with existing operation-record package identity remains a review gate.

### Durability and confirmation contract
An inventory artifact may become authoritative only after the required write, durable flush/synchronization, atomic publication and read-back integrity steps have **positively succeeded** on the configured filesystem. If required durability or atomic publication guarantees are unavailable, fail closed and do not advance committed state. No `flush where supported` fallback grants successful publication.
For destructive actions the shared lifecycle service consumes one typed plan-bound confirmation proof including `operation_identity`, `plan_identity`, `removal_set_digest`, `actor_authorization_evidence`, `confirmation_version`. Validate an exact immutable prepared plan and authorized actor at consumption, bind and durably record proof; do not accept a standalone boolean. CLI and System Manager are adapters only. Public top-level result statuses remain existing accepted statuses, no new output fields automatically authorized.
