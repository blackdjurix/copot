# `<Title> (<Milestone / WU / Batch>)`
Date version: 2026-08-29 18:08:49 WIB

This is a GPT/model/session continuity artifact for COPOT. It is not an Agent Instruction and must not be copied wholesale to Work, Codex, or another technical executor.

# USAGE BOUNDARY

The Handoff carries the minimum context required for a new GPT/session to recover:
- objective project state;
- authoritative repository anchors;
- relevant unresolved/deferred/risks;
- current/next target;
- materially relevant Workplan/Concept context;
- unresolved thread-level continuity payload when not yet durably reconciled;
- session-transition readiness.

NRP is GPT/session-transition readiness only. Repository project state must use objective lifecycle wording.

Delivery rule:
- deliver the Handoff through an editable writing block when that capability is available;
- do not wrap the Handoff in a triple-backtick code fence;
- fenced code inside the Handoff is allowed only for actual code/config/literal technical content that genuinely requires code formatting.

## HANDOFF TITLE / FIRST-LINE SEMANTICS

The Handoff title is the first line inside the editable writing-block content. Do not use the writing block's external title/name field to carry the Handoff continuity title.

Choose the title from the destination continuity boundary:

- When moving to a new workstream or milestone, use `[Title] ([Milestone])` or `[Title] - [Milestone]`.
  - Example: `Core Module Refinement (MR.2)`
  - Example: `Core Module Refinement - MR.2`
- When moving to a new Work Unit or Batch, use `[Title] ([WU/Batch])` or `[Title] - [WU/Batch]`.
  - Example: `System Manager (WU2)`
  - Example: `System Manager - Batch 2`

Use the current accepted project vocabulary when selecting `[Title]`; examples illustrate the heading shape and do not override later terminology decisions.

Do not prepend a generic heading such as `PROJECT SESSION HANDOFF TEMPLATE`, `PROJECT SESSION HANDOFF`, or another wrapper title to a generated Handoff. The destination continuity title is the Handoff's first-line header.

This rule changes only Handoff title/header presentation. All other Handoff structure, continuity semantics, authorization boundaries, NRP rules, bootstrap requirements, and conditional sections remain unchanged.

## Direct Handoff Boundary

This Handoff reinforces the cross-project routing rule defined in the canonical `governance/project_rule.md`.

- Do not directly transfer this Handoff or invoke Work, Codex, or another execution destination from it unless the user explicitly requests that direct transfer.
- A new session may recommend Work/Codex or prepare an executor instruction, but recommendation is not permission to invoke.
- If execution through Work or Codex is requested, derive a separate Agent Instruction using the canonical `governance/Agent_Instruction_Template.md`.
- Direct-transfer permission controls transport only; it does not widen technical authorization, scope, validation, stop conditions, or external side-effect permissions.
- Never use Handoff as the executor payload merely because a direct-transfer capability exists.

# PROJECT — `<name>`

## Required Core

Authoritative repository: `<remote / N/A>`

Accepted integration baseline: `<branch + verified commit / N/A>`

Authoritative active implementation state: `<branch + verified commit / N/A>`

Current phase / milestone / provisional track / work unit: `<state>`

Latest verified remote commit: `<hash + subject / N/A>`

Remote synchronization state: `<VERIFIED / PARTIAL / UNKNOWN / N/A>`

Next target: `<target>`

Authoritative sources: `<project instruction + relevant repo docs/contracts/source>`

## Conditional GPT Planning Context

Fill only when Workplan/Concept context materially affects continuity or next-target selection.

Workplan logical source: `workplan`

Resolved Workplan file: `<exact filename / N/A / UNAVAILABLE>`

Workplan lifecycle: `<CURRENT / SUPERSEDED / RETIRED / N/A>`

Workplan reconciliation state: `<MID-WORKSTREAM DEFERRED / CLOSURE-RECONCILED / EARLY MATERIAL UPDATE / NEEDS REVIEW / N/A>`

Workplan adequacy for next-target selection: `<ADEQUATE / NEEDS RECONCILIATION / UNVERIFIED / N/A>`

Active provisional track(s): `<logical target(s) / None>`

Promotion candidate(s): `<logical target(s) / None>`

Promoted/closed registry entries still material to lineage: `<logical target(s) / None>`

Relevant Concept identities and sources:
- `<canonical title> — <exact source file + heading when material>`
- `<canonical title> — <exact source file + heading when material>`

Unreconciled thread-level saved concepts/rules: `<summary / None>`

Planning caveat: `<material caveat / None>`

Rules:
- Workplan is a planning/sequencing/provenance artifact, not a live mirror of repository current state;
- one Workplan may span multiple Work Units, workstreams, and milestones;
- normal repository advancement during an active workstream does not require immediate Workplan revision;
- default Workplan reconciliation/consolidation occurs at workstream closure / pre-handoff;
- earlier Workplan update is warranted only when planning knowledge materially changes, durable planning context is needed before closure, or the user explicitly requests it;
- repository contracts/documentation remain authoritative for delivered/current lifecycle truth after promotion;
- do not copy the full Workplan;
- do not copy full Concept bodies;
- canonical Concept title is the logical key;
- exact filenames/headings may be included when they improve traceability or continuity;
- one Concept may have multiple sources and one file may contain multiple Concept identities;
- promoted/closed Workplan registry entries remain provenance, not execution authority;
- Workplan/Concept planning context does not override authoritative repository state;
- session change does not clear unreconciled thread-level saved concepts.

## Conditional Execution Context

Primary execution environment: `<Local authenticated writable workspace / Codex Cloud / Work / other / N/A>`

Alternative execution environment: `<...>`

Writable Git workspace: `<path / remote workspace / N/A>`

Runtime workspace: `<path/URL/provider/N/A>`

Last detected platform: `<Desktop / PC / Mobile / Android / Unknown>`

Active manual-operation executor: `<User / Codex / Unknown>`

Execution context terakhir: `<...>`

Device-transition readiness: `<READY / BLOCKED / UNKNOWN / N/A>`

Direct-transfer authorization for next session: `<EXPLICITLY REQUESTED / NOT REQUESTED / N/A>`

Rule: `NOT REQUESTED` means do not directly invoke/transfer to Work/Codex/another destination. Prepare the Agent Instruction in chat if execution guidance is needed.

# STATUS SEBELUMNYA

Unit kerja terakhir: `<...>`

Implementation/result: `<COMPLETE / PARTIAL / NOT STARTED / BLOCKED / other>`

Latest accepted result: `<objective summary>`

Known unresolved issues: `<detail / None>`

Deferred items materially relevant: `<ID + title + source + status/target + impact / None>`

Known risks: `<detail / None>`

## Conditional Thread-Level Reconciliation State

Use when saved session/thread concepts or governance-relevant decisions remain material.

Payload scope: `<workstream/planning concern>`

Durably reconciled items: `<summary / None>`

Still-unreconciled items: `<summary / None>`

Required disposition before closure: `<Concept update / new Concept / Workplan / Deferred / supersede / reject / N/A>`

Rule: session transition does not imply this payload is cleared. Clear only after durable reconciliation or explicit disposition.

## Conditional Deferred Item Review

Use only when materially relevant to next target.

Deferred Item ID: `<ID>`

Title: `<title>`

Source: `<milestone/work unit>`

Current status/target: `<...>`

Relevance: `<...>`

Disposition: `<ADOPT / KEEP DEFERRED / REJECT / SUPERSEDE / NOT APPLICABLE / PENDING>`

Disposition evidence: `<...>`

Target update required: `<YES / NO>`

## Conditional Acceptance State

Isi hanya bila acceptance masih material terhadap closure/next target.

AI acceptance: `<PASS / PARTIAL / BLOCKED / NOT REQUIRED / UNKNOWN>`

Human acceptance: `<PASS / PENDING / CHANGE REQUIRED / NOT REQUIRED / UNKNOWN>`

Human acceptance reason: `<criterion / N/A>`

Review surface: `<URL / local runtime / device / unavailable / N/A>`

Pending remote anchor: `<branch + commit / N/A>`

# NEXT TARGET AND DEPENDENCY

Target berikutnya: `<target>`

Dependency classification: `<INDEPENDENT / SOFT / HARD / CLOSURE / BLOCKING / N/A / UNKNOWN>`

Dependency evidence: `<material fact only>`

Branch routing: `<from main / continue current / stacked / preparation only / hold / N/A>`

Execution routing: `<Local primary / Cloud / Work / other / N/A>`

Recommended next gate: `<preparation / implementation / validation / human review / docs / merge / closure / other>`

Authorization note: `<what is authorized / what still requires explicit user authorization>`

Rule: Handoff never authorizes the next technical action by itself.

Rule: recommended execution routing never authorizes direct transfer by itself.

# LAST VERIFIED GIT STATE

Anchor classification: `<VERIFIED / PARTIAL / UNVERIFIED / STALE OR CONFLICTED / N/A>`

Verification source/time: `<...>`

Accepted baseline: `<branch + commit>`

Active branch: `<branch + remote commit / N/A>`

Ahead/behind: `<value / unknown / N/A>`

PR / merge / branch lifecycle: `<status>`

All intended changes committed: `<YES / NO / UNKNOWN / N/A>`

All intended changes pushed: `<YES / NO / UNKNOWN / N/A>`

Remote commit independently verified: `<YES / NO / UNKNOWN / N/A>`

Workspace cleanliness: `<clean / dirty / unknown / N/A>`

Writable workspace state: `<synced / ahead / behind / diverged / dirty / not checked / N/A>`

Runtime workspace state: `<synced / stale / dirty / not checked / N/A>`

## Mandatory Branch Lifecycle Closure Audit

Fill when current unit/workstream is closing and Git branches are material.

Integrated target: `<main / other>`

All workstream commits contained in integration target: `<YES / NO / UNKNOWN / N/A>`

Remote branch inventory: `<summary>`

Obsolete branch candidates: `<branches / None>`

Ancestry evidence: `<PASS / FAIL / N/A>`

Zero-ahead evidence: `<PASS / FAIL / N/A>`

Authorized deletions performed: `<branches / None / NOT AUTHORIZED>`

Remote-tracking refs pruned: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Post-cleanup inventory verified: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Final workspace clean/synced: `<YES / NO / UNKNOWN / N/A>`

Main-only / no-op explicitly recorded: `<YES / NO / N/A>`

Rule: branch age/name is never sufficient deletion evidence.

# FINAL CHANGESET SUMMARY

Fill only for completed/closing work.

Base commit: `<hash + subject>`

Final commit: `<hash + subject>`

Commit range: `<base>..<final>`

Changed files: `<summary>`

Classification: `<runtime/tests/docs/config/schema/assets/mixed/none>`

Material behavior changes: `<summary / None>`

Documentation changes: `<summary / None>`

Unexpected changes: `<None / detail>`

# DOCUMENTATION CLOSURE

Documentation status: `<PASS / NOT REQUIRED / DEFERRED / BLOCKED / UNKNOWN>`

Documentation impact: `<behavior/capability/architecture/contract/workflow/milestone/state/none>`

Included in authoritative state: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Post-Git documentation state: `<PASS / NOT REQUIRED / BLOCKED / UNKNOWN>`

## NRP Candidate Documentation Consistency Audit

Fill when current readiness is or approaches NRP CANDIDATE.

Audit status: `<NOT STARTED / IN PROGRESS / PASS / MATERIAL FINDINGS / BLOCKED / NOT REQUIRED>`

Audited authority surfaces: `<AGENTS / README / roadmap / contract / Deferred registry / other>`

Material stale/inconsistent findings: `<None / summary>`

Corrections durable: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Final remote verification after correction: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Rule: material current-state inconsistency in authoritative repository documentation must be corrected and durable before NRP CONFIRMED.

GPT-side Workplan synchronization is governed by the planning reconciliation section below, not by repository documentation consistency.

## Conditional Planning Reconciliation / Adequacy Audit

Use when Workplan/Concept context matters to workstream closure, next-target selection, or next-session continuity.

Planning audit status: `<PASS / NEEDS RECONCILIATION / BLOCKED / NOT REQUIRED / UNKNOWN>`

Workplan reconciliation state: `<MID-WORKSTREAM DEFERRED / CLOSURE-RECONCILED / EARLY MATERIAL UPDATE / UNKNOWN / N/A>`

Continuous repo-to-Workplan synchronization required: `NO`

Workplan adequate for next-target selection: `<YES / NO / UNKNOWN / N/A>`

Lifecycle/provenance entries requiring closure disposition reconciled: `<YES / NO / UNKNOWN / N/A>`

Concept references material and resolvable: `<YES / NO / UNKNOWN / N/A>`

Concept source files/headings traceable where needed: `<YES / NO / UNKNOWN / N/A>`

Unreconciled thread-level payload materially misleading continuity: `<None / summary>`

Remaining planning assumptions requiring audit before next workstream selection: `<None / summary>`

Reconciliation/consolidation required before handoff: `<YES / NO>`

Rule:
- do not fail this audit merely because repository state advanced during an active workstream;
- normal default is closure-time reconciliation/consolidation;
- at closure, reconcile enough planning/provenance state to make next-target selection and handoff non-misleading;
- if the immediate next WU is already authoritative inside the same active workstream and no planning decision is required, Workplan reconciliation may remain deferred until full workstream closure.

# SESSION-TRANSITION READINESS

NRP status: `<NRP NOT READY / NRP CANDIDATE / NRP CONFIRMED / NRP UNVERIFIABLE>`

Interpretation:
- NRP NOT READY: state not sufficiently durable/recoverable;
- NRP CANDIDATE: technical/project state mature but closure/bootstrap evidence not complete;
- NRP CONFIRMED: final durable repo/documentation/planning bootstrap state is sufficient for session transition;
- NRP UNVERIFIABLE: required governance/Handoff/evidence cannot be verified.

NRP evaluation considers:
- accepted objective project state;
- documentation state;
- Documentation Consistency Audit;
- durable final remote Git state;
- mandatory Branch Lifecycle Closure Audit when applicable;
- unresolved/deferred/risks;
- next target;
- closure-time Workplan/Concept reconciliation adequacy when materially required;
- thread-level saved-concept reconciliation state;
- context bootstrap sufficiency;
- Final Repository Stability Gate.

NRP does not change repository project/work-unit/milestone state.

## Final Repository Stability Gate

Before NRP CONFIRMED record:
- intended project mutations complete;
- documentation corrections complete;
- objective closure docs complete where required;
- branch lifecycle/merge/post-merge state complete where required;
- final authoritative remote SHA verified;
- materially relevant planning/Concept continuity is either closure-reconciled or explicitly carried forward without misleading next-target selection;
- no planned repo mutation remains for this closing state.

If repository changes after the assumed final anchor, refresh closure evidence and remain/return to NRP CANDIDATE until the new final state is verified.

## Continue or New ChatGPT Session

Setelah `NRP CONFIRMED`, GPT/user memutuskan lanjut atau pindah sesi berdasarkan panjang sesi, relevansi history, kompleksitas next target, kebutuhan source baru, reasoning aktif, dan bootstrap cost.

Keputusan ini tidak diteruskan sebagai concern technical executor.

# CROSS-MILESTONE / CROSS-TRACK DEPENDENCY GOVERNANCE

Use only when predecessor is not fully accepted or provisional work depends on another track.

- INDEPENDENT: may continue;
- SOFT: independent slices may continue;
- HARD: stacked/ordered work only when justified/authorized;
- CLOSURE: implementation may continue but final closure/merge waits;
- BLOCKING: implementation hold; safe planning/audit may continue.

## Conditional Stacked Branch State

Isi hanya bila HARD dependency benar-benar memakai stacked branch.

Predecessor branch: `<branch>`

Predecessor anchor: `<commit>`

Downstream branch: `<branch>`

Stacked base: `<commit>`

Reason: `<HARD dependency>`

Merge order: `<predecessor → downstream>`

Realignment/revalidation: `<detail / N/A>`

# CONTEXT BOOTSTRAP FOR NEXT SESSION

1. Verify and read `governance/project_rule.md`, `governance/Handoff_Template.md`, and `governance/Agent_Instruction_Template.md` from the accepted remote Git repository state.
2. Never silently fall back to an older Git revision, obsolete timestamped repository file, historical File Library copy, memory, chat history, or summary when canonical governance is unreadable/unverifiable.
3. Read project instruction.
4. Apply canonical `governance/project_rule.md` direct-handoff/execution-routing boundary before considering Work/Codex/agent invocation.
5. Read authoritative repo docs/source relevant to the next target.
6. Read repository `workplan.md` when planning/sequencing/lineage is material; File Library Workplans are historical/correction evidence only.
7. Do not assume Workplan must mirror current repository lifecycle state during an active workstream; evaluate it according to the existing planning reconciliation cadence.
8. Resolve/read only relevant Concept identities and exact source files/headings referenced by Workplan or task.
9. If Handoff carries unreconciled thread-level saved concepts, preserve them until durable disposition; session change does not clear them.
10. Verify remote continuity inside the first relevant task.
11. Do not repeat full accepted audit/validation without concrete regression signal.
12. Review only materially relevant Deferred Items; do not auto-adopt.
13. Do not infer technical authorization from Handoff, Workplan, Concept, or NRP status.
14. Do not infer direct-transfer authorization from execution-routing recommendations or tool availability.
15. Do not infer release/tag/publication authorization from feature/workstream closure.
16. Before generating any Agent Instruction in the new session, read canonical `governance/Agent_Instruction_Template.md`; obtain the instruction structure there rather than from this Handoff.
17. Handoff may carry only material segment-level expectations for a future Agent Instruction, such as heading/title semantics, target, Git context when relevant, scope, authorization, validation, stop conditions, report, and conditional segments when material. Do not hardcode, duplicate, or copy the Agent Instruction template structure into this Handoff.

Continuity status: `<MATCH / CHANGED / CONFLICT / UNVERIFIABLE / NOT TRIGGERED>`

# REQUIRED STARTUP REPORT

Detected platform: `<...>`

Previous platform: `<... / unknown>`

Platform comparison: `<UNCHANGED / CHANGED / AMBIGUOUS / UNDETECTED>`

Active manual-operation executor: `<User / Codex / Unknown>`

Confirmation: `<REUSED / CONFIRMED / NOT REQUIRED / REQUIRED>`

Context DNA: `<project / objective current state / next target / scope / authority / material risks>`

Deferred Review: `<relevant Deferred Item IDs + disposition / None>`

Workplan/Concept Planning Context: `<material logical pointers + reconciliation state / None>`

Thread-Level Continuity Payload: `<material unresolved saved concepts/rules / None>`

Execution Routing: `<Local primary / Cloud / Work / remote agent / other>`

Direct-Transfer Authorization: `<EXPLICITLY REQUESTED / NOT REQUESTED / N/A>`

Handoff Anchor: `<VERIFIED / PARTIAL / UNVERIFIED / STALE OR CONFLICTED / N/A>`

Readiness: `<READY / REQUIRES GOVERNANCE RECOVERY / ANCHOR CONFLICT / WORKSPACE CONFLICT / BLOCKED / OTHER>`

# AUTOMATIC HANDOFF

When user chooses a new session:
- use canonical `governance/Handoff_Template.md`;
- fill required core;
- include conditional Workplan/Concept context only when material;
- reconcile/consolidate Workplan at workstream closure before handoff when it is needed to decide or explain the next planning target;
- do not force a Workplan refresh merely because repository state advanced during an active workstream;
- omit obsolete history, rejected options, resolved troubleshooting, redundant logs, and old planning revisions;
- preserve exact final remote anchor and next target;
- preserve material blockers/deferred/risks;
- preserve unresolved thread-level continuity payload until durable disposition;
- keep executor instruction concerns out of the Handoff body except routing context necessary for next GPT session;
- generation of Handoff does **not** authorize direct transfer to Work/Codex/another destination;
- never invoke or transfer to Work/Codex merely because the Handoff recommends that execution route;
- if the user explicitly requests direct transfer, derive a separate Agent Instruction using canonical `governance/Agent_Instruction_Template.md` and route only that instruction.

# CLOSURE STATEMENT

Handoff is complete when the next GPT/session can recover objective continuity without reconstructing obsolete history, without requiring continuous Workplan mirroring, and without treating the Handoff itself as technical execution authorization or direct-transfer authorization.
