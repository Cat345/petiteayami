<?php
$live_or_dev = 'live';

if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
	$live_or_dev = 'dev';

}
WFOCU_Core()->assets->add_scripts( 'wfocu-defiant', plugin_dir_url( WFOCU_PLUGIN_FILE ) . 'assets/'.$live_or_dev.'/js/defiant.min.js', WFOCU_VERSION, true );

if ( ! isset( $data['product']->variations_data ) ) {

	return;
}

if ( ! isset( $data['product']->variations_data['available_variations'] ) ) {
	return;
}

if ( empty( $data['product']->variations_data['available_variations'] ) ) {
	//@todo show some notice text here so that we could tell store admin why variables are not visible.
	return;
}
?>
	<div class="wfocu-product-attr-wrapper" <?php echo (isset($data['display'] ) && $data['display'] === 'no')? "style='display:none;'":"" ?>>
		<form class="wfocu_variation_selector_form" data-key="<?php echo $data['key']; ?>">
			<div class="wfocu_variation_selector_wrap" data-key="<?php echo $data['key']; ?>" data-default="<?php echo $data['product']->variations_data['default']; ?>" data-variable="<?php echo $data['product']->data->get_id(); ?>" data-variations="<?php echo htmlspecialchars( wp_json_encode( $data['product']->variations_data['available_variations'] ) ); ?>" data-variations-stock="<?php echo htmlspecialchars( wp_json_encode( $data['product']->variations_data['available_variation_stock'] ) ); ?>" data-prices="<?php echo htmlspecialchars( wp_json_encode( $data['product']->variations_data['prices'] ) ); ?>" data-shipping-hash="<?php echo htmlspecialchars( wp_json_encode( $data['product']->variations_data['shipping_hash'] ) ); ?>" data-dimensions="<?php echo htmlspecialchars( wp_json_encode( $data['product']->variations_data['dimension_htmls'] ) ); ?>" data-weight-html="<?php echo htmlspecialchars( wp_json_encode( $data['product']->variations_data['weight_htmls'] ) ); ?>" data-images="<?php echo htmlspecialchars( wp_json_encode( $data['product']->variations_data['images'] ) ); ?>">
				<table class="variations" cellspacing="0">
					<tbody>
					<?php foreach ( $data['product']->variations_data['attributes'] as $attribute_name => $options ) : ?>
						<tr>
							<td class="label"><label for="<?php echo sanitize_title( $attribute_name ); ?>"><?php echo wc_attribute_label( $attribute_name ); ?></label></td>
							<td class="value" data-attribute-title="<?php echo wc_attribute_label( $attribute_name ); ?>">
								<?php
								$selected = $data['product']->data->get_variation_default_attribute( $attribute_name );

								wc_wfocu_dropdown_variation_attribute_options( array(
									'options'   => $options,
									'attribute' => $attribute_name,
									'product'   => $data['product']->data,
									'selected'  => $selected,
								) );
								?>
							</td>
						</tr>
					<?php
					endforeach;
					?>
					</tbody>
				</table>
				<input type="hidden" name="_wfocu_variation" value="<?php echo( (apply_filters('wfocu_show_default_variation_on_load',true))? $data['product']->variations_data['default']:'' ); ?>"/>
			</div>
		</form>
	</div>
<?php
