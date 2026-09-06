# WU4 Batch 2 — Site Settings System Operational Projection Pre-contract

Pre-contract lifecycle: MATERIALIZED / PROMOTION READY / NOT PROMOTED
Placement: Post-M3 — Webcore Product Completeness & Stabilization / WU4 Batch 2
Parent authority: `docs/54_webcore_site_settings_appearance_consolidation_contract.md`
Implementation authorization: NONE
Release / tag / publication authorization: NONE

## 1. Purpose

Materialize the bounded implementation-contract candidate for **Site Settings → System** inside WU4 Batch 2.

This pre-contract consolidates already-authoritative Webcore lifecycle, Runtime Registry, Runtime Handoff, recovery, compatibility, permission, and operator-presentation semantics into one product-facing System projection boundary. It does not create a new lifecycle authority and does not authorize implementation.

The target implementation contract must remain a child of `docs/54_webcore_site_settings_appearance_consolidation_contract.md`. It must preserve historical System Manager delivery records as lineage rather than rewriting them as if Site Settings had always been the product surface.

## 2. Governing invariant

Site Settings → System is an **operational projection** of existing Webcore lifecycle authority.

It may organize, summarize, and expose existing authoritative evidence and eligible actions, but it must not:

- become a package lifecycle engine;
- become a migration or schema engine;
- become a recovery engine;
- become Runtime Registry authority;
- own Runtime Handoff persistence or state transitions;
- manufacture lifecycle or handoff eligibility;
- classify transitions from operator choice;
- create a second operation ledger;
- expose raw internal diagnostics as product UI.

## 3. Authoritative lineage

The System projection must reconcile, without duplicating authority:

- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md` for the accepted Webcore lifecycle operator baseline;
- `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md` for retained planner-derived lifecycle semantics and the consumer/coordinator boundary;
- `docs/34_multi_installation_isolation_foundation_contract.md` for Runtime Registry and Runtime Handoff mechanics;
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md` for adoption/reconciliation terminology separation;
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md` for database ownership, compatibility, and lifecycle consequences;
- `docs/54_webcore_site_settings_appearance_consolidation_contract.md` for Site Settings ownership, Batch 2 topology, projection rules, and permission distinction.

Historical System Manager routes and labels remain implementation lineage only where superseded by WU4.

## 4. Canonical product surface

The canonical product-facing System destination is the **System** area under `/admin/settings`.

`/admin/settings/system-manager` is noncanonical under WU4 and is not required as a retained compatibility route. Exact internal route-removal/reconciliation mechanics remain implementation-time details already bounded by the parent contract.

The System area must not recreate a standalone System Manager product identity beside Site Settings.

## 5. Locked System information architecture

The System area contains exactly these six top-level operator groups:

1. **Current System State**
2. **Update & Upgrade**
3. **Repair, Retry & Reconciliation**
4. **Runtime Participation & Runtime Handoff**
5. **Compatibility**
6. **Permissions**

**Operation Evidence is not a top-level System group or destination.** It is a reporting feature/capability that System may consume contextually for version-transition and lifecycle reporting. Diagnostic, historical, and audit-oriented operation reporting belongs with System Health reporting or a dedicated historical-report capability where such a capability is separately authoritative and delivered.

This pre-contract does not create a new historical-report subsystem. Until such a subsystem exists, System may surface only the bounded operation evidence required to explain the current or latest materially relevant transition.

These six groups are product-facing organization only. They do not create new authorities or persistence domains.

## 6. Current System State

The landing group must present the smallest trustworthy current-state summary needed for operator comprehension:

- current Webcore version and package/release identity where authoritative evidence exists;
- committed and installed lifecycle state;
- Core/database schema and migration state in sanitized product form;
- current compatibility summary;
- current recovery-required, blocked, maintenance, or indeterminate state where applicable;
- current `installation_id`;
- current/local runtime `runtime_id` and RuntimeParticipant state;
- bounded participant summary for other runtime participants only where materially relevant to compatibility or Runtime Handoff comprehension;
- concise runtime compatibility/last-seen evidence where safe and useful.

The group must privilege current truth over action controls. It must not expose raw migration ledgers, recovery identities, filesystem paths, SQL, package internals, raw registry tables, or raw exceptions.

## 7. Update & Upgrade

The projection preserves the accepted Webcore package lifecycle operator model:

- released Webcore ZIP intake uses the existing private upload/staging boundary;
- preflight occurs before mutation;
- **Update** remains the operator-facing umbrella;
- eligible action/classification is planner-derived and may surface as:
  - Patch;
  - Update;
  - Upgrade;
  - Database-only Update;
  - Repair, where the lifecycle authority classifies it that way;
- the operator must not choose lifecycle classification manually;
- `DATABASE_UPDATE` may be shown as **Database-only Update** only when the existing classifier/planner makes it eligible;
- there is no generic globally available **Update Database** operation;
- target version/package identity and compatibility must be shown before mutation when available;
- **What's New** may be presented from existing authoritative release/package metadata for the current or target version transition; it is not release authority, must not invent release notes, and does not introduce online update discovery or download infrastructure;
- bounded version-transition reporting may consume Operation Evidence to present result state, sanitized reason, and next valid action after operation completion or failure.

Online discovery/download of updates is not introduced by this contract. Package-source expansion would require separate authority.

## 8. Repair, Retry & Reconciliation

The System projection must preserve existing lifecycle distinctions:

- **Repair** is same-version reconciliation or completion of eligible same-version work. It is not reset, rollback, destructive cleanup, downgrade, or reverse migration.
- **Retry** is available only when authoritative operation evidence makes retry eligible.
- **Reconciliation** is available only where an existing authoritative lifecycle path permits it and must remain distinct from Installer Adopt and Runtime Handoff.
- recovery prerequisites and recovery-required state remain authoritative blockers;
- unresolved restore/recovery state must not be bypassed by UI choice;
- controlled reason and next valid action must be shown for blocked or failed states;
- downgrade and reverse migration remain unsupported.

Operation Evidence may be consumed here only to explain the current/latest materially relevant repair, retry, or reconciliation transition and its next valid action. It must not turn this group into an operation-history destination.

The projection must not collapse Repair, Retry, Reconciliation, Existing-Runtime adoption, Installer Adopt, and Runtime Handoff into one generic recovery action.

## 9. Runtime Participation & Runtime Handoff

The System projection may expose existing runtime-participation evidence including:

- `installation_id`;
- current/local runtime `runtime_id`;
- RuntimeParticipant state;
- bounded summary of other participants where required for handoff or compatibility comprehension;
- safe compatibility and last-seen evidence;
- Runtime Handoff operation status/classification when authoritative evidence exists;
- target attachment/readiness evidence where safely derivable;
- sanitized blocker or interruption state;
- next valid action.

RuntimeParticipant states remain exactly:

`REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, `INCOMPATIBLE`.

Runtime Handoff remains a separate durable operation model. Pending handoff is not a RuntimeParticipant state.

Executable handoff actions such as **Request Detachment**, **Cancel Detachment**, takeover/finalization, or interrupted-operation reconciliation may appear only after the underlying Runtime Handoff capability is implemented and accepted. Until then, Batch 2 may project read-only Runtime Participation evidence only.

The projection must preserve all authoritative handoff boundaries:

- whole-participant / whole-`runtime_id` transfer granularity;
- role/capability data are eligibility/compatibility evidence, not independently detachable state;
- no partial role/capability detachment;
- no automatic takeover because a participant is `STALE`;
- cancellation closes once durable `COMMITTING` is reached;
- detached source authority must not silently resume;
- unrelated compatible runtime participants remain unaffected.

Operation Evidence may be consumed contextually to explain the current/latest Runtime Handoff transition, interruption, result, and next valid action. It is not a handoff ledger UI and does not become a separate System destination.

Site Settings must never write Runtime Registry or Runtime Handoff evidence directly.

## 10. Compatibility

Compatibility presentation must distinguish current state from target eligibility and provide operator-readable evidence for:

- current Webcore/package compatibility;
- target package/version compatibility;
- runtime compatibility;
- schema/database compatibility;
- namespace/installation identity constraints where material;
- unsupported or blocked transition reason;
- forward-only transition boundary.

Compatibility is not a user preference. The UI may explain authoritative outcomes but must not override them.

## 11. Operation Evidence feature boundary

Operation Evidence is a **reporting feature/capability**, not a top-level Site Settings → System menu, tab, group, persistence authority, or lifecycle engine.

Its diagnostic, audit-oriented, and historical presentation belongs to **System Health reporting** or to a separately authorized and delivered **historical-report** capability. This pre-contract does not claim that a separate historical-report subsystem already exists and does not authorize creating one.

Site Settings → System may consume a bounded subset of Operation Evidence only when directly relevant to **version transition or lifecycle-operation reporting**, including Update, Upgrade, Database-only Update, Repair, Retry, Reconciliation, or Runtime Handoff.

That contextual projection may include:

- operation type/classification;
- source/current and target version or state where relevant;
- completed, blocked, failed, interrupted, indeterminate, cleanup-pending, recovery-required, or equivalent authoritative status;
- sanitized reason;
- next valid operator action;
- whether confirmation, recovery, or compatibility prerequisites remain outstanding.

System must not expose Operation Evidence as a general historical browser or raw diagnostic surface. It must not expose:

- raw operation ledger records;
- raw exceptions;
- SQL;
- filesystem paths;
- package staging internals;
- recovery identities;
- arbitrary operator-owned paths;
- internal tokens or secrets.

## 12. Permissions

The System projection must preserve permission distinctions rather than flatten them into one Site Settings write capability.

Required boundaries:

- `admin.access` remains the Admin-surface access prerequisite;
- `system.webcore.manage` remains the expected operator permission lineage for Webcore lifecycle and Runtime Handoff actions;
- ordinary Site Settings write permission does not implicitly grant Webcore lifecycle authority;
- `modules.manage` remains separate and belongs to the peer Modules area;
- read visibility and executable-action availability must be derived from authoritative permission and lifecycle evidence, not cosmetic hide/show logic alone.

A narrower Runtime Handoff-specific capability may be introduced only if direct implementation evidence justifies it and a bounded authority amendment is approved. This pre-contract does not invent one.

## 13. Action and presentation rules

System must present **state before action**.

Action hierarchy must reflect authoritative eligibility:

- unavailable actions are not promoted as ordinary primary buttons;
- blocked states include concise reasons where safe;
- dangerous or irreversible-looking actions must not be represented as routine lifecycle shortcuts;
- classifications remain engine-derived;
- next-action guidance must be evidence-backed;
- no generic maintenance button may bypass the specific lifecycle path that owns the operation.

Presentation may reorganize historical System Manager content for clearer comprehension, responsive layout, accessibility, and action hierarchy, but must not change lifecycle meaning.

## 14. Relationship to peer Site Settings areas

System is a peer of **Modules** and **System Health** under Site Settings.

- Module lifecycle capability is not nested inside System for Batch 2 product IA.
- `modules.manage` and Module lifecycle semantics remain owned by the Module authority and the Batch 2 Modules projection.
- System Health remains a separate Batch 3 read-only reporting projection and is the product home for diagnostic/reporting views of Operation Evidence when such evidence is within its authoritative reporting inputs.
- A dedicated historical-report destination, if introduced later, requires its own authoritative scope; this pre-contract merely reserves it as a valid future home for historical Operation Evidence rather than assigning that role to System.
- Localization, Site Identity, Appearance, Security, and Email retain their parent-contract ownership boundaries.

Cross-links may be used for comprehension, but ownership must remain singular.

## 15. Explicit exclusions

This pre-contract does not authorize or introduce:

- Runtime Handoff implementation;
- new Runtime Registry persistence;
- schema/database changes;
- generic database update controls;
- online update discovery/download infrastructure;
- downgrade or reverse migration;
- automatic failover;
- process/server/container orchestration;
- DNS or load-balancer mutation;
- generic cluster/consensus behavior;
- raw lifecycle/debug dashboards;
- new operation-history persistence or a historical-report subsystem;
- new recovery infrastructure;
- new Module lifecycle semantics;
- System Health implementation;
- production reconciliation;
- release, tag, publication, or distribution;
- branch integration or deletion.

## 16. Implementation-time dispositions

The following remain implementation-time/source-evidence details bounded by this contract candidate:

1. exact route/action names under `/admin/settings`;
2. exact view/service adapter structure used to project existing lifecycle evidence;
3. exact internal reference cleanup for retired `/admin/settings/system-manager` route usage;
4. exact grouping/layout component reuse from the existing Admin Page Frame;
5. exact labels for sanitized blocked/indeterminate/recovery states where existing product terminology does not already lock wording;
6. exact permission-to-visibility mapping for read-only evidence versus executable lifecycle actions, provided the authority distinctions above remain intact;
7. exact read-only Runtime Participation fields that are safe and useful on the current source baseline;
8. exact contextual placement of version-transition Operation Evidence inside the relevant operational group, provided it does not become a seventh top-level group or historical browser;
9. exact contextual placement and display treatment of authoritative **What's New** metadata inside Update & Upgrade, provided it remains a consumer of existing release/package metadata rather than release authority.

These dispositions do not authorize new architecture or product capability.

## 17. Acceptance criteria

A separately authorized implementation may be accepted only when objective evidence and required human/product review demonstrate that:

- `/admin/settings` is the canonical parent and System is reachable as its System area;
- no competing standalone System Manager product destination is restored;
- System exposes exactly six top-level operator groups and does not expose Operation Evidence as a seventh group/destination;
- current system state is understandable before lifecycle actions are presented;
- Update remains the operator umbrella and classification remains planner-derived;
- Database-only Update appears only when eligible and no generic Update Database action exists;
- **What's New** is preserved from authoritative release/package metadata where available, without invented release notes or online update-discovery semantics;
- Repair, Retry, Reconciliation, Existing-Runtime adoption, Installer Adopt, and Runtime Handoff remain semantically distinct;
- compatibility and blocker reasons are sanitized and understandable;
- Runtime Participation distinguishes current/local runtime identity from bounded multi-participant context without exposing registry internals;
- executable Runtime Handoff controls are absent until the underlying capability is implemented and accepted;
- when later enabled, handoff control visibility exactly follows authoritative eligibility and preserves whole-participant granularity;
- contextual Operation Evidence is limited to the current/latest materially relevant version/lifecycle transition and does not become general historical reporting inside System;
- `system.webcore.manage`, `admin.access`, Site Settings write authority, and `modules.manage` remain distinct;
- raw internal lifecycle/recovery/package evidence is not exposed;
- no second lifecycle, Runtime Registry, recovery, schema, migration, package, release-metadata, or operation-history authority is created;
- desktop/mobile responsive behavior, accessibility, action hierarchy, and operator comprehension pass human/product review.

## 18. Promotion readiness checklist

Before promotion into an authoritative System-specific Batch 2 contract, review must confirm:

1. no conflict with `docs/54` parent WU4 authority;
2. no conflict with historical `docs/39` / `docs/41` accepted lifecycle semantics;
3. no conflict with promoted Runtime Handoff authority in `docs/34`, `docs/30`, and `docs/37`;
4. no implied Runtime Handoff implementation claim;
5. no implied Batch 2 implementation-start claim;
6. no new product/architecture decision hidden inside an implementation-time detail;
7. exact authority ownership remains singular;
8. Operation Evidence remains a reporting feature/capability rather than System navigation/ownership;
9. System Health/historical-report placement does not silently create a new reporting subsystem;
10. historical **What's New** capability is preserved only as a consumer of authoritative release/package metadata;
11. acceptance criteria are sufficient to prevent UI-owned lifecycle or release-metadata semantics.

## 19. Current verdict

Pre-contract status: **MATERIALIZED / PROMOTION READY / NOT PROMOTED**.

Runtime Handoff authority prerequisite: **PROMOTED / COMPLETE as contract authority; implementation NOT STARTED**.

Locked product-IA disposition: **six System groups; Operation Evidence is a reporting feature/capability, with System limited to contextual version/lifecycle transition reporting**.

Historical capability preservation: **What's New retained under Update & Upgrade as a consumer of existing authoritative release/package metadata**.

Batch 2 System implementation status: **NOT STARTED / NOT AUTHORIZED**.

Final promotion-readiness review: **PASS**. Promotion and implementation remain separate decisions.