<?php
/**
 * Light "Person details" editor metabox for staff who don't author via the
 * agent — position, pronouns, phone, email, and social/web links. Saves through
 * the same fce... fcs_write_person() the MCP path uses, so both stay consistent.
 *
 * GATED on fcs_people_active(): while Church Theme Content still owns ctc_person
 * it provides its own person metabox, so ours stays hidden to avoid a confusing
 * duplicate. It appears automatically at the CTC cutover.
 *
 * @package FirstChurch\People
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function () {
		if ( ! fcs_people_active() ) {
			return;
		}
		add_meta_box( 'fcp-details', __( 'Person details', 'firstchurch-people' ), 'fcp_metabox_render', FCP_CPT, 'normal', 'high' );
	}
);

function fcp_metabox_render( WP_Post $post ): void {
	wp_nonce_field( 'fcp_save', 'fcp_nonce' );
	$d    = fcs_person_data( $post->ID );
	$urls = implode( "\n", $d['urls'] );
	$row  = 'style="margin:0 0 12px;"';
	?>
	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Position', 'firstchurch-people' ); ?></strong><br>
		<input type="text" name="fcp_position" class="widefat" value="<?php echo esc_attr( $d['position'] ); ?>"
		       placeholder="<?php esc_attr_e( 'e.g. Interim Senior Pastor', 'firstchurch-people' ); ?>"></label></p>

	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Pronouns', 'firstchurch-people' ); ?></strong><br>
		<input type="text" name="fcp_pronouns" value="<?php echo esc_attr( $d['pronouns'] ); ?>"
		       placeholder="<?php esc_attr_e( 'e.g. she/her', 'firstchurch-people' ); ?>"></label></p>

	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Phone', 'firstchurch-people' ); ?></strong><br>
		<input type="text" name="fcp_phone" value="<?php echo esc_attr( $d['phone'] ); ?>"
		       placeholder="(206) 622-7278"></label>
		&nbsp;&nbsp;<label><strong><?php esc_html_e( 'Email', 'firstchurch-people' ); ?></strong><br>
		<input type="email" name="fcp_email" class="regular-text" value="<?php echo esc_attr( $d['email'] ); ?>"></label></p>

	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Social / web links', 'firstchurch-people' ); ?></strong><br>
		<textarea name="fcp_urls" class="widefat" rows="3"
		          placeholder="<?php esc_attr_e( 'One URL per line (https://… or mailto:…)', 'firstchurch-people' ); ?>"><?php echo esc_textarea( $urls ); ?></textarea></label>
		<span class="description"><?php esc_html_e( 'One per line. Recognised: Facebook, Instagram, X, YouTube, LinkedIn, email; others get a generic link icon.', 'firstchurch-people' ); ?></span></p>
	<?php
}

add_action(
	'save_post_' . FCP_CPT,
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['fcp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['fcp_nonce'] ) ), 'fcp_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		fcs_write_person(
			$post_id,
			array(
				'position' => wp_unslash( $_POST['fcp_position'] ?? '' ),
				'pronouns' => wp_unslash( $_POST['fcp_pronouns'] ?? '' ),
				'phone'    => wp_unslash( $_POST['fcp_phone'] ?? '' ),
				'email'    => wp_unslash( $_POST['fcp_email'] ?? '' ),
				'urls'     => wp_unslash( $_POST['fcp_urls'] ?? '' ),
			)
		);
	}
);
