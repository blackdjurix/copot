# Runtime Handoff & Reversible Detachment — Pre-contract

Pre-contract lifecycle: PROMOTED / HISTORICAL PROVENANCE
Placement: Post-M3 — Webcore Product Completeness & Stabilization / WU4 Batch 2 prerequisite authority amendment
Promotion status: PROMOTED into `docs/34`, `docs/30`, `docs/37`, and `docs/54`
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

## 5. Participant state versus handoff operation state

`DETACH_PENDING` is not a `RuntimeParticipant` state.

Runtime participant states remain unchanged:

`REGISTERED / ACTIVE / STALE / DETACHED / INCOMPATIBLE`

Runtime Handoff uses a separate durable operation state machine:

`PENDING → COMMITTING → COMMITTED`

or

`PENDING → CANCELLED`

`INTERRUPTED` is a fail-closed classification derived from a non-terminal handoff record plus current coordination/executor evidence. It need not be persisted as a terminal operation state.

The source runtime may remain `ACTIVE` while the handoff operation is `PENDING`. This avoids corrupting participant-state semantics already owned by heartbeat, staleness, compatibility, and detach behavior.

## 6. Handoff operation record and persistence owner

Runtime Handoff requires a dedicated installation-scoped durable handoff record/store rather than reuse of the package-specific `LifecycleOperationRecord` unchanged.

Exact class names are implementation detail. Contract semantics require:

- one non-terminal Runtime Handoff per installation;
- durable handoff operation identity;
- storage under the installation-scoped `storage/.copot-lifecycle` lineage by default;
- atomic file replacement and strict record validation;
- deterministic terminal result;
- interruption classification based on durable record and installation-wide exclusion ownership/evidence;
- no second copy of Runtime Registry participant state.

A Runtime Handoff may not overlap unsafely with a non-terminal package/Webcore lifecycle operation. Handoff creation must fail closed when conflicting lifecycle activity is active, and package/shared-state mutation must treat a non-terminal Runtime Handoff as conflicting.

No database schema change is currently justified. File-backed persistence remains the default direction unless later evidence proves it insufficient.

## 7. Request Detachment

Request Detachment creates one durable handoff operation in `PENDING`.

It must:

- bind source `runtime_id` and `installation_id`;
- create a durable `handoff_id` / operation identity;
- record request time;
- record source role/capability evidence material to the transfer;
- record source participant/compatibility evidence sufficient for deterministic revalidation;
- preserve installation/database state and source provenance;
- reject conflicting lifecycle activity;
- remain fail-closed on ambiguous, incompatible, stale-without-policy, or contradictory evidence.

Request Detachment must not immediately set the source participant to `DETACHED`.

## 8. Cancel Detachment

Cancel Detachment is a first-class lifecycle operation, not a UI convenience.

Cancellation is permitted only while:

- the handoff is still `PENDING`;
- takeover has not entered `COMMITTING`;
- the same handoff, installation, and source identities remain valid;
- the source can safely continue participation; and
- no conflicting lifecycle state invalidates the original evidence.

Successful cancellation moves the handoff to `CANCELLED`. The source remains or returns to a valid participant state without creating a new installation or runtime identity.

Cancel-vs-commit races are serialized through the same installation-wide exclusion boundary. The contender that acquires the mutex first must re-read and revalidate the same `PENDING` handoff. Once finalization durably transitions the handoff to `COMMITTING`, cancellation is permanently closed for that operation.

Cancellation fails closed when commit may already have occurred or authoritative state is indeterminate.

## 9. Target runtime attachment and installation identity

Current source stores `installation_id` in local installation-scoped lifecycle storage. `InstallationIdentityStore::getOrCreate()` generates a new identity when no identity record exists.

Therefore a target runtime in Runtime Handoff must not unconstrainedly generate a fresh installation identity and then claim equivalence.

The target attachment contract is:

- target runtime uses its own newly generated stable `runtime_id`;
- target must consume or otherwise prove the pending handoff identity and the source installation's `installation_id`;
- target local installation-identity storage must be empty or already match the proven `installation_id`;
- if empty, target may establish the existing `installation_id` only through a guarded handoff/adoption write path that is set-if-empty and bound to pending handoff evidence;
- if target local identity differs, handoff fails closed;
- target must prove the intended database/namespace/installation evidence before Runtime Registry registration/activation;
- target must never reuse the source `runtime_id`;
- exact transport of the handoff descriptor/proof is implementation detail and must not become generic remote orchestration infrastructure.

This guarded identity-adoption path is distinct from Installer Adopt and legacy Webcore adoption/reconciliation.

## 10. Multi-runtime participation boundary

Existing Runtime Registry behavior permits more than one participant per installation and distinct roles such as `web` and `worker`.

Runtime Handoff therefore transfers only the explicitly bound complete source
participant identified by `runtime_id`. Its role/capability data are validation
evidence, not independently detachable state. It does not imply detaching all
participants or enforcing one runtime per installation.

Unrelated compatible participants remain registered and continue participating in normal compatibility/shared-state transition gates.

For an exclusive-serving transfer, source and target authority for the transferred serving role must be unambiguous at commit. Unrelated compatible worker or secondary roles do not violate the no-dual-active invariant.

## 11. Coordination and single-mutex rule

Runtime Handoff must reuse the existing installation-wide coordination lineage.

Current source provides `InstallationMutex` and `RuntimeTransitionCoordinator`, while Runtime Registry mutations also acquire `InstallationMutex` internally.

Implementation must therefore obey a single-acquisition rule:

- one handoff coordinator owns the installation-wide exclusion boundary for a handoff decision;
- mutation primitives used inside that critical section must not reacquire the same non-blocking mutex;
- nested acquisition of the same installation lock is prohibited.

Request, cancellation, retry/reconciliation, and finalization must re-read and revalidate authoritative handoff and runtime evidence while holding the relevant exclusion boundary.

## 12. Finalization state and mutation ordering

Finalization has one logical commit boundary with a durable `COMMITTING` phase.

Inside one installation-wide critical section, finalization must:

1. re-read the handoff record and require the same `handoff_id` in `PENDING`;
2. revalidate source participant, target participant/registration evidence, installation identity, database/namespace identity, compatibility, transferred role/capability evidence, and absence of conflicting lifecycle activity;
3. durably transition the handoff record to `COMMITTING`;
4. atomically replace the Runtime Registry file with one result that marks the source `DETACHED` for the transferred serving participation and establishes the target as the valid participant for that authority while leaving unrelated compatible participants unchanged;
5. durably transition the same handoff record to `COMMITTED`.

`COMMITTING` is required because Runtime Registry and handoff-operation evidence are separate durable files and cannot be assumed to share one filesystem transaction.

Once `COMMITTING` is durable, cancellation is no longer valid.

## 13. Interrupted classification, reconciliation, and retry

A non-terminal Runtime Handoff whose current executor no longer owns the installation-wide exclusion boundary is classified `INTERRUPTED`.

Reconciliation must reacquire the mutex and inspect both the handoff record and Runtime Registry.

Required dispositions:

- `PENDING` + unchanged pre-commit participant evidence → retry finalization or cancel only if all relevant gates still pass;
- `COMMITTING` + exact pre-commit participant evidence → retry the same finalization from the same `handoff_id`;
- `COMMITTING` + exact committed Runtime Registry result → idempotently complete the handoff to `COMMITTED`;
- any mixed, contradictory, foreign, or unprovable result → fail closed in explicit reconciliation-required state; do not offer cancellation and do not fabricate success.

Retry of the same handoff remains bound to the original handoff identity.

## 14. No-dual-active invariant

The governing invariant is:

**A Runtime Handoff must never create an ambiguous dual-active authority state for the same transferred exclusive-serving role on one installation.**

Multiple compatible runtime participants remain valid where existing architecture permits them, but the transferred serving role must have an unambiguous source, target, handoff identity, and commit result.

If the system cannot prove a safe transition, it fails closed before or during reconciliation.

## 15. Source-runtime behavior after commit

After successful takeover commit:

- source runtime is `DETACHED` for the transferred serving participation;
- source heartbeat/automatic reactivation remains blocked for that detached identity;
- source must not continue normal lifecycle mutation through the detached serving identity;
- stale local files or a surviving process do not restore authority;
- re-entry requires an explicit supported lifecycle path, not silent registration.

## 16. Minimum durable handoff evidence

The handoff record must bind enough evidence to make request, cancellation, attachment, finalization, retry, and reconciliation deterministic.

Minimum evidence includes:

- `handoff_id` / operation identity;
- `installation_id`;
- source `runtime_id`;
- transferred role/capability identity or equivalent bounded serving-participation descriptor;
- target `runtime_id` when known;
- source participant/compatibility evidence;
- target participant/compatibility evidence when applicable;
- target installation-identity evidence when applicable;
- requested time and updated time;
- operation state;
- cancellation eligibility or evidence sufficient to derive it;
- commit/finalization result;
- concise sanitized blocking/failure reason where applicable.

Exact serialization is implementation detail.

## 17. Site Settings → System projection

After authoritative promotion and implementation of the underlying Runtime Handoff semantics, Batch 2 Site Settings → System may project:

- current `installation_id`;
- current `runtime_id`;
- runtime participant state;
- safe last-seen/compatibility evidence;
- Runtime Handoff operation state/classification;
- Request Detachment when eligible;
- Cancel Detachment when eligible;
- target-attachment readiness where safely derivable;
- handoff/finalization/reconciliation status;
- sanitized blocked/failure reason;
- next valid action.

Site Settings must derive action visibility from authoritative lifecycle state. It must not manufacture eligibility, directly mutate registry files, or implement its own handoff state machine.

## 18. Permission boundary

Runtime Handoff remains a Webcore system-lifecycle capability.

The final amendment must preserve the existing distinction between:

- `admin.access`;
- `system.webcore.manage`;
- `modules.manage`;
- Site Settings write permissions where separately applicable.

No Module permission may become implicit Runtime Handoff authority.

## 19. Focused acceptance criteria

Implementation acceptance must eventually prove at least:

1. Request creates one durable `PENDING` handoff and does not detach source.
2. Repeated Request does not create a second non-terminal handoff.
3. Eligible Cancel produces `CANCELLED` and preserves safe source participation.
4. Cancel-vs-commit race is mutex-serialized and produces exactly one valid winner.
5. Finalization durably enters `COMMITTING`, atomically produces source-detached/target-valid Runtime Registry state, then reaches `COMMITTED`.
6. Interruption before registry mutation can be retried or cancelled only when evidence proves safety.
7. Interruption after registry mutation but before terminal record update reconciles idempotently to `COMMITTED`.
8. Mismatched target `installation_id`, database/namespace identity, compatibility, role/capability evidence, or handoff identity fails closed.
9. Target cannot silently generate a different installation identity and continue handoff.
10. Detached source identity cannot silently heartbeat or re-register.
11. Unrelated compatible participants such as workers remain unchanged.
12. Stale/incompatible/conflicting participants continue to block unsafe shared-state transitions.
13. Package/Webcore lifecycle activity and Runtime Handoff cannot overlap unsafely.
14. No database schema migration is introduced without new evidence.
15. Site Settings action visibility derives from lifecycle truth rather than UI-local state.

## 20. Explicit non-goals

This amendment does not authorize:

- generic runtime orchestration, process management, containers, VM control, DNS, load balancers, or service discovery;
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

## 21. Source-backed promotion review disposition

The previous promotion blockers are now resolved at pre-contract level as follows:

1. **Handoff operation record and persistence API** — dedicated installation-scoped handoff record/store under `.copot-lifecycle`, one non-terminal handoff per installation, atomic replacement, strict validation, conflict with other non-terminal lifecycle activity.
2. **Target-runtime registration/attachment mechanics** — distinct target `runtime_id`; guarded set-if-empty adoption of the proven existing `installation_id`; mismatch fails closed; database/namespace/installation proof required before registration/activation.
3. **Interrupted reconciliation/retry** — derived `INTERRUPTED` classification over non-terminal evidence; deterministic PENDING/COMMITTING recovery matrix; same handoff identity retained.
4. **Finalization ordering** — `PENDING → COMMITTING`, one atomic Runtime Registry replacement, then `COMMITTED`.
5. **Cancel-vs-commit race** — serialized by the same installation mutex; commit closes cancellation once `COMMITTING` is durable.
6. **Multi-runtime relationship** — transfer is scoped to the bound complete
   serving participant; role/capability data are validation evidence and
   unrelated compatible participants remain intact.
7. **Acceptance criteria** — focused deterministic criteria are defined in Section 19.

No currently known unresolved item requires ownership transfer, destructive behavior, schema migration, or ambiguous authority.

## 22. Promotion readiness boundary

This Pre-contract was the **PROMOTION-READY CANDIDATE** and is retained as
historical provenance for the promoted amendment. The participant-granularity
correction is authoritative in the destination contracts: the complete
`RuntimeParticipant` / `runtime_id` is the handoff unit, while role and
capability data remain validation evidence.

Promotion is complete in the destination contracts. This record authorizes no
implementation and does not reopen any completed workstream.

## 23. Implementation authorization boundary

This Pre-contract authorizes no implementation.

It does not authorize modification of `RuntimeRegistry`, new handoff record classes, guarded installation-identity adoption code, routes, Site Settings UI, schema/storage mutation, runtime mutation, production testing, branch merge, release, or publication.

After review and authoritative promotion, implementation requires a separately authorized execution slice.

## 24. Downstream dependency

Site Settings → System Batch 2 may materialize the read-only/current Runtime Participation projection from existing authority independently where truthful.

Executable Runtime Handoff actions remain **BLOCKED** until this amendment is promoted and the underlying lifecycle capability is implemented/accepted.

This dependency must not block unrelated Batch 2 System projection work that uses already-authoritative lifecycle capabilities.
