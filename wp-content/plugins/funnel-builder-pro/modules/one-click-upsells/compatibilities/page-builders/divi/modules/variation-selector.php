<?php
if ( ! class_exists( 'WFOCU_Variation_Selector' ) ) {
	class WFOCU_Variation_Selector extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			$this->ajax = true;
			parent::__construct();
		}

		public function setup_data() {
			$offer_id        = WFOCU_Core()->template_loader->get_offer_id();
			$variables       = $products = array();
			$product_options = array( '0' => '--No Product--' );

			$widget_key = 'wfocu_variation_selector';
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();

				if ( in_array( $product->type, array( 'variable', 'variable-subscription' ), true ) ) {
					array_push( $variables, $key );
				}
			}
			$condition = [
				'selected_product' => $variables,
			];
			$tab_id    = $this->add_tab( __( 'Variation Selector', 'woofunnels-upstroke-one-click-upsell' ), 5 );

			if ( empty( $variables ) ) {
				$this->add_subheading( $tab_id, __( 'Variation dropdowns will only show for Variable products.', 'woofunnels-upstroke-one-click-upsell' ) );
			} else {

				$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), $product_options, key( $product_options ) );
				$this->add_switcher( $tab_id, 'attr_value_block', __( 'Stacked', 'woofunnels-upstroke-one-click-upsell' ), 'on', $condition );

				$widget_spacing = [
					'%%order_class%%:not(.wfocu-attr_value-block-yes) .variations .value',
					'%%order_class%%:not(.wfocu-attr_value-block-yes) .variations .value',
					'%%order_class%%.wfocu-attr_value-block-yes .variations .value',
				];
				$this->add_margin( $tab_id, $widget_key . '_selector_margin', implode( ',', $widget_spacing ), '', '', $condition );
				$this->add_padding( $tab_id, $widget_key . '_selector_padding', '%%order_class%%:not(.wfocu-attr_value-block-yes) .wfocu_variation_selector_form', '', '', $condition );

				$this->style_field( $condition );
			}
		}

		public function style_field( $condition ) {
			$key = 'wfocu_variation_selector';


			$attribute_label_id = $this->add_tab( __( 'Attribute Label', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$this->add_typography( $attribute_label_id, $key . '_text_typography', '%%order_class%% .wfocu_variation_selector_form .variations label', '', '', $condition );

			$this->add_color( $attribute_label_id, $key . '_attribute_color', '%%order_class%% .variations label', '', '#414349', $condition );
			$this->add_background_color( $attribute_label_id, $key . '_attribute_bg_color', '%%order_class%% .variations label', '', '', $condition );


			$attribute_value_id = $this->add_tab( __( 'Attribute Value', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$this->add_typography( $attribute_value_id, $key . '_attr_value_typography', '%%order_class%% .wfocu_variation_selector_form .variations select', '', '', $condition );

			$this->add_color( $attribute_value_id, $key . '_attr_value_text_color', '%%order_class%% .variations .value select', '', '#333', $condition );
			$this->add_background_color( $attribute_value_id, $key . '_attr_value_bg_color', '%%order_class%% .variations .value select', '#ffffff', '', $condition );

			$this->add_border( $attribute_value_id, $key . '_attr_value_border', '%%order_class%% .variations .value select', $condition );

			$this->add_margin( $attribute_value_id, $key . '_attr_value_margin', '%%order_class%% .variations .value select', '', '', $condition );
			$this->add_padding( $attribute_value_id, $key . '_attr_value_padding', '%%order_class%% .variations .value select', '', '', $condition );

			$default = [ 'default' => '100%', 'unit' => '%' ];
			$this->add_width( $attribute_value_id, 'attr_value_width', [
				'%%order_class%% .wfocu-product-attr-wrapper',

			], __( 'Width', 'woofunnels-upstroke-one-click-upsell' ), $default, $condition, true );

			$this->add_text_alignments( $attribute_value_id, 'align', '%%order_class%%', '', 'left', $condition );


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

			$sel_product = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return '';
			}

			$is_variable = false;

			if ( ! empty( $product_key ) ) {

				if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
					$is_variable = true;
				}
			}

			if ( false === $is_variable ) {
				return '';
			}

			ob_start();
			if ( 'on' === $this->props['attr_value_block'] ) {
				ET_Builder_Element::set_style( $render_slug, array(
					'selector'    => '%%order_class%% .variations td',
					'declaration' => "display: block;",
				) );
				ET_Builder_Element::set_style( $render_slug, array(
					'selector'    => '%%order_class%% .variations td label, %%order_class%% .variations td select option',
					'declaration' => "font-weight: normal;",
				) );
			}
			if ( ! empty( $product_key ) ) {
				if ( true === $is_variable ) {
					echo do_shortcode( '[wfocu_variation_selector_form key="' . $product_key . '"]' );
				}
			}

			return ob_get_clean();

		}


	}

	return new WFOCU_Variation_Selector;
}