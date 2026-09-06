# Runtime Handoff & Reversible Detachment

Status: PLANNING LOCKED / AUTHORITATIVE CONTRACT AMENDMENT CANDIDATE

This Concept materializes the accepted product/architecture direction for Runtime Handoff under Site Settings → System, without claiming delivered capability and without authorizing implementation.

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
- runtime participant states remain `REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, and `INCOMPATIBLE`;
- retired runtimes are detached rather than hard-deleted by default;
- shared-state transitions remain fail-closed when runtime compatibility or participation state is unsafe;
- Site Settings is a projection and must not become a second runtime/lifecycle engine.

## Problem statement

Current Runtime Registry semantics support direct detachment, but current implementation transitions a runtime directly to `DETACHED` and does not provide a reversible handoff window.

That is insufficient for the accepted product flow where one existing installation is intentionally transferred from one serving runtime to another while preserving the installation/database state and allowing the source runtime to cancel before takeover commits.

The required amendment must therefore define Runtime Handoff as a durable lifecycle operation rather than as a presentation-only action or a new participant-state shortcut.

## Terminology boundary

This Concept uses **Runtime Handoff** for transfer of runtime participation over one existing installation.

It must remain distinct from:

- Installer `Adopt`, which selects an existing compatible installation during installation routing; and
- Existing-Runtime Webcore lifecycle adoption/reconciliation, which establishes or reconciles committed Webcore lifecycle state.

Do not use one generic `Adopt` label for all three operations.

## Refined lifecycle model

Source-backed review supersedes the earlier candidate model that placed `DETACH_PENDING` inside `RuntimeParticipant` state.

Runtime participant state remains governed by the existing vocabulary:

`REGISTERED / ACTIVE / STALE / DETACHED / INCOMPATIBLE`

Runtime Handoff has its own durable operation state:

`PENDING → COMMITTED / CANCELLED / INTERRUPTED`

The source runtime may remain an `ACTIVE` participant while the handoff operation is `PENDING`. A pending handoff is represented by authoritative handoff-operation evidence, not by replacing the participant state with `DETACH_PENDING`.

This separation is required because current heartbeat and staleness behavior own participant-state transitions. A handoff-pending semantic must not be silently overwritten by heartbeat or stale classification.

## Detachment request

`Request Detachment` starts a durable Runtime Handoff operation and moves that operation to `PENDING`.

It must not immediately set the source runtime to `DETACHED` and must not destroy source-runtime participation evidence.

Minimum durable handoff evidence should bind:

- `installation_id`;
- source `runtime_id`;
- durable handoff operation identity;
- requested timestamp;
- source participant state and compatibility evidence at request time;
- target `runtime_id` when already known, otherwise explicit unbound-target state;
- current operation state;
- current cancellation eligibility or evidence sufficient to derive it;
- finalization/commit result.

Exact persistence shape remains contract/implementation-time detail, but this evidence must not be stored as a competing runtime registry.

## Cancellation

`Cancel Detachment` is a first-class lifecycle operation, not a UI convenience.

Cancellation is eligible only while the handoff operation remains `PENDING`, takeover has not committed, and authoritative evidence proves the source can safely continue serving.

At minimum, cancellation must fail closed when:

- takeover may already have committed;
- the source runtime can no longer safely continue participation;
- a conflicting lifecycle operation is active or unresolved;
- installation/runtime/handoff identity evidence no longer matches; or
- authoritative state is indeterminate.

Successful cancellation transitions the handoff operation to `CANCELLED`. The source runtime keeps or resumes its valid participant state without creating a new installation or runtime identity.

Cancellation must not mutate a participant state merely to simulate handoff reversal.

## Target-runtime takeover

A target runtime may take over one existing installation only through an explicit handoff/attachment path that proves:

- the target references the same `installation_id` and intended database/namespace state;
- the target has its own stable `runtime_id` and never reuses the source `runtime_id`;
- compatibility requirements pass;
- the same handoff operation identity is used through finalization;
- no conflicting lifecycle operation invalidates the evidence; and
- takeover commit cannot produce ambiguous serving authority.

The exact target registration/attachment mechanics remain contract work unless source evidence already determines them.

## Single exclusion boundary

All handoff mutation that can decide serving authority must execute under one installation-wide exclusion boundary.

Current source already provides `InstallationMutex` and `RuntimeTransitionCoordinator`. The amendment should reuse that authority rather than create a new distributed lock.

The implementation design must avoid nested acquisition of the same non-blocking installation mutex. A handoff coordinator that owns the exclusion boundary must use mutation primitives that do not reacquire the same lock internally.

Request, cancellation, retry/reconciliation, and final commit must re-read and revalidate authoritative handoff and runtime evidence inside the exclusion boundary before mutation.

## Commit boundary

Takeover has one logical commit boundary.

Inside one installation-wide critical section, finalization must revalidate at least:

- handoff operation identity and current `PENDING` state;
- source `runtime_id` and source eligibility;
- target `runtime_id` and target compatibility/eligibility;
- same `installation_id` and installation/database identity;
- absence of conflicting lifecycle state.

A successful commit must produce an unambiguous durable result:

- source runtime becomes `DETACHED`;
- target runtime becomes or remains the valid serving participant according to authoritative Runtime Registry semantics;
- handoff operation becomes `COMMITTED`;
- cancellation eligibility ends permanently for that operation.

Do not split source detachment and target authority activation across an unguarded interval.

## Interruption and retry

Runtime Handoff must be interruption-safe.

If final outcome cannot be proven after interruption, the operation becomes or is classified as `INTERRUPTED` and the system fails closed until deterministic reconciliation proves the safe next action.

Rules:

- interruption never silently becomes success;
- retry/reconciliation remains bound to the same handoff identity where the same operation is being recovered;
- cancellation is not offered when commit may already have occurred;
- finalization must be idempotent or deterministically guarded against duplicate commit;
- ambiguous source/target authority requires explicit reconciliation.

## Durable operation storage direction

Current repository source already has a durable file-backed lifecycle-operation pattern under `storage/.copot-lifecycle` with operation identity, atomic replacement, interruption classification, and terminal cleanup semantics.

That pattern is suitable architectural evidence for Runtime Handoff, but the current `LifecycleOperationRecord` is package-lifecycle-specific and must not be reused unchanged.

The amendment should prefer a handoff-specific durable operation record or a narrowly generalized operation abstraction that preserves singular lifecycle-operation/exclusion semantics without importing package-only fields into Runtime Handoff.

No database schema change is currently required by evidence. A schema change must not be introduced unless later source review proves file-backed installation-scoped persistence insufficient.

## No-dual-active invariant

The core invariant is:

**one Runtime Handoff must never reach a committed or uncertain state where two independent runtime participants can both legitimately act as the exclusive serving authority for the same installation.**

Multiple compatible runtime participants remain possible where existing contracts permit them, but an exclusive-serving transfer must have an unambiguous source, target, and committed result.

If state cannot be proven safe, handoff and cancellation fail closed.

## Source-runtime behavior after finalization

After successful takeover commit:

- source runtime is `DETACHED`;
- source heartbeat/automatic reactivation remains blocked;
- source runtime must not continue normal lifecycle mutation against the installation;
- stale local files or a surviving process do not restore authority;
- re-entry requires an explicit future supported lifecycle path, not silent registration.

## Site Settings → System projection

After the underlying authority is amended and implemented, Batch 2 may project Runtime Handoff under Site Settings → System.

The product projection may expose:

- current `installation_id`;
- current `runtime_id`;
- runtime participant state;
- compatibility and safe last-seen evidence;
- handoff operation state;
- `Request Detachment` when eligible;
- `Cancel Detachment` when eligible;
- takeover/finalization status and next valid action;
- concise fail-closed blocking reasons.

Site Settings must consume authoritative runtime and handoff evidence and must not infer or manufacture eligibility.

## Authority and permission boundary

Runtime Handoff belongs to Webcore runtime/lifecycle authority, not Site Settings persistence.

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

Before Batch 2 implements executable Runtime Handoff controls, authoritative contract work must explicitly lock:

1. Runtime Handoff terminology and ownership;
2. participant-state versus handoff-operation-state separation;
3. `PENDING / COMMITTED / CANCELLED / INTERRUPTED` handoff semantics or contract-equivalent states;
4. Request / Cancel / Finalize transition rules;
5. cancellation eligibility and fail-closed conditions;
6. single installation-wide exclusion and no-nested-lock rule;
7. atomic logical commit ordering;
8. no-dual-active invariant;
9. source-runtime behavior after finalization;
10. target-runtime attachment requirements;
11. durable handoff-operation evidence and persistence owner;
12. interruption/retry/reconciliation semantics; and
13. the exact Site Settings projection boundary.

Until that amendment is promoted, Batch 2 may project existing runtime participation evidence only and must not claim reversible detachment or Runtime Handoff execution as delivered capability.
