# Distribution and Packaging

## Purpose

This document defines the boundary between the copot source repository and an installable release package.

M2 implementation is complete. Post-M2 release preparation must produce a deterministic installable artifact, verify it from a clean state, and avoid distributing local development state.

## Source Repository vs Release Package

The source repository contains development and maintenance material that is not required by an installed site. A release package is therefore not a raw ZIP of a working directory and is not defined by `.gitignore` alone.

The package builder must use an explicit allowlist and explicit exclusions.

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

## Deferred Distribution Work

The following work belongs to later Post-M2 release-preparation steps:

- deterministic package builder implementation;
- generated package manifest enforcement;
- clean-install automation where practical;
- release candidate build and verification;
- GitHub Release artifact attachment.

This cleanup step does not add patch distribution, remote updates, package repositories, signing, delta updates, or multi-version maintenance infrastructure.
