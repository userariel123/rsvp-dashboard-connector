# Column Visibility Toggle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Colonnes" dropdown to the dashboard with one checkbox per table column, letting a viewer hide/show columns on screen without touching plugin settings.

**Architecture:** Purely client-side and purely visual — the set of columns that *exist* is still determined by plugin settings (5 core roles + configured extra columns), unchanged. This feature only controls which of those already-existing columns are currently visible in the browser, via a dynamically-generated `<style>` block using `:nth-child()` selectors, so hidden columns stay hidden across every table re-render (poll, sort, filter, page change) without needing to touch the row-rendering code at all.

**Tech Stack:** Unchanged — vanilla JS, Tabler dropdown/checkbox markup, no new dependencies.

## Global Constraints

- No settings/backend changes: this feature does not read or write `extra_columns`, does not call any REST endpoint, and does not affect Excel export or print (both continue to reflect all configured columns, not the on-screen visibility state — out of scope for this plan).
- Column count is dynamic per site: exactly one checkbox per column that actually exists for the currently configured form (5 fixed + however many `extra_columns` are configured, which can be 0).
- The action column (trash button, last column) is never toggleable — no checkbox for it.
- Hiding a column must persist across the 20-second auto-refresh, sorting, filtering, and pagination — implemented via a single injected `<style>` element keyed by column position, not by touching individual rendered rows.

---

## File Structure (no new files)

```
templates/dashboard-markup.php   + "Colonnes" dropdown button with one checkbox per column
assets/js/dashboard.js           + applyColumnVisibility(): builds/updates the hiding <style> block
```

---

### Task 1: Markup — "Colonnes" dropdown with per-column checkboxes

**Files:**
- Modify: `templates/dashboard-markup.php`

**Interfaces:**
- Consumes: `$extra_columns` (existing, unchanged).
- Produces: checkbox inputs with class `rsvp-dash-col-toggle` and `data-col="<n>"` (1-based, matching each column's `:nth-child()` position in the table: `1`=Prénom, `2`=Nom, `3`=Présence, `4`=Adultes, `5`=Enfants, `6`+=one per configured extra column, in the same order they're already looped for the `<thead>`). Task 2's JS reads these.

- [ ] **Step 1: Add the dropdown**

In `templates/dashboard-markup.php`, find the button group that currently contains the filter dropdown, Print, Excel, and Corbeille buttons (inside the `card-header`'s `d-flex` with `gap:8px`). Add a new "Colonnes" dropdown as the first button in that group, before the filter dropdown:

```php
              <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                  <i class="ti ti-columns"></i> Colonnes
                </button>
                <div class="dropdown-menu dropdown-menu-end p-2" id="rsvp-dash-columns-menu" style="min-width:200px">
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="1">
                    <span class="form-check-label">Prénom</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="2">
                    <span class="form-check-label">Nom</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="3">
                    <span class="form-check-label">Présence</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="4">
                    <span class="form-check-label">Adultes</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="5">
                    <span class="form-check-label">Enfants</span>
                  </label>
                  <?php $rsvp_col_index = 6; foreach ( $extra_columns as $col ) : ?>
                  <label class="form-check">
                    <input class="form-check-input rsvp-dash-col-toggle" type="checkbox" checked data-col="<?php echo (int) $rsvp_col_index; ?>">
                    <span class="form-check-label"><?php echo esc_html( $col['label'] ); ?></span>
                  </label>
                  <?php $rsvp_col_index++; endforeach; ?>
                </div>
              </div>
```

`data-bs-auto-close="outside"` keeps the dropdown open while checking/unchecking multiple boxes — it only closes when clicking outside the menu, matching the multi-toggle use case (a viewer likely wants to hide 2-3 columns in one go, not reopen the menu each time).

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, view the dashboard page. Expected: a "Colonnes" button appears before the "Tous" filter dropdown, opening a checklist with one entry per configured column (5 fixed + any configured extra columns), all checked by default. Checking/unchecking a box (before Task 2 lands) has no visible effect yet — that's expected, Task 2 wires the behavior.
2. Expected: clicking a checkbox does NOT close the dropdown (data-bs-auto-close="outside" working).

- [ ] **Step 3: Commit**

```bash
git add templates/dashboard-markup.php
git commit -m "Add Colonnes dropdown with per-column visibility checkboxes"
```

---

### Task 2: Script — apply column visibility via injected stylesheet

**Files:**
- Modify: `assets/js/dashboard.js`

**Interfaces:**
- Consumes: `.rsvp-dash-col-toggle` checkboxes with `data-col` (Task 1); table id `rsvp-dash-table` (pre-existing).

- [ ] **Step 1: Add the visibility function and event bindings**

In `assets/js/dashboard.js`, add this function anywhere alongside the other top-level functions in the IIFE (e.g., near `renderPagination`):

```javascript
  function applyColumnVisibility() {
    var style = document.getElementById('rsvp-dash-col-style');
    if (!style) {
      style = document.createElement('style');
      style.id = 'rsvp-dash-col-style';
      document.head.appendChild(style);
    }
    var rules = [];
    document.querySelectorAll('.rsvp-dash-col-toggle').forEach(function (cb) {
      if (!cb.checked) {
        var col = cb.getAttribute('data-col');
        rules.push('#rsvp-dash-table th:nth-child(' + col + '), #rsvp-dash-table td:nth-child(' + col + ') { display: none; }');
      }
    });
    style.textContent = rules.join('\n');
  }
```

Then, inside the existing `document.addEventListener('DOMContentLoaded', function () { ... });` block, add this binding alongside the other `querySelectorAll(...).forEach(...)` bindings already there (e.g., near the sort-header binding):

```javascript
    document.querySelectorAll('.rsvp-dash-col-toggle').forEach(function (cb) {
      cb.addEventListener('change', applyColumnVisibility);
    });
```

Do not call `applyColumnVisibility()` on page load — all checkboxes start checked (Task 1), so there is nothing to hide initially; the function only needs to run when a checkbox changes.

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, reload the dashboard page.
2. Uncheck "Enfants" in the Colonnes dropdown — expected: the "Enfants" column (header and every row's cell) disappears immediately from the visible table.
3. Wait 20+ seconds for the auto-refresh, or type in the search box, or click a column header to sort, or change page — expected: "Enfants" stays hidden through all of these (the injected `<style>` rule survives every table re-render).
4. Re-check "Enfants" — expected: the column reappears immediately.
5. Uncheck a configured extra column (if any are set up) — expected: same hide/show behavior.
6. Confirm the Excel export (button next to Colonnes) still includes ALL columns regardless of what's currently hidden on screen — this is intentional, out of scope for this plan.

- [ ] **Step 3: Commit**

```bash
git add assets/js/dashboard.js
git commit -m "Add applyColumnVisibility() wired to Colonnes checkboxes"
```

---

### Task 3: Full end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Combined interaction check**

Hide 2-3 columns via the Colonnes menu, then exercise search, sort, filter, and pagination together — confirm the hidden columns never reappear unexpectedly and the dropdown itself never auto-closes while checking multiple boxes in sequence.

- [ ] **Step 2: Final commit**

```bash
git add -A
git commit -m "Complete end-to-end verification of column visibility toggle"
```
