<?php
/**
 * Light editor: an "Event details" metabox on fce_event for staff who don't
 * author via the agent. Date/time/venue/registration + a recurrence picker +
 * cancelled dates. Saves through the same fce_write_event() the MCP path uses,
 * so RRULE + the "when" stay derived and consistent.
 *
 * @package FirstChurch\Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FCE_WEEKDAYS = array( 'SU' => 'Sun', 'MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat' );

add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fce-details', __( 'Event details', 'firstchurch-events' ), 'fce_metabox_render', FCE_CPT, 'normal', 'high' );
} );

function fce_metabox_render( WP_Post $post ): void {
	wp_nonce_field( 'fce_save', 'fce_nonce' );
	$f        = fce_recurrence_fields( $post->ID );
	$reg      = (string) get_post_meta( $post->ID, FCE_REGURL, true );
	$skip     = implode( "\n", fce_skip_dates( $post->ID ) );
	$freq     = $f['recurrence'] ?: 'none';
	$days     = array_filter( array_map( 'trim', explode( ',', $f['weekly_days'] ) ) );
	$mo_mode  = $f['monthly_type'] === 'week' ? 'week' : 'day';
	$mo_weeks = array_filter( array_map( 'trim', explode( ',', $f['monthly_week'] ) ) );
	$row      = 'style="margin:0 0 10px;"';
	?>
	<style>.fce-grid label{display:inline-block;margin-right:10px}.fce-sub{margin:6px 0 0 18px}</style>
	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Date', 'firstchurch-events' ); ?></strong><br>
		<input type="date" name="fce_dtstart" value="<?php echo esc_attr( $f['start'] ); ?>"></label>
		&nbsp;&nbsp;<label><strong><?php esc_html_e( 'Time', 'firstchurch-events' ); ?></strong><br>
		<input type="time" name="fce_time" value="<?php echo esc_attr( $f['start_time'] ); ?>"></label></p>
	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Timing note', 'firstchurch-events' ); ?></strong> <span style="color:#666">(<?php esc_html_e( 'optional — for timing a single start can\'t express, e.g. "doors 6, show 7" or "9:30 & 11:00 services"', 'firstchurch-events' ); ?>)</span><br>
		<input type="text" name="fce_time_text" class="widefat" value="<?php echo esc_attr( $f['time_text'] ); ?>"></label></p>
	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Venue', 'firstchurch-events' ); ?></strong><br>
		<input type="text" name="fce_venue" class="widefat" value="<?php echo esc_attr( $f['venue'] ); ?>"></label></p>
	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Registration URL', 'firstchurch-events' ); ?></strong><br>
		<input type="url" name="fce_regurl" class="widefat" value="<?php echo esc_attr( $reg ); ?>"
		       placeholder="https://firstchurchseattle.breezechms.com/form/…"></label></p>

	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Repeats', 'firstchurch-events' ); ?></strong><br>
		<select name="fce_recurrence" id="fce-recurrence">
			<?php foreach ( array( 'none' => 'Does not repeat', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly' ) as $v => $l ) : ?>
				<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $freq, $v ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select></label></p>

	<div class="fce-sub" data-when="weekly">
		<label><?php esc_html_e( 'Every', 'firstchurch-events' ); ?>
			<input type="number" name="fce_interval" min="1" max="52" style="width:4em" value="<?php echo esc_attr( max( 1, $f['weekly_interval'] ) ); ?>">
			<?php esc_html_e( 'week(s) on:', 'firstchurch-events' ); ?></label><br>
		<span class="fce-grid"><?php foreach ( FCE_WEEKDAYS as $code => $label ) : ?>
			<label><input type="checkbox" name="fce_weekdays[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $days, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
		<?php endforeach; ?></span>
	</div>

	<div class="fce-sub" data-when="monthly">
		<label><input type="radio" name="fce_monthly_mode" value="day" <?php checked( $mo_mode, 'day' ); ?>> <?php esc_html_e( 'On the same day-of-month as the date', 'firstchurch-events' ); ?></label><br>
		<label><input type="radio" name="fce_monthly_mode" value="week" <?php checked( $mo_mode, 'week' ); ?>> <?php esc_html_e( 'On the', 'firstchurch-events' ); ?></label>
		<span class="fce-grid"><?php foreach ( array( '1' => '1st', '2' => '2nd', '3' => '3rd', '4' => '4th', 'last' => 'last' ) as $v => $l ) : ?>
			<label><input type="checkbox" name="fce_monthly_weeks[]" value="<?php echo esc_attr( $v ); ?>" <?php checked( in_array( $v, $mo_weeks, true ) ); ?>> <?php echo esc_html( $l ); ?></label>
		<?php endforeach; ?></span>
		<?php esc_html_e( 'weekday of the date', 'firstchurch-events' ); ?>
	</div>

	<p class="fce-sub" data-when="weekly monthly yearly" <?php echo $row; ?>>
		<label><?php esc_html_e( 'Until (optional)', 'firstchurch-events' ); ?> <input type="date" name="fce_until" value="<?php echo esc_attr( $f['end_date'] ); ?>"></label></p>

	<?php $kind_override = (string) get_post_meta( $post->ID, FCE_KIND, true ); ?>
	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Shows as', 'firstchurch-events' ); ?></strong> <span style="color:#666">(<?php esc_html_e( 'how surfaces classify it — Auto follows the Repeats setting', 'firstchurch-events' ); ?>)</span><br>
		<select name="fce_kind">
			<?php
			$kinds = array(
				''       => __( 'Auto (from Repeats)', 'firstchurch-events' ),
				'rhythm' => __( 'Weekly rhythm (standing pattern, e.g. Sunday worship)', 'firstchurch-events' ),
				'group'  => __( 'Group / gathering (ongoing community)', 'firstchurch-events' ),
				'event'  => __( 'Event (time-bound, promotable)', 'firstchurch-events' ),
			);
			foreach ( $kinds as $v => $l ) :
				?>
				<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $kind_override, $v ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select></label></p>

	<p <?php echo $row; ?>><label><strong><?php esc_html_e( 'Cancelled dates', 'firstchurch-events' ); ?></strong> <span style="color:#666">(<?php esc_html_e( 'one YYYY-MM-DD per line', 'firstchurch-events' ); ?>)</span><br>
		<textarea name="fce_skip_dates" rows="2" class="widefat"><?php echo esc_textarea( $skip ); ?></textarea></label></p>

	<script>
	( function () {
		var sel = document.getElementById( 'fce-recurrence' );
		function sync() {
			document.querySelectorAll( '.fce-sub' ).forEach( function ( el ) {
				el.style.display = el.getAttribute( 'data-when' ).split( ' ' ).indexOf( sel.value ) > -1 ? '' : 'none';
			} );
		}
		sel.addEventListener( 'change', sync );
		sync();
	} )();
	</script>
	<?php
}

add_action( 'save_post_' . FCE_CPT, static function ( $post_id ) {
	if ( ! isset( $_POST['fce_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fce_nonce'] ) ), 'fce_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$freq = isset( $_POST['fce_recurrence'] ) ? sanitize_text_field( wp_unslash( $_POST['fce_recurrence'] ) ) : 'none';
	$skip = isset( $_POST['fce_skip_dates'] ) ? preg_split( '/\s+/', sanitize_textarea_field( wp_unslash( $_POST['fce_skip_dates'] ) ), -1, PREG_SPLIT_NO_EMPTY ) : array();

	fce_write_event( (int) $post_id, array(
		'date'             => isset( $_POST['fce_dtstart'] ) ? sanitize_text_field( wp_unslash( $_POST['fce_dtstart'] ) ) : '',
		'time'             => isset( $_POST['fce_time'] ) ? sanitize_text_field( wp_unslash( $_POST['fce_time'] ) ) : '',
		'time_text'        => isset( $_POST['fce_time_text'] ) ? sanitize_text_field( wp_unslash( $_POST['fce_time_text'] ) ) : '',
		'venue'            => isset( $_POST['fce_venue'] ) ? sanitize_text_field( wp_unslash( $_POST['fce_venue'] ) ) : '',
		'registration_url' => isset( $_POST['fce_regurl'] ) ? esc_url_raw( wp_unslash( $_POST['fce_regurl'] ) ) : '',
		'kind'             => isset( $_POST['fce_kind'] ) ? sanitize_text_field( wp_unslash( $_POST['fce_kind'] ) ) : '',
		'skip_dates'       => $skip,
		'recurrence'       => array(
			'frequency'     => 'none' === $freq ? '' : $freq,
			'interval'      => isset( $_POST['fce_interval'] ) ? (int) $_POST['fce_interval'] : 1,
			'weekdays'      => isset( $_POST['fce_weekdays'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['fce_weekdays'] ) ) : array(),
			'monthly_weeks' => ( isset( $_POST['fce_monthly_mode'] ) && 'week' === $_POST['fce_monthly_mode'] && isset( $_POST['fce_monthly_weeks'] ) )
				? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['fce_monthly_weeks'] ) )
				: array(),
			'until'         => isset( $_POST['fce_until'] ) ? sanitize_text_field( wp_unslash( $_POST['fce_until'] ) ) : '',
		),
	) );
} );
