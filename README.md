# copot

A modular PHP-based website framework designed for flexible content, business, and automation solutions.

## Status

M1.1 Core Bootstrap is complete.

The framework is now runnable as a minimal PHP skeleton. It can serve a default page through Apache when the document root points to `public/`.

## Current Milestone

M1.1 Core Bootstrap

Included in this milestone:

- Application bootstrap
- Configuration loader
- Environment loading
- Custom `Copot\Core` autoloader
- GET-only router
- Request and response classes
- Lazy PDO database connection
- Include-only view renderer
- Default page rendering

Not included yet:

- Authentication
- CMS features
- Modules or module manager
- Themes or theme manager
- ORM
- API layer
- Queue system
- Event system
- Migration runner

## Local Development

Recommended local setup uses an Apache VirtualHost:

```text
ServerName: copot.test
DocumentRoot: <repo>/public
URL: http://copot.test
```

Example local path:

```text
DocumentRoot: K:\My Drive\GitHub\copot\public
```

Expected output:

```text
Copot
M1.1 Core Bootstrap is running.
```

The default page does not require a database connection.

## Configuration

Copy `.env.example` to `.env` for local environment values when needed.

Do not commit `.env`.

## Documentation

See `docs/` for project vision, principles, architecture, and roadmap.

Roadmap:

```text
docs/03_roadmap.md
```
