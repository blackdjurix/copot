# M3.7 Theme Manager Preparation Contract

## Purpose and status

### Post-merge status

WU1–WU6 are complete and integrated into `main` at
`667ae1f0dbb8079ea0420a107bc1795c43cc5bea` by fast-forward with no merge
commit. Post-merge documentation closure commit `8dea71c82a4076c8b9d399047031e0a1ad18b0c6` preceded this correction. Objective automated acceptance and reachable browser/presentation
review passed; feature containment and local/remote feature-branch cleanup
passed. Full M3.7 is `NRP CONFIRMED`. The real-settings Theme Settings/color-control spot-check remains
deferred and non-blocking because the bundled Default Theme declares no
settings. No known implementation, security, schema, package, or integration
blocker remains. Release, tag, and publication remain not started. The next
target is M3.8 Media Library preparation, not implementation.

M3.7 defines and delivers an official first-party `theme-manager` Admin module over the
existing Core Theme System. The preparation contract remains the scope
authority for the implementation work. M3.6 Navigation
Manager is closed with WU1–WU6 and full M3.6 `NRP CONFIRMED`; M3.7 preparation
was independent of that closure. WU1–WU6 implementation and objective automated
acceptance are complete and fast-forward integrated into `main` at
`667ae1f0dbb8079ea0420a107bc1795c43cc5bea`; reachable browser and human
presentation review passed. Feature containment and branch cleanup passed.
Full M3.7 is `NRP CONFIRMED`.

### WU1 implementation state

WU1 implementation is present on `feature/m3.7-theme-manager` as a compatibility
baseline. Fresh schema provisioning, the idempotent existing-
installation upgrade, baseline module activation, package inclusion, and the
tolerant catalog companion API are implemented. The focused WU1 compatibility
test passes 16 assertions covering healthy plus malformed discovery, unavailable
roots, bounded diagnostics, registry non-mutation, fresh and existing
permission provisioning, module activation, and package inclusion.

WU2 implementation adds the declarative metadata, controlled capability,
contained screenshot, and versioned theme-settings definition contract. It
normalizes optional descriptors into `ThemeDefinition` without adding
persistence, activation, Admin routes, or runtime settings behavior. The
focused WU2 definition contract covers valid normalization and rejection of
malformed, duplicate, unsupported, incompatible, and path-escaping definitions.

WU3 implementation adds the Core `ThemeLifecycle` catalog and activation path.
It joins fresh discovery, bounded diagnostics, registry rows, and active state
without mutation during reads. Activation always performs fresh preflight and
atomically refreshes the normalized registry snapshot and single active
frontend state, with rollback preservation on persistence or postcondition
failure. No Admin workspace, settings persistence, or filesystem mutation is
included in WU3. At WU3 delivery, full M3.7 had not yet reached closure; the
final integrated state is recorded above as `NRP CONFIRMED`.

WU4 implementation adds the first-party Theme Manager Admin workspace. The
enabled module registers configured-path `GET /themes`, protected screenshot
delivery, and `POST /themes/{theme_id}/activate` routes. The workspace reads
only `ThemeLifecycle::inventory()`, requires both `admin.access` and
`themes.manage`, and routes activation only through
`ThemeLifecycle::activate()`. It presents deterministic healthy, active,
inactive, discovered, stale, invalid, unavailable, empty, and catalog-error
states with escaped bounded metadata, normalized screenshot placeholders or
images, PRG success notices, and sanitized controlled failures. No settings
forms, setting-value writes, runtime resolution, Navigation assignment, or
later-work-unit behavior is included. Focused WU4 automation, predecessor
regressions, package checks, targeted runtime synchronization, and authenticated
browser checks pass; browser review found no known Theme-workspace defect after
narrow 320px overflow containment in the shared Admin Shell. Visual human
review remains limited to hierarchy, screenshot usefulness, activation-warning
comprehension, and final presentation quality.

WU5 implementation adds the controlled Theme settings workspace and Core runtime
resolution. Theme overrides use the existing `settings` table and Settings
primitives under a deterministic, length-safe Theme namespace mapper; no second
settings table is introduced. Save and reset operations validate every field
before writing and use the established owned-transaction/savepoint boundary,
including preservation of caller-owned outer transactions. Reset removes all
overrides in the Theme namespace, including obsolete fields. Corrupt or unreadable
overrides fall back to declared defaults.

Core resolves only the active registry Theme's normalized settings metadata and
exposes the deterministic effective values intrinsically as `$themeSettings` to
both content and layout rendering. Runtime resolution does not require the
`theme-manager` module or filesystem discovery; malformed active metadata fails
closed to an empty controlled settings array. Theme settings display declared
`supports.navigation_locations` in deterministic read-only form and perform no
Navigation writes. WU5 focused evidence covers typed controls, namespace mapping,
validation, inactive-value preservation, reset behavior, runtime isolation, and
malformed metadata handling.

The reachable WU4/WU5 browser and human presentation review passed. The actual
Theme Settings form and color-control visual spot-check remains deferred and
non-blocking because the bundled Default Theme declares no settings; it will be
performed later with a real settings-bearing Theme. The broader deferred review
criteria remain visual hierarchy, screenshot usefulness, activation-warning
comprehension, settings-form clarity, color-control usability, true 200% zoom,
320px presentation, and final Theme workspace quality.

Deferred Item: `DI-M3.7-WU5-01` — Real-settings Theme Settings and
color-control spot-check. Status: Deferred and Unscheduled. It is non-blocking
until a real settings-bearing Theme makes the review materially possible; it
does not authorize Theme implementation or a future milestone.

Objective WU6 security, regression, documentation, and integration-readiness work
is complete in the feature branch. Discovery bounds `theme.json` metadata,
isolates malformed, missing, unavailable, and corrupted Theme inputs with generic
diagnostics, and validates contained screenshot size and detected MIME. Activation
and Theme settings routes authorize before CSRF and mutation, require coherent
submitted targets, reject malformed or oversized values, and preserve the
transactional atomicity established by WU3 and WU5. Runtime Theme settings remain
active-Theme-only and independent of the Admin module; Navigation locations remain
read-only. Automated WU6 evidence also checks package manifest inclusion/exclusion
and the clean-install/upgrade validation surface. Integration completed at
`667ae1f0dbb8079ea0420a107bc1795c43cc5bea`; feature containment and branch
cleanup passed. Release, tag, and publication remain separate and not started.

The existing Core Theme System remains the sole owner of filesystem discovery,
registry persistence, active-theme state, activation, loading, rendering, view
resolution and overrides, controlled assets, and runtime theme-setting
resolution. Theme Manager is an Admin presentation, validation-orchestration,
and controlled-write surface over that system. It must not introduce a second
active-theme store, lifecycle engine, or public class named `ThemeManager`.

## Locked architecture

### Active theme and settings

Active frontend state remains in the existing `themes` registry and Core
lifecycle. Themes declare settings and defaults through a controlled,
declarative `theme.json` contract; executable PHP definition manifests are not
allowed.

Theme Manager uses the existing Settings persistence primitives and `settings`
table. It owns Admin presentation, validation orchestration, and controlled
writes. Core owns runtime resolution so frontend rendering does not depend on
the Admin module remaining enabled. Values for inactive themes are preserved;
only the active theme's effective settings are applied during frontend
rendering. No second theme-settings table is permitted.

The initial vocabulary is intentionally narrow:

* Types: `string`, `integer`, `float`, `boolean`.
* Controls: `text`, `number`, `checkbox`, `select`, `color`.
* Validation: `required`, `allowed_values`, `min`, `max`, `max_length`, and
  controlled format validators.

Arbitrary JSON, PHP callbacks, HTML, CSS, secrets, uploads, media descriptors,
and filesystem paths are not generic editable fields.

### Metadata and activation

Required metadata is `id`, `name`, `version`, `type`, and `entry.layout`.
Optional metadata is `description`, `author`, `screenshot`, `supports`, and
`settings`. Screenshot support is optional, local, contained inside the theme
folder, and has a placeholder fallback. Live frontend preview is excluded.

Immediately before activation, the target is rediscovered and revalidated for
required files, contained paths, metadata, capabilities, and setting
definitions. Registry refresh and active-state switching are atomic. If
preflight or persistence fails, the previous active theme remains active. The
contract does not promise automatic rollback after arbitrary trusted PHP
rendering failure. The Admin Shell remains independent from frontend themes
and is the recovery surface.

Only local discovered themes may be registered/refreshed and activated. ZIP
installation, upload, remote download, marketplace, uninstall, and filesystem
deletion are excluded.

### Navigation boundary

Themes declare supported location keys. Navigation owns menu data, assignments,
target resolution, and resolved render data. Themes own markup, styling,
composition, and responsive rendering. Theme Manager may display declared
locations and link to Navigation, but must not edit Navigation assignments or
duplicate Navigation Manager behavior.

### Branding boundary

Historical documentation describes a future Core palette and semantic mapping,
but the current implementation does not provide that complete runtime contract.
M3.7 may support theme-declared color settings, but must not claim to override
an implemented Core palette or semantic mapping without source evidence. Custom
CSS remains excluded.

### Permission and recovery

Use the existing permissions `admin.access` and `themes.manage`. Do not add a
read-only or split activation/settings permission without a separately approved
persona and compatibility decision. Authorization must precede CSRF checks and
all mutation; CSRF, PRG, escaping, safe diagnostics, and fail-closed behavior
follow established Admin contracts.

## Locked work units

Exactly six responsibility-level work units are authorized:

1. **Contract, Catalog Compatibility, Provisioning, and Baseline Evidence** —
   lock the contract and `theme-manager` boundary, provision `themes.manage`,
   establish package/fresh-install and existing-install expectations, and prove
   healthy plus invalid/unavailable catalog behavior. No Admin workspace or
   settings form.
2. **Metadata, Capability, Screenshot, and Settings Definition Contract** —
   normalize descriptors; validate metadata, capabilities, contained screenshots,
   and the controlled settings schema, defaults, sections, fields, and
   validation vocabulary; reject duplicate, malformed, or unsupported
   definitions. No persistence workflow or Admin routes.
3. **Activation and Lifecycle Orchestration** — define the joined
   filesystem/registry inventory and discovered, registered, stale, unavailable,
   invalid, active, and inactive states; implement fresh preflight, atomic
   refresh/switch, and recovery behavior. No install, uninstall, or file
   mutation.
4. **Admin Theme Workspace** — provide configured-path listing, state and
   metadata presentation, screenshot/placeholder handling, controlled
   activation, permission/CSRF/PRG/escaping/sanitized failures, Admin placement,
   and responsive/accessibility evidence.
5. **Theme Settings Workspace, Runtime Resolution, and Navigation Boundary** —
   provide per-theme controlled forms, validation-before-write, atomic save,
   reset-to-default, inactive-value preservation, active-only runtime
   resolution, intrinsic frontend exposure, and read-only Navigation locations.
6. **Security, Design Adjustment, Regression, Documentation, Integration, and
   Lifecycle Closure** — cover hardening, payload guards, authorization order,
   diagnostics, stale/unavailable isolation, Theme/Settings/Navigation/Module/
   package/clean-install regression, design classification, documentation
   closure, integration, and branch lifecycle closure. Release, tag, and
   publication remain separate.

## Admin design checkpoint

WU4 and WU5 must classify each applicable surface as exactly one of:
`redesign required`, `retouch required`, `review only`, or `NO CHANGE REQUIRED`.
Human review is limited to visual hierarchy, screenshot usefulness,
activation-warning comprehension, settings-form clarity, color-control
usability, and final presentation quality. Objective behavior is accepted by
automation and source/runtime evidence.

## Dependency, scope, and acceptance gates

After M3.6 reconciliation, the M3.7 preparation dependency was `INDEPENDENT`.
The original preparation gate required evidence for GPT evaluation and did not
decide final M3.7 preparation NRP or authorize implementation. That historical
gate is closed; the final integrated state is recorded above as `NRP CONFIRMED`.

No PHP source, tests, schema or upgrade SQL, configuration, package manifest,
runtime workspace, browser validation, implementation branch, merge, branch
deletion, release, tag, or publication is authorized by this preparation
contract. Narrow Core corrections may be considered only when source evidence
justifies error-tolerant healthy-theme discovery with bounded per-theme
failures, atomic activation of a freshly validated definition, or runtime
active-theme settings resolution independent of Theme Manager. No broader Core
refactor is authorized.
