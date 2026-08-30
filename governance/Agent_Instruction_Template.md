# GENERAL AI AGENT INSTRUCTION TEMPLATE
Date version: 2026-08-30 11:59:56 WIB

## TEMPLATE USAGE

Template modular untuk Codex/technical AI Agent.

Tujuan: instruksi terkecil yang masih memberi first-pass success reliable.

Agent Instruction = minimum execution delta, bukan Handoff yang diformat ulang.

Tambahkan field hanya jika omission dapat menyebabkan target/branch/anchor
salah, unauthorized action, dependency terlewat, validation tidak valid,
unsafe side effect, atau stop condition terlewat.

Jangan menyalin secara otomatis:
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
GPT conversation dan penjelasan tetap menggunakan primary conversation language
user; untuk current COPOT conversation, Bahasa Indonesia.

Jangan menerjemahkan identifier, path, branch, command, API name, error
message, filename, status token, atau official technical term bila terjemahan
mengurangi presisi. Technical/project vocabulary boleh tetap literal.

## INSTRUCTION PRESENTATION AND HEADING SEMANTICS

- Sampaikan Agent Instruction melalui editable writing block bila capability
  tersebut tersedia.
- Jangan membungkus Agent Instruction dalam triple-backtick code fence. Fenced
  code hanya untuk actual code/config/literal technical content yang memang
  memerlukan code formatting.
- Instruction short description/title MUST menjadi baris pertama di dalam
  writing-block content.
- Jangan menggunakan external title/name field untuk membawa short description.
- Aturan placement ini hanya mengubah presentation header-description; seluruh
  structure dan semantics lain tetap diatur oleh template ini.

Heading semantics:
- For the first Agent Instruction after entering a new technical-agent session, the first-line short description is a destination/context heading, at the scope of the technical work context being entered, for example `Core Module Refinement (MR.2)` or `System Manager (WU2)`.
- For subsequent Agent Instructions in the same technical-agent thread, the first-line short description remains required but becomes an instruction-purpose heading, scoped only to that individual instruction, for example `Webcore Navigation & Redirects Extraction`, `Human Acceptance Preview Preparation`, or `WU5 Closure Reconciliation`.
- Do not add or repeat a destination/WU heading merely because the target WU changes inside an already-established technical-agent thread.

# DELIVERY MODE

## Local Authenticated Writable Workspace — Default

Gunakan ketika local environment tersedia, authenticated, writable, dan memadai.

Remote repository/branch tetap menjadi durable authority.

Untuk state baru/resumed/transitioned/untrusted, verifikasi repo/branch/anchor/
workspace/synchronization yang relevan sebelum write.

Untuk same-thread accepted continuation, gunakan Check-and-Run dan jangan mengulang continuity verification yang setara.

Jangan melakukan silent normalization atas drift dengan reset/stash/clean/
discard/force operations.

Durable work memerlukan authorized commit, push, dan independent remote verification.

## Remote / Cloud Execution

Gunakan ketika dipilih atau secara material lebih sesuai.

Sandbox bukan durable authority. Rule full-vs-Check-and-Run yang sama tetap berlaku.

## Local Runtime Validation

Runtime workspace bukan Git authority. Source fixes harus dilakukan di verified writable Git workspace.

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

Di luar scope: `<...>`

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

Jangan rerun: `<accepted evidence only when repetition must be prevented>`

## STOP CONDITIONS

Stop jika:
- remote anchor mismatch;
- unexpected dirty/material drift;
- workspace identity cannot be verified;
- scope expansion required;
- required capability unavailable;
- irreversible action unauthorized;
- release/tag/publication would be required but is not explicitly authorized;
- durable delivery unavailable when required.

## REPORT

Laporan compact default:
1. Verification
2. Changes / Findings
3. Validation
4. Blockers / Risks
5. Final Git / Environment State

Same-thread continuation harus melaporkan material deltas/exceptions, bukan mengonfirmasi ulang accepted state yang tidak berubah.

# PROJECT SOURCE READING

Gunakan progressive reading:
1. project instruction;
2. target source;
3. direct dependencies;
4. related tests;
5. relevant docs/contracts;
6. expand only if evidence insufficient.

Jangan meminta agent membaca GPT governance atau ChatGPT session history.

# WORKPLAN AND CONCEPT INPUT

Workplan dan Concept artifacts adalah planning inputs, bukan implicit authorization.

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

Ketika Concept direferensikan, utamakan canonical Concept title/logical identity dan sertakan exact filename/heading bila task bergantung pada source location tersebut.

# FOCUSED DOCUMENTATION CONSISTENCY AUDIT

Gunakan hanya ketika GPT secara eksplisit mengotorisasi repository documentation audit/correction task.

Operational target wording harus objective, misalnya:

`Audit materially relevant current repository documentation against accepted committed repository state. Identify and correct stale, contradictory, or misleading current-state wording.`

Jangan mengirim internal `NRP CANDIDATE` terminology kepada agent.

Pertahankan intentional historical records. Jangan melakukan blind search-and-replace. Non-material style differences bukan blockers.

Jika corrections diotorisasi, commit/push/remote-verify hanya intended material fixes.

# DEFERRED ITEM EXECUTION

Deferred Item bukan authorization.

`Candidate`, `Unscheduled`, `future`, `KEEP DEFERRED`, `NOT APPLICABLE`, `REJECT`, and `SUPERSEDE` do not authorize implementation.

Hanya explicit instruction/adopted planned scope yang dapat mengeksekusi Deferred Item.

Pertahankan stable Deferred Item ID bila tersedia.

# ACCEPTANCE EVIDENCE

Agent hanya melaporkan technical evidence:
- validation result;
- AI acceptance evidence;
- possible human-required criterion;
- technical merge eligibility;
- blocker/risk;
- final Git/environment state.

Jangan meminta agent memutuskan:
- NRP;
- ChatGPT session transition;
- project governance closure;
- authorization for next milestone/workstream;
- automatic Workplan/Concept/Deferred adoption.

# DEPENDENCY

Sertakan hanya jika materially changes execution.

`Dependency: <INDEPENDENT / SOFT / HARD / CLOSURE / BLOCKING>`

`Operational consequence: <continue / hold / stack / merge blocked>`

Utamakan concise accepted predecessor wording dan jangan membuka kembali tanpa concrete regression signal.

# BRANCH LIFECYCLE

Sertakan hanya ketika branch action/audit relevan terhadap authorized task.

## Branch Closure Audit

Untuk workstream closure instruction, jika branch lifecycle termasuk scope, wajibkan evidence untuk:
- workstream commit containment in the accepted integration target;
- remote branch inventory;
- ancestry/containment of obsolete candidates;
- zero-ahead status for obsolete candidates;
- deletion only after both conditions pass and deletion is authorized;
- prune where applicable;
- post-cleanup inventory recheck;
- final clean/synchronized workspace where material;
- explicit `main-only` / `no-op` record when no cleanup is required.

Jangan menghapus berdasarkan branch age/name atau assumption.

Jika deletion tidak diotorisasi, audit dan laporkan eligibility saja.

Contoh:

`Fast-forward main only. Do not delete branches.`

or

`Delete remote feature branch only after containment and zero-ahead verification.`

Jangan menempelkan full governance doctrine ketika concise operation-specific instruction sudah memadai.

# EXECUTION GATES

Gunakan gate sebanyak yang diperlukan task saja.

Untuk same-thread continuation, jangan menambahkan redundant continuity gate hanya untuk mengonfirmasi ulang accepted state.

Jangan melewati failed gate.

# REMOTE-AUTHORITATIVE GIT RULES

Untuk write task yang initial/resumed/transitioned/untrusted, verifikasi repository identity, remote, branch, anchor, workspace identity/state, included changes, scope, dan authorization yang relevan.

Same-thread Check-and-Run mewarisi accepted state dan memeriksa material drift selama eksekusi normal.

Jangan:
- commit on unexpected branch;
- push unrelated changes;
- auto merge/rebase;
- create/delete branch without authorization;
- tag/release/publish without authorization;
- treat unpushed commit as authoritative;
- edit source in unverified runtime copy.

Setelah push, independently verify final remote branch/commit bila memungkinkan.

# RELEASE / TAG / PUBLICATION

Release advancement terpisah dari feature/workstream completion.

Jangan menyimpulkan release need atau release authorization dari completed feature, merged branch, closed workstream, accepted source delta, atau Handoff next target.

Tag/release/publication harus diotorisasi dan di-scope secara eksplisit.

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

Technical executors tidak memiliki thread-level saved-concept lifecycle.

Jika satu saved Concept secara material memengaruhi execution, GPT harus menerjemahkan hanya required execution delta ke dalam instruction.

Jangan meminta Codex merekonstruksi session memory, merekonsiliasi seluruh thread backlog, memutuskan Concept ownership, atau memutuskan apakah saved concept menjadi Workplan/Deferred/repository scope kecuali explicit task memang repository-side documentation/planning implementation dan scope tersebut diotorisasi.

# CONTINUATION TOKEN RULE

Untuk same technical-agent thread, bawa hanya changed target, anchor, authorization, scope, validation, dan stop condition.

Use:

`Continue the current task.`

`Changed target: [...]`

`Changed authorization: [...]`

`New stop condition: [...]`

`All other prior boundaries remain unchanged.`

Jangan mengulang governance narrative atau environment/history yang tidak berubah.
