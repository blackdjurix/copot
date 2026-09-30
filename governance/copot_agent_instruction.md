# AGENT INSTRUCTION TEMPLATE
Date version: 2026-09-30 21:32:22 WIB

## Purpose

This is a thin, task-specific execution contract for the `Project` Technical Executor.

Canonical governance identity: resolve from Rule variable `Governance Agent Instruction`.

## Semantik title Agent Instruction

Dua baris pertama merupakan title session dan title instruksi.

Format:

`Project` <Continuity Boundary> — <Title>
`<Instruction Title>`

Baris pertama mengidentifikasi project, session atau continuity boundary, dan title continuity. Baris kedua menjelaskan pekerjaan teknis yang dilakukan oleh Technical Executor pada interaction atau bubble tersebut.

`Instruction Title` harus berupa ringkasan pekerjaan teknis aktual, bukan salinan Handoff, Workplan, Concept, authorization, atau lifecycle governance. Jangan menambahkan prefix `AGENT INSTRUCTION` atau wrapper generik lain pada title yang dikirim ke Technical Executor.

It must be generated for the exact authorized execution slice. It is not a copy of the full Rule, Handoff, Workplan, or project lifecycle governance.

Apply the minimum execution delta principle: include a field only when omitting it could cause a wrong target, wrong branch or anchor, unauthorized action, missed dependency, invalid validation, unsafe side effect, or missed stop condition.

### Internal generation and leakage boundary

These governance-routing rules are for GPT's internal compilation process and must not be copied into a generated Agent Instruction. The generated instruction must contain only the execution delta required by the Technical Executor: target, exact payload, technical scope, authorization, dependency, validation, and stop condition.

Do not reproduce governance-only vocabulary or negative governance lists in a generated instruction unless the term is an exact technical input required for execution. Prefer generic execution boundaries such as `Execute only the supplied technical slice` and `Do not infer or expand requirements beyond the supplied payload.`

Before delivery, perform a leakage preflight: remove governance wording that does not change execution, remove redundant prohibition lists, and verify that every remaining field is material to the authorized technical slice.

Do not copy automatically:

- full Handoff or lifecycle history;
- internal project/session decision reasoning;
- prior troubleshooting or old Git states;
- full planning library or repository history;
- thread-level saved-concept history unless one unresolved item changes execution.

## Delivery

- Delivery: `USER-MEDIATED / DIRECT TRANSFER`
- Explicit direct-transfer request: `<YES / NO>`
- Technical Executor: `Technical Executor`
- Authorization source: `<exact source, scope, actor, and action; never a role pointer or generic accepted boundary>`

Direct transfer is permitted only when explicitly requested by the user. Transport method does not expand authority or scope.

Delivery context must identify the applicable route:

- local writable workspace;
- remote/cloud execution;
- local runtime validation;
- user-mediated delivery.

Use only the route required by this execution slice.

## Project context

- Project: `Project`
- Continuity Boundary: `<Work Unit / batch / workstream / phase>`
- Objective: `<single concrete objective>`
- Repository/workspace: `<exact identity when material>`
- Integration target: `Integration Target`
- Runtime: `Primary Runtime` only when material
- Applicable tool: resolve `Gateway` or `Prototyping` when material; otherwise None

Use only context needed for this execution slice.

## Task

- Task: `<concrete technical action>`
- Mode: `AUDIT / IMPLEMENTATION / DEBUG / VALIDATION / TECHNICAL PRE-CONTRACT REVIEW`
- In scope: `<specific files, behavior, or evidence>`
- Out of scope: `<specific exclusions>`
- Preconditions: `<required starting state>`

Do not infer adjacent work from visible defects, future Work Units, dependencies, or available time.

## Technical pre-contract review

Use this mode only when GPT/user explicitly requests technical analysis of a supplied pre-contract or contract draft.

Required package:

- exact pre-contract or contract payload, inline or through an exact verifiable source;
- technical review scope;
- allowed technical delta;
- explicit authorization for proposal, application, or drafting action;
- required output and validation.

The Technical Executor may identify technical gaps, feasibility constraints, dependencies, interface/data-flow issues, validation gaps, or technical acceptance details. It may propose a technical delta and may apply that delta only when the authorization explicitly permits application. It must not infer missing semantic content or expand the supplied boundary.

Required review statuses:

- `TECHNICAL REVIEW COMPLETE`;
- `CHANGES PROPOSED`;
- `TECHNICAL DELTA APPLIED`;
- `READY FOR GPT/USER REVIEW`;
- `READY TO PROMOTE`;
- `SEMANTIC PAYLOAD NOT SUPPLIED`;
- `BLOCKED`.

`READY TO PROMOTE` means technically ready for GPT/user review. It does not by itself make the draft authoritative. If the exact payload or verifiable source is missing, stop with `SEMANTIC PAYLOAD NOT SUPPLIED`.

## Authorization boundary

Authorized:

- `<exact technical actions>`
- `<exact repository/runtime actions, if any>`
- `<exact validation actions>`

Not authorized:

- scope expansion or boundary changes;
- acceptance, closure, or lifecycle decisions outside the supplied execution slice;
- planning, deferred-scope, or session changes unless explicitly included;
- release, tag, publication, deployment, or integration unless explicitly included;
- unrelated branch or runtime cleanup;
- fixing adjacent findings merely because they are discovered.

A finding is not authorization. Report it and stop or continue only within the written execution boundary.

Authorization to validate does not authorize remediation. Authorization to modify implementation does not authorize documentation, integration, release, branch cleanup, or adjacent Work Unit changes unless explicitly stated.

Do not treat a Handoff field, technical acceptance result, role assignment, dependency satisfaction, test pass, or available capability as authorization. The written authorization source must be exact and traceable.

## Source of truth

Use sources by function:

- Rule for governance and authorization boundaries;
- project instructions and Authoritative Documentation for accepted project constraints;
- implementation for observed behavior;
- tests/evidence for demonstrated behavior;
- identified planning section only when explicitly required by this instruction;
- Handoff only through the minimum context reproduced here.

Do not request or reconstruct full continuity or planning artifacts.

When material sources conflict, report the conflict and stop before substantive change.

If documentation consistency is part of the task, inspect only the identified authoritative document and directly affected evidence. Do not perform a broad documentation rewrite for consistency alone.

## Progressive source reading

Read in this order, expanding only when evidence is insufficient:

1. project instructions;
2. target source;
3. direct dependencies;
4. relevant tests;
5. directly relevant documentation or contracts;
6. additional sources only when required by an observed dependency or conflict.

Do not ask the Technical Executor to reconstruct unavailable context. Translate only the execution delta required by this instruction.

## Planning-input boundary — internal GPT routing rule

This section is an internal generation filter. Do not reproduce it as a governance explanation in a generated instruction. Render only the exact technical dependency or invariant that changes execution.

Planning artifacts are inputs, not implicit authorization.

- Do not implement an item merely because a planning source marks it `NEXT`, `ACTIVE`, or `PROVISIONAL`.
- Do not auto-promote planning content into repository, contract, or roadmap authority.
- Do not execute a deferred or future item merely because it is visible or referenced.
- If planning context is material, name the exact target and reading purpose in this instruction.
- If a Concept is material, identify its canonical title, source, relevant invariant, provenance, and unresolved technical dependency only.
- Preserve stable Deferred Item identity when an explicitly authorized Deferred Item is in scope.

Deferred Item statuses such as `Candidate`, `Unscheduled`, `future`, `KEEP DEFERRED`, `NOT APPLICABLE`, `REJECT`, or `SUPERSEDE` do not authorize execution.

## Conditional documentation and technical-evidence boundaries

This section is an internal generation filter. In a generated instruction, include only the applicable technical operation and its evidence requirement.

Use these boundaries only when the task explicitly includes them:

- Documentation consistency: correct only materially stale or contradictory current-state wording against accepted implementation evidence; preserve historical records and avoid blind search-and-replace.
- Technical evidence: report validation, possible human-required criteria, merge eligibility, blockers, and final Git/environment state. Do not make decisions outside the supplied technical slice.
- Branch lifecycle: perform closure or deletion only when explicitly authorized and only after containment, zero-ahead, remote, and clean-state evidence is verified.

### Minimum technical Concept continuity input

When Concept continuity is material, include only:

- Concept identity and source;
- required technical decision or invariant;
- relevant revision/provenance;
- unresolved technical dependency;
- exact reading purpose.

Do not request or consume the full Concept set, full Handoff, or unrelated planning history.

## Before starting

1. Verify repository/workspace identity and material starting state.
2. Verify branch, HEAD, tracking, and unexpected changes when Git is material.
3. Inspect only target files, direct dependencies, relevant documentation, and focused tests.
4. Confirm the written authorization and preconditions.
5. Protect unrelated state.

If verified state materially differs from the instruction, stop. Do not reset, clean, stash, overwrite, or normalize automatically.

## Dependency boundary

- Direct dependency: `<source, artifact, branch, runtime, or capability>`
- Dependency status: `<satisfied / unsatisfied / unknown / blocked>`
- Dependency evidence: `<exact evidence>`
- Dependency action authorized in this slice: `<yes/no and exact action>`

Do not resolve or implement a dependent task merely because its dependency is visible. Report unsatisfied or newly discovered dependencies.

## Execution gates

Use only the gates material to this task:

- Gate 1 — inspect/plan: target, starting state, dependencies, and authorization verified;
- Gate 2 — modify: implementation authorization and preconditions verified;
- Gate 3 — validate: relevant technical checks completed or limitation recorded;
- Gate 4 — repository/external action: separate authorization and final-state review verified.

Do not cross a gate until its conditions are satisfied. Completion of one gate does not authorize the next. Stop before an unapproved gate.

## Execution rules

- Make the minimum sufficient change.
- Preserve unrelated behavior and state.
- Do not modify production, shared state, credentials, external services, or remote/network configuration without explicit authorization.
- Do not perform repository mutation unless written authorization includes that mutation.
- Do not treat test failure as permission to fix neighboring code or fixtures.
- Do not make decisions outside the supplied technical slice.

## Conditional tools

### `Primary Runtime`

Use only when runtime validation is in scope. Treat runtime copy as disposable/non-authoritative. Do not turn a runtime port into a durable project identifier.

### `Gateway`

Use only when remote access is in scope. Verify target runtime and route. Do not expose services, change firewall/network configuration, or alter credentials without explicit authorization.

### `Prototyping`

Use only when visual/prototype work is in scope. Treat prototype output as reference until accepted. Do not infer implementation authorization from a design artifact.

## Git

Apply only when Git is material and authorized:

- verify repository, branch, HEAD, upstream, and unexpected state;
- use short-lived feature branch when required;
- preserve local/remote distinction;
- commit/push/integrate/delete branches only when explicitly authorized;
- do not rebase, reset, clean, stash, force-push, or rewrite accepted history unless explicitly authorized.
- branch closure requires accepted-tip, containment/zero-ahead, remote, and workspace verification before deletion when deletion is authorized.

The remote repository/branch remains durable authority. Do not treat an unpushed commit, runtime copy, or disposable workspace as an authoritative checkpoint. After an authorized push, independently verify the resulting remote tip when possible.

## Validation

Run validation closest to the target first. Report exact checks and distinguish:

- passed;
- failed;
- inspected only;
- not run;
- blocked;
- not applicable.

Map acceptance evidence to the technical criteria written in this instruction. Do not claim project acceptance.

Validation rules:

1. start closest to the change;
2. expand only by actual impact;
3. never claim unexecuted tests;
4. distinguish `PASS`, `FAILED`, `INSPECTED ONLY`, `NOT RUN`, `BLOCKED`, and `NOT APPLICABLE`;
5. review the final diff and accidental changes;
6. do not repeat accepted suites without a concrete regression reason;
7. documentation-only work does not require runtime regression unless review exposes a behavior inconsistency.

## Stop conditions

Stop and report when:

- the execution slice is complete;
- a material source/authority conflict exists;
- starting state drift is detected;
- required access, capability, dependency, or approval is missing;
- a finding requires scope expansion;
- the next action is destructive, irreversible, external, or separately gated;
- continuing would modify an adjacent Work Unit, project boundary, or unrelated artifact.

## Report

Return only execution evidence:

1. target and result;
2. files/artifacts changed or inspected;
3. technical acceptance criteria and evidence;
4. validation commands/results;
5. blockers, risks, unresolved/unsaved technical payload, and out-of-scope findings;
6. final workspace/repository/runtime state when material.

Do not add governance conclusions or authorize a next task. Report only technical evidence and the written execution result.

## Continuation

For same-thread continuation, carry only the changed target, anchor, authorization, scope, validation, stop condition, and unresolved technical issue. Do not repeat unchanged governance, environment, or history. This is an internal GPT routing rule; the generated continuation must contain only the material technical delta.

For same-thread continuation with accepted state, use `CHECK-AND-RUN`: verify only material drift in target, authorization, repository/branch anchor, dependencies, workspace, and relevant external state. Do not repeat equivalent continuity verification. If material drift, new evidence, authority conflict, or a changed boundary exists, return to full verification and stop when the issue cannot be resolved safely.

Use a concise continuation token such as:

`Continue the current task.`

`Changed target: [...]`

`Changed authorization: [...]`

`New stop condition: [...]`

`All other prior boundaries remain unchanged.`

The controlling GPT/user evaluates those decisions from this report.

If execution continues in the same technical thread, return a concise continuation token containing the execution slice, final technical state, unresolved technical issue, and exact next technical check. Do not use the token to authorize a new scope or project decision.
