# COPOT — Shared Destructive Plan Confirmation Operator-Input Contract
Date version: 2026-10-11 13:48:08 WIB

Status: ACCEPTED / COORDINATED CONTRACT PROMOTION
Prospective manifest-v3 input authority only, not implementation. Companion contracts: Canonical Target Inventory & Fingerprint, docs/28 Package Lifecycle and docs/31 Backup & Recovery.

## 1. Shared authority
One shared lifecycle service validates destructive consent. CLI and System Manager merely transport equivalent typed proof, never grant removal permission independently. Existing manifest v1/v2 inputs and all public output statuses remain unchanged. Existing standalone boolean `confirmed=1` or bare CLI confirmation is invalid for v3 removal/rename.

## 2. DestructivePlanConfirmation v1
Required semantic fields:
- `operation_identity`: nonempty exact durable operation identity string.
- `plan_identity`: nonempty canonical scheme-prefixed immutable prepared-plan identity string, bound to package evidence scheme+digest, owned roots/paths, source/target fingerprints and operation/recovery identity.
- `removal_set_digest`: exact 64-character lowercase SHA-256 hex string computed by Canonical Target Inventory contract removal-set serialization v1.
- `actor_authorization_evidence`: authenticated trusted actor/session capability or opaque service-verifiable reference, not self-declared permission and never stored raw credentials.
- `confirmation_version`: typed integer exactly 1, never string or boolean.

Reject missing, unknown, duplicate, malformed, wrongly typed, stale, mismatched and replayed fields. Verify actor's current effective authority and exact prepared plan/removal set before consent acceptance and immediately before any file deletion. Persist durable evidence of accepted consent bound to immutable operation/plan/removal digest and actor authorization outcome without saving credentials. Plan or source changes invalidate confirmation.

## 3. CLI and System Manager transport
For manifest-v3 destructive operations CLI and System Manager MUST transport **all five fields** to the same shared lifecycle service. CLI may employ explicit flags or protected structured input; System Manager may employ a versioned HTTP request object. Surface byte transport differs but yields identical typed semantic proof and identical permission checks. Existing boolean-only request is not enough. Transport adapters cannot create alternate lifecycle authority. Exact CLI flag spelling/HTTP envelope is a bounded implementation detail and cannot weaken this contract.

## 4. Error boundary
Missing/invalid/mismatched consent pre-mutation -> existing rejected public status; missing necessary recovery evidence pre-mutation -> blocked. Post-mutation inconsistency -> indeterminate/recovery-required projection. Preserve public invalid_package, rejected, blocked, indeterminate, cleanup_pending, failed, unavailable vocabulary without introducing new output fields or revealing internal reason codes. No credential persistence or implicit destructive consent.

## 5. Implementation gate
This contract authorizes specification of a future shared typed input, not implementation, runtime execution, source deletion or deployment. Physical restoration remains with Backup & Recovery.
