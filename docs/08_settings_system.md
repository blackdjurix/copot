# Settings System

## Purpose

M1.7 Settings Foundation provides global settings for one copot installation. Settings is a Core/platform service, not an optional module.

Core owns setting persistence, retrieval, validation, and type casting. The Admin Settings UI is also part of the platform foundation. M1.7 is limited to global/site settings and basic localization settings.

## Future Settings Manager Boundary

The Admin Settings UI introduced in M1.7 is a minimal platform-foundation interface for the six approved General and Localization settings.

It is not the future M3 Settings Manager.

The future Settings Manager will remain built on `SettingsService` and may provide broader registered-settings organization, reusable field rendering, module-contributed settings sections, and permission-aware management UI.

After the future M2 Branding Foundation defines its contract, the M3 Settings Manager may provide UI to edit the four Core palette values: main, accent, neutral dark, and neutral light. Site Settings must not make the locked Core semantic mapping editable.

Theme-specific palette or semantic-mapping overrides, advanced color settings, and later Custom CSS belong to the future Theme Manager. Their values remain scoped to the active theme and must not write back to the Core palette. See `docs/11_branding_foundation.md`.

It must not replace the SettingsService foundation, permit arbitrary unregistered keys, or store environment secrets.

Settings must not store secrets, passwords, SMTP credentials, API tokens, or environment configuration. Sensitive and deployment-specific values belong in environment variables or appropriate configuration files.

---

## Logical Identifiers

Every setting uses a namespace and key. A logical identifier joins them with a dot for human-readable references:

```text
site.name
site.tagline
localization.timezone
localization.locale
localization.date_format
localization.time_format
```

The service API keeps `namespace` and `key` as separate arguments. Namespace and key values use these exact formats:

```text
namespace: ^[a-z][a-z0-9_-]{0,63}$
key:       ^[a-z][a-z0-9_-]{0,127}$
```

The Admin Settings UI cannot create arbitrary identifiers.

---

## Database Schema

M1.7 implements one core table:

```sql
CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    namespace VARCHAR(64) NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value MEDIUMTEXT NOT NULL,
    value_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_settings_namespace_key (namespace, setting_key)
);
```

`setting_key` is used instead of the ambiguous SQL identifier `key`.

A missing row means no override exists. `setting_value` is not nullable, so SQL `NULL` is not overloaded to mean either a missing override or a stored value. Empty string remains a valid stored override. Any future typed representation of a logical null value must use an explicit serialized representation and definition approved with the type system.

The database stores overrides only. Resolution order is:

1. Use a database override when present.
2. Otherwise use the code-defined default.
3. Otherwise use the caller-provided default argument.

Deleting a row removes the override and restores default resolution.

---

## Value Types

M1.7 supports:

* `string`
* `integer`
* `boolean`
* `float`
* `json`

Values are validated before write and cast to their declared type on read. JSON must be valid JSON-compatible data. M1.7 does not support encrypted values, object serialization, PHP serialized values, or file/media setting types.

---

## Service API

The Settings service is backed by a code-defined registry. Each `SettingDefinition` contains its namespace, key, type, default value, validation rule, and optional allowed values or metadata. Duplicate definitions and invalid identifiers are rejected. The registry supports definition lookup, existence checks, namespace-scoped lists, and namespace discovery.

The public Settings service contract is:

```text
get($namespace, $key, $default = null)
set($namespace, $key, $value, $type = null)
has($namespace, $key)
all($namespace)
delete($namespace, $key)
```

Behavior:

* `get()` returns a usable typed database override for a known definition, otherwise its code-defined default. A stored override that cannot be deserialized, has a mismatched type, or fails definition validation is unusable and falls back only that setting. A caller fallback never overrides a known definition default. For an unknown but valid identifier, it returns the caller fallback or `null` when no fallback is supplied.
* `set()` accepts registered definitions only, validates before persistence, and treats the definition type as authoritative. When `type` is omitted, the definition type applies; an explicit type must match it.
* `has()` reports whether a definition is registered. It does not report raw override existence.
* `all()` returns effective typed values for all registered definitions in one namespace, merging overrides with defaults.
* `delete()` removes only the database override, allowing the code-defined default to apply again.

Unknown `set()` and `delete()` operations are rejected. Invalid identifiers are rejected for all operations. The M1.7 Admin Settings UI will not expose arbitrary create or delete operations. Users edit only definitions known to the system.

---

## Serialization and Casting

Stored types are limited to `string`, `integer`, `boolean`, `float`, and `json`. A stored type outside this list, a stored type that differs from its definition, malformed stored data, or a stored value that fails definition validation is treated as an unusable override. `SettingsService::get()` catches only the resulting controlled `SettingsException` and returns that definition's default without rewriting the stored row. Other valid overrides remain available.

Canonical behavior:

* `string`: accepts and stores strings unchanged; reads as string.
* `integer`: accepts integers or valid in-range integer strings; stores a canonical integer string; reads as integer.
* `boolean`: accepts booleans or the exact strings `1`, `0`, `true`, and `false`; stores `1` or `0`; reads as boolean. Loose PHP truthiness is not used.
* `float`: accepts integers, floats, or finite numeric strings; stores a canonical JSON numeric string; reads as float. `NAN` and infinite values are rejected.
* `json`: accepts JSON-compatible PHP values, stores JSON using `JSON_THROW_ON_ERROR`, and reads JSON objects as associative arrays consistently. PHP serialization is not used.

Validation and serialization complete before persistence, so a failed write leaves any previous override unchanged.

---

## M1.7 Definitions

### General

| Identifier | Type | Default |
| --- | --- | --- |
| `site.name` | string | `copot` |
| `site.tagline` | string | empty string |

### Localization

| Identifier | Type | Default |
| --- | --- | --- |
| `localization.timezone` | string | `UTC` |
| `localization.locale` | string | `en_US` |
| `localization.date_format` | string | `Y-m-d` |
| `localization.time_format` | string | `H:i` |

The M1.8 Installer may replace these initial values during installation.

M1.7 locale values use the pattern `^[a-z]{2}_[A-Z]{2}$`. The Admin Settings UI offers only `en_US` and `id_ID`, with `en_US` as the default. Locale availability in M1.7 does not translate the Admin UI; the Admin UI remains English.

The supported date format options are:

* `Y-m-d`
* `d/m/Y`
* `m/d/Y`
* `d M Y`

The supported time format options are:

* `H:i`
* `h:i A`

---

## Validation

* Site name is required, cannot be empty after trimming, and is limited to 150 characters for M1.7.
* Site tagline may be empty and is limited to 255 characters for M1.7.
* Timezone must match a valid PHP timezone identifier.
* Locale must match `^[a-z]{2}_[A-Z]{2}$` and M1.7 accepts only `en_US` or `id_ID` through the UI.
* Date format must be one of `Y-m-d`, `d/m/Y`, `m/d/Y`, or `d M Y`.
* Time format must be either `H:i` or `h:i A`.
* Namespace and key identifiers cannot be created freely through the Admin Settings UI.
* Invalid values must produce a controlled rejection and must not be stored silently.

---

## Admin UI Scope

M1.7 implements these routes under the configured admin path:

```text
GET  /admin/settings
POST /admin/settings
```

`/admin` is an example using the default configurable admin path.

The page contains two sections:

* General
* Localization

Access requires `settings.update`. The POST route requires CSRF validation. The UI does not provide an arbitrary namespace/key editor or setting-definition create/delete controls.

The implemented form contains these explicit whitelisted fields:

* `site_name` maps to `site.name`.
* `site_tagline` maps to `site.tagline`.
* `localization_timezone` maps to `localization.timezone`.
* `localization_locale` maps to `localization.locale`.
* `localization_date_format` maps to `localization.date_format`.
* `localization_time_format` maps to `localization.time_format`.

Unknown posted fields are ignored and cannot create definitions or overrides. GET reads effective values exclusively through `SettingsService`. POST checks `settings.update`, validates CSRF, validates all six values through `SettingsService` before the first write, and then persists the six overrides in one PDO transaction.

Validation failures return a controlled English error summary with status `422`, preserve submitted values, and leave previous overrides unchanged. Database persistence failures roll back the transaction and return a generic `503` storage-unavailable response without exposing SQL details. Successful writes use POST/Redirect/GET and redirect to the configured admin settings path with minimal success feedback.

The Admin Settings UI remains English regardless of the selected locale. It is not an arbitrary settings editor and does not provide create, delete, or reset-to-default controls.

---

## Runtime Integration

M1.7 runtime integration provides:

* `site.name` is used in the common admin browser title as `<Page Title> | <site.name>` and is escaped by the admin views.
* M1.7 does not change admin headings, menu labels, or public theme branding.
* Active locale is available through `Application::locale()` without translating the Admin UI.
* Date and time formats are available for later application use.

Timezone is applied after the database and Settings service are available but before route and module request handling. A valid persisted override wins; otherwise the code-defined `UTC` default applies. Active timezone is available through `Application::timezone()`.

Settings storage reads catch `PDOException` only. If the settings table is missing or a storage read fails, known settings resolve to their code-defined defaults for the remainder of that request. Deserialization, stored-type, and definition-validation failures are represented by `SettingsException`; `SettingsService::get()` catches that domain exception around the individual override and returns only its definition default. It does not catch `Throwable`, rewrite the invalid row, discard other valid overrides, or expose raw exception and SQL details.

Existing modules are not required to migrate all configuration reads to Settings in M1.7.

---

## Module Direction

Future modules may own namespaces such as `content`, `taxonomy`, or `commerce`. A module remains responsible for defining, validating, and presenting its own module-specific settings.

Module-specific definitions and UI are outside M1.7. The Settings service must not depend on Content, Taxonomy, Theme, or business modules; Core and modules may depend on the Settings service.

---

## Explicit Exclusions

M1.7 does not include:

* Translation engine
* Multilingual content
* Translation management UI
* Per-user preferences
* Per-user timezone or locale
* Module-specific settings UI
* Secrets management
* SMTP or email configuration
* Cache or server configuration
* Environment variable editor
* Logo or favicon upload
* Core brand palette editing before Branding Foundation and Settings Manager
* Semantic color-mapping editor
* Theme-specific advanced color settings
* Custom CSS
* Media Library integration
* Feature flags
* Settings cache layer
* Settings revision/history
* Import/export
* Public Settings API
* Public Settings UI
* Public theme title integration
* Flash message infrastructure
* Generic date/time formatter
* Multisite settings
* Installer integration
* Arbitrary setting creation from admin
