# WU4 Batch 2 — Site Settings Modules Operational Projection

**Pre-contract status:** PROMOTED / HISTORICAL PROVENANCE
**Promotion target:** `docs/56_webcore_site_settings_modules_operational_projection_contract.md`
**Promotion status:** COMPLETED
**Implementation status:** NOT STARTED
**Implementation authorization by this pre-contract:** NONE
**Release, tag, and publication authorization:** NONE

## 1. Purpose

This pre-contract defines the bounded product and authority target for the
Webcore-owned Modules operational projection under Site Settings. It
materializes an Admin operator surface for existing Module lifecycle and
package-management capability; it does not create or redesign that capability.

The governing invariant is:

> Re-home the Webcore Module operator surface. Preserve Module lifecycle authority.

The projection is a consumer of existing authoritative Module services and
state. Site Settings does not become a Module lifecycle engine, package engine,
dependency resolver, migration authority, provisioning authority, Module
registry, or Module-owned persistence authority.

## 2. Authority and lineage

The parent authority is
`docs/54_webcore_site_settings_appearance_consolidation_contract.md`.

Relevant accepted lineage and bounded source evidence are:

- `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`;
- `docs/04_module_system.md`;
- existing Core/Webcore Module lifecycle, package, discovery, repository,
  planner, diagnostic, and result services;
- existing Module Manager and historical System Manager Module presentation
  only as evidence of accepted capability and reusable interaction patterns;
- `precontracts/wu4_batch2_site_settings_system_operational_projection_precontract.md`
  only as a structural sibling precedent.

The existing Module authorities remain singular. The historical System Manager
surface is implementation and capability lineage, not a reason to restore its
product ownership or routing. The standalone Module Manager likewise does not
regain product-facing ownership through this pre-contract.

The existing facing-UI references `/admin/modules` and
`/admin/settings/system-manager?section=modules` do not establish current
routing, ownership, permission, lifecycle, package, persistence, or
architecture. Useful presentation patterns may be reconciled during authorized
implementation without cloning either surface wholesale.

## 3. Canonical product surface

The canonical product-facing destination is:

`/admin/settings#modules`

Site Settings is the Admin projection host. Internal DOM or panel identifiers,
such as `site-settings-modules`, may remain where useful for ARIA, JavaScript,
or implementation structure; they are not the canonical browser URL format.

The superseded System Manager product route and standalone Module Manager
product surface must not be restored as competing ownership. Module Detail,
lifecycle actions, package intake, and operation-result transport remain
subordinate to the canonical Site Settings → Modules composition and must not
route through a System-owned product namespace. Exact internal transport and
path mechanics remain an implementation-time disposition; this pre-contract
does not invent an endpoint or authorize a second canonical Module surface.

## 4. Inventory information hierarchy

The desktop inventory has exactly four primary information columns:

| Module | Version | Issue | Status |
| --- | --- | --- | --- |

There is no `Actions` column, `Discovery` column, or `Notes` column. Lifecycle
mutation actions belong in Module Detail unless source reconciliation proves a
required existing shared-product exception.

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
category, for example `Dependency error`, `Metadata mismatch`, `Discovery
failure`, or `Package integrity error`. Terminology must remain truthful to the
underlying evidence and must not mechanically suffix every label with “issue”.

Issue presentation reflects the authoritative severity level. No new severity
model is introduced. For multiple issues, the inventory may show the most
severe or operationally significant issue and a bounded indication such as
`+N more` only when ordering/severity can be derived truthfully from existing
evidence.

The inventory must not expose raw diagnostic codes, filesystem paths,
exceptions, dependency dumps, checksums, stack traces, or other implementation
details unnecessary for operator action.

### Status

Status is lifecycle state only. Existing lifecycle semantics such as `Enabled`,
`Disabled`, and `Not installed` remain authoritative. Health and diagnostic
state must not be merged into lifecycle Status.

## 5. Row interaction and responsive behavior

Use existing shared Admin row interaction primitives where applicable,
including the existing shared hover behavior. The whole inventory row is the
primary open target for Module Detail. Do not add redundant `Open`, `Manage`,
or equivalent row buttons solely to open detail. This projection does not
introduce a new interaction framework.

The desktop table uses the available content width rather than shrinking to
content. `Module` is the flexible primary column; `Version` and `Status` are
compact; `Issue` is bounded but wide enough for concise diagnostic categories.
Exact percentages are implementation-time layout details and are not contract
constants. The four columns must not be divided equally merely for convenience.

On mobile, do not force the desktop four-column table into a compressed
horizontal presentation. Use the accepted shared responsive table/row
primitive where available, with stacked presentation preserving Module
identity, version, issue signal/type, lifecycle status, and whole-row tap-to-open
behavior. Scalable client-side search and filtering are a preserved accepted
capability for usable inventory navigation. Exact control placement, responsive
presentation, and implementation mechanics remain implementation-time details;
the capability remains bounded to authoritative inventory data.

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

Module discovery, repository/runtime state, dependency and conflict analysis,
permission metadata, lifecycle transition planning, install/enable/disable/
uninstall execution, package validation, preflight, result, recovery, and
reconciliation semantics remain owned by their existing authorities.

The projection may expose existing inventory, package-intake/preflight,
eligibility, action, result, blocked, failed, indeterminate,
recovery-required, or reconciliation-required evidence. It must preserve
preflight-before-mutation where supported, dependency/conflict safety,
self-protection, CSRF, authorization, state integrity, sanitized failures, and
recovery guidance. Planned transition, execution result, committed state, and
recovery-required state remain distinct.

Package operations remain distinct from Site Settings → System Webcore Update
semantics. Site Settings may project package intake, result, and next-action
behavior but must not create another package operator, classifier, planner, or
lifecycle engine. Shared File Intake adoption is not authorized by this
pre-contract.

This pre-contract does not create a new lifecycle state, including a `retired`
state for architecture or planning disposition.

## 8. System Health relationship

When meaningful authoritative Module-originated diagnosable conditions exist,
the Modules projection must contribute them through the existing Webcore
System Health provider and aggregation path:

> Module authoritative evidence/diagnostics → existing Webcore System Health
> provider and aggregation path → sanitized System Health projection

System Health remains the Webcore system-wide reporting and aggregation
authority defined by the parent WU4 contract. This pre-contract creates no
Module-owned health engine, second aggregator, report store, competing
diagnostic authority, or new severity taxonomy. Modules inventory and Module
Detail may consume the same authoritative issue evidence for contextual
presentation.

## 9. Permission and access boundary

The projection must preserve these independent concerns:

- `admin.access` is baseline Admin access;
- ordinary Site Settings write authority, currently represented by the existing
  Site Settings write permission, governs editable Site Settings values;
- `modules.manage` gates Module inventory and Module lifecycle/operator
  capability;
- `system.webcore.manage` gates Webcore System lifecycle capability and is not
  a substitute for `modules.manage`.

A user may hold `admin.access` and `modules.manage` without ordinary Site
Settings write permission. Such a user must be able to view and manage the
Modules projection without unrelated Site Settings write authority.
Conversely, `modules.manage` must not implicitly grant ordinary settings
mutation authority, and Site Settings access must not implicitly grant Module
lifecycle authority.

The current source route composition groups Site Settings rendering with the
ordinary Site Settings write requirement. This is recorded as an
implementation-time route/read-versus-action disposition: authorized source
work must compose the Modules projection so its read and Module-action checks
preserve the independent `admin.access` + `modules.manage` boundary without
inventing a second permission model. Existing Module authority remains the
enforcement point for Module-specific authorization and permission metadata;
metadata must not be converted into automatic grants.

## 10. Implementation-time dispositions

Before any separately authorized implementation, resolve only the mechanics
needed to realize this pre-contract:

- exact `/admin/settings#modules` route and tab composition;
- read-versus-action permission enforcement, including the independent
  `modules.manage` boundary identified above;
- reuse or bounded extraction of existing System Manager/Module Manager
  services without restoring a second product-facing owner;
- Module Detail routing and transport;
- existing package-intake route ownership;
- operation-result transport and sanitized presentation;
- bounded search/filter reuse;
- isolation or removal of obsolete System Manager-specific coupling.

These are implementation dispositions, not permission to redesign Module
authority, package semantics, lifecycle semantics, System Health, persistence,
or routing outside the canonical Site Settings projection.

## 11. Acceptance direction

Objective technical acceptance must confirm that:

- `/admin/settings#modules` is the canonical active Webcore Module projection;
- the two historical/facing-UI routes are not restored as competing product
  ownership;
- existing Module lifecycle and package authorities remain singular;
- inventory columns are exactly Module, Version, Issue, and Status, with no
  Actions, Discovery, or Notes column;
- lifecycle Status and diagnostic Issue remain separate;
- issue labels are concise, severity is evidence-derived, and raw internals are
  sanitized;
- row interaction and responsive behavior reuse existing shared primitives;
- Module issues use the existing Webcore System Health provider/aggregation
  path rather than a Module health subsystem;
- `admin.access`, ordinary Site Settings write authority, `modules.manage`, and
  `system.webcore.manage` remain distinct;
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

This pre-contract does not authorize:

- source or runtime implementation;
- promotion into a child contract;
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

## 13. Historical promotion provenance

This document is retained as the historical promotion lineage for the
authoritative child contract at
`docs/56_webcore_site_settings_modules_operational_projection_contract.md`.
Its substantive decisions were promoted without creating a second competing
normative contract. The pre-contract did not authorize implementation, and the
Modules slice remains implementation not started.

Only a separate implementation authorization may begin source/runtime/test,
schema, database, or lifecycle work. Release, publication, and branch actions
remain separately controlled.
