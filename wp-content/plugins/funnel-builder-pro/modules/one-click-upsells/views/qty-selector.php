<?php
$product_key = $data['key'];
$product     = $data['product'];
$label       = isset( $data['label'] ) ? $data['label'] : __( 'Quantity', 'woocommerce' );
if ( true === apply_filters( 'wfocu_is_show_quantity_selector', $this->offer_data->settings->qty_selector ) ) {

	$get_max_qty_decimal = $this->offer_data->settings->qty_max;

	$get_max_qty_decimal = ( empty( $get_max_qty_decimal ) ) ? 9999 : $get_max_qty_decimal;
	$stcok_set           = false;

	/**
	 * Check if product Stock max qty is set,
	 * IF set
	 */
	if ( isset( $product->max_qty ) && $product->max_qty && $product->max_qty > 0 && ( $product->max_qty <= ( $get_max_qty_decimal * $product->quantity ) ) ) {

		$get_max_qty_decimal = absint( $product->max_qty / absint( $product->quantity ) );
		$stcok_set           = true;
	}
	if ( ! $stcok_set && $get_max_qty_decimal === 9999 ) {
		$get_max_qty_decimal = 10;
	}
	?>
    <div class="wfocu-prod-qty-wrapper">
        <label><?php echo $label; ?> </label>

        <span class="wfocu-select-wrapper">
					<select class="wfocu-select-qty-input" data-key="<?php echo $product_key; ?>">

	<?php for ( $i = 1; $i <= $get_max_qty_decimal; $i ++ ) { ?>
        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
	<?php } ?>
	 </select>
				</span>
    </div>
	<?php
} else {
	?>
    <input type="hidden" class="wfocu-select-qty-input" data-key="<?php echo $product_key; ?>" value="1"/>
	<?php
}
