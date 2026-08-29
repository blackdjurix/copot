# System Health & Status

Status: PROMOTED FOUNDATION COMPLETE / CLOSED; FUTURE SEMANTIC EXTENSION
PLANNING ONLY

This Concept is the current Git-side semantic source for the logical identity
`System Health & Status`. The delivered foundation remains governed by
`docs/35_system_health_status_contract.md` and is complete, closed, and
contract locked. The future direction below does not reopen or supersede that
authority and does not authorize implementation.

## Delivered foundation

The System Health foundation is `COMPLETE / CLOSED / CONTRACT LOCKED` under
`docs/35_system_health_status_contract.md`. Historical candidate WU1–WU6
planning is provenance only; it is not a pending implementation roadmap.

The accepted foundation preserves these boundaries:

- Webcore/shared platform ownership;
- the reporting unit owns diagnosis;
- the reporting unit owns any optional remediation recommendation;
- System Health receives, normalizes, aggregates, prioritizes, sanitizes,
  authorizes, and reports findings;
- Dashboard is a presentation consumer only;
- problem-first health UX;
- generic update availability is not health;
- viewer-scoped filtering;
- producer availability is distinct from subsystem health;
- no automatic remediation;
- freshness and cost awareness;
- deterministic reuse of lifecycle evidence;
- consumer-neutral reporting; and
- no inference of healthy state from absent evidence.

## Future hybrid evidence model

Future System Health semantics may combine active inspection with event-derived
evidence.

### Pull / active inspection

System Health or a registered producer may evaluate deterministic conditions
when appropriate. Potential triggers include lifecycle-triggered,
operator/on-demand, selectively periodic, and consumer-triggered checks proven
cheap and safe. Examples include runtime requirement validation, committed
lifecycle-state validation, bounded provider-resolution consistency, and
stale or incomplete transition-state detection.

Pull checks must respect cost and freshness classification. Dashboard rendering
must not trigger expensive or destructive audits.

### Push / event-derived evidence

Subsystems may expose normalized health-relevant findings when authoritative
state changes, including install/update/repair completion, enable/disable
transitions, provider-resolution changes, provider-transition migration
start/failure/success, capability degradation, lifecycle failure, or
compatibility loss.

System Health consumes the health-relevant result. The producer/domain remains
the diagnosis owner.

## Capability and provider health integration

System Health may consume normalized findings from the separate capability and
provider architecture. Relevant conditions may include:

- a mandatory capability has no usable provider;
- a selected provider is disabled, inactive, or incompatible;
- providers claim conflicting authority;
- a capability is degraded or blocked;
- provider selection is required;
- a provider transition is available;
- migration is in progress or failed;
- cutover is incomplete;
- fallback was restored after failure; or
- stale/orphaned provider state or split-brain/dual-authority risk is detected.

Not every provider lifecycle state is automatically unhealthy. For example,
`MIGRATION_AVAILABLE` may be informational unless the current provider is
degraded, unsupported, or unsafe.

The related Concept
`concepts/copot_module_package_identity_and_capability_provider_concept.md`
owns provider resolution, transition, and migration semantics. System Health
consumes their normalized health consequences and does not duplicate or own
those lifecycle rules.

## Ownership boundary

System Health does not own:

- provider discovery;
- provider selection or activation;
- provider transition;
- capability-state migration;
- cutover;
- fallback selection; or
- remediation execution.

Those responsibilities remain with the owning capability/provider lifecycle
subsystem. When provider or capability state becomes unhealthy, that owner
diagnoses, determines domain-specific classification/severity, and may provide
remediation guidance. System Health normalizes and aggregates the finding;
consumers render it.

## Safe provider context

A provider-related finding may carry safe, normalized context such as
capability identity, requirement type, current provider, provider source or
class, active/inactive state, compatibility state, fallback availability,
migration state, degradation or blocking impact, and a producer-authorized
remediation hint or action target. Exact serialization and enum names remain
future contract work.

## Trigger, freshness, and quiet healthy state

The existing cost/freshness model remains authoritative. Future evidence may
be classified as a synchronous cheap check, event-derived authoritative
evidence, cached/recent evidence, operator-triggered inspection, or
selectively periodic inspection.

Periodic inspection is appropriate only where freshness materially matters,
cost is acceptable, the condition can change independently of lifecycle
events, and the producer/domain can evaluate it safely. This does not turn
System Health into background monitoring or observability infrastructure.

The problem-first rule remains: healthy systems should stay quiet. Provider
integration must not create a permanent inventory of healthy providers; only a
materially useful healthy-state summary may be exposed.

## Non-goals and authorization boundary

This Concept does not define or authorize a provider resolver, migration
engine, generic event bus, log aggregation platform, telemetry/APM, uptime or
metrics platform, arbitrary polling framework, automatic remediation engine,
automatic provider migration, or automatic provider takeover.

It does not merge Module Package Identity & Capability Provider ownership into
System Health, change Dashboard ownership, reopen the delivered foundation, or
authorize schema, runtime, production, or release work.

## Historical inputs

The GPT/File Library Concept source remains historical/current semantic input
for reconciliation:

- `copot_system_health_status_concept_260809_210842.md`;
- `copot_system_health_status_concept_260805_083932.md`;
- `concept_future_widget_layout_260810_060139.md`;
- `Future_Widget_Layout_Contract_260804_161738.md`.

Repository contracts, committed source/tests, and independently verified
remote Git state remain authority for delivered/current lifecycle truth. The
repository Workplan indexes this Concept but does not authorize implementation
or promotion.
