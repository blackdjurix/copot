# copot <Batas Continuity> — <Judul>
Date version: 2026-09-30 21:32:22 WIB

## Batas penggunaan

Handoff adalah artifact continuity GPT/session untuk `Project`. Handoff bukan Agent Instruction, bukan payload executor, bukan authorization baru, dan bukan pengganti Rule.

Identitas canonical: resolve dari Rule variable `Governance Handoff`.

## Semantik title Handoff

Baris pertama adalah canonical continuity title dan kandidat utama untuk nama session.

Format:

`Project` <Batas Continuity> — <Judul>

Contoh:

`Project` WU2 — Security Platform Capability Baseline Closure

`Project` WU2 Batch 1 — Security Platform Capability Baseline Closure

Gunakan nilai dari Rule variable `Project` sebagai nama project canonical. Jangan menambahkan prefix `HANDOFF`, `PROJECT SESSION HANDOFF`, atau wrapper generik lain.

`Batas Continuity` mengidentifikasi milestone, Work Unit, batch, slice, workstream, phase, atau boundary project lain yang relevan. `Judul` menjelaskan tujuan atau state continuity yang dicatat. External title/name field tidak menggantikan canonical title pada baris pertama.

## Handoff Delivery Format

Generated Handoff harus menggunakan struktur berikut:

- First line: # `Project` <Continuity Boundary> — <Judul>
- Then: `<Batas penggunaan dan continuity isolation>`
- Required section order: `## Perpindahan`, `## Objective dan state saat ini`, `## Target berikutnya`.
- Then: `<handoff contents yang material>` sesuai continuity transition.
- Final line must be the bare timestamp `<YYYY-MM-DD HH:mm:ss WIB>` without any prefix or label.

Section conditional hanya dimasukkan bila material terhadap continuity transition.


## Isolasi session baru dan bahasa

GPT penerima tidak boleh mengandalkan implicit memory, summary session sebelumnya, chat history, atau model context sebagai pengganti Handoff ini dan sumber authoritative saat ini. Lakukan rekonstruksi hanya dari governance saat ini, Handoff ini, project instructions, state Repository yang diverifikasi, dan source material yang diidentifikasi secara eksplisit.

Gunakan bahasa utama conversation untuk prosa Handoff. Pertahankan identifier, path, command, filename, dan technical token secara literal bila precision memerlukannya.

## Perpindahan

- Transition type: `NORMAL / NRP / EMERGENCY`
- Project: `Project`
- Continuity Boundary: `<milestone / Work Unit / batch / workstream / phase>`
- Title: `<title>`
- Prepared at: `<YYYY-MM-DD HH:mm:ss WIB>`
- Prepared by: `<actor>`

Untuk `EMERGENCY`, risiko continuity dan kebutuhan recovery/revalidation wajib dinyatakan secara eksplisit.

## Objective dan state saat ini

- Objective: `<objective>`
- Accepted result: `<verified accepted result / None>`
- Current state: `<what is true now>`
- Last reliable evidence: `<commit, document, test, artifact, or observation>`
- Unresolved/unsaved payload: `<reference to the section below or None>`

### Contract authorization

Cantumkan hanya bila contract material terhadap continuity:

Section ini hanya mencatat contract authority state dan tidak memberikan authorization baru.

- Authoritative contract: `<path/title / None>`
- Contract status: `<provisional / proposed / accepted / promoted / durable / None>`
- Authorized contract scope: `<summary / None>`
- Contract exclusions or separate gates: `<summary / None>`
- Contract authorization evidence: `<source / None>`

Pisahkan state accepted, provisional, rejected, superseded, dan unresolved.

## Payload unresolved dan unsaved

Perlakukan payload unresolved dan unsaved/unpersisted sebagai satu kelas continuity. Output-nya berbeda, tetapi keduanya tidak authoritative sampai jalur persistence dan verifikasi yang diperlukan selesai.

- Payload identity/source: `<artifact, thread state, decision, instruction, change, or runtime state>`
- Payload kind: `<unresolved / unsaved / both>`
- Output currently available: `<partial result, open decision, evidence, or None>`
- Persistence state: `<not persisted / partially persisted / persisted but unverified / persisted and verified>`
- Durable disposition: `<carry forward / persist before transition / intentionally discard with authorization / blocked / unknown>`
- Required next action: `<exact action or None>`
- Authority status: `<non-authoritative until persisted and verified>`

Cantumkan bila berlaku:

- keputusan user yang belum disimpan;
- kesimpulan atau keputusan turunan GPT yang belum disimpan;
- Agent Instruction yang belum dikirim;
- perubahan Repository yang belum di-commit;
- perubahan Workplan/Concept yang belum dipersist;
- state runtime sementara;
- artifact yang dihasilkan tetapi belum diterima atau dipersist.

## Target berikutnya

- Next target: `<smallest safe next target>`
- Dependency: `<dependency or None>`
- Authorization status: `<authorized / approval required / blocked>`
- Required first action: `<bootstrap/check>`
- Stop condition: `<condition>`

Next target bukan authorization baru. GPT penerima harus memvalidasi ulang authority dan state saat ini.

## Continuity Planning dan Concept

- Workplan state: `<status>`
- Non-synchronization check: `<no repository sync implied / issue>`
- Closure reconciliation: `<complete / required / blocked>`
- Saved Concept payload: `<semantic identity, provenance, revision, unresolved/unsaved payload reference>`
- Deferred Items: `<status and adoption state>`

Perpindahan session tidak menghapus payload planning yang unresolved.

### Reconciliation Saved Concept tingkat thread

- Accumulated thread-level saved Concepts: `<identity and source>`
- Reconciled durable disposition: `<carried forward / incorporated / deferred / superseded / rejected / unresolved>`
- Unresolved/unsaved payload preserved: `<yes/no and summary>`
- Reconciliation evidence: `<source, revision, or None>`

Perubahan session tidak boleh menghapus, menutup secara diam-diam, mengubah klasifikasi, atau membuang secara diam-diam Saved Concept maupun payload unresolved/unsaved.

## State dependency dan stacked branch

Jika material, catat:

- cross-boundary dependency: `<dependency and satisfied/unsatisfied state>`;
- stacked branch relation: `<base, dependent branch, containment, or None>`;
- dependency evidence: `<exact source or None>`.

State dependency adalah context, bukan authorization untuk memulai pekerjaan yang bergantung padanya.

## State Repository dan runtime

Cantumkan hanya bila material:

- Repository: `Repository Link`
- Integration target: `Integration Target`
- Branch: `<branch>`
- HEAD/revision: `<revision>`
- Working tree: `<clean / listed changes / unknown>`
- Runtime role: `Primary Runtime` role or None
- Runtime endpoint/port: `<only if material; never project identity>`
- Divergence or lifecycle issue: `<value or None>`

Runtime copy bukan authority Repository.

## Acceptance dan closure

- Acceptance criteria: `<met / partial / not met>`
- Validation: `<checks and outcomes>`
- Reconciliation documentation/planning: `<status>`
- Closure work-unit/workstream: `<closed / open / blocked>`
- Release/publication: `<separate status>`
- NRP: `<candidate / confirmed / not applicable / blocked>`

### Konsistensi documentation untuk NRP candidate

- Candidate project/work-unit documentation: `<source>`
- Current implementation/repository evidence reconciled: `<yes / no / blocked>`
- Material documentation conflict: `<None or exact conflict>`
- Required correction before NRP confirmation: `<None or exact correction>`

Handoff tidak boleh menyatakan NRP confirmed hanya karena technical work selesai.

## Continuity session

- Continue current session/thread or new: `<decision and reason>`
- Context requiring revalidation: `<items>`
- Minimum bootstrap: `<first reads/checks>`
- Material `Gateway` state: `<only if remote access is relevant>`
- Material `Prototyping` state: `<only if visual/prototype context is relevant>`

Urutan bootstrap: governance saat ini, Handoff ini, project instructions, state project/Repository yang diverifikasi, lalu hanya Workplan, Concept, documentation, runtime, atau tool sources yang material dan diidentifikasi secara eksplisit.

## Boundary direct transfer

Jangan mentransfer full Handoff ke Technical Executor.

Jika user meminta direct transfer, turunkan Agent Instruction terpisah yang hanya membawa minimum execution context yang material. Handoff tidak memperluas authorization.

## Report startup wajib

GPT penerima harus melaporkan:

- exact governance artifacts yang dibaca;
- objective/state saat ini yang diverifikasi;
- state unresolved;
- status authorization;
- revalidation yang dilakukan;
- blocker atau risiko continuity.

## Pernyataan closure

Handoff ini mencatat state continuity. Handoff tidak dengan sendirinya menyatakan implementation complete, closure project, konfirmasi NRP, release readiness, atau authorization eksekusi.
