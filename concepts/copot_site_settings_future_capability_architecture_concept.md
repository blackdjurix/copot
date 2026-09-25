# Site Settings Future Capability Architecture

Status: CONCEPT / FUTURE PRODUCT PROJECTION / PLANNING ONLY

This Concept records future Site Settings product projection and capability
ownership direction. It does not reopen or rewrite WU4 delivery history and is
not implementation authority or authorization for UI, source, schema,
database, runtime, migration, lifecycle, permission, release, or publication
changes.

## Current delivered truth

The authoritative current Site Settings baseline remains
`docs/54_webcore_site_settings_appearance_consolidation_contract.md`.
Current Site Settings has exactly four visible top-level areas:

1. Site Identity
2. System
3. Modules
4. System Health

Security and Email are absent from the current visible surface. Historical
six-area planning and fragment wording, including the wording retained in
`docs/50_webcore_site_settings_information_architecture_clarification.md` and
`docs/55_webcore_site_settings_system_operational_projection_contract.md`, is
historical/stale projection lineage and does not prove current delivery.

## Governing invariant

> Product projection follows delivered capability. Projection does not
> transfer subsystem authority.

Future Site Settings is capability-gated. It does not require all future
concern families to exist simultaneously and does not make a not-yet-delivered
capability visible merely because a product area is conceptually useful.

## Future capability-gated concern families

Future Site Settings may project the following concern families only when the
underlying capability is sufficiently delivered or explicitly adopted:

- Site / Site Identity;
- System;
- Modules;
- Redirects;
- System Health;
- Security;
- Email; and
- Database.

These are future projection families, not a declaration that eight current
visible top-level areas exist.

### Site / Site Identity

Existing Site Identity ownership and current delivered groups remain preserved.
Future grouping or product naming may evolve, but persistence and authority for
Site Asset, Media, Homepage Content, Localization, and Appearance must not
move into a competing subsystem. No additional site capabilities are invented
by this Concept.

### System

The accepted Site Settings → System projection remains over existing Webcore
lifecycle authority. No second package, lifecycle, or recovery engine is
created.

### Modules

The accepted Modules projection remains over existing Module discovery,
permission, package, and lifecycle authority. Site Settings does not absorb
Module ownership.

### Redirects

Redirects remain Webcore-native. A dedicated Site Settings product projection
may be considered when the Redirects operator workflow is materialized, but it
must not recreate Redirect Manager package ownership. This Concept invents no
CRUD semantics, migration behavior, implementation detail, or permission
change not supported by authoritative evidence.

### System Health

System Health remains a dedicated derived, read-only operational projection.
It is not generic settings persistence and does not become executable
remediation authority.

### Security and Email

Security and Email ownership and capability definitions are recorded in the
canonical Concept:
`concepts/copot_user_settings_security_email_surface_ownership_concept.md`.
This Concept references that ownership boundary rather than duplicating it.
Their Site Settings projections remain capability-gated and absent from the
current four-area surface.

### Database

Database is a future operator projection over existing database, schema,
lifecycle, and compatibility authorities. When authoritative evidence exists,
it may present current database/schema compatibility, target-release database
requirements, adoption readiness, satisfied and unmet requirements, supported
transition/path evidence, blockers, sanitized compatibility reasons, and the
next valid operator action.

The Database projection does not own migration execution, schema authority,
package lifecycle, repair authority, arbitrary SQL, a generic database version
engine, or a parallel lifecycle engine.

## Target-relative Database compatibility

Database compatibility is target-relative. The target runtime/release declares
the requirements it needs, and the actual database is inspected against those
requirements.

- satisfied requirements remain untouched;
- compatible extra state is preserved;
- missing target-required state may be satisfied only through a known, safe,
  authorized, ownership-bounded, deterministic path; and
- compatibility is re-evaluated after any authorized requirement-filling
  change.

If compatibility or an authorized path cannot be proven, the applicable
case-specific disposition fails closed. An exact target-schema snapshot is not
required merely for equivalence. No global `DB_VERSION` is introduced. Existing
lifecycle and migration machinery remains mutation/execution authority;
migration is one possible requirement-resolution mechanism, not the definition
of the planning shorthand “Fill-the-Hole”. Conflict handling remains evidence-
and case-specific; no generalized conflict SOP is created.

## Remaining contract-model reconciliation

Exactly one material reconciliation item remains unresolved: legacy
exact-match/current Adopt semantics must be reconciled with requirement-driven
Fill-the-Hole compatibility.

The conflict is between:

- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`, where
  exact-match adoption rejects missing, extra, or materially altered schema
  and the separate mutating legacy reconciliation action handles non-exact
  state; and
- `docs/37_database_ownership_lifecycle_management_foundation_contract.md`,
  whose current Adopt wording prohibits schema provisioning, migration, and
  repair during Adopt while preserving exact compatible-installation gates.

The newer target-relative direction permits compatible extras and permits
known unmet target requirements to be safely resolved through existing
authorized machinery. This task does not resolve that conflict or modify
either closed contract.

The candidate distinction to reconcile is:

- Adoption = broader compatibility-establishment workflow; and
- Adopt = terminal installer/finalization intent after compatibility is
  established.

This remains a bounded planning candidate, not a new lifecycle operation,
installer intent, engine, status family, or implementation authorization.

## Historical release-support provenance

The historical `Database Historical Release-Support Window` planning identity
is retained as provenance, but is no longer treated as a separate unresolved
universal policy question. Supported historical reach is derived per target
release from target requirements and available proven transition paths. A
bridge release/version is required only when actual release evidence proves a
direct transition unsafe or insufficient. Unsupported source states fail
closed.

Concrete release-support boundaries remain future release-specific
Fill-the-Hole analysis and are not defined by this Concept.

## Authorization boundary

This Concept preserves current delivered Site Settings truth and records
future planning direction only. It does not sequence implementation, promote a
capability, authorize a product surface, create ownership, reopen WU4, alter
closed contracts, or authorize database/schema/runtime/migration/lifecycle,
release, tag, or publication work.
