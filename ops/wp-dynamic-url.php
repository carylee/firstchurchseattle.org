<?php
/**
 * Dynamic site URL — local/DDEV dev shim, loaded via auto_prepend_file
 * (see .ddev/php/dynamic-url.ini). Runs BEFORE wp-config.php on every web
 * request, so it defines WP_HOME/WP_SITEURL from the *request host* before
 * wp-config-ddev.php's `defined() || define()` can pin them to DDEV_PRIMARY_URL.
 *
 * Why: WordPress builds wp-admin / wp-login asset + form URLs from WP_SITEURL.
 * With it hardcoded to the *.ddev.site host, those URLs 404 when the site is
 * reached on any other host — e.g. the Tailscale-served
 * firstchurchseattle.<tailnet>.ts.net — so the login page renders unstyled and
 * images don't load. Deriving the URL from the host makes the same WordPress
 * serve correct URLs on whichever host you reached it.
 *
 * Lives here (not in wp-config.php) so it SURVIVES `ddev restart`, which
 * regenerates wp-config.php. Local/DDEV only (guarded on IS_DDEV_PROJECT) and
 * never deployed: ops/ ships selectively and this file isn't in ops/deploy.sh.
 * Host-derived URLs are a dev convenience, not a production pattern.
 */

if ( 'cli' !== PHP_SAPI
	&& getenv( 'IS_DDEV_PROJECT' ) === 'true'
	&& ! empty( $_SERVER['HTTP_HOST'] ) ) {

	$fc_host = preg_replace( '/[^A-Za-z0-9.:\-]/', '', (string) $_SERVER['HTTP_HOST'] );
	if ( '' !== $fc_host ) {
		$fc_https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] )
			|| ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] )
			|| ( '.ts.net' === substr( $fc_host, -7 ) ); // Tailscale serve always terminates TLS

		if ( $fc_https ) {
			$_SERVER['HTTPS'] = 'on'; // so is_ssl() agrees; avoids admin redirect loops
		}

		$fc_url = ( $fc_https ? 'https' : 'http' ) . '://' . $fc_host;
		defined( 'WP_HOME' )    || define( 'WP_HOME', $fc_url );
		defined( 'WP_SITEURL' ) || define( 'WP_SITEURL', $fc_url );
	}
}
