# Post-M3 — Webcore & Extension Architecture Reconciliation Contract

## Status and authority

```text
Post-M3 track: Webcore & Extension Architecture Reconciliation
Contract status: PROMOTED / CONTRACT LOCKED
WU1 implementation: COMPLETE AND CLOSED
WU1 focused validation: PASS
WU2 technical validation: PASS
WU2 human visual/product acceptance: PASS
WU2 implementation: COMPLETE AND CLOSED
WU3: NEXT / NOT STARTED
WU4-WU7: NOT STARTED
MR.2 WU1: COMPLETE AND CLOSED
MR.2 WU2: COMPLETE AND CLOSED
MR.2 WU3 onward: ON HOLD until this workstream closes
```

This contract promotes the Post-M3 Webcore and Extension Architecture
Reconciliation into the authoritative repository documentation baseline. It is
a documentation and architecture-ownership contract only. It does not start
WU1, authorize source/schema/runtime mutation, authorize package deletion, or
adopt any Deferred Item.

Where an earlier milestone document records the architecture that was
implemented at that milestone, that record remains historical. This contract
controls the current target architecture and supersedes conflicting
forward-looking assumptions in those records.

WU1 establishes the minimum explicit target-ownership metadata needed by later
extraction WUs. It does not change the currently enforced owner, physical table
resolution, migration authority, installer materialization, or package layout.

## Baseline architecture

Webcore owns complete minimum viability. A valid COPOT installation remains
usable with zero optional Modules and zero Themes. Platform capabilities provide
reusable internal machinery above the minimum runtime; they do not acquire
domain ownership merely because more than one consumer uses them.

Capability composition may use explicit contracts, but ownership remains
singular. A capability has one authoritative owner for its state, lifecycle,
security boundary, persistence, and public contract. A baseline capability must
not be intentionally crippled merely to justify an advanced Module.

The target dependency direction is:

```text
Webcore minimum viability
-> reusable Platform machinery
-> optional Bundled or external Modules
-> optional Themes
```

No generic global provider framework is introduced without a concrete repository
requirement and an explicitly bounded contract.

## Extension vocabulary and relationship semantics

- **Module** — an executable capability extension.
- **Bundled Module** — a Module distributed with COPOT. Bundled does not imply
  mandatory, installed, or enabled.
- **Theme** — an optional frontend presentation extension.
- **Contribution** — bounded participation through an explicit extension
  contract.

Supported relationship semantics are:

```text
EXTENDS
PROVIDES
CONSUMES
ENHANCES
CONTRIBUTES
INTEROPERATES
AGGREGATES / COORDINATES
```

These terms describe a declared relationship in a concrete contract. They do
not create a universal registry, provider, plugin, or dependency framework.

## Built-in Public View and Theme boundary

Webcore does not own a Theme. Webcore owns an always-available Built-in Public
View. The presentation resolver must produce this behavior:

```text
no active Theme        -> Built-in Public View
compatible active Theme -> Theme presentation
```

Theme remains optional. No Theme is required for a valid public site.

Site Identity includes Logo and Favicon. Baseline appearance may include Color
Scheme and Font. These are bounded Webcore baseline values; a Theme may consume
them through an explicit presentation contract without taking ownership of
Webcore identity state.

### WU2 implementation result

WU2 establishes the Webcore-owned Built-in Public View as the runtime fallback
for public rendering. The resolver now treats an absent or invalid active Theme
as an optional-extension condition rather than a public-rendering prerequisite:

```text
no active or compatible Theme -> Webcore Built-in Public View
compatible active Theme       -> Theme presentation
Theme deactivated or removed  -> Webcore Built-in Public View
```

The Built-in Public View is a Webcore layout and is not a packaged or
implicitly active Theme. It provides a neutral responsive shell, optional Site
Identity Logo/Favicon, Site Name fallback, bounded accent-color variants,
existing safe navigation context when available, plain-text content rendering,
and a runtime-derived `© {current_year} {site_name}` footer. It does not imply
rich-text, Markdown, block composition, or Theme ownership.

WU2 consumes the currently available transitional Content and navigation view
contracts without moving their ownership or implementing WU3-WU5 extraction.
Theme Manager and Theme packages remain optional extension machinery. Installer
reconciliation remains WU6 scope. Technical validation and human
visual/product acceptance are PASS. WU2 is COMPLETE AND CLOSED. The normal
application entrypoint's pre-existing masked HTTP 500 was reproduced at the
WU2 parent and is not a WU2 regression or a reason to reopen this closure.

## Webcore Content

Webcore owns the baseline Content capability and state, including:

- Page and Article baseline types;
- persistence;
- slug/public identity;
- draft, published, and archived lifecycle;
- basic authorship;
- basic Admin list, create, edit, publish, and archive operations;
- basic Media reference;
- the public Content delivery contract; and
- normalized render data.

Presentation remains separate from Content ownership. The target public flow is:

```text
Request
-> Routing
-> Content delivery
-> normalized render data
-> presentation resolver
-> Built-in Public View or Theme
```

Content Manager remains a Bundled Module that **EXTENDS** Webcore Content.
Rich-text/editor implementation is not part of this promotion or WU3
extraction scope.

## Webcore Media

Webcore owns baseline Media capability, including upload, validation, safe
storage, stable Media identity, basic metadata, controlled delivery, simple
selection, usage/reference awareness, and safe deletion.

Media Manager remains a Bundled Module that **EXTENDS** Webcore Media with
advanced library and processing capability. Media processing must remain
consumer-profile-driven rather than becoming a Content-specific baseline.

## Webcore Navigation and URL concerns

Webcore owns one usable primary navigation capability with ordered items,
bounded hierarchy, custom URL targets, Webcore Content targets, and Built-in
Public View consumption.

Navigation Manager remains a Bundled Module that **EXTENDS** Webcore Navigation
with advanced multi-menu, location, and provider capability.

The following concerns remain distinct:

- **Routing** — internal request dispatch.
- **Slug/public URL identity** — owned by the applicable domain.
- **Redirects** — explicit source-URL to target-URL redirection.

“Addressing” is not a required umbrella capability name. Redirect Manager is
planned for retirement and Redirects become Webcore-native.

## Taxonomy boundary

Webcore has no default taxonomy system. Taxonomy semantics and state are
Module-owned by default. Shared taxonomy identity is exceptional and requires a
concrete cross-domain semantic need. Reusable taxonomy helpers may exist only
where repository evidence justifies reusable machinery.

Taxonomy Manager is a high-confidence retirement candidate. This is a planned
architecture disposition, not permission to delete its package, schema, or
runtime in this promotion.

## Existing Manager disposition

Planned disposition under this reconciliation:

| Manager | Disposition | Reconciliation meaning |
|---|---|---|
| Module Manager | Retire | Module lifecycle remains a Webcore/System Manager responsibility. |
| Settings Manager | Retire | Settings Platform remains reusable internal machinery. |
| Redirect Manager | Retire | Redirects become Webcore-native. |
| Taxonomy Manager | High-confidence retirement candidate | Taxonomy remains Module-owned by default. |
| Content Manager | Retain | Bundled Module; **EXTENDS** Webcore Content. |
| Media Manager | Retain | Bundled Module; **EXTENDS** Webcore Media. |
| Navigation Manager | Retain | Bundled Module; **EXTENDS** Webcore Navigation. |
| Theme Manager | Retain | Manages optional Theme presentation. |
| Users & Access | Retain | Existing access-management capability. |
| Form Manager | Retain | Existing form-management capability. |

System Manager is retained as the product-facing parent surface. “Site” remains
a subsection. Module lifecycle remains a Webcore/System Manager
responsibility. Settings Platform remains reusable internal machinery and is
not thereby promoted to a user-facing Settings Manager ownership boundary.

## Installation and Bundled Module policy

Fresh installation must require zero mandatory optional Modules and zero
mandatory Themes. The installer design may later allow a Bundled Module to be:

- not installed;
- installed but disabled; or
- installed and enabled.

Theme selection or installation may also be offered when available. No Theme
may be required for a valid public site because Built-in Public View is always
available.

This policy is a later reconciliation target. It does not authorize installer
implementation in this promotion.

## Development migration boundary

The current project has no live production site requiring preservation of the
historical development architecture. Later Architecture Reconciliation
implementation may use explicitly scoped destructive cleanup of obsolete
development schema or data when technically cleaner.

No destructive cleanup is implemented by this promotion. The development
allowance must not be documented as normal Module uninstall or delete behavior.
`C:\xampp\htdocs\copot.test` remains untouched.

## Work unit topology

The workstream is locked to this seven-WU topology:

1. **WU1 — Architecture Contract & Ownership Reconciliation**
2. **WU2 — Built-in Public View & Theme Decoupling**
3. **WU3 — Webcore Content Extraction**
4. **WU4 — Webcore Media Extraction**
5. **WU5 — Webcore Navigation & Redirects Extraction**
6. **WU6 — Bundled Module & Installer Reconciliation**
7. **WU7 — Cross-Lifecycle Acceptance & Architecture Closure**

Current workstream execution state:

- WU1 — COMPLETE AND CLOSED.
- WU2 — COMPLETE AND CLOSED.
- WU3 — NEXT / NOT STARTED.
- WU4-WU7 — NOT STARTED.

MR.2 WU1 and WU2 are already complete and closed. MR.2 WU3 onward remains on
hold until this workstream closes. This workstream does not reopen MR.2 and
does not authorize implementation of any WU beyond the separately authorized
execution slice.

## WU1 ownership result

The current ownership catalog remains the enforcement authority for the
delivered runtime. WU1 adds target ownership and transition-work-unit metadata;
it does not pretend that WU3, WU4, or WU5 extraction has already occurred.

| Table family | Current enforced owner | Target owner | Transition |
|---|---|---|---|
| `content` | Content Module | Webcore | WU3 |
| `media` | Media Module | Webcore | WU4 |
| `media_usages` | Media Module | Webcore | WU4 |
| `media_variants` | Media Module | Media Manager / Media Module | No ownership move; advanced state remains Module-owned |
| `navigation_menus` | Navigation Module | Webcore | WU5 |
| `navigation_items` | Navigation Module | Webcore | WU5 |
| `navigation_menu_assignments` | Navigation Module | Navigation Manager / Navigation Module | WU5; advanced location-specific state remains Module-owned |
| `redirects` | Redirects Module | Webcore | WU5 |
| `taxonomy_types` | Taxonomy Module | Taxonomy Module | No Webcore move |
| `taxonomy_terms` | Taxonomy Module | Taxonomy Module | No Webcore move |
| `taxonomy_assignments` | Taxonomy Module | Taxonomy Module | No Webcore move |
| Form Manager tables | Form Manager | Form Manager | No ownership move |

The target metadata is descriptive and transition-scoped. It does not grant a
Module authority over a Webcore target table, grant Webcore authority over a
currently Module-owned table, or authorize cross-owner mutation. Existing
namespace-aware physical resolution and owner-bounded extension grants remain
unchanged.

## Acceptance and implementation boundary

Promotion is complete when this contract and the materially affected current
documentation are durable on authoritative `main`, the final diff is
documentation-only, and the remote commit is independently verified.

Before any implementation WU begins, its execution slice must reconcile the
affected source, schema, runtime, package, installer, and accepted lifecycle
contracts. No implementation, destructive cleanup, package deletion, release,
tag, or publication is part of this promotion.
