# COPOT — Shared Destructive Plan Confirmation Operator-Input Contract Review
Date version: 2026-10-11 12:28:26 WIB

Status: REVIEW ONLY / FOURTH COORDINATED AUTHORITY SURFACE / NOT PROMOTED / NO IMPLEMENTATION AUTHORITY

## Scope and purpose
This bounded prospective input contract enables manifest-v3 explicit Webcore package-owned file removal/rename. Existing v1/v2 input and public output status contracts are unchanged. Promotion requires simultaneous acceptance of the new Canonical Target Inventory contract, docs/28 prospective removal exception, and docs/31 recovery amendment.

## One shared service authority
Neither CLI nor System Manager can independently approve destructive file actions. They convey one typed `DestructivePlanConfirmation` request to the existing shared lifecycle authority with required semantic fields:
- `operation_identity`: exact current durable operation identity.
- `plan_identity`: immutable prepared plan identity, binding package identity scheme+digest, source/target fingerprints, source state, ordered destructive actions, guarded roots/paths, expected before/after bytes, and recovery identity.
- `removal_set_digest`: SHA-256 over a deterministic canonical representation of the ordered, immutable plan removal actions; the representation must be bound to plan identity and validated against the accepted manifest.
- `actor_authorization_evidence`: validated actor and effective authorization grant bound to confirmation; raw credentials/secrets must not be persisted in the record.
- `confirmation_version`: explicitly versioned request contract, initially integer 1.

The shared service validates exact required fields, value types, plan/source freshness, actor permission, confirmation scope, and confirmation replay protection. The accepted immutable proof is durably recorded before any destructive action; service rechecks all relevant preconditions immediately before execution. Any change invalidates confirmation, requiring new user confirmation. A bare boolean, implicit UI checkbox, previous apply consent or unbound approval token is insufficient for v3 removals.

## CLI/System Manager compatibility
Both adapters submit semantically identical typed proof to the same shared service and receive existing accepted top-level statuses only. Exact transport/wire representation for CLI flags and System Manager request must be reconciled against their accepted operator surfaces at promotion, without new public output fields or raw internal reason-code leakage. Existing v1/v2 operator behavior is preserved.

## Negative and interruption rules
Missing, changed, stale, invalid, unauthorized or unpersisted destructive confirmation rejects pre-mutation using existing `rejected` semantics. Missing recovery evidence blocks pre-mutation. Once any mutation has occurred, errors are mapped through existing indeterminate/recovery-required reconciliation, never retroactively treated as ordinary unconfirmed preflight failure.

## Promotion gate
This input contract is an inseparable fourth normative authority surface, not a separate lifecycle implementation. Confirm the precise wire representation and canonical removal-set digest serialization before authoritative promotion; do not invent an accepted public API wire change through a review draft.
