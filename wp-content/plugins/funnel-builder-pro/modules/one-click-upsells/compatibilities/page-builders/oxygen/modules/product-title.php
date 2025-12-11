<?php
if ( ! class_exists( 'WFOCU_Oxy_Product_Title' ) ) {
	class WFOCU_Oxy_Product_Title extends WFOCU_Oxy_HTML_BLOCK {
		private $products = [];
		public $slug = 'wfocu_product_title';
		protected $id = 'wfocu_product_title';

		public function __construct() {
			$this->ajax = true;
			$this->name = __( "WF Product Title" );
			parent::__construct();
		}

		public function setup_data() {
			$this->text_settings();
			$this->color_settings();
			$this->typography_settings();
			$this->spacing_setting();
			$this->border_setting();

		}

		private function text_settings() {
			$tab_id = $this->add_tab( __( 'Product', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );

			$headings = [
				'h1'  => 'H1',
				'h2'  => 'H2',
				'h3'  => 'H3',
				'h4'  => 'H4',
				'h5'  => 'H5',
				'h6'  => 'H6',
				'div' => 'div',
				'p'   => 'p',
			];
			$this->add_select( $tab_id, 'header_size', __( 'HTML Tag', 'woofunnels-upstroke-one-click-upsell' ), $headings, 'div' );
		}

		private function color_settings() {
			$tab_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $tab_id, $this->slug . '_text_color', '.wfocu-product-title-wrapper .wfocu-product-title', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349' );
			$this->add_background_color( $tab_id, $this->slug . '_background_color', '.wfocu-product-title-wrapper .wfocu-product-title', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
		}

		private function typography_settings() {

			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Title Typography' ) );
			$default = [
				'font_size'   => '20',
				'font_weight' => '600',
			];

			$this->add_text_alignments( $tab_id, $this->slug . '_alignment', '.wfocu-product-title-wrapper .wfocu-product-title' );
			$this->custom_typography( $tab_id, $this->slug . '_typography', '.wfocu-product-title-wrapper .wfocu-product-title', '', $default );

		}

		private function spacing_setting() {
			$tab_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Margin & Padding', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_margin( $tab_id, $this->slug . '_text_margin', '.wfocu-product-title-wrapper .wfocu-product-title' );
			$this->add_padding( $tab_id, $this->slug . '_text_padding', '.wfocu-product-title-wrapper .wfocu-product-title' );
		}

		private function border_setting() {
			$tab_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_border( $tab_id, $this->slug . '_border', '.wfocu-product-title-wrapper .wfocu-product-title' );
			$this->add_box_shadow( $tab_id, $this->slug . '_box_shadow', '.wfocu-product-title-wrapper .wfocu-product-title' );
		}

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$title = __( 'Product Title', 'woofunnels-upstroke-one-click-upsell' );

			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product     = WFOCU_Common::default_selected_product( $sel_product );
			if ( $product instanceof WC_Product ) {
				$title = $product->get_title();
			}

			if ( empty( $title ) ) {
				return;
			}
			$header_size = isset( $settings['header_size'] ) ? $settings['header_size'] : '';
			?>
            <div class="wfocu-product-title-wrapper">
				<?php echo sprintf( '<%s class="wfocu-product-title">%s</%s>', $header_size, $title, $header_size );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
			<?php
		}

		public function defaultCSS() {

			$defaultCSS = "
            .oxy-wfocu-product-title{
            	width:100%;
            }
            .wfocu-product-title-wrapper .wfocu-product-title {
                font-size: 20px;
                font-weight: 600;
                line-height: 1.5;
                color: #414349;
                background-color: transparent;
                box-shadow: none;
            }
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Oxy_Product_Title();
}