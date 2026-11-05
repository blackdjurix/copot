# COPOT — V3 Simplification Foundation & C0–C7 Capability Roadmap Concept
Date version: 2026-11-05 15:38:42 WIB
Status: USER-ACCEPTED FOUNDATION DIRECTION / DETAIL CONCEPT MATERIALIZED / NON-NORMATIVE / C2–C7 EXECUTION TOPOLOGY PROPOSED
Parent objective: Alternative C — Fully Operational Canonical V3 Package Lifecycle and Installation-Scoped Maintenance Guard
Repository planning index: `workplan.md`, “Alternative C — V3 Simplification C0–C7 Roadmap (Planning Candidate)”
Implementation anchor: `codex/alternative-c-slice-1a@01ba0f033d779d589a3d26da2ff31e6c863c23aa`
Source baseline: Ghost Update Checkpoint C `a0b801c81ab15945a708137cea2007a018787b95`

## 1. Purpose, status, and authority

This Concept consolidates the user-accepted V3 Simplification foundation direction and records a detailed, **proposed** progressive C0–C7 capability map. It is a planning and semantic technical-design reference during Alternative C's accepted Controlled Contract Breach strategy. It does **not** become a normative Contract, waive applicable safety gates, grant execution authority, reclassify evidence, or convert C2–C7 into accepted Work Units. The original Alternative C objective remains unchanged; V3 Simplification is not a new parent workstream.

Distinguish: (a) previously accepted decisions, (b) verified or executor-reported behavior, (c) candidate technical architecture, and (d) future acceptance gates. C0 is formally CLOSED within its preparation scope. C1 substantive advancement is PAUSED; C2–C7 are PROPOSED/REVISABLE. A detail proposal here is not authorization to implement it.

## 2. Fundamental design principles

### 2.1 V3-forward, historical evidence preserved

V3 is the intended forward package/lifecycle architecture. Legacy v1/v2 engines are not presumed permanent V3 runtime dependencies, but older installed-state records, executed artifacts, provenance, and independently applicable compatibility obligations remain immutable historical evidence. Existing-install V2→V3 authority must be established from verifiable current state, not fabricated committed inventory. “V3-only” does not mean an unproven legacy-retirement claim, automatic deletion, or retroactive conversion of old packages.

### 2.2 Capability-driven simplification

Only invoke checks and subsystems applicable to the actual operation. Filesystem-only execution with no real database requirement must not silently depend on DB migration, capture, health, ledger verification, or global quiescence. Actual package/target/database dependencies must still be verified and fail closed if unproven. Complexity from old attempt-aware historical reconstruction is not automatically mandatory for a newly identified, properly captured operation. Simplification is dependency elimination, **not** a relaxation of recoverability or ownership protection.

### 2.3 Package declares WHAT; Lifecycle determines eligibility and HOW

Package describes identity, supplied payload, compatibility, requirements, source-state conditions, explicit mutations, canonical target inventory and expected resulting state. Lifecycle derives mutation classification and authoritative plan from both package claims and observed installation state. Self-consistent SHA proves integrity relative to supplied evidence, not official publisher authenticity. Package declarations alone confer no mutation permission.

### 2.4 Preflight is an operational gate

Preflight validates actual target installation/source state, artifact and manifest integrity, requirement/compatibility relevance, ownership, path containment, explicit action, source-relative preconditions, protected resource policy, applicable database scope, Guard and BnR readiness, and target resulting-state evidence. Preflight is not merely reading a ZIP. Time-sensitive observations require revalidation before first mutation; stale plan/authority fails closed.

### 2.5 Explicit mutation, resulting-state proof, commit last

Filesystem actions are `ADD`, `REPLACE`, `REMOVE`, `RENAME`. Tree differences do not authorize destructive actions. The authorized action plan must link to recovery captures, and each mutation must match an owned, physically contained target. Verify installed target integrity and any applicable capability health/ledger checks; advance committed installed state only after success of all applicable gates. Failed mutation must not be reported as a successful commit.

### 2.6 Installation-scoped protection with distinct operation domains

Guard excludes registered COPOT-controlled writer paths for the **one verified installation**. It is not a MariaDB server lock, does not exclude external/DBA writers, and is not proof of consistent database snapshots. File, database, and combined operations use distinct relevant evidence; cross-filesystem/database atomicity is never assumed. No global server quiescence as implicit File Cycle dependency. Other installations sharing XAMPP/MariaDB remain unaffected.

### 2.7 Protected resources

Physical `PUBLIC_ROOT/.htaccess` is deployment-owned and subject to COPOT-scope `ABSOLUTE_IMMUTABILITY`: no create, replace, remove, rename, restore, or rollback mutation by COPOT. Read/inspect/verify/routing-compatibility admission remain applicable. Old package-owned references remain historical evidence, not an active mutation license. Generalized Resource Policy framework and wholesale ownership migration remain deferred.

## 3. Webcore peer services and ownership

**CP-01 Maintenance Guard:** installation identity, writer admission, protection session authority, installation exclusion/Mutex coordination, relevant in-flight drain and safe release. Applicable to package operations and other registered Webcore writers (e.g. content/media/navigation/CLI); complete coverage is NOT YET VERIFIED.

**CP-02 Package Lifecycle:** package intake/admission, compatibility and inventory interpretation, deterministic source-relative plan, explicit execution, resulting-state verification, installed-state finalization and lifecycle outcome/retry. It consumes Guard and BnR; it does not own their general domains.

**CP-03 Backup & Recovery (BnR):** original-state capture, absence evidence, scoped recovery inventory/manifest and artifacts, restore, recovery verification, domain-specific recovery disposition. BnR may support authorized Webcore operations independent of Package Lifecycle. It requires appropriate scoped Guard/protection authority **and** separate backup/restore operation authorization.

These are accepted **semantic direction** as peer Webcore services, not proof of physically separated implementations. Distinguish current implementation owner from candidate intended owner. Do not reorganize classes wholesale merely to match this diagram; reconcile specific ownership conflicts incrementally when actual safety blocker, proven dependency or authority ambiguity appears. T-C01–T-C08 temporary capability ladders do not become mandatory implementation gates.

## 4. Common operation and evidence model

Logical success path: **preflight → acquire scoped protection → verify/capture original state → authorize mutation → mutate → verify resulting state → finalize → verify safe terminal state → release protection**. Phase placement may differ according to accepted domain contracts, but capture must complete before first mutation.

Failure path: **detect and persist failure → establish eligible recovery/disposition and its independent authority → restore or safe fail-closed disposition → verify actual state → release only if verified safe**. A failed/corrupt/mismatched capture is not license to mutate or release unsafely.

Persist or otherwise verifiably bind installation, source/package, target state, action/plan, operation, attempt where applicable, recovery set and scoped protection session. Avoid cross-operation/cross-attempt/cross-installation artifact reuse and synthetic historical provenance. `ADD` requires original absence proof; overwrite/remove/rename need original bytes and target/destination mapping. Restore must be scoped to actual mutated resources. Represent `SUCCESS`, restored failure, unresolved recovery requirement, and proven no-mutation disposition truthfully; conceptual vocabulary here does not define new database enums.

Temporary `LifecycleTrace` is per-SDR cycle/human-acceptance support only; never substitutes committed lifecycle identity, verified recovery artifacts, canonical Activity Journal, or actual operational acceptance. Remove/dispose of temporary instrumentation only under the accepted end-of-simplification cleanup gate.

## 5. Progressive capability and evidence reuse rules

A C-label describes a *candidate proof boundary*, not a required series of mechanical implementation slices. Before beginning any later stage, classify already accepted evidence as directly reusable, reusable with bounded revalidation, or inapplicable. Revalidate only material changes and genuinely unproven failure/acceptance cases. Hard safety requirements apply as soon as the operation uses them, even if their comprehensive hardening is listed at C6.

Each execution slice requires its own authorization (target/source, operation and actor), preconditions, safety controls, evidence, and exit assessment; successful validation is not automatic publication, deployment, user acceptance or parent Alternative C closure. Runtime is evidence, not the durable source authority.

## 6. C0–C7 detailed capability planning

### C0 — File Cycle preparation (historical accepted closure)

**Objective:** establish an integrated, DB-free V3 source/fixture preparation baseline for `ADD/REPLACE/REMOVE/RENAME`, including normal execution, bounded failure and deterministic recovery/retry.

**Entry and provenance:** Ghost Checkpoint C `a0b801c...`; accepted First File Cycle scope 2026-11-02; resulting C0 source `01ba0f033d779d589a3d26da2ff31e6c863c23aa` on `codex/alternative-c-slice-1a`.

**Accepted evidence:** Codex-reported four normal cases (35 assertions), sixteen negative/recovery cases (155 assertions), nine DB-free preparation assertions, targeted lint/regression and trace behavior; publication remotely verified. GPT did not independently rerun local executor tests. `docs/devlog.md` 2026-11-05 08:55:06 WIB records formal acceptance.

**Boundary:** C0 is a *preparation/source capability gate*; not proof of deployment, real SDR package advancement, database recovery, or Alternative C closure. Preserve its accepted evidence and do not reopen without material contradiction.

**Exit:** COMPLETE / ACCEPTED / FORMALLY CLOSED. No new C0 execution authorized by this Concept.

### C1 — Historical source advancement reconciliation + first operational File Cycle

**Objective:** maintain original C1 historical source preparation/advancement identity while establishing first real target-bound V3 filesystem-only operation on an authorized SDR.

**Entry:** frozen C0 source; exact baseline target; verified Checkpoint C original-state/installation identity; authenticated package eligibility and source-relative plan; approval for any actual package mutation. Canonical SDR role `v3simp_01ba0f03`, APP_ROOT `C:\xampp\copot\sdr\v3simp_01ba0f03`, PUBLIC_ROOT `C:\xampp\htdocs\v3simp_01ba0f03`, DB `copot_sdr_v3simp_01ba0f03`; baseline reported version 0.13.0, V2 committed manifest, 19 DB tables, HTTP 200. These are executor-reported pre-state, not authorization for apply.

**Blocking questions to resolve, without synthetic evidence:** (1) V2-installed committed source-inventory establishment under trusted current-state authority; (2) real filesystem-only eligibility, including declared MySQL/migration requirements when applicable; (3) explicit ownership/plan disposition for inferred `.copot/package.json` removal, not inferred deletion authorization; (4) operation-bound original bytes/absence capture, recovery linkage and rollback readiness.

**Candidate proof:** target-bound preflight with protected `.htaccess`; Guard protection and BnR capture; actual declared applicable filesystem action(s), original-state recovery after controlled failure, resulting-state verification, truthful finalization and safe release; no DB mutation under a proven DB-free operation. Test only actual C1 coverage gap relative to C0, without fabricating a success for an unexecuted action.

**Exit candidate:** operational File Cycle and recovery evidence on exact SDR, consistent installed/committed state, no unhandled recovery debt, user acceptance. **Current status: SOURCE/TARGET PREPARATION PARTIAL; SUBSTANTIVE ADVANCEMENT PAUSED / NOT AUTHORIZED FOR APPLY.**

### C2 — Multi-File Cycle (proposed)

**Distinct objective:** prove that several declared filesystem actions are *one recovery-coupled operation*, not merely multiple individually successful actions.

**Entry candidate:** accepted operational C1 state and sufficient multi-action plan/recovery identity; explicit target authorization. Check whether C0 integrated fixtures or C1 already prove these properties; collapse C2 if fully covered.

**Candidate scenarios:** mixed `ADD`+`REPLACE`+`REMOVE`+`RENAME` with different APP_ROOT/PUBLIC_ROOT destinations; deterministic action ordering; original absence/bytes across all affected targets; failure at early/middle/late cursor; restore *whole operation* to verified pre-state; conflicting duplicate target and rename-path constraints fail closed; final target inventory/fingerprint.

**Safety:** one scoped Guard authority and BnR set bound to entire plan; no partial-success committed advancement; preserve .htaccess. **Exit candidate:** verified multi-action success and full-operation failure disposition. Status: PROPOSED / NOT AUTHORIZED.

### C3 — Feature/Capability Cycle (proposed)

**Distinct objective:** prove a coherent Webcore feature/capability can transition through a package operation with multiple files, dependency/compatibility relationships and resulting-state verification.

**Entry candidate:** actual selected bundled/Webcore capability and scoped package ownership; C2-like operation proof or already equivalent evidence. Custom third-party modules are not silently pulled into scope.

**Candidate scenarios:** relevant service/controller/view/config and module-owned package files; partial target release with unchanged retained files properly represented by canonical target inventory; feature-level compatibility/health verification; fail on missing dependency or incomplete resulting state; rollback preserves source capability state.

**Safety:** do not equate number of files with feature-level proof; no automatic new UI, permission or ownership refactor. **Exit candidate:** one authorized feature-level transition with successful and failed path evidence plus user acceptance where applicable. Status: PROPOSED / NOT AUTHORIZED.

### C4 — Database Cycle (proposed)

**Distinct objective:** prove an explicitly scoped database operation independent of File Cycle using actual schema/data/migration requirements and BnR database recovery guarantees.

**Entry candidate:** exact database installation identity, owned physical tableset, migration declaration and compatibility; independent namespace-scoped consistency proof; scoped Guard admission for COPOT writers; separately authorized DB mutation and restore; no external-writer safety assumptions.

**Candidate scenarios:** additive schema/migration first where applicable; explicit existing-object collision behavior; data/schema/ledger before-after verification; operation failure during migration; restore test of original database without touching other installations; false/missing consistency proof blocks before mutation. Non-additive/destructive migration needs its own separately accepted specification.

**Safety:** Guard is not full database writer exclusion; server-global read_only/quiescence cannot be introduced silently. Do not claim database snapshot and schema consistency from Mutex alone. **Exit candidate:** isolated DB operation with verifiable schema/data/ledger and restore result. Status: PROPOSED / NOT AUTHORIZED.

### C5 — Combined File + Database Cycle (proposed)

**Distinct objective:** safely coordinate a package operation involving both filesystem and database mutation domains with truthful partial-failure disposition.

**Entry candidate:** verified File Cycle and Database Cycle guarantees, exact package/domain scope, compatible recovery identities, bounded authorization and agreed ordering.

**Candidate scenarios:** files succeed then DB fails, DB succeeds then files fail if that ordering is supported, interruption between domains, corruption/ambiguity of one recovery set, retry after partial progress, final success only after both domain postconditions are satisfied.

**Safety:** no assumed cross-domain atomicity; design explicit recovery order, state identity and compensation/restore evidence. Do not collapse separate BnR domain proofs into an invented universal rollback transaction. **Exit candidate:** cross-domain operation and negative-path recovery independently verified. Status: PROPOSED / NOT AUTHORIZED.

### C6 — Operational hardening (proposed)

**Distinct objective:** validate operational robustness of proven capabilities across applicable runtime lifecycle and failure modes, without postponing mandatory safety validation from earlier cycles.

**Entry candidate:** representative accepted operations and known unresolved risk register; justified extra test runtime only if needed and authorized. Reuse main SDR checkpoints but never assume SDR is a durable backup or overwrite an unrelated runtime.

**Candidate matrix:** process termination/restart, exact attempt-bound retry, concurrent operations exclusion, stale preflight/Guard recovery, persistent recovery-required state, safe release after failure, registered HTTP/CLI/scheduled/background write admission coverage, cross-installation isolation, fresh install and existing-install compatibility, routing behavior with untouched `.htaccess`, operational visibility and temporary trace limitations. External direct DB writers remain an explicitly bounded limitation unless independent consistency is established.

**Exit candidate:** risk-weighted applicable multi-runtime and interruption validation, unresolved limitations explicitly classified; no mandatory replay of every accepted focused fixture. Status: PROPOSED / NOT AUTHORIZED.

### C7 — Alternative C closure evaluation (proposed label only)

**Distinct objective:** evaluate original Alternative C objectives and existing normative closure criteria against accepted evidence; this label does not itself create new project-level closure authority.

**Entry candidate:** accepted applicable operational capabilities, deployment/installed-state/evidence reconciled, C0–C6 dispositions (completed, collapsed as redundant, deferred, rejected, or unresolved) recorded without fabrication.

**Closure questions:** is canonical V3 Package Lifecycle operational end to end; is installation-scoped Guard evidenced across applicable COPOT-controlled writers; is BnR recoverability and source/target identity proven; are multi-runtime effects and legacy-dependent residuals classified; are protected resources immutable; are Controlled Contract Breach deviations accounted for; what outstanding normative amendment and post-closure reconciliation debt remains?

**Boundary:** closure is GPT/user decision under Rule and existing accepted contracts; no auto-success from passing C6. Accepted strategy sequences cumulative normative contract reconciliation **after** Alternative C closure, without excusing independent operation-level safety or authorization.

**Exit candidate:** explicit evidence-based acceptance/closure decision, or OPEN/BLOCKED with clear unresolved criteria and next target. Status: PROPOSED / NO CLOSURE AUTHORITY.

## 7. Acceptance and status discipline

Use separate states for (1) proposal, (2) technical readiness, (3) source publication, (4) deployment/operation, (5) validation evidence, (6) user acceptance, (7) cycle closure, and (8) parent Alternative C closure. Evidence statuses must distinguish VERIFIED, executor REPORTED, INFERRED and UNVERIFIED; an accepted preparation suite does not prove actual SDR execution. No reopening of accepted C0 merely because implementation ancestry is historical. Do not assert complete Guard writer-path coverage, canonical existing V3 inventory, or DB consistency until independently evidenced.

## 8. Controlled Contract Breach and normative boundaries

Alternative C remains ACTIVE/OPEN. Deliberate deviations require exact provision/operation/resource identity, explicit user authorization, scope, safety controls, implementation/validation evidence and tracked disposition. Existing deviation `CD-V3-HTACCESS-20261026-01` remains separately OPEN until its applicable conditions are met. An old concept's “standalone Guard contract before implementation” sequence may conflict with current accepted implementation-first strategy; classify it as planning/normative reconciliation debt, **not** automatic reactivation of a broad pre-implementation contract-promotion gate. Do not silently amend accepted contracts.

Cumulative normative reconciliation is post-Alternative C closure per accepted user sequencing; do not interpret this sentence as permission for unsafe action before closure.

## 9. Related concepts, contracts, and workplan

Foundation sources:
- `concepts/copot_adaptive_package_lifecycle_independent_domain_compatibility_concept.md` — package declares, lifecycle decides, domain separation and offline inventory evidence.
- `concepts/copot_installation_scoped_maintenance_management_concept.md` — Webcore-owned reusable Guard; historical WU6/contract sequence not silently reactivated.
- `concepts/copot_centralized_resource_protection_layered_resource_policy_concept.md` — current `.htaccess` absolute immutability without generalized policy framework.
- `concepts/copot_lifecycle_net_zero_retirement_safe_retry_capability_concept.md` — historical bounded disposition/retry evidence, not a requirement to restore all historical machinery.

Authoritative normative sources include `docs/28_package_lifecycle_migration_foundation_contract.md`, `docs/31_backup_recovery_foundation_contract.md`, `docs/32_webcore_deployment_portability_foundation_contract.md`, and applicable accepted contracts. `workplan.md` is the canonical planning/sequence index; `docs/devlog.md` is the canonical chronological Activity Journal. Neither this Concept nor Workplan replaces normative authority.

## 10. Unresolved/deferred and document lifecycle

Unresolved: exact trusted V2→V3 installed-source authority; explicit current C1 package database requirements and inferred removal disposition; operation-bound backup/restore evidence on SDR; complete registered writer coverage; exact Guard/BnR/Package Lifecycle interface/owner mapping; database external-writer consistency; combined-domain sequencing; C2–C7 scoping/acceptance; residual v1/v2 dependencies; exact Controlled Contract Breach reconciliation and required post-closure normative updates.

Deferred: generalized Resource Policy architecture, broad ownership refactoring, universal recovery/historical provenance reconstruction, non-required UI/productization, unaccepted schema exceptions, unrelated cleanup and optional deployment complexity.

**Disposition:** this one Concept records the accepted foundation direction together with **proposed** detailed C0–C7 definitions. It is not a pre-contract, authoritative contract, implementation authorization, lifecycle operation plan, deployment instruction, or promise that the proposed sequence will be executed. A future substantive revision must preserve provenance and be accepted through the relevant user/project decision gate.
