<?php
/**
 * Plugin Name: First Church Bulletin Publish
 * Description: Receives the weekly bulletin (web HTML + print PDF) from the bulletin editor's Publish action (../hocuspocus/apps/bulletins) via an authenticated REST route, and writes it into the /bulletin/ web server so firstchurchseattle.org/bulletin shows it. The artifacts are rendered upstream by the Cloudflare Worker's Typst container; this plugin just authenticates and stores them. See ../hocuspocus/PUBLISH-PLAN.md.
 * Version:     0.1.0
 * Author:      First Church Seattle
 *
 * The shared secret is the value the Worker sends as X-Publish-Secret. Set it on
 * prod as the constant FC_BULLETIN_PUBLISH_SECRET (in wp-config.php, NOT in the
 * repo) or the option fc_bulletin_publish_secret; the route 401s if neither is
 * set, so it fails closed.
 *
 * NOT deployed until wired into ops/deploy.sh (see CLAUDE.md) and activated on
 * prod: `cd ~/public_html && wp plugin activate firstchurch-bulletin-publish`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FCBP_VERSION       = '0.1.0';
const FCBP_OPTION_SECRET = 'fc_bulletin_publish_secret';
// Bulletins are served from this web-root subdir (sibling to wp-content), the
// same folder bulletin/index.php reads. ABSPATH has the trailing slash.
const FCBP_DIR = ABSPATH . 'bulletin/';

require_once __DIR__ . '/inc/rest.php';
