# Runtime Handoff & Reversible Detachment

Status: PLANNING LINEAGE / HISTORICAL PROVENANCE

This Concept preserves the accepted product/architecture planning lineage for
Runtime Handoff under Site Settings → System. The reviewed amendment is now
promoted into the authoritative contracts, while this Concept continues to
make no claim of delivered capability and authorizes no implementation.

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

Runtime Handoff has a distinct durable operation state machine:

`PENDING → COMMITTING → COMMITTED`

or

`PENDING → CANCELLED`

`INTERRUPTED` is a fail-closed classification for a non-terminal handoff whose current executor no longer owns the installation-wide exclusion boundary. It is derived from durable operation evidence and coordination state; it need not be a separate persisted terminal state.

The source runtime may remain an `ACTIVE` participant while the handoff operation is `PENDING`. A pending handoff is represented by authoritative handoff-operation evidence, not by replacing the participant state with `DETACH_PENDING`.

This separation is required because current heartbeat and staleness behavior own participant-state transitions. A handoff-pending semantic must not be silently overwritten by heartbeat or stale classification.

## Dedicated handoff operation record

Runtime Handoff requires a dedicated installation-scoped durable operation record rather than reuse of the package-specific `LifecycleOperationRecord` unchanged.

The handoff record/store may use implementation-specific names, but contract semantics require:

- one non-terminal Runtime Handoff per installation;
- durable `handoff_id` / operation identity;
- atomic file replacement under `storage/.copot-lifecycle` by default;
- strict read/validation rules;
- deterministic terminal state;
- interruption classification from durable record plus mutex ownership/executor evidence;
- no competing copy of Runtime Registry participant state.

Creation of a Runtime Handoff must fail when a conflicting non-terminal package/Webcore lifecycle operation is active. Likewise, package/shared-state mutation must treat a non-terminal Runtime Handoff as conflicting. The exact shared activity-guard API is implementation detail, but the exclusion relationship is contract-level.

No database schema change is currently justified. File-backed installation-scoped persistence remains the default direction unless later evidence proves it insufficient.

## Detachment request

`Request Detachment` starts a durable Runtime Handoff operation and moves that operation to `PENDING`.

It must not immediately set the source runtime to `DETACHED` and must not destroy source-runtime participation evidence.

Minimum durable handoff evidence must bind:

- `installation_id`;
- source `runtime_id`;
- durable handoff operation identity;
- requested timestamp;
- source participant role/capability evidence relevant to the transfer;
- source participant state and compatibility evidence at request time;
- target `runtime_id` when already known, otherwise explicit unbound-target state;
- target installation identity evidence when available;
- current operation state;
- current cancellation eligibility or evidence sufficient to derive it;
- finalization/commit result;
- concise sanitized blocking/failure reason where applicable.

## Cancellation

`Cancel Detachment` is a first-class lifecycle operation, not a UI convenience.

Cancellation is eligible only while the handoff operation remains `PENDING`, takeover has not entered `COMMITTING`, and authoritative evidence proves the source can safely continue serving.

Successful cancellation transitions the operation to `CANCELLED`. The source runtime keeps or resumes its valid participant state without creating a new installation or runtime identity.

Cancellation and commit races are serialized by the same installation-wide mutex. Whichever operation acquires the mutex first must re-read and revalidate the same `PENDING` handoff record. Commit closes cancellation by durably transitioning the operation to `COMMITTING` before Runtime Registry serving-authority mutation. Cancellation is never valid from `COMMITTING` or `COMMITTED`.

Cancellation must fail closed when:

- takeover is `COMMITTING` or may already have committed;
- the source runtime can no longer safely continue participation;
- a conflicting lifecycle operation is active or unresolved;
- installation/runtime/handoff identity evidence no longer matches; or
- authoritative state is indeterminate.

## Target-runtime attachment and installation identity

Current source stores `installation_id` in installation-scoped local lifecycle storage and `InstallationIdentityStore::getOrCreate()` generates a new identity when no record exists. Therefore a target runtime participating in Runtime Handoff must not call an unconstrained fresh `getOrCreate()` path and then claim equivalence.

The target attachment contract is:

- target runtime has its own new stable `runtime_id`;
- target runtime must consume or otherwise prove the pending handoff identity and the source installation's `installation_id`;
- target local installation-identity storage must either be empty or already match that `installation_id`;
- if empty, the target may establish the proven existing `installation_id` only through a guarded handoff/adoption write path that is set-if-empty and bound to the pending handoff evidence;
- if target local identity differs, handoff fails closed;
- target must prove the intended database/namespace/installation evidence before Runtime Registry registration/activation;
- target must not reuse the source `runtime_id`;
- exact transport of the handoff descriptor/proof is an implementation detail and must not become remote orchestration infrastructure.

This identity-adoption path is distinct from Installer Adopt and from legacy Webcore lifecycle adoption.

## Multi-runtime participation boundary

Existing Runtime Registry evidence permits more than one participant for one installation, including distinct roles such as `web` and `worker`.

Therefore Runtime Handoff does not mean "detach every participant" and does not impose a one-runtime-per-installation architecture.

The handoff must bind the complete source participant identified by `runtime_id`
and the serving responsibility being transferred. Role/capability data remain
validation evidence rather than independently detachable state. Unrelated
compatible participants remain registered and are not detached merely because
a Web-serving handoff occurs. They continue to participate in normal
compatibility/shared-state transition gates.

For an exclusive-serving transfer, source and target serving authority for the transferred role must be unambiguous at commit. Multiple unrelated compatible roles do not violate the no-dual-active invariant.

## Single exclusion boundary

All handoff mutation that can decide serving authority must execute under one installation-wide exclusion boundary.

Current source already provides `InstallationMutex` and `RuntimeTransitionCoordinator`. The amendment must reuse that lineage rather than create a new distributed lock.

The implementation design must avoid nested acquisition of the same non-blocking installation mutex. A handoff coordinator that owns the exclusion boundary must use mutation primitives that do not reacquire the same lock internally.

Request, cancellation, retry/reconciliation, and final commit must re-read and revalidate authoritative handoff and runtime evidence inside the exclusion boundary before mutation.

## Commit boundary and mutation ordering

Takeover has one logical commit boundary with an explicit `COMMITTING` phase to make multi-file persistence recoverable.

Inside one installation-wide critical section, finalization must:

1. re-read the handoff record and require the same `handoff_id` in `PENDING`;
2. revalidate source participant, target participant/registration evidence, installation identity, database/namespace identity, compatibility, transferred role/capabilities, and absence of conflicting lifecycle activity;
3. durably transition the handoff record to `COMMITTING`;
4. atomically replace the Runtime Registry file with one result that marks the source `DETACHED` for the transferred serving authority and establishes the target as the valid participant for that authority while leaving unrelated compatible participants unchanged;
5. durably transition the same handoff record to `COMMITTED`.

Once `COMMITTING` is durable, `Cancel Detachment` is closed.

The `COMMITTING` phase is required because Runtime Registry and handoff-operation evidence are separate durable files and cannot be assumed to share one filesystem transaction.

## Interruption, reconciliation, and retry

Runtime Handoff must be interruption-safe and idempotently recoverable.

A non-terminal handoff with no current executor owning the installation-wide exclusion boundary is classified `INTERRUPTED`.

Reconciliation must reacquire the mutex and inspect both the handoff record and Runtime Registry:

- `PENDING` + unchanged pre-commit participant evidence: operator may retry finalization or cancel if all cancellation gates still pass;
- `COMMITTING` + exact pre-commit participant evidence: retry may continue the same finalization from the same `handoff_id`;
- `COMMITTING` + exact committed Runtime Registry result: reconciliation completes the operation to `COMMITTED` idempotently;
- any mixed, contradictory, foreign, or unprovable result: fail closed and expose explicit reconciliation-required state; do not offer cancellation or fabricate success.

A retry of the same handoff never creates a new handoff identity merely to bypass interrupted evidence.

## No-dual-active invariant

The core invariant is:

**one Runtime Handoff must never reach a committed or uncertain state where two independent runtime participants can both legitimately act as the exclusive serving authority for the same transferred role on one installation.**

Multiple compatible runtime participants remain possible where existing contracts permit them, but an exclusive-serving transfer must have an unambiguous source, target, and committed result.

If state cannot be proven safe, handoff and cancellation fail closed.

## Source-runtime behavior after finalization

After successful takeover commit:

- source runtime is `DETACHED` for the transferred serving participation;
- source heartbeat/automatic reactivation remains blocked for that detached identity;
- source runtime must not continue normal lifecycle mutation against the installation through the detached serving identity;
- stale local files or a surviving process do not restore authority;
- re-entry requires an explicit future supported lifecycle path, not silent registration.

## Site Settings → System projection

After the underlying authority is amended and implemented, Batch 2 may project Runtime Handoff under Site Settings → System.

The product projection may expose:

- current `installation_id`;
- current `runtime_id`;
- runtime participant state;
- compatibility and safe last-seen evidence;
- handoff operation state/classification;
- `Request Detachment` when eligible;
- `Cancel Detachment` when eligible;
- target-attachment readiness where safely derivable;
- takeover/finalization/reconciliation status and next valid action;
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

## Focused acceptance model

The amendment implementation must eventually prove at least:

- request creates one durable `PENDING` handoff and does not detach source;
- repeated request does not create a second active handoff;
- cancellation from eligible `PENDING` returns a deterministic `CANCELLED` result;
- cancel-vs-commit race is serialized and produces exactly one valid winner;
- commit transitions through durable `COMMITTING` and produces source-detached/target-valid registry state;
- interruption before registry mutation can be retried or cancelled only when evidence proves safety;
- interruption after registry mutation but before terminal record update reconciles idempotently to `COMMITTED`;
- mismatched target `installation_id`, namespace, database identity, compatibility, or handoff identity fails closed;
- target cannot silently generate a different installation identity and continue;
- detached source identity cannot silently heartbeat or re-register;
- unrelated compatible participants such as workers remain unchanged;
- stale/incompatible/conflicting participants continue to block unsafe shared-state transitions;
- package/Webcore lifecycle activity and Runtime Handoff cannot overlap unsafely;
- no database schema migration is introduced without new evidence;
- Site Settings action visibility derives from lifecycle truth rather than UI-local state.

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

Before Batch 2 implements executable Runtime Handoff controls, authoritative contract work must explicitly lock the source-backed model above, including:

1. Runtime Handoff terminology and ownership;
2. participant-state versus handoff-operation-state separation;
3. dedicated handoff record/store and one-non-terminal-handoff rule;
4. `PENDING / COMMITTING / COMMITTED / CANCELLED` persistence semantics plus derived `INTERRUPTED` classification;
5. Request / Cancel / Finalize / Reconcile transition rules;
6. cancellation eligibility and mutex-serialized race handling;
7. guarded target installation-identity adoption and registration requirements;
8. multi-runtime role boundary;
9. single installation-wide exclusion and no-nested-lock rule;
10. recoverable commit ordering;
11. no-dual-active invariant;
12. source-runtime behavior after finalization;
13. focused regression/compatibility acceptance criteria; and
14. the exact Site Settings projection boundary.

The amendment is now promoted into the authoritative contracts. Until the
underlying capability is implemented and accepted, Batch 2 may project existing
runtime participation evidence only and must not claim reversible detachment or
Runtime Handoff execution as delivered capability.
