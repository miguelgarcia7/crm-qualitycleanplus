# Phase 08c — Knowledge Base

| Field | Value |
|---|---|
| Status | 🚧 In progress |
| Last updated | 2026-06-09 |
| Owner | Engineering |

## Goal

Port the legacy CRM's strongest module per `docs/20-domain/knowledge-base.md`:
versioned rich-text articles organized by hierarchical categories and flat tags,
with **role-based visibility** (zero roles attached = visible to all authenticated
users; otherwise role-gated; super_admin bypasses), explicit status transitions
(draft → published → archived), full-text search with AJAX autosuggest, article
attachments, and a polymorphic "Was this helpful?" **feedback** loop. Two reader
surfaces: the back office (`/admin/kb`) and a simplified contractor view in QC
Minute.

## Deviations from the spec doc (decided this phase)

- **Attachments use the shared polymorphic `files` table** (morphMany on
  `KbArticle`) instead of a dedicated `kb_attachments` table. The spec kept
  `kb_attachments` "for query simplicity", but our `App\Domain\Shared\Models\File`
  was designed for this ("contract documents now; KB/invoices later") and every
  other module (contracts, onboarding docs, selfies) already stores uploads there.
  Downloads stream through an authenticated controller route so role-gated
  articles keep their attachments gated too.
- **`feedback.user_id` → `person_id`** — our auth model is `Person`.
- **FULLTEXT index is MySQL-only** (tests run on SQLite). Search uses
  `MATCH … AGAINST` on MySQL and falls back to `LIKE` elsewhere — the legacy app
  shipped the index but actually queried with `LIKE` everywhere anyway.
- **Publish notifications deferred** — same call as PTO notifications (Phase 08a):
  no notification UI exists yet; the dashboard widgets + KB hub cover visibility
  in v1. The spec's per-article "notify on publish" toggle ships with the future
  notifications phase.
- **Two permissions added** (`kb.categories.manage`, `kb.feedback.manage`, both
  admin + office_manager) — the seeded `kb.articles.*` set didn't cover the
  spec's "manage categories/tags" and "manage feedback" rows. Role-visibility
  management rides with `kb.articles.publish` (same role set in the spec table).

## Schema (spec §Shape, adapted)

`kb_categories` (name, slug unique, description, sort_order, parent_id self-FK
nullable — hierarchical, is_active) · `kb_articles` (title, slug unique, summary,
content longtext, author_id→people, last_edited_by, status draft|published|archived,
published_at, view_count, is_featured, version int, soft deletes, FULLTEXT
title/summary/content on MySQL) · `kb_article_versions` (immutable snapshots:
article_id, version, title/summary/content, author_id, change_summary,
created_at only, UNIQUE(article_id, version)) · `kb_tags` (name, slug unique) ·
pivots `kb_article_category` / `kb_article_tag` (UNIQUE pairs) ·
`kb_article_role` (kb_article_id, role_id → Spatie roles, UNIQUE pair) ·
`feedback` (polymorphic feedbackable, person_id nullable, type
helpful|not_helpful|suggestion|issue|question, message, url, user_agent,
is_resolved, resolved_by/at, admin_notes).

## Locked rules

- **Visibility** (`KbArticle::scopeVisibleTo(Person)` + `isVisibleTo`): no roles
  attached → all authenticated users; roles attached → user needs ≥1 matching
  role; super_admin sees everything. Readers only ever see `published`.
- **Versioning**: every update snapshots the *current* state into
  `kb_article_versions` first, then increments `version`, applies the edit, and
  stamps `last_edited_by`. Snapshots are immutable.
- **Statuses**: explicit transitions only (Publish / Unpublish / Archive).
  `archived` is not editable — unarchive first. `published_at` set on first
  publish only.
- **Tags auto-create** when authors type new ones (`KbTag::findOrCreateByName`).
- **Slugs** generated once from title (uniquified `-2`, `-3`…), stable across
  title edits — they're route keys.
- **Feedback votes** (helpful / not_helpful) dedupe per person per article: a
  repeat identical vote is rejected; a changed vote updates the existing row.
  Suggestion/issue/question entries are unlimited and feed the admin queue.
- **Permissions**: read = `kb.articles.view` (all roles incl. contractor, minus
  property_manager); author = `kb.articles.create/edit` (admin, office_manager,
  hr); publish/delete = admin + office_manager; categories/tags + feedback
  management = admin + office_manager (new perms above).

## Increments

- **Inc 0 — Infra**: this doc; enums `KbArticleStatus`, `FeedbackType` (Shared);
  six migrations; models `KbArticle` / `KbArticleVersion` / `KbCategory` /
  `KbTag` (+ `Shared\Feedback`) with factories; seed the two new permissions.
- **Inc 1 — Authoring backend**: `UpdateKbArticle` action (snapshot → increment →
  apply); `KbArticlePolicy`; controllers (articles CRUD + publish/unpublish/
  archive + versions, categories, tags + autosuggest, feedback queue, attachment
  upload/download/delete via `File`); `backoffice.kb.*` routes.
- **Inc 2 — Admin UI**: Tiptap editor; `views/admin/kb/*` — articles DataTable,
  article form (editor, categories, tag input w/ autosuggest, role visibility,
  attachments), article show + version history, categories, tags, feedback queue.
- **Inc 3 — Reader surfaces**: back-office KB hub (search + category tree +
  featured), article reader (view-count, related articles, attachments, feedback
  widget), search page + AJAX suggest, category/tag browse; QC Minute contractor
  reader; sidebar entries.
- **Inc 4 — Tests + docs + seed**: Pest feature coverage (visibility, versioning,
  transitions, tag auto-create, search, feedback dedupe, permission gates,
  contractor surface); sample KB content in `SampleDataSeeder`; docs (this file
  "as built", roadmap, Domain README, permissions matrix).

## Out of scope / deferred

Publish notifications (+ per-article toggle); KB analytics beyond view counts and
the hub's feedback aggregates; Spanish content; public (unauthenticated) KB.
