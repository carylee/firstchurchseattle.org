<?php
/**
 * Render an enews_issue to email-safe HTML, and a staff-only browser preview.
 *
 * The issue's body is a mix of editorial blocks (headings, the Pastoral Message
 * paragraph, a list, a separator) and `firstchurch/happenings` blocks. This
 * walks those blocks: the happenings blocks become email cards drawn from the
 * SAME spine lens (happenings_section_items) the /engage web block uses — so the
 * email and the website agree on every section's contents — and every other
 * block renders through WordPress's own block renderer. The result is wrapped in
 * the email scaffold with the issue envelope (subject / preview / date).
 *
 * The pure markup (cards + scaffold) lives in src/Email.php (unit-tested); this
 * is the WordPress glue that feeds it.
 *
 * @package FirstChurch\ENews
 */

use FirstChurch\ENews\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The full email document for an issue.
 *
 * @param int $post_id enews_issue id.
 * @return string Email-safe HTML ('' if not an issue).
 */
function fcen_render_email( int $post_id ): string {
	$post = get_post( $post_id );
	if ( ! $post || FCEN_CPT !== $post->post_type ) {
		return '';
	}

	$inner = '';
	foreach ( parse_blocks( $post->post_content ) as $block ) {
		$name = $block['blockName'] ?? null;
		if ( null === $name ) {
			continue; // freeform whitespace between blocks
		}
		if ( 'firstchurch/happenings' === $name ) {
			$inner .= fcen_render_happenings_email( $block['attrs'] ?? array() );
		} else {
			// Editorial blocks render to clean semantic HTML; the scaffold sets the
			// base font around them.
			$inner .= render_block( $block );
		}
	}

	$subject = (string) get_post_meta( $post_id, FCEN_SUBJECT_KEY, true );
	$env     = array(
		'subject' => '' !== $subject ? $subject : get_the_title( $post ),
		'preview' => (string) get_post_meta( $post_id, FCEN_PREVIEW_KEY, true ),
		'date'    => (string) get_post_meta( $post_id, FCEN_DATE_KEY, true ),
		'footer'  => fcen_email_footer(),
	);

	return Email::document( $inner, $env );
}

/**
 * The church footer: social + past-issues + copyright (mirroring the live
 * newsletter) plus the Mailchimp merge tags (unsubscribe / update preferences /
 * physical address) so a pushed draft is send-ready. The merge tags resolve when
 * Mailchimp sends; in the browser preview they show literally. Filterable via
 * `fcen_email_footer_html`.
 */
function fcen_email_footer(): string {
	$maroon = '#7a1f2b';
	$social = array(
		'Facebook'              => 'https://www.facebook.com/firstchurchseattle',
		'Instagram'             => 'https://www.instagram.com/firstchurchseattle/',
		'firstchurchseattle.org' => 'https://firstchurchseattle.org/',
	);
	$past = 'https://us2.campaign-archive.com/home/?u=18291af87fbc7224df67d6ab8&id=24fee5f80d';
	$year = (string) current_time( 'Y' );

	$links = array();
	foreach ( $social as $label => $url ) {
		$links[] = '<a href="' . esc_url( $url ) . '" style="color:' . $maroon . ';">' . esc_html( $label ) . '</a>';
	}

	$html  = '<p style="margin:0 0 8px;">' . implode( ' &middot; ', $links ) . '</p>';
	$html .= '<p style="margin:0 0 8px;"><a href="' . esc_url( $past ) . '" style="color:' . $maroon . ';">View past issues</a></p>';
	$html .= '<p style="margin:0 0 8px;">Copyright &copy; ' . esc_html( $year ) . ' First Church Seattle. All rights reserved.</p>';
	// Mailchimp-resolved at send (literal in preview); required for a sendable draft.
	$html .= '<p style="margin:0;">*|HTML:LIST_ADDRESS_HTML|*<br>'
		. '<a href="*|UPDATE_PROFILE|*" style="color:#666666;">Update preferences</a> &middot; '
		. '<a href="*|UNSUB|*" style="color:#666666;">Unsubscribe</a></p>';

	return (string) apply_filters( 'fcen_email_footer_html', $html );
}

/**
 * One `firstchurch/happenings` block as a stack of email cards, resolved through
 * the shared spine lens so it matches the web block exactly.
 *
 * @param array<string,mixed> $attrs Block attributes (section/count/weeks/days/excludeFeatured).
 */
function fcen_render_happenings_email( array $attrs ): string {
	if ( ! function_exists( 'happenings_section_items' ) || ! function_exists( 'happenings_card_view' ) ) {
		return ''; // spine inactive — fail soft.
	}

	$section = isset( $attrs['section'] ) ? (string) $attrs['section'] : 'featured';
	$count   = max( 1, (int) ( $attrs['count'] ?? 3 ) );
	$weeks   = max( 1, (int) ( $attrs['weeks'] ?? 8 ) );
	$days    = max( 1, (int) ( $attrs['days'] ?? 30 ) );

	$items = happenings_section_items( $section, $count, $weeks, $days, ! empty( $attrs['excludeFeatured'] ) );

	$html = '';
	foreach ( $items as $item ) {
		// Same meta-suppression rule as the web card (happenings.md §4): a featured
		// announcement hides its publish date; an event always shows its when-line.
		$show_meta = ( 'featured' !== $section ) || ( 'event' === ( $item['source'] ?? '' ) );
		$html     .= Email::card( happenings_card_view( $item ), $show_meta );
	}

	return $html;
}

/* ---- Staff preview ---------------------------------------------------------
 * An admin-post endpoint (not a front-end route) so it works for DRAFT issues
 * too and is never publicly reachable: it renders the email HTML for one issue,
 * gated by edit-cap + nonce.
 */

add_action( 'admin_post_fcen_email_preview', 'fcen_email_preview' );

function fcen_email_preview(): void {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to preview this issue.', 'firstchurch-enews' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'fcen_email_preview_' . $post_id );

	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo fcen_render_email( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Email::* escapes all dynamic text internally.
	exit;
}

/** A nonced "Preview email" link to that endpoint. @param int $post_id */
function fcen_email_preview_url( int $post_id ): string {
	return wp_nonce_url(
		admin_url( 'admin-post.php?action=fcen_email_preview&post=' . $post_id ),
		'fcen_email_preview_' . $post_id
	);
}

/**
 * Surface the preview link on the issues list table — a Gutenberg-safe path
 * (unlike post_submitbox_misc_actions, which only fires in the classic editor).
 * The in-editor affordance lives in the E-News Settings meta box (inc/meta.php),
 * which Gutenberg renders in the sidebar.
 *
 * @param array<string,string> $actions Row actions.
 * @param WP_Post              $post    The row's post.
 * @return array<string,string>
 */
add_filter( 'post_row_actions', 'fcen_row_action_preview', 10, 2 );

function fcen_row_action_preview( $actions, $post ) {
	if ( $post instanceof WP_Post && FCEN_CPT === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
		$actions['fcen_preview'] = '<a href="' . esc_url( fcen_email_preview_url( $post->ID ) ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Preview email', 'firstchurch-enews' ) . '</a>';
	}
	return $actions;
}

