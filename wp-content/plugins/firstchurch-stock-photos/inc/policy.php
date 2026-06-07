<?php
/**
 * Instant Images policy-as-code.
 *
 * Instant Images stays installed as the human-facing picker, but its settings
 * otherwise live on prod where they drift (and a church site shouldn't ship a
 * mature-content toggle that depends on someone remembering to flip it). These
 * filters bake our choices into code instead:
 *
 *   - force safe search / exclude mature content across every provider, and
 *   - apply one attribution template so credit lands in the caption (which the
 *     provenance bridge then mirrors into _fcsp_attribution).
 *
 * All values are constants for one-line tuning. Every hook here is an Instant
 * Images filter — harmless no-ops when II isn't installed.
 */

defined( 'ABSPATH' ) || exit;

// Unsplash content_filter level: 'low' or 'high'. 'high' is strictest.
const FCSP_II_UNSPLASH_CONTENT_FILTER = 'high';

// Attribution written into the image caption on upload. Instant Images template
// tags: {username} {user_url} {provider} {provider_url} {image_url}.
const FCSP_II_ATTRIBUTION = 'Photo by {username} on {provider}';

add_filter( 'instant_images_unsplash_content_filter', static fn() => FCSP_II_UNSPLASH_CONTENT_FILTER );
add_filter( 'instant_images_openverse_mature', '__return_false' );   // exclude mature on Openverse
add_filter( 'instant_images_pixabay_safesearch', '__return_true' );  // enable Pixabay safe search
add_filter( 'instant_images_attribution', static fn() => FCSP_II_ATTRIBUTION );
