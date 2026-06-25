# copot

A modular PHP-based website framework designed for flexible content, business, and automation solutions.

## Status

M1.4.1 Admin Shell is complete.

The framework is runnable as a lightweight PHP skeleton with authentication, authorization, a local module manager foundation, a frontend theme system, and a minimal core admin shell. It can serve public pages through an active theme, login/logout users, protect routes, discover local modules and themes, register them in the database, enable or disable modules, activate a frontend theme, resolve themed views, serve controlled active-theme assets, and provide a configurable admin entry point.

## Current Milestone

M1.4.1 Admin Shell

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
- Login and logout routes
- Protected milestone test route
- Local module discovery through `modules/*/module.json`
- Module install registration
- Module enable, disable, and uninstall registration removal
- Simple module dependency validation
- Dependency guard before disabling or uninstalling required modules
- Module permission metadata storage without auto-syncing to core permissions
- Enabled module route loading
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

Not included yet:

- User management UI
- Module management UI
- Marketplace
- Password reset
- Email verification
- OAuth
- 2FA
- ORM
- API layer
- Queue system
- Event system
- Migration runner
- Theme marketplace
- Theme installer
- Asset pipeline
- Template engine
- Theme settings UI
- Content CRUD
- Admin theming
- Admin navigation manager
- Role/permission UI
- Settings UI
- Analytics
- Editor.js
- Media/image service
- Localization
- Remote module download

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

The schema seeds the `admin` and `user` roles plus the `protected.access` and `admin.access` permissions. It does not seed a default admin user.

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

## Admin Shell

M1.4.1 adds a minimal core admin shell.

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

The configured path is stored without a leading slash. The runtime route becomes:

```text
/admin
```

Admin path rules for M1.4.1:

- Single segment only.
- Lowercase letters, numbers, and hyphens only.
- Valid examples: `admin`, `backend`, `administrator`, `dapur`.
- Invalid examples: `/admin`, `admin/panel`, `../admin`, `Admin`, `admin\`, empty.

Admin login lives at the same admin path. `GET /admin` shows the admin login form for guests and does not redirect to `/login`. `POST /admin` handles admin login with CSRF protection. Successful login redirects back to `/admin`, where the dashboard renders. Admin logout posts to `/admin/logout` and returns to `/admin`.

M1.4.1 intentionally does not include admin theming, an admin navigation manager, Content CRUD, module UI, theme UI, role/permission UI, settings UI, analytics, editor functionality, media/image services, localization, or middleware.

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

See `docs/` for project vision, principles, architecture, and roadmap.

Roadmap:

```text
docs/03_roadmap.md
```
