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

The current WU4 disposition is preserved: Security is not a current visible
Site Settings top-level area. This Concept does not infer MFA, recovery,
password-reset, session management, or any other new security capability.

## System Email Capability & Surface Ownership

Status: FUTURE / PLANNING ONLY / NOT IMPLEMENTATION-AUTHORIZED

System Email product surfaces must be capability-backed. `users.email` is
account identity data, not evidence of outbound/system email delivery
capability.

Current-user email editing belongs with User Settings when implemented;
administrator editing of another user's email remains a `Users & Access`
concern. Site Settings → Email becomes justified only if an actual system-level
email capability exists, such as sender identity, transport/provider, delivery
state/policy, or equivalent delivered capability.

The current WU4 disposition is preserved: Email is not a current visible Site
Settings top-level area. This Concept does not create or imply SMTP, provider
transport, queueing, notification delivery, test-email, recovery-email, or
other mail infrastructure.

## Shared authorization boundary

These three identities remain separate planning concerns. Their registration
does not sequence work, adopt a capability, promote a product surface, or
authorize implementation. Any future delivery requires a separately bounded
contract and explicit authorization grounded in the underlying delivered or
adopted capability.
