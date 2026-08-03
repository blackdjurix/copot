# M3.10 Redirect Manager Preparation Contract

## Purpose and status

This document locks the accepted preparation direction for M3.10 Redirect
Manager. It is a preparation and implementation-entry contract only. It does
not implement M3.10, create an implementation branch, authorize runtime
synchronization, or authorize release, tag, merge, or publication work.

```text
M3.9 Internal Dashboard:
COMPLETE AND CLOSED

M3.10 preparation:
LOCKED

M3.10 implementation:
NOT STARTED

M3.10 implementation branch:
NOT AUTHORIZED / ABSENT
```

The authoritative repository baseline for this preparation lock is `main` at
`2e457c40b4fcdad35e38ce67a4e28e1b7d7bb3a0`:

```text
docs(m3.9): close feature branch lifecycle state
```

## Product definition

M3.10 provides a first-party Redirect Manager module for manually managed,
one-hop request redirects.

The baseline Redirect identity is:

```text
source path -> target location + redirect status
```

The baseline is deliberately limited to:

* manually managed redirects;
* source path identity only;
* query strings excluded from source identity;
* no persisted host, domain, locale, or method dimension;
* eligible unmatched public `GET` request resolution only;
* `301` and `302` status codes only;
* `302` as the default status;
* root-relative internal targets;
* absolute `http://` and `https://` targets;
* no regex or wildcard matching;
* no analytics, hit counts, import/export, multi-domain routing,
  expiration, priority, enabled flag, or generic redirect-engine platform.

## Ownership and boundaries

Redirect Manager owns:

* the Redirect entity;
* persistence;
* source and target validation;
* manual create, update, and delete lifecycle;
* the Redirect Admin workspace;
* the managed redirect resolution service.

Core owns routing and the smallest unresolved-route integration seam needed to
consult an eligible Redirect resolver. The Core seam must not become a
parallel Router, generic middleware platform, route-arbitration system, or
route-introspection framework.

Content retains ownership of Content identity, slug lifecycle, persistence,
and the `/content/{slug}` route. Navigation retains ownership of menus, menu
items, and target-provider resolution. Redirect Manager must not directly read
Content or Navigation tables or repositories.

## Request lifecycle and precedence

The locked logical request order is:

1. installer gate;
2. normal Application bootstrap;
3. exact and pattern application, authentication, Admin, and enabled-module
   routing;
4. configured Admin routing and fallback behavior;
5. managed Redirect resolution only when no normal route matched and the
   request is an eligible public `GET`;
6. normal `404`.

The current Router returns a handler response immediately after an exact or
pattern route matches, including a handler-generated `404`. A handler-level
`404` is therefore not reinterpreted as an unresolved route.

M3.10 must not be implemented as a catch-all module route that competes with
normal routes. Existing routes always win. A Redirect whose source later
becomes owned by a real route becomes inert at runtime rather than shadowing
that route.

Static files served outside the application entry point remain outside the
managed resolver. Installer behavior remains outside the normal Application
resolver path: uninstalled requests redirect to `/install`, and installed
requests to `/install` remain blocked.

The pattern-owned `/content/{slug}` namespace does not fall through to
Redirect Manager. Therefore this is baseline-supported:

```text
/old-page -> /content/new-page
```

This is not baseline-supported:

```text
/content/old-slug -> /content/new-slug
```

No Content behavior is modified by the M3.10 baseline.

## Source path contract

Source identity is the canonical path representation used by the M3.10
resolver. Storage normalization and runtime matching must agree.

Required rules:

* exactly one outer leading `/`;
* trailing `/` removed except for root;
* `/` itself is not a valid Redirect source;
* query strings are not allowed in stored source values;
* fragments are not allowed;
* host and scheme are not allowed;
* internal repeated slashes are rejected;
* backslashes are rejected;
* null and control characters are rejected;
* literal `.` and `..` path segments are rejected;
* malformed percent-encoding is rejected;
* percent-encoded path separators, backslashes, dot-segment representations,
  null bytes, and controls are rejected;
* valid non-structural percent-encoded bytes are preserved literally;
* arbitrary request paths are not URL-decoded and recanonicalized;
* matching remains case-sensitive, consistent with the current Router.

No additional Unicode or filesystem-style path normalization is introduced.

## Source storage

The source path is a bounded, case-sensitive, full-unique identity. The
preparation baseline is:

```sql
source_path VARCHAR(512)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_bin
    NOT NULL
UNIQUE
```

The full unique index is required. `VARCHAR(2048) UNIQUE` is not authorized
for `source_path`.

`target` may use `VARCHAR(2048) NOT NULL` because it is not a unique indexed
identity.

## Reserved sources

Reserved-source protection must follow current route ownership evidence. At
minimum, protect `/`, the installer-owned `/install` path and namespace, the
configured Admin base and descendants, current Admin/static asset namespaces,
current public asset-delivery namespaces, and `/content` and descendants.

Reserved exact paths and reserved prefixes or pattern-owned namespaces must be
documented separately. Historical or test-only paths must not be frozen as
reserved without current route-ownership evidence. Runtime route precedence
remains the final safety boundary for future routes.

## Target contract

Root-relative application paths are supported. Protocol-relative targets
beginning with `//` are rejected.

Internal targets must reject control characters, CR/LF, null bytes, malformed
values, backslashes, and dangerous or ambiguous structural encoding.

Absolute targets using only `http` or `https` are supported. They require a
valid scheme and host and must not contain credentials/userinfo unless a later
separate decision explicitly justifies them.

Reject `javascript:`, `data:`, `file:`, `mailto:`, protocol-relative URLs, all
other schemes, CR/LF, null/control characters, backslashes, and malformed URLs.

External HTTP(S) destinations are intentionally Admin-managed values, not
request-controlled open-redirect parameters. Redirect Manager enforces this
allowlist before calling the existing generic `Response::redirect()`
primitive; the generic response contract is not changed by preparation.

## Status codes

The baseline supports exactly `301` and `302`. The default is `302`.
`307` and `308` remain outside baseline scope until a concrete
method-preserving requirement exists.

## Chain and loop policy

Managed redirects are one-hop.

For internal root-relative targets:

* direct self-redirects are rejected;
* create and update reject a target that is another managed source;
* creating or updating a source is rejected when an existing internal managed
  redirect already targets that source;
* the runtime resolver never traverses managed redirect chains.

For absolute external HTTP(S) targets, the target is terminal, no managed graph
traversal is attempted, and host-independent validation does not claim to prove
that a same-site absolute URL can never participate in a global cycle.

## Duplicate and conflict policy

* normalized `source_path` is unique;
* duplicate normalized sources fail validation;
* reserved sources fail validation;
* existing normal routes win at runtime;
* later route additions may make an existing Redirect inert;
* M3.10 adds no generic route arbitration or route-introspection
  infrastructure.

## Admin workspace

Admin applicability is `REQUIRED`.

Baseline workflows are list, create, edit, and delete. The list displays
source, target, status, and useful created/updated timestamps.

Search, filtering, enabled/disabled state, bulk actions, import/export, and
analytics are outside baseline. Deletion is hard delete.

## Permission and mutation security

The dedicated runtime permission is `redirects.manage`.

Admin access requires both `admin.access` and `redirects.manage`. Mutation
routes require authorization before CSRF validation, CSRF protection, controlled
validation failures, and sanitized persistence failures.

## Dashboard applicability

The post-M3.9 Dashboard applicability review is `NOT APPLICABLE`. No Dashboard
widget is part of M3.10 baseline, and no Deferred Item is created merely
because no widget is selected.

## Content integration

Disposition: `DEFERRED`.

Baseline Redirect Manager may target Content URLs but must not automatically
create redirects when Content slugs change. It must not retain historical
Content slugs, alter Content schema, read Content repositories or tables, add
automatic Content slug redirect behavior, or create a speculative event or
implementation placeholder for this direction.

A future adoption requires an explicit Content-origin integration or a
separately justified richer route-miss contract. No new Deferred Item registry
entry is required by this preparation lock.

## Navigation integration

Disposition: `NOT APPLICABLE`.

Redirect records are neither Navigation entities nor Navigation target
providers. Navigation Manager is not modified for the M3.10 baseline.

## Persistence and lifecycle

The smallest baseline table is:

```text
redirects

id
source_path
target
status_code
created_at
updated_at
```

Semantics:

* `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`;
* `source_path`: maximum 512 characters, `utf8mb4_bin`, unique;
* `target`: maximum 2048 characters;
* `status_code`: allowlisted `301`/`302`, default `302`;
* timestamps are required.

No fields are authorized for hits, analytics, notes, regex, priority,
expiration, locale, domain, owner, or enabled state.

Lifecycle consists of create, update, and hard delete. Persistence uses a
transaction boundary where required, optimistic stale-write protection
consistent with existing Admin manager conventions, and controlled uniqueness
failures.

## Provisioning and packaging

Implementation is expected to provide the canonical fresh-install schema
addition, an idempotent M3.10 existing-install upgrade artifact, Redirect
Manager module manifest, `redirects.manage` permission, seeded Administrator
mapping, baseline fresh-install module activation if confirmed consistent with
the existing first-party manager policy, package-manifest inclusion, and
focused fresh-install and upgrade evidence.

No generic migration runner is introduced. No schema or provisioning changes
are made during preparation lock.

## Security contract

The implementation must lock and test dedicated authorization,
authorization-before-CSRF, CSRF mutation protection, escaped Admin rendering,
source canonicalization and 512-character bound, case-sensitive source
identity, reserved-source rejection, dangerous target-scheme rejection,
protocol-relative rejection, CR/LF/null/control rejection, malformed
percent-encoding rejection, encoded structural path rejection, internal
self-target rejection, internal chain/cycle rejection, normal route
precedence, no handler-level-404 fallback, controlled persistence/runtime
failures, sanitized diagnostics, and no direct Content/Navigation persistence
access.

## Deferred Item review

| Item | Relevance | Disposition | Existing target changed? |
|---|---|---|---|
| `DI-M3.9-01` Dashboard layout | None | `NOT APPLICABLE` to M3.10 | No |
| `DI-M3.9-02` Admin Media CSS tokens | Unrelated | `KEEP DEFERRED` | No |
| `DI-M3.9-03` Admin Batch 4 regression modernization | Unrelated | `KEEP DEFERRED` | No |

None of these items is adopted into M3.10.

## Exact Work Units

Exactly four domain Work Units are locked.

### WU1 — Redirect Contract and Core Routing Boundary

Objective: lock and implement the smallest redirect-resolution integration
contract between Core routing and Redirect Manager.

Scope includes source and target semantics, status allowlist, source matching,
the unresolved-route seam, route precedence, protected/reserved namespaces,
the internal one-hop policy, and focused routing-boundary tests.

It excludes persistence lifecycle beyond interfaces required by the seam,
Admin UI, Content integration, Navigation integration, and generic
middleware/Router redesign.

Acceptance evidence covers explicit-route precedence, installer/Admin
protection, eligible unmatched-GET resolution, malformed path rejection, and
no handler-level-404 fallthrough.

### WU2 — Redirect Persistence, Module Lifecycle, and Provisioning

Objective: deliver Redirect Manager domain persistence and installation
lifecycle.

Scope includes entity/value validation, repository/service, table, upgrade
artifact, permission provisioning, module manifest, baseline activation if
confirmed by implementation entry review, package inclusion, create/update/
delete behavior, stale-write and uniqueness handling, and the internal chain
invariant.

It excludes Admin presentation, Content/Navigation integration, analytics, and
import/export.

Acceptance evidence covers fresh schema, idempotent existing-install upgrade,
module install/enable, permission mapping, package inclusion, and persistence
invariant tests.

### WU3 — Admin Redirect Workspace

Objective: deliver minimal configured-path Admin management.

Scope includes list, create, edit, delete, authorization,
authorization-before-CSRF, CSRF, escaped rendering, controlled validation
states, configured Admin path, and responsive Admin presentation.

It excludes search/filter, enabled switch, bulk actions, and Dashboard widget.

Acceptance evidence covers guest, unauthorized, authorized, malformed, CSRF,
duplicate, stale, persistence-failure, configured-path, and escaped-rendering
tests.

### Separate Admin Shell Design-Adjustment Checkpoint

Run after the WU3 Admin surface is available and before final milestone
closure. This is horizontal governance work, not a fifth domain Work Unit.

### WU4 — Public Integration, Hardening, Regression, and Closure

Objective: validate complete M3.10 behavior and close the milestone.

Scope includes public unmatched-GET resolution, explicit route precedence,
reserved namespace behavior, 301/302 response behavior, internal/external
target behavior, security, affected regressions, fresh install,
existing-install upgrade, package verification, the Admin Shell checkpoint
result, documentation closure, and containment readiness.

It excludes automatic Content redirects, Navigation changes, Dashboard widget,
and release/tag/publication.

## AI and human acceptance

AI/objective acceptance covers entity and schema invariants, route precedence,
path normalization, target validation, status handling, chain prevention,
authorization/CSRF, escaped rendering, provisioning, upgrade idempotency,
package inclusion, configured Admin paths, regressions, and failure
containment.

Human review is required only for the separate Admin Shell visual and
presentation checkpoint. HTTP redirect semantics do not require subjective
human acceptance.

## Preparation closure and implementation entry

Preparation closure requires this contract, focused contract checks,
`git diff --check`, and a clean synchronized `main`.

Implementation entry requires separate authorization after preparation closure.
No WU1 implementation begins as part of this contract-lock task.
