# Module Package Identity & Capability Provider

Status: CONCEPT / FUTURE ECOSYSTEM / PLANNING ONLY

This Concept is the current Git-side semantic source for the logical identity
`Module Package Identity & Capability Provider`. It is architecture and
planning guidance, not implementation authority, a contract, or a lifecycle
decision. Future work remains separately planned and authorized.

## Purpose and lineage

Module package identity is distinct from a display title. A package has a
stable technical module name, publisher and provenance information, and a
package identity that can be used independently of presentation copy. This
Concept continues the existing package identity, publisher/provenance,
capability/provider, conflict, migration, and distribution direction. It is
related to Package Lifecycle & Migration and retains a future relationship to
M6 — Distribution & Ecosystem without automatically scheduling that work.

The delivered package and lifecycle foundations remain governed by their
authoritative contracts. The provider and capability semantics below are
future conceptual direction and must not be read as already implemented.

## Capabilities, providers, and consumers

A package may declare capabilities it provides. A consumer may declare
capabilities it requires, rather than depending directly on a concrete
provider package identity wherever a capability contract is sufficient. A
requirement distinguishes at least:

- mandatory;
- optional; and
- fallback-capable.

A capability requirement is not the same thing as a package dependency.
Provider declarations and consumer requirements should also define the
capability contract/version compatibility, provider mode, conflict behavior,
fallback behavior, selection expectations, and lifecycle responsibilities.

Existing conceptual provider modes remain meaningful: Exclusive, Multiple,
Composable, and Selectable. Multiple providers, composition, aggregation, or
handler/contribution behavior require an explicit capability contract; visual
or package co-existence alone is not sufficient.

## Discovery and runtime satisfaction

The future model distinguishes these states:

`installed/discovered provider` != `selected provider` != `active runtime provider`

An installed provider is only a discoverable candidate. A provider becomes
runtime-eligible only when it is enabled or active where its lifecycle
requires, compatible with the capability contract, valid for the consumer,
and selected where explicit selection is required. Discovery never implies
automatic selection or takeover.

Normalized future outcomes may include `FULL`, `DEGRADED`, `BLOCKED`,
`PROVIDER_UNAVAILABLE`, `PROVIDER_SELECTION_REQUIRED`, and
`MIGRATION_AVAILABLE`. These labels are conceptual; exact implementation
enums remain future contract work.

By default, one authoritative provider serves a consumer-capability at a
time. An explicit capability contract may authorize multiple providers,
composition, aggregation, or handler/contribution semantics. No implicit dual
authority or split-brain writes are permitted.

## Provider transition and capability-state migration

A newly installed or newly enabled compatible provider must not automatically
replace the current provider. A provider change is an explicit transition
when authority or capability-owned persisted state is involved.

The future transition may require:

1. compatibility and preflight validation;
2. target-provider readiness verification;
3. capability-owned state mapping;
4. state or data migration;
5. migration verification;
6. controlled or atomic cutover;
7. post-cutover verification; and
8. fallback/source-state retention until the transition is proven safe.

Package installation, provider availability, provider selection, provider
activation, state migration, and authoritative cutover are distinct lifecycle
events. Replacement may include capability-owned configuration, references,
or provider-specific metadata, not only package files.

If transition or migration fails, the candidate must not silently become
authoritative. The current provider remains authoritative while healthy;
fallback behavior is restored or retained where available; dual writes and
partial split ownership are avoided; and enough source state is retained for
safe retry or recovery.

## Developer-facing guidance

Future capability/provider architecture should guide developers on:

- declaring provided capabilities;
- declaring required capabilities;
- contract and version compatibility;
- provider mode;
- fallback behavior;
- provider selection;
- conflicts;
- transition requirements;
- lifecycle and health state;
- migration responsibilities; and
- deterministic failure behavior.

Illustrative examples may clarify these concepts, but this Concept does not
define a concrete manifest schema. Any schema or executable lifecycle contract
requires separate authoritative design and authorization.

## System Health relationship

Provider resolution and provider transitions may expose normalized findings to
System Health. System Health consumes and reports those findings; it does not
own provider selection, transition authority, or migration authority. The
separate System Health Concept and `docs/35_system_health_status_contract.md`
retain ownership of System Health semantics, including its future semantic
extensions, and are not reopened by this Concept.

## Boundaries and future disposition

This Concept does not authorize a resolver, provider registry, manifest/schema
change, lifecycle implementation, state migration, health engine, package
distribution feature, or production action. It does not alter Module
ownership, Content ownership, Media ownership, or the existing Package
Lifecycle contracts. Provider transition, capability-state migration, and
developer-facing declaration guidance remain future planning directions.

The logical identity is intentionally singular: provider transition,
provider migration, capability requirements, and provider health states remain
parts of this Concept's future direction rather than separate duplicate
Concept identities.

## Historical inputs

The following GPT/File Library Concept sources are retained as historical or
correction inputs to this Git-side Concept:

- `copot_module_package_identity_and_capability_provider_concept.md`;
- `copot_module_package_identity_and_capability_provider_concept_260803_220452.md`;
- `copot_update_upgrade_migration_concept.md`.

Repository authority remains in committed contracts, source/tests, and
independently verified remote Git state. The repository Workplan indexes this
Concept but does not authorize implementation or promotion.
