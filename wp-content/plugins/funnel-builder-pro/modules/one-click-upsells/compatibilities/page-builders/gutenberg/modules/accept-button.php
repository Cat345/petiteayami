<?php
if ( ! class_exists( 'WFOCU_Guten_Accept_Button' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Guten_Accept_Button extends WFOCU_Guten_Field {
		public $slug  = 'wfocu_accept_button';
		protected $id = 'wfocu_accept_button';

		public function __construct() {

			$this->name = __( 'WF Accept Button' );
			parent::__construct();
		}

		public function get_icon_html( $icon ) {
			?>
			<span class="wfocu-button-icon">
			<i class="fa <?php echo esc_js( $icon ); ?>" aria-hidden="true"></i>
		</span>
			<?php
		}

		public function html( $settings, $content = '' ) {

			$sel_product_key     = isset( $settings['product'] ) ? $settings['product'] : '';
			$product_key         = WFOCU_Common::default_selected_product_key( $sel_product_key );
			$_product_key        = ( $product_key !== false ) ? $product_key : $sel_product_key;
			$product             = WFOCU_Common::default_selected_product( $_product_key );
			$settings['product'] = $_product_key;
			$product_id          = '';
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			}
			ob_start();
			do_action( 'wfocu_add_custom_html_above_accept_button', $product_id, $_product_key );
			$custom_html = ob_get_clean();

			return $custom_html . BWFBlocksUpsell_Render_Block::do_button_block( $settings, $content );
		}
	}

	return new WFOCU_Guten_Accept_Button();
}
