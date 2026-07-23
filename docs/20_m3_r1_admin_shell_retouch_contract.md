# M3.R1 Admin Shell Retouch 1 Preparation Contract

## Purpose

M3.R1 is a horizontal, presentation-only Admin Shell work unit between the
completed M3.4 Content Manager milestone and M3.5 Taxonomy Manager. It locks
the visual authority, reviewed-surface inventory, evidence model, approval
gates, and closure boundary required before any M3.R1 UI implementation.

M3.R1 does not create a new manager, domain capability, navigation system, or
Core abstraction. It may later approve presentation changes and Admin
navigation ordering within the existing permission-aware, request-scoped
navigation contract.

## Milestone Position and Entry Status

The locked sequence is:

```text
M3.4 closure (NRP CONFIRMED)
->
M3.R1 Admin Shell Retouch 1
->
M3.5 Taxonomy Manager
```

M3.4 closure is confirmed at the verified `main` anchor
`8bdd48463c26ef8c43683455eba3d1a99c3e4aa7`. The preparation branch is created
from that anchor as `feature/m3.r1-admin-shell-retouch-1`.

Entry criteria:

1. Full M3.4 closure and NRP confirmation.
2. Clean, synchronized `main` at the approved starting anchor.
3. M3.1 Users & Access, M3.2 Settings Manager, and M3.3 Module Manager Admin
   surfaces available for review.
4. The Canonical Style source is readable and its correspondence with the
   extracted prototype is verified.
5. This preparation contract is reviewed before classification begins.

Current entry status: all five criteria are satisfied. The Canonical Style
source correspondence and corrected contract passed focused source review;
the preparation contract was committed, pushed, and fast-forward integrated
into synchronized `main`. Classification is the next gate. Final per-surface
classifications remain undecided, M3.R1 implementation remains unauthorized.
The post-merge documentation correction was committed and
pushed, and local and remote preparation branches were deleted after verified
containment; the preparation branch lifecycle is closed. M3.R1 preparation:
NRP CANDIDATE. Full M3.R1: NRP NOT REACHED. Final closure evaluation remains
pending after this documentation state is committed and synchronized;
classification is next, and the status labels do not authorize implementation.

## Authority and Source Relationship

Repository authority is read progressively from:

- `AGENTS.md`;
- `README.md`;
- `docs/03_roadmap.md`;
- `docs/16_m3_core_freeze_and_module_contract.md`;
- `docs/18_m3_4_content_manager_contract.md`;
- `docs/19_m3_admin_shell_design_adjustment_contract.md`;
- `docs/10_admin_ui_foundation.md`.

The structural and current implementation baseline is the committed Admin
Shell and Content workspace at HEAD `8bdd48463c26ef8c43683455eba3d1a99c3e4aa7`.
The baseline preserves behavior, permissions, routes, ownership, module
contracts, configured Admin paths, CSRF, and accessibility associations.

## Canonical Style Authority

The Canonical Style is the binding cross-page presentation standard for M3.R1,
but its visual evidence sources do not replace the project authority model.

### Governing Authority

For intended behavior, project constraints, architecture, ownership,
permissions, routes, domain contracts, and preservation requirements, governing
authority is:

1. applicable project instructions and locked contracts;
2. committed tests and implementation for proven current behavior;
3. relevant committed technical documentation;
4. README or roadmap state where relevant.

### Visual Evidence Stack

For visual comparison and Canonical Style normalization, use:

1. the approved prototype ZIP and matching extracted prototype;
2. the committed Admin Shell and Content implementation baseline;
3. existing committed Admin UI, responsive, navigation, and accessibility
   contracts, including the approved Admin Shell visual reference and any
   approved refinement plan;
4. approved M3.R1 classification decisions once recorded.

The approved prototype ZIP is
`K:/My Drive/Codex/copot/prototype/copot(6).zip`, and its matching extracted
prototype is `K:/My Drive/Codex/copot/prototype/copot-6-extracted`.

The ZIP and extracted directory currently correspond: both contain 16 files
with matching relative paths and SHA-256 content. Prototype evidence inspected
for preparation includes `css_files/admin.css`, `_dashboard.html`,
`_content.html`, `_users.html`, `_user_create.html`, `_user_details.html`,
`_roles.html`, `_roles_details.html`, `_role_create.html`, `_settings.html`,
`_modules.html`, and `_module_details.html`. The remaining taxonomy prototype
files are included in the inventory evidence and may be reviewed during
classification.

A prototype difference does not authorize a change. The prototype is visual and
UX evidence; it does not override locked project contracts or committed
behavior automatically. The committed baseline is the structural and
behavioral baseline, but it does not automatically make every existing visual
choice canonical. For every relevant difference, classification must identify
the affected surface, prototype evidence, committed-baseline evidence,
preserved contracts, proposed style decision, and explicit implementation
approval. Existing contracts and behavior remain preserved by default.

### Canonical Style rule status

Every Canonical Style rule or candidate rule must use exactly one of these
statuses:

- `CONFIRMED — PROTOTYPE AND BASELINE`: the prototype and committed baseline
  agree on the relevant rule for the affected surface;
- `CONFIRMED — COMMITTED CONTRACT`: the rule is required by a locked contract,
  regardless of prototype treatment;
- `PROTOTYPE-LED PROPOSAL — APPROVAL REQUIRED`: the prototype provides useful
  direction, but the rule is not binding until the affected surface is
  classified and implementation is approved;
- `UNRESOLVED`: evidence or project direction is insufficient to decide the
  rule;
- `EXCLUDED`: the rule or feature is outside M3.R1 or prohibited by a committed
  boundary;
- `NOT APPLICABLE`: the rule does not apply to the affected surface or state.

Each inventory item must record the rule or visual area, status, evidence
source, affected surfaces, preservation boundary, approval requirement,
validation implication, and unresolved dependency when applicable. Proposal and
unresolved statuses must not be described as final Canonical Style.

The following preparation rules are evidence records, not blanket
implementation approvals:

| Area | Preparation rule | Status |
|---|---|---|
| Shell composition | Persistent sidebar plus main content frame, top bar, page heading, actions, and responsive mobile drawer. | CONFIRMED — PROTOTYPE AND BASELINE |
| Navigation | Permission-aware, request-scoped navigation; preserve `Dashboard → Content → Taxonomy → Users → Roles → Modules → Settings`. | CONFIRMED — COMMITTED CONTRACT |
| Rhythm | Prototype token scale includes 4/8/12/16/20/24/32/40px spacing. | PROTOTYPE-LED PROPOSAL — APPROVAL REQUIRED |
| Density | Compact controls and information-dense lists with readable supporting text. | CONFIRMED — PROTOTYPE AND BASELINE |
| Panels | White surfaces, thin border, modest radius, and restrained shadow. | PROTOTYPE-LED PROPOSAL — APPROVAL REQUIRED |
| Typography | Strong page/title hierarchy, muted supporting text, readable labels, and compact metadata. | PROTOTYPE-LED PROPOSAL — APPROVAL REQUIRED |
| Actions | Primary, secondary, danger, link, compact, row, and contextual action treatments are distinct. | CONFIRMED — PROTOTYPE AND BASELINE |
| Lists and tables | Lists/tables use readable primary and supporting metadata, explicit action hierarchy, controlled overflow, and responsive row/card treatment rather than page-wide overflow. | CONFIRMED — PROTOTYPE AND BASELINE |
| Status | Semantic badges and status text use success, warning, danger, and information treatments. | CONFIRMED — PROTOTYPE AND BASELINE |
| Forms | Grouped fields, explicit labels, help text, field errors, summary/recovery presentation, and responsive stacking. | CONFIRMED — COMMITTED CONTRACT |
| Empty/unavailable states | States remain truthful, contextual, and non-editable where the current contract defers capability. | CONFIRMED — COMMITTED CONTRACT |
| Responsive behavior | Desktop, narrow mobile, drawer behavior, stacked layouts, and responsive rows require explicit review. | CONFIRMED — COMMITTED CONTRACT |
| Accessibility | Semantic landmarks, labels, focus visibility, keyboard operation, active-state semantics, and valid error associations are mandatory. | CONFIRMED — COMMITTED CONTRACT |
| Touch targets | Controls must remain usable on narrow screens; approximate 44px targets are the review expectation where applicable. | CONFIRMED — PROTOTYPE AND BASELINE |
| Color/contrast | Existing semantic color tokens and readable contrast are preserved; numeric contrast claims require measurement. | CONFIRMED — COMMITTED CONTRACT |
| Icons | Existing safe, decorative, current-color Admin icon contract is preserved. | CONFIRMED — PROTOTYPE AND BASELINE |
| Gradients | No gradient is binding unless explicitly approved for the affected surface. | UNRESOLVED |
| Notifications, command search, system status | Prototype examples do not override the committed omission/deferred boundaries. | EXCLUDED |

Exact visual values not supported by evidence remain `UNRESOLVED` rather than
being promoted to binding rules. A surface-specific rule may also be
`NOT APPLICABLE` when the reviewed surface does not contain that visual area.

## Reviewed Surface Inventory

The next classification phase must review the following complete boundary.

### Shared Admin Shell

- sidebar, grouping, labels, final order, icons, and active states;
- top bar, breadcrumb/content frame, Quick menu, and account control;
- shared page header and action treatment;
- mobile drawer, overlay, focus containment, Escape handling, and focus return;
- responsive behavior, semantic associations, and interaction states.

### M3.1 Users & Access

Repository surfaces:

- `modules/users-access/views/admin/list.php` — Users list;
- `modules/users-access/views/admin/create.php` — User create;
- `modules/users-access/views/admin/edit.php` — User edit;
- `modules/users-access/views/admin/roles-list.php` — Roles list;
- `modules/users-access/views/admin/roles-create.php` — Role create;
- `modules/users-access/views/admin/roles-edit.php` — Role edit.

The repository does not currently contain separate `user-detail.php` or
`role-detail.php` views. Prototype detail surfaces are evidence for comparison,
not proof that new detail routes or pages may be created. Permission selection,
assignment, validation, denial, and self-protection states are reviewed where
rendered by these existing views.

### M3.2 Settings Manager

- `modules/settings-manager/views/admin/settings.php` — Settings navigation,
  six-tab presentation, section forms, Branding presentation, validation,
  and truthful deferred/unavailable states;
- configured Admin path and shared-shell integration;
- permission-denied and controlled error states where rendered by the current
  contract.

### M3.3 Module Manager

- `modules/module-manager/views/admin/modules.php` — module list, filters,
  lifecycle actions, dependency/safety messaging, and empty/error states;
- `modules/module-manager/views/admin/module-detail.php` — module detail,
  diagnostics, dependency/safety messaging, and lifecycle actions;
- permission-filtered and unavailable states where rendered by the existing
  module contract.

### M3.4 Content baseline only

`modules/content/views/admin/list.php` and
`modules/content/views/admin/form.php` are not primary M3.R1 retouch targets.
They are structural, interaction, and Canonical Style comparison evidence only.

## Mandatory Classification Model

Every reviewed surface receives exactly one primary classification:

- `REDESIGN REQUIRED`;
- `RETOUCH REQUIRED`;
- `REVIEW ONLY`;
- `NO CHANGE REQUIRED`.

Each record must include:

1. surface identifier and owner;
2. current implementation evidence;
3. prototype evidence;
4. applicable Canonical Style rule;
5. classification and reason;
6. allowed presentation/navigation boundary;
7. preservation requirements;
8. required automated validation;
9. required desktop/mobile browser validation;
10. unresolved dependency or approval decision.

Preparation locks the inventory, method, evidence requirements, and approval
gate. It does not assign a final classification where visual judgment remains.

## Scope and Preservation

In scope: prototype inspection, Canonical Style extraction, contract and
roadmap/current-state documentation, inventory definition, classification,
acceptance and validation planning, and NRP planning.

Later implementation may change only approved presentation and Admin navigation
ordering within the current contract. It must preserve domain behavior,
permissions, routes, CSRF, schema, services, module ownership, Theme ownership,
Core architecture, configured Admin paths, data contracts, and existing
accessibility associations.

Out of scope: new UI capability, Navigation Manager, frontend Theme rendering,
domain changes, PHP routes/services/views outside an approved presentation
change, CSS/JavaScript implementation during preparation, schema/migrations,
permissions, tests during preparation, runtime synchronization, browser
validation during preparation, M3.5, release, tag, publication, and unrelated
Git work.

## Gates and Work-Unit Strategy

### Preparation gate

The contract, source relationship, prototype correspondence, inventory,
preservation rules, and validation plan are reviewed and accepted. No UI
implementation starts before this gate.

### Classification gate

Each inventory item has evidence-backed classification, approved style
direction, explicit boundaries, and unresolved decisions recorded. `NO CHANGE
REQUIRED` is valid when evidence supports it.

### Implementation gate

Only approved `RETOUCH REQUIRED` or `REDESIGN REQUIRED` records may enter
implementation. The smallest affected presentation files and focused tests are
selected just-in-time. A prototype detail remains a proposal until this gate.

### Validation gate

Focused automation, source review, lint/diff checks, responsive checks,
accessibility review, authenticated desktop/mobile browser evidence, limitation
disposition, and documentation closure must pass before NRP evaluation.

Likely strategy: one preparation contract followed by one or more narrowly
locked presentation work units. Batch count is not fixed in this contract.

## Validation Requirements

Focused automated validation must cover the affected shared shell and views,
while preserving existing navigation filtering/order, active states, configured
Admin paths, CSRF/ownership boundaries, error associations, and deferred states.
No test file is changed during preparation.

Minimum preparation-level browser matrix:

| Baseline | Required exercise |
|---|---|
| Desktop Standard — `1440 × 900` | Sidebar, top bar, Quick menu, account control, page header, primary content layout, list/table or responsive-row presentation, applicable forms and validation states, active navigation, and action hierarchy. |
| Mobile Standard — `390 × 844` | Mobile drawer, top-bar controls, content stacking, action wrapping, form layout, list/card-row conversion, touch targets, overflow/clipping, active states, and focus states. |
| Narrow Mobile Stress — `320 × 800` | Smallest supported layout behavior, horizontal overflow, label/action wrapping, compact row/card presentation, drawer/header usability, and minimum touch-target behavior. |
| Zoom and Accessibility — `1440 × 900` at `200%` browser zoom | Reflow, clipping, horizontal scrolling, keyboard focus visibility, control discoverability, and text/action readability. |

These are minimum baseline viewports. Classification may add evidence-specific
widths. Responsive breakpoints must be tested around affected layout
transitions when a retouch changes behavior.

Responsive checks include content-frame overflow, table/row strategy, action
wrapping, form stacking, drawer behavior, and usable 200% zoom. Unavailable
reliable numeric zoom or contrast measurement must be documented as a
validation limitation, not silently treated as a pass.

Accessibility checks include landmarks, labels, keyboard order, visible focus,
`aria-current`, drawer semantics, Escape and focus containment, error-summary
and field associations, semantic status messaging, permission-aware visibility,
and contrast measurement where tooling permits. A screenshot alone is not
complete accessibility evidence.

## Documentation, Git, and NRP

Closure documentation must record scope, every classification, evidence,
limitations, remediation, remaining approvals, validation, branch state, and
NRP status. M3.R1 uses its dedicated branch
`feature/m3.r1-admin-shell-retouch-1` and its own lifecycle. Branch creation,
integration, push, cleanup, and final changeset operations remain separately
authorized.

NRP Candidate is reached when approved classifications have been implemented or
closed as `NO CHANGE REQUIRED`, focused validation and browser/accessibility
evidence are complete, documentation is synchronized, and no unresolved
in-scope blocker remains.

NRP Confirmed requires the NRP Candidate record plus separately authorized Git
integration, final verification, clean synchronized state, branch-lifecycle
closure, and final changeset requirements. M3.5 remains blocked until this
boundary is confirmed.

Design completion does not authorize or imply release, tag, or publication.
Those remain separate project decisions.

## Unresolved Decisions Before Implementation

- Which prototype-led differences are approved for each reviewed repository
  surface.
- Whether any prototype-only sidebar status, notifications, command search, or
  other deferred surface is rejected, retained as reference, or separately
  approved; committed omission boundaries remain the default.
- Whether prototype-only detail surfaces remain comparison evidence or receive
  separately approved routes/pages; M3.R1 itself does not authorize them.
- Exact per-surface spacing, density, gradient, contrast, and responsive values
  where prototype evidence and committed contracts do not uniquely decide.
- Final classification records and implementation file scope.

No production or test implementation is authorized until these decisions are
resolved through the classification and implementation gates.
