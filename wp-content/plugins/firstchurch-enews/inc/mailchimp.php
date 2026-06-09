<?php
/**
 * Push a rendered issue to Mailchimp as a DRAFT campaign (Marketing API v3).
 *
 * Boundary on purpose: this only ever creates/updates a *draft* — it never
 * sends. A staffer reviews the draft in Mailchimp and sends it there, so the
 * irreversible step stays a human action in Mailchimp's own UI. Re-pushing the
 * same issue updates its existing draft (the campaign id is stored on the post)
 * rather than piling up duplicates.
 *
 * Credentials live in wp-config constants (never committed):
 *   FCEN_MAILCHIMP_API_KEY      e.g. "abc…-us2" (the -us2 suffix is the datacenter)
 *   FCEN_MAILCHIMP_AUDIENCE_ID  the audience/list id (Audience → Settings → Unique id)
 *   FCEN_MAILCHIMP_FROM_NAME    optional (default "First Church Seattle")
 *   FCEN_MAILCHIMP_REPLY_TO     optional (default comms@firstchurchseattle.org)
 *
 * The payload shaping + error parsing is the pure src/Mailchimp.php; this is the
 * WordPress glue (HTTP, credentials, the admin action, notices, the button).
 *
 * @package FirstChurch\ENews
 */

use FirstChurch\ENews\Mailchimp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Where the created campaign is remembered on the issue, so a re-push updates it.
const FCEN_MC_CAMPAIGN_KEY = '_enews_mailchimp_campaign_id';
const FCEN_MC_WEBID_KEY    = '_enews_mailchimp_web_id';

/**
 * Resolved Mailchimp config, or null when the required constants are unset.
 *
 * @return array{key:string,dc:string,list:string,from:string,reply:string}|null
 */
function fcen_mailchimp_config(): ?array {
	$key  = defined( 'FCEN_MAILCHIMP_API_KEY' ) ? (string) FCEN_MAILCHIMP_API_KEY : '';
	$list = defined( 'FCEN_MAILCHIMP_AUDIENCE_ID' ) ? (string) FCEN_MAILCHIMP_AUDIENCE_ID : '';
	$dc   = Mailchimp::datacenter( $key );
	if ( '' === $key || '' === $list || '' === $dc ) {
		return null;
	}
	return array(
		'key'   => $key,
		'dc'    => $dc,
		'list'  => $list,
		'from'  => defined( 'FCEN_MAILCHIMP_FROM_NAME' ) ? (string) FCEN_MAILCHIMP_FROM_NAME : 'First Church Seattle',
		'reply' => defined( 'FCEN_MAILCHIMP_REPLY_TO' ) ? (string) FCEN_MAILCHIMP_REPLY_TO : 'comms@firstchurchseattle.org',
	);
}

/**
 * One Marketing-API request. Basic auth (any user + the key as password).
 *
 * @param array{key:string,dc:string,list:string,from:string,reply:string} $config
 * @param array<string,mixed>|null                                         $body
 * @return array{status:int,body:mixed,raw:string,error:?string}
 */
function fcen_mailchimp_request( array $config, string $method, string $path, ?array $body = null ): array {
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( 'fcen:' . $config['key'] ),
			'Content-Type'  => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$resp = wp_remote_request( Mailchimp::apiBase( $config['dc'] ) . $path, $args );
	if ( is_wp_error( $resp ) ) {
		return array( 'status' => 0, 'body' => null, 'raw' => '', 'error' => $resp->get_error_message() );
	}

	$raw = (string) wp_remote_retrieve_body( $resp );
	return array(
		'status' => (int) wp_remote_retrieve_response_code( $resp ),
		'body'   => json_decode( $raw, true ),
		'raw'    => $raw,
		'error'  => null,
	);
}

/** True for a 2xx with no transport error. @param array{status:int,error:?string} $res */
function fcen_mailchimp_ok( array $res ): bool {
	return null === $res['error'] && $res['status'] >= 200 && $res['status'] < 300;
}

/** A human error line from a request result. @param array{error:?string,raw:string} $res */
function fcen_mailchimp_error( array $res ): string {
	return $res['error'] ?? Mailchimp::errorMessage( (string) $res['raw'] );
}

/**
 * Create or update the issue's Mailchimp draft and push the rendered HTML.
 *
 * @return array{ok:bool,message:string,edit_url?:?string}
 */
function fcen_push_to_mailchimp( int $post_id ): array {
	$config = fcen_mailchimp_config();
	if ( null === $config ) {
		return array( 'ok' => false, 'message' => 'Mailchimp is not configured (set FCEN_MAILCHIMP_API_KEY and FCEN_MAILCHIMP_AUDIENCE_ID).' );
	}

	$post = get_post( $post_id );
	if ( ! $post || FCEN_CPT !== $post->post_type ) {
		return array( 'ok' => false, 'message' => 'Not an e-news issue.' );
	}

	$subject = (string) get_post_meta( $post_id, FCEN_SUBJECT_KEY, true );
	$env     = array(
		'subject'   => '' !== $subject ? $subject : get_the_title( $post ),
		'preview'   => (string) get_post_meta( $post_id, FCEN_PREVIEW_KEY, true ),
		'title'     => 'E-News: ' . get_the_title( $post ),
		'from_name' => $config['from'],
		'reply_to'  => $config['reply'],
	);

	$campaign_id = (string) get_post_meta( $post_id, FCEN_MC_CAMPAIGN_KEY, true );

	// Update the existing draft's settings; if it's gone (404) or no longer
	// editable (e.g. already sent — 400), fall through to creating a fresh one.
	if ( '' !== $campaign_id ) {
		$res = fcen_mailchimp_request( $config, 'PATCH', "/campaigns/{$campaign_id}", array( 'settings' => Mailchimp::settings( $env ) ) );
		if ( 404 === $res['status'] || 400 === $res['status'] ) {
			$campaign_id = '';
		} elseif ( ! fcen_mailchimp_ok( $res ) ) {
			return array( 'ok' => false, 'message' => fcen_mailchimp_error( $res ) );
		}
	}

	if ( '' === $campaign_id ) {
		$res = fcen_mailchimp_request( $config, 'POST', '/campaigns', Mailchimp::campaignPayload( $config['list'], $env ) );
		if ( ! fcen_mailchimp_ok( $res ) ) {
			return array( 'ok' => false, 'message' => fcen_mailchimp_error( $res ) );
		}
		$campaign_id = (string) ( $res['body']['id'] ?? '' );
		if ( '' === $campaign_id ) {
			return array( 'ok' => false, 'message' => 'Mailchimp did not return a campaign id.' );
		}
		update_post_meta( $post_id, FCEN_MC_CAMPAIGN_KEY, $campaign_id );
		update_post_meta( $post_id, FCEN_MC_WEBID_KEY, (int) ( $res['body']['web_id'] ?? 0 ) );
	}

	// Push the rendered issue as the campaign's HTML content.
	$res = fcen_mailchimp_request( $config, 'PUT', "/campaigns/{$campaign_id}/content", array( 'html' => fcen_render_email( $post_id ) ) );
	if ( ! fcen_mailchimp_ok( $res ) ) {
		return array( 'ok' => false, 'message' => fcen_mailchimp_error( $res ) );
	}

	$web_id = (int) get_post_meta( $post_id, FCEN_MC_WEBID_KEY, true );
	return array(
		'ok'       => true,
		'message'  => 'Draft updated in Mailchimp. Review and send it there.',
		'edit_url' => $web_id ? Mailchimp::editUrl( $config['dc'], $web_id ) : null,
	);
}

/* ---- Admin action + notice ------------------------------------------------ */

add_action( 'admin_post_fcen_mailchimp_push', 'fcen_mailchimp_push_handler' );

function fcen_mailchimp_push_handler(): void {
	$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to push this issue.', 'firstchurch-enews' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'fcen_mailchimp_push_' . $post_id );

	$result = fcen_push_to_mailchimp( $post_id );
	set_transient( 'fcen_mc_notice_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

	wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
	exit;
}

add_action( 'admin_notices', 'fcen_mailchimp_admin_notice' );

function fcen_mailchimp_admin_notice(): void {
	$screen = get_current_screen();
	if ( ! $screen || FCEN_CPT !== $screen->post_type ) {
		return;
	}
	$key    = 'fcen_mc_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( ! is_array( $notice ) ) {
		return;
	}
	delete_transient( $key );

	$class = ! empty( $notice['ok'] ) ? 'notice-success' : 'notice-error';
	$msg   = esc_html( (string) ( $notice['message'] ?? '' ) );
	if ( ! empty( $notice['edit_url'] ) ) {
		$msg .= ' <a href="' . esc_url( (string) $notice['edit_url'] ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Open in Mailchimp', 'firstchurch-enews' ) . '</a>';
	}
	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
}

/* ---- The "Push to Mailchimp" control (rendered in the settings meta box) --- */

function fcen_mailchimp_button( WP_Post $post ): void {
	if ( 'auto-draft' === get_post_status( $post ) ) {
		return; // nothing to push from a blank new issue.
	}

	if ( null === fcen_mailchimp_config() ) {
		echo '<p style="color:#646970;font-size:12px;margin:10px 0 0;">'
			. esc_html__( 'Set FCEN_MAILCHIMP_API_KEY + FCEN_MAILCHIMP_AUDIENCE_ID in wp-config to enable “Push to Mailchimp”.', 'firstchurch-enews' )
			. '</p>';
		return;
	}

	$pushed = '' !== (string) get_post_meta( $post->ID, FCEN_MC_CAMPAIGN_KEY, true );
	$label  = $pushed ? __( 'Update Mailchimp draft', 'firstchurch-enews' ) : __( 'Push to Mailchimp draft', 'firstchurch-enews' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:10px 0 0;">
		<input type="hidden" name="action" value="fcen_mailchimp_push">
		<input type="hidden" name="post" value="<?php echo esc_attr( (string) $post->ID ); ?>">
		<?php wp_nonce_field( 'fcen_mailchimp_push_' . $post->ID ); ?>
		<button type="submit" class="button button-primary"><?php echo esc_html( $label ); ?></button>
		<span style="display:block;color:#646970;font-size:12px;margin-top:4px;">
			<?php esc_html_e( 'Creates/updates a DRAFT in Mailchimp — review & send it there.', 'firstchurch-enews' ); ?>
		</span>
	</form>
	<?php
}
