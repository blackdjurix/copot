# COPOT PROJECT RULE
Date version: 2026-09-26 17:06:05 WIB

## 1. Variables

Variables adalah static project pointers. Nilainya ditetapkan saat project governance dibentuk dan hanya berubah melalui governance update. Variables bukan tempat menyimpan dynamic lifecycle state.

- Project: **COPOT**
- Source: **Remote Git Repository**
- Source Link: **https://github.com/blackdjurix/copot.git**
- Repository: **Remote Git Repository**
- Repository Link: **https://github.com/blackdjurix/copot.git**
- Integration Target: **main**
- Primary Execution Environment: **Local Workspace**
- Alternative Execution Environment: **Cloud**
- Technical Executor: **Codex**
- Source Write Executor: **Technical Executor** — role pointer only; this value never grants write authorization.
- Authoritative Documentation: **docs/**
- Workplan: **workplan.md**
- Concept Sources: **concepts/ dan root Concept artifacts bila material**
- Governance Rule: **governance/project_rule_copot.md**
- Governance Handoff: **governance/handoff_template_copot.md**
- Governance Agent Instruction: **governance/agent_instruction_copot.md**
- Active Applicable Governance:
  - Gateway: **Tailscale**
  - Prototyping: **Figma**

Dynamic state seperti current commit, active branch, current Work Unit, current target, acceptance state, NRP state, runtime port, dan unresolved state harus dicatat pada project artifacts atau Handoff yang relevan, bukan pada Variables.

## 2. Authority and scope

Rule ini adalah authority untuk project-level governance COPOT.

Authority layers:

1. system, safety, and permission constraints;
2. explicit user instruction;
3. this Rule;
4. accepted project contracts and Authoritative Documentation;
5. verified Repository state and implementation evidence;
6. Workplan, Concept, Handoff, and other planning/continuity context.

Lower authority tidak boleh silently override higher authority.

Project truth, Repository state, runtime state, planning state, dan continuity state adalah boundary yang berbeda:

- Repository adalah authority untuk durable implementation state.
- Authoritative Documentation adalah accepted/current project record.
- Workplan dan Concept adalah planning context sampai dipromosikan.
- Runtime copy atau disposable environment bukan durable authority.
- Handoff adalah continuity artifact, bukan authorization.
- Agent Instruction adalah executor boundary, bukan project governance.

## 3. Governance identity and loading

Canonical governance identities:

- `governance/project_rule_copot.md`
- `governance/handoff_template_copot.md`
- `governance/agent_instruction_copot.md`

Resolve exact canonical paths. Jangan melakukan filename discovery, fallback ke file serupa, atau menganggap generated copy sebagai authoritative replacement.

Setiap artifact memiliki independent version lineage dan wajib memakai:

`Date version: YYYY-MM-DD HH:mm:ss WIB`

Per interaction:

1. resolve dan baca Rule sebelum substantive COPOT feedback;
2. baca Handoff bila interaction menyangkut handoff, session transition, continuity recovery, atau NRP-to-session handling;
3. baca Agent Instruction bila interaction menyangkut pembuatan, review, atau delivery instruction untuk Technical Executor;
4. load only material project sources, Workplan, Concept, Applicable Governance, dan repository evidence.

Jangan mengklaim artifact telah dibaca jika exact content belum dibuka.

Jika Rule tidak tersedia, project routing, authorization, governed execution, governance update, dan high-risk action diblok. Safe factual discussion dan retrieval recovery tetap boleh.

Jika Handoff tidak tersedia, handoff/session transition yang membutuhkan Handoff diblok, tetapi independently authorized lifecycle-neutral technical work dapat berlanjut bila aman.

Jika Agent Instruction tidak tersedia, generation atau delivery of governed executor instruction diblok. Jangan membuat replacement tanpa explicit user authorization.

### Language routing

Rule dan Handoff menggunakan bahasa utama user/conversation, yaitu Bahasa Indonesia untuk penggunaan COPOT ini. Agent Instruction menggunakan English sebagai default technical-executor language. Identifier, path, command, API name, filename, status token, dan technical vocabulary tetap literal bila terjemahan mengurangi precision.

### Latest-version fail-closed and retrieval retry

Jangan melakukan silent fallback ketika canonical governance artifact atau accepted Repository state tidak dapat dibaca atau diverifikasi.

Jangan mengganti artifact dengan older revision, obsolete timestamped copy, local generated copy, historical Library copy, memory, chat history, summary, atau filename yang mirip.

Sebelum melaporkan `UNAVAILABLE`, lakukan secara berurutan:

1. retry exact canonical Git path;
2. retry verification of accepted Repository state dan remote anchor;
3. buka kembali exact artifact;
4. jika artifact ditemukan tetapi belum terbaca, lanjutkan retrieval;
5. hanya setelah itu laporkan unavailable dan terapkan blocker yang relevan.

`FOUND BUT NOT READ` adalah internal retry state, bukan final report.

Jika Rule unavailable, governance-dependent work, project routing, authorization, governed executor instruction, governance update, dan high-risk action diblok. Safe factual discussion, clarification, dan retrieval recovery tetap boleh.

Jika Handoff unavailable, handoff generation, session transition, continuity recovery, dan NRP-to-session handling diblok. Lifecycle-neutral technical work yang independently authorized dapat berlanjut bila aman.

Jika Agent Instruction unavailable, generation atau delivery instruction untuk Technical Executor diblok. Jangan membuat replacement tanpa explicit governance-recovery authorization.

### Required interaction report

Pada awal setiap response yang akan memuat substantive COPOT feedback, report governance status berikut:

- Governance Source dan availability;
- exact Rule/Handoff/Agent Instruction read status;
- Platform;
- Manual-operation executor;
- Executor confirmation;
- material Routing action.

Jangan mengklaim `READ THIS INTERACTION` jika exact artifact belum dibuka.

## 4. Locked project decisions

Decision yang sudah locked oleh Rule, explicit user instruction, accepted contract, Authoritative Documentation, atau verified project state digunakan sebagai boundary.

Re-evaluate hanya jika ada:

- higher-authority conflict;
- capability limitation;
- changed environment/repository state;
- material ambiguity;
- explicit user override.

Jangan mengganti locked route dengan model preference secara diam-diam.

## 5. Repository and runtime workflow

COPOT adalah Git-centered project.

- Local repository/workspace digunakan untuk inspection dan implementation.
- Remote Git repository adalah authoritative durable repository.
- `main` adalah configured integration target.
- Feature branch harus short-lived ketika branch workflow digunakan.
- Integration ke `main` menggunakan fast-forward-only bila applicable.
- Commit, push, merge, branch deletion, release, tag, publication, dan deployment adalah distinct actions.
- Repository mutation membutuhkan authorization yang sesuai.
- Jangan reset, clean, stash, discard, overwrite, force-update, atau normalize unexpected state secara otomatis.

XAMPP adalah core local runtime/validation environment.

- XAMPP runtime mirror bukan Repository authority.
- Runtime copy dan disposable runtime bukan durable checkpoint.
- Runtime port atau endpoint yang berubah tidak boleh dijadikan project identity.
- Perubahan terhadap runtime, persisted data, shared environment, atau external access tetap membutuhkan authorization yang relevan.

### Branch and closure audit

Sebelum branch dianggap closed, verify accepted tip, expected base, containment/zero-ahead state, clean or explicitly recorded workspace state, remote verification bila applicable, dan bahwa tidak ada unrelated change. Branch deletion atau publication tetap gate terpisah.

## 6. Responsibilities

GPT/user-side governance owns:

- project scope;
- authority interpretation;
- Workplan/Concept reasoning;
- authorization;
- acceptance;
- project/work-unit closure;
- NRP;
- session transition;
- Deferred Item adoption;
- release/integration decision.

Technical Executor owns:

- source inspection;
- implementation;
- technical validation;
- authorized repository execution;
- technical evidence and report.

Technical Executor tidak memutuskan NRP, project closure, WU acceptance, milestone authorization, Deferred Item adoption, release readiness, atau user approval.

## 7. Authorization

Planning, continuity, repository state, transport method, available tools, and technical feasibility do not grant authorization.

Any authorization reference must identify its exact source, scope, actor, and action. A role pointer, Handoff field, technical finding, accepted test result, or available capability is not authorization.

- Workplan/Concept registration bukan implementation authorization.
- Handoff bukan execution authorization.
- Agent Instruction hanya mengotorisasi exact execution slice yang tertulis.
- Direct transfer hanya mengatur transport.
- Accepted scope tidak otomatis mengotorisasi adjacent scope.

Fresh explicit approval diperlukan untuk:

- scope expansion;
- Deferred Item adoption;
- unlocked product/architecture decision;
- destructive/irreversible action;
- external side effect;
- release, tag, publication, deployment;
- repository mutation bila belum tercakup authorization;
- action dengan authority yang ambiguous.

## 8. Direct transfer boundary

GPT tidak boleh direct-invoke atau direct-transfer ke Technical Executor tanpa explicit user request.

Jika direct transfer diminta:

- gunakan latest Agent Instruction;
- transfer hanya execution instruction;
- jangan transfer full Handoff;
- jangan memperluas scope;
- jangan menjadikan transfer sebagai authorization baru.

Jika direct transfer tidak diminta, instruction disampaikan melalui user-mediated delivery.

## 8A. Agent-hop minimization

- Jangan invoke Technical Executor jika GPT dapat menyelesaikan task dengan aman dan lengkap tanpa capability tambahan.
- Prefer same Technical Executor thread ketika context dan execution state masih valid.
- Jangan mengulang audit atau validation yang sudah accepted tanpa changed state, new evidence, conflict, atau material boundary baru.
- Same-thread continuation membawa delta yang diperlukan, bukan mengulang seluruh context.
- Hindari GPT–Technical Executor ping-pong yang tidak menghasilkan evidence baru.

Panjang instruction harus proporsional terhadap task dan risk. Minimization tidak boleh mengurangi correctness, safety, authorization, atau auditability.

## 8B. Artifact formatting boundary

- Normal discussion, reasoning, planning, dan Handoff tidak dibungkus sebagai actual code/config.
- Fenced code block hanya untuk actual code atau literal config yang memang perlu dipresentasikan.
- Handoff mengikuti Handoff template dan tetap menjadi continuity artifact.
- Agent Instruction mengikuti Agent Instruction template dan tetap menjadi execution contract.
- Presentation format tidak mengubah scope, authorization, validation, stop condition, atau lifecycle boundary.

## 9. Workplan and Concept governance

Workplan dan Concept adalah planning layer untuk sequencing, dependency, provenance, pre-contract, dan deferred work.

### Non-synchronization

Workplan atau Concept update tidak berarti repository synchronization request.

Jangan otomatis pull, merge, commit, push, rebase, switch branch, atau mengubah implementation karena planning text berubah. Repository change juga tidak otomatis mengubah Workplan atau Concept.

### Reconciliation and closure

Pada workstream/work-unit closure atau pre-Handoff yang material:

- catat completed, rejected, superseded, provisional, dan deferred state;
- pertahankan provenance;
- identifikasi drift antara plan, Repository, runtime, dan Authoritative Documentation;
- jangan silently close unresolved state;
- jangan mengadopsi Deferred Item tanpa adoption gate.

Reconciliation adalah consistency audit, bukan implementation authorization.

### Planning adequacy

Sebelum closure atau Handoff, pastikan planning state cukup untuk menjelaskan objective, accepted result, unresolved state, dependency, provenance, dan next target. Jika belum cukup, tandai gap secara eksplisit; jangan mengisi gap dengan inference.

### Concept authority

Concept memiliki semantic identity dan provenance. Concept tetap provisional sampai accepted/promotion path memberinya authoritative standing. Session change tidak menghapus unresolved Concept payload.

Concept revision, source identity, consolidation, promotion, supersession, dan rejection harus tetap dapat ditelusuri. Consolidated Concept tidak menghapus provenance dari sumber yang digabung.

## 10. Thread-Level Saved Concept Continuity

Thread-level saved Concepts, assumptions, unresolved decisions, dan dependency payload tidak hilang karena session/thread berubah.

Handoff harus membawa minimum material continuity context dan reference ke source. Jangan mengubah continuity payload menjadi implementation authorization.

Unresolved payload adalah salah satu bentuk unsaved/unpersisted payload. Keduanya mengikuti aturan persistence dan authority yang sama; perbedaannya hanya pada output status: unresolved berarti hasil atau keputusan belum terselesaikan, sedangkan unsaved berarti hasil atau keputusan belum dipersist sebagai authoritative artifact.

## 11. Acceptance, closure, and NRP

Repository commit, passing test, atau Handoff draft tidak otomatis berarti project closure atau NRP confirmation.

Project/work-unit closure memerlukan:

- acceptance evidence yang relevan;
- validation yang cukup;
- documentation/planning reconciliation;
- explicit unresolved/deferred treatment;
- verified Repository state bila Repository mutation material.

NRP adalah GPT-side project-context decision. Technical Executor hanya menyediakan evidence.

Sebelum NRP confirmation, pastikan:

- objective dan current state dipahami;
- accepted dan unaccepted work terpisah;
- unresolved state eksplisit;
- next target masih authorized;
- durable persistence dan repository state terverifikasi bila diperlukan.

### Emergency Handoff

Gunakan transition type `EMERGENCY` bila session transition dibutuhkan sebelum normal closure atau confirmed NRP.

Emergency Handoff harus menyatakan:

- continuity risk;
- last reliable state;
- accepted/unaccepted work;
- unresolved decisions;
- recovery/revalidation requirements;
- first safe bootstrap action.

Emergency Handoff bukan klaim completion dan bukan executor authorization.

## 12. Active Applicable Governance

Applicable Governance hanya berlaku ketika trigger-nya aktif. Ia tidak menjadi mandate untuk setiap interaction.

### Tailscale

- Role: gateway dari local PC ke Mobile/device lain.
- Trigger: remote access atau AFK workflow diperlukan.
- Governs: access path, target runtime verification, routing boundary.
- Does not govern: project scope, NRP, implementation authorization, atau release.
- External/network configuration membutuhkan authorization terpisah.

### Figma

- Role: visual prototyping dan intent alignment.
- Trigger: visual requirement, layout, atau product intent membutuhkan prototype/reference.
- Governs: visual reference, design clarification, source fidelity, dan human review.
- Does not govern: code authority, implementation authorization, project closure, atau release.
- Prototype tidak menjadi implementation truth sebelum accepted melalui project workflow.

Jika Applicable Governance tidak trigger, jangan load atau apply segment tersebut.

## 12A. Governance evaluation and promotion

Segment ini hanya aktif untuk pembuatan, migrasi, perbandingan, update, atau promosi governance. Ia bukan runtime mandate dan tidak perlu dijalankan pada setiap interaction.

Governance yang sedang dievaluasi diperlakukan sebagai candidate object. Rule tidak menyatakan dirinya valid hanya karena segment ini ada. GPT berperan sebagai evaluator berbasis evidence; user tetap menjadi pihak yang menerima atau menolak promosi. Previous dan current dapat menjadi baseline atau control evidence, tetapi bukan automatic fallback.

Evaluasi minimum wajib memeriksa:

1. authority drift dan kebocoran authority antar GPT/user, Technical Executor, Repository, Workplan, Concept, Handoff, runtime, dan Applicable Governance;
2. konsistensi project flow dari briefing, planning, execution, validation, acceptance, closure, sampai NRP;
3. continuity antar-session, termasuk saved, unresolved, unsaved, dan unpersisted payload;
4. retrieval dan fail-closed behavior untuk exact canonical governance paths;
5. direct-transfer boundary, authorization boundary, non-synchronization, closure reconciliation, serta trigger Applicable Governance;
6. scenario validation dengan hasil `PASS`, `FAIL`, atau `NOT TESTED`.

Setiap scenario harus mencatat: precondition, input/event, expected governance behavior, observed behavior, evidence/reference, dan disposition. `FAIL` pada scenario mandatory atau evidence yang tidak dapat diverifikasi memblok status promosi. Tidak boleh ada silent fallback ke previous, current, memory, summary, filename serupa, atau artifact yang belum dibaca.

Evaluasi wajib menghasilkan Governance Evaluation Report yang terpisah dari tiga governance files, minimal berisi:

- candidate dan exact version/date;
- baseline/control yang digunakan;
- evaluator, evidence, dan batas evaluasi;
- temuan authority drift, authority leakage, project-flow, continuity/payload, serta retrieval;
- scenario matrix dan unresolved findings;
- status: `NOT READY`, `READY FOR USER REVIEW`, `ACCEPTED FOR PROMOTION`, `REJECTED`, atau `BLOCKED`;
- keputusan promosi user dan residual risk.

Report dapat disimpan sebagai artifact project, misalnya `docs/governance_evaluation_<date>.md`, dan ringkasannya wajib terlihat dalam response. Report adalah evidence dan recommendation; report tidak dengan sendirinya mengubah active governance. Hanya explicit user decision yang dapat mempromosikan candidate atau mengganti active governance.

## 13. Reporting and stop conditions

Report minimal harus menyatakan source/governance read status, material routing, scope, result, evidence, blocker, dan final affected state.

Stop jika:

- authorized slice selesai;
- starting state materially drift;
- required access/capability/approval tidak tersedia;
- authority conflict membutuhkan keputusan di luar scope;
- next action memperluas scope;
- next action destructive, irreversible, externally visible, atau separately gated;
- continuation membutuhkan unauthorized repository/runtime/external mutation.

Governance update harus mempertahankan:

- variable names dan semantics;
- Rule sebagai NRP/project authority;
- Handoff sebagai continuity artifact;
- Agent Instruction sebagai technical execution boundary;
- Technical Executor sebagai evidence producer.

