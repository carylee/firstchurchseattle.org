<?php
/**
 * Pure card logic for the Comms Desk — the testable seams behind the worklist.
 *
 * These functions take plain arrays/scalars and return values or escaped HTML;
 * they touch only a tiny set of WP primitives (the esc_* family). The
 * WordPress-coupled glue (WP_Query, get_post_meta, REST, echo) lives in
 * desk.php / the REST handlers and calls into here. Keeping the logic pure is
 * what lets the suite exercise it without standing up WordPress.
 *
 * @package FirstChurch\CommsDesk
 */

defined( 'ABSPATH' ) || exit;
