<?php
/**
 * WP-CLI seeder for the evergreen carousel cards:
 *
 *   wp firstchurch-carousel seed [--force]
 *
 * Imports the standing cards (intro/mission, dividers, QR callouts, housekeeping
 * info) currently hand-listed in ../hocuspocus/apps/slides/content/announcements/
 * announcements.yaml. Events live in ctc_event and are NOT seeded here.
 *
 * Backgrounds in the YAML are photo *filenames* (e.g. backgrounds/hands.jpg)
 * with no media-library equivalent, so seeded cards carry only their
 * background_color (where the YAML set one) and land solid-color — staff assign
 * art per the design's 90%-automated-plus-human-tweak split. Idempotent: skips
 * if any carousel cards already exist unless --force is given.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::add_command( 'firstchurch-carousel', 'FCCar_CLI' );

class FCCar_CLI {

	/**
	 * Seed the evergreen carousel cards.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Seed even if carousel cards already exist (may create duplicates).
	 *
	 * @when after_wp_load
	 */
	public function seed( $args, $assoc_args ): void {
		$existing = new WP_Query( array(
			'post_type'      => FCCAR_CPT,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		if ( $existing->posts && empty( $assoc_args['force'] ) ) {
			WP_CLI::warning( 'Carousel cards already exist; pass --force to seed anyway. Nothing done.' );
			return;
		}

		$order = 0;
		foreach ( self::cards() as $c ) {
			$post_id = wp_insert_post( array(
				'post_type'   => FCCAR_CPT,
				'post_status' => 'publish',
				'post_title'  => $c['title'],
				'menu_order'  => $order,
			), true );
			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( 'Failed: ' . $c['title'] . ' — ' . $post_id->get_error_message() );
				continue;
			}
			update_post_meta( $post_id, FCCAR_META_LAYOUT, $c['layout'] );
			update_post_meta( $post_id, FCCAR_META_BODY, $c['body'] ?? '' );
			update_post_meta( $post_id, FCCAR_META_PROMPT, $c['prompt'] ?? '' );
			update_post_meta( $post_id, FCCAR_META_DETAILS, $c['details'] ?? '' );
			update_post_meta( $post_id, FCCAR_META_QR, $c['qr_url'] ?? '' );
			update_post_meta( $post_id, FCCAR_META_BGCOLOR, $c['bg_color'] ?? '' );
			update_post_meta( $post_id, FCCAR_META_PRESVC, empty( $c['preservice'] ) ? '' : '1' );
			++$order;
			WP_CLI::log( sprintf( '  [%s] %s', $c['layout'], $c['title'] ) );
		}
		WP_CLI::success( "Seeded {$order} carousel cards." );
	}

	/** The evergreen set, in carousel order (from announcements.yaml). */
	private static function cards(): array {
		return array(
			array(
				'layout' => 'intro',
				'title'  => 'First United Methodist Church of Seattle',
				'body'   => 'First United Methodist Church of Seattle is a progressive faith community offering a place to grow spiritually and a refuge of inclusive Christianity. We use our voices and energy to advocate for persons on society\'s margins and for all God\'s creation.',
			),
			array( 'layout' => 'divider', 'title' => 'Worshipping with Us!' ),
			array(
				'layout'   => 'qr_callout',
				'title'    => 'Digital Bulletin',
				'prompt'   => "Scan the QR code for\nthe Digital Bulletin",
				'qr_url'   => 'https://firstchurchseattle.org/bulletin',
				'bg_color' => '#7FA888',
			),
			array(
				'layout' => 'info',
				'title'  => 'Sunday Worship',
				'body'   => "- Leave prayer requests, note ministry areas of interest, and sign up for events in the comment area.\n- Drop in the offering plate later in the service, or bring to the pastors after worship.",
				'qr_url' => 'https://firstchurchseattle.org/connection-card',
			),
			array(
				'layout' => 'info',
				'title'  => 'Need a Name Tag?',
				'body'   => 'Will be available in the basket on the nametag table in the Narthex on an upcoming Sunday.',
				'qr_url' => 'https://firstchurchseattle.org/forms/nametag',
			),
			array(
				'layout' => 'info',
				'title'  => 'Hearing Devices Available',
				'body'   => 'At the bottom of the steps that lead up to the balcony. Please remember to turn off before returning.',
			),
			array(
				'layout' => 'info',
				'title'  => "Children's Activity Bags",
				'body'   => 'Available on top of the small bookshelf in the back of the sanctuary, please return after the service.',
			),
			array(
				'layout' => 'info',
				'title'  => 'Children and Youth Sunday School',
				'body'   => 'Every 2nd & 3rd Sunday. Meet in the Narthex after the Scripture Reading. Kids will return during Holy Communion.',
			),
			array(
				'layout'   => 'qr_callout',
				'title'    => 'Weekly Newsletter',
				'prompt'   => "Scan the QR code for this week's newsletter!",
				'qr_url'   => 'https://firstchurchseattle.org/enews/latest',
				'bg_color' => '#1B7AA5',
			),
			array(
				'layout'     => 'info',
				'title'      => 'Communion at Home',
				'body'       => 'If you are worshipping with us online, we invite you to take this time to prepare a solid and liquid for Holy Communion.',
				'preservice' => true,
			),
			array(
				'layout' => 'divider',
				'title'  => 'Upcoming Events',
				'qr_url' => 'https://firstchurchseattle.org/upcoming-events',
			),
			array(
				'layout' => 'info',
				'title'  => 'Room 301: Corner Library',
				'body'   => 'Visit our library on the third floor! It is filled with religious topics, social justice topics, the First Church Book Club books, reference collections and archival materials. Browse the library or check out a book!',
			),
			array(
				'layout'   => 'qr_callout',
				'title'    => 'Upcoming Events',
				'prompt'   => 'Upcoming Events',
				'qr_url'   => 'https://firstchurchseattle.org/upcoming-events/',
				'bg_color' => '#1F1F1F',
			),
		);
	}
}
