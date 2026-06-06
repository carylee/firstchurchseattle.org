<?php
/**
 * Header Logo
 *
 * This is loaded by header.php.
 */

// No direct access
if (! defined( 'ABSPATH' )) exit;

global $post;

?>

<div id="maranatha-logo">

	<div id="maranatha-logo-content">

		<?php

		// Text Logo
		if ('text' == ctfw_customization( 'logo_type' ) || ! ctfw_customization( 'logo_image' )) : // or no logo image specified

		?>

			<div id="maranatha-logo-text" class="maranatha-logo-text-<?php echo esc_attr( ctfw_customization( 'logo_text_size' ) ); ?>">
				<div id="maranatha-logo-text-inner">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php echo nl2br( esc_html( wptexturize( strip_tags( ctfw_customization( 'logo_text' ) ) ) ) ); ?>
					</a>
				</div>
			</div>

		<?php

		// Image Logo
		else :

		?>

			<?php

			// Get logo URL(s)
			$logo_url = ctfw_customization( 'logo_image' ); // uploaded logo
			$logo_hidpi_url = ctfw_customization( 'logo_hidpi' ); // Retina version, if uploaded
			$logo_width = '';
			$logo_height = '';

			// Ensure logo image is not displayed beyond max recommended dimensions
			// This keeps the height from being too tall on mobile or sticky scroll
			// It also solves some issues with mobile that CSS alone is insufficient for
			$logo_size_atts = '';
			if (ctfw_is_local_url( $logo_url )) {

				// Ensure logo URL uses https if site uses SSL
				// When a site switches to SSL, the saved logo URL is still often http://
				if (is_ssl()) {
					$logo_url = str_replace('http://', 'https://', $logo_url);
					$logo_hidpi_url = str_replace('http://', 'https://', $logo_hidpi_url);
				}

				// Get path to file
				$upload_dir = wp_upload_dir();
				$logo_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $logo_url ); // remove base URL

				// File exists
				if (file_exists( $logo_path )) {

					// Get logo size
					$logo_size = getimagesize( $logo_path );
					if ($logo_size) {

						$original_logo_width = $logo_size[0];
						$original_logo_height = $logo_size[1];
						$logo_width = $original_logo_width;
						$logo_height = $original_logo_height;

						$logo_max_width = maranatha_logo_size( 'max_width' ); // set in includes/images.php
						$logo_max_height = maranatha_logo_size( 'max_height' );

						// Width exceeds max, adjust width then height proportionately
						if ($logo_width > $logo_max_width) {

							$logo_width = $logo_max_width;

							// Adjust height proportionately
							$logo_height = round( $logo_height * $logo_width / $original_logo_width );

							// Set new "original" size in case new height exceeds max
							if ($logo_height > $logo_max_height) {
								$original_logo_width = $logo_width;
								$original_logo_height = $logo_height;
							}

						}

						// Height exceeds max, adjust height then width proportionately
						if ($logo_height > $logo_max_height) {

							$logo_height = $logo_max_height;

							// Adjust width proportionately
							$logo_width = round( $logo_width * $logo_height / $original_logo_height );

						}

						// Build attributes
						$logo_size_atts = 'width="' . esc_attr( $logo_width ) . '" height="' . esc_attr( $logo_height ) . '"';

					}

				}

			}

			?>

			<div id="maranatha-logo-image"<?php if ($logo_hidpi_url) : ?> class="maranatha-has-hidpi-logo"<?php endif; // tell stylesheet Retina logo exists ?>>

				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="max-width:<?php echo esc_attr( $logo_width ); ?>px;max-height:<?php echo esc_attr( $logo_height ); ?>px">

					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" id="maranatha-logo-regular" <?php echo $logo_size_atts; ?>>

					<?php if ($logo_hidpi_url) : // Retina logo is optional ?>
						<img src="<?php echo esc_url( $logo_hidpi_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" id="maranatha-logo-hidpi" <?php echo $logo_size_atts; ?>>
					<?php endif; ?>

				</a>

			</div>

		<?php endif; ?>

	</div>

</div>
