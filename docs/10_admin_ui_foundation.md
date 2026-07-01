# Admin UI Foundation

## Purpose

Admin UI Foundation is the first M2 Platform Capability.

It provides reusable, theme-independent infrastructure for building consistent administrative interfaces across Core and modules.

The foundation centralizes:

* admin path and URL generation;
* Admin Shell and page rendering;
* shared visual tokens and static assets;
* reusable admin UI patterns;
* permission-aware navigation;
* active-state resolution;
* minimal dashboard-widget contribution.

Admin UI Foundation is infrastructure.

It is not a business module, management module, admin theme marketplace, visual page builder, or complete Internal Dashboard.

---

## Milestone

### M2.1 Admin UI Foundation

Status: Complete. All six batches, automated regression coverage, and manual browser verification are complete.

Latest release before this milestone:

```text
v0.8.0
```

The target release version is v0.9.0.

---

## Objective

Build a reusable Admin UI Foundation that is:

* independent from the frontend Theme System;
* compatible with conventional PHP shared hosting;
* centralized around one admin URL and rendering contract;
* accessible and responsive by default;
* reusable by Core and module-owned admin pages;
* small enough to remain understandable without a frontend framework.

M2.1 must improve presentation infrastructure without changing Content, Taxonomy, Settings, authentication, or other domain behavior.

---

## Existing Foundation

M1.4.1 introduced a minimal Admin Shell with:

* configurable single-segment admin path;
* same-path admin login;
* CSRF-protected logout;
* `admin.access` permission protection;
* static Dashboard navigation;
* minimal responsive layout;
* request-scope `AdminNavigation`.

M1.5, M1.6, and M1.7 added Content, Taxonomy, and Settings administration on top of that shell.

The existing implementation proves the basic flow but ownership remains distributed.

Current limitations include:

* repeated admin-path validation;
* repeated shell-rendering closures;
* literal `/admin` fallbacks in runtime templates;
* inline and duplicated styles;
* duplicated form and alert markup;
* inconsistent tables and actions;
* no shared active-navigation resolution;
* no stable navigation item IDs or explicit ordering;
* no dashboard contribution registry;
* incomplete accessibility and small-screen behavior.

M2.1 consolidates these patterns without replacing the existing domain workflows.

---

## Architecture Position

Admin UI Foundation belongs to M2 Platform Capabilities.

```text
M1 Framework Foundation
->
M2 Admin UI Foundation
->
M3 Core Module management interfaces
->
M4 Business/Application Module management interfaces
```

Core Modules and Business/Application Modules may depend on Admin UI Foundation.

Admin UI Foundation must not depend on:

* Content;
* Taxonomy;
* Settings Manager;
* Media Library;
* Theme Manager;
* Navigation Manager;
* Internal Dashboard;
* any Business/Application Module.

---

## Ownership Boundary

Admin UI Foundation owns:

* normalized admin-path access;
* admin URL generation;
* Admin Shell rendering;
* shared admin page context;
* admin navigation registration and resolution;
* dashboard-widget registration and resolution;
* static admin assets;
* shared layout and UI patterns;
* baseline accessibility and responsive behavior.

Admin UI Foundation does not own:

* authentication rules;
* domain repositories;
* Content lifecycle;
* Taxonomy lifecycle;
* Settings persistence;
* module lifecycle;
* frontend theme rendering;
* user-customizable dashboard layouts;
* analytics;
* management functionality from M3.

---

## Admin Path and URL Strategy

The admin path remains configured through:

```text
config/admin.php
```

The configured value remains a lowercase single-segment slug.

Examples:

```text
admin
backend
administrator
dapur
```

M2.1 must establish one centralized owner for:

* path validation;
* normalized path retrieval;
* base admin URL generation;
* child admin URL generation.

Runtime templates and module admin views must not contain fallback literals such as:

```text
/admin
```

Changing the configured admin path must move all Admin UI routes, links, form actions, redirects, and navigation destinations consistently.

Subdirectory deployment support is not introduced by M2.1 unless separately approved.

---

## Admin Page and Shell Rendering

M2.1 must replace distributed shell-rendering closures with one centralized page-rendering contract.

The renderer should receive controlled context such as:

* page title;
* site name;
* current user;
* current request path;
* admin base URL;
* resolved navigation;
* notices;
* page actions;
* rendered page content.

The Admin Shell should expose clear structural regions:

```text
Admin Shell
|
|-- Navigation
|-- Top Bar
|-- Page Heading
|-- Page Actions
|-- Notices
|-- Main Content
|-- Dashboard Widgets
```

The page renderer must not inject unrestricted application services into templates.

Views should receive only the data needed for presentation.

---

## View and Partial Strategy

M2.1 may use focused PHP views and partials.

Expected direction:

```text
resources/views/admin/
|
|-- layout.php
|-- login.php
|-- dashboard.php
|-- partials/
|   |-- alert.php
|   |-- field.php
|   |-- actions.php
|   |-- panel.php
|   |-- table.php
|   |-- empty-state.php
```

Exact filenames may change during implementation when justified by the existing code.

M2.1 must not introduce:

* a universal component framework;
* a template engine;
* frontend Theme System resolution;
* arbitrary hooks in every markup location;
* deeply abstract render trees.

Use focused reusable patterns only where current duplication proves the need.

---

## Static Asset Strategy

Admin assets must be directly deployable from the public document root.

Planned public namespace:

```text
public/admin-assets/
```

Expected baseline:

```text
public/admin-assets/css/admin.css
```

JavaScript may be added only when a specific interaction cannot be handled adequately with HTML and CSS.

M2.1 does not require:

* Node.js;
* npm;
* a bundler;
* minification tooling;
* a manifest;
* a development daemon;
* symlink-based publishing;
* a CSS framework;
* a JavaScript framework.

Admin assets must remain independent from frontend theme assets.

Changing or disabling the active frontend theme must not change the Admin UI.

---

## Visual Direction

The visual direction is a neutral modern administrative interface.

### Desktop direction

* dark neutral navigation;
* light main content canvas;
* white or off-white panels;
* clear page heading and action areas;
* medium-to-large surface radius;
* subtle borders and restrained shadows;
* compact controls where density is useful;
* relaxed spacing between major sections;
* one configurable primary accent token;
* semantic status colors used sparingly.

### Mobile direction

* single-column content flow;
* desktop navigation collapsed into a compact mobile pattern;
* large enough touch targets;
* page actions kept near their related content;
* stacked cards and form sections;
* controlled table overflow;
* readable content at narrow widths and increased zoom.

The visual references guide layout rhythm and surface treatment.

They do not require:

* analytics charts;
* product-specific dashboard cards;
* a green environmental theme;
* a monochrome commerce theme;
* a pastel HR theme;
* copying any referenced product interface;
* converting the admin system into a mobile application.

---

## Design Token Direction

Admin CSS uses a small set of centralized custom properties.

Candidate groups:

```text
Color
Typography
Spacing
Radius
Border
Shadow
Layout width
Control height
Focus ring
Responsive breakpoints
```

Candidate naming direction:

```css
--admin-color-bg
--admin-color-surface
--admin-color-navigation
--admin-color-text
--admin-color-muted
--admin-color-border
--admin-color-primary
--admin-color-success
--admin-color-warning
--admin-color-danger
--admin-color-info
```

Exact values are implementation decisions within the approved visual direction. These tokens are internal to Admin UI and do not read Site Branding. Any future brand-color integration must be limited, explicit, contrast-safe, and separately approved. The broader branding boundary is defined in `docs/11_branding_foundation.md`.

Tokens are implementation infrastructure, not a user-selectable admin skin system.

---

## Shared UI Patterns

M2.1 should formalize the currently repeated patterns.

### Layout

* shell;
* sidebar or primary navigation;
* top bar;
* page container;
* page heading;
* description;
* page actions;
* content sections.

### Feedback

* success notice;
* error alert;
* warning notice;
* informational notice;
* validation summary;
* field-level validation state.

### Forms

* labeled text input;
* select;
* textarea;
* checkbox;
* field description;
* field error;
* fieldset;
* form actions;
* primary submit;
* cancel link;
* destructive submit.

### Content Surfaces

* panel;
* card;
* status display;
* empty state;
* table wrapper;
* action group;
* simple list.

These patterns should remain lightweight PHP markup plus semantic CSS classes.

M2.1 does not include:

* modal framework;
* toast framework;
* complex data grid;
* rich client-side form framework;
* drag-and-drop components;
* visual component editor.

---

## Navigation Contract

Admin navigation remains permission-aware and request-scoped.

M2.1 should extend navigation entries with:

* stable ID;
* label;
* URL;
* explicit order;
* required permissions;
* active-state matching.

Visibility continues to use permission checks.

A navigation item with multiple registered permissions is visible when the current user has at least one approved permission, unless a later milestone explicitly changes that contract.

Initial navigation remains flat.

M2.1 does not include:

* database-backed navigation configuration;
* nested menu management;
* drag-and-drop ordering;
* user-customizable navigation;
* Navigation Manager;
* required icon-library integration.

Active items should expose semantic state such as:

```html
aria-current="page"
```

Ordering must not depend accidentally on module bootstrap order.

Duplicate stable IDs must be handled deterministically and must not silently create ambiguous navigation entries.

---

## Dashboard Widget Contract

M2.1 introduces a minimal permission-aware dashboard-widget registry.

A widget contribution should have:

* stable ID;
* title;
* explicit order;
* optional required permissions;
* controlled render callback or view;
* controlled presentation context.

The existing dashboard status information should become the first consumer.

The registry is an extension contract, not the M3 Internal Dashboard.

M2.1 does not include:

* database-backed widget persistence;
* user-selectable widgets;
* drag-and-drop layout;
* dashboard analytics;
* external data feeds;
* charting framework;
* per-user layouts;
* configurable grid placement;
* visual dashboard builder.

Duplicate widget IDs must be rejected or resolved deterministically.

Widget output must follow the shared Admin UI patterns.

---

## Accessibility Baseline

M2.1 should establish an accessibility baseline for migrated Admin UI pages.

Included direction:

* semantic page landmarks;
* skip link;
* visible keyboard focus;
* `aria-current` for active navigation;
* alert and status roles;
* explicit labels for controls;
* field errors associated through `aria-describedby`;
* invalid fields marked with `aria-invalid`;
* semantic tables with headings and `scope`;
* meaningful button and link labels;
* keyboard-operable controls;
* sufficient contrast;
* usable content at 200% zoom;
* no interaction requiring pointer input only.

The runtime document language should use the active locale when safely available.

M2.1 does not implement the broader localization or translation capability.

---

## Responsive Baseline

The existing responsive shell behavior provides an initial reference but is not sufficient for all admin pages.

M2.1 should cover:

* narrow mobile widths;
* compact tablets;
* normal desktop widths;
* long headings and labels;
* dense action groups;
* wide tables;
* form controls;
* navigation collapse;
* 200% browser zoom.

Tables must use an explicit responsive strategy, such as a controlled horizontal wrapper, rather than overflowing the whole page.

Responsive behavior must remain usable without requiring a JavaScript framework.

---

## Migration Scope

M2.1 should migrate presentation for:

* admin login;
* Admin Dashboard;
* Core Settings;
* Content administration;
* Taxonomy administration.

Migration means:

* using centralized admin URLs;
* using centralized page rendering;
* using shared assets and classes;
* using shared feedback and form patterns;
* using shared responsive and accessibility behavior.

Migration must not change:

* route meaning;
* permission requirements;
* validation rules;
* database behavior;
* CSRF behavior;
* Content statuses;
* Taxonomy rules;
* Settings definitions;
* domain workflow.

The public `/login` view remains outside M2.1.

The installer remains outside M2.1 and is only a visual and accessibility reference.

---

## Included Scope

M2.1 includes:

* centralized admin path and URL ownership;
* centralized Admin Shell and page renderer;
* shared page context;
* static Admin CSS;
* design-token baseline;
* responsive shell baseline;
* accessibility baseline;
* shared alert and validation patterns;
* shared form patterns;
* shared panel and card patterns;
* shared action patterns;
* shared table and empty-state patterns;
* stable navigation IDs;
* explicit navigation ordering;
* permission-aware navigation;
* active-navigation resolution;
* minimal dashboard-widget registry;
* migration of existing Core, Content, and Taxonomy admin presentation;
* removal of runtime template dependence on literal `/admin`;
* automated structural and behavioral tests;
* manual browser and accessibility verification.

---

## Excluded Scope

M2.1 does not include:

* public login redesign;
* installer redesign;
* frontend theme integration;
* admin theme marketplace;
* user-selectable admin skins;
* dark-mode setting;
* visual page builder;
* SPA architecture;
* CSS framework;
* JavaScript framework;
* frontend build pipeline;
* icon-library dependency;
* nested admin navigation;
* Navigation Manager;
* full Internal Dashboard;
* analytics;
* charting framework;
* database-backed dashboard customization;
* Content Manager / Workspace functionality;
* Taxonomy Manager functionality;
* Settings Manager functionality;
* Users & Access management UI;
* Media Library;
* Editor Framework;
* Image Service;
* localization implementation;
* translation engine;
* database schema changes;
* new third-party dependencies;
* subdirectory deployment expansion;
* domain behavior changes.

---

## Shared Hosting Requirements

M2.1 must remain compatible with the existing shared-hosting baseline.

Requirements:

* PHP 8.2 minimum;
* static assets served directly from the public document root;
* no background process;
* no Node runtime;
* no build step required during deployment;
* no advanced server configuration;
* no required symlink;
* no daemon;
* no long-running worker;
* no dependency on Imagick;
* no dependency on frontend theme assets.

The Admin UI should continue working after a normal file upload deployment.

---

## Security Rules

M2.1 must preserve the existing authentication, permission, CSRF, and escaping boundaries.

Admin UI infrastructure must:

* escape plain text output;
* avoid exposing filesystem paths;
* avoid exposing exception details;
* preserve permission checks before rendering protected pages;
* preserve CSRF protection for state-changing actions;
* avoid arbitrary view-path rendering;
* avoid unrestricted callback or template access;
* avoid accepting navigation or widget IDs without validation;
* prevent untrusted values from becoming raw CSS or HTML.

UI refactoring must not weaken domain validation.

---

## Testing Strategy

### Automated Tests

Candidate automated coverage:

* admin path validation;
* base and child URL generation;
* non-default admin path behavior;
* absence of runtime `/admin` fallback leakage;
* centralized page rendering;
* controlled page context;
* navigation stable IDs;
* navigation duplicate handling;
* navigation ordering;
* navigation permission visibility;
* navigation active-state resolution;
* widget stable IDs;
* widget duplicate handling;
* widget ordering;
* widget permission visibility;
* layout landmarks;
* active-navigation `aria-current`;
* alert and status roles;
* field label and error association;
* table semantic structure;
* responsive-wrapper markup;
* route, permission, CSRF, and redirect regression.

M2.1 should prefer a lightweight native PHP test or smoke harness unless a test dependency is separately approved.

### Manual Tests

Manual verification should include:

* admin login;
* dashboard;
* Settings;
* Content list/create/edit;
* Taxonomy type and term pages;
* default admin path;
* non-default path such as `/dapur`;
* keyboard-only navigation;
* visible focus;
* validation errors;
* success notices;
* destructive actions;
* empty states;
* wide tables;
* long labels and content;
* 320px and 375px widths;
* existing 720px breakpoint behavior;
* normal desktop widths;
* 200% browser zoom;
* frontend theme change with no Admin UI effect;
* representative shared-hosting static asset delivery.

---

## Batch Plan

### Batch 1 — Admin URL and Page Rendering

Status: Complete.

* centralize admin path validation;
* centralize admin URL generation;
* introduce one Admin Page/Shell renderer;
* migrate existing shell-rendering ownership;
* remove runtime template fallback dependence on `/admin`;
* add focused tests.

### Batch 2 — Shared Assets and Shell Baseline

Status: Complete.

* add static Admin CSS;
* establish design tokens;
* implement the approved dark-navigation and light-content direction;
* establish responsive shell behavior;
* add skip link and visible focus;
* improve active-navigation semantics;
* add focused tests.

### Batch 3 — Core Admin Patterns

Status: Complete.

* add focused alert, field, action, panel, table, and empty-state patterns;
* migrate admin login;
* migrate Dashboard;
* migrate Core Settings;
* preserve existing Core behavior;
* add focused tests.

### Batch 4 — Module Admin Migration

Status: Complete.

* migrate Content admin views;
* migrate Taxonomy admin views;
* preserve domain behavior and permissions;
* normalize forms, alerts, tables, and actions;
* add focused tests.

### Batch 5 — Dashboard Registry and Extension Boundary

Status: Complete.

* introduce minimal dashboard-widget registration;
* migrate existing status information as the first widget;
* validate stable IDs, order, permissions, and rendering;
* confirm no M3 Internal Dashboard behavior enters scope;
* add focused tests.

### Batch 6 — Regression and Manual Verification

Status: Complete.

* run automated regression coverage;
* verify configurable admin path;
* run accessibility checks;
* run responsive browser matrix;
* verify shared-hosting asset delivery;
* confirm frontend Theme System independence;
* fix only M2.1 regressions;
* prepare final documentation and release review.

---

## Completion Record

M2.1 implementation and verification are complete.

Completion evidence includes:

* all six implementation batches complete;
* Batch 1 integration coverage passing with one explicit environment-dependent permission skip;
* Batch 1 through Batch 5 smoke suites passing;
* unified M2.1 regression gate passing;
* responsive layout, 320px and 375px widths, 200% zoom, keyboard flow, focus visibility, and live static-asset delivery manually verified;
* no database schema change, third-party dependency, frontend build pipeline, or frontend Theme System coupling introduced.

The milestone targets v0.9.0. Merge, tag, and release remain user-owned Git/GitHub operations.

---

## Acceptance Criteria

M2.1 is complete when:

* all admin URLs use one centralized owner;
* no migrated runtime template depends on fallback literal `/admin`;
* all migrated admin pages use one centralized page renderer;
* Admin UI assets are static and theme-independent;
* shared UI patterns replace proven duplicated markup;
* navigation supports stable IDs, order, permissions, and active state;
* active navigation exposes semantic state;
* minimal dashboard-widget registration works with deterministic ordering and permission visibility;
* existing status information renders through the widget contract;
* admin login, Dashboard, Settings, Content, and Taxonomy use the shared foundation;
* existing domain behavior remains unchanged;
* keyboard focus and feedback semantics meet the documented baseline;
* tables and action groups remain usable at narrow widths;
* the configured non-default admin path works without `/admin` leakage;
* automated tests pass;
* manual browser and accessibility checks pass;
* no database schema change is introduced;
* no third-party dependency or build pipeline is introduced;
* the frontend Theme System remains independent;
* no M3 manager functionality is implemented.

---

## Future Evolution

Future milestones may build on Admin UI Foundation.

Examples:

```text
Users & Access
Settings Manager
Media Library
Theme Manager
Content Manager / Workspace
Taxonomy Manager
Navigation Manager
Internal Dashboard
Business/Application Module admin interfaces
```

Those systems may consume the Admin UI Foundation.

They must not force Admin UI Foundation to depend on their domain logic.

The future Branding Foundation does not change this dependency direction. M2.1 retains internal Admin UI color tokens; future Admin brand integration, if approved, remains limited and contrast-safe. See `docs/11_branding_foundation.md`.
