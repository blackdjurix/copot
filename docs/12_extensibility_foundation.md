# M2.2 Extensibility Foundation

## Status

Complete and released as v0.10.0.

Batch 1 contract lock, Batch 2 Core Dispatcher, Batch 3 enabled-module listener wiring, the unified regression gate, automated-assisted runtime verification, and manual browser verification are complete. First Production Consumer Integration remains deferred to the first milestone that proves a real caller/listener pair and is not part of the v0.10.0 foundation contract.

Copot v0.12.0 is the current stable Webcore baseline. This document remains the authoritative historical contract for M2.2 Extensibility Foundation behavior and boundaries.

---

## Purpose

M2.2 establishes a minimal, predictable extension boundary between Core and enabled modules.

The capability exists to reduce direct coupling where current framework behavior already needs controlled participation from multiple owners. It is not a generic plugin rewrite and it is not permission to add hooks everywhere.

The initial foundation is:

* synchronous;
* in-process;
* request-scoped;
* explicit;
* deterministic;
* shared-hosting-safe;
* dependency-free unless a later approved need proves otherwise.

---

## Problem Statement

copot already contains several contribution and lifecycle patterns:

* enabled modules contribute routes;
* modules contribute permissions through metadata;
* modules contribute Admin navigation;
* modules contribute Admin dashboard widgets;
* themes contribute view overrides and presentation;
* Content optionally integrates with Taxonomy.

These mechanisms prove that extension behavior exists, but they are currently implemented through separate, purpose-specific wiring.

M2.2 must provide only the smallest common runtime boundary justified by current Core and module behavior. Existing focused registries should remain focused registries. They must not be replaced by a universal event bus merely to make the architecture look abstract.

---

## Objectives

M2.2 must:

* define one Core-owned synchronous dispatcher contract;
* define explicit listener registration;
* define controlled module listener contribution;
* prevent disabled modules from contributing listeners;
* provide deterministic listener execution;
* define predictable exception behavior;
* document event naming, payload, ownership, and lifecycle rules;
* add only lifecycle events supported by current concrete use cases;
* preserve existing routes, permissions, CSRF behavior, persistence, rendering, and domain behavior;
* remain deployable on conventional PHP/MySQL shared hosting.

---

## Non-Goals

M2.2 does not include:

* asynchronous event execution;
* background jobs;
* queue infrastructure;
* scheduler infrastructure;
* persistent event storage;
* event replay;
* wildcard event matching;
* distributed messaging;
* external APIs;
* webhooks;
* notification delivery;
* workflow automation;
* a generic plugin framework;
* a service-container rewrite;
* a Router rewrite;
* a Module Manager rewrite;
* package discovery beyond the existing local module model;
* user-facing event or listener management UI;
* database schema changes;
* new third-party dependencies unless separately approved.

---

## Architecture Principles

### Core Ownership

Core owns:

* dispatcher interfaces and implementation;
* listener registration rules;
* event-name or event-class validation;
* ordering behavior;
* exception behavior;
* request-scope runtime wiring;
* module contribution loading.

Modules may contribute listeners only through the approved integration boundary.

Themes do not register listeners. Themes remain presentation-only.

### Synchronous and Request-Scoped

Dispatch occurs during the current PHP request.

A listener finishes before the next listener runs and before dispatch returns.

No listener may assume:

* a queue worker;
* a daemon;
* a scheduler;
* persistent event delivery;
* retry infrastructure;
* cross-request state.

### Explicit Registration

Listeners must be registered explicitly.

The foundation must not scan arbitrary PHP classes, infer listeners from naming conventions, or use reflection-based discovery.

Registration must remain readable from normal application or module wiring.

### Deterministic Ordering

Default listener order must be deterministic.

Priority support may be implemented only if the repository audit finds a concrete ordering need. If priority is added:

* higher or lower numeric precedence must be documented;
* equal-priority listeners must retain registration order;
* ordering tests are required.

### Predictable Failure Handling

Listener exceptions must not be silently swallowed.

The default rule is fail-fast dispatch:

* the current listener exception propagates;
* later listeners do not run;
* Core error boundaries remain responsible for converting uncaught exceptions into safe responses where applicable.

A future event may define a different controlled policy only through an explicit, documented contract.

### Small Payloads

Event payloads must be narrow and documented.

Payloads must not expose the entire application container, database connection, Router, or unrelated services as a convenience shortcut.

Mutable payloads, result aggregation, cancellation, or propagation stopping are not part of the baseline unless a concrete approved event requires one of them.

### Stable Ownership

Every event must define:

* owner;
* purpose;
* dispatch point;
* payload;
* listener expectations;
* failure behavior;
* whether ordering matters.

Core-owned lifecycle events use a Core namespace or naming convention.

Module-owned domain events remain owned by that module even when other modules may listen.

---

## Existing Extension Mechanisms

The repository audit must inspect and preserve the role of:

* enabled module route loading;
* module metadata and permission contribution;
* `AdminNavigation`;
* `AdminDashboardRegistry`;
* theme view overrides;
* Content and Taxonomy optional integration;
* application bootstrap and request lifecycle wiring.

Focused registries remain valid when their contract is clearer than an event.

M2.2 must not migrate them automatically to dispatcher-based behavior.

---

## Batch 1 Event Admission Result

No initial lifecycle event is approved.

The audit found no candidate that currently has both:

* one real caller;
* one real independent listener;
* a narrow stable payload;
* reduced coupling;
* a safe transaction boundary.

Content-to-Taxonomy integration is real coupling, but it spans reads, form composition, validation input, and persistence. Converting only post-save synchronization into an event would retain Taxonomy semantics inside Content and could introduce partial-write risk under fail-fast behavior.

First Production Consumer Integration is therefore deferred to the first milestone that requires it. It is not a blocker for M2.2 foundation completion. M2.2 must not add a dummy event merely to satisfy an acceptance checklist.

The dispatcher and enabled-module contribution boundary are proven end to end with controlled temporary fixtures. Fixture event names are test-only and do not establish production API.

---

## Candidate Concrete Event Areas

The repository audit must verify whether any of these areas justify an initial event:

* enabled-module registration during application bootstrap;
* post-bootstrap readiness after Core and enabled module wiring is complete;
* module lifecycle completion after install, enable, disable, or uninstall registration succeeds;
* Content lifecycle completion where another enabled module currently needs decoupled reaction;
* Taxonomy assignment lifecycle where current direct integration demonstrates a real dependency.

These are candidates, not approved events.

An event enters implementation only when the audit shows:

1. a current caller;
2. a current or immediately required listener;
3. reduced coupling compared with direct service use;
4. a stable payload;
5. a testable dispatch point.

Events with no real listener must not be added as future-proofing.

---

## Locked Runtime Contract

The repository audit approved a stable string-name dispatcher with object payloads:

```php
interface EventDispatcher
{
    public function listen(string $eventName, callable $listener): void;

    public function dispatch(string $eventName, object $event): void;
}
```

Contract rules:

* event names use lowercase dotted segments;
* listener execution follows registration order;
* listener priority is not part of M2.2;
* dispatch with no listeners succeeds as a no-op;
* duplicate explicit registrations are allowed and execute independently;
* the same payload object is delivered to every listener;
* invalid event names or listener definitions raise `InvalidArgumentException`;
* listener exceptions propagate unchanged;
* later listeners do not execute after a fail-fast exception;
* one dispatcher instance belongs to one `Application` instance/request.

The final design must avoid:

* global static state;
* hidden auto-discovery;
* arbitrary container access;
* wildcard listeners;
* string payload arrays with undocumented keys;
* silent exception suppression.

---

## Module Integration Boundary

Only installed and enabled modules may contribute runtime listeners.

Module listener contribution should follow the existing enabled-module loading path rather than introducing a second independent module discovery system.

A disabled module must not:

* register listeners;
* receive dispatched events;
* alter Core runtime behavior.

Malformed listener definitions must fail in a controlled and diagnosable way without exposing sensitive filesystem or environment details to public responses.

The repository audit approved one optional module metadata field:

```json
{
  "listeners": "listeners.php"
}
```

The dedicated file returns an approved event-to-callable map:

```php
return [
    $approvedEventName => $listener,
];
```

Rules:

* only installed and enabled modules are inspected;
* the existing enabled-module order remains authoritative;
* the path must resolve safely inside the module directory;
* a declared file must exist and return an array;
* every key must be a valid event name;
* every value must be callable;
* one callable per event per module keeps the format unambiguous;
* malformed declared contributions fail with a sanitized module-specific runtime exception;
* disabled modules contribute nothing on the next request;
* listener contribution is controlled, but listener code remains trusted application code;
* `$app` may be available through include scope, matching existing trusted route-file wiring;
* this is not sandboxing and does not restrict service access inside trusted local module code.

---

## Event Naming and Versioning

M2.2 uses stable lowercase dotted string names with object payloads.

No production event name is introduced or reserved by the foundation itself. Each future production event must be defined by the first consumer milestone with a real caller/listener pair and must document its owner, dispatch point, payload, listener expectations, ordering, and failure behavior.

Temporary fixture event names exist only inside automated tests. They are not production API and must not be copied into production documentation as established lifecycle hooks.

Once introduced by an approved consumer milestone, an event name becomes an internal compatibility contract. Renaming it requires documentation and regression coverage.

Public semantic versioning guarantees for third-party extensions are not established by M2.2 alone.

---

## Security and Isolation

Listeners run with the privileges of application code and therefore must remain trusted local code.

M2.2 does not provide sandboxing.

Listeners must not bypass:

* permission checks;
* CSRF checks;
* validation;
* repository rules;
* module enablement rules;
* theme isolation;
* safe public error handling.

An event must be dispatched only after prerequisite validation and authorization have succeeded unless its documented purpose explicitly concerns failure observation.

Sensitive values such as credentials, password hashes, raw environment values, CSRF tokens, or database connection details must not be included in event payloads.

---

## Batch Plan

The final batch plan may be refined after the repository audit, but implementation should remain within these boundaries.

### Batch 1 — Repository Audit and Contract Lock

Status: Complete.

* inspect existing extension mechanisms and lifecycle wiring;
* identify concrete initial caller/listener pairs;
* choose event naming or event-class convention;
* choose listener contribution format;
* lock ordering and failure behavior;
* confirm exact files and tests;
* update this document only if the audit proves a necessary architecture correction.

No runtime implementation belongs in this batch unless separately approved.

Batch 1 locked these decisions:

* stable lowercase dotted string event names;
* object payloads;
* synchronous request-scoped dispatch;
* registration-order execution;
* no listener priority;
* successful no-op dispatch when no listeners exist;
* duplicate explicit registrations execute independently;
* unchanged exception propagation with later listeners skipped;
* one optional module metadata entry pointing to `listeners.php`;
* enabled modules only;
* trusted local listener code with controlled contribution, not sandboxing;
* First Production Consumer Integration is deferred until one real caller/listener pair has a narrow payload and safe transaction boundary; it is not a blocker for foundation completion.

### Batch 2 — Core Dispatcher

Status: Complete.

* add the minimal Core dispatcher contract and implementation;
* add explicit listener registration;
* add deterministic execution;
* add validation and fail-fast exception behavior;
* add focused unit or smoke coverage.

Implemented through the Core `EventDispatcher`, `SynchronousEventDispatcher`, and Batch 2 smoke coverage.

### Batch 3 — Application and Module Wiring

Status: Complete.

* create one request-scope dispatcher instance;
* expose it only through the approved application boundary;
* load listeners from enabled modules;
* reject or control malformed listener definitions;
* confirm disabled modules contribute nothing;
* add integration coverage.

Implemented with one dispatcher per `Application`, optional listener metadata, enabled-only controlled loading before module routes, sanitized validation failures, and temporary fixture integration coverage.

### Batch 4 — First Production Consumer Integration

Status: Deferred to the first milestone that requires it. Not a blocker for M2.2 completion.

When a real consumer appears, that milestone must:

* identify one real caller and one real independent listener;
* approve a narrow payload and safe transaction boundary;
* add only the required production event;
* remove direct coupling only where the event genuinely replaces it;
* preserve existing domain behavior;
* add caller, listener, ordering, and failure tests.

M2.2 does not add placeholder production events while no such consumer exists.

### Batch 5 — Regression and Completion Preparation

Status: Complete. Automated regression, documentation, runtime verification, and manual browser verification pass.

* run existing M1 and M2.1 regression suites;
* add unified M2.2 regression coverage;
* manually verify affected admin and frontend flows before release readiness;
* verify disabled-module behavior;
* verify safe error handling;
* update README, roadmap, this document, AGENTS, and CHANGELOG for completion.

---

## Test Strategy

Automated coverage must verify at minimum:

* listener registration;
* dispatch to one listener;
* dispatch to multiple listeners;
* deterministic order;
* duplicate registration behavior;
* invalid event or listener rejection;
* event payload delivery;
* listener exception propagation;
* later listeners do not run after a fail-fast exception;
* request-scope isolation;
* enabled-module listener contribution;
* disabled-module non-contribution;
* malformed module contribution handling;
* end-to-end dispatcher and enabled-module wiring through controlled temporary fixtures;
* preservation of existing route, permission, CSRF, persistence, and rendering behavior.

Listener priority is not part of M2.2.

---

## Manual Verification

Manual verification is complete. It covers the foundation wiring and preserved application flows; there is no production event flow in M2.2.

At minimum:

* application boot remains successful;
* public frontend routes still render;
* admin login and dashboard still render;
* Content and Taxonomy admin flows still work;
* enabling a listener-owning module activates its contribution;
* disabling it removes its contribution on the next request;
* controlled listener failure behavior remains fail-fast when exercised directly;
* public responses expose no stack traces, filesystem paths, credentials, or payload internals.

Verification record:

* public home rendered successfully;
* Admin login and dashboard rendered successfully;
* Content and Taxonomy admin routes remained available with their existing guards;
* Admin CSS loaded successfully;
* keyboard navigation and visible focus passed;
* responsive layout and browser zoom passed;
* `admin.access` denial passed with a real user;
* malformed listener contribution behavior passed with `display_errors=Off`;
* public output exposed no sensitive filesystem, credential, environment, stack-trace, or payload details.

---

## Acceptance Criteria

M2.2 is complete when:

* the Core dispatcher contract is minimal and documented;
* registration is explicit;
* dispatch is synchronous and request-scoped;
* listener execution is deterministic;
* exception behavior is documented and tested;
* enabled modules can contribute listeners through one controlled boundary;
* disabled modules contribute no listeners;
* controlled temporary fixture coverage proves dispatcher-to-enabled-module wiring end to end;
* fixture event names remain test-only and are not treated as production API;
* production events remain demand-driven and are deferred until one real caller/listener pair exists;
* speculative hooks are absent;
* existing focused registries remain intact unless a documented need justifies change;
* no queue, scheduler, persistence, replay, wildcard, API, webhook, or distributed system has been introduced;
* no database schema or third-party dependency has been added without separate approval;
* existing M1 and M2.1 behavior passes regression verification;
* documentation and CHANGELOG reflect the completed implementation.

First Production Consumer Integration is not an M2.2 completion criterion. It belongs to the first later milestone that requires a production event.

---

## Completion Record

M2.2 delivers:

* one synchronous request-scoped dispatcher per `Application`;
* stable lowercase dotted string event names with object payloads;
* explicit listener registration and registration-order execution;
* no listener priority;
* successful no-listener dispatch;
* independent duplicate explicit registrations;
* unchanged fail-fast listener exception propagation with later listeners skipped;
* one optional metadata-declared `listeners.php` contribution boundary for installed and enabled modules;
* no listener contribution from disabled or merely installed modules;
* listener path containment, declared-file, return-map, event-name, and callable validation;
* controlled temporary fixture proof covering dispatcher-to-module wiring end to end;
* no production lifecycle event or speculative hook;
* passing unified regression, automated-assisted runtime verification, and manual browser verification.

The first production caller/listener integration remains demand-driven and belongs to the first consumer milestone that requires it. M2.4 Platform Hardening remains a separate milestone.

---

## Deferred Direction

Future milestones may build on M2.2 for:

* notifications;
* workflow and automation;
* search indexing reactions;
* cache invalidation;
* audit logging;
* asynchronous jobs;
* external integration delivery.

Those capabilities must define their own persistence, retry, security, delivery, and operational contracts. M2.2 does not pre-build them.

---

## Decision Summary

The locked M2.2 direction is:

```text
small synchronous dispatcher
+ explicit listeners
+ controlled enabled-module contribution
+ fixture-proven foundation wiring
+ demand-driven production events only
+ deterministic fail-fast behavior
```

It is not:

```text
universal hook system
+ queue
+ scheduler
+ persistent event store
+ webhook platform
+ plugin-framework rewrite
```

M2.4 Platform Hardening remains a separate milestone and is not moved into this completion contract.
