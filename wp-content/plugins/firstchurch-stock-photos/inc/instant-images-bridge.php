<?php
/**
 * Instant Images provenance bridge.
 *
 * We run a dual setup: humans browse stock photos through the Instant Images
 * media-modal tab, while the MCP/agent path uses this plugin's Openverse code.
 * Left alone, the Media library "Source" column would only reflect OUR imports.
 *
 * This bridge hooks Instant Images' documented `instant_images_after_upload`
 * action so images added through II are stamped with the same `_fcsp_*` meta,
 * giving one trustworthy Source column across both paths. Harmless when
 * Instant Images isn't installed — the action simply never fires.
 *
 * II's $args expose: filename, id, title, alt, caption, attachment_id,
 * attachment_url, provider, original_url. It does NOT provide a creator or
 * license, so those stay empty for II uploads; the Source column falls back to
 * the provider name. When the policy attribution template (inc/policy.php) is
 * active, II writes an attribution string into the caption, which we capture.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'instant_images_after_upload',
	static function ( $args ): void {
		if ( ! is_array( $args ) || empty( $args['attachment_id'] ) ) {
			return;
		}
		fcsp_store_provenance(
			(int) $args['attachment_id'],
			array(
				'source'      => $args['provider'] ?? '',
				'foreign_url' => $args['original_url'] ?? '',
				'attribution' => $args['caption'] ?? '',
			)
		);
	}
);
