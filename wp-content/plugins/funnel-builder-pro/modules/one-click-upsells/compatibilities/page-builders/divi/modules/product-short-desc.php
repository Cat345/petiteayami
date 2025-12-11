<?php
if ( ! class_exists( 'WFOCU_Product_Short_Desc' ) ) {
	class WFOCU_Product_Short_Desc extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			$this->ajax = true;
			parent::__construct();
		}

		public function setup_data() {

			$key    = "wfocu_product_short_desc";
			$tab_id = $this->add_tab( __( 'Offer Product Description', 'woofunnels-upstroke-one-click-upsell' ), 5 );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );

			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_text_alignments( $tab_id, $key . '_text_align', '%%order_class%% .wfocu-widget-container p' );
			$this->add_typography( $tab_id, $key . '_typography', '%%order_class%%' );

			$color_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $color_id, $key . '_title_color', '%%order_class%% .wfocu-widget-container', __( 'Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349' );
			$this->add_background_color( $color_id, $key . '_title_bg_color', '%%order_class%% .wfocu-widget-container', 'transparent', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

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

			$this->add_border( $border_id, $key . '_border', '%%order_class%% .wfocu-widget-container', [], $default_args );
			$this->add_box_shadow( $border_id, $key . '_box_shadow', '%%order_class%% .wfocu-widget-container', [ 'enable' => 'off', 'vertical' => 0, 'color' => '#00B211' ] );

			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $spacing_id, $key . '_text_margin', '%%order_class%% .wfocu-widget-container' );
			$this->add_padding( $spacing_id, $key . '_text_padding', '%%order_class%% .wfocu-widget-container' );


		}

		public function html( $attrs, $content = null, $render_slug = '' ) {
			ob_start();
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}
			$sel_product  = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key  = WFOCU_Core()->template_loader->default_product_key( $sel_product );
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
            <div class="wfocu-widget-container">
				<?php echo $short_description; // WPCS: XSS ok. ?>
            </div>
			<?php
			return ob_get_clean();
		}


	}

	return new WFOCU_Product_Short_Desc();
}