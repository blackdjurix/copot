# Content Manager Refinement

Status: FUTURE DEDICATED BUNDLED MODULE REFINEMENT / PLANNING ONLY

This Concept is the current Git-side semantic source for the future logical
identity `Content Manager Refinement`. It describes a retained optional
extension over Webcore Content. It is not implementation authority, a
contract, or a product commitment.

## Delivered baseline and historical boundary

Webcore owns minimum Content viability, including Page and Article types,
persistence, slug/public identity, draft/published/archived lifecycle, basic
authorship, basic Admin list/create/edit/publish/archive, basic Media
reference, public delivery, and normalized render data.

Content Manager remains an optional Bundled Module that `EXTENDS` Webcore
Content. The baseline must remain usable when Content Manager is disabled and
must never depend on the Module for its ownership or operation.

Historical MR.2 WU4 — Content Manager Refinement is `COMPLETE / CLOSED` for
its accepted bounded list/form, filtering, lifecycle presentation, responsive,
and Featured Media control scope. That history is provenance only; this future
Concept does not reopen or reinterpret WU4.

## Future Content Manager role

Content Manager is the advanced editorial and content-management extension
over Webcore Content. Future refinement may add richer capability while
preserving Webcore ownership of the minimum Content domain.

Capability expansion should prefer explicit capability contracts rather than
coupling to concrete provider package identities. Content Manager may declare
requirements such as baseline media, optional richer media, fallback-capable
taxonomy, and future editorial sub-capabilities. The related provider Concept
defines mandatory/optional/fallback-capable requirements, discovered versus
selected versus active providers, one authoritative provider by default,
explicit transition, and migration/cutover where state is involved.

## Taxonomy capability

Webcore has no taxonomy system by default. Taxonomy semantics and state are
Module-owned by default, and Taxonomy Manager remains a high-confidence
retirement candidate. Content Manager may own Content-specific taxonomy
semantics and state itself.

Future Content Manager taxonomy behavior may use:

- a self-owned taxonomy fallback; or
- a compatible external taxonomy capability provider.

The dependency targets the taxonomy capability contract, not a concrete
Taxonomy Manager package identity. Installing or enabling a compatible provider
does not automatically replace the current taxonomy authority; discovery does
not imply selection or takeover. If taxonomy state must move, explicit
provider transition and state migration are required under
`concepts/copot_module_package_identity_and_capability_provider_concept.md`.
Failed migration must not create split-brain ownership or silent dual writes.

This direction does not reverse the Taxonomy Manager retirement disposition or
require a shared taxonomy identity across unrelated domains.

## Media capability

Content Manager consumes media capability contracts rather than a concrete
Media Manager identity. Webcore Media can satisfy baseline Content media needs,
and Content Manager must remain functional with Webcore Media only. Media
Manager or another compatible provider may satisfy richer media capabilities
when available, without becoming mandatory.

Potential future uses include featured media, inline media, richer selection or
picker experience, and richer preparation or processing where the active
provider supports it. Media domain ownership remains with Media; Content
Manager does not move Media processing, storage, delivery, or usage ownership
into the Content Module.

Optional provider absence should degrade truthfully: a missing richer provider
falls back to Webcore Media where possible, unsupported advanced behavior is
hidden or disabled, and a genuinely mandatory future capability is `BLOCKED`
only where it is required. Optional provider absence must not unnecessarily
break baseline Content Manager capability.

## Editorial and rich-formatting direction

Future Content Manager refinement may include an advanced editorial capability
with bounded user-facing formatting such as bold, italic, headings, links,
lists, inline images/media, and related rich-content features.

The exact implementation remains unresolved and is not authorized. Future
preparation must separately evaluate:

- editor implementation or library;
- storage representation;
- normalized document model versus markup;
- sanitization boundary;
- rendering contract;
- migration of historical/plain content;
- interoperability with Themes and Built-in Public View; and
- any extension/plugin contribution model.

This Concept does not select HTML, Markdown, block JSON, Gutenberg, ProseMirror,
or another editor/storage architecture. Editorial capability belongs naturally
to future Content Manager refinement unless later evidence proves it must be a
separate cross-cutting capability.

## Ownership boundaries

Content Manager does not own:

- Webcore Content baseline;
- Webcore Media baseline;
- provider resolver infrastructure;
- System Health;
- global taxonomy identity across domains;
- Theme rendering ownership;
- Site Color Scheme; or
- System Manager lifecycle authority.

It may consume those capabilities through accepted contracts. Provider
selection, transition, migration, and health reporting remain owned by the
corresponding capability/provider and System Health boundaries.

## Historical lineage and disposition

This Concept preserves enough lineage to reconstruct the original Content
Manager refinement direction, the architecture reconciliation that made the
Module an optional extension over Webcore Content, the closed MR.2 WU4 scope,
and the later re-homing of per-Bundled-Module refinement outside MR.2.

Historical GPT/File Library planning sources are provenance and correction
inputs only. They do not override the Git Workplan or repository authority.
No historical execution plan is adopted as current future scope by this file.

## Authorization boundary

This Concept does not authorize Content Manager implementation, taxonomy or
provider implementation, Media changes, editor selection, schema/persistence
changes, lifecycle changes, production action, or release/publication. Exact
editor, storage, rendering, and sanitization decisions require future
preparation and separate authoritative approval.
