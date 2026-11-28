# COPOT — Lifecycle Continuity & Handoff Concept

Status: USER-DIRECTED PLANNING REPLACEMENT / NON-NORMATIVE / NOT PROMOTED / IMPLEMENTATION NOT READY

Project: COPOT
Planning anchor: `workplan.md`, Alternative C / C3-B
Primary objective: self-update continuity for execution-critical Package Lifecycle components
Initial technical evidence anchor: `a06745eb1f0c8fa361aa3241e89d3c741521b050`

## 1. Purpose and disposition

This Concept **supersedes the inline C3-B I1 Ownership & Fencing technical contract drafts v0.1–v0.2.1 as standalone contract candidates**. It does not erase those drafts, the six previously accepted architecture directions, validation results, or historical provenance. Ownership & Fencing remains a constituent technical mechanism and evidence dependency, **not** the scope or name of the parent continuity design.

The planning objective is one safe lifecycle operation that can continue across process interruption, bounded executor transfer, and replacement of execution-critical components while preserving independently verifiable protection and recovery. The primary use case is self-update of Package Lifecycle Core, installation-scoped Maintenance Guard, Backup & Recovery, Handler/Executor, and other components actually required for the lifecycle operation. Exact dependencies are determined by the observed execution graph, not by a fixed class list.

C3-B is a planning and readiness boundary within Alternative C, not an automatically approved full end-to-end self-update implementation. This Concept is **not** an authoritative Contract or source modification authorization. Existing normative Contract requirements and separately authorized controlled deviations remain unchanged until the accepted post-Alternative-C reconciliation.

## 2. Responsibility boundary

- **Package Lifecycle Core:** declares and coordinates the authorized lifecycle operation, planning, mutation sequence, resulting-state verification, and finalization. It consumes Guard and BnR; it does not absorb their general responsibilities.
- **Maintenance Guard:** owns installation-scoped application writer admission, drain, durable protection state, scope-bound evidence, and safe reopening. Protection persists across supported executor handoffs, crashes, and replacement of Guard implementation. It is **not MariaDB freeze**, not global database writer exclusion, and not database snapshot consistency proof.
- **Backup & Recovery (BnR):** owns recovery capture and identity, independent recovery evidence, restore, verification, and disposition. Replacing BnR must not invalidate the ability to recover the operation already in progress.
- **Lifecycle Continuity & Handoff:** binds one operation and its authorization across executor changes while coordinating the existing peer components' authority, protected intervals, recovery evidence, and final disposition. This is a conceptual cross-component responsibility, not a new monolithic owner or a currently implemented class.

## 3. Constituent mechanisms and continuity invariants

1. **Operation/session continuity:** stable operation identity and authorized scope, with durable state required for reconstruction rather than process-local memory.
2. **Ownership & Fencing:** current owner identity, monotonic generation where needed, and stale-executor rejection at actual mutation/finalization boundaries; `InstallationMutex` alone does not prove durable ownership. Prior I1 experiments and accepted architectural direction remain reference evidence.
3. **Adaptive executor handoff:** repeated handoff may occur within one operation; transfer must be recoverable/idempotent, with explicit before/after authority and no two concurrent mutating owners. A handoff itself never commits application state.
4. **Protection continuity:** Maintenance Guard must remain effective across executor restart and its own replacement. New writer admission is controlled, in-flight writers are drained/proven safe, and stale executors cannot reopen it.
5. **Recovery continuity:** retained, independently verifiable BnR evidence survives component replacement, failed takeover, partial mutation, and retries; no unproven recovery success.
6. **Execution/state/finalization continuity:** mutation, lifecycle state, committed state, terminal disposition and cleanup are separately fenced; partial publication does not imply successful completion.
7. **Failure and evidence:** absent, incomplete, contradictory, or stale credentials/evidence fail closed; process-crash evidence never implies proven power-loss durability.

These are planning-level invariants. Proposed implementation primitives such as AuthorityGate, immutable transition journal, Guard writerLeaseId/admissionEpoch and terminal cleanup credentials remain **candidate techniques**, not mandatory concrete classes, final schemas, or already implemented features.

## 4. Operation applicability

The **primary intended case** is self-update of execution-critical components. Reuse of individual mechanisms in other protected Package Lifecycle or recovery operations may be technically appropriate, but is **not automatically adopted as an expanded implementation scope**. Ordinary application requests must not be given full lifecycle handoff machinery merely because they use Guard writer admission.

Capability activation should follow the risks and verified dependencies of the exact operation, not blindly activate every proposed submechanism.

## 5. Portability and hosting boundary

The design must serve supported hosting capability classes, especially shared hosting with fixed `PUBLIC_ROOT` and private `APP_ROOT`. Windows/XAMPP is a development and test environment, **not** a production runtime dependency. No core reliance on Windows-native named mutex, PID reuse assumptions, persistent daemon, process inspection, custom VirtualHost, or MariaDB server freeze.

Filesystem/locking/persistence authority is capability-qualified for the actual host. A platform may use suitable PHP/filesystem primitives only after its safety properties are established; if unavailable, the affected mutation fails closed. A Windows-only proof is bounded local feasibility evidence, not shared-hosting acceptance.

For the self-update path, application-writer quiescence is Maintenance Guard responsibility. Legacy `DatabaseQuiescenceCapability` paths present in current BnR remain separately inventoried and are **not** silently deleted or reintroduced as mandatory self-update dependency. Guard is also not a substitute for applicable transactional or database-consistency evidence.

## 6. Existing work and evidence preservation

Retain ongoing isolated Codex Atomicity Mechanism Feasibility Verification (journal-first transition, authority gate, Guard epoch, terminal cleanup), its original execution boundaries and exact source anchor. Its findings feed **constituent-mechanism feasibility** of this broader Concept; they do not automatically accept, implement, or narrow the parent continuity objective.

Preserve accepted prior technical evidence, C3-B three durable validation sets, reported TEMP prototype material, historical P1–P4 qualifications, outstanding L10, WU6 retained workspace/changes, current runtime and database. Do not restart, expand, cancel, or reinterpret the in-flight executor task without its separate technical delta and authorization. Portability adjustment is a design interpretation and evidence-qualification correction, not an instruction to change that task's platform in mid-flight.

## 7. Outstanding planning and readiness questions

- Exact component dependency graph and which component replacements occur during the same operation.
- Trusted bootstrap/re-entry when the Guard, BnR, or Package Lifecycle implementation itself is being updated.
- Transfer/exclusion and durable operation-state publication across multiple handoffs, including failure windows.
- Guard-held protected interval, admission reopen authority, and writer coverage under request-scoped PHP.
- BnR evidence continuity, version compatibility and restoration when BnR is a mutation target.
- Finalization/terminal and cleanup authority after executor reacquisition.
- Portable filesystem capability contract, fail-closed unsupported host, process-crash versus power-loss proof boundary.
- Exact minimal implementation slices and I1/I2/I3 dependencies; regressions, recovery and authorization per slice.

No question is declared resolved merely by listing a design candidate.

## 8. Authority and next gate

Status: `CONCEPT RECORDED / NON-NORMATIVE / HANDOFF DESIGN INCOMPLETE / IMPLEMENTATION READY NO`.

Alternative C Controlled Contract Breach remains `ACTIVE / OPEN`. Normative Contract amendment/promotion is **not** performed here. User retains semantic acceptance and separately authorizes future production source, runtime, branch, publishing and materialization actions. Next design review should incorporate ongoing Codex evidence and evaluate the complete Lifecycle Continuity & Handoff boundary, without discarding already valid Ownership & Fencing work.

## 9. Accepted Detailed Workplan — P0–P6 (2026-11-21 10:04:51 WIB)

Status: **USER-ACCEPTED PLANNING / NON-NORMATIVE / NOT AN IMPLEMENTATION AUTHORIZATION**. Tahap P0–P6 adalah work packages yang direkonsiliasi ke satu C3-B, bukan tujuh Work Units baru dan bukan syarat mengulang evidence yang sudah diterima. `OWNERSHIP & FENCING` adalah P3/I1 di bawah Lifecycle Continuity & Handoff, bukan standalone authoritative Contract.

### P0 — Evidence & Scope Reconciliation

**Objective:** membangun baseline evidence dan exact execution-critical dependency graph tanpa mengulang proof yang sudah diterima.

- **P0.1 Evidence inventory:** inventaris multi-handoff P1–P4 dan qualifications; P5–P8; V-I1; ownership crash-safety T1–T8/L1–L9; A1–A10/B1–B7/C1–C8/D1–D8 (33 isolated prototype cases); L10 yang masih unresolved; regression Guard, BnR, dan Package Lifecycle. Catat source SHA, runtime, provenance, PASS/qualification dan immutable artifact identity.
- **P0.2 New fixture verification:** pastikan exact local bytes/size/SHA-256 `atomicity_feasibility.php` (34,158 byte; `BFE1BBDAAE146CB0F2E09D5B532EDBD5D686475A70B89A8C34A13F6E325839B3`) dan `evidence.json` (6,674 byte; `31D7BFFEDD98545A01614E52F0E2EAD89E70269D282C54B0DCA6F1516A6FEB6C`) yang saat ini hanya executor-reported / TEMP_LOCAL_ONLY. Reconcile perbedaan A4 stale older replay versus historical result-idempotency. Canonical Material Store materialization membutuhkan keputusan yang sesuai; jangan otomatis menulisnya.
- **P0.3 Execution-critical inventory:** petakan actual call/boot/replace graph Package Lifecycle Core, Guard, BnR, Handler/Executor, persistence, applier, bootstrap, health/commit/finalization; bedakan mutation target, runtime dependency, recovery dependency, dan trusted-until-terminal.
- **P0.4 Scope reconciliation:** C3-B tidak otomatis mengadopsi C4, C5, Module coordinator migration, generic ownership refactor, product UI, atau legacy removal.

**Deliverables:** evidence/provenance matrix, qualified prototype assessment, execution-critical component graph, scope/gap matrix. **Exit:** source/evidence provenance dan unresolved material boundaries cukup jelas untuk desain P1/P2; no implicit rerun.

### P1 — Portability Reconciliation

**Objective:** menetapkan portable locking, publication, persistence dan recovery capability sebelum memilih primitive production.

- **P1.1 Hosting:** reconcile class A configurable document root, class B fixed PUBLIC_ROOT + private APP_ROOT (primary shared-host target), class C public-only unsupported unless separately proven safe; preserve existing deployment-root/base-path contract.
- **P1.2 Locking:** cross-process exclusion, identity, replacement/ABA, lock ordering, crash release, filesystem backend/local/network qualification. `flock()` alone never proves universal cross-host semantics.
- **P1.3 Persistence:** create-only immutable transition, temp/rename/fsync behavior, snapshot reconciliation, torn metadata, recovery, process-crash vs power-loss claims.
- **P1.4 Execution:** PHP request/eligible CLI compatibility without required native Windows mutex, persistent daemon, PID/process inspection, privileged host configuration or MariaDB freeze.
- **P1.5 Disposition:** RETAIN/QUALIFY existing `InstallationMutex`, `LifecycleOperationStore`, Maintenance Guard; RETAIN CONCEPT/ADAPT owner generation, AuthorityGate, immutable journal, Guard leases/epoch, terminal fence, ABA detection; EXCLUDE Windows-native/PID/daemon/DB-freeze as core dependency. Explicit fail-closed when capabilities cannot be proven.

**Deliverables:** hosting compatibility matrix, authority locking/storage/execution capability specification, mechanism disposition and unsupported-host policy. **Exit:** each required primitive has qualification requirement, threat model, supported/unsupported bounds and fail-closed behavior. Windows-only PASS not shared-host proof.

### P2 — Lifecycle Continuity Architecture

**Objective:** define cross-component parent continuity model before locking I1/P4 integration.

- **P2.1 Responsibility/authority map:** Package Lifecycle owns orchestration/plan/commit, Guard owns admission/drain/protection/reopening, BnR owns capture/restore/evidence, Continuity binds one authorized operation over executor changes without becoming peer-service owner.
- **P2.2 State model:** operation, protected execution, handoff prepared/transferred/resumed, recovery, terminal/cleanup; conceptual states do not automatically create production phases.
- **P2.3 Adaptive handoff:** durable state, monotonic valid executor authority, multi-handoff in same operation, no parallel mutating owners or accidental committed-state advancement.
- **P2.4 Self-replacement trust:** bootstrap and reentry when Package Lifecycle, Guard, BnR, Handler/Executor or direct dependencies are mutation targets; independent recovery path.
**P2.4 accepted architectural criteria (2026-11-28 WIB):** USER ACCEPTED / NON-NORMATIVE PLANNING. User explicitly accepted the following four mandatory architectural criteria and authorized their durable persistence; reevaluation is permitted if material drift, conflicting evidence, feasibility issues, or changed dependencies arise, preserving decision provenance.

1. **Trusted Bootstrap:** establish a trusted execution entry and independently verifiable operation context when execution-critical implementation is a replacement target.
2. **Protected Replacement:** preserve operation identity, authorized mutation scope, valid exclusive executor authority, protection and recovery continuity throughout replacement.
3. **Trusted Re-entry:** qualify replacement implementation and reconcile actual durable operation state before authorized execution resumes; loading replacement code alone is insufficient.
4. **Independent Recovery:** retain an authorized, independently verifiable recovery path that does not depend on successful execution of the failed replacement.

All four capabilities must be covered by the architecture and qualification; actual activation is conditional on the operation and its failure disposition. Successful replacement does not require executing recovery. These criteria do not select a concrete implementation, establish technical qualification, close P2.4, confirm NRP, or authorize implementation. P2.3 unresolved fencing/authority and downstream qualification dependencies remain open. Six exploratory evaluation scenarios proposed separately are not accepted by this decision and are not incorporated as deliverables.

**P2.4 subsequent acceptance and conceptual refinement (2026-11-28 WIB):** USER ACCEPTED / NON-NORMATIVE PLANNING. The earlier four-criteria decision did not itself accept the six scenarios; subsequent explicit user acceptance supersedes their proposal status (Devlog at main `ad5a9328953386929f4d6db25cd4d46ad49680bd`).

Accepted conceptual evaluation scenarios:
- Successful Self-Replacement — MANDATORY.
- Failed Re-entry — MANDATORY.
- Process Interruption — MANDATORY.
- Trusted Entry Replacement — CONDITIONALLY MANDATORY when trusted entry is a mutation target.
- Recovery Dependency Replacement — CONDITIONALLY MANDATORY when a recovery dependency is a mutation target.
- Interrupted Retirement — MANDATORY for a mechanism with a retirement/cleanup stage.

Mandatory denotes relevant evaluation coverage, not unconditional activation or runtime testing in P2.4; this is not an exhaustive P2.5 Failure Matrix.

User subsequently accepted and authorized persistence of the two conceptual refinements proposed in Devlog at `ea99c7dc48a1bc9c59adbb86962a7c0720adbb56`:
- **Protected Trust Transition:** qualify an alternate trusted route before replacing the old route; protect it relative to the current mutation scope, maintain exclusive valid authority under P2.3, preserve rediscovery and durable operation state through interruption, and fail closed when no trusted route is available. A minimum trusted boundary must remain available relative to the mutation scope, but no permanent immutable implementation, exact file/class, or concrete execution environment is selected.
- **Terminal Disposition:** distinguish Operation Finalization (durable verified outcome), Authority Disposition (valid return/transfer/termination), and Environment Retirement (only when no longer required for continuation, recovery or finalization). A verified outcome must not trigger repeated replacement solely because cleanup was interrupted; retirement must be recoverable/idempotent, and an environment still needed when outcome is uncertain must be retained.

These are refinements of the four accepted criteria, not additional criteria or scenarios, and do not automatically accept the earlier seven-stage mechanism or temporary environment implementation. Conceptual maturity: SUFFICIENT / USER ACCEPTED for these refinements; exact trust anchor, interruption-safe transition and terminal evidence, P2.3 fencing, P4 protection/recovery and downstream qualification remain unproven. Technical qualification NOT ESTABLISHED; P2.4 OPEN pending formal closure evaluation; implementation HOLD; no contract promotion or NRP confirmation.

**P2.4 formal planning closure (2026-11-28 WIB):** COMPLETE WITH QUALIFICATIONS / USER ACCEPTED / CLOSED (BOUNDED). The preceding OPEN status is superseded for P2.4 architectural planning only following GPT closure evaluation and user direction to proceed. Four criteria, six classified scenarios and both refinements remain accepted; earlier seven-stage mechanism and concrete Temporary Execution Environment remain unaccepted candidates. Technical qualification NOT ESTABLISHED: carry failure coverage to P2.5, authority/fencing to P3, Guard/BnR protection and independent recovery to P4, concrete bootstrap/re-entry/retirement integration to P5 and integrated readiness to P6 as applicable. Parent P2 and C3-B remain OPEN, implementation HOLD, NRP NOT CONFIRMED. No contract promotion, source/runtime mutation or automatic next-phase execution. Activity Journal Rollover is a separate gated next target following durable closure verification.

- **P2.5 Failure matrix:** crash before/during/after takeover, partial apply, corrupted Guard state, failed new-version bootstrap, missing BnR evidence, repeated retries, terminal interruption.

**Deliverables:** responsibility/interface matrix, continuity state and handoff protocol, critical-replacement strategy and failure/recovery matrix. **Exit:** every critical transition has verified authority prerequisite, durable evidence, postcondition, and safe failure disposition; do not mistake proposed sequence for implemented behavior.

### P3 — Ownership & Fencing Readiness (existing I1 continuation)

**Objective:** complete I1 technical readiness using already reported 33 prototype PASS, not start a new standalone Contract or duplicate validation.

- **P3.1 Credentials:** operationId, ownerIdentity, ownerGeneration, expected state identity and scope binding; never PID/lock handle as owner authority.
- **P3.2 Atomic transitions:** CAS, monotonic generation, immutable journal publication, previous-state chain, request identity, older historical retries, snapshot replay/corruption reconciliation; inspect A1–A10 limitations.
- **P3.3 Authority Gate/lock graph:** actual mutation interval exclusion, InstallationMutex vs owner transition/admission locks, ABA/object identity, interrupted processes; inspect B1–B7 and real source lock graph; global deadlock freedom remains unproven.
- **P3.4 Terminal fencing:** committed/verified recovery disposition, exact terminal evidence, fenced idempotent cleanup, UNSETTLED vs INDETERMINATE as classifications, not new phases; reconcile D1–D8.
- **P3.5 I1 minimal integration map:** LifecycleOperationStore/Record, MaintenanceCoordinator, lock and credential surfaces, Guard/BnR authorization/finalization interfaces; distinguish I1 state authority from I2 activation-time and I3 finalization coupling.
- **P3.6 Readiness:** explicit exclusion, takeover, crash recovery, persistent transition semantics and portability qualification. I1 ready is only constituent technical-design readiness and not parent readiness or source mutation authority.

**Deliverables:** I1 credential, transition/recovery, AuthorityGate/lock graph, terminal protocol, minimal integration delta and qualification matrix. **Exit:** `I1 TECHNICAL DESIGN READY` only on resolved blockers; otherwise `PARTIAL/BLOCKED`.

### P4 — Maintenance Guard & BnR Continuity

**Objective:** preserve independent application protection and recovery when execution-critical implementations change.

- **P4.1 Guard admission:** durable writer identity/leases, Guard-owned epoch OPEN→DRAINING→CLOSED→OPEN+1, interrupted register/release reconciliation, positive no-live-writer evidence, fenced reopening.
- **P4.2 Guard replacement:** new implementation resumes durable protected state, never opens unguarded admission, stale generation cannot release; failed replacement remains recoverable.
- **P4.3 BnR continuity:** retained operation-bound capture, schema/version-compatible evidence, independent revalidation/restore/verification postconditions.
- **P4.4 BnR replacement:** restoration remains possible when BnR itself is a mutation target; no reliance on broken newly installed code as sole recovery provider.
- **P4.5 Cross-component coordination:** Guard owns writer admission; owner fencing owns executor mutation authorization; BnR owns recovery. Guard-issued application-writer quiescence evidence is not MariaDB freeze or DB consistency evidence. Legacy DB-quiescence paths need separate impact review, not silent removal.

**Deliverables:** Guard admission and replacement protocols, BnR recovery/replacement protocols, cross-component authority matrix. **Exit:** protected state and verifiable recovery persist across relevant handoffs and component replacements.

### P5 — Adaptive Handoff & Execution Integration

**Objective:** define independently authorizable minimum source implementation slices after P2/P3/P4 boundaries are consistent.

- **P5.1 Source map:** actual operation persistence, Guard/BnR integration, file activation, committed finalization, bootstrap and terminal cleanup targets.
- **P5.2 Activation:** generation-aware validation must be coupled with actual protected file mutation; eliminate check-to-write race, not just precheck operation identity.
- **P5.3 Runtime handoff:** request-scoped PHP/eligible CLI where feasible, durable reconstruction, Guard continuity, stale executor rejection, failed-version bootstrap recovery; no mandatory daemon.
- **P5.4 Finalization:** after mutex reacquisition, revalidate owner generation, evidence/Guard state, target postconditions; terminal publication and idempotent cleanup.
- **P5.5 Candidate slices:** preserve historical `I1` for Ownership & Fencing; `I2` protected mutation/handoff; `I3` recovery/finalization continuity; `I4` execution-critical component replacement integration. I2–I4 labels are provisional and do not create authorized Work Units.

**Deliverables:** exact source integration map, mutation/handoff/finalization enforcement designs, dependency-ordered minimum implementation slices with tests, rollback and stop criteria. **Exit:** each slice has bounded payload, actor, authorized action gate, validation and rollback strategy; no implementation automatically begins.

### P6 — Integrated Readiness Assessment

**Objective:** determine evidence-backed readiness of the **whole** continuity concept, not only I1.

- **P6.1 Scenarios:** normal operation, 1+ handoffs, crash around every critical transition, concurrent takeover, stale mutation/finalization, Guard/BnR/Core self-replacement, interrupted apply, failed bootstrap, recovery, terminal cleanup, unsupported hosting; reuse applicable existing tests.
- **P6.2 Portability:** distinct Windows/XAMPP feasibility, Linux/PHP, shared-host capability and optional private-server qualification; no automatic cross-platform support claim.
- **P6.3 Failure assurance:** process crash, durable replay, lock identity, BnR recovery, power-loss limitations and unsupported environments explicitly classified.
- **P6.4 Final gate:** full architecture, technical feasibility, Guard/BnR protection, implementation delta, evidence durability, regression/recovery plan, authority and unresolved blockers.

**Deliverables:** integrated test/portability matrix, failure assurance and readiness assessment. **Exit:** `READY FOR IMPLEMENTATION REVIEW`, `PARTIAL — BLOCKERS REMAIN`, atau `BLOCKED`; no full self-update operational acceptance inferred from design readiness.

### P0–P6 Dependencies, Preservation and Authorization

`P0 → P1 → P2 → (P3 and P4 when stable interfaces permit parallel work) → P5 → P6`. Portability P1 criteria carry through every subsequent gate. Work may be reconciled concurrently where dependencies are satisfied; no automatic seven new Work Units.

The existing `33` latest feasibility PASS are executor-reported isolated prototype results, not verified production semantics. Keep the existing three durable C3-B evidence sets, unresolved P1–P4 qualifications, L10, source anchor and nine retained WU6 changes. Avoid reruns without concrete reason. New prototype artifacts remain `TEMP_LOCAL_ONLY` until separately authorized durable disposition.

Planning acceptance does **not** authorize production implementation, source changes, branch mutation, runtime/DB/recovery actions, package deployment, release, or normative Contract promotion. `Alternative C Controlled Contract Breach: ACTIVE / OPEN`; `IMPLEMENTATION READY: NO`; `PRODUCTION IMPLEMENTATION: HOLD`. Normative amendment remains sequenced after Alternative C closure.
