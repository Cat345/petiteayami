<?php
if ( ! class_exists( 'WFOCU_Quantity_Selector' ) ) {
	class WFOCU_Quantity_Selector extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			$this->ajax = true;
			parent::__construct();
		}

		public function setup_data() {
			$offer_id             = WFOCU_Core()->template_loader->get_offer_id();
			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;

			$tab_id = $this->add_tab( __( 'Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ), 5 );

			if ( false === $qty_selector_enabled ) {
				$this->add_subheading( $tab_id, __( 'Quantity selector is not available for this offer. Kindly allow customer to chose the quantity while purchasing this upsell product(s) from "Offers" tab.', 'woofunnels-upstroke-one-click-upsell' ) );

				return;
			}

			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );

			$this->add_text( $tab_id, 'text', __( 'Text', 'woofunnels-upstroke-one-click-upsell' ), __( 'Quantity', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_text_alignments( $tab_id, 'align', '%%order_class%%' );

			$this->add_switcher( $tab_id, 'slider_enabled', __( 'Stacked', 'woofunnels-upstroke-one-click-upsell' ), 'on' );
			$this->add_margin( $tab_id, 'qty_dropdown_margin', '%%order_class%% .wfocu-select-qty-input' );

			$this->style_field();

		}


		public function style_field() {
			$key    = "wfocu_qty_selector";
			$tab_id = $this->add_tab( __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$this->add_subheading( $tab_id, __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_typography( $tab_id, $key . '_text_typography', '%%order_class%% .wfocu-prod-qty-wrapper label' );

			$this->add_subheading( $tab_id, __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $tab_id, $key . '_text_color', '%%order_class%% .wfocu-prod-qty-wrapper label', __( 'Text Color', 'elementor' ), '#333' );
			$this->add_background_color( $tab_id, $key . '_bg_color', '%%order_class%% .wfocu-prod-qty-wrapper label', '', __( 'Background Color', 'elementor' ) );
			$this->add_margin( $tab_id, $key . '_qty_block_margin', '%%order_class%% .wfocu-prod-qty-wrapper label' );


			$qty_tab_id = $this->add_tab( __( 'Dropdown', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$default    = [ 'default' => '100%', 'unit' => '%' ];
			$this->add_width( $qty_tab_id, 'qty_dropdown_width', [
				'%%order_class%%  .wfocu-prod-qty-wrapper',


			], '', $default, [], true );
			$this->add_typography( $qty_tab_id, $key . '_qty_select', '%%order_class%% .wfocu-prod-qty-wrapper .wfocu-select-qty-input' );
			$this->add_subheading( $qty_tab_id, __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $qty_tab_id, 'qty_dropdown_color', '%%order_class%% .wfocu-prod-qty-wrapper .wfocu-select-qty-input', __( 'Text Color', 'elementor' ), '#333' );
			$this->add_background_color( $qty_tab_id, 'qty_dropdown_bg_color', '%%order_class%% .wfocu-prod-qty-wrapper .wfocu-select-qty-input', '#ffffff' );

			$this->add_border( $qty_tab_id, 'qty_dropdown_border', '%%order_class%% .wfocu-prod-qty-wrapper .wfocu-select-qty-input' );
			$this->add_padding( $qty_tab_id, 'qty_dropdown_padding', '%%order_class%% .wfocu-prod-qty-wrapper .wfocu-select-qty-input' );


			$border_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$default_args = [
				'border_type'          => 'none',
				'border_width_top'     => '0',
				'border_width_bottom'  => '0',
				'border_width_left'    => '0',
				'border_width_right'   => '0',
				'border_radius_top'    => '0',
				'border_radius_bottom' => '0',
				'border_radius_left'   => '0',
				'border_radius_right'  => '0',
				'border_color'         => '#fff',
			];


			$this->add_border( $border_id, $key . '_border', '%%order_class%%', [], $default_args );

			$this->add_box_shadow( $border_id, $key . '_box_shadow', '%%order_class%% ' );

			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $spacing_id, $key . '_margin', '%%order_class%% ' );
			$this->add_padding( $spacing_id, $key . '_padding', '%%order_class%% ' );

		}

		public function html( $attrs, $content = null, $render_slug = '' ) {

			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return '';
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$sel_product  = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key  = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}

			if ( ! $product instanceof WC_Product ) {
				return '';
			}

			$offer_id             = WFOCU_Core()->template_loader->get_offer_id();
			$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;
			$qty_text             = isset( $this->props['text'] ) ? $this->props['text'] : '';
			if ( false === $qty_selector_enabled ) {
				return '';
			}

			$class_name = "wfocu_proqty_inline";
			ob_start();
			if ( 'on' == $this->props['slider_enabled'] ) {
				$class_name = "wfocu_proqty_block";
				ET_Builder_Element::set_style( $render_slug, array(
					'selector'    => '%%order_class%% .wfocu-prod-qty-wrapper label',
					'declaration' => "display: block; background: transparent; font-weight: normal;",
				) );
				if ( wp_doing_ajax() ) {
					echo "<style> .wfocu-prod-qty-wrapper label{display:block !important;}</style>";
				}

			}

			if ( ! empty( $product_key ) ) {
				echo "<div class=$class_name>";
				echo do_shortcode( '[wfocu_qty_selector key="' . $product_key . '" label="' . $qty_text . '"]' );
				echo "</div>";

			}

			return ob_get_clean();
		}


	}

	return new WFOCU_Quantity_Selector;
}