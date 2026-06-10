<?php
/**
 * Issue-level editorial meta — the email envelope around the composed body:
 * the Mailchimp subject line, the preview/tagline text, and the send date.
 * These are Bucket C (enews-spine.md §3) — the only hand-authored fields besides
 * the post body. Registered in REST (so a future render/push step can read them)
 * and edited through a small classic meta box on the issue screen.
 *
 * @package FirstChurch\ENews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'fcen_register_meta' );

function fcen_register_meta(): void {
	$can_edit = static function ( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_post', $object_id );
	};

	$string_meta = static function ( string $key, callable $auth ): void {
		register_post_meta(
			FCEN_CPT,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
				'default'           => '',
			)
		);
	};

	$string_meta( FCEN_SUBJECT_KEY, $can_edit );
	$string_meta( FCEN_PREVIEW_KEY, $can_edit );

	register_post_meta(
		FCEN_CPT,
		FCEN_DATE_KEY,
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'fcen_sanitize_date',
			'auth_callback'     => $can_edit,
			'default'           => '',
		)
	);
}

/** A YYYY-MM-DD date, or '' to clear. Mirrors the announcement expiry sanitizer. */
function fcen_sanitize_date( $value ): string {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$d = DateTime::createFromFormat( 'Y-m-d', $value );
	return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : '';
}

add_action( 'add_meta_boxes', static function () {
	add_meta_box(
		'fcen-settings',
		__( 'E-News Settings', 'firstchurch-enews' ),
		'fcen_settings_meta_box_render',
		FCEN_CPT,
		'side',
		'high'
	);
} );

/**
 * Render the settings box: subject, preview text, send date.
 *
 * @param WP_Post $post Current issue.
 */
function fcen_settings_meta_box_render( $post ): void {
	wp_nonce_field( 'fcen_settings_save', 'fcen_settings_nonce' );
	$subject = (string) get_post_meta( $post->ID, FCEN_SUBJECT_KEY, true );
	$preview = (string) get_post_meta( $post->ID, FCEN_PREVIEW_KEY, true );
	$date    = (string) get_post_meta( $post->ID, FCEN_DATE_KEY, true );
	?>
	<p>
		<label for="fcen_subject_field" style="display:block;font-weight:600;margin-bottom:4px;">
			<?php esc_html_e( 'Subject line', 'firstchurch-enews' ); ?>
		</label>
		<input type="text" id="fcen_subject_field" name="fcen_subject_field"
		       value="<?php echo esc_attr( $subject ); ?>" class="widefat"
		       placeholder="<?php esc_attr_e( 'First Church Weekly News', 'firstchurch-enews' ); ?>">
	</p>
	<p>
		<label for="fcen_preview_field" style="display:block;font-weight:600;margin-bottom:4px;">
			<?php esc_html_e( 'Preview text (tagline)', 'firstchurch-enews' ); ?>
		</label>
		<input type="text" id="fcen_preview_field" name="fcen_preview_field"
		       value="<?php echo esc_attr( $preview ); ?>" class="widefat"
		       placeholder="<?php esc_attr_e( 'All-Church Conference, Open Mic Night, …', 'firstchurch-enews' ); ?>">
	</p>
	<p>
		<label for="fcen_date_field" style="display:block;font-weight:600;margin-bottom:4px;">
			<?php esc_html_e( 'Send date', 'firstchurch-enews' ); ?>
		</label>
		<input type="date" id="fcen_date_field" name="fcen_date_field"
		       value="<?php echo esc_attr( $date ); ?>" class="widefat">
	</p>
	<p style="color:#666;font-size:12px;margin:0;">
		<?php esc_html_e( 'The subject + preview become the email envelope; the body below is the issue. The timely sections fill themselves from the Happenings spine.', 'firstchurch-enews' ); ?>
	</p>
	<?php
	// The in-editor "Preview email" affordance lives here (this meta box is
	// rendered by Gutenberg in the sidebar) rather than on the classic-only
	// post_submitbox_misc_actions hook. A brand-new auto-draft has no body yet,
	// so we wait until there's a saved draft to preview.
	if ( function_exists( 'fcen_email_preview_url' ) && 'auto-draft' !== get_post_status( $post ) ) :
		?>
		<p style="margin:10px 0 0;">
			<a href="<?php echo esc_url( fcen_email_preview_url( $post->ID ) ); ?>" target="_blank" rel="noopener" class="button">
				<span class="dashicons dashicons-email-alt" style="vertical-align:text-bottom;"></span>
				<?php esc_html_e( 'Preview email', 'firstchurch-enews' ); ?>
			</a>
		</p>
		<?php
	else :
		?>
		<p style="color:#666;font-size:12px;margin:10px 0 0;">
			<?php esc_html_e( 'Save a draft to preview the email.', 'firstchurch-enews' ); ?>
		</p>
		<?php
	endif;

	// The "Push to Mailchimp" control (creates/updates a draft campaign).
	if ( function_exists( 'fcen_mailchimp_button' ) ) {
		fcen_mailchimp_button( $post );
	}
}

add_action( 'save_post_' . FCEN_CPT, 'fcen_settings_save' );

/**
 * Persist the settings box.
 *
 * @param int $post_id Issue being saved.
 */
function fcen_settings_save( $post_id ): void {
	if ( ! isset( $_POST['fcen_settings_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcen_settings_nonce'] ) ), 'fcen_settings_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$subject = isset( $_POST['fcen_subject_field'] )
		? sanitize_text_field( wp_unslash( $_POST['fcen_subject_field'] ) ) : '';
	$preview = isset( $_POST['fcen_preview_field'] )
		? sanitize_text_field( wp_unslash( $_POST['fcen_preview_field'] ) ) : '';
	$date = isset( $_POST['fcen_date_field'] )
		? fcen_sanitize_date( wp_unslash( $_POST['fcen_date_field'] ) ) : '';

	update_post_meta( $post_id, FCEN_SUBJECT_KEY, $subject );
	update_post_meta( $post_id, FCEN_PREVIEW_KEY, $preview );
	update_post_meta( $post_id, FCEN_DATE_KEY, $date );
}
