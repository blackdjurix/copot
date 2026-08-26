# Copot Refinement Milestone Governance Concept
Datetime: 03 Aug 2026 22:04:52 WIB
Status: Temporary concept note
Project: copot
Purpose: Preserve the proposed MR.x / WUy / Batch lifecycle model for intentional refinement work after accepted functional baselines.

---

## 1. Purpose

Copot needs a lifecycle namespace for intentional refinement that does not:

- reopen already closed feature milestones;
- misuse major product milestone numbering;
- conflate refinement with defects or maintenance;
- permanently bind a work-unit number to one UI surface.

Proposed namespace:

```text
MR.x
```

where `MR` means Refinement Milestone.

This concept is independent from the historical milestone:

```text
M3.R1 — Admin Shell Retouch 1
```

A closed historical milestone remains closed.

---

## 2. Refinement Definition

Refinement is intentional improvement of an accepted baseline.

Examples:

- Admin UX improvement;
- presentation hierarchy;
- permission grouping;
- responsive behavior improvement;
- interaction polish;
- component consistency;
- accessibility refinement;
- workflow clarity;
- information-density improvement.

Refinement is not automatically:

- defect correction;
- security remediation;
- emergency maintenance;
- new domain capability;
- unrelated feature development;
- architectural replacement.

Those use their appropriate lifecycle.

---

## 3. MR.x

Syntax:

```text
MR.x
```

Examples:

```text
MR.1
MR.2
MR.3
```

`x` is a monotonically increasing refinement milestone number.

It is independent from major roadmap namespaces such as:

```text
M1
M2
M3
M4
M5
M6
```

An MR does not imply that all major milestones before or after it must execute linearly.

---

## 4. Locked MR Scope

Each MR must have a locked refinement boundary.

Example:

```text
MR.1 — Admin UX Refinement
```

Potential scope:

- shared Admin Shell;
- Dashboard;
- manager workspaces;
- forms;
- tables/lists;
- permission presentation;
- Admin responsive behavior;
- Admin accessibility/presentation.

MR scope is not bounded by an arbitrary number of Work Units.

As long as a proposed refinement remains inside the locked MR boundary, new Work Units may continue to be added until the MR is intentionally closed.

---

## 5. WUy

Syntax:

```text
MR.x WUy
```

`y` is monotonically increasing within one MR lifecycle.

WU numbers represent work sequence, not permanent surface identity.

Incorrect mental model:

```text
WU1 = Dashboard forever
WU2 = Roles forever
```

Correct model:

```text
WU1 = first accepted refinement work unit
WU2 = second accepted refinement work unit
...
```

---

## 6. Repeated Surface Refinement

If a surface is refined again later inside the same active MR, it receives the next WU number.

Example:

```text
MR.1 WU1 — Dashboard Refinement
MR.1 WU2 — Roles Refinement
MR.1 WU3 — Media Refinement
MR.1 WU4 — Navigation Refinement
MR.1 WU5 — Form Refinement
MR.1 WU6 — Dashboard Refinement 2
```

Dashboard does not reclaim WU1.

Avoid:

```text
WU1.2
WU1b
WU1-R2
```

The WU number represents execution sequence.

The title preserves surface lineage.

---

## 7. Batch

A Work Unit may be decomposed into implementation batches.

Syntax:

```text
MR.x WUy Batch n
```

Batch numbering is local to one WU.

Example:

```text
MR.1 WU2 — Roles Refinement
  Batch 1 — Permission grouping model
  Batch 2 — Accordion interaction
  Batch 3 — Responsive/accessibility validation
```

The next WU resets its batch numbering.

Batch count is driven by implementation complexity and validation needs.

A Batch is not an independent milestone.

---

## 8. Example MR

```text
MR.1 — Admin UX Refinement

WU1 — Dashboard Refinement
  Batch 1 — Card hierarchy
  Batch 2 — Responsive layout
  Batch 3 — Focused validation

WU2 — Roles Refinement
  Batch 1 — Permission ownership grouping
  Batch 2 — Accordion presentation
  Batch 3 — Responsive/accessibility validation

WU3 — Media Refinement

WU4 — Navigation Refinement

WU5 — Form Refinement

WU6 — Dashboard Refinement 2
```

Numbers may continue:

```text
WU7
WU8
...
WU-n
```

as long as they remain within the active MR scope.

---

## 9. When to Create a New MR

A new MR should be created when:

- the previous MR is intentionally closed;
- the refinement boundary materially changes;
- a new refinement era or product area needs separate governance;
- the current MR boundary would otherwise become an unlimited miscellaneous bucket.

Example:

```text
MR.1 — Admin UX Refinement
MR.2 — Public UX Refinement
```

A new MR is not required merely because WU count becomes large.

Scope boundary matters more than number.

---

## 10. Closed MR

A closed MR is a historical accepted baseline.

New refinement findings must not reopen it by default.

If the same surface needs refinement later after MR closure:

```text
new refinement
-> next appropriate active/new MR
-> new WU
```

Closed does not mean "closed until another idea appears."

---

## 11. Relationship to Major Milestones

After full M3 closure, major milestone development is not assumed to be strictly:

```text
M4 -> M5 -> M6
```

Future development may proceed by need and explicit dependency.

MR work may coexist with M4/M5/M6 work when dependencies permit.

Examples:

```text
MR.1 WU2 independent from M4.x
```

or:

```text
M4.x depends on MR.1 WU3
```

Only real dependency should impose ordering.

---

## 12. Dependency Model

Preferred roadmap model:

```text
dependency graph
```

not:

```text
single forced linear sequence
```

Every cross-track dependency should be explicit.

An MR does not automatically block M4/M5/M6.

A major milestone does not automatically block MR work.

---

## 13. Refinement Candidate Capture

Before an MR/WU is formally adopted, findings may be preserved as candidates.

Conceptual candidate record:

```text
Surface
Finding
Rationale
Proposed direction
Known constraints
Candidate MR
Status: Deferred / Candidate
Revisit trigger
```

Example:

```text
Surface:
Roles

Finding:
Permission navigation degrades as module permissions grow.

Direction:
Accordion grouping by explicit permission ownership/domain.

Candidate:
MR.1 Admin UX Refinement

Status:
Concept / Deferred

Revisit:
MR.1 preparation
```

Candidate status does not authorize implementation.

---

## 14. Roles Permission Refinement Candidate

Current concept candidate:

```text
Surface:
Roles permission editor
```

Direction:

- group by explicit domain/module ownership;
- `System` is a formal platform group;
- a single-permission module still receives its own group when ownership is clear;
- `Others` is only for genuinely unclassified permissions;
- render groups as accordion sections;
- show selected/total count;
- optional Select All per group;
- preserve permission semantics and authorization behavior.

Candidate location:

```text
MR.1 — Admin UX Refinement
```

This is not scope of active M3.11 Form Manager.

---

## 15. Relationship to Historical M3.R1

Historical:

```text
M3.R1 — Admin Shell Retouch 1
```

is a closed M3 sequencing work item.

MR.x is a different namespace.

The similarity in letters must not be used to imply lifecycle continuity.

Conceptual distinction:

```text
M3.R1
= historical sub-milestone inside M3 sequence
```

```text
MR.1
= refinement milestone namespace
```

---

## 16. Preparation and Adoption

Before an MR begins:

1. identify candidate refinements;
2. verify current accepted baselines;
3. define locked MR boundary;
4. classify findings;
5. decide adopted WUs;
6. define validation model;
7. define branch/repository workflow;
8. document exclusions.

Individual future WUs may still require focused preparation before implementation.

---

## 17. MR Governance Boundaries

An MR must not silently:

- create new domain capabilities;
- change permission semantics;
- alter ownership contracts;
- add unrelated schema;
- change API contracts;
- absorb defects that require corrective lifecycle;
- become a generic backlog bucket.

If refinement reveals a functional/platform requirement, reclassify it to the appropriate milestone/capability track.

---

## 18. Numbering Rules

```text
MR.x
x increases per refinement milestone.
```

```text
WU y
y increases monotonically inside MR.x.
```

```text
Batch n
n is local to WUy and starts from 1.
```

No fixed maximum is required for x, y, or n.

Their meaningful boundary is scope, not arithmetic.

---

## 19. Current Concept Decision

```text
Refinement namespace
= MR.x
```

```text
MR scope
= explicitly locked
```

```text
WU number
= sequential work identity, not surface identity
```

```text
Repeated surface
= next WU number
```

```text
Batch
= local decomposition inside WU
```

```text
MR may coexist with M4/M5/M6
= yes, dependency permitting
```

```text
Closed MR
= not reopened by default
```

---

## 20. Open Questions

1. Does every MR require its own dedicated branch or only every WU?
2. Should MR preparation create a single contract or a lightweight index plus WU contracts?
3. What closure evidence is mandatory at MR level beyond WU closure?
4. How are late candidate WUs adopted into an already active MR?
5. Does each repeated surface title use `Refinement 2`, `Refinement 3`, or another lineage label?
6. How are cross-repository refinements handled when a branded module has independent governance?
7. Should MR numbering be global per project repository or per independently governed product repository?

---

## 21. Non-Authorization

This concept does not:

- create MR.1;
- modify the authoritative roadmap;
- authorize Roles refinement;
- authorize Dashboard refinement;
- reopen M3.R1;
- authorize implementation;
- create branches;
- change project governance automatically.

Formal adoption requires project roadmap/governance review.