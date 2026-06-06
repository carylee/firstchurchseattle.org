<?php
/**
 * The carousel_card custom post type — the home for evergreen / standing cards
 * (intro/mission, dividers, QR callouts, housekeeping info). Events and news
 * are NOT stored here; the resolver pulls those from ctc_event and the
 * Announcements category and projects them alongside these cards.
 *
 * Card content lives in post meta edited through a classic metabox so the
 * field set matches the slide renderer's per-layout contract
 * (apps/slides/app/src/cards/cardTypes.ts). The post title is the card title
 * and the featured image is the background photo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'fccar_register_cpt' );

function fccar_register_cpt(): void {
	register_post_type(
		FCCAR_CPT,
		array(
			'labels'          => array(
				'name'          => 'Carousel Cards',
				'singular_name' => 'Carousel Card',
				'add_new_item'  => 'Add Carousel Card',
				'edit_item'     => 'Edit Carousel Card',
				'menu_name'     => 'Carousel',
				'all_items'     => 'Carousel Cards',
			),
			// Curation-only content: no public single/archive pages, but a full
			// admin UI. Our REST/MCP feed is the read surface, not wp/v2.
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_icon'       => 'dashicons-images-alt2',
			'menu_position'   => 26,
			'capability_type' => 'post',
			'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
		)
	);
}

// Standing cards are short structured records — the classic metabox is a better
// fit than the block editor (and guarantees the metabox renders).
add_filter( 'use_block_editor_for_post_type', static function ( $use, $type ) {
	return FCCAR_CPT === $type ? false : $use;
}, 10, 2 );

/* ---- Edit screen: the card fields metabox ---- */

add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fccar_fields', 'Card', 'fccar_render_metabox', FCCAR_CPT, 'normal', 'high' );
} );

function fccar_render_metabox( WP_Post $post ): void {
	wp_nonce_field( 'fccar_save', 'fccar_nonce' );
	$layout = (string) get_post_meta( $post->ID, FCCAR_META_LAYOUT, true ) ?: 'info';
	$body   = (string) get_post_meta( $post->ID, FCCAR_META_BODY, true );
	$prompt = (string) get_post_meta( $post->ID, FCCAR_META_PROMPT, true );
	$detail = (string) get_post_meta( $post->ID, FCCAR_META_DETAILS, true );
	$qr     = (string) get_post_meta( $post->ID, FCCAR_META_QR, true );
	$color  = (string) get_post_meta( $post->ID, FCCAR_META_BGCOLOR, true );
	$presvc = (bool) get_post_meta( $post->ID, FCCAR_META_PRESVC, true );
	?>
	<style>
		.fccar-grid label{display:block;font-weight:600;margin:14px 0 4px}
		.fccar-grid input[type=text],.fccar-grid input[type=url],.fccar-grid textarea,.fccar-grid select{width:100%;max-width:640px}
		.fccar-grid .desc{font-weight:400;color:#666;font-size:12px}
	</style>
	<div class="fccar-grid">
		<label for="fccar_layout">Layout</label>
		<select name="fccar_layout" id="fccar_layout">
			<?php foreach ( FCCAR_LAYOUTS as $l ) : ?>
				<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $layout, $l ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="desc">The slide layout this card renders as. The post <strong>title</strong> is the card title; the <strong>featured image</strong> is the background photo.</p>

		<label for="fccar_body">Body <span class="desc">(info cards: one <code>- </code> per bulleted line)</span></label>
		<textarea name="fccar_body" id="fccar_body" rows="4"><?php echo esc_textarea( $body ); ?></textarea>

		<label for="fccar_prompt">Prompt <span class="desc">(qr_callout)</span></label>
		<textarea name="fccar_prompt" id="fccar_prompt" rows="2"><?php echo esc_textarea( $prompt ); ?></textarea>

		<label for="fccar_details">Details <span class="desc">(feature — italic line)</span></label>
		<textarea name="fccar_details" id="fccar_details" rows="2"><?php echo esc_textarea( $detail ); ?></textarea>

		<label for="fccar_qr_url">QR link <span class="desc">(becomes the card's QR target)</span></label>
		<input type="url" name="fccar_qr_url" id="fccar_qr_url" value="<?php echo esc_attr( $qr ); ?>">

		<label for="fccar_bg_color">Background color <span class="desc">(solid fallback when no photo, e.g. <code>#7FA888</code>)</span></label>
		<input type="text" name="fccar_bg_color" id="fccar_bg_color" value="<?php echo esc_attr( $color ); ?>" placeholder="#1F1F1F">

		<label><input type="checkbox" name="fccar_preservice" value="1" <?php checked( $presvc ); ?>> Preservice-only <span class="desc">(dropped from the post-service loop)</span></label>
	</div>
	<?php
}

add_action( 'save_post_' . FCCAR_CPT, 'fccar_save_metabox', 10, 2 );

function fccar_save_metabox( int $post_id, WP_Post $post ): void {
	if ( ! isset( $_POST['fccar_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fccar_nonce'] ), 'fccar_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$layout = isset( $_POST['fccar_layout'] ) ? sanitize_key( wp_unslash( $_POST['fccar_layout'] ) ) : 'info';
	if ( ! in_array( $layout, FCCAR_LAYOUTS, true ) ) {
		$layout = 'info';
	}
	update_post_meta( $post_id, FCCAR_META_LAYOUT, $layout );

	update_post_meta( $post_id, FCCAR_META_BODY, sanitize_textarea_field( wp_unslash( $_POST['fccar_body'] ?? '' ) ) );
	update_post_meta( $post_id, FCCAR_META_PROMPT, sanitize_textarea_field( wp_unslash( $_POST['fccar_prompt'] ?? '' ) ) );
	update_post_meta( $post_id, FCCAR_META_DETAILS, sanitize_textarea_field( wp_unslash( $_POST['fccar_details'] ?? '' ) ) );
	update_post_meta( $post_id, FCCAR_META_QR, esc_url_raw( wp_unslash( $_POST['fccar_qr_url'] ?? '' ) ) );

	$color = sanitize_text_field( wp_unslash( $_POST['fccar_bg_color'] ?? '' ) );
	$color = preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ? $color : '';
	update_post_meta( $post_id, FCCAR_META_BGCOLOR, $color );

	update_post_meta( $post_id, FCCAR_META_PRESVC, empty( $_POST['fccar_preservice'] ) ? '' : '1' );
}

/* ---- Admin list: surface layout + sequence so the deck reads at a glance ---- */

add_filter( 'manage_' . FCCAR_CPT . '_posts_columns', static function ( $cols ) {
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['fccar_layout'] = 'Layout';
			$out['fccar_presvc'] = 'Preservice-only';
		}
	}
	$out['fccar_order'] = 'Order';
	return $out;
} );

add_action( 'manage_' . FCCAR_CPT . '_posts_custom_column', static function ( $col, $post_id ) {
	if ( 'fccar_layout' === $col ) {
		echo esc_html( (string) get_post_meta( $post_id, FCCAR_META_LAYOUT, true ) );
	} elseif ( 'fccar_presvc' === $col ) {
		echo get_post_meta( $post_id, FCCAR_META_PRESVC, true ) ? '✓' : '';
	} elseif ( 'fccar_order' === $col ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}, 10, 2 );
