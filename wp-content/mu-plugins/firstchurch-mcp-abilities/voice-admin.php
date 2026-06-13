<?php
/**
 * Tools → Church Voice — view and edit the house voice that drives every AI
 * draft, rewrite, and the guide-church-voice MCP resource.
 *
 * Everyone with edit_posts can VIEW the effective guide (useful reference);
 * only administrators can edit or reset it. Saving writes the FCMCP_VOICE_OPTION
 * option that fc_church_voice() reads; resetting deletes it (falls back to the
 * in-code default). A Preview runs a sample rewrite with the *unsaved* text so an
 * editor sees the effect before committing.
 *
 * Loaded by ../firstchurch-mcp-abilities.php (after voice.php).
 *
 * @package FirstChurch\Mcp
 */

defined( 'ABSPATH' ) || exit;

/** Option holding "who/when last edited" metadata for the voice guide. */
const FCMCP_VOICE_META = 'fc_church_voice_updated';

add_action(
	'admin_menu',
	static function (): void {
		add_management_page(
			'Church Voice',
			'Church Voice',
			'edit_posts', // editors and up can VIEW
			'fc-church-voice',
			'fcmcp_voice_admin_render'
		);
	}
);

function fcmcp_voice_admin_render(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.' ) );
	}
	$can_edit = current_user_can( 'manage_options' );
	$notice   = '';
	$preview  = null;

	if ( $can_edit && isset( $_POST['fc_voice_action'] ) && check_admin_referer( 'fc_voice_save' ) ) {
		// Trusted admin input: a system prompt. Store raw (no tag-stripping — the
		// guide legitimately mentions tokens like <p>); it is never rendered as HTML.
		$submitted = isset( $_POST['fc_voice_text'] ) ? (string) wp_unslash( $_POST['fc_voice_text'] ) : '';
		$action    = sanitize_key( (string) $_POST['fc_voice_action'] );

		if ( 'save' === $action ) {
			update_option( FCMCP_VOICE_OPTION, $submitted );
			update_option( FCMCP_VOICE_META, array( 'time' => time(), 'user' => get_current_user_id() ) );
			$notice = 'Saved. Every AI draft and rewrite now uses this voice.';
		} elseif ( 'reset' === $action ) {
			delete_option( FCMCP_VOICE_OPTION );
			delete_option( FCMCP_VOICE_META );
			$notice = 'Reset to the built-in default.';
		} elseif ( 'preview' === $action ) {
			$sample = 'Members must RSVP by Friday or they will not be allowed to attend the workshop.';
			$out    = fcmcp_voice_generate(
				"Rewrite this in the house voice. Return only the rewritten passage.\n\n{$sample}",
				null,
				array( 'system' => $submitted )
			);
			$preview = array(
				'in'  => $sample,
				'out' => is_wp_error( $out ) ? ( 'Preview failed: ' . $out->get_error_message() ) : (string) $out,
			);
		}
	}

	$current   = (string) get_option( FCMCP_VOICE_OPTION, '' );
	$is_custom = '' !== trim( $current );
	$text      = ( $preview && isset( $_POST['fc_voice_text'] ) )
		? (string) wp_unslash( $_POST['fc_voice_text'] )            // keep unsaved edits visible after Preview
		: ( $is_custom ? $current : fcmcp_church_voice_default() );
	$meta      = (array) get_option( FCMCP_VOICE_META, array() );

	echo '<div class="wrap">';
	echo '<h1>Church Voice</h1>';
	echo '<p>The house voice fed to every AI draft, the editor&#8217;s &#8220;Rewrite in church voice&#8221; button, and the <code>guide-church-voice</code> MCP resource. ';
	echo $is_custom
		? '<strong>Status: customized.</strong>'
		: '<strong>Status: built-in default</strong> (no override saved).';
	echo '</p>';

	if ( $is_custom && ! empty( $meta['time'] ) ) {
		$who = ! empty( $meta['user'] ) ? get_userdata( (int) $meta['user'] ) : null;
		echo '<p class="description">Last edited ' . esc_html( human_time_diff( (int) $meta['time'] ) ) . ' ago'
			. ( $who ? ' by ' . esc_html( $who->display_name ) : '' ) . '.</p>';
	}

	if ( '' !== $notice ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	if ( ! $can_edit ) {
		echo '<div class="notice notice-info"><p>You can view the current voice guide here. Editing it requires an administrator.</p></div>';
	}

	if ( $preview ) {
		echo '<h2>Preview</h2><table class="widefat striped" style="max-width:60em"><tbody>';
		echo '<tr><th style="width:6em">Before</th><td>' . esc_html( $preview['in'] ) . '</td></tr>';
		echo '<tr><th>After</th><td><strong>' . esc_html( $preview['out'] ) . '</strong></td></tr>';
		echo '</tbody></table><p class="description">Preview uses the unsaved text above and does not save it.</p>';
	}

	echo '<form method="post">';
	wp_nonce_field( 'fc_voice_save' );
	printf(
		'<textarea name="fc_voice_text" rows="28" style="width:100%%;max-width:60em;font-family:ui-monospace,Menlo,monospace;font-size:13px" %s>%s</textarea>',
		$can_edit ? '' : 'readonly',
		esc_textarea( $text )
	);
	if ( $can_edit ) {
		echo '<p class="submit">';
		echo '<button type="submit" name="fc_voice_action" value="save" class="button button-primary">Save voice</button> ';
		echo '<button type="submit" name="fc_voice_action" value="preview" class="button">Preview a rewrite</button> ';
		echo '<button type="submit" name="fc_voice_action" value="reset" class="button button-link-delete" onclick="return confirm(\'Reset to the built-in default? Your customizations will be discarded.\')">Reset to default</button>';
		echo '</p>';
	}
	echo '</form>';
	echo '</div>';
}
