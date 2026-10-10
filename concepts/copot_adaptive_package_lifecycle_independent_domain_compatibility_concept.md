# Adaptive Package Lifecycle & Independent Domain Compatibility
Date version: 2026-10-10 15:13:36 WIB

Status: ACTIVE / CURRENT-SESSION SEMANTIC RECONCILIATION / CONCEPT / NOT PROMOTED
Project: COPOT
Provenance: User/GPT conceptual reconciliation, 2026-10-10. TLC-01–TLC-07 are conversation inventory labels, not separate permanent planning identities.

## Boundary and primary invariant
**Package declares WHAT; Lifecycle determines WHAT CAN / MUST HAPPEN.**
Package metadata describes target identity, payload, compatibility, dependencies, expected state, ownership and integrity evidence; the package does not have to declare PATCH, UPDATE or UPGRADE. The Lifecycle interprets the installed state plus package description, determines eligibility, classification, execution plan and safety gates. No Concept discussion overrides the accepted contracts in `docs/28_package_lifecycle_migration_foundation_contract.md`, `docs/29_module_package_lifecycle_contract.md` or other domain authorities. Technical review and explicit promotion are required.

## Package representations
- Package coverage is independent of operation classification: full Webcore, partial Webcore, schema-only database, and explicitly justified composite packages are conceptual candidates.
- PATCH need not contain a full application. UPDATE and UPGRADE may be full or partial if compatibility and resulting state can be proven. A full fresh-installable COPOT distribution remains an important delivery representation but is not a universal lifecycle source requirement under this candidate.
- Every package carries machine-readable identity/compatibility/inventory information and evidence for safe verification; filename, extension and exact manifest grammar are not locked.
- Payload layout alternatives: flat files with destination mapping; folder structure mirroring logical destinations; explicit per-file source/destination mapping including deliberate rename. All normalize to one unambiguous canonical operation plan. Duplicate names in distinct destinations must not collide.
- Destinations are logical authorized roots (e.g. APP_ROOT and PUBLIC_ROOT), resolved for the actual deployment; no escape or unauthorized overwrite. Include per-file integrity, ownership and expected replacement evidence.
- Trust of publisher/distribution, archive and inventory validity, runtime compatibility, and permission to execute are separate gates. A self-consistent checksum does not independently establish trust.

## Independent but coupled lifecycle domains
- Webcore, Database and Modules retain distinct identities, lifecycle state and ownership. Updating one does not inherently require updating the others.
- Webcore expresses the minimum *capabilities* and schema requirements of the database it uses; Lifecycle evaluates them. An installed database with greater schema generation can be acceptable only if required capability and backward compatibility hold, not merely because its generation number is larger.
- Database UPDATE versus UPGRADE can be inferred by Lifecycle from requirements, installed state and compatibility impact, rather than declared as mandatory package operation metadata.
- A database package may contain a full target schema. For an existing database this is desired-state evidence, not authorization to replay full CREATE TABLE SQL indiscriminately.

## Database schema evolution
- Default is additive-first / fill-the-hole: create genuinely missing tables/columns/indexes/capabilities; do not silently mutate or remove existing schema.
- A same-named column collision is defined within the *same table/owned schema object*, not between unrelated tables; different tables are separate domains for this concern.
- Unexpected divergence against the declared canonical schema is classified and diagnosed as source/ownership defect or target drift according to evidence; it is not silently fixed by the installer.
- Intended datatype changes are explicit, independently authorized database migrations. Prefer expand/backfill/switch/retain when feasible. If in-place non-additive modification is genuinely necessary, demand a specific migration declaration, data transformation mapping, backup/recovery and compatibility boundaries. The exception itself is NOT yet accepted as a general operation.

## Lifecycle verification, safety and recovery
- Preflight verifies archive, identity, provenance/trust, dependency, source-state preconditions, destination ownership, existing domain compatibility, migration declarations, expected replacement result and recovery readiness before mutation.
- Partial packages need source-state and post-apply *resulting-state* proof to avoid creating an unrecognized hybrid version.
- Application uses accepted mutex/maintenance, domain-owned coordination, migration ledger, health, integrity and final installed-state commit gates where appropriate.
- Different domain backups/recovery procedures must not be misrepresented as one atomic filesystem-plus-database rollback.
- Potential shared protected lifecycle storage (retained source archives, snapshots, operation journals, recovery evidence) is a separate deferred installer/recovery architecture; physical location and retention are not locked.

## State and dependencies
ACTIVE conceptual review in this session, NOT CONTRACT-PROMOTED or IMPLEMENTATION-AUTHORIZED by this file. Relationship: existing Package Lifecycle Forward-Update Bootstrap Authority Reconciliation is a separate corrective workstream on HOLD / contract unpromoted, **not** another saved Concept. Current Seven-Tab WU5 remains blocked until its actual upstream authority is reconciled.
Unresolved: exact grammar/versioning, transition classification criteria, package trust anchor, partial-result proof, schema exceptions, migration execution trust, bounded historical support, recovery integration, and compatibility impact on accepted contracts.
