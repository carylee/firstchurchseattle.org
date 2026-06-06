<?php
/**
 * Short Sermon Content (Archive)
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Get sermon data:
// $has_full_text			True if full text of sermon was provided as post content
// $has_download   			Has at least one download (audio, video or PDF)
// $video_player			Embed code generated from uploaded file, URL for file on other site, page on oEmbed-supported site such as YouTube, or manual embed code (HTML or shortcode)
// $video_download_url 		URL to file with extension (ie. not YouTube). If local, URL changed to force "Save As" via headers.
// $video_extension			File extension for local file (e.g. mp3)
// $video_size				File size for local file (e.g. 10 MB, 980 kB, 2 GB)
// $audio_player			Same as video
// $audio_download_url 		Same as video
// $audio_extension			File extension for local file (e.g. mp3)
// $audio_size				File size for local file (e.g. 10 MB, 980 kB, 2 GB)
// $pdf_download_url 		Same as audio/video
// $pdf_size				File size for local file (e.g. 10 MB, 980 kB, 2 GB)
extract( ctfw_sermon_data() );

// Taxonomy Terms
$speakers = get_the_term_list( $post->ID, 'ctc_sermon_speaker', '', __( ', ', 'maranatha' ) );
$topics = get_the_term_list( $post->ID, 'ctc_sermon_topic', '', __( ', ', 'maranatha' ) );
$series = get_the_term_list( $post->ID, 'ctc_sermon_series', '', __( ', ', 'maranatha' ) );
$books = get_the_term_list( $post->ID, 'ctc_sermon_book', '', __( ', ', 'maranatha' ) );

?>

<article id="post-<?php the_ID(); ?>" <?php maranatha_short_post_classes( 'maranatha-sermon-short' ); ?>>

	<header class="maranatha-entry-short-header">

		<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-header-short' ); // show title and image ?>

		<ul class="maranatha-entry-meta maranatha-entry-short-meta">

			<li class="maranatha-sermon-short-date maranatha-dark">
				<time datetime="<?php echo esc_attr( the_time( 'c' ) ); ?>"><?php ctfw_post_date(); ?></time>
			</li>

			<?php if ( $speakers ) : ?>
				<li class="maranatha-sermon-short-speaker">
					<?php echo $speakers; ?>
				</li>
			<?php endif; ?>

			<?php if ( $topics ) : ?>
				<li class="maranatha-sermon-short-topic">
					<?php echo $topics; ?>
				</li>
			<?php endif; ?>

			<?php if ( $series ) : ?>
				<li class="maranatha-sermon-short-series">
					<?php echo $series; ?>
				</li>
			<?php endif; ?>

			<?php if ( $books ) : ?>
				<li class="maranatha-sermon-short-book">
					<?php echo $books; ?>
				</li>
			<?php endif; ?>

		</ul>

	</header>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-excerpt' ); // show excerpt if no image ?>

	<?php get_template_part( CTFW_THEME_PARTIAL_DIR . '/content-footer-short' ); // show appropriate button(s) ?>

</article>
