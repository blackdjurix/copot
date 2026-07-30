# M3.7 Theme Manager Preparation Contract

## Purpose and status

M3.7 prepares an official first-party `theme-manager` Admin module over the
existing Core Theme System. The preparation contract remains the scope
authority for the implementation work. M3.6 Navigation
Manager is closed with WU1–WU6 and full M3.6 `NRP CONFIRMED`; M3.7 preparation
is independent of that closure and is evaluated from the final documentation,
Git, and remote evidence. Full M3.7 is `NRP NOT REACHED`.

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
included in WU3. Full M3.7 NRP remains `NRP NOT REACHED`.

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

After M3.6 reconciliation, the M3.7 preparation dependency is `INDEPENDENT`.
The preparation gate must report evidence for GPT evaluation and must not decide
final M3.7 preparation NRP or authorize implementation. Full M3.7 remains
`NRP NOT REACHED` until its separately authorized implementation and closure.

No PHP source, tests, schema or upgrade SQL, configuration, package manifest,
runtime workspace, browser validation, implementation branch, merge, branch
deletion, release, tag, or publication is authorized by this preparation
contract. Narrow Core corrections may be considered only when source evidence
justifies error-tolerant healthy-theme discovery with bounded per-theme
failures, atomic activation of a freshly validated definition, or runtime
active-theme settings resolution independent of Theme Manager. No broader Core
refactor is authorized.
