<?php
/**
 * Announcement / Featured CTA cards.
 *
 * Adds an optional call-to-action (button text + URL) to standard posts, and a
 * shortcode that renders recent posts from a category as cards — title,
 * excerpt, and (when present) a maroon CTA button. Built to replace the stock
 * core/latest-posts block on the "What's Happening" (/engage) page, which can
 * only show title + excerpt + permalink and has no button affordance.
 *
 * Ways the CTA fields get set:
 *   1. wp-admin meta box (staff, by hand) — see the meta box below.
 *   2. Authenticated WP REST API (meta is show_in_rest) — for scripted/Breeze
 *      population: POST /wp/v2/posts/<id> { "meta": { "fcs_cta_url": "..." } }.
 *
 * Mirrors how Church Theme Content events already carry a `registration_url`.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Meta keys — single source of truth shared by meta box, REST reg, renderer. */
const FCS_CTA_TEXT_KEY = 'fcs_cta_text';
const FCS_CTA_URL_KEY  = 'fcs_cta_url';

/**
 * Happenings lifecycle meta (see ops/docs/happenings.md §3-4). `fcs_weight` is
 * the prominence used to auto-sort the /engage Featured row and the carousel
 * news block (0 = normal, higher floats up). `fcs_expires` drops the post from
 * feed surfaces after that date while it stays published in the news archive.
 */
const FCS_WEIGHT_KEY  = 'fcs_weight';
const FCS_EXPIRES_KEY = 'fcs_expires';

/** Weight presets surfaced in the meta box (label => stored int). */
function fcs_weight_choices() {
	return array(
		0  => __( 'Normal', 'maranatha-child' ),
		10 => __( 'Featured', 'maranatha-child' ),
		20 => __( 'Pinned (top)', 'maranatha-child' ),
	);
}

/**
 * Post types that carry the Prominence (weight) control, so any of them can be
 * promoted into the /engage + e-news Featured row. Announcements are posts;
 * events span both backends (CTC is being decommissioned, fce is current). The
 * spine reads fcs_weight from all three (see firstchurch-happenings sources).
 *
 * @return array<int,string>
 */
function fcs_promo_post_types() {
	return array( 'post', 'fce_event' );
}

/** Sanitize an expiry value: a YYYY-MM-DD date, or '' to clear. */
function fcs_sanitize_expires( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$d = DateTime::createFromFormat( 'Y-m-d', $value );
	return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : '';
}

/**
 * Register CTA meta on the `post` type, exposed in REST so it can be set via
 * the authenticated REST API and read back. Only users who can edit the post
 * may write.
 */
add_action(
	'init',
	function () {
		$can_edit = function ( $allowed, $meta_key, $object_id ) {
			return current_user_can( 'edit_post', $object_id );
		};

		register_post_meta(
			'post',
			FCS_CTA_TEXT_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $can_edit,
				'default'           => '',
			)
		);

		register_post_meta(
			'post',
			FCS_CTA_URL_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $can_edit,
				'default'           => '',
			)
		);

		// Prominence (and expiry) ride on every promotable type — posts AND events
		// — so a weighted event can join the Featured row (ops/docs/happenings.md
		// Phase 4). Expiry is meaningful mainly for posts (events self-expire by
		// date), but registering it uniformly is harmless and keeps the meta box
		// one shape.
		foreach ( fcs_promo_post_types() as $promo_type ) {
			register_post_meta(
				$promo_type,
				FCS_WEIGHT_KEY,
				array(
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => $can_edit,
					'default'           => 0,
				)
			);

			register_post_meta(
				$promo_type,
				FCS_EXPIRES_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'fcs_sanitize_expires',
					'auth_callback'     => $can_edit,
					'default'           => '',
				)
			);
		}
	}
);

/**
 * wp-admin meta box so staff can set the button label + link without touching
 * the post body.
 */
add_action(
	'add_meta_boxes',
	function () {
		// CTA button is post-only — events carry their own registration URL field.
		add_meta_box(
			'fcs-cta',
			__( 'Call to Action (button)', 'maranatha-child' ),
			'fcs_cta_meta_box_render',
			'post',
			'side',
			'default'
		);
		// Prominence rides on posts AND events so either can be featured.
		foreach ( fcs_promo_post_types() as $promo_type ) {
			add_meta_box(
				'fcs-promo',
				__( 'Promotion & expiry', 'maranatha-child' ),
				'fcs_promo_meta_box_render',
				$promo_type,
				'side',
				'default'
			);
		}
	}
);

/**
 * Render the meta box.
 *
 * @param WP_Post $post Current post.
 */
function fcs_cta_meta_box_render( $post ) {
	wp_nonce_field( 'fcs_cta_save', 'fcs_cta_nonce' );
	$text = get_post_meta( $post->ID, FCS_CTA_TEXT_KEY, true );
	$url  = get_post_meta( $post->ID, FCS_CTA_URL_KEY, true );
	?>
	<p>
		<label for="fcs_cta_text_field" style="display:block;font-weight:600;margin-bottom:4px;">
			<?php esc_html_e( 'Button text', 'maranatha-child' ); ?>
		</label>
		<input type="text" id="fcs_cta_text_field" name="fcs_cta_text_field"
		       value="<?php echo esc_attr( $text ); ?>" class="widefat"
		       placeholder="<?php esc_attr_e( 'Sign up', 'maranatha-child' ); ?>">
	</p>
	<p>
		<label for="fcs_cta_url_field" style="display:block;font-weight:600;margin-bottom:4px;">
			<?php esc_html_e( 'Button link (URL)', 'maranatha-child' ); ?>
		</label>
		<input type="url" id="fcs_cta_url_field" name="fcs_cta_url_field"
		       value="<?php echo esc_attr( $url ); ?>" class="widefat"
		       placeholder="https://firstchurchseattle.breezechms.com/form/…">
	</p>
	<p style="color:#666;font-size:12px;margin:0;">
		<?php esc_html_e( 'Shown as a button on the What\'s Happening page. Leave blank for no button.', 'maranatha-child' ); ?>
	</p>
	<?php
}

/**
 * Render the Promotion & expiry box: weight (prominence) + expiry date. These
 * drive the /engage Featured ordering and feed-surface lifecycle — see
 * ops/docs/happenings.md.
 *
 * @param WP_Post $post Current post.
 */
function fcs_promo_meta_box_render( $post ) {
	wp_nonce_field( 'fcs_promo_save', 'fcs_promo_nonce' );
	$weight  = (int) get_post_meta( $post->ID, FCS_WEIGHT_KEY, true );
	$expires = get_post_meta( $post->ID, FCS_EXPIRES_KEY, true );
	// Events self-expire by date, so the explicit "stop showing after" date is a
	// posts-only control; events show Prominence alone.
	$show_expiry = ( 'post' === $post->post_type );
	?>
	<p>
		<label for="fcs_weight_field" style="display:block;font-weight:600;margin-bottom:4px;">
			<?php esc_html_e( 'Prominence', 'maranatha-child' ); ?>
		</label>
		<select id="fcs_weight_field" name="fcs_weight_field" class="widefat">
			<?php foreach ( fcs_weight_choices() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $weight, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php if ( $show_expiry ) : ?>
	<p>
		<label for="fcs_expires_field" style="display:block;font-weight:600;margin-bottom:4px;">
			<?php esc_html_e( 'Stop showing after', 'maranatha-child' ); ?>
		</label>
		<input type="date" id="fcs_expires_field" name="fcs_expires_field"
		       value="<?php echo esc_attr( $expires ); ?>" class="widefat">
	</p>
	<?php endif; ?>
	<p style="color:#666;font-size:12px;margin:0;">
		<?php
		echo $show_expiry
			? esc_html__( 'Featured/Pinned float this up the What\'s Happening page and lobby screen. After the expiry date it drops off those surfaces but stays in the news archive.', 'maranatha-child' )
			: esc_html__( 'Featured/Pinned float this event up the What\'s Happening page, the e-news, and the lobby screen. Past events drop off on their own.', 'maranatha-child' );
		?>
	</p>
	<?php
}

/**
 * Save the meta box values.
 *
 * @param int $post_id Post being saved.
 */
add_action(
	'save_post_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['fcs_cta_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcs_cta_nonce'] ) ), 'fcs_cta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text = isset( $_POST['fcs_cta_text_field'] )
			? sanitize_text_field( wp_unslash( $_POST['fcs_cta_text_field'] ) )
			: '';
		$url = isset( $_POST['fcs_cta_url_field'] )
			? esc_url_raw( wp_unslash( $_POST['fcs_cta_url_field'] ) )
			: '';

		update_post_meta( $post_id, FCS_CTA_TEXT_KEY, $text );
		update_post_meta( $post_id, FCS_CTA_URL_KEY, $url );
	}
);

/**
 * Save the Promotion & expiry box — on posts AND events (the box renders on every
 * fcs_promo_post_types()). Its own nonce, since the box appears on screens that
 * lack the post-only CTA box. Expiry is only present in POST for posts (events
 * hide it); when absent we leave the stored value untouched rather than clearing
 * it.
 */
add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['fcs_promo_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fcs_promo_nonce'] ) ), 'fcs_promo_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$weight = isset( $_POST['fcs_weight_field'] ) ? absint( wp_unslash( $_POST['fcs_weight_field'] ) ) : 0;
		update_post_meta( $post_id, FCS_WEIGHT_KEY, $weight );

		// Posts-only field: only touch expiry when the control was on screen.
		if ( isset( $_POST['fcs_expires_field'] ) ) {
			update_post_meta(
				$post_id,
				FCS_EXPIRES_KEY,
				fcs_sanitize_expires( wp_unslash( $_POST['fcs_expires_field'] ) )
			);
		}
	}
);

/**
 * Shortcode: [fcs_announcements category="announcements" count="4" heading="" empty=""]
 *
 * Renders recent published posts in a category as cards: title (links to post)
 * + date + excerpt + CTA button (only when fcs_cta_url is set). Renders nothing
 * (or an optional note) when there are no posts, so it's safe on any page.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML.
 */
function fcs_announcements_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'category' => 'announcements',
			'count'    => 4,
			'heading'  => '',
			'empty'    => '',
		),
		$atts,
		'fcs_announcements'
	);

	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'category_name'       => sanitize_title( $atts['category'] ),
			'posts_per_page'      => max( 1, (int) $atts['count'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	if ( ! $query->have_posts() ) {
		wp_reset_postdata();
		if ( '' !== $atts['empty'] ) {
			return '<p class="fcs-cards-empty">' . esc_html( $atts['empty'] ) . '</p>';
		}
		return '';
	}

	ob_start();

	if ( '' !== $atts['heading'] ) {
		echo '<h2 class="fcs-cards-heading">' . esc_html( $atts['heading'] ) . '</h2>';
	}

	echo '<div class="fcs-card-grid">';

	while ( $query->have_posts() ) {
		$query->the_post();
		$pid      = get_the_ID();
		$cta_text = get_post_meta( $pid, FCS_CTA_TEXT_KEY, true );
		$cta_url  = get_post_meta( $pid, FCS_CTA_URL_KEY, true );
		?>
		<article class="fcs-card">
			<div class="fcs-card__body">
				<h3 class="fcs-card__title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>
				<p class="fcs-card__date"><?php echo esc_html( get_the_date() ); ?></p>
				<p class="fcs-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28 ) ); ?></p>
			</div>
			<div class="fcs-card__cta">
				<?php if ( $cta_url ) : ?>
					<a href="<?php echo esc_url( $cta_url ); ?>" class="fcs-cta-button">
						<?php echo esc_html( $cta_text ? $cta_text : __( 'Learn more', 'maranatha-child' ) ); ?>
					</a>
				<?php else : // No explicit CTA — fall back to a quiet "Read more" so every card has an action. ?>
					<a href="<?php the_permalink(); ?>" class="fcs-cta-button is-fallback">
						<?php esc_html_e( 'Read more', 'maranatha-child' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	echo '</div>';

	wp_reset_postdata();

	return (string) ob_get_clean();
}
add_shortcode( 'fcs_announcements', 'fcs_announcements_shortcode' );
