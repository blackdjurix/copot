# MR.2 WU7 — Representative Adoption & Propagation Proof — Pre-contract

Lifecycle: DRAFT / TRIAL / NOT AUTHORITATIVE
Workplan role: forward MR.2 WU7 planning boundary
Promotion status: NOT PROMOTED
Implementation authorization: NONE
Dependency: WU6 shared artifact implementation accepted and stable enough for propagation proof

## Purpose

Define the bounded proof unit that demonstrates the WU6 shared Admin artifacts actually propagate across representative consumers without erasing valid domain differences.

This file is a planning artifact. It does not authorize implementation and does not imply WU6 completion.

## Objective

Adopt or normalize the accepted shared primitives across a small representative set of Admin consumers sufficient to prove:
- canonical shared changes propagate;
- shared abstractions remain usable across materially different surfaces;
- explicit semantic exceptions remain bounded and intact;
- responsive and accessibility behavior remain valid;
- local duplicate base styling can be removed where no longer justified.

WU7 is proof of the shared foundation. It is not a disguised per-Bundled-Module refinement pass.

## Representative consumer selection

The representative set must be small but materially diverse.

Selection should cover, where available and useful:
- at least one accepted Webcore-owned/Admin baseline surface;
- at least one accepted domain/Bundled-Module surface;
- at least one form-oriented or action-heavy surface;
- at least one list/workspace-oriented surface;
- responsive/mobile behavior where the primitive materially changes layout or interaction.

Accepted WU1–WU4 consumers are preferred evidence because their behavior and product boundaries are already established.

Consumer selection must be based on proof value, not on a desire to touch every surface.

## Proof requirements

WU7 must demonstrate that:
1. an intended change to a canonical primitive reaches every representative consumer that should inherit it;
2. consumers do not require unnecessary local copies of the same base styling;
3. legitimate semantic differences remain explicit exceptions rather than accidental forks;
4. domain behavior, workflow, action eligibility, validation, permissions, and lifecycle semantics remain unchanged;
5. the shared abstraction is not overfit to one reference surface;
6. responsive/mobile behavior remains coherent;
7. accessibility relationships and interaction semantics remain intact;
8. no consumer is redesigned beyond what is necessary to adopt the accepted primitive.

## Adoption boundaries

WU7 may make bounded consumer adjustments required to consume the WU6 shared artifacts, such as:
- replacing local duplicate classes/tokens with canonical equivalents;
- adjusting wrapper markup where required by an accepted shared semantic contract;
- removing redundant local base styling after equivalence is proven;
- preserving or documenting local specialization where semantic differences require it.

If adoption requires a new primitive or a material change to the WU6 abstraction, stop and route the finding back to the shared-foundation boundary rather than inventing a consumer-local workaround that obscures the design problem.

## Explicit exclusions

WU7 does not authorize:
- full migration of every Admin surface;
- per-Bundled-Module refinement;
- Dashboard composition/widget redesign;
- domain workflow expansion;
- new product capability;
- schema/persistence changes;
- ownership changes;
- retired-manager restoration;
- Deferred Item adoption;
- old Media WU5 continuation as Media-specific scope;
- production reconciliation;
- release/tag/publication/distribution;
- unrelated cleanup.

## Validation direction

Validation should prove propagation rather than merely screenshot similarity.

Required evidence should include, as applicable:
- source-level confirmation that representative consumers use the canonical artifact;
- focused rendering/presentation tests where existing harnesses support them;
- responsive/mobile review for materially affected primitives;
- accessibility checks relevant to changed semantics;
- focused regression of directly affected accepted behavior;
- verification that removed local styling was truly redundant;
- `git diff --check`;
- final diff review for accidental per-consumer redesign.

Human/product review is required only where visual judgment or interaction comprehension cannot be established with sufficient confidence by deterministic evidence.

## Completion direction

WU7 is complete when representative evidence demonstrates the shared foundation is reusable, propagating, exception-aware, and non-destructive.

WU7 completion does not mean every future consumer is migrated. WU8 decides whether remaining cross-surface inconsistencies are material blockers to MR.2 closure or belong to later owner workstreams.

## Promotion and authorization boundary

This pre-contract may be revised during review.

Only explicit promotion may create an authoritative WU7 contract. Promotion does not authorize WU8 closure work unless separately stated.
