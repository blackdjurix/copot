# Webcore Deployment & Portability Foundation Contract

## Status

Preparation: COMPLETE / CONTRACT LOCKED

Implementation: NOT STARTED

Adoption: PROMOTED FOR POST-M3 PLATFORM FOUNDATION PREPARATION

Release / tag / publication: NOT AUTHORIZED

This contract adopts Webcore Deployment & Portability Foundation as a Post-M3 Platform Foundation workstream. It does not reopen M2.4 Platform Hardening and does not authorize implementation merely by existing in the repository.

The implementation target must be explicitly authorized after preparation continuity is verified.

---

## 1. Why this foundation exists

M2.4 Platform Hardening established shared-hosting runtime/deployment checks, production document-root expectations, private writable storage requirements, and ordinary PHP/MySQL shared-host operation. M2.4 is complete and remains closed.

Subsequent real deployment analysis exposed a narrower architecture gap that is not a reason to reopen M2.4:

- the current repository layout places private application directories and `public/` under one project root;
- normal operation assumes the serving web server can select `<project-root>/public` as the document root;
- local XAMPP development commonly satisfies that assumption with a COPOT-specific VirtualHost and local hostname mapping;
- shared hosting may instead provide a fixed public document root such as `public_html`, `www`, `httpdocs`, or an equivalent provider-owned path;
- a hosting account may still provide private filesystem space outside that public document root, but the private application root name and location are provider/account specific;
- COPOT must not require a particular hosting panel, a literal `public_html` directory name, Windows `hosts` modification, or a custom VirtualHost as a product runtime requirement.

The actual portability problem is therefore not `public` versus `public_html` naming. It is the current coupling between the private application root and the public document root.

This foundation establishes the deployment boundary needed to make that coupling explicit and safely separable.

---

## 2. Objective

Establish a Webcore-owned deployment-root and base-path contract that allows COPOT to operate safely when the private application root and public document root are different filesystem paths, while preserving the existing configurable-document-root deployment model.

The foundation must remain hosting-panel agnostic.

Representative target layouts include:

```text
Configurable document root

/home/user/copot/
├── app/
├── config/
├── modules/
├── storage/
└── public/          <- document root
```

and:

```text
Fixed public document root

/home/user/copot/
├── app/
├── config/
├── modules/
├── storage/
└── ...              <- private application root

/home/user/public_html/
├── index.php
├── public assets
└── deployment routing file where required
```

The literal names `copot` and `public_html` are examples only. COPOT must not require either name.

---

## 3. Core deployment model

The intended baseline distinguishes at least these logical roots:

```text
APP_ROOT
PUBLIC_ROOT
BASE_PATH / base URL path where applicable
```

### 3.1 Application root

`APP_ROOT` owns private framework/application material such as, where applicable:

- `app/`;
- `bin/`;
- `bootstrap/`;
- `build/`;
- `config/`;
- `database/`;
- `docs/`;
- `modules/`;
- `resources/`;
- `routes/`;
- private `storage/`;
- `tests/`;
- `themes/` source/runtime material that is not explicitly public;
- `tools/`.

The exact inventory must be audited against current package ownership before implementation.

`APP_ROOT` must not depend on one fixed directory name and must not require residence directly under the web document root.

### 3.2 Public root

`PUBLIC_ROOT` is the only deployment tree intended for direct web serving.

It may contain only material explicitly intended for public delivery, such as:

- the front-controller entrypoint;
- required web-server routing/bootstrap files;
- static public/Admin/theme assets that are intentionally public;
- other explicitly public package-owned material established by audit.

Private configuration, private storage, database definitions, tests, tools, package staging, recovery data, and equivalent private material must not become public merely to accommodate a fixed hosting document root.

### 3.3 Base path

Filesystem root separation and URL base-path handling are related but distinct.

COPOT must not assume it always runs at `/` when the supported hosting/router environment serves it at a subdirectory such as:

```text
https://example.com/copot/
```

Preparation must audit root-relative redirects, generated URLs, installer paths, Admin URLs, Theme/public assets, Media delivery, form actions, CSRF flows, redirects, and other materially affected URL producers/consumers.

---

## 4. Supported hosting capability classes

The foundation is capability-based rather than panel-specific.

### Class A — Configurable document root

The hosting environment allows the site/document root to point directly to `<APP_ROOT>/public` or the equivalent public tree.

This remains a supported and desirable deployment model.

### Class B — Fixed public document root with private readable filesystem area

The provider fixes the public document root but allows the account/runtime to read a private application directory elsewhere inside the account boundary.

This is a primary portability target.

COPOT must be able to bootstrap from the public root into the private application root without requiring privileged server configuration.

### Class C — Public-only filesystem boundary

The provider exposes no suitable private readable application area and requires the complete application tree to live under a directly web-served root.

This class is not automatically supported by this foundation.

Preparation/implementation may prove a safe bounded deployment, but COPOT must not claim support by relying on one `.htaccess` rule as the sole protection for private framework/configuration/runtime material. If a secure portable boundary cannot be established, this deployment class must remain unsupported rather than weakening the security model.

---

## 5. Local XAMPP role

XAMPP is a validation environment, not a COPOT-specific server product.

Portability acceptance must include a representative generic-XAMPP path where feasible:

```text
Apache main document root: C:\xampp\htdocs
public COPOT path:         C:\xampp\htdocs\copot
private APP_ROOT:          outside the public COPOT path and preferably outside htdocs
request:                   http://localhost/copot/
```

The portability proof must not require:

- a COPOT-specific Windows `hosts` entry;
- a COPOT-specific Apache VirtualHost;
- changing the global Apache document root to COPOT;
- MariaDB privileges/configuration that exist only to make a portability test pass.

Existing historical/current development runtimes that use a VirtualHost may remain useful evidence, but they do not by themselves prove shared-host portability.

---

## 6. Relationship to existing closed work

### M2.4 Platform Hardening

M2.4 remains COMPLETE AND CLOSED.

This foundation consumes its security/deployment expectations and extends the deployment architecture where later evidence exposed a root-layout portability gap.

Do not reopen M2.4 merely because this foundation exists.

### Package Lifecycle & Existing-Runtime Webcore Lifecycle Adoption

Package Lifecycle, Backup & Recovery, and Existing-Runtime Webcore Lifecycle Adoption remain separate capability owners.

Portability implementation must not weaken package ownership, lifecycle state, recovery-root privacy, migration integrity, or quiescence requirements.

Historical Webcore forward-migration support discovered during Module Package Lifecycle acceptance work remains a separate lifecycle concern unless a concrete portability dependency requires a bounded intersection.

Portability must not fabricate migration history or silently normalize an unknown legacy runtime.

---

## 7. Relationship to Module Package Lifecycle WU7

Module Package Lifecycle WU1-WU7 implementation remains complete for its accepted implementation scope.

Final WU7 human/E2E acceptance remains BLOCKED / PARKED until an authoritative committed Webcore lifecycle state exists on an intended acceptance runtime.

This portability foundation is selected while WU7 is parked because the current investigation showed that the deployment model of the acceptance/runtime environment must be explicit and representative rather than made COPOT-specific merely to satisfy acceptance.

This contract does not reopen Module Package Lifecycle implementation and does not perform WU7 acceptance.

Broader portability work must not be invented as an unlimited WU7 prerequisite. When a sufficiently representative committed Webcore runtime exists and the actual WU7 prerequisite is satisfied, WU7 may resume through separate authorization even if unrelated portability tails remain.

---

## 8. Relationship to Multi-Installation Isolation Foundation

Dependency classification: SOFT overall, with an ownership boundary that prevents duplicate base-path work.

Webcore Deployment & Portability Foundation owns the single-install baseline for:

- application-root/public-root separation;
- hosting-panel-agnostic public bootstrap;
- single-install base-path/subdirectory correctness;
- safe public/private filesystem deployment boundary.

Multi-Installation Isolation Foundation owns cross-install concerns such as:

- installation identity;
- database/table namespaces;
- cross-install session/cookie/runtime isolation;
- multiple installations sharing one host/database/account;
- collision prevention;
- installation-scoped lifecycle/recovery boundaries.

Multi-Installation must consume the established single-install base-path/root contract rather than implement a second competing portability mechanism.

Independent Multi-Installation preparation/audit may continue, but overlapping base-path implementation should wait for or align with the portability boundary.

---

## 9. Relationship to System Health & Status

Dependency classification: INDEPENDENT for preparation.

System Health may later report deployment capability/health findings, but this foundation does not require System Health in order to establish deployment-root portability.

Do not expand portability into monitoring or observability.

---

## 10. Deferred boundary

`DI-PACKAGE-LIFECYCLE-WU7-01 — Server-Empty Bootstrap & Package Clean Install`

Disposition: KEEP DEFERRED.

This foundation does not adopt server-empty package bootstrap merely because installation/deployment paths are relevant.

Existing web-installer/bootstrap behavior may be audited where required to make an already-present installation flow respect root/base-path boundaries, but building a package-driven server-empty bootstrap executor remains separate deferred work.

---

## 11. Preparation scope

The first preparation pass must audit current repository behavior before implementation lock.

Material audit surfaces include:

1. bootstrap/root resolution;
2. public `index.php` assumptions;
3. environment/config path loading;
4. storage/log/cache/temp/package/recovery path derivation;
5. route and redirect generation;
6. Admin URL generation;
7. installer URL/path handling;
8. Theme/public asset URLs;
9. Media/public delivery;
10. Form/CSRF redirects and actions where base path matters;
11. package builder inventory/public-root ownership;
12. CLI behavior when APP_ROOT and PUBLIC_ROOT differ;
13. tests/fixtures with hardcoded project-root/public assumptions;
14. Apache-specific `.htaccess` behavior versus framework-owned routing requirements.

Audit findings must distinguish:

- framework requirement;
- deployment adapter concern;
- web-server/provider responsibility;
- unsupported hosting capability;
- Multi-Installation concern;
- package lifecycle concern;
- unrelated historical behavior.

---

## 12. Candidate work-unit shape

This shape is provisional until the focused repository audit locks it.

```text
WU1 — Root & Base-Path Audit / Contract Confirmation
WU2 — Application Root / Public Root Resolution Boundary
WU3 — Base-Path-Aware Routing, Redirect & URL Generation
WU4 — Split-Root Public Entrypoint and Public Asset Boundary
WU5 — Existing Installer / Runtime Integration
WU6 — Shared-Host-Like Compatibility & Regression Acceptance
```

The audit may merge, split, reorder, rename, or reject candidate units.

Implementation must not start merely because this candidate list exists.

---

## 13. Acceptance direction

The eventual foundation should prove at minimum:

### Existing configurable-document-root compatibility

A normal deployment where the document root points to the package public tree continues to work.

### Split-root deployment

COPOT can run with `APP_ROOT` and `PUBLIC_ROOT` at separate paths inside one supported hosting/runtime filesystem boundary.

### Generic local/shared-host-like evidence

A representative XAMPP/shared-host-like deployment can serve COPOT from a subdirectory without requiring a COPOT-specific hostname or VirtualHost.

### Public/private containment

Private application/configuration/runtime material is not directly exposed merely to satisfy a fixed public web root.

### Base-path correctness

Material framework-owned routes, redirects, forms, Admin links, assets, and other audited URL surfaces work under a non-root base path where that deployment mode is declared supported.

### Existing runtime compatibility

The foundation must preserve current supported root deployment behavior and must not require migration merely because the operator keeps the current `<project>/public` document-root layout.

### Fail-closed unsupported environments

Where the hosting capability cannot provide an acceptable private/public security boundary, COPOT must report/document the unsupported condition rather than silently weaken the deployment model.

---

## 14. Non-goals

This preparation does not authorize:

- Server-Empty Bootstrap & Package Clean Install;
- generic hosting-panel integrations;
- cPanel/DirectAdmin/Plesk APIs;
- SSH/SFTP/FTP deployment automation;
- container/Kubernetes deployment framework;
- remote/distributed APP_ROOT across unrelated servers;
- Multi-Installation database namespace implementation;
- System Health implementation;
- historical migration-chain fabrication;
- Module Package Lifecycle WU7 acceptance;
- release/tag/publication;
- broad framework filesystem abstraction without repository evidence.

---

## 15. Adoption and next gate

Adoption decision:

```text
Webcore Deployment & Portability Foundation
= ADOPTED as a Post-M3 Platform Foundation preparation target
```

Implementation state:

```text
NOT STARTED
```

Next gate:

```text
Focused repository portability audit and implementation-contract confirmation
```

No implementation branch should be created from this preparation state until the next session/task explicitly authorizes implementation after continuity and repository freshness verification.
