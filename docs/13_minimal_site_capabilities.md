# M2.3 Minimal Site Capabilities

## 1. Goal

M2.3 completes the smallest site-level capability layer needed before Core Module work: deterministic localization formatting, a controlled site-identity contract, and two safe local image slots for Logo and Favicon.

M2.3 extends existing Settings and Theme boundaries. It does not create a multilingual system, Media Library, generic upload platform, or visual Branding Manager.

Status: Batch 1 scope, repository audit, architecture, and contract lock.

---

## 2. Scope

M2.3 includes:

* site-level Locale and Timezone resolution through existing Settings;
* one explicit Core boundary for date, time, date-time, and number presentation;
* deterministic behavior on shared hosting without requiring `ext-intl`;
* a Core site-branding contract for Site Name, optional Tagline, optional Logo, and optional Favicon;
* controlled consumption of that contract by Core and the active frontend Theme;
* local Logo and Favicon storage outside the public document root;
* narrow upload, replacement, removal, and public-delivery behavior for those two slots only;
* MIME, size, image-structure, generated-name, and path-containment validation;
* automated regression and focused manual browser/runtime verification.

No database migration or new table is required. Existing registered Settings remain the persistence boundary.

---

## 3. Explicit Non-goals

M2.3 does not include:

* a Media Library, media picker, asset browser, folder manager, search, bulk actions, or usage browser;
* arbitrary public or administrator file upload;
* generic file management or a reusable arbitrary asset controller;
* image editing, crop, resize, optimization, variants, responsive images, or metadata editing;
* SVG upload;
* CDN, S3, cloud storage, external storage adapters, FTP, or SFTP;
* asynchronous processing, queues, schedulers, daemons, or background workers;
* multilingual content, translation management, UI translation, language switching, or localized routes;
* per-user or per-module Locale and Timezone;
* currency, accounting, pluralization, relative-time, or measurement formatting;
* branding per theme, tenant, site variant, or multisite;
* a palette editor, advanced brand colors, semantic color mapping, or Custom CSS;
* a Settings, Theme, Router, Module Manager, service-container, or dispatcher rewrite;
* a production event without one real production caller and listener.

The separate four-color palette proposal in `docs/11_branding_foundation.md` remains deferred. It is not the M2.3 site-identity contract and is not an M2.3 acceptance requirement.

---

## 4. Existing-system Audit

### Settings and localization

`app/Core/SettingsRegistry.php::core()` already defines:

```text
site.name
site.tagline
localization.timezone
localization.locale
localization.date_format
localization.time_format
```

Defaults are `copot`, an empty Tagline, `UTC`, `en_US`, `Y-m-d`, and `H:i`. Locale is currently limited to `en_US` and `id_ID`; date and time formats use controlled allowlists.

`SettingsService` already provides definition-backed validation, typed reads/writes, database overrides, and controlled default fallback. `database/schema.sql` already contains the generic `settings` table, so M2.3 does not need a schema change.

`Application::initializeRuntimeSettings()` already resolves Site Name, Timezone, and Locale, then calls `date_default_timezone_set()`. Application exposes `settings()`, `siteName()`, `timezone()`, and `locale()`. Date Format and Time Format are not exposed through a formatting boundary.

`routes/admin.php` and `resources/views/admin/settings.php` already provide a permission-checked, CSRF-protected, transactional form for all six definitions. The installer writes Site Name, Tagline, Timezone, and Locale through `InstallerAdministratorSetup`; Date Format and Time Format retain registered defaults.

There is no Settings module under `modules/`. Settings is correctly implemented as a Core/platform service.

### Current formatting

There is no generic date, time, date-time, or number formatter. Runtime presentation code has no shared formatting boundary. The only domain timestamp formatting found is `date('Y-m-d H:i:s')` in `ContentRepository`, which is a database persistence timestamp and is not a display-formatting caller.

`InstallationState` uses UTC `gmdate(DATE_ATOM)` for the installation marker. That machine contract must remain UTC and must not be routed through site presentation formatting.

### Branding and themes

Site Name is used by the Admin page renderer. Site Tagline is persisted and editable but is not consumed by the public Theme. The public home route still passes `config/app.php` application name as its title, and the default Theme has no Site Name, Tagline, Logo, or Favicon contract.

`ViewRenderer` already supplies a controlled Theme context and does not inject `Application` or database access. This is the correct integration boundary to extend with a small read-only site-branding value. Themes must not query Settings directly.

The existing `docs/11_branding_foundation.md` describes a separate future color-palette contract. It explicitly does not implement Logo or Favicon management. M2.3 keeps that palette work deferred and uses “site branding” narrowly for site identity.

### Assets, uploads, and storage

`ThemeAssets` provides useful patterns for safe relative paths, `realpath()` containment, extension allowlists, controlled URLs, `nosniff`, and generic not-found responses. It serves trusted files that ship inside the active Theme; it is not an upload validator and must not be reused as one.

The repository has no `$_FILES` handling, `is_uploaded_file()`, `move_uploaded_file()`, `finfo` validation, Logo/Favicon storage, or generic upload service. The only writable runtime locations are existing `storage` facilities used for installer state, cache, and logs.

The current automated suite covers Admin UI and M2.2 extensibility but has no focused Settings-formatting or uploaded-site-asset suite. M2.3 requires new focused tests while preserving the existing regression gates.

---

## 5. Localization Contract

Locale and Timezone are site-level settings only.

The initial supported locales remain:

```text
en_US
id_ID
```

The default Locale remains `en_US`. The default Timezone remains `UTC`. Timezone values remain valid PHP timezone identifiers. Invalid or unavailable overrides resolve through the existing registered defaults and must not fall back silently to the server timezone.

M2.3 localization means deterministic presentation conventions. It does not translate interface text or content.

The runtime may retain the existing `date_default_timezone_set()` compatibility behavior, but the new formatting boundary must construct and use the configured `DateTimeZone` explicitly. Formatter output must not depend on the process or server default timezone.

`setlocale()` must not be used because host locale packages are inconsistent across shared hosting. `ext-intl` must not be required. Presence or absence of `ext-intl` must not change the initial M2.3 output contract.

---

## 6. Formatting Boundary

Core will own one request-scoped formatter, provisionally named `SiteFormatter`, exposed explicitly by `Application`.

Its minimum contract is:

```text
formatDate(DateTimeInterface $value): string
formatTime(DateTimeInterface $value): string
formatDateTime(DateTimeInterface $value): string
formatNumber(int|float $value, int $fractionDigits = 0): string
```

Rules:

* date/time inputs represent instants and are converted to the configured site Timezone before presentation;
* Date Format and Time Format come from the existing registered Settings allowlists;
* date-time joins the formatted date and time with one ASCII space;
* number fraction digits must be explicit, deterministic, and limited to `0..6`;
* non-finite floats are rejected with a controlled exception;
* `en_US` uses `,` grouping and `.` decimal separators;
* `id_ID` uses `.` grouping and `,` decimal separators;
* PHP's locale-independent date tokens remain authoritative for the current approved date formats; M2.3 does not add translated month names;
* formatting returns plain strings and performs no HTML escaping; the rendering boundary still escapes output;
* persistence timestamps, lock timestamps, database timestamps, and protocol timestamps remain machine contracts and must not use the presentation formatter.

New presentation consumers must use this boundary instead of calling `date()`, `number_format()`, `setlocale()`, or formatter extensions directly in views. Existing machine-format calls are not migrated merely to inflate adoption.

---

## 7. Branding Contract

Core will expose one small read-only site-branding contract, provisionally named `SiteBranding`, containing:

```text
name(): string
tagline(): string
logoUrl(): ?string
faviconUrl(): ?string
```

Resolution rules:

* Name reads existing `site.name`; invalid or unavailable storage falls back to registered default `copot`;
* Tagline reads existing `site.tagline`; empty string is valid;
* Logo and Favicon are optional and resolve to `null` when unset, malformed, missing, or unavailable;
* consumers receive public URLs or `null`, never raw storage paths or uploaded client filenames;
* Admin UI keeps its independent M2.1 design tokens and does not adopt frontend branding colors;
* existing Admin browser-title Site Name behavior remains valid;
* `ViewRenderer` supplies the read-only value as `$branding` to frontend content views and layouts, alongside the existing controlled render variables; Themes do not receive `Application`, Settings, or database access.

M2.3 will add two registered JSON definitions:

```text
site.logo
site.favicon
```

Each defaults to `null`. A stored value is either `null` or a validated internal descriptor containing exactly:

```text
filename
mime_type
size
```

The descriptor contains a generated basename only. It does not contain an absolute path, public URL, original client filename, user identity, or arbitrary metadata.

---

## 8. Minimal Asset/Upload Contract

The upload foundation owns exactly two slots:

```text
logo
favicon
```

No API accepts an arbitrary slot or destination supplied by the request.

Allowed Logo formats:

| MIME | Canonical extension |
| --- | --- |
| `image/png` | `.png` |
| `image/jpeg` | `.jpg` |
| `image/webp` | `.webp` |

Allowed Favicon formats:

| MIME | Canonical extension |
| --- | --- |
| `image/png` | `.png` |
| `image/x-icon` | `.ico` |
| `image/vnd.microsoft.icon` | `.ico` |

Limits:

* Logo: maximum 2 MiB and maximum decoded dimensions of 4096 × 4096;
* Favicon: maximum 512 KiB and maximum decoded dimensions of 512 × 512;
* empty files, upload-error states, multiple files, and values outside the allowlists fail closed;
* MIME is determined from file content with `finfo`, not from client headers or extensions;
* image structure and dimensions must be readable before acceptance;
* the canonical extension comes from the detected allowlisted MIME;
* SVG, GIF, animated-image handling, and format conversion are excluded.

Generated filenames use the fixed slot prefix, `bin2hex(random_bytes(16))`, and the canonical extension. Original filenames are never used for storage.

The focused service may accept controlled local fixture input in tests, but the HTTP adapter must require a successful PHP upload and `is_uploaded_file()` before moving it.

---

## 9. Security Rules

* Upload handling is deny-by-default and accepts only the two fixed slots and allowlisted image contracts.
* Client MIME, client extension, and client filename are untrusted.
* Null bytes, separators, drive prefixes, dot segments, symlinks, and path traversal are rejected.
* Resolved storage paths must remain inside the fixed slot directory under the fixed site-asset root.
* Existing storage roots or slot directories that resolve through symlinks are rejected.
* New files are written completely in the destination filesystem before activation.
* Public responses set the validated `Content-Type` and `X-Content-Type-Options: nosniff`.
* Public delivery never accepts a filename or filesystem path from the URL.
* Admin state changes require authentication, `admin.access`, `settings.update`, and CSRF protection.
* Public/Admin errors are generic and must not include paths, original filenames, stack traces, environment data, or raw filesystem/database errors.
* Failed validation does not modify Settings or the current active file.
* No executable file type is accepted or served.

---

## 10. Storage and URL Rules

The fixed storage root is:

```text
storage/site-assets/
```

Slot directories are:

```text
storage/site-assets/logo/
storage/site-assets/favicon/
```

Files stay outside `public/`. Runtime setup may create these directories with shared-hosting-compatible PHP filesystem calls when the fixed parent is writable. No web-server rewrite, symlink, daemon, or deployment-specific adapter is required.

Stable public URLs are:

```text
/site-assets/logo
/site-assets/favicon
```

The route resolves only the currently active validated descriptor for that fixed slot. Arbitrary stored filenames are not public routes. An unset, malformed, missing, or unsafe active asset returns controlled `404` output.

Initial delivery uses `Cache-Control: no-store` so replacement is immediately observable at the stable URL without adding a versioning or cache-invalidation system.

Replacement order:

1. validate the complete new upload;
2. create and contain the new generated file;
3. persist the new descriptor through `SettingsService`;
4. delete the previous inactive file.

If descriptor persistence fails, the new file is removed and the old descriptor/file remain active. If old-file deletion fails after successful replacement, the new asset remains active and the old file is an unreachable orphan; the public response remains generic and the condition is covered by operational verification rather than exposing a path.

Removal order:

1. persist `null` for the slot;
2. delete the previous inactive file.

If persistence fails, the previous asset remains active. If later deletion fails, the stable public route no longer resolves the orphan. M2.3 does not add a generic orphan browser or background cleanup worker.

---

## 11. Shared-hosting Constraints

M2.3 must work with PHP 8.2+ and the existing synchronous request lifecycle.

It adds no Composer package, JavaScript framework, Node build, service process, queue, scheduler, worker, cloud SDK, or `ext-intl` requirement. Content-based upload validation requires PHP Fileinfo at upload time; when Fileinfo is unavailable, upload operations are unavailable with a controlled error rather than weakened validation. Fileinfo availability must be included in the M2.3 runtime requirement audit before upload integration is accepted.

Filesystem operations use the local `storage` tree and ordinary PHP APIs. Writable-directory failure is controlled and does not expose the resolved host path.

---

## 12. Integration Boundaries

* `SettingsRegistry` owns definitions and validation for site/localization values and asset descriptors.
* `SettingsService` remains the only persistence API; M2.3 code does not query the `settings` table directly.
* `Application` owns request-scoped formatter, branding, and focused site-asset services through explicit typed getters.
* `ViewRenderer` provides the read-only branding value to frontend Theme templates.
* Core public routes provide only the two stable asset-delivery endpoints.
* Existing Admin Settings routes remain the management surface; M2.3 does not create a Settings Manager module.
* Logo/Favicon upload and removal use dedicated POST actions below the configured Settings path rather than weakening the existing six-field transactional POST. Each action reuses `settings.update`, CSRF, configured Admin URL generation, and POST/Redirect/GET behavior.
* The installer keeps its existing Site Name, Tagline, Timezone, and Locale defaults. Fresh installs begin with no Logo or Favicon.
* `ThemeAssets` remains responsible only for trusted active-theme files.
* Content, Taxonomy, Module Manager, Theme lifecycle, Router behavior, and M2.2 dispatcher contracts remain unchanged.
* No M2.3 production event is approved by this Batch 1 contract because the audit found no required production listener.

---

## 13. Proposed Batch Plan

### Batch 1 — Scope, Audit, Architecture, and Contract Lock

Document existing capabilities, security boundaries, exact non-goals, batch order, and acceptance criteria. No runtime implementation.

### Batch 2 — Localization and Formatting Foundation

Add the request-scoped formatting boundary, explicit site-Timezone conversion, deterministic supported-locale number formatting, focused tests, and Application wiring. Do not modify presentation callers without a real display requirement.

### Batch 3 — Core Branding Settings Contract

Add validated optional Logo/Favicon descriptor definitions and the read-only `SiteBranding` resolver with safe fallbacks. Keep palette work and upload handling out of this batch.

### Batch 4 — Minimal Local Asset/upload Foundation

Add the two-slot storage service, upload/MIME/image/size validation, safe generated names, containment, replacement/removal behavior, stable controlled delivery, and focused failure tests. No Admin UI integration yet.

### Batch 5 — Logo and Favicon Integration

Extend the existing Admin Settings page with narrow Logo/Favicon upload/remove controls backed by dedicated configured-path POST actions, and integrate the read-only branding contract into the default public Theme. Preserve Admin UI independence and the existing six-field Settings transaction/security behavior.

### Batch 6 — Regression, Manual Verification, and Completion

Add a unified M2.3 regression gate, preserve M2.1/M2.2 coverage, audit the locked contract, verify shared-hosting failure paths, complete browser/runtime checks, and prepare completion documentation.

---

## 14. Acceptance Criteria

M2.3 is complete only when all applicable criteria pass:

### Localization and formatting

* Locale, Timezone, Date Format, and Time Format resolve from existing registered Settings with controlled defaults.
* Formatting converts date/time inputs through the configured site Timezone and is independent of server timezone.
* Supported `en_US` and `id_ID` number output is deterministic with and without `ext-intl`.
* Date, time, date-time, and number contracts have focused automated coverage.
* Machine timestamps remain outside the presentation formatter.

### Branding

* Site Name and Tagline reuse existing definitions.
* Logo and Favicon descriptors are optional, strictly validated internal values with `null` fallback.
* Core and the active Theme consume one controlled read-only branding contract.
* Themes do not gain Settings, database, or `Application` access.
* Empty optional branding values render safely and Site Name retains its registered fallback.

### Local assets

* Only Logo and Favicon slots can be written, removed, or delivered.
* MIME comes from content and matches a slot allowlist and canonical extension.
* Size, image structure, dimensions, upload status, generated filename, containment, and symlink guards fail closed.
* Files remain outside `public/`; stable controlled URLs expose only active slot assets.
* Replacement/removal failure paths preserve a deterministic active state and do not leak paths.
* Disabled MIME detection or unwritable storage fails safely.
* SVG and arbitrary files are rejected.

### Integration and regression

* Existing Admin Settings permissions, CSRF, validation, transaction, and configured admin-path behavior remain intact.
* Public home, Admin login/dashboard/settings, Content/Taxonomy routes, Theme assets, and M2.2 listener wiring regressions pass.
* No database schema change, third-party dependency, daemon, queue, scheduler, worker, or production lifecycle event is added.
* Manual verification covers valid upload/replace/remove, invalid files, stable URLs, fallback rendering, keyboard flow, responsive layout, and public error redaction.

---

## 15. Deferred Capabilities

Deferred beyond M2.3:

* Media Library and all asset-management UI;
* generic Media Foundation beyond the two site-identity slots;
* image processing, variants, optimization, crop/resize, and responsive images;
* external storage, CDN, and storage adapters;
* SVG security/sanitization policy;
* translated interface strings, content translation, and multilingual routing;
* per-user/per-module localization;
* currency, relative-time, pluralization, and advanced locale data;
* Core color palette and semantic mapping;
* theme-scoped branding, advanced color settings, and Custom CSS;
* Branding Manager, Settings Manager, Theme Manager, multisite, and multi-tenant branding;
* asynchronous cleanup and orphan-management UI.

Deferred work must not be pulled into M2.3 without a concrete dependency and explicit scope approval.

---

## 16. Risks and Open Decisions

The Batch 1 audit leaves no unresolved architecture decision that blocks Batch 2.

Known implementation risks are:

* host-specific MIME reporting for ICO must be normalized only to the two explicitly allowed icon MIME values and tested on the canonical runtime;
* filesystem and database updates cannot be one atomic transaction, so replacement/removal ordering and cleanup-failure tests are mandatory;
* stable asset URLs use `Cache-Control: no-store`; changing that policy later requires explicit cache-versioning or validation semantics;
* `fileinfo` is not part of the existing M1.8 required-extension list, so Batch 4 must fail closed when it is unavailable without changing installer scope prematurely;
* legacy code can still call global date functions, so regression searches must distinguish machine persistence timestamps from new presentation formatting.

These are verification obligations, not invitations to add storage abstractions, logging architecture, background cleanup, or image-processing scope.
