# copot

A modular PHP-based website framework designed for flexible content, business, and automation solutions.

## Status

M1.2 User & Authentication is complete.

The framework is runnable as a lightweight PHP skeleton with a basic authentication and authorization foundation. It can serve public pages, login/logout users, protect a test route, and verify basic role-based permissions.

## Current Milestone

M1.2 User & Authentication

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

Not included yet:

- Admin dashboard
- User management UI
- Password reset
- Email verification
- OAuth
- 2FA
- ORM
- API layer
- Queue system
- Event system
- Migration runner
- Module or theme integration

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
M1.1 Core Bootstrap is running.
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

The schema seeds the `admin` and `user` roles and the `protected.access` permission. It does not seed a default admin user.

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

## Documentation

See `docs/` for project vision, principles, architecture, and roadmap.

Roadmap:

```text
docs/03_roadmap.md
```
