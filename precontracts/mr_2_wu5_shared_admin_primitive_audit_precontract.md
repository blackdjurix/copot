# MR.2 WU5 — Shared Admin Primitive Audit & Contract — Pre-contract

Lifecycle: DRAFT / TRIAL / NOT AUTHORITATIVE
Workplan role: forward MR.2 WU5 planning boundary
Promotion status: NOT PROMOTED
Audit classification: REVIEW-READY
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

## Audit result — 2026-08-27

The audit was performed against authoritative `main` at `8a4fba956693a021215685d207ad9c40dc431d3b`. The repository was clean after fast-forwarding the local checkout to that remote anchor. No source, runtime, schema, or Concept files were changed.

### Primitive audit matrix

| Primitive/pattern | Current canonical/shared source | Representative consumers | Local variants or duplicates | Classification | Evidence and disposition | WU6 impact |
|---|---|---|---|---|---|---|
| Design tokens: color, spacing, radius, control height, typography | `public/admin-assets/css/admin.css`, `:root` tokens | Admin Shell, Page Frame, Content, Media, Settings, System Manager, Users | A few domain rules use literal values in older/local sections | already-canonical shared primitive | The token set is the actual single source. Retain it; WU6 may replace only directly equivalent literals where semantics are unchanged. | Token ownership is retained; no new theme/design-system authority. |
| Buttons and primary/secondary/link sizing | `.admin-button`, `.admin-button--primary`, `--secondary`, `--danger`, `--link` | Content, Media, Taxonomy, Navigation, Users, Settings, System Manager | Domain action groups add placement or destructive context | already-canonical shared primitive | Shared base sizing and focus behavior are real and broadly consumed. Keep action meaning consumer-owned. | WU6 may normalize equivalent button markup/classes, not action semantics. |
| Panel/card/surface treatment | `.admin-panel`, `.admin-panel__header`, `__heading`, `__title`, `__description`, `__body`; WU1 `.admin-page-frame*` | Media, Settings, Users, Taxonomy, Navigation, Theme, Module Manager | Content has `admin-content-*` page/table/form panels; System Manager has status/module cards | already-canonical shared primitive | Base panel and Page Frame ownership are explicit. Content and System Manager internal cards carry different data/state semantics and are not interchangeable. | Consolidate only equivalent wrappers; preserve stateful/domain cards. |
| Page title, eyebrow/contextual kicker, and header composition | Content `.admin-content-eyebrow`, `.admin-content-header*`; panel header pattern; Page Frame heading | Content, Form Manager, Redirects; conventional panel consumers | Many consumers omit eyebrow or use panel headers; Form Manager repeats Content header composition | duplicated/local equivalent suitable for consolidation | Repetition is structural, not evidence that every page needs an eyebrow. WU6 can define an optional page-heading primitive with optional kicker/description/action regions. | Must preserve optionality and page-level ownership; no Shell redesign. |
| Toolbar/search/filter controls | Content `.admin-content-filters`, `admin-content-filter-field`, summary/actions; Media reuses the filter grid plus `.admin-media-filters`; Form submissions reuse Content filter classes | Content, Media, Form submissions | Media heading/help and spacing are local; Settings uses separate tab/form controls | duplicated/local equivalent suitable for consolidation | Content filter composition is reused by Media and Form submissions, proving a candidate shared filter layout, but field meaning/query semantics remain consumer-owned. | WU6 may extract layout/active-filter presentation only; no generic search/indexing. |
| Control-to-results spacing and section rhythm | Content page/layout gaps and table-panel headers; Media-specific filter margin; System Manager workspace gaps | Content, Media, System Manager | Media filter/library separation and Settings tab/panel gaps are local | duplicated/local equivalent suitable for consolidation | Equivalent visual separation is repeated, but spacing is currently encoded by container composition rather than one class. WU6 may introduce bounded stack/section spacing where it does not change flow semantics. | Verify no unintended density or overflow changes. |
| Form fields, labels, helper/error text | `.admin-fieldset`, `.admin-field`, `.admin-field__label`, `__help`, `__error`, `.admin-form`, `.admin-form__actions` | Content, Taxonomy, Settings, Users, Navigation, Theme | System Manager has semantic detail rows; Media upload has domain title-row; some older forms use direct markup | already-canonical shared primitive | Shared field/error primitives and accessible invalid styling are real. Inline detail rows and upload intake rows have different responsive/semantic requirements. | WU6 may add an optional bounded inline-field layout, never replace semantic field grouping. |
| Action rows and paired actions | `.admin-actions`, `.admin-panel__actions`, `.admin-form__actions`, `.admin-row-actions` | Content, Media, Taxonomy, Navigation, Users, Forms | Domain action rows control ordering, destructive treatment, and table nowrap behavior | already-canonical shared primitive | Shared flex/gap and button classes exist; row-specific classes encode context and should remain where needed. | Normalize equivalent action-row spacing only; retain context-specific grouping. |
| Modal/dialog action rhythm | Media preview/picker selectors and native `dialog`; no generic dialog primitive | Media preview, Content Featured Media picker | Media preview and picker have separate markup, focus, state, and safety behavior | legitimate domain-specific specialization | Dialog interactions differ materially: preview navigation/title/delete versus consumer-scoped crop/select/cancel. Similar spacing is insufficient evidence for a shared behavior primitive. | WU6 may share neutral spacing tokens/classes only; no dialog framework. |
| Typography scale and grid | Root font/token baseline; Page Frame/panel headings; Content and System Manager grids | All Admin surfaces | Older module-specific headings and compact metadata rules | already-canonical shared primitive | Global font and core heading sizes are shared; metadata density and grid columns follow domain information architecture. | Avoid global typography changes; bound any consolidation to equivalent declarations. |
| Radius versus flat/square surfaces | Root radius tokens; panels/buttons consume them; System Manager update bar explicitly flat | Admin panels/buttons, System Manager update bar, tables/dialogs | Media picker uses local literal radius/padding; older surfaces vary | conflicting accepted surface requiring a bounded decision | Accepted WU1/Page Frame and System Manager surfaces establish both panel-radius and intentionally flat variants. WU6 must document intent before touching local literals; no global flattening follows from similarity. | Requires bounded disposition per selector, not an architectural decision. |
| Responsive behavior and accessibility | Shared focus ring, min-width/overflow rules, table wrappers, Page Frame media rules; consumer JS where interactive | Content, Media, System Manager, Admin Shell | Content table, Media grid/preview, System Manager fit logic use different breakpoints and interaction models | legitimate domain-specific specialization | Responsive and accessibility behavior is partly shared CSS and partly consumer-owned JS/markup. Media and System Manager require domain-specific keyboard/focus/state handling. | WU6 proves propagation without flattening interaction behavior. |
| Shared JS/artifact ownership | `admin-shell.js`, `admin-form-capabilities.js`, `admin-settings.js`, `admin-media.js`, `system-manager.js`, `content-media-picker.js` loaded by owning surfaces | Shell, Settings/System Manager, Media, Content | No generic component registry or shared visual JS layer | product/architecture/capability question outside WU5 | Existing scripts own behavior, not a generic primitive registry. Introducing one would exceed WU5/WU6. | WU6 remains CSS/markup/artifact consolidation; behavior stays consumer-owned. |

### Accepted reference surfaces

WU1 Page Frame is the authority for page-level frame ownership and optional surface/spacing intent. WU4 Content is the strongest reference for list hierarchy, filter composition, form rhythm, and responsive table behavior, but it is not universal design authority. WU2/WU3 System Manager is the reference for responsive state/detail grids and lifecycle-action comprehension. Media is historical evidence only for this forward WU5 and is not resumed as a Media-specific refinement stream.

No unresolved accepted-surface conflict requires a new product or architecture decision. The radius/flat-surface difference is a bounded selector-level disposition for WU6, not permission for Admin Shell redesign.

### Questions intentionally left outside WU5

None block the proposed WU6 boundary. The unresolved thread-level Shared File Intake Interaction Pattern remains separately registered in `workplan.md` and is not adopted, resolved, or promoted by this audit. Dashboard composition, Admin Shell identity/theme direction, dark mode, generic component registration, and domain-specific refinement remain separate planning questions.

## Old Media WU5 disposition during audit

The old Media WU5 delta must be classified only where material to shared primitives:
- valid correctness fix;
- reusable shared-artifact candidate;
- future Media-specific refinement;
- local redesign that should not survive.

WU5 must not resume Media-specific refinement.

### Concrete old Media WU5 evidence disposition

- **Valid correctness fix:** the historical correction that captured the controlled `$mediaUrl` dependency in the Content context-picker route; it corrected an actual active-picker failure without changing ownership or processing semantics. The historical deletion-guidance ordering correction is likewise a local correctness/presentation fix, not a shared primitive.
- **Reusable shared-artifact candidate:** reuse of `.admin-panel`, `.admin-actions`, `.admin-button*`, root spacing/radius tokens, `.admin-content-filters`, and existing overflow/focus patterns. These are evidence of existing shared artifacts, not new Media-owned primitives. Media-specific filter separation may inform a bounded stack/section-spacing utility.
- **Future Media-specific refinement:** Media card/grid hierarchy, preview metadata/navigation, upload intake, controlled preview dialog, and Content consumer crop/pending preparation remain Media/consumer-owned surfaces. They are not WU6 adoption targets and must not be resumed here.
- **Local redesign that should not survive:** the historical design direction for a Media-only thumbnail/list mode, renamed upload destination, new destructive confirmation workflow, or any hardcoded Media-specific picker/layout values treated as universal Admin rules. No evidence authorizes carrying those concepts into shared primitives.

The existence of `docs/43_mr_2_wu5_media_manager_refinement_contract.md` and its implementation is historical old-WU evidence only; it does not establish current forward WU5 authority.

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

## Proposed WU6 implementation boundary

WU6 may implement only bounded shared presentation artifacts proven by this audit:

1. an optional page-heading composition for title, optional eyebrow/kicker, description, and action regions;
2. a shared filter-toolbar layout with optional search/filter fields, active-filter/result context, and recovery presentation, while consumers retain query/filter semantics;
3. bounded stack/section spacing and optional inline-field layout using existing tokens;
4. normalization of equivalent action-row/button composition using existing button semantics;
5. selector-level radius/flat-surface dispositions where accepted intent is explicit; and
6. focused representative adoption in existing consumers sufficient to prove propagation, responsive behavior, accessibility, and documented exceptions.

WU6 must not introduce a component registry, generic search/indexing, dialog framework, form engine, workflow engine, new JS behavior authority, domain lifecycle behavior, schema/persistence, or per-Module refinement. System Manager, Media, Content, Dashboard, and retired-manager ownership remain unchanged.

## Promotion-ready acceptance criteria

The WU5 boundary is review-ready when an authoritative contract can require that:

- each adopted shared primitive has one identifiable CSS/token/class/artifact owner;
- equivalent consumers use the shared artifact without changing domain semantics;
- local variants are either retired, explicitly documented as exceptions, or left outside the adoption proof;
- page headers, filters, form/helper/error presentation, action rows, spacing, radius policy, responsive behavior, and accessibility are checked against concrete representative consumers;
- System Manager, Media, Content, Dashboard, and retained Bundled Module ownership boundaries remain unchanged;
- no old Media-specific refinement, Deferred capability, generic framework, or product/architecture decision is promoted; and
- WU6 focused validation proves desktop/mobile readability, keyboard/focus behavior, accessible labels/errors, no unintended overflow, and no source-of-truth duplication.

## Acceptance direction

WU5 pre-contract review is acceptable when the audit boundary is specific enough that WU6 can implement shared primitives without making new product, architecture, ownership, or domain-workflow decisions.

The audit must preserve WU1–WU4 accepted semantics and must not treat visual consistency as permission to flatten meaningful domain differences.

## Promotion boundary

This pre-contract may be revised during review.

Only explicit promotion may create an authoritative MR.2 WU5 repository contract. Promotion does not by itself authorize WU6 implementation.
