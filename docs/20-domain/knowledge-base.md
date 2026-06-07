# Knowledge Base

| Field | Value |
|---|---|
| Status | Accepted (port from legacy CRM, mostly unchanged) |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

The Knowledge Base (KB) is the strongest module in the legacy QCP CRM and we port it forward with minor adjustments. Articles are written by editors, versioned automatically, organized by categories and tags, and made visible based on role.

## Shape

```
kb_articles
  - id
  - title, slug (unique)
  - summary (text)
  - content (longtext — rich HTML from Tiptap or similar)
  - author_id (FK to people)
  - last_edited_by (FK to people, nullable)
  - status: enum (draft | published | archived)
  - published_at (nullable)
  - view_count (int)
  - is_featured (bool)
  - version (int, monotonic)
  - created_at, updated_at, deleted_at
  - FULLTEXT(title, summary, content)

kb_article_versions  (immutable snapshots)
  - id
  - article_id (FK)
  - version (int)
  - title, summary, content (snapshot at this version)
  - author_id (FK to people — who created this version)
  - change_summary (text, optional)
  - created_at (no updated_at)
  - UNIQUE(article_id, version)

kb_categories
  - id, name, slug (unique), description
  - sort_order (int)
  - parent_id (nullable FK — hierarchical)
  - is_active (bool)
  - created_at, updated_at

kb_article_category  (M2M pivot)
  - id, article_id, category_id, created_at
  - UNIQUE(article_id, category_id)

kb_tags
  - id, name, slug (unique)
  - created_at, updated_at

kb_article_tag  (M2M pivot)
  - id, article_id, tag_id, created_at
  - UNIQUE(article_id, tag_id)

kb_attachments
  - id, article_id (FK)
  - filename, filepath, mime_type, file_size
  - sort_order (int)
  - created_at, updated_at

kb_article_role  (M2M pivot — controls visibility)
  - id, kb_article_id, role_id, created_at, updated_at
  - UNIQUE(kb_article_id, role_id)
```

## Role-based visibility

The `kb_article_role` table controls who sees an article:

- **If an article has zero roles attached:** visible to all authenticated users
- **If an article has one or more roles attached:** visible only to users with a matching role
- **Super admins always see everything** (bypasses the check)

This lets us publish articles only to contractors (e.g. "How to use the time clock"), only to PMs (e.g. "How approving timesheets works"), only to staff (e.g. "Office procedures"), or to combinations.

Implementation: `KbArticle::scopeVisibleToUser(User $user)`:

```php
public function scopeVisibleToUser($query, ?User $user)
{
    if ($user?->hasRole('super_admin')) {
        return $query;
    }

    return $query->where(function ($q) use ($user) {
        $q->whereDoesntHave('roles')  // no roles = visible to all
          ->orWhereHas('roles', fn($q) => $q->whereIn('id', $user?->roles->pluck('id') ?? []));
    });
}
```

## Versioning

Every edit creates a new `kb_article_versions` row before the article is updated:

```
On article update:
  1. Snapshot current article state into kb_article_versions
  2. Increment article.version
  3. Apply the update to kb_articles
  4. Set article.last_edited_by
```

The snapshot is **immutable** — never edited or deleted. Provides full history.

Version display:

- Article detail page shows current version + "Versions" link
- Versions page lists all historical versions with author, date, change_summary
- Clicking a version shows the historical content in read-only

## Statuses

| Status | Visible to readers | Editable |
|---|---|---|
| `draft` | No | Yes |
| `published` | Yes (subject to role visibility) | Yes (creates new version on edit) |
| `archived` | No | No — must unarchive to edit |

Status transitions are explicit clicks (Publish / Unpublish / Archive). No auto-transitions.

## Categories (hierarchical)

Categories can have parents — supports trees like:

```
Operations
├── Time Clock
│   ├── How to clock in
│   ├── How to clock out
│   └── Troubleshooting
├── Uniforms
│   ├── Requesting a uniform
│   └── Care instructions
└── ...

HR
├── PTO
├── Onboarding
└── ...
```

Sidebar navigation reflects the tree. Articles can be in multiple categories.

## Tags

Flat (no hierarchy). Articles can have multiple tags. Tags are auto-created when authors type new ones (Tagify-style UI).

## Attachments

PDFs, images, docs attached to articles. Use the same file storage pattern as the rest of the system (`files` polymorphic table can be replaced or supplemented; legacy KB has its own `kb_attachments`, we'll keep that for query simplicity).

Attachments are displayed inline (images) or as download links (PDFs/docs).

## Search

Three search paths:

- **Full-text** on `title + summary + content` (MySQL FULLTEXT index)
- **Category browse** (sidebar navigation)
- **Tag filter** (click a tag to see all articles with it)
- **AJAX autosuggest** as the user types in the search bar

## Feedback on articles

Every article has a "Was this helpful?" mechanism via the polymorphic `feedback` table (see legacy CRM):

```
feedback
  - id
  - feedbackable_type, feedbackable_id (polymorphic — points to KbArticle)
  - user_id (nullable; null for anonymous)
  - type: enum (helpful | not_helpful | suggestion | issue | question)
  - message (text, optional for some types)
  - url, user_agent (for context)
  - is_resolved (bool)
  - resolved_by, resolved_at, admin_notes
  - created_at, updated_at
```

Feedback aggregates show on the admin KB dashboard (e.g. "5 articles with negative feedback this month").

## Contractor view of KB

In QC Minute, contractors see a simplified KB view:

- Articles with role `contractor` attached (or no roles)
- No authoring affordance
- No category management
- Can still submit feedback ("This was helpful" thumbs)
- Mobile-friendly layout

## Permissions

| Action | Roles |
|---|---|
| Read KB | All authenticated users (subject to role-gated articles) |
| Create / edit articles | super_admin, office_manager, hr |
| Publish / unpublish | super_admin, office_manager |
| Delete articles | super_admin |
| Manage categories / tags | super_admin, office_manager |
| Manage role visibility | super_admin, office_manager |
| Manage feedback | super_admin, office_manager |

## Editor

Tiptap (already used in legacy) or similar rich-text editor. Supports headings, lists, tables, links, images, code blocks, highlights, underline, sub/superscript.

## Notifications

Article published → notification to roles that can see it (configurable per article: "notify on publish: yes/no").

## Permissions sync to legacy

If we want to allow visibility based on legacy roles before the rebuild ships fully, the role table is shared (Spatie). No migration needed.

## Related

- `20-domain/people-lifecycle.md` — who has what role
- `10-architecture/permissions-matrix.md` — `kb.*` permissions
- `30-schema/kb-tables.md` (TBD)
