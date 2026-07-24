# copot

A modular PHP-based website framework designed for flexible content, business, and automation solutions.

## Status

M1 Framework Foundation is complete and released as v0.8.0.

M2.1 Admin UI Foundation is complete and released as v0.9.0.

M2.2 Extensibility Foundation is complete and released as v0.10.0.

M2.3 Minimal Site Capabilities is complete and released as v0.11.0.

M2.4 Platform Hardening, Post-M2 Distribution & Release Preparation, and the reproducibility blocker fix are complete. Copot v0.12.0 is the current stable Webcore baseline and installable release.

The framework is runnable as a lightweight PHP foundation with authentication, authorization, local module and theme systems, a minimal core admin shell, Content, Taxonomy, Settings Manager, and Module Manager modules, Core Settings, and a fresh-install web installer.

New deployments can be configured through `/install` before normal application bootstrap is allowed.

Installation and production deployment guidance is in `INSTALL.md`. Source/package boundaries and release-artifact rules are defined in `docs/15_distribution_and_packaging.md`.

## Current Phase

M3 Core Modules. M3.1 Users & Access is complete and merged to `main`; M3.2 Settings Manager is complete, validated, and merged to `main` through `afd82f0`. M3.3 Module Manager Batches 1–5 are complete and were fast-forward merged into `main` at `020f2b2`; its local and remote feature branches are deleted. M3.3 remains unreleased, untagged, and unpublished.

M3 Prep Stage 1 Governance + Architecture Lock is complete.

M3 Prep Stage 2 M3 Sequencing Lock is complete.

M3.4 Content Manager is complete on `main`; Batch 6 and full M3.4 are `NRP CONFIRMED`. M3.R1 preparation, classification, and lifecycle closure are complete, with final outcome `NO MATERIAL RETOUCH REQUIRED`. The exact Canonical Style ZIP `K:/My Drive/Codex/copot/prototype/copot(6).zip` is 81,458 bytes with SHA-256 `B0C5F4D237FD6BB203EAAD21500139A00185A058054041461377C7682372FD2E`; its 16-file inventory matches `K:/My Drive/Codex/copot/prototype/copot-6-extracted` with no missing, unexpected, or mismatched files. No implementation batches, implementation branch, production/test changes, runtime synchronization, or browser validation were required. Final lifecycle documentation commit `c5d27adeba6c1f440f6b9c62309a447f82e43a08` (`docs(m3.r1): record classification closure`) is pushed; local and remote `main` are synchronized at `0/0`, the workspace is clean, and full M3.R1 is `NRP CONFIRMED`. M3.5 product scope is accepted and its preparation contract is committed to `main` at `1e6c837340b0ea561870b7fe729791edcc0aa9f5` (`docs(m3.5): lock taxonomy manager preparation contract`); the active milestone branch is `feature/m3.5-taxonomy-manager`, Work Unit 1 implementation and primary validation are complete, and Work Unit 1 is `NRP CANDIDATE` pending Git/documentation closure. The existing baseline schema was sufficient; no schema upgrade or migration artifact was required, and production PHP/schema/install-upgrade state remained unchanged. Work Unit 2 — Hierarchy Domain and Transaction Safety is next. Full M3.5 is `NRP NOT REACHED`. Category hierarchy and flat tags are included; type CRUD, filtering, and Navigation Manager are excluded. Release, tag, and publication have not started and remain separately authorized.

M3.R1 final classifications are: Shared page header/action treatment and visual tokens `NO CHANGE REQUIRED`; Users list, Roles list, and Module list `REVIEW ONLY`; standalone prototype User Detail and Role Detail surfaces `EXCLUDED`. Prototype-led summary cards, filtering, extra metadata, avatars, pagination, compact Module action menus, standalone detail pages, command search, notifications, and sidebar system status remain optional future proposals or excluded scope; they are not M3.R1 implementation scope.

Batch 4 focused validation passes 33 assertions. Directly affected Content regressions pass with 9 provisioning, 37 transaction/lifecycle, 53 authorization, and 33 workspace assertions. Package builder smoke passes 825 assertions and clean-install verification passes 60 assertions. PHP lint, `git diff --check`, targeted source-to-runtime synchronization, and source review pass. Browser validation passes with limitations: normal lifecycle, published rendering, Draft/Archived denial, plaintext escaping, malformed read-ID containment, current configured Admin path, and desktop/390 × 844 smoke were confirmed; request replay was unavailable for CSRF, authorization-before-CSRF, malformed mutation payloads/identifiers, duplicate slug, stale write, repeated transitions, and injected persistence-error responses. Those cases remain covered by focused automated tests and source review. Optional Taxonomy-disabled browser behavior was not exercised. Batch 4 is NRP CONFIRMED after final documentation commit and verification; Batch 5 implementation, validation, documentation, commit, feature push, fast-forward merge, main synchronization, branch cleanup, and post-cleanup verification are complete. Batch 5 is NRP CONFIRMED.

M2 Platform Capabilities are complete. Copot v0.12.0 remains the latest stable released Webcore baseline. M3.1 and M3.2 are merged but are not yet included in a new release. M3.3 is merged but remains unreleased, untagged, and unpublished; Batches 1–5 implementation, validation, and manual Admin verification are complete.

The approved M3 sequence is:

```text
M3.1  Users & Access
M3.2  Settings Manager
M3.3  Module Manager
M3.4  Content Manager
M3.R1 Admin Shell Retouch 1 (after full M3.4 closure, before M3.5)
M3.5  Taxonomy Manager
M3.6  Navigation Manager
M3.7  Theme Manager
M3.8  Media Library
M3.9  Internal Dashboard
M3.10 Redirect Manager
M3.11 Form Manager
```

The sequence is governed by real dependency evidence, risk, architecture boundaries, and the approved M3 Admin Shell design-adjustment governance. Planning batch counts are domain envelopes rather than immutable implementation counts; horizontal M3.R1 and relevant design-adjustment checkpoints are governed separately, and exact milestone batch structure is locked just-in-time before each milestone starts.

M3.1 Users & Access completed its five approved batches and merged through `5c4cf8c`; local XAMPP workflow commit `35863e9` followed on `main`. M3.2 Settings Manager completed its five approved batches and merged to `main` through `afd82f0`: lifecycle-owned configured-path Admin routes, registered scalar management with validation-before-write and atomic persistence, specialized Logo/Favicon workflows, and tests-only security/compatibility hardening. Generic JSON and Site Asset descriptors remain excluded. Final focused M3.2 coverage is 366 assertions, required M2.1/M2.3/M3.1 compatibility regressions and manual verification pass. M3.3 implementation and validation are complete and merged into `main`; documentation synchronization, final focused review, user-owned commit, push, and merge-readiness assessment are complete. M3.2 is merged but not released.

The approved M3.3 Module Manager contract requires both `admin.access` and dedicated runtime permission `modules.manage` (`Manage modules`) for inventory and install, enable, disable, and uninstall. Fresh-install provisioning is present in the canonical schema; existing-install provisioning is present in the controlled operator-run `database/upgrades/m3_3_module_manager_permission.sql`. The artifact is the second independent upgrade artifact, but the Database Upgrade / Migration System trigger is not currently reached. Batch 3 activation and Admin implementation are complete, and all established lifecycle, authorization, CSRF, database, filesystem, and policy contracts remain unchanged.

Admin UX Refinement 1 and the subsequent Shell Foundation are presentation-only work after M3.3 and before M3.4 Content Manager. Shell Foundation provides the shared Admin shell, responsive mobile drawer, keyboard/accessibility behavior, navigation, menus, breadcrumbs, and configured Admin-path presentation. Settings Category 1 adds the locked six-tab presentation, truthful Security/Email/Maintenance empty states, standalone Branding presentation, and preserved existing Settings workflows. Local development uses a 30-day session lifetime; non-local environments retain the 120-minute default. Focused automated, source, runtime, and authenticated browser evidence passed, with documented manual-only limitations. No new backend capability is introduced. The approved Copot Admin Shell image and latest UI Refinement Plan remain the visual and external scope authorities; WordPress and other Admin interfaces are supporting references only.

Authenticated Public Toolbar is not part of Webcore or Admin UX Refinement 1. It remains Theme-owned future scope; Webcore may expose only existing authentication, current-user, and permission facts, or a minimal hook if later proven necessary, and must not render or own the toolbar UI contract.

The approved activation policy adds `module-manager` to `InstallerFinalizer::BASELINE_MODULES`, so fresh installations install and enable it through the existing generic ModuleManager lifecycle. No new activation framework, bootstrap synchronization, or automatic module reconciliation is introduced. For existing installations, apply `database/upgrades/m3_3_module_manager_permission.sql`, then explicitly install and enable `module-manager` through ModuleManager; its routes become available on the next request through the enabled-module loader. The project’s existing PHP/XAMPP command style is the supported operator path.

For an existing installation after applying the permission artifact, run:

```powershell
& "C:\xampp\php-8.5.7\php.exe" -r "chdir('C:/Git/copot'); `$app = require 'bootstrap/app.php'; `$app->modules()->install('module-manager'); `$app->modules()->enable('module-manager'); echo 'module-manager enabled';"
```

`modules/module-manager` is included in `build/package_manifest.php` and fresh-install baseline activation. M3.3 Batch 5 package, clean-install, focused regression, and manual Admin evidence pass. The Module Manager denies self-disable and self-uninstall with visibly disabled controls and human-readable denial text; stable denial codes remain internal. No additional schema, upgrade artifact, migration runner, or automatic permission synchronization is required.

Validation evidence is stated separately: baseline automated validation passes 816 assertions; patch-focused reruns pass 130 assertions; cumulative executed evidence is 946 assertions with overlap and is not a unique full-suite total.

M2.2 completion record:

* synchronous request-scoped dispatch with stable lowercase dotted names and object payloads;
* explicit registration-order listener execution without priority;
* no-listener no-op, duplicate explicit registrations, and unchanged fail-fast exception propagation;
* controlled optional `listeners.php` contribution from installed and enabled modules only;
* path containment and contribution-map validation;
* fixture-based end-to-end proof, unified regression, automated-assisted runtime verification, and manual browser verification passing;
* no production lifecycle event; the first production integration remains deferred to its real consumer milestone.

M2.2 scope, architecture, and completion evidence are defined in `docs/12_extensibility_foundation.md`. M2.3 Minimal Site Capabilities is complete and released as v0.11.0. M2.4 Platform Hardening and Post-M2 Distribution & Release Preparation are complete in v0.12.0. M3 Prep governance, architecture, and sequencing rules are defined in `docs/16_m3_core_freeze_and_module_contract.md`.

## Implemented Foundation

Included so far:

- Application bootstrap
- Configuration loader
- Environment loading
- Custom `Copot\Core` autoloader
- GET and POST route support
- Request input handling
- Redirect responses
- Session handling
- CSRF protection through the session layer
- Lazy PDO database connection
- Include-only view renderer
- User authentication
- Password hashing with PHP `PASSWORD_DEFAULT`
- Basic user object and provider
- Basic roles and permissions foundation
- Administrator-facing Users and Roles management
- User creation, identity editing, administrator-managed password changes, and active/inactive status controls
- User-role and role-permission desired-final-set assignments with multi-role effective permission unions
- Self-lockout and final active administrator-capable protections
- Permission-aware Users & Access routes and Admin navigation on the configured Admin path
- Users & Access CSRF, escaping, Admin error, and Content/Taxonomy/Settings compatibility hardening
- Login and logout routes
- Protected milestone test route
- Local module discovery through `modules/*/module.json`
- Module install registration
- Module enable, disable, and uninstall registration removal
- Simple module dependency validation
- Dependency guard before disabling or uninstalling required modules
- Module permission metadata storage without auto-syncing to core permissions
- Enabled module route loading
- Request-scoped synchronous event dispatcher
- Explicit registration-order listener execution with fail-fast exception propagation
- Optional enabled-module listener contribution through declared `listeners.php` metadata
- Unified M2.2 regression gate with controlled temporary end-to-end fixtures
- Sample `modules/example` module
- Local theme discovery through `themes/*/theme.json`
- Theme registry and single active frontend theme
- Theme activation and active-theme lifecycle guards
- Active theme layout rendering
- Core, theme-owned, and module view namespace resolution
- Theme overrides for core and module views
- Controlled active-theme asset serving through `/theme-assets/{theme-id}/{asset-path}`
- Minimal default theme at `themes/default`
- Configurable single-segment admin path through `config/admin.php`
- Core admin login at the admin path
- Minimal admin dashboard shell
- Static admin navigation foundation
- Admin logout route returning to the admin path
- `admin.access` permission for admin shell access
- Request-scope `AdminNavigation` service
- POST-body-only `Csrf` service helper
- Local `modules/content` module
- Content database schema and PDO repository
- Content permissions: `content.create`, `content.update`, `content.delete`, `content.publish`
- Admin content list, create, edit, publish, draft, and archive workflows
- Plain textarea content body input
- Draft, published, and archived content statuses
- Frontend published content route at `/content/{slug}`
- Escaped plaintext content body rendering with line breaks
- Theme System rendering through `content::show`
- Theme override support for content view rendering
- Local `modules/taxonomy` module
- Taxonomy database schema for types, terms, and assignments
- Default taxonomy types: `category`, `tag`
- Taxonomy permissions: `taxonomy.create`, `taxonomy.update`, `taxonomy.delete`
- Admin taxonomy term management under `/admin/taxonomy`
- Generic taxonomy assignment engine using `entity_type`
- Content admin category/tag selection when Taxonomy module is enabled
- Content admin fallback when Taxonomy module is disabled
- Core Settings definitions, registry, repository, and typed service
- Deterministic registered-only read-only Settings definition discovery through `SettingsService`
- Module-local Settings Manager editability/presentation policy with deterministic section and typed scalar field contracts
- Aggregated Settings validation with optional-field omission and validation-before-write
- Atomic Settings Manager candidate persistence through root transactions or caller-safe nested savepoints
- Grouped dynamic Settings Manager Admin presentation with text, number, checkbox, and select fields
- Nested identifier-based Settings submission with safe validation redisplay and configured-path PRG behavior
- Namespaced settings overrides with code-defined defaults
- Lifecycle-owned `settings-manager` Admin Settings routes and navigation under the configured Admin path
- Dynamic scalar Settings presentation and specialized fixed Site Asset controls owned by `settings-manager`
- `settings.update` permission for viewing and saving settings
- Transactional, CSRF-protected Settings form
- Runtime timezone application and active locale foundation
- Admin browser titles using the configured Site Name
- Controlled per-setting fallback for missing, unavailable, or invalid overrides
- Pre-bootstrap installation gate
- Fresh web installer at `/install`
- Runtime, filesystem, and database requirement checks
- Atomic root `.env` database configuration persistence
- Canonical `database/schema.sql` installation
- First administrator and initial site/localization setup
- Automatic default-theme activation
- Automatic Content, Taxonomy, Settings Manager, and Module Manager module enablement
- Atomic final installation marker at `storage/installed.lock`
- Installer denial after successful installation

Not included yet:

- Module management UI
- Marketplace
- Password reset
- Email verification
- OAuth
- 2FA
- ORM
- API layer
- Queue system
- Production lifecycle events without a concrete caller/listener consumer
- Migration runner
- Theme marketplace
- Theme installer
- Asset pipeline
- Template engine
- Theme settings UI
- Full admin theme or skin system
- Admin navigation manager
- Analytics
- Full Internal Dashboard
- Database-backed dashboard customization
- Editor.js
- Media/image service
- SEO module
- Comments
- Advanced search
- Revisions or autosave
- Approval workflow
- Custom fields
- Scheduling engine
- Expanded Content Manager / Workspace
- Translation engine and multilingual UI/content
- Per-user settings, timezone, or locale
- Public Settings UI
- Settings cache layer
- Public taxonomy URLs
- Taxonomy type management UI
- Taxonomy tree UI
- Taxonomy archive pages
- Remote module download

## Local Development

Recommended local setup uses an Apache VirtualHost:

```text
ServerName: copot.test
DocumentRoot: <repo>/public
URL: http://copot.test
```

Example local Git clone:

```text
C:\Git\copot
```

For XAMPP development, keep the Git clone on local storage and use a separate
one-way runtime mirror such as `C:\xampp\htdocs\copot.test`. Synchronize with:

```powershell
powershell -ExecutionPolicy Bypass -File tools\sync-local-xampp.ps1
```

Use `-DryRun` to preview the copy. Edit and commit only in the local Git clone;
never edit or commit from the htdocs mirror. GitHub is the source of truth
between machines, and each machine should use its own local clone. The sync
preserves destination-local `.env`/`.env.*`, `auth.json`, and runtime state in
`storage` while excluding Git metadata; it never copies the mirror back.

Expected public output:

```text
Copot
Default frontend theme rendering is active.
```

## Configuration

Copy `.env.example` to `.env` for local environment values when needed.

Do not commit `.env`.

## Database Setup

Create the local database configured in `.env`, then import:

```text
database/schema.sql
```

The schema creates:

- `users`
- `roles`
- `permissions`
- `user_roles`
- `role_permissions`
- `modules`
- `module_permissions`
- `themes`
- `content`
- `taxonomy_types`
- `taxonomy_terms`
- `taxonomy_assignments`
- `settings`

The schema seeds the `admin` and `user` roles plus the `protected.access`, `admin.access`, basic content permissions, basic taxonomy permissions, `settings.update`, and the nine M3.1 Users & Access permissions with initial `admin` role mappings. It does not seed a default admin user or default setting rows.

## Manual Default Theme

M1.4 stores active theme state in the database. After importing the schema, register and activate the default theme:

```sql
INSERT INTO themes (
    theme_id,
    name,
    version,
    type,
    path,
    is_active,
    metadata,
    created_at,
    updated_at
) VALUES (
    'default',
    'Default Theme',
    '0.1.0',
    'frontend',
    'themes/default',
    1,
    '{"id":"default","name":"Default Theme","version":"0.1.0","description":"Default frontend theme for copot.","author":"blackdjurix","type":"frontend","entry":{"layout":"layouts/app.php"},"supports":{"module_view_overrides":true}}',
    NOW(),
    NOW()
);
```

## Manual Admin User

Create an admin user manually after importing the schema.

Generate a password hash with PHP:

```php
echo password_hash('secret123', PASSWORD_DEFAULT);
```

Insert the admin user. Store the email in lowercase because login normalizes email input to lowercase.

```sql
INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
VALUES ('Admin', 'admin@example.com', '<PASSWORD_HASH>', 'active', NOW(), NOW());
```

Assign the admin role:

```sql
INSERT INTO user_roles (user_id, role_id)
SELECT users.id, roles.id
FROM users
INNER JOIN roles ON roles.slug = 'admin'
WHERE users.email = 'admin@example.com';
```

For an existing local database created before M1.4.1, add the admin shell permission manually:

```sql
INSERT IGNORE INTO permissions (name, slug, created_at, updated_at)
VALUES ('Access admin shell', 'admin.access', NOW(), NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'admin.access'
WHERE roles.slug = 'admin';
```

For an existing local database created before M1.5, apply the `content` table and content permission statements from `database/schema.sql`, or recreate the local database from the full schema if that is acceptable for your test data.

For an existing local database created before M1.6, apply the taxonomy table, seed, permission, and admin role mapping statements from `database/schema.sql`, or recreate the local database from the full schema if that is acceptable for your test data.

For an existing local database created before M1.7, apply the `settings` table plus the `settings.update` permission and admin role mapping statements from `database/schema.sql`. The table stores overrides only; defaults remain in Core definitions.

For an existing installation entering M3.1, run the controlled idempotent operator upgrade:

```text
database/upgrades/m3_1_users_access_permissions.sql
```

The upgrade provisions the nine runtime permissions and seeded `admin` role mappings. It does not register or enable the module. Install and enable `users-access` separately through the existing `ModuleManager` lifecycle flow. Runtime authorization continues to use `permissions`, `role_permissions`, and `user_roles`; module metadata does not create a second authorization system.

## Admin Shell and M2.1 Direction

M1.4.1 adds a minimal core Admin Shell.

The admin path is configured in:

```text
config/admin.php
```

Default configuration:

```php
return [
    'path' => 'admin',
    'permission' => 'admin.access',
];
```

The configured path is stored without a leading slash. The default runtime route becomes:

```text
/admin
```

Admin path rules:

* Single segment only.
* Lowercase letters, numbers, and hyphens only.
* Valid examples: `admin`, `backend`, `administrator`, `dapur`.
* Invalid examples: `/admin`, `admin/panel`, `../admin`, `Admin`, `admin\`, empty.

Admin login lives at the configured admin path. With the default configuration, `GET /admin` shows the admin login form for guests and does not redirect to `/login`. `POST /admin` handles admin login with CSRF protection. Successful login redirects back to the configured admin path, where the dashboard renders. Admin logout posts to the configured admin logout route and returns to the same admin base path.

M1.4.1 provides only the minimal shell. M1.7 adds the Core Admin Settings page without introducing admin theming or a generic settings editor.

M2.1 Admin UI Foundation evolved the existing shell into reusable, theme-independent Admin UI infrastructure with:

* centralized admin path and URL generation;
* centralized Admin Shell and page rendering;
* static Admin UI assets and design tokens;
* reusable alert, form, action, panel, table, and empty-state patterns;
* responsive and accessibility baseline;
* stable permission-aware navigation with active-state resolution;
* a minimal permission-aware dashboard-widget contribution registry;
* migrated Admin Login, Dashboard, Settings, Content, and Taxonomy presentation;
* a unified M2.1 regression gate.

M2.1 does not include a full admin theme system, user-selectable skins, M3 manager modules, analytics, a full Internal Dashboard, database-backed dashboard customization, frontend build tooling, or domain behavior changes.

All six M2.1 batches are complete. Automated regression and manual browser verification pass. Admin colors remain internal Admin UI tokens and do not consume Site Branding.

The future Core brand palette, Theme override boundary, Settings Manager ownership, and later Custom CSS direction are defined in `docs/11_branding_foundation.md`.

## M2.2 Extensibility Foundation

M2.2 adds a small, synchronous extension boundary for Core and enabled modules.

Status: complete. Implementation, automated regression, runtime verification, and manual browser verification pass.

The approved direction is:

* in-process, request-scoped dispatch;
* explicit listener registration;
* deterministic behavior;
* predictable exception handling;
* module participation only through controlled integration points;
* lifecycle events only where current behavior proves a concrete need.

Each `Application` owns one request-scoped dispatcher. Enabled modules may declare one optional `listeners.php` contribution file in `module.json`; disabled modules contribute nothing. Listener files are trusted local code, and contribution loading is controlled rather than sandboxed.

The dispatcher and enabled-module wiring are proven end to end through controlled temporary test fixtures. Fixture event names are test-only and are not production API. No production event is introduced by M2.2; the first production consumer milestone will add one only when a real caller/listener pair exists.

Manual verification passed for the public home, Admin login and dashboard, Content and Taxonomy admin routes, Admin CSS, keyboard navigation, focus visibility, responsive layout, browser zoom, real-user `admin.access` denial, and malformed contribution behavior with `display_errors=Off` and no sensitive-detail leakage.

The unified regression command is:

```powershell
& "C:\xampp\php-8.5.7\php.exe" tests/extensibility_m2_2_regression.php
```

M2.2 intentionally does not include queues, asynchronous processing, persistent event logs, replay, wildcard matching, webhooks, external APIs, distributed messaging, a service-container rewrite, or a generic plugin framework.

Detailed scope, architecture, batch planning, and acceptance criteria are defined in:

```text
docs/12_extensibility_foundation.md
```

## M2.3 Minimal Site Capabilities

M2.3 Batches 1–6 are complete. The unified regression gate and manual browser verification pass, and the milestone is released as v0.11.0.

The locked lean scope is:

* reuse the existing site-level Locale, Timezone, Date Format, and Time Format settings;
* add one explicit deterministic date, time, date-time, and number formatting boundary;
* expose Site Name, optional Tagline, optional Logo, and optional Favicon through one controlled Core branding contract;
* store only Logo and Favicon locally outside the public document root;
* validate upload status, content MIME, file size, image structure/dimensions, generated names, and path containment;
* deliver only the active Logo and Favicon through stable controlled public URLs;
* preserve shared-hosting operation without a new dependency or required `ext-intl` behavior.

M2.3 does not include multilingual content/UI, per-user localization, Media Library, arbitrary uploads, SVG upload, image processing, CDN/cloud storage, palette editing, Theme Manager, Settings Manager, queues, or background cleanup. The separate four-color palette proposal remains deferred.

The implementation batches are Localization/Formatting, Core Branding, focused local asset/upload storage, Logo/Favicon integration, and final regression/manual verification. Detailed contracts are defined in:

```text
docs/13_minimal_site_capabilities.md
```

Batch 2 provides one request-scoped `SiteFormatter` per `Application`, explicit configured-Timezone conversion, deterministic date/time/date-time output, and locale-aware integer/decimal separators for `en_US` and `id_ID`. Unsupported locales fall back to `en_US`. Output does not depend on the server timezone, OS locale, or `ext-intl`.

Batch 3 adds strictly validated internal `site.logo` and `site.favicon` JSON descriptors plus one request-scoped read-only `SiteBranding` value. Batch 4 adds the two-slot storage, validation, replacement/removal, controlled delivery, and focused failure coverage. Batch 5 adds permission- and CSRF-protected Admin upload/removal controls plus active-Theme consumption of the controlled branding value. Batch 6 adds the unified M2.3 regression gate and records completed manual verification.

## M2.4 Platform Hardening

M2.4 implementation is complete and released in v0.12.0. Batches 1–6 are complete, the unified regression gate is included, and applicable local/manual verification passes. This closed the lean M2 Platform Capabilities implementation phase; M3 Preparation subsequently completed, and M3.1 Users & Access is complete and merged to `main`.

The locked scope covers:

* consistent early and normal application error boundaries;
* sanitized public and Admin rendering without raw exceptions, warnings, traces, paths, SQL, credentials, environment data, tokens, request bodies, or client filenames;
* authenticated Admin errors rendered inside the existing shell only when the application, session, authentication, user, and renderer remain safely available;
* one small local request-synchronous logging baseline with allowlisted context, redaction, safe error references, and non-recursive failure behavior;
* controlled missing, unreadable, unwritable, unsafe, partial-write, rename, read, and cleanup failure paths for existing storage/filesystem ownership;
* focused authentication, permission, CSRF, upload, session-cookie, and escaping review;
* a production/shared-hosting checklist and one regression gate across M1 and lean M2.

M2.4 does not add a database change, dependency, Admin redesign, logging framework, observability platform, external service, queue, worker, scheduler, global rate limiter, generic storage abstraction, Media Library, arbitrary uploads, or background cleanup.

Batch 2 adds one request-scoped `Diagnostics` instance per `Application`. It writes append-locked JSON lines only to `storage/logs/copot.log`, omits raw exception messages, keeps source locations project-relative, accepts only fixed scalar context, returns an opaque `ERR-` reference only after a successful append, and returns `null`/`false` without a secondary sink when logging is unavailable. It does not register a global handler or change public/Admin rendering.

Batch 3 adds a fixed pre-autoload emergency `500`, a post-autoload bootstrap boundary, and an `Application::run()` dispatch boundary without changing Router or Response. Unexpected failures default to sanitized standalone `500` responses and include an opaque reference only when Diagnostics writes successfully. `503` remains an explicit status for positively identified availability failures; `PDOException` is not implicitly mapped. Boundary and renderer-owned buffers are cleaned back to their exact caller level, direct/partial output is rejected, and public Theme rendering failures now reach the centralized dispatch boundary. Batch 4 renders eligible authenticated Admin errors inside the existing shell and preserves standalone fallbacks when recovery is unsafe. Batch 5 makes Secure session cookies environment-configurable and records material site-asset read/cleanup degradation through the existing no-throw Diagnostics boundary without exposing paths or filenames.

Production release-readiness requires `public/` as the document root, `display_errors=Off`, private writable storage/logs, HTTPS-capable Secure session cookies, the existing PHP 8.2+ contract, and no daemon or build process.

For HTTPS production deployments, set:

```text
SESSION_SECURE=true
```

Keep `HttpOnly` enabled and the approved `SameSite=Lax` policy. The production web server must point its `DocumentRoot` to `<repo>/public`, keep `.env` and `storage/` outside direct web access, keep `display_errors=Off`, and leave PHP/web-server error logging enabled at the host level.

The error taxonomy, sanitization and redaction policy, storage/filesystem boundary, batch plan, acceptance criteria, and risks are defined in:

```text
docs/14_platform_hardening.md
```

## Content Module

M1.5 adds a basic local Content Module at:

```text
modules/content
```

For an existing or manually prepared installation, install and enable the Content Module through the Module Manager. A fresh installation completed through `/install` installs and enables Content, Taxonomy, Settings Manager, and Module Manager automatically as baseline modules.

Install and enable the Content Module:

```powershell
cd "K:\My Drive\GitHub\copot"
& "C:\xampp\php\php.exe" -r "chdir('K:/My Drive/GitHub/copot'); `$app = require 'bootstrap/app.php'; `$app->modules()->install('content'); `$app->modules()->enable('content'); echo 'content enabled';"
```

After the module is enabled, the admin content area is available at:

```text
/admin/content
```

Current capabilities:

- List content entries.
- Create content.
- Edit content.
- Publish content.
- Move published content back to draft.
- Archive content without hard delete UI.
- Render published content at `/content/{slug}`.

Content statuses:

```text
draft
published
archived
```

Only `published` content renders on the frontend. Draft, archived, and missing content return `404 Not Found`.

M1.5 uses a plain textarea for the body. M1.6 adds optional taxonomy integration when the Taxonomy module is enabled. Content still works when the Taxonomy module is disabled; the category/tag fields and list column are hidden and assignment sync is skipped.

The current Content Manager does not include Editor.js, media/image handling, SEO, comments, advanced search, revisions, autosave, approval workflow, custom fields, or scheduling. The approved M3.4 scope remains limited to the existing Content module and defers those future capabilities. Batch 6 separately covers scoped Admin Content workspace presentation and Content-related Admin Shell navigation placement/order; it does not add domain capabilities, Navigation Manager, Theme Manager, frontend Theme rendering, or Core behavior. The M3.4 contract is defined in `docs/18_m3_4_content_manager_contract.md`, and reusable Admin Shell design-adjustment governance is defined in `docs/19_m3_admin_shell_design_adjustment_contract.md`.

## Taxonomy Foundation

M1.6 adds a reusable local Taxonomy Foundation module at:

```text
modules/taxonomy
```

For an existing or manually prepared installation, install and enable the Taxonomy Module through the Module Manager. A fresh installation completed through `/install` installs and enables Content, Taxonomy, Settings Manager, and Module Manager automatically as baseline modules.

Install and enable the Taxonomy Module:

```powershell
cd "K:\My Drive\GitHub\copot"
& "C:\xampp\php\php.exe" -r "chdir('K:/My Drive/GitHub/copot'); `$app = require 'bootstrap/app.php'; `$app->modules()->install('taxonomy'); `$app->modules()->enable('taxonomy'); echo 'taxonomy enabled';"
```

Admin routes:

```text
/admin/taxonomy
/admin/taxonomy/category
/admin/taxonomy/tag
```

Current capabilities:

- Manage seeded taxonomy types `category` and `tag`.
- Create, edit, and delete flat terms.
- Reject deleting a term while it is assigned.
- Assign category/tag terms to content entries from the content create/edit form.
- Hide taxonomy fields in Content admin when the Taxonomy module is disabled.

Permissions:

```text
taxonomy.create
taxonomy.update
taxonomy.delete
```

M1.6 intentionally does not include public taxonomy URLs, `/category/{slug}`, `/tag/{slug}`, taxonomy archive pages, taxonomy type management UI, tree UI, drag-drop hierarchy UI, SEO taxonomy pages, search indexing, API endpoints, import/export, taxonomy custom fields, or taxonomy media/icon handling.

## Settings Foundation

M1.7 adds Core Settings at the configured admin path:

```text
/{admin_path}/settings
```

With the default `admin.path` value, the route is `/admin/settings`. Access and save operations require `settings.update`.

M1.7 defines six settings:

```text
site.name
site.tagline
localization.timezone
localization.locale
localization.date_format
localization.time_format
```

Definitions and defaults live in Core code. The `settings` database table stores overrides only. Runtime behavior applies the active timezone before route/module handling, exposes the active locale through `Application`, and formats common admin browser titles as `<Page Title> | <site.name>`.

Missing tables, unavailable storage reads, missing overrides, and invalid stored overrides fall back to definition defaults without exposing SQL or exception details. An invalid stored row is not rewritten automatically, and valid overrides for other settings remain available.

M1.7 does not add a translation engine, multilingual content/UI, per-user settings, a public Settings page, a settings cache layer, public theme title integration, flash infrastructure, or a generic date/time formatter.

## Installer Foundation

M1.8 provides a fresh-install web flow at:

```text
/install
```

When `storage/installed.lock` is absent, normal application requests redirect to the installer before `Application` or database-dependent routes are bootstrapped. The installer checks the environment, tests a dedicated empty database, persists the five `DB_*` connection values in the root `.env`, installs the canonical schema, creates the first administrator, saves Site Name, Site Tagline, Timezone, and Locale, activates the local `default` theme, and installs/enables Content, Taxonomy, Settings Manager, and Module Manager. The final marker is created only after all required setup succeeds. A valid marker makes `/install` return `404` and allows normal application bootstrap.

Requirements:

- PHP 8.2 minimum; PHP 8.3+ recommended; PHP 8.4 preferred.
- PDO, `pdo_mysql`, Session, JSON, and Filter support.
- MySQL 8.0+ or MariaDB 10.4.32+.
- MySQL 8.4 LTS+ or MariaDB 10.11+ recommended for production; MariaDB 11.4 LTS preferred.
- A pre-created dedicated empty database. Existing tables or views are rejected.
- Writable `storage`, writable root `.env` when present, and a writable project root for same-directory atomic replacement.

M1.8 is designed for conventional PHP/MySQL shared hosting where PHP can write and rename files in the project root and `storage`, and where `flock()` is available. Hosts that prohibit those operations are not supported by an FTP/SFTP fallback. The installer does not create databases, use table prefixes, upgrade an installation, repair/reset partial installations, select optional modules/themes, or provide backup/restore.

For a fresh manual check:

1. Ensure `storage/installed.lock` is absent and select an empty disposable database.
2. Open `/install` and complete Requirements, Database, Administrator & Site, and Finalize.
3. Confirm the final redirect uses the configured admin path.
4. Confirm `/install` returns `404`, the default theme is active, and Content, Taxonomy, Settings Manager, and Module Manager are enabled.
5. If schema execution fails partially, use a new clean database; M1.8 has no destructive repair/reset flow.

## Manual Settings Test Checklist

Run these checks at `http://copot.test` after applying the M1.7 schema statements:

- Login as an admin with `settings.update` and open `/{admin_path}/settings`.
- Confirm all six effective values render from overrides or definitions.
- Save General and Localization values and confirm POST/Redirect/GET success feedback.
- Save repeatedly and confirm one row remains for each namespace/key pair.
- Submit an empty Site Name and confirm status `422` without changing previous overrides.
- Submit an unsupported timezone, locale, date format, or time format and confirm controlled validation.
- Submit an invalid or missing CSRF token and confirm status `419` without changes.
- Confirm unknown posted fields do not create definitions or rows.
- Confirm a user without `settings.update` cannot see navigation or access the page directly.
- Confirm HTML characters in Site Name and Site Tagline render as text.
- Confirm changing `admin.path` moves the Settings route and leaves no hardcoded `/admin/settings` route.
- Confirm missing storage or an invalid stored override falls back to the relevant definition default while other valid settings remain available.

## Manual Auth Test Checklist

Run these checks at `http://copot.test`:

- `GET /` shows the public default page.
- `GET /login` shows the login form.
- Invalid CSRF on login or logout returns `Invalid CSRF token.` with status `419`.
- Invalid login returns the login form with an error and status `422`.
- Valid login redirects to `/protected`.
- `GET /protected` without login redirects to `/login`.
- `GET /protected` after valid admin login shows the protected test page.
- `POST /logout` logs out and redirects to `/`.
- Setting a logged-in user to `inactive` causes `/protected` to reject the session and redirect to `/login`.

## Manual Admin Shell Test Checklist

Run these checks at `http://copot.test`:

- Logged out `GET /admin` shows the admin login form and keeps the URL at `/admin`.
- Wrong admin login keeps the user at `/admin` and shows an error with status `422`.
- Valid admin login with a user that has `admin.access` shows the Admin Shell dashboard at `/admin`.
- The dashboard shows app name, user name/email, current admin path, static Dashboard navigation, and a logout button.
- Admin logout posts to `/admin/logout` and returns to the admin login form at `/admin`.
- A logged-in user without `admin.access` receives `403 Forbidden` at `/admin`.
- `GET /login` still uses the existing public/auth login flow.
- `GET /protected` remains the separate M1.2 protected route.

## Manual Content Module Test Checklist

Run these checks at `http://copot.test` after the Content Module is installed and enabled:

- `GET /admin/content` shows the Content list in the Admin Shell.
- `GET /admin/content/create` shows the create form.
- Create content as draft and confirm it appears in the list.
- Edit the content and confirm changes are saved.
- Invalid or missing CSRF on create, update, publish, draft, or archive returns `419`.
- Publish the content and confirm `/content/{slug}` renders through the active frontend theme.
- Draft content returns `404` at `/content/{slug}`.
- Archived content remains visible in admin but returns `404` at `/content/{slug}`.
- A temporary theme override at `themes/default/views/modules/content/show.php` wins over `modules/content/views/show.php`, then remove the temporary file after testing.
- With the Taxonomy module enabled, create category/tag terms and confirm the content create/edit form can select them.
- With the Taxonomy module disabled, confirm content list/create/edit still work and taxonomy fields are hidden.

## Manual Taxonomy Module Test Checklist

Run these checks at `http://copot.test` after the Taxonomy Module is installed and enabled:

- `GET /admin/taxonomy` shows available taxonomy types.
- `GET /admin/taxonomy/category` shows category terms.
- `GET /admin/taxonomy/tag` shows tag terms.
- Create a category term and tag term.
- Edit each term and confirm changes save.
- Invalid or missing CSRF on term create, update, or delete returns `419`.
- Delete an unused term successfully.
- Assign a term to content, then confirm deleting the assigned term is rejected.

## Manual Module Test Checklist

The repository includes a sample module at:

```text
modules/example
```

The sample module is discovered and installable, but it is not enabled automatically.

Run these checks at `http://copot.test`:

- Before enabling the module, `GET /example` returns `404 Not Found`.
- Install the sample module:

```powershell
cd "K:\My Drive\GitHub\copot"
& "C:\xampp\php\php.exe" -r "chdir('K:/My Drive/GitHub/copot'); `$app = require 'bootstrap/app.php'; `$app->modules()->install('example'); echo 'installed';"
```

- Enable the sample module:

```powershell
& "C:\xampp\php\php.exe" -r "chdir('K:/My Drive/GitHub/copot'); `$app = require 'bootstrap/app.php'; `$app->modules()->enable('example'); echo 'enabled';"
```

- After enabling the module, `GET /example` shows `Example Module`.
- Disable the sample module:

```powershell
& "C:\xampp\php\php.exe" -r "chdir('K:/My Drive/GitHub/copot'); `$app = require 'bootstrap/app.php'; `$app->modules()->disable('example'); echo 'disabled';"
```

- After disabling the module, `GET /example` returns `404 Not Found`.

## Manual Theme Test Checklist

Run these checks at `http://copot.test` after the default theme is active:

- `GET /` renders through the active frontend theme.
- `GET /theme-assets/default/css/app.css` serves the default theme CSS.
- `GET /theme-assets/default/%2e%2e/theme.json` returns controlled `404 Not Found`.
- `GET /theme-assets/other/css/app.css` returns controlled `404 Not Found`.
- Missing active theme or missing theme files return a generic `Theme rendering error.` without stack traces or filesystem paths.

Theme view names use namespaces:

```text
core::home
theme::landing
example::index
```

Resolution order:

```text
core::home
1. themes/<active-theme>/views/home.php
2. resources/views/home.php

example::index
1. themes/<active-theme>/views/modules/example/index.php
2. modules/example/views/index.php
```

Theme templates receive only:

```text
$content
$title
$theme
$themeAsset
$context
```

The application container, database, auth service, repositories, and managers are not injected into theme templates.

## Documentation

See `docs/` for project vision, principles, architecture, roadmap, and capability specifications.

Key documents:

```text
docs/02_architecture.md
docs/03_roadmap.md
docs/10_admin_ui_foundation.md
docs/11_branding_foundation.md
docs/12_extensibility_foundation.md
docs/13_minimal_site_capabilities.md
docs/14_platform_hardening.md
docs/15_distribution_and_packaging.md
docs/16_m3_core_freeze_and_module_contract.md
```
