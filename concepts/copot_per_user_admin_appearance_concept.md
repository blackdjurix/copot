# Per-user Admin Appearance

Status: CONCEPT / FUTURE ADMIN PERSONALIZATION / PLANNING ONLY

This Concept is the current Git-side semantic source for the future logical
identity `Per-user Admin Appearance`. It describes presentation preferences
owned by an individual authenticated user. It is not implementation authority,
a contract, or authorization for settings, schema, or runtime changes.

## Relationship to Site Color Scheme

`Site Color Scheme` remains the site-level appearance authority. Public and
Built-in View appearance remains governed by that site-level source, not by a
user's Admin preference. The related Concept is:

`concepts/copot_site_color_scheme_concept.md`

Default inheritance is:

1. Site Color Scheme supplies the default Admin appearance baseline where
   applicable.
2. Per-user Admin Appearance may override only supported Admin-facing
   presentation preferences.
3. An absent, invalid, unsupported, or stale user preference falls back safely
   to the site/default appearance.

A user override must never mutate site branding globally or affect other users
or public consumers.

## Future preference family

Future preferences may include, individually or in a bounded supported set:

- Admin color-scheme or accent override;
- light, dark, or system/inherit appearance;
- interface density;
- Dashboard layout;
- Dashboard widget placement;
- sidebar or navigation presentation preferences; and
- other bounded Admin personalization proven useful later.

These possibilities do not require one combined implementation. Exact setting
keys, controls, database columns, storage schema, and synchronization behavior
remain unresolved future contract work.

## Dashboard and Widget Layout relationship

Per-user Admin Appearance relates to the existing Future Widget Layout and
Dashboard personalization lineage. Widget layout remains its own architecture
concern. This Concept may become the user-level preference family that
consumes or stores future Dashboard layout choices, but it does not absorb the
Widget Layout architecture or authorize Dashboard implementation. Dashboard
layout remains future and separately prepared.

## Color and appearance boundaries

A future per-user color preference may specialize Admin presentation only. It
must not change public or site appearance, replace Site Color Scheme, or
override semantic operational colors. Warning, danger, success, information,
validation, destructive-action, focus, and other accessibility-critical
meaning remain independently governed.

Future Admin modes may include light, dark, and system/inherit. This Concept
does not define browser or device detection and does not require Site Color
Scheme itself to become light/dark aware. Token and contrast handling require
later contract preparation.

Density or layout preferences are appropriate only where shared Admin
primitives can support them consistently. Per-page ad hoc layout settings,
global flat/radius policy, and reopening MR.2 shared primitive design are
excluded.

## Ownership, isolation, and security

The conceptual owner is the user-specific Admin preference domain. Exact
persistence ownership remains open for future preparation, which must compare
user profile settings, a dedicated preference store, and other accepted
Webcore mechanisms without selecting one here.

Preferences must be scoped to the correct user and installation and must not
leak across users or installations. Future implementation must respect
authenticated identity, installation isolation, bounded preference keys, safe
defaults, and authorization where an administrative capability could be
affected. Pure presentation preferences must never grant or alter permissions.

## Accessibility and fallback

User customization remains subordinate to accessibility. Future implementation
must preserve readable contrast, visible focus states, semantic operational
states, usable disabled states, and safe fallbacks. A preference should be
rejected, normalized, or safely ignored when it would break accepted
accessibility constraints. Exact validation algorithms remain future contract
work.

## Portability and reset

Future preparation should consider reset to inherited/default appearance,
behavior after Site Color Scheme changes, behavior when a preference becomes
unsupported, and whether preferences are portable across devices for the same
installation and user. This Concept does not decide synchronization or
cloud-account architecture.

## Non-goals and authorization boundary

This Concept is not a full Theme system, public-site user theming, a Site Color
Scheme replacement, Dashboard or Widget Layout implementation, permission
customization, semantic status-color customization, a browser extension
preference system, or a cross-site account profile platform.

It does not authorize implementation, settings UI, schema or persistence
changes, Admin CSS changes, Dashboard changes, light/dark token work,
permission changes, production action, or release/publication. Any future
implementation requires a separate bounded preparation and authoritative
contract.
