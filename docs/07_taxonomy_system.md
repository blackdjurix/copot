# Taxonomy System

## Purpose

The taxonomy system provides reusable classification for Copot.

Taxonomy is the primary domain concept. Category and tag are taxonomy types, not separate primary architecture models.

---

## M1.6 Scope

Included:

* Local module at `modules/taxonomy`
* Taxonomy types
* Taxonomy terms
* Generic assignments
* Default taxonomy types:
  * `category`
  * `tag`
* Delete terms only when unused
* Admin term management
* Minimal Content integration

Excluded:

* Public taxonomy URLs
* `/category/{slug}`
* `/tag/{slug}`
* Taxonomy type management UI
* Tree UI
* Drag-drop hierarchy UI
* SEO taxonomy pages
* Multilingual taxonomy
* API endpoints
* Search indexing
* Import/export
* Taxonomy custom fields
* Taxonomy media or icon handling

---

## Assignment Boundary

Taxonomy assignments should use a generic `entity_type` domain key.

For M1.6 implementation, only `content` is used.

Future modules may use the same assignment foundation after their milestones are approved.

---

## Database

M1.6 adds:

```text
taxonomy_types
taxonomy_terms
taxonomy_assignments
```

Default taxonomy types are seeded:

```text
category
tag
```

The `parent_id` column exists on terms for future hierarchy support, but M1.6
Admin UI remains flat and does not provide tree editing. M3.5 Taxonomy Manager
is the separately accepted scope for category hierarchy management; it does not
authorize taxonomy type CRUD or change the flat `tag` contract.

---

## Admin Term Management

Admin routes:

```text
/admin/taxonomy
/admin/taxonomy/category
/admin/taxonomy/tag
```

Supported actions:

* List category/tag terms
* Create terms
* Edit terms
* Delete unused terms

Required permissions:

```text
taxonomy.create
taxonomy.update
taxonomy.delete
```

Taxonomy type management is not included. M1.6 only manages terms for the seeded `category` and `tag` types.

---

## Delete Behavior

Terms can be deleted only when unused.

If a term is assigned through `taxonomy_assignments`, deletion is rejected with a controlled response. This prevents orphaned classification references without introducing soft-delete or revision behavior in M1.6.

---

## Content Integration

When the Taxonomy module is enabled, Content admin create/edit forms can select:

* Multiple category terms
* Multiple tag terms

Assignments are stored as:

```text
entity_type = content
entity_id = content.id
```

Content saves sync category and tag assignments separately, so category changes do not remove tag assignments and tag changes do not remove category assignments.

When the Taxonomy module is disabled:

* Content admin still works
* Category/tag fields are hidden
* Taxonomy list columns are hidden
* Assignment sync is skipped

M1.6 does not add frontend taxonomy rendering or public taxonomy archive pages.

---

## M3.5 Taxonomy Manager Boundary

M3.5 evolves this existing module without replacing it. Its accepted scope is
limited to hierarchically managing `category` terms, keeping `tag` terms flat,
and preserving the existing Content assignment boundary. Category parent
validation, cycle prevention, child-safe deletion, and clear Admin hierarchy
presentation are M3.5 requirements. The existing `taxonomy.create`,
`taxonomy.update`, and `taxonomy.delete` permissions remain the permission
boundary; taxonomy type CRUD is excluded.

The dedicated contract is:

```text
docs/21_m3_5_taxonomy_manager_contract.md
```

## Future Work

Deferred beyond M1.6:

* Public taxonomy URLs
* `/category/{slug}`
* `/tag/{slug}`
* Taxonomy archive pages
* Taxonomy type management UI
* Tree UI
* Drag-drop hierarchy UI
* SEO taxonomy pages
* Multilingual taxonomy
* API endpoints
* Search indexing
* Import/export
* Taxonomy custom fields
* Taxonomy media or icon handling
