# Project Logging — Project State Checkpoint & Activity Journal Rollover
Date version: 2026-10-11 20:36:54 WIB

Status: ACTIVE TRIAL / PROJECT GOVERNANCE CANDIDATE / NOT PROMOTED
Project: COPOT
Provenance: Reconciliation of the original Checkpoint & Devlog — Project Governance Trial (2026-10-10 16:19:51 WIB; conversation TLC-12), subsequent user/GPT planning decisions, and the accepted Project Logging Workplan identity. This revises the existing Concept in place; historical identity and earlier Git revisions are retained.

## Capability identity and authority
Project Logging is a proposed governance-regulated capability, parallel in responsibility to Documentation. It is not a replacement for contracts, Workplan, Handoff, accepted evidence, Git history, or the canonical Rule. It has two independent logging objects sharing one repository commit timeline, not a combined operational lifecycle.

This is planning Concept authority only. It does not amend `governance/copot_project_rule.md`, implement automated logging, authorize Git tag creation, authorize cleanup/reset, authorize package generation, or promote a governance policy. Annotated tags, commits, release/package artifacts, and project-flow decisions remain separate actions with their own authority.

## Object A — Project State / Checkpoint
- Purpose: name an accepted, sufficiently validated stable project baseline for reconstruction and subsequent project workflow. A checkpoint is not a release identity, a disposable runtime, or an NRP.
- Save point: a uniquely named annotated Git tag resolving to an exact accepted repository commit; the tag anchors the entire commit snapshot, not a generated project ZIP.
- Validation: project-state acceptance and appropriate technical/functional evidence determine suitability. For an installable accepted baseline, fresh-install and operational validation are material. Exact gates for other baseline types remain open within the trial.
- Optional output: a separately assembled and validated COPOT installation/distribution ZIP where required; creating a tag does not create that package. Artifact identity and provenance must not be inferred merely from a tag.
- Historical accepted-baseline checkpoint/tag and package evidence remain unchanged; this Concept does not regenerate, move, or overwrite them.
- Checkpoint creation is a separately authorized action; successful commit, test, NRP, Handoff, or closure alone does not automatically create or validate a checkpoint.

## Object B — Activity Journal / Rollover
- Purpose: maintain an active chronological event log in `docs/devlog.md` for recording and anchoring events during a journal cycle; use Git commit history as the durable historical recall source.
- Existing execution: GPT-side writes via GitHub connector to canonical `main` produce commits and have been verified in this trial. This does not exempt further writes from repository authorization, current SHA checks, or conflict handling.
- Recording: record meaningful events and significant activities using GPT contextual judgment. Meaningfulness is descriptive, not a closed event taxonomy, exact scoring formula, or mandatory state-transition test. The log is descriptive and cannot create authority, acceptance, NRP, promotion, or implementation permission. Format: `YYYY-MM-DD HH:mm:ss WIB     [EVENT_TYPE] Description`. Prefer reliable event-time; otherwise use record-time, without fabricating timestamps or silently backdating.

### Meaningful events and significant activities
Activity Journal records meaningful events and significant activities across all COPOT domains, explicitly including Project, Documentation, and Governance. This covers implementation and validation; documentation drafting, reconciliation and normative promotion; governance discussions, authority decisions and continuity evaluations; and other activities with significant value for reconstructing the project's journey, context, outcomes, learning, or direction. Every domain retains its own authority and lifecycle: a journal record never by itself approves a governance decision, promotes documentation, authorizes execution, or changes accepted project state. The occurrence of a discussion or planning activity is itself an actual event, even if the proposed plan is not executed, no final decision is reached, and no project state changes. Recording must distinguish the activity that actually happened from any proposed, pending, or unapproved future action.

Value may be positive or negative: successes, failures, substantive negative findings, confirmation of an existing condition, eliminated alternatives, lessons learned, material risks, or improvements in understanding may all warrant recording. Significant work can be recorded even when it produces no mutation, no acceptance, or no new conclusion. Routine repetition without material information need not be logged.

GPT assesses whether an event or activity is worth recording in context, without rigid numerical thresholds or an exhaustive rule set. `EVENT_TYPE` is an open-ended descriptive label, not a controlled nine-type enum or a source of significance or authority. Prefer an existing clear label for comparable events, while allowing new labels when needed. Historical labels and the earlier discussion of nine types are examples, not a restriction.

Activity Journal is a concise historical index, not a duplicate of detailed evidence or semantic continuity artifacts. Thread-Level Saved Concepts retain substantial ideas and planning semantics when applicable; their discussion, development, or materialization may be logged as meaningful activity without implying that the Concept has been promoted. Journal recording follows the existing applicable Git/repository authorization requirements; this paragraph grants no standing write permission.
- Save point: at journal rollover, create an independently named annotated Git tag pointing to the exact committed snapshot containing the full previous journal. Verify the tag target and historical ability to retrieve `docs/devlog.md` before changing the working journal.
- Cleanup: after successfully verifying the prior journal tag, replace only the working journal contents with a fresh header, reference to the previous journal tag/commit, and when available the first new event. Make the reset and first write in one ordinary commit when operationally feasible. Never rewrite Git history, force-move/delete a historical tag, or silently discard uncommitted journal content.
- No additional exported file or ZIP is required for journal rollover; historical file versions are recovered from the tagged Git snapshot.
- Candidate timing includes at NRP, after Handoff, or first meaningful record of a new cycle. These are examples and possible evidence/conditions, not mandatory triggers, policy locks, or standing cleanup authorization.
- Rollover itself remains UNTESTED / NOT AUTHORIZED TO EXECUTE under this Concept until separately accepted operational parameters and bounded action authorization exist.

## Operational capability and executor routing (trial observation)
- GPT can evaluate meaningful activity and record authorized journal entries directly to `docs/devlog.md` on canonical `main` through the available GitHub connector. Each successful write creates a normal commit; check the current blob SHA/remote HEAD and verify persistence.
- The currently available GPT-side GitHub connector does not expose annotated Git tag creation. Creating and verifying an annotated tag for either a Checkpoint or Activity Journal rollover therefore routes to Codex using an authenticated Git workspace, only under explicit bounded authorization. This is an observed tool limitation, not permanent architectural ownership: reevaluate routing if capabilities change.
- GPT/user governance evaluates checkpoint eligibility, journal-cycle validity, acceptance and authorization. Codex executes only authorized Git operations and returns evidence; execution ability does not grant decision authority. Rollover reset and its first new event may be executed by Codex within the same bounded operation once tag/recall proof succeeds. No independent journal engine or second authority is introduced.

## Journal format and cycle-range trial decisions
- Retain the existing `YYYY-MM-DD HH:mm:ss WIB     [EVENT_TYPE] Description` record format during this trial. No demonstrated defect or balanced cost/benefit evaluation currently warrants a format change. Event labels remain open-ended. Contextual phase/workstream references can live naturally in the description when they help recall; do not introduce a mandatory phase field, registry, or index now.
- **Candidate only, not accepted rollover policy:** favor fair-volume / historical usability as the primary reason to evaluate rollover, with development phase or milestone meaning as helpful human-recall context. A journal cycle may span multiple logical phases or workstreams; a phase may span multiple cycles. NRP, Handoff, and WU/milestone closure are not mandatory boundaries or automatic triggers.
- No numerical record-count threshold, automatic rotation, phase-level trigger, or standing rollover authorization has been accepted. Use the first meaningful write of an approved new journal cycle as a candidate execution moment when practical, without requiring the rollover to coincide with session bootstrap.

## First rollover operational acceptance trial
- Trial objective: show that the committed complete prior journal can be recalled through its **annotated** rollover tag both immediately before reset and after the fresh journal commit and later subsequent `main` commits.
- Preconditions: verify canonical governance, repository identity, exact remote HEAD, complete committed journal, unique noncolliding tag name, no concurrent/conflicting journal changes, and safe authenticated workspace. Preserve dirty/stale local worktrees; do not reset, stash, clean or overwrite.
- Tag the exact pre-cleanup commit, verify annotated object/type, target SHA, and retrieve the old `docs/devlog.md` by tag. If creation or retrieval fails, stop without cleanup.
- Only after successful tag proof, replace the working journal with a fresh header referencing the previous tag and commit plus the first truthful meaningful new-cycle event when available. Commit and push through the authorized route, check remote HEAD, verify old and new journal retrieval, and prove recall again from a subsequent future `main` commit.
- Where subsequent future commit does not yet exist, report that proof as PENDING, not PASS. Preserve previous journal history and tag immutability; report failures without fabricating success, altering tags, or deleting evidence.
- This trial authorizes no checkpoint creation, application/runtime mutation, WU1 implementation, Rule promotion, or release. Trial outcome and human-recall usability inform later fair-volume policy evaluation; do not infer a permanent policy from one successful rotation.

## Shared Git timeline, independent semantic boundaries
- Both objects use verified commits as immutable historical state references and separate annotated-tag identities. Tags name a repository commit snapshot, not an individual file, auto-generated archive, or new commit.
- Project State checkpoint and Activity Journal rollover differ in purpose, validity evidence, tag namespace, timing, retention, and post-save actions; they need not coincide and must not be treated as interchangeable.
- NRP, Handoff, acceptance, and closure belong to governance/project-flow. Their status/evidence may inform logging save-point validity but they neither constitute the Project Logging domain nor automatically trigger, prove, authorize, or close either lifecycle.
- Release tags, installation packages, checkpoint tags and journal tags are distinct semantic artifacts even when they reference the same commit.

## Operational proof and remaining decisions
- PROVEN: existing Git-based journal appends produce separate history commits; historical accepted-baseline checkpoint and package exist.
- NOT PROVEN: end-to-end annotated journal tag -> verified historical recall -> working journal reset + new record -> subsequent history recall.
- OPEN: checkpoint acceptance/validation classes, exact journal rollover trigger/default, tag naming/uniqueness and permission route, collision/concurrency handling, retention and failure reconciliation.
- Review benefit, operational cost, reproducibility and authority leakage after a bounded journal rollover trial. Promotion to project Rule requires independent evaluation and explicit user acceptance.

## Next planning action
Retain the unified Project Logging Concept and two independent objects. Reconcile Workplan source-status wording; prepare a bounded trial proposal without executing tag or cleanup. Do not amend Rule or claim governance promotion by virtue of Concept reconciliation.
