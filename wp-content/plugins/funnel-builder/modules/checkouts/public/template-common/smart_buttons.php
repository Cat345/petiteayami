<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}

$instance = wfacp_template();

$payment_buttons = $instance->get_smart_buttons();
if ( empty( $payment_buttons ) ) {
	return;
}


$or_title     = apply_filters( 'wfacp_smart_button_or_text', __( 'OR', 'funnel-builder' ) );
$legend_title = apply_filters( 'wfacp_smart_button_legend_title', __( 'Express Checkout', 'funnel-builder' ) );

$button_count = sizeof( $payment_buttons );

$button_class = '';
if ( $button_count > 0 ) {
	$button_class = 'wfacp_button_count_' . $button_count;
}

$show_smart_button_shimmer     = apply_filters( 'wfacp_show_smart_button_shimmer', true, $payment_buttons );
$show_smart_wrapper_visibility = 'display:none';
if ( ! $show_smart_button_shimmer ) {
	$show_smart_wrapper_visibility = '';
}
?>
<style>

	@media (min-width: 768px) {
		/*
		 * Flex layout for the express buttons row.
		 *
		 * The wrap is a flex container and every visible button gets `flex: 1 1 0`,
		 * so the buttons always share the row in equal width and equal height and
		 * flow into a single line. Hidden containers (display:none) are dropped from
		 * the flex flow automatically, so the layout no longer depends on the
		 * `[count]` attribute matching the number of rendered buttons.
		 */
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_wrap_st {
			display: flex;
			flex-wrap: wrap;
			align-items: stretch;
			justify-content: center;
			gap: 20px;
			margin: 0 !important;
		}

		/*
		 * Max 3 buttons per row. The basis is one-third of the row (40px = 2 x
		 * the 20px gap), so a 4th button wraps and remainder rows stretch to
		 * fill: 4 buttons = 3+1 full-width, 5 = 3+2 halves, 6 = 3+3. The 150px
		 * floor matches the SDKs' hard minimums (Google Pay min-width:150px,
		 * PayPal ~150px) - a basis of 0 never triggered flex-wrap, so columns
		 * shrank below that and the buttons overflowed onto each other.
		 */
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_outer_buttons .wfacp_smart_button_container {
			flex: 1 1 calc((100% - 40px) / 3);
			width: auto !important;
			max-width: 100%;
			min-width: 150px;
			float: none !important;
			margin: 0 !important;
			padding: 0 !important;
		}

		/*
		 * The wrap's clearfix ::before/::after (content:""; display:block)
		 * become zero-width flex ITEMS, each consuming a 20px gap slot and
		 * shifting wrapped lines out of alignment. Clearing is meaningless in
		 * a flex container, so drop them.
		 */
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_wrap_st::before,
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_wrap_st::after {
			display: none;
		}

		/*
		 * Third-party SDK buttons render at their intrinsic width (Google Pay
		 * 150px, PayPal 150-160px) and overflow or under-fill their flex
		 * column - force them to fill it. The PayPal SDK re-renders its
		 * iframes to the container width automatically.
		 */
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_container .fkwcppcp-express-button-container,
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_container .paypal-buttons,
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_container .gpay-button,
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_container .gpay-card-info-container,
		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_container .gpay-button-fill {
			width: 100% !important;
			max-width: 100% !important;
		}

		#wfacp_smart_buttons .wfacp_smart_button_outer_buttons[count="1"] #pay_with_amazon {
			background-size: 20%;
			margin: 0 auto;

		}

		#wfacp_smart_buttons .wfacp_smart_button_outer_buttons[count="2"] #pay_with_amazon {
			background-size: 30%;
		}

		#wfacp_smart_buttons .wfacp_smart_button_outer_buttons[count="3"] #pay_with_amazon {
			background-size: 45%;
		}

		#wfacp_smart_buttons .wfacp_smart_button_outer_buttons[count="4"] #pay_with_amazon {
			background-size: 50%;
		}

		#wfacp_smart_buttons.wfacp_smart_buttons .wc-amazon-checkout-message.wc-amazon-payments-advanced-populated {
			display: block;
		}

		#wfacp_smart_buttons.wfacp_smart_buttons div#pay_with_amazon,
		#wfacp_smart_buttons #wfacp_smart_button_stripe_gpay_apay div#wc-stripe-payment-request-wrapper,
		#wfacp_smart_buttons .wfacp_smart_button_wrap_st div#paypal_box_button > div {
			width: 100%;
		}

		.wfacp_smart_button_wrap_st div#paypal_box_button > div {
			max-width: 100%;
		}

		/*
		 * Tablet/iPad fix (>=768px): the gateway *wrapper* is stretched to 100%
		 * above, but the INNER express button element keeps its intrinsic ~150px
		 * and left-aligns, leaving a misaligned sliver inside the full-width
		 * column. Force the inner Stripe / WooPayments / FunnelKit Stripe button
		 * element (and the Stripe request iframe) to fill and center its column.
		 * Gated by the media query only -- do NOT rely on the JS/UA-derived
		 * `.wfacp_mac` class (added after DOM-ready, flaky on iPad).
		 */
		#wfacp_smart_buttons #wfacp_smart_button_stripe_gpay_apay #wc-stripe-payment-request-button.StripeElement,
		#wfacp_smart_buttons #wfacp_smart_button_stripe_gpay_apay #wc-stripe-payment-request-button.StripeElement > div,
		#wfacp_smart_buttons #wfacp_smart_button_stripe_gpay_apay div#wc-stripe-payment-request-wrapper iframe,
		#wfacp_smart_buttons #wfacp_smart_button_wc_payment_gpay_apay #wcpay-payment-request-wrapper,
		#wfacp_smart_buttons #wfacp_smart_button_wc_payment_gpay_apay #wcpay-express-checkout-button,
		#wfacp_smart_buttons #fkwcs_custom_express_button .fkwcs_smart_buttons {
			width: 100% !important;
			margin: 0 auto;
		}

		#wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_container iframe {
			max-height: 42px !important;
			height: 100% !important;
		}

		#wfacp_smart_buttons .wfacp_smart_button_wrap_st div#paypal_box_button .paypal-buttons {
			min-width: 1px;
			height: 42px !important;
			display: block !important;
		}

		#wfacp_smart_buttons .wfacp_smart_button_outer_buttons[count="2"] .wfacp_smart_button_container .angelleye_ppcp-button-container.angelleye_ppcp_horizontal_medium {
			width: 100%;
		}

		#wfacp_smart_buttons .wfacp_smart_button_outer_buttons[count="2"] .wfacp_smart_button_container .angelleye_ppcp-button-container.angelleye_ppcp_horizontal_medium .paypal-buttons.paypal-buttons-context-iframe {
			height: 40px;
			margin-top: 8px;
		}
		span.wfacp_single_btn_shimmer {
	display: none;
	position: absolute;
	background: #ececec;
	top: 0;
	bottom: 0;
	left: 0;
	right: 0;
	z-index: 1000000;
}
	}

</style>
<div class="wfacp_smart_buttons wfacp-dynamic-checkout-loading" id="wfacp_smart_buttons" style="<?php echo esc_attr( $show_smart_wrapper_visibility ); ?>">
	<div class="wfacp_smart_button_outer_buttons" count="<?php echo esc_attr( $button_count ); ?>">
		<div class="wfacp_smart_button_inner wfacp_smart_buttons_placeholder">
			<fieldset>
				<legend><?php echo esc_html( $legend_title ); ?></legend>
				<div class="wfacp_smart_button_wrap_st wfacp_clearfix">
					<div class="dynamic-checkout__skeleton">
						<div class="placeholder-line placeholder-line--animated"></div>
					</div>
					<?php
					foreach ( $payment_buttons as $slug => $payment ) {

						$hide_button_container = 'display: none';
						if ( isset( $payment['show_default'] ) ) {
							$hide_button_container = '';
						}

						?>
						<div class="wfacp_smart_button_container" id="wfacp_smart_button_<?php echo esc_attr( $slug ); ?>" style="<?php echo esc_attr( $hide_button_container ); ?>">
							<div class="wfacp_button_container">
								<?php
								if ( isset( $payment['iframe'] ) ) {
									do_action( 'wfacp_smart_button_container_' . $slug, $payment, $slug );

								} elseif ( '' !== $payment['image'] ) {
									?>
										<div class="wfacp_smart_button_image_container">
											<img src="<?php echo esc_url( $payment['image'] ); ?>">
										</div>
										<?php

								}
								?>
								<span class="wfacp_single_btn_shimmer"></span>
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</fieldset>
		</div>
		<?php
		if ( '' !== $or_title ) {
			?>
			<div class="wfacp_smart_button_inner wfacp_smart_button_or_text_placeholder"><label><?php echo esc_html( $or_title ); ?></label></div>
			<?php
		}
		?>
	</div>
</div>

