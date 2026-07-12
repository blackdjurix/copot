# M3.2 Settings Manager Contract

## Status

M3.2 Settings Manager is in preparation. Scope and batch structure are locked by this document. Runtime implementation has not started.

The exact five-batch structure refines the Stage 2 four-batch planning envelope using current repository evidence. The additional boundary separates approval of existing Core Settings route/definition ownership from domain behavior, Admin integration, hardening, and completion; it does not change the approved M3 sequence.

M3.1 Users & Access is complete and merged to `main` through `5c4cf8c`. Its 504 focused assertions, complete M2.4 unified regression chain, and manual Admin verification pass remain the incoming baseline.

## Objective

Build an administrator-facing Settings Manager on the existing Core Settings foundation. The manager organizes registered application/site settings in the configured Admin shell, validates and persists controlled typed values, and preserves the single existing settings system.

Settings Manager must not create a second configuration system or expose environment/runtime secrets as editable application settings.

## Existing Foundation Audit

Reusable capability already present:

* `SettingDefinition` owns namespace, key, supported type, default, validation, allowed values, and metadata.
* `SettingsRegistry` owns code-defined definitions and namespace discovery. Core definitions currently cover Site Name, Tagline, Logo/Favicon descriptors, Timezone, Locale, Date Format, and Time Format.
* `SettingsRepository` owns PDO-backed override lookup, namespace listing, upsert, existence checks, and deletion in the existing `settings` table.
* `SettingsService` owns effective fallback, typed serialization/deserialization for string/integer/boolean/float/JSON, validation, set/delete, and controlled storage degradation.
* `Application::settings()` exposes the service; runtime consumers already use effective values.
* Core Admin routes provide configured-path Settings GET/POST, explicit field allowlisting, transactional validation-before-write, CSRF, Admin shell rendering, sanitized errors, and fixed Logo/Favicon controls.
* `settings.update` is seeded, mapped to the seeded administrator role, used by navigation/routes, and covered by existing Admin and compatibility tests.

Current gaps:

* the Admin surface is a fixed Core route/view rather than a manager-owned contribution;
* only six scalar fields are wired into the grouped form, plus fixed Site Asset controls;
* field presentation is not derived from a controlled manager-facing definition contract;
* no module contribution contract exists for registered settings sections;
* manager-specific domain, authorization, configured-path, failure, and integration regression is not isolated as an M3.2 suite;
* ownership transition from the existing Core Settings page is not yet approved.

## Settings Boundary

Editable application settings are registered, non-secret values with explicit definitions, defaults, types, validation, and ownership. Initial examples are the existing site identity text and localization values. Logo/Favicon descriptors remain controlled by Site Asset workflows rather than generic JSON fields.

The following are never editable through Settings Manager:

* database host, name, username, or password;
* application secrets, keys, tokens, credentials, or `auth.json`;
* `.env` or arbitrary environment variables;
* filesystem credentials or storage paths;
* PHP, Apache, server, session-cookie, or deployment configuration;
* arbitrary unregistered namespace/key creation.

## Permission Contract

M3.2 reuses the existing `settings.update` permission for both opening and saving the editable manager surface, in addition to the configured base `admin.access` guard.

No `settings.read` permission is introduced during preparation. There is no approved read-only Settings persona, and adding a split permission now would create provisioning and compatibility cost without a concrete workflow. A future read-only requirement must be proposed separately with explicit navigation, route, sensitive-value, and upgrade behavior.

## In Scope

* Settings Manager module/ownership skeleton consistent with the existing Module Manager lifecycle;
* controlled discovery and grouping of registered editable definitions;
* supported reusable typed fields derived from explicit definition metadata;
* preservation of existing Site and Localization behavior;
* validation-before-write and atomic multi-field save behavior;
* reuse of `settings.update`, configured Admin URL, Admin shell, CSRF, sanitized errors, and existing success/error patterns;
* safe persistence and fallback through `SettingsService` and `SettingsRepository`;
* compatibility for Site Branding and fixed Site Asset controls;
* focused domain, integration, security, and compatibility coverage;
* manual Admin verification and documentation closure.

An existing-install upgrade artifact may enter a later implementation batch only if a proven schema or permission change is approved. Preparation adds no schema change or SQL artifact.

## Explicitly Out of Scope

* `.env` editor, secrets management, or credential storage;
* server/PHP/Apache/deployment configuration editor;
* multisite or tenant-specific settings;
* Theme Customizer, visual page builder, or Custom CSS;
* Module Manager installation/enablement UI;
* Database Upgrade / Migration System implementation;
* Admin UX Refinement 1;
* public Settings API;
* settings import/export, version history, rollback, or audit-log platform;
* arbitrary key creation or generic database editor;
* cache platform, queues, background workers, or external configuration service;
* multilingual content or per-user locale/timezone.

## Core Freeze and Approval Points

Module-local design and existing public services must be exhausted first. Two potential Core touchpoints require separate approval before implementation:

1. Manager-facing read-only definition discovery. `SettingsService` does not currently expose definition metadata or namespaces, while the registry is constructed privately inside `Application`. The preferred minimal proposal is a generic read-only Settings service contract; exposing the container or database is forbidden.
2. Route/view ownership transition. Existing `/settings` routes and `resources/views/admin/settings.php` are Core-owned. A manager module cannot register a colliding route. The transition must preserve configured Admin paths, permission/CSRF/error behavior, Site Asset controls, and compatibility without leaving duplicate routes.

These are approval points, not implementation authorization. No Core source changes occur during preparation.

## Batch Plan

### Batch 1 — Contract and Ownership Foundation

Goal: close the focused repository audit, approve the definition-discovery and route-ownership boundaries, and establish a module-local manager skeleton without changing behavior.

Main areas: M3.2 contract, module metadata/skeleton, ownership map, focused baseline tests, separately approved minimal Core extension point if required.

Acceptance: existing Settings behavior is inventoried; permission and secret boundaries are executable test requirements; route collision and Site Asset ownership have one approved transition plan.

Validation: focused baseline plus existing Settings/Admin regression.

Non-goals: new fields, UI redesign, schema changes, migration runner, Admin UX refinement.

Core touchpoint: definition discovery and route ownership only if separately approved.

### Batch 2 — Manager Domain and Field Contract

Goal: implement manager-local section/field mapping over registered editable definitions and safe multi-field validation/save orchestration.

Main areas: manager services/value objects, supported field metadata mapping, validation aggregation, transactional save behavior.

Acceptance: only registered editable definitions are exposed; supported types render deterministically; all values validate before persistence; failed validation preserves prior stored values.

Validation: domain tests for grouping, type mapping, validation, unknown definitions, and atomic failure.

Non-goals: routes/views, module-contributed sections, secrets, import/export.

Core touchpoint: none expected beyond the approved Batch 1 contract.

### Batch 3 — Admin Routes and Presentation

Goal: provide configured-path manager routes, grouped Admin presentation, save flow, and compatibility with fixed Site Asset controls.

Main areas: module routes, Admin navigation, manager views, form actions, notices/errors, existing Settings route transition.

Acceptance: authorized administrators can open/save; values persist after reload; inline errors remain; Site/Localization and Site Asset behavior remain available without route collisions.

Validation: focused route/view integration, configured Admin path fixture, escaping, PRG success behavior.

Non-goals: Admin UX Refinement 1, Theme Customizer, public API.

Core touchpoint: execute only the previously approved route/view ownership transition.

### Batch 4 — Security and Compatibility Hardening

Goal: prove authorization, CSRF, validation containment, storage failure behavior, secret exclusion, and compatibility.

Main areas: permission matrix, failure fixtures, sanitized Admin errors, regression against branding/runtime consumers.

Acceptance: `admin.access` and `settings.update` are both required; authorization precedes CSRF/lookup/mutation; invalid CSRF and invalid values do not mutate; no environment secret is rendered or accepted; configured path and unrelated permissions remain stable.

Validation: security/integration tests and relevant M2/M3 compatibility chain.

Non-goals: new permission split, migration system, infrastructure editor.

Core touchpoint: none expected.

### Batch 5 — Completion and Manual Verification

Goal: run unified M3.2 regression, manual Admin verification, documentation sync, and completion audit.

Main areas: regression gate, manual checklist, roadmap/contract completion evidence.

Acceptance: focused M3.2 suites pass; required platform/M3.1 compatibility passes; manual verification passes; no unresolved runtime, schema, security, or Core-boundary blocker remains.

Validation: unified automated chain, lint/diff checks as applicable, manual browser verification.

Non-goals: release, M3.3 implementation, deferred UX work.

Core touchpoint: none.

## Manual Verification Plan

* an administrator with `admin.access` and `settings.update` can open Settings;
* a user missing either permission is denied with the established Admin behavior;
* an editable setting can be saved and remains after reload;
* invalid values are rejected and prior stored values remain unchanged;
* missing or invalid CSRF is rejected without mutation;
* configured non-default Admin paths move every manager route/action and leave no default-path duplicate;
* unrelated registered settings and runtime consumers continue to work;
* Site Name, Tagline, localization, Logo, and Favicon workflows remain compatible;
* HTML input is escaped and controlled errors remain in the Admin shell where eligible;
* database credentials, application secrets, `.env`, keys, and server configuration are neither rendered nor editable.

## Completion Gate

M3.2 is complete only when all five batches pass their gates, approved Core touchpoints are minimal and reviewed, the existing Settings system remains singular, automated and manual verification pass, documentation matches runtime behavior, and M3.3 has not begun early.
