# MR.2 WU6 — Shared Artifact Consolidation & Implementation — Pre-contract

Lifecycle: DRAFT / TRIAL / NOT AUTHORITATIVE
Workplan role: forward MR.2 WU6 planning boundary
Promotion status: NOT PROMOTED
Reconciliation classification: REVIEW-READY
Implementation authority: bounded by the promoted MR.2 governance boundary; this pre-contract does not authorize out-of-scope work
Dependency: WU5 authoritative shared-primitive contract or explicitly accepted equivalent planning boundary

## Purpose

Define the bounded implementation unit that follows WU5 after canonical shared Admin primitives and their exception model have been accepted.

This file remains a planning artifact and is not an authoritative WU6
contract. It does not override repository contracts or authorize work outside
the accepted WU6 boundary. WU5 is now complete and contract-locked under
`docs/44_mr_2_wu5_shared_admin_primitive_audit_contract.md`.

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

WU6 may implement only the following primitives accepted through WU5:
1. optional page-heading composition for title, optional kicker, description,
   and action regions;
2. shared filter-toolbar layout with optional search/filter fields,
   active-filter/result context, and recovery presentation, while consumers
   retain query/filter semantics;
3. bounded stack/section spacing and optional inline-field layout using
   existing tokens;
4. equivalent action-row/button normalization using existing button semantics;
5. selector-level radius/flat-surface dispositions with explicit intent; and
6. focused representative adoption proving propagation, responsive behavior,
   accessibility, and documented exceptions.

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

Only explicit promotion may create an authoritative WU6 contract. Routine
continuation from WU5 into the bounded WU6 scope does not require fresh user
approval merely because the work unit changes; execution proceeds through a
GPT-framed Agent Instruction under the accepted MR.2 workstream. Fresh
explicit approval remains required for scope expansion, Deferred Item
adoption, an unlocked product or architecture decision, destructive or
irreversible action, production reconciliation or similarly sensitive
operations, release/tag/publication/external distribution, or another gate
explicitly reserved to the user.
