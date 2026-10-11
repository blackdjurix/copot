# COPOT — docs/28 Bounded Amendment Review: Manifest v3 Removal/Rename
Date version: 2026-10-11 12:10:24 WIB
Status: PROPOSED DELTA / NOT APPLIED / NOT PROMOTED
Target: docs/28_package_lifecycle_migration_foundation_contract.md (verified base sha 7363a871c50d7413d2e6159ddb572544aa026ed4)
Dependency: fingerprint normative contract and docs/31 recovery delta must be accepted together.

## Exact scope of current exclusion
The historical WU5 line approximately L250 'WU5 performs no stale package-owned deletion' remains **historically true**. Likewise historical WU deliverables/exclusions near L561 and L571 describe the accepted historical WU implementation, not a perpetual retrospective edit. Do not rewrite completed historical acceptance. At present stale-file deletion remains unavailable in the accepted implementation and accepted future authority.

## Proposed new prospective normative section
### Manifest v3 explicitly planned package-owned removal and rename
The existing broad no-stale-file-deletion rule remains the default. Only a manifest-v3 operation with an explicit removal declaration, exact validated committed source ownership, actual source file size/hash, guarded destination and protection policy, immutable complete action-plan identity, separately recorded operator confirmation of the exact destructive actions, and verified Backup & Recovery artifact may coordinate deletion. An absent file in target inventory is not sufficient. Unknown legacy files are never automatically deleted. Manifest v1/v2 behavior remains unchanged.

Package Lifecycle owns planning, action order, preconditions, consent binding, serialized application, source/target fingerprint verification, release advancement and terminal disposition. Backup & Recovery (docs/31) owns durable capture and physical restoration. No synthetic rollback engine or cross-domain atomic promise.
The immutable plan ID binds action kind, source and target root/path, before/after byte identities, source and target complete fingerprints, recovery identity and dependencies. Operator confirmation contains operation ID, immutable plan ID, canonical digest of exact removal set, actor authority evidence and confirmation version; CLI and System Manager are adapters to the **same** shared service authorization outcome. Any change to source/plan or confirmation context invalidates consent; no endpoint may unilaterally create removal authority.
Rename declares a verified source removal and delivered target addition with audited relationship. Stage and verify target, durably preserve original in recovery, add and verify new target, remove verified old bytes, verify full resulting-state fingerprint. Reject existing unrelated target collision. When failures occur after mutation, mark nonfinalized/indeterminate and reconcile with docs/31 before retry. Final release identity commits only after full target proof and existing health/migration gates.

## Conflict-resolution clause proposed for docs/28
The historical WU5 no-deletion statements remain unaltered as statements about the historical WU5 scope. The prospective manifest-v3 removal capability is a *bounded exception* effective only once this coordinated amendment and docs/31 companion are promoted and implemented under separate authorization. No global reversal of WU exclusions.

## Review requirements
Compare exact paragraphs containing 'no stale-file deletion' or 'stale package-owned deletion' and insert explicit forward-reference qualification where they could otherwise read as permanent normative exclusions. Report exact anchor locations and accepted operator permission vocabulary before promotion. No existing accepted doc is modified by this draft.

## Final corrective proposal: prospective exclusion qualification

At each historical no-deletion paragraph of docs/28 (the WU5 narrative near line 250, deliverables near 561, exclusions near 571, and WU6 exclusions near 605), retain the historical claim and add this forward-looking qualification:

"Historical WU5/WU6 excluded stale package-owned deletion. That remains true for those work units and manifest v1/v2. A future manifest-v3 capability, only after coordinated acceptance of the fingerprint and Backup & Recovery amendments and separate implementation authorization, may perform explicitly declared, source-verified, operator-confirmed package-owned removals or auditable rename-as-add-plus-remove. No inferred stale cleanup or Package Lifecycle physical-restoration authority is granted."

## Shared operator input proposal

Define one typed plan-bound operator proof containing operation_identity, immutable plan_identity, removal_set_digest, actor_authorization_evidence, and confirmation_version. The shared lifecycle service verifies these against the immutable planned actions, source state, and operator permissions. Any change invalidates confirmation. CLI and System Manager submit the same semantic proof; an unbound boolean does not suffice. This proposes an operator-input contract extension, not a new public result status. No changes to authoritative docs/28 have been made.
