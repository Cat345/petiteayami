<?php
/**
 * Single variation cart button
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product, $wfob_qv_data;

$btn_name = esc_html( $product->single_add_to_cart_text() );



?>
<div class="woocommerce-variation-add-to-cart variations_button">
	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
    <button type="button" class="wfob_single_add_to_cart_button button alt" name="wfob_update"><?php echo $btn_name; ?></button>
	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

    <input type="hidden" name="add-to-cart" value="<?php echo absint( $product->get_id() ); ?>"/>
    <input type="hidden" name="product_id" value="<?php echo absint( $product->get_id() ); ?>"/>
    <input type="hidden" name="variation_id" class="variation_id" value="0"/>
</div>

<?php
include WFOB_PLUGIN_DIR . '/includes/quick-view/short-description.php';
?>