# MR.2 WU7 — Representative Adoption & Propagation Proof Contract

## Status and authority

```text
MR.2 WU1: COMPLETE AND CLOSED
MR.2 WU2: COMPLETE AND CLOSED
MR.2 WU3: COMPLETE AND CLOSED
MR.2 WU4: COMPLETE AND CLOSED
MR.2 WU5: COMPLETE / CONTRACT LOCKED
MR.2 WU6: COMPLETE / IMPLEMENTED AND ACCEPTED
WU7 scope: Representative Adoption & Propagation Proof
WU7 contract: PROMOTED / CONTRACT LOCKED
WU7 runtime/source implementation: COMPLETE
WU7 objective validation: COMPLETE / PASS
WU7 AI acceptance: PASS
WU7 human acceptance: NOT REQUIRED
WU7 lifecycle state: COMPLETE / CLOSED
```

This contract is the authoritative promotion of the reviewed WU7 Pre-contract.
The Pre-contract remains preserved as historical planning provenance. Promotion
defines the bounded WU7 implementation scope and does not authorize WU8 work or
any capability outside this contract.

The accepted implementation is anchored at
`dd8c85ad1a8544c05c7753dba84f518593e985e0` (`feat(mr.2): prove WU7
representative propagation`).

## Objective

Adopt or normalize the accepted WU6 shared Admin primitives across a small,
materially diverse representative set sufficient to prove that shared
presentation artifacts propagate without erasing valid domain differences.

WU7 is proof of the shared foundation. It is not a disguised per-Bundled-Module
refinement pass or full Admin-surface migration.

## Locked representative proof set

The bounded representative proof set is:

- **Core Webcore Content Admin:** the list plus create/edit form surfaces. This
  is the preferred Webcore-owned baseline representative because the Webcore
  Content Admin Baseline is complete and closed.
- **System Manager:** the representative Modules/reference surface, including
  its state, detail, and lifecycle-action presentation.
- **Media Manager:** the representative library/list plus upload surfaces,
  included only as a shared-artifact propagation consumer and not as renewed
  Media-specific refinement scope.

Coverage may use these bounded route/surface families rather than requiring
whole-module migration. The proof set is not a full migration of each surface
family.

The set covers, through those bounded surfaces:

- an accepted Webcore-owned baseline;
- an accepted retained Bundled Module;
- form-oriented and action-heavy presentation;
- list/workspace presentation; and
- responsive/mobile behavior where the shared primitive materially affects
  layout or interaction.

## Preserved WU6 and consumer ownership

The accepted WU6 shared-artifact ownership remains authoritative. Shared
artifacts own presentation semantics only. Consumers retain ownership of:

- route and workflow semantics;
- data and lifecycle state;
- action eligibility;
- permissions and authorization;
- validation rules and error meaning;
- domain-specific markup where semantic differences require it; and
- specialized responsive or interaction behavior justified by the domain.

Visual similarity alone does not justify forced consolidation.

## Bounded implementation scope

WU7 may make only the bounded consumer adjustments required to prove adoption
of the accepted WU6 artifacts, including:

- replacing local duplicate classes or tokens with canonical equivalents;
- adjusting wrapper markup where required by an accepted shared presentation
  contract;
- removing redundant local base styling after equivalence is proven; and
- preserving or documenting local specialization where semantic differences
  require it.

If adoption requires a new primitive or a material change to the WU6
abstraction, implementation must stop and route the finding back to the shared
foundation boundary rather than inventing a consumer-local workaround.

## Legitimate local specialization

The following remain local unless exact semantic equivalence is proven:

- System Manager lifecycle, status, and module-card semantics;
- Media cards, preview/dialog, processing, usage, and deletion-safety
  presentation;
- domain-specific responsive behavior; and
- dialog-specific focus and interaction behavior.

Media Manager is included only for representative shared-artifact propagation.
This contract does not resume the historical Media WU5 refinement stream.

## Proof requirements

WU7 must demonstrate that:

1. an intended change to a canonical primitive reaches every representative
   consumer that should inherit it;
2. consumers do not require unnecessary local copies of the same base styling;
3. legitimate semantic differences remain explicit exceptions rather than
   accidental forks;
4. domain behavior, workflow, action eligibility, validation, permissions, and
   lifecycle semantics remain unchanged;
5. the shared abstraction is not overfit to Content, Media, System Manager, or
   another single surface;
6. responsive/mobile behavior remains coherent;
7. accessibility relationships and interaction semantics remain intact; and
8. no consumer is redesigned beyond what is necessary to adopt the accepted
   primitive.

## Explicit exclusions

WU7 does not authorize:

- full migration of every Admin surface;
- per-Bundled-Module refinement;
- Content Manager or Media Manager redesign;
- Dashboard composition or widget redesign;
- domain workflow expansion or new product capability;
- schema or persistence changes;
- ownership changes or retired-manager restoration;
- new shared primitive creation unless WU6 is proven materially insufficient;
- global flat/radius policy or Color Scheme work;
- thread-level capability/provider concepts;
- Deferred Item adoption;
- old Media WU5 continuation as Media-specific scope;
- production reconciliation;
- release, tag, publication, or distribution work; or
- unrelated cleanup.

## Validation direction

Validation must prove propagation rather than merely screenshot similarity and
must include, as applicable:

- source-level confirmation that the representative consumers use canonical
  artifacts;
- focused rendering/presentation tests where harnesses support them;
- responsive/mobile review for materially affected primitives;
- keyboard, focus, and accessibility checks relevant to changed semantics;
- focused regression of directly affected accepted behavior;
- verification that removed local styling was truly redundant; and
- `git diff --check` plus final scope and ownership review.

Evidence must confirm that workflow, permissions, validation, lifecycle, and
domain semantics remain unchanged. Human/product review is required only where
visual judgment or interaction comprehension cannot be established
deterministically.

The historical Media workspace test
`tests/m3_8_work_unit5_admin_media_workspace.php` remains classified as
**NON-BLOCKING PRE-EXISTING VALIDATION DEBT**. It produces an undefined
`$mediaAdminPath` warning from `modules/media/routes.php:192` and fails the
expected Media upload-form presentation assertion at test line 94. The same
warning and assertion failure reproduce identically at the immediate WU7 parent
`f73e60ea7d507c447353c9aa258bd2012a80b3fa`; the Media route source is identical
between parent and WU7 implementation, and no WU7-changed file participates in
that path. The historical assertion is not required to prove WU7 propagation,
independent WU7 Media evidence is sufficient, and no WU7 acceptance criterion
remains unproven.

## Completion direction

WU7 is complete when representative evidence demonstrates that the shared
foundation is reusable, propagating, exception-aware, responsive,
accessible, and non-destructive. Completion does not mean every future consumer
is migrated. WU8 determines whether remaining cross-surface inconsistencies are
material blockers to MR.2 closure or belong to later owner workstreams.

## Closure record

WU7 implementation and objective validation are complete and accepted for the
locked representative proof set. No human acceptance was required for this
bounded source/test delta. WU7 proves representative propagation only and does
not claim that every Admin consumer has been migrated or refined.

The branch lifecycle is **main-only / no-op**: the accepted work was performed
on authoritative `main`, with no WU7-specific feature branch requiring cleanup.
MR.2 WU8 — Cross-Surface Verification & MR.2 Closure remains the next work unit
and is **NOT STARTED**. This closure does not implement or authorize WU8.

## Authorization and governance boundary

Bounded WU7 execution may proceed through a GPT-framed Agent Instruction under
the accepted and promoted MR.2 workstream authorization. Promotion of this
contract does not itself execute implementation.

Fresh explicit user approval remains required only when execution crosses a real
governance boundary, including scope expansion outside accepted MR.2 scope,
Deferred Item adoption, an unresolved product or architecture decision,
destructive or irreversible action, production reconciliation or similarly
sensitive operations, release/tag/publication/external distribution, or another
approval gate explicitly reserved to the user.

## Source and provenance references

- `precontracts/mr_2_wu7_representative_adoption_propagation_proof_precontract.md` — reviewed historical planning provenance;
- `docs/44_mr_2_wu5_shared_admin_primitive_audit_contract.md` — shared primitive classifications and exceptions;
- `docs/45_mr_2_wu6_shared_artifact_consolidation_implementation_contract.md` — accepted shared-artifact boundary;
- `docs/46_webcore_content_admin_baseline_contract.md` — closed Webcore Content Admin baseline;
- `docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md` — accepted Page Frame foundation;
- `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md` — accepted System Manager baseline; and
- `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md` — accepted System Manager reference surface.
