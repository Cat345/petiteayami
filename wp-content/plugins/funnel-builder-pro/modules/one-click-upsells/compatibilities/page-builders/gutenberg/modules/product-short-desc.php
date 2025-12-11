<?php
if ( ! class_exists( 'WFOCU_Guten_Product_Short_Desc' ) ) {
	class WFOCU_Guten_Product_Short_Desc extends WFOCU_Guten_Field {
		public $slug = 'wfocu_product_short_description';
		protected $id = 'wfocu_product_short_description';

		public function __construct() {
			$this->name = __( "WF Product Short Description" );
			$this->ajax = true;
			parent::__construct();

		}

		public function html( $settings ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$sel_product_key = isset( $settings['product'] ) ? $settings['product'] : '';
			$product_key     = WFOCU_Common::default_selected_product_key( $sel_product_key );
			$product_key     = ( $product_key !== false ) ? $product_key : $sel_product_key;

			$product_data = WFOCU_Core()->template_loader->product_data->products;

			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return;
			}
			$post_object = get_post( $product->get_id() );

			$description = $post_object->post_excerpt;
			if ( 'product_variation' === $post_object->post_type ) {
				$product = wc_get_product( $product->get_id() );
				if ( $product instanceof WC_Product ) {
					$description = $product->get_description();
				}
			}

			$short_description = apply_filters( 'woocommerce_short_description', $description );
			if ( empty( $short_description ) ) {
				return;
			}
			?>
            <div class="wfocu-widget-containe wfocu_short_description">
				<?php echo wp_kses_post( "<{$settings['htmlTag']}>" . $short_description . "</{$settings['htmlTag']}>" ); ?>
            </div>
			<?php
		}

		public function defaultCSS() {

			$defaultCSS = "
			
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Guten_Product_Short_Desc();
}