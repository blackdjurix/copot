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


## Installer-managed private Recovery Root (future target)
- The normal supported fresh-installation flow provisions and configures a dedicated, private, durable Recovery Root automatically. A user should not normally need to create a directory in File Manager, supply `COPOT_RECOVERY_ROOT` manually, or navigate a mandatory filesystem-path picker.
- The Recovery Root is installation-managed infrastructure, not a directory inside APP_ROOT or PUBLIC_ROOT. It must remain outside every HTTP-served document root and live project tree, with canonical path, containment, safe permission, writable, and symlink/reparse checks. Preserve existing Backup & Recovery ownership and isolation of operation recovery artifacts.
- The installer discovers or requests an acceptable private location, validates that deployment has creation authority, provisions it using bounded permissions, then persists the resolved configuration for the installation. Never silently fall back to `public_html`, another HTTP-accessible directory, arbitrary temporary storage, or a shared existing recovery namespace.
- When hosting policy prevents automatic directory creation, provide a guided **manual fallback**: explain the required private location, allow the user or hosting operator to create the folder, then verify its canonical path, safety, and writability before continuing. If no safe private location can be established, stop installation with an actionable error; do not weaken containment or silently proceed without recoverability.
- Current implementation uses explicit deployment configuration (for example `COPOT_RECOVERY_ROOT`) and an already-existing writable directory. This is acceptable for the present C1 SDR validation and is not modified by this concept.

## Original installation ZIP retention and repair provenance
- Preserve the **original uploaded/distributed installation ZIP**, byte-for-byte, after successful installation, in a protected retained-source area of installation-managed private recovery storage. Do not substitute a ZIP rebuilt from the installed APP_ROOT/PUBLIC_ROOT tree.
- Record immutable original archive identity (including SHA-256, size, release/manifest linkage and installation association) and verify it on retention and before any later use. Define atomic publication, retention permissions, capacity and failure handling before implementation.
- Separate retained distribution ZIPs (initial install and later update/patch packages) from operational recovery sets, live-tree snapshots, database backups, and lifecycle journals. A retained ZIP is a source for **future authorized repair**, not proof of runtime/database state or permission to overwrite current files.
- Repair selection must consider current committed source, intervening updates/patches, dependency and ownership evidence; no automatic repair on a generic health failure.

## Initial deployment provisioning and `.htaccess` authority (unresolved)
- Future installer target: deploy APP_ROOT and PUBLIC_ROOT automatically and establish working routing without mandatory unexplained manual `.htaccess` edits on supported hosting; provide guided operator setup/verification where web-server configuration cannot be changed programmatically.
- Two options remain **open for explicit reconciliation**, with neither adopted here: (1) an explicitly bounded initial deployment provisioning phase with a separately defined exception before installation scope is committed; (2) a strict, policy-scoped Maintenance Guard authorization determining when deployment resource mutation is allowed.
- Current physical `PUBLIC_ROOT/.htaccess` remains `DEPLOYMENT_ENVIRONMENT`-owned with COPOT-scope `ABSOLUTE_IMMUTABILITY`. Neither option permits Package Lifecycle update, ordinary repair, or an active Guard alone to modify it. The actual initial provisioning authority, phase boundaries, fail-closed routing checks, and relevant contract deviation must be reviewed before implementation.

## Planning dependencies and decision status
- Automatic root deployment, installer-managed recovery storage, original-ZIP retention, repair-readiness and shared-hosting manual fallback are accepted future **requirements**, not acceptance of an implementation or promotion of this Concept.
- The precise distributable package layout, safe host-location discovery, initial routing authority, interaction with Backup & Recovery and System Health, and lifecycle repair semantics remain design/contract work. Keep the current C1 fresh-install and File Cycle boundary unchanged.

## Boundary
DEFERRED, OUT OF CURRENT WU5 and corrective bootstrap implementation scope. Related references: MR.1 Installation Refinement, Webcore Deployment & Portability, Package Lifecycle, `docs/31_backup_recovery_foundation_contract.md`, `concepts/copot_system_health_status_concept.md`. No contract amendment or implementation authority from materialization.
