# MR.x Session-Level Refinement Backlog
Date version: 2026-08-09 20:11:13 WIB

Status: Future Concept Artifact / Backlog Capture
Project: COPOT
Purpose: Preserve refinement candidates explicitly saved at ChatGPT session level so they can be reviewed during future MR.x preparation without being mistaken for repository authority or implementation authorization.

---

## 1. Concept Identity

Canonical Concept title:

`MR.x Session-Level Refinement Backlog`

Concept file family:

`concept_mrx_session_refinement_backlog_*`

This Concept is a backlog capture artifact.

It is related to, but does not replace:

`Copot Refinement Milestone Governance Concept`

The governance Concept defines the MR.x / WUy / Batch lifecycle model.

This Concept preserves candidate refinement items that were explicitly saved during project sessions and were not sourced from another Concept artifact.

---

## 2. Source Boundary

Source classification:

`SESSION-LEVEL CAPTURE`

The items below were preserved from explicit session-level notes and decisions.

They are intentionally separated from:

- repository-authoritative contracts;
- Workplan authority;
- Deferred Item registry authority;
- other Concept files;
- inferred future improvements;
- generic UX recommendations that were never explicitly saved.

This artifact does not claim that every item below is already eligible for MR.x adoption.

Each item must be revalidated against the current accepted baseline during MR.x preparation.

---

## 3. Lifecycle Boundary

Current status:

`CANDIDATE BACKLOG`

MR milestone:

`NOT YET ASSIGNED`

Work Unit assignment:

`NOT YET ASSIGNED`

Implementation:

`NOT AUTHORIZED`

Repository authority:

`NO`

Formal MR.x adoption requires:

1. accepted baseline verification;
2. candidate classification;
3. locked MR scope;
4. explicit adoption decision;
5. authoritative preparation/contract mutation where required;
6. implementation authorization.

If an item is found to be a defect, regression, security issue, maintenance requirement, or new capability rather than refinement, it must be reclassified to the appropriate lifecycle instead of being forced into MR.x.

---

## 4. Candidate Backlog

### 4.1 Installer — Finalize Installation UI Refinement

Surface:

`Installer / Finalize Installation`

Saved finding:

- label/value/status presentation is visually cramped;
- information hierarchy and spacing need improvement;
- baseline-module summary presentation needs clearer structure.

Known baseline:

- functional installation flow had already passed;
- finding was non-blocking for the accepted release baseline.

Candidate direction:

- improve hierarchy;
- improve spacing and scanability;
- clarify module/status summary presentation;
- preserve installation semantics and behavior.

Candidate lifecycle:

`MR.x UI/UX refinement`

Implementation authority:

`NONE`

---

### 4.2 Module Manager — Lifecycle Action & Version Hierarchy Refinement

Surface:

`Admin / Module Manager`

Saved finding:

- `Open / Repair` lifecycle action layout feels awkward;
- installed-version versus available-version hierarchy is not sufficiently clear;
- lifecycle badge and action placement need refinement.

Known baseline:

- functional Module Package Lifecycle behavior had already passed acceptance;
- finding was non-blocking.

Candidate direction:

- improve lifecycle information hierarchy;
- clarify installed versus available version presentation;
- normalize lifecycle badge/action placement;
- preserve lifecycle semantics and permissions.

Candidate lifecycle:

`MR.x Admin UX refinement`

Implementation authority:

`NONE`

---

### 4.3 Module Manager — Lifecycle Button Sizing Consistency

Surface:

`Admin / Module Manager`

Saved finding:

- `Install / Repair` buttons are too wide or visually inconsistent;
- action controls should prefer intrinsic/content width or a shared sizing rule.

Known baseline:

- functional action behavior had passed;
- finding is presentation-level unless later evidence proves otherwise.

Candidate direction:

- establish consistent lifecycle-action sizing;
- preserve accessibility, touch target, and responsive behavior;
- avoid semantic/action changes.

Candidate lifecycle:

`MR.x component/presentation refinement`

Implementation authority:

`NONE`

---

### 4.4 Module Manager — Post-Action Scroll / Focus Preservation

Surface:

`Admin / Module Manager lifecycle workflow`

Saved finding:

- lifecycle action uses `POST → redirect`;
- after action completion the page returns to the top;
- the operated module loses immediate visual context.

Saved candidate direction:

Preserve or restore focus/context to the affected module row after redirect, for example through a stable row anchor such as:

`/admin/modules#module-<module-id>`

Preparation questions:

- whether anchor restoration alone is sufficient;
- whether focus should also move programmatically;
- accessibility implications;
- behavior on validation/error/success states;
- stable row identity contract.

Candidate lifecycle:

`MR.x interaction/workflow clarity refinement`

Implementation authority:

`NONE`

---

### 4.5 Webcore Lifecycle — Settings/Admin UX Refinement

Surface:

`Webcore lifecycle Admin / Settings presentation`

Saved finding/direction:

Webcore lifecycle capability should receive an Admin/Settings-page UX refinement after core engine/capability work is closed, instead of being mixed into foundational lifecycle implementation.

Boundary:

- presentation and workflow clarity only unless audit discovers a functional gap;
- do not reopen closed lifecycle engine work merely to improve Admin UX;
- do not infer new package-lifecycle capability from this candidate.

Candidate lifecycle:

`MR.x Webcore/shared Admin UX refinement`

Implementation authority:

`NONE`

---

### 4.6 Installer — Database Selection & Multi-Installation UX Refinement

Surface:

`Installer / Database connection, inspection, and installation routing`

Saved context:

Multi-Installation Isolation Foundation is COMPLETE AND CLOSED for its accepted WU1–WU6 scope. The current installer already has database probing, occupancy/ownership classification, namespace-aware routing, and internal intent semantics. This candidate refines how those capabilities are presented and sequenced for users. It does not reopen the closed Multi-Installation ownership/isolation model.

Saved candidate requirements:

1. **Database connection first, installation intent later.**  
   The user enters/selects Host, Port, Database Name, Username, and Password before choosing an installation mode.

2. **Inspect the selected database after connection succeeds.**  
   COPOT determines occupancy, proven COPOT ownership, namespaces/table prefixes, and routing constraints before presenting installation choices.

3. **Empty database → Fresh Install only.**  
   Do not present irrelevant coexist/adopt/update choices when the database is empty.

4. **Non-empty database with no proven COPOT → Install Another COPOT / Coexist only.**  
   Require an isolated non-empty prefix/namespace and do not imply that an unrelated non-empty database can be adopted or migrated as COPOT.

5. **Proven existing COPOT → offer only valid existing/new/update actions.**  
   Candidate user-facing choices: `Use Existing COPOT Installation`, `Install Another COPOT in This Database`, and `Update Existing COPOT Installation`, subject to current routing/ownership evidence.

6. **Rename user-facing `Database namespace` to `Table Prefix`.**  
   Internal architecture may continue to use `namespace`; the installer should expose the concept users actually interact with.

7. **Make Table Prefix behavior conditional by route.**  
   - Fresh Install: optional; blank remains valid where the empty namespace is valid.
   - Install Another COPOT / coexist: required and non-empty.
   - Use Existing / update existing: detected prefix should be preserved and normally presented read-only.

8. **Show database inspection results before schema mutation.**  
   Present relevant occupancy/status, COPOT detection, detected prefix(es), selected/proposed action, and collision/warning information in a concise human-readable summary.

9. **Auto-suggest an available prefix and fail closed on ambiguity/collision.**  
   For coexist, COPOT may propose an available prefix such as `cp1_`, `cp2_`, etc. Ambiguous ownership or unsafe collisions must block progression rather than offering a generic “continue anyway” path.

10. **Show a final confirmation summary before schema write.**  
    Summarize database, action, table prefix, existing COPOT detection where relevant, and isolation consequence using user-facing labels. Internal enum names such as `FRESH`, `COEXIST`, `ADOPT`, and `MIGRATE` should remain implementation terminology rather than primary UX copy.

Boundary:

- this candidate is installer workflow/presentation refinement unless revalidation finds a functional gap;
- do not add Installation ID or Runtime ID input fields;
- do not introduce shared tableset ownership across independent installations;
- do not change the accepted rule that independent installations use disjoint COPOT-owned object sets;
- do not reopen Multi-Installation Foundation solely to improve installer UX;
- do not auto-adopt Server-Empty Bootstrap & Package Clean Install;
- preserve fail-closed ownership/occupancy safety.

Candidate lifecycle:

`MR.x Installer workflow / Multi-Installation UX refinement`

Implementation authority:

`NONE`

---

## 5. Candidate Consolidation Notes

The following candidates may belong to one future locked MR scope if preparation confirms a coherent boundary:

- Installer Finalize Installation UI;
- Installer Database Selection & Multi-Installation UX;
- Module Manager lifecycle information/action presentation;
- Module Manager lifecycle button sizing;
- Module Manager post-action context preservation;
- Webcore lifecycle Admin/Settings UX.

Possible shared theme:

`Lifecycle & Installation Admin UX Refinement`

This is only a candidate grouping.

It does not create:

- `MR.1`;
- a Work Unit;
- a branch;
- a roadmap target;
- implementation authorization.

Preparation may instead split these candidates across different MR milestones or reclassify individual items.

---

## 6. Relationship to MR.x Governance

Related Concept:

`Copot Refinement Milestone Governance Concept`

Relevant governance principles:

- MR.x is for intentional refinement of accepted baselines;
- closed feature milestones remain closed;
- Work Unit numbers represent execution sequence, not permanent surface identity;
- repeated refinement of a surface receives a new WU when adopted;
- MR scope must be explicitly locked;
- candidate capture does not authorize implementation;
- defects, maintenance, new capabilities, and architectural replacement must use their proper lifecycle.

This backlog should be consumed during MR.x preparation as candidate input only.

---

## 7. Revalidation Requirements Before Adoption

For each candidate:

1. verify the current authoritative repository baseline;
2. confirm the original finding still exists;
3. classify it as refinement versus defect/maintenance/new capability;
4. verify no later implementation already resolved it;
5. identify dependencies and affected surfaces;
6. define objective acceptance where possible;
7. identify subjective/human visual acceptance where required;
8. decide whether to adopt, keep deferred, reclassify, supersede, or reject.

No candidate should be implemented solely because it appears in this file.

---

## 8. Current Disposition

| Candidate | Current disposition |
|---|---|
| Installer Finalize Installation UI | MR.x candidate |
| Installer Database Selection & Multi-Installation UX | MR.x candidate |
| Module Manager lifecycle action/version hierarchy | MR.x candidate |
| Module Manager lifecycle button sizing | MR.x candidate |
| Module Manager post-action scroll/focus | MR.x candidate |
| Webcore lifecycle Settings/Admin UX | MR.x candidate |

Overall Concept disposition:

`PRESERVE FOR FUTURE MR.x PREPARATION`

---

## 9. Non-Authorization

This Concept does not:

- create an MR milestone;
- assign MR.x numbering;
- assign WU numbering;
- modify the authoritative roadmap;
- modify the Workplan;
- create branches;
- authorize implementation;
- reopen accepted M3/Post-M3 baselines;
- classify any item as a defect;
- adopt any Deferred Item;
- supersede repository contracts;
- supersede the MR.x governance Concept.

Formal adoption requires a future MR.x preparation/review and explicit project approval.