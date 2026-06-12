<?php
/**
 * Template Name: Worship Live (Custom)
 *
 * Hand-built layout for /worship/live/. Replaces the flat figure → p →
 * button → figure → p → button page-content rendering with a clean
 * action-card-plus-tile-grid design.
 *
 * Assign via wp-admin → Pages → Worship → Page Attributes → Template.
 * (Also assigned by ID via wp-cli during local development.)
 *
 * @package FirstChurch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); // header.php — also renders banner + breadcrumb via partials

// Action card CTAs — primary buttons shown immediately after the breadcrumb.
$primary_actions = array(
	array(
		'label' => __( 'Bulletin',           'firstchurch' ),
		'url'   => '/bulletin',
		'desc'  => __( 'This week\'s order of worship.', 'firstchurch' ),
	),
	array(
		'label' => __( 'Communication Card', 'firstchurch' ),
		'url'   => '/connection-card',
		'desc'  => __( 'Let us know you were with us.', 'firstchurch' ),
	),
	array(
		'label' => __( 'Prayer Request',     'firstchurch' ),
		'url'   => 'https://firstchurchseattle.org/worship/prayer/',
		'desc'  => __( 'Share what we can pray for.',    'firstchurch' ),
	),
	array(
		'label' => __( 'Give',               'firstchurch' ),
		'url'   => 'https://firstchurchseattle.breezechms.com/give/online',
		'desc'  => __( 'Support First Church\'s mission.', 'firstchurch' ),
	),
);

$uploads = '/wp-content/uploads';

// Secondary CTA tiles — rendered as a 2x2 grid below the livestream.
$secondary_tiles = array(
	array(
		'title' => __( 'Check-in & Connection Card', 'firstchurch' ),
		'image' => $uploads . '/2025/07/Untitled-11-768x192.png',
		'body'  => __( 'Let us know you were with us today by filling out our Check-in &amp; Connection Card.', 'firstchurch' ),
		'cta'   => __( 'Check in',           'firstchurch' ),
		'url'   => '/connection-card',
	),
	array(
		'title' => __( 'Give to First Church', 'firstchurch' ),
		'image' => $uploads . '/2025/07/Special-Events-40-768x192.png',
		'body'  => __( 'Thank you for supporting First Church\'s missions and ministries. Make an online gift today.', 'firstchurch' ),
		'cta'   => __( 'Online giving page', 'firstchurch' ),
		'url'   => 'https://firstchurchseattle.breezechms.com/give/online',
	),
	array(
		'title' => __( 'Prayer Requests',    'firstchurch' ),
		'image' => $uploads . '/2025/07/Special-Events-41-768x192.png',
		'body'  => __( 'Please let us know how we can be praying for you. Confidential requests go only to Reverend Wongee; all others reach the First Church Prayer team.', 'firstchurch' ),
		'cta'   => __( 'Prayer request form', 'firstchurch' ),
		'url'   => 'https://firstchurchseattle.org/worship/prayer/',
	),
	array(
		'title' => __( 'Name Tag Request',   'firstchurch' ),
		'image' => $uploads . '/2025/07/Special-Events-43-768x192.png',
		'body'  => __( 'Fill out the request and you\'ll find your name tag on the welcome table in the Narthex.', 'firstchurch' ),
		'cta'   => __( 'Name tag form',      'firstchurch' ),
		'url'   => 'https://firstchurchseattle.breezechms.com/form/dcd943',
	),
);

$livestream_thumb = $uploads . '/2026/05/youtube-768x576.png';
$livestream_url   = 'https://www.youtube.com/@firstchurchseattle/live';
$past_services    = 'https://youtube.com/playlist?list=PLeNlEFIwMCurkDsg-R2rQoAJFUiB7hx4_';
?>

<main id="fcs-content" tabindex="-1" class="bg-surface">
	<div class="max-w-4xl mx-auto px-4 sm:px-6 pt-6 pb-12">

		<!-- ===== Action card ===== -->
		<section aria-labelledby="actions-heading" class="card-action">
			<h2 id="actions-heading" class="sr-only"><?php esc_html_e( 'When you arrive', 'firstchurch' ); ?></h2>
			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
				<?php foreach ( $primary_actions as $a ) : ?>
					<a href="<?php echo esc_url( $a['url'] ); ?>" class="btn-primary"><?php echo esc_html( $a['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- ===== Watch Live ===== -->
		<section aria-labelledby="live-heading" class="mt-10">
			<div class="flex items-baseline justify-between mb-3">
				<h2 id="live-heading" class="m-0 text-2xl sm:text-3xl font-display font-medium text-ink">
					<?php esc_html_e( 'Watch Live', 'firstchurch' ); ?>
				</h2>
				<span class="text-sm font-medium text-accent uppercase tracking-wider">
					<?php esc_html_e( 'Sundays · 10:30 AM PT', 'firstchurch' ); ?>
				</span>
			</div>

			<?php // Filled + revealed by the worship-live island (assets/js/islands/worship-live.js); stays hidden with no JS. ?>
			<p data-island="worship-live"
			   class="m-0 mb-3 text-sm font-medium text-accent uppercase tracking-wider"
			   hidden></p>

			<a href="<?php echo esc_url( $livestream_url ); ?>"
			   class="block rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow ring-1 ring-line"
			   aria-label="<?php esc_attr_e( 'Open the First Church live worship stream on YouTube', 'firstchurch' ); ?>">
				<img src="<?php echo esc_url( $livestream_thumb ); ?>"
				     alt=""
				     class="w-full h-auto block dark:opacity-95"
				     loading="lazy">
			</a>

			<p class="mt-3 text-sm text-muted text-center sm:text-left">
				<?php esc_html_e( 'Or', 'firstchurch' ); ?>
				<a href="<?php echo esc_url( $past_services ); ?>" class="text-accent underline hover:text-accent-strong">
					<?php esc_html_e( 'browse past services', 'firstchurch' ); ?></a>.
			</p>
		</section>

		<!-- ===== Secondary CTA grid ===== -->
		<section aria-labelledby="connect-heading" class="mt-12">
			<h2 id="connect-heading" class="m-0 mb-5 text-2xl sm:text-3xl font-display font-medium text-ink">
				<?php esc_html_e( 'Connect &amp; respond', 'firstchurch' ); ?>
			</h2>

			<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
				<?php foreach ( $secondary_tiles as $t ) : ?>
					<article class="cta-tile">
						<img src="<?php echo esc_url( $t['image'] ); ?>"
						     alt=""
						     class="w-full h-32 object-cover block dark:opacity-90"
						     loading="lazy">
						<div class="p-5 flex flex-col gap-3 grow">
							<h3 class="m-0 text-lg font-display font-medium text-ink">
								<?php echo esc_html( $t['title'] ); ?>
							</h3>
							<p class="m-0 text-sm leading-relaxed text-soft grow">
								<?php echo wp_kses_post( $t['body'] ); ?>
							</p>
							<div>
								<a href="<?php echo esc_url( $t['url'] ); ?>" class="btn-primary">
									<?php echo esc_html( $t['cta'] ); ?>
								</a>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

	</div>
</main>

<?php get_footer(); // footer.php ?>
