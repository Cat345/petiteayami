<?php
/**
 * Internal non-modal rating petition view.
 *
 * @var string $logo_url Octolize logo URL.
 * @package FlexibleShipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<aside id="fs-rating-petition" class="fs-rating-petition" aria-label="<?php esc_attr_e( 'Rate Flexible Shipping', 'flexible-shipping' ); ?>" hidden>
	<img class="fs-rating-petition__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Octolize" width="100" height="17">
	<div data-rating-step="ask">
		<h2><?php esc_html_e( "You've set up a few shipping rules already. How would you rate Flexible Shipping?", 'flexible-shipping' ); ?></h2>
		<p><?php esc_html_e( 'Rate us by tapping a star', 'flexible-shipping' ); ?></p>
		<div class="fs-rating-petition__stars" role="group" aria-label="<?php esc_attr_e( 'Your rating', 'flexible-shipping' ); ?>">
			<?php for ( $rating = 1; $rating <= 5; $rating++ ) : ?>
				<button type="button" data-rating="<?php echo esc_attr( $rating ); ?>" aria-pressed="false" aria-label="<?php /* translators: %d: number of stars. */ echo esc_attr( sprintf( __( '%d out of 5 stars', 'flexible-shipping' ), $rating ) ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.7 5.7 6.2.6-4.6 4.3 1.3 6.2L12 16.6l-5.6 3 1.3-6.2-4.6-4.3 6.2-.6z"/></svg>
				</button>
			<?php endfor; ?>
		</div>
	</div>
	<div data-rating-step="low" hidden>
		<button type="button" data-change-rating class="fs-rating-petition__link"><?php esc_html_e( 'Change rating', 'flexible-shipping' ); ?></button>
		<h2><label for="fs-rating-feedback"><?php esc_html_e( "What's the one thing we should fix first?", 'flexible-shipping' ); ?></label></h2>
		<textarea id="fs-rating-feedback" rows="3" maxlength="5000" aria-required="true" aria-describedby="fs-rating-privacy" placeholder="<?php esc_attr_e( 'Describe missing or unclear functionality (at least 10 characters).', 'flexible-shipping' ); ?>"></textarea>
		<p id="fs-rating-privacy" class="fs-rating-petition__privacy"><?php esc_html_e( 'Your feedback is private and will only be sent to our support team. It will not be published.', 'flexible-shipping' ); ?></p>
		<button type="button" data-send-feedback class="fs-rating-petition__primary" disabled><?php esc_html_e( 'Send feedback', 'flexible-shipping' ); ?></button>
	</div>
	<div data-rating-step="high" hidden>
		<h2 tabindex="-1"><?php esc_html_e( 'Thank you!', 'flexible-shipping' ); ?></h2>
		<p><?php esc_html_e( 'You can finish your review on WordPress.org. We appreciate you taking a moment.', 'flexible-shipping' ); ?></p>
		<a data-review-link target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Continue to WordPress.org', 'flexible-shipping' ); ?></a>
	</div>
	<div data-rating-step="thanks" hidden>
		<h2 tabindex="-1"><?php esc_html_e( 'Thanks — this really helps.', 'flexible-shipping' ); ?></h2>
		<p><?php esc_html_e( 'Our support team will read your feedback.', 'flexible-shipping' ); ?></p>
	</div>
	<div data-rating-step="snoozed" hidden>
		<p data-snooze-confirm="15" hidden><?php esc_html_e( "Got it, we'll ask again in 15 minutes.", 'flexible-shipping' ); ?></p>
		<p data-snooze-confirm="30" hidden><?php esc_html_e( "Got it, we'll ask again in 30 minutes.", 'flexible-shipping' ); ?></p>
		<p data-snooze-confirm="60" hidden><?php esc_html_e( "Got it, we'll ask again in an hour.", 'flexible-shipping' ); ?></p>
		<p data-snooze-confirm="muted" hidden><?php esc_html_e( "Got it — we won't ask again on this install.", 'flexible-shipping' ); ?></p>
	</div>
	<div data-snooze>
		<button type="button" data-toggle-snooze class="fs-rating-petition__link" aria-expanded="false" aria-controls="fs-rating-snooze-options"><?php esc_html_e( 'Ask me later', 'flexible-shipping' ); ?></button>
		<div id="fs-rating-snooze-options" class="fs-rating-petition__snooze-options" hidden>
			<button type="button" data-snooze-minutes="15"><?php esc_html_e( 'In 15 minutes', 'flexible-shipping' ); ?></button>
			<button type="button" data-snooze-minutes="30"><?php esc_html_e( 'In 30 minutes', 'flexible-shipping' ); ?></button>
			<button type="button" data-snooze-minutes="60"><?php esc_html_e( 'In an hour', 'flexible-shipping' ); ?></button>
		</div>
	</div>
	<p data-rating-error role="alert" hidden><?php esc_html_e( 'We could not save your response. Please try again.', 'flexible-shipping' ); ?></p>
	<button type="button" data-retry-rating hidden><?php esc_html_e( 'Try again', 'flexible-shipping' ); ?></button>
	<p data-rating-status role="status" hidden><?php esc_html_e( 'Saving your response…', 'flexible-shipping' ); ?></p>
	<button type="button" data-close-rating hidden><?php esc_html_e( 'Close', 'flexible-shipping' ); ?></button>
</aside>
