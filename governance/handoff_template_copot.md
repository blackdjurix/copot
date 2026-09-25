# COPOT (<Continuity Boundary>) - <Title>
Date version: 2026-09-25 14:37:30 WIB

Ini adalah artifact kontinuitas GPT/model/session untuk COPOT. Ini bukan `Agent Instruction` dan tidak boleh disalin secara utuh sebagai payload untuk `Technical Executor`.

# USAGE BOUNDARY

Handoff membawa minimum context yang diperlukan agar GPT/session baru dapat memulihkan kontinuitas `Project` secara akurat tanpa merekonstruksi obsolete history atau mengandalkan prior-session memory sebagai pengganti applicable authoritative source.

Handoff dapat membawa, ketika material:

- objective current project state;
- applicable authoritative anchors atau state references;
- relevant unresolved items, Deferred Items, dan risks;
- current target dan next target;
- materially relevant `Workplan Set` context;
- unresolved thread-level continuity payload yang belum durably reconciled;
- materially relevant execution context;
- NRP dan session-continuity state.

Handoff adalah continuity artifact, bukan authority baru. Handoff tidak menggantikan `Governance`, `Authoritative Documentation`, `Repository`, `Workplan Set`, atau applicable authoritative source lainnya.

NRP mengikuti semantics pada latest applicable `Rule`. NRP bukan project/repository lifecycle state dan tidak otomatis menyebabkan session transition.

Delivery rules:

- generated Handoff menggunakan bahasa utama user;
- canonical technical terms, defined terms, identifiers, statuses, dan domain-specific terminology mempertahankan authoritative/original form ketika translation dapat mengubah meaning, identity, atau precision;
- deliver Handoff melalui editable writing block ketika capability tersebut tersedia;
- jangan membungkus seluruh Handoff dalam triple-backtick code fence;
- fenced code di dalam Handoff hanya digunakan untuk actual code, configuration, atau literal technical content yang memang memerlukan code formatting;
- field atau conditional section yang tidak applicable dapat diomit dari generated Handoff kecuali secara eksplisit diwajibkan untuk selalu dilaporkan.

## HANDOFF TITLE / FIRST-LINE SEMANTICS

Judul Handoff adalah first-line header di dalam Handoff content. Jangan gunakan external writing-block title/name field sebagai pengganti continuity title.

Format default:

`COPOT (<Continuity Boundary>) - <Title>`

Jika tidak ada applicable `Continuity Boundary`, gunakan:

`COPOT - <Title>`

Jangan prepend generic wrapper heading seperti `PROJECT SESSION HANDOFF`, `PROJECT SESSION HANDOFF TEMPLATE`, atau equivalent heading lain. First-line header harus langsung mengidentifikasi `Project`, applicable `Continuity Boundary` ketika ada, dan `Title` yang material terhadap destination session.

## DIRECT HANDOFF BOUNDARY

Handoff mengikuti direct-handoff dan execution-routing semantics pada latest applicable `Rule`.

Handoff tidak boleh digunakan sebagai `Technical Executor` payload.

Direct transfer, direct invocation, atau execution routing tidak boleh diinfer hanya dari keberadaan Handoff, recommendation, next target, execution context, atau tool availability.

Jika execution melalui `Technical Executor` diperlukan, gunakan separate `Agent Instruction` sesuai latest applicable governance.

Direct-transfer authorization hanya mengatur transport dan tidak memperluas technical scope, authorization, validation, stop conditions, atau external side-effect permissions.

# REQUIRED CORE

Current project state: `<objective current state>`

Current target: `<current target / None>`

Next target: `<next target / None>`

Latest accepted result: `<objective summary / None>`

Applicable authoritative sources: `<material source identities/references>`

Material unresolved items / blockers / risks: `<summary / None>`

## CONDITIONAL PLANNING CONTEXT

Isi hanya ketika `Workplan Set` context materially memengaruhi continuity, closure, atau next-target selection.

Resolved `Workplan` source: `<exact artifact/location / UNAVAILABLE / N/A>`

`Workplan` lifecycle: `<CURRENT / SUPERSEDED / RETIRED / N/A>`

`Workplan` reconciliation state: `<MID-WORKSTREAM DEFERRED / CLOSURE-RECONCILED / EARLY MATERIAL UPDATE / NEEDS REVIEW / N/A>`

`Workplan` adequacy for next-target selection: `<ADEQUATE / NEEDS RECONCILIATION / UNVERIFIED / N/A>`

Active provisional track(s): `<logical target(s) / None>`

Promotion candidate(s): `<logical target(s) / None>`

Promoted/closed registry entries still material to lineage: `<logical target(s) / None>`

Relevant `Concept` identities and sources:

- `<canonical title> — <exact source artifact/location + section/heading when material>`

Unreconciled thread-level saved concepts/rules: `<summary / None>`

Planning caveat: `<material caveat / None>`

Rules:

- bawa hanya planning context yang material terhadap continuity atau next-target selection;
- jangan copy full `Workplan` atau full `Concept` bodies;
- unresolved thread-level continuity payload tetap dibawa sampai durable reconciliation atau explicit disposition.

## CONDITIONAL EXECUTION CONTEXT

Isi hanya ketika execution context materially memengaruhi continuation.

Primary execution environment: **Local Workspace**

Alternative execution environment: **Cloud**

Execution workspace: `<path / remote workspace / provider / N/A>`

Runtime workspace: `<path / URL / provider / N/A>`

Last detected user platform: `<Desktop / PC / Mobile / Android / Other / Unknown>`

Active manual-operation executor: `<User / Technical Executor / Other / Unknown>`

Last material execution context: `<summary / N/A>`

Device/environment-transition readiness: `<READY / BLOCKED / UNKNOWN / N/A>`

Direct-transfer instruction state: `<EXPLICITLY REQUESTED / NOT REQUESTED / N/A>`

Rule: direct-transfer instruction state hanya merekam transport instruction yang material terhadap continuity dan tidak memperluas execution authorization.

## CONDITIONAL APPLICABLE GOVERNANCE CONTEXT

Isi hanya ketika satu atau lebih active segment dalam `Applicable Governance` materially memengaruhi continuity.

Active segment(s): `<Runtime / Gateway / Design Tooling / other active segment / None>`

Material function/value: `<function + preferred value / scalar segment value / N/A>`

Actual task-level route or exception: `<actual tool/runtime/gateway route / None / N/A>`

Continuity-relevant state: `<material state, caveat, accepted decision, unresolved review, or required revalidation / None>`

Rules:

- jangan copy seluruh `Applicable Governance` atau full segment rules ke Handoff;
- bawa hanya project-specific value/state yang materially diperlukan receiving session;
- task-level exception tidak mengubah canonical mapping pada `Applicable Governance`;
- receiving session tetap me-resolve latest applicable `Rule` secara independen.

# HANDOFF TRANSITION STATE

Transition type: `<NRP / EMERGENCY>`

Transition reason: `<material reason / N/A>`

Continuity condition: `<NORMAL / ELEVATED RECOVERY REQUIRED>`

Rules:

- `NRP` berarti Handoff dilakukan pada confirmed project-context continuity boundary sesuai latest applicable `Rule`.
- `EMERGENCY` berarti Handoff dilakukan di luar confirmed NRP karena session transition diperlukan atau materially preferable sebelum dependency terhadap prior context cukup diminimalkan.
- `EMERGENCY` tidak mengubah objective project lifecycle state, tidak mengimplikasikan closure, dan tidak memberikan authorization baru.
- Transition type menentukan continuity-recovery posture receiving session, bukan technical execution scope.

# STATUS SEBELUMNYA

Previous unit / continuity scope: `<...>`

Previous work result: `<COMPLETE / PARTIAL / NOT STARTED / BLOCKED / other applicable state>`

Material transition into current state: `<objective summary / None>`

## CONDITIONAL THREAD-LEVEL RECONCILIATION STATE

Gunakan ketika saved session/thread concepts, planning rules, atau unresolved semantic decisions masih material terhadap continuity.

Payload scope: `<workstream / planning concern / continuity scope>`

Durably reconciled items: `<summary / None>`

Still-unreconciled items: `<summary / None>`

Required durable disposition: `<Concept update / new Concept / Workplan / Deferred Item / supersede / reject / other / N/A>`

Rule: session transition tidak menghapus continuity payload ini. Clear hanya setelah durable reconciliation atau explicit disposition.

## CONDITIONAL DEFERRED ITEM REVIEW

Gunakan hanya ketika Deferred Item materially relevan terhadap current atau next target.

Deferred Item ID: `<ID>`

Title: `<title>`

Source: `<applicable authoritative source / boundary>`

Current status/target: `<...>`

Relevance: `<...>`

Review state: `<PENDING / COMPLETE>`

Disposition: `<ADOPT / KEEP DEFERRED / REJECT / SUPERSEDE / NOT APPLICABLE / N/A>`

Disposition evidence: `<... / N/A>`

Target update required: `<YES / NO / N/A>`

## CONDITIONAL ACCEPTANCE STATE

Isi hanya ketika acceptance masih material terhadap continuity, closure, atau next target.

AI acceptance: `<PASS / PARTIAL / BLOCKED / NOT REQUIRED / UNKNOWN>`

Human acceptance: `<PASS / PENDING / CHANGE REQUIRED / NOT REQUIRED / UNKNOWN>`

Human acceptance reason: `<criterion / N/A>`

Review surface: `<artifact / URL / runtime / device / environment / other / unavailable / N/A>`

Additional acceptance state: `<applicable role + state / None>`

# NEXT TARGET AND DEPENDENCY

Target berikutnya: `<target>`

Dependency classification: `<INDEPENDENT / SOFT / HARD / CLOSURE / BLOCKING / N/A / UNKNOWN>`

Dependency evidence: `<material fact only / N/A>`

Execution routing: `<Primary Execution Environment / Alternative Execution Environment / Technical Executor / User / other applicable route / N/A>`

Recommended next gate: `<planning / preparation / research / review / implementation / validation / acceptance / approval / documentation / integration / closure / delivery / other applicable gate>`

Authorization state: `<authorized boundary + remaining explicit approval requirement / N/A>`

Rule: pencantuman next target, execution routing, atau recommended next gate dalam Handoff tidak dengan sendirinya memberikan execution authorization maupun direct-transfer/invocation authorization.

# CONDITIONAL REPOSITORY STATE

Isi hanya ketika `Repository` applicable dan repository state material terhadap continuity.

`Repository`: **Remote Git Repository**

`Repository Link`: **https://github.com/blackdjurix/copot.git**

`Integration Target`: **main**

Repository state classification: `<VERIFIED / PARTIAL / UNVERIFIED / STALE OR CONFLICTED>`

Verification source/time: `<...>`

Accepted integration baseline: `<authoritative baseline / N/A>`

Authoritative active implementation state: `<authoritative state / N/A>`

Repository synchronization state: `<VERIFIED / PARTIAL / UNKNOWN / N/A>`

Branch routing: `<continue current / branch from Integration Target / stacked / preparation only / hold / other / N/A>`

## CONDITIONAL GIT STATE

Isi hanya ketika applicable `Repository` menggunakan Git dan Git-specific state material terhadap continuity.

Accepted baseline: `<branch + commit / N/A>`

Active branch: `<branch + commit / N/A>`

Ahead/behind: `<value / UNKNOWN / N/A>`

PR / merge / branch lifecycle: `<status / N/A>`

All intended changes committed: `<YES / NO / UNKNOWN / N/A>`

All intended changes pushed: `<YES / NO / UNKNOWN / N/A>`

Remote commit independently verified: `<YES / NO / UNKNOWN / N/A>`

Workspace cleanliness: `<CLEAN / DIRTY / UNKNOWN / N/A>`

Writable workspace state: `<SYNCED / AHEAD / BEHIND / DIVERGED / DIRTY / NOT CHECKED / N/A>`

Runtime workspace state: `<SYNCED / STALE / DIRTY / NOT CHECKED / N/A>`

## CONDITIONAL BRANCH LIFECYCLE CLOSURE AUDIT

Isi ketika current continuity boundary sedang closing, `Repository` menggunakan branch-based workflow, dan branch state material terhadap closure.

Integrated target: `<Integration Target / other applicable target>`

All intended commits contained in integration target: `<YES / NO / UNKNOWN / N/A>`

Branch inventory: `<summary>`

Obsolete branch candidates: `<branches / None>`

Ancestry/containment evidence: `<PASS / FAIL / N/A>`

Zero-ahead evidence: `<PASS / FAIL / N/A>`

Authorized deletions performed: `<branches / None / NOT AUTHORIZED>`

Tracking references pruned: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Post-cleanup inventory verified: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Final workspace clean/synced: `<YES / NO / UNKNOWN / N/A>`

Single-branch / no-op explicitly recorded: `<YES / NO / N/A>`

Rule: branch obsolescence tidak boleh diinfer hanya dari branch age, naming, atau appearance of merged history.

# FINAL CHANGESET SUMMARY

Isi hanya untuk work yang complete atau sedang closing ketika final delta materially membantu continuity.

Base state: `<authoritative starting state / N/A>`

Final state: `<authoritative resulting state / N/A>`

Changed artifacts / surfaces: `<summary>`

Change classification: `<implementation / behavior / documentation / configuration / data / schema / assets / planning / governance / mixed / other / none>`

Material outcome / behavior changes: `<summary / None>`

Documentation changes: `<summary / None>`

Planning / governance changes: `<summary / None>`

Unexpected changes: `<None / detail>`

## CONDITIONAL GIT CHANGESET

Isi hanya ketika applicable `Repository` menggunakan Git dan commit-level identity material terhadap continuity.

Base commit: `<hash + subject / N/A>`

Final commit: `<hash + subject / N/A>`

Commit range: `<base>..<final> / N/A`

# DOCUMENTATION CLOSURE

Documentation status: `<PASS / NOT REQUIRED / DEFERRED / BLOCKED / UNKNOWN>`

Documentation impact: `<behavior / capability / architecture / contract / workflow / planning / governance / lifecycle state / other / none>`

Included in authoritative state: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Post-persistence documentation state: `<PASS / NOT REQUIRED / BLOCKED / UNKNOWN>`

## CONDITIONAL NRP CANDIDATE DOCUMENTATION CONSISTENCY AUDIT

Isi ketika current readiness berada pada atau mendekati `NRP CANDIDATE`.

Audit status: `<NOT STARTED / IN PROGRESS / PASS / MATERIAL FINDINGS / BLOCKED / NOT REQUIRED>`

Audited authoritative surfaces: `<material Authoritative Documentation / project records / contracts / lifecycle records / Deferred Item registry / other>`

Material stale/inconsistent findings: `<None / summary>`

Corrections durable: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Final authoritative verification after correction: `<YES / NO / NOT REQUIRED / UNKNOWN>`

Rule: material current-state inconsistency pada applicable `Authoritative Documentation` harus dikoreksi, dibuat durable melalui applicable authoritative workflow, dan diverifikasi sebelum `NRP CONFIRMED`.

## CONDITIONAL PLANNING RECONCILIATION / ADEQUACY AUDIT

Gunakan ketika `Workplan Set` context material terhadap workstream closure, next-target selection, atau next-session continuity.

Planning audit status: `<PASS / NEEDS RECONCILIATION / BLOCKED / NOT REQUIRED / UNKNOWN>`

`Workplan` reconciliation state: `<MID-WORKSTREAM DEFERRED / CLOSURE-RECONCILED / EARLY MATERIAL UPDATE / UNKNOWN / N/A>`

`Workplan` adequate for next-target selection: `<YES / NO / UNKNOWN / N/A>`

Lifecycle/provenance entries requiring closure disposition reconciled: `<YES / NO / UNKNOWN / N/A>`

Material `Concept` references resolvable: `<YES / NO / UNKNOWN / N/A>`

Material `Concept` sources/locations traceable where needed: `<YES / NO / UNKNOWN / N/A>`

Unreconciled thread-level payload materially misleading continuity: `<None / summary>`

Remaining planning assumptions requiring audit before next-target selection: `<None / summary>`

Reconciliation/consolidation required before Handoff: `<YES / NO>`

Rule: planning audit mengikuti reconciliation cadence pada latest applicable `Rule` dan tidak gagal hanya karena `Workplan` tidak continuously mirror authoritative lifecycle state.

# NRP AND SESSION-CONTINUITY READINESS

NRP status: `<NRP NOT READY / NRP CANDIDATE / NRP CONFIRMED / NRP UNVERIFIABLE>`

Interpretation:

- `NRP NOT READY`: dependency terhadap prior project context belum cukup diminimalkan atau applicable continuity safeguards belum terpenuhi;
- `NRP CANDIDATE`: project/context state sudah mendekati continuity boundary yang aman tetapi applicable closure, reconciliation, durability, atau verification evidence belum lengkap;
- `NRP CONFIRMED`: applicable project/context state sudah cukup durable, reconciled, verified, dan recoverable untuk menjadi confirmed continuity boundary;
- `NRP UNVERIFIABLE`: required governance, authoritative state, atau material evidence untuk mengevaluasi NRP tidak dapat diverifikasi.

NRP evaluation mempertimbangkan, ketika material:

- accepted objective project state;
- durability dan recoverability dari applicable authoritative state;
- documentation consistency;
- applicable closure dan acceptance state;
- unresolved items, Deferred Items, dan risks;
- current dan next target;
- `Workplan Set` reconciliation/adequacy;
- thread-level continuity reconciliation;
- applicable repository/integration/branch lifecycle state;
- context-bootstrap sufficiency;
- applicable Final Stability Gate.

NRP tidak mengubah project, repository, Work Unit, workstream, milestone, atau lifecycle state lainnya.

`NRP CONFIRMED` tidak otomatis menyebabkan session transition. Sebaliknya, Handoff dengan transition type `EMERGENCY` dapat dilakukan sebelum `NRP CONFIRMED` sesuai applicable continuity rules.

## FINAL STABILITY GATE

Isi ketika NRP evaluation atau closure state membutuhkan final stability assessment.

Gate status: `<PASS / PARTIAL / BLOCKED / NOT REQUIRED / UNKNOWN>`

Durable authoritative state: `<VERIFIED / PARTIAL / UNVERIFIED / N/A>`

Required validation / acceptance state: `<COMPLETE / PARTIAL / BLOCKED / NOT REQUIRED / UNKNOWN>`

Documentation consistency state: `<PASS / MATERIAL FINDINGS / BLOCKED / NOT REQUIRED / UNKNOWN>`

Planning / continuity reconciliation state: `<COMPLETE / CARRIED FORWARD / BLOCKED / NOT REQUIRED / UNKNOWN>`

Repository / integration stability state: `<PASS / PARTIAL / BLOCKED / NOT REQUIRED / UNKNOWN>`

Remaining mutation or reconciliation required before `NRP CONFIRMED`: `<None / summary>`

Material stability caveat: `<None / summary>`

Rule: Final Stability Gate mengikuti latest applicable `Rule`; repository-specific evidence hanya diperlukan ketika `Repository` applicable dan material terhadap continuity boundary.

## CONDITIONAL EMERGENCY CONTINUITY RISK

Isi hanya ketika Handoff transition type adalah `EMERGENCY`.

Continuity risk level: `<LOW / MEDIUM / HIGH / UNKNOWN>`

At-risk context: `<material context yang belum fully durable, reconciled, verified, atau recoverable / None / Unknown>`

Potential continuity degradation: `<information loss / stale state / reduced precision / unresolved assumptions / incomplete reconciliation / other / None / Unknown>`

Known continuity gap: `<material known gap / None>`

State likely to require revalidation: `<material state / None>`

Required recovery action: `<re-read authoritative source / revalidate state / reconcile continuity payload / compare authoritative state / recover missing context / other / None>`

Recovery priority: `<IMMEDIATE / BEFORE NEXT MATERIAL DECISION / BEFORE CLOSURE / OTHER / N/A>`

Rule:

- `EMERGENCY` transition tidak dengan sendirinya berarti continuity degradation telah terjadi;
- catat actual known gap sebagai known fact dan catat potential loss/change/reduction sebagai risk hanya ketika terdapat material uncertainty atau evidence yang mendukung;
- receiving session tidak boleh menganggap unresolved, unreconciled, unverified, atau non-durable state sebagai accepted authoritative truth hanya karena dibawa oleh Handoff;
- recovery harus berfokus pada material continuity gap dan tidak merekonstruksi obsolete history yang tidak diperlukan.

## CONTINUE OR NEW SESSION

Setelah `NRP CONFIRMED`, lakukan session-continuity assessment sesuai latest applicable `Rule`.

Session disposition: `<CONTINUE CURRENT SESSION / NEW SESSION RECOMMENDED / N/A>`

Disposition basis: `<material continuity factors / N/A>`

Keputusan session transition tetap berada pada user.

Untuk `EMERGENCY` transition, jangan infer `NRP CONFIRMED`; receiving session mengikuti recovery behavior yang ditentukan oleh Handoff transition state, emergency continuity risk, dan context-bootstrap requirements.

Session-continuity disposition bukan concern atau authority `Technical Executor`.

# CROSS-BOUNDARY DEPENDENCY STATE

Gunakan hanya ketika current atau next target materially bergantung pada predecessor, parallel track, provisional track, milestone, workstream, atau applicable project boundary lain.

Dependency relation: `<predecessor / parallel / provisional / cross-workstream / cross-milestone / other>`

Dependency classification: `<INDEPENDENT / SOFT / HARD / CLOSURE / BLOCKING>`

Dependency target/source: `<material logical target or boundary>`

Dependency effect: `<what may continue / what must wait / what is blocked>`

Dependency evidence: `<material fact only>`

Rule:

- `INDEPENDENT`: applicable work dapat lanjut tanpa dependency ordering;
- `SOFT`: independent slices dapat lanjut, tetapi dependent slices tetap menunggu applicable dependency;
- `HARD`: ordered/stacked continuation hanya dilakukan ketika justified dan authorized;
- `CLOSURE`: applicable work dapat lanjut, tetapi final closure/integration menunggu dependency terpenuhi;
- `BLOCKING`: affected work ditahan; safe planning, analysis, atau audit dapat tetap dilakukan ketika applicable.

## CONDITIONAL STACKED BRANCH STATE

Isi hanya ketika `HARD` dependency menggunakan stacked branch pada applicable `Repository`.

Predecessor branch: `<branch>`

Predecessor anchor: `<commit / authoritative anchor>`

Downstream branch: `<branch>`

Stacked base: `<commit / authoritative anchor>`

Reason: `<material HARD dependency>`

Integration order: `<predecessor → downstream>`

Realignment / revalidation state: `<detail / N/A>`

Rule: stacked branch state tidak dengan sendirinya mengotorisasi merge, rebase, branch mutation, atau integration action.

# CONTEXT BOOTSTRAP FOR NEXT SESSION

Receiving GPT/session harus menganggap dirinya memasuki `Project` untuk pertama kalinya.

Jangan mengandalkan prior conversation memory, model memory, project memory, personal context, prior-session summaries, atau implicit history lain sebagai pengganti applicable authoritative source.

Gunakan Handoff ini hanya sebagai continuity context dan bootstrap aid. Handoff tidak menggantikan latest applicable `Governance`, `Authoritative Documentation`, `Repository`, `Workplan Set`, atau authoritative project state lain yang harus di-resolve secara independen.

1. Resolve dan read latest applicable `Governance` secara independen dari `Source` pada `Source Link` sesuai latest applicable `Rule`.
2. Jangan silently fallback ke older applicable governance ketika newer applicable version diketahui atau terindikasi tetapi unreadable atau unverifiable.
3. Read applicable project instruction ketika tersedia dan material terhadap current atau next target.
4. Apply latest applicable `Rule`, termasuk authorization, direct-handoff, execution-routing, planning, continuity, dan NRP semantics yang material terhadap continuation.
5. Resolve dan read materially relevant `Authoritative Documentation` serta applicable authoritative project state untuk current atau next target.
6. Jika `Repository` applicable dan material, verify repository continuity sesuai applicable repository/execution workflow sebelum mengandalkan repository state dari Handoff.
7. Resolve dan read applicable `Workplan` ketika planning, sequencing, dependency, lineage, closure, atau next-target selection materially bergantung padanya.
8. Jangan mengasumsikan `Workplan` harus continuously mirror current authoritative lifecycle state; evaluasi menurut latest applicable planning reconciliation semantics.
9. Resolve dan read hanya `Concept` identities dan exact source locations yang material terhadap current task atau continuity recovery.
10. Preserve unreconciled thread-level continuity payload sampai durable reconciliation atau explicit disposition; session transition tidak menghapus payload tersebut.
11. Review hanya Deferred Items yang materially relevan; jangan auto-adopt Deferred Item sebagai current atau next scope.
12. Jangan mengulang full accepted audit, validation, atau historical reconstruction tanpa concrete regression signal, continuity gap, conflict, atau material uncertainty.
13. Jangan infer technical execution authorization dari Handoff, `Workplan Set`, NRP status, next target, execution routing, atau tool availability.
14. Jangan infer direct-transfer/invocation authorization dari recommended execution routing atau availability `Technical Executor`.
15. Jangan infer release, tag, publication, distribution, atau equivalent external-delivery authorization dari feature, Work Unit, workstream, milestone, atau closure state.
16. Sebelum menghasilkan `Agent Instruction`, resolve dan read latest applicable artifact untuk role `Agent Instruction` sesuai latest applicable `Rule`; jangan derive executor-instruction structure hanya dari Handoff.
17. Handoff hanya boleh membawa material continuity expectations untuk future executor instruction dan tidak boleh menduplikasi atau menggantikan applicable `Agent Instruction` governance.

## TRANSITION-TYPE BOOTSTRAP BEHAVIOR

Jika transition type adalah `NRP`:

- gunakan normal continuity bootstrap;
- prioritaskan verification terhadap latest applicable governance, authoritative state, next target, dan material continuity payload;
- jangan merekonstruksi obsolete prior-session history kecuali conflict atau material gap ditemukan.

Jika transition type adalah `EMERGENCY`:

- gunakan elevated continuity recovery;
- prioritaskan known continuity gaps, at-risk context, unresolved/unreconciled state, dan state yang memerlukan revalidation;
- jangan infer closure, acceptance, reconciliation, durability, atau authoritative standing yang belum explicitly supported;
- re-read atau revalidate material source/state berdasarkan recorded Emergency Continuity Risk;
- recover hanya context yang materially diperlukan untuk safe continuation;
- jangan melakukan wholesale reconstruction terhadap obsolete history.

Continuity status: `<MATCH / CHANGED / CONFLICT / UNVERIFIABLE / NOT TRIGGERED>`

# REQUIRED STARTUP REPORT

Detected platform: `<... / Unknown>`

Previous platform: `<... / Unknown / N/A>`

Platform comparison: `<UNCHANGED / CHANGED / AMBIGUOUS / UNDETECTED / N/A>`

Active manual-operation executor: `<User / Technical Executor / Other / Unknown>`

Executor confirmation state: `<REUSED / CONFIRMED / REQUIRED / NOT REQUIRED / UNKNOWN>`

Transition type: `<NRP / EMERGENCY>`

Project continuity summary: `<Project + objective current state + current/next target + material scope>`

Authority summary: `<material Governance / Authoritative Documentation / Repository / Workplan Set authority state>`

Material risks / blockers: `<summary / None>`

Deferred Review: `<relevant Deferred Item IDs + disposition/review state / None>`

`Workplan Set` Planning Context: `<material logical pointers + reconciliation/adequacy state / None>`

Thread-Level Continuity Payload: `<material unresolved saved concepts/rules / None>`

Emergency Continuity Risk: `<material known/potential continuity risk / None / N/A>`

Execution Routing: `<Primary Execution Environment / Alternative Execution Environment / Technical Executor / User / other applicable route / N/A>`

Direct-Transfer Instruction State: `<EXPLICITLY REQUESTED / NOT REQUESTED / N/A>`

Handoff Continuity State: `<VERIFIED / PARTIAL / UNVERIFIED / STALE OR CONFLICTED / N/A>`

Readiness: `<READY / REQUIRES GOVERNANCE RECOVERY / CONTINUITY CONFLICT / EXECUTION-ENVIRONMENT CONFLICT / BLOCKED / OTHER>`

# HANDOFF GENERATION RULES

Ketika user memilih atau membutuhkan session transition:

- gunakan latest applicable `Handoff`;
- isi required core dan hanya sertakan conditional sections yang material terhadap continuity;
- gunakan transition type yang sesuai (`NRP` atau `EMERGENCY`) dan jangan infer `NRP CONFIRMED` untuk emergency transition;
- reconcile/consolidate `Workplan` pada applicable closure gate ketika diperlukan untuk menentukan atau menjelaskan next planning target;
- jangan memaksa `Workplan` refresh hanya karena authoritative project atau `Repository` state maju selama active workstream;
- omit obsolete history, rejected options, resolved troubleshooting, redundant logs, dan superseded planning detail yang tidak material terhadap continuity;
- preserve applicable authoritative anchors/state references dan next target;
- preserve material blockers, Deferred Items, risks, dan unresolved continuity payload;
- untuk `EMERGENCY` transition, preserve known continuity gaps, at-risk context, required recovery actions, dan state yang memerlukan revalidation;
- pertahankan unresolved thread-level continuity payload sampai durable reconciliation atau explicit disposition;
- jangan copy full `Workplan`, full `Concept`, atau full authoritative source kecuali material continuity memang memerlukannya;
- keep executor-instruction detail keluar dari Handoff kecuali routing atau execution context yang materially diperlukan receiving GPT/session;
- generation Handoff tidak memberikan technical execution authorization maupun direct-transfer/invocation authorization;
- jangan invoke atau transfer ke `Technical Executor` hanya karena Handoff merekomendasikan execution route tertentu;
- jika user secara eksplisit meminta direct transfer, derive separate `Agent Instruction` berdasarkan latest applicable `Agent Instruction` governance dan route hanya instruction tersebut;
- generated Handoff harus cukup untuk recovery tanpa mengharuskan receiving session merekonstruksi obsolete prior-session history.

# CLOSURE STATEMENT

Handoff dianggap complete ketika receiving GPT/session dapat memulihkan objective continuity `Project` secara akurat tanpa merekonstruksi obsolete history, tanpa mengandalkan Handoff sebagai pengganti applicable authoritative source, dan tanpa memperlakukan Handoff sebagai technical execution authorization atau direct-transfer/invocation authorization.

Untuk transition type `NRP`, Handoff harus cukup untuk normal continuity bootstrap pada confirmed continuity boundary.

Untuk transition type `EMERGENCY`, Handoff harus cukup untuk elevated continuity recovery dengan material continuity gaps, at-risk context, unresolved state, dan required recovery actions yang tetap explicit.