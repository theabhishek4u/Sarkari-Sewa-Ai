<?php
/**
 * Source Card Template.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a verified Sarkari Sewa source card.
 *
 * @param array $card
 */
function sarkari_sewa_ai_render_source_card( $card ) {
	if ( empty( $card['url'] ) || empty( $card['title'] ) ) {
		return;
	}
	?>
	<a href="<?php echo esc_url( $card['url'] ); ?>" class="sarkari-source-card" target="_blank" rel="noopener noreferrer">
		<div class="card-inner">
			<div class="card-meta">
				<span class="card-category"><?php echo esc_html( $card['category'] ?? 'Latest' ); ?></span>
				<?php if ( ! empty( $card['last_date'] ) ) : ?>
					<span class="card-date">Last Date: <?php echo esc_html( $card['last_date'] ); ?></span>
				<?php endif; ?>
			</div>
			<h4 class="card-title"><?php echo esc_html( $card['title'] ); ?></h4>
			<span class="card-cta">View Full Details &rarr;</span>
		</div>
	</a>
	<?php
}
