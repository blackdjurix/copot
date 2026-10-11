# Project Logging — Project State Checkpoint & Activity Journal Rollover
Date version: 2026-10-11 17:25:35 WIB

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
- Recording: append only after meaningful events occurred. The log is descriptive and cannot create authority, acceptance, NRP, promotion, or implementation permission. Format: `YYYY-MM-DD HH:mm:ss WIB     [EVENT_TYPE] Description`. Prefer reliable event-time; otherwise use record-time, without fabricating timestamps or silently backdating.
- Save point: at journal rollover, create an independently named annotated Git tag pointing to the exact committed snapshot containing the full previous journal. Verify the tag target and historical ability to retrieve `docs/devlog.md` before changing the working journal.
- Cleanup: after successfully verifying the prior journal tag, replace only the working journal contents with a fresh header, reference to the previous journal tag/commit, and when available the first new event. Make the reset and first write in one ordinary commit when operationally feasible. Never rewrite Git history, force-move/delete a historical tag, or silently discard uncommitted journal content.
- No additional exported file or ZIP is required for journal rollover; historical file versions are recovered from the tagged Git snapshot.
- Candidate timing includes at NRP, after Handoff, or first meaningful record of a new cycle. These are examples and possible evidence/conditions, not mandatory triggers, policy locks, or standing cleanup authorization.
- Rollover itself remains UNTESTED / NOT AUTHORIZED TO EXECUTE under this Concept until separately accepted operational parameters and bounded action authorization exist.

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
