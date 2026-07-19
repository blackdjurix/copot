# Changelog

## Unreleased

### Added

- Added Shell Foundation on `feature/admin-shell-foundation`, including the shared responsive Admin shell, mobile off-canvas drawer with scroll locking and focus recovery, accessible navigation and menus, keyboard behavior, and configured Admin-path presentation.
- Added the Settings Category 1 presentation remediation with ordered tabs, automatic keyboard activation, truthful Security/Email/Maintenance empty states, standalone Branding presentation, safe empty asset states, and preserved full Settings save and Logo/Favicon workflows.
- Added local-only session lifetime behavior: `APP_ENV=local` uses a 30-day cookie and server-side session lifetime, while non-local environments retain the 120-minute default. Focused automated validation, source/runtime equality, runtime checks, and authenticated browser acceptance passed.

- Added the M3.3 Module Manager implementation through Batches 1–5 on `feature/m3.3-module-manager`, including lifecycle, security, package, clean-install, Admin verification, and focused review evidence; the milestone was fast-forward merged into `main` at `020f2b2`, with its local and remote feature branches deleted.
- Added the M3.2 Batch 1 Settings Manager module boundary with lifecycle-owned Admin Settings routes, navigation, fixed compatibility presentation, and Site Asset controls.
- Added deterministic registered-only Settings definition discovery through the existing `SettingsService`, plus 42 focused contract and integration assertions.
- Added M3.2 Batch 2 module-local manager policy plus reusable deterministic section and typed scalar field contracts, with registered-only editability and generic JSON/Site Asset descriptor exclusion.
- Added aggregated validation and atomic multi-field save orchestration with optional-field omission, validation-before-write, root transactions, caller-safe nested savepoints, and prior-value preservation on failure.
- Added M3.2 Batch 3 grouped dynamic Admin Settings presentation for text, number, checkbox, and select fields derived from the manager domain contract.
- Added nested `settings[domain.identifier]` request handling delegated to `SettingsManager`, with inline/form validation redisplay, controlled invalid-select preservation, collision-safe field IDs, and controlled Admin-shell `503` handling.
- Added M3.2 Batch 4 tests-only security and compatibility suites covering the `admin.access` plus `settings.update` permission matrix, authorization-before-CSRF/lookup/mutation ordering, request-sensitive missing/invalid CSRF containment, and unknown, uneditable, secret-like, Logo, and Favicon scalar rejection.
- Added route-level mid-write storage-failure coverage proving rollback, prior-value preservation, transaction closure, sanitized Admin-shell `503` responses, and absence of raw PDO, SQLSTATE, exception, stack-trace, or credential-like detail.
- Added M3.2 Batch 5 completion evidence: final automated validation, runtime prerequisite checks, manual and automated-assisted Admin verification, and documentation closure.
- Added the Post-M3.1 Roadmap Sync and M3.2 Settings Manager preparation contract, including scope, batches, permission reuse, Core approval points, and manual verification gates.
- Locked the planned Database Upgrade / Migration System concept and the post-M3.3 Admin UX Refinement 1 checkpoint without starting either implementation.

- Added M3.1 Users & Access administrator workflows for Users, Roles, user-role assignments, role-permission assignments, active/inactive status, and administrator-managed passwords using the existing authorization model.
- Added nine M3.1 runtime permissions, canonical fresh-install provisioning, and the controlled idempotent existing-installation upgrade artifact.
- Added configured-Admin-path, CSRF, escaping, compatibility, administrator-invariant, and access-denied logout recovery coverage.

- Added the M3 Prep Stage 3 Final Review + Entry Audit remediation contract, including authoritative-document cleanup targets, M3.1 Users & Access scope boundaries, Core touchpoint rules, test strategy, branch strategy, entry criteria, and acceptance criteria.
- Added the approved M3.1-M3.11 sequencing lock with milestone dependency rationale, planning batch envelopes, risk levels, just-in-time batch locking, parallelization guardrails, and explicit sequence change control.
- Added the Pre-M3 Core Freeze and Module Contract to lock post-v0.12.0 Webcore maintenance rules, module ownership boundaries, dependency direction, Navigation/Media architecture decisions, repository strategy, and M3 entry criteria.
- Added `Copot\Core\Version` as the single source of truth for the framework release version and installer marker version.
- Added `INSTALL.md` and `docs/15_distribution_and_packaging.md` to define fresh-install requirements, environment responsibilities, package boundaries, and clean-install acceptance.
- Added a focused Post-M2 distribution cleanup smoke test for version, environment, ignore, and distribution-contract guards.
- Added a deterministic CLI package builder that produces `dist/copot-v0.12.0.zip` from `Copot\Core\Version::CURRENT`.
- Added an explicit build-time package manifest for release package include/exclude policy.
- Added package-content smoke coverage for required runtime/install files, forbidden source-only and local-only files, `.env` exclusion, runtime-state exclusion, and package self-exclusion.
- Added package-based clean-install verification using an isolated extracted artifact, a dedicated guarded D4 test database, installed-version validation, and minimal public, Admin, Settings, and Site Asset behavior checks.

- Added the M2.4 Platform Hardening Batch 1 repository audit, architecture, scope lock, error taxonomy, sanitized rendering policy, Admin in-shell error rule, logging/redaction contract, storage/filesystem boundary, runtime/deployment checklist, batch plan, acceptance criteria, and risk register.
- Added request-scoped `Diagnostics` with append-locked local JSON-line records, opaque error references returned only after successful writes, controlled summaries, project-relative source locations, and fixed allowlisted context.
- Added focused M2.4 Batch 2 diagnostics coverage for formatting, uniqueness, redaction, invalid events, unavailable sinks, symlink/unwritable behavior, Application ownership, scope guards, and repository-log isolation.
- Added sanitized pre-autoload emergency, post-autoload bootstrap, and request-scoped dispatch failure boundaries with optional Diagnostics references.
- Added standalone `ServerErrorResponse`, exact owned-buffer cleanup, partial-output rejection, and focused Batch 3 failure-path coverage.
- Added shared Admin in-shell error rendering and focused Batch 4 failure-path coverage.
- Added environment-configurable Secure session cookies and observable site-asset read/cleanup degradation with focused Batch 5 runtime/storage coverage.
- Added the unified M2.4 regression gate covering focused Batches 2–5 plus the complete chained M2.3 → M2.2 → M2.1 regression path.
- Added Git ignore coverage for runtime `storage/site-assets/` output created by Site Asset upload and manual verification flows.

### Deferred validation

- Deferred non-material browser checks are limited to local Logo/Favicon file-chooser preview, exact cookie `Expires`/`Max-Age` metadata, a safe server-side validation-error interaction, and exact DOM persistence observation after cancelling a `beforeunload` dialog. These are validation-surface limitations, not known implementation failures.

### Changed

- Completed Admin UX Refinement 1 presentation work on `feature/admin-ux-refinement-1`: compact Module Manager list/detail presentation, Role-aligned User and Role Detail layouts, compact role and permission controls, and full-width technical metadata alignment. At that historical checkpoint, focused integration evidence, syntax and source review, source/runtime equality, runtime smoke, and authenticated browser acceptance passed; Shell Foundation had not yet started. Existing routes, backend, Core, permission, CSRF, lifecycle, and persistence contracts were unchanged.
- Integrated the completed Admin UX Refinement 1 and Shell Foundation work into `main` in parent-first order with `--ff-only` at `69fda0d`; both feature branches remain present pending separate cleanup authorization, and no PR was used.
- Completed M3.3 Batch 5 validation and manual Admin verification on the feature branch. Baseline automated validation passes 816 assertions; patch-focused reruns pass 130 assertions; cumulative executed evidence is 946 assertions with overlap and is not a unique full-suite total.
- Corrected Module Manager Admin presentation so stable denial codes remain internal and known codes render as human-readable messages with a controlled fallback. Lifecycle, authorization, CSRF, database, filesystem, and policy contracts are unchanged.
- Preserved the M3.3 boundaries: the `InstallerFinalizer::BASELINE_MODULES` addition remains the sole approved Core touchpoint; no additional Core change, package publication, release, or tag is included by this closure synchronization.
- Recorded the Post-M3 transition to Admin UX Refinement 1 after M3.3 and before reserved M3.4 Content Manager. The approved Copot Admin Shell image is the canonical visual authority, and the latest UI Refinement Plan is the external scope and implementation authority; neither source authorizes new backend or Core behavior. Authenticated Public Toolbar remains Theme-owned future scope and is excluded from Webcore and Admin UX Refinement 1.
- Completed M3.2 Batch 3 Admin Routes and Presentation while preserving configured Admin paths, `admin.access`, `settings.update`, CSRF, PRG, the Admin shell, and specialized Logo/Favicon workflows.
- Completed M3.2 Batch 4 Security and Compatibility Hardening as tests-only work without runtime, Core, schema, SQL, permission, manifest, dependency, route, view, or production behavior changes; at that historical checkpoint, M3.2 remained in progress and Batch 5 had not started.
- Completed M3.2 Batch 5 Completion and Manual Verification on its feature branch as the historical completion step, then merged the complete, validated Settings Manager milestone to `main` through `afd82f0`. Batch 5 introduced no production/runtime, schema, or Core change; M3.3 implementation and validation subsequently advanced through Batches 1–5 and were fast-forward merged into `main` at `020f2b2`, with documentation synchronization, final focused review, user-owned commit, push, and merge-readiness assessment complete.
- Preserved the singular Settings domain and excluded generic JSON plus Logo/Favicon descriptors from dynamic scalar rendering without Core, schema, permission, dependency, or Batch 2 domain changes.
- Moved the existing fixed Admin Settings route/view ownership from Core to `settings-manager` without changing URLs, `settings.update`, CSRF, validation-before-write, transactions, Admin shell errors, or Logo/Favicon behavior.
- Added `settings-manager` to fresh-install baseline module enablement and the release package; existing installations continue to use explicit ModuleManager install/enable lifecycle operations.
- Recorded M3.1 Users & Access as merged to `main` through `5c4cf8c` and activated M3.2 preparation without starting Batch 1 runtime work.

- Completed all five M3.1 batches on the milestone branch without activating M3.2 or creating a new release.
- Preserved standalone `403` denial for authenticated users without `admin.access` while adding a CSRF-protected POST Sign out recovery action; guest standalone errors remain unchanged.
- Isolated the Batch 3 final-administrator integration fixture from existing active administrator-capable database users without changing runtime invariant semantics.

- Advanced M3 Prep to Stage 3 Final Review + Entry Audit after completing the Stage 2 sequencing lock, without starting M3 runtime implementation.
- Advanced M3 Prep to Stage 2 M3 Sequencing Lock and confirmed Users & Access as the M3.1 entry target without starting runtime implementation.
- Started M3 Prep Stage 1 Governance + Architecture Lock without starting M3 runtime implementation.
- Marked v0.12.0 as the completed M2 Webcore baseline and moved Webcore into maintenance-only mode for future module-first development.
- Started Post-M2 Distribution & Release Preparation after M2 implementation completion without starting M3.
- Replaced the installer finalizer's stale hardcoded `0.8.0` marker version with the framework version source of truth.
- Clarified `.env.example` as a configuration reference while installer-generated `.env` remains minimum operational database configuration.
- Cleaned `.gitignore` down to copot-relevant local, runtime, build, and tooling exclusions and added `dist/` release-output isolation.
- Verified deterministic package rebuilds produce identical v0.12.0 ZIP output and that the archive extracts successfully with external tooling.

- Made M2.4 Platform Hardening the active phase.
- Recorded M2.3 Minimal Site Capabilities as complete and released as v0.11.0.
- Completed M2.4 Batch 2 Minimal Diagnostics Baseline without adding a global error boundary or changing response behavior.
- Completed M2.4 Batch 3 Application Error Boundary and Rendering Safety without changing Router, Response, Admin in-shell rendering, or the Batch 2 Diagnostics contract.
- Completed M2.4 Batch 4 Admin In-Shell Errors without changing auth, permission, CSRF, session, Router, Response, or Diagnostics contracts.
- Completed M2.4 Batch 5 Runtime, Security, Storage, and Deployment Hardening without adding dependencies, services, generic storage, or background cleanup.
- Completed M2.4 Batch 6 Unified Regression and Release Readiness without runtime architecture changes or new product capability.
- Recorded M2.4 implementation as complete and ready for merge, tag, and release preparation, closing the lean M2 Platform Capabilities implementation phase while leaving M3 unstarted.

### Verification

- M3.3 baseline automated validation passes 816 assertions: 272 focused regression, 58 clean-install, and 486 package builder smoke assertions.
- M3.3 patch-focused reruns pass 130 assertions: 35 Batch 3 integration, 41 Batch 3 security, and 54 Batch 4 lifecycle assertions. Cumulative executed evidence is 946 assertions with overlap and is not a unique full-suite total.
- Manual Admin verification passes in a disposable official-package installation, including inventory, self-management protection, human-readable denial messaging, fixture lifecycle, module-file preservation, and leak checks; disposable resources were fully cleaned.

- M3.2 Batch 4 security and compatibility suites pass 145 focused assertions (126 security and 19 compatibility); cumulative focused M3.2 coverage through Batches 1–4 totals 366 assertions.
- Relevant M2.1 Admin UI, M2.3 Branding/Site Asset and unified, and M3.1 permission/access regressions pass after Batch 4.
- All five M3.2 batches pass their gates. Final automated validation confirms 366 focused assertions, required compatibility regressions, PHP lint, repository and boundary checks; the manual matrix passes, with permission-denial and configured-path cases verified automated-assisted.
- M3.2 Batch 3 integration and presentation suites pass 85 focused assertions (46 integration and 39 presentation); focused M3.2 coverage through Batches 1–3 totals 221 assertions.
- Relevant M2.1 Admin UI, M2.3 Branding/Site Asset, and M2.3 unified regressions pass after Batch 3.
- M3.2 Batch 2 domain and integration suites pass 94 focused assertions; Batch 1 Settings suites and directly relevant Branding, Settings, and Site Asset regressions pass.
- M3.2 Batch 1 definition and integration suites pass 42 focused assertions; relevant Admin UI, Settings, Site Asset, M3.1 compatibility, package-content, and isolated clean-install verification pass.
- M3.1 Batches 1–4 pass 487 focused assertions; the access-denied recovery regression adds 17 assertions for 504 focused assertions total.
- The complete M2.4 unified platform regression chain and M3.1 manual Admin verification pass.

- M3 Prep Stage 3 remediation remains documentation-only and preserves the released v0.12.0 runtime, Stage 1 architecture contract, and Stage 2 sequencing governance.
- M3 Prep Stage 2 changes remain documentation-only and preserve the Stage 1 architecture contract while locking sequencing governance.
- M3 Prep Stage 1 changes are documentation-only and introduce no runtime code, schema, dependency, or module implementation change.
- M2.4 Batch 2 focused diagnostics smoke coverage passes on the canonical PHP 8.5 runtime.
- M2.4 Batch 3 focused boundary/rendering smoke coverage passes with `display_errors=1`.
- M2.4 Batch 4 Admin in-shell error smoke coverage passes.
- M2.4 Batch 5 runtime/storage hardening smoke coverage passes.
- The complete M2.3 regression gate continues to pass.
- The unified M2.4 regression gate covers focused M2.4 Batches 2–5 and the complete M2.3 → M2.2 → M2.1 chain.
- Applicable local/manual Admin, session, CSRF, Site Asset lifecycle, controlled failure, leak, and Diagnostics checks pass.
- Live HTTPS Secure-cookie and production document-root isolation checks remain explicit deployment-environment verification items.
- PHP syntax checks and repository-log isolation checks pass.
- Post-M2 package reproducibility, package-content audit, and clean-install verification from the built artifact pass locally.

### Notes

- Batch 2 does not store raw `Throwable::getMessage()` output, does not return a dead error reference after append failure, and does not add a secondary sink.
- Batch 2 adds no global error boundary, response integration, database/config change, dependency, Admin redesign, queue, worker, scheduler, rotation service, observability platform, external service, or Media Library behavior.
- Batch 3 defaults unexpected failures to `500`; `503` requires an explicit positively identified availability condition and is not inferred from every `PDOException`.
- Batch 3 preserves trusted internal raw-HTML fragments without introducing a `SafeHtml` abstraction.
- Batch 4 adds shared Admin in-shell error rendering for eligible authenticated Admin requests while keeping guest, base-permission denial, early-bootstrap, and unsafe-recovery failures standalone.
- Batch 4 preserves `403`, `404`, `419`, controlled `503`, and unexpected `500` status semantics, reuses the original diagnostics reference for unexpected Admin failures, and avoids secondary recovery logging.
- Batch 4 registers configured Admin catch-all GET/POST routes only after Core and module routes so existing route precedence remains intact.
- Batch 6 records live HTTPS Secure-cookie verification, production `public/` document-root isolation, and symlink-capable host checks as deployment-environment responsibilities rather than claiming unperformed live verification.

<details open>
<summary>v0.11.0 - M2.3 Minimal Site Capabilities</summary>

### Added

- Added the M2.3 Minimal Site Capabilities scope, repository audit, architecture, batch plan, and acceptance contract.
- Defined the deterministic site-level localization and formatting boundary.
- Added the request-scoped `SiteFormatter` with explicit site-Timezone conversion.
- Added deterministic date, time, date-time, integer, and decimal formatting for `en_US` and `id_ID` with `en_US` fallback.
- Added focused M2.3 Batch 2 formatting coverage, including environment isolation and per-Application ownership.
- Defined the Core Site Name, Tagline, Logo, and Favicon contract.
- Defined the two-slot local Logo/Favicon upload, storage, replacement, removal, and controlled delivery contract.
- Added strict internal JSON definitions for optional `site.logo` and `site.favicon` descriptors.
- Added request-scoped read-only `SiteBranding` with Site Name and Tagline fallbacks while Logo/Favicon URLs remain unavailable.
- Added focused M2.3 Batch 3 descriptor, fallback, isolation, and scope-guard coverage.
- Added request-scoped `SiteAssetStorage` for fixed Logo/Favicon slots with content MIME, image structure, dimension, size, generated-name, containment, and symlink validation.
- Added safe descriptor persistence, replacement/removal cleanup, stable `/site-assets/logo` and `/site-assets/favicon` delivery, and focused Batch 4 failure coverage.
- Added fixed Admin Logo/Favicon upload and removal controls using existing permission and CSRF boundaries.
- Added controlled request upload access and active-Theme consumption of `SiteBranding`, including Logo, Favicon, Site Name, and Tagline fallbacks.
- Added focused M2.3 Batch 5 integration and scope-guard coverage.
- Added the unified M2.3 regression gate covering Batches 2–5 plus the complete M2.2 regression chain.

### Changed

- Completed M2.3 Batch 1 documentation and contract lock, Batch 2 Localization and Formatting Foundation, and Batch 3 Core Branding Settings Contract.
- Completed and verified the narrow Batch 4 two-slot local asset/storage and controlled delivery foundation.
- Completed and verified Batch 5 Admin upload/removal controls and active-Theme branding consumption.
- Completed Batch 6 unified regression, manual verification, contract audit, and release-readiness documentation.
- Kept Media Library, multilingual capability, image processing, arbitrary uploads, external storage, and the separate Core color-palette proposal deferred.

### Verification

- M2.3 focused Batch 2–5 tests pass on the canonical PHP 8.5 runtime.
- The unified M2.3 regression gate passes and preserves the complete M2.2 and M2.1 regression chain.
- Manual browser verification passes for Admin upload/replace/remove, invalid-file handling, controlled Logo/Favicon delivery, active-Theme branding, safe fallbacks, keyboard flow, responsive behavior, and public error redaction.

### Notes

- M2.3 Batch 2 is implemented without a database migration, dependency, production event, or presentation-call-site migration.
- M2.3 Batch 3 adds no upload, storage, URL delivery, Admin UI, Theme integration, database change, or production event.
- M2.3 Batch 4 is verified and committed without widening into generic uploads or Media Library behavior.
- M2.3 Batch 5 keeps upload handling limited to the two fixed site-identity slots and adds no generic file API, Media Library, or image editor.
- M2.3 implementation and verification are complete and released as v0.11.0.

</details>

<details open>
<summary>v0.10.0 - M2.2 Extensibility Foundation</summary>

### Added

- Added the Core `EventDispatcher` contract and synchronous request-scoped implementation.
- Added stable lowercase dotted event-name validation, object payload delivery, registration-order execution, duplicate registration support, no-listener no-op behavior, and fail-fast exception propagation.
- Added optional enabled-module listener contribution through a metadata-declared `listeners.php` file with controlled path and contribution-map validation.
- Added Batch 2 dispatcher coverage, Batch 3 enabled-module wiring integration coverage, and a unified M2.2 regression gate.

### Changed

- Corrected the M2.2 completion contract so controlled temporary fixture coverage proves the foundation without requiring a speculative production event.
- Deferred First Production Consumer Integration to the first milestone with a real caller/listener pair.

### Verification

- Unified M2.2 regression, automated-assisted application/runtime checks, and manual browser verification pass.
- Verified public home, Admin login and dashboard, Content and Taxonomy admin routes, and Admin CSS delivery.
- Verified keyboard navigation, focus visibility, responsive behavior, browser zoom, and real-user `admin.access` denial.
- Verified malformed contribution behavior with `display_errors=Off` without sensitive-detail leakage.

### Notes

- M2.2 implementation and verification are complete and released as v0.10.0.
- Temporary fixture event names are test-only and do not establish production API.
- At the v0.10.0 release, M2.4 Platform Hardening was still a separate future milestone.

</details>

<details open>
<summary>v0.8.0 - M1.8 Installer Foundation</summary>

### Added

- Added a pre-bootstrap installation gate and fresh web installer at `/install`.
- Added PHP, extension, filesystem, MySQL/MariaDB version, connection, and empty-database checks.
- Added atomic root `.env` database configuration persistence and focused canonical schema execution.
- Added transactional first-administrator creation and initial Site Name, Site Tagline, Timezone, and Locale setup.
- Added default frontend theme activation and automatic Content and Taxonomy installation/enablement.
- Added atomic `storage/installed.lock` creation after successful finalization.
- Added live-state installer progress, POST/Redirect/GET transitions, and responsive form presentation.

### Security

- All installer POST actions require session-backed CSRF validation.
- Credentials, passwords, DSNs, SQL, absolute paths, and stack traces are excluded from public errors.
- Installer writes use same-directory temporary files, controlled cleanup, atomic rename, and exclusive non-blocking `flock()` coordination.
- Malformed markers fail safely and are never overwritten; a valid marker disables `/install`.

### Notes

- Installation requires a pre-created dedicated empty database and does not support table prefixes.
- Partial schema DDL is not automatically rolled back; retry requires a clean empty database.
- M1.8 does not include CLI installation, upgrades/migrations, repair/reset, multisite, optional module/theme selection, marketplace integration, backup/restore, infrastructure provisioning, or M2 capabilities.

</details>

<details>
<summary>v0.7.0 - M1.7 Settings Foundation</summary>

### Added

- Added the Core Settings definition, registry, repository, and typed service foundation.
- Added namespaced setting identifiers with code-defined defaults and database overrides.
- Added typed serialization and casting for string, integer, boolean, float, and JSON values.
- Added the `settings` database table and `settings.update` permission with admin role mapping.
- Added a General and Localization Admin Settings page at the configured admin path.
- Added runtime timezone application, active locale access, and Site Name admin browser titles.

### Security

- Settings POST uses the Core CSRF service and `settings.update` permission guard.
- The Admin Settings form accepts only six explicit whitelisted fields.
- Settings save validates all fields before a transactional write.
- Admin output and browser titles escape stored Site Name and Site Tagline values.
- Missing storage and invalid stored overrides use controlled defaults without exposing SQL or exception details.

### Notes

- The database stores overrides only; defaults remain in code-defined settings definitions.
- Invalid stored rows are not rewritten automatically and do not discard other valid overrides.
- M1.7 does not include a translation engine, multilingual content/UI, per-user settings, module-specific settings UI, public Settings UI, settings cache, flash infrastructure, public theme title integration, a generic date/time formatter, or installer integration.

</details>

<details>
<summary>v0.6.0 - M1.6 Taxonomy Foundation</summary>

### Added

- Added a local `modules/taxonomy` Taxonomy Foundation module.
- Added `taxonomy_types`, `taxonomy_terms`, and `taxonomy_assignments` database tables.
- Added seeded `category` and `tag` taxonomy types.
- Added taxonomy permissions for create, update, and delete actions.
- Added admin taxonomy navigation and minimal term management UI.
- Added flat category and tag term list, create, edit, and delete workflows.
- Added generic assignment repository support for entity/type assignment syncing.
- Added Content Module integration for selecting category and tag terms during create/edit.
- Added assigned category/tag display on the admin content list when taxonomy is enabled.

### Security

- Taxonomy admin POST actions use `$app->csrf()->validateOrReject($request)`.
- Term delete rejects assigned terms through usage-count validation.
- Content taxonomy assignment sync is skipped when the Taxonomy module is disabled.
- Content admin screens hide taxonomy columns and fields when the Taxonomy module is disabled.

### Notes

- Taxonomy is the primary classification domain concept; Category and Tag are taxonomy types.
- M1.6 uses generic assignments with `entity_type`, but only `content` is integrated in this milestone.
- M1.6 intentionally does not include public taxonomy URLs, taxonomy archive pages, taxonomy type management UI, tree UI, drag-drop hierarchy UI, SEO taxonomy pages, multilingual taxonomy, API endpoints, search indexing, import/export, taxonomy custom fields, taxonomy media/icon handling, or a roadmap overhaul.

</details>

<details>
<summary>v0.5.0 - M1.5 Content Module</summary>

### Added

- Added a local `modules/content` Content Module foundation.
- Added `content` database schema and PDO-based content repository.
- Added content permissions for create, update, archive, and publish actions.
- Added admin Content navigation through the request-scope `AdminNavigation` service.
- Added admin content list, create, edit, publish, draft, and archive workflows.
- Added POST-body-only CSRF helper service through `Copot\Core\Csrf`.
- Added frontend content rendering at `/content/{slug}` for published content.
- Added module view fallback at `modules/content/views/show.php`.
- Added Theme System integration through `content::show` with active theme override support.

### Security

- Content POST actions use `$app->csrf()->validateOrReject($request)`.
- CSRF tokens are read from POST body only.
- Invalid or missing CSRF tokens return `419` before content database changes.
- Frontend content body rendering escapes plaintext and preserves line breaks.
- Draft and archived content return `404` on the frontend route.

### Notes

- Content is the primary domain concept; Article and Page are content types.
- M1.5 uses a plain textarea and does not implement Editor.js.
- M1.5 intentionally does not include taxonomy, media/image services, SEO, analytics, AI, translation, comments, newsletter, forms, advanced search, revisions, autosave, approval workflow, custom fields, scheduling, menu management, settings management, role/permission management, module management UI, theme management UI, or the expanded Content Manager / Workspace.

</details>

<details>
<summary>v0.4.1 - M1.4.1 Admin Shell</summary>

### Added

- Added configurable single-segment admin path through `config/admin.php`.
- Added core admin routes loaded after auth routes and before module routes.
- Added admin login at the configured admin path instead of redirecting to `/login`.
- Added CSRF-protected admin login and admin logout flow.
- Added `admin.access` permission seed and admin role mapping.
- Added minimal core admin layout, static Dashboard navigation, and dashboard status page.
- Added responsive admin login and dashboard shell styles.

### Security

- Admin access uses the existing authentication and permission system.
- Admin login and logout require CSRF tokens.
- Admin path validation accepts only lowercase single-segment slugs.
- Admin Shell does not use the frontend Theme System and does not allow frontend themes to override admin UI.

### Notes

- M1.4.1 intentionally does not include Content CRUD, module UI, theme UI, role/permission UI, settings UI, analytics, editor functionality, media/image services, localization, admin theming, an admin navigation manager, or middleware.
- The M1.2 `/protected` route remains a separate milestone test route.

</details>

<details>
<summary>v0.4.0 - M1.4 Theme System</summary>

### Added

- Added local theme metadata definitions through `theme.json`.
- Added theme discovery for direct children of the `themes/` folder.
- Added theme registry table with one active frontend theme.
- Added theme manager lifecycle operations for register, activate, and unregister.
- Added active theme loading and layout resolution.
- Added `ViewResolver` for `core::`, `theme::`, and module view namespaces.
- Added theme override support for core and module views.
- Added `ViewRenderer` for wrapping resolved content in the active theme layout.
- Added controlled active-theme asset serving through `/theme-assets/{theme-id}/{asset-path}`.
- Added a minimal default theme at `themes/default`.

### Security

- Theme and view resolution validate safe relative paths and resolved filesystem boundaries.
- Theme asset serving rejects traversal, encoded traversal, backslash traversal, null bytes, absolute paths, Windows drive paths, inactive theme IDs, and unsupported extensions.
- Theme asset responses include `X-Content-Type-Options: nosniff`.
- Frontend theme rendering errors use a generic public message without stack traces, filesystem paths, or internal diagnostics.
- Theme templates receive only `$content`, `$title`, `$theme`, `$themeAsset`, and `$context`; `$app` and core services are not injected.

### Notes

- Theme code is trusted local project code.
- The database stores theme registry state and metadata snapshots, not template source.
- M1.4 intentionally does not include an admin shell, admin theming, theme marketplace, installer, ZIP upload, asset pipeline, bundler, minifier, cache busting manifest, theme settings UI, theme editor, child themes, multi-site support, or generic theme hooks.

</details>

<details>
<summary>v0.3.0 - M1.3 Module Manager</summary>

### Added

- Added local module metadata definitions through `module.json`.
- Added module discovery for direct children of the `modules/` folder.
- Added module registry tables for installed modules and module permission metadata.
- Added module manager operations for discover, install, enable, disable, and uninstall.
- Added enabled module route loading after core and auth routes.
- Added simple duplicate route detection in the router.
- Added dependency validation when enabling modules.
- Added dependency guards before disabling or uninstalling modules required by enabled modules.
- Added a minimal sample module at `modules/example`.

### Notes

- Module install registers local module metadata only and leaves modules disabled by default.
- Module uninstall removes database registration only; it does not delete module folders, drop data, or run migrations.
- Module permission metadata is stored in `module_permissions` but is not auto-synced into the core `permissions` table.
- Module route files are treated as trusted local project code.
- M1.3 intentionally does not include a module UI, marketplace, installer, migration runner, asset publishing, theme integration, composer package system, or complex dependency resolver.

</details>

<details>
<summary>v0.2.0 - M1.2 User & Authentication</summary>

### Added

- Added session handling with configurable session name, cookie settings, and CSRF token storage.
- Added POST route support and redirect responses.
- Added request input handling for form submissions.
- Added authentication core with login, logout, active-user validation, and session regeneration.
- Added native PHP password hashing and verification through `PasswordHasher`.
- Added basic `User`, `UserProvider`, and `PermissionChecker` core classes.
- Added manual database schema for users, roles, permissions, user roles, and role permissions.
- Added seed data for `admin` and `user` roles and the `protected.access` permission.
- Added login and logout routes.
- Added protected milestone test route at `/protected`.
- Added login and protected test views.

### Security

- Added CSRF validation for login and logout forms.
- Rotated CSRF tokens after login and logout.
- Regenerated session IDs after login and logout.
- Invalidated sessions when the stored user no longer exists or becomes inactive.

### Documentation

- Updated README with M1.2 status, database setup, manual admin user creation, and manual test checklist.
- Updated architecture documentation to mark basic authentication and permissions as implemented in core.
- Updated roadmap to mark M1.2 as implemented.

### Notes

- M1.2 intentionally does not include an admin dashboard, user management UI, password reset, email verification, OAuth, 2FA, ORM, API layer, module integration, theme integration, or a migration runner.
- The `/protected` route is a milestone test route, not a product feature.

</details>

<details>
<summary>v0.1.0 - M1.1 Core Bootstrap</summary>

### Added

- Added the initial runnable PHP framework skeleton for M1.1 Core Bootstrap.
- Added a custom `Copot\Core` autoloader mapped to `app/Core`.
- Added application bootstrap flow through `public/index.php`, `bootstrap/autoload.php`, and `bootstrap/app.php`.
- Added PHP array configuration files and `.env.example` for environment-based settings.
- Added a GET-only router with simple 404 handling.
- Added request and response classes for the minimal request lifecycle.
- Added lazy PDO database connection using `database.default` configuration.
- Added an include-only view renderer with path validation under `resources/views`.
- Added a default `/` route and welcome page that runs without a configured database.
- Added initial runtime folders for storage, cache, logs, database placeholders, and tests.

### Documentation

- Updated project status and local Apache VirtualHost setup notes for M1.1.
- Clarified that Authentication, Module Loading, and Theme Loading are planned core responsibilities implemented progressively by roadmap milestone.
- Cleaned mojibake arrow characters in documentation.

### Notes

- M1.1 intentionally does not include authentication, CMS features, modules, themes, ORM, API, queues, events, middleware, controllers, or migration runners.
- The supported local setup for this milestone is an Apache VirtualHost pointing DocumentRoot to `public/`.

</details>

<details>
<summary>v0.0.1 - Repository Initialization</summary>

### Added

- Initial repository setup.
- Documentation structure.
- Project vision draft.
- Roadmap placeholder.
- AGENTS instructions.

</details>
