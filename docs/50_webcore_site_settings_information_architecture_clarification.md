# Webcore Site Settings Information Architecture Clarification

## Status and authority

```text
Workstream: Post-M3 — Webcore Product Completeness & Stabilization
Classification: AUTHORITATIVE PRODUCT-PROJECTION / INFORMATION-ARCHITECTURE CLARIFICATION
Status: PROMOTED / LOCKED CLARIFICATION
Parent authority: docs/49_webcore_product_completeness_stabilization_contract.md
Technical implementation authorization: NONE
Release / tag / publication authorization: NONE
```

This clarification records the accepted product-facing information-architecture
decision for the Webcore Site Settings target inside the promoted Webcore Product
Completeness & Stabilization workstream.

It supersedes only conflicting product-projection and information-architecture
wording in `docs/49_webcore_product_completeness_stabilization_contract.md` and
its planning lineage. It does not rewrite historical delivery evidence in
`docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md` or the closed
architecture history in
`docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`.

It does not transfer Settings, Site Asset, Branding, Module lifecycle, System
Health, or other underlying state/lifecycle authority.

## Locked product direction

The current Webcore **System Manager** product-facing surface is to evolve into
**Site Settings** as its successor parent administrative surface.

Site Settings is therefore not a separate sibling surface beside a retained
System Manager, and `Site` is not retained as a peer tab under System Manager.
The parent product identity changes from `System Manager` to `Site Settings`
while accepted underlying Webcore capabilities are preserved and reorganized.

The target top-level hierarchy is:

```text
Site Settings
├─ Site Identity
├─ System
├─ Security
├─ Email
├─ Modules
└─ System Health
```

`Site Identity` is the site-facing configuration area. It contains the bounded
site-level groups that were previously described as peer Site Settings product
groups, including at minimum:

```text
Site Identity
├─ General
│  ├─ Site Name
│  ├─ Site Tagline
│  ├─ Logo
│  └─ Favicon
├─ Localization
│  ├─ Locale
│  ├─ Timezone
│  ├─ Date Format
│  └─ Time Format
└─ Appearance
   ├─ accepted Webcore Branding baseline
   └─ bounded Site Color Scheme
```

Exact control layout and interaction mechanics remain subject to the relevant
WU4 implementation contract and human/product acceptance where subjective
judgment is required.

## Supersession of earlier Site Settings grouping wording

Where `docs/49_webcore_product_completeness_stabilization_contract.md` describes
`Site Identity`, `Localization`, and `Appearance` as intended peer product
groups, this clarification supersedes that hierarchy.

The authoritative interpretation is now:

- `Site Settings` is the evolved parent surface and successor product identity
  to the current System Manager projection;
- `Site Identity`, `System`, `Security`, `Email`, `Modules`, and `System Health`
  are the intended top-level areas;
- `Localization` and `Appearance` belong within `Site Identity` rather than as
  peer top-level Site Settings areas;
- the historical candidate `Site` tab is redundant under the evolved parent
  identity and is not a target top-level area;
- previously accepted System Manager capabilities are preserved unless a later
  authorized WU proves a bounded change is required.

## System Manager evolution boundary

The change from System Manager to Site Settings is a product-facing evolution,
not permission for a wholesale rewrite of Webcore system-management authority.

Existing accepted System Manager capability remains implementation evidence,
including current System, Modules, and System Health behavior. WU4 may reconcile
those capabilities into the evolved Site Settings surface while preserving
underlying services, permissions, lifecycle responsibilities, recovery
semantics, and singular authorities unless exact source evidence justifies a
bounded correction.

`Security` and `Email` remain target top-level areas from the accepted planning
lineage, but this clarification does not invent or authorize unsupported runtime
capability. Their exact delivered baseline, empty-state behavior, or later
implementation requirement must be resolved against current source and the WU4
contract before implementation.

## Authority preservation

The following remain unchanged:

- Settings definitions, typed persistence, validation, defaults, and effective
  value resolution remain under Settings Platform;
- Logo/Favicon validation, storage, activation, cleanup, and public serving
  remain under Site Asset authority;
- Webcore Branding remains upstream durable authority for accepted baseline
  Branding data and palette behavior;
- Module lifecycle remains Webcore-owned;
- System Health remains Webcore-owned;
- Site Color Scheme remains a bounded resolved projection over accepted
  Branding lineage and does not gain competing persistence;
- historical System Manager contracts remain valid evidence of what was
  previously delivered.

The evolved Site Settings surface consolidates product-facing access and
information architecture only. It must not create competing persistence,
provider arbitration, generic settings-editor behavior, or duplicated canonical
editors for the same baseline setting family.

## Work Unit consequence

This clarification is an input to WU1 scope reconciliation and WU4 contract
materialization.

WU1 must carry this hierarchy forward when locking WU2-WU6 execution
boundaries.

WU4 must treat the target as **System Manager → Site Settings evolution**, not as
creation of a separate Site Settings sibling while leaving System Manager as a
competing parent surface.

The six-WU workstream topology remains unchanged.

This clarification does not authorize WU1-WU6 technical implementation, source
mutation beyond this documentation decision, runtime mutation, schema/settings
migration, destructive cleanup, release, tag, publication, or external
distribution.
