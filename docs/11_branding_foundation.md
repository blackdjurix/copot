# Branding Foundation

## Purpose

Branding Foundation defines the Core color contract shared by Webcore consumers without turning Site Settings into a visual-theme editor. MR.2 WU2 promotes the Webcore Basic Branding baseline and its bounded palette validation/resolution into the Webcore System Manager contract at `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`.

The original M2 foundation text below remains the historical foundation
boundary. MR.2 WU2 is the separately approved delivery contract for the
Webcore Basic Branding baseline; it does not authorize a universal Theme
engine, generic color system, or unbounded runtime-setting change.

---

## Ownership Model

Branding responsibilities are split deliberately:

```text
Core Branding Foundation
-> stable palette and default semantic contract

Webcore System Manager
-> edits the Webcore Basic Branding baseline values

Settings Manager
-> may later provide richer delivery without taking over Webcore authority

Theme + Theme Manager
-> active-theme-scoped overrides and advanced presentation controls

Admin UI Foundation
-> independent internal Admin color tokens
```

Core defines the stable baseline. Themes retain presentation authority. Admin UI remains operationally independent from frontend themes and Site Branding.

---

## Webcore Palette Contract

Webcore has four Core palette values:

* main;
* accent;
* neutral dark;
* neutral light.

The future Branding Foundation must provide validation, safe fallback values, and a consumer contract that exposes the resolved four-color palette plus the locked default semantic mapping.

Site Settings may eventually change only the four palette values. Site Settings must not redefine which component or semantic role consumes each color.

---

## Locked Default Semantic Mapping

Webcore owns a default semantic distribution that is not editable from Site Settings:

| Semantic role | Core palette source |
| --- | --- |
| Navigation | main |
| Page background | neutral light |
| Surface | neutral light |
| Body text | neutral dark |
| Primary actions | accent |
| Links | accent |
| Focus | accent |

The distribution principle keeps neutral colors dominant, uses main for structure and identity, and reserves accent for emphasis and interaction. This is a design relationship, not a literal pixel-ratio calculation.

Consumers that use the Core contract must receive a complete resolved palette and mapping. Invalid or unavailable values must fall back to safe Core defaults rather than producing a partial contract.

---

## Theme Capability Boundary

A theme may:

* not support the Core brand palette;
* use the Core palette and locked default mapping;
* override palette values for the active theme;
* override semantic mapping for the active theme;
* provide advanced color settings;
* control buttons, hover states, links, navigation, surfaces, borders, gradients, and component-specific colors.

Theme overrides must remain scoped to the active theme. They must not write back to or silently mutate the Core palette.

Advanced color settings are exclusively a Theme capability. Branding Foundation does not define a universal advanced-color schema for every theme.

The future M3 Theme Manager reads declared theme capabilities and provides UI for theme-specific palette overrides, semantic-mapping overrides, and advanced color settings.

Custom CSS belongs to a later Theme Manager enhancement. It is not part of the base Branding Foundation or Site Settings.

---

## Admin UI Boundary

M2.1 Admin UI Foundation uses internal Admin UI color tokens only.

Admin UI does not currently read Site Branding, the Core palette, or frontend Theme settings. The dark-navigation and light-content baseline remains an Admin-owned, contrast-safe operational design.

MR.2 WU2 may add only the explicit, contrast-safe Admin identity baseline
(Logo/Text and a selection from the four Core palette values). Admin UI remains
independent from the active frontend Theme and retains its own internal tokens;
advanced Admin branding integration remains deferred.

---

## Milestone Distribution

### M2.1 Admin UI Foundation

* Internal safe Admin color tokens only.
* Batch 1 Admin URL and Page Rendering is complete.
* Batch 2 Shared Assets and Shell Baseline is complete.
* Batch 3 Core Admin Patterns is the current focus.
* Historical M2.1 boundary; WU2 separately defines the bounded System Manager
  Basic Branding and Admin identity integration.

### MR.2 WU2 Webcore System Manager Baseline

* Core four-color palette contract.
* Locked default semantic mapping.
* Palette validation.
* Safe fallback behavior.
* Contrast-aware Neutral Dark / Neutral Light resolution for required foreground
  relationships.
* Basic Branding Site Name, Tagline, Logo, Favicon, and Admin identity baseline.
* Consumer contract.

### M3 Settings Manager

* A richer settings/branding delivery may be added only while preserving the
  Webcore System Manager authority and fallback.
* No Site Settings editor for the locked semantic mapping.

### M3 Theme Manager

* Read theme capabilities.
* Manage active-theme-scoped palette overrides.
* Manage active-theme-scoped semantic-mapping overrides.
* Provide advanced theme color settings.

### Later Theme Manager Enhancement

* Custom CSS.

### Future Branding Manager

A separate Branding Manager is justified only if branding scope expands beyond the four-color foundation to include capabilities such as:

* logo variants;
* favicon;
* social images;
* email or document branding;
* white-label behavior;
* multi-brand or per-tenant branding.

---

## Explicit Exclusions

The base Branding Foundation does not include:

* editable component-to-color mapping in Site Settings;
* advanced theme color settings;
* Custom CSS;
* Admin UI theme coupling;
* social-image, email, document, or white-label management;
* multi-brand or per-tenant branding;
* a database schema decision before the milestone is approved.

---

## Related Documents

* `docs/02_architecture.md`
* `docs/03_roadmap.md`
* `docs/05_theme_system.md`
* `docs/08_settings_system.md`
* `docs/10_admin_ui_foundation.md`
