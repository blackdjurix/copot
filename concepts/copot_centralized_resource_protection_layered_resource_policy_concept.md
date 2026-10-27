# Centralized Resource Protection — Layered Resource Policy

Status: CONCEPT / PLANNING MATERIALIZED / NOT CONTRACT / NOT IMPLEMENTATION AUTHORITY

## 1. Purpose and boundary

COPOT memerlukan satu sumber definisi pembatasan operasi atas resource yang dapat dikonsumsi lintas capability, tanpa mengambil alih package provenance, installed-state evidence, ataupun ownership operasional masing-masing capability. Konsep ini membedakan *resource authority* dari *lifecycle evidence*. Fase pertama diterapkan secara terbatas untuk `PUBLIC_ROOT/.htaccess` dalam controlled deviation `CD-V3-HTACCESS-20261026-01`, bukan penerapan framework generik secara menyeluruh.

## 2. Nomenklatur

- **Centralized Resource Protection**: konsep arsitektur perlindungan resource bersama.
- **Layered Resource Policy**: model komposisi pembatasan.
- **Global Resource Policy**: pembatasan tertinggi pada level COPOT runtime yang tidak dapat dilonggarkan oleh local policy.
- **Scoped Resource Policy**: aturan milik module, feature, capability, atau subsystem, yang tunduk pada global restriction.
- **Policy Scope**: pihak/konteks yang menetapkan dan mengonsumsi policy.
- **Resource Target**: resource yang dikenai aturan; dapat berupa satu file spesifik, folder, atau tipe resource lainnya.
- **Effective Resource Policy**: hasil resolusi global dan scoped policy.
- **Resource Operation Decision**: keputusan terhadap operasi konkret; `ALLOW`, `DENY`, `UNSUPPORTED`, `INDETERMINATE` adalah kosakata konseptual dan belum status token kontrak.
- **Protected Resource Registry**: katalog deklarasi opsional, bukan kewajiban materialisasi class/service sejak fase pertama.

## 3. Policy, ownership, dan evidence

Hanya **policy** yang layered. Tidak ada layered ownership. Satu resource memiliki satu effective authoritative owner dalam konteks saat ini; ownership classification pada historical package/source inventory merupakan evidence historis, bukan competing active authority. `PACKAGE_OWNED` pada existing lifecycle evidence tidak otomatis memberikan izin mutation dan tidak perlu dihapus dari historical record.

Development Git/release provenance dipisahkan dari committed installed lifecycle evidence. Yang terakhir tetap diperlukan pada runtime instalasi terkait untuk `PATCH`, `REPAIR`, `UPDATE`, `UPGRADE`, reconciliation, dan recovery sesuai contract yang berlaku. Retention atau schema migration tidak diputuskan oleh Concept ini.

Policy scope dan resource target merupakan dua sumbu berbeda. Scoped policy bisa menargetkan satu file; kategori module/feature/capability/subsystem adalah scope provider, bukan pembatas granularitas target.

## 4. Composition and conflict semantics

Global restriction tidak dapat dioverride local/scoped policy. Scoped policy dapat memperketat aturan, bukan melonggarkan larangan global. Untuk resource tanpa global declaration, ketidakhadiran rule global berarti **tidak menambahkan restriction**, bukan memberikan authorization positif.

Unknown owner/policy, unsupported rule, maupun conflict tidak berubah menjadi implicit mutation permission. Conflict deklaratif/historical tanpa permintaan mutation menghasilkan diagnostic/notification dan tidak menghentikan seluruh development/runtime. Operasi yang secara aktual mensyaratkan mutation terlarang harus **ditolak sebelum filesystem mutation, recovery capture, atau installed-state advancement**. Package/plan yang mendeklarasikan prohibited mutation tidak admissible; jangan silent-skip lalu menganggap operasi berhasil. Deployment routing incompatibility pada package yang tidak meminta prohibited mutation adalah admission blocker terpisah, bukan otomatis package invalid.

## 5. First application: deployment-owned .htaccess

- Exact target: root identity `PUBLIC_ROOT` + canonical relative path `.htaccess`. Hindari blanket filename matching, wildcard, atau restriksi otomatis pada file lain.
- Effective owner: `DEPLOYMENT_ENVIRONMENT`.
- Global policy: `ABSOLUTE_IMMUTABILITY` **hanya dalam authority operasi COPOT**, tanpa OS ACL/read-only lock dan tanpa membatasi cPanel/server administrator atau independent deployment tools.
- COPOT boleh read, inspect, verify compatibility, report. COPOT tidak boleh create, write, overwrite, replace, rename sebagai source atau destination, remove, restore, atau rollback mutation terhadap file fisik ini.
- Perlindungan dibutuhkan pada manifest admission, inventory/removal/rename validation, source-relative planning, apply executor, recovery plan/domain/path guard, serta fresh-install/package-update routing compatibility yang relevan.
- Historical package claim tetap dapat dibaca sebagai evidence. Plan yang menggunakan claim tersebut untuk memutasi target terlindungi harus ditolak.
- Rejection harus terjadi sedini mungkin dan dikonfirmasi ulang pada final mutation boundary sebagai defense-in-depth; seluruh guard harus berbagi semantik yang sama.
- Notification conflict bukan pengganti guard. Operasi package yang invalid gagal secara atomik sebelum mutation, tetapi runtime dan operasi independen lainnya tidak harus diblokir.
- Fokus negative tests `HTA-N01`–`HTA-N07`, termasuk failure/interruption/rollback preservation dan SHA-256 byte identity pada isolated disposable fixtures.

## 6. Execution boundary and relationship to Alternative C

Bounded `.htaccess` protection termasuk dalam arah implementation-first controlled deviation `CD-V3-HTACCESS-20261026-01` yang tercatat di `workplan.md`. Contract yang masih authoritative tetap berlaku kecuali explicit controlled breach yang sudah direkam; implementasi dan test tidak otomatis mempromosikan contract baru. `R3-A E4` tetap BLOCKED hingga contract reconciliation, evidence, dan acceptance yang diwajibkan selesai.

Fase ini **tidak** mengotorisasi universal registry, multi-owner framework, dynamic policy UI, migration seluruh ownership/provenance model, rewrite historical evidence, unrelated runtime changes, release, deployment, atau advancement E4. Penggunaan shared predicate/service yang minimal diperbolehkan sebagai desain bounded enforcement, sementara bentuk final API/class dan file-change set diputuskan melalui technical validation sesuai scope yang berlaku.

## 7. Future architecture / deferred preparation

Rancang selective consumption lintas capability: masing-masing mengonsumsi policy checks dan feature yang relevan tanpa diwajibkan memakai semua hypothetical owner/policy categories. Mandatory global restrictions tetap berlaku untuk semua COPOT-controlled mutation entrypoints. Evaluasi policy composition, policy provider identity, declaration lifecycle, diagnostic observability, scope/target matching, versioning, conflict handling, performance, test matrices, serta compatibility adapters hanya pada workstream/contract berikutnya.

Full Centralized Resource Protection framework adalah planning-only future architecture, bukan dependency untuk menyelesaikan Alternative C. No layered ownership.

## 8. Open decisions and validation

- Exact shared API/predicate, integration boundaries, dan representasi runtime policy declaration belum dikunci.
- Penentuan exact negative-test fixtures, valid rejection stage, recovery interception, dan atomicity perlu technical evidence.
- Mapping existing package classifications dan authoritative installed-state formats tidak boleh diubah hanya demi naming baru.
- Stale planning yang menyebut future COPOT-managed directive mutation pada `.htaccess` harus direkonsiliasi dengan absolute immutability; compatibility verification tetap diperlukan, tetapi automatic directive editing oleh COPOT tidak diperbolehkan.
