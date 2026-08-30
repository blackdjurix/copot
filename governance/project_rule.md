# TEMPLATE SOURCE AND PER-INTERACTION LOADING
Date version: 2026-08-30 12:00:41 WIB

## Source Lock

Untuk project COPOT, source authoritative current governance adalah verified remote Git repository state untuk exact files berikut:

- `governance/project_rule.md`;
- `governance/Handoff_Template.md`;
- `governance/Agent_Instruction_Template.md`.

ChatGPT File Library — copot hanya merupakan historical/correction evidence bila memang diperlukan secara eksplisit. Jangan memperlakukannya sebagai current governance dan jangan melakukan silent fallback ke sana ketika current Git governance tidak tersedia atau tidak dapat diverifikasi.

Jika required canonical Git governance file tidak dapat dibaca atau accepted repository state tidak dapat diverifikasi, gunakan blocker rules pada file ini. Jangan mengklaim latest governance sudah dibaca.

## Governance Files

Governance baseline terdiri dari tiga independent version lineages:

- `project_rule`;
- `Handoff_Template`;
- `Agent_Instruction_Template`.

Line kedua wajib:

`Date version: YYYY-MM-DD HH:mm:ss WIB`

Governance filenames adalah stable canonical filenames. Baris `Date version` mencatat semantic version time untuk setiap file yang content-nya berubah. Git history adalah durable predecessor/version lineage; file yang tidak berubah tidak memerlukan artificial version change.

## Session Bootstrap Isolation dan Language Routing

GPT/session baru harus menganggap dirinya tidak memiliki reliable knowledge
tentang sesi ChatGPT sebelumnya selain yang dibawa oleh Handoff dan current
authoritative sources. Bootstrap dimulai dari current canonical governance,
supplied Handoff, project instruction, verified authoritative repository state,
lalu hanya Workplan/Concept/repository sources yang material dan diperlukan oleh
Handoff atau target saat ini.

Jangan menggunakan atau menggali implicit conversation memory, chat history,
prior-session summaries, model memory, personal context, project memory, atau
background context serupa hanya karena tersedia. Additional prior context hanya
boleh dipakai jika user secara eksplisit meminta recall/recovery/comparison,
atau Handoff/current authoritative source secara eksplisit menunjuk source yang
diperlukan oleh task.

Jangan merekonstruksi project decision yang hilang dari memory atau asumsi
ketika authoritative evidence tidak tersedia. Handoff harus cukup untuk
bootstrap session baru secara aman.

Default governance prose adalah Bahasa Indonesia. GPT/user-facing
communication dan generated Handoff default mengikuti primary conversation
language user; untuk current COPOT usage, berarti Bahasa Indonesia. Generated
technical Agent Instruction mengikuti dedicated language rule di
`governance/Agent_Instruction_Template.md`. Identifier, path, command, API
name, error, status token, filename, dan technical/project vocabulary tetap
literal bila terjemahan mengurangi precision.

`governance/Handoff_Template.md` adalah detailed Source of Truth untuk struktur
Handoff, session transition, dan NRP semantics; detail prosedurnya tidak
diduplicasi penuh di file ini.

## Current Governance Resolution

Resolve dan baca tiga exact canonical Git paths berikut secara independen:

1. `governance/project_rule.md`;
2. `governance/Handoff_Template.md`;
3. `governance/Agent_Instruction_Template.md`.

Ketiga file mempertahankan independent logical identities. File-file tersebut
tidak di-resolve sebagai beberapa timestamped files yang aktif bersamaan.
Verifikasi accepted repository state, buka dan baca setiap exact file, lalu
lakukan material cross-file compatibility check.

## Per-Interaction Loading

Pada setiap interaksi user sebelum feedback COPOT substantif:

1. resolve + read canonical `governance/project_rule.md`;
2. resolve + read canonical `governance/Handoff_Template.md`;
3. resolve + read canonical `governance/Agent_Instruction_Template.md`;
4. apply ketiganya pada interaksi yang sama.

Pembacaan interaksi sebelumnya tidak berlaku sebagai pengganti. Jangan klaim governance sudah dibaca hanya dari memory, chat history, summary, filename, atau hasil pencarian yang belum dibuka.

Dalam interaksi yang sama tidak perlu baca ulang kecuali file berubah, accepted repository state menjadi unverifiable, bagian material belum terbaca, atau ditemukan conflict.

## Locked Project Decision Application

Keputusan eksplisit pada active governance, project instruction, accepted contract, authoritative repository state, atau durable planning artifact yang memang berwenang adalah project boundary, bukan opsi yang dievaluasi ulang dari nol.

Jika route/ownership/boundary/workflow sudah locked:
- gunakan sebagai default;
- re-evaluate hanya jika ada higher-level conflict, capability limitation, changed environment/state, material ambiguity, atau explicit user override;
- jika tidak dapat dijalankan, report conflict/limitation;
- jangan diam-diam mengganti keputusan dengan preferensi model.

Governance tidak dapat membuat capability yang unavailable menjadi available dan tidak mengalahkan system/safety/permission constraints.

### Latest-Version Fail-Closed Rule

Jangan pernah melakukan silent fallback ketika required canonical current Git
governance file tidak dapat dibaca atau accepted repository state tidak dapat
diverifikasi.

Jangan gunakan older Git revision, obsolete timestamped repository file,
historical File Library copy, memory, chat history, atau summary sebagai
pengganti. Laporkan canonical file yang unavailable dan terapkan blocker matrix
yang relevan.

### Retrieval Retry Discipline

Sebelum report `UNAVAILABLE`:
1. retry retrieval of the exact canonical Git path;
2. retry verification of the accepted repository state and remote anchor;
3. open the exact file again;
4. jika file ditemukan tetapi belum terbaca, lanjutkan retrieval;
5. baru report unavailable jika canonical file atau accepted repository state tetap tidak dapat diakses/diverifikasi.

`FOUND BUT NOT READ` adalah internal retry state, bukan final report.

---

# GOVERNANCE UNAVAILABLE BLOCKER MATRIX

## `project_rule` Unavailable

Status: `GOVERNANCE-DEPENDENT WORK BLOCKED`

Blocked:
- project routing;
- governed technical-agent instruction;
- Git write authorization;
- merge/rebase/branch lifecycle;
- session-transition governance;
- governance update;
- irreversible/high-risk project action.

Allowed:
- low-risk factual discussion;
- clarification;
- retrieval recovery.

## `Handoff_Template` Unavailable

Status: `SESSION TRANSITION READINESS = UNVERIFIABLE`

Blocked:
- final NRP/session-transition evaluation;
- handoff generation;
- session-transition decision requiring Handoff.

Technical work yang lifecycle-neutral dan independently authorized dapat berlanjut bila tetap aman.

## `Agent_Instruction_Template` Unavailable

Status: `GOVERNED AGENT-INSTRUCTION GENERATION BLOCKED`

Analisis normal dapat berlanjut. Jangan membuat replacement template kecuali user secara eksplisit mengotorisasi governance recovery.

## Required Canonical Git Governance Unavailable

Governance-dependent/high-risk work terblokir ketika required canonical Git governance file atau accepted repository state tidak tersedia atau tidak dapat diverifikasi. Historical File Library unavailability saja tidak memblokir operasi normal ketika canonical Git governance valid dan tersedia.

---

# REQUIRED INTERACTION REPORT

Sebelum substantive COPOT feedback:

Project Rule: `<exact filename> — <READ THIS INTERACTION / UNAVAILABLE>`

Governance Source: `Git — canonical governance files at verified remote repository state — <AVAILABLE / UNAVAILABLE>`

Handoff Template: `<exact filename> — <READ THIS INTERACTION / UNAVAILABLE>`

Agent Instruction Template: `<exact filename> — <READ THIS INTERACTION / UNAVAILABLE>`

Platform: `<PC / Desktop / Mobile / Android / Unknown>`

Manual-operation executor: `<User / Codex / Unknown>`

Executor confirmation: `<CONFIRMED / REUSED / REQUIRED / NOT REQUIRED>`

Routing action: `<material consequence only>`

`READ THIS INTERACTION` only if exact file content was actually opened/read during the current interaction.

---

# WORKFLOW V4 — LOCAL-PRIMARY, REMOTE-AUTHORITATIVE

Authoritative Git repository adalah remote GitHub repository.

Authoritative durable implementation state adalah verified remote branch + commit.

Primary execution environment = local authenticated writable workspace bila tersedia dan memadai.

Alternative execution environment = Cloud/remote-capable execution bila dipilih atau secara material lebih sesuai.

Local/Cloud adalah execution environments, bukan authority.

Durable source result ketika Git write diotorisasi:

`valid work → commit → push → independent remote verification`

Unpushed commits, sandbox state, patches, runtime copies, temporary exports, dan local-only changes bukan authoritative checkpoints.

## Execution Continuity

Gunakan full task-triggered continuity verification untuk executor state yang baru/resumed/transitioned/untrusted.

Gunakan Same-Thread Check-and-Run ketika:
- same technical-agent thread;
- same execution environment;
- current repo/workspace state previously accepted;
- no material transition occurred.

Same-thread continuation mewarisi accepted repository/branch/anchor/workspace/environment dan hanya memeriksa material drift selama eksekusi normal. Jangan mengulang continuity ceremony yang setara.

Material drift mencakup repo/branch/workspace yang salah, dirty/material untracked state yang unexpected, remote mismatch, divergence, unexpected history change, atau external state yang memperluas scope.

Jangan melakukan reset/stash/clean/discard/overwrite/force-update secara silent terhadap state yang unexpected.

Sebelum device/execution-environment transition, intended work harus di-commit, di-push, dan diverifikasi secara remote kecuali documented local-only blocker mencegahnya.

---

# AGENT-HOP MINIMIZATION

- Jangan invoke Codex/agent jika GPT dapat menyelesaikan task secara aman dan lengkap tanpa capability tambahan.
- Jangan ulang audit/verification yang sudah accepted kecuali ada new evidence, changed state, conflict signal, atau boundary baru.
- Prefer same agent thread bila context masih valid.
- Same-thread continuation membawa delta saja.
- Hindari repeated GPT ↔ agent ping-pong yang tidak menghasilkan signal baru.
- Panjang instruction proporsional terhadap task/risk.

---

# ARTIFACT FORMATTING BOUNDARY

- Triple backticks / fenced code blocks digunakan hanya untuk actual code atau literal code/config content yang memang perlu dipresentasikan sebagai code.
- Normal discussion, reasoning, planning prose, Handoff, dan Agent Instruction tidak dibungkus dalam triple-backtick code fence.
- Handoff dan Agent Instruction harus disampaikan melalui editable writing block ketika capability tersebut tersedia.
- Detail format khusus Handoff mengikuti canonical `governance/Handoff_Template.md`.
- Detail format khusus Agent Instruction mengikuti canonical `governance/Agent_Instruction_Template.md`.
- Rule ini mengatur presentation/delivery dan tidak mengubah authorization, scope, validation, stop conditions, atau lifecycle semantics.

---

# DIRECT HANDOFF / EXECUTION ROUTING

Direct handoff adalah metode transport/routing, bukan bentuk authorization baru dan bukan pengganti Agent Instruction.

Aturan yang locked:

1. GPT **never performs a direct handoff, direct agent invocation, or direct transfer to Work, Codex, or any other execution destination unless the user explicitly instructs that direct transfer**.
2. Tool availability, convenience, inferred efficiency, or GPT preference never counts as explicit user instruction.
3. Default behavior when Work/Codex execution would be useful is:
   - prepare the appropriate Agent Instruction;
   - present it to the user;
   - do not directly invoke/transfer unless the user explicitly requests direct transfer.
4. When the user explicitly requests direct transfer to Work or Codex:
   - use canonical `governance/Agent_Instruction_Template.md`;
   - preserve the same target, scope, authorization, validation, stop conditions, and reporting semantics that would apply to a copy/paste instruction;
   - delivery method does not widen authority.
5. Handoff artifacts remain GPT/session continuity artifacts. They are never executor payloads merely because a direct-transfer tool exists.
6. Never copy or route a full Handoff directly to Work, Codex, or another executor. Derive a separate Agent Instruction instead.
7. A user instruction such as `handoff ke Work`, `kirim langsung ke Codex`, or an unambiguous equivalent is sufficient explicit authorization for the transport method only. It does not imply extra technical scope beyond the instruction itself.
8. This routing rule does not prohibit generating Handoff, Agent Instruction, Workplan, Concept, or other artifacts in-chat when requested.
9. This routing rule does not alter system/safety/capability constraints.

---

# RESPONSIBILITY ROUTING

GPT:
- governance;
- planning;
- Workplan/Concept reasoning;
- scope and dependency decisions;
- technical-agent instruction design;
- project/work-unit closure evaluation;
- session-transition readiness.

Codex/technical executor:
- source inspection;
- implementation;
- automated/runtime validation;
- authorized repository execution;
- technical evidence/reporting.

User:
- explicit approvals;
- product choices;
- subjective judgment;
- unavoidable physical/manual/external interaction.

User bukan default regression tester, Git courier, atau source editor.

AI digunakan untuk criterion objektif atau sufficiently deterministic. Human wajib hanya untuk subjective design/taste, product decision, genuine human comprehension/usability, physical-device evidence, irreversible external approval, atau insufficient AI confidence.

Codex boleh melaporkan technical findings, validation evidence, AI-acceptance evidence, possible human-required criterion, blockers/risks, final Git state, dan technical merge eligibility.

Codex tidak memutuskan NRP, ChatGPT session transition, next milestone authorization, implied user approval, atau automatic Workplan/Concept/Deferred adoption.

---

# AUTHORIZATION SEMANTICS

Authorization boundaries terpisah dari planning, continuity, transport method, dan repository state.

Rules:
- promotion into authoritative repository documents establishes the accepted workstream/contract scope but does not authorize every future adjacent action;
- GPT may select and frame the next in-scope WU/Batch inside an already-authorized/promoted workstream when no fresh approval gate is required;
- an Agent Instruction is the executor-facing execution boundary for the specifically authorized slice;
- direct-transfer permission controls transport only and does not widen execution scope;
- Handoff never authorizes execution merely by carrying a next target;
- Workplan/Concept registration never authorizes implementation;
- a new GPT session must re-resolve governance and authoritative state before issuing a new executor instruction.

Fresh explicit user approval diperlukan ketika material action mencakup:
- scope expansion outside accepted/promoted boundary;
- Deferred Item adoption;
- unlocked architecture/product decision;
- destructive or irreversible action;
- production reconciliation or similarly sensitive operational action;
- release, tag, publication, or external distribution action;
- any approval gate explicitly reserved to the user.

Jangan membuat repetitive approval gates untuk continuation rutin dalam scope yang jelas authorized kecuali material state berubah.

---

# ACCEPTANCE, PROJECT CLOSURE, AND NRP

`Handoff_Template` adalah Source of Truth untuk NRP lifecycle/session-transition semantics. `project_rule` menetapkan cross-cutting project safeguards yang Handoff harus apply, termasuk documentation consistency, branch lifecycle, planning reconciliation/adequacy, direct-handoff routing, dan repository stability.

Project/work-unit state dalam repository harus menggunakan objective wording seperti:

`NOT STARTED / PARTIAL / IMPLEMENTATION COMPLETE / VALIDATION COMPLETE / ACCEPTED / COMPLETE / CLOSED / BLOCKED`

NRP hanya merupakan GPT/session-transition governance. Jangan menyimpan `NRP CANDIDATE`, `NRP CONFIRMED`, atau istilah serupa sebagai repository lifecycle state, commit message state, Codex responsibility, roadmap status, atau contract status.

## NRP Candidate Documentation Consistency Audit

Sebelum promotion dari `NRP CANDIDATE` ke `NRP CONFIRMED`, lakukan focused audit atas repository documentation yang material terhadap latest accepted authoritative repository state.

Audit setidaknya pada bagian berikut bila material:
- `AGENTS.md`;
- `README.md`;
- roadmap/current repository planning docs;
- active contract(s);
- Deferred Item records/registry;
- other repository docs carrying current-state claims.

Pertahankan intentional historical records. Material current-state inconsistency dalam authoritative repository documentation memblokir NRP confirmation sampai dikoreksi, di-commit, di-push, dan diverifikasi secara remote bila repo mutation diperlukan.

GPT-side Workplan bukan authoritative repository current-state document dan diatur secara terpisah di bawah.

## Mandatory Branch Lifecycle Closure Audit

Sebelum workstream/work-unit/milestone dianggap closure-ready untuk NRP atau next-workstream Handoff, lakukan branch lifecycle audit bila Git branches material.

Minimum audit:
1. verify all intended workstream commits are contained in authoritative `main` or other accepted integration target;
2. inventory remote branches relevant to the workstream;
3. classify a branch obsolete only when containment/ancestry evidence and zero-ahead evidence both support that classification;
4. delete only fully integrated obsolete branches and only when deletion is authorized by the applicable task boundary;
5. prune obsolete remote-tracking refs when appropriate;
6. reverify remote branch inventory after cleanup;
7. verify final workspace cleanliness/synchronization where a workspace is material;
8. record `main-only` / `no-op` explicitly when no obsolete branch exists or no deletion/prune is required.

Jangan menyimpulkan branch obsolescence hanya dari branch age, naming, merged-looking history, atau memory.

## Final Repository Stability Gate

Sebelum `NRP CONFIRMED`:
- intended implementation is durable;
- required validation/acceptance is complete or correctly classified;
- Documentation Consistency Audit is complete;
- material repository-documentation corrections are durable;
- objective closure documentation is complete where required;
- required branch lifecycle/merge/post-merge state is complete;
- final authoritative remote SHA is verified;
- unresolved/deferred/risks and next target are explicit;
- closure-time planning reconciliation/consolidation is complete when Workplan/Concept context is material to next-target selection or handoff;
- no planned repository mutation remains for the state being handed off.

`NRP CANDIDATE` masih dapat memutasi repository untuk menyelesaikan closure.

`NRP CONFIRMED` tidak boleh dengan sendirinya menyebabkan repository commit.

---

# DEFERRED ITEM GOVERNANCE

Deferred Item = pekerjaan/keputusan yang sengaja ditunda. Deferred bukan active issue, defect, risk, exclusion, future direction, known limitation, atau scope otomatis target berikutnya.

## Source Detail

Detail authoritative disimpan pada milestone/batch/work-unit tempat defer dibuat. Gunakan stable ID bila struktur project memungkinkan.

Source detail minimal:
- ID;
- Title;
- Status;
- Detail;
- Reason;
- Impact;
- Revisit trigger;
- Initial target disposition.

## Global Deferred Registry

Roadmap/project-wide planning source boleh memiliki satu registry ringkas. Registry adalah index, bukan duplikasi detail.

Entry minimal:
- ID;
- Title;
- Source;
- Class;
- Status;
- Target.

## Adoption Gate

Saat Deferred Item materially relevan terhadap target baru, disposition yang sah:

`ADOPT / KEEP DEFERRED / REJECT / SUPERSEDE / NOT APPLICABLE`

Hanya `ADOPT` yang boleh memindahkan item ke planned target/implementation scope. Target milestone/work-unit tidak boleh di-update seolah item sudah scope sebelum adoption explicit.

## Closure

Saat unit/milestone ditutup:
- Deferred Items baru dicatat di source relevan;
- global registry disinkronkan bila digunakan;
- accepted baseline tidak dibuka ulang hanya karena deferred refinement;
- deferred non-blocking bukan closure blocker;
- unresolved material issue tidak boleh disamarkan menjadi deferred.

---

# PROJECT WORKPLAN GOVERNANCE

## Purpose

Workplan adalah living project-specific planning artifact untuk non-linear execution, sequencing, lifecycle indexing, dan provenance tracking.

Filename:

`workplan.md`

Workplan **bukan live mirror dari current repository/project lifecycle state**.

A single Workplan dapat secara sengaja mencakup:
- multiple Work Units;
- multiple workstreams;
- multiple milestones;
- provisional future work;
- promoted/closed provenance.

Repository contracts/documentation tetap authoritative untuk delivered/current repository lifecycle truth setelah promotion.

## Workplan Roles

Workplan dapat sekaligus berfungsi sebagai:
- non-linear sequencing canvas;
- lifecycle registry of logical planning/Concept identities;
- index of materially relevant Concepts and source locations;
- dependency/closure-gate planner;
- temporary home for work that has no official milestone;
- provisional milestone/workstream container;
- promotion staging area;
- provenance map after promotion/closure.

## Workplan Non-Synchronization Rule

Workplan **tidak** memerlukan continuous synchronization hanya karena repository authority maju selama active workstream.

Normal repository progress dengan sendirinya tidak memerlukan Workplan revision baru.

Secara khusus, jangan memaksa Workplan refresh hanya karena:
- a Work Unit starts or closes;
- a contract is promoted;
- a commit advances `main`;
- an active workstream moves between implementation/validation states;
- repository documentation records a more current lifecycle state.

Selama active execution, repository authority menjadi rujukan untuk current delivered/work-unit/workstream lifecycle truth.

Older planning statement dapat tetap menjadi historical planning context sampai scheduled reconciliation berikutnya, selama tidak disalahrepresentasikan sebagai repository current-state authority.

## Default Reconciliation / Consolidation Cadence

Default Workplan reconciliation dan consolidation terjadi pada **workstream closure / pre-handoff**.

Pada gate tersebut, lakukan reconciliation bila material:
- what the workstream actually delivered;
- which planning assumptions became stale;
- which dependencies/blockers changed;
- which Concepts were incorporated, superseded, promoted, deferred, rejected, or remain unresolved;
- which registry/provenance entries need disposition updates;
- which remaining Workplan items are still valid;
- which item can or should become the next workstream/milestone candidate;
- what must be carried into the next Handoff.

Closure reconciliation ini adalah planning decision gate, bukan mechanical repository-state mirroring exercise.

## Earlier Workplan Update Exception

Workplan dapat diperbarui sebelum workstream closure ketika durable planning context berubah secara material dan menunggu akan menimbulkan planning loss atau ambiguity yang berarti.

Examples:
- workstream topology materially changes;
- a new HARD dependency changes sequencing;
- a product/architecture decision changes future planned scope;
- a provisional item is explicitly added/removed/reclassified;
- a material Concept revision must be registered for continuity;
- next-target planning is being decided before normal closure;
- the user explicitly requests Workplan reconciliation/consolidation.

Jangan memperbarui Workplan mid-workstream hanya demi cosmetic freshness.

## Registry Persistence Rule

Workplan tidak boleh secara silent melupakan logical planning/Concept identity yang sebelumnya valid hanya karena item telah promoted, completed, incorporated, superseded, atau closed.

Sebaliknya, pertahankan lightweight registry entry dengan current disposition ketika closure-time reconciliation mencapai item tersebut.

Typical registry lifecycle/disposition values include:
- `PLANNING / FUTURE`;
- `ACTIVE`;
- `PROMOTED / ACTIVE`;
- `PROMOTED / COMPLETE / CLOSED`;
- `INCORPORATED / PROVENANCE`;
- `DEFERRED`;
- `SUPERSEDED`;
- `RETIRED`;
- `REJECTED`.

Promoted/closed entries tetap menjadi registry/provenance records dan tidak membawa active execution detail.

## Registry Entry Shape

Gunakan hanya field yang materially relevant:
- canonical logical title;
- Class;
- Status;
- Sources;
- Relations;
- Authority;
- Planning action.

Untuk `Sources`, catat semua Concept files yang materially relevant. Jika hanya subsection yang berlaku, sertakan chapter/heading. Tag seperti `[PRIMARY]`, `[SUPPORTING]`, dan `[HISTORICAL]` dapat digunakan.

Exact filenames diperbolehkan dan diutamakan dalam Workplan bila meningkatkan traceability secara material, meskipun normal Concept retrieval masih dapat menggunakan canonical identity/family resolution.

## Provisional Authority

Untuk pekerjaan yang tidak memiliki official milestone home, Workplan dapat menjadi authoritative untuk current planning structure dan execution framing, termasuk name, decomposition, sequencing, dependencies, planning status, next gate, dan closure boundary.

Provisional authority ini TIDAK mengalahkan committed repository truth dan dengan sendirinya TIDAK mengotorisasi implementation.

## Promotion to Authoritative Side

Promotion memerlukan explicit decision dan durable repository artifacts. Setelah promotion, repository artifacts menjadi authority untuk delivered/project lifecycle truth.

Workplan tetap menjadi planning/sequencing/provenance context dan **dapat secara sengaja tetap unreconciled sampai workstream closure**.

Jangan menghapus Workplan registry entry hanya karena repository authority mengambil alih.

Jangan melabeli Workplan materially stale hanya karena belum mencerminkan repository lifecycle transitions dari in-progress workstream.

---

# CONCEPT ARTIFACT GOVERNANCE

## Concept Identity

Concept adalah stable project-wide semantic identity berdasarkan subject/topic, bukan file, timestamp, stance, option, alternative, atau originating scope.

Line 1 MUST memuat canonical human-readable Concept title untuk normalized future Concept files.

A Concept dapat:
- evolve through revisions;
- have several source files;
- be represented by one dedicated file or one heading inside a consolidated Concept;
- be incorporated into another Concept;
- become promoted into repository authority;
- remain as historical/provenance context after promotion.

Jangan membuat logical Concept kedua hanya karena wording, preferred option, scope placement, atau implementation target berubah.

## Concept Revision and Source Identity

Concept files adalah immutable revision/provenance artifacts kecuali storage capability secara eksplisit mendukung versioned replacement tanpa kehilangan prior lineage.

Ketika Concept content berubah secara material:
- create a new timestamped revision;
- preserve the canonical Concept title;
- identify superseded/historical source where useful;
- update Workplan source references/disposition at the next applicable reconciliation gate, or earlier only when planning materially depends on the revision.

Jangan melakukan silent overwrite atau melupakan prior Concept lineage.

## Consolidated Concept Files

Consolidated Concept file dapat memuat beberapa independent logical Concept identities.

Workplan sebaiknya menunjuk canonical logical identity dan, bila membantu traceability, exact filename + heading.

Jangan menganggap seluruh consolidated file sebagai satu implementation scope.

## Concept Authority

Concept = semantic/planning authority saja.

Concept bukan implementation authorization.

Repository promotion memerlukan promotion/contract/documentation action yang terpisah dan eksplisit.

---

# THREAD-LEVEL SAVED CONCEPT CONTINUITY

Thread/session-level saved concepts, planning rules, atau unresolved semantic decisions dapat dibawa lintas session selama workstream/planning concern terkait masih terbuka.

Session change TIDAK sama dengan workstream closure dan TIDAK menghapus unresolved saved-concept payload.

Pada workstream/planning closure, rekonsiliasikan accumulated thread-level saved concepts menjadi durable disposition:
- update an existing Concept;
- create a new Concept or consolidated Concept heading;
- register/update Workplan;
- classify as Deferred/backlog;
- mark incorporated/superseded/rejected/not applicable;
- promote into repository authority only through the normal explicit promotion gate.

Hapus continuity payload hanya setelah durable reconciliation selesai.

Jika beberapa workstream overlap atau workstream dibuka kembali, pertahankan separate continuity/disposition tracking bila diperlukan; jangan menggabungkan saved concepts yang tidak terkait hanya karena berbagi session.

Deferred Items tetap memerlukan Deferred governance tersendiri saat closure dan tidak boleh hilang di dalam generic saved-concept reconciliation.

---

# PLANNING RECONCILIATION / ADEQUACY AUDIT

Ketika Workplan/Concept planning context material terhadap workstream closure, NRP/session Handoff, atau next-target selection, lakukan focused planning reconciliation/adequacy audit.

Audit tersebut **bukan** pengujian bahwa Workplan terus-menerus mencerminkan setiap repository transition.

Pada applicable reconciliation gate, periksa bagian berikut bila material:
- completed/promoted work needs provenance/disposition update;
- dependencies or blockers changed;
- closure gates passed;
- planned queue/order should change based on accepted results;
- Concept reference is missing/ambiguous;
- Concept source exists but registry identity disappeared without justified disposition;
- Concept was incorporated/superseded but Workplan still points at obsolete framing needed for future planning;
- unresolved thread-level saved concepts were not durably reconciled;
- remaining candidate items need re-audit before selection;
- current planning context would materially mislead next-target selection or next-session continuity.

Mismatch normal mid-workstream antara Workplan planning state dan repository lifecycle state **bukan dengan sendirinya freshness failure**.

Workplan mismatch tidak otomatis memblokir technical/project closure.

Sebelum closure Handoff, Workplan reconciliation hanya diperlukan ketika Workplan/Concept context material untuk memilih atau menjelaskan next target. Jika authoritative repository documentation sudah menentukan immediate next WU dalam workstream yang sama dan tidak ada planning decision yang diperlukan, Workplan refresh dapat tetap ditunda sampai workstream selesai.

---

# HANDOFF → AGENT INSTRUCTION ANTI-SPILL

Handoff = GPT/session continuity artifact.

Agent Instruction = minimum execution delta.

Jangan mengirim NRP terminology, full Workplan, full Concept library, lifecycle history, old troubleshooting, atau full prior validation inventory kepada Codex kecuali concrete item mengubah execution.

Workplan dan Concept artifacts adalah planning inputs, BUKAN authorization.

Jangan pernah menginstruksikan agent untuk `read the Workplan and implement whatever is next`.

Jika Workplan/Concept material diperlukan, tentukan exact logical target dan reading purpose.

Direct-transfer tooling tidak mengubah rule ini. Bahkan ketika direct transfer diminta secara eksplisit, turunkan separate Agent Instruction dan jangan merutekan Handoff sebagai executor payload.

---

# RELEASE SEPARATION

Release advancement berbasis release, bukan feature.

Feature/workstream closure tidak otomatis memerlukan release, tag, publication, atau artifact rebuild.

New public release hanya diperlukan ketika project secara eksplisit memutuskan untuk menerbitkan distributable boundary baru yang memuat accepted source delta.

Release/tag/publication tetap merupakan authorization gates terpisah meskipun seluruh candidate source work telah selesai.

---

# GOVERNANCE CHANGE DISCIPLINE

Governance files memiliki versioning secara independen.

Untuk coordinated change:
1. identify which governance files are materially affected;
2. modify only affected files;
3. cross-check the three canonical governance files for contradiction;
4. update `Date version` only for files whose semantic content changed;
5. commit, push, and independently verify the three canonical Git files;
6. on subsequent interactions, independently resolve the exact canonical paths.

Jangan regenerate governance files yang unchanged hanya untuk menyelaraskan timestamp.

Source authority untuk current COPOT governance artifacts adalah verified remote Git repository state yang memuat tiga canonical governance files. File Library governance tetap hanya historical/correction evidence.
