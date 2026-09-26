# COPOT HANDOFF — <Continuity Boundary> — <Title>
Date version: 2026-09-26 14:00:00 WIB

## Usage boundary

Handoff adalah GPT/session continuity artifact untuk COPOT. Handoff bukan Agent Instruction, bukan executor payload, bukan authorization baru, dan bukan pengganti Rule.

Canonical identity: `governance/handoff_template_copot.md`.

## Fresh-session isolation and language

Receiving GPT must not rely on implicit memory, prior-session summary, chat history, or model context as a substitute for this Handoff and current authoritative sources. Reconstruct only from current governance, this Handoff, project instructions, verified Repository state, and explicitly identified material sources.

Use the primary conversation language for Handoff prose. Keep identifiers, paths, commands, filenames, and technical tokens literal when precision requires it.

## Transition

- Transition type: `NORMAL / NRP / EMERGENCY`
- Project: `COPOT`
- Continuity Boundary: `<milestone / Work Unit / batch / workstream / phase>`
- Title: `<title>`
- Prepared at: `<YYYY-MM-DD HH:mm:ss WIB>`
- Prepared by: `<actor>`

Untuk `EMERGENCY`, continuity risk dan recovery/revalidation requirements wajib eksplisit.

## Current objective and state

- Objective: `<objective>`
- Accepted result: `<verified accepted result / None>`
- Current state: `<what is true now>`
- Last reliable evidence: `<commit, document, test, artifact, or observation>`
- Unresolved/unsaved payload: `<reference to the section below or None>`

Pisahkan accepted, provisional, rejected, superseded, dan unresolved state.

## Unresolved and unsaved payload

Treat unresolved and unsaved/unpersisted payload as the same continuity class. The output differs, but neither is authoritative until its required persistence and verification path is complete.

- Payload identity/source: `<artifact, thread state, decision, instruction, change, or runtime state>`
- Payload kind: `<unresolved / unsaved / both>`
- Output currently available: `<partial result, open decision, evidence, or None>`
- Persistence state: `<not persisted / partially persisted / persisted but unverified / persisted and verified>`
- Durable disposition: `<carry forward / persist before transition / intentionally discard with authorization / blocked / unknown>`
- Required next action: `<exact action or None>`
- Authority status: `<non-authoritative until persisted and verified>`

Include, when applicable:

- unsaved user decisions;
- unsaved GPT conclusions or derived decisions;
- unsent Agent Instructions;
- uncommitted repository changes;
- unpersisted Workplan/Concept changes;
- temporary runtime state;
- generated artifacts not yet accepted or persisted.

## Next target

- Next target: `<smallest safe next target>`
- Dependency: `<dependency or None>`
- Authorization status: `<authorized / approval required / blocked>`
- Required first action: `<bootstrap/check>`
- Stop condition: `<condition>`

Next target bukan authorization baru. Receiving GPT harus revalidate authority dan current state.

## Planning and Concept continuity

- Workplan state: `<status>`
- Non-synchronization check: `<no repository sync implied / issue>`
- Closure reconciliation: `<complete / required / blocked>`
- Saved Concept payload: `<semantic identity, provenance, revision, unresolved/unsaved payload reference>`
- Deferred Items: `<status and adoption state>`

Session transition tidak menghapus unresolved planning payload.

### Thread-Level Saved Concept Reconciliation

- Accumulated thread-level saved Concepts: `<identity and source>`
- Reconciled durable disposition: `<carried forward / incorporated / deferred / superseded / rejected / unresolved>`
- Unresolved/unsaved payload preserved: `<yes/no and summary>`
- Reconciliation evidence: `<source, revision, or None>`

Session change tidak boleh menghapus, silently close, mengubah klasifikasi, atau silently discard saved Concept maupun unresolved/unsaved payload.

## Dependency and stacked-branch state

When material, record:

- cross-boundary dependency: `<dependency and satisfied/unsatisfied state>`;
- stacked branch relation: `<base, dependent branch, containment, or None>`;
- dependency evidence: `<exact source or None>`.

Dependency state is context, not authorization to begin the dependent work.

## Repository and runtime state

Include only when material:

- Repository: `https://github.com/blackdjurix/copot.git`
- Integration target: `main`
- Branch: `<branch>`
- HEAD/revision: `<revision>`
- Working tree: `<clean / listed changes / unknown>`
- Runtime role: `<XAMPP runtime role or None>`
- Runtime endpoint/port: `<only if material; never project identity>`
- Divergence or lifecycle issue: `<value or None>`

Runtime copy bukan Repository authority.

## Acceptance and closure

- Acceptance criteria: `<met / partial / not met>`
- Validation: `<checks and outcomes>`
- Documentation/planning reconciliation: `<status>`
- Work-unit/workstream closure: `<closed / open / blocked>`
- Release/publication: `<separate status>`
- NRP: `<candidate / confirmed / not applicable / blocked>`

### NRP candidate documentation consistency

- Candidate project/work-unit documentation: `<source>`
- Current implementation/repository evidence reconciled: `<yes / no / blocked>`
- Material documentation conflict: `<None or exact conflict>`
- Required correction before NRP confirmation: `<None or exact correction>`

Handoff tidak boleh menyatakan NRP confirmed hanya karena technical work selesai.

## Session continuity

- Continue current session/thread or new: `<decision and reason>`
- Context requiring revalidation: `<items>`
- Minimum bootstrap: `<first reads/checks>`
- Material Tailscale state: `<only if remote access is relevant>`
- Material Figma state: `<only if visual/prototype context is relevant>`

Bootstrap order: current governance, this Handoff, project instructions, verified project/Repository state, then only explicitly identified material Workplan, Concept, documentation, runtime, or tool sources.

## Direct-transfer boundary

Jangan transfer full Handoff ke Technical Executor.

Jika user meminta direct transfer, turunkan Agent Instruction terpisah yang hanya membawa minimum material execution context. Handoff tidak memperluas authorization.

## Required startup report

Receiving GPT harus melaporkan:

- exact governance artifacts yang dibaca;
- verified objective/current state;
- unresolved state;
- authorization status;
- revalidation performed;
- blocker atau continuity risk.

## Closure statement

Handoff ini mencatat continuity state. Handoff tidak dengan sendirinya menyatakan implementation complete, project closure, NRP confirmation, release readiness, atau execution authorization.

