# MR.2 WU5 — Shared Admin Primitive Audit & Contract — Pre-contract

Lifecycle: DRAFT / TRIAL / NOT AUTHORITATIVE
Workplan role: forward MR.2 WU5 planning boundary
Promotion status: NOT PROMOTED
Implementation authorization: NONE

## Purpose

Materialize the current MR.2 WU5 planning target into a reviewable pre-contract before any promotion into authoritative repository documentation.

This file is a planning artifact. It does not override existing authoritative contracts, does not reopen closed WU1–WU4, does not supersede repository authority by itself, and does not authorize implementation.

The historically promoted Media Manager WU5 contract remains repository evidence for the old topology. Current forward WU5 is the shared Admin primitive audit described here.

## Objective

Audit current Admin presentation implementation and accepted MR.2 refinement lineage, distinguish canonical shared presentation primitives from duplicated or domain-specific variants, and define a bounded shared-primitive contract candidate for later explicit promotion.

WU5 is an audit and contract-definition unit. It is not a broad implementation or redesign pass.

## Planning and authority inputs

Primary planning inputs:
- `workplan.md` — current MR.2 registry and forward topology;
- `copot_core_module_refinement_concept_260810_184600.md`;
- `copot_consolidated_refinement_concepts_260816_194432.md`;
- `copot_consolidated_refinement_concepts_260814_151800.md`;
- `core_modules_dashboard_refinement_concept_260804_161738.md`.

Current architecture input:
- `concept_webcore_extension_architecture_reconciliation_260820_234300.md`;
- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`.

Accepted historical MR.2 evidence:
- WU1 Admin Page Frame;
- WU2 System Manager baseline;
- WU3 System Manager lifecycle/modules UX;
- WU4 Content Manager refinement;
- old Media WU5 implementation and contract as evidence only, not current forward authority.

## Minimum audit surface

WU5 must audit at minimum:
- page title;
- eyebrow/contextual kicker;
- page-header composition;
- section/card/surface treatment;
- toolbar/search/filter treatment;
- spacing between controls and result/content regions;
- form-field layout;
- helper text;
- action rows;
- primary/secondary paired button sizing;
- modal/dialog action spacing and rhythm;
- typography scale;
- spacing/grid;
- radius and flat/square surface policy;
- responsive behavior;
- accessibility behavior;
- shared CSS/token/class/artifact ownership.

## Required classification model

Each materially repeated presentation pattern must be classified as one of:
1. already-canonical shared primitive;
2. duplicated/local equivalent suitable for consolidation;
3. legitimate domain-specific specialization that should remain local;
4. conflicting accepted surface requiring a bounded decision;
5. reference surface only;
6. product/architecture/capability question that must leave WU5 scope.

Similarity alone is not enough to force consolidation. A local variant may remain when semantic responsibility, interaction, accessibility, state model, or consumer requirements materially differ.

## Shared primitive rules

- Semantically equivalent shared presentation should consume a canonical shared artifact/token/class rather than recreate equivalent local styling.
- Consumer/domain styling may specialize a shared primitive without redefining its base semantics.
- Canonical shared primitives must not take ownership of domain behavior, workflow, data state, authorization, or lifecycle semantics.
- A shared primitive change should propagate to intended consumers unless an explicit semantic exception is documented.
- Flat/square treatment remains the default visual direction unless a concrete component requires a justified exception.

## Reference-surface rules

Accepted WU1–WU4 surfaces may be used as evidence and comparison baselines.

No single accepted consumer automatically becomes universal design authority merely because it was refined earlier.

Content may be a useful reference for density, hierarchy, form/action treatment, and responsive behavior, but WU5 must verify whether each pattern is actually generic before extracting it.

## Old Media WU5 disposition during audit

The old Media WU5 delta must be classified only where material to shared primitives:
- valid correctness fix;
- reusable shared-artifact candidate;
- future Media-specific refinement;
- local redesign that should not survive.

WU5 must not resume Media-specific refinement.

## Explicit exclusions

WU5 does not authorize:
- shared-artifact implementation beyond minimal audit/prototype evidence strictly necessary to prove classification;
- broad CSS migration;
- full Admin Shell redesign;
- Dashboard composition or widget-layout redesign;
- per-Bundled-Module refinement;
- domain workflow changes;
- new capability;
- schema or persistence work;
- ownership changes;
- retired-manager restoration;
- Deferred Item adoption;
- production reconciliation;
- release/tag/publication/distribution;
- unrelated cleanup.

## Audit outputs required before promotion

A promotion-ready WU5 result must identify:
- canonical primitives to keep;
- primitives to introduce or consolidate;
- local duplicates to retire later;
- valid domain exceptions;
- representative reference surfaces;
- affected shared artifacts/tokens/classes;
- expected consumer impact;
- unresolved questions that leave MR.2;
- exact WU6 implementation boundary;
- acceptance criteria suitable for authoritative contract promotion.

## Acceptance direction

WU5 pre-contract review is acceptable when the audit boundary is specific enough that WU6 can implement shared primitives without making new product, architecture, ownership, or domain-workflow decisions.

The audit must preserve WU1–WU4 accepted semantics and must not treat visual consistency as permission to flatten meaningful domain differences.

## Promotion boundary

This pre-contract may be revised during review.

Only explicit promotion may create an authoritative MR.2 WU5 repository contract. Promotion does not by itself authorize WU6 implementation.
