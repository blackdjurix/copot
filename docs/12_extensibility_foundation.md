# M2.2 Extensibility Foundation

## Status

Active milestone.

Scope and architecture are locked for repository audit and phased implementation.

Latest completed release:

```text
v0.9.0 — M2.1 Admin UI Foundation
```

Target release for this milestone will be confirmed after the repository audit and batch plan are validated.

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

## Proposed Runtime Contract

The exact class names remain subject to repository audit, but the implementation should remain equivalent to:

```php
interface EventDispatcher
{
    public function listen(string $eventName, callable $listener): void;

    public function dispatch(string $eventName, object $event): object;
}
```

This example is directional, not a mandatory signature.

The audit may recommend explicit event classes, a separate listener provider, or another small contract when that better matches the current codebase.

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

The repository audit must determine whether listener registration belongs in:

* `module.json`;
* `routes.php`-adjacent module bootstrap code;
* a dedicated optional module file;
* a Core-owned registration callback.

No format is approved before the audit compares it with current module-loading behavior.

---

## Event Naming and Versioning

The implementation must choose one documented convention.

Acceptable directions include:

```text
core.application.ready
core.module.enabled
content.published
taxonomy.assignments.updated
```

or explicit event classes such as:

```text
Copot\Core\Events\ApplicationReady
Copot\Modules\Content\Events\ContentPublished
```

Do not mix conventions casually.

Event names or classes become internal compatibility contracts. Renaming them requires documentation and regression coverage.

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

Status: Next.

* inspect existing extension mechanisms and lifecycle wiring;
* identify concrete initial caller/listener pairs;
* choose event naming or event-class convention;
* choose listener contribution format;
* lock ordering and failure behavior;
* confirm exact files and tests;
* update this document only if the audit proves a necessary architecture correction.

No runtime implementation belongs in this batch unless separately approved.

### Batch 2 — Core Dispatcher

* add the minimal Core dispatcher contract and implementation;
* add explicit listener registration;
* add deterministic execution;
* add validation and fail-fast exception behavior;
* add focused unit or smoke coverage.

### Batch 3 — Application and Module Wiring

* create one request-scope dispatcher instance;
* expose it only through the approved application boundary;
* load listeners from enabled modules;
* reject or control malformed listener definitions;
* confirm disabled modules contribute nothing;
* add integration coverage.

### Batch 4 — First Concrete Extension Points

* add only audit-approved lifecycle events;
* provide at least one real listener use case;
* remove any direct coupling made redundant by the approved event;
* preserve existing domain behavior;
* add caller, listener, ordering, and failure tests.

### Batch 5 — Regression and Documentation Completion

* run existing M1 and M2.1 regression suites;
* add unified M2.2 regression coverage;
* manually verify affected admin and frontend flows;
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
* concrete event dispatch at the approved lifecycle point;
* preservation of existing route, permission, CSRF, persistence, and rendering behavior.

If priority is implemented, tests must verify:

* priority precedence;
* stable registration order for equal priority.

---

## Manual Verification

Manual verification should cover only flows affected by implemented events.

At minimum:

* application boot remains successful;
* public frontend routes still render;
* admin login and dashboard still render;
* Content and Taxonomy admin flows still work;
* enabling a listener-owning module activates its contribution;
* disabling it removes its contribution on the next request;
* listener failure produces the approved controlled application behavior;
* public responses expose no stack traces, filesystem paths, credentials, or payload internals.

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
* at least one concrete caller/listener integration proves the capability;
* speculative hooks are absent;
* existing focused registries remain intact unless a documented need justifies change;
* no queue, scheduler, persistence, replay, wildcard, API, webhook, or distributed system has been introduced;
* no database schema or third-party dependency has been added without separate approval;
* existing M1 and M2.1 behavior passes regression verification;
* documentation and CHANGELOG reflect the completed implementation.

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
+ concrete lifecycle events only
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
