# Maintenance punch-list

Open hygiene/improvement items surfaced during the 2026-05-23 site exploration. Grouped by category; rough effort + risk for each. Tackle in roughly the listed order within each category.

> **Important precondition**: nothing in this list should be done on production from this local copy. Use the local environment to verify, then apply to production via wp-admin / wp-cli on the live host. Where DB writes are needed, prefer wp-cli `option update` over raw SQL so WordPress object caches stay coherent.

---

## 🔥 Broken / actively noisy (do first)

### `utm-event-tracker-and-analytics` plugin is broken
- **What**: Registered in the `active_plugins` option but the plugin folder is missing on disk. Throws "The plugin file does not exist" admin notice on every wp-admin page load. Stale `_transient_utm_event_tracker_*` rows litter the options table.
- **Fix**:
  ```sh
  podman compose --profile cli run --rm wpcli wp plugin deactivate utm-event-tracker-and-analytics
  podman compose --profile cli run --rm wpcli wp transient delete --all
  ```
- **Risk**: none — plugin is already broken, can't get worse.
- **Effort**: 2 min.

### Ghost slot in `active_plugins`
- **What**: The serialized `active_plugins` array skips from index 11 → 13. A plugin was removed via filesystem rather than deactivated through admin. Cosmetic but indicates poor uninstall hygiene.
- **Fix**: Resaving `active_plugins` after the deactivation above will re-index. Otherwise:
  ```sh
  # Force re-serialization:
  podman compose --profile cli run --rm wpcli wp plugin deactivate hello
  podman compose --profile cli run --rm wpcli wp plugin activate hello   # if you actually want hello.php; else just leave deactivated
  ```
- **Risk**: none.
- **Effort**: 2 min (folded into above).

### Stale "January 29th" maintenance-mode copy
- **What**: WP Maintenance Mode is disabled, but the saved splash text references "Maintenance mode until January 29th" — leftover from the Jan 2021 relaunch. If the plugin ever gets toggled on (e.g. during an emergency), the wrong message ships.
- **Fix**: Edit the message via Settings → WP Maintenance Mode → General → Maintenance text, OR:
  ```sh
  podman compose --profile cli run --rm wpcli wp option pluck wpmm_settings general
  # then wp option patch update wpmm_settings general text "We'll be right back."
  ```
- **Risk**: low. Verify the plugin stays disabled afterward.
- **Effort**: 5 min.

---

## ⚙️ Configuration debt

### Akismet has no API key
- **What**: Akismet plugin active, but `wordpress_api_key` row absent. Comment spam filtering is inert.
- **Decide**: Is comment moderation actually desired on this site? Comments may already be globally disabled in Settings → Discussion. If so, deactivate Akismet rather than configuring a key.
- **Fix (if keeping)**: Get a free Personal API key from akismet.com, set via Settings → Akismet.
- **Effort**: 10 min.

### Google Site Kit not connected
- **What**: Plugin active with `pagespeed-insights` and `analytics` listed in `active_modules`, but no `googlesitekit_credentials` and `has_connected_admins=0`. No actual data is reaching this dashboard.
- **Decide**: Does the church actually use Google Analytics / Search Console? If yes, connect properly. If no, deactivate Site Kit to stop the dashboard placeholders.
- **Effort**: 30 min (auth flow), or 2 min to deactivate.

### Site Kit, Yoast, and several other plugins are out of date
- **What**: e.g. Site Kit 1.86 vs latest 1.179+. WordPress core itself is on 6.9.4 which is reasonably current, but plugins lag substantially.
- **Fix**: Plugin update sweep. Take a manual UpdraftPlus backup *first*, then update one plugin at a time, smoke-testing the site between each. Be especially careful with the commercial Maranatha / Church Theme Content / Church Content Pro updates — confirm the ChurchThemes license is still active.
- **Risk**: medium. The commercial church-theme plugins have less of a safety net than mainstream WP plugins.
- **Effort**: 1-2 hours.

### Yoast Twitter handle mismatch
- **What**: Yoast → Social → Twitter is set to `firstumcseattle` (old branding). Facebook, Instagram, YouTube all use the newer `firstchurchseattle` handle. OG/Twitter card metadata currently points to the legacy Twitter account.
- **Fix**: Confirm which Twitter/X account is canonical, then update in Yoast → Social.
- **Effort**: 5 min.

### No caching layer
- **What**: Endurance Page Cache (Bluehost's bundled must-use plugin) is the only caching active. No CDN, no Cloudflare integration, no WP Rocket / WP Super Cache / W3 Total Cache.
- **Decide**: Is page load performance a known pain point? If so, evaluate adding a caching plugin (compatible with the church-theme stack) and/or putting Cloudflare in front.
- **Effort**: 1-2 hours to evaluate + install.

### No active contact-form plugin
- **What**: WPForms Lite has DB residue (`wpforms_version` 1.6.4.1) but folder is gone. The "Contact Form" page in the menu likely just shows a mailto or a hard-coded form in the page body. Confirm what it actually renders.
- **Decide**: If a real form is needed (spam-protected, sends to office@), install a current contact-form plugin. If a mailto suffices, clean up the orphaned WPForms options.
- **Fix-the-residue**:
  ```sh
  podman exec website_db_1 mariadb -uroot -prootpw seattle1_wp806 -e "
    DELETE FROM wpqg_options WHERE option_name LIKE 'wpforms_%';"
  ```
- **Effort**: 5 min (residue), 30 min (new form), 1 hr (full reimplementation).

### Legacy URL redirects missing
- **What**: Redirection plugin has only 3 vanity rules. There's no migration map from the Joomla-era URL space (e.g. `/index.php?option=com_content&task=view&id=...`) or the first-WP-era URL space (`blog2/...`) to current URLs. Search engines linking to old URLs hit 404s unless the production `.htaccess` does something.
- **Fix**: Audit the production `.htaccess` (don't edit — just read) for existing rewrites. Crawl old URLs against the live site to find 404 patterns. Add the high-traffic ones as Redirection rules. Use Google Search Console's coverage report to identify dead-link sources (requires connecting Site Kit first — see above).
- **Effort**: 2-4 hours.

---

## 🗑️ Disk cleanup

Run all of these on the **rsynced copy first** to confirm impact, then mirror to production if desired. Some of these only matter on disk if you're going to re-rsync down — production isn't affected by deletes in this local copy.

### `paxchristiyoga.org/` directory (149 MB)
- **What**: A complete separate WordPress site sitting inside the rsync as a sibling. Has its own `wp-config.php`, `wp-content/`, full WP install. Last touched March 2026 — appears live.
- **Action**:
  1. **Confirm with the site owner** that `paxchristiyoga.org` is hosted alongside `firstchurchseattle.org` on the same Bluehost account. (It almost certainly is — Pax Christi yoga group is associated with the church.)
  2. If yes, the rsync was likely scoped one level too high — adjust the rsync source path to `firstchurchseattle.org/` only (excluding sibling sites).
  3. In the meantime, **don't delete it from production**. Locally you can `rm -rf firstchurchseattle.org/paxchristiyoga.org/` to reclaim 149 MB.
- **Effort**: 5 min to delete locally; 15 min to fix the rsync; needs confirmation before any production action.

### Duplicate Annual Report PDFs (~100 MB)
- **What**: `wp-content/uploads/` has multiple copies of the same annual reports (Annual-Report-2024.pdf, -1, -2, -5.28; Annual-Report-2025-1, -1-1). WP auto-appends `-N` when re-uploaded over the original.
- **Action**: For each year, decide which version is canonical (probably the largest / most recent), then:
  1. Use Media Library → Replace Media (with the Enable Media Replace plugin) or update the canonical post's attached file.
  2. Find references to the duplicate URLs in posts/pages — Redirection plugin or a search-replace.
  3. Delete the duplicates from Media Library (which will also delete the files).
- **Risk**: medium — links to specific `-N` URLs may exist in old posts, social media, or external sites. Run Yoast → Tools → Bulk editor or a SQL `LIKE` search for each duplicate filename before deleting.
- **Effort**: 1 hour per year of duplicates.

### Empty Bluehost cache probes
- **What**: 8 empty `temp-write-test-68db…` files in `wp-content/` from Sep 29-30, 2025. Bluehost / Endurance cache write-test probes that didn't get cleaned up.
- **Action**:
  ```sh
  rm firstchurchseattle.org/wp-content/temp-write-test-*
  ```
- **Risk**: none.
- **Effort**: 1 min.

### Stale `.htaccess.*` variants at site root
- **What**: 5 stale files: `.htaccess.lock-*` (2 of them), `.htaccess.phpupgrader.c7edb660`, `.htaccess.phpupgrader.dde91d73`, `.htaccess.phpupgrader.initial`. Left over from PHP upgrade tooling Bluehost ran in Jan 2021 and Dec 2024.
- **Action**: Confirm the active `.htaccess` is intact, then remove the variants:
  ```sh
  cd firstchurchseattle.org && rm .htaccess.lock-* .htaccess.phpupgrader.*
  ```
- **Risk**: low if `.htaccess` itself is healthy.
- **Effort**: 5 min.

### Stale vim swap (16 MB)
- **What**: `s/.2026-05-24.html.swp` — someone edited `s/2026-05-24.html` and never closed vim cleanly.
- **Action**: `rm firstchurchseattle.org/s/.2026-05-24.html.swp`
- **Risk**: low (no in-progress edits to lose since this is a snapshot).
- **Effort**: 1 min.

### `.qidb/` (Berkeley DB package database)
- **What**: 2.7 MB of RPM package database files (`Packages`, `Basenames`, `Sha1header`, etc.) — leaked from a server backup. Has no role in serving the site.
- **Action**: `rm -rf firstchurchseattle.org/.qidb/`
- **Risk**: none.
- **Effort**: 1 min.

### Joomla-era root files & directories
- **What**: `administrator/`, `components/`, `modules/`, `libraries/`, `includes/`, `language/`, `plugins/`, `tmp/`, `logs/`, `CHANGELOG.php`, `configuration.php`, `configuration.php-dist`, `configuration.php.save`, `COPYRIGHT.php`, `CREDITS.php`, `INSTALL.php`, `LICENSE.php`, `LICENSES.php`, `default.html`, `sitemap.xml` (dated 2011), `php.ini-phpup-1398702029`, `sandbox/`, `xmlrpc/` (the dir, not `xmlrpc.php`), `media/`, `attachments/`, `blog/`, `blog2/`, `advent/`, `lent/`, `testsite/`, `cgi-bin/`.
- **⚠️ READ THIS BEFORE DELETING**: `components/com_podcast/media/` contains **119 sermon mp3s from 2009-2011, ~2.2 GB, that exist nowhere else.** They are the only copy of the early sermon audio archive.
- **Action**:
  1. **First**: archive `components/com_podcast/media/` separately (e.g. upload to Google Drive or copy to a long-term storage location). Possibly also `attachments/` (which has 2 more mp3s + 15 PDFs from 2008-2011).
  2. Confirm none of the Joomla URLs are still being hit (check production access logs).
  3. Audit `.htaccess` to ensure it doesn't try to route to any Joomla path.
  4. Then delete from production. Bulk:
     ```sh
     rm -rf administrator components modules libraries includes language tmp logs \
       sandbox advent lent testsite blog blog2 cgi-bin \
       CHANGELOG.php configuration.php* COPYRIGHT.php CREDITS.php \
       INSTALL.php LICENSE.php LICENSES.php default.html sitemap.xml \
       php.ini-phpup-* attachments media .qidb
     # keep: plugins/ (WP), xmlrpc.php (WP), wp-* files, firstchurchseattle.org-specific paths
     ```
     Note: WordPress has its own `plugins/` directory at `wp-content/plugins/` — the root-level `plugins/` directory is the Joomla one and is safe to delete. Verify before running.
- **Risk**: medium. The Joomla URL space has been dead for 10+ years but external links may exist. Run a 4-week production access-log audit first if you want to be sure.
- **Effort**: 2-4 hours including archival, audit, and verification. **~5 GB reclaimed.**

### `blog2/` (128 MB dormant WP install)
- **What**: Full older WP install with last touches around 2016. Has its own wp-config, wp-admin, wp-includes. Not linked from the current site's nav.
- **Action**: Before deleting, archive a copy. Check if any URLs under `/blog2/` still get traffic. Then `rm -rf firstchurchseattle.org/blog2/`.
- **Risk**: low-medium. Same as Joomla-era — old WP URLs may still be linked from elsewhere.
- **Effort**: 1 hour.

---

## 🗃️ Preservation (do BEFORE any cleanup above)

### Archive 2009-2011 sermon audio
- **What**: `firstchurchseattle.org/components/com_podcast/media/` — 119 mp3s, ~2.2 GB. These are the church's earliest digital sermon recordings (Rev. before-Jeremy-Smith era) and are not stored anywhere else. The current site uses YouTube for sermons, and WP `uploads/` only contains 6 modern audio files.
- **Action**:
  1. Copy the directory to a long-term storage location (church Google Drive, off-site backup, or both).
  2. Inventory what's there — pull a `ls -lh` listing, sample a few files to confirm playability.
  3. Cross-reference with anything in `attachments/` that's also unique (2 additional sermon mp3s and 15 PDFs from 2008-2011 are in there).
  4. Consider whether any of this content should be re-published as historical sermon CPT entries — could be valuable for the archive.
- **Effort**: 1-2 hours.

### Document the current state of UpdraftPlus
- **What**: Last successful backup was 2026-05-02 (~3 weeks before this snapshot). Backup destination is Google Drive. Retention is "keep last 2". The OAuth token in `wp-config` is present but may be stale.
- **Action**: Trigger a manual backup and confirm it lands in Google Drive successfully. Verify the church has access to that Google Drive account, and that the OAuth token will not expire. Confirm the retention policy is acceptable — losing all but the last 2 backups is risky.
- **Effort**: 30 min.

---

## 🏗️ Architecture improvements (longer-horizon)

### Single shared editorial account
- **What**: All 712 published items are authored by user `firstchurch` (ID 1). The actual writers — Rev. Jeremy Smith (lead preacher, 61 sermons), Rev. Wongee Joh, plus guest preachers — are not WP users, they're CTC `ctc_person` records.
- **Tradeoff**: This is fine for sermon attribution (the CTC plugin handles sermon-speaker display). It's awkward for blog posts ("Pastoral Letters" category has 17 posts — were these all from a single pastor, or several?). And it's a security concern — every editor logging in shares one account, so brute-force-detected logins via Loginizer can't be attributed.
- **Action**: Decide whether per-author accounts are worth creating. If yes, audit which historical posts should be reassigned and migrate. If no, document the convention and move on.
- **Effort**: 1 day for a full per-author migration; 15 min to document.

### No child theme
- **What**: The active `maranatha` theme is stock. No child theme exists. If any customization is ever needed, the theme update will wipe it.
- **Action**: Create an empty child theme now (one folder + `style.css` + `functions.php`) so it exists when needed. Even unused, it makes future customization safe.
- **Effort**: 30 min.

### Better backup strategy
- **What**: Monthly UpdraftPlus → Google Drive is the only backup. Files + DB. Retains last 2. No off-site database-only nightly snapshot, no point-in-time recovery.
- **Action**: Evaluate adding a weekly DB-only backup with longer retention (~12 weeks), or a managed WP backup service (BlogVault, Jetpack Backup). Especially important given the site's active publishing cadence.
- **Effort**: 1-2 hours to evaluate + set up.

---

## What's NOT on this list

The following came up during exploration but are working as intended:
- **Maranatha theme is stock with no child theme** — listed under architecture improvements above, but not urgent unless customization is needed.
- **Homepage is 4 widgets, not a normal page** — this is the theme's intended pattern. Editing through Appearance → Widgets is correct, not a workaround.
- **Sermon audio lives on YouTube, not in WP** — also working as intended via `wp-youtube-live` and embedded video URLs in the CTC sermon CPT.
- **Single CTC location record** — single-campus church; nothing to fix.
- **`temp-write-test-*` in `wp-content/`** — Endurance Page Cache uses these as write-permission probes. The empty stragglers are listed in cleanup above but it's normal for new ones to appear over time.
