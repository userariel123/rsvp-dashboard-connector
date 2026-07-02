# RSVP Dashboard v2 — Design

**Goal:** Make the dashboard look like a real Tabler product (navbar, colored status badges, proper table styling), let each site show extra guest data beyond the 5 fixed fields, fix the confirmed/declined numbers to mean "people," and let the admin soft-delete/restore a guest entry from the dashboard itself, synced with Fluent Forms.

**Builds on:** the existing plugin at `rsvp-dashboard-connector/` (live, working, validated on a real site). Nothing described below replaces the existing security model (per-site field mapping, the `/stats` access token, CDN-only Tabler/Chart.js, the admin-only `/debug` endpoint, 20s polling) — all of that stays as-is.

## Out of scope

Nothing removed from the v1 scope discussion this round — everything raised (visual redesign, extra columns, headcount totals, deletion/trash) is in scope. The only thing explicitly deferred earlier and now reinstated is deletion/trash, which appears below with an explicit verification step because part of its mechanism is not yet confirmed against real Fluent Forms data.

Page-level password protection is **out of scope for code** — it's handled by WordPress's native "Password Protected" page visibility setting (Page Attributes, no plugin code involved). This gets documented in the README/install checklist, not built.

## 1. Visual redesign

Move from the current bare `card` + `subheader` + plain-text table to a more authentic Tabler layout, validated against a live side-by-side mockup:

- **Navbar**: a `navbar navbar-expand-md navbar-light` bar above the dashboard content, showing a configurable title (new setting, see below).
- **Stat cards**: exactly 4, unchanged in count from today — **Confirmés** (total têtes présentes: adultes+enfants côté "oui"), **Déclinés** (idem côté "non"), **Adultes** (juste les adultes parmi les confirmés), **Enfants** (juste les enfants parmi les confirmés). Confirmés/Déclinés are the headcount total; Adultes/Enfants are the breakdown of that same total — not a separate, unrelated number. Each card gets a small `badge bg-green-lt` / `bg-red-lt` accent instead of plain numbers only.
- **Guest table**: wrapped in a `card` with a `card-header` (`<h3 class="card-title">Invités</h3>`), using `table-vcenter card-table`. The "Présence" column renders a colored badge (`bg-green-lt` for confirmé, `bg-red-lt` for décliné) instead of plain text.

**New setting:** `dashboard_title` (free text, e.g. "Yoela & Shalev — RSVP"), rendered in the navbar. Empty falls back to the site name.

## 1b. More Tabler components (buttons, dropdown filter)

Confirmed against Tabler's own live markup, not guessed:

- **Trash/restore icons** become real Tabler icon buttons: `<button class="btn btn-icon btn-sm">` with a `<i class="ti ti-trash">` / `<i class="ti ti-arrow-back-up">` icon inside (Tabler Icons webfont, same CDN family as `@tabler/core`), instead of a bare unstyled icon — gets Tabler's built-in hover/focus/disabled states for free.
- **Filter dropdown** next to the search box (Tous / Confirmés / Déclinés), standard Bootstrap-5-based Tabler pattern: `<div class="dropdown">` wrapping a `<button class="btn dropdown-toggle" data-bs-toggle="dropdown">` and a `<div class="dropdown-menu">` with `<a class="dropdown-item" href="#">` entries — filtering happens client-side in `dashboard.js` against the already-fetched guest list, no new endpoint needed.
- **Lists** (list-group, media-list style) and **Datagrid** (key/value fiche layout) considered and intentionally left out: lists would replace the sortable-header table with no clear benefit here, and datagrid only makes sense for a future per-guest detail popup, which isn't in scope.

## 2. Extra columns (per-site flexible data)

Not every client's RSVP form has the same fields beyond prénom/nom/présence/adultes/enfants — some want to show régime alimentaire, table number, phone, etc. Rather than a dynamic add/remove list (which would need JS in the settings page, which doesn't exist today), the settings page gets **5 fixed optional slots**, each a `{label, field key}` pair, matching the same UX pattern as the 5 existing required fields:

```
Colonne libre 1: Étiquette [___]  Clé exacte [___]
...
Colonne libre 5: Étiquette [___]  Clé exacte [___]
```

An empty label means the slot is unused and never shown. Stored in `rsvp_dashboard_settings['extra_columns']` as an array of up to 5 `{label, key}` pairs (empty ones dropped on save).

**Rendering:** `templates/dashboard-markup.php` reads the configured extra columns at page-render time (PHP, not JS) and emits the matching `<th>` headers directly — the exact set of columns is already known server-side, no need to discover it client-side. `/stats` includes each guest's extra values in the same order, and `dashboard.js` fills in the matching `<td>` cells on each poll.

## 3. Confirmés / Déclinés = headcount, not response count

Today `confirmed`/`declined` in `/stats` count RSVP *entries* (11 confirmed entries). This is misleading for a host who wants total *people* coming. Change: `confirmed` = sum of (adultes + enfants) across entries where présence matches the "yes" value; `declined` = same sum for entries where it doesn't. Adultes/enfants stay the only two fields that feed this sum — extra columns (section 2) never participate in any calculation, display only.

## 4. Deletion / trash (needs a verification step first)

**UI:** each guest row in the table gets a small trash-can icon. Clicking it removes the row from the main view and adds it to a separate "Corbeille" panel (toggled via a button near the search box), which lists trashed guests with a restore icon.

**Backend, mechanism (to confirm before building — see Task 0 below):** Fluent Forms entries carry a `status` field (confirmed present in the raw data we already inspected: `"status":"read"`). Fluent Forms Pro's own admin UI has a trash/restore flow for entries, which almost certainly works by changing this status rather than deleting rows outright — but the exact status value used for "trashed" and whether Fluent Forms exposes a clean PHP method for it (vs. requiring a direct query through their `wpFluent()` query builder) is **not yet confirmed**, the same kind of gap that caused two live bugs earlier in this project. Before writing the real implementation, add a verification task: extend the existing `/debug/{form_id}` admin-only endpoint (or a new sibling route) to trash one real test entry via Fluent Forms' own admin UI, then inspect what changed in the raw entry data, and confirm the safest way to reproduce that change from our own code.

**New endpoints (shape, pending the verification above):**
- `POST /wp-json/rsvp-dashboard/v1/entries/{id}/trash` — token-protected (same token as `/stats`), marks one entry trashed.
- `POST /wp-json/rsvp-dashboard/v1/entries/{id}/restore` — same, reverses it.
- `/stats` excludes trashed entries from the normal `guests[]`/counts by default; a `?trash=1` variant (or a second lightweight response field) returns just the trashed ones for the "Corbeille" panel.

**Why token-auth, not a WP login check:** the dashboard's viewers (e.g. the couple) are not necessarily WordPress users — the page itself is gated by WordPress's native page password (section "Out of scope"), not a WP account. Consistent with `/stats`, mutation endpoints reuse the same shared-secret token rather than introducing a second auth model.

## What stays unchanged

Per-site field mapping (5 required roles), the `/stats` access token and its generation, Tabler/Chart.js loaded from CDN with no build step, the admin-only `/debug/{form_id}` endpoint, 20-second polling, the plugin's file structure (`includes/`, `assets/`, `templates/`).
