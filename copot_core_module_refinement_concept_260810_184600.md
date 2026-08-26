# COPOT — Core Module Refinement Concept

Status: CONCEPT / PLANNING INPUT ONLY  
Authority: GPT-side concept; not repository authority  
Implementation Authorization: NONE  
Scope: Admin Shell shared refinement + per-core-module refinement concepts  
Dashboard: special case, intentionally not fully defined here

## 1. Purpose

This concept consolidates human UI/UX validation findings for COPOT Admin Shell pages and each core module surface.

It is intended as a planning input for later refinement workstreams/contracts.

This concept does not:
- authorize implementation;
- promote any item into repository authority;
- redefine accepted functional behavior;
- absorb confirmed defects into refinement scope;
- make Dashboard a standard CRUD page.

## 2. Shared Admin Shell Refinement Principles

### 2.1 Page headers

Pages should use a consistent page-header pattern comparable to the current Content page.

Applies where missing, including:
- Taxonomy;
- Terms;
- Term Detail;
- Users;
- Roles;
- Modules;
- Navigation;
- Navigation Menu;
- Edit Navigation Item;
- Settings.

Dashboard remains a special case.

### 2.2 Clickable list rows

Where a list row represents one primary destination, the whole row should be the primary click target.

This should remove redundant `Open` / `Edit` actions where appropriate.

Applies conceptually to:
- Content;
- Taxonomy;
- Users;
- Roles;
- Modules;
- Navigation;
- Navigation Menu.

Action buttons that perform a distinct state mutation remain separate.

### 2.3 Action presentation

Inline text actions should be replaced with compact rectangular buttons where the action is operationally significant.

Examples:
- Draft;
- Publish;
- Restore;
- Archive;
- Change;
- Adjust;
- Remove;
- Change Database.

### 2.4 Density and sizing

Current page elements are generally too large.

Refinement should reduce visual density through:
- smaller body/control typography;
- reduced vertical padding;
- tighter grid/row spacing;
- more horizontal composition where appropriate;
- fewer unnecessarily full-width controls.

Candidate direction:
- body text around 13–14 px;
- labels around 12–13 px;
- page titles around 20–24 px;
- compact controls around 28–32 px;
- normal controls around 32–36 px;
- list rows around 36–44 px.

Exact dimensions remain implementation/design decisions.

### 2.5 Flat visual language

Target direction:
- flat design;
- square/rectangular surfaces;
- no rounded corners unless a specific component requires an exception;
- consistent borders, spacing, and hierarchy.

### 2.6 Brand color integration

Admin Shell should eventually consume configurable brand color(s) from Settings.

This is a design-system direction, not yet an implementation contract.

### 2.7 Dark / night mode

Dark mode is a candidate future admin appearance option.

Status: OPEN PRODUCT/DESIGN DECISION.

### 2.8 Admin Shell identity

Potential Admin Shell top-menu identity options:
- Website Name;
- Site Logo.

Status: OPEN PRODUCT/DESIGN DECISION.

## 3. Dashboard

Dashboard is intentionally treated as a special case and is not fully specified in this concept.

Current note:
- `Framework Status` in System Overview appears redundant with System Health.

Dashboard requires separate information-architecture review rather than direct reuse of generic CRUD-page composition.

## 4. Content Refinement

1. Entire content row should act as the primary navigation target; redundant `Edit` button can be removed.
2. Draft / Publish / Restore / Archive should use rectangular buttons rather than text actions.
3. Edit Content breadcrumb should follow the hierarchy:
   - `Dashboard › Contents › Edit Content`, or
   - `Dashboard › Contents › <Content ID / Name / Slug>`.
4. After Featured Image is set, controls should appear immediately below the image:
   - Change
   - Adjust
   - Remove
5. Remove redundant controls:
   - `Clear` vs `Remove Media`;
   - `Change` vs `Select Media`.
6. Featured-image status copy should be reduced. Keep only one useful instruction/status line and place it above the image preview if needed.
7. Select Media window:
   - increase spacing between preview and buttons;
   - keep `Confirm Crop` and `Choose Another` placement;
   - place `Cancel` on the same action row, right-aligned to the modal/window;
   - respect modal padding;
   - Cancel should use destructive/red visual treatment only if semantics justify it; otherwise reserve red for destructive actions.

## 5. Media Refinement

1. Increase spacing between Search and media inventory, using Content page spacing as reference.
2. Add at least:
   - Thumbnail view;
   - List view.
3. Rename `Upload Media` to `Upload`.
4. In Media Detail, place `Save Changes` to the right of the relevant textbox/form area where practical.
5. Place `Delete` on the same action row, right-aligned to the modal/window.
6. Media deletion requires explicit confirmation/warning before destructive execution.

## 6. Taxonomy Refinement

1. Add consistent page header to:
   - Taxonomy;
   - Terms;
   - Term Detail.
2. Entire row should be primary navigation target; redundant `Open` button can be removed.
3. Re-evaluate whether `Built-in Type` metadata is useful:
   - keep if it communicates immutability/system ownership;
   - remove if it adds no operator value.
4. Product/functional clarification required:
   - Can users add taxonomy types?
   - Are current classifications intentionally limited?
   - What exactly does `Structure` mean?
5. Term Detail currently consumes excessive horizontal space for low-volume content; redesign toward a more compact detail/form layout.

## 7. Forms Refinement

### 7.1 List page

Forms list structure is conceptually similar to Content and should use the same shared page framework where applicable.

### 7.2 Create/Edit form density

Current per-field blocks are too tall.

Candidate direction:
- labels and inputs may share rows where practical;
- use horizontal space more effectively;
- field groups may use accordion/collapsible panels;
- completed field blocks may be collapsed.

### 7.3 Required control

`Required` checkbox is visually oversized.

Target pattern:
`[] Required`

Placement should be near the top of each field configuration group.

### 7.4 Field Key terminology

`Field Key` is currently ambiguous and potentially conflicts conceptually with database key terminology.

This is a critical terminology/behavior clarification item.

Required audit must determine whether `field_key` means:
- machine identifier;
- field slug;
- storage key;
- schema key;
- field name;
- another internal identity.

Do not rename this label until actual behavior is confirmed.

## 8. Form Manager Functional Audit Requirement

Human validation produced a concrete defect signal:

- `Form Name` repeatedly errors during Create Form.

This must be audited before Form Manager refinement is promoted into an authoritative implementation contract.

Audit should determine:

1. Valid naming rules for Form Name.
2. Whether current UI explains those rules.
3. Whether valid-looking names fail incorrectly.
4. Whether failure originates from:
   - frontend validation;
   - backend validation;
   - slug/key generation;
   - persistence;
   - database constraint;
   - error mapping.
5. Authoritative meaning of `field_key`.
6. Whether `field_key` is user-defined or generated.
7. Whether uniqueness is:
   - per form;
   - global;
   - conditional.
8. Reserved-name rules, if any.
9. Whether simple create → save → edit roundtrip works.
10. Whether field add/remove/reorder persists correctly.
11. Whether current error messages represent actual failures correctly.

Classification rule:
- confirmed functional defects must be resolved separately from UI refinement;
- do not hide a functional defect inside a refinement workstream.

Current status:
`FORM MANAGER FUNCTIONAL AUDIT REQUIRED`

## 9. Users Refinement

1. Add standard page header.
2. Entire row should be primary navigation target; redundant `Open` action can be removed.

## 10. Roles Refinement

1. Add standard page header.
2. Entire row should be primary navigation target; redundant `Edit` action can be removed.
3. Permissions should use accordion/collapsible grouping.

## 11. Modules Refinement

1. Add standard page header.
2. Entire row should be primary navigation target; redundant `Open` action can be removed.
3. Add Module should use a separate compact card:
   - title: `Add Module package (ZIP)`;
   - file chooser field;
   - `Add Module` button to the right of the chooser;
   - compact button width, not full-width.
4. Module health:
   - detailed module-specific health should be available;
   - System Health may provide aggregate/summary status;
   - Module Manager should expose deeper per-module health than Dashboard.
5. Module inventory should eventually show brief severity/status such as:
   - Warning;
   - Critical.
6. Product naming consistency must be resolved:
   - current names mix `Content Module` and `Module Manager`;
   - determine whether product-facing naming standard is `Module`, `Manager`, or a distinct Product Name taxonomy.
7. Add module search.
8. Search/Add Module placement should be arranged so both remain clear and compact.

## 12. Navigation Refinement

1. Add standard page header to:
   - Navigation;
   - Navigation Menu;
   - Edit Navigation Item.
2. Entire row should be primary navigation target where there is one primary destination.
3. Remove redundant row-level `Edit` / `Manage Items` actions accordingly.
4. Navigation Menu page should not keep an unnecessary Edit button when row navigation already provides the destination.
5. Edit Navigation Detail currently consumes too much horizontal space.
6. First layout refinement:
   - label + textbox on one row where appropriate;
   - continue review after this density improvement.

## 13. Themes Refinement

1. Theme Inventory currently lacks a clear container/surface for items.
2. Add at least three viewing modes:
   - Table;
   - Thumbnail;
   - Extra Large.

Exact behavior and persistence of view preference remain open.

## 14. Settings Refinement

1. Add standard page header.
2. Fix inconsistent and excessive gap between tabs and tab content.
3. Tabs should not be hard-coded merely to reserve future space.
4. Empty tabs should not be displayed.
5. New tabs should appear only when a real settings group exists.
6. Branding layout should use side-by-side composition where practical rather than purely vertical stacking.
7. Admin Shell identity candidate:
   - Website Name;
   - Logo.
8. Brand-color setting should eventually feed Admin Shell visual theming.
9. Dark/night mode remains a candidate, not yet locked.

## 15. Cross-Module Shared Design System Direction

The per-page findings should ultimately reconcile into a shared Admin Shell design system covering:

- page header;
- page toolbar;
- search;
- action card;
- list/table surface;
- clickable rows;
- detail/form surface;
- action hierarchy;
- modal/dialog spacing;
- destructive confirmation;
- control density;
- typography scale;
- spacing/grid;
- flat square-corner treatment;
- responsive behavior;
- brand color;
- accessibility.

Do not implement each module as a visually isolated redesign.

## 16. Planning Structure

Recommended conceptual decomposition:

### A. Admin Shell Shared Refinement Foundation

Shared visual/layout behavior.

### B. Per-Core-Module Refinement

Separate refinement scopes for:
- Content;
- Media;
- Taxonomy;
- Forms;
- Users;
- Roles;
- Modules;
- Navigation;
- Themes;
- Settings.

### C. Dashboard Refinement

Separate special-case workstream because Dashboard requires distinct information architecture.

## 17. Functional / Product Questions That Must Not Be Treated as Styling

Open questions requiring audit or product decision:

- Taxonomy type creation capability.
- Meaning of Taxonomy `Structure`.
- Form Name validation rules / defect status.
- Authoritative meaning of `field_key`.
- Module Product Name / Module / Manager naming model.
- Module health-reporting architecture and severity presentation.
- Admin Shell Logo vs Website Name.
- Dark/night mode.
- Any functional defect discovered during human UI validation.

## 18. Authority and Lifecycle Boundary

This file is a Concept only.

It may later be:
- referenced by Workplan;
- split into module-specific Concepts;
- promoted in part into authoritative repository contracts;
- superseded or refined.

It does not authorize:
- source changes;
- documentation promotion;
- branch creation;
- implementation;
- release/tag/publication.

Confirmed defects must use a corrective lifecycle appropriate to their scope and must not be disguised as refinement items.