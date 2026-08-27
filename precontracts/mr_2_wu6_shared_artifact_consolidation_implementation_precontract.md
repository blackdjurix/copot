# MR.2 WU6 — Shared Artifact Consolidation & Implementation — Pre-contract

Lifecycle: DRAFT / TRIAL / NOT AUTHORITATIVE
Workplan role: forward MR.2 WU6 planning boundary
Promotion status: NOT PROMOTED
Implementation authorization: NONE
Dependency: WU5 authoritative shared-primitive contract or explicitly accepted equivalent planning boundary

## Purpose

Define the bounded implementation unit that follows WU5 after canonical shared Admin primitives and their exception model have been accepted.

This file is a planning artifact. It does not authorize implementation, does not override repository contracts, and must not be used to infer that WU5 has been completed or promoted.

## Objective

Implement the bounded canonical shared Admin primitives established by WU5 using single-source shared artifacts, tokens, classes, or equivalent reusable presentation infrastructure while preserving domain behavior and accepted ownership boundaries.

WU6 is shared-foundation implementation, not a per-module redesign program.

## Preconditions

WU6 must not begin unless WU5 has established, at minimum:
- the canonical primitive inventory;
- current canonical sources and duplicated/local variants;
- explicit semantic exceptions;
- target shared ownership for each primitive;
- affected consumer classes/surfaces;
- migration boundaries;
- acceptance criteria.

If WU6 implementation exposes an unresolved product, architecture, ownership, or domain-workflow decision not already bounded by WU5, stop and return it for planning rather than deciding it implicitly in code.

## In-scope implementation

WU6 may implement only primitives accepted through WU5, including where applicable:
- page title / eyebrow / page-header composition;
- shared surface/card treatment;
- toolbar/search/filter presentation;
- shared spacing relationships;
- form-field and helper-text presentation;
- action-row and paired-button treatment;
- modal/dialog action spacing;
- typography tokens/classes;
- spacing/grid tokens/classes;
- radius/flat-surface rules;
- responsive/accessibility shared behavior;
- bounded shared CSS/token/class/artifact ownership.

Implementation should prefer the smallest stable shared abstraction that removes proven duplication. It must not create a generic component framework merely because several pages look similar.

## Ownership and behavior boundaries

Shared artifacts own presentation semantics only.

Consumers retain ownership of:
- route and workflow semantics;
- data and lifecycle state;
- action eligibility;
- permissions/authorization;
- validation rules;
- domain-specific markup where semantic differences require it;
- specialized responsive/interaction behavior justified by the domain.

A shared implementation must support accepted exceptions without forcing local consumers to fork or override the entire primitive.

## Consumer migration rule

Only the minimum consumer changes necessary to introduce the canonical shared primitive are in scope.

WU6 must not use consolidation as an excuse to:
- redesign a consumer workspace;
- change information architecture;
- alter domain interaction flow;
- add missing product capability;
- normalize legitimate semantic differences.

## Explicit exclusions

WU6 does not authorize:
- per-Bundled-Module UX refinement;
- Dashboard composition/widget redesign;
- Admin Shell/sidebar/topbar redesign unless a WU5-accepted shared primitive requires a strictly bounded shared adjustment;
- new lifecycle/domain capability;
- schema/persistence changes;
- ownership changes;
- retired-manager restoration;
- Deferred Item adoption;
- old Media WU5 continuation as a Media workstream;
- production reconciliation;
- release/tag/publication/distribution;
- broad unrelated cleanup.

## Validation direction

Validation must be proportional to the actual shared artifacts touched and should prove:
- canonical shared source exists and is consumed as intended;
- no unnecessary duplicate base styling remains for migrated primitives;
- accepted domain exceptions remain intact;
- existing routes/workflows/data semantics remain unchanged;
- responsive behavior remains valid;
- accessibility behavior remains valid;
- directly affected accepted WU1–WU4 surfaces do not regress;
- CSS/JS/PHP/static validation relevant to changed files passes;
- `git diff --check` passes;
- final diff contains no opportunistic domain redesign.

## Completion direction

WU6 is complete only when the accepted WU5 canonical primitives exist as real shared implementation artifacts and are ready for representative adoption/proof without unresolved shared-foundation design decisions.

WU6 completion does not imply every Admin consumer has been normalized. That proof belongs to WU7.

## Promotion and authorization boundary

This pre-contract may be revised during review.

Only explicit promotion may create an authoritative WU6 contract. Separate implementation authorization is still required after promotion unless the promoting action explicitly authorizes the bounded implementation slice.
