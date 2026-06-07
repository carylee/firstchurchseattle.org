<?php
/**
 * Plugin Name: First Church Carousel
 * Description: Makes the website the source of truth for the pre-/post-worship announcement carousel. Registers the carousel_card CPT (evergreen cards), and exposes a resolved, ordered Announcement[] feed — assembled from evergreen cards + upcoming events + recent announcements — over REST and MCP for the slides pipeline (../hocuspocus/apps/slides). See ops/docs/carousel-source-of-truth.md.
 * Version:     0.5.0
 * Author:      First Church Seattle
 *
 * Phase 2 (per the design doc): the CPT + the resolver/feed. The feed auto-
 * assembles a sensible default deck from live content; the curation screen
 * (pick/order/decorate, stored as references+overrides) is a later phase. Until
 * then, evergreen ordering comes from each card's menu_order (Page Attributes).
 *
 * DEPENDS ON firstchurch-happenings: the events + announcements sources and the
 * shared item/text/layout/when helpers live in that plugin (the spine). The
 * carousel composes its evergreen cards on top of that feed. Keep it active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FCCAR_VERSION = '0.5.0';

// Card post type + its meta keys (featured image = background photo; the post
// title = the card title; menu_order = sequence within the evergreen run).
const FCCAR_CPT          = 'carousel_card';
const FCCAR_META_LAYOUT  = '_fccar_layout';
const FCCAR_META_BODY    = '_fccar_body';
const FCCAR_META_PROMPT  = '_fccar_prompt';
const FCCAR_META_DETAILS = '_fccar_details';
const FCCAR_META_QR      = '_fccar_qr_url';
const FCCAR_META_BGCOLOR = '_fccar_bg_color';
const FCCAR_META_PRESVC  = '_fccar_preservice';

// The six slide-card layouts (mirrors apps/slides/app/src/schema.ts CARD_LAYOUTS).
const FCCAR_LAYOUTS = array( 'intro', 'divider', 'qr_callout', 'event', 'info', 'feature' );

// Default windows for the auto-assembled deck (tunable per request).
const FCCAR_DEFAULT_WEEKS = 8;  // upcoming events look-ahead
const FCCAR_DEFAULT_DAYS  = 30; // recent announcements look-back

// Announcements live as posts in this category (matches the MCP mu-plugin).
const FCCAR_ANNOUNCE_SLUG = 'announcements';

require_once __DIR__ . '/inc/cpt.php';
require_once __DIR__ . '/inc/resolve.php';
require_once __DIR__ . '/inc/deck.php';
require_once __DIR__ . '/inc/rest.php';
require_once __DIR__ . '/inc/rest-card.php';
require_once __DIR__ . '/inc/mcp.php';
// Live web carousel: the /carousel/ kiosk page (the website renders the deck,
// not just the feed). See design doc Phase 5.
require_once __DIR__ . '/inc/render.php';
// Loaded unconditionally: its admin page/asset hooks self-gate, but the deck
// save route registers on rest_api_init (not an is_admin() context).
require_once __DIR__ . '/inc/admin-curate.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/inc/seed.php';
}

register_activation_hook( __FILE__, static function () {
	fccar_register_cpt();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
