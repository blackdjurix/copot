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

Current Runtime Registry authority already provides stable `runtime_id`, installation binding, participation evidence, compatibility state, and explicit `DETACHED` semantics. Current implementation transitions directly to `DETACHED` and has no reversible handoff window.

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
- existing participation vocabulary includes `REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, and `INCOMPATIBLE`;
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

The installation remains the same installation. The source and target runtimes remain distinct runtime participants with distinct `runtime_id` values.

## 5. Required lifecycle amendment

The target lifecycle adds one bounded transitional state:

`ACTIVE → DETACH_PENDING → DETACHED`

`DETACH_PENDING` is an explicit handoff state. It is not equivalent to `DETACHED` and must not silently inherit final-detachment behavior.

The amendment must define at least these operator/lifecycle actions:

1. **Request Detachment**
2. **Cancel Detachment**
3. **Finalize Detachment / Handoff Commit**

Exact method names remain implementation detail; the state semantics are contract-level requirements.

## 6. Request Detachment

Request Detachment begins a handoff window for the current source runtime.

The operation must:

- bind the source `runtime_id` and `installation_id`;
- create a durable handoff/detachment operation identity;
- record the request time;
- transition only an eligible source runtime to `DETACH_PENDING`;
- preserve the installation/database state;
- preserve source runtime provenance;
- prevent conflicting lifecycle operations that would make the handoff unsafe;
- remain fail-closed on ambiguous, incompatible, stale-without-policy, or contradictory participation evidence.

A Request Detachment action must not itself declare the source runtime finally detached.

## 7. Cancel Detachment

Cancellation is part of the required lifecycle contract, not a UI convenience.

Cancel Detachment is permitted only while the handoff remains reversible.

At minimum, cancellation eligibility requires:

- source runtime is still `DETACH_PENDING`;
- no target runtime has successfully committed takeover for the same handoff;
- no conflicting lifecycle transition has invalidated the original handoff evidence;
- installation identity and handoff operation identity remain unchanged;
- the source runtime is still eligible to resume participation.

Successful cancellation returns the source runtime to a valid active participation state and closes the pending handoff operation without creating a new installation or runtime identity.

Cancellation must fail closed after takeover/finalization has committed or when eligibility cannot be proven.

## 8. Target runtime and takeover

A target runtime participating in Runtime Handoff:

- uses its own stable `runtime_id`;
- must never inherit or reuse the source runtime's `runtime_id`;
- must prove the same `installation_id` and accepted installation/database identity;
- must satisfy compatibility and participation gates;
- must not silently attach to ambiguous or unsafe state;
- must not cause two runtimes to be treated as authoritative active participants for one exclusive-serving handoff state.

Exact target registration/attachment mechanics remain implementation-time details unless current source evidence requires a further contract decision.

## 9. Handoff commit and final detachment

The handoff has a distinct commit boundary.

Before commit, the source runtime remains cancel-eligible only while all cancellation gates still pass.

After successful handoff commit:

- the source runtime becomes `DETACHED`;
- the source runtime loses cancellation eligibility;
- the source runtime must fail closed for normal participation/heartbeat against that installation unless a separately authorized lifecycle path explicitly permits future reattachment;
- the target runtime becomes the valid serving participant according to the accepted Runtime Registry/compatibility rules;
- the committed handoff result must be durable and attributable to its operation identity.

The amendment must not allow a committed handoff to be reversed merely by re-registering the old runtime identity.

## 10. No-dual-active invariant

The governing invariant is:

**A Runtime Handoff must never create an ambiguous dual-active authority state for one installation.**

Multiple compatible runtime participants remain architecturally possible where existing contracts permit them, but Runtime Handoff for an exclusive serving transfer must have an unambiguous source, target, and commit result.

If the system cannot prove a safe transition, it fails closed before takeover commit.

## 11. Minimum handoff evidence

The authoritative handoff record must bind enough evidence to make cancellation, takeover, retry, and finalization deterministic.

Minimum contract-level evidence includes:

- handoff/detachment operation identity;
- `installation_id`;
- source `runtime_id`;
- target `runtime_id` when known/participating;
- source participation state;
- target participation/compatibility state when applicable;
- requested time;
- current handoff state;
- cancellation eligibility or the evidence required to derive it;
- commit/finalization result;
- concise sanitized blocking/failure reason where applicable.

Exact persistence format is implementation detail. The amendment must not create a second competing runtime registry.

## 12. Recovery, retry, and interruption boundary

Runtime Handoff must be interruption-safe.

The final authoritative amendment must specify how an interrupted or uncertain handoff is classified before implementation begins.

At minimum:

- uncertain state must not silently resolve to success;
- retry must bind to existing handoff evidence rather than fabricate a new unrelated operation;
- cancellation must not be offered when takeover may already have committed;
- finalization must be idempotent or otherwise deterministically guarded;
- ambiguous source/target authority must fail closed and require explicit reconciliation.

This Pre-contract does not invent a new generic recovery engine. Existing lifecycle, coordination, and recovery authorities must be reused where applicable.

## 13. Site Settings → System projection

After authoritative promotion and implementation of the underlying handoff semantics, Batch 2 Site Settings → System may project:

- current `installation_id`;
- current `runtime_id`;
- runtime participation state;
- safe last-seen/compatibility evidence;
- Request Detachment when eligible;
- Cancel Detachment when eligible;
- handoff/takeover status;
- sanitized blocked/failure reason;
- next valid action.

Site Settings must derive action visibility from authoritative lifecycle state. It must not manufacture eligibility, mutate registry files directly, or implement its own handoff state machine.

## 14. Permission boundary

Runtime Handoff remains a Webcore system-lifecycle capability.

The final amendment must preserve the existing distinction between:

- `admin.access`;
- `system.webcore.manage`;
- `modules.manage`;
- Site Settings write permissions where separately applicable.

No Module permission may become implicit Runtime Handoff authority.

## 15. Explicit non-goals

This amendment does not authorize:

- runtime orchestration, process management, containers, VM control, DNS, load balancers, or service discovery;
- automatic takeover based only on heartbeat timeout;
- forced takeover of a merely `STALE` runtime without explicit policy/proof;
- hard deletion of runtime provenance;
- reuse of a detached source `runtime_id` by a new deployment;
- Installer workflow changes;
- package Update / Upgrade / Repair changes except where a direct compatibility gate is required;
- database ownership transfer;
- schema migration unrelated to the minimum handoff metadata/state requirement;
- Module lifecycle changes;
- System Health redesign;
- remote distribution or online update infrastructure;
- release, tag, publication, or production reconciliation.

## 16. Promotion requirements

Promotion to authoritative contract requires a source-backed review proving that the amendment can integrate with current Runtime Registry, installation mutex/coordination, compatibility, lifecycle-operation, and recovery boundaries without creating competing authority.

Before promotion, resolve at least:

1. exact persistence owner for pending handoff evidence;
2. exact mutation/coordination boundary for `DETACH_PENDING` transitions;
3. target-runtime attachment/registration mechanics;
4. interruption and idempotency semantics;
5. finalization ordering;
6. cancellation race handling;
7. relation to existing multi-runtime participation semantics;
8. whether any schema or storage-format amendment is actually required;
9. focused compatibility and regression acceptance criteria.

Any unresolved item that changes ownership, introduces destructive behavior, or permits ambiguous authority blocks promotion.

## 17. Implementation authorization boundary

This Pre-contract authorizes no implementation.

It does not authorize modification of `RuntimeRegistry`, new runtime states, routes, Site Settings UI, schema/storage mutation, runtime mutation, production testing, branch merge, release, or publication.

After review and promotion, implementation requires a separately authorized execution slice.

## 18. Downstream dependency

Site Settings → System Batch 2 may materialize the read-only/current Runtime Participation projection from existing authority independently where truthful.

Executable Runtime Handoff actions are **BLOCKED** until this amendment is promoted and the underlying lifecycle capability is implemented/accepted.

This dependency must not block unrelated Batch 2 System projection work that uses already-authoritative lifecycle capabilities.