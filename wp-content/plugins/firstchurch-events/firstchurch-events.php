<?php
/**
 * Plugin Name: First Church Events
 * Description: Lean, RRULE-backed events (no recurrence cron). Stores CTC-shaped recurrence meta so RRULE + the human "when" reuse the Happenings spine's tested code, exposes events to the spine (happenings_event_items) and a /events.ics subscription feed, and supports MCP + a light editor for authoring. Transitional: the spine reads this alongside Church Theme Content until events are migrated.
 * Version:     0.1.0
 * Author:      First Church Seattle
 *
 * @package FirstChurch\Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// rlanvin/php-rrule (v2.6.0, MIT) is a RUNTIME dependency and prod runs no
// Composer, so it's vendored under lib/ and required directly (the repo's
// no-composer-on-prod pattern). Composer stays dev-only, for PHPUnit.
require_once __DIR__ . '/lib/rrule/RRuleInterface.php';
require_once __DIR__ . '/lib/rrule/RRuleTrait.php';
require_once __DIR__ . '/lib/rrule/RfcParser.php';
require_once __DIR__ . '/lib/rrule/RRule.php';
require_once __DIR__ . '/lib/rrule/RSet.php';
require_once __DIR__ . '/src/Recurrence.php';
require_once __DIR__ . '/src/Ics.php';

const FCE_CPT = 'fce_event';

// Event meta. Recurrence is stored CTC-shaped so Recurrence::toRrule() (→ RRULE
// for .ics/occurrences) and the spine's EventWhen::format() (→ "Sundays at
// 10:30 am · Sanctuary") both consume it directly. RRULE is derived at read
// time, never stored. _fce_skip_dates (EXDATE) is ours, not a CTC field.
const FCE_DTSTART = '_fce_dtstart';
const FCE_TIME    = '_fce_time';
const FCE_VENUE   = '_fce_venue';
const FCE_REGURL  = '_fce_registration_url';
const FCE_SKIP    = '_fce_skip_dates';
const FCE_RECUR   = '_fce_recurrence';      // ''|none|weekly|monthly|yearly
const FCE_WK_INT  = '_fce_weekly_interval'; // int
const FCE_WK_DAYS = '_fce_weekly_days';     // CSV: SU,TU
const FCE_MO_TYPE = '_fce_monthly_type';    // week|day
const FCE_MO_WEEK = '_fce_monthly_week';    // CSV: 2,4 | last
const FCE_END     = '_fce_end_date';        // YYYY-MM-DD

add_action( 'init', static function () {
	register_post_type( FCE_CPT, array(
		'label'        => 'Events',
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-calendar-alt',
		'supports'     => array( 'title', 'thumbnail' ),
	) );
} );

require_once __DIR__ . '/inc/event.php';
require_once __DIR__ . '/inc/source.php';
require_once __DIR__ . '/inc/mcp.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/feed.php';

register_activation_hook( __FILE__, static function () {
	flush_rewrite_rules(); // pick up the /events.ics rule
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
