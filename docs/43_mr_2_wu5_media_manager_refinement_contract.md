# MR.2 WU5 — Media Manager Refinement Contract

## Purpose and status

This contract promotes the accepted MR.2 WU5 preparation and source audit into
the authoritative repository contract for Media Manager refinement.

```text
MR.2 WU5 preparation/audit: COMPLETE
MR.2 WU5 contract: PROMOTED / CONTRACT LOCKED
Runtime/source implementation: NOT AUTHORIZED BY THIS PROMOTION
```

WU5 is a bounded presentation and interaction refinement of the retained
Media Manager workspace and its existing Content picker. It does not change
Media ownership, storage, processing, consumer, permission, usage, or
deletion semantics.

## Objective

Refine the retained Media Manager workspace and existing Content picker around
authoritative Media evidence so administrators can find, inspect, upload,
process, select, and safely manage Media more efficiently across desktop and
mobile without changing ownership, storage, processing, consumer, permission,
or deletion semantics.

This contract authorizes no implementation by itself. Runtime, source, test,
schema, database, deployment, and runtime-state changes require a separate
implementation authorization.

## Authoritative architecture

Webcore owns minimum Media viability, including the baseline Media identity,
metadata, controlled delivery, simple selection/reference awareness, and safe
deletion boundary.

Media Manager remains a retained Bundled Module that **EXTENDS** Webcore
Media. Media Manager owns the retained library and Admin workspace, upload and
inspection orchestration, preparation, processing, variants, usage tracking
presentation, and the extension-level deletion workflow while preserving the
Core/Webcore lifecycle authority.

Content owns its consumer field and save lifecycle and stores stable Media
identity. The existing Content picker remains consumer-scoped. Content does
not acquire Media storage, upload, processing, variant, delivery, or deletion
authority.

The `content.featured` processing profile remains an explicit consumer
profile. Generic Media processing must remain free of Content-specific 16:9,
responsive-width, or other consumer assumptions. Original assets remain
preserved, and generated variants remain separately managed Media descriptors.

The existing permission boundaries remain unchanged:

- `media.view`
- `media.upload`
- `media.use`
- `media.edit`
- `media.delete`

## In-scope refinement

WU5 may refine only the following existing surfaces and evidence:

- Media library/card hierarchy and scanability;
- search and filter affordance, active-filter comprehension, and result context;
- empty and no-match recovery;
- upload/intake guidance and presentation;
- existing preview/detail presentation;
- existing metadata, usage, variant, processing, and action-eligibility
  evidence where authoritative source evidence already exists;
- deletion-denial comprehension and sanitized recovery presentation;
- responsive and mobile presentation;
- existing Content picker/reference presentation where directly relevant;
- bounded CSS, JavaScript, and view refinement; and
- directly affected focused presentation and regression tests.

The implementation must reuse the existing Admin Page Frame, Media routes,
Core/Webcore Media boundary, Media processing services, consumer contracts,
and established Admin presentation patterns.

## Preserved behavior and safety boundaries

The following remain unchanged and mandatory:

- Webcore minimum Media ownership and Media Manager extension ownership;
- stable Media identity and consumer references;
- Media upload inspection, safe storage, controlled delivery, and original-file
  preservation;
- existing processing, preparation, variant, and consumer-profile semantics;
- Content picker behavior and Content save/reference lifecycle;
- usage tracking and reference-safe deletion;
- permission and Admin access boundaries;
- CSRF protection and sanitized failure handling;
- existing pagination, search, kind/capability filtering, and route semantics;
- no disclosure of physical storage paths or internal storage keys; and
- responsive and accessible interaction behavior.

WU5 must expose only authoritative metadata or evidence. It must not invent
history entries, changelog text, health state, usage state, processing state,
or action eligibility.

## Explicit exclusions

WU5 does not authorize:

- schema or persistence changes;
- a new Media ownership authority;
- new lifecycle, processing, usage, or deletion semantics;
- folders or organizational grouping;
- galleries;
- bulk actions or bulk workflow;
- drag-and-drop upload;
- expanded video, audio, archive, or MIME capability;
- arbitrary consumer-profile infrastructure;
- advanced image editing or optimization;
- CDN, cloud, or external storage;
- specialized multimedia metadata;
- new history, changelog, or health systems;
- a new processing engine;
- Content ownership or lifecycle changes;
- System Manager changes;
- Installer work;
- Deferred Item adoption;
- production reconciliation;
- release, tag, publication, or distribution work; or
- unrelated cleanup.

## Deferred and expansion disposition

The following dispositions are authoritative for WU5:

| Capability | WU5 disposition |
| --- | --- |
| Folders / organizational grouping | Deferred |
| Galleries | Deferred / excluded |
| Bulk actions / bulk workflow | Deferred / excluded |
| Drag-and-drop upload | Deferred / excluded |
| Expanded MIME, video, or archive support | Deferred / excluded |
| Arbitrary consumer profiles | Not authorized |
| Advanced editing / optimization | Deferred / excluded |
| CDN / cloud / external storage | Architecture expansion |
| Specialized multimedia metadata | Architecture/product expansion |

No item in this table is adopted by WU5.

## Objective acceptance criteria

WU5 is acceptable only when evidence demonstrates that:

1. Media ownership and the Webcore/Core boundary remain unchanged.
2. Search, kind and capability filters, pagination, and empty/no-match
   behavior remain correct.
3. Library cards and preview/detail surfaces expose only authoritative
   metadata and evidence.
4. Usage visibility and deletion blocking remain safe and understandable.
5. Upload success and failure states remain clear, sanitized, and recoverable.
6. Existing processing and variant evidence is presented without fabricating
   history, changelog, or health state.
7. Content picker behavior and consumer-profile processing remain unchanged.
8. Permissions, CSRF, controlled delivery, and responsive/mobile behavior
   remain intact.
9. No Deferred capability, new ownership authority, new processing engine, or
   unrelated architecture is introduced.

## Focused validation surface

Validation should be limited to directly affected evidence:

- Media workspace/list/grid rendering;
- filter, result-context, empty, and no-match states;
- preview/detail metadata and accessibility behavior;
- upload success and sanitized failure presentation;
- usage-aware deletion denial;
- existing processing and variant evidence consumed by the UI;
- Content picker compatibility;
- PHP lint for changed PHP files;
- affected JavaScript, CSS, and static checks;
- `git diff --check`; and
- targeted desktop and responsive/mobile review.

Broad historical Media regression suites are not required unless a concrete
impact or regression signal justifies them.

## Scope gate

Stop before implementation expansion if satisfying an apparent refinement
requires a new ownership or processing model, adoption of a Deferred
capability, a Content consumer-processing change, schema or persistence work,
or an unresolved product/architecture decision. Such work requires separate
planning and authorization.
