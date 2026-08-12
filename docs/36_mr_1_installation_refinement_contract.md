# MR.1 — Installation Refinement Contract

## Status and authority

```text
MR.1 Installation Refinement: PROMOTED / IMPLEMENTATION IN PROGRESS (WU4 NEXT)
Contract: AMENDED / LOCKED
MR.1 implementation: AUTHORIZED WITHIN THE LOCKED SIX-WU CONTRACT
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
MR.1 WU4: NOT STARTED / NEXT ACTIVE IMPLEMENTATION TARGET
MR.1 WU5–WU6: NOT STARTED
MR.1 branch lifecycle: main-only / no-op
```

This is the authoritative contract for the Installation Refinement workstream.
It adopts the accepted staged-installation architecture without reopening the
completed Multi-Installation WU1–WU6 baseline or authorizing release work.

## Objective and installation flow

MR.1 refines the installer while preserving accepted ownership, namespace,
lifecycle, and fail-closed semantics. The authoritative flow is:

1. Requirements
2. Database
3. Administrator & Site
4. Modules
5. Review & Install
6. Installation Result

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
staging, optional bundled/Core Module selection, final review and installation
commit, installer routes/shell, contextual feedback, responsive behavior,
accessibility, and human acceptance.

Out of scope: Dashboard; post-install Admin Shell or Core Module management UI
refinement; cross-fileset ownership architecture; migration-capability redesign;
package-lifecycle redesign; Server-Empty Bootstrap; production reconciliation;
release, tag, and publication. Selecting/installing optional bundled Core
Modules during installation is in scope; later Core Module Admin UI refinement
remains separate.

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

## Pre-MR.1 Correctness Gate

The gate is satisfied. MR.1 implementation proceeds within this locked contract;
WU2 and WU3 are complete and closed; WU4 is the next active implementation
target, while WU5–WU6 remain not started.

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
MR.1 WU4 = NOT STARTED / NEXT ACTIVE IMPLEMENTATION TARGET
MR.1 WU5–WU6 = NOT STARTED
```

## Separate non-blocking architecture investigation

The **Cross-Fileset Upgrade Ownership Proof Gap** is
**ARCHITECTURE / PRODUCT INVESTIGATION REQUIRED**. It is separate from MR.1,
non-blocking by default, and does not authorize a cross-fileset Adopt or Migrate
capability promise.

## Work Unit topology

MR.1 has exactly six work units:

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

4. **WU4 — Optional Core Module Selection**

   Expose optional bundled/Core/first-party Modules for staged installation
   choice; distinguish mandatory platform Modules from optional Modules; support
   staged Install and Active selections; require Install for Active; clear Active
   when Install is unselected; validate dependencies; and permit recommended
   defaults. Mandatory platform Modules are not user-disableable here.

   Before implementation, inspect repository-native Module lifecycle vocabulary
   so AVAILABLE/BUNDLED, INSTALLED, and ACTIVE are not conflated and no
   incompatible persisted lifecycle semantics are invented.

5. **WU5 — Final Review, Installation Commit & Result**

   Final Review presents the complete staged Database, Table Prefix/namespace,
   installation mode, Administrator, Site, Modules, warnings, and planned
   actions using clear label/value alignment. Install revalidates requirements,
   database/namespace/ownership, and the accepted coordination boundary; then
   creates the COPOT schema/tableset, Administrator/Site state, mandatory and
   selected optional Modules, selected activations, and required
   identity/schema/migration/lifecycle/runtime evidence before committing
   completion.

   Failure handling must account for non-transactional DDL, track objects owned
   by the operation, never delete pre-existing objects, classify failures, and
   represent incomplete cleanup as incomplete/recoverable rather than Fresh.
   Installation Result reports a concise success and Admin/Site handoff, or a
   human-readable failed stage, cleanup/recovery state, and safe guidance.

6. **WU6 — Cross-Step Responsive, Accessibility & Human Acceptance**

   Validate Steps 1–6 across desktop/mobile, keyboard/focus/screen-reader
   behavior, Back/forward comprehension, staged-data retention, absence of
   unintended COPOT mutation before Install, Module-selection usability, final
   review comprehension, result usability, and final human UX acceptance.

## Validation and implementation boundaries

Validation must cover the staged-plan and mutation invariant in addition to
negative/ambiguous database states, namespaced Core/Module state, responsive
behavior, accessibility, and human comprehension. Findings do not silently
expand MR.1.

WU2 and WU3 are **COMPLETE AND CLOSED** with technical validation **PASS** and
human acceptance **PASS**. Reconciliation Batches 1–5 are **COMPLETE**. WU4 is
the **NEXT ACTIVE IMPLEMENTATION TARGET**; WU5–WU6 are **NOT STARTED**. This
amendment does not authorize work outside the
MR.1 contract, lifecycle redesign beyond an accepted work-unit boundary, Module
implementation outside WU4, cross-fileset ownership work, release, tag, or
publication.

## Documentation and lifecycle boundaries

Only materially relevant current-authority documentation is reconciled. The
historical Multi-Installation WU1–WU6 contract and evidence remain preserved and
closed. This contract records the six-WU topology, staged-installation invariant,
satisfied correctness gate, WU1–WU3 completion and acceptance status, accepted
Reconciliation Batches 1–5, and separate ownership-proof investigation. The
accepted `Install UI.png` visual guideline
continues as the structural reference for later installer refinement; page-specific
Database, Administrator/Site, and Final Review layout work remains owned by WU2,
WU3, and WU5 respectively, while the accepted mobile shell remains distinct.
MR.1 remains main-only / no-op for branch lifecycle. Review & Install remains the
first COPOT installation mutation boundary; the database-container provisioning
exception is unchanged. WU4 is not started and is the next implementation target.
