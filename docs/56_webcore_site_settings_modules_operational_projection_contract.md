# WU4 Batch 2 — Site Settings Modules Operational Projection Contract

**Contract status:** AUTHORITATIVE CONTRACT / IMPLEMENTATION NOT STARTED
**Parent authority:** `docs/54_webcore_site_settings_appearance_consolidation_contract.md`
**Promotion lineage:** `precontracts/wu4_batch2_site_settings_modules_operational_projection_precontract.md`
**Implementation authorization:** NONE

This authoritative child contract defines the bounded product and authority
target for the Webcore-owned Modules operational projection under Site
Settings. It records the approved promotion of the Modules pre-contract. The
contract itself does not authorize source, runtime, test, schema, database, or
lifecycle implementation.

## 1. Purpose and governing boundary

The canonical product surface is Site Settings → Modules. It is an Admin
operator projection of existing Module lifecycle and package-management
capability; it does not create or redesign that capability.

The governing invariant is:

> Re-home the Webcore Module operator surface. Preserve Module lifecycle authority.

Site Settings is a consumer of existing authoritative Module services and
state. It is not a Module lifecycle engine, package engine, dependency
resolver, migration authority, provisioning authority, Module registry, or
Module-owned persistence authority.

## 2. Authority and lineage

The parent WU4 contract remains authoritative for the Site Settings
consolidation boundary. Accepted Module lineage includes
`docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`,
`docs/04_module_system.md`, and the existing Core/Webcore Module lifecycle,
package, discovery, repository, planner, diagnostic, and result services.

Existing Module authorities remain singular, including discovery, repository and
runtime state, dependency/conflict analysis, permission metadata, lifecycle
transition planning and execution, package validation and lifecycle,
persistence, migration/provisioning, result, recovery, and reconciliation
semantics.

The historical System Manager and standalone Module Manager surfaces are
bounded source and presentation evidence only. They do not establish current
product ownership, routing, permission, lifecycle, package, persistence, or
architecture. Useful presentation patterns may be reconciled during a later
authorized implementation without restoring either product owner or cloning
either surface wholesale.

## 3. Canonical product surface and composition

The canonical product-facing destination is:

`/admin/settings#modules`

Site Settings is the product projection host. Internal DOM or panel identifiers,
such as `site-settings-modules`, may remain for ARIA, JavaScript, or
implementation structure; they are not the browser URL format.

Module Detail, lifecycle actions, package intake, and operation-result transport
remain subordinate to the canonical Site Settings → Modules composition. They
must not route through a System-owned product namespace, restore the
superseded System Manager product identity, or restore standalone Module
Manager as a competing product owner. Exact internal route, action, package,
and result transport mechanics remain implementation-time dispositions; this
contract does not invent a specific endpoint.

## 4. Inventory information hierarchy

The desktop inventory uses exactly these four primary information columns:

| Module | Version | Issue | Status |
| --- | --- | --- | --- |

There is no `Actions` column, `Discovery` column, or `Notes` column. Lifecycle
mutation actions belong in Module Detail unless authoritative source
reconciliation proves a required existing shared-product exception.

### Module

Show the human-facing Module title as the primary value. Show the technical
Module identity/name as secondary, subdued evidence.

### Version

Show the current/effective Module version. A newer available package version
may be surfaced concisely only where authoritative package evidence exists.
Package classification, compatibility detail, and transition evidence belong
in Module Detail.

### Issue

Issue is diagnostic/operational evidence and is distinct from lifecycle Status.
When no issue is present, use a neutral empty representation such as `—`.
When authoritative evidence supplies one, use a concise human-readable
category such as `Dependency error`, `Metadata mismatch`, `Discovery failure`,
or `Package integrity error`, or equivalent truthful terminology.

Do not mechanically suffix every label with “issue”. Do not expose raw internal
diagnostic codes, paths, exceptions, dependency dumps, checksums, stack traces,
or other implementation details unnecessary for operator action.

Issue presentation reflects authoritative severity. No new severity model is
introduced. For multiple issues, the inventory may show the most severe or
operationally significant issue plus a bounded indication such as `+N more`
only when ordering/severity can be derived truthfully from existing evidence.

### Status

Status is lifecycle state only. Existing lifecycle semantics such as `Enabled`,
`Disabled`, and `Not installed` remain authoritative. Health and diagnostic
state must not be merged into lifecycle Status.

## 5. Search, interaction, and responsive behavior

Scalable client-side search and filtering are preserved accepted capability of
the Modules projection. Exact control placement, responsive presentation, and
implementation mechanics remain implementation-time details, bounded to
authoritative inventory data.

Use existing shared Admin row interaction primitives where applicable,
including the existing shared hover behavior. The whole inventory row is the
primary open target for Module Detail. Do not add redundant `Open`, `Manage`,
or equivalent row buttons solely to open detail. This contract introduces no
new interaction framework.

The desktop table uses the available content width rather than shrinking to
content. `Module` is the flexible primary column; `Version` and `Status` are
compact; `Issue` is bounded but wide enough for concise diagnostic categories.
The four columns must not be divided equally merely for convenience. Exact
percentages are implementation-time layout details, not contract constants.

On mobile, do not force the desktop four-column table into a compressed
horizontal presentation. Use the accepted shared responsive table/row
primitive where available, with stacked presentation preserving Module
identity, version, issue signal/type, lifecycle status, and whole-row tap-to-open
behavior.

## 6. Module Detail

Module Detail is the operational surface for:

- complete lifecycle evidence;
- dependency and conflict context;
- discovery and metadata evidence;
- issue details, severity, and impact where authoritative;
- a recommended resolution only where a truthful recommendation exists;
- package/update evidence;
- eligibility and blocking reasons;
- lifecycle actions;
- operation results and next-action guidance.

Technical evidence should use progressive disclosure where appropriate. The
projection must not fabricate diagnostics, remediation recommendations,
eligibility, lifecycle state, operation results, or next actions.

## 7. Lifecycle and package boundary

The projection may expose existing inventory, package-intake/preflight,
eligibility, action, result, blocked, failed, indeterminate,
recovery-required, or reconciliation-required evidence. It must preserve
preflight-before-mutation where supported, dependency/conflict safety,
self-protection, CSRF, authorization, state integrity, sanitized failures, and
recovery guidance. Planned transition, execution result, committed state, and
recovery-required state remain distinct.

Module package operations remain distinct from Site Settings → System Webcore
Update semantics. Site Settings may project package intake, result, and
next-action behavior but must not create another package operator, classifier,
planner, or lifecycle engine.

This contract creates no new Module lifecycle state, including no `retired`
state for architecture or planning disposition.

## 8. System Health relationship

When meaningful authoritative Module-originated diagnosable conditions exist,
the Modules projection must contribute them through the existing Webcore
System Health provider and aggregation path:

> Module authoritative evidence/diagnostics → existing Webcore System Health provider/aggregation path → sanitized System Health projection

System Health remains the Webcore system-wide reporting and aggregation
authority defined by the parent contract. This contract creates no Module-owned
health engine, second aggregator, report store, competing diagnostic authority,
or new severity model. Modules inventory and Module Detail may contextually
consume the same authoritative issue evidence.

## 9. Permission and access boundary

The following concerns remain independent:

- `admin.access` is baseline Admin access;
- ordinary Site Settings write authority governs editable Site Settings values;
- `modules.manage` gates Module inventory and Module lifecycle/operator
  capability;
- `system.webcore.manage` gates Webcore System lifecycle capability and is not
  a substitute for `modules.manage`.

A user with `admin.access` and `modules.manage` must be able to view and manage
the Modules projection without unrelated Site Settings write permission.
Conversely, `modules.manage` must not implicitly grant ordinary settings
mutation authority, and Site Settings access must not implicitly grant Module
lifecycle authority.

Existing Module authority remains the enforcement point for Module-specific
authorization and permission metadata. Metadata must not be converted into
automatic grants. Later implementation must resolve read-versus-action route
composition while preserving this boundary and without inventing a second
permission model.

## 10. Implementation-time dispositions

Implementation, if separately authorized, must resolve only the mechanics
needed to realize this contract:

- exact `/admin/settings#modules` route and tab composition;
- independent read-versus-action enforcement for `modules.manage`;
- reuse or bounded extraction of existing Module/System Manager services
  without restoring a second product-facing owner;
- Module Detail routing and transport subordinate to Site Settings → Modules;
- existing package-intake ownership;
- operation-result transport and sanitized presentation;
- search/filter control placement and shared responsive reuse;
- isolation or removal of obsolete System Manager-specific coupling.

These dispositions do not authorize redesign of Module authority, package
semantics, lifecycle semantics, System Health, persistence, or routing outside
the canonical Site Settings projection.

## 11. Acceptance direction

Objective acceptance must confirm that:

- `/admin/settings#modules` is the canonical active Webcore Module projection;
- no competing System Manager or standalone Module Manager product ownership
  is restored;
- existing Module lifecycle and package authorities remain singular;
- inventory columns are exactly Module, Version, Issue, and Status, with no
  Actions, Discovery, or Notes column;
- scalable client-side search/filtering remains available;
- lifecycle Status and diagnostic Issue remain separate;
- issue categories are concise, severity is evidence-derived, and raw internals
  are sanitized;
- whole-row interaction and responsive behavior reuse existing shared
  primitives;
- meaningful Module issues use the existing Webcore System Health
  provider/aggregation path;
- permission boundaries remain distinct and independently enforced;
- package, CSRF, dependency/conflict, integrity, eligibility, result, and
  recovery boundaries remain intact;
- no generic Site Settings save transaction stages Module lifecycle state;
- no Bundled Module refinement or inventory-disposition scope is introduced;
- no new lifecycle engine, registry, package engine, permission framework,
  health infrastructure, persistence, marketplace, or destructive retirement
  behavior is added.

Human/product acceptance is required for operational comprehension, inventory
usability, issue/status clarity, Module Detail usability, row interaction,
responsive/mobile behavior, accessibility, and result/recovery guidance.

## 12. Explicit exclusions

This contract does not authorize:

- source, runtime, or test implementation by itself;
- Bundled Module product refinement, retention/retirement reconciliation,
  classification UI, feature audits, or per-Bundled-Module UX;
- package deletion, destructive retirement, marketplace, online discovery or
  download, remote catalogs, or automatic dependency installation/mutation;
- new dependency-solving semantics, lifecycle states, schema, migrations,
  provisioning, or persistence;
- a new permission framework, automatic grants, health infrastructure, severity
  taxonomy, or report store;
- Runtime Handoff, detach, adoption, registry, `runtime_id`, or handoff state;
- Installer changes, production reconciliation, Shared File Intake adoption,
  merge/rebase, branch deletion, release, tag, publication, or distribution.

Bundled Modules may be referenced only as existing Webcore capability
consumers, lifecycle subjects, dependency/conflict or package fixtures, source
evidence, or validation subjects. Their distribution and product disposition
remain outside this slice.

## 13. Contract status and next authorization gate

This document is the authoritative child contract for the WU4 Batch 2 Site
Settings → Modules projection and is subordinate to the parent WU4 contract.
Promotion does not start implementation. The Modules slice remains
**IMPLEMENTATION NOT STARTED** until a separate implementation authorization is
granted. Release, publication, merge, and branch lifecycle actions remain
separate gates.
