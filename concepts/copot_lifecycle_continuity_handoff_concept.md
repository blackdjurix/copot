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
