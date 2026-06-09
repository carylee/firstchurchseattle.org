# Events migration: Church Theme Content → firstchurch-events

**Status (updated 2026-06-08):** steps 1–4 **executed**. `firstchurch-events` is active; the live
set was imported into `fce_event` (every imported post carries `_fce_ctc_origin`) and the CTC
originals were unpublished (cut-over). The spine reads CTC **and** `fce_event` (read-both), and the
standalone `/upcoming-events/` + `/events-calendar/` pages — formerly CTC-only parent-theme
templates that went empty post-cut-over — are now **spine-backed** child templates (see
`happenings.md` §5). To give the projected event links a real destination, `fce_event` was also
made publicly viewable: a lean single page at `/event/<slug>/` that itself projects the spine
(`happenings_item_by_id`). This added a surface beyond the original projection-only plan — a
deliberate evolution, kept in spirit by reading the same Happening contract, not post meta.
**Remaining:** step 5 (decommission CTC), which is now unblocked re: those pages since they no
longer query `ctc_event` for the live set.

**Why:** CTC carries a tiny live load (~10 upcoming events, 8 recurrence rules; sermons dead) for a
heavy, paid (`church-content-pro`) dependency — see the `ctc-barely-used` finding. We keep CTC's
data model spirit (the recurrence shape) but own the backend. The spine isolated events behind one
function, so this is contained.

**Guiding rule:** migrate the **live** set (upcoming + recurring), not the ~300 dead historical
events. Leave history in CTC (or export later); re-seed what's actually used.

## Why the import is nearly a meta copy

`fce_event` stores recurrence in the **same shape as CTC** (so `Recurrence::toRrule()` +
`EventWhen::format()` work directly), so importing is a near 1:1 meta copy:

| CTC meta | fce meta |
|---|---|
| `_ctc_event_start_date` | `_fce_dtstart` |
| `_ctc_event_start_time` | `_fce_time` |
| `_ctc_event_venue` | `_fce_venue` |
| `_ctc_event_registration_url` | `_fce_registration_url` |
| `_ctc_event_recurrence` | `_fce_recurrence` |
| `_ctc_event_recurrence_weekly_interval` | `_fce_weekly_interval` |
| `_ctc_event_recurrence_weekly_day` | `_fce_weekly_days` |
| `_ctc_event_recurrence_monthly_type` | `_fce_monthly_type` |
| `_ctc_event_recurrence_monthly_week` | `_fce_monthly_week` |
| `_ctc_event_recurrence_end_date` | `_fce_end_date` |

(`_fce_skip_dates` has no CTC equivalent — it stays empty.)

## Runbook

> ⚠️ All prod `wp` runs from `~/public_html` (`ssh firstchurch 'cd ~/public_html && wp …'`).

1. **Deploy + activate** (post-merge of this PR):
   `ssh firstchurch 'cd ~/public_html && wp plugin activate firstchurch-events'`.
   The spine now merges CTC + fce; with no `fce_event` posts yet, the feed is unchanged.
2. **Dry-run import** the live set into `fce_event` (recurring events + one-offs in the next ~26
   weeks). Run as a `wp eval-file` of the import script below; it prints what it would create.
   Tune the window/filter, then run for real.
3. **Verify parity:** compare `fce_event_items(8)` to the events that were in
   `happenings_event_items(8)` before — same titles, same next dates, same "when". `/events.ics`
   serves them.
4. **Cut over each migrated event:** unpublish (or trash) the **CTC original** so it doesn't
   double-show (the spine doesn't dedup — one source per event). Do this per-event as you confirm
   its fce twin renders.
5. **Decommission:** once the live set is fully on fce and verified, deactivate the CTC events
   capability (`church-content-pro` provides recurrence; `church-theme-content` the CPT). Keep them
   *installed but inactive* for a cycle as rollback insurance before removing.
6. **Sermons:** separate, simpler call — retire the dead `ctc_sermon` archive to the YouTube
   channel/playlists (already the real archive) rather than rebuilding. Not part of this migration.

## Import script (sketch — review before running)

```php
// wp eval-file. Imports upcoming/recurring CTC events into fce_event (idempotent-ish:
// skips a CTC event that already has an fce twin tagged with its origin id).
$map = [
  '_ctc_event_start_date'=>'_fce_dtstart', '_ctc_event_start_time'=>'_fce_time',
  '_ctc_event_venue'=>'_fce_venue', '_ctc_event_registration_url'=>'_fce_registration_url',
  '_ctc_event_recurrence'=>'_fce_recurrence',
  '_ctc_event_recurrence_weekly_interval'=>'_fce_weekly_interval',
  '_ctc_event_recurrence_weekly_day'=>'_fce_weekly_days',
  '_ctc_event_recurrence_monthly_type'=>'_fce_monthly_type',
  '_ctc_event_recurrence_monthly_week'=>'_fce_monthly_week',
  '_ctc_event_recurrence_end_date'=>'_fce_end_date',
];
$today = current_time('Y-m-d');
$ctc = get_posts(['post_type'=>'ctc_event','post_status'=>'publish','numberposts'=>-1,
  'meta_query'=>[['key'=>'_ctc_event_start_date','value'=>$today,'compare'=>'>=','type'=>'DATE']]]);
foreach ($ctc as $e) {
  if (get_posts(['post_type'=>'fce_event','meta_key'=>'_fce_ctc_origin','meta_value'=>$e->ID,'fields'=>'ids'])) continue;
  $id = wp_insert_post(['post_type'=>'fce_event','post_status'=>'publish','post_title'=>$e->post_title]);
  update_post_meta($id,'_fce_ctc_origin',$e->ID); // provenance, for re-runs + cut-over
  foreach ($map as $from=>$to) update_post_meta($id,$to,(string)get_post_meta($e->ID,$from,true));
  // featured image:
  if (has_post_thumbnail($e)) set_post_thumbnail($id, get_post_thumbnail_id($e));
  echo "imported {$e->ID} → {$id}: {$e->post_title}\n";
}
```

Add `_fce_ctc_origin` to the plugin's meta consts if we keep provenance. Cut-over step 4 then:
for each fce_event with `_fce_ctc_origin`, `wp post update <origin> --post_status=draft` once verified.

## Rollback

fce events are *additive* to the feed, so backing out is safe at any point: trash the imported
`fce_event` posts and re-publish any CTC originals you unpublished. Until step 5, CTC is untouched.
