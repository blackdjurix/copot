# Changelog

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
- M1.5 intentionally does not include taxonomy, media/image services, SEO, analytics, AI, translation, comments, newsletter, forms, advanced search, revisions, autosave, approval workflow, custom fields, scheduling engine, menu manager, settings UI, role/permission UI, module UI, theme UI, or Content Workspace.

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
