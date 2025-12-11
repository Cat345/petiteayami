<?php
if ( ! class_exists( 'WFOCU_Product_Title' ) ) {
	class WFOCU_Product_Title extends WFOCU_Divi_HTML_BLOCK {
		private $products = [];

		public function __construct() {
			$this->ajax = true;
			parent::__construct();
		}

		public function setup_data() {

			$tab_id = $this->add_tab( __( 'Product Title', 'woofunnels-upstroke-one-click-upsell' ), 5 );
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

			$this->add_text_alignments( $tab_id, 'align', '%%order_class%% .wfocu-product-title' );

			$this->style_field();
		}

		public function style_field() {
			$key               = "wfocu_product_title";
			$tab_id            = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$font_side_default = [ 'default' => '20px', 'unit' => 'px' ];
			$default           = '|600|||||||';
			$this->add_typography( $tab_id, $key . '_typography', '%%order_class%% .wfocu-product-title-wrapper .wfocu-product-title', '', $default, [], $font_side_default );

			$color_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $color_id, $key . '_title_color', '%%order_class%% .wfocu-product-title-wrapper .wfocu-product-title', __( 'Color', 'elementor' ), '#414349' );
			$this->add_background_color( $color_id, $key . '_title_bg_color', '%%order_class%% .wfocu-product-title-wrapper .wfocu-product-title', 'transparent', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

			$border_id    = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$default_args = [
				'border_type'          => 'none',
				'border_width_top'     => '1',
				'border_width_bottom'  => '1',
				'border_width_left'    => '1',
				'border_width_right'   => '1',
				'border_radius_top'    => '0',
				'border_radius_bottom' => '0',
				'border_radius_left'   => '0',
				'border_radius_right'  => '0',
				'border_color'         => '#dddddd',
			];

			$this->add_border( $border_id, $key . '_border', '%%order_class%%', [], $default_args );
			$this->add_box_shadow( $border_id, $key . '_box_shadow', '%%order_class%% .wfocu-product-title', [ 'enable' => 'off', 'vertical' => 0, 'color' => '#00B211' ] );

			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $spacing_id, $key . '_text_margin', '%%order_class%%' );
			$this->add_padding( $spacing_id, $key . '_text_padding', '%%order_class%%' );


			/**
			 * @todo Need to add Text Shadow Field
			 */
		}

		public function html( $attrs, $content = null, $render_slug = '' ) {
			$title       = __( 'Product Title', 'woofunnels-upstroke-one-click-upsell' );
			$sel_product = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;

			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}
			if ( $product instanceof WC_Product ) {
				$title = $product->get_title();
			}

			if ( empty( $title ) ) {
				return;
			}
			ob_start();
			?>
            <div class="wfocu-product-title-wrapper">
				<?php echo sprintf( '<%s class="wfocu-product-title">%s</%s>', $this->props['header_size'], $title, $this->props['header_size'] ); ?>
            </div>
			<?php
			return ob_get_clean();
		}


	}

	return new WFOCU_Product_Title();
}