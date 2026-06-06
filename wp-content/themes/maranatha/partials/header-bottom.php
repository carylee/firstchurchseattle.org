<?php
/**
 * Header Bottom
 *
 * Output breadcrumb (left) and archive dropdowns (right). Not on homepage.
 *
 * This is loaded by header.php.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Not on homepage
if ( is_page_template( CTFW_THEME_PAGE_TPL_DIR . '/homepage.php' ) ) {
	return;
}

// Get breadcrumbs
$breadcrumbs = maranatha_breadcrumbs( 'content' );

// Get content type
$content_type = ctfw_current_content_type();

// Get archives data for current section (content type)
$archives = ctfw_content_type_archives( array(
	'content_type' => $content_type,
) );

// Number of terms to show before link to taxonomy's index page
$max_archives = apply_filters( 'maranatha_archive_dropdowns_max', 12 );

// Map content type and archive keys to page templates for creating links in dropdowns to index pages
$page_templates = array(
	'sermon' => array(
		'ctc_sermon_topic'		=> 'sermon-topics.php',
		'ctc_sermon_series'		=> 'sermon-series.php',
		'ctc_sermon_book'		=> 'sermon-books.php',
		'ctc_sermon_speaker'	=> 'sermon-speakers.php',
		'months'				=> 'sermon-dates.php',
	),
);
$page_templates = isset( $page_templates[$content_type] ) ? $page_templates[$content_type] : array();

// Add event views to archive dropdowns
if ( $archives && 'event' == $content_type ) {

	$calendar_url = ctfw_get_page_url_by_template( 'events-calendar.php' );
	$upcoming_url = ctfw_get_page_url_by_template( 'events-upcoming.php' );
	$past_url = ctfw_get_page_url_by_template( 'events-past.php' );

	$dropdown['view'] = array();
	$dropdown['view']['type'] = 'custom';
	$dropdown['view']['name'] = _x( 'Views', 'event view', 'maranatha' );

	$items = array();
	$i = -1; // $i++ will start keys at 0

	if ( $calendar_url ) {
		$i++;
		$items[$i] = new stdClass();
		$items[$i]->name = _x( 'Calendar', 'event view', 'maranatha' );
		$items[$i]->url = $calendar_url;
	}

	if ( $upcoming_url ) {
		$i++;
		$items[$i] = new stdClass();
		$items[$i]->name = _x( 'List &mdash; Upcoming', 'event view', 'maranatha' );
		$items[$i]->url = $upcoming_url;
	}

	if ( $past_url ) {
		$i++;
		$items[$i] = new stdClass();
		$items[$i]->name = _x( 'List &mdash; Past', 'event view', 'maranatha' );
		$items[$i]->url = $past_url;
	}

	// Have at least 2 items
	if ( count( $items ) >= 2 ) {
		$dropdown['view']['items'] = $items;
		$archives = array_merge( $dropdown, $archives );
	}

}

// Have breadcrumbs and archives
if ( $breadcrumbs || $archives ) :

	$classes = array();

	if ( $breadcrumbs ) {
		$classes[] = 'maranatha-has-breadcrumbs';
	}

	if ( $archives ) {
		$classes[] = 'maranatha-has-header-archives';
	}

	$classes = implode( ' ', $classes );

?>

	<div id="maranatha-header-bottom" class="<?php echo esc_attr( $classes ); ?>">

		<div id="maranatha-header-bottom-inner" class="maranatha-centered-large maranatha-clearfix">

			<?php echo $breadcrumbs; ?>

			<?php if ( $archives ) : ?>

				<ul id="maranatha-header-archives">

					<?php

					$section_post_types = ctfw_current_content_type_data( 'post_types' );

					// Post type exists.
					if ( isset( $section_post_types[0] ) && post_type_exists( $section_post_types[0] ) ) {

						$post_type_obj = get_post_type_object( $section_post_types[0] );
						$section_name = $post_type_obj->labels->name;
						$section_page_templates = ctfw_current_content_type_data( 'page_templates' );
						$section_url = isset( $section_page_templates[0] ) ? ctfw_get_page_url_by_template( $section_page_templates[0] ): '';

						?>

						<li id="maranatha-header-archives-section-name" class="maranatha-header-archive-top">

							<?php if ( $section_url ) : ?>
								<a href="<?php echo esc_url( $section_url ); ?>"><?php echo esc_html( $section_name ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $section_name ); ?>
							<?php endif; ?>

						</li>

						<?php

					}

					// Get last key
					end( $archives );
					$last_archive_key = key( $archives );

					// Loop archives
					foreach ( $archives as $archive_key => $archive ) :

						// Has page using an index page template
						$all_url = isset( $page_templates[$archive_key] ) ? ctfw_get_page_url_by_template( $page_templates[$archive_key] ) : '';

						// Reduce number of terms shown
						// Do this if have index page to link to or if is months dropdown (sermons, blog, events) or is blog tags (that can get huge, so show most used only)
						if ( $all_url || 'months' == $archive_key || 'post_tag' == $archive_key ) {
							$archive['items'] = array_slice( $archive['items'], 0, $max_archives );
						}
					?>

						<?php if ( $archive['name'] && ! empty( $archive['items'] ) ) : // not empty ?>

							<li class="maranatha-header-archive-top">

								<a href="#" class="maranatha-header-archive-top-name">
									<?php echo esc_html( $archive['name'] ) ?>
									<span class="<?php echo maranatha_get_icon_class( 'archive-dropdown' ); ?>"></span>
								</a>

								<div id="maranatha-header-<?php echo esc_attr( ctfw_make_friendly( $archive_key ) ); ?>-dropdown" class="maranatha-header-archive-dropdown maranatha-dropdown<?php echo ( $last_archive_key == $archive_key ? ' maranatha-dropdown-anchor-right' : '' ) ?>">

		  							<div class="maranatha-dropdown-panel">

										<ul class="maranatha-header-archive-list">

											<?php foreach ( $archive['items'] as $archive_item ) : ?>

												<li>

													<a href="<?php echo esc_url( $archive_item->url ); ?>" title="<?php echo esc_attr( $archive_item->name ); ?>"><?php echo esc_html( $archive_item->name ); ?></a>

													<?php if ( isset( $archive_item->count ) ) : ?>
														<span class="maranatha-header-archive-dropdown-count"><?php echo esc_html( $archive_item->count ); ?></span>
													<?php endif; ?>

												</li>

											<?php endforeach; ?>

											<?php if ( $all_url ) : ?>

												<li class="maranatha-header-archive-dropdown-all">

													<a href="<?php echo esc_url( $all_url ); ?>">
														<?php
														printf(
															/* translators: %s is archive dropdown name (Topics, Series, etc.) */
															_x( 'All %s', 'archive dropdown', 'maranatha' ),
															esc_html( $archive['name'] )
														);
														?>
													</a>

												</li>

											<?php endif; ?>

										</ul>

									</div>

								</div>

							</li>

						<?php endif; ?>

					<?php endforeach; ?>

				</ul>

			<?php endif; ?>

		</div>

	</div>

<?php endif; ?>