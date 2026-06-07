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
				'name'          => 'Standing Cards',
				'singular_name' => 'Standing Card',
				'add_new_item'  => 'Add Standing Card',
				'edit_item'     => 'Edit Standing Card',
				'menu_name'     => 'Standing Cards',
				'all_items'     => 'Standing Cards',
			),
			// Curation-only content: no public single/archive pages, but a full
			// admin UI. Our REST/MCP feed is the read surface, not wp/v2.
			// show_in_menu => false: the library lives under the Carousel menu
			// (Curate is the front door) — wired up in admin-curate.php.
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false,
			'show_in_rest'    => false,
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
	<?php $curate_url = fccar_curate_url(); ?>
	<div class="fccar-edit-preview">
		<div class="fccar-edit-thumb" id="fccar-edit-thumb"></div>
		<p class="fccar-edit-hint">Live preview. This is a <strong>standing card</strong> — <a href="<?php echo esc_url( $curate_url ); ?>">arrange the deck in Curate&nbsp;→</a></p>
	</div>
	<div class="fccar-grid">
		<div class="fccar-fieldrow">
			<label for="fccar_layout">Layout</label>
			<select name="fccar_layout" id="fccar_layout">
				<?php foreach ( FCCAR_LAYOUTS as $l ) : ?>
					<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $layout, $l ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="desc">The slide layout this card renders as. The post <strong>title</strong> is the card title; the <strong>featured image</strong> is the background photo.</p>
		</div>

		<div class="fccar-fieldrow" data-layouts="intro info">
			<label for="fccar_body">Body <span class="desc">(info cards: one <code>- </code> per bulleted line)</span></label>
			<textarea name="fccar_body" id="fccar_body" rows="4"><?php echo esc_textarea( $body ); ?></textarea>
		</div>

		<div class="fccar-fieldrow" data-layouts="qr_callout">
			<label for="fccar_prompt">Prompt <span class="desc">(qr_callout)</span></label>
			<textarea name="fccar_prompt" id="fccar_prompt" rows="2"><?php echo esc_textarea( $prompt ); ?></textarea>
		</div>

		<div class="fccar-fieldrow" data-layouts="feature">
			<label for="fccar_details">Details <span class="desc">(feature — italic line)</span></label>
			<textarea name="fccar_details" id="fccar_details" rows="2"><?php echo esc_textarea( $detail ); ?></textarea>
		</div>

		<div class="fccar-fieldrow" data-layouts="divider qr_callout event info feature">
			<label for="fccar_qr_url">QR link <span class="desc">(becomes the card's QR target)</span></label>
			<input type="url" name="fccar_qr_url" id="fccar_qr_url" value="<?php echo esc_attr( $qr ); ?>">
		</div>

		<div class="fccar-fieldrow" data-layouts="divider qr_callout feature">
			<label for="fccar_bg_color">Background color <span class="desc">(solid fallback when no photo, e.g. <code>#7FA888</code>)</span></label>
			<input type="text" name="fccar_bg_color" id="fccar_bg_color" value="<?php echo esc_attr( $color ); ?>" placeholder="#1F1F1F">
		</div>

		<div class="fccar-fieldrow">
			<label><input type="checkbox" name="fccar_preservice" id="fccar_preservice" value="1" <?php checked( $presvc ); ?>> Preservice-only <span class="desc">(dropped from the post-service loop)</span></label>
		</div>
	</div>
	<?php
}

/**
 * The single validation gate for a standing card's fields — shared by the
 * classic metabox save and the Curate drawer's REST save, so both paths agree
 * on what a valid card is. Pure: a raw assoc (form field names, no `fccar_`
 * prefix) in, a clean assoc out. `image_id` is the featured-image attachment id
 * the drawer's media picker supplies (0 = none); the metabox ignores it, since
 * WordPress's own featured-image box owns that field there.
 *
 * @param array<string,mixed> $raw
 * @return array{title:string,layout:string,body:string,prompt:string,details:string,qr_url:string,bg_color:string,preservice:bool,image_id:int}
 */
function fccar_sanitize_card_input( array $raw ): array {
	$layout = isset( $raw['layout'] ) ? sanitize_key( (string) $raw['layout'] ) : 'info';
	if ( ! in_array( $layout, FCCAR_LAYOUTS, true ) ) {
		$layout = 'info';
	}

	$color = sanitize_text_field( (string) ( $raw['bg_color'] ?? '' ) );
	$color = preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ? $color : '';

	return array(
		'title'      => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
		'layout'     => $layout,
		'body'       => sanitize_textarea_field( (string) ( $raw['body'] ?? '' ) ),
		'prompt'     => sanitize_textarea_field( (string) ( $raw['prompt'] ?? '' ) ),
		'details'    => sanitize_textarea_field( (string) ( $raw['details'] ?? '' ) ),
		'qr_url'     => esc_url_raw( (string) ( $raw['qr_url'] ?? '' ) ),
		'bg_color'   => $color,
		'preservice' => ! empty( $raw['preservice'] ) && '0' !== $raw['preservice'],
		'image_id'   => absint( $raw['image_id'] ?? 0 ),
	);
}

/** Write a sanitized card's meta onto a post (title/featured image handled by the caller). */
function fccar_apply_card_meta( int $post_id, array $clean ): void {
	update_post_meta( $post_id, FCCAR_META_LAYOUT, $clean['layout'] );
	update_post_meta( $post_id, FCCAR_META_BODY, $clean['body'] );
	update_post_meta( $post_id, FCCAR_META_PROMPT, $clean['prompt'] );
	update_post_meta( $post_id, FCCAR_META_DETAILS, $clean['details'] );
	update_post_meta( $post_id, FCCAR_META_QR, $clean['qr_url'] );
	update_post_meta( $post_id, FCCAR_META_BGCOLOR, $clean['bg_color'] );
	update_post_meta( $post_id, FCCAR_META_PRESVC, $clean['preservice'] ? '1' : '' );
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

	fccar_apply_card_meta( $post_id, fccar_sanitize_card_input( array(
		'layout'     => wp_unslash( $_POST['fccar_layout'] ?? '' ),
		'body'       => wp_unslash( $_POST['fccar_body'] ?? '' ),
		'prompt'     => wp_unslash( $_POST['fccar_prompt'] ?? '' ),
		'details'    => wp_unslash( $_POST['fccar_details'] ?? '' ),
		'qr_url'     => wp_unslash( $_POST['fccar_qr_url'] ?? '' ),
		'bg_color'   => wp_unslash( $_POST['fccar_bg_color'] ?? '' ),
		'preservice' => empty( $_POST['fccar_preservice'] ) ? '' : '1',
	) ) );
}

/* ---- Admin list: surface layout + sequence so the deck reads at a glance ---- */

add_filter( 'manage_' . FCCAR_CPT . '_posts_columns', static function ( $cols ) {
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'cb' === $k ) {
			$out['fccar_preview'] = 'Preview';
		}
		if ( 'title' === $k ) {
			$out['fccar_layout'] = 'Layout';
			$out['fccar_presvc'] = 'Preservice-only';
		}
	}
	$out['fccar_order'] = 'Order';
	return $out;
} );

add_action( 'manage_' . FCCAR_CPT . '_posts_custom_column', static function ( $col, $post_id ) {
	if ( 'fccar_preview' === $col ) {
		// A rendered mini of the card, drawn client-side by FCCarCard (list-cards.js)
		// from this item's resolved data — so the list matches the curator.
		$item = fccar_card_to_item( get_post( $post_id ) );
		echo '<div class="fccar-list-thumb" data-fccar="' . esc_attr( (string) wp_json_encode( $item ) ) . '"></div>';
	} elseif ( 'fccar_layout' === $col ) {
		echo esc_html( (string) get_post_meta( $post_id, FCCAR_META_LAYOUT, true ) );
	} elseif ( 'fccar_presvc' === $col ) {
		echo get_post_meta( $post_id, FCCAR_META_PRESVC, true ) ? '✓' : '';
	} elseif ( 'fccar_order' === $col ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}, 10, 2 );

add_filter( 'manage_edit-' . FCCAR_CPT . '_sortable_columns', static function ( $cols ) {
	$cols['fccar_order'] = 'menu_order';
	return $cols;
} );

/* ---- Shared card renderer on the CPT screens: live edit preview + list thumbs ---- */

add_action( 'admin_enqueue_scripts', static function ( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || FCCAR_CPT !== $screen->post_type ) {
		return;
	}
	$base = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
	wp_enqueue_style( 'fccar-raleway', 'https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,400;0,600;0,700;1,400&display=swap', array(), null );
	wp_enqueue_style( 'fccar-card', $base . 'card.css', array(), FCCAR_VERSION );
	wp_enqueue_style( 'fccar-admin-card', $base . 'admin-card.css', array( 'fccar-card' ), FCCAR_VERSION );
	wp_enqueue_script( 'fccar-qrcode', $base . 'vendor/qrcode-generator.js', array(), FCCAR_VERSION, true );
	wp_enqueue_script( 'fccar-card-render', $base . 'card-render.js', array( 'fccar-qrcode' ), FCCAR_VERSION, true );

	if ( 'post' === $screen->base ) {            // single card add/edit
		wp_enqueue_script( 'fccar-edit-card', $base . 'edit-card.js', array( 'jquery', 'fccar-card-render' ), FCCAR_VERSION, true );
	} elseif ( 'edit' === $screen->base ) {      // the Carousel Cards list
		wp_enqueue_script( 'fccar-list-cards', $base . 'list-cards.js', array( 'fccar-card-render' ), FCCAR_VERSION, true );
	}
} );

// Frame the list as the standing-card library and point at the curator.
add_action( 'admin_notices', static function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-' . FCCAR_CPT !== $screen->id ) {
		return;
	}
	$curate = fccar_curate_url();
	echo '<div class="notice notice-info"><p>These are the <strong>standing (evergreen) cards</strong> — reusable announcements (intro, dividers, QR callouts, housekeeping) shown every week. Events and news come from their own posts. <a href="' . esc_url( $curate ) . '">Curate the deck →</a></p></div>';
} );
