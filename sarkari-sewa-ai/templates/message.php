<?php
/**
 * Message bubble template helper.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render message bubble markup.
 *
 * @param string $sender 'user' or 'bot'
 * @param string $content HTML formatted content
 * @param array  $sources Optional array of source items
 */
function sarkari_sewa_ai_render_message( $sender, $content, $sources = array() ) {
	$sender_class = $sender === 'user' ? 'msg-user' : 'msg-bot';
	?>
	<div class="sarkari-msg <?php echo esc_attr( $sender_class ); ?>">
		<?php if ( $sender === 'bot' ) : ?>
			<div class="msg-avatar">
				<span class="bot-icon">SS</span>
			</div>
		<?php endif; ?>
		<div class="msg-bubble">
			<div class="msg-text">
				<?php echo wp_kses_post( $content ); ?>
			</div>
			<?php if ( ! empty( $sources ) && is_array( $sources ) ) : ?>
				<div class="msg-sources">
					<div class="sources-title">Published on Sarkari Sewa:</div>
					<div class="sources-list">
						<?php foreach ( $sources as $src ) : ?>
							<a href="<?php echo esc_url( $src['url'] ); ?>" class="source-card-link" target="_blank" rel="noopener noreferrer">
								<div class="source-card">
									<div class="source-category"><?php echo esc_html( $src['category'] ?? 'Latest' ); ?></div>
									<div class="source-title"><?php echo esc_html( $src['title'] ); ?></div>
									<span class="source-action">View Details &rarr;</span>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
