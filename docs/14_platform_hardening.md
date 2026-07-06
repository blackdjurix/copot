# M2.4 Platform Hardening

## Status

Current phase.

Batch 1 documentation, repository audit, architecture, scope lock, non-goals, batch plan, acceptance criteria, and risk register are complete.

Batch 2 Minimal Diagnostics Baseline is implemented and focused verification passes.

Batch 3 Application Error Boundary and Rendering Safety is implemented and focused verification passes.

Batch 4 Admin In-Shell Errors is implemented and focused verification passes. Eligible authenticated Admin errors now render inside the existing shell while unsafe recovery conditions fall back to standalone sanitized responses without duplicate diagnostics logging.

M2.3 Minimal Site Capabilities is complete and released as v0.11.0.

Batch 5 Runtime, Security, Storage, and Deployment Hardening is implemented and focused verification passes. Batch 6 Unified Regression and Release Readiness requires separate implementation approval.

---

## 1. Objective

M2.4 is the final lean-M2 release gate. It hardens the existing copot runtime without widening the product surface or replacing the framework's lightweight shared-hosting architecture.

The milestone establishes:

* consistent application error boundaries;
* sanitized public and Admin error rendering;
* a minimal internal logging and redaction contract;
* controlled storage and filesystem failure behavior;
* focused authentication, permission, CSRF, upload, and escaping review;
* explicit runtime and deployment checks;
* one regression gate across M1 and lean M2.

M2.4 improves failure containment and diagnosability. It does not add a new end-user capability.

---

## 2. Audit Baseline

The Batch 1 audit found that existing capability-specific boundaries already provide useful foundations:

* frontend Theme failures use a generic response;
* Installer failures use controlled messages and avoid public credentials, SQL, paths, and traces;
* Admin and public templates generally escape plain text with `htmlspecialchars()`;
* Site Asset storage validates MIME, image structure, size, dimensions, generated names, containment, and symlink boundaries;
* Settings reads have controlled definition-default fallback;
* M2.1–M2.3 provide chained regression gates.

The audit also found the gaps owned by M2.4:

* no application-wide boundary covers normal bootstrap, route registration, module loading, dispatch, rendering, and response delivery;
* caught failures are frequently discarded because no internal logging baseline exists;
* several authenticated Admin `403`, `404`, `419`, `500`, and `503` paths render outside the Admin Shell;
* output-buffer cleanup and trusted rendered-fragment ownership are not consistent across renderers;
* storage cleanup failures are best-effort but not observable;
* production runtime, cookie, error-display, logging, and document-root expectations are not one explicit release checklist;
* the existing M2.3 regression chain does not yet cover the complete M2.4 failure matrix.

---

## 3. Scope Lock

M2.4 includes only the following hardening work.

### 3.1 Error boundaries

* Add one minimal early/normal application failure boundary covering failures that currently escape route-local handling.
* Preserve controlled route and domain validation behavior when the failure is expected.
* Produce stable generic public responses for unexpected failures.
* Preserve meaningful HTTP status codes without exposing the underlying exception.
* Remove partial rendered output when rendering fails.

### 3.2 Sanitized rendering

* Audit public, Installer, authentication, Admin, module, and Theme rendering contexts.
* Keep plain text escaped for its HTML context.
* Define the existing page-content slot as a trusted rendered fragment owned only by controlled internal renderers.
* Prevent raw exception messages, warnings, paths, SQL, credentials, environment data, request payloads, stack traces, and client filenames from reaching responses.
* Keep production responses sanitized regardless of `APP_DEBUG`.

### 3.3 Admin in-shell errors

* Render authenticated Admin errors inside the existing Admin Shell when `Application`, authentication, the current user, and `AdminPageRenderer` remain safely available.
* Preserve the original error status.
* Use the existing Admin alert, panel, and action patterns without redesigning Admin.
* Use a standalone sanitized response when bootstrap, session, authentication, or Admin rendering is unavailable.

### 3.4 Internal logging baseline

* Add one small request-synchronous local diagnostic boundary outside the public document root.
* Record unexpected server failures and material degraded storage/runtime failures.
* Correlate a safe response error reference with its internal record where a server error is rendered.
* Fail safely when logging itself is unavailable.

### 3.5 Storage and filesystem failures

* Cover missing, unreadable, unwritable, symlinked, partially written, rename-failed, and delete-failed local paths used by existing runtime capabilities.
* Preserve the M2.3 rule that failed Logo/Favicon replacement does not deactivate the previous valid asset.
* Keep cleanup best-effort and observable without adding background cleanup.
* Ensure filesystem warnings do not become response content.

### 3.6 Runtime, security, and deployment review

* Review existing authentication, permission, CSRF, upload, session-cookie, and escaping boundaries for regressions.
* Lock production requirements for document root, error display, HTTPS/session cookies, private writable storage, and required PHP capabilities.
* Preserve PHP 8.2+ and ordinary PHP/MySQL shared-hosting operation.

### 3.7 Regression and release readiness

* Add focused failure-injection coverage.
* Add one M2.4 gate that includes the existing M2.3, M2.2, and M2.1 regression chain.
* Complete manual public, Admin, runtime, and representative shared-hosting verification before release readiness.

---

## 4. Non-Goals

M2.4 does not include:

* a database or schema change;
* a new dependency or package manager requirement;
* an Admin redesign, Admin theme system, or new component library;
* a general exception hierarchy rewrite;
* an enterprise logging framework, log viewer, metrics system, tracing system, or observability platform;
* an external logging, alerting, monitoring, or storage service;
* a queue, worker, scheduler, daemon, retry service, or global rate limiter;
* automated log rotation or retention infrastructure;
* a generic storage abstraction, cloud adapter, CDN, FTP, or SFTP fallback;
* a Media Library, media picker, arbitrary uploads, generic file management, or orphan browser;
* automatic background cleanup of stale site assets;
* broad Content, Taxonomy, Settings, Theme, Module, Router, or service-container redesign;
* public raw exception details in local or debug mode;
* a promise to catch PHP startup or parse failures that occur before the entrypoint can execute.

---

## 5. Error Taxonomy

M2.4 uses four operational categories.

### 5.1 Expected request and authorization outcomes

Examples include invalid input, authentication failure, permission denial, missing resources, and invalid CSRF tokens.

Expected statuses include `400`, `401` where applicable, `403`, `404`, `419`, and `422`.

These outcomes:

* use controlled messages;
* preserve the appropriate status;
* do not expose exception details;
* are not logged as unexpected server failures by default.

### 5.2 Controlled availability failures

Examples include an unavailable database, unavailable required storage, or an operation that cannot safely continue.

These outcomes normally use `503`, a controlled retry-safe message, and a warning/error diagnostic record when the failure is operationally useful.

### 5.3 Unexpected application failures

Unhandled `Throwable` instances from bootstrap, contribution loading, dispatch, rendering, or response preparation are unexpected failures.

They use:

* a generic `500` response unless a safer controlled status is already known;
* no raw exception content;
* one safe error reference;
* one best-effort internal diagnostic record.

Batch 3 defaults all unexpected failures to `500`. `503` may be selected only through an explicit controlled status when the caller positively knows the failure is availability-related. Exception class alone, including `PDOException`, and raw exception-message parsing are not sufficient classification.

### 5.4 Early runtime failures

Failures before normal `Application` and Admin services are available use a minimal standalone sanitized response. The boundary must not attempt to construct dependencies that already failed.

PHP startup, engine, web-server, and parse failures that happen before userland entrypoint execution remain the hosting environment's responsibility and must be handled by production PHP/web-server logging with `display_errors=Off`.

Batch 3 adds a fixed pre-autoload emergency boundary for failures while loading the Core autoloader. It has no Diagnostics dependency, exposes no reference, and emits only a fixed `500` document. After autoload succeeds, the bootstrap boundary may use standalone local Diagnostics. Application dispatch uses the request-scoped Diagnostics instance owned by `Application`.

---

## 6. Sanitized Rendering Policy

The response boundary is stricter than the internal diagnostic boundary.

Responses must never contain:

* raw exception or PHP warning text;
* stack traces;
* absolute filesystem paths;
* SQL, DSNs, database credentials, or environment contents;
* passwords, CSRF tokens, session identifiers, cookies, or authorization values;
* request bodies or uploaded client filenames;
* arbitrary module contribution diagnostics;
* partially rendered template output from a failed render.

Plain values must be escaped for their output context. URL, attribute, and text output must not be treated as interchangeable contexts.

The Admin and Theme layout `$content` slot is a trusted rendered fragment, not a generic string escape bypass. Only controlled internal view rendering may create that fragment. Request data, setting values, exception messages, and module metadata must be escaped before entering it.

Batch 3 does not introduce a `SafeHtml` type. `Response::html()`, Router scalar HTML returns, Theme `$content`, and Admin `$content` remain trusted internal contracts. This trust does not sanitize their inputs; each renderer remains responsible for contextual escaping before constructing the fragment.

Every Batch 3 bootstrap, dispatch, and PHP-view rendering boundary records its caller output-buffer level. On failure it removes all buffers it owns back to that exact level, without closing caller-owned buffers. Unbalanced nested buffers and direct output outside returned responses are treated as unexpected failures. `Response::send()` remains outside recovery because already-sent output cannot be replaced reliably.

`APP_DEBUG` must not enable raw exception rendering. Local diagnostics belong in the internal log or test output, not in an HTTP response.

---

## 7. Admin In-Shell Error Boundary

An Admin error may render inside the existing shell only when all of the following are true:

* normal `Application` construction succeeded;
* the request resolves under the configured Admin path;
* session and authentication state can be read safely;
* the current authenticated user is available;
* the Admin renderer can run without repeating the failed operation.

The in-shell page must:

* preserve `403`, `404`, `419`, `500`, or `503` as applicable;
* use a generic title and existing Admin UI patterns;
* avoid echoing the thrown exception;
* show a safe error reference only for unexpected server failures;
* avoid retry actions that repeat a state-changing request automatically.

Guest Admin login errors and failures before the conditions above are met remain sanitized standalone or login-page responses. M2.4 does not weaken permission checks in order to render a nicer error page.

---

## 8. Logging and Redaction Contract

The Batch 2 logging baseline is local, synchronous, small, and framework-owned. It is not a general logging framework.

`Application` owns one request-scoped `Diagnostics` instance. Its constructor is side-effect free. It does not create the log directory, open a file, register a handler, or require working database/config/runtime services.

The fixed sink is:

```text
storage/logs/copot.log
```

Each record is one append-locked JSON object followed by a newline. The service does not buffer across requests and does not use a secondary sink.

### 8.1 Minimum record

A diagnostic record may contain only the fields needed for diagnosis:

* ISO-8601 timestamp;
* severity;
* stable event name;
* safe error reference when present;
* exception class;
* controlled and length-limited summary;
* request method and normalized path when safely available;
* project-relative source location when safely derivable;
* explicit scalar context from a fixed allowlist.

Unexpected reports never read or store raw `Throwable::getMessage()` output. They record only the exception class, the fixed `Unexpected application failure.` summary, and a project-relative source location when it can be derived safely. Warning summaries are caller-owned controlled text and pass through control-character normalization, length limits, and sensitive-value redaction.

Context accepts only the scalar keys `component`, `operation`, `method`, `path`, `status`, and `slot`. Unknown keys and non-scalar values are dropped. Methods, identifiers, statuses, and paths receive key-specific validation; query strings and fragments are removed from paths.

### 8.2 Forbidden log data

Logs must not contain:

* passwords or password hashes;
* database credentials, DSNs, or connection strings;
* CSRF tokens, session IDs, cookies, or authorization headers;
* `.env` contents;
* request bodies or arbitrary query values;
* SQL statements or bound values;
* raw uploaded client filenames or file contents;
* full server arrays;
* stack arguments;
* unredacted absolute paths.

Raw exception messages are omitted rather than heuristically redacted. Context is opt-in and allowlisted; dumping arbitrary arrays or objects is forbidden.

### 8.3 Failure behavior

Logging is best-effort. A missing or unwritable log destination must not:

* expose the original exception;
* replace the intended HTTP response;
* cause recursive logging;
* create a second uncaught failure.

Missing directories are not created automatically. A missing, non-directory, symlinked, or unwritable log location; an invalid event; an encoding, random-source, open, lock, write, or flush failure; or any internal `Throwable` returns `null` from `report()` or `false` from `warning()`. The diagnostics service emits no response output and does not call PHP `error_log()` or any other fallback.

`report()` generates `ERR-` followed by 24 uppercase hexadecimal characters from 12 random bytes. It returns the reference only after the matching line is appended and flushed successfully. `warning()` never creates a reference and returns only a boolean result.

Normal validation, `403`, `404`, and `419` responses are not error-log events unless a separate unexpected failure caused them.

---

## 9. Storage and Filesystem Failure Boundary

Existing capability ownership remains unchanged:

* Installer owns `.env`, mutex, schema-source, and installation-marker operations;
* Site Asset storage owns only fixed Logo and Favicon slots;
* the logging baseline owns only its private diagnostic destination;
* no generic storage service is introduced.

Each owned operation must distinguish successful completion from missing, unreadable, unwritable, unsafe, partially written, rename-failed, and cleanup-failed states.

Failure rules:

* no warning text reaches HTTP output;
* unsafe or symlinked paths fail closed;
* a failed write or rename does not activate incomplete state;
* a failed Site Asset persistence step leaves the previous descriptor active;
* a failed cleanup may leave an unreachable orphan, but must not restore a removed descriptor or require a worker;
* material cleanup failure is eligible for a redacted diagnostic record;
* logging failure never changes the storage operation's public contract.

M2.4 does not make filesystem and database operations globally atomic. Existing operation-specific ordering and transaction boundaries remain authoritative.

---

## 10. Runtime and Deployment Checklist

Release-readiness verification must confirm:

* the web document root points to `public/`, never the repository root;
* `.env`, `storage/`, application code, logs, and database schema are not directly web-accessible;
* production PHP uses `display_errors=Off` and host-level error logging for failures before userland handling;
* `storage/` and the private log destination have the minimum required PHP-user permissions without broad public write access;
* `storage/installed.lock` is valid before normal bootstrap;
* PHP 8.2+ and the existing required extensions remain supported;
* Fileinfo-dependent upload operations fail closed when Fileinfo is unavailable;
* HTTPS deployments enable the Secure session-cookie setting while retaining HttpOnly and the approved SameSite policy;
* session and CSRF behavior survives controlled `419` paths without exposing tokens;
* the configured Admin path remains authoritative;
* local Theme, module, and Site Asset paths preserve containment and symlink rejection;
* no Node process, build daemon, queue, worker, scheduler, external service, or advanced server module is required;
* representative Apache/shared-hosting routing and static Admin asset delivery still work;
* backup, retention, and rotation of private logs remain an explicit operator responsibility in M2.4.

---

## 11. Batch Plan

### Batch 1 — Audit, Architecture, and Contract Lock

Status: Complete. Documentation only.

* record M2.3 v0.11.0 completion;
* make M2.4 the active phase;
* lock scope and non-goals;
* define error, sanitization, Admin, logging, filesystem, runtime, and deployment contracts;
* define batches, acceptance criteria, and risks;
* add no runtime implementation.

### Batch 2 — Minimal Diagnostics Baseline

Status: Complete.

* implemented one request-scoped `Diagnostics` instance per `Application`;
* implemented the fixed local append-locked JSON-line sink at `storage/logs/copot.log`;
* implemented safe error references returned only after successful append;
* omitted raw exception messages and restricted records to controlled summaries, relative source locations, and fixed scalar context;
* implemented non-throwing, non-recursive unavailable-sink behavior without a secondary sink;
* added focused temporary-directory smoke coverage and repository-log isolation guards.

### Batch 3 — Application Error Boundary and Rendering Safety

Status: Complete.

* added a fixed pre-autoload emergency `500` boundary;
* added a post-autoload request/installation/bootstrap boundary using standalone Diagnostics;
* added an `Application::run()` dispatch boundary using request-scoped Diagnostics;
* added standalone sanitized `ServerErrorResponse` output with optional successful-log references;
* kept unexpected failures at `500` unless an explicit positive availability classification selects `503`;
* removed public route-local rendering catches that swallowed unexpected Theme/View failures;
* cleaned View, ViewRenderer, Content, Taxonomy, Example, bootstrap, and dispatch-owned buffers back to exact caller levels;
* preserved trusted internal HTML fragments without a new abstraction;
* added focused `display_errors=1`, leak, partial-output, bootstrap, dispatch, rendering, and repository-log-isolation coverage.

### Batch 4 — Admin In-Shell Errors

Status: Complete.

* added one small `AdminErrorRenderer` recovery boundary and shared Admin error view;
* render eligible authenticated `403`, `404`, `419`, unexpected `500`, and controlled `503` responses inside the existing Admin Shell;
* keep guest, login, base-permission denial, early-bootstrap, and unsafe-recovery paths on standalone sanitized responses;
* reuse the original unexpected-failure diagnostics reference without a second log record;
* preserve existing `422` validation flows and permission, CSRF, status, and configured Admin-path behavior;
* register configured Admin GET/POST catch-all routes only after Core and module route registration;
* added focused status, shell eligibility, fallback, reference, leak, output-buffer, and route-order smoke coverage.

### Batch 5 — Runtime, Security, Storage, and Deployment Hardening

Status: Implemented and focused verification passes.

Batch 5:

* preserves existing auth, permission, CSRF, upload provenance, and escaping contracts while adding focused regression guards;
* makes Secure session cookies configurable through `SESSION_SECURE=true` without a code patch;
* keeps HttpOnly enabled and SameSite at the approved `Lax` baseline;
* passes the existing request-scoped Diagnostics instance into Site Asset storage;
* records material site-asset read and cleanup degradation as controlled warning records without references, paths, or filenames;
* suppresses filesystem warnings from rename, size/MIME probing, stream copy, flush, and cleanup operations so warnings cannot enter responses;
* keeps failed replacement/removal ordering intact and adds no worker, orphan browser, generic storage abstraction, or external sink;
* documents the production/shared-hosting contract for `public/` document root, `display_errors=Off`, private storage/logs, HTTPS Secure cookies, PHP 8.2+, and no daemon/build process.

### Batch 6 — Unified Regression and Release Readiness

Status: Planned.

* add the M2.4 regression gate across M1 and lean M2;
* run automated failure and redaction coverage;
* complete manual public, Admin, runtime, and deployment verification;
* finalize milestone and release-readiness documentation.

Batch 6 must not begin without separate implementation approval.

---

## 12. Acceptance Criteria

M2.4 is complete only when all applicable criteria pass.

### Error containment and rendering

* Unexpected failures from normal bootstrap, route/contribution registration, dispatch, and rendering return a sanitized controlled response.
* Public response bodies contain no exception detail, warning, trace, path, SQL, credential, environment content, client filename, or partial template output.
* Expected `403`, `404`, `419`, and `422` outcomes retain their status and controlled behavior.
* Authenticated Admin failures render in-shell when the boundary prerequisites are available.
* Early and unsafe-to-render failures use a standalone sanitized response.
* `APP_DEBUG` does not expose raw HTTP diagnostics.
* Pre-autoload failures use a fixed generic `500` without Diagnostics or a reference.
* Post-autoload bootstrap and dispatch failures use a reference only when their diagnostic append succeeds.
* Unexpected `PDOException` instances remain `500` unless a caller explicitly and positively classifies an availability failure.
* Boundary and renderer failures restore the exact caller output-buffer level and expose no partial output.

### Logging

* Each unexpected server failure has at most one safe response reference and one corresponding diagnostic record.
* Records contain only the approved minimum fields and allowlisted context.
* Secret, token, credential, request-body, SQL, client-filename, and absolute-path fixtures are absent from logs.
* Log write failure is non-recursive and does not change the sanitized response.
* Expected validation and ordinary authorization outcomes are not logged as server errors.
* Raw `Throwable::getMessage()` output is never read or stored by Diagnostics.
* A reference is returned only after its matching local record is appended successfully.
* Warning records contain no reference.

### Storage and filesystem

* Missing, unreadable, unwritable, symlinked, partial-write, rename, read, and cleanup failures are covered where applicable.
* Failed Site Asset replacement preserves the previously active valid asset.
* Failed removal cleanup leaves no active descriptor and requires no queue or worker.
* Filesystem warnings do not reach response content.

### Runtime, security, and deployment

* Authentication, permission, CSRF, session, upload, and escaping regression coverage passes.
* HTTPS deployments can enable Secure cookies without a code patch.
* Production documentation requires `public/` document root and `display_errors=Off`.
* PHP 8.2+ and supported shared-hosting operation remain intact without a new dependency or service process.
* Manual verification covers public and Admin error states, error references, inaccessible private files, unwritable logging/storage behavior, keyboard flow, responsive Admin shell, and representative shared-hosting delivery.

### Regression and scope

* The M2.4 gate includes all focused M2.4 tests and the complete existing M2.3 regression chain.
* No database/schema change is introduced.
* No dependency, external service, queue, worker, scheduler, global rate limiter, observability platform, or Media Library is introduced.
* Documentation and changelog match the implemented milestone state.

### Batch 1 acceptance

Batch 1 is accepted when:

* M2.3 is consistently recorded as complete and released as v0.11.0;
* M2.4 is consistently recorded as the active phase;
* this contract is linked from architecture, roadmap, governance, README, and changelog status;
* stale wording that blocks M2.4 after the M2.3 release is removed;
* repository consistency searches and `git diff --check` pass;
* only documentation/status files change;
* no runtime implementation exists in the Batch 1 diff.

---

## 13. Risks and Blockers

Known risks are:

* early bootstrap failures cannot safely depend on the full application or Admin renderer;
* a global boundary must not swallow controlled status responses or weaken fail-fast extension behavior inside the application;
* duplicated route-local renderers may require narrow cleanup without a broad View-system rewrite;
* controlled summaries and strict context filtering intentionally trade raw exception detail for stronger secret containment;
* a local log can grow without bound unless operators apply manual retention because automated rotation is outside scope;
* logging and storage may fail at the same time, so diagnostics must remain best-effort;
* Windows development checks do not prove Linux shared-hosting permission, rename, symlink, and `flock()` behavior;
* failures before PHP userland execution remain outside the application boundary;
* changing plain Admin errors to in-shell pages requires regression updates without changing authorization semantics;
* storage cleanup remains non-atomic with database persistence and may leave unreachable orphans by design.

No Batch 3 blocker remains. Windows did not permit symlink creation during Batch 2 focused verification, so the implemented Diagnostics symlink rejection still requires confirmation on a symlink-capable Linux/shared-hosting environment. Failures in `public/index.php` itself, PHP/web-server startup, hard process termination, memory exhaustion, and response transport after bytes are sent remain outside reliable userland recovery. Implementation decisions that exceed this contract require a new scope approval before Batch 4 or later work proceeds.

---

## 14. Explicit Batch 1 Boundary

Batch 1 changes documentation and milestone status only.

It does not modify `app/`, `bootstrap/`, `config/`, `database/`, `modules/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, or `themes/`.

It adds no logger, error handler, route behavior, Admin markup, filesystem operation, configuration key, test, dependency, or database change.
