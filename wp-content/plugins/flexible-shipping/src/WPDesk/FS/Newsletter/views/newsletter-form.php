<?php
/**
 * @var bool   $show_shipping_analytics_banner
 * @var string $email
 */
?>
<div id="fs-info-newsletter" class="fs-info-metabox fs-info-newsletter<?php echo esc_attr( $show_shipping_analytics_banner ? ' fs-shipping-analytics-banner' : '' ); ?>" style="display: none;">
	<?php if ( $show_shipping_analytics_banner ) : ?>
		<div class="fs-shipping-analytics-banner__content">
			<h3>
				<span aria-hidden="true">&#128640;</span>
				<?php esc_html_e( 'Ready to optimize your WooCommerce shipping setup?', 'flexible-shipping' ); ?>
			</h3>
			<p>
				<?php esc_html_e( 'Get actionable recommendations based on real checkout issues, missing rates, abandoned carts, and shipping configuration gaps. Be the first to access Shipping Analytics PRO.', 'flexible-shipping' ); ?>
			</p>
	</div>
		<p class="fs-shipping-analytics-banner__action">
			<a class="oct-btn fs-shipping-analytics-banner__button" href="https://shipping-optimization.octolize.com/" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Get FREE early access', 'flexible-shipping' ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</p>
	<?php else : ?>
		<h3><?php esc_html_e( 'Sign up for our newsletter', 'flexible-shipping' ); ?></h3>
		<p>
			<label for="fs-newsletter-email"><?php esc_html_e( 'Email:', 'flexible-shipping' ); ?></label>
			<input id="fs-newsletter-email" type="text" value="<?php echo esc_attr( $email ); ?>"/>
		</p>
		<p>
			<input id="fs-newsletter-checkbox" type="checkbox">
			<label for="fs-newsletter-checkbox">
				<?php echo wp_kses_post(
					sprintf(
						// Translators: link
						__( 'I’d like to receive exclusive tips, updates, and special offers from Octolize by email. I can unsubscribe at any time. %1$sPrivacy Policy%2$s', 'flexible-shipping' ),
						'<a href="https://octolize.com/terms-of-service/privacy-policy/" target="_blank">',
						'</a>'
					)
				);?>
			</label>
		</p>
		<p><button id="fs-newsletter-submit" class="oct-btn" disabled="disabled">Submit</button> <span id="fs-newsletter-status"></span></p>
	<?php endif; ?>
	</div>
<script type="text/javascript">
	jQuery(document).ready(function() {
		const $newsletter_element = jQuery('#fs-info-newsletter');
		const $newsletter_submit = jQuery('#fs-newsletter-submit');
		const $newsletter_status = jQuery('#fs-newsletter-status');

		function clearStatus() {
			$newsletter_status.html('');
			$newsletter_status.removeClass('success');
			$newsletter_status.removeClass('error');
		}

		jQuery('div.fs-info-wrapper').append($newsletter_element);
		$newsletter_element.show();

		jQuery('#fs-newsletter-checkbox').on('change', function() {
			$newsletter_submit.prop( 'disabled', !jQuery(this).is(':checked') );
		})

		$newsletter_submit.on('click', function(e) {
			e.preventDefault();

			$newsletter_submit.prop( 'disabled', true );
			clearStatus();

			let data = {
				email: jQuery('#fs-newsletter-email').val()
			};

			jQuery.ajax({
				url: "https://fsnewsletter.octolize.com/webhook/fs-newsleter-submit",
				type: "GET",
				data: data,
				success: function(response) {
					$newsletter_status.html(response);
					$newsletter_status.addClass('success');
				},
				error: function(xhr) {
					$newsletter_status.html(xhr.responseText);
					$newsletter_status.addClass('error');
					console.log("Error:", xhr.responseText);
				},
				complete: function() {
					$newsletter_submit.prop( 'disabled', false );
				}
			});
		})
	})
</script>
