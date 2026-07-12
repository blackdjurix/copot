# Installer System

## Purpose

M1.8 Installer Foundation provides a web-based fresh installation flow for copot on PHP/MySQL shared hosting. It prepares one new installation from the canonical project files and existing Core services.

M1.8 is not an upgrade, migration, repair, reset, or provisioning system. The target database must be dedicated to copot and empty before installation begins.

The installer endpoint is `/install`. Supported database baselines are MySQL 8.0 or newer and MariaDB 10.4.32 or newer.

## Locked Scope

M1.8 includes:

* Web installer only.
* Fresh installation only.
* Environment and runtime requirement checks.
* Database credential validation and connection testing.
* Persistence of database configuration through the root `.env` pattern already consumed by `Env` and `config/database.php`.
* Installation of the canonical `database/schema.sql` without a table prefix.
* Creation of the first active administrator through a dedicated installer workflow service using `PasswordHasher` and the seeded `admin` role.
* Initial `site.name`, `site.tagline`, `localization.timezone`, and `localization.locale` overrides through `SettingsService`.
* Registration and activation of the local `default` frontend theme through the Theme System.
* Installation and enablement of the local `content` and `taxonomy` modules through the Module Manager.
* A final installation lock written only after all required setup succeeds.
* Installer denial after successful installation.

The installer does not select an admin path in M1.8. The existing configurable admin path remains governed by `config/admin.php`.

## Entry and Bootstrap Boundary

`public/index.php` now runs a small installation-state gate before normal application bootstrap. Normal bootstrap constructs `Application`, reads Settings, starts the session, loads routes, and queries enabled modules, so it is reached only after installation is complete.

The gate performs this flow:

1. Resolve the installation lock path outside the public document root.
2. If the lock exists, continue through the normal application bootstrap.
3. If the lock does not exist, route the request into the isolated installer bootstrap.

The installer bootstrap may reuse the Core autoloader, request/response primitives, session/CSRF support, database wrapper, and focused domain services. It must not construct the complete normal `Application` before valid database configuration and the canonical schema are available.

The web entry is `/install`. Requests to normal application routes while uninstalled redirect to the installer without attempting module, theme, authentication, or Settings queries. Once installed, `/install` returns a controlled `404` response.

## Requirements Checks

Checks must complete before persistent installation work:

* PHP 8.2 or newer runtime. PHP 8.2 is accepted with an aging warning, PHP 8.3 or newer is recommended, and PHP 8.4 is preferred. A compatible newer release such as PHP 8.5 is not blocked solely for being newer.
* PDO, `pdo_mysql`, Session, JSON, and Filter extension availability. Other optional extensions are not required by M1.8.
* MySQL 8.0+ or MariaDB 10.4.32+ server version. MySQL 8.0 is the supported minimum and MySQL 8.4 LTS+ is recommended for production. MariaDB 10.4.32 through releases before 10.6 are accepted with a legacy/end-of-life warning; MariaDB 10.11+ is recommended and MariaDB 11.4 LTS is preferred.
* Session and password-hashing support.
* Writable root `.env` target or writable project root when `.env` must be created.
* Writable `storage` location for the final lock and installer synchronization.

Checks should report capability names and remediation guidance, not absolute filesystem paths or server internals.

The canonical schema is validated when database installation runs. Default theme and baseline module metadata are validated during finalization through their existing discovery and lifecycle services.

## Database Contract

The installer accepts MySQL host, port, database name, username, and password. It builds a test connection in memory using prepared configuration and must not echo credentials or a DSN containing credentials.

The selected database must already exist and must contain no tables or views. M1.8 does not create databases, add table prefixes, reuse an existing schema, or attempt to distinguish compatible tables from unrelated tables.

After a successful connection and empty-database check, database values are persisted as these existing environment keys:

```text
DB_HOST=...
DB_PORT=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

Environment persistence must preserve unrelated existing keys, reject control characters in submitted values, serialize values safely, and replace the target atomically through a temporary file in the same directory. `.env` remains ignored by Git.

The canonical `database/schema.sql` is the only schema source for M1.8. The schema runner supports only the controlled statement format used by copot's canonical schema; it is not a universal SQL parser or migration runner. Schema errors must stop the flow and produce a generic installation error while retaining detailed diagnostics only for an explicitly approved non-public logging mechanism.

The canonical schema consists only of semicolon-terminated `CREATE TABLE` and `INSERT INTO` statements, with quoted string literals and no stored procedures, triggers, or delimiter changes. The focused runner recognizes comments and quoted semicolons while rejecting statement families outside that contract. Every canonical table declares `ENGINE=InnoDB`, `DEFAULT CHARSET=utf8mb4`, and `utf8mb4_unicode_ci` explicitly so installation does not inherit incompatible server defaults.

Database persistence updates only `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Existing unrelated environment keys and comments are preserved, duplicate target keys are collapsed, and values are quoted with controlled escaping. An existing `.env` is copied to a temporary same-directory backup before replacement. The replacement file is fully written and flushed in the same directory before an atomic rename, and temporary write/backup files are removed on success or failure.

## Installation Flow

The implemented capability flow is:

1. Confirm that no completed-installation lock exists.
2. Start an installer session and issue a CSRF token.
3. Run runtime, extension, session, and writable-location checks.
4. Collect database credentials and test the target connection.
5. Verify that the selected database is dedicated and empty.
6. Persist database environment configuration atomically.
7. Execute `database/schema.sql` against the verified empty database.
8. Collect first-administrator and initial site/localization values.
9. Validate all administrator and Settings values before their first database write.
10. Hash the administrator password with `PasswordHasher`, insert one active user, and assign the seeded `admin` role.
11. Save Site Name, Site Tagline, Timezone, and Locale through `SettingsService` in the same transaction.
12. Discover, register, and activate the local `default` frontend theme through existing Theme services.
13. Discover, install, and enable the local `content` and `taxonomy` modules through `ModuleManager`.
14. Recheck all required resulting state.
15. Create the final installation lock atomically.
16. Redirect to the configured admin path for login.

No optional module/theme choices, sample content, mail setup, infrastructure setup, or M2 capability is included.

## Administrator Creation

The canonical schema seeds roles and permissions but does not seed a user. The installer must:

* Normalize the administrator email to lowercase.
* Validate a non-empty name and unique valid email.
* Require a password of at least 10 characters and an exact confirmation match without storing or logging the plaintext password.
* Generate `password_hash` through the existing `PasswordHasher`.
* Insert an active user with timestamps using prepared statements.
* Resolve the seeded `admin` role and insert the `user_roles` relation.

There is no current user-creation repository. M1.8 uses a dedicated installer administrator workflow service that reuses existing authentication and role primitives; route code must not accumulate raw creation SQL.

The Batch 4 workflow first validates every administrator and initial Settings field, then confirms the complete canonical schema is available and acquires the installer mutex. It rejects an existing email and refuses to create another first administrator when any user already exists. User insertion, assignment to the seeded `admin` role, and the four initial Settings overrides run on the same PDO connection and transaction. A database failure rolls back all administrator, role-assignment, and Settings writes.

Installer progress is derived live on every request from the root `.env` database connection, canonical schema presence, and whether any user already exists. It is not stored in the session. When the active database already has a first administrator, the form is replaced by a controlled completed-state message and a `Change Database` link to `/install?step=database`. That query changes presentation only; it does not alter `.env`, delete data, or bypass the normal requirements, connection, version, empty-database, CSRF, and atomic persistence checks.

The database form uses one stable-width action button. A successful asynchronous connection test changes its label from `Test Database` to `Install Database` without rerendering the password field. Editing any database field marks that browser-only test result stale and restores the test action. This state is presentation-only: the install POST repeats all server-side validation, connection/version and empty-database checks, mutex acquisition, atomic environment persistence, and schema execution.

## Baseline Finalization

After the first administrator and initial Settings exist, the installer presents one explicit finalization action. A dedicated finalizer acquires the installer mutex and rechecks the canonical schema, exactly one active administrator assigned to the built-in `admin` role, and valid persisted overrides for Site Name, Site Tagline, Timezone, and Locale.

Finalization registers and activates the discovered `default` frontend theme through `ThemeManager`, then installs and enables Content, Settings Manager, and Taxonomy through `ModuleManager`. Existing registry rows and already-enabled modules are reused so a retry after a partial failure does not create duplicate registry rows. Theme and module operations are not claimed to be fully transactional; a failure leaves no installation marker and a later retry resumes through the same idempotent lifecycle checks.

`storage/installed.lock` is created atomically as the final operation only. Its version comes from the framework release source of truth, `Copot\Core\Version::CURRENT`. Once present and valid, `/install` is blocked by the pre-bootstrap gate and normal application requests proceed. Successful finalization redirects to the configured admin path rather than a hardcoded `/admin` URL.

## Installer Presentation and Flow

The installer displays Requirements, Database, Administrator & Site, and Finalize in a compact progress indicator. Completed, current, pending, and blocked states are derived from live requirements, schema, and administrator state on each request; step state is never stored in the session. Progress labels are informational rather than links, so a request cannot navigate directly past an unmet prerequisite. The only recovery navigation is `Change Database`, which selects the database view without changing persistent state.

Successful schema installation and successful administrator creation both redirect to a clean `GET /install`. Successful finalization redirects to the configured admin path. Validation failures render only within the action's own step, while a later GET starts with clean request-local error state. CSRF failures remain immediate `419` responses and do not create flash or validation state.

The database test remains an asynchronous, browser-only convenience. Its stable-width action changes from `Test Database` to `Install Database` after success and returns to the test state whenever any database field changes. Password fields are never repopulated, all rendered values are escaped, and final installation still relies exclusively on repeated server-side checks.

## Failure and Shared-Hosting Policy

The requirements check reflects the actual atomic-write prerequisites: an existing root `.env` must be a writable regular non-symlink file, and its parent project directory must also be writable so a same-directory temporary file can be renamed into place. Installer storage must exist and be writable for the mutex and final marker. The installer does not provide FTP or SFTP filesystem adapters and does not claim compatibility with hosts that block these required filesystem operations.

Both `.env` and `installed.lock` are written completely to temporary files in their destination directories, flushed, optionally synchronized when `fsync()` is available, and then renamed. Temporary and backup files are removed on handled failures. A malformed existing marker is never overwritten; it puts the gate into a controlled fail-safe state. Missing or unavailable `flock()` support is rejected with a controlled concurrency error rather than treated as an unlocked installation.

Connection, schema, administrator transaction, theme/module lifecycle, marker, and unexpected runtime failures are contained at the public installer boundary. Responses remain generic and do not include credentials, DSNs, SQL, absolute filesystem paths, environment contents, or stack traces. A valid final marker is still created only after theme and module activation succeeds.

Schema DDL is not assumed to be transactionally reversible. If schema execution fails after creating database objects, the database is partial and non-empty; a retry is rejected by the empty-database probe and the operator must select a clean empty database. No repair, reset, table deletion, or destructive cleanup flow is provided. Theme/module activation can likewise leave partial registry state, but subsequent requests reuse existing rows and retry through the idempotent lifecycle checks before any marker is created.

## Settings Initialization

The installer writes only these registered definitions:

```text
site.name
site.tagline
localization.timezone
localization.locale
```

`SettingsService` remains authoritative for type handling and validation. Date and time formats keep their code-defined defaults in M1.8. The installer must not create arbitrary settings or store secrets in the Settings table.

The installer collects Site Name, optional Site Tagline, Timezone, and Locale only after the canonical schema is ready. Timezone options come from PHP timezone identifiers and locale remains limited to `en_US` and `id_ID`. Non-secret values may be returned after controlled validation failure; administrator password and confirmation are never stored in the session or rendered back into the form.

## Theme and Module Initialization

The default theme is local trusted project code. Installation must use `ThemeDiscovery`, `ThemeManager::register()`, and `ThemeManager::activate('default')` so registry metadata, relative path storage, layout validation, and single-active-theme rules remain centralized.

Content, Settings Manager, and Taxonomy are local baseline modules. Installation must use `ModuleManager::install()` followed by `enable()` for each module. M1.8 does not enable the Example module and does not add module selection UI.

The finalizer verifies `default`, `content`, and `taxonomy` through existing discovery services before it creates the final marker. Missing or invalid local project metadata stops finalization without claiming installation success.

## Installation State and Lock

The completed-installation marker is:

```text
storage/installed.lock
```

It is outside `public` and contains exactly this JSON contract:

```json
{
  "installed_at": "2026-06-27T14:00:00+00:00",
  "version": "0.12.0"
}
```

`installed_at` must be an ISO-8601 timestamp accepted by PHP's `DATE_ATOM` format. `version` must be a valid copot semantic version. The marker has no status flag, schema version, credentials, or extension metadata. Additional or missing fields make the marker invalid until a future marker-contract change is explicitly documented.

Presence of a successfully validated marker means normal bootstrap is allowed and the installer is unavailable.

The completed-installation marker must be created with an atomic/exclusive filesystem operation only after schema installation, administrator assignment, Settings writes, theme activation, module enablement, and final verification all succeed. A writable-location probe must run before database changes, but the marker itself must not be created early.

Concurrent installation submissions must be serialized with an exclusive non-blocking `flock`. The temporary lock file may remain on disk, but its presence is never installation state; only a successfully validated `storage/installed.lock` marks completion.

## Partial Installation Behavior

MySQL DDL commonly commits implicitly. M1.8 explicitly does not provide automatic full DDL rollback.

If any required step fails:

* Do not create `storage/installed.lock`.
* Stop subsequent setup steps.
* Show a generic controlled failure with a retry prerequisite.
* Never display credentials, SQL text, filesystem paths, stack traces, or raw PDO errors.
* Do not claim installation success.

Because M1.8 accepts only an empty database and has no repair/reset mode, a failure after schema execution requires the operator to empty or recreate the dedicated database before retrying. The persisted `.env` database values may remain so the installer can reconnect, but they do not indicate successful installation. A schema failure may leave a partially populated database; retrying Batch 3 against that database is rejected by the empty-database guard.

Where practical, related post-schema DML should use local transactions. Those transactions reduce partial data inside a step but do not promise rollback across schema DDL, filesystem writes, Theme lifecycle transactions, and Module lifecycle operations.

## Security Rules

* All state-changing installer requests require session-backed CSRF validation.
* Installer forms accept explicit whitelisted fields only.
* Database credentials and administrator passwords must never be placed in query strings, responses, logs, exceptions, lock files, or session flash payloads.
* Password fields must never be repopulated after a failed submission.
* Public errors must use stable generic messages and controlled HTTP status codes.
* Environment values must reject null bytes and line breaks to prevent `.env` injection.
* Database names and credentials are connection values, never interpolated into SQL statements.
* The database-empty check must inspect the selected database through `information_schema` without accepting arbitrary SQL identifiers.
* The installer must verify canonical schema, module, and theme paths against fixed project roots.
* The final lock path must be fixed by the application, not supplied by the request.
* Wizard step and validation-error state must remain request-local rather than being persisted in the session.
* A completed installation must not expose installer forms or database checks.

## Implementation Batches

### Batch 1 - Installation State and Pre-bootstrap Gate

Implemented marker handling, exclusive non-blocking `flock`, the pre-bootstrap gate, and controlled marker/storage failures.

### Batch 2 - Requirements and Database Validation

Implemented environment checks, explicit database input validation, server-version checks, connection testing, dedicated empty-database enforcement, and credential redaction.

### Batch 3 - Atomic Configuration and Schema Installation

Implemented safe `.env` persistence, focused canonical-schema execution, controlled schema failures, and partial-install handling.

### Batch 4 - First Administrator and Initial Settings

Implemented first-admin creation, admin-role assignment, four initial Settings values, and rollback-capable post-schema DML.

### Batch 5 - Baseline Theme, Modules, and Final Lock

Implemented default-theme activation, Content/Taxonomy installation and enablement, final live-state verification, atomic marker creation, and configured admin redirect.

### Batch 6 - Installer UI and End-to-End Flow

Implemented live-state progress UI, request-local validation feedback, CSRF-protected transitions, password non-repopulation, responsive presentation, and POST/Redirect/GET flow.

### Batch 7 - Failure, Security, and Shared-hosting Hardening

Hardened filesystem checks, atomic writes, malformed-marker behavior, mutex failures, partial-install policy, public diagnostics, and critical failure/retry paths. Version policy remains documented separately from the local environment used for end-to-end verification.

### Batch 8 - Documentation and Release Preparation

Finalized README, architecture, roadmap, changelog, governance, installer documentation, and release material for v0.8.0 without adding installer behavior.

All implementation milestones require a final audit and explicit approval before the user performs commit, merge, tag, or release operations.

## Explicit Exclusions

M1.8 does not include:

* CLI installer.
* Upgrade or migration engine.
* Repair or reset installer.
* Table prefixes.
* Multisite.
* Module or theme selection UI.
* Marketplace integration.
* SMTP or mail setup.
* Queue, cron, or cache setup.
* Sample content.
* Import or export.
* Backup or restore.
* Automatic full DDL rollback.
* Docker or cloud provisioning.
* Any M2 capability work.
