# Site Color Scheme

Status: FUTURE EXTENSION / APPEARANCE RECONCILIATION / PLANNING ONLY

This Concept is the current Git-side semantic source for the future logical
identity `Site Color Scheme`. It extends and reconciles the existing Site
Identity and appearance architecture lineage. It is not a second competing
appearance authority, an implementation contract, or implementation
authorization.

## Site-level appearance authority

Site Color Scheme is a simplified site-level product-facing projection and
resolution layer for bounded brand-facing appearance. It does not supersede,
erase, or destructively replace the richer Webcore Branding / Brand Colors
lineage. The conservative relationship is:

```text
Brand Colors / Webcore Branding data
-> bounded resolved Site Color Scheme
-> Built-in Public View / Theme / permitted bounded Admin consumers
```

The initial upstream source may remain the existing Core Branding settings:
`branding.main`, `branding.accent`, `branding.neutral-dark`, and
`branding.neutral-light`. Site Color Scheme is not a second independent
storage or ownership authority, and this Concept does not imply new
`site_color_scheme.*` keys or destructive migration.

Where supported by the accepted appearance contract, the resolved projection
may govern primary or accent brand color, supporting neutral and color
relationships, public/Built-in View appearance, and bounded Admin branding,
navigation, or accent inheritance.

Theme and other site-facing consumers read the accepted appearance contract
and do not independently invent competing site color authorities. Theme
lifecycle and implementation ownership remain outside this Concept.

Webcore retains ownership of site-level configuration and identity where that
ownership is already accepted, including the upstream Branding data. Exact
future projection mechanics, configuration presentation, and consumer
contracts remain future contract work; this Concept does not authorize
implementation.

## Semantic operational color boundary

Site appearance colors remain distinct from semantic operational colors.
Branding must not override or make the meaning of operational states
ambiguous. Semantic colors remain independently governed, including:

- warning;
- danger;
- success;
- information;
- validation;
- destructive-action states; and
- other accessibility-critical semantic states.

This Concept does not create a second global Admin color authority and does
not grant arbitrary site branding control over health, status, validation,
error, or destructive-action meaning.

## Admin relationship

Admin may inherit bounded Site Color Scheme presentation such as navigation
accent, selected-state accent, brand-highlight treatment, or non-semantic
decorative emphasis. Operational, validation, focus, and action semantics
remain governed by their own presentation and accessibility requirements.

Future `Per-user Admin Appearance` may override or specialize Admin
presentation separately. That is a distinct future identity and is not defined
or materialized by this Concept.

## Public and Built-in View relationship

Public/Built-in View may consume Site Color Scheme as its site-level appearance
source where supported. Consumers remain subordinate to readability,
accessibility, and the accepted appearance contract; they must not create
parallel site-level color configuration.

## Accessibility and fallback

Site Color Scheme remains subordinate to readable and accessible rendering.
Future implementation contracts should account for sufficient contrast,
foreground/background compatibility, visible focus and selection states,
readable disabled states, and safe fallback when configured colors are invalid
or incomplete.

Future implementation should provide a stable default or fallback Site Color
Scheme. Invalid, incomplete, or unavailable configuration must not break Admin
or public rendering. Existing Webcore Branding defaults, validation, resolved
palette behavior, and fallback remain the relevant accepted lineage; exact
future projection mechanics remain contract work. This Concept does not
authorize a schema migration, new provider framework, or generic color engine.

## Ownership boundaries and exclusions

Site Color Scheme does not own:

- semantic health or status colors;
- validation or error semantics;
- destructive-action semantics;
- per-user Admin Appearance;
- Dashboard layout;
- Theme lifecycle;
- Module-specific presentation; or
- System Manager lifecycle authority.

Theme may consume the resolved Site Color Scheme and may provide
active-Theme-scoped appearance overrides where separately supported. Such
overrides must remain within Theme scope and must not write back to or take
ownership of Webcore site-level Branding data. Theme-specific advanced
appearance, advanced Brand Kit, multi-brand, and white-label concerns remain
outside this Concept.

It does not reopen MR.2 shared Admin primitive scope, define a global
flat/radius policy, change Admin CSS, alter Theme behavior, or authorize
System Manager settings work. It does not authorize implementation, schema or
configuration changes, production action, or release/publication.

## Future disposition

This is a future planning identity and an extension/reconciliation of existing
Site Identity and appearance lineage, not an unrelated new architecture. Any
implementation must preserve the separation between site branding and
semantic operational meaning and must establish its authority through a future
contract.
