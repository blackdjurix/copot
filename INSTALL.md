# Installing copot

copot is distributed as an installable package. Do not deploy a developer working directory or a raw repository archive as a production package.

## Requirements

- PHP 8.2 or newer.
- PDO MySQL support.
- PHP session support.
- A dedicated empty MySQL database.
- A writable project root for installer-managed `.env` persistence.
- A writable `storage/` directory for installation state, cache, diagnostics, and site assets.
- A web server whose document root points to `public/`.

For production, keep `display_errors` disabled and use HTTPS. Set `SESSION_SECURE=true` when the site is served exclusively over HTTPS.

## Fresh installation

1. Extract the official release package into the target application directory.
2. Point the domain or virtual host document root to the package `public/` directory.
3. Ensure the project root and `storage/` are writable by the PHP process according to the host's ownership model.
4. Create a dedicated empty MySQL database.
5. Open the site URL. Requests are redirected to `/install` while no valid installation marker exists.
6. Complete the database, administrator, site settings, and finalization steps.
7. After finalization, the installer creates `storage/installed.lock`, activates the default theme, and enables the baseline Content and Taxonomy modules.
8. Verify Admin login, public rendering, Content and Taxonomy access, and Site Asset upload/delivery before accepting the deployment.

## Environment configuration

`.env.example` is a configuration reference, not a file copied automatically by the installer.

The installer writes the minimum operational database keys into `.env`:

```text
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
```

Optional application and runtime overrides documented in `.env.example`, including `SESSION_SECURE`, may be added to the generated `.env` as required by the target environment.

Never ship or publish a local `.env` file. It may contain machine-specific hosts, database credentials, or other environment-specific values.

## Production checks

Before accepting a deployment:

- confirm the domain document root is `public/`, not the project root;
- confirm `.env`, `storage/`, `app/`, `config/`, and database files are not directly web-accessible;
- confirm `display_errors=Off`;
- confirm HTTPS deployments use `SESSION_SECURE=true`;
- confirm `storage/` remains writable to the PHP process but is not publicly served;
- confirm diagnostics contain no credentials, raw exception messages, SQL, request bodies, tokens, cookies, or client filenames;
- run the release regression checks from the source repository before building a release candidate.
