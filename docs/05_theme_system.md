# Theme System

## Purpose

The theme system provides the frontend presentation layer for copot.

Themes own layout, markup, partials, view overrides, and frontend assets. Themes do not own business logic, database queries, authentication, permission enforcement, module lifecycle behavior, or routing decisions.

M1.4 introduces the first local frontend theme foundation. It is intentionally small and does not include an admin shell, marketplace, installer, ZIP upload, asset pipeline, template engine, theme editor, child themes, multi-site behavior, or generic hook system.

M1.4.1 introduces a separate core Admin Shell. The Admin Shell does not use frontend themes, active theme layouts, `ViewRenderer`, or frontend theme overrides.

---

## M1.4 Scope

Included:

* Local theme discovery from `themes/*/theme.json`
* Theme metadata validation
* Theme registry table
* One active frontend theme
* Theme activation and unregister guards
* Active theme loading
* Layout rendering through `ViewRenderer`
* View namespace resolution through `ViewResolver`
* Core view fallback
* Module view fallback
* Theme overrides for core and module views
* Controlled active-theme asset serving
* Minimal default theme at `themes/default`

Excluded:

* Admin Shell
* Admin theme support
* Content Module
* Theme Marketplace
* Theme Installer
* ZIP upload
* Asset pipeline
* Bundler or minifier
* Cache busting manifest
* Twig, Blade, or another template engine
* Theme settings UI
* Theme editor
* Child themes
* Multi-site themes
* Generic theme hooks

M1.4.1 keeps admin UI outside the frontend Theme System. Frontend themes must not override admin layout, admin login, admin dashboard, admin navigation, or admin logout behavior.

---

## Theme Folder Contract

Themes live under:

```text
themes/
```

Example:

```text
themes/
  default/
    theme.json
    layouts/
      app.php
    views/
      home.php
      modules/
        example/
          index.php
    assets/
      css/
        app.css
```

Required:

```text
theme.json
layouts/app.php
```

Optional:

```text
views/
partials/
assets/
```

Theme code is trusted local project code. M1.4 does not sandbox PHP templates.

---

## theme.json

Example:

```json
{
  "id": "default",
  "name": "Default Theme",
  "version": "0.1.0",
  "description": "Default frontend theme for copot.",
  "author": "blackdjurix",
  "type": "frontend",
  "entry": {
    "layout": "layouts/app.php"
  },
  "supports": {
    "module_view_overrides": true
  }
}
```

Required fields:

* `id`
* `name`
* `version`
* `type`
* `entry.layout`

Rules:

* `id` must use lowercase letters, numbers, and hyphens.
* `type` must be `frontend` in M1.4.
* `entry.layout` must be a safe relative path inside the theme folder.
* `supports`, when present, must be a JSON object.
* Unknown metadata keys are preserved in the normalized metadata snapshot.

---

## Database Registry

Theme registry data is stored in:

```text
themes
```

Columns:

```text
id
theme_id
name
version
type
path
is_active
metadata
created_at
updated_at
```

`metadata` is `TEXT NULL` and stores a JSON encoded snapshot. The database does not store template source.

The source of code is the filesystem. The database stores registry state and the active frontend theme.

M1.4 supports one active frontend theme for the whole application.

---

## View Namespaces

Views are resolved by namespace:

```text
core::home
core::errors.404
theme::landing
example::index
example::products.detail
```

Dot notation becomes a filesystem path:

```text
errors.404 -> errors/404.php
products.detail -> products/detail.php
```

Namespace rules:

* `core` is reserved for core views.
* `theme` is reserved for active-theme-owned views.
* Module namespaces must use lowercase letters, numbers, and hyphens.

View segment rules:

* Lowercase letters, numbers, hyphens, and underscores.
* No empty segment.
* No slash or backslash.
* No `..`.
* No null byte.
* No absolute path.
* No Windows drive path.

---

## Resolution Order

Core view:

```text
core::home
```

Order:

```text
1. themes/<active-theme>/views/home.php
2. resources/views/home.php
3. ViewException
```

Theme-owned view:

```text
theme::landing
```

Order:

```text
1. themes/<active-theme>/views/landing.php
2. ViewException
```

Module view:

```text
example::index
```

Order:

```text
1. themes/<active-theme>/views/modules/example/index.php
2. modules/example/views/index.php
3. ViewException
```

The module fallback path uses lowercase `views/` for Linux compatibility.

---

## Rendering Policy

`ViewRenderer` renders a resolved content file and wraps it in the active theme layout.

Variables available to content views:

```text
$title
$theme
$themeAsset
$context
```

Variables available to layouts:

```text
$content
$title
$theme
$themeAsset
$context
```

`$app` is not injected. The database, authentication, repositories, managers, and service container are not injected into theme templates.

`$context` contains data intentionally passed by the caller. Context keys are not automatically extracted into local variables.

---

## Asset Serving

M1.4 serves active theme assets through:

```text
/theme-assets/{theme-id}/{asset-path}
```

Example:

```text
/theme-assets/default/css/app.css
```

Source files stay in:

```text
themes/<theme-id>/assets/
```

Rules:

* Only the active frontend theme may be served.
* Asset paths are decoded and normalized before use.
* `..`, null bytes, absolute paths, Windows drive paths, and backslash traversal are rejected.
* The resolved file must remain inside the active theme `assets/` directory.
* Unsupported file extensions return controlled `404 Not Found`.
* Served assets include `X-Content-Type-Options: nosniff`.

Supported extensions:

```text
css
js
png
jpg
jpeg
gif
svg
webp
ico
woff
woff2
ttf
```

M1.4 does not compile, minify, fingerprint, copy, publish, or bundle assets.

---

## Error Policy

Theme and view failures should be controlled.

The public frontend route displays a generic message:

```text
Theme rendering error.
```

It must not expose stack traces, filesystem paths, database errors, or internal diagnostics.

---

## Manual Testing

Recommended checks at `http://copot.test`:

* `GET /` renders through the active theme.
* `GET /theme-assets/default/css/app.css` serves CSS when `default` is active.
* Traversal attempts such as `/theme-assets/default/%2e%2e/theme.json` return controlled `404 Not Found`.
* Wrong theme IDs such as `/theme-assets/other/css/app.css` return controlled `404 Not Found`.
* Missing active theme or missing theme files return controlled frontend errors.
