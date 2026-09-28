# Seven-Tab Site Settings Completion & Hardening Pre-contract

Status: ACCEPTED PRE-CONTRACT / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Project: COPOT

Target workstream: Seven-Tab Site Settings Completion & Hardening

## 1. Purpose

Pre-contract ini mendefinisikan target capability, ownership boundary, sequencing, dependency, dan acceptance direction untuk penyelesaian Site Settings menjadi tujuh area operasional:

1. Site Identity
2. System
3. Security
4. Email
5. Modules
6. Redirects
7. System Health

Database lifecycle dan compatibility tetap merupakan underlying capability dan bukan area Site Settings kedelapan.

Pre-contract ini adalah planning artifact. Ia bukan implementation authorization, bukan repository implementation authority, dan bukan release authorization.

## 2. Existing delivered baseline

Current delivered Site Settings baseline berasal dari Webcore Site Settings & Appearance Consolidation yang telah accepted dan locked.

Baseline tersebut menyediakan empat area yang sudah delivered:

1. Site Identity
2. System
3. Modules
4. System Health

Workstream ini tidak membangun ulang empat area tersebut tanpa kebutuhan yang dibuktikan. Existing capability harus dipertahankan, direvalidasi, dan di-hardening hanya bila current evidence menunjukkan kebutuhan.

Security, Email, dan Redirects melengkapi target tujuh area melalui capability work yang terpisah sebelum final Site Settings integration.

## 3. Governing principles

Workstream ini mengikuti prinsip berikut:

- capability harus ada sebelum diproyeksikan sebagai operator surface;
- Site Settings adalah product projection, bukan ownership layer baru;
- underlying authority tetap singular;
- secrets tidak disimpan sebagai generic Settings values;
- current accepted capability tidak dibangun ulang tanpa kebutuhan;
- target capability harus compatible dengan current lifecycle, migration, adoption, dan multi-installation boundaries;
- implementation detail tidak boleh mengubah accepted semantic boundary;
- visual/product refinement dilakukan hanya ketika material terhadap usability atau product intent.

## 4. Security capability baseline

Security menjadi Webcore system-level capability.

Baseline capability mencakup:

### 4.1 Password policy

Security menyediakan configurable password policy yang mencakup:

- minimum password length;
- reasonable maximum password length;
- rejection terhadap blank atau invalid password values;
- tanpa mandatory composition rule seperti kewajiban uppercase, lowercase, digit, atau symbol.

Password hashing dan verification tetap menggunakan canonical Webcore password authority.

### 4.2 Login throttling

Security menyediakan bounded login throttling untuk mengurangi brute-force attempts.

Behavior harus:

- deterministic;
- fail-closed;
- tidak membocorkan validitas account melalui response behavior;
- compatible dengan existing authentication flow.

Exact thresholds, delay profile, dan duration menjadi contract-level decision.

### 4.3 Failed-login tracking

Security menyediakan persisted failed-login state minimum per account.

Persisted state harus cukup untuk mendukung:

- failure counting;
- active failure window;
- last failure evidence;
- temporary lockout state.

Persisted state bukan general security analytics store.

### 4.4 Temporary lockout

Security menyediakan bounded temporary account lockout setelah threshold kegagalan yang ditentukan tercapai.

Lockout:

- bersifat temporary;
- tidak mengubah permanent account status;
- tidak menggantikan inactive-user semantics;
- tidak memberikan account-enumeration signal.

Exact threshold dan duration menjadi contract-level decision.

### 4.5 Session timeout

Security menyediakan runtime-editable authenticated-session timeout.

Timeout:

- memiliki bounded allowed range;
- memiliki safe default;
- berlaku melalui canonical Webcore session authority;
- tidak menciptakan parallel session implementation.

Exact default dan bounds menjadi contract-level decision.

### 4.6 Persistent self-session registry

Webcore menyediakan persistent canonical registry untuk authenticated sessions milik current user.

Registry mendukung:

- inventory session milik current user;
- current-session identification;
- bounded browser/device descriptor derived from available request metadata;
- created time;
- last-active evidence;
- expiry evidence;
- revocation state.

Device descriptor adalah descriptive runtime evidence dan bukan verified physical-device identity.

Raw PHP session identifier tidak boleh dipersist sebagai durable session identity.

### 4.7 Self-session revocation

Current user dapat:

- melihat session miliknya;
- revoke specific own session;
- sign out all other own sessions;
- logout/revoke current session melalui canonical logout behavior.

Revoked atau expired persistent session tidak boleh tetap dianggap authenticated.

### 4.8 Sensitive-action re-authentication

Security menyediakan bounded re-authentication untuk sensitive actions.

Re-authentication menggunakan canonical password verification authority dan menghasilkan temporary proof bahwa current authenticated user telah diverifikasi kembali.

Exact validity window dan sensitive-action integration menjadi contract-level decision.

### 4.9 Security event baseline

Security menyediakan persisted security-event baseline untuk event yang materially relevant terhadap authentication dan session lifecycle.

Baseline event families mencakup:

- login success/failure;
- temporary lockout;
- logout;
- session creation/revocation;
- relevant policy changes;
- sensitive-action re-authentication result.

Security event records harus sanitized dan tidak menyimpan credential atau secret material.

## 5. Security persistence boundary

Security operational state menggunakan dedicated Webcore-owned persistence.

Dedicated persistence digunakan untuk:

- failed-login state;
- session registry;
- security events;
- additional narrowly required operational security state.

Generic Settings persistence tidak digunakan untuk operational security state atau secret material.

Schema dan migration changes yang diperlukan berada dalam Webcore lifecycle authority.

## 6. System Email capability baseline

System Email menjadi Webcore system-level capability.

Baseline capability mencakup:

- one active outbound mail transport configuration;
- one active sender identity;
- Webcore outbound mail abstraction;
- optional operator-triggered test delivery;
- sanitized delivery result;
- explicit failure state.

System Email baseline tidak menjadi generic provider marketplace atau arbitrary multi-transport routing framework.

## 7. Email secret persistence

SMTP or equivalent transport credentials tidak disimpan dalam generic Settings database values.

Secret values menggunakan environment-backed persistence sesuai existing Webcore environment/credential boundary.

Operator surface boleh:

- menunjukkan apakah secret telah configured;
- menerima replacement secret;
- melakukan controlled test delivery;

tetapi tidak boleh menampilkan persisted secret value kembali.

## 8. Redirects capability

Redirects tetap merupakan Webcore-native capability.

Workstream ini tidak membuat Redirects menjadi Module dan tidak menciptakan competing redirect authority.

WU4 merekonsiliasi Redirects operator projection agar konsisten dengan:

- existing Webcore redirect ownership;
- current permission model;
- current persistence;
- current routing behavior;
- final Seven-Tab Site Settings structure.

## 9. Permissions

Workstream memperkenalkan atau merekonsiliasi permission minimum berikut:

- `security.manage`
- `email.manage`
- existing `redirects.manage`

Administrator role mapping harus direkonsiliasi sesuai existing role/permission authority.

Permission implementation tidak boleh melemahkan existing `admin.access`, lifecycle permissions, Module permissions, atau other accepted permission boundaries.

## 10. Site Settings integration

Final Seven-Tab Site Settings target adalah:

1. Site Identity
2. System
3. Security
4. Email
5. Modules
6. Redirects
7. System Health

Final integration hanya dilakukan setelah Security, Email, dan Redirects capability prerequisites tersedia dan accepted.

Existing four delivered areas tetap mempertahankan ownership dan underlying capability authority masing-masing.

Site Settings hanya menyatukan operator projection dan navigation hierarchy.

## 11. Database and lifecycle impact

Security dan Email capability dapat membutuhkan:

- Webcore-owned tables;
- indexes;
- schema generation updates;
- Core migration registration;
- target package requirement updates;
- compatibility/adoption evaluation updates.

Semua perubahan tersebut harus mengikuti existing Database Ownership & Lifecycle Management authority.

Workstream tidak menciptakan parallel migration framework.

## 12. Adoption and target-relative compatibility

Capability baru harus diperhitungkan terhadap target-relative compatibility dan adoption readiness.

Target requirement harus menjelaskan kebutuhan database/schema yang diperlukan oleh target Webcore.

Existing compatible database state tidak boleh dimutasi di luar requirement yang dibutuhkan oleh target capability.

Adoption behavior tetap tunduk pada accepted Database Lifecycle Adoption Compatibility authority.

## 13. Visual and product routing

Visual work hanya digunakan ketika material terhadap product intent atau operator usability.

Dua route diperbolehkan:

### Route A — Preview-first

Capability contract → GPT visual preview → user review/refinement → accepted visual reference → implementation.

### Route B — Implementation-first

Capability contract → functional implementation → GPT/user review → optional user Figma refinement → accepted adjustment → implementation refinement.

Figma adalah optional prototyping/reference surface dan bukan implementation authority.

## 14. Work-unit topology

Workstream menggunakan enam Work Unit:

### WU1 — Capability Contract & Current-State Reconciliation

Dependency: NONE

Objective:

- finalize capability contract;
- reconcile accepted scope terhadap current implementation evidence;
- establish Security, Email, Redirects, migration, permission, adoption, dan Site Settings integration boundaries.

WU1 tidak mengotorisasi implementation.

### WU2 — Security Platform Capability Baseline

Dependency: HARD → WU1

Objective:

Implement accepted Security platform capability baseline.

### WU3 — System Email Platform Capability Baseline

Dependency: HARD → WU1

Objective:

Implement accepted System Email capability baseline.

### WU4 — Webcore Redirects Operator Projection Reconciliation

Dependency: HARD → WU1

Objective:

Reconcile Webcore Redirects operator projection terhadap accepted Seven-Tab target.

WU2, WU3, dan WU4 dapat berjalan paralel setelah WU1 apabila dependency masing-masing satisfied.

### WU5 — Seven-Tab Site Settings Integration & Hardening

Dependency: HARD → WU2 + WU3 + WU4

Objective:

- integrate Security, Email, dan Redirects;
- preserve and harden Site Identity, System, Modules, dan System Health;
- reconcile final seven-tab navigation and operator experience;
- perform required visual/product refinement.

### WU6 — Cross-Capability Acceptance & Closure

Dependency: HARD → WU1 + WU2 + WU3 + WU4 + WU5

Objective:

- cross-capability validation;
- permission verification;
- migration/lifecycle verification;
- adoption compatibility verification;
- product/human acceptance where material;
- documentation/planning reconciliation;
- workstream closure evidence.

## 15. Contract decisions still required

Pre-contract ini intentionally tidak menetapkan contract-level implementation semantics berikut:

- exact password minimum and maximum;
- login failure window;
- throttling progression;
- lockout threshold and duration;
- session timeout default and bounds;
- persistent session lifecycle details;
- session last-seen update behavior;
- sensitive re-authentication validity window;
- exact Security table/schema shape;
- exact Core migration identity, sequence, and target mapping;
- exact Email transport configuration fields;
- exact test-delivery behavior;
- exact permission migration mechanics;
- exact Site Settings UI composition.

Item tersebut harus ditentukan dan diterima pada contract stage sebelum relevant implementation authorization.

## 16. Authorization boundary

Status pre-contract ini:

- semantic scope: ACCEPTED PRE-CONTRACT;
- planning authority: YES;
- contract authority: NO;
- implementation authorization: NONE;
- repository implementation mutation authorization: NONE;
- release/tag/publication authorization: NONE.

Materialization pre-contract tidak mempromosikan artifact menjadi implementation authority.

Promotion ke authoritative contract membutuhkan explicit GPT/user-side contract preparation, reconciliation, dan user acceptance.

## 17. Acceptance condition for pre-contract stage

Pre-contract stage dianggap lengkap ketika:

- exact artifact telah dipersist secara durable;
- Work Unit topology tetap utuh;
- accepted capability boundary tetap utuh;
- no unintended future/deferred capability masuk ke current scope;
- repository diff hanya memuat authorized planning materialization;
- no implementation mutation dilakukan;
- materialized artifact dapat digunakan sebagai provenance untuk contract preparation.

## 18. Technical pre-contract reconciliation

Technical review terhadap current Repository mengonfirmasi bahwa workstream ini feasible, tetapi contract preparation wajib membawa technical boundary berikut tanpa mengubah accepted scope atau Work Unit topology.

### 18.1 Canonical Security integration boundary

Security controls harus terintegrasi melalui canonical Webcore Auth/Session authority, bukan diimplementasikan secara terpisah pada individual login routes.

Semua login entry point yang applicable harus menggunakan Security-enforced authentication path yang sama.

Password policy harus digunakan secara konsisten pada seluruh password-entry boundary yang berada dalam scope Webcore, termasuk administrator creation pada installer, Users & Access user creation/password change, dan password-changing flow lain yang applicable.

Exact policy values dan behavior detail tetap menjadi contract-level decision.

### 18.2 Runtime session-timeout resolution

Runtime-editable session timeout membutuhkan contract-level resolver/data-flow yang menjelaskan:

- precedence antara runtime Site Settings dan environment/default configuration;
- request-time resolution melalui canonical Session/Auth authority;
- hubungan terhadap cookie lifetime dan server-side expiry enforcement;
- behavior terhadap session yang telah diterbitkan ketika timeout berubah.

Exact precedence, default, bounds, dan existing-session behavior tetap menjadi contract-level decision.

### 18.3 Security persistence dan lifecycle integration inventory

Contract wajib merekonsiliasi setiap authority yang terdampak oleh Security persistence, minimum mencakup:

- canonical schema;
- Core migration registry dan declaration;
- table ownership classification;
- namespace/table-name mapping;
- installer schema readiness;
- database health verification;
- canonical schema baseline identity;
- package target requirements;
- target-relative compatibility dan adoption planning;
- committed lifecycle/migration-ledger verification.

Pre-contract ini tidak menetapkan exact table name, migration identity, sequence, schema identity, atau target-version mapping.

### 18.4 System Email technical boundary

System Email adalah new Webcore platform capability, bukan settings-only projection.

WU3 harus mendefinisikan minimum:

- bounded secret-provider/write path yang compatible dengan environment/credential boundary;
- redacted operator read model;
- outbound mail transport abstraction;
- explicit delivery/failure model;
- optional controlled test-delivery path;
- behavior yang memastikan mail configuration/delivery tidak menjadi dependency untuk installation atau login kecuali kemudian diotorisasi secara eksplisit sebagai scope terpisah.

Exact transport fields, provider semantics, test-delivery authorization, dan persistence detail tetap menjadi contract-level decision.

### 18.5 Redirects ownership dan projection reconciliation

Redirects tetap Webcore-owned.

Current module-hosted route/service implementation tidak dengan sendirinya mengubah ownership authority.

WU4 contract preparation harus memetakan canonical Redirect repository/resolver, operator routes, permission, table accessor, dan final Site Settings projection dengan invariant:

- tidak ada duplicate redirect authority;
- tidak ada duplicate physical persistence;
- tidak ada implicit ownership transfer.

Perubahan ownership, bila pernah diperlukan, adalah separate approval boundary dan bukan bagian implisit workstream ini.

### 18.6 Permission projection matrix

Contract wajib mendefinisikan authorization matrix untuk Security, Email, dan Redirects yang minimum membedakan:

- navigation visibility;
- read access;
- mutation access;
- sensitive-action authorization;
- Administrator role seeding/reconciliation.

Contract harus menetapkan hubungan `settings.update` dengan `security.manage`, `email.manage`, dan existing `redirects.manage` tanpa melemahkan accepted permission boundaries.

### 18.7 Contract-stage acceptance and compatibility matrix

Contract-stage acceptance wajib mencakup minimum:

- fresh install;
- existing-install migration/upgrade;
- adoption dan target-relative compatibility;
- namespace isolation;
- lifecycle/database health;
- permission separation;
- secret redaction;
- mail configuration/delivery non-dependency terhadap installation/login;
- failed-login account-enumeration resistance;
- lockout expiry;
- session revocation effectiveness;
- current-session identification;
- concurrent/stale session behavior;
- sensitive re-authentication expiry;
- security-event sanitization.

Human/product acceptance untuk final Security dan Email operator surface tetap dilakukan bila material terhadap UI/product intent.

### 18.8 Reconciliation boundary

Technical reconciliation ini menambahkan implementation-grounded contract requirements tetapi tidak mengunci decision yang pada Section 15 masih dinyatakan sebagai contract-level decision.

Technical finding atau feasibility evidence tidak mengotorisasi implementation, contract promotion, release, atau scope expansion.
