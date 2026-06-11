<?php
/**
 * Deprecated — the lean MCP authoring surface (create-event-lean /
 * update-event-lean) has been folded into the primary event abilities
 * (firstchurch/create-event / firstchurch/update-event), which now target
 * fce_event directly (see mu-plugins/firstchurch-mcp-abilities/events.php).
 *
 * This file is kept as a zero-load so 'inc/mcp.php' require_once in
 * firstchurch-events.php does not fatal.
 *
 * @package FirstChurch\Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
