<?php
global $post, $product;
$old_post    = $post;
$old_product = $product;

$mode        = $data['mode'];
$align       = $data['align'];
$custom      = $data['custom'];
$product_obj = $data['product'];

$tab_style = 'horizontal';
/** vertical */

switch ( $tab_style ) {
	case 'horizontal':
		$tab_class = 'wfocu-product-tabs-view-horizontal';
		break;
	case 'vertical':
		$tab_class = 'wfocu-product-tabs-view-vertical';
		break;
	default:
		$tab_class = 'wfocu-product-tabs-view-horizontal';
		break;
}

$tabs = array();

if ( 'default' === $mode ) {
	$post                           = get_post( $product_obj->get_id() );
	$product                        = wc_setup_product_data( $post );
	WFOCU_Common::$tabs_product_obj = $product;

	/**
	 * Unhooking 'wc_setup_product_data' in order to sustain $_GLOBALS['product'],
	 * If any WP_Query run in between then $_GLOBALS['product'] would be unset by WooCommerce.
	 * @see wc_setup_product_data()
	 */
	remove_action( 'the_post', 'wc_setup_product_data' );


	$tabs = apply_filters( 'woocommerce_product_tabs', $tabs );

	/**
	 * Re-hooking so that we never prevent WooCommerce to set any $_GLOBALS['product']
	 *  Do not uncomment the below line as there is no need further to set/unset any product.
	 * add_action( 'the_post', 'wc_setup_product_data' );
	 */

	if ( isset( $tabs['reviews'] ) ) {
		unset( $tabs['reviews'] );
	}
} elseif ( 'custom' === $mode ) {
	if ( is_array( $custom ) && count( $custom ) > 0 ) {
		$p = 1;
		foreach ( $custom as $key => $tab ) {
			if ( isset( $tab['title'], $tab['desc'] ) && ! empty( $tab['title'] ) && ! empty( $tab['desc'] ) ) {
				$tab_key = sanitize_title( $tab['title'] ) . '_' . $key;

				$tabs[ $tab_key ]['title']    = $tab['title'];
				$tabs[ $tab_key ]['content']  = $tab['desc'];
				$tabs[ $tab_key ]['priority'] = 10 * $p;
				$p ++;
			}
		}
	}
}

/**
 * Horizontal: add class wfocu-product-tabs-view-horizontal on wfocu-product-widget-tabs
 * Vertical: add class wfocu-product-tabs-view-vertical on wfocu-product-widget-tabs
 */
if ( is_array( $tabs ) && count( $tabs ) > 0 ) {
	?>
    <div class="wfocu-clearfix"></div>
    <div class="<?php echo $tab_class; ?> wfocu-product-widget-tabs">
        <div class="wfocu-product-widget-container">
            <div class="wfocu-product-tabs wfocu-tabs-style-line" role="tablist">
                <div class="wfocu-product-tabs-wrapper <?php echo $align; ?>">
					<?php
					$active_tab = false;
					$count      = 1;
					foreach ( $tabs as $key => $tab ) :
						$active_class = '';
						if ( false === $active_tab ) {
							$active_class = ' wfocu-active';
							$active_tab   = true;
						}
						?>
                        <div class="wfocu-tab-title wfocu-tab-desktop-title<?php echo $active_class . ' ' . esc_attr( $key ); ?>_tab" id="tab-title-<?php echo esc_attr( $key ); ?>" data-tab="<?php echo $count; ?>" tabindex="<?php echo esc_attr( $key ); ?>" role="tab" aria-controls="wfocu-tab-content-<?php echo esc_attr( $key ); ?>">
							<?php echo apply_filters( 'woocommerce_product_' . $key . '_tab_title', esc_html( $tab['title'] ), $key ); ?>
                        </div>
						<?php
						$count ++;
					endforeach;
					?>
                </div>
                <div class="wfocu-product-tabs-content-wrapper">
					<?php
					$active_tab       = false;
					$count            = 1;
					foreach ( $tabs as $key => $tab ) :
						$active_style = 'style="display: none;"';
						$active_class = '';
						if ( false === $active_tab ) {
							$active_class = ' wfocu-active';
							$active_style = 'style="display: block;"';
							$active_tab   = true;
						}
						?>

                        <div id="wfocu-tab-content-<?php echo esc_attr( $key ); ?>" class="wfocu-tab-content wfocu-clearfix" data-tab="<?php echo $count ?>" role="tabpanel" aria-labelledby="wfocu-tab-title-<?php echo esc_attr( $key ); ?>" <?php echo $active_style ?>>
							<?php if ( isset( $tab['callback'] ) ) {
								if ( 'woocommerce_product_description_tab' == $tab['callback'] ) {
									echo apply_filters( 'wfocu_the_content', $post->post_content );
								} else {

									call_user_func( $tab['callback'], $key, $tab );
								}
							} elseif ( isset( $tab['content'] ) ) {
								echo apply_filters( 'wfocu_the_content', $tab['content'] );
							}
							?>
                        </div>
						<?php
						$count ++;
					endforeach;
					?>
                </div>
            </div>
        </div>
    </div>
	<?php
}
$post    = $old_post;
$product = $old_product;
?>
