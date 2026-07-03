# Présence Value Suggestions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop admins from having to guess/type the "présence confirmée" value blindly — show them the distinct values actually seen in real submitted responses for the currently-mapped Présence field, as autocomplete suggestions.

**Architecture:** A new admin-gated REST endpoint reuses the plugin's existing entry-fetching and dot-path extraction helpers (already used by `/stats` and `/debug`) to compute the distinct values seen for one field across real entries. The settings page fetches this once on load (only when a form and a Présence field key are already configured) and populates an HTML5 `<datalist>` wired to the existing text input — native browser autocomplete, no new dependency, free typing still works as a fallback for a brand-new form with no submissions yet.

**Tech Stack:** Unchanged — PHP REST route, vanilla JS, HTML5 `<datalist>` (no library).

## Global Constraints

- This does not replace the existing "voir un exemple de réponse brute" debug link — it's a narrower, additional helper specifically for the "présence confirmée" value, which is what caused a real live misconfiguration (ISR form) when typed by hand from an assumption instead of a real value.
- No access to Fluent Forms' internal form-builder field definitions is used or claimed — only real submitted entry data (the same data source `/stats` and `/debug` already use), reusing the plugin's own `fetch_all_entries()` and `extract_value()` helpers, not a new/different data path.
- The new REST route is admin-only (`manage_options`), consistent with `/debug`.
- The suggestion fetch only runs when BOTH a form is selected AND the "Champ Présence (clé exacte)" field already has a value — without a key, there's nothing to extract distinct values from.
- Free text entry must keep working exactly as before — `<datalist>` offers suggestions, it never restricts input to only the suggested values.

---

## File Structure (no new files)

```
includes/class-rest-api.php    + admin-gated GET /field-values/{form_id}?key=<path> route,
                                  returning distinct real values seen for that dot-path
includes/class-settings.php    + <datalist> wired to the "présence confirmée" input,
                                  + inline script fetching suggestions on page load
```

---

### Task 1: REST endpoint — distinct field values from real entries

**Files:**
- Modify: `includes/class-rest-api.php`

**Interfaces:**
- Consumes: `self::fetch_all_entries( $form_id )`, `self::extract_value( $data, $path )` (both pre-existing private methods, unchanged).
- Produces: REST route `GET /wp-json/rsvp-dashboard/v1/field-values/{form_id}?key=<dot-path>` (admin-only), returning `{"values": [string, ...]}` — the distinct, non-empty values seen for that dot-path across all fetched entries, in first-seen order, deduplicated.

- [ ] **Step 1: Register the route and add the callback**

In `includes/class-rest-api.php`, inside `register_routes()`, add this new route registration alongside the existing ones (after `/debug/(?P<form_id>\d+)`, before `/stats`):

```php
        register_rest_route( 'rsvp-dashboard/v1', '/field-values/(?P<form_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'field_values' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );
```

Then add this new method anywhere alongside the other public static methods (e.g. right after `debug_form()`):

```php
    public static function field_values( $request ) {
        self::send_no_cache_headers();
        $form_id = (int) $request['form_id'];
        $key     = (string) $request->get_param( 'key' );

        if ( ! function_exists( 'fluentFormApi' ) || '' === $key || ! $form_id ) {
            return rest_ensure_response( array( 'values' => array() ) );
        }

        $entries = self::fetch_all_entries( $form_id );
        $values  = array();

        foreach ( $entries as $entry ) {
            $data  = is_array( $entry ) ? $entry : (array) $entry;
            $value = self::extract_value( $data, $key );
            if ( '' !== $value && ! in_array( (string) $value, $values, true ) ) {
                $values[] = (string) $value;
            }
        }

        return rest_ensure_response( array( 'values' => $values ) );
    }
```

- [ ] **Step 2: Verify manually on the real site**

1. Re-upload, while logged in as admin visit (replace `<form_id>` and `<key>` with a real form id and its mapped Présence key, and get a valid nonce the same way the debug link does — or simplest, just click the debug link first to get a fresh page-generated nonce value, then reuse it):
   `/wp-json/rsvp-dashboard/v1/field-values/<form_id>?key=response.dropdown&_wpnonce=<nonce>`
2. Expected: `{"values":["Présent à la Houppa","Ne pourra(ons) pas être présent(s)"]}` (or whatever the real distinct values are for that form) — not an error, not an empty array (assuming the form has real submissions).

- [ ] **Step 3: Commit**

```bash
git add includes/class-rest-api.php
git commit -m "Add admin-only /field-values endpoint returning distinct real values for a field"
```

---

### Task 2: Settings page — datalist suggestions for the présence-confirmée field

**Files:**
- Modify: `includes/class-settings.php`

**Interfaces:**
- Consumes: `GET /wp-json/rsvp-dashboard/v1/field-values/{form_id}?key=<path>` (Task 1).

- [ ] **Step 1: Add the datalist and wire the input to it**

In `includes/class-settings.php`, find the table row for "Valeur qui signifie \"présence confirmée\"", which currently looks like:

```php
                    <tr>
                        <th><label>Valeur qui signifie "présence confirmée"</label></th>
                        <td>
                            <input type="text" style="width:200px"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[presence_yes_value]"
                                   value="<?php echo esc_attr( $settings['presence_yes_value'] ); ?>"
                                   placeholder="ex: Oui" />
                        </td>
                    </tr>
```

Replace it with (adds `list="rsvp-dash-presence-suggestions"` to the input, an empty `<datalist>` right after it, and a short hint):

```php
                    <tr>
                        <th><label>Valeur qui signifie "présence confirmée"</label></th>
                        <td>
                            <input type="text" style="width:200px" list="rsvp-dash-presence-suggestions"
                                   name="<?php echo esc_attr( self::OPTION_KEY ); ?>[presence_yes_value]"
                                   value="<?php echo esc_attr( $settings['presence_yes_value'] ); ?>"
                                   placeholder="ex: Oui" />
                            <datalist id="rsvp-dash-presence-suggestions"></datalist>
                            <p class="description">Si des réponses existent déjà, les valeurs réellement vues apparaissent en suggestion pendant que tu tapes.</p>
                        </td>
                    </tr>
```

- [ ] **Step 2: Add the suggestion-fetching script**

In the same file, find where the debug link is rendered:

```php
            <?php if ( $settings['form_id'] ) :
                // Some hosts/security plugins now require the REST cookie-auth nonce even
                // on a plain clicked link (not just fetch() calls with an X-WP-Nonce header) —
                // WordPress's REST API accepts the nonce as a _wpnonce query arg for exactly
                // this case, confirmed live after a plain link started 401'ing on this site.
                $debug_url = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( 'rsvp-dashboard/v1/debug/' . $settings['form_id'] ) );
                ?>
                <p><a href="<?php echo esc_url( $debug_url ); ?>" target="_blank">
                    Voir un exemple de réponse brute (aide pour remplir les clés de champs ci-dessus)
                </a></p>
            <?php endif; ?>
```

Add this new block immediately after it (still inside the same `if ( $settings['form_id'] )` check is NOT needed here — this new block has its own, narrower condition):

```php
            <?php if ( $settings['form_id'] && ! empty( $settings['map']['presence'] ) ) :
                $field_values_url = add_query_arg(
                    array(
                        'key'      => $settings['map']['presence'],
                        '_wpnonce' => wp_create_nonce( 'wp_rest' ),
                    ),
                    rest_url( 'rsvp-dashboard/v1/field-values/' . $settings['form_id'] )
                );
                ?>
                <script>
                (function () {
                  var url = <?php echo wp_json_encode( esc_url_raw( $field_values_url ) ); ?>;
                  fetch(url, { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                      var list = document.getElementById('rsvp-dash-presence-suggestions');
                      if (!list || !data.values) return;
                      data.values.forEach(function (v) {
                        var opt = document.createElement('option');
                        opt.value = v;
                        list.appendChild(opt);
                      });
                    })
                    .catch(function () {});
                })();
                </script>
            <?php endif; ?>
```

- [ ] **Step 3: Verify manually on the real site**

1. Re-upload, go to Réglages → RSVP Dashboard, with a form selected and its "Champ Présence" already filled in.
2. Click into the "Valeur qui signifie..." text field — expected: a native browser dropdown appears showing the real distinct values seen in that form's actual responses (e.g., "Présent à la Houppa" and "Ne pourra(ons) pas être présent(s)").
3. Clicking a suggestion fills the field with that exact value — expected: no typo risk.
4. Typing a value NOT in the list — expected: still allowed and saved normally (datalist never restricts input).
5. Select a form with NO Présence key mapped yet, or a brand-new form with zero submissions — expected: no suggestions appear (empty datalist), no JS error, the field behaves like a normal text input.

- [ ] **Step 4: Commit**

```bash
git add includes/class-settings.php
git commit -m "Add real-value autocomplete suggestions for the présence-confirmée field"
```

---

### Task 3: Full end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Re-verify the exact scenario that caused the original bug**

On the "ISR" form (or an equivalent second form with a different présence wording), clear the "Valeur qui signifie présence confirmée" field, click into it, and confirm the real Hebrew (or other) value appears as a clickable suggestion rather than needing to be typed/guessed.

- [ ] **Step 2: Final commit**

```bash
git add -A
git commit -m "Complete end-to-end verification of présence value suggestions"
```
