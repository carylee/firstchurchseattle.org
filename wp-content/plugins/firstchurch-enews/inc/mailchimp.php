<?php
/**
 * Push a rendered issue to Mailchimp as a DRAFT campaign, via the official
 * `mailchimp/marketing` SDK (vendored + PHP-Scoped into FirstChurch\ENews\Vendor
 * at build time — see ops/docs/composer-on-prod.md and build.sh).
 *
 * Boundary on purpose: this only ever creates/updates a *draft* — it never
 * sends. A staffer reviews the draft in Mailchimp and sends it there. Re-pushing
 * an issue updates its existing draft (the campaign id is stored on the post).
 *
 * Credentials live in wp-config constants (never committed):
 *   FCEN_MAILCHIMP_API_KEY      e.g. "abc…-us2" (the -us2 suffix is the datacenter)
 *   FCEN_MAILCHIMP_AUDIENCE_ID  the audience/list id (Audience → Settings → Unique id)
 *   FCEN_MAILCHIMP_FROM_NAME    optional (default "First Church Seattle")
 *   FCEN_MAILCHIMP_REPLY_TO     optional (default comms@firstchurchseattle.org)
 *
 * The payload shaping + error parsing stays in the pure src/Mailchimp.php (still
 * unit-tested) — it just feeds the SDK now instead of wp_remote_request. The SDK
 * owns transport, retries, and auth.
 *
 * @package FirstChurch\ENews
 */

use FirstChurch\ENews\Mailchimp;
// Real SDK namespace in source; PHP-Scoper rewrites these to
// FirstChurch\ENews\Vendor\MailchimpMarketing\… in the shipped build.
use MailchimpMarketing\ApiClient;

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
 * A configured SDK client for the account's datacenter.
 *
 * @param array{key:string,dc:string,list:string,from:string,reply:string} $config
 */
function fcen_mailchimp_client( array $config ): ApiClient {
	$client = new ApiClient();
	$client->setConfig( array( 'apiKey' => $config['key'], 'server' => $config['dc'] ) );
	return $client;
}

/** A human error line from an SDK exception (its response body, else its message). */
function fcen_mailchimp_exception_message( \Throwable $e ): string {
	$raw = method_exists( $e, 'getResponseBody' ) ? (string) $e->getResponseBody() : '';
	return '' !== $raw ? Mailchimp::errorMessage( $raw ) : $e->getMessage();
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

	$client      = fcen_mailchimp_client( $config );
	$campaign_id = (string) get_post_meta( $post_id, FCEN_MC_CAMPAIGN_KEY, true );

	try {
		// Update the existing draft's settings; if it's gone (404) or no longer
		// editable (already sent — 400), fall through to creating a fresh one.
		if ( '' !== $campaign_id ) {
			try {
				$client->campaigns->update( $campaign_id, array( 'settings' => Mailchimp::settings( $env ) ) );
			} catch ( \Throwable $e ) {
				if ( ! in_array( (int) $e->getCode(), array( 400, 404 ), true ) ) {
					throw $e;
				}
				$campaign_id = '';
			}
		}

		if ( '' === $campaign_id ) {
			$created     = $client->campaigns->create( Mailchimp::campaignPayload( $config['list'], $env ) );
			$campaign_id = (string) ( $created->id ?? '' );
			if ( '' === $campaign_id ) {
				return array( 'ok' => false, 'message' => 'Mailchimp did not return a campaign id.' );
			}
			update_post_meta( $post_id, FCEN_MC_CAMPAIGN_KEY, $campaign_id );
			update_post_meta( $post_id, FCEN_MC_WEBID_KEY, (int) ( $created->web_id ?? 0 ) );
		}

		// Push the rendered issue as the campaign's HTML content.
		$client->campaigns->setContent( $campaign_id, array( 'html' => fcen_render_email( $post_id ) ) );
	} catch ( \Throwable $e ) {
		return array( 'ok' => false, 'message' => fcen_mailchimp_exception_message( $e ) );
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
