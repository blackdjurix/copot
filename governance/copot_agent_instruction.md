# AGENT INSTRUCTION TEMPLATE
Date version: 2026-10-12 13:48:00 WIB

## Purpose

Ini adalah kontrak eksekusi yang ringkas, fokus, dan spesifik terhadap task untuk `Project` `Technical Executor`.

Identitas governance canonical: resolve dari Variable `Agent Instruction` pada `Rule`.

### Identitas `Rule`

Dalam template ini, `Rule` berarti canonical project `Rule` terbaru yang di-resolve dari Variable `Rule` pada governance `Project`. `Rule` adalah sumber untuk Variable, authority boundary, dan routing governance yang digunakan oleh template ini.

### Reference Variable

Setiap Variable canonical yang digunakan template ini wajib di-resolve dari Variables pada `Rule` terbaru. Jangan mendefinisikan ulang, menginfer, atau mengganti value Variable berdasarkan memory, `Handoff`, context session sebelumnya, atau isi `Agent Instruction` yang sudah digenerate.

## Semantik title Agent Instruction

Dua baris pertama merupakan title session dan title instruksi.

Format:

`Project` <Continuity Boundary> — <Title>
`<Instruction Title>`

Baris pertama mengidentifikasi `Project`, session atau continuity boundary, dan title continuity. Baris kedua menjelaskan pekerjaan teknis yang dilakukan oleh `Technical Executor` pada interaction atau bubble tersebut.

`Instruction Title` harus berupa ringkasan pekerjaan teknis aktual, bukan salinan `Handoff`, `Workplan`, `Concept`, authorization, atau lifecycle governance. Jangan menambahkan prefix `AGENT INSTRUCTION` atau wrapper generik lain pada title yang dikirim ke `Technical Executor`.

`Agent Instruction` harus dibuat untuk exact execution slice yang sudah diotorisasi. `Agent Instruction` bukan salinan penuh `Rule`, `Handoff`, `Workplan`, atau governance lifecycle `Project`.

Terapkan prinsip minimum execution delta: masukkan field hanya bila menghilangkannya dapat menyebabkan target, branch atau anchor yang salah, action tanpa authorization, dependency terlewat, validation tidak valid, side effect yang tidak aman, atau stop condition terlewat.

### Internal generation and leakage boundary

`Rule` routing governance ini hanya untuk proses kompilasi internal GPT dan tidak boleh disalin ke `Agent Instruction` yang digenerate. Instruction hanya boleh memuat execution delta yang dibutuhkan `Technical Executor`: target, exact payload, technical scope, authorization, dependency, validation, dan stop condition.

Jangan meneruskan vocabulary yang hanya milik governance atau daftar larangan governance ke instruction, kecuali istilah tersebut adalah technical input exact yang memang dibutuhkan untuk eksekusi. Utamakan boundary generik seperti `Execute only the supplied technical slice` dan `Do not infer or expand requirements beyond the supplied payload.`

Sebelum delivery, lakukan leakage preflight: hapus wording governance yang tidak mengubah eksekusi, hapus daftar larangan yang redundant, dan pastikan setiap field yang tersisa material terhadap technical slice yang diotorisasi.

Jangan otomatis menyalin:

- full `Handoff` atau riwayat lifecycle;
- reasoning keputusan internal `Project`/session;
- troubleshooting sebelumnya atau state `Git` lama;
- planning library penuh atau riwayat `Repository`;
- riwayat saved `Concept` tingkat thread, kecuali ada satu item unresolved yang mengubah eksekusi.

## Instruction Format

Generate setiap `Agent Instruction` dengan struktur delivery berikut:

1. `Title`
2. `Current Project State`
3. `Execution Instruction and Boundary`
   - context `Project`;
   - target / task;
   - scope;
   - authorization;
   - precondition;
   - dependency;
   - hanya module conditional yang material terhadap task: source reading, planning/`Concept`, pre-contract review, documentation, tools, atau Git/`Repository`.
4. `Execution Controls`
   - execution gates;
   - validation requirements;
   - stop conditions.

`Title` memakai format dua baris yang didefinisikan di atas. `Current Project State` hanya memuat state terverifikasi yang dapat memengaruhi eksekusi, seperti identity repository/workspace, branch atau anchor, runtime, dependency, atau hasil sebelumnya yang relevan.

`Execution Instruction and Boundary` adalah satu blok eksekusi yang terintegrasi. Conditional modules bergantung pada konteks dan dapat ditempatkan di bagian yang paling memperjelas eksekusi; module tidak harus dikeluarkan sebagai blok terpisah sebelum atau sesudah field inti.

Format ini adalah skeleton delivery yang usage-friendly, bukan kewajiban untuk memasukkan semua field atau module. Masukkan hanya minimum execution delta yang diperlukan untuk mencegah target yang salah, action tanpa authorization, dependency terlewat, validation tidak valid, side effect yang tidak aman, atau stop condition terlewat. Hilangkan governance, history, atau context berulang yang tidak memengaruhi slice yang diotorisasi.

Jangan menambahkan section report generik ke `Agent Instruction`. Tentukan technical output hanya bila execution slice membutuhkannya; `Technical Executor` mengembalikan evidence eksekusi melalui delivery channel yang berlaku.

## Current Project State

- `Project`: `Project`
- Continuity Boundary: `<Work Unit / batch / workstream / phase>`
- Objective: `<objective konkret tunggal>`
- Repository/workspace: `<identity exact bila material>`
- Integration target: `Integration Target`
- Primary Runtime: `Primary Runtime` bila material
- Runtime target: `Main Runtime / Disposable Runtime / None` bila material
- Runtime purpose: `<development / integration / operational validation / isolated technical validation / human acceptance support / None>`
- Applicable tool: resolve `Gateway` atau `Prototyping` bila material; selain itu None

Gunakan hanya state terverifikasi yang dapat memengaruhi eksekusi.

## Execution Instruction and Boundary

### Delivery

- Delivery: `USER-MEDIATED / DIRECT TRANSFER`
- Explicit direct-transfer request: `<YES / NO>`
- `Technical Executor`: `Technical Executor`
- Authorization source: `<source, scope, actor, dan action yang exact; bukan role pointer atau accepted boundary generik>`

Direct transfer hanya boleh dilakukan bila diminta secara eksplisit oleh user. Metode transport tidak memperluas authority atau scope.

Delivery context harus mengidentifikasi route yang berlaku:

- workspace lokal yang writable;
- eksekusi remote/cloud;
- validation runtime lokal;
- delivery yang dimediasi user.

Gunakan hanya route yang dibutuhkan execution slice ini.

### Task

- Task: `<action teknis konkret>`
- Mode: `AUDIT / IMPLEMENTATION / DEBUG / VALIDATION / TECHNICAL PRE-CONTRACT REVIEW`
- In scope: `<file, behavior, atau evidence yang spesifik>`
- Out of scope: `<pengecualian spesifik>`
- Preconditions: `<starting state yang diperlukan>`

Jangan menginfer pekerjaan berdekatan dari defect yang terlihat, Work Unit mendatang, dependency, atau waktu yang tersedia.

### Technical pre-contract review

Gunakan mode ini hanya bila GPT/user secara eksplisit meminta analisis teknis atas pre-contract atau draft contract yang disediakan.

Package yang wajib tersedia:

- payload pre-contract atau contract yang exact, secara inline atau melalui source exact yang dapat diverifikasi;
- scope review teknis;
- technical delta yang diizinkan;
- authorization eksplisit untuk proposal, application, atau drafting action;
- output dan validation yang diperlukan.

`Technical Executor` boleh menemukan technical gap, constraint feasibility, dependency, masalah interface/data-flow, validation gap, atau detail technical acceptance. `Technical Executor` boleh mengusulkan technical delta dan hanya boleh menerapkannya bila authorization secara eksplisit mengizinkan penerapan. `Technical Executor` tidak boleh menginfer semantic content yang hilang atau memperluas boundary yang disediakan.

Status review yang wajib digunakan:

- `TECHNICAL REVIEW COMPLETE`;
- `CHANGES PROPOSED`;
- `TECHNICAL DELTA APPLIED`;
- `READY FOR GPT/USER REVIEW`;
- `READY TO PROMOTE`;
- `SEMANTIC PAYLOAD NOT SUPPLIED`;
- `BLOCKED`.

`READY TO PROMOTE` berarti siap secara teknis untuk direview GPT/user. Status ini tidak dengan sendirinya membuat draft menjadi authoritative. Bila payload exact atau source yang dapat diverifikasi tidak tersedia, berhenti dengan `SEMANTIC PAYLOAD NOT SUPPLIED`.

### Authorization boundary

Authorized:

- `<action teknis yang exact>`
- `<action repository/runtime yang exact, bila ada>`
- `<action validation yang exact>`

Tidak diotorisasi:

- scope expansion atau perubahan boundary;
- keputusan acceptance, closure, atau lifecycle di luar execution slice yang disediakan;
- perubahan planning, deferred-scope, atau session kecuali dimasukkan secara eksplisit;
- release, tag, publication, deployment, atau integration kecuali dimasukkan secara eksplisit;
- cleanup branch atau runtime yang tidak terkait;
- memperbaiki finding berdekatan hanya karena finding tersebut ditemukan.

Finding bukan authorization. Laporkan finding tersebut dan berhenti, atau lanjut hanya di dalam execution boundary tertulis.

Authorization untuk validation bukan authorization untuk remediation. Authorization untuk mengubah implementation bukan authorization untuk documentation, integration, release, branch cleanup, atau perubahan Work Unit berdekatan kecuali dinyatakan secara eksplisit.

Jangan memperlakukan field `Handoff`, hasil technical acceptance, role assignment, dependency yang satisfied, test pass, atau capability yang tersedia sebagai authorization. Sumber authorization tertulis harus exact dan traceable.

### Before starting

1. Verifikasi identity repository/workspace dan starting state yang material.
2. Verifikasi branch, HEAD, tracking, dan perubahan tidak terduga bila Git material.
3. Inspeksi hanya file target, direct dependency, dokumentasi relevan, dan test yang fokus.
4. Konfirmasi authorization tertulis dan precondition.
5. Lindungi state yang tidak terkait.

Jika state terverifikasi berbeda secara material dari instruction, berhenti. Jangan reset, clean, stash, overwrite, atau menormalkan state secara otomatis.

### Dependency boundary

- Direct dependency: `<source, artifact, branch, runtime, atau capability>`
- Dependency status: `<satisfied / unsatisfied / unknown / blocked>`
- Dependency evidence: `<evidence exact>`
- Dependency action authorized in this slice: `<yes/no dan action exact>`

Jangan menyelesaikan atau mengimplementasikan task dependent hanya karena dependency-nya terlihat. Laporkan dependency yang unsatisfied atau baru ditemukan.

## Conditional Execution Modules

Masukkan hanya module yang material terhadap execution slice. Urutan internalnya bergantung pada konteks.

### Conditional tools

#### `Primary Runtime`, `Main Runtime`, dan `Disposable Runtime`

Gunakan `Primary Runtime` sebagai default runtime environment. Dalam COPOT, `Primary Runtime` menunjuk ke `XAMPP` dan `Main Runtime` adalah instance existing yang dipakai untuk development, integration, debugging, validation normal, serta operational/human acceptance bila task dapat dijalankan dengan aman pada existing project state.

Perubahan implementation/package-owned filesystem atau database state pada `Main Runtime` harus melalui Package Lifecycle yang berlaku. Jangan melakukan direct mutation bila task membutuhkan route lifecycle berbasis package.

State `Main Runtime` adalah evidence eksekusi atau installed state. Jangan mengklaim human acceptance, closure, atau NRP hanya dari state tersebut.

Gunakan `Disposable Runtime` hanya bila dibutuhkan isolation material, termasuk clean-state validation, destructive mutation testing, failure injection, incompatible-version coexistence, unknown blast radius, atau isolated technical validation yang eksplisit.

`Disposable Runtime` bukan pengganti `Main Runtime` untuk operational atau human acceptance. Runtime bukan authority source Repository; rekonsiliasikan source state, installed state, package provenance, dan evidence bila material.

#### `Gateway`

Gunakan hanya bila remote access masuk scope. Verifikasi target runtime dan route. Jangan expose service, mengubah konfigurasi firewall/network, atau mengubah credential tanpa authorization eksplisit.

#### `Prototyping`

Gunakan hanya bila pekerjaan visual/prototype masuk scope. Perlakukan output prototype sebagai reference sampai diterima. Jangan menginfer implementation authorization dari design artifact.

### Git

Terapkan hanya bila Git material dan diotorisasi:

- verifikasi repository, branch, HEAD, upstream, dan state tidak terduga;
- gunakan feature branch berumur pendek bila diperlukan;
- pertahankan perbedaan local/remote;
- lakukan commit/push/integrate/delete branch hanya bila diotorisasi secara eksplisit;
- jangan rebase, reset, clean, stash, force-push, atau rewrite history yang sudah diterima kecuali diotorisasi secara eksplisit;
- branch closure memerlukan verifikasi accepted-tip, containment/zero-ahead, remote, dan workspace sebelum deletion bila deletion diotorisasi.

Remote repository/branch tetap menjadi durable authority. Jangan memperlakukan commit yang belum dipush, runtime copy, atau disposable workspace sebagai checkpoint authoritative. Setelah push yang diotorisasi, verifikasi remote tip yang dihasilkan secara independen bila memungkinkan.

### Source of truth

Gunakan source berdasarkan fungsinya:

- `Rule` untuk boundary governance dan authorization;
- project instructions dan Authoritative Documentation untuk constraint `Project` yang sudah diterima;
- implementation untuk behavior yang diamati;
- test/evidence untuk behavior yang terbukti;
- section planning yang disebut hanya bila diwajibkan instruction ini;
- `Handoff` hanya melalui context minimum yang direproduksi di sini.

Jangan meminta atau merekonstruksi artifact continuity atau planning secara penuh.

Bila source yang material bertentangan, laporkan conflict tersebut dan berhenti sebelum perubahan substantif.

Jika konsistensi dokumentasi menjadi bagian task, inspeksi hanya dokumen authoritative yang disebutkan dan evidence yang terdampak langsung. Jangan melakukan rewrite dokumentasi luas hanya demi konsistensi.

### Progressive source reading

Baca dengan urutan ini dan perluas hanya bila evidence belum cukup:

1. project instructions;
2. target source;
3. direct dependencies;
4. test yang relevan;
5. dokumentasi atau contract yang relevan secara langsung;
6. source tambahan hanya bila dibutuhkan oleh dependency atau conflict yang teramati.

Jangan meminta `Technical Executor` merekonstruksi context yang tidak tersedia. Terjemahkan hanya execution delta yang dibutuhkan instruction ini.

### Planning-input boundary — internal GPT routing rule

Section ini adalah filter internal generation. Jangan mereproduksinya sebagai penjelasan governance di instruction yang digenerate. Render hanya dependency teknis atau invariant exact yang mengubah eksekusi.

Planning artifact adalah input, bukan implicit authorization.

- Jangan mengimplementasikan item hanya karena planning source menandainya `NEXT`, `ACTIVE`, atau `PROVISIONAL`.
- Jangan otomatis mempromosikan planning content menjadi authority repository, contract, atau roadmap.
- Jangan mengeksekusi item deferred atau future hanya karena item tersebut terlihat atau direferensikan.
- Bila planning context material, sebutkan target exact dan tujuan pembacaannya di instruction ini.
- Bila `Concept` material, identifikasi hanya canonical title, source, invariant relevan, provenance, dan technical dependency yang unresolved.
- Pertahankan identity Deferred Item yang stabil bila Deferred Item tersebut diotorisasi dan masuk scope.

Status Deferred Item seperti `Candidate`, `Unscheduled`, `future`, `KEEP DEFERRED`, `NOT APPLICABLE`, `REJECT`, atau `SUPERSEDE` tidak mengotorisasi eksekusi.

### Conditional documentation and technical-evidence boundaries

Section ini adalah filter internal generation. Di instruction yang digenerate, masukkan hanya operasi teknis yang berlaku dan kebutuhan evidence-nya.

Gunakan boundary ini hanya bila task secara eksplisit memasukkannya:

- Konsistensi dokumentasi: koreksi hanya wording current-state yang material stale atau contradictory terhadap evidence implementation yang diterima; pertahankan historical record dan hindari search-and-replace buta.
- Technical evidence: laporkan validation, kriteria yang mungkin membutuhkan manusia, kelayakan merge, blocker, dan final state Git/environment. Jangan membuat keputusan di luar technical slice yang disediakan.
- Branch lifecycle: lakukan closure atau deletion hanya bila diotorisasi secara eksplisit dan setelah evidence containment, zero-ahead, remote, dan clean-state terverifikasi.

### Minimum technical `Concept` continuity input

Bila continuity `Concept` material, masukkan hanya:

- identity dan source `Concept`;
- technical decision atau invariant yang diperlukan;
- revision/provenance yang relevan;
- technical dependency yang unresolved;
- tujuan pembacaan yang exact.

Jangan meminta atau menggunakan full `Concept` set, full `Handoff`, atau planning history yang tidak terkait.

## Execution Controls

### Execution gates

Gunakan hanya gate yang material terhadap task ini:

- Gate 1 — inspect/plan: target, starting state, dependency, dan authorization terverifikasi;
- Gate 2 — modify: implementation authorization dan precondition terverifikasi;
- Gate 3 — validate: pemeriksaan teknis relevan selesai atau limitation dicatat;
- Gate 4 — repository/external action: authorization terpisah dan review final state terverifikasi.

Jangan melewati gate sampai kondisinya terpenuhi. Selesainya satu gate tidak mengotorisasi gate berikutnya. Berhenti sebelum gate yang belum disetujui.

### Execution rules

- lakukan perubahan minimum yang cukup;
- pertahankan behavior dan state yang tidak terkait;
- jangan mengubah production, shared state, credential, external service, atau konfigurasi remote/network tanpa authorization eksplisit;
- jangan melakukan mutation `Repository` kecuali authorization tertulis mencakup mutation tersebut;
- jangan memperlakukan test failure sebagai izin untuk memperbaiki code atau fixture yang berdekatan;
- jangan membuat keputusan di luar technical slice yang disediakan.

### Validation

Jalankan validation yang paling dekat dengan target terlebih dahulu. Laporkan pemeriksaan exact dan bedakan:

- passed;
- failed;
- inspected only;
- not run;
- blocked;
- not applicable.

Petakan acceptance evidence ke kriteria teknis yang tertulis di instruction ini. Jangan mengklaim project acceptance.

Validation rules:

1. mulai dari pemeriksaan yang paling dekat dengan perubahan;
2. perluas hanya berdasarkan impact aktual;
3. jangan pernah mengklaim test yang tidak dijalankan;
4. bedakan `PASS`, `FAILED`, `INSPECTED ONLY`, `NOT RUN`, `BLOCKED`, dan `NOT APPLICABLE`;
5. review final diff dan perubahan tidak sengaja;
6. jangan mengulang suite yang sudah diterima tanpa alasan regression yang konkret;
7. pekerjaan yang hanya menyentuh dokumentasi tidak membutuhkan runtime regression kecuali review menemukan inkonsistensi behavior.

### Stop conditions

Berhenti dan laporkan bila:

- execution slice sudah selesai;
- terdapat conflict source/authority yang material;
- starting state drift terdeteksi;
- access, capability, dependency, atau approval yang dibutuhkan tidak tersedia;
- sebuah finding membutuhkan scope expansion;
- action berikutnya bersifat destructive, irreversible, external, atau memiliki gate terpisah;
- kelanjutan pekerjaan akan mengubah Work Unit berdekatan, boundary `Project`, atau artifact yang tidak terkait.

## Continuation

Untuk continuation dalam thread yang sama, bawa hanya target, anchor, authorization, scope, validation, stop condition, dan technical issue unresolved yang berubah. Jangan mengulang governance, environment, atau history yang tidak berubah. Ini adalah routing rule internal GPT; continuation yang digenerate hanya boleh memuat technical delta yang material.

Untuk continuation dalam thread yang sama dengan state yang sudah accepted, gunakan `CHECK-AND-RUN`: verifikasi hanya drift material pada target, authorization, anchor repository/branch, dependency, workspace, dan external state yang relevan. Jangan mengulang verification continuity yang setara. Jika ada drift material, evidence baru, conflict authority, atau boundary berubah, kembali ke full verification dan berhenti bila issue tidak dapat diselesaikan dengan aman.

Gunakan continuation token yang ringkas seperti:

`Continue the current task.`

`Changed target: [...]`

`Changed authorization: [...]`

`New stop condition: [...]`

`All other prior boundaries remain unchanged.`

GPT/user yang mengendalikan project mengevaluasi keputusan tersebut dari report ini.

Jika eksekusi berlanjut di thread teknis yang sama, kembalikan continuation token ringkas yang memuat execution slice, final technical state, technical issue unresolved, dan technical check berikutnya yang exact. Jangan gunakan token untuk mengotorisasi scope baru atau keputusan project.
