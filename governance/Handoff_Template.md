# `<Title> (<Milestone / WU / Batch>)`
Date version: 2026-08-30 11:36:13 WIB

Ini adalah GPT/model/session continuity artifact untuk COPOT. Ini bukan Agent
Instruction dan tidak boleh disalin secara utuh ke Work, Codex, atau technical
executor lain.

## BAHASA DAN FRESH-SESSION ISOLATION

Generated Handoff menggunakan primary conversation language user kecuali user
secara eksplisit meminta bahasa lain. Untuk current COPOT usage, generated
Handoff menggunakan Bahasa Indonesia. Technical/project vocabulary dapat tetap
English/literal bila lebih precise.

Receiving GPT/session harus menganggap dirinya memasuki project untuk pertama
kalinya. Jangan mengasumsikan pengetahuan tentang session sebelumnya dan jangan
bergantung pada prior conversation memory, model memory, project memory,
personal context, prior-session summaries, atau implicit history lain.

Jangan mencari atau merekonstruksi extra prior-session context hanya untuk
memperkaya continuity. Additional prior-context retrieval hanya diperbolehkan
ketika user secara eksplisit meminta recall/recovery/comparison, atau ketika
Handoff/current authoritative source secara eksplisit menunjuk source yang
diperlukan. Handoff bersama current authoritative sources adalah intended
bootstrap package.

# USAGE BOUNDARY

Handoff membawa context minimum yang diperlukan new GPT/session untuk
memulihkan:
- objective project state;
- authoritative repository anchors;
- relevant unresolved/deferred/risks;
- current/next target;
- materially relevant Workplan/Concept context;
- unresolved thread-level continuity payload when not yet durably reconciled;
- session-transition readiness.

NRP hanya untuk GPT/session-transition readiness. Repository project state harus
menggunakan objective lifecycle wording.

Aturan delivery:
- sampaikan Handoff melalui editable writing block bila capability tersebut tersedia;
- jangan membungkus Handoff dalam triple-backtick code fence;
- fenced code di dalam Handoff hanya diperbolehkan untuk actual code/config/
  literal technical content yang memang memerlukan code formatting.

## HANDOFF TITLE / FIRST-LINE SEMANTICS

Judul Handoff adalah baris pertama di dalam editable writing-block content.
Jangan menggunakan external title/name field dari writing block untuk membawa
judul continuity Handoff.

Pilih judul berdasarkan destination continuity boundary:

- When moving to a new workstream or milestone, use `[Title] ([Milestone])` or `[Title] - [Milestone]`.
  - Example: `Core Module Refinement (MR.2)`
  - Example: `Core Module Refinement - MR.2`
- When moving to a new Work Unit or Batch, use `[Title] ([WU/Batch])` or `[Title] - [WU/Batch]`.
  - Example: `System Manager (WU2)`
  - Example: `System Manager - Batch 2`

Gunakan current accepted project vocabulary saat memilih `[Title]`; contoh hanya
menunjukkan bentuk heading dan tidak mengalahkan terminology decision yang lebih baru.

Jangan menambahkan generic heading seperti `PROJECT SESSION HANDOFF TEMPLATE`,
`PROJECT SESSION HANDOFF`, atau wrapper title lain ke generated Handoff.
Destination continuity title adalah first-line header Handoff.

Aturan ini hanya mengubah presentation title/header Handoff. Seluruh Handoff structure, continuity semantics, authorization boundaries, NRP rules, bootstrap requirements, dan conditional sections lainnya tetap unchanged.

## Direct Handoff Boundary

Handoff ini menegaskan cross-project routing rule yang ditetapkan dalam canonical `governance/project_rule.md`.

- Jangan melakukan direct transfer Handoff atau invoke Work, Codex, maupun execution destination lain kecuali user secara eksplisit meminta direct transfer tersebut.
- Session baru boleh merekomendasikan Work/Codex atau menyiapkan executor instruction, tetapi recommendation bukan permission untuk invoke.
- Jika execution melalui Work atau Codex diminta, turunkan separate Agent Instruction menggunakan canonical `governance/Agent_Instruction_Template.md`.
- Direct-transfer permission hanya mengatur transport; tidak memperluas technical authorization, scope, validation, stop conditions, atau external side-effect permissions.
- Jangan pernah menggunakan Handoff sebagai executor payload hanya karena direct-transfer capability tersedia.

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

Isi hanya jika Workplan/Concept context secara material memengaruhi continuity atau next-target selection.

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
- Workplan adalah planning/sequencing/provenance artifact, bukan live mirror dari repository current state;
- one Workplan may span multiple Work Units, workstreams, and milestones;
- normal repository advancement during an active workstream does not require immediate Workplan revision;
- default Workplan reconciliation/consolidation occurs at workstream closure / pre-handoff;
- earlier Workplan update is warranted only when planning knowledge materially changes, durable planning context is needed before closure, or the user explicitly requests it;
- repository contracts/documentation remain authoritative for delivered/current lifecycle truth after promotion;
- jangan menyalin full Workplan;
- jangan menyalin full Concept bodies;
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

Rule: `NOT REQUESTED` berarti jangan melakukan invoke/transfer langsung ke Work/Codex/another destination. Siapkan Agent Instruction di chat bila execution guidance diperlukan.

# STATUS SEBELUMNYA

Unit kerja terakhir: `<...>`

Implementation/result: `<COMPLETE / PARTIAL / NOT STARTED / BLOCKED / other>`

Latest accepted result: `<objective summary>`

Known unresolved issues: `<detail / None>`

Deferred items materially relevant: `<ID + title + source + status/target + impact / None>`

Known risks: `<detail / None>`

## Conditional Thread-Level Reconciliation State

Gunakan ketika saved session/thread concepts atau governance-relevant decisions masih material.

Payload scope: `<workstream/planning concern>`

Durably reconciled items: `<summary / None>`

Still-unreconciled items: `<summary / None>`

Required disposition before closure: `<Concept update / new Concept / Workplan / Deferred / supersede / reject / N/A>`

Rule: session transition tidak berarti payload ini cleared. Clear hanya setelah durable reconciliation atau explicit disposition.

## Conditional Deferred Item Review

Gunakan hanya jika materially relevant terhadap next target.

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

Rule: Handoff tidak pernah dengan sendirinya mengotorisasi next technical action.

Rule: recommended execution routing tidak pernah dengan sendirinya mengotorisasi direct transfer.

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

Isi ketika current unit/workstream sedang closing dan Git branches material.

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

Isi hanya untuk completed/closing work.

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

Isi ketika current readiness mencapai atau mendekati NRP CANDIDATE.

Audit status: `<NOT STARTED / IN PROGRESS / PASS / MATERIAL FINDINGS / BLOCKED / NOT REQUIRED>`

Audited authority surfaces: `<AGENTS / README / roadmap / contract / Deferred registry / other>`

Material stale/inconsistent findings: `<None / summary>`

Corrections durable: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Final remote verification after correction: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Rule: material current-state inconsistency dalam authoritative repository documentation harus dikoreksi dan dibuat durable sebelum NRP CONFIRMED.

GPT-side Workplan synchronization diatur oleh planning reconciliation section di bawah, bukan oleh repository documentation consistency.

## Conditional Planning Reconciliation / Adequacy Audit

Gunakan ketika Workplan/Concept context penting bagi workstream closure, next-target selection, atau next-session continuity.

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

Interpretasi:
- NRP NOT READY: state not sufficiently durable/recoverable;
- NRP CANDIDATE: technical/project state mature but closure/bootstrap evidence not complete;
- NRP CONFIRMED: final durable repo/documentation/planning bootstrap state is sufficient for session transition;
- NRP UNVERIFIABLE: required governance/Handoff/evidence cannot be verified.

NRP evaluation mempertimbangkan:
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

NRP tidak mengubah repository project/work-unit/milestone state.

## Final Repository Stability Gate

Sebelum `NRP CONFIRMED`, catat:
- intended project mutations complete;
- documentation corrections complete;
- objective closure docs complete where required;
- branch lifecycle/merge/post-merge state complete where required;
- final authoritative remote SHA verified;
- materially relevant planning/Concept continuity is either closure-reconciled or explicitly carried forward without misleading next-target selection;
- no planned repo mutation remains for this closing state.

Jika repository berubah setelah assumed final anchor, refresh closure evidence dan tetap/kembali ke NRP CANDIDATE sampai new final state diverifikasi.

## Continue or New ChatGPT Session

Setelah `NRP CONFIRMED`, GPT/user memutuskan lanjut atau pindah sesi berdasarkan panjang sesi, relevansi history, kompleksitas next target, kebutuhan source baru, reasoning aktif, dan bootstrap cost.

Keputusan ini tidak diteruskan sebagai concern technical executor.

# CROSS-MILESTONE / CROSS-TRACK DEPENDENCY GOVERNANCE

Gunakan hanya ketika predecessor belum fully accepted atau provisional work bergantung pada track lain.

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

Receiving GPT/session harus menganggap dirinya memasuki project untuk pertama
kalinya dan tidak memiliki pengetahuan tentang session sebelumnya di luar
Handoff serta current authoritative sources. Secara berurutan:

1. resolve, read, dan apply current `governance/project_rule.md`;
2. resolve, read, dan apply current `governance/Handoff_Template.md`;
3. resolve, read, dan apply current `governance/Agent_Instruction_Template.md`;
4. baca supplied Handoff;
5. baca project instruction;
6. independently verify current authoritative remote repository state;
7. baca hanya relevant repository, Workplan, dan Concept sources yang material
   serta diperlukan oleh Handoff/target;
8. rekonsiliasi material drift antara Handoff anchor dan current authoritative
   state;
9. baru berikan substantive COPOT feedback atau buat Agent Instruction baru.

Jangan menggunakan prior conversation memory, model memory, project memory,
personal context, prior-session summaries, atau implicit history lain untuk
melengkapi continuity. Jangan mencari extra prior-session context kecuali user
secara eksplisit meminta recall/recovery/comparison, atau Handoff/current
authoritative source menunjuk source tertentu yang diperlukan.

Jangan melakukan silent fallback ke older Git revision, obsolete timestamped
repository file, historical File Library copy, memory, chat history, atau
summary ketika canonical governance tidak dapat dibaca/diverifikasi.

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

Ketika user memilih session baru:
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

Handoff selesai ketika GPT/session berikutnya dapat memulihkan objective continuity tanpa merekonstruksi obsolete history, tanpa memerlukan continuous Workplan mirroring, dan tanpa memperlakukan Handoff sebagai technical execution authorization atau direct-transfer authorization.
