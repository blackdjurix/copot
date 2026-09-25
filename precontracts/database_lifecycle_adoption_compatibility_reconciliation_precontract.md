# Database Lifecycle — Adoption Compatibility Reconciliation Pre-Contract

Pre-contract lifecycle: PROMOTED / HISTORICAL PROVENANCE
Target: Database Lifecycle — Adoption Compatibility Reconciliation
Promotion status: PROMOTED into `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md` and `docs/37_database_ownership_lifecycle_management_foundation_contract.md`
Implementation authorization: NONE
Database/schema/runtime mutation authorization: NONE
Release / tag / publication authorization: NONE

## 1. Purpose

Pre-Contract ini mendefinisikan candidate contract model untuk merekonsiliasi legacy exact-match Adoption semantics dengan target-relative, requirement-driven database compatibility.

Target model harus memungkinkan existing database menjadi compatible terhadap target Webcore/runtime tanpa mewajibkan exact target-schema equality, selama setiap missing target requirement dapat dipenuhi melalui path yang known, safe, authorized, ownership-bounded, deterministic, dan dapat diverifikasi ulang.

Pre-Contract ini tidak mengubah current authoritative contracts, tidak mengotorisasi database/schema/runtime mutation, dan tidak mengotorisasi implementation.

## 2. Problem Statement

Current authoritative state memiliki dua model yang belum sepenuhnya selaras.

Legacy Existing-Runtime Webcore Lifecycle Adoption model memperlakukan `Adopt Existing Installation` sebagai non-mutating exact-match operation dan memisahkan non-exact state ke `Reconcile Legacy Installation`.

Database Ownership & Lifecycle Management Foundation kemudian membangun compatibility, ownership, migration authority, historical baseline handling, dan authorized forward-transition machinery yang memungkinkan database compatibility ditentukan dari declared requirements dan available authorized path.

Target-relative planning model selanjutnya memperkenalkan arah bahwa:

- exact-match tidak perlu menjadi satu-satunya bentuk compatibility;
- compatible extra state dapat dipertahankan;
- missing target-required state dapat dipenuhi secara bounded;
- mutation tetap menggunakan existing lifecycle/migration authority;
- compatibility harus dibuktikan ulang setelah requirement-resolution.

Pre-Contract ini merekonsiliasi ketiga arah tersebut tanpa menghapus historical truth.

## 3. Governing Principle

Database compatibility adalah **target-relative**.

Target runtime/release mendeklarasikan requirement yang dibutuhkannya. Existing database dinilai terhadap requirement tersebut.

Compatibility tidak ditentukan dengan memaksa database menjadi byte-for-byte atau schema-for-schema identik terhadap satu canonical target snapshot.

Model dasarnya:

`Target Requirements`
→ inspect actual database state
→ preserve satisfied requirements
→ preserve compatible extra state
→ identify missing or conflicting requirements
→ resolve eligible gaps through authorized lifecycle machinery
→ re-prove compatibility
→ `Adoption Ready`
→ terminal `Adopt`

## 4. Definitions

### 4.1 Adoption

`Adoption` adalah broader compatibility-establishment workflow yang menilai apakah existing COPOT installation/database dapat digunakan oleh target runtime/release dan, bila eligible, mengarahkan requirement-resolution sampai compatibility dapat dibuktikan.

Adoption bukan lifecycle mutation engine.

### 4.2 Adopt

`Adopt` adalah terminal installation/finalization intent yang hanya dapat dilakukan setelah compatibility terhadap target telah positively proven.

`Adopt` bukan sinonim untuk Update, Upgrade, Repair, Reconciliation, atau migration.

### 4.3 Target Requirements

`Target Requirements` adalah requirement authoritative yang harus dipenuhi agar target runtime/release dapat menggunakan installation/database secara aman dan supported.

Requirement dapat mencakup schema capability, migration state, package compatibility, ownership state, dependency, historical baseline evidence, atau requirement lain yang secara authoritative dinyatakan oleh target.

### 4.4 Satisfied Requirement

Requirement target yang sudah terbukti dipenuhi oleh actual state.

Satisfied requirement tidak boleh dimutasi hanya demi menormalkan database terhadap target snapshot.

### 4.5 Compatible Extra State

State tambahan yang tidak diperlukan target tetapi dapat dipertahankan tanpa menciptakan ownership conflict, incompatibility, ambiguity, atau unsafe behavior.

Keberadaan compatible extra state tidak dengan sendirinya membuat database incompatible.

### 4.6 Requirement Gap

Target requirement yang belum terpenuhi tetapi actual state cukup dapat dibuktikan untuk menentukan bahwa requirement tersebut missing.

Requirement Gap berbeda dari unknown, ambiguous, contradictory, atau unprovable state.

### 4.7 Filler

`Filler` adalah bounded change atau transition outcome yang memenuhi satu atau lebih Requirement Gap.

Filler bukan lifecycle operation dan bukan authority.

Filler hanya boleh dieksekusi melalui lifecycle mechanism yang memang berwenang terhadap schema/state terkait.

### 4.8 Resolution Route

`Resolution Route` adalah authorized lifecycle path yang dapat menghasilkan Filler.

Satu Requirement Gap dapat memiliki nol, satu, atau lebih dari satu valid Resolution Route.

### 4.9 Adoption Readiness

`Adoption Readiness` berarti seluruh mandatory Target Requirements telah positively proven satisfied, tidak ada unresolved unsafe or unprovable state yang material, dan target dapat melanjutkan terminal Adopt.

### 4.10 Compatibility Re-Proof

Compatibility Re-Proof adalah full or appropriately bounded re-evaluation setelah requirement-resolution yang membuktikan bahwa resulting state memenuhi Target Requirements.

Successful mutation sendiri tidak membuktikan Adoption Readiness.

## 5. Fill-the-Hole Model

`Fill-the-Hole` adalah requirement-resolution principle.

Fill-the-Hole berarti:

- hanya requirement target yang terbukti missing yang menjadi candidate untuk resolution;
- requirement yang sudah satisfied tetap untouched;
- compatible extra state tetap dipertahankan;
- filler tidak boleh memperluas mutation melampaui requirement yang perlu dipenuhi;
- setiap filler harus menggunakan existing authorized lifecycle machinery;
- resulting compatibility harus dibuktikan ulang.

Fill-the-Hole bukan:

- schema normalization engine;
- generic database repair;
- generic convergence operation;
- replacement migration engine;
- global database upgrade mechanism;
- permission untuk arbitrary SQL.

Migration adalah salah satu possible Fill-the-Hole mechanism, bukan definisi Fill-the-Hole.

## 6. Compatibility Classification

Candidate compatibility evaluation minimal harus dapat membedakan:

### A. Already Compatible

Seluruh mandatory Target Requirements sudah satisfied.

Disposition:

`Adoption Ready`

Exact-match dapat termasuk dalam kategori ini sebagai fast path.

### B. Compatible With Resolvable Gaps

Satu atau lebih Requirement Gap terbukti ada dan seluruh gap memiliki valid Resolution Route.

Disposition:

`Resolution Required`

### C. Compatible Extra State

Target requirements satisfied dan additional state terbukti compatible.

Disposition:

`Adoption Ready`

Extra state tidak perlu dihapus hanya untuk mencapai target equivalence.

### D. Requires Pre-Adoption Lifecycle Transition

Gap atau prerequisite dapat diselesaikan, tetapi authorized path mensyaratkan lifecycle transition sebelum target Adoption dapat dilanjutkan.

Disposition:

`Pre-Adoption Lifecycle Transition Required`

### E. Unknown / Ambiguous / Unsafe / Unsupported

Actual state, requirement, ownership, migration path, source provenance, recovery condition, atau compatibility tidak dapat dibuktikan secara aman.

Disposition:

fail closed.

Exact machine-readable classification vocabulary tetap implementation/contract-promotion decision dan tidak dikunci oleh Pre-Contract ini.

## 7. Resolution Route Model

Pre-Contract mengakui lebih dari satu valid route menuju Adoption Readiness.

### Route A — Pre-Adoption Lifecycle Resolution

Existing runtime/installation menjalankan lifecycle operation yang memang berwenang, misalnya:

- Update;
- Upgrade;
- Repair;
- Retry;
- applicable Reconciliation;
- applicable owner-authorized migration transition.

Setelah lifecycle operation selesai, Adoption melakukan fresh compatibility evaluation terhadap target.

Contoh konseptual:

`v0.13.0 runtime + DB`
→ Update/Upgrade ke requirement-compatible state
→ resulting DB re-evaluated against v0.14.0 requirements
→ `Adoption Ready`
→ fresh v0.14.0 performs terminal Adopt

### Route B — Adoption-Orchestrated Requirement Resolution

Target Adoption workflow menemukan Requirement Gap lalu mengorkestrasi existing authorized lifecycle machinery untuk menjalankan applicable filler.

Adoption tidak mengeksekusi arbitrary schema mutation sendiri.

Conceptual flow:

`fresh target runtime`
→ Adoption evaluates DB
→ Requirement Gap identified
→ authorized lifecycle route resolved
→ existing lifecycle machinery executes filler
→ compatibility re-proof
→ `Adoption Ready`
→ terminal Adopt

### Route C — Composite Resolution

Satu Adoption target dapat secara konseptual memerlukan lebih dari satu bounded requirement-resolution step apabila:

- setiap step independently authorized;
- ordering deterministic;
- ownership boundaries preserved;
- transition path supported;
- recovery and interruption semantics remain valid;
- intermediate state tidak dianggap terminal-compatible tanpa proof.

Apakah Composite Resolution akan diizinkan sebagai satu orchestrated operation atau dipecah menjadi beberapa explicit lifecycle operations tetap open decision.

## 8. Route Eligibility

Resolution Route eligibility harus ditentukan dari evidence, bukan convenience.

Material inputs dapat mencakup:

- source package/runtime state;
- target package/runtime requirements;
- installation identity;
- database namespace;
- owner identity;
- schema/migration state;
- historical-baseline classification;
- applicable migration path;
- package compatibility;
- recovery readiness;
- quiescence requirements;
- runtime-specific capability;
- current lifecycle-operation state;
- prior failed/incomplete operation;
- dependency ordering;
- safety and determinism.

Tidak semua Fill-the-Hole boleh dilakukan dari fresh target runtime.

Jika authorized lifecycle path membutuhkan source runtime/package context, maka Adoption harus route ke Pre-Adoption Lifecycle Resolution daripada mencoba mereplikasi authority tersebut.

## 9. Resolution Authority

Filler tidak memiliki independent mutation authority.

Authority tetap mengikuti existing ownership and lifecycle model.

### Webcore-Owned State

Mutation hanya melalui authorized Webcore lifecycle/migration machinery.

### Module-Owned State

Mutation hanya melalui lifecycle/migration authority Module pemilik.

### Authorized Extensions

Mutation mengikuti explicit owner-authorized extension grant dan existing lifecycle authorization.

### Cross-Owner State

Tidak boleh dimutasi berdasarkan convenience Adoption.

Ownership tetap menentukan siapa yang boleh mengubah apa.

Lifecycle machinery tetap menentukan bagaimana authorized transition dijalankan.

Adoption hanya boleh evaluate, plan/orchestrate, consume result, dan re-prove compatibility.

## 10. Exact-Match Fast Path

Exact-match tetap valid tetapi tidak lagi menjadi universal compatibility requirement.

Jika target requirements, identity, ownership, health, migration state, dan applicable proof gates sudah satisfied tanpa filler:

`inspect`
→ compatibility proven
→ `Adoption Ready`
→ terminal Adopt

Exact-match menjadi optimized no-resolution path, bukan satu-satunya allowed Adoption path.

## 11. Adopt Boundary

Terminal Adopt tetap harus sempit.

Adopt:

- memakai satu positively proven compatible installation;
- mempertahankan proven installation identity dan namespace;
- tidak menyamarkan Update, Upgrade, Repair, atau migration sebagai Adopt;
- tidak membuat Administrator atau Site state baru ketika existing state harus dipertahankan;
- tidak melakukan arbitrary schema provisioning;
- tidak menjadi generic lifecycle mutation boundary;
- dilakukan hanya setelah Adoption Readiness terbukti.

Requirement-resolution yang terjadi dalam broader Adoption workflow harus tetap dicatat sebagai operation milik lifecycle authority sebenarnya.

## 12. Update / Upgrade / Repair Relationship

Operation identity tidak boleh hilang hanya karena operation tersebut dipakai untuk mencapai Adoption Readiness.

Jika database menjadi compatible karena Update:

`Update` tetap Update.

Jika karena Upgrade:

`Upgrade` tetap Upgrade.

Jika karena Repair/Retry:

operation tersebut tetap Repair/Retry.

Adoption boleh:

- membutuhkan operation tersebut sebelum continuation;
- mengorkestrasinya ketika authority dan execution context memungkinkan;
- mengonsumsi hasilnya.

Adoption tidak boleh relabel operation tersebut sebagai generic Adoption mutation.

## 13. Legacy Reconciliation Relationship

`Reconcile Legacy Installation` tidak otomatis dihapus atau diubah menjadi Fill-the-Hole.

Legacy Reconciliation tetap dapat memiliki fungsi untuk state yang materially melibatkan:

- uncommitted legacy runtime;
- package-owned filesystem divergence;
- historical provenance reconstruction boundary yang telah accepted;
- missing committed lifecycle state;
- legacy recovery/finalization requirements;
- broader runtime/package convergence yang bukan sekadar database Requirement Gap.

Namun:

database non-exactness sendiri tidak lagi cukup untuk otomatis mengklasifikasikan installation sebagai Legacy Reconciliation case.

Jika state dapat positively classified, compatible extras dapat dipertahankan, dan missing Target Requirements memiliki authorized Resolution Route, target-relative Adoption path dapat tetap valid.

Unknown or unprovable legacy state tetap fail closed.

## 14. Historical Baseline and Provenance

Historical validity harus dipertahankan.

Historically valid aggregate-provisioned state tidak boleh dianggap invalid hanya karena newer owner-aware model lebih precise.

Historical pre-provisioning adalah compatibility/provenance evidence, bukan ownership authority.

Pre-Contract tidak mengizinkan database rebuild hanya untuk modernisasi metadata atau normalization.

## 15. Release and Version Relationship

Tidak ada global `DB_VERSION`.

Database compatibility tidak boleh direduksi menjadi:

`database version >= target version`

atau arithmetic equivalent lain.

Target compatibility berasal dari:

- target-declared requirements;
- source-state evidence;
- available authorized transition path;
- ownership;
- migration/schema state;
- relevant release/package compatibility evidence.

Bridge release/version diperlukan hanya ketika authoritative release evidence membuktikan direct transition tidak supported atau tidak sufficient.

## 16. Forward-Only Boundary

Existing forward-only lifecycle rule tetap berlaku.

Pre-Contract tidak memperkenalkan:

- generic downgrade;
- reverse migration;
- destructive rollback as migration semantics;
- database rewind untuk mencapai older target.

Recovery/restore tetap mengikuti accepted Backup & Recovery authority dan tidak menjadi reverse migration.

## 17. Recovery and Mutation Safety

Setiap Resolution Route yang melakukan mutation harus mempertahankan applicable:

- recovery preparation;
- immutable operation/plan identity;
- quiescence;
- lifecycle mutex/exclusion;
- ownership authorization;
- retry semantics;
- interruption handling;
- postcondition verification.

Broader Adoption orchestration tidak boleh bypass safety gate hanya karena target akhirnya adalah Adopt.

## 18. Compatibility Re-Proof Gate

Setelah requirement-resolution:

1. actual resulting state di-inspect ulang;
2. Target Requirements dievaluasi ulang;
3. all mandatory requirements harus positively proven;
4. ownership/provenance contradictions harus absent atau otherwise proven safe;
5. unresolved lifecycle/recovery-required state harus absent;
6. target compatibility harus PASS.

Hanya setelah gate ini boleh dihasilkan Adoption Readiness.

Successful filler execution tanpa successful re-proof tidak menghasilkan Adoption Readiness.

## 19. Failure Semantics

Pre-Contract membedakan setidaknya conceptual failure families:

### Evaluation Failure

Target requirement atau actual state tidak dapat dinilai secara deterministik.

### Resolution Planning Failure

Gap diketahui tetapi tidak ada safe authorized route.

### Resolution Execution Failure

Authorized lifecycle operation gagal atau incomplete.

### Recovery-Required State

Mutation outcome memerlukan restore/recovery sebelum continuation.

### Compatibility Re-Proof Failure

Resolution selesai secara teknis tetapi resulting state belum memenuhi target.

### Terminal Adopt Failure

Compatibility sudah proven tetapi final Adopt/finalization gagal.

Failure family tidak harus menjadi persistent status family baru. Exact runtime representation tetap implementation decision.

## 20. Source Runtime and Target Runtime

Pre-Contract mengakui setidaknya dua execution contexts:

### Source-Runtime-Led Resolution

Existing runtime melakukan lifecycle transition sebelum target Adoption.

### Target-Runtime-Led Orchestration

Fresh/new target runtime memulai Adoption dan mengorkestrasi authorized requirement-resolution.

Target-runtime-led orchestration hanya valid apabila target environment dapat menggunakan existing lifecycle authority secara aman tanpa menciptakan parallel authority.

Pre-Contract tidak menggeneralisasi:

- Runtime Handoff;
- detach;
- runtime replacement;
- downgrade;
- rollback;
- authority transfer;

kecuali evidence menunjukkan concern tersebut materially diperlukan oleh Adoption Compatibility.

## 21. Installer Relationship

Installer Refinement I adalah downstream consumer dari reconciled Adoption model.

Current Pre-Contract hanya menyediakan semantics yang nanti dapat digunakan Installer untuk:

- discover candidate installation;
- inspect Adoption compatibility;
- expose satisfied/unmet target requirements;
- identify applicable Resolution Route;
- orchestrate allowed route;
- require compatibility re-proof;
- perform terminal Adopt after readiness.

Detailed Installer UX, phases, forms, wording, review screen, and result behavior tetap downstream.

## 22. Database Operator Projection

Future Database projection dapat menampilkan:

- target requirements;
- actual compatibility;
- satisfied requirements;
- unresolved Requirement Gaps;
- compatible extras;
- available Resolution Route;
- blockers;
- sanitized fail-closed reason;
- Adoption Readiness;
- next valid operator action.

Projection tetap bukan migration authority atau arbitrary SQL surface.

## 23. Fail-Closed Conditions

At minimum, Adoption compatibility fails closed ketika ditemukan:

- unknown target requirement;
- unknown or ambiguous database state;
- unsupported historical state;
- contradictory installation identity;
- ambiguous namespace ownership;
- unknown schema owner;
- missing required authorization;
- unsupported migration path;
- unsafe cross-owner mutation;
- unresolved prior lifecycle operation;
- unresolved recovery-required state;
- required quiescence unavailable;
- requirement-resolution result cannot be verified;
- compatibility re-proof fails;
- target compatibility cannot otherwise be positively proven.

## 24. Contract Reconciliation Strategy

Promotion target harus preserve historical truth.

`docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md` tetap authoritative record untuk historical Existing-Runtime Adoption and Legacy Reconciliation delivery.

Reconciliation tidak boleh menulis ulang sejarah seolah exact-match semantics tidak pernah berlaku.

Promoted contract work nantinya harus menentukan:

- bagian `docs/30` yang tetap historical/current within its original boundary;
- bagian yang perlu explicit amendment atau superseding clarification;
- amendment terhadap current Adopt wording di `docs/37`;
- relationship terhadap `docs/28` lifecycle machinery;
- target-relative compatibility rule yang menjadi current model ke depan.

## 25. Explicit Non-Goals

Pre-Contract ini tidak membuat:

- new migration engine;
- new database lifecycle engine;
- global `DB_VERSION`;
- generic Convergence operation;
- generic `Update Database`;
- new Installer intent;
- new durable Adoption status family;
- automatic destructive normalization;
- generic conflict-resolution procedure;
- downgrade;
- reverse migration;
- Bundled Module refinement;
- Installer Refinement I implementation;
- Installer Refinement II implementation;
- release/tag/publication authority.

## 26. Open Decisions Before Contract Promotion

Items berikut belum dikunci:

1. apakah Resolution Route selalu planner-selected atau operator dapat memilih ketika multiple routes equally valid;
2. exact eligibility target-runtime-led Route B;
3. apakah Composite Resolution boleh berjalan sebagai satu orchestrated Adoption workflow;
4. exact durable/runtime vocabulary untuk Adoption Readiness;
5. exact boundary antara database Requirement Gap dan `Reconcile Legacy Installation`;
6. continuation semantics setelah Route B failure;
7. apakah requirement-resolution plan perlu durable standalone identity atau cukup menggunakan identity dari underlying lifecycle operations;
8. extent target requirement declaration yang harus berada di package contract versus derived authoritative capability metadata.

Items ini harus diselesaikan sebelum authoritative contract promotion apabila materially menentukan implementation contract.

## 27. Promotion Gate

Pre-Contract dapat dianggap ready untuk contract promotion hanya jika:

- target-relative compatibility semantics coherent;
- Adoption versus Adopt boundary coherent;
- Fill-the-Hole authority coherent;
- alternative Resolution Route model coherent;
- exact-match fast path preserved;
- Legacy Reconciliation boundary sufficiently resolved;
- existing lifecycle/migration authority preserved;
- recovery/failure semantics coherent;
- no parallel lifecycle/migration authority introduced;
- material open decisions resolved or explicitly proven safe to defer;
- resulting model dapat direkonsiliasi secara konsisten terhadap `docs/28`, `docs/30`, dan `docs/37`.

Contract promotion tetap separate authorization gate.

## 28. Implementation Boundary

Pre-Contract completion tidak mengotorisasi implementation.

Setelah contract model accepted dan authoritative reconciliation dipromosikan, detailed Work Unit topology baru boleh disusun berdasarkan actual delta antara:

- accepted target semantics;
- current implementation;
- current tests;
- current Authoritative Documentation.

No Work Unit count is implied by this Pre-Contract.

## Authoritative Sources to Reconcile Against

Before writing, verify the approved materialized content remains representable as a planning proposal against:

- `workplan.md`
- `docs/28_package_lifecycle_migration_foundation_contract.md`
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`
- `concepts/copot_site_settings_future_capability_architecture_concept.md`

These sources constrain the Pre-contract but must not be modified by this task.

If the approved text creates a material contradiction that cannot truthfully remain as a pre-promotion proposal, stop and report the exact conflict rather than silently editing the approved semantics.
