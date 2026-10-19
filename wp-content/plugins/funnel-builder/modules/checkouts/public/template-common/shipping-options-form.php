<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$wfacp_instance = wfacp_template();
if ( is_null( $wfacp_instance ) ) {
	return '';
}


$data        = $wfacp_instance->get_checkout_fields();
$placeholder = WFACP_Common::default_shipping_placeholder_text();
if ( isset( $data['advanced']['shipping_calculator'] ) ) {
	$field = $data['advanced']['shipping_calculator'];
	if ( isset( $field['default'] ) && '' !== $field['default'] ) {
		$placeholder = $field['default'];
	}
}

$class = 'wfacp_single_methods';
if ( 1 < count( $available_methods ) ) {
	$class = 'wfacp_multi_methods';
} elseif ( 0 === count( $available_methods ) ) {
	$class .= ' wfacp_no_shipping_found';
}
$colspan              = apply_filters( 'wfacp_shipping_col_span', '' );
$display_package_name = apply_filters( 'wfacp_show_shipping_package_name', false, $available_methods );


if ( false == $wfacp_instance->have_shipping_method() ) {
	$class .= ' wfacp_no_shipping_method_field_used';
}
?>
<tr class="shipping <?php echo esc_attr( $class ); ?> <?php echo $display_package_name ? 'wfacp_package_name_display' : ''; ?>">
	<?php
	if ( false == $wfacp_instance->have_shipping_method() ) {
		?>
		<th><?php echo wp_kses_post( $package_name ); ?></th>
		<?php
	}
	?>
	<td class="<?php echo $display_package_name ? 'wfacp_shipping_package_name' : ''; ?>" data-title="<?php echo esc_attr( $package_name ); ?>" <?php echo $colspan; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- complete attribute from the wfacp_order_summary_cols_span filter; revisit in the ignore-audit phase. ?>>
		<?php
		if ( $display_package_name ) {
			?>
			<p class=""><?php echo wp_kses_post( $package_name ); ?></p>
			<?php
		}
		if ( 1 < count( $available_methods ) ) :
			?>
			<ul id="shipping_method" class="woocommerce-shipping-methods">
				<?php
				$available_methods = WFACP_Common::sort_shipping( $available_methods );
				foreach ( $available_methods as $method ) :
					?>
					<li class="wfacp_single_shipping_method">
						<div class="wfacp_single_shipping">
							<div class="wfacp_shipping_radio">
								<?php
								printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />', (int) $index, esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ) );
								if ( true == $wfacp_instance->have_shipping_method() ) {
									printf( '<label for="shipping_method_%s_%s">%s</label>', (int) $index, esc_attr( sanitize_title( $method->id ) ), wp_kses_post( WFACP_Common::shipping_method_label( $method ) ) );
								} else {
									printf( '<label for="shipping_method_%s_%s">%s: <span class="wfacp_shipping_cost_no_field_used">%s</span></label>', (int) $index, esc_attr( sanitize_title( $method->id ) ), wp_kses_post( WFACP_Common::shipping_method_label( $method ) ), wp_kses_post( WFACP_Common::wc_cart_totals_shipping_method_cost( $method ) ) );
								}
								?>
							</div>
							<?php
							if ( true == $wfacp_instance->have_shipping_method() ) {
								?>
								<div class="wfacp_shipping_price">
									<?php
									echo wp_kses_post( WFACP_Common::wc_cart_totals_shipping_method_cost( $method ) );
									?>
								</div>
							<?php } ?>
						</div>

							<?php do_action( 'woocommerce_after_shipping_rate', $method, $index ); ?>

					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		elseif ( 1 === count( $available_methods ) ) :
			$method = current( $available_methods );
			?>
			<div class="wfacp_recuring_shiping_count_one">
				<ul id="shipping_method" class="woocommerce-shipping-methods">
					<li class="wfacp_single_shipping_method">
						<div class="wfacp_single_shipping">
							<div class="wfacp_shipping_radio">
								<?php
								printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />', (int) $index, esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ) );
								printf( '<label for="shipping_method_%s_%s">%s</label>', (int) $index, esc_attr( sanitize_title( $method->id ) ), wp_kses_post( WFACP_Common::shipping_method_label( $method ) ) );
								?>
							</div>
							<div class="wfacp_shipping_price">
								<?php
								echo wp_kses_post( WFACP_Common::wc_cart_totals_shipping_method_cost( $method ) );
								?>
							</div>
						</div>

							<?php do_action( 'woocommerce_after_shipping_rate', $method, $index ); ?>

					</li>
				</ul>
			</div>
		<?php elseif ( WC()->customer->has_calculated_shipping() ) : ?>
			<ul id="shipping_method">
				<li>
					<?php
					if ( is_cart() ) {
						echo wp_kses_post( apply_filters( 'woocommerce_cart_no_shipping_available_html', wpautop( __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ) ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
					} else {
						echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', wpautop( __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ) ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
					}
					?>
				</li>
			</ul>
		<?php elseif ( ! is_cart() ) : ?>
			<ul id="shipping_method" class="wfacp_no_add_here">
				<li>
					<p><?php echo wp_kses_post( apply_filters( 'wfacp_default_shipping_message', $placeholder ) ); ?></p>
				</li>
			</ul>
		<?php endif; ?>

		<?php if ( $show_package_details ) : ?>
			<ul id="shipping_method">

				<li>
					<?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?></li>
			</ul>
		<?php endif; ?>
	</td>
</tr>
