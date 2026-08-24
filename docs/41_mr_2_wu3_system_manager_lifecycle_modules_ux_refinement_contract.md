# MR.2 WU3 — System Manager Lifecycle & Modules UX Refinement Contract

## Status and authority

```text
MR.2 preparation: APPROVED
WU1: COMPLETE AND CLOSED
WU2: COMPLETE AND CLOSED
WU3 scope: System Manager Lifecycle & Modules UX Refinement
WU3 preparation/audit: COMPLETE
WU3 contract: PROMOTED / CONTRACT LOCKED
WU3 runtime implementation: NOT STARTED
WU3 source mutation: NOT AUTHORIZED by this promotion
```

This contract promotes the bounded WU3 preparation audit into the
authoritative repository contract. It defines the refinement target and its
ownership boundaries; it does not authorize runtime implementation, package
deletion, schema changes, installer changes, production reconciliation,
release work, or adoption of a Deferred Item.

The accepted WU2 System Manager baseline remains a historical implementation
and acceptance record. In particular, WU2's conditional fallback and
preferred-Module-Manager model describe the baseline that was delivered and
accepted at WU2. They are not the forward-looking WU3 ownership model.

## Objective

Make System Manager the canonical product-facing operator for Webcore and
Module lifecycle administration after the Post-M3 architecture reconciliation
retired standalone Module Manager as a product-facing destination. Preserve
the useful lifecycle comprehension and operator capability already present in
the richer Module Manager surface while presenting it within System Manager's
existing Admin Page Frame and authorization boundaries.

WU3 is a refinement and re-homing boundary. It must not create a second
lifecycle engine or weaken the singular Core Module lifecycle authority.

## Authoritative architecture

The Post-M3 Webcore & Extension Architecture Reconciliation contract is the
current architecture authority. It establishes that:

- System Manager is the retained product-facing parent surface.
- Module lifecycle is a Webcore/System Manager responsibility.
- Module Manager is retired as a standalone product-facing manager.
- Settings Platform remains reusable internal machinery and is not recreated
  as a Settings Manager ownership boundary.
- Module ownership remains singular for Module state, lifecycle, security,
  persistence, schema, migrations, and public contracts.

The existing Core Module lifecycle machinery remains authoritative, including
discovery, repository state, dependency and conflict analysis, permission
metadata, lifecycle services, package intake and transition planning, module
state ledgers, migration/provisioning reconciliation, package application,
integrity checks, and lifecycle result semantics. WU3 may extract or re-home
orchestration currently trapped behind standalone Module Manager private
implementation where required for System Manager consumption. Such work must
reuse the existing authorities and must not introduce a second planner,
lifecycle engine, state ledger, migration authority, schema authority, or
generic provider framework.

## Delivered baseline and refinement delta

WU2 already delivered the following Webcore-owned System Manager areas:

- Webcore lifecycle and engine-derived Update/Upgrade/Repair presentation;
- controlled Localization fields;
- Webcore Basic Branding and Admin identity;
- authorized read-only System Health presentation; and
- a conditional minimum Modules fallback.

WU3 does not reopen or duplicate those capabilities. Its refinement delta is:

1. make the Modules area a canonical System Manager area rather than a
   fallback shown only when a standalone Module Manager is unavailable;
2. preserve and safely present materially useful richer Module lifecycle
   capability from the existing implementation;
3. align inventory, diagnostics, dependency/conflict information,
   action-eligibility, package lifecycle, operation results, and next-action
   guidance with the singular Core authorities;
4. reconcile System Manager navigation, permissions, routes, and result
   language with the retired standalone-manager disposition; and
5. audit and close correctness gaps exposed by the re-homing boundary before
   any implementation acceptance is claimed.

## Modules area contract

The canonical System Manager Modules area may present, subject to the existing
authorization and evidence boundaries:

- discovered and installed Module inventory;
- valid lifecycle state and version evidence;
- discovery, compatibility, dependency, conflict, and permission diagnostics;
- action eligibility and concise human-readable blocking reasons;
- install, enable, disable, uninstall, and package lifecycle actions where
  the existing Core planners and lifecycle services make them eligible;
- package preflight and result states;
- operation classification, completion, failure, blocked, indeterminate, and
  recovery-required presentation; and
- the next valid operator action after each result.

The surface must not silently regress to the minimal WU2 fallback inventory or
replace planner-owned eligibility with unconditional buttons. Self-protection,
dependency safety, authorization, CSRF, package validation, state integrity,
and sanitized error behavior remain mandatory.

Standalone Module Manager routes, navigation, views, and private orchestration
are not a second product-facing destination for WU3. If existing implementation
pieces are retained internally during re-homing, that is an implementation
detail and does not restore standalone Module Manager ownership or preferred
operator status. Destructive package/runtime retirement is outside this
contract.

## Lifecycle and package semantics

Module lifecycle classifications remain engine-derived. WU3 must not add an
operator-selected classification picker or invent new transition semantics.
Webcore package lifecycle classifications likewise remain derived by the
existing Webcore lifecycle planner and are presented through the existing
System Manager Update umbrella.

WU3 must preserve the established distinction between lifecycle planning and
action eligibility; package intake, isolated staging, and validation; package
application and migration/provisioning execution; committed state and
operation state; retry/reconciliation eligibility; and safe result and
next-action presentation.

System Manager remains a consumer and coordinator of these authorities, not a
package, migration, recovery, schema, or release-metadata engine.

## Concrete preparation finding

The current `app/Core/SystemManagerModulePackageFallback.php` contains an
undefined `$classification` reference in `preflight()`. This is a concrete
WU3 correctness finding because package preflight and completion guidance are
part of the re-homed Modules operator boundary. It must be addressed and
covered by focused validation during separately authorized WU3 implementation.

This promotion records the finding only. It does not modify runtime source or
authorize a fix in the current task.

## Presentation and integration boundaries

WU3 adopts the closed WU1 Admin Page Frame inside the existing `admin-main`
boundary. It may refine System Manager area navigation, hierarchy, responsive
behavior, accessibility, status/result presentation, and action treatment,
while preserving consumer-owned content and existing Admin Shell ownership.

The System Manager permission boundary remains `system.webcore.manage`,
combined with the existing Admin access requirement. Module-specific
authorization remains enforced by the existing Core/module lifecycle
authorities; System Manager must not broaden it by presentation choice.

Settings Platform, Branding, System Health, Content, Media, Navigation, Theme,
Users & Access, Forms, Dashboard, Taxonomy, Redirects, Installer behavior,
and production Webcore reconciliation remain outside WU3 except where a
bounded read-only integration is required to preserve existing System Manager
comprehension.

## Objective acceptance criteria

WU3 may be accepted only when separately authorized implementation and
validation demonstrate that:

- System Manager is the canonical product-facing Module lifecycle surface;
- no forward-looking route, navigation, or contract language restores
  standalone Module Manager as the preferred operator;
- the singular Core Module lifecycle authorities remain unchanged in meaning
  and ownership;
- the richer materially useful inventory, diagnostics, dependency/conflict,
  eligibility, package lifecycle, result, and next-action capability is
  preserved or safely improved;
- module and Webcore classifications remain planner-derived;
- authorization, CSRF, self-protection, dependency safety, package integrity,
  migration/provisioning, and sanitized-error boundaries remain intact;
- the undefined `$classification` finding is resolved with focused regression
  evidence;
- WU1 Page Frame and existing Admin Shell boundaries are preserved; and
- no schema, installer, release, production-reconciliation, destructive
  retirement, second lifecycle engine, or generic framework is introduced.

## Explicit exclusions

- WU3 runtime/source implementation in this promotion;
- fixing the recorded `$classification` defect in this promotion;
- destructive Module Manager package or runtime deletion;
- a second Module lifecycle engine, planner, ledger, migration authority, or
  schema authority;
- schema/database changes or Installer changes;
- Settings Manager recreation or Settings Platform ownership change;
- new Module capabilities unrelated to canonical operator re-homing;
- Deferred Item adoption;
- production Webcore reconciliation;
- release, tag, publication, or distribution work; and
- unrelated documentation cleanup.

## Source and contract references

- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`
- `routes/system_manager.php`
- `app/Core/SystemManagerLifecycleService.php`
- `app/Core/SystemManagerModuleFallback.php`
- `app/Core/SystemManagerModulePackageFallback.php`
- `modules/module-manager/Services/ModuleManagerAdmin.php` as historical
  implementation evidence for capability preservation, not as a current
  product-facing ownership authority
