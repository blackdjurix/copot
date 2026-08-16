# MR.1 — Installation Refinement Contract

## Status and authority

```text
MR.1 Installation Refinement: COMPLETE AND CLOSED
Contract: AMENDED / LOCKED
MR.1 implementation: WU1–WU5 COMPLETE AND CLOSED
MR.1 WU1: COMPLETE AND CLOSED
MR.1 WU1 technical validation: PASS
MR.1 WU1 human acceptance: PASS
MR.1 WU2: COMPLETE AND CLOSED
MR.1 WU2 technical validation: PASS
MR.1 WU2 human visual acceptance: PASS
MR.1 WU2 focused validation: 33 assertions PASS; runtime staging and responsive review PASS
MR.1 WU3: COMPLETE AND CLOSED
MR.1 WU3 implementation: COMPLETE
MR.1 WU3 technical validation: PASS
MR.1 WU3 human acceptance: PASS
MR.1 Reconciliation Batches 1–5: COMPLETE
MR.1 former WU4 Module-selection feature: SUPERSEDED / REMOVED FROM MR.1 SCOPE
MR.1 WU4: COMPLETE AND CLOSED
MR.1 WU4 implementation: COMPLETE
MR.1 WU4 technical validation: PASS
MR.1 WU4 functional installation validation: PASS
MR.1 WU4 human UI acceptance: PASS
MR.1 WU5: COMPLETE AND CLOSED
MR.1 WU5 implementation: COMPLETE
MR.1 WU5 technical/objective validation: PASS
MR.1 WU5 human acceptance: PASS
MR.1 branch lifecycle: main-only / no-op
```

This is the authoritative contract for the Installation Refinement workstream.
It adopts the accepted staged-installation architecture without reopening the
completed Multi-Installation WU1–WU6 baseline or authorizing release work.

The completed Installer Shared CSS foundation is the durable post-WU3 installer
UI foundation. Subsequent installer UI work must consume its shared shell,
progress, status, form, control, feedback, action/navigation, focus, spacing,
and responsive primitives rather than duplicate generic step-local styling.
Step-specific styling remains appropriate only for genuinely unique layout or
behavior.

## Objective and installation flow

MR.1 refines the installer while preserving accepted ownership, namespace,
lifecycle, and fail-closed semantics. The authoritative flow is:

1. Requirements
2. Database
3. Administrator & Site
4. Review & Install
5. Installation Result

Installation Result is an outcome phase, not another configuration-input step.

## Core mutation invariant

Before the user confirms **Install** on Review & Install, COPOT must not create
installation-owned schema/tables, Administrator/Site records, or optional Module
installation or activation state. Review & Install is the first COPOT
installation mutation boundary.

Back navigation changes or reviews staged installer intent only. It neither
undoes nor mutates installed state. A Database step may create the database
container only when the user explicitly requests provisioning and the connected
environment proves sufficient capability and permission. Database-container
creation is distinct from COPOT schema/tableset creation.

## Locked scope and exclusions

MR.1 covers staged Requirements, database decision UX, Administrator/Site
staging, Review & Install, installation commit, installer routes/shell,
contextual feedback, responsive behavior, accessibility, and human acceptance.

Out of scope: Dashboard; post-install Admin Shell or Core Module management UI
refinement; cross-fileset ownership architecture; migration-capability redesign;
package-lifecycle redesign; Server-Empty Bootstrap; production reconciliation;
release, tag, and publication. Module installation/activation selection is not
part of MR.1 installer UX; later Core Module Admin UI refinement remains
separate.

## Locked UX and safety principles

- **Requirements:** truthful preflight with actionable prerequisites. It does
  not imply that a later step or installation has started.
- **Staged plan:** input, validation, and permitted inspection are staged until
  Review & Install; revisiting a step preserves staged input.
- **Database decision:** connect, inspect occupancy/namespace, select a
  permitted decision, and prove it with available evidence. Table-name
  similarity is not ownership proof.
- **Contextual feedback:** local to its producing decision or operation,
  explains consequence, preserves safe intent, and fails closed on incomplete
  evidence.
- **Review & Install:** revalidates the complete staged plan and is the first
  COPOT mutation boundary.
- **Result:** reports success or a truthfully classified failure rather than
  implying that an incomplete mutation left a Fresh environment.

## Accepted functional baseline

The baseline includes connection testing; occupancy classification; Table
Prefix/`DB_NAMESPACE` selection; Fresh, New Independent, Adopt, and Migrate
decisions only where available proof permits them; fail-closed collision,
ambiguity, mismatch, and incomplete-evidence handling; namespaced Core and
Module schema state; empty-namespace handling; installation identity;
split-root portability; and accepted lifecycle evidence.

There is no cross-fileset Adopt or Migrate promise. A complete-looking table set
without positive ownership evidence is not an owned installation.

Current Installer authority was subsequently reconciled by Database Ownership
WU5: the active intents are Fresh, Coexist, and Adopt. The historical New
Independent and Migrate references in this MR.1 baseline describe the accepted
refinement evidence and are not current Installer lifecycle ownership. Normal
existing-install Update / Upgrade / Repair belongs to System Manager / Webcore
Lifecycle.

## Pre-MR.1 Correctness Gate

The gate is satisfied. MR.1 implementation is complete and closed through WU5
within this locked contract. The former WU4 Module-selection feature is
superseded and removed from active scope.

### Blocker A — `DB_NAMESPACE` Persistence Merge Defect

Status: **RESOLVED / VALIDATED**. The environment merge boundary replaces
existing and repeated `DB_NAMESPACE` entries without duplication. Focused
regression coverage passed.

### Blocker B — Change Database / Finalize Progression State

Status: **CLASSIFIED / NOT A DEFECT**. The current request reloads the
persisted environment, constructs a namespace-bound `Database`, and derives
Administrator & Site completion from `InstallerAdministratorSetup::administratorExists()`,
which counts users in the active namespace only. A newly provisioned independent
namespace therefore correctly returns to Administrator & Site before Finalize.
The observed behavior is accepted lifecycle behavior, not state leakage. The
staged-installation amendment supersedes it as the future mutation boundary.

### Gate result

```text
PRE-MR.1 CORRECTNESS GATE = SATISFIED / VALIDATED
MR.1 WU1 = COMPLETE AND CLOSED / TECHNICAL VALIDATION PASS / HUMAN ACCEPTANCE PASS
MR.1 WU2 = COMPLETE AND CLOSED / TECHNICAL VALIDATION PASS / HUMAN VISUAL ACCEPTANCE PASS
MR.1 WU3 = COMPLETE AND CLOSED / TECHNICAL VALIDATION PASS / HUMAN ACCEPTANCE PASS
MR.1 former WU4 Module Selection = SUPERSEDED / REMOVED FROM ACTIVE SCOPE
MR.1 WU4 = COMPLETE AND CLOSED / IMPLEMENTATION COMPLETE / TECHNICAL VALIDATION PASS / FUNCTIONAL INSTALLATION VALIDATION PASS / HUMAN UI ACCEPTANCE PASS
MR.1 WU5 = COMPLETE AND CLOSED / IMPLEMENTATION COMPLETE / TECHNICAL/OBJECTIVE VALIDATION PASS / HUMAN ACCEPTANCE PASS
```

## Separate non-blocking architecture investigation

The **Cross-Fileset Upgrade Ownership Proof Gap** is
**ARCHITECTURE / PRODUCT INVESTIGATION REQUIRED**. It is separate from MR.1,
non-blocking by default, and does not authorize a cross-fileset Adopt or Migrate
capability promise.

## Work Unit topology

MR.1 has exactly five work units:

1. **WU1 — Installer Shell, Requirements & Navigation Framework**

   Shared installer shell; Requirements-first progression; step/progress and
   Back/Previous navigation; completed-step review; non-skippable future/pending
   steps; navigation that does not itself reset or mutate installation state;
   rendering values already available from the current runtime/state model;
   semantic status presentation; desktop density; responsive/mobile
   current-phase presentation; and accessibility foundation. WU1 does not own
   authoritative staged-form persistence.

   Status: **COMPLETE AND CLOSED**. Technical validation is **PASS** and human
   acceptance is **PASS**. Accepted evidence includes Requirements-first flow,
   mandatory revalidation, contextual descriptions and conditional semantic
   status, displayed/review-phase synchronization with preserved forward
   lifecycle state, completed-step review, bounded Previous/navigation behavior,
   non-skippable pending/future phases, stable desktop shell geometry and
   centering, viewport-aware footprint and scrolling, bottom-oriented action
   zones, separated Database operation/navigation, Previous-left/forward-right
   semantics, mobile current-phase presentation, side-by-side mobile navigation,
   and accessibility behavior. Focused WU1 validation passed 67 assertions;
   PHP lint, diff-check, desktop shell/action verification, and 390px mobile
   verification passed with no horizontal overflow.

2. **WU2 — Staged Installation Plan & Database Decision**

   Establish the authoritative staged plan; database inputs/validation; compact
   Host + Port shared-row direction; contextual/floating database help;
   occupancy inspection; Table Prefix/namespace; permitted Fresh/New
   Independent/Adopt/Migrate presentation; fail-closed ambiguity/collision
   handling; authoritative persistence of the latest Database inputs and
   decisions across Back/revisit; and database/namespace intent changes before
   Install that leave no COPOT tableset behind. WU2 owns the staged Database
   plan and must ensure Next creates no COPOT schema/tableset.

   Existing Database is the universal baseline. Optional Create New Database may
   be offered only when environment and credentials prove sufficient capability;
   its failure must be actionable. Provider-specific cPanel/Plesk integrations
   are not a baseline requirement. Ownership proof and `AMBIGUOUS` semantics
   must not be weakened.

   Status: **COMPLETE AND CLOSED**. Technical validation is **PASS**, including
   33 focused assertions, runtime Database staging, no-schema-on-Next behavior,
   and PHP/diff checks. Human visual acceptance is **PASS** for the accepted
   desktop and 390px mobile review. WU2 introduced no change to Database
   functionality or the Review & Install mutation boundary.

3. **WU3 — Administrator & Site Staging**

   Administrator/Site inputs, validation, and authoritative staged persistence;
   latest Administrator/Site values across Back/revisit; and no
   Administrator/Site row creation or other installation mutation before
   Review & Install.

   Status: **COMPLETE AND CLOSED**. Implementation is **COMPLETE**, technical
   validation is **PASS**, and human acceptance is **PASS**. Administrator &
   Site values are staged and persisted across revisit without pre-Install
   mutation. The accepted reconciliation result also establishes event-based
   Database feedback that is quiet on staged revisit, shared installer form
   primitives and visual semantics, inspection-derived eligible intents,
   immediate namespace collision validation during Test Database, collision-free
   empty-namespace support, safe Coexist handling for ambiguous ownership, and
   ownership-proof/compatibility-constrained Adopt/Migrate eligibility. No
   schema, table, Administrator/Site, or Module mutation occurs before Review &
   Install.

4. **WU4 — Review, Installation Commit & Result**

   Review & Install presents the complete staged Database, Table
   Prefix/namespace, installation mode, Administrator, Site, warnings, and
   planned actions using clear label/value alignment. Install revalidates
   requirements, database/namespace/ownership, and the accepted coordination
   boundary; then creates the COPOT schema/tableset, Administrator/Site state,
   baseline Modules, and required
   identity/schema/migration/lifecycle/runtime evidence before committing
   completion.

   Failure handling must account for non-transactional DDL, track objects owned
   by the operation, never delete pre-existing objects, classify failures, and
   represent incomplete cleanup as incomplete/recoverable rather than Fresh.
   Installation Result reports a concise success and Admin/Site handoff, or a
   human-readable failed stage, cleanup/recovery state, and safe guidance.

   Status: **COMPLETE AND CLOSED**. Implementation, technical validation,
   functional installation validation, and human UI acceptance are **PASS**.
   Review & Install remains the first COPOT installation-owned mutation boundary;
   Installation Result preserves the successful five-phase completion state and
   uses the shared installer footer for its Admin handoff.

5. **WU5 — Cross-Step Responsive, Accessibility & Human Acceptance**

   Validate Steps 1–5 across desktop/mobile, keyboard/focus/screen-reader
   behavior, Back/forward comprehension, staged-data retention, absence of
   unintended COPOT mutation before Install, final review comprehension, result
   usability, and final human UX acceptance.

   Status: **COMPLETE AND CLOSED**. Implementation, technical/objective
   validation, and human acceptance are **PASS**. Accepted WU5 evidence covers
   the browser-facing five-phase journey, desktop and 390px mobile rendering,
   keyboard/error-recovery and available semantic inspection, staged Database
   and Administrator/Site retention across revisit, and the pre-Install
   mutation invariant. Shared field presentation now programmatically associates
   rendered help/error text with its control and exposes invalid state only for
   invalid controls. Successful installation commit now creates the required
   installation identity before Installation Result represents completion; the
   Continue to Admin handoff no longer creates it. At the mobile breakpoint the
   installer is an accepted full-screen shell without card treatment: header and
   footer remain outside the phase-content scroll region, which scrolls only
   when necessary. The accepted five-phase flow and Review & Install first
   installation-owned mutation boundary are unchanged.

## Validation and implementation boundaries

Validation must cover the staged-plan and mutation invariant in addition to
negative/ambiguous database states, namespaced Core/Module state, responsive
behavior, accessibility, and human comprehension. Findings do not silently
expand MR.1.

WU1–WU5 are **COMPLETE AND CLOSED** with accepted technical validation and
human acceptance **PASS**. WU4 functional installation validation is **PASS**;
WU5 technical/objective validation is **PASS**. Reconciliation Batches 1–5 are
**COMPLETE**. The former WU4 Module-selection feature is **SUPERSEDED / REMOVED
FROM ACTIVE SCOPE**. This amendment does not authorize work outside the
MR.1 contract, lifecycle redesign beyond an accepted work-unit boundary, Module
implementation outside the installer baseline, cross-fileset ownership work, release, tag, or
publication.

## Documentation and lifecycle boundaries

Only materially relevant current-authority documentation is reconciled. The
historical Multi-Installation WU1–WU6 contract and evidence remain preserved and
closed. This contract records the five-WU topology, staged-installation invariant,
satisfied correctness gate, WU1–WU5 completion and acceptance status, accepted
Reconciliation Batches 1–5, and separate ownership-proof investigation. The
accepted `Install UI.png` visual guideline
continues as the structural reference for later installer refinement; page-specific
Database, Administrator/Site, and Review & Install layout work remains owned by
WU2, WU3, and WU4 respectively. The completed Installer Shared CSS foundation is
the durable post-WU3 UI ownership boundary for those surfaces; generic
presentation must be shared there, with step-specific CSS reserved for genuinely
unique layout or behavior. The accepted mobile shell remains distinct.
MR.1 remains main-only / no-op for branch lifecycle. Review & Install remains the
first COPOT installation mutation boundary; the database-container provisioning
exception is unchanged. WU1–WU5 and full MR.1 are complete and closed.
