# Content System

## Purpose

The content system provides the first publishing foundation for copot.

Content is the primary domain concept. Article, Page, News, Video, Gallery, Documentation, FAQ, and similar terms are content types or use cases, not the primary architecture model.

M1.5 introduces a local Content Module. It includes basic content creation, editing, publishing lifecycle, and frontend rendering. It does not include advanced editorial, media, SEO, analytics, translation, or workflow features.

---

## M1.5 Scope

Included:

* Local module at `modules/content`
* Content domain model
* Simple string content type
* Default content types: `page`, `article`
* Draft, published, and archived statuses
* Slug-based frontend URL: `/content/{slug}`
* Admin list, create, edit, publish, draft, and archive workflows
* Plain textarea body input
* Escaped plaintext frontend body rendering with line breaks
* Permission metadata:
  * `content.create`
  * `content.update`
  * `content.delete`
  * `content.publish`
* Theme override support through the existing module view resolution order
* Request-scope admin navigation item through `AdminNavigation`
* POST-body-only CSRF validation through `Csrf`

Excluded:

* Editor.js implementation
* Media Library
* Image Editor
* SEO module
* Analytics
* AI
* Translation or multilanguage content
* Comments
* Newsletter
* Forms
* Advanced search
* Revision or version history
* Autosave
* Approval workflow
* Custom fields
* Full scheduling engine
* Menu manager
* Settings UI
* Role or permission UI
* Module UI
* Theme UI
* Taxonomy categories and tags

---

## Content Type Strategy

M1.5 uses a simple string `type` field.

Allowed defaults:

```text
page
article
```

There is no `content_types` table in M1.5.

---

## Publishing Strategy

M1.5 statuses:

```text
draft
published
archived
```

Only published content is public. Draft and archived content must not render on the frontend route.

Delete behavior in M1.5 is archive-only. There is no hard delete UI.

Publishing actions:

* `content.publish` is required to publish content.
* `content.publish` is required to move published content back to draft.
* `content.delete` is required to archive content.
* Archived content remains visible in the admin list.

---

## Frontend Rendering

Published content renders at:

```text
/content/{slug}
```

Resolution uses the Theme System namespace:

```text
content::show
```

Resolution order:

```text
1. themes/<active-theme>/views/modules/content/show.php
2. modules/content/views/show.php
```

The frontend view renders escaped plaintext with line breaks. It does not render raw HTML or Editor.js block JSON.

Draft, archived, and missing content return `404 Not Found`.

---

## Editor Strategy

M1.5 uses a plain textarea.

Editor.js is the leading planned default editor adapter, but it is not implemented in M1.5, required by Core Infrastructure, or hardcoded into Content.

The future M2 Editor Framework must keep editor implementations pluggable and replaceable.

The Content Module owns content lifecycle and will evolve toward the future M3 Content Manager / Workspace without becoming a duplicate replacement module.

M1.5 uses basic CRUD and form UI only.

The long-term editing direction is an expanded Content Manager / Workspace, including possible row-open workspace behavior, preview mode, HTML mode, plugin panels, and richer editor UX.

These features are future work and are not part of M1.5.

---

## Security Services

Repeated security-sensitive logic should move into dedicated shared services in Core Infrastructure or an approved Platform Capability, according to ownership and dependency direction.

CSRF handling currently uses `app/Core/Csrf.php`. New POST routes should use `$app->csrf()->validateOrReject($request)` or a future equivalent instead of manually extracting tokens.

---

## Taxonomy Boundary

Categories and tags are provided by the M1.6 Taxonomy Foundation when the Taxonomy module is installed and enabled.

Content can integrate taxonomy terms in the admin create/edit form:

* Category terms from taxonomy type `category`
* Tag terms from taxonomy type `tag`

Assignments are stored through the Taxonomy module using:

```text
entity_type = content
entity_id = content.id
```

Content still works when the Taxonomy module is disabled. In that case, taxonomy fields and taxonomy list columns are hidden and assignment sync is skipped.

The Content Module remains responsible for content lifecycle and rendering. Taxonomy does not replace Content and does not add public taxonomy URLs in M1.6.
