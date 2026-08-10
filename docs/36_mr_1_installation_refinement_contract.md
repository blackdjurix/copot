# MR.1 — Installation Refinement Contract

## Status and authority

```text
MR.1 Installation Refinement: PROMOTED / IMPLEMENTATION NOT STARTED
Contract: LOCKED
Implementation authorization: NOT GRANTED
MR.1 WU1: BLOCKED BY PRE-MR.1 CORRECTNESS GATE
```

This is the authoritative contract for the current Installation Refinement
workstream. It promotes the accepted MR.1 contract into repository guidance;
it does not start WU1, authorize implementation, reopen the completed
Multi-Installation WU1–WU6 baseline, or authorize release work.

## Objective

Complete the accepted installer refinement from Requirements through Finalize
and the Admin/Site handoff while preserving accepted installation, ownership,
namespace, lifecycle, and fail-closed semantics.

## Locked scope and exclusions

MR.1 covers Requirements, database UX, installer routes, Table Prefix and
namespace selection, collision/ownership/ambiguity handling, Administrator &
Site, Finalize, installer shell, contextual feedback, responsive behavior,
accessibility, and human UX acceptance.

Out of scope: Admin Shell after completion, Dashboard, Core Module/Admin,
cross-fileset ownership architecture, migration capability, package lifecycle
redesign, Server-Empty Bootstrap, production reconciliation, release, tag,
and publication.

## Locked UX principles

- **Requirements:** truthful preflight with actionable prerequisites; it does
  not imply that later steps or installation have started.
- **Installer Shell:** clear step identity, progress, current-state context,
  stable navigation, and responsive/accessibility baseline.
- **Contextual Feedback:** local to the producing decision/operation, explains
  consequence, preserves safe intent, and fails closed when evidence is incomplete.
- **Database Decision:** staged connect, inspect occupancy/namespace, select a
  permitted decision, prove it with available evidence, then proceed. Table-name
  similarity is not ownership proof.
- **Administrator & Site:** explicit, validated, bound to the selected decision,
  with human-readable confirmation before finalization.
- **Finalize:** a bounded truthful transition; completion is reported only after
  accepted identity, schema, lifecycle, and runtime evidence are committed,
  then the installer locks and hands off to Admin/Site.

## Accepted functional baseline

The baseline includes connection testing; occupancy classification; Table
Prefix/`DB_NAMESPACE` selection and persistence; Fresh, New Independent,
Adopt, and Migrate decisions only where available proof permits them;
fail-closed collision, ambiguity, mismatch, and incomplete-evidence handling;
namespaced Core and Module schema state; empty-namespace handling; installation
identity; Administrator and Site data; finalization; completion lock; Admin/Site
handoff; and split-root portability.

There is no cross-fileset Adopt or Migrate promise. A complete-looking table set
without positive ownership evidence is not an owned installation.

## Pre-MR.1 Correctness Gate

MR.1 WU1 cannot begin until this gate is satisfied.

### Blocker A — `DB_NAMESPACE` Persistence Merge Defect

Status: **RESOLVED / VALIDATED**. The environment merge boundary now treats
`DB_NAMESPACE` like the other database keys, replacing existing and repeated
entries without duplication. Focused regression coverage passed. This bounded
correction is complete; it does not satisfy the overall gate while Blocker B
remains unresolved.

### Blocker B — Change Database / Finalize Progression State

Status: **AUDIT REQUIRED**. Classify the observed progression as accepted
behavior or incorrect state reuse. This external investigation blocks WU1
until classified; do not audit or fix it under this contract.

### Gate result

```text
MR.1 WU1 = BLOCKED BY PRE-MR.1 CORRECTNESS GATE
```

## Separate non-blocking architecture investigation

The **Cross-Fileset Upgrade Ownership Proof Gap** is
**ARCHITECTURE / PRODUCT INVESTIGATION REQUIRED**. It is separate from MR.1,
not a default WU1 blocker, does not authorize a new capability or ownership
redesign, and must not become a cross-fileset Adopt or Migrate promise. Only
routes that backend evidence proves are in scope.

## Work Unit topology

MR.1 has exactly four work units; there is no catchall:

1. **WU1 — Installer shell, Requirements, and step progression:** shell,
   Requirements truthfulness, step identity, progression boundaries, and
   accessible/responsive foundations. Currently blocked by the gate.
2. **WU2 — Database inspection and installation decision UX:** connection,
   occupancy, namespace/Table Prefix, collision, ownership, ambiguity, and
   fail-closed decision presentation.
3. **WU3 — Admin/Site and finalization UX:** Administrator & Site, Finalize,
   completion lock, identity, lifecycle evidence, and Admin/Site handoff.
4. **WU4 — Responsive, accessibility, and human UX acceptance:** cross-step
   responsive behavior, keyboard/screen-reader semantics, clarity, feedback,
   and human acceptance of the locked baseline.

## Validation and implementation entry gate

Validation covers the baseline, negative/ambiguous database states, namespaced
Core/Module state, progression/completion locking, split-root portability,
responsive behavior, accessibility, and human comprehension. Findings are
classified against the accepted baseline and do not silently expand MR.1.

Implementation requires: Blocker A corrected and verified; Blocker B audited and
classified; resulting defects corrected and verified; authoritative `main`
re-verified; no new blocker; and separate explicit WU1 authorization. The
cross-fileset proof gap is non-blocking by default.

## Documentation and lifecycle boundaries

Only materially relevant current-authority documentation is reconciled. The
historical Multi-Installation WU1–WU6 contract and evidence remain preserved
and closed; they are not reopened. This contract makes promotion,
implementation-not-started status, four-WU topology, the gate, external
blockers, and the separate ownership-proof investigation visible. It
authorizes no WU1 implementation, Multi-Installation expansion, release, tag,
or publication.
