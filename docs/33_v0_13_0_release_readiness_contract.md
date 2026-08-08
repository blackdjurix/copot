# COPOT v0.13.0 Release Readiness Contract

## Status

Preparation: COMPLETE / CONTRACT LOCKED

Release authorization: GRANTED BY USER, subject to the gates in this contract

Release candidate readiness: NOT YET ESTABLISHED

Git tag `v0.13.0`: NOT YET CREATED

GitHub/public distribution publication: NOT YET PERFORMED

This contract owns the bounded release-readiness path for the reconciled Webcore source version `0.13.0`. It does not reopen completed Package Lifecycle, Backup & Recovery, Existing-Runtime Webcore Lifecycle Adoption, or Webcore Deployment & Portability work. It does not take ownership of Module Package Lifecycle WU7 final human/E2E acceptance.

## Authoritative release baseline

The release-readiness baseline is the exact authoritative source state selected from `main` after continuity verification.

Initial contract baseline:

```text
main
6011f0efae1a23611b31ef1b0f00c9513948d42c
docs: reconcile post-portability lifecycle state
```

If `main` advances before a release artifact is built, the newer authoritative state must be inspected and explicitly classified before the artifact is accepted. A release artifact must be traceable to one exact source commit.

The Webcore version source of truth remains:

```text
Copot\Core\Version::CURRENT = 0.13.0
```

Module versions remain independently owned and are not advanced merely because Webcore `0.13.0` is released.

## Scope

v0.13.0 release readiness covers only the current integrated product scope and the evidence required to publish that scope safely.

Included readiness responsibilities:

- exact-source release artifact generation;
- package composition and lifecycle metadata verification;
- clean extraction verification;
- browser/web-installer fresh-install acceptance on supported deployment topologies;
- canonical current schema and installation-state verification;
- post-install public and Admin baseline verification;
- compatibility/runtime requirement documentation;
- release-note and installation-document reconciliation;
- final release-candidate determination;
- explicitly authorized tag and publication after all mandatory gates pass.

Excluded unless separately adopted:

- remote discovery or package download infrastructure;
- automatic updates or update channels;
- signing/trust infrastructure;
- marketplace/distribution service infrastructure;
- differential packages;
- downgrade or reverse migration;
- production Webcore reconciliation;
- Multi-Installation;
- System Health;
- unrelated Admin UI/UX refinement;
- custom Theme work;
- new product features not required by a concrete release regression.

## Relationship to Module Package Lifecycle WU7

Module Package Lifecycle WU1-WU7 implementation remains separately owned.

The release-readiness relationship is:

```text
release-readiness preparation: INDEPENDENT
internal v0.13.0 acceptance artifact: INDEPENDENT / MPL-WU7 ENABLING
fresh-install acceptance: INDEPENDENT / MPL-WU7 SUPPORTING EVIDENCE
MPL WU7 final human/E2E acceptance: OWNED BY THE PARALLEL MPL SESSION
final v0.13.0 release-candidate closure: CLOSURE-dependent on authoritative MPL closure
release/tag/publication: CLOSURE-dependent and separately gated by this contract
```

The release-readiness track may produce a fresh current runtime or acceptance artifact that the parallel MPL WU7 session uses. Doing so does not transfer ownership of WU7 acceptance or Full Module Package Lifecycle closure.

Final v0.13.0 release-candidate readiness must not be declared while authoritative repository state still reports Full Module Package Lifecycle as not closed.

## Deferred server-empty bootstrap disposition

`DI-PACKAGE-LIFECYCLE-WU7-01 — Server-Empty Bootstrap & Package Clean Install` remains:

```text
DEFERRED / UNSCHEDULED
KEEP DEFERRED
```

This release does not adopt it.

A normal supported fresh installation from the official distribution ZIP through the existing web installer is not the deferred package-driven Server-Empty Bootstrap capability.

The accepted ordinary installation path is:

```text
obtain official distribution ZIP
→ extract package
→ establish supported APP_ROOT / PUBLIC_ROOT deployment
→ create dedicated empty database
→ open site in browser
→ web installer
→ canonical current installation state
→ post-install public/Admin verification
```

CLI/operator tooling may exist but must not become a mandatory ordinary installation requirement without an explicit product decision and implementation evidence.

## Official distribution builder

The authoritative source-package builder is:

```text
build/package.php
```

The authoritative build-time include/exclude manifest is:

```text
build/package_manifest.php
```

The builder consumes `Copot\Core\Version::CURRENT`. For Webcore `0.13.0`, the expected internal acceptance and eventual release artifact path is:

```text
dist/copot-v0.13.0.zip
```

The package must remain deterministic for one exact source materialization according to the repository checkout/line-ending contract.

The package must include the package lifecycle metadata at `.copot/package.json` generated by the builder and must preserve package-owned inventory, byte-size, SHA-256, release identity, Webcore target version, runtime compatibility, and migration declaration semantics.

## Acceptance artifact

An internal v0.13.0 acceptance artifact is required before release-candidate readiness.

Artifact generation is not release, tagging, prerelease publication, or public distribution.

The acceptance artifact record must capture at minimum:

- exact Git commit;
- exact package filename;
- SHA-256 digest;
- package entry count or equivalent bounded composition identity;
- generated lifecycle manifest identity;
- extraction result;
- acceptance environment(s);
- clean-install result(s);
- unresolved blockers or deviations.

The artifact must be built from exact authoritative source with no local runtime state, `.env`, source-only documentation/tests, build output recursion, or other forbidden material leaking into the ZIP.

### Recorded acceptance evidence

The internal acceptance artifact was built from exact source commit
`fb48c0974e74bc8b3a3846131142265063a95f97` (`docs(release): lock v0.13.0 readiness contract`).
The official builder passed and produced `dist/copot-v0.13.0.zip` with 565
package-owned payload files plus `.copot/package.json`, size 2,250,433 bytes,
and SHA-256
`f97971634ee71479bb131d7a1af9f70b4b7f71e1b40a52b5be233966e4a33f9c`.
The package inventory matched all payload entries, byte sizes, and SHA-256
values; required and forbidden-content checks passed. The generated manifest
reports target `0.13.0`, package type `copot-webcore`, minimum PHP `8.2.0`,
minimum MySQL `8.0.0`, and required extensions `json`, `pdo`, `pdo_mysql`,
`session`, `filter`, and `zip`.

The current package-builder smoke validation passed 1,826 assertions. The
package-based clean-install verification passed 105 assertions against the
dedicated `copot_d4_clean_install_test` database, including extraction,
installer requirements, generated `.env`, canonical schema, first
administrator, default Theme, nine approved baseline Modules, marker `0.13.0`,
installer blocking, normal bootstrap, public response, Admin login/dashboard,
Settings, Redirects, and controlled Site Asset behavior.

Browser/web-installer acceptance was attempted through an isolated local HTTP
server and did not pass: the packaged front controller returned its controlled
HTTP 500 response before rendering the installer. No browser acceptance claim
is made. The local PHP environment has a mismatched configured extension
directory; the accepted non-HTTP validation used PHP 8.5.7 with explicit
`pdo_mysql` and `zip` extensions.

## Runtime compatibility contract

Release documentation must state the actual runtime compatibility encoded by the package and verified by release acceptance.

Current minimum package compatibility is:

```text
PHP >= 8.2.0
MySQL >= 8.0.0
required PHP extensions:
- json
- pdo
- pdo_mysql
- session
- filter
- zip
```

Additional runtime requirements already established by shipped product behavior, including image-processing requirements where applicable, must remain documented in the relevant product/runtime documentation and must not be silently weakened by release packaging.

## Supported deployment boundary

v0.13.0 release acceptance consumes the completed Webcore Deployment & Portability Foundation rather than reopening it.

Supported release-acceptance deployment classes include:

1. configurable document root pointing to the package public tree;
2. generic subdirectory/base-path deployment;
3. split private `APP_ROOT` and public `PUBLIC_ROOT` deployment on a supported shared-host-like capability class.

Public-only hosting environments that cannot preserve the accepted private application boundary are not automatically supported.

## Minimum clean-install matrix

The acceptance artifact must pass the smallest representative matrix that proves the released package works across the already-accepted deployment contract.

### Matrix A — canonical configurable public root

```text
APP_ROOT = extracted package root
PUBLIC_ROOT = <APP_ROOT>/public
base path = /
```

Required evidence:

- clean package extraction;
- browser reaches installer from no-install state;
- requirements gate passes;
- empty database accepted;
- installer writes required environment state;
- canonical schema installed;
- first administrator created;
- site settings finalized;
- default Theme active;
- approved baseline Modules enabled;
- installed marker reports `0.13.0`;
- installer becomes unavailable after finalization;
- public runtime and Admin login/logout function.

### Matrix B — generic subdirectory/base path

Representative shape:

```text
http://localhost/copot/
```

Required evidence adds:

- installer redirects remain inside the base path;
- Admin URLs and assets remain base-path correct;
- internal redirects/forms remain base-path correct;
- public Theme/static delivery remains base-path correct;
- Media/public delivery remains base-path correct where exercised.

### Matrix C — split private/public roots

Representative shape:

```text
private APP_ROOT outside the served public tree
separate PUBLIC_ROOT inside the generic web root
```

Required evidence adds:

- browser installation succeeds using the accepted split-root bootstrap;
- private application material is not directly served;
- public front controller and public assets resolve correctly;
- installer/runtime/Admin paths use the accepted deployment context;
- resulting installed application remains operable after installer finalization.

This matrix is release acceptance over previously accepted Portability behavior. Do not repeat broad Portability archaeology or reopen completed Portability work unless concrete regression evidence appears.

## Canonical fresh-install state

A successful fresh v0.13.0 installation must establish, at minimum:

- `storage/installed.lock` exists under the accepted private application storage root;
- installed marker version equals `0.13.0`;
- marker retains its established exact bounded format;
- canonical current schema is present;
- first administrator exists;
- default Theme is active;
- current approved baseline Modules are enabled;
- current required permissions/provisioning are present;
- installer gate blocks further installation after finalization;
- normal application bootstrap succeeds;
- current lifecycle/migration state required by the existing implementation is internally consistent.

The release-readiness track must validate current lifecycle state. It must not invent a second installed-state format or silently fabricate migration history to make a clean install appear healthy.

## Package composition acceptance

The v0.13.0 distribution artifact must be checked against the current build manifest and release contract.

At minimum, acceptance must prove:

- all runtime/install files selected by the manifest are present;
- all ten current product Core Module directories selected by the manifest are present;
- `release.json` is present;
- `.env.example`, `INSTALL.md`, `README.md`, `CHANGELOG.md`, and license material are present as specified;
- `.env` is absent;
- `AGENTS.md`, `docs/`, `tests/`, `build/`, `dist/`, and `modules/example/` are absent;
- installation locks, logs, cache content, Site Asset runtime content, and other local runtime state are absent;
- `.copot/package.json` is present and consistent with actual package-owned files;
- package inventory verification rejects a materially tampered artifact.

## Release documentation reconciliation

Before release-candidate readiness, current user-facing release/install documentation must describe the actual supported v0.13.0 product state.

Required reconciliation includes at least:

- `INSTALL.md` deployment instructions aligned with completed APP_ROOT / PUBLIC_ROOT portability and supported base-path deployment;
- `docs/15_distribution_and_packaging.md` current output/version wording aligned with the dynamic `0.13.0` builder target while preserving historical v0.12.0 evidence as history;
- `CHANGELOG.md` current `0.13.0` release contents and material limitations;
- `README.md` current stable/release wording once release state changes;
- `AGENTS.md` and `docs/03_roadmap.md` objective lifecycle wording once release state changes;
- release notes derived from committed product state and package-owned release metadata rather than invented feature claims.

## Known limitations and disclosures

The v0.13.0 release must not imply support for capabilities that remain excluded or deferred.

Material disclosures include, where relevant:

- package lifecycle is local/operator-provided package operation; remote update discovery/download infrastructure is not included;
- signing/trust and automatic update channels are not included;
- downgrade/reverse migration is unsupported;
- stale package-owned file deletion remains unsupported where existing lifecycle contracts say so;
- production Webcore reconciliation is a separate operational activity and not performed by releasing v0.13.0;
- package-driven server-empty bootstrap remains deferred;
- public-only hosting capability class is not automatically supported where the accepted private filesystem boundary cannot be preserved;
- future Multi-Installation, System Health, custom Theme work, and unrelated UI refinement are not v0.13.0 scope.

## Release gates

The release process is explicitly gated.

### Gate 1 — Contract lock

PASS when this contract is committed and remotely verifiable on the release-readiness branch.

### Gate 2 — Internal acceptance artifact

PASS when exact-source `copot-v0.13.0.zip` is built and its identity/composition are recorded and verified.

### Gate 3 — Automated package and clean-install validation

PASS when directly relevant package, clean-install, installer, lifecycle, and Portability regressions required by current impact pass in a capable environment.

### Gate 4 — Browser clean-install acceptance

PASS when the minimum supported installation matrix has sufficient real browser/runtime evidence and no release-blocking regression remains.

### Gate 5 — Documentation reconciliation

PASS when material current user/release documentation matches the accepted artifact, supported deployment boundary, current limitations, and actual release state.

### Gate 6 — Module Package Lifecycle closure dependency

PASS only when authoritative repository state records final MPL WU7 acceptance and Full Module Package Lifecycle closure. The release-readiness session consumes that result but does not perform or assume it.

### Gate 7 — Release candidate readiness

PASS only when Gates 1-6 pass and the exact final release commit/artifact pair is frozen with no unresolved release-blocking finding.

### Gate 8 — Release execution

The user has explicitly authorized v0.13.0 release execution. That authorization becomes executable only after Gate 7 passes.

Release execution consists of the smallest ordered irreversible sequence:

```text
verify exact final commit and artifact
→ create Git tag v0.13.0 at the accepted final release commit
→ verify tag target
→ create/publish GitHub Release v0.13.0
→ attach exact accepted copot-v0.13.0.zip
→ verify published asset identity and release metadata
```

A failed gate stops this sequence. Do not publish a different build from the one accepted.

### Gate 9 — Post-publication verification

PASS when the public release, tag, asset filename, asset digest/identity, target commit, release notes, and downloadable package are independently verified.

Only then may v0.13.0 be recorded as released/published in authoritative repository documentation.

## Release authorization boundary

The user has granted explicit authorization to release v0.13.0.

This authorization permits the tag/publication actions in Gate 8 after the mandatory preceding gates pass. It does not convert a failed or unexecuted acceptance gate into PASS and does not authorize unrelated scope expansion.

If source changes after artifact acceptance, the artifact becomes stale and must be rebuilt/revalidated against the new intended release commit before tag/publication.

## Current next action

After this contract is durably committed and remotely verified, the next eligible action is:

```text
build and validate the internal v0.13.0 acceptance artifact from the exact authoritative source selected for release readiness
```

No release tag or public publication should occur before the acceptance artifact, browser clean-install evidence, documentation reconciliation, and authoritative MPL closure dependency are complete.
