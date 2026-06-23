# Changelog

<details open>
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
