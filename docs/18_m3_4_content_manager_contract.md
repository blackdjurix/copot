# M3.4 Content Manager Contract

## Status

M3.4 Content Manager Batch 1 and Batch 2 Foundation: COMPLETE. The foundation implementation, archived-lifecycle correction, and narrow shared Admin 422 correction are committed and merged into `main`. Focused validation passes: provisioning/upgrade (9 assertions), transaction/lifecycle/slug/stale-write/Taxonomy atomicity (37 assertions), and authorization matrix (53 assertions), for 99 assertions total. PHP lint, `git diff --check`, disposable database cleanup, runtime synchronization, and focused browser validation pass, including archived restore, controlled HTTP 422 handling, public `/content/{slug}` rendering and restrictions, escaped plaintext, Taxonomy enabled/disabled behavior, and stale-write atomicity.

Git lifecycle: the foundation feature branch was fast-forward merged into `main`; `main` was pushed and verified against `origin/main`; local and remote foundation feature branches were deleted; `main` and `origin/main` are synchronized at `5b51a1471da63b280e1444cd2f7ba8da4d168f28`; only `main` remains locally; and the workspace is clean. Previous work-unit NRP: CONFIRMED.

Current work unit: M3.4 Content Manager Batch 6 implementation and validation are complete. Feature commit `79aee25d78dbe905ea0a6149ec5c07110375db04` (`feat(m3.4): redesign content admin workspace`) was fast-forward merged into `main` with no merge commit, and local/remote `main` were pushed and verified synchronized at that commit. The local and remote feature branches remain at the same commit; branch cleanup, final lifecycle documentation, and final local/remote verification remain pending. Batch 6 redesigned the Content Admin list and create/edit presentation, improved filters, empty states, human-readable type/status presentation, responsive rows, action hierarchy, and validation accessibility recovery. The final Admin navigation order is `Dashboard → Content → Taxonomy → Users → Roles → Modules → Settings`, implemented through minimal optional ordering metadata within the existing request-scoped navigation contract. Permission filtering, active state, module loading, routes, ownership, and domain behavior remain preserved. Focused automation, source review, targeted repository-to-runtime synchronization with SHA-256 verification, and authenticated browser validation passed; mobile Content action targets were remediated and browser-verified at approximately 46px. Browser limitations remain documented for permission-variant account switching, full reliable automated keyboard traversal, numeric contrast measurement, true 200% zoom measurement, initial-empty fixtures, and Taxonomy-unavailable runtime fixtures; these are not known implementation defects. Batch 6 remains `NRP CANDIDATE` until branch cleanup, final lifecycle documentation, and final local/remote verification are complete. Full M3.4 closure remains pending, and M3.R1 follows full M3.4 closure before M3.5.

Batch 6 form validation evidence includes a global error summary with valid field-specific associations. The browser-verified mobile remediation raised visible Content row-action targets to approximately 46px without changing action semantics or lifecycle behavior.

M3.4 is Content Manager. M3.7 remains Theme Manager. The existing `modules/content` module remains the sole Content owner; M3.4 evolves that module and does not create a replacement Content Manager module.

Batch 5 retains its historical closure and NRP record above. Batch 6 has been integrated into `main`, but its feature-branch lifecycle and NRP evaluation remain open. Release, tag, and publication remain separately authorized and have not started.

The six-batch structure below is the approved M3.4 preparation contract. Batch 6 follows Batch 5 and is required before full M3.4 closure. The reusable Admin Shell design-adjustment rules are defined in `docs/19_m3_admin_shell_design_adjustment_contract.md`. M3.R1 is a separate horizontal work unit after full M3.4 closure and before M3.5; it is not part of M3.4.

## Objective

Evolve the existing Content module into a focused Content Manager / Workspace that provides controlled content administration and editorial lifecycle behavior while preserving the existing module, Admin, Taxonomy, Theme, Webcore, installer, package, and public-rendering boundaries.

M3.4 must remain module-first. No unapproved Core ownership expansion is authorized.

## Ownership and Architecture Boundary

The existing Content module owns:

* content records and content lifecycle;
* Content Manager Admin routes, services, views, and module tests;
* content validation and slug policy;
* Content permissions and permission-aware navigation;
* Content-to-Taxonomy integration through the existing public Taxonomy contract.

Webcore continues to provide only existing public contracts for routing, authentication, permissions, CSRF, Admin URLs, Admin Shell rendering, database access, and sanitized error handling.

Themes continue to own presentation. Public Content rendering remains through the existing `content::show` Theme boundary. Content logic must not move into Webcore or Theme code.

## Locked Content Model

M3.4 retains the fixed content types:

* `page`;
* `article`.

M3.4 does not add:

* a content-type registry or content-type management UI;
* custom fields;
* reusable field-schema definitions;
* a new content storage model;
* a replacement Content module.

Content body storage and editing remain plaintext. Public rendering remains escaped plaintext through the existing `content::show` Theme boundary.

## Locked Lifecycle Contract

The approved state transitions are:

```text
draft -> published
published -> draft
draft -> archived
published -> archived
archived -> draft
```

Rules:

* New content defaults to `draft` unless an authorized user explicitly publishes it.
* Repeated or invalid transitions fail without mutation.
* Restore changes `archived` content to `draft`.
* There is no direct `archived -> published` transition.
* There is no hard delete.
* Archive sets `archived_at`.
* Restore clears `archived_at`.
* Publish refreshes `published_at` on every successful publish.
* `published_at` is not a scheduling contract.
* Drafting clears `published_at` and `archived_at`.
* Publishing clears `archived_at`.
* Archived content remains retained for Admin recovery to draft.

The lifecycle contract is defined by allowed transitions and persistence outcomes, not by current UI labels alone.

## Locked Permission and Authorship Contract

M3.4 adds the required runtime permission:

```text
content.read
```

Content listing and Content Admin navigation require both:

```text
admin.access
content.read
```

Action permissions remain separate:

* `content.create` — create content;
* `content.update` — edit content;
* `content.publish` — publish or move published content back to draft;
* `content.delete` — archive or restore content for compatibility with the existing permission name.

Permissions remain global. M3.4 does not add object-level, author-owned, team-owned, or “own content” authorization.

The creator becomes `author_id` at creation. Author reassignment UI is deferred. Updates do not change the author.

Fresh-install provisioning and an idempotent existing-install upgrade path are required for `content.read`. This requirement does not authorize schema or provisioning implementation in the preparation branch.

## Persistence and Integrity Contract

Content persistence must provide:

* centralized slug normalization and validation;
* database uniqueness as the authoritative uniqueness guarantee;
* controlled validation errors for write-time uniqueness failures;
* no automatic numeric slug suffix;
* stable slugs when only the title changes;
* optimistic stale-write protection without introducing revisions;
* explicit failure behavior with no partial Content mutation.

When Taxonomy assignments are submitted, Content and category/tag assignment changes must have one all-success-or-all-rollback outcome. Taxonomy errors must not be swallowed.

Content may use only the existing public Taxonomy contract and must not access private Taxonomy storage directly. Taxonomy remains optional, and Content must continue to function when Taxonomy is disabled.

The generic migration-system trigger is not currently declared reached. Cross-module transaction ownership and the permission upgrade design remain implementation risks to resolve in the relevant batch.

## Admin Content Workspace Contract

The M3.4 Admin workspace must provide:

* title/slug text search;
* type filtering;
* status filtering;
* deterministic default ordering: `updated_at DESC, id DESC`;
* pagination with a default page size of 25;
* bounded page size;
* preserved query parameters across list navigation;
* distinct initial-empty and no-filter-result states;
* configured Admin-path URLs through the existing `AdminUrl` contract;
* CSRF protection on every write;
* explicit permission checks on every management route;
* no bulk mutation.

The workspace must preserve the existing Admin Shell and sanitized error boundary. Batch 1–5 work did not authorize an Admin redesign or frontend framework. Batch 6 separately authorizes scoped Content workspace presentation refinement and Content-related Admin Shell navigation placement/order within the existing Admin navigation contract; it does not authorize a frontend framework or a shell-wide redesign.

## Public and Runtime Boundary

Only published Content is publicly available.

The existing public route remains:

```text
/content/{slug}
```

The existing Theme resolution contract remains:

```text
content::show
```

M3.4 does not add:

* Content APIs;
* public taxonomy archives;
* frontend Navigation integration;
* Theme Manager behavior;
* a Theme rendering redesign;
* Content logic in Webcore or Themes.

Batch 6 navigation work is limited to Content-related placement and ordering within the existing Admin Shell navigation contract. It is not Navigation Manager implementation or frontend Navigation integration. Batch 6 is not Theme Manager behavior or a frontend Theme rendering redesign.

No Webcore change is approved without concrete defect evidence and the existing Core-change escalation review.

## Required End-to-End Fixtures

Validation must use records built from the existing `page` and `article` types:

* draft page;
* published article;
* archived article;
* duplicate-slug candidate;
* content with Taxonomy assignments;
* content without Taxonomy;
* stale-update scenario.

The fixture set must remain records using existing types. It must not introduce a fixture-only content type or imply that a validation fixture is a product capability.

## Explicitly Deferred

The following remain outside strict M3.4 scope:

* hard delete;
* revisions;
* autosave;
* preview;
* scheduling and scheduler/worker behavior;
* workflow approval;
* author reassignment;
* object-level permissions;
* raw HTML, rich text, Markdown, sanitizer, or editor abstraction implementation;
* custom fields;
* content-type registry or management UI;
* Media Library and featured images;
* bulk actions;
* import/export;
* comments;
* APIs;
* multilingual content;
* SEO;
* public taxonomy archives;
* frontend Navigation integration;
* generic migration runner;
* Theme Manager or rendering redesign.

## Six-Batch Plan

### Batch 1 — Contract, Permission, and Baseline

Objective: lock the Content Manager ownership, lifecycle, permissions, authorship, persistence, Taxonomy atomicity, workspace, public-rendering, fixture, and Core/schema boundaries.

Ownership: Content module, contract documentation, permission/migration decision, and focused baseline tests.

Acceptance boundary: the contract is internally consistent, `content.read` provisioning requirements are explicit, and no implementation or schema change is implied by documentation alone.

Focused validation: contract assertions, source-boundary checks, permission matrix baseline, and fixture-definition checks.

Approval gates: user approval of lifecycle, permission, authorship, Taxonomy transaction ownership, stale-write policy, schema/migration implications, and any Core touchpoint.

Documentation/runtime impact: create the authoritative M3.4 contract and synchronize only the roadmap/current-state reference. No runtime behavior changes.

### Batch 2 — Domain and Persistence

Objective: implement the module-local Content domain and persistence boundary for validation, transitions, slugs, stale writes, authorship, and aggregate failure behavior.

Ownership: `modules/content/Services`, repository boundary, validators, and module-local persistence orchestration.

Acceptance boundary: all approved transitions and invalid-transition failures are deterministic; uniqueness failures are controlled; title-only edits preserve slugs; stale writes cannot silently overwrite newer content; Content plus submitted Taxonomy changes are all-success-or-all-rollback.

Focused validation: domain, repository, transition, uniqueness, stale-write, transaction, and Taxonomy-enabled/disabled tests.

Approval gates: any new table, column, index, upgrade artifact, migration-system decision, or Core change.

Documentation/runtime impact: update the contract only if approved implementation evidence changes a stated boundary; record schema and migration decisions before implementation.

### Batch 3 — Admin Content Workspace

Objective: provide the Content Manager Admin workspace over the approved domain contract.

Ownership: `modules/content/routes.php`, module-local Admin services/views, navigation, and configured-path integration.

Acceptance boundary: read authorization, search, type/status filters, deterministic ordering, bounded pagination, preserved query parameters, empty/no-result states, create/edit, publish/draft, archive/restore, CSRF, and sanitized errors operate through the existing Admin Shell.

Focused validation: route, permission, CSRF, query/filter/pagination, form, presentation, and configured Admin-path tests.

Approval gates: any new permission beyond the approved matrix, bulk action, ownership rule, preview path, or Admin/Core contract change.

Documentation/runtime impact: preserve existing Admin patterns and document only approved behavior. Browser validation is required later but is not part of preparation.

### Batch 4 — Integration and Security Hardening

Objective: prove cross-module integrity and harden authorization, validation, stale-write, taxonomy, and public-exposure boundaries.

Ownership: Content module with focused Taxonomy, Users & Access, Theme, Webcore error-boundary, installer, and package compatibility coverage.

Acceptance boundary: unauthorized reads/writes, malformed data, slug conflicts, stale writes, invalid transitions, Taxonomy failures, CSRF failures, unpublished-content exposure, and raw-error leakage are contained without partial mutation.

Focused validation: security, integration, failure, sanitized-error, package, clean-install, fixture, and public-route regression tests.

Approval gates: schema/upgrade implementation, permission provisioning implementation, migration-system trigger, or Core-change escalation.

Documentation/runtime impact: record verified implementation, automated, synchronization, and browser evidence with explicit browser connector limitations; do not claim full runtime acceptance or Git lifecycle closure until the separate Batch 5 and Git gates pass.

### Batch 5 — Runtime Acceptance and Closure

Objective: complete focused regression, runtime/manual acceptance, documentation closure, and completion review.

Ownership: Content module, tests, documentation, and user-owned Git integration workflow.

Acceptance boundary: Batches 1–5 pass; approved Content Admin and public rendering behavior is validated; fixture scenarios pass; package and fresh-install implications are checked; no unresolved scope, schema, security, or Core blocker remains within the Batch 5 boundary. Batch 6 is a subsequent approved M3.4 work unit and is not required to validate Batch 5 evidence.

Focused validation: unified focused Content suite plus required existing Admin, Taxonomy, Theme rendering, hardening, package, and clean-install regressions.

Approval gates: manual/browser acceptance, documentation closure, Git integration, branch cleanup, and post-cleanup verification are complete; this final documentation correction, its commit/push, final verification, and NRP confirmation remain separate user approvals. Release, tag, and publication remain outstanding.

Documentation/runtime impact: record Batch 5 validation and documentation completion without claiming full M3.4 closure. Full M3.4 contract and roadmap closure remain deferred until Batch 6 branch lifecycle closure, final verification, and NRP evaluation pass.

### Batch 6 — Admin Content Workspace Redesign and Admin Shell Navigation Ordering

Objective: refine the Content Manager Admin workspace and the Content-related placement/order of existing Admin Shell navigation while preserving the approved Content runtime contract and current shell identity.

Ownership: Content module presentation, applicable Admin Shell presentation, tests, documentation, Product Designer-supported review, and the user-owned Git integration workflow.

Scope: desktop and responsive Content workspace hierarchy; search and filters; listing readability; status presentation; forms; contextual actions; empty states; accessibility; and Content-related Admin Shell navigation placement and ordering within the existing request-scoped Admin navigation contract.

Design authority: project and M3.4 contracts, committed implementation and tests, approved Admin Shell design authority, Product Designer recommendation as design support, and approved implementation. The reusable review rules are defined in `docs/19_m3_admin_shell_design_adjustment_contract.md`.

Acceptance: the Content workspace is readable and usable at approved desktop and mobile widths; existing Content routes, permissions, CSRF, lifecycle, Taxonomy compatibility, sanitized errors, and deferred boundaries remain intact; valid contextual actions and empty states are presented; Content-related navigation placement/order is consistent with the approved shell; focused regressions and required browser, responsive, accessibility, lint, synchronization, and documentation checks pass.

Out of scope: M3.R1; Navigation Manager; Theme Manager; frontend Theme rendering; Content domain, permission, route, schema, lifecycle, or Core changes unless a concrete in-scope defect is found; new Content capabilities; and release, tag, publication, or unrelated Git work.

Branch: `feature/m3.4-content-manager-batch-6` when separately authorized.

Approval gates: design review, implementation review, focused validation, browser acceptance, documentation, branch review, staging, commit, push, merge, branch cleanup, release, tag, and publication remain separate approvals.

Documentation/runtime impact: Batch 6 has its own documentation, validation evidence, branch lifecycle, and independent NRP evaluation. Batch 6 follows Batch 5 and is required before full M3.4 closure. It does not invalidate Batch 5.

## Core, Schema, and Migration Gate

The Webcore freeze remains active. Content requirements must first be solved in the Content module using existing public services and contracts.

The following are not authorized by this document:

* Core changes, except the explicitly approved narrow `AdminErrorRenderer` HTTP 422 supported-status correction for controlled Content validation responses;
* schema changes;
* permission provisioning implementation;
* upgrade SQL implementation;
* generic migration-runner implementation;
* dependency additions;
* runtime or package changes.

`content.read` provisioning is a required M3.4 decision and acceptance boundary. Fresh-install provisioning and the idempotent existing-install upgrade are implemented and validated; the runtime database contains exactly one `content.read` permission and one Administrator mapping after two upgrade passes. Remaining work belongs to later M3.4 batches and final milestone closure rather than permission provisioning.

## Completion Boundary

M3.4 preparation is complete when this contract is reviewed and approved. M3.4 implementation is complete only after Batches 1–6, focused validation, required runtime/browser acceptance, documentation closure, and separate Git integration approvals pass. Batch 5 retains an independent closure and NRP lifecycle; Batch 6 is required before full M3.4 closure. M3.R1 follows full M3.4 closure and must complete before M3.5 begins.
