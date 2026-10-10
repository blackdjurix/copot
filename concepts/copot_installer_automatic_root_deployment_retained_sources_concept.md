# Installer Distribution, Automatic Root Deployment & Retained Sources
Date version: 2026-10-10 15:13:36 WIB

Status: DEFERRED / THREAD-LEVEL SAVED CONCEPT MATERIALIZED / NOT PROMOTED
Project: COPOT
Provenance: User/GPT conceptual reconciliation, 2026-10-10; conversation TLC-08–TLC-09.

## Purpose
Make fresh installation less manually demanding while preserving separation of private APP_ROOT and web-serving PUBLIC_ROOT. This is primarily an *installer distribution/deployment architecture*, not merely a repair archive feature.

## Proposed distribution and installation
- Example concept: installer package containing `root.zip` (private application payload), `install.php` (installer entry), and a `public/` payload or wrapper. Exact names/layout not locked.
- Installer deploys root archive content to APP_ROOT and public assets/entry points to the selected document root (e.g. www/public_html); coordinates supported routing or `.htaccess`/deployment configuration rather than requiring unexplained manual user editing.
- Distinguish APP_ROOT, PUBLIC_ROOT, and deployment-specific mapping; avoid exposing private application and retained sources via web server.
- Fresh-install payload may include canonical complete schema and bundled modules where governed by their own package ownership.

## Retained sources and first aid
- Preserve verified installation and subsequent update/upgrade/patch packages so Lifecycle and System Health can, when separately authorized, locate the appropriate known source for diagnosis/repair.
- A retained ZIP is not a runtime-state backup. Keep package provenance and immutable identities separate from live snapshots, database backups, operation journals and recovery checkpoints.
- `APP_ROOT/lifecycle/` is a **candidate logical protected namespace** for packages, snapshots, operations and recovery; `APP_ROOT/repair/` was an earlier illustration. Neither physical path is accepted.
- Before repair, prove source/release/file identity, applied patch history, destination ownership and expected result. Do not automatically repair or rollback on a generic System Health finding.
- Evaluate permissions, disk usage, expiration/retention, private storage, encryption if needed, interaction with existing Backup & Recovery and System Health provider ownership.

## Boundary
DEFERRED, OUT OF CURRENT WU5 and corrective bootstrap implementation scope. Related references: MR.1 Installation Refinement, Webcore Deployment & Portability, Package Lifecycle, `docs/31_backup_recovery_foundation_contract.md`, `concepts/copot_system_health_status_concept.md`. No contract amendment or implementation authority from materialization.
