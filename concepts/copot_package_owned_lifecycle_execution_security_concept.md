# COPOT — Package-Owned Lifecycle Execution & Zero-Trust Package Security Concept

Status: EXPLORATORY / PLANNING ONLY / NOT ADOPTED AS IMPLEMENTATION ARCHITECTURE
Scope: Webcore, Module/Extension, Theme
Source: User discussion, 2026-11-19 (conceptual direction and unresolved design questions)

## 1. Tujuan dan status

Mengevaluasi arsitektur Package Lifecycle di mana setiap package membawa manifest, payload, tools, dependencies, dan executable lifecycle engine untuk mengorkestrasi backup readiness, mutation, verification, dan finalization. Webcore tetap menyediakan Package Lifecycle Handler untuk admission/compatibility serta Guard, Backup & Recovery (BnR), trusted recovery, dan committed-state authority.

Konsep ini bukan contract amendment, implementation instruction, acceptance, ataupun authorization. Current Alternative C / C3-A F1 dan scoped deviations tetap berjalan sesuai authorization sebelumnya. Naming reconciliation (termasuk `awaiting_wu6`, `v1/v2/v3`) tetap DEFERRED.

## 2. User-defined conceptual direction

- Package adalah operator lifecycle setelah bounded Webcore authorization, termasuk direct filesystem/database mutations melalui executable engine milik package.
- Model berlaku lintas Webcore, Module/Extension, dan Theme Package Lifecycle, dengan ownership dan resource policy spesifik domain. Tidak berarti semua domain memperoleh privilege yang sama.
- Webcore mempertahankan Package Lifecycle Handler untuk package intake, identity, compatibility, manifest/operation-plan inspection, leakage detection, scope authorization, dan admission rejection.
- Handler menolak package yang memerlukan protocol/handler capability di atas versi yang didukung. Upgrade handler dilakukan lebih dulu melalui package yang masih kompatibel dengan handler lama. Handler yang perlu dimutasi harus dapat diganti tanpa menghilangkan bootstrap/finalization/recovery authority.
- Package mengorkestrasi permintaan Guard dan BnR, memverifikasi backup readiness, menjalankan authorized mutation, melakukan verification, lalu meminta finalization. Guard, BnR, recovery evidence, dan committed-state authority tetap Webcore-owned.
- Jika executable package crash/interrupted atau finalization gagal, Webcore/BnR mengambil alih recovery berdasarkan durable independent evidence dan exact operation scope, tanpa menggantungkan pemulihan pada executable package yang gagal. Unprovable recovery harus fail-closed.
- Canonical development source saat ini `https://github.com/blackdjurix/copot.git`; project belum deployment. Publisher ecosystem, multi-publisher trust administration, dan distribution policy DEFERRED. Single repository source tidak memberikan trust atau execution permission otomatis.
- Tidak ada operator override untuk failed security inspection. Package `REJECTED` tidak boleh dieksekusi; package `UNVERIFIED` juga tidak boleh masuk execution admission. Package/policy yang bermasalah harus diperbaiki secara sah dan diperiksa ulang sebelum admission. Pemisahan verdict `PASS`, `REJECTED`, `UNVERIFIED` masih candidate taxonomy, bukan implemented status schema.

## 3. Security dan System Health: target eksplorasi

Security inspection diharapkan meliputi:
1. Archive, format, dan artifact inspection: hidden files, unknown extensions/format, nested archives, traversal, symlink/special files, disguised executable, payload anomalies, dan exact byte identities.
2. Static/source-level inspection hingga file/code-line evidence saat memungkinkan: destructive code, unauthorized data access, information exfiltration, dynamic execution, obfuscation, secret exposure, dan mismatch antara manifest/plan dengan executable behavior.
3. Dynamic/behavior analysis dalam isolated environment: filesystem, database, process, network, dan capability usage.
4. Scoped authorization atas installation, package, executable/dependency identity, action plan, operation/attempt/recovery identity, resource ownership, privilege, serta time-of-use revalidation.
5. Runtime enforcement dan independent System Health verification; monitor dan tolak unauthorized behavior, lanjutkan recovery secara aman saat applicable.

Static scanner, dynamic scanner, archive integrity, atau signature tidak pernah dianggap jaminan bahwa executable aman. `PASS` berarti syarat evaluasi yang berlaku terpenuhi, bukan bukti tidak ada malicious behavior. Hidden/unknown code yang tidak dapat dinilai aman tetap blocked, bukan dibypass.

## 4. Trust dan execution boundary unresolved

- Direct package-owned filesystem/database writes menimbulkan risiko bypass terhadap manifest-only authorization apabila worker memiliki privilege PHP/Webcore yang sama. Enforcement harus nyata di OS/process/filesystem/database/network, termasuk read access dan exfiltration, bukan hanya PHP API/token policy.
- Evaluasi kesesuaian mekanisme isolasi terhadap Windows/XAMPP dan deployment target di masa depan; belum ada pilihan implementasi.
- Definisikan versioned minimum handler/worker protocol, safe handler self-update bootstrap, protected trusted components, independent recovery handoff, and commit-last verification.
- BnR tidak dapat membatalkan informasi yang sudah bocor; prevention, containment, dan leak detection penting secara independen.
- Domain-specific ownership untuk Webcore, Module/Extension, dan Theme harus diteliti sebelum perubahan shared lifecycle contract.
- Klasifikasi severity, false-positive adjudication, scanner/policy update pathway, dan unknown-format treatment masih butuh desain. Memperbaiki rule atau package lalu re-evaluate bukan operator override terhadap rejection.

## 5. Hubungan dengan current accepted architecture

Existing `concepts/copot_v3_simplification_foundation_concept.md` menyatakan "Package declares WHAT; Lifecycle determines eligibility and HOW"; konsep ini mengeksplorasi perpindahan execution HOW ke package, sementara Webcore tetap memvalidasi eligibility, mengotorisasi scope, mengendalikan Guard/BnR, dan memverifikasi committed state.

`docs/28_package_lifecycle_migration_foundation_contract.md` mempertahankan shared lifecycle pipeline, Webcore-owned Core migrations, protected ownership, dan commit-last. `docs/31_backup_recovery_foundation_contract.md` mempertahankan independent BnR ownership. Semua contract tetap berlaku sampai reconciliation/promotion yang terpisah secara eksplisit.

External C2 executor untuk C3-A F1 hanya merupakan evidence bahwa execution source bisa terpisah dari installed Webcore engine; itu bukan bukti package sandboxing atau acceptance arsitektur baru.

## 6. Disposition dan future evaluation

Status: EXPLORATORY / DEFERRED FOR ARCHITECTURAL EVALUATION AFTER CURRENT PACKAGE LIFECYCLE WORK. Jangan mengubah source, runtime, manifest contract, handler, package, tests, operational F1 gate, atau publisher policy berdasarkan file ini.

Future evaluation harus membuktikan feasibility direct writes dengan enforced containment, secure bootstrap/self-update, failure takeover by Webcore BnR, consistent ownership bagi semua package types, dan compatibility migration. User approval diperlukan sebelum conceptual adoption, pre-contract promotion, atau implementation.
