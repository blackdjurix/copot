# Webcore Product Completeness & Stabilization — WU3 Core Primary Navigation Admin Baseline Contract

## Status and authority

Workstream: Post-M3 — Webcore Product Completeness & Stabilization
Work Unit: WU3 — Core Primary Navigation Admin Baseline
Contract status: PROMOTED / CONTRACT LOCKED
Technical implementation: SEPARATELY AUTHORIZED / IN PROGRESS
Release / tag / publication authorization: NONE

This contract refines and locks the WU3 boundary established by
`docs/49_webcore_product_completeness_stabilization_contract.md` and
`docs/51_webcore_product_completeness_wu1_scope_reconciliation_contract.md`
against current Core Navigation and Core Content source evidence plus the
subsequently approved product decisions required to complete the baseline.

Promotion of this contract establishes accepted WU3 scope. It does not by itself
authorize source, runtime, schema, configuration, package, release, or other
technical execution.

## WU3 objective

Provide an always-available Webcore-owned Admin projection for one complete
Primary Navigation capability while preserving existing Core Navigation and
Core Content authority and operating with Navigation Manager absent.

WU3 is not a general menu-management product and is not authorization to move
the Navigation Manager implementation wholesale into Webcore.

## Existing authority and reuse boundary

WU3 must preserve existing singular authorities:

- Core Navigation repository/service/model authority remains authoritative for
  baseline Navigation persistence, hierarchy, ordering, validation, and
  lifecycle behavior;
- Core Content repository/delivery/model authority remains authoritative for
  individual Webcore Content and the bounded Article Collection read/delivery
  addition introduced by this contract;
- the existing `navigation.manage` permission remains the baseline management
  permission;
- existing CSRF, Admin access, validation, and sanitized-failure boundaries are
  preserved.

No duplicate Navigation or Content authority may be introduced merely to
materialize the Admin projection or Article Collection delivery.

## Canonical Primary Navigation identity

The operator-facing Webcore Primary Navigation uses the existing canonical menu
identity `primary`.

Existing read/render compatibility behavior that falls back to the first
available menu may remain where technically required, but such fallback does not
redefine an arbitrary first menu as the operator-facing canonical Primary
Navigation.

WU3 must not introduce:

- `primary_menu_id` or equivalent competing persistence;
- a baseline menu selector;
- a baseline multi-menu workspace;
- a new primary-menu assignment subsystem.

If the canonical `primary` menu is absent, the Core Admin projection may
materialize it through existing Core Navigation authority.

## Core Primary Navigation product shape

The baseline operator model is:

`Navigation -> Primary Navigation`

The Core Admin surface manages one navigation structure, not a generic menu
catalog.

Required baseline operations:

- view the Primary Navigation hierarchy;
- add item;
- edit item;
- remove item;
- reorder sibling items;
- change parent within the accepted hierarchy boundary.

The accepted WU3 interaction includes bounded drag-and-drop for sibling reorder
and cross-level reparenting, with a deterministic keyboard/non-pointer fallback.
This remains specific to the Core Primary Navigation tree and does not authorize
a generic drag-and-drop framework.

## Hierarchy boundary

WU3 reuses existing Core hierarchy semantics and preserves the current maximum
total depth of five levels, including the top-level item.

Baseline hierarchy behavior must continue to reject:

- cycles;
- self-parenting;
- descendant-as-parent placement;
- cross-menu parent placement;
- placements that cause the moved subtree to exceed the five-level maximum.

Existing exact-sibling reorder validation and transactional mutation behavior
remain authoritative.

## Baseline target model

Core Primary Navigation supports exactly four baseline target classes:

1. Link;
2. Content;
3. Article Collection; and
4. Navigation Group.

### 1. Link

Link represents an explicit operator-supplied destination. Accepted forms are
bare domains (including an optional path), root-relative paths, fragments, and
the explicit `http`, `https`, `ftp`, `mailto`, and `tel` schemes. Bare domains
remain unprefixed in persistence and resolve publicly as protocol-relative
destinations. Unsupported or unsafe schemes, including `javascript:`, `data:`,
and `file:`, plus control-character, backslash, and malformed values, are
rejected by the existing Core validation boundary.

### 2. Webcore Content

Webcore Content represents one individual published Webcore Content entry.

Eligible baseline Content types are:

- Page;
- Article.

Page and Article are one Navigation target family, `Content`; their Content type
is semantic/type metadata rather than separate Navigation target kinds.

Native Content targeting must be preserved even though an operator could
technically reproduce an Article URL as a Link. Native targeting keeps the
Navigation item aware of Content identity, validation, and lifecycle state
instead of degrading the target to an opaque URL string.

For public resolution:

- published Page resolves;
- published Article resolves;
- draft Content does not resolve;
- archived Content does not resolve;
- missing or stale Content reference does not resolve;
- failure is safe and does not silently retarget elsewhere.

### 3. Article Collection

Article Collection is one canonical Webcore-owned aggregate destination.

The canonical zero-optional public URL is:

`/articles`

Baseline collection semantics:

- membership includes only Content with `type = article`;
- membership includes only `status = published`;
- Page is excluded;
- membership follows Article lifecycle dynamically;
- default ordering is publication chronology, newest first;
- ordering uses a deterministic tie-breaker where publication timestamps are
  equal;
- the collection identity remains valid when zero Articles are published;
- an empty collection produces a valid empty-state delivery rather than a 404 or
  stale-target failure.

Navigation targets the aggregate Article Collection identity, not a stored list
of Article IDs.

### 4. Navigation Group

Navigation Group is a structural baseline target with a required label and no
destination or target reference. It may exist without children and may contain
children within the existing five-level hierarchy boundary. Core public
resolution retains visible groups with a null URL and distinguishes them from
destination items.

For the default Webcore public navigation, destination items with children
expose their submenu on hover and focus while their destination link remains
activatable. Navigation Groups use a semantic non-link control: pointer hover,
focus/focus-within, and touch activation expose or toggle their child submenu;
the Group itself never navigates. This is bounded to the default public
projection and does not authorize Theme-wide navigation infrastructure or a
generic menu framework.

## Bounded Article Collection delivery

WU3 may add only the minimum Core Content read/delivery capability required to
support the accepted Article Collection target.

Permitted bounded additions include:

- a Core ContentRepository read operation for published Articles using
  publication chronology;
- a Core ContentDeliveryService collection read projection;
- the canonical `/articles` public route;
- a minimal Core public Article Collection view;
- the Core Navigation target-resolution addition necessary to resolve the
  canonical Article Collection destination.

These additions do not reopen Content ownership and do not turn WU3 into Content
refinement.

WU3 must not introduce:

- a generic Content Collection framework;
- arbitrary collection builders;
- a generic collection registry;
- generic provider arbitration;
- new Content write/lifecycle semantics merely for collection delivery;
- new schema solely for Article Collection;
- stored Navigation membership lists for collection items.

## Page / Article semantic scope

This contract consumes only the minimum semantic distinction necessary for
Navigation and Article Collection delivery:

- Page is an individual Webcore Content destination and does not automatically
  participate in the Article Collection;
- Article is an individual Webcore Content destination and, when published,
  participates in the Article Collection;
- publication chronology has collection-ordering meaning for Article.

This WU does not perform full Page/Article product refinement beyond what is
required by this bounded Navigation/collection contract.

## Taxonomy exclusion

Webcore Article Collection has no Taxonomy dependency.

WU3 does not adopt:

- categories;
- tags;
- taxonomy filtering;
- taxonomy archives;
- taxonomy-aware collection routes;
- taxonomy ownership transfer.

Taxonomy-aware or filtered Article collections remain a later Bundled Module
extension concern.

## Article Collection extension boundary

The canonical Webcore baseline remains `/articles`.

Retained Bundled Modules may later extend collection delivery with separately
accepted capabilities such as:

- taxonomy-aware collections;
- filtered collections;
- alternate collection routes;
- richer archive behavior.

Such extension must not make a Bundled Module a prerequisite for the baseline
`/articles` route and must not silently replace Webcore ownership of the
zero-optional Article Collection.

The possibility of future extension is not authorization to create a generic
collection/provider framework in WU3.

## Zero-optional invariant

The WU3 result must remain operable with:

- Navigation Manager absent;
- Taxonomy absent;
- zero optional Modules;
- zero Themes.

Core Navigation and Core Content remain the baseline authorities.

## Navigation Manager extension boundary

Navigation Manager remains the retained extension for advanced capability,
including where separately supported:

- multiple independent menus;
- Theme navigation locations;
- assignment management;
- generic provider capability or richer provider browsing;
- advanced visibility;
- advanced target types beyond the four locked baseline classes;
- richer workspace conveniences;
- bulk actions;
- analytics;
- import/export.

The Manager may extend the baseline but must not replace, fork, or take over Core
Primary Navigation authority.

## Preserved exclusions

WU3 does not authorize:

- Redirect management;
- Content Manager refinement;
- Navigation Manager refinement;
- Taxonomy Manager refinement;
- Theme Manager refinement;
- broad Admin Shell redesign;
- generic provider framework;
- generic collection framework;
- destructive cleanup;
- Deferred Item adoption;
- production reconciliation;
- release, tag, package, publication, or external distribution.

## Validation requirements for later implementation

A separately authorized technical implementation must validate at minimum:

- canonical `primary` operator-facing behavior;
- Primary Navigation item add/edit/remove/reorder;
- five-level hierarchy boundary and invalid-placement rejection;
- Link targeting, including safe bare-domain, path, fragment, and supported-scheme resolution;
- individual published Page targeting;
- individual published Article targeting;
- safe non-resolution of draft, archived, missing, or stale Content targets;
- `/articles` delivery using published Articles only;
- newest-first publication chronology with deterministic ordering;
- valid empty Article Collection behavior;
- Article lifecycle-driven collection membership;
- Navigation resolution of the Article Collection target;
- Navigation Group creation, persistence, public retention, and bounded submenu interaction;
- operation with Navigation Manager and Taxonomy absent;
- permission, CSRF, validation, and sanitized-failure behavior;
- coexistence with retained Navigation Manager where enabled;
- directly impacted regression coverage;
- final diff and unintended-change review.

Subjective Admin usability and comprehension require human/product acceptance
only where materially necessary after a reviewable implementation exists.

## Contract verdict

WU3 preparation and source/scope reconciliation are complete enough to lock the
implementation boundary.

The accepted WU3 baseline is:

1. one canonical `primary` Navigation;
2. one Core Primary Navigation Admin workspace;
3. item CRUD, reorder, and bounded five-level hierarchy;
4. Link targets;
5. individual Webcore Content targets covering Page and Article;
6. one canonical Article Collection target at `/articles`;
7. Navigation Group structural targets with bounded public submenu behavior;
8. bounded Core Article Collection public delivery;
9. zero optional Module, Taxonomy, and Theme dependency;
10. preserved Navigation Manager extension boundary; and
11. no generic provider, collection, assignment, or Taxonomy framework expansion.

Technical implementation is separately authorized and in progress under the
locked four-target baseline; release, publication, and broader WU3 closure
remain separately controlled.

## Authority and provenance

Primary authority:

- `docs/49_webcore_product_completeness_stabilization_contract.md`;
- `docs/51_webcore_product_completeness_wu1_scope_reconciliation_contract.md`;
- this contract.

Supporting accepted architecture / historical evidence:

- `docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`;
- `docs/46_webcore_content_admin_baseline_contract.md`;
- `docs/22_m3_6_navigation_manager_contract.md`;
- current Core Navigation and Core Content source on the accepted repository
  baseline.

Thread-level saved concepts informed product composition but do not remain
implementation authority once their WU3-relevant decisions are represented by
this promoted contract. Unrelated saved concepts remain unreconciled until their
own disposition or later durable reconciliation.
