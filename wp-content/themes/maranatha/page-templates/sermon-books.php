<?php
/**
 * Template Name: Sermon Books
 *
 * This shows a page with sermon books.
 *
 * partials/content-full.php outputs the page content.
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Prepare sermon books to output after page content
function maranatha_sermon_books_after_content() {

	// Books by testament
	$books_by_testament = ctfw_sermon_books_by_testament();

	?>

	<div id="maranatha-sermon-books" class="maranatha-sermon-index">

		<?php
		// Buttons for switching between indexes
		get_template_part( CTFW_THEME_PARTIAL_DIR . '/sermon-index-header' );
		?>

		<?php if ( $books_by_testament ) : ?>

			<div id="maranatha-sermon-books-list">

				<?php foreach ( $books_by_testament as $testament_key => $testament ) : ?>

					<?php if ( ! empty( $testament['books'] ) ) : ?>

						<section id="maranatha-sermon-books-<?php echo esc_attr( $testament_key ); ?>" class="maranatha-sermon-books-testament">

							<h2><?php echo esc_html( $testament['name'] ); ?></h2>

							<ul class="maranatha-list">

								<?php foreach ( $testament['books'] as $book_key => $book ) : ?>

									<li>

										<a href="<?php echo esc_url( $book->url ); ?>">
											<?php echo esc_html( $book->name ); ?>
										</a>

										<span class="maranatha-list-item-count"><?php echo esc_html( $book->count ); ?></span>

									</li>

								<?php endforeach; ?>

							</ul>

						</section>

					<?php endif; ?>

				<?php endforeach; ?>

			</div>

		<?php else : ?>

			<p id="maranatha-sermon-index-none">
				<?php _e( 'There are no books to show.', 'maranatha' ); ?>
			</p>

		<?php endif; ?>

	</div>

	<?php

}

// Insert content after the_content() in content.php
add_action( 'maranatha_after_content', 'maranatha_sermon_books_after_content' );

// Load main template to show the page
locate_template( 'index.php', true );