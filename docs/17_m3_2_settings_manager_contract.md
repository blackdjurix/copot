# M3.2 Settings Manager Contract

## Status

M3.2 Settings Manager preparation and Batches 1–5 are complete. Batch 1 is committed and pushed at baseline commit `31d540a` and reached its implementation No-Return Point with manager route/view ownership, generic read-only definition discovery, and compatibility coverage active. Batch 2 Manager Domain & Field Contract passes its focused implementation, adversarial review, remediation, and completion gates with 94 assertions. Batch 3 Admin Routes and Presentation passes implementation, three focused remediation cycles, final completion review, and relevant regressions with 85 focused assertions. Batch 4 Security and Compatibility Hardening passes tests-only implementation, focused remediation, completion review, and relevant regressions with 145 focused assertions. Batch 5 Completion and Manual Verification passes final automated validation, manual and automated-assisted verification, completion audit, and documentation closure. Cumulative focused M3.2 coverage is 366 assertions. M3.2 is complete on its feature branch; M3.3 has not started.

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
* The `settings-manager` module owns configured-path Settings GET/POST, grouped dynamic scalar presentation, manager-domain validation and atomic persistence, CSRF, Admin shell rendering, sanitized errors, and fixed Logo/Favicon controls.
* `SettingsService::definitions()` and `definitionGroups()` provide stable identifier ordering, namespace filtering/grouping, and registered-only `SettingDefinition` discovery without storage mutation.
* `settings.update` is seeded, mapped to the seeded administrator role, used by manager navigation/routes, and covered by existing Admin and compatibility tests.

Current gaps:

* no M3.2 implementation or completion gap remains; Git checkpoint and subsequent milestone planning are separate user-owned steps.

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

Module-local design and existing public services must be exhausted first. Batch 1 approved and executed exactly two Core touchpoints:

1. Manager-facing read-only definition discovery. `SettingsService::definitions(?string $namespace)` returns deterministically ordered registered `SettingDefinition` objects, and `definitionGroups()` groups the same approved set by namespace. Neither API queries arbitrary stored rows, mutates storage, or exposes the registry/container/database.
2. Route/view ownership transition. Settings navigation, configured-path GET/POST, fixed Site Asset mutations, and the fixed compatibility view moved from `routes/admin.php` and `resources/views/admin/settings.php` to `modules/settings-manager/`. Core retains generic primitives, Admin shell/error/URL services, settings persistence, and Site Asset storage.

Fresh installation enables `settings-manager` through the existing installer-owned ModuleManager baseline. Existing installations explicitly install and enable the module through the existing ModuleManager lifecycle. No schema, runtime permission, or upgrade SQL change is required. Duplicate Core route/view ownership is absent.

## Batch Plan

### Batch 1 — Contract and Ownership Foundation

Goal: close the focused repository audit, approve the definition-discovery and route-ownership boundaries, and establish a module-local manager skeleton without changing behavior.

Main areas: M3.2 contract, module metadata/skeleton, ownership map, focused baseline tests, separately approved minimal Core extension point if required.

Acceptance: existing Settings behavior is inventoried; permission and secret boundaries are executable test requirements; route collision and Site Asset ownership have one approved transition plan.

Validation: focused baseline plus existing Settings/Admin regression.

Non-goals: new fields, UI redesign, schema changes, migration runner, Admin UX refinement.

Core touchpoint: definition discovery and route ownership only if separately approved.

Batch 1 result: complete, committed, and pushed at baseline commit `31d540a`. The module manifest, lifecycle-owned routes/view, deterministic registered-only discovery APIs, focused 42-assertion baseline, relevant platform compatibility, package-content coverage, and isolated clean-install verification pass. The fixed compatibility form established the route/view transition baseline later replaced by Batch 3 dynamic presentation. The No-Return Point is reached, and Batches 2–3 are complete.

### Batch 2 — Manager Domain and Field Contract

Goal: implement manager-local section/field mapping over registered editable definitions and safe multi-field validation/save orchestration.

Main areas: manager services/value objects, supported field metadata mapping, validation aggregation, transactional save behavior.

Acceptance: only registered editable definitions are exposed; supported types render deterministically; all values validate before persistence; failed validation preserves prior stored values.

Validation: domain tests for grouping, type mapping, validation, unknown definitions, and atomic failure.

Non-goals: routes/views, module-contributed sections, secrets, import/export.

Core touchpoint: none expected beyond the approved Batch 1 contract.

Batch 2 result: complete and pass. A validated module-local policy owns editability and presentation metadata for the six approved scalar definitions. Reusable deterministic section and field contracts map string, integer, float, and boolean types while generic JSON plus specialized Logo/Favicon descriptors remain excluded. The manager aggregates controlled validation errors, distinguishes optional omission from explicit empty input, validates all submitted/normalized candidates before persistence, and writes only valid deterministic candidates through a root transaction or caller-safe nested savepoint. Validation or persistence failure preserves prior stored values. Focused domain and integration coverage passes 94 assertions; Batch 1 Settings and directly relevant Branding, Settings, and Site Asset regressions pass. No Core, route, view, schema, SQL, runtime permission, or dependency change was introduced. Batch 3 now consumes this domain contract.

### Batch 3 — Admin Routes and Presentation

Goal: provide configured-path manager routes, grouped Admin presentation, save flow, and compatibility with fixed Site Asset controls.

Main areas: module routes, Admin navigation, manager views, form actions, notices/errors, existing Settings route transition.

Acceptance: authorized administrators can open/save; values persist after reload; inline errors remain; Site/Localization and Site Asset behavior remain available without route collisions.

Validation: focused route/view integration, configured Admin path fixture, escaping, PRG success behavior.

Non-goals: Admin UX Refinement 1, Theme Customizer, public API.

Core touchpoint: execute only the previously approved route/view ownership transition.

Batch 3 result: complete and pass. The lifecycle-owned configured-path route/view now renders grouped dynamic text, number, checkbox, and select fields from `SettingsManager::sections()` and accepts nested `settings[domain.identifier]` payloads delegated to `SettingsManager::save()`. Validation failures preserve effective and submitted values, expose field/form errors in the Admin shell, retain escaped invalid select values as controlled invalid-current options, use collision-safe deterministic HTML IDs, and return controlled Admin-shell `503` responses for validation-redisplay storage failure. Configured-path GET, save redirect, scalar and Site Asset actions have executable coverage with no default `/admin` duplicate. Generic JSON and Logo/Favicon descriptors remain outside scalar rendering, and specialized Logo/Favicon workflows remain unchanged. Batch 3 integration passes 46 assertions and presentation coverage passes 39 assertions, for 85 focused assertions and 221 cumulative focused M3.2 assertions through Batch 3. Relevant M2.1 Admin UI, M2.3 Branding/Site Asset, and M2.3 unified regressions pass. No Core, schema, SQL, runtime permission, dependency, or Batch 2 domain change was introduced. Batch 4 and Batch 5 have not started.

### Batch 4 — Security and Compatibility Hardening

Goal: prove authorization, CSRF, validation containment, storage failure behavior, secret exclusion, and compatibility.

Main areas: permission matrix, failure fixtures, sanitized Admin errors, regression against branding/runtime consumers.

Acceptance: `admin.access` and `settings.update` are both required; authorization precedes CSRF/lookup/mutation; invalid CSRF and invalid values do not mutate; no environment secret is rendered or accepted; configured path and unrelated permissions remain stable.

Validation: security/integration tests and relevant M2/M3 compatibility chain.

Non-goals: new permission split, migration system, infrastructure editor.

Core touchpoint: none expected.

Batch 4 result: complete and pass. The work is tests-only: `tests/settings_manager_batch4_security.php` passes 126 assertions and `tests/settings_manager_batch4_compatibility.php` passes 19 assertions, for 145 focused Batch 4 assertions and 366 cumulative focused M3.2 assertions through Batches 1–4. Executable coverage proves that `admin.access` and `settings.update` are both required; authorization precedes CSRF, definition discovery, effective-value lookup, scalar validation/persistence, and Logo/Favicon mutation; missing and invalid CSRF return controlled Admin-shell `419` responses without downstream work or state mutation; and unknown, uneditable, secret-like, Logo, and Favicon scalar identifiers are rejected without partial persistence. A route-level failure on the second scalar write proves rollback of the first write, restoration of all prior effective values, transaction closure, controlled Admin-shell `503`, and suppression of raw PDO, SQLSTATE, exception, stack-trace, and credential-like detail. Batch 3 configured-path/default-path exclusion remains executable, Logo/Favicon remain specialized, no `settings.read` or other permission was introduced, and relevant M2.1 Admin UI, M2.3 Branding/Site Asset and unified, plus M3.1 permission regressions pass. No runtime, Core, schema, SQL, permission, manifest, dependency, route, view, or production behavior change was introduced. Direct executable injection for rollback/savepoint cleanup-failure branches remains deferred and non-blocking. Batch 5 has not started.

### Batch 5 — Completion and Manual Verification

Goal: run unified M3.2 regression, manual Admin verification, documentation sync, and completion audit.

Main areas: regression gate, manual checklist, roadmap/contract completion evidence.

Acceptance: focused M3.2 suites pass; required platform/M3.1 compatibility passes; manual verification passes; no unresolved runtime, schema, security, or Core-boundary blocker remains.

Validation: unified automated chain, lint/diff checks as applicable, manual browser verification.

Non-goals: release, M3.3 implementation, deferred UX work.

Core touchpoint: none.

Batch 5 result: complete and pass. Batch 5 is validation, manual verification, and documentation closure only; it introduces no code, test, runtime, Core, schema, SQL, permission, manifest, dependency, route, view, or production behavior change. Final automated completion validation passes all eight focused suites for 366 assertions: Batch 1 contract/integration 13/29, Batch 2 domain/integration 62/32, Batch 3 integration/presentation 46/39, and Batch 4 compatibility/security 19/126. Required M2.1 Admin UI; M2.3 Branding, Site Asset, Logo/Favicon integration, and unified; plus M3.1 permission/access hardening regressions pass; PHP lint, repository checks, and boundary audit pass. The manual matrix passes: authorized access, scalar and localization save/reload/restoration, invalid containment, CSRF rejection, Logo/Favicon workflows, escaping/secret exclusion, and runtime-consumer sanity were manually verified; missing `admin.access`, missing `settings.update`, and configured non-default Admin path were verified automated-assisted, while storage-failure handling passes automated/runtime-assisted. Localization values persist and restore correctly; the existing Content Updated and Users Last Login/Updated views still render raw `Y-m-d H:i:s` timestamps because they do not yet consume Localization Settings, which is not an M3.2 blocker or Batch 5 scope. No code or test gap, environment blocker, or unresolved Core/schema/runtime/security blocker remains. Documentation closure is complete, M3.3 has not started, and direct executable injection for rollback/savepoint cleanup-failure branches remains deferred and non-blocking.

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

M3.2 completion gate: met. All five batches pass their gates; approved Core touchpoints remain minimal and reviewed; the existing Settings system remains singular; registered-only scalar fields, generic JSON and secret/environment exclusion, specialized Logo/Favicon workflows, configured Admin paths, controlled Admin-shell errors, validation-before-write, and atomic save behavior remain preserved. Automated and manual verification pass, documentation matches runtime behavior, and M3.3 has not begun early.
