# COPOT — Legacy-to-V3 Architecture Injection Pre-contract
Date version: 2026-10-17 18:32:48 WIB

Status: PROVISIONAL / GPT SEMANTIC DRAFT / NOT PROMOTED
Scope: Package Lifecycle v3 bootstrap pada separated disposable runtime `85eb6a4c`.
Purpose: Bounded transition existing Contract-v1 installation menuju operational Contract-v3 capability, tanpa mengganti foundational Webcore secara menyeluruh ataupun memalsukan committed lifecycle state.

## 1. Authoritative dependency

Pre-contract ini tunduk pada current accepted authority:
- `docs/28_package_lifecycle_migration_foundation_contract.md`
- `docs/31_backup_recovery_foundation_contract.md`
- `docs/canonical_target_inventory_fingerprint_contract.md`
- `docs/shared_destructive_plan_confirmation_operator_input_contract.md`

Existing normative v3 behavior tetap berlaku; pre-contract ini mendefinisikan bootstrap-specific gaps dan tidak mengamendemen Contract secara otomatis.

## 2. Target, references, dan installation boundary

- Primary mutation testbed: `85eb6a4c`.
- Implementation reference: `97e03b5-after`; reference bukan mutation target.
- Main Runtime / acceptance anchor: `dev`; di luar seluruh mutation scope ini.
- Tailscale gateway identity: `copot`; remote access routing saja, bukan installation identity, APP_ROOT, PUBLIC_ROOT, atau database namespace.
- User mengonfirmasi ketiga folder runtime telah di-rename. Actual filesystem paths, URL deployment base paths, database associations, installation identities, dan health setelah rename tetap harus diverifikasi secara independen.

Native XAMPP configuration frozen / untouched. Tidak ada isolated MariaDB instance maupun perubahan konfigurasi XAMPP. Target tidak boleh mengadopsi file, metadata, atau backup dari runtime reference atau `dev`.

## 3. Pre-mutation eligibility

Controlled injection hanya eligible apabila:
1. Actual target installation identity, APP_ROOT, PUBLIC_ROOT, database namespace, installed Webcore version, serta committed state terverifikasi.
2. Source dan direct dependencies target dapat diinspeksi; existing active lifecycle/recovery state tidak memiliki unresolved mutation yang membahayakan.
3. Target-specific recoverable and consistent backups atas APP_ROOT, PUBLIC_ROOT, logical database, serta relevant installation/lifecycle evidence diverifikasi sebelum mutation.
4. Exact injection inventory dan existing-file collision dispositions ditetapkan berdasarkan physical path, hash, ownership, provenance, dan compatibility evidence.
5. Failure handling, manual restoration boundary, dan verifikasi latest-good state tersedia.

Jika salah satu eligibility gate tidak terpenuhi, mutation blocked.

## 4. Controlled architecture injection dan collision disposition

Exact mutation inventory mengklasifikasikan physical destination:
- `ADD`: file destination belum ada; delivery sah dan dependency verified.
- `PRESERVE`: existing bytes tidak diubah; compatibility serta authority tetap harus dibuktikan.
- `REPLACE`: overwrite spesifik dengan source evidence, original bytes recoverable, dan authorization yang sesuai.
- `BLOCK`: collision, ownership, integrity, atau compatibility tidak memenuhi syarat.
- `UNVERIFIED`: evidence belum cukup; tidak boleh dijalankan sebagai mutation.

Disposition physical collision bukan pengganti canonical v3 manifest coverage. Full target-inventory coverage tetap memerlukan complete delivery of resulting package-owned bytes sebagaimana canonical contract; existing files tidak otomatis menjadi package-owned, dan `PRESERVE` bukan pengecualian implisit terhadap coverage. Jangan melakukan indiscriminate filesystem replacement atau silent cleanup.

Foundational Webcore dipertahankan kecuali direct verified dependency mengharuskan perubahan spesifik.

## 5. Initial source ownership dan committed authority

Initial authoritative Contract-v3 source inventory hanya boleh dibangun dari complete full-v3 target verification dan successful canonical finalization yang memenuhi current authoritative contracts. Unknown legacy paths dan operator/runtime-owned files tidak diadopsi hanya berdasarkan directory location. Published source-inventory artifact tanpa committed-state reference tetap non-authoritative.

## 6. Bootstrap operation dan finalization

Bootstrap harus memiliki truthful, durable operation/installation/source identity dan memenuhi lifecycle, recovery, inventory, database/migration, health, serta committed-state consistency gates. Existing Package Lifecycle dan Backup & Recovery machinery digunakan kembali sejauh terbukti kompatibel; tidak membuat lifecycle/recovery engine atau synthetic committed state kedua.

Finalized Contract v3 dan source-inventory reference tidak boleh diklaim sebelum seluruh applicable checks PASS. Interruption dan incomplete finalization mempertahankan truthful operation/recovery state sesuai existing authority.

## 7. Failure dan target-specific manual restore

Jika injection gagal, hentikan operasi dan simpan truthful evidence. User memilih manual rollback dari verified backup milik `85eb6a4c` itu sendiri, bukan milik `97e03b5-after` atau `dev`. Restore mencakup affected APP_ROOT, PUBLIC_ROOT, logical database, dan state relevan. Jangan mengklaim `RESTORED` tanpa actual canonical restore dan independent applicable-domain verification. Manual fallback untuk testbed tidak menghapus ordinary v3 recovery obligations.

## 8. Minimum maintenance protection

Architecture injection mensyaratkan write protection pada target installation selama protected mutation. Minimum Installation-Scoped Maintenance Guard merupakan technical/contract reconciliation terpisah, bukan new maintenance management system. Pertahankan existing recovery capture dan conditional DatabaseQuiescenceCapability/MariaDbReadOnlyQuiescence; installation-scoped application write protection tidak dengan sendirinya memerlukan global DB quiescence, tetapi tidak boleh melemahkan database consistency requirements.

## 9. Independent acceptance gates

1. `CONTRACT ACCEPTANCE`: amendment normative disetujui/dipromosikan melalui jalur acceptance yang berlaku.
2. `PRE-MUTATION READINESS`: runtime identity, recovery, source/dependency, ownership, dan collision proof lulus.
3. `OPERATIONAL ACCEPTANCE`: injection, authentic finalization, health, failure/recovery, dan applicable lifecycle validation terbukti setelah mutation.

Lulus satu gate tidak otomatis mengotorisasi atau meluluskan gate lain.

## 10. Excluded / deferred

- Mutation `dev` atau `97e03b5-after`.
- XAMPP / Apache / MariaDB configuration changes dan dedicated database server.
- Full Maintenance Management, future queues, scheduling, media backup portability.
- Release, checkpoint promotion, annotated tag, package publication, deployment.
- Arbitrary legacy cleanup/ownership adoption, synthetic lifecycle state.
- Redefinition historical Contract-v1/v2 semantics.

## 11. Unresolved technical review targets

- Exact Contract-v1 to v3 bootstrap operation composition.
- Actual dependency closure dan file collision pada `85eb6a4c`.
- Minimum target write-path protection and drain semantics.
- Original-byte evidence dan interruption handling.
- Compatibility of existing v3 finalization and cleanup paths.
- Correct source-inventory coverage and identity.

Disposition: SEMANTIC PRE-CONTRACT MATERIALIZED / TECHNICAL REVIEW PENDING / NOT PROMOTED / NO RUNTIME MUTATION.
