# MR.2 WU1 — Webcore Admin View Foundation Contract

## Status and authority

```text
MR.2 preparation: APPROVED
WU1 scope: Webcore Admin View Foundation
WU1 implementation: COMPLETE
WU1 focused validation: COMPLETE
WU1 integration and lifecycle closure: COMPLETE AND CLOSED
```

This contract establishes the smallest reusable Webcore-owned Admin Page Frame
inside the existing `admin-main` boundary. It does not redesign the Admin
Shell, top bar, breadcrumbs, Dashboard, or any consumer-owned internal UI.

## Objective

Provide a shared page-level frame with a stable semantic contract for:

- title;
- optional description;
- optional action or utility bar;
- consumer-owned content;
- optional footer.

The frame owns page-level presentation only. Consumers continue to own the
meaning, markup, controls, tables, forms, tabs, dialogs, and other internal
content they place inside the Content region.

## Consumer contract

Consumers may request only bounded presentation intent:

```text
surface: panel | transparent
spacing: default | none
```

The values are allowlisted by Webcore. Arbitrary Webcore CSS classes are not a
stable consumer contract. Optional regions are omitted by passing no value;
the frame must remain structurally valid when Description, Bar, or Footer is
absent.

Consumer content is treated as opaque rendered HTML. The frame must not
rewrite, inspect, register, discover, or otherwise compose consumer-owned
internal UI.

## Ownership and boundaries

Webcore owns the frame markup, semantic region structure, bounded presentation
intent mapping, title/description accessibility association, and responsive
page-level layout.

Consumers own all Content markup and behavior. Tabs, if later extracted as a
shared capability, remain consumer-selected: the consumer owns tab existence,
count, labels, order, content, and conditional visibility.

No generic component registry, dynamic discovery mechanism, page schema, DSL,
plugin framework, or generic asset-registration subsystem is introduced.

Shared capabilities remain optional. Existing callers of
`AdminPageRenderer::render()` remain valid without adopting the frame.

## Representative adoption

WU1 proves the frame with:

1. the Users conventional/list-oriented Admin surface; and
2. a focused controlled fixture covering all regions, optional-region absence,
   both surface values, both spacing values, and arbitrary consumer content.

The Users adoption moves only its page-level title, description, and create
action into the frame. Its list/table and empty-state content remain
Users-owned.

## Explicit exclusions

- Admin Shell, top bar, breadcrumb, navigation, and mobile-drawer refinement;
- Dashboard refinement;
- System Manager or Core Module refinement beyond representative adoption;
- domain, permission, route, schema, package, Installer, ownership, or
  System Health architecture changes;
- WU2 and later MR.2 work;
- Deferred Item adoption;
- release, tag, publication, or external distribution.

## Acceptance and closure

Acceptance requires focused source/render tests for every region and semantic
intent, representative consumer coverage, accessibility associations,
responsive frame behavior, unchanged shell/navigation behavior, PHP lint,
directly impacted regressions, final diff review, documentation reconciliation,
and normal branch containment/integration verification.
