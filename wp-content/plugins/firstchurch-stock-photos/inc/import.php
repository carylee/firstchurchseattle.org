<?php
/**
 * Download an Openverse image into the media library and stamp its provenance.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import one image.
 *
 * The caller passes the candidate fields returned by fcsp_search() so we record
 * full provenance without a second API round-trip. Only image_url is required.
 *
 * @param array $data {
 *     @type string $image_url    Required http(s) URL of the full image.
 *     @type string $title        Attachment title / fallback alt.
 *     @type string $alt          Alt text (defaults to title).
 *     @type int    $post_id      If set, also use the import as that post's featured image.
 *     @type string $openverse_id
 *     @type string $creator
 *     @type string $creator_url
 *     @type string $license
 *     @type string $license_url
 *     @type string $attribution
 *     @type string $source
 *     @type string $foreign_url
 * }
 * @return array|WP_Error { attachment_id, attachment_url, alt, credit }
 */
function fcsp_import( array $data ) {
	if ( ! current_user_can( fcsp_capability() ) ) {
		return new WP_Error( 'fcsp_forbidden', 'You are not permitted to upload files.' );
	}

	$url = isset( $data['image_url'] ) ? esc_url_raw( (string) $data['image_url'] ) : '';
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return new WP_Error( 'fcsp_bad_url', 'image_url must be an http(s) URL.' );
	}

	$post_id = (int) ( $data['post_id'] ?? 0 );
	if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'fcsp_forbidden_post', 'You are not permitted to edit the target post.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	if ( '' === $name || false === strpos( $name, '.' ) ) {
		// Openverse URLs are often extensionless; give the sideloader something
		// sane and let WP correct the extension from the downloaded mime type.
		$name = sanitize_title( (string) ( $data['title'] ?? 'openverse-image' ) ) . '.jpg';
	}

	$file_array = array(
		'name'     => sanitize_file_name( $name ),
		'tmp_name' => $tmp,
	);

	$title         = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '';
	$attachment_id = media_handle_sideload( $file_array, $post_id, $title ?: null );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		return $attachment_id;
	}
	$attachment_id = (int) $attachment_id;

	// Alt text — fall back to title.
	$alt = isset( $data['alt'] ) ? sanitize_text_field( (string) $data['alt'] ) : $title;
	if ( '' !== $alt ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	}

	fcsp_store_provenance( $attachment_id, $data );

	if ( $post_id ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}

	return array(
		'attachment_id'  => $attachment_id,
		'attachment_url' => (string) wp_get_attachment_url( $attachment_id ),
		'alt'            => $alt,
		'credit'         => fcsp_attachment_credit( $attachment_id ),
	);
}

/**
 * Persist the where-it-came-from metadata on an attachment.
 */
function fcsp_store_provenance( int $attachment_id, array $data ): void {
	$map = array(
		FCSP_META_OV_ID       => 'openverse_id',
		FCSP_META_CREATOR     => 'creator',
		FCSP_META_CREATOR_URL => 'creator_url',
		FCSP_META_LICENSE     => 'license',
		FCSP_META_LICENSE_URL => 'license_url',
		FCSP_META_ATTRIBUTION => 'attribution',
		FCSP_META_SOURCE      => 'source',
		FCSP_META_FOREIGN_URL => 'foreign_url',
	);
	foreach ( $map as $meta_key => $field ) {
		$value = isset( $data[ $field ] ) ? trim( (string) $data[ $field ] ) : '';
		if ( '' === $value ) {
			continue;
		}
		// URLs through esc_url_raw, everything else through sanitize_text_field.
		$value = ( false !== strpos( $field, 'url' ) ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
		update_post_meta( $attachment_id, $meta_key, $value );
	}
}
