<?php
/**
 * Quick view form for a product that is not variable but still needs options (e.g. a bundle).
 *
 * The form carries the classes the drawer's add button reads (`variations_form cart`, a `product_id`
 * field), and fires the native add to cart hooks so the plugin that flagged the product through
 * `fkcart_product_needs_options` can print its own fields.
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_add_to_cart_form' ); ?>
    <form class="variations_form cart fkcart-options-form" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="[]">
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
		<?php if ( ! empty( $cart_key ) ) : ?>
            <input type="hidden" name="fkcart-cart-key" class="fkcart-cart-key" value="<?php echo esc_attr( $cart_key ); ?>"/>
		<?php endif; ?>
        <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>"/>
        <input type="hidden" name="quantity" value="1"/>
		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
    </form>
<?php
do_action( 'woocommerce_after_add_to_cart_form' );
