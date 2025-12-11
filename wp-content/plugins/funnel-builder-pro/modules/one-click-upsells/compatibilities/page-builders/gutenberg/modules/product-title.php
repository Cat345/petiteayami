<?php
if ( ! class_exists( 'WFOCU_Guten_Product_Title' ) ) {
	class WFOCU_Guten_Product_Title extends WFOCU_Guten_Field {
		private $products = [];
		public $slug = 'wfocu_product_title';
		protected $id = 'wfocu_product_title';

		public function __construct() {
			$this->ajax = true;
			$this->name = __( "WF Product Title" );
			parent::__construct();
		}

		public function html( $settings ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$sel_product_key = isset( $settings['product'] ) ? $settings['product'] : '';
			$title           = __( 'Product Title', 'woofunnels-upstroke-one-click-upsell' );

			$product_key = WFOCU_Common::default_selected_product_key( $sel_product_key );
			$product_key = ( $product_key !== false ) ? $product_key : $sel_product_key;

			if ( isset( $product_key ) && ! empty( $product_key ) ) {

				if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
					return;
				}

				$product_data = WFOCU_Core()->template_loader->product_data->products;

				if ( isset( $product_data->{$product_key} ) ) {
					$product = $product_data->{$product_key}->data;
					if ( $product instanceof WC_Product ) {
						$title = $product->get_title();
					}
				}
			}

			if ( empty( $title ) ) {
				return;
			}

			?>
            <div class="wfocu-product-title-wrapper">
				<?php echo sprintf( '<%s class="wfocu-product-title">%s</%s>', $settings['header_size'], $title, $settings['header_size'] );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
			<?php
		}

		public function defaultCSS() {

			$defaultCSS = "
		   
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Guten_Product_Title();
}