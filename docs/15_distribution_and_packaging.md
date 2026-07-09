# Distribution and Packaging

## Purpose

This document defines the boundary between the copot source repository and an installable release package.

M2 implementation is complete. Post-M2 release preparation must produce a deterministic installable artifact, verify it from a clean state, and avoid distributing local development state.

## Source Repository vs Release Package

The source repository contains development and maintenance material that is not required by an installed site. A release package is therefore not a raw ZIP of a working directory and is not defined by `.gitignore` alone.

The package builder must use an explicit allowlist and explicit exclusions.

The official deterministic builder is:

```text
build/package.php
```

It consumes the build-time manifest at:

```text
build/package_manifest.php
```

The manifest is a build concern only. It is not runtime configuration and must not create a second version source of truth.

## Version Source of Truth

The framework release version is defined by:

```text
Copot\Core\Version::CURRENT
```

Installer finalization uses that value when writing `storage/installed.lock`. Package and release tooling must consume the same version source instead of duplicating release numbers.

Current M2 final release candidate version:

```text
0.12.0
```

The official package output for this release candidate is:

```text
dist/copot-v0.12.0.zip
```

## Environment Contract

`.env` is environment-specific runtime state and must never be included in an installable package.

`.env.example` is the documented configuration reference. It is not required to mirror installer output byte-for-byte and is not copied automatically by the installer.

The installer persists the minimum database connection keys required for operation. Additional supported runtime overrides, such as `APP_ENV`, `APP_DEBUG`, `APP_URL`, and `SESSION_SECURE`, are documented by `.env.example` and may be added to the generated `.env` for the target deployment.

## Package Include Policy

The installable package is expected to include runtime application code and end-user installation documentation, including:

```text
app/
bootstrap/
config/
database/
modules/content/
modules/taxonomy/
public/
resources/
routes/
storage/cache/.gitkeep
storage/logs/.gitkeep
themes/default/
.env.example
CHANGELOG.md
INSTALL.md
LICENSE
README.md
```

This list is the release-preparation contract. The package builder implementation may encode it in a manifest, but must not broaden it implicitly by archiving the working directory.

The current builder encodes this contract as an explicit build-time include list with explicit exclusions. It creates `dist/` when needed and replaces an existing same-name ZIP deterministically.

## Package Exclude Policy

The installable package must exclude development-only, local, secret, runtime, and generated material, including:

```text
.env
.git/
.github/
AGENTS.md
docs/
tests/
modules/example/
storage/*.lock
storage/logs/*
storage/site-assets/
dist/
local editor or IDE state
runtime caches
temporary installer files
```

The Example module remains in the source repository as a controlled lifecycle fixture and must not be shipped as a production module.

Tests and project documentation remain source-controlled. They are excluded from the end-user package, not deleted from the repository.

## Writable Runtime Paths

A fresh package must provide the required runtime directory structure without shipping runtime content.

At minimum:

```text
storage/
storage/cache/
storage/logs/
```

Site Asset storage is created by runtime behavior as needed and must remain excluded from source and release artifacts.

## Deterministic Package Builder

The deterministic package builder is implemented and produces:

```text
dist/copot-v0.12.0.zip
```

Builder behavior:

* runs as CLI PHP;
* reads the release version from `Copot\Core\Version::CURRENT`;
* uses the build-time package manifest as the package allowlist/exclusion contract;
* creates `dist/` when it does not exist;
* replaces an existing same-name ZIP deterministically;
* does not modify source files;
* excludes runtime-local state, source-only files, local tooling state, and generated package output.

Release-candidate audit verified that two builds from the same source state produced identical SHA-256 hashes. External archive compatibility was also verified with PowerShell `Expand-Archive`, with an additional independent `tar -tf` listing check where available.

### Line-Ending Reproducibility

The package builder reads file bytes from the checked-out filesystem. It does not read canonical blobs directly from Git.

The repository therefore owns a checkout materialization contract through `.gitattributes`: tracked text content is materialized with LF line endings, while binary content must not be transformed as text. Package reproducibility must be verified across clean isolated checkouts or worktrees from the same Git tree, not only by rebuilding twice inside one existing working tree.

## Clean-Install Verification

A release candidate is not accepted until the built artifact is tested from a clean state with:

```text
fresh extracted package
fresh directory
fresh empty database
no .env
no installed.lock
no cache/log content
no Site Asset content
```

Verification must cover:

1. installer requirements and database connection;
2. `.env` generation;
3. schema installation;
4. first administrator creation;
5. initial Settings persistence;
6. default Theme activation;
7. baseline Content and Taxonomy module enablement;
8. Admin login and logout;
9. Content and Taxonomy Admin access;
10. public Theme rendering;
11. Logo/Favicon upload, delivery, replacement, and removal;
12. controlled error behavior and no sensitive-detail leakage;
13. final package contents against the include/exclude contract.

The current release candidate has passed automated clean-install verification from the built package ZIP. Verification extracts `dist/copot-v0.12.0.zip` into an isolated temporary target, uses a dedicated guarded D4 database, runs the installer service flow from the extracted artifact, validates the installed marker version as `0.12.0`, boots the installed application, and checks minimal public, Admin, Settings, and controlled Site Asset behavior.

The verification proves the package does not depend on the source repository `.env`, source runtime locks, source logs, source cache content, local Site Asset data, `tests/`, `build/`, or `docs/`.

Deployment-environment verification remains separate and pending for real deployment targets, including HTTPS Secure-cookie behavior, production `public/` document-root isolation, direct-access blocking for private paths, and symlink-capable host filesystem semantics.

## Deferred Distribution Work

The following work belongs to later Post-M2 release-preparation steps:

- GitHub Release artifact attachment.

This release-preparation phase does not add patch distribution, remote updates, package repositories, signing, delta updates, or multi-version maintenance infrastructure.
