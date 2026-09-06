# WU4 Batch 2 — Site Settings System Operational Projection Contract

Status: **AUTHORITATIVE CONTRACT / RECONCILED FOR IMPLEMENTATION**

Placement: Post-M3 — Webcore Product Completeness & Stabilization / WU4 Batch 2
Parent authority: `docs/54_webcore_site_settings_appearance_consolidation_contract.md`
Source promotion record: `precontracts/wu4_batch2_site_settings_system_operational_projection_precontract.md`
Implementation authorization: **NONE**
Release / tag / publication authorization: **NONE**

## 1. Purpose and governing boundary

This contract defines the bounded **Site Settings → System** product projection
for WU4 Batch 2. It is a child of `docs/54` and does not replace or duplicate
any existing lifecycle, Runtime Registry, Runtime Handoff, recovery, schema,
migration, package, Module, System Health, or reporting authority.

System organizes and summarizes authoritative evidence and eligible actions. It
must not become a package lifecycle, migration, schema, recovery, Runtime
Registry, Runtime Handoff, operation-ledger, or diagnostic engine. It must not
manufacture lifecycle classification, handoff eligibility, or raw internal
diagnostic output.

The canonical product destination is the **System** area under
`/admin/settings`. A standalone `/admin/settings/system-manager` product
destination is noncanonical under WU4; exact implementation-time route
reconciliation remains bounded by this contract and the parent contract.

Historical System Manager delivery and closure records in `docs/39` and
`docs/41` remain truthful historical lineage. They are not rewritten to imply
that Site Settings was always their delivery surface.

## 2. Locked System information architecture

### Canonical tab URL

Site Settings tabs use the canonical fragment URLs under the single
`/admin/settings` product surface: `#identity`, `#system`, `#security`,
`#email`, `#modules`, and `#health`. Fragment keys are deliberately separate
from internal DOM identifiers such as `site-settings-system`. The query-string
form `?section=*` is not a Site Settings tab-selection mechanism, and
`/admin/settings/system` is not a competing product destination. Lifecycle
action endpoints under `/admin/settings/system/*` remain implementation
endpoints and are not tab URLs.

The normal idle System area contains these three top-level operator groups,
in this order:

1. **Current System State**
2. **What's New**, when authoritative release/package metadata is available;
3. **Update**.

Operation Evidence, Compatibility, Repair, Retry, and Reconciliation are
contextual projections only. They appear after a concrete target/update
operation or authoritative recovery state makes them relevant. They are not
permanent idle groups, menu items, tabs, or destinations.

Runtime Participation, Runtime Handoff, and Permissions are not user-facing
System information groups in this slice. Their underlying authority and
enforcement boundaries remain unchanged.

These groups are product-facing organization only; they create no new
persistence domain or authority.

## 3. Current System State

Current System State presents the smallest trustworthy current summary needed
for operator comprehension, including where authoritative evidence exists:

- current Webcore version and package/release identity;
- committed and installed lifecycle state;
- sanitized Core/database schema and migration state;
- compatibility, maintenance, blocked, recovery-required, or indeterminate
  state;
- current `installation_id`;
- safe compatibility and last-seen evidence when a concrete target or
  operation makes it materially relevant.

It must privilege current truth over controls and must not expose raw migration
ledgers, recovery identities, filesystem paths, SQL, package internals, raw
registry tables, or raw exceptions.

## 4. Update

The projection preserves the accepted Webcore package lifecycle model from
`docs/39` and `docs/41`:

- released Webcore ZIP intake uses the existing private upload/staging boundary;
- preflight occurs before mutation;
- **Update** remains the operator-facing umbrella;
- Patch, Update, Upgrade, Database-only Update, and Repair classifications
  remain planner-derived technical outcomes, never manually selected by the
  operator;
- no generic **Update Database** operation exists;
- target package/version identity and compatibility are shown before mutation
  where available; and
- **What's New** may consume existing authoritative release/package metadata
  for the current or target transition.

What's New is not release authority, must not invent release notes, and does
not introduce online update discovery or download infrastructure. Operation
Evidence may be consumed only to report the current/latest materially relevant
transition, its sanitized result or reason, and the next valid action.

## 5. Contextual lifecycle outcomes

The projection preserves these distinct lifecycle meanings:

- **Repair** is same-version reconciliation or completion of eligible
  same-version work; it is not reset, rollback, destructive cleanup, downgrade,
  or reverse migration.
- **Retry** is available only when authoritative operation evidence permits it.
- **Reconciliation** is available only through an existing authoritative
  lifecycle path and remains distinct from Installer Adopt and Runtime Handoff.
- recovery-required state and restore prerequisites remain authoritative
  blockers; UI choice cannot bypass them.
- downgrade and reverse migration remain unsupported.

The user enters the normal lifecycle flow through **Update**. A technical
classification such as Repair may be shown only after the planner derives it.
Retry appears only when existing operation evidence makes retry eligible.
Reconciliation guidance/action appears only when authoritative lifecycle state
requires it. These contextual outcomes must not become manual classification
selection.

## 6. Runtime Participation & Runtime Handoff

The projection may expose authoritative runtime participation evidence:

- `installation_id`;
- local runtime `runtime_id` and RuntimeParticipant state;
- bounded context for other participants when needed for comprehension;
- safe compatibility and last-seen evidence;
- Runtime Handoff operation status/classification when available;
- target attachment/readiness evidence where safely derivable;
- sanitized blocker or interruption state; and
- the next valid action.

RuntimeParticipant states remain exactly:

`REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, `INCOMPATIBLE`.

Runtime Handoff is a separate durable operation model. Its pending operation is
not a participant state. Handoff granularity is one complete Runtime Registry
participant identified by `runtime_id`; role and capability data are
eligibility/compatibility evidence, not independently detachable state. Partial
role/capability detachment is not authorized. A `STALE` participant does not
authorize automatic takeover, and cancellation closes once durable
`COMMITTING` is reached. Unrelated compatible participants remain unaffected.

Executable controls such as **Request Detachment**, **Cancel Detachment**,
takeover/finalization, and interrupted-operation reconciliation remain absent
until the underlying Runtime Handoff capability is implemented and accepted.
This slice does not expose Runtime Participation, Runtime Handoff, detach, or
adoption UI. Site Settings must never write Runtime Registry or Runtime
Handoff evidence directly. `docs/34`, `docs/30`, and `docs/37` remain the
underlying authorities.

Installer Adopt, Existing-Runtime Webcore adoption/reconciliation, and Runtime
Handoff remain distinct operator/lifecycle actions.

## 7. Contextual compatibility

Compatibility presentation distinguishes current state from target eligibility
and provides readable evidence for:

- current Webcore/package compatibility;
- target package/version compatibility;
- runtime compatibility;
- schema/database compatibility;
- namespace and installation-identity constraints; and
- unsupported, blocked, or forward-only transition reasons.

Compatibility is shown only with a concrete target/update operation or another
authoritative state where it materially affects the next action. The UI
explains authoritative outcomes but cannot override them.

## 8. Operation Evidence reporting boundary

Operation Evidence is a **reporting feature/capability**, not System navigation,
System ownership, a persistence authority, or a lifecycle engine. System may
consume a bounded subset only for current/latest materially relevant Update,
Upgrade, Database-only Update, Repair, Retry, Reconciliation, or Runtime
Handoff reporting.

That contextual subset may include operation type/classification, relevant
source/target version or state, authoritative status, sanitized reason, next
valid action, and outstanding confirmation/recovery/compatibility prerequisites.

System must not expose a general historical browser, raw operation ledger, raw
exceptions, SQL, filesystem paths, staging internals, recovery identities,
arbitrary operator paths, tokens, or secrets. Diagnostic, audit, and historical
reporting remains with System Health or a separately authorized delivered
capability; no such subsystem is created here.

## 9. Permissions

Permission boundaries remain separate and are enforced even though they are not
rendered as a permanent System information panel:

- `admin.access` is the Admin-surface access prerequisite;
- `system.webcore.manage` is the expected operator permission lineage for
  Webcore lifecycle and Runtime Handoff actions;
- ordinary Site Settings write permission does not grant Webcore lifecycle
  authority; and
- `modules.manage` remains separate and belongs to the peer Modules area.

Read visibility and executable-action availability must derive from authoritative
permission and lifecycle evidence, not cosmetic hide/show behavior. A narrower
Runtime Handoff capability requires separate source evidence and authority.

## 10. Peer-area and presentation boundaries

System is a peer of Modules and System Health under Site Settings. Module
lifecycle remains owned by Module authority and its projection. System Health
remains the separate read-only reporting projection and product home for
diagnostic/reporting views of Operation Evidence within its inputs.

System presents state before action, uses evidence-backed next-action guidance,
and does not introduce generic maintenance controls. Idle presentation is
intentionally minimal: Current System State, What's New when available, and
Update. Presentation may improve comprehension, responsive behavior,
accessibility, and action hierarchy, but must not change lifecycle meaning or
authority ownership.

## 11. Explicit exclusions and implementation gate

This contract does not authorize:

- Batch 2 source/runtime implementation;
- Runtime Handoff implementation or new Runtime Registry persistence;
- schema/database changes or generic database-update controls;
- online update discovery/download infrastructure;
- downgrade, reverse migration, automatic failover, orchestration, DNS, or
  load-balancer mutation;
- raw lifecycle/debug dashboards, operation-history persistence, or a new
  historical-report subsystem;
- new recovery or Module lifecycle semantics;
- System Health implementation;
- production reconciliation, release, tag, publication, distribution, branch
  integration, or branch deletion.

Exact route/action names, adapter structure, grouping components, sanitized
labels, read-only field selection, contextual Operation Evidence placement, and
What's New placement remain implementation-time details only when they stay
within this authority boundary.

## 12. Acceptance boundary

A separately authorized implementation may be accepted only when evidence and
human/product review confirm:

- `/admin/settings` is canonical and System is reachable there;
- idle System exposes Current System State, What's New when available, and
  Update in that order;
- contextual Operation Evidence and Compatibility are not permanent idle
  groups or destinations;
- Update is the only user-facing lifecycle umbrella and classifications remain
  planner-derived;
- Database-only Update is eligibility-bound and no generic Update Database
  action exists;
- What's New uses only existing authoritative release/package metadata;
- Repair, Retry, Reconciliation, Installer Adopt, Existing-Runtime adoption,
  and Runtime Handoff remain distinct;
- Runtime Participation, Runtime Handoff, detach, and adoption UI are absent
  until separately authorized capability implementation and acceptance;
- handoff cancellation closes at durable `COMMITTING` and `STALE` does not
  authorize automatic takeover;
- Operation Evidence remains contextual rather than historical System
  reporting;
- `system.webcore.manage`, `admin.access`, Site Settings write authority, and
  `modules.manage` remain distinct; and
- no second lifecycle, registry, recovery, schema, migration, package,
  release-metadata, or operation-history authority is created.

This reconciliation authorizes no Runtime Handoff, detach, adoption, registry,
schema, or lifecycle-semantics implementation. It records the accepted
contextual System presentation target only.
