# Back-office theme (Sage)

| Field | Value |
|---|---|
| Status | Reference |
| Last updated | 2026-10-10 |
| Owner | Engineering |

The back office and QC Minute use the template's **Sage** skin (`html[data-skin="sage"]`).
Its variables live in `resources/css/admin/config/_theme-sage.css`, layered over the
template defaults in `config/_root.css`. Change the look there before touching components.

## Readability pass (2026-10-09)

The skin only overrode one shade of the gray scale, so text kept the template's
blue-slate grays: `text-default-400` (used about 420 times) sat at 2.4:1 contrast on cards.
The pass, made to match the System Reference mockup:

| Change | Where in `_theme-sage.css` |
|---|---|
| Full warm gray scale `--color-default-50…950`, light and dark mode. Captions (400) ~4:1, secondary text (500) 4.7:1, headings (900) `#313a36` | Top of the skin block and the dark-mode block |
| Page title (`.page-main-title`) 24px in heading ink | Sage signature block |
| `.badge-label` badges as pills | Sage signature block |
| Page spacing from tablet width up: 32px sides, 28px above the title, 24px below it, 48px at the bottom | Sage signature block, `@media (min-width: 768px)` |
| Sidebar 260px wide; item rows 10px top and bottom, 12px sides; sub-items 13.5px | `--sidenav-width`, `--sidenav-item-padding-*`, `--sidenav-sub-item-font-size` |

Section titles in the sidebar keep their standard top margin (the `mt-0!` override was
removed from `AppMenu.tsx`).

## Keep as they are

- **The dark left sidebar** (`data-menu-color="dark"`, background `#2a322e`). It has its own
  `--sidenav-*` variables per menu color, and none of the changes above touch them.
- **Body text at 16px.** The mockup used 15px, but readability came from contrast, not size.

## Page patterns

- **Tabs under the page title,** full width and underlined, as on System Reference and
  My Profile; not inside a card.
- **Settings forms as titled cards** with the save button in a footer strip (My Profile).
- **Tables in a card** whose header row sits below an empty band at the top, with role
  columns as buttons that highlight the column (Roles & permissions, Notifications).
