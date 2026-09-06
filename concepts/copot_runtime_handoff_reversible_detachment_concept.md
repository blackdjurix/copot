# Runtime Handoff & Reversible Detachment

Status: PLANNING LOCKED / AUTHORITATIVE CONTRACT AMENDMENT CANDIDATE

This Concept materializes the currently accepted product/architecture direction for runtime handoff under Site Settings → System, without claiming delivered capability and without authorizing implementation.

The target is to amend the existing Runtime Registry / Multi-Installation authority so Batch 2 can project runtime participation and handoff truthfully instead of inventing lifecycle semantics in the UI layer.

## Authoritative lineage to preserve

This Concept does not replace existing authority. It is intended to reconcile with and amend the relevant boundaries in:

- `docs/34_multi_installation_isolation_foundation_contract.md`;
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`;
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`;
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md`.

Existing locked facts remain intact:

- each installation has a stable `installation_id`;
- each runtime participant has a stable `runtime_id` distinct from PID/process identity;
- Runtime Registry remains the singular runtime-participation authority;
- existing runtime states include `REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, and `INCOMPATIBLE`;
- retired runtimes are detached rather than hard-deleted by default;
- shared-state transitions remain fail-closed when runtime compatibility or participation state is unsafe;
- Site Settings is a projection and must not become a second runtime/lifecycle engine.

## Problem statement

Current Runtime Registry semantics support direct detachment, but current implementation transitions a runtime directly to `DETACHED` and does not provide a reversible handoff window.

That is insufficient for the accepted product flow where an existing installation is intentionally transferred from one serving runtime to another while preserving the installation/database state and allowing the source runtime to cancel before takeover commits.

The required amendment must therefore define runtime handoff as a lifecycle operation rather than as a presentation-only action.

## Terminology boundary

This Concept uses **Runtime Handoff** for transfer of runtime participation over one existing installation.

It must remain distinct from:

- Installer `Adopt`, which selects an existing compatible installation during installation routing; and
- Existing-Runtime Webcore lifecycle adoption/reconciliation, which establishes or reconciles committed Webcore lifecycle state.

Do not use one generic `Adopt` label for all three operations.

## Target lifecycle model

Runtime handoff is a reversible operation until takeover is committed.

The target source-runtime flow is:

`ACTIVE → DETACH_PENDING → DETACHED`

From `DETACH_PENDING`:

- `Cancel Detachment` returns the source runtime to `ACTIVE` when cancellation is still eligible;
- successful target-runtime takeover/finalization commits the source runtime to `DETACHED`;
- failure before committed takeover must leave an explicit recoverable/pending or cancelled state and must not silently create dual authority.

`DETACHED` is final for that handoff operation. A detached runtime identity must not silently resume authority through heartbeat or re-registration.

## Detachment request

`Request Detachment` starts a durable handoff operation. It must not immediately destroy source-runtime participation evidence.

Minimum durable handoff evidence should bind:

- `installation_id`;
- source `runtime_id`;
- durable handoff/detachment operation identity;
- requested timestamp;
- source runtime state and compatibility evidence at request time;
- target runtime identity when already known, otherwise explicit unbound-target state;
- current cancellation eligibility;
- finalization/commit state.

Exact persistence shape and enum names remain contract/implementation-time decisions, but the semantic distinctions above must survive.

## Cancellation

`Cancel Detachment` is a first-class operation, not a UI convenience.

Cancellation is eligible only while takeover has not been committed and no conflicting lifecycle state makes restoration unsafe.

At minimum, cancellation must fail closed when:

- a target runtime has already successfully attached/adopted and takeover is committed;
- the source runtime can no longer safely resume authority;
- a conflicting lifecycle operation is active or unresolved;
- installation/runtime identity evidence no longer matches the handoff plan; or
- authoritative state is indeterminate.

Successful cancellation restores the source runtime to `ACTIVE` under the same installation identity and closes the pending handoff operation without creating a second active owner.

## Target-runtime takeover

A new runtime may take over one existing installation only through an explicit handoff/attachment path that proves:

- the target references the same `installation_id` and intended database/namespace state;
- compatibility requirements pass;
- the source runtime is in an eligible handoff state;
- no conflicting active runtime authority exists;
- the same handoff operation identity is used through finalization; and
- takeover is committed atomically enough to prevent split-brain or ambiguous dual-active authority.

The target may have its own stable `runtime_id`; takeover must not reuse the old source `runtime_id` as a shortcut.

## No-dual-active invariant

The core invariant is:

**one installation must never reach a committed state where two independent runtime participants both believe they hold active serving authority through the same handoff.**

Temporary coordination evidence may involve more than one registered participant, but committed serving authority must remain unambiguous.

If state cannot be proven safe, handoff and cancellation must fail closed.

## Source-runtime behavior after finalization

After successful takeover commit:

- source runtime becomes `DETACHED`;
- source heartbeat/automatic reactivation remains blocked;
- source runtime must not continue normal lifecycle mutation against the installation;
- stale local files or process survival do not restore authority;
- re-entry requires an explicit future supported operation, not silent registration.

## Site Settings → System projection

After the underlying authority is amended and implemented, Batch 2 may project Runtime Handoff under Site Settings → System.

The product projection may expose:

- current `installation_id`;
- current `runtime_id`;
- runtime participation state;
- compatibility and last-seen evidence where safe;
- handoff pending state;
- `Request Detachment` when eligible;
- `Cancel Detachment` when eligible;
- takeover/finalization status and next valid action;
- concise fail-closed blocking reasons.

Site Settings must consume authoritative runtime evidence and must not infer or manufacture handoff eligibility.

## Authority and permission boundary

Runtime Handoff belongs to Webcore runtime/lifecycle authority, not to Site Settings persistence.

The existing `system.webcore.manage` operator boundary is the expected product-facing permission lineage unless source evidence during amendment work requires a narrower dedicated capability.

Any final contract must preserve the distinction between:

- `admin.access`;
- `system.webcore.manage`;
- `modules.manage`; and
- Site Settings ordinary write permissions.

## Explicit non-goals

This Concept does not authorize:

- generic runtime orchestration;
- server/VM/container/process management;
- DNS/load-balancer mutation;
- automatic failover;
- cluster consensus or distributed lock replacement;
- arbitrary multi-primary serving;
- background remote-control infrastructure;
- production reconciliation;
- Installer Fresh/Coexist changes;
- Module lifecycle changes;
- release/tag/publication work; or
- implementation merely because this Concept exists.

## Required authoritative amendment outcome

Before Batch 2 implements Runtime Handoff controls, authoritative contract work must explicitly lock:

1. runtime-handoff terminology and ownership;
2. `DETACH_PENDING` or equivalent pending semantic state;
3. request/cancel/finalize transition rules;
4. cancellation eligibility and fail-closed conditions;
5. takeover commit boundary;
6. no-dual-active invariant;
7. source-runtime behavior after finalization;
8. target-runtime attachment/adoption requirements;
9. required durable operation evidence; and
10. the exact Site Settings projection boundary.

Until that amendment is promoted, Batch 2 may project existing runtime participation evidence only and must not claim reversible detachment or runtime handoff execution as delivered capability.
