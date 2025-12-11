<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $product_obj WC_Product;
 */
if ( isset( $data['is_added_cart'] ) && isset( $data['whats_included'] ) && ! isset( WC()->cart->removed_cart_contents[ $data['is_added_cart'] ] ) ) {
	?>
    <div class="wfacp_product_switcher_description" data-item-key="<?php echo $data['item_key']; ?>">
		<?php
		$title = apply_filters( 'wfacp_whats_included_title', $data['title'], $data, $data['is_added_cart'] );
		if ( isset( $title ) && ! empty( $title ) ) {
			echo '<h4>' . $title . '</h4>';
		}

		if ( isset( $data['whats_included'] ) && ! empty( $data['whats_included'] ) ) {
			$whats_included = apply_filters( 'wfacp_whats_included_content', $data['whats_included'], $data, $data['is_added_cart'] );
			echo '<div class="wfacp_description">' . apply_filters( 'wfacp_the_content', $whats_included, $data ) . '</div>';
		}
		?>
    </div>
	<?php
}
