# M3.11 Form Manager Preparation Contract

## Purpose and status

This document locks the M3.11 Form Manager product boundary and records the
accepted WU1 and WU2 implementation state. It does not authorize WU3 or later
work, runtime deployment, release, tag, merge, or publication work.

```text
M3.10 Redirect Manager:
COMPLETE AND CLOSED

M3.11 Form Manager preparation:
LOCKED

M3.11 implementation:
IN PROGRESS

M3.11 feature branch:
ACTIVE FOR IMPLEMENTATION

M3.11 Work Unit 1:
COMPLETE

M3.11 Work Unit 2:
COMPLETE

M3.11 Work Unit 3:
NOT STARTED

Full M3.11:
NOT COMPLETE
```

Preparation remains locked. WU1 adds only module-local persistence, domain
services, schema/upgrade convergence, permissions, package inclusion, and
focused installation evidence. WU2 adds only the protected Admin definition
workspace; it adds no public rendering, submission workspace, or rate-limit
persistence. WU3 remains not started.

Release, tag, and publication remain not started and separately authorized.

## Product definition

M3.11 provides a first-party module for creating reusable public forms,
accepting validated submissions, persisting those submissions, and managing
them through a protected Admin workspace.

```text
public visitor submits form
-> server validates submission
-> submission is stored persistently
-> submission appears in Form Manager Admin
-> authorized Admin reviews or permanently deletes it
```

The Admin submission workspace is the primary source of truth. Submissions are
not delivered as email, PDF, chat, or external messages in this baseline.

## Ownership and boundaries

Form Manager owns form definitions, ordered field definitions, its fixed field
vocabulary, form lifecycle, public rendering and submission handling,
server-side validation, persistent submissions and values, review state, Admin
form and submission workspaces, permissions, repositories, schema and upgrade
artifacts, package inclusion, and focused security/lifecycle/installation
tests.

It reuses existing request parsing, CSRF, routing, database transactions,
authentication and permission resolution, Admin Shell/navigation, escaping,
module lifecycle, and package construction. It must not duplicate platform
infrastructure inside the module.

The baseline may integrate with Users/Roles through existing permissions,
Admin Shell rendering/navigation, Module Manager/installer lifecycle, and only
the smallest explicit Theme public-rendering contract if needed. It must not
couple to Content, Media, Navigation, Taxonomy, Redirect Manager, Dashboard,
or Settings. Cross-module access must use an explicit public contract, never a
private table, path, or implementation detail.

## Form and field contract

The form lifecycle is exactly `draft`, `published`, and `disabled`. Draft and
disabled forms are retained but not publicly submittable; only published forms
may be rendered and submitted. Version history, scheduling, archiving,
cloning, and workflow states are excluded.

A form definition may be permanently deleted only when it has no retained
submissions; a form with retained submissions must be disabled instead. This
preserves the Admin submission workspace as the source of truth without
orphaning persisted records.

The supported vocabulary is bounded and explicitly allowlisted. The initial
allowlist is `text`, `email`, `textarea`, `select`, and `checkbox`. Definitions
may contain only label, field key, type, order, required state, bounded
type-appropriate options, and bounded type-appropriate validation constraints.
The exact implementation limits remain module-local constants or bounded
configuration.

Arbitrary HTML, executable rules, arbitrary schemas, file uploads, passwords,
payment data, hidden executable fields, conditional logic, and multi-step
forms are not supported. Form Manager must not collect passwords,
authentication secrets, payment-card data, or similarly high-risk secrets.

## Submission and retention contract

Submission lifecycle is exactly `new` and `reviewed`. Authorized Admin users
may view a submission, mark it reviewed, and permanently delete it. Returning a
reviewed submission to `new` is permitted only if implementation preserves
existing lifecycle conventions and focused evidence justifies it.

Assignment, ownership queues, tags, comments, replies, escalation, messaging,
or automation are excluded. Internal user-to-user messaging, threads,
inboxes/sent folders, read receipts, and user notifications are not Form
Manager behavior and must not be modeled as submissions.

Submissions remain stored until an authorized Admin permanently deletes them.
Deletion is hard deletion with no restore or soft delete; the Admin UI must
make that permanence clear. There is no automatic retention period, purge,
scheduler, archive, or export baseline. Future automatic retention requires an
appropriate platform lifecycle or scheduler decision.

## Public placement and anti-abuse

M3.11 supplies a narrow public rendering and submission contract. It does not
assume Content shortcodes or blocks, a page builder, Theme widgets, Navigation
target types, cross-site embeds, or arbitrary template scripting. Any Theme
placement is limited to the smallest explicit contract supported by current
Theme architecture.

Public submission security requires session-backed CSRF where compatible,
honeypot validation, minimum submission-duration validation, bounded payload
size, field count and field lengths, and bounded per-form/per-client rate
limiting. The rate-limit direction is database-backed bounded attempt records:
anonymous clients may not retain stable sessions and session-only rate limits
are easy to bypass. Exact thresholds are implementation-time constants or
bounded configuration unless an established repository convention is proven.

Public success/failure behavior must be generic where appropriate to reduce
probing. No raw persistence, validation, SQL, stack-trace, filesystem, or
internal diagnostic detail may be exposed. External CAPTCHA, spam providers,
IP reputation, device fingerprinting, browser tracking, queues, and provider
integration are excluded.

## Permissions and Admin workspace

| Permission | Baseline intent |
|---|---|
| `forms.view` | Access the Form Manager definition workspace. |
| `forms.manage` | Create, edit, publish, disable, and delete form definitions. |
| `forms.submissions.view` | List, inspect, and mark submissions reviewed. |
| `forms.submissions.delete` | Permanently delete submissions. |

Every Admin Form Manager route also requires `admin.access`. Protected Admin
mutations must authorize before validating CSRF. The module may add one
permission-aware Form Manager navigation entry through current request-scoped
navigation conventions and must preserve configured Admin paths.

The Admin surface includes form-definition list/create/edit/lifecycle/delete
workflows and submission list/detail/review/delete workflows. It must preserve
escaped redisplay, controlled errors, responsive behavior, keyboard/focus
accessibility, and the shared Admin Shell. The M3 Admin Shell
design-adjustment checkpoint applies to these Admin surfaces; public rendering
is outside that presentation checkpoint. No Dashboard widget is included.

## Persistence, provisioning, and packaging

Form Manager owns persistence for at least `forms`, `form_fields`,
`form_submissions`, and `form_submission_values`, plus bounded rate-limit
attempt records when required for the locked anti-abuse contract. Exact column
design is an implementation concern. Invariants include ordered fields scoped
to one form, allowed form/field/submission states, field/value ownership,
transaction-safe form-and-field writes, and transaction-safe submission
persistence.

Implementation is expected to add the canonical fresh-install schema, a
first-party module manifest, permission registration and Administrator grants,
an idempotent existing-install upgrade artifact, package-manifest inclusion,
and a baseline module activation-policy review. It must prove genuine
pre-M3.11 upgrade convergence, clean installation, package inclusion, and
normal Module Manager installation/enablement. No generic migration runner is
introduced by M3.11.

## Explicit deferred capability directions

The following are plausible later Form Manager extensions but are not M3.11
baseline scope: email notifications, file uploads, conditional fields,
analytics, submission export, Dashboard summary widget, webhooks, public APIs,
automatic retention, form templates, and multi-step forms. These directions
are neither scheduled Work Units nor adopted Deferred Items.

Dashboard submission summary is `DEFERRED`: a future integration may expose
only a count or summary of new submissions linking to Form Manager. Email
notification is `DEFERRED`. The existing global floating-notification direction
remains `KEEP DEFERRED` and is not adopted as Form Manager delivery or shared
notification infrastructure.

Generic workflow engines, CRM, marketing automation, email campaign
management, payment processing, visual page building, arbitrary scripting,
third-party integration frameworks, a form marketplace, chatrooms, internal
messaging, and enterprise records management are outside the Form Manager
product boundary. They require separate ownership and explicit authorization.

## Deferred Item review

| Item | Current status | M3.11 disposition | Evidence |
|---|---|---|---|
| `DI-M3.8-WU6-01` | Deferred / Unscheduled | `NOT APPLICABLE`; uploads remain excluded. | Media presentation follow-on only. |
| `DI-M3.9-01` | Deferred / Unscheduled | `NOT APPLICABLE`; no Dashboard widget is baseline. | User-customizable Dashboard layout. |
| `DI-M3.9-02` | Deferred / Unscheduled | `KEEP DEFERRED`; only relevant if separately approved Admin token work occurs. | Admin Media CSS token normalization. |
| `DI-M3.9-03` | Deferred / Unscheduled | `NOT APPLICABLE`; Content Media-picker regression debt is unrelated. | Admin UI regression modernization. |

No Deferred Item is adopted. No new Deferred Item ID is created.

## Exact Work Units

Exactly four domain Work Units are locked. The planning envelope does not turn
the Admin Shell design-adjustment checkpoint into a fifth domain Work Unit.

### WU1 — Persistence, Provisioning, and Domain Services

Goal: establish module persistence, repositories/services, schema and upgrade
convergence, permissions, form/submission lifecycle invariants, and package and
Module Manager lifecycle integration.

Validation: domain, validation, transaction, clean-schema, genuine pre-M3.11
upgrade, repeated-upgrade idempotence, permission-grant, and package-inclusion
evidence.

Excludes: Admin presentation, public rendering, notifications, uploads,
conditional logic, and Dashboard integration.

WU1 is complete. It provides `modules/form-manager` with fixed-vocabulary form
and submission records, positive identifiers, PDO repositories, bounded
validators, caller-safe transaction/savepoint services, canonical schema,
idempotent `m3_11_form_manager.sql` provisioning, permissions and
Administrator grants, baseline activation, package inclusion, and focused
domain/provisioning evidence. It adds no routes, UI, public rendering,
rate-limit persistence, or cross-module integration.

### WU2 — Admin Form Management Workspace

Goal: provide protected form-definition listing, creation, editing, lifecycle
management, and deletion using current Admin Shell patterns.

Validation: permission matrix, authorization-before-CSRF, field-definition
validation, stale-state handling, escaped redisplay, configured-path behavior,
and responsive/accessibility review.

Excludes: drag-and-drop builders, templates, analytics, and Content embedding.

### WU3 — Admin Submission Workspace

Goal: provide protected submission listing, detail, review-state management,
and permanent deletion.

Validation: view-versus-delete permissions, authorization-before-CSRF, escaped
values, stale/deleted behavior, permanent-deletion confirmation, and
responsive/accessibility review.

Excludes: replies, messaging, assignment, export, notification delivery, and a
Dashboard widget.

### WU4 — Public Rendering, Submission Security, Regression, and Closure

Goal: render published forms, validate and persist public submissions, enforce
anti-abuse boundaries, and complete regression, package, installation, upgrade,
and closure evidence.

Validation: draft/published/disabled behavior; CSRF, honeypot, timing,
rate-limit, payload/field bounds, invalid/malicious input, escaping, transaction
failure, duplicate-submission, package, clean-install, existing-install
upgrade, cross-module regression, Admin Shell checkpoint, and documentation
consistency evidence.

Excludes: email notifications, uploads, conditional logic, analytics, external
CAPTCHA, and Content, Media, Navigation, Dashboard, or Redirect integration,
as well as release, tag, and publication.

## Implementation entry and acceptance direction

Implementation may begin only with explicit authorization on a verified branch
from the accepted baseline. WU1 and WU2 are complete; WU3 is the next target,
pending separate authorization. All implementation must preserve module
ownership, dependency direction, least-privilege permissions, controlled
failures, and the package/clean-install
contract. Preparation itself makes no runtime, schema, package, or dependency
change.
