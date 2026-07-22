# M3 Admin Shell Design Adjustment Contract

## Purpose and Applicability

This contract governs design review and presentation refinement for M3 Admin pages and the shared Admin Shell. It applies to M3.4 Batch 6, M3.R1, and relevant Admin design-adjustment checkpoints in M3.5–M3.11.

It supplements the shared Admin UI Foundation defined in `docs/10_admin_ui_foundation.md` and does not replace milestone-specific domain contracts.

## Authority Hierarchy

Design decisions follow this order:

1. project instructions and locked contracts;
2. committed implementation and tests;
3. approved Admin Shell design authority;
4. Product Designer recommendation;
5. approved implementation.

The approved Admin Shell design authority includes the committed shell implementation, shared Admin UI tokens and patterns, the approved Admin Shell visual reference, and any explicitly approved refinement plan.

Product Designer input supports UX audit, visual hierarchy, navigation architecture, component consistency, interaction flow, responsive behavior, accessibility review, design proposals, and rationale. Product Designer input does not authorize domain architecture, permissions, routes, schema, data contracts, implementation ownership, Git state, release state, or publication.

## Review Workflow

Each applicable design-adjustment work unit must:

1. identify the Admin pages and shell surfaces in scope;
2. capture and inspect the current desktop and mobile experience;
3. compare the experience with the approved Admin Shell authority;
4. classify each reviewed page as `redesign required`, `retouch required`, `review only`, or `NO CHANGE REQUIRED`;
5. record the approved design direction and boundaries;
6. implement only approved presentation and interaction changes;
7. validate focused behavior, runtime compatibility, responsive behavior, accessibility, and browser evidence;
8. document the result, limitations, remaining gates, and closure status.

## Navigation Ordering Governance

Existing Admin navigation remains permission-aware, request-scoped, and owned through the current Admin navigation contract. A design-adjustment work unit may recommend or implement approved placement, grouping, label, icon, order, and active-state changes within that contract.

Design-adjustment work units must not implement Navigation Manager, introduce frontend Navigation integration, create a new navigation data model, or bypass the Navigation ownership decision in `docs/16_m3_core_freeze_and_module_contract.md`.

M3.6 Navigation Manager remains the future owner of navigation data, menu structures, locations, item ordering metadata, visibility metadata, and navigation-management UI.

## Responsive and Accessibility Acceptance

Review must cover approved desktop and narrow mobile widths, readable hierarchy, usable touch targets, keyboard focus, visible focus treatment, semantic structure, labels, active-state clarity, drawer behavior, and permission-aware navigation visibility. A screenshot review alone must not be presented as complete accessibility compliance.

## Validation Evidence

Evidence must identify the exact pages and states reviewed and may include focused automated tests, source review, runtime synchronization checks, browser screenshots or walkthroughs, responsive checks, and accessibility findings. Browser limitations must identify the contract requirement they prevent; request-level cases may rely on focused automation and source review where the milestone contract permits.

## Documentation and Closure

Each work unit requires its own scope record, validation evidence, browser limitation disposition, remediation record, remaining approval gates, branch state, and NRP evaluation. `NO CHANGE REQUIRED` is a valid completion outcome when supported by evidence.

Design completion does not imply domain, milestone, Git, release, tag, merge, push, branch-cleanup, or publication completion.

## Branch and NRP Expectations

M3.R1 and each milestone-specific design-adjustment work unit use their own dedicated branch and lifecycle. Batch 6 used `feature/m3.4-content-manager-batch-6`; its implementation was merged into `main`, the feature branch was deleted locally and remotely after containment verification, and its lifecycle is closed. Batch 6 is `NRP CONFIRMED`. Each work unit reaches its own NRP evaluation only after documentation, validation, Git integration, final verification, and final changeset requirements are satisfied.

## Exclusions

This contract does not authorize changes to domain behavior, permissions, routes, schema, data contracts, Core architecture, module ownership, Theme rendering ownership, runtime synchronization, release, tags, publication, or Git state. Such changes require the applicable project and milestone approvals.
