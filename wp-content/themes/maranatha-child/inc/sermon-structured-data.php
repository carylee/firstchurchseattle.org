<?php
/**
 * VideoObject JSON-LD for single sermons.
 *
 * Sermons (the ctc_sermon CPT from Church Theme Content) are public single
 * pages and most carry a video. Yoast emits Article/WebPage schema for them but
 * not VideoObject (that's a Yoast premium add-on), so this is complementary —
 * it gives Google the structured video data needed for video rich results.
 *
 * Conservative by design: we emit the VideoObject ONLY when the sermon has both
 * a video URL and a featured-image thumbnail (the two fields a valid, useful
 * VideoObject needs), so we never publish incomplete schema that would trip
 * Search Console warnings.
 *
 * @package Maranatha_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extract a YouTube video id from a watch / youtu.be / embed URL.
 *
 * @param string $url
 * @return string Video id, or '' if the URL isn't a recognized YouTube link.
 */
function fcs_sermon_youtube_id( string $url ): string {
	if ( ! preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
		return '';
	}
	return $m[1];
}

/**
 * Print a VideoObject <script type="application/ld+json"> on single sermons.
 */
add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( 'ctc_sermon' ) || ! function_exists( 'ctfw_sermon_data' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		$data    = ctfw_sermon_data( $post_id );
		$video   = isset( $data['video'] ) ? trim( (string) $data['video'] ) : '';

		// Require a real URL (the field can also hold raw embed code) and a
		// thumbnail — without both we can't emit a valid VideoObject.
		if ( '' === $video || ! filter_var( $video, FILTER_VALIDATE_URL ) ) {
			return;
		}
		$thumb = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( ! $thumb ) {
			return;
		}

		$description = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
		if ( '' === $description ) {
			$description = get_the_title( $post_id );
		}

		$schema = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => get_the_title( $post_id ),
			'description'  => $description,
			'thumbnailUrl' => $thumb,
			'uploadDate'   => get_the_date( 'c', $post_id ),
			'url'          => get_permalink( $post_id ),
		);

		// YouTube → player (embed) URL; any other URL → treat as the media file.
		$yt = fcs_sermon_youtube_id( $video );
		if ( '' !== $yt ) {
			$schema['embedUrl'] = 'https://www.youtube.com/embed/' . $yt;
		} else {
			$schema['contentUrl'] = $video;
		}

		echo "\n" . '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	},
	20
);
