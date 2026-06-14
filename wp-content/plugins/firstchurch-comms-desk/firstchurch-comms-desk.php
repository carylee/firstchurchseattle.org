<?php
/**
 * Plugin Name: First Church Comms Desk
 * Description: A human-centered workspace for the Communications Coordinator — a "Comms Desk" landing page (a weekly worklist cockpit + in-place review of AI drafts), a scoped human comms_editor role, and a decluttered admin. Sits on top of the intake/voice engine in firstchurch-mcp-abilities + firstchurch-breeze-forms; it surfaces existing data (review-queue, content-health, intake), it does not duplicate it.
 * Version:     0.7.0
 * Author:      First Church Seattle
 *
 * @package FirstChurch\CommsDesk
 */

defined( 'ABSPATH' ) || exit;

/** The human communications role (distinct from the mcp_editor service account). */
const FCCD_ROLE = 'comms_editor';

/** Comms Desk admin page slug. */
const FCCD_SLUG = 'fc-comms-desk';

require_once __DIR__ . '/inc/cards.php';
require_once __DIR__ . '/inc/role.php';
require_once __DIR__ . '/inc/desk.php';
require_once __DIR__ . '/inc/review-actions.php';
require_once __DIR__ . '/inc/card-editor.php';
require_once __DIR__ . '/inc/chrome.php';

register_activation_hook( __FILE__, 'fccd_ensure_role' );
