<?php
/**
 * @var \WPDesk\FS\TableRate\ShippingMethodSingle $this
 * @var string                                    $field_key
 * @var string                                    $key
 * @var array                                     $data
 *
 * @package Flexible Shipping
 */

?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr( $field_key ); ?>">
			<?php echo wp_kses_post( $data['title'] ); ?>
			<?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</label>
	</th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
			<input
				class="wc_input_price input-text regular-input <?php echo esc_attr( $data['class'] ); ?>"
				type="text"
				name="<?php echo esc_attr( $field_key ); ?>"
				id="<?php echo esc_attr( $field_key ); ?>"
				style="<?php echo esc_attr( $data['css'] ); ?>"
				value="<?php echo esc_attr( wc_format_localized_price( $this->get_option( $key ) ) ); ?>"
				placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>"
				<?php disabled( $data['disabled'], true ); ?>
				<?php echo $this->get_custom_attribute_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			/>
			<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="description fs-free-shipping-threshold-error-message" style="color:#b32d2e;margin-top:4px;"><?php echo wp_kses_post( $data['error_message'] ); ?></p>
		</fieldset>
	</td>
</tr>
