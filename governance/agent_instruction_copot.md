# COPOT AGENT INSTRUCTION TEMPLATE
Date version: 2026-09-25 14:37:30 WIB

## TEMPLATE USAGE

This template is a modular baseline for producing an `Agent Instruction` for a `Technical Executor`. It is not a prompt that must be used or transferred in full.

When producing a final `Agent Instruction`:

- include only sections that are material to the task;
- omit unused placeholders and sections;
- do not repeat project instructions or authoritative project context that can be resolved directly by the `Technical Executor`, unless they are material to the execution boundary;
- do not copy full source files or `Authoritative Documentation` when the `Technical Executor` can read the applicable source directly; do not transfer or instruct the `Technical Executor` to consume the full `Handoff` or `Workplan Set`.
- preserve all material targets, requirements, acceptance criteria, scope boundaries, authorization boundaries, validation requirements, approval gates, safety boundaries, stop conditions, and reporting requirements;
- adapt wording and instruction structure to the capabilities, limitations, permission boundaries, and execution environment of the `Technical Executor`;
- do not reduce required scope, validation, safety, or authorization boundaries merely to make the instruction shorter.

Goal: produce the minimum execution delta that still provides sufficient context and constraints for reliable first-pass success.

---

## INSTRUCTION LANGUAGE

Write the final `Agent Instruction` in English by default, independently from the user's conversation language.

Preserve code, identifiers, paths, branch names, commands, API names, exact error messages, defined terms, and official technical or domain-specific terminology in their authoritative/original form when translation could reduce meaning, identity, or precision.

---

## DELIVERY MODE

Determine the applicable delivery mode before finalizing the `Agent Instruction`.

Delivery mode governs how the instruction is delivered to the `Technical Executor`. It does not expand task scope, execution authorization, approval state, validation requirements, stop conditions, or external side-effect permissions.

### A. User-Mediated Delivery

Use by default when the user has not explicitly requested direct transfer or invocation.

Rules:

- present the final `Agent Instruction` to the user in a copy-ready form;
- keep the instruction compact and sufficient for execution;
- include only context that is material to the execution boundary;
- preserve all applicable target, scope, authorization, validation, approval, stop, and reporting requirements;
- do not assume that presentation to the user authorizes execution by the `Technical Executor`.

### B. Direct Transfer

Use only when the user has explicitly requested direct transfer, direct handoff, direct invocation, or an equivalent delivery action to the `Technical Executor`.

Rules:

- transfer only the final `Agent Instruction`; do not transfer the full `Handoff` or other non-executor-facing artifacts from `Governance` or `Workplan Set`;
- preserve the same target, scope, authorization, validation, approval, stop, and reporting boundaries that would apply to user-mediated delivery;
- do not expand authority because direct-transfer capability is available;
- do not infer direct-transfer authorization from device, platform, execution environment, convenience, tool availability, or recommended execution routing.

### C. Explicit Delivery Override

Use when the user explicitly specifies another delivery method that is compatible with applicable project and execution constraints.

Rules:

- preserve the same execution boundary regardless of transport method;
- adapt formatting only as needed for the destination or interaction surface;
- do not allow the delivery method to redefine scope, authorization, validation, stop conditions, or reporting requirements.

---

## PROJECT CONTEXT

`Project`: **COPOT**

Current target: `<material execution target>`

`Continuity Boundary`: `<applicable boundary / N/A>`

`Repository`: **Remote Git Repository**

`Repository Link`: **https://github.com/blackdjurix/copot.git**

`Integration Target`: **main**

`Technical Executor`: **Codex**

Execution environment: **Local Workspace** by default; **Cloud** when selected, required, or materially preferable

Execution workspace: `<path / workspace / URL / provider / attached source / other applicable location / N/A>`

Expected authoritative state: `<material version / branch / commit / revision / anchor / lifecycle state / N/A>`

Rules:

- include only project and execution context that is material to the task;
- do not assume that every `Project` uses a `Repository`, Git, branches, commits, or a local workspace;
- when repository state is material, distinguish authoritative `Repository` state from execution-workspace state;
- when Git is applicable, include branch, commit, tracking, ahead/behind, or workspace state only when material to execution safety or continuity;
- do not treat execution location as authority unless applicable project governance explicitly defines it as authoritative.

COPOT repository workflow:

- `main` is the authoritative integration target;
- use short-lived feature branches when a feature branch is required;
- integrate to `main` using fast-forward only;
- do not treat a local workspace, runtime copy, disposable instance, or unpushed branch state as an authoritative checkpoint.

---

## TASK CLASSIFICATION

Classify the task only when classification materially helps determine execution depth, validation breadth, approval gates, or reporting detail.

### A. Narrow

Use for a bounded, low-risk task with limited dependencies or a simple read-only investigation.

Typical expectations:

- inspect the target source;
- inspect direct dependencies when material;
- perform focused validation;
- verify execution-workspace safety before authorized write operations.

### B. Standard

Use for work spanning multiple related files, components, behaviors, or connected dependencies without elevated operational or architectural risk.

### C. Elevated-Risk

Use when the task materially affects areas such as:

- security or permissions;
- data integrity;
- schema or migration;
- production or shared environments;
- public API or compatibility;
- architecture or cross-component contracts;
- release, publication, or external side effects;
- irreversible or difficult-to-recover operations.

Rules:

- classification does not expand execution authorization;
- classification does not reduce required scope, validation, acceptance criteria, approval gates, or stop conditions;
- use only the execution depth and validation breadth justified by the actual task and risk;
- omit this section from the final `Agent Instruction` when classification does not materially improve execution.

---

## TECHNICAL EXECUTOR COMPATIBILITY

Identify the capabilities and constraints of the `Technical Executor` that are material to the task, including when applicable:

- authoritative source access;
- `Repository` access;
- filesystem read/write access;
- terminal or command execution;
- Git capability;
- network access;
- test or validation execution;
- external services or integrations;
- session/runtime persistence or continuity;
- permission, sandbox, approval, and external side-effect boundaries.

Do not require capabilities that are unavailable to the `Technical Executor`.

If a material capability is unknown:

- use conditional instructions where appropriate;
- do not claim verification or execution that has not occurred;
- report blockers or limitations that materially affect the target;
- do not silently reduce, redefine, or substitute the target without reporting the impact.

Do not assume access, permissions, tools, services, or execution capabilities that have not been established.

## CONDITIONAL APPLICABLE GOVERNANCE EXECUTION CONTEXT

Include only when an active segment in `Applicable Governance` materially affects the task.

COPOT preferred routing:

- `Runtime`: XAMPP.
- `Gateway`: Tailscale.
- `Design Tooling`:
  - `Prototyping`: Figma.
  - `General`: Canva.

Rules:

- treat these values as preferred/default project routing, not exclusive capability boundaries;
- a task-level alternative may be used when capability, speed, fidelity, editability, compatibility, execution environment, or task-specific requirements materially justify it;
- using an alternative for one task does not change the canonical mapping in `Applicable Governance`;
- when a task-level alternative is material, state the actual route and why it is being used;
- do not change project-level routing unless the authorized target explicitly includes a durable governance decision;
- tool or gateway availability does not expand implementation, repository, external-side-effect, or direct-transfer authorization.

COPOT segment-specific boundaries:

- **Runtime**: disposable runtime state is non-authoritative; runtime address/port may rotate; verify the intended runtime role and current endpoint before relying on runtime evidence.
- **Gateway**: treat Tailscale as an access/routing layer; verify the intended target runtime; do not hard-code a rotating disposable-runtime port as the durable gateway contract.
- **Design Tooling**: preserve required source fidelity and editability; use human acceptance when the outcome materially depends on subjective design/taste.

---

## TASK

Primary target:

`<state the concrete outcome>`

Acceptance criteria:

- `<criterion 1>`
- `<criterion 2>`
- `<criterion 3>`

Verify the actual outcome against each applicable acceptance criterion and report the supporting evidence.

---

## SCOPE BOUNDARY

In scope:

- `<allowed files, behavior, workflow, component, data, environment, or other applicable area>`

Out of scope:

- `<excluded area or concern>`

Forbidden actions:

- `<actions that must not be performed>`

When material, explicitly identify restricted actions such as changes to `Authoritative Documentation`, schema, dependencies, public API, migration, `Repository` state, deployment, publication, tagging, release, production/shared environments, or other external side effects unless specifically authorized by the applicable execution boundary.

Rules:

- do not expand scope based on adjacent findings, convenience, or available capability;
- do not treat an out-of-scope finding as authorization to modify it;
- report materially relevant out-of-scope findings without acting on them unless the applicable execution boundary authorizes further work.

---

## EXECUTION GATES

Use this section only when the task requires staged execution, elevated-risk handling, explicit approval boundaries, or controlled progression between materially different execution states.

Define only the gates that are material to the task.

Example gate sequence:

- Gate 1: audit, investigation, or planning;
- Gate 2: implementation or controlled modification;
- Gate 3: validation or acceptance verification;
- Gate 4: `Repository` mutation, deployment, publication, release, external side effect, or another separately authorized operation.

For each applicable gate, define the condition required before proceeding to the next gate.

Rules:

- do not cross a gate until its required conditions and applicable approvals are satisfied;
- completion of one gate does not automatically authorize the next gate;
- do not infer authorization from successful validation, available capability, remaining time, or technical feasibility;
- stop and report when the next gate requires approval or authorization that has not been provided.

---

## BEFORE STARTING

1. Identify the context, capabilities, constraints, and execution environment that are material to the task.
2. Inspect only what is relevant, including when applicable:
   - execution workspace or project root;
   - applicable `Repository` and repository workflow;
   - current branch, revision, HEAD, tracking, ahead/behind, or equivalent repository state;
   - project instruction files;
   - materially relevant `Authoritative Documentation`;
   - relevant implementation, configuration, data, and tests or validation sources.
3. Do not assume that every `Project` uses Git, a remote repository, branch-based workflow, standard documentation, automated tests, a package manager, CI/CD, deployment, or release processes.
4. Do not inspect the entire project, repository, history, documentation set, or test suite without a material reason.
5. When authoritative sources, implementation, tests, specifications, contracts, or recorded project state materially conflict:
   - identify the conflicting sources;
   - distinguish observed current behavior from intended or accepted behavior;
   - determine the applicable authority boundary when it can be established from available project sources;
   - stop and report when resolving the conflict requires a decision outside the authorized execution boundary.
6. Protect unrelated workspace, repository, data, and environment state.
7. If the expected authoritative state materially differs from the verified starting state, stop and report the mismatch. Do not automatically repair, reset, synchronize, migrate, overwrite, or otherwise mutate state unless specifically authorized.

---

## SOURCE OF TRUTH BY FUNCTION

Use each source according to its applicable function and authority boundary.

- **`Agent Instruction`**: concrete execution target, scope, authorization, validation requirements, approval gates, stop conditions, and reporting requirements for the current task.
- **Project instruction**: project-specific execution rules, workflow constraints, locked decisions, and applicable executor-facing boundaries.
- **`Authoritative Documentation`**: accepted/current project truth within the documented authority of each applicable artifact.
- **Implementation**: observed current implementation behavior and structure.
- **Tests or validation evidence**: behavior demonstrated by the checks that were actually executed or otherwise established.
- **Specification, contract, or technical documentation**: intended behavior, interfaces, architecture, or other defined technical obligations within its applicable authority.
- **Repository/project records**: version, history, lifecycle, change, or integration state within their applicable authority.
- **Execution workspace or environment state**: actual current working condition of the environment being inspected or modified.

Do not treat `Handoff`, conversation history, `Workplan Set`, or other continuity/planning context as implementation authorization or as a substitute for the applicable authoritative project source.

The `Agent Instruction` must not silently override higher-authority project constraints, locked contracts, safety boundaries, workspace protection requirements, or authorization limits.

When sources materially conflict:

- identify the conflicting sources and their applicable functions;
- distinguish observed state, intended state, accepted state, and planning/context state when relevant;
- apply the applicable authority boundary when it can be established;
- stop and report before substantive change when resolving the conflict requires a decision outside the authorized execution boundary.

---

## EXECUTION MODE

Apply only the execution modes that are material to the authorized task. A task may use more than one mode when its execution boundary or applicable execution gates require staged progression.

### A. Audit / Investigation

- inspect only sources and state that are material to the target;
- collect relevant evidence;
- distinguish observed fact, inference, recommendation, and unresolved decision;
- do not perform implementation or other state-changing work unless specifically authorized;
- do not expand scope based on unrelated findings.

### B. Implementation

- understand the relevant execution path, dependencies, and affected behavior before modification;
- modify only the areas required by the authorized target;
- update tests or validation assets when behavior changes and such updates are material;
- update `Authoritative Documentation` only when documentation mutation is within the authorized execution boundary;
- if the authorized implementation would leave materially applicable `Authoritative Documentation` incorrect but documentation mutation is not authorized, report the required documentation correction without performing it;
- preserve unrelated behavior and state.

### C. Debugging / Fix

1. reproduce, isolate, or otherwise establish the failure using sufficient evidence;
2. identify the root cause when reasonably determinable;
3. implement the minimum correct change within the authorized scope;
4. validate the original failure case;
5. run materially relevant regression checks;
6. report unresolved uncertainty when the root cause or complete validation cannot be established.

### D. Planning / Proposal

- inspect the current state sufficiently to support the proposal;
- identify material dependencies, constraints, risks, and unresolved decisions;
- define the proposed scope and boundaries;
- propose an execution sequence or decision path when useful;
- do not perform implementation, `Repository` mutation, or other state-changing work unless specifically authorized.

Rules:

- an execution mode does not expand task scope or execution authorization;
- transitioning between execution modes must respect applicable execution gates, approval boundaries, and stop conditions;
- do not continue into implementation merely because an audit, investigation, or proposal identifies a possible solution.

---

## CONTEXT READING RULES

Use progressive reading:

1. project instructions;
2. target sources;
3. direct dependencies;
4. related tests or validation sources;
5. materially relevant `Authoritative Documentation`;
6. expand only when the available evidence is insufficient for safe and complete execution.

Do not dump the entire `Repository`, read all `Authoritative Documentation`, inspect full history, run broad scans, or expand context without a material reason.

---

## SCOPE RULES

- Work only within the authorized scope.
- Do not perform speculative work, unrelated refactoring, or adjacent improvements that are not required by the target.
- Do not alter naming, formatting, architecture, behavior, dependencies, compatibility, lifecycle structure, or other project boundaries unless the authorized target materially requires it.
- Preserve backward compatibility when required by the applicable project boundary.
- Prefer the minimum sufficient change when it fully satisfies the target and acceptance criteria.
- Do not build new abstractions, systems, or infrastructure when a smaller correct change is sufficient.

---

## USAGE-FRIENDLY RULES

Minimize unnecessary context, tool calls, commands, repeated reading, repeated validation, compute, verbose output, speculative exploration, repeated reporting, and alternative attempts after one approach has been sufficiently validated.

Do not reduce correctness, completeness, validation, security, safety, acceptance criteria, relevant edge cases, workspace checks, or auditability in order to reduce usage.

Optimize for reliable first-pass success, not merely the shortest instruction or the fewest execution steps.

---

## VALIDATION

Choose validation appropriate to the `Project`, authorized target, affected behavior, and execution risk.

Possible checks include:

- syntax, lint, type, or static checks;
- unit, focused, integration, smoke, regression, or equivalent tests;
- build, runtime, configuration, or environment verification;
- manual behavior verification when materially required;
- migration, schema, compatibility, security, or data-integrity checks;
- repository diff or equivalent change review;
- final execution-workspace and affected-state review.

Rules:

1. Start validation closest to the change or target.
2. Expand validation only when the affected scope, dependency surface, or risk justifies expansion.
3. Do not run broad or expensive validation without a material reason.
4. Do not skip material validation merely to reduce usage, execution time, or effort.
5. Do not claim a test, check, or validation passed unless it was actually executed or otherwise established by valid evidence.
6. Explain materially blocked or unavailable validation.
7. Distinguish clearly between validated, inspected only, not tested, blocked, and not applicable.
8. For state-changing tasks, review the final delta, authorized scope, accidental or unrelated changes, generated artifacts, and resulting workspace or environment state.
9. When Git is applicable, use checks such as `git diff --check`, final diff review, and relevant repository-state verification when material.

Map each applicable acceptance criterion to supporting validation or evidence.

---

## CONDITIONAL GIT RULES

Apply this section only when the applicable `Repository` uses Git and Git operations are material to the task.

By default, do not stage, commit, push, pull, merge, rebase, tag, switch branches, create or delete branches, reset, stash, discard changes, or perform equivalent Git mutations unless specifically authorized by the applicable execution boundary.

Release or publication actions remain separate authorization concerns even when Git is used.

Before an authorized Git mutation:

- verify the applicable branch and HEAD;
- inspect status and materially relevant working-tree state;
- verify tracking and ahead/behind state when applicable;
- verify the intended changes and affected scope;
- identify unrelated or unexpected changes and protect them from accidental mutation.

Treat local and remote repository states separately.

Do not automatically repair, reset, synchronize, clean, discard, overwrite, rebase, or otherwise alter unexpected Git state unless specifically authorized.

---

## EXTERNAL SIDE-EFFECT RULES

Do not modify production, remote or shared state, external services, shared data, billing, credentials, user-visible state, third-party systems, deployment targets, publication surfaces, or other external side effects unless specifically authorized by the applicable execution boundary.

Permission or technical capability to perform an external action does not constitute authorization to perform it.

Audit, inspection, preview, dry-run, draft, proposal, simulation, validation, or successful preparation does not authorize final execution.

If an external action requires a separate approval or execution gate, stop before that action until the required authorization is satisfied.

---

## TOOL AND ENVIRONMENT RULES

- Use only tools, environments, services, and execution paths that are materially necessary for the authorized task.
- Prefer the minimum sufficient toolset without reducing correctness, safety, validation, or auditability.
- Do not run destructive or difficult-to-recover commands unless specifically authorized and sufficiently safe.
- Do not use network access, package installation, external APIs, credentials, privileged operations, or external services without material need and applicable authorization.
- Do not expose secrets, credentials, tokens, private keys, or other sensitive information.
- Do not claim tool, environment, service, or execution results that were not actually obtained.
- Summarize long or repetitive outputs while preserving material evidence.
- In read-only environments, provide an applicable patch, replacement artifact, change plan, or equivalent deliverable when that is sufficient for the authorized target.
- Do not impose tools, workflows, package managers, repository models, deployment systems, or execution patterns that the `Project` does not use or require.
- When a required capability is unavailable, report the limitation and its impact rather than silently substituting a materially different execution path.

---

## CONDITIONAL NON-GIT PROJECT RULES

Apply this section when the applicable `Project` or task does not use Git.

- Do not introduce Git workflow, branch semantics, commit assumptions, or repository-history requirements unless specifically required by the authorized target.
- Do not infer unavailable version history or change lineage.
- Report changed artifacts, affected state, and materially relevant execution evidence using the mechanisms available to the `Project`.
- Verify final state using applicable workspace, artifact, environment, system, or other authoritative evidence.
- Preserve unrelated state and do not invent Git-equivalent workflow where none exists.

---

## DOCUMENTATION RULES

Update `Authoritative Documentation` only when documentation mutation is within the authorized execution boundary.
When an authorized change would leave materially applicable `Authoritative Documentation` stale or incorrect but documentation mutation is not authorized, report the required documentation correction without performing it.

Documentation updates may be material when, for example:

- accepted behavior changes;
- setup, configuration, workflow, interface, API, architecture, contract, or operational requirements change;
- lifecycle or project state recorded in `Authoritative Documentation` changes;
- existing authoritative content becomes materially stale or incorrect;
- applicable project instructions require documentation persistence.

Rules:

- follow the established documentation structure and authority boundaries of the `Project`;
- update only materially affected documentation;
- preserve intentional historical records;
- do not rewrite unrelated documentation for consistency, style, or freshness alone;
- do not treat documentation changes as authorization for implementation, release, publication, or other separate actions.

---

## CONTINUITY AND TRANSITION BOUNDARY

`Handoff`, NRP, session-transition decisions, and continuity governance are not responsibilities of the `Technical Executor`.

Do not evaluate, redefine, confirm, or modify:

- NRP status;
- session-transition readiness;
- `Handoff` transition type;
- chatbot/session continuity disposition;
- continuity recovery requirements;
- project-level transition governance.

Do not request or consume the full `Handoff`.

Do not request or consume the full `Workplan Set`. When exact material from a `Workplan` or `Concept` is required for execution, use only the explicitly identified source, logical target, or section and the reading purpose provided by the `Agent Instruction`.

Any context derived from the `Handoff` that is required by the `Technical Executor` must be carried into the `Agent Instruction` as the minimum material execution context rather than requiring the executor to read the `Handoff`.

At the end of the authorized execution slice, report only the execution evidence needed by the controlling chatbot/session to evaluate continuity or next steps, including when material:

- result or implementation state;
- acceptance-criteria result;
- validation evidence;
- unresolved issues or blockers;
- material risks;
- final execution-workspace state;
- final `Repository` or branch state when applicable;
- materially relevant out-of-scope findings;
- recommended technical next action.

Do not make project-level NRP, session-transition, milestone-authorization, Deferred Item adoption, or equivalent governance decisions in the report.

---

## STOP CONDITIONS

Stop the current execution slice and report when:

- the authorized target has been reached;
- audit, investigation, planning, or other explicitly bounded work is complete;
- applicable acceptance criteria have been sufficiently evaluated;
- a material blocker cannot be resolved within the authorized boundary;
- further work would exceed scope;
- a material authority or Source of Truth conflict requires a decision outside the execution boundary;
- required access, credentials, dependency, permission, capability, or environment is unavailable;
- the next action is destructive, irreversible, externally visible, or otherwise separately gated and is not authorized;
- an applicable approval or execution gate has not been satisfied;
- verified starting state materially differs from the expected state and safe continuation cannot be established;
- continuing would require an unauthorized repository mutation, deployment, publication, release, migration, production action, or external side effect.

Do not continue into another `Continuity Boundary`, target, execution gate, repository operation, deployment, release, or other materially separate work merely because time, context, tools, or compute remain available.

---

## REPORT

Use the minimum report structure that still preserves material execution evidence.

Use report depth proportional to the task, execution risk, and evidence needed for downstream decisions. Task-classification labels do not need to appear in the final Agent Instruction or executor report when classification itself is not material.

### Narrow Task

1. Result, changes, or findings
2. Validation or supporting evidence
3. Issues, blockers, or material limitations
4. Final workspace or affected-state summary when relevant

### Standard / Elevated-Risk Task

1. Starting-state and source verification
2. Findings, changes, or affected artifacts
3. Acceptance-criteria results
4. Validation performed and outcomes
5. Scope and authorization-boundary verification
6. Issues, blockers, risks, or materially relevant deferred/out-of-scope findings
7. Final workspace, environment, and `Repository` state when applicable
8. Recommended technical next action

For audits or investigations:

- order findings by materiality or severity when useful;
- distinguish facts, inference, recommendations, and unresolved decisions;
- include exact artifact, file, line, location, command, test, or other evidence references when available and materially useful.

Reporting rules:

- do not repeat the entire `Agent Instruction`;
- do not paste large unchanged source, `Authoritative Documentation`, or irrelevant logs;
- do not claim validation, verification, execution, or state that was not actually established;
- do not hide blockers, uncertainty, skipped validation, or scope deviations behind vague wording;
- keep the report concise enough to support downstream project decisions without discarding material evidence.