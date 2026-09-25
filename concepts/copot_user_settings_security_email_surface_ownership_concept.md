# User Settings, Security, and System Email Surface Ownership

Status: CONCEPT / FUTURE CAPABILITY OWNERSHIP / PLANNING ONLY

This Concept is the canonical Git-side semantic source for three distinct
future planning identities. It is not implementation authority, a contract,
or authorization for settings, schema, runtime, permission, security, or mail
changes.

Shared invariant:

> Product surface follows delivered or explicitly adopted capability.
> Historical placeholder UI or inherited information architecture alone is
> not sufficient authority to manufacture a permanent product capability
> surface.

## User Settings / Current User Settings

Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

User Settings is a future Webcore current-user self-service capability for the
currently authenticated user only. Candidate future scope may include the
user's own name/profile identity, own email, own password, and future
current-user security controls when the underlying capability actually exists.
An authenticated-user or account menu is a possible future entry point, but
exact routing and UI remain future contract work.

User Settings is distinct from `Users & Access`, which administers other or
all users under administrative authority. It must not include user
creation/deletion, editing other users, activation/deactivation of other
users, role/permission management, or administrator recovery operations.

Relations:

- relates to, but does not merge with, `Users & Access Refinement`;
- relates to `concepts/copot_per_user_admin_appearance_concept.md`, whose
  appearance-preference ownership remains separate; and
- current-user security and email editing belong here only when the relevant
  delivered capabilities exist.

## Security Capability & Surface Ownership

Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

Security product surfaces must be capability-backed. Historical placeholder UI
or inherited information architecture is not sufficient authority to create a
permanent Security surface.

Current-user security belongs conceptually with User Settings / My Account
when the relevant capability exists. Administrative security management of
other users remains with `Users & Access`. Site/system-wide Security belongs in
Site Settings only if a real Webcore site/system security-policy capability is
delivered or explicitly adopted later.

Security owns system/site-wide policy and enforcement configuration. Identity/
Auth remains the owner of authentication mechanics and identity truth; Security
must not absorb Identity/Auth.

Minimum future Security capability direction includes:

- password policy;
- login throttling and failed-login tracking;
- lockout policy/state;
- session timeout policy;
- session revocation where supported;
- sensitive-action re-authentication; and
- security events / audit evidence.

The future security event/audit direction is a unified, append-oriented model
with timestamp, category, severity, actor, target, action, result, and bounded
sanitized context. Administrators do not edit audit events, and secrets must
not be recorded.

MFA / 2FA, trusted devices, geo/IP anomaly analysis, risk scoring, SSO/OAuth,
and hardware security keys remain outside this baseline until separately
justified.

The current WU4 disposition is preserved: Security is not a current visible
Site Settings top-level area. Site Settings → Security appears only after
sufficient system/site Security capability exists. This Concept does not infer
current delivery or create a full Auth implementation contract.

## System Email Capability & Surface Ownership

Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

System Email product surfaces must be capability-backed. `users.email` is
account identity data, not evidence of outbound/system email delivery
capability.

System Email owns outbound transport and sender identity. Its initial future
direction is one system sender identity, outbound transport/provider
configuration, transport/sender health or bounded delivery state where
technically supported, and optional test delivery only when a real transport
implementation exists.

Message and template ownership remains with the originating capability/domain:
Auth owns authentication/recovery message templates, and another originating
capability owns its own message content/template. System Email provides
delivery infrastructure, not universal message-content ownership.

Current-user email editing belongs with User Settings when implemented;
administrator editing of another user's email remains a `Users & Access`
concern. Site Settings → Email becomes justified only if sufficient outbound /
system-email capability exists.

The current WU4 disposition is preserved: Email is not a current visible Site
Settings top-level area. Email must not be a hard dependency for installation,
normal authentication/login, or baseline zero-optional viability. Multi-sender
identity remains deferred. `users.email` remains user/account identity data
and does not prove system-mail capability. This Concept does not infer current
delivery.

## Identity/Auth relation

Identity/Auth owns authentication mechanics and identity truth. Verified-email
recovery may be added as an Auth capability; its primary direction is a signed,
expiring, one-time verified-email link, with a bounded code flow as an
alternate mechanism. There is no universal/master recovery credential.

Sensitive credential changes require fresh re-authentication and invalidate
affected sessions where applicable. Email provides transport only; Auth owns
Auth-specific recovery content and semantics.

## Shared authorization boundary

These three identities remain separate planning concerns. Their registration
does not sequence work, adopt a capability, promote a product surface, or
authorize implementation. Any future delivery requires a separately bounded
contract and explicit authorization grounded in the underlying delivered or
adopted capability.
