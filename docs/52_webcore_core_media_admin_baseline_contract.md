# Webcore Product Completeness & Stabilization — WU2 Core Media Admin Baseline Contract

## Status and authority

Workstream: Post-M3 — Webcore Product Completeness & Stabilization
Work Unit: WU2 — Core Media Admin Baseline
Contract status: PROMOTED / CONTRACT LOCKED
WU2 implementation: COMPLETE / ACCEPTED
WU3-WU6 implementation: NOT STARTED
Technical implementation authorization: NONE
Release / tag / publication authorization: NONE

## WU2 closure record

WU2 implementation, focused validation, disposable-runtime verification, and
human acceptance are complete for the locked baseline boundary. The accepted
feature state is committed on `feature/webcore-media-wu2-baseline` and awaits
fast-forward integration into `main`; WU3-WU6 remain not started.

This contract establishes the exact execution boundary for WU2 under
`docs/49_webcore_product_completeness_stabilization_contract.md` and the locked
WU1 scope in
`docs/51_webcore_product_completeness_wu1_scope_reconciliation_contract.md`.

It is a technical-scope contract. It does not itself authorize runtime/source,
schema, package, lifecycle, release, or publication changes.

## Purpose

Materialize an always-available Webcore-owned Core Media Admin projection over
the accepted Core Media capability so baseline COPOT Media operation does not
depend on the optional Media Manager Bundled Module.

WU2 is a baseline extraction/materialization unit. It is not a Media Manager
refinement and must not move the complete existing `modules/media` product
surface into Webcore.

## Current source finding

Current repository source confirms that the existing `media` package still
contains a rich Admin implementation and declares Media permissions for view,
upload, use, edit, and delete.

The current Module implementation combines baseline-capable behavior with
extension-only behavior in the same package and routing surface. Relevant
existing baseline evidence includes:

- Media inventory/workspace retrieval;
- upload;
- basic Media identity and title handling;
- controlled Media delivery;
- usage/reference awareness;
- reference-safe deletion;
- permission enforcement;
- CSRF enforcement;
- upload validation; and
- sanitized Admin-facing failure behavior.

The same implementation also contains richer processing and workspace behavior,
including variants, processing presets, crop/preparation flows, filtering,
pagination, and Content-specific prepared-image behavior. Their existence does
not make them Webcore baseline requirements.

## Ownership invariant

Accepted Core Media ownership remains singular.

WU2 must not create a second Media state, persistence, storage, lifecycle,
delivery, validation, or usage-reference authority merely to expose a Webcore
Admin surface.

Where current implementation remains physically located under `modules/media`,
WU2 must separate or reuse baseline behavior according to accepted ownership
rather than treating filesystem location as authority.

The implementation must leave a coherent extension boundary for retained Media
Manager capability.

## Required baseline capability

The Webcore-owned Core Media Admin baseline must provide the minimum operator
capability required for zero-optional product operation.

Required capability:

1. **Media inventory**
   - list available Media records;
   - expose sufficient stable identity and basic metadata for operator
     recognition;
   - preserve existing permission boundaries.

2. **Upload**
   - accept supported baseline Media upload;
   - use authoritative validation and storage behavior;
   - expose safe, sanitized validation/failure responses;
   - enforce CSRF and applicable upload permission.

3. **Basic identity and metadata presentation**
   - expose stable Media identity;
   - expose original filename, Media type/MIME information, title, and other
     already-authoritative basic facts where available without introducing a
     new metadata model.

4. **Bounded basic title editing**
   - title update may be included because current source already supports it
     through Media lifecycle behavior;
   - WU2 must not generalize this into an arbitrary metadata editor;
   - no new metadata persistence model is authorized.

5. **Simple selection/reference behavior**
   - provide only the selection/reference capability required by accepted
     baseline Webcore consumers;
   - baseline consumer integration must not depend on Media Manager being
     installed or enabled.

6. **Usage/reference awareness**
   - surface sufficient usage evidence for safe lifecycle decisions;
   - do not invent a generic cross-domain dependency browser merely for WU2.

7. **Reference-safe deletion**
   - refuse unsafe deletion when authoritative usage evidence reports the Media
     record in use;
   - preserve controlled file/storage cleanup behavior;
   - use sanitized operator-facing failure behavior.

8. **Security boundary**
   - retain `admin.access` and Media permission semantics or their accepted
     Webcore-equivalent mapping;
   - enforce CSRF for state-changing Admin operations;
   - avoid leaking internal exception details.

## Baseline presentation boundary

WU2 requires a usable Admin projection, not parity with the existing rich Media
workspace.

The baseline may use the accepted shared Admin presentation foundations.

The following presentation conveniences are not mandatory merely because the
current Media Module already has them:

- search;
- kind/capability filters;
- pagination;
- card-heavy workspace behavior;
- advanced preview behavior;
- variant evidence display; and
- richer Media Manager organization patterns.

A convenience may be retained or reused only if implementation/runtime evidence
shows it is materially required for practical baseline operation and doing so
does not collapse the Media Manager extension boundary.

## Extension-only boundary

The following remain outside WU2 Webcore baseline:

- derivative/variant management;
- processing presets;
- crop;
- resize;
- rotate;
- format conversion controls;
- advanced image editing;
- advanced processing workflows;
- pending/prepared variant workflows;
- galleries;
- folders;
- bulk workflows;
- advanced organization;
- advanced library/workspace conveniences; and
- Content-specific processing semantics that belong to richer Media Manager or
  consumer-specific extension behavior.

Existing implementation of any excluded feature may remain as Media Manager
extension evidence. WU2 must not delete valid retained extension capability
merely to establish the Core baseline.

## Consumer and picker boundary

Simple baseline selection/reference is required as a capability, but WU2 must
not automatically copy the current Content-specific picker implementation.

The implementation must distinguish:

- generic baseline Media selection/reference required by Webcore consumers; from
- Content-specific crop, variant preparation, responsive derivative, or richer
  picker behavior.

Consumer-specific advanced preparation remains outside Core Media baseline
unless a separately accepted Webcore consumer contract requires a bounded piece
of it.

## Persistence and schema

WU1 found no justification for a new schema migration, and WU2 does not alter
that finding by default.

WU2 must reuse the accepted Core Media persistence/storage authority and must
not introduce:

- duplicate Media tables;
- competing storage roots;
- parallel usage/reference state;
- a new generic metadata store; or
- schema migration without concrete implementation evidence and a separately
  accepted expansion decision.

If implementation proves a schema change unavoidable, stop and return the
finding for scope review rather than silently expanding WU2.

## Permissions

Current Media permission semantics are implementation evidence:

- `media.view`;
- `media.upload`;
- `media.use`;
- `media.edit`;
- `media.delete`.

WU2 must preserve least-privilege behavior and avoid broadening access while
moving/materializing the baseline projection.

Any permission-registration relocation required by zero-optional operation must
preserve semantics and must not make the optional Media Manager the authority
for baseline permissions.

## Coexistence with Media Manager

Media Manager remains a retained optional Bundled Module that **EXTENDS** Core
Media.

When Media Manager is absent or disabled:

- Core Media Admin baseline must remain operable.

When Media Manager is present and enabled:

- it may extend the Media experience;
- it must not replace or fork Core Media authority;
- it must not create a competing baseline state;
- it must not be required merely for baseline list/upload/select/delete
  operation.

Exact coexistence presentation mechanics may be selected during implementation
provided the authority and dependency boundaries above remain intact.

## Validation requirements

Implementation validation must begin closest to the actual source delta and
expand according to impact.

At minimum, WU2 acceptance evidence must cover:

- zero-Media-Manager access to Core Media Admin;
- inventory/list behavior;
- upload success and validation failure;
- applicable permission denial;
- CSRF rejection for state-changing actions;
- bounded title edit if implemented;
- simple baseline selection/reference behavior;
- usage-aware deletion refusal;
- successful safe deletion;
- sanitized not-found/failure behavior;
- controlled public/original Media delivery compatibility where directly
  impacted;
- coexistence with Media Manager enabled, if implementation touches shared
  seams;
- no unintended dependency on advanced processing/variant services for baseline
  operation; and
- final diff/unintended-change review.

Broad historical regressions are not required merely for ceremony. Expand only
where actual dependency impact or a regression signal justifies it.

## Human/product acceptance

Human/product review is required only if WU2 introduces a materially new Media
Admin presentation whose usability cannot be accepted objectively.

Human review should judge baseline usability and comprehension, not demand Media
Manager feature parity.

Objective route, security, lifecycle, dependency, responsive, and deterministic
accessibility criteria remain AI/technical acceptance work.

## Explicit exclusions

WU2 does not authorize:

- Media Manager refinement;
- wholesale movement of `modules/media` into Webcore;
- advanced processing or variant management;
- generic asset-management framework creation;
- generic provider arbitration;
- new schema/settings migration without concrete evidence and scope review;
- Bundled Module retirement;
- unrelated Content refinement;
- Navigation implementation;
- Site Settings implementation;
- broad Admin Shell redesign;
- Dashboard redesign;
- destructive cleanup;
- production reconciliation;
- Deferred Item adoption;
- release;
- tag;
- publication; or
- external distribution.

## Completion condition

WU2 may be accepted only when the resulting authoritative implementation proves
that baseline Media administration is usable with Media Manager absent, while
retained Media Manager capability remains a true extension over singular Core
Media authority.

Completion of this contract does not imply WU2 implementation completion. WU2
remains `NOT STARTED` until a separately authorized technical execution slice is
performed and accepted.

## Next relationship

WU3 — Core Primary Navigation Admin Baseline remains the next work unit after
WU2 implementation and acceptance.

WU3 is not authorized by this contract.

## References

Primary authority:

- `docs/49_webcore_product_completeness_stabilization_contract.md`;
- `docs/51_webcore_product_completeness_wu1_scope_reconciliation_contract.md`.

Supporting current-source evidence:

- `modules/media/module.json`;
- `modules/media/routes.php`;
- `modules/media/Services/MediaAdmin.php`;
- existing Media repository, upload, lifecycle, usage/reference, storage,
  delivery, processing, and related test source as progressively required during
  implementation.

Historical architecture/provenance remains governed by its own accepted status
and is not rewritten by this WU2 contract.
