# Changelog

<details open>
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
