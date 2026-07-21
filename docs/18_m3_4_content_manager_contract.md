# M3.4 Content Manager Contract

## Status

M3.4 Content Manager Batch 1 and Batch 2 Foundation: COMPLETE. The foundation implementation, archived-lifecycle correction, and narrow shared Admin 422 correction are committed and merged into `main`. Focused validation passes: provisioning/upgrade (9 assertions), transaction/lifecycle/slug/stale-write/Taxonomy atomicity (37 assertions), and authorization matrix (53 assertions), for 99 assertions total. PHP lint, `git diff --check`, disposable database cleanup, runtime synchronization, and focused browser validation pass, including archived restore, controlled HTTP 422 handling, public `/content/{slug}` rendering and restrictions, escaped plaintext, Taxonomy enabled/disabled behavior, and stale-write atomicity.

Git lifecycle: the foundation feature branch was fast-forward merged into `main`; `main` was pushed and verified against `origin/main`; local and remote foundation feature branches were deleted; `main` and `origin/main` are synchronized at `5b51a1471da63b280e1444cd2f7ba8da4d168f28`; only `main` remains locally; and the workspace is clean. Previous work-unit NRP: CONFIRMED.

Current work unit: M3.4 Content Manager Batch 4 — Cross-module Integration and Security Hardening. Implementation and validation are complete. Commit `48c1ca12ada0fe813b8efc1f4e8e0b9d52c03ccc` (`feat(m3.4): harden content manager batch 4`) was fast-forward merged into local `main`; the main push was previously completed and freshly re-verified. Local and remote `main` and feature refs match this commit, and the local feature branch remains present and fully contained pending cleanup. The post-merge documentation correction is recorded by this change; final verification remains outstanding. Focused Batch 4 validation passes 33 assertions. Directly affected Content regressions pass: Batch 1 provisioning (9), Batch 1 transaction/lifecycle (37), Batch 2 authorization (53), and Batch 3 workspace (33). Package builder smoke passes 825 assertions and clean-install verification passes 60 assertions. PHP lint, `git diff --check`, source review, and targeted source-to-runtime synchronization pass. Browser validation passes with limitations: normal lifecycle, published rendering, Draft/Archived denial, plaintext escaping, malformed read-ID containment, current configured Admin path, and desktop/390 × 844 smoke pass. Browser request replay was unavailable for missing/invalid CSRF, authorization-before-CSRF, malformed mutation payloads/identifiers, duplicate slug, stale write, repeated transitions, and injected persistence-error responses; focused automated tests and source review cover those cases. Optional Taxonomy-disabled browser behavior was not exercised. No active Batch 4 implementation blocker exists. Batch 4 is an NRP CANDIDATE, not NRP CONFIRMED. Batch 5 and full M3.4 completion remain outstanding. Release, tag, and publication have not started.

M3.4 is Content Manager. M3.7 remains Theme Manager. The existing `modules/content` module remains the sole Content owner; M3.4 evolves that module and does not create a replacement Content Manager module.

The exact five-batch structure below is the approved M3.4 preparation contract. It refines the Stage 2 six-batch planning envelope using current repository evidence without changing the approved M3 sequence.

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

The workspace must preserve the existing Admin Shell and sanitized error boundary. No Admin redesign or frontend framework is authorized.

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

## Five-Batch Plan

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

Acceptance boundary: all five batches pass; approved Content Admin and public rendering behavior is validated; fixture scenarios pass; package and fresh-install implications are checked; no unresolved scope, schema, security, or Core blocker remains.

Focused validation: unified focused Content suite plus required existing Admin, Taxonomy, Theme rendering, hardening, package, and clean-install regressions.

Approval gates: manual/browser acceptance, documentation closure, branch review, staging, commit, push, merge, release, and publication remain separate user approvals.

Documentation/runtime impact: close the M3.4 contract and roadmap state only after implementation and validation evidence exists. This preparation document does not claim completion.

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

M3.4 preparation is complete when this contract is reviewed and approved. M3.4 implementation is complete only after all five batches, focused validation, required runtime/browser acceptance, documentation closure, and separate Git integration approvals pass.
