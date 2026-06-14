<?php
/**
 * Compose an issue body as block markup — the "opens pre-filled, not blank"
 * win (enews-spine.md §4) for issues created over the API/MCP, where the CPT's
 * editor `template` (inc/cpt.php) never fires.
 *
 * It mirrors fcen_issue_block_template() one-for-one: the same headings, the
 * same `firstchurch/happenings` blocks (which self-fill from the spine at render
 * time), the same self-filling `firstchurch/pastoral-letter` block, the same
 * evergreen list and footer — so an MCP-drafted issue previews and pushes to
 * Mailchimp identically to one a human opened in Gutenberg. The one variable is
 * the optional Pastoral Message prose, stored as the pastoral-letter block's
 * fallback (shown only when no recent pastoral-letters post exists).
 *
 * Kept deliberately pure (string building + htmlspecialchars only, no WordPress
 * calls) so it is unit-testable in the plugin's standalone PHPUnit harness; the
 * runtime render path (fcen_render_email) parses this markup with parse_blocks /
 * render_block. When you change the composition, change it in BOTH this file and
 * fcen_issue_block_template() (cpt.php).
 *
 * @package FirstChurch\ENews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The default issue body, as serialized Gutenberg block markup.
 *
 * @param string $pastoral_message Optional prose for the "From the Pastor" block.
 * @return string Block markup suitable for enews_issue post_content.
 */
function fcen_compose_issue_body( string $pastoral_message = '' ): string {
	$blocks = array(
		// Bucket C: the "From the Pastor" slot — a self-filling block (the latest
		// pastoral-letters post within ~5 days), with any supplied prose kept as the
		// fallback used when there is no recent letter. No heading: the email hoists
		// this into its own letter slot above the worship buttons (enews-spine.md §9).
		fcen_compose_pastoral_letter( $pastoral_message ),

		// The week's lead highlight (a featured event or announcement).
		fcen_compose_heading( "This Week\u{2019}s Highlight" ),
		fcen_compose_happenings( array( 'section' => 'featured', 'count' => 1 ) ),

		// Everything happening this week (events), minus the highlight.
		fcen_compose_heading( 'This Week at First Church' ),
		fcen_compose_happenings( array( 'section' => 'events', 'weeks' => 1, 'excludeFeatured' => true ) ),

		// Recent announcements / news & notes.
		fcen_compose_heading( 'News &amp; Notes' ),
		fcen_compose_happenings( array( 'section' => 'announcements', 'days' => 7, 'excludeFeatured' => true ) ),

		// Bucket B: evergreen recurring ministries (editable furniture for now).
		fcen_compose_heading( 'Recurring at First Church' ),
		"<!-- wp:list -->\n<ul class=\"wp-block-list\"></ul>\n<!-- /wp:list -->",

		// Fixed footer furniture (Bucket C).
		"<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->",
		"<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"small\"} -->\n"
			. "<p class=\"has-text-align-center has-small-font-size\">E-news deadline: Tuesdays at noon \u{00B7} comms@firstchurchseattle.org</p>\n"
			. "<!-- /wp:paragraph -->",
	);

	return implode( "\n\n", $blocks );
}

/**
 * A self-closing firstchurch/pastoral-letter block. The block self-fills from the
 * latest pastoral-letters post at render time; any prose supplied here is stored
 * as the `fallback` attribute (used only when no recent letter exists). The prose
 * is a stored attribute, escaped at render — not HTML-escaped into the markup.
 *
 * @param string $fallback Optional fallback prose for the "no recent letter" case.
 */
function fcen_compose_pastoral_letter( string $fallback = '' ): string {
	$attrs    = array( 'days' => 5 );
	$fallback = trim( $fallback );
	if ( '' !== $fallback ) {
		$attrs['fallback'] = $fallback;
	}
	$json = fcen_block_attrs_json( $attrs );
	return "<!-- wp:firstchurch/pastoral-letter {$json} /-->";
}

/** A level-2 heading block. @param string $html Pre-escaped inner HTML. */
function fcen_compose_heading( string $html ): string {
	return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$html}</h2>\n<!-- /wp:heading -->";
}

/**
 * A self-closing firstchurch/happenings block.
 *
 * @param array<string,mixed> $attrs Block attributes (section/count/weeks/days/excludeFeatured).
 */
function fcen_compose_happenings( array $attrs ): string {
	$json = fcen_block_attrs_json( $attrs );
	return "<!-- wp:firstchurch/happenings {$json} /-->";
}

/**
 * JSON for a block-comment attribute payload. Uses wp_json_encode when present
 * (runtime), falls back to json_encode with matching flags (standalone tests).
 *
 * @param array<string,mixed> $data Attributes.
 */
function fcen_block_attrs_json( array $data ): string {
	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
	if ( function_exists( 'wp_json_encode' ) ) {
		return (string) wp_json_encode( $data, $flags );
	}
	return (string) json_encode( $data, $flags );
}
