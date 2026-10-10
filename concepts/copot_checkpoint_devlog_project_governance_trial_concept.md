# Checkpoint & Devlog — Project Governance Trial
Date version: 2026-10-10 16:19:51 WIB

Status: ACTIVE TRIAL / PROJECT GOVERNANCE CANDIDATE / NOT PROMOTED
Project: COPOT
Provenance: User/GPT governance discussion and trial, 2026-10-10; conversation TLC-12.

## Authority boundary
This is a *project governance* candidate, not a mere technical implementation workflow. It does not amend `governance/copot_project_rule.md`, replace NRP ownership, or become active governance merely because it is indexed in Workplan.

## Checkpoint trial
- Use durable, immutable historical accepted Git checkpoints/tags to identify reconstructible project state; checkpoint is distinct from versioned release and from a disposable runtime.
- Checkpoint candidate should undergo direct fresh-install and operational acceptance validation *before marking closure*, not merely source/test inspection. Exact placement in the NRP/closure sequence (e.g. after NRP Candidate versus before pre-NRP documentation reconciliation) remains unresolved for trial.
- Preserve checkpoint identity and historical source; later corrections apply to successor state. An annotated accepted-baseline checkpoint exists as historical evidence; this Concept does not recreate, rewrite or tag it.
- Capture relationship between accepted changeset, checkpoint, validation evidence, documentation reconciliation, NRP decisions, provenance and session continuity without transferring NRP authority to Codex.

## Devlog trial
- `docs/devlog.md` exists on `main` and records chronological evidence/index entries for meaningful events in project/session context. Its existence and entries have been verified; this is an active trial, not promoted project governance.
- Record only **after the event has occurred**. Devlog is descriptive, never prescriptive: an entry documents an event or existing decision but cannot create authorization, acceptance, promotion, NRP, or implementation authority.
- Entry format: `YYYY-MM-DD HH:mm:ss WIB     [EVENT_TYPE] Deskripsi kejadian`. Preserve factual sequence and clearly mark retrospective entries. Prefer **event-time** when reliable event timing can be reconstructed from durable evidence; otherwise use **record-time**, without inventing past timestamps or presenting record-time as known event-time.
- GPT-side direct append to `docs/devlog.md` on canonical `main` via GitHub connector is the user-selected **trial route**, without requiring Codex simply to record events. This is a specific user direction, **not yet a standing governance exemption** from Repository write authorization, branch/commit safety, conflict handling, or independent promotion gates.
- Append should happen promptly after relevant events when practical; omission during the trial is a discipline gap to assess, not retroactive proof that the event did not occur. Do not fabricate missing events to fill a timeline.
- An append to `main` creates a Git commit; check the current remote state and target content, preserve unrelated entries, avoid overwriting concurrent changes, and report resulting durability honestly. Do not silently route through Codex or alter project state beyond the explicitly requested log update.
- Devlog is **not** a bootstrap source for fresh-session GPT, decision authority, replacement for Git history, Workplan, Handoff, contract, acceptance evidence, or project Rule. It is a chronological record/index to support traceability.
- Checkpoint rollover, retention, and possible post-checkpoint clearance are **unresolved and untested**. Do not truncate, archive, or reset the devlog automatically until trial evidence and user acceptance establish the rule.
- Promotion into canonical project governance requires separate review and user acceptance. This Concept does not amend `governance/copot_project_rule.md`.

## Pending promotion review
Assess benefit, authority leakage, interaction cost, reproducibility, NRP/closure relationship, failure handling, checkpoint tagging rules and devlog discipline. Promotion requires user acceptance and a separately governed amendment/evaluation of canonical project governance. No governance file is changed here.
