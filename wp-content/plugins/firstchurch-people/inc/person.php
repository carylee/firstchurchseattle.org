<?php
/**
 * The person data model: read/write the _ctc_person_* meta, decoupled from the
 * parent theme's ctfw_person_data(). One writer (fcs_write_person()) is shared
 * by the MCP abilities and the admin metabox, mirroring firstchurch-events.
 *
 * @package FirstChurch\People
 */

use FirstChurch\People\Person;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured person data for templates — the first-party replacement for the
 * parent's ctfw_person_data(). Reads the same meta keys, so it returns the same
 * values whether CTC or we registered the type.
 *
 * @param int|null $post_id Defaults to the current post.
 * @return array{
 *   position:string, pronouns:string, phone:string, email:string,
 *   urls:array<int,string>, social:array<int,array{href:string,icon:string,label:string}>
 * }
 */
function fcs_person_data( ?int $post_id = null ): array {
	$post_id = $post_id ?: get_the_ID();
	$urls    = (string) get_post_meta( $post_id, FCP_URLS, true );

	return array(
		'position' => (string) get_post_meta( $post_id, FCP_POSITION, true ),
		'pronouns' => Person::pronouns( (string) get_post_meta( $post_id, FCP_PRONOUNS, true ) ),
		'phone'    => (string) get_post_meta( $post_id, FCP_PHONE, true ),
		'email'    => (string) get_post_meta( $post_id, FCP_EMAIL, true ),
		'urls'     => Person::urls( $urls ),
		'social'   => Person::socialLinks( $urls ),
	);
}

/**
 * A `tel:`-linked phone number (escaped), or the plain escaped text when it has
 * no diallable digits — the first-party replacement for ctfw_format_phone().
 */
function fcs_person_phone_html( string $phone ): string {
	$phone = trim( $phone );
	if ( '' === $phone ) {
		return '';
	}
	$href = Person::telHref( $phone );
	if ( '' === $href ) {
		return esc_html( $phone );
	}
	return sprintf( '<a href="%s">%s</a>', esc_attr( 'tel:' . $href ), esc_html( $phone ) );
}

/**
 * Write person meta from a normalized authoring array — the single writer shared
 * by the MCP path and the editor metabox. Every key is optional; only provided
 * keys are touched (partial updates). Title/body/thumbnail are handled by the
 * caller (they're core post fields, not meta).
 *
 * @param array<string,mixed> $in Keys: position, pronouns, phone, email, urls (array|string).
 */
function fcs_write_person( int $post_id, array $in ): void {
	if ( array_key_exists( 'position', $in ) ) {
		update_post_meta( $post_id, FCP_POSITION, sanitize_text_field( (string) $in['position'] ) );
	}
	if ( array_key_exists( 'pronouns', $in ) ) {
		update_post_meta( $post_id, FCP_PRONOUNS, sanitize_text_field( Person::pronouns( (string) $in['pronouns'] ) ) );
	}
	if ( array_key_exists( 'phone', $in ) ) {
		update_post_meta( $post_id, FCP_PHONE, sanitize_text_field( (string) $in['phone'] ) );
	}
	if ( array_key_exists( 'email', $in ) ) {
		update_post_meta( $post_id, FCP_EMAIL, sanitize_email( (string) $in['email'] ) );
	}
	if ( array_key_exists( 'urls', $in ) ) {
		// Accept either a list or a newline blob; store the CTC-shaped blob.
		$list  = is_array( $in['urls'] ) ? $in['urls'] : Person::urls( (string) $in['urls'] );
		$clean = array();
		foreach ( $list as $url ) {
			$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https', 'mailto' ) );
			if ( '' !== $url ) {
				$clean[] = $url;
			}
		}
		update_post_meta( $post_id, FCP_URLS, implode( "\n", $clean ) );
	}
}

/**
 * People grouped by ctc_person_group term, each group's members in manual
 * (menu_order) order — the shape the /staff/ listing renders. Groups follow
 * their term `term_order`/name; ungrouped people (if any) come last.
 *
 * @return array<int,array{group:?WP_Term,people:array<int,WP_Post>}>
 */
function fcs_people_by_group(): array {
	$groups = get_terms(
		array(
			'taxonomy'   => FCP_TAX,
			'hide_empty' => true,
		)
	);
	if ( is_wp_error( $groups ) ) {
		$groups = array();
	}

	$out = array();
	foreach ( $groups as $group ) {
		$people = get_posts(
			array(
				'post_type'      => FCP_CPT,
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => FCP_TAX,
						'field'    => 'term_id',
						'terms'    => $group->term_id,
					),
				),
			)
		);
		if ( $people ) {
			$out[] = array(
				'group'  => $group,
				'people' => $people,
			);
		}
	}
	return $out;
}
