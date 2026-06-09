<?php
/**
 * Plugin Name: First Church Happenings
 * Description: The Happenings spine — projects upcoming events + recent announcements into one ordered Happening[] feed, exposed over REST (GET /wp-json/firstchurch/v1/happenings) and MCP (firstchurch/get-happenings) for every surface to consume. Lifted out of firstchurch-carousel, which now consumes this. See ops/docs/happenings.md.
 * Version:     0.3.0
 * Author:      First Church Seattle
 *
 * NOTE: firstchurch-carousel DEPENDS on this plugin — it composes its lobby-screen
 * deck from this feed plus its own evergreen cards. Keep this active.
 *
 * @package FirstChurch\Happenings
 */

if (!defined('ABSPATH')) {
    exit;
}

// Production loads the pure core via explicit requires (no Composer on prod);
// the test suite loads the same classes through Composer's PSR-4 autoloader.
require_once __DIR__ . '/src/Id.php';
require_once __DIR__ . '/src/Item.php';
require_once __DIR__ . '/src/Layout.php';
require_once __DIR__ . '/src/Text.php';
require_once __DIR__ . '/src/EventWhen.php';
require_once __DIR__ . '/src/CardView.php';
require_once __DIR__ . '/src/Featured.php';

const HAPPENINGS_VERSION = '0.1.0';

// Default windows for the spine feed (tunable per request).
const HAPPENINGS_DEFAULT_WEEKS = 8;  // upcoming-events look-ahead
const HAPPENINGS_DEFAULT_DAYS  = 30; // recent-announcements look-back

// Announcements live as posts in this category (matches the MCP mu-plugin + carousel).
const HAPPENINGS_ANNOUNCE_SLUG = 'announcements';

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/sources.php';
require_once __DIR__ . '/inc/resolve.php';
require_once __DIR__ . '/inc/rest.php';
require_once __DIR__ . '/inc/mcp.php';
