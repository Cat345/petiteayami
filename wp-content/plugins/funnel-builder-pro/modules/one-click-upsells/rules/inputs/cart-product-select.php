<?php
if ( ! class_exists( 'wfocu_Input_Cart_Product_Select' ) ) {
	class wfocu_Input_Cart_Product_Select {
		public function __construct() {
			// vars
			$this->type = 'Cart_Product_Select';

			$this->defaults = array(
				'multiple'      => 0,
				'allow_null'    => 0,
				'choices'       => array(),
				'default_value' => '',
				'class'         => 'ajax_chosen_select_products'
			);
		}

		public function render( $field, $value = null ) {
			$field = array_merge( $this->defaults, $field );
			if ( ! isset( $field['id'] ) ) {
				$field['id'] = sanitize_title( $field['id'] );
			}

			?>

            <table style="width:100%;">
                <tr>
                    <td style="width:32px;"><?php esc_html_e( 'Quantity', 'woofunnels-upstroke-one-click-upsell' ); ?></td>
                    <td><?php esc_html_e( 'Products', 'woofunnels-upstroke-one-click-upsell' ); ?></td>
                </tr>
                <tr>
                    <td style="width:32px; vertical-align:top;">
                        <input type="text" id="<?php echo esc_attr( $field['id'] ); ?>_qty" name="<?php echo esc_attr( $field['name'] ); ?>[qty]" value="<?php echo isset( $value['qty'] ) ? esc_attr( $value['qty'] ) : 1; ?>"/>

                    </td>
                    <td>
                        <select id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>[products]" class="ajax_chosen_select_products" data-placeholder="<?php esc_html_e( 'Look for a product&hellip;', 'woocommerce' ); ?>">
							<?php
							$defaults = array(
								'numberposts'      => 5,
								'category'         => 0,
								'orderby'          => 'date',
								'order'            => 'DESC',
								'include'          => array(),
								'exclude'          => array(),
								'post_type'        => 'product',
								'suppress_filters' => true, //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue
								'fields'           => 'ids',
							);

							$current     = isset( $value['products'] ) ? array( $value['products'] ) : array();
							$product_ids = ! empty( $current ) ? array_map( 'absint', array_unique( array_merge( get_posts( $defaults ), $current ) ) ) : get_posts( $defaults ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts

							if ( empty( $current ) ) {
								$current = $product_ids;
							}
							if ( $product_ids ) {
								foreach ( $product_ids as $product_id ) {

									$product      = wc_get_product( $product_id );
									$product_name = WFOCU_WC_Compatibility::woocommerce_get_formatted_product_name( $product );

									echo "<option value='" . esc_attr( $product_id ) . "' " . selected( $current[0], $product_id, false ) . ">" . esc_html( $product_name ) . "</option>";
								}
							}
							?>
                        </select>
                    </td>
                </tr>
            </table>


			<?php

		}

	}
}
?>