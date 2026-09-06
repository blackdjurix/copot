# Runtime Handoff — Authoritative Amendment Promotion Proposal

Proposal lifecycle: MATERIALIZED / REVIEW READY / NOT PROMOTED
Placement: Post-M3 — Webcore Product Completeness & Stabilization / WU4 Batch 2 prerequisite authority amendment
Source pre-contract: `precontracts/runtime_handoff_reversible_detachment_precontract.md`
Implementation authorization: NONE
Release / tag / publication authorization: NONE

## 1. Purpose

Prepare the exact bounded authoritative-document delta required to promote the reviewed Runtime Handoff & Reversible Detachment model without mutating authoritative contracts yet.

This proposal is a promotion-review artifact only. It does not itself change authority, implementation state, runtime behavior, schema, branch lifecycle, release state, or Site Settings delivery status.

The target authoritative amendment destinations are:

- `docs/34_multi_installation_isolation_foundation_contract.md`;
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`;
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`;
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md`.

The promotion model preserves historical wording and adds bounded current authority. Do not rewrite predecessor history to imply Runtime Handoff existed before this amendment.

## 2. Promotion invariant

Promotion must establish exactly one new underlying lifecycle capability family:

**Runtime Handoff** = transfer of one bounded serving participation from one runtime participant to another for the same existing installation, with reversible detachment before commit and fail-closed reconciliation after interruption.

It must remain distinct from:

- Installer `Adopt / Use Existing Installation`;
- Existing-Runtime Webcore exact-match adoption;
- legacy Webcore reconciliation;
- package Update / Upgrade / Repair;
- database ownership transfer;
- runtime orchestration or automatic failover.

Runtime Registry remains the singular participant authority. Site Settings remains a projection.

## 3. Proposed amendment to `docs/34_multi_installation_isolation_foundation_contract.md`

### Placement

Extend the current `### Runtime Registry` / runtime-coordination authority with a bounded subsection titled:

`#### Runtime Handoff and reversible detachment`

### Proposed authoritative wording

Runtime Registry additionally supports a bounded **Runtime Handoff** operation for intentionally transferring one serving participation of an existing installation from a source runtime to a target runtime.

Runtime Handoff does not create a new installation, transfer database ownership, or reuse the source runtime identity. The installation retains the same stable `installation_id`; source and target remain distinct participants with distinct stable `runtime_id` values.

The existing RuntimeParticipant vocabulary remains unchanged:

`REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, `INCOMPATIBLE`.

Pending handoff is not a RuntimeParticipant state. Runtime Handoff uses separate durable installation-scoped operation evidence with at least:

`PENDING → COMMITTING → COMMITTED`

or

`PENDING → CANCELLED`.

A non-terminal handoff whose executor no longer owns valid coordination is classified fail-closed as interrupted until deterministic reconciliation proves the next safe action.

`Request Detachment` creates one durable pending handoff without immediately detaching the source. `Cancel Detachment` is permitted only while the same handoff remains `PENDING` and authoritative evidence proves takeover has not entered commit. A successful cancellation closes the handoff as `CANCELLED` and preserves valid source participation.

Takeover commit must execute under the existing installation-wide coordination lineage. One coordinator owns the `InstallationMutex` acquisition for the handoff decision; nested reacquisition of the same non-blocking installation mutex inside that critical section is prohibited.

Finalization must revalidate the handoff identity, installation identity, source and target runtime identities, compatibility, transferred serving role/capability evidence, database/namespace identity, and conflicting lifecycle state while the exclusion boundary is held. It then durably moves the handoff to `COMMITTING`, atomically replaces Runtime Registry participant evidence so the source is detached for the transferred serving participation and the target becomes the valid participant for that authority, and finally records `COMMITTED`.

Once `COMMITTING` is durable, cancellation is no longer valid.

The governing handoff invariant is:

**A Runtime Handoff must never create ambiguous dual-active authority for the same transferred exclusive-serving role on one installation.**

Existing multi-runtime topology remains valid. Unrelated compatible participants, including worker or secondary roles not bound to the handoff, remain registered and continue to participate in compatibility/shared-state gates.

A target runtime must use its own stable `runtime_id` and must prove the existing installation identity before registration/activation. If target-local installation identity storage is empty, establishing the existing `installation_id` is permitted only through a guarded set-if-empty handoff/adoption path bound to authoritative pending-handoff evidence. A conflicting local installation identity fails closed.

Runtime Handoff durable evidence should remain installation-scoped under the existing `.copot-lifecycle` lineage unless future evidence proves that storage insufficient. The package-specific `LifecycleOperationRecord` is not Runtime Handoff authority and must not be reused unchanged. No database schema change is required by this amendment.

A detached source runtime identity must not silently resume authority through heartbeat or re-registration. Re-entry requires an explicit supported lifecycle path.

### Historical preservation note

Retain the existing WU1–WU6 completion statements unchanged. This amendment adds later authority and must not reclassify the historical Multi-Installation workstream as incomplete.

## 4. Proposed amendment to `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`

### Placement

Add a terminology/relationship subsection near `Boundary and actions` or `Relationship to completed and excluded work` titled:

`## Relationship to Runtime Handoff`

### Proposed authoritative wording

**Runtime Handoff** is a separate Webcore runtime-participation lifecycle capability and is not part of this contract's `Adopt Existing Installation` or `Reconcile Legacy Installation` actions.

This contract's adoption/reconciliation semantics establish or reconcile committed Webcore lifecycle state for an existing runtime. Runtime Handoff instead transfers bounded serving participation for the same already-identified installation between distinct runtime participants while preserving the installation identity and existing database state.

Runtime Handoff must not be used to bypass the proof, recovery, package identity, schema/migration, or committed-state requirements of this contract. Likewise, exact-match adoption or legacy reconciliation does not by itself detach a runtime participant or authorize transfer of serving participation to another runtime.

Where both capabilities are relevant, each must satisfy its own authoritative gates. The terms `Adopt Existing Installation`, `Reconcile Legacy Installation`, and `Runtime Handoff` remain distinct operator/lifecycle actions.

### Historical preservation note

Do not alter the completed IU1/IU2 historical acceptance state. This is a terminology and authority-boundary amendment only.

## 5. Proposed amendment to `docs/37_database_ownership_lifecycle_management_foundation_contract.md`

### Placement

Add a bounded subsection in the System Manager / lifecycle authority region titled:

`### Runtime participation transfer boundary`

### Proposed authoritative wording

Runtime Handoff is a Webcore system-lifecycle capability operating over existing installation/runtime authority. It does not transfer database-table ownership, migration authority, schema ownership, or namespace ownership.

All database ownership, schema compatibility, migration authorization, and owner-bounded transition rules in this contract remain unchanged during Runtime Handoff.

Runtime Handoff may transfer which compatible runtime participant serves one bounded runtime role for an existing installation, but it must not mutate database schema merely to perform that transfer. A schema change is outside the handoff path unless separately justified by an independently authorized lifecycle requirement.

Before handoff commit, target runtime compatibility must be evaluated against the same installation, package/Webcore state, database/namespace identity, and relevant capability requirements. Unsafe or unprovable compatibility fails closed.

Package/Webcore lifecycle mutation and Runtime Handoff must not overlap unsafely. A non-terminal Runtime Handoff is conflicting lifecycle evidence for shared-state mutation, and handoff creation must reject incompatible non-terminal package/Webcore lifecycle activity.

The existing `system.webcore.manage` authority remains the expected operator permission lineage for Runtime Handoff unless a later bounded security contract explicitly narrows it. `admin.access`, `modules.manage`, and ordinary Site Settings write permissions do not implicitly grant Runtime Handoff authority.

### Historical preservation note

Retain the WU1–WU6 CLOSED state. This amendment adds a later system-lifecycle capability boundary and does not reopen the completed Database Ownership workstream.

## 6. Proposed amendment to `docs/54_webcore_site_settings_appearance_consolidation_contract.md`

### Placement A

Extend the authority-preservation table row for System lifecycle.

### Proposed replacement row meaning

System lifecycle, adoption, recovery, compatibility, Runtime Handoff, and lifecycle semantics remain owned by existing Webcore lifecycle/runtime authority. Site Settings remains an operational projection only.

### Placement B

Extend `## System, Modules, and System Health boundary`.

### Proposed authoritative wording

For Site Settings → System, Runtime Handoff may be exposed only after the underlying Runtime Handoff authority is promoted and implemented/accepted.

The System projection may expose, where authoritative evidence supports it:

- current `installation_id`;
- current runtime `runtime_id`;
- runtime participant state;
- safe compatibility/last-seen evidence;
- Runtime Handoff operation status/classification;
- `Request Detachment` when eligible;
- `Cancel Detachment` when eligible;
- target-attachment readiness where safely derivable;
- finalization/reconciliation state;
- sanitized blocking/failure reason; and
- next valid operator action.

Site Settings must derive every executable handoff action from underlying lifecycle authority. It must not write Runtime Registry files directly, manufacture eligibility, own handoff persistence, invent a second state machine, or reinterpret `STALE` as automatic takeover permission.

Runtime Handoff presentation must remain distinct from Installer Adopt and Existing-Runtime Webcore adoption/reconciliation terminology.

Batch 2 System may independently project read-only/current Runtime Participation evidence already authoritative before Runtime Handoff implementation. Executable Runtime Handoff actions remain unavailable until underlying capability implementation and acceptance are complete.

### Placement C

Extend Batch 2 acceptance requirements with:

- runtime identity and participation status are understandable without exposing internal registry mechanics;
- detachment/cancellation visibility matches authoritative eligibility;
- interrupted/reconciliation-required handoff state is fail-closed and comprehensible;
- no UI path implies that Installer Adopt, legacy Webcore adoption, and Runtime Handoff are the same operation.

### Historical preservation note

Do not change Batch 1 accepted state or mark Batch 2 started. Promotion of underlying Runtime Handoff authority does not itself authorize or implement Batch 2.

## 7. Cross-document consistency rules for promotion

The authoritative amendment must preserve all of the following:

1. Runtime Handoff is one bounded Webcore runtime/system lifecycle capability, not four independent copies across four contracts.
2. `docs/34` carries the primary runtime-participation/handoff mechanics authority.
3. `docs/30` carries the terminology and non-overlap boundary against existing-runtime adoption/reconciliation.
4. `docs/37` carries database ownership/lifecycle non-transfer and compatibility/exclusion consequences.
5. `docs/54` carries the Site Settings product-projection boundary only.
6. Participant states remain unchanged; handoff operation state is separate.
7. Existing multi-runtime topology remains supported.
8. Cancel Detachment exists and is valid only before durable `COMMITTING`.
9. No schema migration is implied.
10. No destructive cleanup, production reconciliation, runtime mutation, release, tag, publication, or implementation is authorized by promotion.

## 8. Promotion-time documentation disposition

When promotion is explicitly authorized:

- amend the four destination contracts only with the bounded wording above or semantically equivalent text;
- mark `precontracts/runtime_handoff_reversible_detachment_precontract.md` as promoted/historical provenance;
- preserve `concepts/copot_runtime_handoff_reversible_detachment_concept.md` as planning lineage;
- update this proposal to promoted/historical provenance or retain it as review evidence according to repository convention;
- do not start implementation in the same action unless separately authorized;
- independently verify the resulting remote commit and exact destination-document deltas.

## 9. Review verdict

Promotion wording status: **READY FOR HUMAN/GPT PROMOTION REVIEW**

Known higher-level conflict: **NONE FOUND in the reviewed destination-contract boundaries**

Implementation status: **NOT STARTED / NOT AUTHORIZED**

Promotion status: **NOT PROMOTED**
