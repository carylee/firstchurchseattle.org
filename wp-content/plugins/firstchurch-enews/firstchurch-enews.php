<?php
/**
 * Plugin Name: First Church E-News
 * Description: The weekly e-news as a Happenings surface. An `enews_issue` is a thin, block-editor authoring object that composes the firstchurch-happenings spine — a featured event, this week's events, recent announcements — plus evergreen recurring items and a fixed footer. New issues open pre-filled from the spine (not duplicated from last week), so staff write the editorial bits and curate rather than re-key content. See ops/docs/enews-spine.md.
 * Version:     0.1.0
 * Author:      First Church Seattle
 *
 * Pairs with firstchurch-happenings (the spine): the composing blocks are the
 * theme's `firstchurch/happenings` dynamic block, which renders server-side from
 * the spine. This plugin owns only the issue container + its block template.
 *
 * @package FirstChurch\ENews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FCEN_CPT = 'enews_issue';

// Issue-level editorial meta (Bucket C in ops/docs/enews-spine.md §3): the only
// hand-authored fields. The body — Pastoral Message + composing blocks — lives in
// post_content; these are the email envelope around it.
const FCEN_SUBJECT_KEY = '_enews_subject';      // Mailchimp subject line
const FCEN_PREVIEW_KEY  = '_enews_preview';     // preview / tagline text
const FCEN_DATE_KEY     = '_enews_date';        // YYYY-MM-DD send date / window anchor

// Production loads the pure render core via explicit require (no Composer on
// prod); the test suite loads it through Composer's PSR-4 autoloader.
require_once __DIR__ . '/src/Email.php';
require_once __DIR__ . '/src/Mailchimp.php';

require_once __DIR__ . '/inc/cpt.php';
require_once __DIR__ . '/inc/meta.php';
require_once __DIR__ . '/inc/render.php';

// The CPT carries a custom rewrite slug (/enews/<slug>/ for preview + a web
// archive), so flush rewrites on activation. On deploy, also run once:
//   ssh firstchurch 'cd ~/public_html && wp rewrite flush'
register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
