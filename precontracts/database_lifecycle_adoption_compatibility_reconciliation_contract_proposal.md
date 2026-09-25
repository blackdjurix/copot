# Database Lifecycle — Adoption Compatibility Reconciliation Contract Proposal

Status: CONTRACT PROPOSAL / PRE-PROMOTION / NOT AUTHORITATIVE / NOT IMPLEMENTATION-AUTHORIZED

Source Pre-contract:

`precontracts/database_lifecycle_adoption_compatibility_reconciliation_precontract.md`

Primary authoritative reconciliation targets:

- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`

Existing execution foundation retained:

- `docs/28_package_lifecycle_migration_foundation_contract.md`

## 1. Proposal Objective

Promote a target-relative Adoption compatibility model without erasing historical Existing-Runtime Adoption semantics, without creating a new lifecycle operation, and without transferring mutation authority away from existing owner-bounded Package Lifecycle & Migration machinery.

The resulting authority model must distinguish:

- historical exact-match Existing-Runtime Adoption;
- broader future `Adoption` compatibility-establishment workflow;
- terminal Installer `Adopt`;
- existing Update / Upgrade / Repair / Retry / Reconciliation operations;
- Legacy Reconciliation;
- Runtime Handoff.

These identities remain separate even when one is used as a prerequisite or resolution route for another.

## 2. Authority Shape

The proposal uses three authority roles.

### 2.1 `docs/30` — Historical Existing-Runtime Adoption and Legacy Reconciliation Authority

`docs/30` remains authoritative for the capability it actually delivered:

- exact-match Existing-Runtime Adoption;
- committed-state establishment for previously uncommitted runtime;
- deterministic legacy classification;
- Legacy Reconciliation;
- immutable reconciliation planning;
- recovery, confirmation, quiescence, migration, retry, and finalization semantics within that historical boundary.

Its historical exact-match behavior remains true.

The promoted reconciliation must not rewrite those facts as though exact-match was never required.

### 2.2 `docs/37` — Current General Database Compatibility and Installer Adoption Authority

`docs/37` becomes the primary current authority for generalized target-relative database compatibility and Installer Adoption semantics.

Its existing compatibility, ownership, migration authorization, independent schema lineage, fail-closed, Case A/B/C, and System Manager boundaries remain authoritative.

Its current `Adopt` wording must be amended so that:

- terminal `Adopt` itself remains non-mutating;
- broader `Adoption` may establish compatibility before terminal Adopt;
- requirement-resolution may use existing authorized lifecycle machinery;
- resolved compatibility must be positively re-proven before terminal Adopt.

### 2.3 `docs/28` — Execution Foundation

`docs/28` remains the execution foundation for applicable package/lifecycle operations.

No parallel engine is introduced.

Where Adoption requires Update, Upgrade, Repair, Retry, Reconciliation, or migration-backed requirement-resolution, the operation remains classified and executed through existing lifecycle authority.

## 3. Historical Exact-Match Semantics

The following `docs/30` semantics remain valid within their original Existing-Runtime legacy-adoption boundary:

- `Adopt Existing Installation` was delivered as a non-mutating exact-match operation;
- exact-match required authoritative package/release, package-owned inventory, schema baseline, migration-state identity, health/integrity, and installed-state proof;
- mismatch rejected that exact-match operation;
- legacy non-exact state was handled through separately authorized Legacy Reconciliation;
- unknown or unprovable legacy state failed closed.

Promotion must preserve these as historical/current authority for that capability lineage.

However, these semantics no longer define every future database Adoption case.

## 4. Target-Relative Compatibility

For generalized future Adoption, compatibility is evaluated relative to the target runtime/release requirements.

The evaluator must distinguish:

- satisfied Target Requirements;
- Compatible Extra State;
- Requirement Gaps;
- unknown, ambiguous, contradictory, unsafe, or unsupported state.

Exact target-schema identity is not a universal compatibility prerequisite.

A database may be compatible when:

- all mandatory Target Requirements are satisfied;
- additional state is proven compatible;
- ownership remains coherent;
- installation and namespace identity remain valid;
- no unresolved lifecycle or recovery state exists.

## 5. Fill-the-Hole Principle

Missing Target Requirements may be resolved through the Fill-the-Hole principle.

Fill-the-Hole means:

- identify only positively proven missing Target Requirements;
- preserve already-satisfied requirements;
- preserve compatible extra state;
- resolve only the required gap;
- use existing authorized lifecycle machinery;
- re-prove compatibility afterward.

Fill-the-Hole is not:

- a lifecycle operation;
- a migration engine;
- generic reconciliation;
- schema normalization;
- global database upgrade;
- arbitrary SQL authority.

## 6. Adoption vs Adopt

### Adoption

`Adoption` is the broader compatibility-establishment workflow.

It may:

- inspect a candidate installation/database;
- evaluate target-relative requirements;
- classify Requirement Gaps;
- determine valid Resolution Routes;
- orchestrate separately authorized lifecycle operations;
- consume their results;
- re-evaluate compatibility.

Adoption itself does not own database/schema mutation.

### Adopt

`Adopt` remains the terminal Installer/finalization intent.

Terminal Adopt:

- requires positively proven Adoption Readiness;
- preserves installation identity and namespace;
- preserves applicable Administrator/User/Site state;
- does not silently perform Update, Upgrade, Repair, Reconciliation, or migration;
- does not itself provision schema or tables;
- does not create a parallel mutation authority.

## 7. Resolution Route Selection

Resolution Route selection is deterministic by default.

The planner selects the route from authoritative eligibility evidence.

Operator choice may be exposed only when multiple routes are:

- independently valid;
- equally supported;
- equivalent in target outcome;
- equivalent in ownership and safety semantics;
- equivalent in recovery guarantees.

Operator preference must never override lifecycle correctness or authorization.

## 8. Resolution Route A — Pre-Adoption Lifecycle Resolution

When the required resolution depends on existing/source runtime or package context, the applicable lifecycle operation occurs before Adoption continuation.

Examples may include:

- Update;
- Upgrade;
- Repair;
- Retry;
- Reconciliation;
- package-defined same-version schema-forward transition;
- owner-authorized migration path.

After completion, Adoption performs fresh compatibility evaluation.

The prior lifecycle operation does not become an Adoption operation merely because it prepares the database for Adoption.

## 9. Resolution Route B — Adoption-Orchestrated Requirement Resolution

Target-runtime-led orchestration is allowed only when the target execution context can legitimately invoke the existing lifecycle authority.

Eligibility requires at least:

- same recognized installation identity;
- same valid namespace/database target;
- trusted package/release evidence;
- valid underlying lifecycle operation;
- available ownership authorization;
- valid migration/transition path;
- applicable recovery preparation;
- applicable quiescence/mutex safety;
- no incompatible non-terminal lifecycle or Runtime Handoff state.

If these conditions are not satisfied, Adoption must not reproduce or bypass the missing authority.

It must instead require an eligible pre-Adoption lifecycle transition or fail closed.

## 10. Composite Resolution

One Adoption workflow may require zero, one, or multiple requirement-resolution steps.

Composite Resolution is permitted only as orchestration of separately authorized lifecycle operations.

Each mutating step retains its own:

- lifecycle operation identity;
- owner;
- source state;
- target state;
- authorized migration/change set;
- recovery semantics;
- completion/failure state.

Composite Resolution does not create a new composite mutation operation.

After each material step, Adoption may re-evaluate requirements before deciding whether another resolution step remains necessary.

## 11. Adoption Readiness

`Adoption Readiness` is a derived compatibility condition/result, not a durable lifecycle state family.

Adoption Readiness exists only when:

- all mandatory Target Requirements are positively proven satisfied;
- compatible extra state is accepted;
- no unresolved Requirement Gap remains;
- no unknown/unsafe/unsupported state remains;
- no unresolved lifecycle or recovery-required state remains;
- resulting compatibility proof passes.

Machine-readable compatibility result codes may exist without becoming lifecycle states.

## 12. Requirement Gap Boundary

A Requirement Gap exists only when the source state is sufficiently positively classified.

At minimum, the evaluator must be able to establish relevant:

- installation identity;
- namespace;
- owner identity;
- source schema/migration state;
- target requirement;
- supported resolution path.

Unknown, ambiguous, contradictory, or unprovable state must not be mislabeled as a Requirement Gap.

## 13. Legacy Reconciliation Boundary

Legacy Reconciliation remains appropriate when the problem is broader than a positively classified missing Target Requirement.

This includes cases involving:

- uncommitted legacy runtime;
- missing committed lifecycle state;
- package-owned filesystem drift;
- unresolved historical provenance;
- legacy runtime/package convergence;
- source state that cannot yet be trusted as a normal lifecycle-managed installation.

Database non-exactness alone does not imply Legacy Reconciliation.

A positively classified database containing compatible extras or safely resolvable Requirement Gaps may remain within the generalized Adoption compatibility path.

Unknown or unprovable legacy state continues to fail closed.

## 14. Failure and Continuation

When an underlying Resolution Route operation fails:

- Adoption is suspended;
- no subsequent filler may execute;
- Adoption Readiness cannot be produced;
- the underlying lifecycle operation's failure/recovery semantics remain authoritative;
- applicable Repair / Retry / Reconciliation / restore requirements must be resolved through the owning lifecycle authority.

After the underlying state returns to a supported terminal/safe condition, Adoption must perform fresh compatibility evaluation before continuation.

Adoption does not own independent rollback or recovery machinery.

## 15. Resolution Planning Identity

An Adoption orchestration or requirement-resolution plan may have a durable or reproducible identity when materially required for:

- auditability;
- operator confirmation;
- resumability;
- recovery binding;
- deterministic continuation.

That plan identity does not grant mutation authority.

Mutation authority remains attached to each actual underlying lifecycle operation.

No new general Adoption mutation-operation identity is introduced.

## 16. Target Requirement Authority

Release-bound Target Requirements must trace to authoritative owning sources.

For Webcore release compatibility, authoritative requirement sources may include:

- trusted package/release contract;
- declared source-version compatibility;
- migration applicability;
- runtime requirements;
- authoritative capability/schema requirements;
- owner-specific declarations referenced by the release.

Derived metadata may:

- normalize;
- compose;
- project;
- explain;
- aggregate authoritative requirements.

Derived metadata must not invent requirements that are absent from the owning authority.

## 17. Existing Lifecycle Classification Preservation

The existing `docs/37` database lifecycle classification remains intact.

### Case A

Package/version changes and database transition required:

`Update / Upgrade`.

### Case B

Prior authorized transition failed or incomplete:

`Repair / Retry / Reconciliation`.

### Case C

Same package/version with explicitly declared forward schema transition:

bounded package-defined same-version database lifecycle operation.

Adoption does not create Case D.

A Resolution Route must resolve to an already-supported lifecycle authority or fail closed.

## 18. Recovery and Safety Preservation

Any mutating Resolution Route must retain existing applicable safety boundaries:

- immutable or deterministic planning;
- backup/recovery preparation;
- quiescence;
- lifecycle exclusion/mutex;
- ownership authorization;
- migration applicability;
- retry semantics;
- interruption handling;
- final postcondition verification.

Adoption orchestration cannot bypass these because the desired terminal action happens to be Adopt.

## 19. Runtime Handoff Boundary

Runtime Handoff remains separate.

Adoption does not:

- implicitly detach a runtime;
- transfer runtime participation;
- transfer migration authority;
- transfer database ownership;
- bypass Runtime Handoff compatibility checks.

If Runtime Handoff and lifecycle mutation are both material, each must independently satisfy its authoritative gates and must not overlap unsafely.

## 20. Exact-Match Fast Path

Exact-match remains a valid optimized path.

When actual state already satisfies all Target Requirements and other proof gates:

`evaluate`
→ compatibility PASS
→ Adoption Readiness
→ terminal Adopt

No requirement-resolution operation is needed.

This preserves the value of exact-match without retaining it as the universal compatibility criterion.

## 21. Installer Boundary

The future Installer consumes the reconciled model.

Installer may:

- discover candidate installations;
- evaluate Adoption compatibility;
- present satisfied and missing requirements;
- present valid next action;
- orchestrate allowed lifecycle resolution when eligible;
- re-check compatibility;
- perform terminal Adopt after Adoption Readiness.

Installer does not become:

- migration engine;
- schema owner;
- recovery engine;
- lifecycle authority.

Detailed Installer UX remains downstream Installer Refinement I scope.

## 22. Contract Amendment Map

### `docs/30`

Retain:

- historical preparation/delivery state;
- exact-match Existing-Runtime Adoption;
- deterministic legacy classification;
- immutable Legacy Reconciliation planning;
- recovery/quiescence/failure/retry semantics;
- implemented IU1/IU2 history;
- historical exact-match acceptance evidence.

Add a bounded superseding clarification stating:

- its exact-match `Adopt Existing Installation` semantics remain authoritative for the historical Existing-Runtime capability it delivered;
- those semantics do not define generalized future target-relative Adoption compatibility;
- generalized Adoption compatibility is governed by the reconciled database lifecycle authority in `docs/37`;
- Legacy Reconciliation remains separate and applies to legacy/uncommitted/unprovable/convergence cases rather than every non-exact database.

Do not rewrite historical sections to pretend the newer model existed at the time.

### `docs/37`

Amend:

- Compatibility Model;
- Installer intent reconciliation / Adopt;
- relationship to System Manager lifecycle;
- fail-closed compatibility handling.

Add authoritative sections for:

- target-relative compatibility;
- Adoption versus Adopt;
- Requirement Gap / Compatible Extra State;
- Resolution Route;
- deterministic route selection;
- Route A / Route B;
- Composite Resolution;
- Adoption Readiness;
- Compatibility Re-Proof;
- Legacy Reconciliation boundary;
- failure continuation;
- target requirement authority.

Preserve:

- ownership model;
- independent schema lineages;
- migration authorization;
- Case A/B/C;
- System Manager ownership;
- Runtime Handoff separation;
- forward-only rule;
- no global `DB_VERSION`.

### `docs/28`

No architectural rewrite is required.

Reference it as the existing execution foundation for applicable:

- package validation;
- compatibility;
- Update / Upgrade / Repair;
- migration execution;
- recovery/maintenance preparation;
- commit ordering;
- forward-only semantics.

A later amendment to its package requirement metadata may be necessary only if implementation evidence proves the current logical package contract cannot express Target Requirements adequately.

That is an implementation-contract question, not a reason to reopen the lifecycle engine now.

## 23. Explicit Non-Goals

Promotion does not create:

- new lifecycle engine;
- new migration engine;
- new Installer intent;
- global `DB_VERSION`;
- generic Convergence operation;
- generic Update Database operation;
- reverse migration;
- downgrade;
- automatic destructive normalization;
- new durable Adoption lifecycle status family;
- new database ownership model;
- bundled Module implementation;
- Installer Refinement implementation;
- release/tag/publication authority.

## 24. Promotion Acceptance Criteria

The proposal is promotion-ready only when the promoted authority:

1. preserves historical truth in `docs/30`;
2. makes `docs/37` the clear current generalized compatibility authority;
3. keeps terminal Adopt non-mutating;
4. allows broader Adoption to establish compatibility through existing lifecycle operations;
5. makes exact-match a fast path rather than universal criterion;
6. preserves compatible extra state;
7. distinguishes Requirement Gap from unknown/unprovable state;
8. preserves Legacy Reconciliation for genuinely legacy/convergence cases;
9. permits deterministic Route A and Route B;
10. permits Composite Resolution without creating a new mutation operation;
11. treats Adoption Readiness as derived result, not lifecycle state;
12. preserves owner-bounded mutation authority;
13. preserves recovery and fail-closed behavior;
14. preserves Case A/B/C;
15. preserves Runtime Handoff separation;
16. introduces no parallel lifecycle/migration engine;
17. preserves `docs/28` as execution foundation;
18. leaves implementation and Work Unit topology unauthorized.

## 25. Post-Promotion Boundary

Successful contract promotion establishes semantic and architectural authority only.

After promotion:

- implementation delta must be audited against current source and tests;
- current machinery must be checked for Route B feasibility;
- Composite Resolution support must be checked;
- requirement representation in current package/lifecycle primitives must be checked;
- required Work Unit topology must then be derived from observed implementation delta.

Promotion itself does not imply implementation readiness or any specific Work Unit count.
