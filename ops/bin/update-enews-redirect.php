<?php
/**
 * Point the Redirection rule for /enews/latest at the most recent Mailchimp
 * e-news campaign, read from the archive RSS feed.
 *
 * Idempotent: only writes when the latest issue's URL actually changed.
 *
 * Deployed to the server OUTSIDE the webroot at ~/bin/ (not web-accessible) and
 * run from cron via WP-CLI (loads WP, so fetch_feed() + $wpdb are available):
 *
 *   /usr/local/bin/php /usr/local/bin/wp --path=/home3/seattle1/public_html \
 *     eval-file /home3/seattle1/bin/update-enews-redirect.php
 *
 * See README.md (e-news redirect automation) and manifests/crontab.txt.
 */

$FEED   = 'https://us2.campaign-archive.com/feed?u=18291af87fbc7224df67d6ab8&id=24fee5f80d';
$SOURCE = '/enews/latest';   // the Redirection source path (rule #6, url->url)

if ( ! function_exists( 'fetch_feed' ) ) {
	require_once ABSPATH . WPINC . '/feed.php';
}

// Don't let SimplePie serve a stale cached copy for long.
add_filter( 'wp_feed_cache_transient_lifetime', static function () {
	return 5 * MINUTE_IN_SECONDS;
} );

$feed = fetch_feed( $FEED );
if ( is_wp_error( $feed ) ) {
	WP_CLI::error( 'Feed fetch failed: ' . $feed->get_error_message() );
}

$item = $feed->get_item( 0 );
if ( ! $item ) {
	WP_CLI::error( 'Feed returned no items.' );
}

$latest = esc_url_raw( $item->get_permalink() );
if ( ! $latest ) {
	WP_CLI::error( 'Newest feed item had no link.' );
}

global $wpdb;
$table = $wpdb->prefix . 'redirection_items';

$row = $wpdb->get_row( $wpdb->prepare(
	"SELECT id, action_data FROM {$table} WHERE url = %s AND action_type = 'url' LIMIT 1",
	$SOURCE
) );

if ( ! $row ) {
	WP_CLI::error( "No url-target Redirection rule found for {$SOURCE}." );
}

if ( $row->action_data === $latest ) {
	WP_CLI::success( "Already current: {$latest}" );
	return;
}

$old = $row->action_data;
$wpdb->update( $table, array( 'action_data' => $latest ), array( 'id' => (int) $row->id ) );
wp_cache_flush(); // refresh any persistent object cache Redirection reads through

WP_CLI::success( "Updated {$SOURCE}\n  was: {$old}\n  now: {$latest}" );
