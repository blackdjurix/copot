# M3.5 Taxonomy Manager Contract

## Purpose and Status

M3.5 evolves the existing `modules/taxonomy` module into a focused Taxonomy
Manager. It manages the existing built-in `category` and `tag` types and does
not create a replacement module, generic taxonomy platform, or new Core
abstraction.

This is the M3.5 preparation contract and scope lock. It records accepted
product direction, ownership, invariants, preservation boundaries, probable
work units, validation strategy, and lifecycle gates. It does not authorize
implementation, branch creation, Git integration, release, tag, or publication.

Product scope is accepted. The dedicated preparation contract is committed to
`main` at `1e6c837340b0ea561870b7fe729791edcc0aa9f5`
(`docs(m3.5): lock taxonomy manager preparation contract`), and local and
remote `main` are synchronized at `0/0`. The five implementation work units
are locked at responsibility level. Branch creation is not authorized and
implementation has not started. Preparation is ready for final NRP evaluation;
full M3.5 is `NRP NOT REACHED`.

## Milestone Position

```text
M3.4 Content Manager (NRP CONFIRMED)
->
M3.R1 Admin Shell Retouch 1 (NRP CONFIRMED)
->
M3.5 Taxonomy Manager
->
M3.6 Navigation Manager
```

M3.5 evolves the existing Taxonomy module. M3.6 may later consume proven
Content and Taxonomy target contracts, but M3.5 must not implement Navigation
Manager.

The roadmap's five-batch envelope is locked at responsibility level by this
contract. Exact implementation file scope and internal task decomposition
remain just-in-time decisions within each approved work unit.

## Authority and Ownership

Authority is applied in this order:

1. project instructions and this accepted M3.5 contract;
2. `docs/16_m3_core_freeze_and_module_contract.md`;
3. `docs/07_taxonomy_system.md` as clarified by this scope lock;
4. committed Taxonomy, Content, Core, schema, and test behavior;
5. `docs/19_m3_admin_shell_design_adjustment_contract.md` for presentation;
6. approved implementation decisions.

The `modules/taxonomy` module owns routes, permissions, services, repositories,
schema and any narrowly required upgrade artifact, Admin views, assets, tests,
fixtures, category hierarchy, and term lifecycle behavior.

Core remains maintenance-only. M3.5 must use existing public Core contracts and
the existing Admin Shell. A generic migration framework, new navigation model,
or Core taxonomy behavior is outside this contract.

## Product Scope

Only these built-in types are managed:

* `category`;
* `tag`.

Taxonomy type CRUD, arbitrary custom taxonomies, and replacement modules are
excluded.

Category terms become hierarchically manageable using the existing storage
model where sufficient. A category may have a parent category. The hierarchy
must be clear in Admin without drag-and-drop.

Tags remain flat: `parent_id` is always `null`, no parent control is rendered,
and existing flat assignment behavior remains compatible.

M3.5 may provide clearer term listing, category hierarchy presentation, safe
create/edit/delete, usage counts, validation and recovery, empty/unavailable/
restricted states, permission-aware actions, and responsive/accessibility
treatment. Simple term search and filtering are:

```text
DEFERRED — NOT PART OF THE BASELINE M3.5 IMPLEMENTATION
```

## Domain Invariants

* `category` and `tag` are the only managed types.
* Type CRUD is not implemented.
* Category is hierarchical; tag is flat.
* Parent and child must belong to the same taxonomy type.
* A tag cannot receive a parent, including through malformed requests.
* A category parent must exist and belong to `category`.
* A category cannot be its own parent.
* A category cannot select a descendant as its parent.
* Malformed, missing, stale, or wrong-type identifiers fail safely.
* Invalid parent input must not partially persist an update.

### Cycle prevention

Cycle detection is a Taxonomy service/repository responsibility, not a UI-only
check. Before creating or updating a category parent, the service must walk the
persisted ancestor chain with a visited-ID set and bounded failure-safe
condition. It rejects when the current term is encountered, an existing cycle
is detected, or an ancestor cannot be resolved consistently.

The check must run against current persisted data inside the same transaction
boundary as the write. A UI selector is advisory only.

### Deletion safety

Deletion fails closed when a term is assigned to any entity, a category has
children, the term is both assigned and has children, or the target is missing,
malformed, stale, or the wrong type. Existing unused-term protection remains.
M3.5 adds child protection for categories; it does not silently reparent,
cascade-delete, or orphan children.

### Stale writes

Create and edit flows preserve controlled validation and PRG patterns. If stale
write protection is required by the implementation path, it must fail without
overwriting newer term or assignment data. No generic concurrency framework is
authorized by this contract.

## Routes and Behavior Boundaries

The existing configured Admin-path ownership remains in `modules/taxonomy`.
The existing route family is preserved:

```text
/{admin_path}/taxonomy
/{admin_path}/taxonomy/category
/{admin_path}/taxonomy/tag
/{admin_path}/taxonomy/category/create
/{admin_path}/taxonomy/tag/create
/{admin_path}/taxonomy/{type}/{term_id}/edit
/{admin_path}/taxonomy/{type}/{term_id}
/{admin_path}/taxonomy/{type}/{term_id}/delete
```

M3.5 may refine orchestration within this family. It must not introduce public
taxonomy routes, archives, APIs, or a second route owner. Configured Admin paths
must continue working without literal `/admin` dependencies.

## Permissions and Security

Existing permissions remain sufficient and unchanged:

```text
taxonomy.create
taxonomy.update
taxonomy.delete
```

All protected requests require `admin.access` plus the applicable Taxonomy
permission. Mutation handling must preserve or prove authorization-before-CSRF
ordering for unauthorized requests, CSRF on every mutation, positive identifier
parsing, validation-before-write, safe escaping, sanitized persistence errors,
and truthful controlled `403`, `404`, `409`, `419`, `422`, and unavailable
responses.

No permission is added for conceptual neatness.

## Persistence and Upgrade Strategy

The existing schema provides the relevant storage:

* `taxonomy_types.is_hierarchical`;
* `taxonomy_terms.parent_id`;
* `(taxonomy_type_id, parent_id)` indexing;
* parent foreign-key containment;
* generic `taxonomy_assignments` ownership and indexing.

No schema expansion is authorized by this contract. The preferred strategy is
to enforce category hierarchy and tag flatness in Taxonomy services and
repositories using existing columns and constraints.

Before implementation, clean-install and existing-install checks must confirm
these columns, indexes, seeded types, and constraints. If an existing supported
installation cannot reliably provide them, implementation stops for a narrowly
scoped upgrade/provisioning decision. A generic migration runner must not be
invented.

Fixtures must distinguish clean install, existing install, category parents and
descendants, tags, assignments, stale identifiers, and deletion cases.

## Content Compatibility

Content remains the owner of Content workflows. M3.5 preserves category and tag
assignments, separate category/tag synchronization, generic
`entity_type = content` ownership, atomic assignment and rollback guarantees,
Content operation when Taxonomy is disabled, and current Content routes, forms,
permissions, and lifecycle behavior.

M3.5 may harden compatibility tests and the Taxonomy assignment boundary. It
must not redesign Content Manager forms, move Content ownership, or introduce
unapproved taxonomy behavior into Core or Content.

## Admin Surfaces

The smallest required surfaces are:

1. Taxonomy landing page showing fixed `category` and `tag` types;
2. category term list with hierarchy, usage, and permission-aware actions;
3. category create form with an accessible parent selector;
4. category edit form excluding self and descendant choices;
5. tag term list without hierarchy controls;
6. tag create/edit forms without hierarchy controls;
7. delete, assigned, and has-children safety states;
8. empty, unavailable, not-found, validation, and restricted states.

The preferred hierarchy presentation is a server-rendered indented ordered list
or table with an explicit depth or parent indicator. It must preserve semantic
structure, readable scanning, and keyboard access. Drag-and-drop is excluded.

## Admin Shell Checkpoint

The reusable Admin Shell adjustment contract applies only to Taxonomy surfaces:

* page hierarchy and supporting copy;
* list density and hierarchy clarity;
* action hierarchy;
* parent-selection clarity;
* usage and safety presentation;
* empty, unavailable, and validation states;
* 1440×900 desktop, 390×844 mobile, and 320×800 narrow mobile;
* keyboard operation, visible focus, labels, error associations, touch targets,
  and practical 200% zoom.

This checkpoint must not reopen M3.R1 or alter unrelated Admin surfaces.

## Probable Work Units

The roadmap's five-unit envelope is provisionally justified by separate domain,
security, presentation, and closure risks. The exact breakdown remains subject
to approval.

### Work Unit 1 — Provisioning, Baseline Fixtures, and Focused Compatibility Evidence

Verify clean/existing schema support and define baseline fixtures and focused
compatibility evidence for fixed types, trees, assignments, stale identifiers,
and deletion states. The preparation contract belongs to the documentation-only
phase and must be accepted and integrated before an implementation branch exists.

Probable files: focused Taxonomy compatibility tests and fixtures, with
`database/schema.sql` only if a verified provisioning defect is found, and
documentation updates only where evidence requires them. Validation is schema,
clean-install, upgrade, fixture, and focused compatibility review. Stop on
unresolved schema or compatibility.

### Work Unit 2 — Domain Hierarchy and Transaction Safety

Enforce category parenting, cycle prevention, tag flatness, child-safe deletion,
and atomic writes in Taxonomy services/repositories.

Probable files: `modules/taxonomy/Services/TaxonomyRepository.php`,
`TaxonomyAssignmentRepository.php`, type/term models, and focused domain tests.
Validate parent existence, same-type rules, self/descendant rejection, cycles,
stale IDs, tag flatness, deletion safety, rollback, and Content assignments.
Stop on partial writes, unsafe deletion, or assignment regression.

### Work Unit 3 — Authorization and Route Orchestration

Apply the invariant boundary to existing configured-path routes and permission-
aware mutations.

Probable files: `modules/taxonomy/routes.php`, `module.json`, and focused
security tests. Validate the permission matrix, authorization-before-CSRF,
CSRF, identifiers, wrong-type/missing targets, controlled failures, configured
paths, and escaping. Stop on disclosure, route drift, or ownership leakage.

### Work Unit 4 — Admin Workspace and Presentation

Add accessible category hierarchy and tag-flat surfaces using existing Admin
patterns.

Probable files: `modules/taxonomy/views/admin/types.php`, `terms.php`,
`form.php`, shared CSS only if an approved gap is proven, and presentation
tests. Validate semantic hierarchy, selectors, action visibility, states,
desktop/mobile/narrow-mobile, keyboard/focus, labels, touch targets, and zoom.
Stop on inaccessible controls or unrelated shell expansion.

### Work Unit 5 — Integration, Hardening, Documentation, and Closure

Prove Content compatibility, installation/package compatibility where affected,
focused regressions, documentation, and lifecycle closure.

Probable files: focused tests and authorized documentation. Validate assignment
regressions, security, package/clean install where relevant, PHP lint, runtime
hashes only if synchronization occurs, authenticated browser evidence,
accessibility, `git diff --check`, final diff, containment, and clean state.
Stop on any unresolved blocker or incomplete evidence.

## Validation Contract

Validation is proportional to the approved implementation and includes PHP
lint; schema/provisioning or upgrade checks if persistence is affected; focused
hierarchy, cycle, cross-type, stale-ID, flat-tag, and deletion tests; permission,
authorization-before-CSRF, CSRF, validation, escaping, and controlled-failure
tests; Content assignment and disabled-Taxonomy regressions; Admin presentation
tests; package/clean-install checks when affected; source-to-runtime hashes only
if synchronization occurs; authenticated browser validation for changed Admin
flows; responsive/accessibility review; and final documentation, Git, branch,
containment, and workspace verification.

No broad unrelated suite is required without a concrete regression reason.

## Branch and NRP Lifecycle

Preparation approval is required before branch creation. The provisional branch
is `feature/m3.5-taxonomy-manager`, based on the accepted synchronized `main`
preparation anchor, merged to `main`, and deleted locally/remotely only after
verified containment and closure. No branch exists or is authorized during this
preparation task.

Preparation NRP and full M3.5 NRP are separate. Preparation NRP requires this
contract, scope approval, dependency review, exact work-unit approval, and an
accepted validation plan. Full M3.5 NRP additionally requires approved
implementation, focused validation, applicable runtime/browser evidence,
documentation, Git integration, clean synchronization, branch cleanup, and
final verification.

This task does not claim preparation or full M3.5 `NRP CONFIRMED`. Release,
tag, and publication remain separately authorized.

## Exclusions and Completion Boundary

Excluded: taxonomy type CRUD; arbitrary custom taxonomies; drag-and-drop;
public URLs and archives; APIs; import/export; custom fields; icons/media;
search indexing; Navigation Manager; M3.R1 reopening; unrelated Admin redesign;
speculative Core refactors; generic migration infrastructure; release; tag; and
publication. Simple term search and filtering are `DEFERRED — NOT PART OF THE
BASELINE M3.5 IMPLEMENTATION`.

Preparation is complete only after this contract and exact work-unit breakdown
are approved. M3.5 completion requires approved work units, focused domain,
security, integration, and presentation validation, applicable runtime/browser
evidence, documentation closure, user-owned Git integration, branch closure,
and independent NRP evaluation.
