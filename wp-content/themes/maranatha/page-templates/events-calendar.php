<?php
/**
 * Template Name: Events - Calendar
 *
 * This shows a page with monthly calendar.
 *
 * See theme support feature for 'ctfw-event-calendar-redirection'
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Prepare calendar content to output
function maranatha_calendar_after_content() {

	global $post;

	// Event post type must be supported.
	if ( ! post_type_exists( 'ctc_event' ) ) {
		return;
	}

	// Get month/year and category from URL
	$year_month_query = isset( $_GET['month'] ) ? $_GET['month'] : ''; // default will be this month
	$category_query = isset( $_GET['category'] ) ? $_GET['category'] : ''; // default is empty (all)

	// Get calendar data
	$calendar = ctfw_event_calendar_data( array(
		'year_month' => $year_month_query,
		'category' => $category_query,
	) );

	// Get month data
	// $year_month, $year, $month (without leading 0), $month_ts (first day of month), $prev_month, $next_month
	extract( $calendar['month_data'] );
	$weeks_in_month = count( $calendar['weeks'] );

	// Get today
	// Useful for highlighting current day on calendar
	$today = date_i18n( 'Y-m-d' );
	//$today = '2015-09-26'; // debug
	$today_ts = strtotime( $today );
	$today_month = date_i18n( 'Y-m', $today_ts );
	$today_month_ts = strtotime( $today_month );

	// This year and month
	$this_year = date_i18n( 'Y' );
	$this_month = date_i18n( 'm', $today_ts );
	$this_year_month = date_i18n( 'Y-m', $today_ts );
	$this_month_ts = strtotime( $this_year_month );

	// Next year
	// This limits 'Next' button and month selector
	// Users can only view months through the next full year
	$next_year = $this_year + 1;

	// Month name
	/* translators: F is month; Y is year for calendar title (uses PHP date format) */
	$month_name = date_i18n( _x( 'F Y', 'event calendar', 'maranatha' ), $month_ts );

	// Date format
	$date_format = get_option( 'date_format' );

	// Category handling
	$categories = array();
	$category = array();
	$category_chosen = false;
	$categories_enabled = false; // taxonomy supported and there are categories with events
	if ( function_exists( 'ctc_taxonomy_supported' ) && ctc_taxonomy_supported( 'events', 'ctc_event_category' ) ) { // theme may not support it; or old plugin version

		// Get categories (excludes those w/no posts by default)
		// Used for showing dropdown control
		$categories = get_terms( 'ctc_event_category', array(
			'parent' => 0, // top-level categories only
		) );

		// Have at least one category?
		// If so then we can show the category selector
		if ( $categories ) {

			// Category selector can be shown
			$categories_enabled = true;

			// Get category slug from URL
			$category_slug = isset( $_GET['category'] ) ? trim( $_GET['category'] ) : '';

			// Show a specific category
			if ( $category_slug ) {

				// Get taxonomy yerm
				$category = get_term_by( 'slug', $category_slug, 'ctc_event_category', ARRAY_A );

				// Have term and name
				if ( ! empty( $category ) && ! empty( $category['name'] ) ) {

					// Category filter is being used
					$category_chosen = true;

					// Get name
					$category_name = $category['name'];

				}

			}

		}

	}

	?>

	<section id="maranatha-calendar">

		<div id="maranatha-calendar-loading"></div>

		<div id="maranatha-calendar-month">

			<div id="maranatha-calendar-header">

				<div id="maranatha-calendar-header-left">

					<h2 id="maranatha-calendar-title">

						<span><?php echo $month_name; ?></span>

						<?php if ( $category_chosen ) : ?>
							<span id="maranatha-calendar-title-category">

								<?php echo $category_name; ?>

								<?php if ( $category_chosen ) : ?>
									<span id="maranatha-calendar-remove-category">
										<a href="<?php
											// Note: _pjax is removed because it is not needed (this is coming from AJAX request, which is already cached)
											// Explanation : https://github.com/defunkt/jquery-pjax/issues/89
											echo esc_url( remove_query_arg( array( 'category', '_pjax' ) ) );
										?>" class="<?php maranatha_icon_class( 'calendar-remove' ); ?> maranatha-calendar-control" title="<?php echo esc_attr( _x( 'Remove Category Filter', 'event calendar', 'maranatha' ) ); ?>"></a>
									</span>
								<?php endif; ?>

							</span>
						<?php endif; ?>

					</h2>

				</div>

				<ul id="maranatha-calendar-header-right" class="maranatha-list-icons">

					<?php if ( $prev_month_ts >= $today_month_ts ) : ?>
						<li><a href="<?php echo esc_url( remove_query_arg( '_pjax', add_query_arg( 'month', $prev_month ) ) ); ?>" class="maranatha-calendar-control <?php maranatha_icon_class( 'calendar-prev' ); ?>" title="<?php echo esc_attr( sprintf( __( 'Previous Month &ndash; %1$s', 'maranatha' ), date_i18n( 'F', $prev_month_ts ) ) ); ?>"></a></li>
					<?php endif; ?>

					<?php if ( $year < $next_year || $month < 12 ) : ?>
						<li><a href="<?php echo esc_url( remove_query_arg( '_pjax', add_query_arg( 'month', $next_month ) ) ); ?>" class="maranatha-calendar-control <?php maranatha_icon_class( 'calendar-next' ); ?>" title="<?php echo esc_attr( sprintf( __( 'Next Month &ndash; %1$s', 'maranatha' ), date_i18n( 'F', $next_month_ts ) ) ); ?>"></a></li>
					<?php endif; ?>

					<li><a href="#" id="maranatha-calendar-month-control" class="maranatha-calendar-dropdown-control <?php maranatha_icon_class( 'calendar-month' ); ?>" title="<?php echo esc_attr( _x( 'Choose Month', 'event calendar', 'maranatha' ) ); ?>"></a></li>

					<?php if ( $categories_enabled ) : ?>
						<li><a href="#" id="maranatha-calendar-category-control" class="maranatha-calendar-dropdown-control <?php maranatha_icon_class( 'calendar-category' ); ?>" title="<?php echo esc_attr( _x( 'Choose Category', 'event calendar', 'maranatha' ) ); ?>"></a></li>
					<?php endif; ?>

				</ul>

			</div>

			<table id="maranatha-calendar-table">

				<tr class="maranatha-calendar-table-top-row">
					<td colspan="7">
						<div class="maranatha-calendar-table-top"></div>
					</td>
				</tr>

				<tr class="maranatha-calendar-table-header-row">

					<?php foreach ( $calendar['days_of_week'] as $day_of_week ) : ?>

						<th class="maranatha-calendar-table-header">
							<div class="maranatha-calendar-table-header-content">
								<span class="maranatha-calendar-table-header-full"><?php echo $day_of_week['name']; ?></span>
								<span class="maranatha-calendar-table-header-short"><?php echo $day_of_week['name_short']; ?></span>
							</div>
						</th>

					<?php endforeach; ?>

				</tr>

				<?php foreach ( $calendar['weeks'] as $week_of_month => $week ) : ?>

					<tr class="<?php

							$week_classes = array();
							$week_classes[] = 'maranatha-calendar-table-week';

							// First week
							if ( 0 == $week_of_month ) {
								$week_classes[] = 'maranatha-calendar-table-week-first';
							}

							// Last week
							if ( $weeks_in_month == ( $week_of_month + 1 ) ) {
								$week_classes[] = 'maranatha-calendar-table-week-last';
							}

							echo implode( ' ', $week_classes );

						?>">

						<?php foreach ( $week['days'] as $day_of_week => $day ) : ?>

							<?php
							$day_of_week_friendly = $day_of_week + 1; // 1 - 7
							$is_today = $today == $day['date'] ? true : false;
							$is_yesterday = date_i18n( 'Y-m-d', $today_ts - DAY_IN_SECONDS ) == $day['date'] ? true : false;
							$is_tomorrow = date_i18n( 'Y-m-d', $today_ts + DAY_IN_SECONDS ) == $day['date'] ? true : false;
							$is_day_previous_week = date_i18n( 'Y-m-d', $today_ts - WEEK_IN_SECONDS ) == $day['date'] ? true : false;
							$is_day_next_week = date_i18n( 'Y-m-d', $today_ts + WEEK_IN_SECONDS ) == $day['date'] ? true : false;
							$is_last_day_of_week = 6 == $day_of_week ? true : false;
							?>

							<td class="<?php

							$day_classes = array();
							$day_classes[] = 'maranatha-calendar-table-day';

							// Day of week (1 - 7)
							$day_classes[] = 'maranatha-calendar-table-day-' . $day_of_week_friendly;

							// Day is in previous or next month
							if ( $day['other_month'] ) {
								$day_classes[] = 'maranatha-calendar-table-day-other-month';
							}

							// Day is today
							if ( $is_today ) {
								$day_classes[] = 'maranatha-calendar-table-day-today';
							}

							// Day is yesterday
							if ( $is_yesterday ) {
								$day_classes[] = 'maranatha-calendar-table-day-yesterday';
							}

							// Day is tomorrow
							if ( $is_tomorrow ) {
								$day_classes[] = 'maranatha-calendar-table-day-tomorrow';
							}

							// Day is previous week
							if ( $is_day_previous_week ) {
								$day_classes[] = 'maranatha-calendar-table-day-previous-week';
							}

							// Day is next week
							if ( $is_day_next_week ) {
								$day_classes[] = 'maranatha-calendar-table-day-next-week';
							}

							// Day is last day of week
							if ( $is_last_day_of_week ) {
								$day_classes[] = 'maranatha-calendar-table-day-last-of-week';
							}

							// Day is in past
							if ( $day['date_ts'] < $today_ts ) {
								$day_classes[] = 'maranatha-calendar-table-day-past';
							}

							// Has events
							if ( ! empty( $day['event_ids'] ) ) {
								$day_classes[] = 'maranatha-calendar-table-day-has-events';
							}

							echo implode( ' ', $day_classes );

						?>" data-date="<?php echo esc_attr( $day['date'] ); ?>" data-date-formatted="<?php echo esc_attr( $day['date_formatted'] ); ?>">

								<div class="maranatha-calendar-table-day-content-container">

									<div class="maranatha-calendar-table-day-content">

										<div class="maranatha-calendar-table-day-heading">

											<?php if ( $day['first_of_next_month'] || $day['last_of_previous_month'] ) : ?>
												<span class="maranatha-calendar-table-day-label"><?php echo date_i18n( 'M', $day['date_ts'] ); ?></span>
											<?php elseif ( $is_today ) : ?>
												<span class="maranatha-calendar-table-day-label"><?php _ex( 'Today', 'event calendar', 'maranatha' ); ?></span>
											<?php endif; ?>

											<span class="maranatha-calendar-table-day-number">
												<?php echo $day['day']; ?>
											</span>

											<a href="#" class="maranatha-calendar-table-day-number">
												<?php echo $day['day']; ?>
											</a>

										</div>

										<?php if ( $day['event_ids'] && $day['date_ts'] >= $today_ts ) : ?>

											<ul class="maranatha-calendar-table-day-events">

												<?php foreach ( $day['event_ids'] as $event_id ) : ?>

													<?php

													// Get event
													$event = $calendar['events'][$event_id];

													// Setup post data
													$post = $event['post'];
													setup_postdata( $post );

													// Extract friendly meta data
													extract( $event['data'] );

													?>

													<li data-event-id="<?php the_ID(); ?>">

														<a href="<?php the_permalink(); ?>" data-event-id="<?php echo the_ID(); ?>"><?php the_title(); ?></a>

														<?php if ( $time_range_or_description ) : ?>
															<span class="maranatha-calendar-table-day-event-time"><?php echo esc_html( $time_range_or_description ); ?></span>
														<?php endif; ?>

													</li>

												<?php endforeach; ?>

												<?php wp_reset_postdata(); ?>

											</ul>

										<?php endif; ?>

									</div>

								</div>

							</td>

						<?php endforeach; ?>

					</tr>

				<?php endforeach; ?>

			</table>

			<!-- dropdown controls -->

			<div id="maranatha-calendar-month-dropdown" class="maranatha-dropdown maranatha-calendar-dropdown maranatha-dropdown-anchor-right">

		  		<div class="maranatha-dropdown-panel">

			  		<?php
					// Show current and future months through end of next year
					for ( $year = $this_year; $year <= $next_year; $year++ ) :
					?>

			  			<div class="maranatha-calendar-month-dropdown-year"><?php echo $year; ?></div>

			  			<ul class="maranatha-calendar-month-dropdown-months">

				  			<?php for ( $month = 1; $month <= 12; $month ++ ) : ?>

				  				<?php

				  				$month_ts = mktime( 0, 0, 0, $month, 1, $year );
				  				$dropdown_year_month = date_i18n( 'Y-m', $month_ts );

				  				// Is month in past?
				  				$month_is_past = false;
				  				if ( $month_ts < $this_month_ts ) {
				  					$month_is_past = $month_ts < $this_month_ts;
				  				}

				  				// Hide rows in which all months are past
				  				// This assumes 4 months are shown per row
				  				if ( $year == $this_year && ( ( $this_month >= 5 && $month < 5 ) || ( $this_month >= 9 && $month < 9 ) ) ) {
				  					continue;
				  				}

				  				?>

				  				<li<?php

				  					$classes = array();

				  					// Month is currently selected
					  				if ( $dropdown_year_month == $year_month ) {
					  					$classes[] = 'maranatha-dropdown-selected';
					  				}

					  				// Month is in past
				  					if ( $month_is_past ) {
					  					$classes[] = 'maranatha-calendar-month-dropdown-past';
				  					}

					  				if ( $classes ) {
					  					echo ' class="' . implode( ' ', $classes ) . '"';
					  				}

				  				?>>

				  					<?php if ( ! $month_is_past ) : ?>
										<a href="<?php echo esc_url( remove_query_arg( '_pjax', add_query_arg( 'month', $dropdown_year_month ) ) ); ?>">
									<?php endif; ?>

										<?php echo date_i18n( 'M', $month_ts ); ?>

				  					<?php if ( ! $month_is_past ) : ?>
										</a>
									<?php endif; ?>

				  				</li>

				  			<?php endfor; ?>

				  		</ul>

			  		<?php endfor; ?>

				</div>

			</div>

			<?php if ( ! empty( $categories ) ) : ?>

				<div id="maranatha-calendar-category-dropdown" class="maranatha-dropdown maranatha-calendar-dropdown maranatha-dropdown-anchor-right">

					<ul class="maranatha-dropdown-menu">

						<li<?php if ( empty( $category ) ) : ?> class="maranatha-dropdown-selected"<?php endif; ?>>
							<a href="<?php echo esc_url( remove_query_arg( array( 'category', '_pjax' ) ) ); ?>">
								<?php _ex( 'All Categories', 'event calendar', 'maranatha' ); ?>
							</a>
						</li>

						<?php foreach ( $categories as $dropdown_category ) : ?>

			  				<li<?php

			  					$classes = array();

			  					// Category is currently selected
				  				if ( isset( $category['slug'] ) && $category['slug'] == $dropdown_category->slug ) {
				  					$classes[] = 'maranatha-dropdown-selected';
				  				}

				  				if ( $classes ) {
				  					echo ' class="' . implode( ' ', $classes ) . '"';
				  				}

			  				?>>

								<a href="<?php echo esc_url( remove_query_arg( '_pjax', add_query_arg( 'category', $dropdown_category->slug ) ) ); ?>">
									<?php echo esc_html( $dropdown_category->name ); ?>
								</a>

							</li>

						<?php endforeach; ?>

					</ul>

				</div>

			<?php endif; ?>

		</div>

		<section id="maranatha-calendar-list">

			<h3 id="maranatha-calendar-list-heading" class="screen-reader-text"><?php _ex( 'Events', 'calendar', 'maranatha' ); ?></h3>

			<?php

			// List events to show in hover or mobile view
			foreach ( $calendar['events'] as $event ) :

				// Setup post data
				$post = $event['post'];
				setup_postdata( $post );

				// Load partial to output HTML
				get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-event-short' );

			endforeach;

			wp_reset_postdata();

			?>

		</section>

	</section>

	<?php

}

// Insert content after the_content() in content.php
add_action( 'maranatha_after_content', 'maranatha_calendar_after_content' );

// Load main template to show the page
locate_template( 'index.php', true );
