# Seven-Tab Site Settings Completion & Hardening Contract Candidate

Status: CONTRACT CANDIDATE / NOT AUTHORITATIVE / NOT IMPLEMENTATION-AUTHORIZED

Project: COPOT

Target workstream: Seven-Tab Site Settings Completion & Hardening

Source Pre-contract:

`precontracts/seven_tab_site_settings_completion_hardening_precontract.md`

## 1. Candidate objective

Contract candidate ini menerjemahkan accepted pre-contract dan technical pre-contract reconciliation menjadi contract-level capability semantics untuk penyelesaian Site Settings menjadi tujuh area operasional:

1. Site Identity
2. System
3. Security
4. Email
5. Modules
6. Redirects
7. System Health

Candidate ini belum authoritative dan belum mengotorisasi implementation. Promotion membutuhkan review dan acceptance GPT/user-side yang eksplisit.

## 2. Governing capability model

Site Settings tetap merupakan operator projection, bukan ownership layer baru.

Capability authority tetap berada pada owner masing-masing:

- Site Identity dan System pada existing Webcore settings capability;
- Security pada Webcore system-level Security capability;
- Email pada Webcore system-level outbound mail capability;
- Modules pada existing Module lifecycle/operator capability;
- Redirects pada Webcore-native Redirect capability;
- System Health pada existing Webcore health capability.

Database lifecycle dan compatibility tetap underlying capability dan bukan tab kedelapan.

## 3. Security authority and integration

Security controls harus terintegrasi melalui canonical Webcore Auth/Session authority.

Semua login entry point yang applicable wajib menggunakan Security-enforced authentication path yang sama. Route-level duplicate authentication policy tidak diperbolehkan.

Password hashing dan verification tetap menggunakan canonical Webcore password authority.

Password policy service yang sama harus digunakan pada seluruh password-entry boundary dalam scope Webcore, termasuk:

- administrator creation pada installer;
- Users & Access user creation;
- Users & Access password change;
- future Webcore password-change flow yang applicable.

## 4. Password policy

Default password policy:

- minimum length: 12 characters;
- maximum length: 128 characters;
- blank atau invalid password values ditolak;
- tidak ada mandatory composition rule untuk uppercase, lowercase, digit, atau symbol.

Policy harus configurable tanpa menciptakan parallel password authority.

Perubahan policy tidak me-retrofit existing password hashes dan tidak memaksa password rotation kecuali kemudian diotorisasi sebagai capability terpisah.

## 5. Login throttling and failed-login semantics

Failed-login tracking menggunakan persisted Webcore-owned operational state.

Default failure window: 15 minutes.

Default progression:

- attempts 1-4: normal authentication path tanpa artificial delay;
- attempts 5-7: bounded delay;
- attempts 8-9: stronger bounded delay;
- attempt 10 within active failure window: temporary lockout.

Exact bounded delay values boleh ditentukan pada implementation design selama tetap deterministic, bounded, testable, dan tidak mengubah threshold contract.

Authentication responses dan timing behavior tidak boleh secara material membocorkan validitas account.

Failure state harus expire/reset sesuai contract semantics setelah successful authentication atau expiration of the active failure window, subject to implementation design yang konsisten dan deterministic.

## 6. Temporary lockout

Default lockout threshold: 10 failed attempts within the active failure window.

Default lockout duration: 15 minutes.

Temporary lockout:

- tidak mengubah permanent account status;
- tidak menggantikan inactive-user semantics;
- harus expire secara otomatis;
- tidak boleh memberikan account-enumeration signal.

## 7. Runtime session timeout

Default authenticated idle timeout: 120 minutes.

Runtime Site Settings value menjadi operational value utama setelah aplikasi berjalan.

Environment/configuration value berfungsi sebagai bootstrap/default fallback ketika runtime setting belum tersedia atau belum dapat dibaca secara valid.

Session timeout resolution harus terjadi melalui canonical Session/Auth authority.

Timeout enforcement harus mencakup server-side expiry/idle evaluation dan tidak bergantung hanya pada client cookie lifetime.

Perubahan timeout berlaku pada evaluation berikutnya terhadap existing sessions. Perubahan setting tidak dengan sendirinya memaksa logout seluruh existing sessions.

Implementation design harus menjaga hubungan konsisten antara runtime timeout, cookie lifetime, garbage-collection configuration bila applicable, dan server-side authenticated-session validity.

## 8. Persistent self-session registry

Webcore menyediakan persistent canonical registry untuk authenticated sessions milik current user.

Durable session identity harus opaque dan tidak boleh menyimpan raw PHP session identifier sebagai durable identity.

Registry minimum menyimpan atau dapat merekonstruksi:

- durable session identity;
- owning user identity;
- current-session identification evidence;
- bounded browser/device descriptor;
- created time;
- last-active evidence;
- expiry/idle evidence;
- revocation state.

Device descriptor adalah descriptive runtime evidence, bukan verified physical-device identity.

Expired, revoked, atau idle-invalid session tidak boleh dianggap authenticated.

## 9. Session activity and last-seen behavior

`last_seen` atau equivalent last-active evidence harus dipersist secara throttled, bukan melalui write pada setiap request.

Exact write-throttle interval adalah implementation detail selama:

- cukup presisi untuk operator-facing session inventory;
- tidak menimbulkan write amplification yang tidak perlu;
- tidak melemahkan timeout/revocation correctness;
- dapat diuji secara deterministic.

## 10. Self-session revocation

Current user dapat:

- melihat own sessions;
- revoke specific own session;
- sign out all other own sessions;
- revoke/logout current session melalui canonical logout behavior.

`Sign out all other sessions` harus mengecualikan current session.

Revocation harus efektif pada authentication evaluation berikutnya dan tidak boleh bergantung hanya pada browser-side cookie deletion.

## 11. Sensitive-action re-authentication

Sensitive-action re-authentication menggunakan canonical password verification authority.

Default re-authentication validity window: 5 minutes.

Successful re-authentication:

- menghasilkan temporary proof untuk current authenticated session;
- tidak membuat authenticated session baru;
- tidak memperpanjang scope authorization di luar sensitive action yang applicable.

Failed re-authentication tidak dengan sendirinya logout current authenticated session, tetapi harus menghasilkan sanitized failure evidence bila event logging applicable.

Sensitive actions yang membutuhkan re-authentication harus ditentukan secara explicit pada relevant implementation slice.

## 12. Security event baseline

Webcore menyediakan persisted sanitized security-event baseline untuk event materially relevant terhadap authentication dan session lifecycle.

Minimum event families:

- login success;
- login failure;
- temporary lockout;
- logout;
- session creation;
- session revocation;
- relevant Security policy changes;
- sensitive-action re-authentication success/failure.

Security event records tidak boleh menyimpan plaintext credential, secret, raw password material, atau equivalent sensitive authentication material.

Event design bukan general analytics platform.

## 13. Security persistence and lifecycle authority

Security operational state menggunakan dedicated Webcore-owned persistence.

Generic Settings persistence tidak digunakan untuk failed-login state, persistent session registry, security events, atau secret material.

Implementation contract wajib merekonsiliasi seluruh affected lifecycle authorities, minimum:

- canonical schema;
- Core migration registry/declaration;
- table ownership classification;
- namespace/table-name mapping;
- installer schema readiness;
- database health verification;
- canonical schema baseline identity;
- package target requirements;
- target-relative compatibility/adoption planning;
- committed lifecycle/migration-ledger verification.

Exact Security table names, migration IDs, sequence, schema generation identity, dan target package version tidak ditetapkan oleh candidate ini. Nilai tersebut harus repository-derived melalui technical design dan diverifikasi terhadap current lifecycle authority sebelum implementation authorization.

## 14. System Email capability

System Email adalah new Webcore platform capability, bukan settings-only projection.

Baseline menyediakan:

- one active outbound mail transport configuration;
- one active sender identity;
- Webcore-level outbound mail abstraction;
- explicit delivery result;
- explicit failure state;
- optional operator-triggered controlled test delivery.

Capability ini tidak menjadi provider marketplace atau arbitrary multi-transport routing framework.

## 15. Email configuration and secret boundary

Non-secret transport configuration dapat menggunakan bounded Webcore configuration/settings projection sesuai implementation design.

Secret values seperti SMTP password atau equivalent credential:

- tidak disimpan dalam readable generic Settings values;
- menggunakan bounded environment-backed credential write path yang compatible dengan existing environment/credential boundary;
- dapat ditulis atau diganti oleh authorized operator;
- tidak boleh dibaca kembali dalam plaintext melalui operator UI;
- hanya boleh diproyeksikan sebagai configured/not-configured atau equivalent redacted state.

Supported transport fields minimum harus cukup untuk satu conventional SMTP-compatible transport, termasuk host, port, encryption/security mode bila applicable, username bila applicable, credential secret, sender address, dan sender name.

Exact field naming dan adapter implementation boleh ditentukan pada technical design selama tidak memperluas capability menjadi multi-provider framework.

## 16. Email test delivery

Test delivery adalah optional operator action.

Authorization minimum mengikuti `admin.access + email.manage`.

Test result harus sanitized dan tidak menampilkan credential atau low-level secret material.

Failure of test delivery:

- tidak mengubah installation availability;
- tidak mengubah login availability;
- tidak menyebabkan global application failure;
- tidak otomatis mengubah transport configuration selain explicit operator save/replace action.

Persistent storage of detailed delivery history bukan requirement baseline. Implementation boleh menyediakan bounded last-result evidence bila diperlukan untuk operator feedback dan tidak berubah menjadi general mail analytics/logging subsystem.

## 17. Redirects ownership and projection

Redirects tetap Webcore-owned capability.

Current module-hosted route/service location tidak mengubah ownership authority.

WU4 harus merekonsiliasi:

- canonical Redirect repository/resolver;
- operator routes;
- `redirects.manage` permission;
- table accessor;
- final Site Settings projection.

Invariant:

- satu redirect authority;
- satu physical persistence authority;
- tidak ada implicit ownership transfer;
- tidak ada duplicate resolver/repository stack.

Perubahan ownership membutuhkan separate approval dan berada di luar implicit scope contract ini.

## 18. Permission model

Capability-specific permissions:

- Security mutation authority: `security.manage`;
- Email mutation authority: `email.manage`;
- Redirect mutation authority: existing `redirects.manage`.

Existing admin shell access boundary tetap berlaku melalui `admin.access`.

Capability-specific mutation tidak membutuhkan `settings.update` sebagai permission tambahan.

Default operator authorization:

- Security navigation/read/mutation: `admin.access + security.manage`;
- Email navigation/read/mutation: `admin.access + email.manage`;
- Redirects navigation/read/mutation: `admin.access + redirects.manage`;
- Site Identity/System generic settings mutation tetap mengikuti existing `settings.update` boundary;
- Modules dan System Health mempertahankan existing capability permissions.

Sensitive Security action dapat menambahkan re-authentication requirement tanpa menambahkan unrelated permission dependency.

Administrator role seeding/reconciliation harus mencakup `security.manage` dan `email.manage` sesuai existing role/permission authority.

## 19. Seven-Tab Site Settings integration

Final order:

1. Site Identity
2. System
3. Security
4. Email
5. Modules
6. Redirects
7. System Health

Site Settings hanya menyatukan navigation hierarchy dan operator projection.

Existing capability ownership tetap dipertahankan.

Security, Email, dan Redirects hanya diproyeksikan setelah capability prerequisites masing-masing tersedia dan accepted.

Exact visual composition, control placement, form grouping, dan micro-interaction detail tidak dikunci oleh contract ini dan dapat direfine pada WU5 sesuai accepted product flow.

## 20. Database lifecycle, target requirements, and adoption

New Webcore persistence harus mengikuti accepted Database Ownership & Lifecycle Management authority dan tidak boleh menciptakan parallel migration framework.

Target package requirement harus secara eksplisit menyatakan schema/migration capability yang dibutuhkan target Webcore.

Target-relative compatibility dan adoption readiness harus memperhitungkan Security persistence dan related schema requirements.

Existing compatible database state tidak boleh dimutasi di luar requirement target yang dibutuhkan.

Exact migration identity, sequence, schema generation identity, dan target package version harus berasal dari repository-grounded technical design, bukan ditetapkan secara arbitrer dalam candidate ini.

## 21. Acceptance matrix

Relevant implementation harus menyediakan evidence untuk minimum:

### Security behavior

- password policy enforcement di seluruh in-scope password-entry boundaries;
- account-enumeration resistance pada failed login/lockout;
- failure-window behavior;
- bounded throttling progression;
- temporary lockout threshold/expiry;
- runtime session-timeout enforcement;
- behavior existing sessions setelah timeout change;
- current-session identification;
- own-session revocation effectiveness;
- sign-out-other-sessions semantics;
- stale/concurrent session behavior;
- re-authentication expiry dan failure behavior;
- sanitized security events.

### Email behavior

- secret redaction;
- secret replace/write semantics;
- configured/not-configured read model;
- outbound transport success/failure handling;
- sanitized test-delivery result;
- installation/login non-dependency terhadap mail configuration atau delivery failure.

### Permission behavior

- navigation visibility;
- read access;
- mutation access;
- capability separation;
- Administrator role seeding/reconciliation;
- no unintended `settings.update` dependency for Security/Email/Redirects.

### Lifecycle and compatibility

- fresh install;
- existing-install migration/upgrade;
- target-relative adoption compatibility;
- namespace isolation;
- canonical schema verification;
- migration registry/declaration consistency;
- ownership catalog consistency;
- installer readiness;
- database/lifecycle health;
- committed migration-ledger verification.

### Product acceptance

Human/product acceptance tetap dilakukan bila Security atau Email operator surface materially membutuhkan UI/product refinement.

## 22. Work-unit topology

Workstream tetap menggunakan enam Work Unit:

### WU1 — Capability Contract & Current-State Reconciliation

Dependency: NONE

Objective:

- finalize/promote accepted contract;
- reconcile contract against current implementation evidence;
- establish exact technical implementation slices for downstream WUs.

WU1 tidak dengan sendirinya mengotorisasi downstream implementation sebelum relevant authorization diberikan.

### WU2 — Security Platform Capability Baseline

Dependency: HARD -> WU1

Objective: implement accepted Security platform capability baseline.

### WU3 — System Email Platform Capability Baseline

Dependency: HARD -> WU1

Objective: implement accepted System Email platform capability baseline.

### WU4 — Webcore Redirects Operator Projection Reconciliation

Dependency: HARD -> WU1

Objective: reconcile Webcore Redirects operator projection terhadap accepted Seven-Tab target.

WU2, WU3, dan WU4 dapat berjalan paralel setelah WU1 apabila dependency masing-masing satisfied dan masing-masing implementation slice diotorisasi.

### WU5 — Seven-Tab Site Settings Integration & Hardening

Dependency: HARD -> WU2 + WU3 + WU4

Objective:

- integrate Security, Email, dan Redirects projection;
- preserve/harden existing Site Identity, System, Modules, dan System Health;
- reconcile final navigation/operator experience;
- perform product/visual refinement bila material.

### WU6 — Cross-Capability Acceptance & Closure

Dependency: HARD -> WU1 + WU2 + WU3 + WU4 + WU5

Objective:

- cross-capability validation;
- permission verification;
- migration/lifecycle verification;
- adoption compatibility verification;
- product/human acceptance where material;
- documentation/planning reconciliation;
- closure evidence.

## 23. Explicit exclusions

Current contract candidate tidak mencakup:

- multi-user session administration;
- revocation of another user's sessions;
- provider marketplace atau arbitrary multi-transport mail routing;
- general security analytics platform;
- forced password rotation policy;
- ownership transfer of Redirects;
- parallel authentication/session authority;
- parallel migration framework;
- implicit adoption of unrelated Deferred Items.

## 24. Authorization boundary

Candidate status:

- semantic contract candidate: YES;
- authoritative contract: NO;
- implementation authorization: NONE;
- repository implementation mutation authorization: NONE;
- release/tag/publication/deployment authorization: NONE.

Physical materialization candidate tidak mempromosikan artifact menjadi authoritative contract.

Promotion membutuhkan explicit GPT/user review and acceptance.

Technical review dapat menemukan technical delta, tetapi tidak memiliki promotion authority.

## 25. Promotion readiness criteria

Candidate siap dipertimbangkan untuk promotion hanya jika:

- accepted pre-contract semantics tetap terjaga;
- accepted technical reconciliation telah tercakup;
- contract-level policy decisions telah tercakup;
- tidak ada unresolved semantic conflict;
- repository-grounded values yang memang tidak boleh diinvent seperti migration identity/target mapping tetap ditandai sebagai technical-design-derived;
- no scope expansion atau Deferred Item adoption terjadi secara implisit;
- candidate telah melalui GPT/user review;
- jika technical verification menemukan material conflict, conflict tersebut telah direkonsiliasi sebelum promotion.
