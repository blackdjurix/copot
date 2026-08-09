# System Health & Status Contract

## Status and authority

```text
System Health & Status: PROMOTED / PREPARATION
Contract: LOCKED
Implementation: WU1–WU2 COMPLETE / WU3–WU6 NOT STARTED
WU1–WU2: IMPLEMENTATION COMPLETE / VALIDATION COMPLETE
Dependency: INDEPENDENT
Operational consequence: CONTINUE
```

This contract promotes System Health & Status from planning concept into
authoritative repository preparation. WU1 and WU2 implementation are recorded
below; the contract authorizes no WU3–WU6, schema, Dashboard, Module, permission,
production-reconciliation, release, or publication work.

WU1 and WU2 implementation and focused validation are complete. The delivered
foundation is producer-agnostic and WU2 adapts only existing Webcore evidence;
WU3 remains the next separately authorized work unit.

System Health does not depend on Production Webcore Reconciliation,
Server-Empty Bootstrap & Package Clean Install, broad MR.x refinement, or
Module Permission Dependency / Base Access implementation. Completed Package
Lifecycle, Module Package Lifecycle, Portability, Backup & Recovery, and
Multi-Installation foundations are reused as accepted boundaries and are not
reopened without concrete regression evidence.

## 1. Ownership and boundaries

System Health is Webcore/shared-platform owned. Dashboard is a presentation
consumer only. Reporting units own detection, diagnosis, finding semantics,
finding severity, and optional remediation recommendations. System Health
owns receive, validation, normalization, aggregation, prioritization,
sanitization, viewer authorization/filtering, and report construction.

System Health receives authoritative diagnostic results; it must not inspect
private subsystem internals or duplicate subsystem diagnosis.

## 2. Producer boundary

A reporting unit may submit a bounded result containing only the material
semantics required for producer/source identity, availability or adoption
disposition, observation/freshness metadata where applicable, and zero or
more findings.

`producer_version` and public `installation_id` fields are not required by
this contract. Execution, collection, authorization context, caching, and
report construction remain installation-scoped through the accepted
installation context and Multi-Installation boundaries.

Producer dispositions are conceptually:

```text
READY
NOT_APPLICABLE
NOT_ADOPTED
UNAVAILABLE
PRODUCER_ERROR
```

Exact implementation enum names remain open. Missing evidence is never
automatically healthy. `NOT_APPLICABLE` is not a successful health check.
Producer failures are controlled and sanitized; an optional producer's
absence or failure must not break unrelated producers.

## 3. Normalized findings

The minimal conceptual finding contains stable or deterministic identity,
source, optional target/subject, producer-owned code, severity, summary,
optional sanitized detail, optional producer-owned recommended action,
optional approved action target, and observation/freshness metadata where
materially useful.

This contract does not require a generic per-finding `status` field or
`expires_at` field.

Baseline finding severities are:

```text
WARNING
ERROR
CRITICAL
```

Healthy/OK conditions normally produce no finding. Severity remains
producer-owned. System Health may derive an aggregate or overall status but
must not silently rewrite an individual finding's severity.

## 4. Overall status

Baseline overall statuses are:

```text
OPERATIONAL
ATTENTION_REQUIRED
DEGRADED
CRITICAL
```

The conceptual mapping is:

| Evidence | Overall status |
|---|---|
| No material findings and sufficient required evidence | `OPERATIONAL` |
| Highest finding is `WARNING` | `ATTENTION_REQUIRED` |
| Highest finding is `ERROR` | `DEGRADED` |
| Highest finding is `CRITICAL` | `CRITICAL` |

Producer availability/evidence disposition is separate from overall status.
Insufficient required evidence must not be represented as `OPERATIONAL`.
The exact treatment of incomplete evidence remains an implementation
decision within this constraint.

## 5. Remediation and update boundary

Remediation recommendations are optional, producer-owned, advisory, and
non-executable. System Health may sanitize, filter, and present them; it must
not invent or execute remediation.

Generic patch, update, and upgrade availability is outside System Health. A
current state may become a health finding only when its authoritative owner
determines that it is operationally unsupported, unsafe, degraded, or
requires mandatory remediation. System Health must not infer urgency from
version comparison alone.

## 6. Authorization and visibility

Reports are viewer-scoped. Existing Admin authorization remains the outer
boundary where applicable. Each reporting unit owns the policy defining who
may see its findings; Webcore/System Health enforces that policy before a
report is exposed. Dashboard receives only the already-authorized report.

Unauthorized producers or findings must not leak existence or diagnostic
detail. No permission hierarchy such as `edit → view` is inferred. Current
direct permission behavior remains authoritative until a separate permission
decision changes it.

Module Permission Dependency / Base Access is a separate capability and is
not an implementation or promotion dependency for this contract.

## 7. Webcore lifecycle producer

The initial Webcore producer adapts existing authoritative evidence rather
than duplicating diagnosis, including where applicable: committed and
installed lifecycle consistency, database/Core state, migration ledger health
and identity, maintenance and lifecycle-operation state, deterministic
runtime/bootstrap/theme/public/Admin evidence, and accepted package-integrity
evidence.

Lifecycle state is evidence, not automatically equivalent to health. Existing
`HealthGateResult`, `HealthGateMatrix`, committed-state, migration, database,
runtime, maintenance, and integrity primitives are reusable through an
adapter and remain owned by their existing boundaries.

## 8. Bundled and third-party Modules

Initial bundled Core Module adoption belongs to the Webcore baseline/update
that introduces System Health. Every bundled Module is not required to
implement a dedicated reporter. Shared lifecycle, schema, migration, runtime,
and integrity evidence should be used where sufficient.

A Module-specific producer is justified only when that Module owns meaningful
diagnosable conditions not represented by shared lifecycle evidence. Future
Module-specific health behavior belongs to that Module's own update package.
Future third-party Modules may expose compatible health producers through the
public boundary where meaningful; producer adoption remains optional.

## 9. Server / Runtime producer

The baseline Server/Runtime producer is limited to deterministic Copot runtime
requirements and accepted installation/runtime evidence. It may report
required PHP capabilities, extensions, database-driver capability, and
installation-scoped runtime compatibility evidence.

System Health is not generic host monitoring and does not own process,
infrastructure, VM, container, DNS, or operating-system monitoring.

## 10. Cost and freshness

Health evidence must be classified as cheap/synchronous, event-derived,
cached, or operator-triggered, as appropriate.

Normal Dashboard rendering must not trigger full package hashing, broad
filesystem integrity scans, reconciliation-grade diagnostics, destructive
work, or other expensive health work. Expensive package and filesystem
evidence may be cached or explicitly operator-triggered under a later
implementation decision.

## 11. Security and sanitization

System Health must sanitize before report construction. Public findings must
not expose raw exception messages, absolute filesystem/internal paths,
SQL/database details, package contents, lifecycle-operation identities, or
sensitive installation/runtime metadata.

Producer errors must degrade to bounded stable messages and producer-owned
codes. Sanitization is a System Health boundary even when an existing
verifier currently returns a diagnostic exception message.

## 12. Persistence and schema

Initial System Health reporting is derived and read-only. No new health
persistence or database schema is part of the first delivery baseline.
History, durable reports, and cache persistence require a future concrete
requirement and separate decision.

## 13. Dashboard consumer

Dashboard will consume one already-authorized `SystemHealthReport` through
the existing Core/Admin Dashboard contribution boundary. Dashboard may render
bounded status, finding summaries, freshness, availability, and approved
links.

Dashboard must not diagnose subsystems, query private subsystem repositories
for health, authorize findings itself, perform remediation, or expose raw
evidence. The current static `Framework Status` surface is presentation
content and remains so until the Dashboard consumer WU is separately
implemented; it is not current runtime health.

## 14. Multi-Installation isolation

Health collection, any later cache, authorization context, producer result,
and report construction must remain installation-scoped. System Health must
preserve the accepted installation identity, database namespace, runtime
registry, filesystem, session, cookie, lifecycle, and package-staging
isolation boundaries. This contract does not reopen Multi-Installation.

## 15. Work units

WU1–WU2 are complete for the reusable foundation. WU3–WU6 remain `NOT STARTED`:

1. **WU1 — System Health Contract & Aggregation Foundation** — **COMPLETE**
2. **WU2 — Webcore Lifecycle Health Producer** — **COMPLETE**
3. **WU3 — Bundled Core Module Health Adoption**
4. **WU4 — Server / Runtime Baseline Producer**
5. **WU5 — Cross-Producer Acceptance & Report Hardening**
6. **WU6 — Dashboard System Status Consumer**

WU3 is the next technical target only after separate implementation
authorization. This WU2 delivery does not authorize WU3 implementation.

Focused validation: `tests/system_health_wu1.php` passed 23 assertions under
the supported XAMPP PHP 8.5 runtime. PHP lint passed for all WU1 source and
test files. WU2 validation: `tests/system_health_wu2.php` passed 14 assertions;
the WU1 predecessor suite passed again with 23 assertions. PHP lint passed for
all WU2 source and test files.

## 16. Acceptance criteria for later implementation

- producer results validate against the locked minimal boundary;
- missing, not-adopted, unavailable, and failed producers remain distinct;
- missing evidence never becomes healthy;
- finding severity remains producer-owned;
- overall status follows the locked baseline mapping;
- viewer and unit-owned visibility policies are enforced before reporting;
- producer failures are isolated and sanitized;
- expensive checks stay outside normal Dashboard renders;
- no new health schema is introduced without a separate decision;
- Webcore, Module, Runtime, Dashboard, and Multi-Installation boundaries are
  preserved; and
- focused acceptance proves installation-scoped reports and no raw diagnostic
  leakage.

## 17. Open implementation decisions

- Exact producer registration and result enum names.
- Treatment and display of insufficient required evidence.
- Freshness bounds and cache invalidation rules.
- Exact lifecycle-to-severity mapping.
- Approved Dashboard finding-link vocabulary.
- Whether any future health history has a durable retention requirement.

These decisions do not weaken the locked ownership, severity, status,
authorization, cost, security, persistence, or isolation boundaries above.
