# GENERAL AI AGENT INSTRUCTION TEMPLATE
Date version: 2026-08-30 12:19:25 WIB

## TEMPLATE USAGE

Modular template for Codex/technical AI Agents.

Goal: the smallest instruction that still provides reliable first-pass success.

Agent Instruction = minimum execution delta, not a reformatted Handoff.

Include a field only if omission could cause the wrong target/branch/anchor,
an unauthorized action, a missed dependency, invalid validation, an unsafe side
effect, or a missed stop condition.

Do not copy automatically:
- full Handoff;
- full lifecycle history;
- internal NRP/session-transition reasoning;
- prior troubleshooting;
- old Git states;
- full previous validation inventory;
- full Workplan;
- full Concept library;
- thread-level saved-concept history unless one specific unresolved item changes execution;
- repository history the agent can inspect directly.

Generated technical Agent Instruction default language: English. User-facing
GPT conversation and explanations follow the user's primary conversation
language; for current COPOT conversations, that is Bahasa Indonesia.

Do not translate an identifier, path, branch, command, API name, error message,
filename, status token, or official technical term when translation would reduce
precision. Technical/project vocabulary may remain literal.

## INSTRUCTION PRESENTATION AND HEADING SEMANTICS

- Deliver the Agent Instruction through an editable writing block when available.
- Do not wrap the Agent Instruction in a triple-backtick code fence. Fenced code
  is allowed only for actual code/config/literal technical content that genuinely
  requires code formatting.
- The instruction short description/title MUST be the first line inside the
  writing-block content.
- Do not use the external title/name field for the short description.
- This placement rule changes only header-description presentation; all other
  structure and semantics remain governed by this template.

Heading semantics:
- For the first Agent Instruction after entering a new technical-agent session, the first-line short description is a destination/context heading, at the scope of the technical work context being entered, for example `Core Module Refinement (MR.2)` or `System Manager (WU2)`.
- For subsequent Agent Instructions in the same technical-agent thread, the first-line short description remains required but becomes an instruction-purpose heading, scoped only to that individual instruction, for example `Webcore Navigation & Redirects Extraction`, `Human Acceptance Preview Preparation`, or `WU5 Closure Reconciliation`.
- Do not add or repeat a destination/WU heading merely because the target WU changes inside an already-established technical-agent thread.

# DELIVERY MODE

## Local Authenticated Writable Workspace — Default

Use when the local environment is available, authenticated, writable, and sufficient.

The remote repository/branch remains the durable authority.

For new/resumed/transitioned/untrusted state, verify the relevant repo/branch/
anchor/workspace/synchronization before writing.

For same-thread accepted continuation, use Check-and-Run and do not repeat equivalent continuity verification.

Do not silently normalize drift with reset/stash/clean/discard/force operations.

Durable work requires an authorized commit, push, and independent remote verification.

## Remote / Cloud Execution

Use when selected or materially preferable.

The sandbox is not durable authority. The same full-vs-Check-and-Run rule applies.

## Local Runtime Validation

The runtime workspace is not Git authority. Source fixes belong in the verified writable Git workspace.

# MINIMUM CORE

## TARGET

`Primary target: [concrete outcome]`

## GIT CONTEXT — ONLY IF RELEVANT

`Authoritative remote: [...]`

`Writable workspace: [...]`

`Branch: [...]`

`Expected remote anchor: [...]`

## SCOPE

In scope: `<...>`

Out of scope: `<...>`

## AUTHORIZATION

Authorized: `<exact execution slice and allowed Git/external operations>`

Not authorized: `<scope expansion / Deferred adoption / destructive operations / release-tag-publication / other>`

Rules:
- promotion or repository contract scope is context, not blanket authorization for adjacent work;
- execute only the explicit slice named in this instruction;
- do not infer authorization from Handoff, Workplan, Concept, NRP status, or a previous workstream closure;
- do not cross a user-reserved product/architecture/irreversible/release gate without explicit authorization.

## VALIDATION

Required: `<...>`

Do not rerun: `<accepted evidence only when repetition must be prevented>`

## STOP CONDITIONS

Stop if:
- remote anchor mismatch;
- unexpected dirty/material drift;
- workspace identity cannot be verified;
- scope expansion required;
- required capability unavailable;
- irreversible action unauthorized;
- release/tag/publication would be required but is not explicitly authorized;
- durable delivery unavailable when required.

## REPORT

Default compact report:
1. Verification
2. Changes / Findings
3. Validation
4. Blockers / Risks
5. Final Git / Environment State

Same-thread continuation should report material deltas/exceptions, not reconfirm unchanged accepted state.

# PROJECT SOURCE READING

Use progressive reading:
1. project instruction;
2. target source;
3. direct dependencies;
4. related tests;
5. relevant docs/contracts;
6. expand only if evidence insufficient.

Do not ask the agent to read GPT governance or ChatGPT session history.

# WORKPLAN AND CONCEPT INPUT

Workplan and Concept artifacts are planning inputs, not implicit authorization.

Rules:
- never implement an item merely because Workplan marks it NEXT/ACTIVE/PROVISIONAL;
- never auto-promote a Workplan entry into roadmap/contract/repository authority;
- never auto-adopt a Deferred Item because Workplan references it;
- never treat a Concept as implementation authorization;
- do not `read Workplan and implement whatever is next`;
- if Workplan/Concept context is material, instruction must name the explicit logical target and reading purpose;
- a Concept may be sourced by multiple files/headings; read only the source(s) material to the instructed target;
- a consolidated Concept file may contain multiple logical concepts; do not assume the whole file is in scope;
- promoted/closed Workplan registry entries are provenance, not active execution scope;
- Codex may execute provisional Workplan-defined work only when this instruction explicitly authorizes that target;
- promotion to authoritative repository side must be explicitly authorized as part of the task.

When a Concept is referenced, prefer its canonical Concept title/logical identity and include the exact filename/heading when the task depends on that source location.

# FOCUSED DOCUMENTATION CONSISTENCY AUDIT

Use only when GPT explicitly authorizes a repository documentation audit/correction task.

Operational target wording should be objective, for example:

`Audit materially relevant current repository documentation against accepted committed repository state. Identify and correct stale, contradictory, or misleading current-state wording.`

Do not send internal `NRP CANDIDATE` terminology to the agent.

Preserve intentional historical records. Do not use blind search-and-replace. Non-material style differences are not blockers.

If corrections are authorized, commit/push/remote-verify only intended material fixes.

# DEFERRED ITEM EXECUTION

Deferred Item is not authorization.

`Candidate`, `Unscheduled`, `future`, `KEEP DEFERRED`, `NOT APPLICABLE`, `REJECT`, and `SUPERSEDE` do not authorize implementation.

Only explicit instruction/adopted planned scope may execute a Deferred Item.

Preserve the stable Deferred Item ID where one exists.

# ACCEPTANCE EVIDENCE

The agent reports technical evidence only:
- validation result;
- AI acceptance evidence;
- possible human-required criterion;
- technical merge eligibility;
- blocker/risk;
- final Git/environment state.

Do not ask the agent to decide:
- NRP;
- ChatGPT session transition;
- project governance closure;
- authorization for next milestone/workstream;
- automatic Workplan/Concept/Deferred adoption.

# DEPENDENCY

Include only if it materially changes execution.

`Dependency: <INDEPENDENT / SOFT / HARD / CLOSURE / BLOCKING>`

`Operational consequence: <continue / hold / stack / merge blocked>`

Prefer concise accepted predecessor wording and do not reopen without a concrete regression signal.

# BRANCH LIFECYCLE

Include only when branch action/audit is relevant to the authorized task.

## Branch Closure Audit

For a workstream closure instruction, if branch lifecycle is in scope, require evidence for:
- workstream commit containment in the accepted integration target;
- remote branch inventory;
- ancestry/containment of obsolete candidates;
- zero-ahead status for obsolete candidates;
- deletion only after both conditions pass and deletion is authorized;
- prune where applicable;
- post-cleanup inventory recheck;
- final clean/synchronized workspace where material;
- explicit `main-only` / `no-op` record when no cleanup is required.

Do not delete based on branch age/name or assumption.

If deletion is not authorized, audit and report eligibility only.

Contoh:

`Fast-forward main only. Do not delete branches.`

or

`Delete remote feature branch only after containment and zero-ahead verification.`

Do not paste the full governance doctrine when a concise operation-specific instruction is sufficient.

# EXECUTION GATES

Use only as many gates as the task requires.

For same-thread continuation, do not add a redundant continuity gate merely to reconfirm accepted state.

Do not cross a failed gate.

# REMOTE-AUTHORITATIVE GIT RULES

For initial/resumed/transitioned/untrusted write tasks, verify the relevant repository identity, remote, branch, anchor, workspace identity/state, included changes, scope, and authorization.

Same-thread Check-and-Run inherits accepted state and checks material drift during normal execution.

Do not:
- commit on unexpected branch;
- push unrelated changes;
- auto merge/rebase;
- create/delete branch without authorization;
- tag/release/publish without authorization;
- treat unpushed commit as authoritative;
- edit source in unverified runtime copy.

After push, independently verify the final remote branch/commit where possible.

# RELEASE / TAG / PUBLICATION

Release advancement is separate from feature/workstream completion.

Do not infer release need or release authorization from a completed feature, merged branch, closed workstream, accepted source delta, or Handoff next target.

Tag/release/publication must be explicitly authorized and scoped.

# VALIDATION RULES

1. Start closest to change.
2. Expand only by actual impact.
3. Never claim unexecuted tests.
4. Distinguish PASS / inspected-only / not-tested / blocked.
5. Review final diff and accidental changes.
6. Map acceptance criteria to evidence.
7. Do not repeat accepted suites without concrete regression reason.
8. Documentation-only change does not require runtime regression unless documentation review exposes behavior inconsistency requiring it.

# THREAD-LEVEL SAVED CONCEPTS

Technical executors do not own the thread-level saved-concept lifecycle.

If a saved Concept materially affects execution, GPT must translate only the required execution delta into the instruction.

Do not ask Codex to reconstruct session memory, reconcile the entire thread backlog, decide Concept ownership, or decide whether a saved concept should become Workplan/Deferred/repository scope unless the explicit task is a repository-side documentation/planning implementation and that scope is authorized.

# CONTINUATION TOKEN RULE

For the same technical-agent thread, carry only the changed target, anchor, authorization, scope, validation, and stop condition.

Use:

`Continue the current task.`

`Changed target: [...]`

`Changed authorization: [...]`

`New stop condition: [...]`

`All other prior boundaries remain unchanged.`

Do not repeat unchanged governance narrative or environment/history.
