# Runtime Handoff & Reversible Detachment — Pre-contract

Pre-contract lifecycle: MATERIALIZED / PRE-PROMOTION PLANNING
Placement: Post-M3 — Webcore Product Completeness & Stabilization / WU4 Batch 2 prerequisite authority amendment
Promotion status: NOT PROMOTED
Implementation authorization: NONE
Release / tag / publication authorization: NONE

## 1. Purpose

Define the bounded authoritative amendment required before Site Settings → System may expose executable Runtime Handoff capability.

This Pre-contract promotes no runtime behavior by itself. It converts the accepted planning direction in `concepts/copot_runtime_handoff_reversible_detachment_concept.md` into a reviewable pre-promotion contract candidate while preserving existing Runtime Registry, Multi-Installation, Webcore Lifecycle, Installer, and Site Settings ownership boundaries.

## 2. Problem statement

Current Runtime Registry authority already provides stable `runtime_id`, installation binding, participant evidence, compatibility state, and explicit `DETACHED` semantics. Current implementation transitions directly to `DETACHED` and has no reversible handoff window.

Batch 2 Site Settings → System is a product projection over existing authorities. It must not invent lifecycle semantics in the UI layer.

The accepted product direction requires an installation to be intentionally transferred from one serving runtime to another while preserving the existing installation/database state, allowing cancellation before takeover commits, and preventing ambiguous dual-active authority.

That requires a bounded lifecycle-authority amendment before Batch 2 can truthfully expose the operation.

## 3. Authoritative lineage to preserve

The amendment must reconcile with, not replace:

- `docs/34_multi_installation_isolation_foundation_contract.md`;
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`;
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`;
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md`.

Locked existing facts remain authoritative:

- one stable `installation_id` identifies an installation;
- each runtime participant has its own stable `runtime_id`, distinct from PID/process identity;
- Runtime Registry remains the singular runtime-participation authority;
- participant-state vocabulary remains `REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, and `INCOMPATIBLE`;
- retired runtimes are detached rather than hard-deleted by default;
- shared-state transitions fail closed when participation or compatibility evidence is unsafe;
- Installer Adopt remains distinct from normal existing-runtime Webcore lifecycle operations;
- legacy Webcore exact-match adoption/reconciliation remains distinct from runtime participation transfer;
- Site Settings remains a projection and must not become a second runtime or lifecycle engine.

## 4. Terminology boundary

The canonical product/architecture term for this amendment is **Runtime Handoff**.

Runtime Handoff means transfer of runtime participation for one existing installation from a source runtime to a target runtime.

It is not:

- Installer `Adopt / Use Existing Installation`;
- legacy Webcore exact-match adoption;
- legacy Webcore reconciliation;
- package Update / Upgrade / Repair;
- database migration authority transfer;
- installation identity transfer;
- database ownership transfer.

The installation remains the same installation. Source and target runtimes remain distinct runtime participants with distinct `runtime_id` values.

## 5. Refined state model

Source-backed review supersedes the earlier candidate that modeled `DETACH_PENDING` as a `RuntimeParticipant` state.

Runtime participant states remain unchanged:

`REGISTERED / ACTIVE / STALE / DETACHED / INCOMPATIBLE`

Runtime Handoff introduces a distinct durable operation state:

`PENDING → COMMITTED / CANCELLED / INTERRUPTED`

The source runtime may remain `ACTIVE` while the handoff operation is `PENDING`.

This separation is required because current participant-state authority already allows heartbeat and staleness evaluation to mutate participant state. Pending handoff semantics must not be erased or transformed by heartbeat/stale classification.

## 6. Request Detachment

Request Detachment begins a durable Runtime Handoff operation and moves that operation to `PENDING`.

The operation must:

- bind source `runtime_id` and `installation_id`;
- create a durable handoff operation identity;
- record request time;
- record enough source participation/compatibility evidence to support deterministic revalidation;
- preserve installation/database state;
- preserve source runtime provenance;
- prevent conflicting lifecycle operations that would make the handoff unsafe; and
- remain fail-closed on ambiguous, incompatible, stale-without-policy, or contradictory participation evidence.

Request Detachment must not immediately set the source participant to `DETACHED`.

## 7. Cancel Detachment

Cancellation is part of the lifecycle contract, not a UI convenience.

Cancel Detachment is permitted only while the handoff operation remains reversible.

At minimum, cancellation eligibility requires:

- handoff operation is still `PENDING`;
- no target runtime takeover has committed for that operation;
- no conflicting lifecycle transition invalidated the handoff evidence;
- installation identity and handoff operation identity remain unchanged;
- source runtime can safely continue participation.

Successful cancellation transitions the handoff operation to `CANCELLED` and leaves or restores the source runtime in a valid participant state without creating a new installation or runtime identity.

Cancellation must fail closed after takeover/finalization has committed or when eligibility cannot be proven.

## 8. Target runtime and takeover

A target runtime participating in Runtime Handoff:

- uses its own stable `runtime_id`;
- must never inherit or reuse the source runtime's `runtime_id`;
- must prove the same `installation_id` and accepted installation/database identity;
- must satisfy compatibility and participation gates;
- must bind to the same handoff operation identity through finalization;
- must not silently attach to ambiguous or unsafe state; and
- must not create ambiguous exclusive-serving authority.

Exact target registration/attachment mechanics remain contract work unless direct source evidence already fixes them.

## 9. Coordination and single-mutex rule

The amendment must reuse the existing installation-wide coordination authority rather than create a second lock system.

Current source provides `InstallationMutex` and `RuntimeTransitionCoordinator`. Runtime Registry mutations also acquire `InstallationMutex` internally.

Therefore implementation must obey a **single-acquisition rule**:

- one coordinator owns the installation-wide exclusion boundary for a handoff decision;
- mutation primitives used inside that critical section must not reacquire the same non-blocking mutex;
- nested acquisition of the same installation lock is prohibited for Runtime Handoff.

Request, cancellation, retry/reconciliation, and finalization must re-read and revalidate authoritative runtime and handoff evidence while the relevant mutation exclusion is held.

## 10. Handoff commit and final detachment

The handoff has one logical commit boundary.

Inside one installation-wide critical section, finalization must revalidate at least:

- handoff operation identity and current `PENDING` state;
- source `runtime_id` and source eligibility;
- target `runtime_id` and target compatibility/eligibility;
- same `installation_id` and installation/database identity;
- absence of conflicting lifecycle state.

A successful commit must durably produce an unambiguous result:

- source runtime becomes `DETACHED`;
- target runtime becomes or remains the valid serving participant according to authoritative Runtime Registry semantics;
- handoff operation becomes `COMMITTED`;
- cancellation eligibility ends permanently for that operation.

Source detachment and target authority activation must not be separated by an unguarded interval that could create split-brain or ambiguous serving authority.

The amendment must not allow a committed handoff to be reversed merely by re-registering the old runtime identity.

## 11. No-dual-active invariant

The governing invariant is:

**A Runtime Handoff must never create an ambiguous dual-active authority state for one installation.**

Multiple compatible runtime participants remain architecturally possible where existing contracts permit them, but Runtime Handoff for an exclusive serving transfer must have an unambiguous source, target, operation identity, and commit result.

If the system cannot prove a safe transition, it fails closed before takeover commit.

## 12. Minimum handoff evidence

The authoritative handoff record must bind enough evidence to make cancellation, takeover, retry, and finalization deterministic.

Minimum contract-level evidence includes:

- handoff operation identity;
- `installation_id`;
- source `runtime_id`;
- target `runtime_id` when known/participating;
- source participant state/evidence;
- target participation/compatibility evidence when applicable;
- requested time;
- current handoff operation state;
- cancellation eligibility or evidence sufficient to derive it;
- commit/finalization result;
- concise sanitized blocking/failure reason where applicable.

Exact persistence format is implementation detail. The amendment must not create a second competing runtime registry.

## 13. Durable operation persistence

Current repository source already provides a durable file-backed lifecycle-operation pattern under `storage/.copot-lifecycle`, including operation identity, atomic replacement, interrupted classification, and terminal cleanup behavior.

That pattern is suitable architectural evidence, but the current `LifecycleOperationRecord` is package-lifecycle-specific and must not be reused unchanged for Runtime Handoff.

The amendment should prefer either:

- a handoff-specific durable operation record sharing the same lifecycle/exclusion authority; or
- a narrowly generalized lifecycle-operation abstraction that preserves singular operation/coordination semantics without importing package-only fields into Runtime Handoff.

No database schema change is currently required by source evidence. A database/schema amendment must remain out of scope unless later source review proves installation-scoped file-backed persistence insufficient.

## 14. Recovery, retry, and interruption boundary

Runtime Handoff must be interruption-safe.

If the final result cannot be proven after interruption, the operation is classified as `INTERRUPTED` and fails closed until deterministic reconciliation establishes the safe next action.

At minimum:

- uncertain state must not silently resolve to success;
- retry/reconciliation of the same handoff must bind to existing handoff evidence instead of fabricating an unrelated operation identity;
- cancellation must not be offered when takeover may already have committed;
- finalization must be idempotent or deterministically guarded;
- ambiguous source/target authority requires explicit reconciliation.

This Pre-contract does not invent a new generic recovery engine. Existing lifecycle, coordination, and recovery authorities must be reused where applicable.

## 15. Site Settings → System projection

After authoritative promotion and implementation of the underlying handoff semantics, Batch 2 Site Settings → System may project:

- current `installation_id`;
- current `runtime_id`;
- runtime participant state;
- safe last-seen/compatibility evidence;
- Runtime Handoff operation state;
- Request Detachment when eligible;
- Cancel Detachment when eligible;
- handoff/takeover status;
- sanitized blocked/failure reason;
- next valid action.

Site Settings must derive action visibility from authoritative lifecycle state. It must not manufacture eligibility, mutate registry files directly, or implement its own handoff state machine.

## 16. Permission boundary

Runtime Handoff remains a Webcore system-lifecycle capability.

The final amendment must preserve the existing distinction between:

- `admin.access`;
- `system.webcore.manage`;
- `modules.manage`;
- Site Settings write permissions where separately applicable.

No Module permission may become implicit Runtime Handoff authority.

## 17. Explicit non-goals

This amendment does not authorize:

- runtime orchestration, process management, containers, VM control, DNS, load balancers, or service discovery;
- automatic takeover based only on heartbeat timeout;
- forced takeover of a merely `STALE` runtime without explicit policy/proof;
- hard deletion of runtime provenance;
- reuse of a detached source `runtime_id` by a new deployment;
- Installer workflow changes;
- package Update / Upgrade / Repair changes except where a direct compatibility gate is required;
- database ownership transfer;
- schema migration unrelated to handoff evidence;
- Module lifecycle changes;
- System Health redesign;
- remote distribution or online update infrastructure;
- release, tag, publication, or production reconciliation.

## 18. Source-backed promotion requirements

Promotion to authoritative contract requires source-backed review proving that the amendment can integrate with current Runtime Registry, installation coordination, compatibility, lifecycle-operation, and recovery boundaries without creating competing authority.

The following dispositions are now source-backed and no longer open questions:

1. `DETACH_PENDING` is rejected as a `RuntimeParticipant` state; pending semantics belong to the handoff operation.
2. Existing participant-state vocabulary remains unchanged.
3. `InstallationMutex` is the installation-wide exclusion lineage to reuse.
4. Nested acquisition of the same non-blocking installation mutex is prohibited.
5. Current package-specific `LifecycleOperationRecord` cannot be reused unchanged.
6. File-backed `storage/.copot-lifecycle` persistence is sufficient as the default direction; no schema change is currently justified.
7. Handoff commit must be logically atomic inside one installation-wide exclusion boundary.
8. Interruption must classify fail-closed and remain bound to durable operation evidence.

Before promotion, the remaining contract decisions are limited to:

1. exact handoff-operation record type and persistence API;
2. exact target-runtime registration/attachment mechanics;
3. exact `INTERRUPTED` reconciliation and retry transitions;
4. exact finalization mutation ordering inside the single exclusion boundary;
5. race resolution between Cancel Detachment and Handoff Commit;
6. exact relationship to existing multi-runtime participation roles where more than one compatible participant is permitted;
7. focused compatibility/regression acceptance criteria.

Any unresolved item that changes ownership, introduces destructive behavior, or permits ambiguous authority blocks promotion.

## 19. Implementation authorization boundary

This Pre-contract authorizes no implementation.

It does not authorize modification of `RuntimeRegistry`, new runtime participant states, new handoff record classes, routes, Site Settings UI, schema/storage mutation, runtime mutation, production testing, branch merge, release, or publication.

After review and promotion, implementation requires a separately authorized execution slice.

## 20. Downstream dependency

Site Settings → System Batch 2 may materialize the read-only/current Runtime Participation projection from existing authority independently where truthful.

Executable Runtime Handoff actions are **BLOCKED** until this amendment is promoted and the underlying lifecycle capability is implemented/accepted.

This dependency must not block unrelated Batch 2 System projection work that uses already-authoritative lifecycle capabilities.
