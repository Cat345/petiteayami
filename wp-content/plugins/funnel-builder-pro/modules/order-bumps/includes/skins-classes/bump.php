<?php
if ( ! class_exists( 'WFOB_Bump' ) ) {


	class WFOB_Bump {

		private $wfob_id = 0;
		protected $products = [];
		private $design_data = [];
		private $settings = [];
		private $bump_name = '';
		protected $bumps_html = [];
		protected $single_bump_html = '';

		protected static $slug = 'layout_1';
		protected $is_variable_product = false;
		protected $cart_variation_id = 0;
		protected $cart_item_key = '';
		protected $cart_item = [];
		protected $wc_product_object = null;
		protected $blink_url = "";
		protected $selected_layout = "";
		protected $header_enable_pointing_arrow = "false";
		protected $dynamic_css = [];
		protected $temp_dynamic_css = [];
		public $dynamic_inline_css = '';
		protected $fields_labels = [];
		protected $wfob_bump_products = [];
		protected $css_print_already = false;

		protected $field_changes = [];
		protected $wfob_default_model = [];
		protected $wfob_dynamic_css = [];
		protected $bump_all_selectors = [];

		public $override_layout_design_data = [
			'layout_1'  => [
				'heading_box_padding'          => '10 12 10 12',
				'heading_box_border_radius'    => '8',
				'featured_image_border_radius' => '8',
				'price_sale_font_size'         => '14',
				'price_color'                  => '#353030',
				'price_sale_color'             => '#e15334',
				'box_border_radius'            => '8',
				'exclusive_content_bg_color'   => '#D80027',
				'exclusive_content_color'      => '#ffffff',
				'social_proof_enable'          => "true",
			],
			'layout_2'  => [
				'heading_box_padding'          => '10 12 10 12',
				'heading_box_border_radius'    => '8',
				'featured_image_border_radius' => '8',
				'box_border_radius'            => '8',
				'price_sale_font_size'         => '14',
				'price_color'                  => '#ffffff',
				'price_sale_color'             => '#ffffff',
				'social_proof_enable'          => "true",
			],
			'layout_3'  => [
				'price_sale_font_size'         => '14',
				'price_color'                  => '#353030',
				'price_sale_color'             => '#e15334',
				'add_button_border_radius'     => '4',
				'featured_image_border_radius' => '8',
				'box_border_radius'            => '8',


			],
			'layout_4'  => [
				'price_sale_font_size'         => '14',
				'price_color'                  => '#353030',
				'price_sale_color'             => '#e15334',
				'add_button_border_radius'     => '4',
				'featured_image_border_radius' => '8',
				'box_border_radius'            => '8',
			],
			'layout_5'  => [
				'price_sale_font_size'         => '14',
				'price_sale_color'             => '#353030',
				'heading_box_border_radius'    => '4',
				'featured_image_border_radius' => '8',
				'box_border_radius'            => '8',
				'exclusive_content_bg_color'   => '#09B29C',
				'exclusive_content_color'      => '#ffffff',
				'social_proof_enable'          => "true",
			],
			'layout_6'  => [
				'exclusive_content_enable'     => "true",
				'heading_box_border_style'     => "dashed",
				'heading_box_border_color'     => "#82A6DA",
				'price_sale_font_size'         => '14',
				'price_sale_color'             => '#353030',
				'heading_box_border_radius'    => '0',
				'featured_image_border_radius' => '8',
				'box_border_radius'            => '8',
				'exclusive_content_bg_color'   => '#E15333',
				'exclusive_content_color'      => '#ffffff',
				'social_proof_enable'          => "true",
			],
			'layout_7'  => [
				'exclusive_content_bg_color' => '#09B29C',
				'exclusive_content_color'    => '#ffffff',
				'social_proof_enable'        => "true",
			],
			'layout_8'  => [
				'exclusive_content_bg_color' => '#ED1A55',
				'exclusive_content_color'    => '#ffffff',
				'social_proof_enable'        => "true",
			],
			'layout_9'  => [
				'exclusive_content_bg_color' => '#353030',
				'exclusive_content_color'    => '#ffffff',
				'social_proof_enable'        => "true",
			],
			'layout_10' => [
				'exclusive_content_bg_color' => '#353030',
				'exclusive_content_color'    => '#ffffff',
				'social_proof_enable'        => "true",
			]


		];


		public function __construct( $wfob_id = 0 ) {
			if ( $wfob_id > 0 ) {
				$this->wfob_id = $wfob_id;

			}


		}


		public function pre_checked() {
			if ( 0 === $this->wfob_id ) {
				return;
			}

			if ( defined( 'REST_REQUEST' ) ) {
				return;
			}
			$settings = $this->settings;
			if ( ! isset( $settings['order_bump_auto_added'] ) || ! wc_string_to_bool( $settings['order_bump_auto_added'] ) ) {
				return;
			}
			$session_products = WFOB_Common::get_pre_checked_bumps();
			$products         = WFOB_Common::get_bump_products( $this->wfob_id );
			$product_design   = WFOB_Common::get_design_data_meta( $this->wfob_id );
			add_filter( 'woocommerce_add_cart_item', [ 'WFOB_Common', 'handle_swap_product' ], 10, 2 );
			foreach ( $products as $key => $value ) {
				if ( isset( $session_products[ $key ] ) || isset( WFOB_Common::$removed_bump_products[ $key ] ) ) {
					continue;
				}

				$this->add_to_cart( $product_design, $value, $key );
			}

		}

		public function add_to_cart( $product_design, $product, $product_key ) {


			$product_id = absint( $product['id'] );
			$quantity   = absint( $product['quantity'] );


			$product_obj = WFOB_Common::wc_get_product( $product_id );
			if ( ! $product_obj instanceof WC_Product || ! $product_obj->is_purchasable() || false === WFOB_Common::check_manage_stock( $product_obj, $quantity ) ) {
				return false;
			}

			$t_k = "product_{$product_key}";
			if ( isset( $product_design["{$t_k}_title"] ) && '' != $product_design["{$t_k}_title"] ) {
				$product['title'] = $product_design["{$t_k}_title"];
			}

			$custom_image_url = '';
			$featured_image   = wc_string_to_bool( isset( $product_design["{$t_k}_featured_image"] ) ? $product_design["{$t_k}_featured_image"] : 'false' );
			if ( true == $featured_image && isset( $product_design["{$t_k}_featured_image_options"] ) && ! empty( $product_design["{$t_k}_featured_image_options"] ) ) {
				$image_options = $product_design["{$t_k}_featured_image_options"];
				if ( 'custom' == $image_options['type'] && ! empty( $image_options['custom_url'] ) ) {
					$custom_image_url = $image_options['custom_url'];
				}
			}

			// For Variable Product
			$attributes   = [];
			$variation_id = 0;

			if ( in_array( $product['product_type'], WFOB_Common::get_variation_product_type() ) ) {
				$product_id   = absint( $product['parent_product_id'] );
				$variation_id = absint( $product['id'] );
				$product_obj  = WFOB_Common::wc_get_product( $product_id );
				// if variation_id found then we fetch attributes of variation from below function
				$is_found_variation = WFOB_Common::get_first_variation( $product_obj, $variation_id );
				if ( count( $attributes ) == 0 ) {
					$attributes = $is_found_variation['attributes'];
				}
			} else if ( isset( $product['variable'] ) ) {
				$variation_id = absint( $product['default_variation'] );
				$attributes   = $product['default_variation_attr'];
			}


			if ( $variation_id > 0 ) {
				$custom_data['wfob_variable_attributes'] = $attributes;
			}


			$custom_data['_wfob_product']                 = true;
			$custom_data['_wfob_pre_checked']             = $this->wfob_id;
			$custom_data['_wfob_product_key']             = $product_key;
			$custom_data['_wfob_options']                 = $product;
			$custom_data['_wfob_options']['_wfob_id']     = $this->wfob_id;
			$custom_data['_wfob_options']['custom_image'] = $custom_image_url;

			return WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes, $custom_data );
		}

		protected function admin_product_image_field( $product, $product_key ) {
			$schema   = [];
			$schema[] = [
				"type"         => "toggle",
				"key"          => "product_" . $product_key . "_featured_image",
				"label"        => __( "Product Image", 'woofunnels-order-bump' ),
				"selectors"    => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]',
				"contentClass" => 'wfob_enable_image',


			];

			$schema[] = [
				"type"               => "image",
				"key"                => "product_" . $product_key . "_featured_image_options",
				"label"              => '',
				"selectors"          => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_pro_image_wrap',
				'alignmentSelectors' => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]',
				'alignmentClassList' => [
					"top"   => "wfob_img_position_top",
					"left"  => "wfob_img_position_left",
					"right" => "wfob_img_position_right"
				],
				'widthSelectors'     => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_pro_image_wrap',
				"toggler"            => [
					'key'   => "product_" . $product_key . "_featured_image",
					"value" => true
				],
			];

			return $schema;
		}

		protected function get_product_content_schema( $product, $product_key ) {

			$schema = [];

			$description_richeditor = __( 'Use merge tag {{quantity_incrementer}} to show the quantity changer', 'woofunnels-order-bump' );

			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_title",
				"label"     => __( "Call To Action Text", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_title',
				"hint"      => "Use merge tag {{product_name}} to show product name dynamically."
			];

			$schema[] = [
				"type"      => "richeditor",
				"key"       => "product_" . $product_key . "_description",
				"label"     => __( "Description", 'woofunnels-order-bump' ),
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_skin_description',
				"hint"      => $description_richeditor
			];
			$schema[] = [
				"type"         => "checkbox",
				"key"          => "product_" . $product_key . "_exclusive_content_enable",
				"label"        => __( "Add Exclusive Offer Text", 'woofunnels-order-bump' ),
				'contentClass' => 'wfob_active_exclusive',
				"selectors"    => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]',
			];

			$schema[] = [
				"type"  => "text",
				"key"   => "product_" . $product_key . "_exclusive_content",
				"label" => '',

				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob_exclusive_content span',
				'toggler'   => [
					'key'   => "product_" . $product_key . "_exclusive_content_enable",
					'value' => true
				],

			];

			$schema[] = [
				"type"         => "checkbox",
				"key"          => "product_" . $product_key . "_social_proof_enable",
				"label"        => __( "Enable Social Proof Tool Tip", 'woofunnels-order-bump' ),
				'contentClass' => 'wfob_active_social_proof',
				"selectors"    => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]',
			];

			$schema[] = [
				"type"      => "text",
				"key"       => "product_" . $product_key . "_social_proof_heading",
				"label"     => '',
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob-social-proof-tooltip .wfob-social-proof-tooltip-header',
				'toggler'   => [
					'key'   => "product_" . $product_key . "_social_proof_enable",
					'value' => true
				],

			];

			$schema[] = [
				"type"      => "richeditor",
				"key"       => "product_" . $product_key . "_social_proof_content",
				"label"     => '',
				"selectors" => 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"] .wfob-social-proof-tooltip .wfob-social-proof-tooltip-content',
				'toggler'   => [
					'key'   => "product_" . $product_key . "_social_proof_enable",
					'value' => true
				],
			];


			return $schema;
		}

		/**
		 *
		 * admin schema to use in the css & other setting at backend
		 * @return array
		 */
		public function get_admin_schema() {


			$products = WFOB_Common::get_bump_products( $this->wfob_id );


			$schema          = [];
			$temp['content'] = [];
			$product_names   = [];

			$products_key = [];

			$bump_design_data           = $this->get_design_data();
			$add_product_default_values = false;


			foreach ( $products as $key => $product ) {
				$product_obj = wc_get_product( $product['id'] );
				if ( ! $product_obj instanceof WC_Product ) {
					continue;
				}

				$html      = '';
				$content_s = $this->get_product_content_schema( $product, $key );


				$temp['content'][ $key ] = array_merge( $content_s, $this->admin_product_image_field( $product, $key ) );

				$product_names[] = [ 'id' => $key, "name" => $product['title'] ];

				if ( ! isset( $bump_design_data[ 'product_' . $key . '_title' ] ) ) {
					$add_product_default_values = true;
				}


				/**
				 * Enable Social Proof Section
				 */
				if ( ! isset( $bump_design_data[ 'product_' . $key . '_social_proof_enable' ] ) && isset( $bump_design_data['social_proof_enable'] ) ) {
					$bump_design_data[ 'product_' . $key . '_social_proof_enable' ] = $bump_design_data['social_proof_enable'];
				}

				if ( ! isset( $bump_design_data[ 'product_' . $key . '_social_proof_heading' ] ) && isset( $bump_design_data['social_proof_heading'] ) ) {
					$bump_design_data[ 'product_' . $key . '_social_proof_heading' ] = $bump_design_data['social_proof_heading'];
				}

				if ( ! isset( $bump_design_data[ 'product_' . $key . '_social_proof_content' ] ) && isset( $bump_design_data['social_proof_content'] ) ) {
					$bump_design_data[ 'product_' . $key . '_social_proof_content' ] = $bump_design_data['social_proof_content'];
				}


				unset( $content_s );

			}


			$funnel_id             = get_post_meta( $this->wfob_id, '_bwf_in_funnel', true );
			$schema['funnel_data'] = wffn_rest_funnels()->get_funnel_data( $funnel_id );
			$schema['step_data']   = wffn_rest_api_helpers()->get_step_post( $this->wfob_id );
			$schema['contents']    = $temp;


			$merge_design_data = array_merge( $bump_design_data, $products_key );

			$schema['products'] = $product_names;


			$this->print_bump( false );

			if ( ! is_array( $products ) || count( $products ) == 0 ) {
				$schema['products'] = [];

				return $schema;
			}


			$schema['design'] = $this->admin_design_fields( $merge_design_data );


			if ( isset( $this->field_changes['merged_array'] ) && is_array( $this->field_changes['field_changes'] ) && count( $this->field_changes['merged_array'] ) > 0 ) {
				foreach ( $this->field_changes['merged_array'] as $fkey => $fvalue ) {

					if ( isset( $merge_design_data[ $fkey ] ) || array_key_exists( $fkey, $this->field_changes['new_key_value_updated'] ) ) {
						$merge_design_data[ $fkey ] = $fvalue;
					}

				}
			}


			$schema['values'] = $merge_design_data;


			if ( $add_product_default_values === true && is_array( $this->wfob_bump_products ) && count( $this->wfob_bump_products ) > 0 ) {
				$schema['values'] = array_merge( $merge_design_data, $this->wfob_bump_products );

			}


			$schema['html'] = $this->bumps_html;

			/*-----------------------------------General Tab setting----------------------------------------*/


			$default_selected_bump_position = WFOB_Common::default_bump_position();
			$get_all_bump_positions         = WFOB_Common::get_bump_position();
			$options                        = [];
			$mb_options                     = [];
			if ( is_array( $get_all_bump_positions ) && count( $get_all_bump_positions ) > 0 ) {
				foreach ( $get_all_bump_positions as $bkey => $bvalue ) {

					$options[] = [
						'label' => $bvalue['name'],
						'value' => $bvalue['id'],
						'key'   => $bvalue['id'],
					];


				}
			}


			$bump_settings = WFOB_Common::get_setting_data( $this->wfob_id );
			if ( is_array( $bump_settings ) && count( $bump_settings ) > 0 && isset( $bump_settings['order_bump_position_hooks'] ) && ! empty( $bump_settings['order_bump_position_hooks'] ) ) {
				$default_selected_bump_position = $bump_settings['order_bump_position_hooks'];
			}


			$position_fields = [
				[
					'label'   => __( 'Position in Desktop', 'woofunnels-order-bump' ),
					'type'    => 'select',
					'key'     => 'order_bump_position_hooks',
					'class'   => 'bwf-field-one-full',
					'options' => $options,
				],
				[
					'label'   => __( 'Position in Mobile', 'woofunnels-order-bump' ),
					'type'    => 'select',
					'key'     => 'order_bump_position_hooks_mobile',
					'class'   => 'bwf-field-one-full',
					'options' => $options,
				],
				[
					'label'   => __( 'Pre-select Order Bump by default', 'woofunnels-order-bump' ),
					'type'    => 'bwf-toggle',
					'key'     => 'order_bump_auto_added',
					'class'   => 'bwf-field-one-full',
					'tooltip' => __( 'Enable pre-selection for Order Bumps at checkout. Useful for adding Free Products or Trials to the cart.', 'woofunnels-order-bump' )
				],
				[
					'label'   => __( 'Hide Order Bump after selection', 'woofunnels-order-bump' ),
					'type'    => 'bwf-toggle',
					'key'     => 'order_bump_auto_hide',
					'class'   => 'bwf-field-one-full',
					'tooltip' => __( 'Hide Order Bumps after selection. Add Mini Cart Widget to Checkout for easy removal.', 'woofunnels-order-bump' ),
				]
			];

			$default_hook = $bump_settings['order_bump_position_hooks'];
			if ( isset( $bump_settings['order_bump_position_hooks_mobile'] ) && ! empty( $bump_settings['order_bump_position_hooks_mobile'] ) ) {
				$default_hook = $bump_settings['order_bump_position_hooks_mobile'];
			}


			if ( strpos( $default_hook, 'mini_cart' ) !== false ) {
				$default_hook = 'woocommerce_checkout_order_review_below_payment_gateway';
			}


			$schema['bump-settings'] = [

				'values' => [
					'order_bump_position_hooks'        => $default_selected_bump_position,
					'order_bump_position_hooks_mobile' => $default_hook ?? 'woocommerce_checkout_order_review_below_payment_gateway',
					'order_bump_auto_added'            => $bump_settings['order_bump_auto_added'] ?? false,
					'order_bump_auto_hide'             => $bump_settings['order_bump_auto_hide'] ?? false,
				],
				'fields' => $position_fields,

			];


			/*-----------------------------------General Tab setting----------------------------------------*/


			ob_start();
			include WFOB_PLUGIN_DIR . '/assets/css/public.min.css';
			if ( is_rtl() ) {
				include WFOB_PLUGIN_DIR . '/assets/css/wfob-public-rtl.css';
			}

			$css_file = ob_get_clean();


			ob_start();
			include WFOB_PLUGIN_DIR . '/assets/js/wfob-bump-script.js';
			$js = ob_get_clean();


			$schema['default_css']    = $css_file;
			$schema['default_script'] = $js;


			return $schema;
		}

		public function get_order_bump_html( $print_bump ) {
			ob_start();
			$this->print_preview_bump( $print_bump );

			$html = ob_get_clean();


			return $html;
		}


		public function products_key_data( $key, $bump_design_data = [] ) {


			$product_key = 'product_' . $key;


			if ( isset( $bump_design_data[ $product_key ] ) && ! empty( $bump_design_data[ $product_key ] ) ) {

				$this->wfob_bump_products[ $product_key ] = $bump_design_data[ $product_key ];
			}


			if ( ! isset( $bump_design_data[ $product_key . '_title' ] ) && empty( $bump_design_data[ $product_key . '_title' ] ) ) {
				$this->wfob_bump_products[ $product_key . '_title' ] = $bump_design_data['product_title'];
			}


			if ( ! isset( $bump_design_data[ $product_key . '_description' ] ) && empty( $bump_design_data[ $product_key . '_description' ] ) ) {

				$this->wfob_bump_products[ $product_key . '_description' ] = $bump_design_data['product_description'];
			}


			if ( ! isset( $bump_design_data[ $product_key . '_featured_image_options' ] ) && empty( $bump_design_data[ $product_key . '_featured_image_options' ] ) ) {

				$this->wfob_bump_products[ $product_key . '_featured_image_options' ]['image_url'] = WFOB_PLUGIN_URL . '/assets/img/no-image.png';
				$this->wfob_bump_products[ $product_key . '_featured_image_options' ]['position']  = 'left';
				$this->wfob_bump_products[ $product_key . '_featured_image_options' ]['width']     = '96';
				$this->wfob_bump_products[ $product_key . '_featured_image_options' ]['type']      = 'product';


			}


			if ( ! isset( $bump_design_data[ $product_key . '_featured_image' ] ) ) {


				$this->wfob_bump_products[ $product_key . '_featured_image' ] = wc_string_to_bool( $bump_design_data['product_featured_image'] );
			}

			if ( isset( $bump_design_data['layout'] ) && ( $bump_design_data['layout'] == 'layout_3' || $bump_design_data['layout'] == 'layout_4' ) ) {


				if ( ! isset( $bump_design_data[ $product_key . '_sub_title' ] ) ) {
					$this->wfob_bump_products[ $product_key . '_sub_title' ] = $bump_design_data['product_small_title'];
				}

				if ( ! isset( $bump_design_data[ $product_key . '_small_description' ] ) ) {
					$this->wfob_bump_products[ $product_key . '_small_description' ] = $bump_design_data['product_small_description'];
				}
				if ( ! isset( $bump_design_data[ $product_key . '_add_btn_text' ] ) ) {
					$this->wfob_bump_products[ $product_key . '_add_btn_text' ] = $bump_design_data['product_add_button_text'];
				}
				if ( ! isset( $bump_design_data[ $product_key . '_added_btn_text' ] ) ) {
					$this->wfob_bump_products[ $product_key . '_added_btn_text' ] = $bump_design_data['product_added_button_text'];
				}

				if ( ! isset( $bump_design_data[ $product_key . '_remove_btn_text' ] ) ) {
					$this->wfob_bump_products[ $product_key . '_remove_btn_text' ] = $bump_design_data['product_remove_button_text'];
				}

				if ( isset( $bump_design_data['icon_on_button'] ) ) {
					$this->wfob_bump_products['icon_on_button'] = $bump_design_data['icon_on_button'];
				}


			}


			/**
			 * Enable Social Proof Section
			 */
			if ( ! isset( $bump_design_data[ $product_key . '_social_proof_enable' ] ) && isset( $bump_design_data['social_proof_enable'] ) ) {
				$this->wfob_bump_products[ $product_key . '_social_proof_enable' ] = $bump_design_data['social_proof_enable'];
			}

			if ( ! isset( $bump_design_data[ $product_key . '_social_proof_heading' ] ) && isset( $bump_design_data['social_proof_heading'] ) ) {
				$this->wfob_bump_products[ $product_key . '_social_proof_heading' ] = $bump_design_data['social_proof_heading'];
			}

			if ( ! isset( $bump_design_data[ $product_key . '_social_proof_content' ] ) && isset( $bump_design_data['social_proof_content'] ) ) {
				$this->wfob_bump_products[ $product_key . '_social_proof_content' ] = $bump_design_data['social_proof_content'];
			}


		}

		/*--------------------------------------------Bump fields-----------------------------------------------  */

		public function create_bump_field( $key, $bump_design_data, $temp_hover_keys = [], $bump_design_fields = [] ) {

			$type = isset( $bump_design_fields[ $key ]['type'] ) ? $bump_design_fields[ $key ]['type'] : '';
			if ( empty( $type ) ) {
				return [];
			}


			$label    = isset( $bump_design_fields[ $key ]['label'] ) ? $bump_design_fields[ $key ]['label'] : 'Label';
			$stylekey = isset( $bump_design_fields[ $key ]['stylekey'] ) ? $bump_design_fields[ $key ]['stylekey'] : '';


			$selector = isset( $bump_design_fields[ $key ]['selector'] ) ? $bump_design_fields[ $key ]['selector'] : [];

			$styleUnit = isset( $bump_design_fields[ $key ]['styleUnit'] ) ? $bump_design_fields[ $key ]['styleUnit'] : '';

			$class        = isset( $bump_design_fields[ $key ]['class'] ) ? $bump_design_fields[ $key ]['class'] : '';
			$options      = isset( $bump_design_fields[ $key ]['options'] ) ? $bump_design_fields[ $key ]['options'] : [];
			$contentClass = isset( $bump_design_fields[ $key ]['contentClass'] ) ? $bump_design_fields[ $key ]['contentClass'] : '';
			$toggler      = isset( $bump_design_fields[ $key ]['toggler'] ) ? $bump_design_fields[ $key ]['toggler'] : [];

			$hint = isset( $bump_design_fields[ $key ]['hint'] ) ? $bump_design_fields[ $key ]['hint'] : [];


			$field = [
				'label' => $label,
			];


			if ( ! empty( $hint ) ) {
				$field['hint'] = $hint;
			}

			if ( ! empty( $stylekey ) ) {
				$field['stylekey'] = $stylekey;
			}
			if ( ! empty( $key ) ) {
				$field['key'] = $key;
			}
			if ( ! empty( $type ) ) {
				$field['type'] = $type;
			}
			if ( ! empty( $selector ) ) {
				$field['selectors'] = $selector;
			}

			if ( ! empty( $styleUnit ) ) {
				$field['styleUnit'] = $styleUnit;
			}
			if ( ! empty( $class ) ) {
				$field['class'] = $class;
			}
			if ( ! empty( $options ) ) {
				$field['options'] = $options;
			}
			if ( ! empty( $contentClass ) ) {
				$field['contentClass'] = $contentClass;
			}
			if ( ! empty( $toggler ) ) {
				$field['toggler'] = $toggler;
			}


			if ( is_array( $temp_hover_keys ) && count( $temp_hover_keys ) > 0 && isset( $temp_hover_keys[ $key ] ) ) {
				$field['hoverSelectors'] = isset( $temp_hover_keys[ $key ]['selector'] ) ? $temp_hover_keys[ $key ]['selector'] : [];
				$field['hoverKey']       = isset( $temp_hover_keys[ $key ]['key'] ) ? $temp_hover_keys[ $key ]['key'] : [];
				$field['isHover']        = true;

			}

			return $field;
		}


		/*--------------------------------------------End-----------------------------------------------  */


		public function admin_design_fields( $bump_design_data ) {
			$selected_layout = '';
			if ( isset( $bump_design_data['layout'] ) ) {
				$selected_layout = $bump_design_data['layout'];
			}

			$bump_design_fields = $this->get_bump_design_selectors();
			$tmp_fields         = [];
			$bump_design_data   = $this->design_data;

			if ( empty( $selected_layout ) || ! is_array( $this->design_data ) || count( $this->design_data ) == 0 ) {

				return $tmp_fields;
			}


			$exclude_keys    = [
				'layout',
				'layout_name',
				'product_title',
				'product_featured_image',
				'product_description',
			];
			$temp_hover_keys = [];

			foreach ( $bump_design_fields as $key => $value ) {

				if ( isset( $this->design_data[ $key ] ) && is_array( $this->design_data[ $key ] ) && strpos( $key, 'shadow' ) === false ) {
					continue;
				}
				$val = '';
				if ( strpos( $key, 'hover' ) !== false ) {

					$tmp = $key;
					$key = str_replace( [ '_hover_', '_hover', 'hover_' ], '_', $key );

					if ( ! isset( $value['selectors'] ) ) {
						continue;
					}
					$val = isset( $this->design_data[ $key ] ) ? $this->design_data[ $key ] : '';

					$temp_hover_keys[ trim( $key, '_' ) ] = [
						'selector' => $value['selectors'],
						'key'      => $tmp,
						'value'    => $val,
					];
					continue;
				}
				$temp_without_hover_keys[ $key ] = $val;
			}


			$layout_fields = [
				'border_width',
				'border_style',
				'border_color',
				'bump_max_width',

			];

			foreach ( $bump_design_fields as $key => $value ) {

				if ( strpos( $key, 'hover' ) !== false ) {
					continue;
				}

				if ( strpos( $key, 'product_' ) !== false || in_array( $key, $exclude_keys ) ) {
					continue;
				}

				if ( ( strpos( $key, 'heading_' ) !== false || strpos( $key, 'error_' ) !== false || strpos( $key, 'point' ) !== false ) && strpos( $key, 'sub_heading' ) === false && strpos( $key, 'social_proof_tooltip_' ) === false ) {
					$group_key   = 'wfob_group_1';
					$group_label = __( "Call To Action Text", 'woofunnels-order-bump' );


				} elseif ( strpos( $key, 'featured' ) !== false ) {
					$group_key   = 'wfob_group_2';
					$group_label = __( "Image", 'woocommerce' );

				} elseif ( strpos( $key, 'content_' ) !== false && strpos( $key, 'exclusive_' ) === false && strpos( $key, 'sub_content_' ) === false ) {
					$group_key   = 'wfob_group_3';
					$group_label = __( 'Description', 'woocommerce' );


				} elseif ( strpos( $key, 'price_' ) !== false || strpos( $key, '_price' ) !== false ) {
					$group_key   = 'wfob_group_4';
					$group_label = __( "Price", 'woocommerce' );


				} elseif ( ( strpos( $key, 'box_' ) !== false || in_array( $key, $layout_fields ) ) && strpos( $key, 'content_box_padding' ) == false && strpos( $key, 'add_button' ) === false ) {
					$group_key   = 'wfob_group_5';
					$group_label = __( "Layout", 'woocommerce' );
				} elseif ( strpos( $key, 'exclusive_' ) !== false ) {
					$group_key   = 'wfob_group_6';
					$group_label = __( "Exclusive Offer text", 'woofunnels-order-bump' );
				} elseif ( strpos( $key, 'sub_heading_' ) !== false ) {
					$group_key   = 'wfob_group_7';
					$group_label = __( 'Sub Heading', 'woofunnels-order-bump' );
				} elseif ( strpos( $key, 'sub_content' ) !== false ) {
					$group_key   = 'wfob_group_8';
					$group_label = __( 'Short Description', 'woocommerce' );
				} elseif ( strpos( $key, 'add_button' ) !== false ) {

					$group_key   = 'wfob_group_9';
					$group_label = __( 'Buttons', 'woofunnels-order-bump' );
				} elseif ( strpos( $key, '_line_color' ) !== false || strpos( $key, '_switch_color' ) !== false ) {
					$group_key   = 'wfob_group_10';
					$group_label = __( 'Toggle', 'woofunnels-order-bump' );;
				} elseif ( strpos( $key, 'social_proof_tooltip_' ) !== false ) {
					$group_key   = 'wfob_group_11';
					$group_label = __( "Enable Social Proof Tool Tip", 'woocommerce' );

				}


				$tmp_fields[ $group_key ]['key']   = $group_key;
				$tmp_fields[ $group_key ]['label'] = $group_label;


				if ( is_array( $temp_hover_keys ) && count( $temp_hover_keys ) > 0 && isset( $temp_hover_keys[ $key ] ) ) {
					$value['hoverSelectors'] = isset( $temp_hover_keys[ $key ]['selector'] ) ? $temp_hover_keys[ $key ]['selector'] : [];
					$value['hoverKey']       = isset( $temp_hover_keys[ $key ]['key'] ) ? $temp_hover_keys[ $key ]['key'] : [];
					$value['isHover']        = true;

				}

				if ( isset( $value['value'] ) ) {
					unset( $value['value'] );
				}

				if ( is_array( $value ) && count( $value ) ) {
					$tmp_fields[ $group_key ]['fields'][] = $value;
				}


			}


			return array_values( $tmp_fields );


		}


		/**
		 * Get Default Setting of bump
		 * @return array
		 */
		public static function get_default_models() {
			return [];
		}


		/**
		 * For Frontend only
		 * @return void
		 */
		public function prepare_frontend_data() {

			if ( 0 == $this->wfob_id ) {
				return;
			}
			$this->setup_data();
			$this->pre_checked();
		}


		/**
		 * get Bump Id
		 * @return int
		 */
		public function get_id() {
			return $this->wfob_id;
		}

		/**
		 * Return all design Data of bump products
		 * @return array
		 */
		public function get_design_data() {
			if ( is_array( $this->design_data ) && count( $this->design_data ) > 0 ) {
				$this->design_data = WFOB_Common::check_default_bump_keys( $this->design_data );


			}

			return $this->design_data;
		}

		public function set_design_data( $design_data, $preview = false ) {
			if ( is_array( $this->design_data ) && is_array( $design_data ) ) {

				$this->design_data = $design_data;


				if ( is_null( $this->products ) || true === $preview ) {
					$unique_id      = uniqid( 'wfob_' );
					$this->products = [];

					$this->products[ $unique_id ] = [
						'title'       => $this->design_data['product_title'],
						'description' => $this->design_data['product_description'],
						'image'       => $this->design_data['product_image_url'],
						'price'       => $this->design_data['product_price'],
					];


				}


				$tmp_product                  = $this->products;
				$this->products               = [];
				$first_key                    = array_key_first( $tmp_product );
				$this->products[ $first_key ] = $tmp_product[ $first_key ];


			}


		}


		/**
		 * Return bump product is exist or not
		 * @return bool
		 */
		public function have_bumps() {
			return ( ( is_array( $this->products ) ) ? count( $this->products ) > 0 : false );
		}


		public function get_position() {


			$display_hook = $this->settings['order_bump_position_hooks'];
			if ( class_exists( 'WFACP_Mobile_Detect' ) ) {
				$detect = WFACP_Mobile_Detect::get_instance();
				if ( $detect instanceof WFACP_Mobile_Detect && ( $detect->isMobile() || $detect->isTablet() ) ) {
					if ( isset( $this->settings['order_bump_position_hooks_mobile'] ) && ! empty( $this->settings['order_bump_position_hooks_mobile'] ) ) {
						$display_hook = $this->settings['order_bump_position_hooks_mobile'];
					}
					if ( ( ! isset( $this->settings['order_bump_position_hooks_mobile'] ) ) && strpos( $this->settings['order_bump_position_hooks'], 'mini_cart' ) !== false ) {
						return 'woocommerce_checkout_order_review_below_payment_gateway';
					}
				}
			}


			$available_position = WFOB_Common::get_bump_position( true );


			if ( isset( $available_position[ $display_hook ] ) ) {
				$position = $available_position[ $display_hook ];


				return $position['id'];
			}

			return WFOB_Common::default_bump_position();
		}

		public function get_bump_html() {

			ob_start();
			$this->print_bump();

			return ob_get_clean();
		}

		public function get_bump_css() {
			ob_start();
			$this->print_css();

			return ob_get_clean();

		}


		public function print_css() {
			include WFOB_SKIN_DIR . '/style.php';
		}

		public function get_bump_name() {
			return $this->bump_name;
		}

		public function get_bump_selected_layout() {
			return $this->selected_layout;
		}

		public function is_variable_product() {
			return $this->is_variable_product;
		}

		public function is_cart_variation_id() {
			return $this->cart_variation_id;
		}


		/**
		 * Setup product and design data
		 */
		private function setup_data() {
			$post = get_post( $this->wfob_id );
			if ( is_null( $post ) ) {
				return;
			}

			$this->bump_name   = $post->post_title;
			$this->products    = WFOB_Common::get_prepared_products( $this->wfob_id );
			$this->settings    = WFOB_Common::get_setting_data( $this->wfob_id );
			$this->design_data = WFOB_Common::get_design_data_meta( $this->wfob_id );

			if ( empty( $this->design_data ) ) {
				$this->design_data = $this->get_default_design_data();
			}


			$this->selected_layout = $this->design_data['layout'];

		}


		public function maximum_bump_print() {
			if ( ! class_exists( 'WFOB_Bump_Fc' ) ) {
				return 0;
			}
			$max_bumps = WFOB_Bump_Fc::maximum_bump_print();

			return $max_bumps;
		}


		public function get_bump_cart_item_details( $product_key ) {

			if ( empty( $product_key ) || ! class_exists( 'WFOB_Common' ) ) {

				return [];
			}

			$result = WFOB_Common::get_cart_item_key( $product_key );


			if ( ! is_null( $result ) ) {
				$this->cart_item_key = $result[0];

				$this->cart_item = $result[1];
			}

			return $result;
		}

		public function get_bump_cart_item_key() {
			return $this->cart_item_key;
		}

		public function get_bump_cart_item() {
			return $this->cart_item;
		}

		public function get_bump_product_object( $cart_item = [], $data = [] ) {

			if ( ! empty( $cart_item ) && ! is_null( $cart_item ) ) {
				$qty        = $cart_item['quantity'];
				$wc_product = $cart_item['data'];
				if ( isset( $cart_item['variation_id'] ) ) {
					$cart_variation_id = $cart_item['variation_id'];
				}

			} else {

				if ( isset( $data['variable'] ) ) {

					$is_variable_product = true;
					$wc_product          = WFOB_Common::wc_get_product( $data['id'] );
					if ( isset( $data['default_variation'] ) ) {
						$variation_id = absint( $data['default_variation'] );
						$wc_product   = WFOB_Common::wc_get_product( $variation_id );
					}


				} else {
					$wc_product = WFOB_Common::wc_get_product( $data['id'] );
				}
			}
			$this->wc_product_object = $wc_product;

			return $wc_product;
		}

		public function set_product_price( $wc_product, $data, $cart_item_key ) {
			$wc_product = WFOB_Common::set_product_price( $wc_product, $data, $cart_item_key );

			$this->wc_product_object = $wc_product;

			return $wc_product;
		}

		public function get_bump_parent_product( $parent_id ) {
			return WFOB_Common::wc_get_product( $parent_id );
		}

		/*------------------------------Get Product Title------------------------------------------ */

		public function get_bump_product_title( $bump_id, $product_key, $design_data = [] ) {
			$product_title = '';

			if ( is_array( $design_data ) && count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}


			if ( isset( $design_data['product_title'] ) ) {
				$product_title = $design_data['product_title'];
			}


			if ( isset( $design_data["product_{$product_key}_title"] ) ) {
				$product_title = $design_data["product_{$product_key}_title"];
			}


			return $product_title;
		}

		/*------------------------------Get Product Description------------------------------------------ */

		public function get_bump_product_description( $bump_id, $product_key, $design_data = [], $parent_product = null ) {

			$description = '';
			if ( ! is_array( $design_data ) || count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}


			if ( isset( $design_data['product_description'] ) ) {
				$description = $design_data['product_description'];
			}

			if ( isset( $design_data["product_{$product_key}_description"] ) ) {
				$description = $design_data["product_{$product_key}_description"];
			} elseif ( empty( $description ) && $parent_product !== null ) {
				$description = $parent_product->get_short_description();
			}


			return $description;
		}


		/*------------------------------Get Other Fields------------------------------------------ */
		public function get_bump_product_other_fields( $bump_id, $product_key, $design_data = [], $key = '' ) {

			return '';

		}


		/*------------------------------Get Feature Image------------------------------------------ */
		public function is_enable_bump_product_feature_image( $bump_id, $product_key, $design_data = [] ) {

			if ( is_array( $design_data ) && count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}


			$featured_image = true;
			if ( ! isset( $design_data["product_{$product_key}_featured_image"] ) || '' == $design_data["product_{$product_key}_featured_image"] ) {
				$featured_image = false;
			} else {
				$featured_image = wc_string_to_bool( $design_data["product_{$product_key}_featured_image"] );
			}

			return $featured_image;
		}

		public function get_bump_product_feature_image_options( $bump_id, $product_key, $design_data = [] ) {
			if ( is_array( $design_data ) && count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}

			$featured_image_options = [];


			if ( isset( $design_data[ "product_" . $product_key . "_featured_image_options" ] ) ) {
				$image_options = $design_data[ "product_" . $product_key . "_featured_image_options" ];

				if ( isset( $image_options['type'] ) && 'custom' == $image_options['type'] ) {
					$featured_image_options['type'] = $image_options['type'];
					if ( ! empty( $image_options['custom_url'] ) ) {
						$featured_image_options['image_html']        = "<img src='{$image_options['custom_url']}' class='attachment-woocommerce_thumbnail size-woocommerce_thumbnail'>";
						$featured_image_options['custom_image_html'] = "<img src='{$image_options['custom_url']}' class='attachment-woocommerce_thumbnail size-woocommerce_thumbnail'>";
						$featured_image_options['image_url']         = $image_options['custom_url'];
					} else {
						ob_start();
						WFOB_PLUGIN_DIR . '/assets/img/no-image.php';
						$featured_image_options['image_html'] = ob_get_clean();
					}
				}


			}
			if ( isset( $design_data["product_{$product_key}_featured_image_options"]['position'] ) ) {
				$featured_image_options['img_position']       = $design_data["product_{$product_key}_featured_image_options"]['position'];
				$featured_image_options['image_position_cls'] = 'wfob_img_position_' . $featured_image_options['img_position'];

			}
			if ( isset( $design_data["product_{$product_key}_featured_image_options"]['width'] ) ) {
				$featured_image_options['image_width'] = $design_data["product_{$product_key}_featured_image_options"]['width'];
			}


			return $featured_image_options;
		}

		/*------------------------------------Enable Price--------------------------------------------- */

		public function is_enable_bump_product_price( $bump_id, $product_key, $design_data = [] ) {
			if ( is_array( $design_data ) && count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}

			$enable_price = true;
			if ( isset( $design_data['enable_price'] ) ) {
				if ( '0' === $design_data['enable_price'] || false === wc_string_to_bool( $design_data['enable_price'] ) ) {
					$enable_price = false;
				}
			}

			return $enable_price;
		}

		/*------------------------------------get Price Data--------------------------------------------- */

		public function get_bump_product_price_data( $wc_product, $qty = 1 ) {

			if ( ! $wc_product instanceof WC_Product ) {
				return [];
			}

			$price_data = apply_filters( 'wfob_product_switcher_price_data', [], $wc_product, $qty );
			if ( empty( $price_data ) ) {
				$price_data['regular_org'] = $wc_product->get_regular_price( 'edit' );
				$price_data['price']       = $wc_product->get_price( 'edit' );
			}
			if ( isset( $price_data['regular_org'] ) ) {
				$price_data['regular_org'] = floatval( $price_data['regular_org'] );
			}
			if ( isset( $price_data['price'] ) ) {
				$price_data['price'] = floatval( $price_data['price'] );
			}


			return $price_data;
		}

		public function print_bump_price( $final_data = [], $product_key = '' ) {


		}

		/*------------------------------------Enable Pointer--------------------------------------------- */

		public function is_enable_pointer( $bump_id, $design_data = [] ) {
			if ( is_array( $design_data ) && count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}
			$enable_pointer = '';

			if ( isset( $design_data['header_enable_pointing_arrow'] ) && wc_string_to_bool( $design_data['header_enable_pointing_arrow'] ) ) {
				$this->blink_url = WFOB_PLUGIN_URL . '/assets/img/arrow-no-blink.gif';
				$enable_pointer  = 'wfob_enable_pointer';
			}

			return $enable_pointer;
		}

		public function get_blink_url() {
			return $this->blink_url;
		}

		public function get_header_enable_pointing_arrow() {
			return $this->header_enable_pointing_arrow;
		}

		public function get_product_attributes( $cart_item, $wc_product ) {

			$product_attributes = [];
			if ( ! is_null( $cart_item ) && isset( $cart_item['variation_id'] ) ) {
				if ( is_array( $cart_item['variation'] ) && count( $cart_item['variation'] ) ) {
					$product_attributes = $cart_item['variation'];
				} elseif ( 'variation' == $cart_item['data']->get_type() ) {
					$product_attributes = $cart_item['data']->get_attributes();
				}
			} elseif ( 'variation' == $wc_product->get_type() ) {
				$product_attributes = $wc_product->get_attributes();
			}

			return $product_attributes;
		}

		public function get_price_html( $wc_product, $cart_item_key, $price_data, $qty = 1 ) {

			$cart_item = $this->get_bump_cart_item();
			if ( '' !== $cart_item_key ) {
				$price_data = WFOB_Common::get_cart_product_price_data( $wc_product, $cart_item, $cart_item['quantity'] );
			} else {
				$price_data             = WFOB_Common::get_product_price_data( $wc_product, $price_data );
				$price_data['quantity'] = $qty;
			}


			$printed_price = '';
			if ( apply_filters( 'wfob_show_product_price', true, $wc_product, $cart_item_key, $price_data ) ) {
				$printed_price = WFOB_Common::decode_merge_tags( "{{price}}", $price_data, $wc_product, [], $cart_item, $cart_item_key, '', [] );
			} else {
				$printed_price = apply_filters( 'wfob_show_product_price_placeholder', $printed_price, $wc_product, $cart_item_key, $price_data );
			}

			return $printed_price;
		}

		public function get_featured_image( $product_key, $wc_product, $parent_product, $design_data, $print_bump = true ) {

			$bump_id = $this->get_id();


			$featured_image        = $this->is_enable_bump_product_feature_image( $bump_id, $product_key, $design_data );
			$feature_image_options = $this->get_bump_product_feature_image_options( $bump_id, $product_key, $design_data );


			$default_width = isset( $feature_image_options['image_width'] ) ? $feature_image_options['image_width'] : '96';
			$default_size  = apply_filters( 'wfob_product_image_size', [ $default_width, $default_width ] );


			if ( isset( $data['variable'] ) && 'yes' == $data['variable'] && empty( $cart_item_key ) ) {
				$image_url                           = WFOB_Common::get_product_image( $parent_product, $data );
				$feature_image_options['image_html'] = "<img src='{$image_url}' class='attachment-woocommerce_thumbnail size-woocommerce_thumbnail'>";
				$feature_image_options['image_url']  = $image_url;
			} else {
				$feature_image_options['image_html'] = $wc_product->get_image();


				$tmp_url = wp_get_attachment_image_src( get_post_thumbnail_id( $wc_product->get_iD() ) );


				if ( isset( $feature_image_options['type'] ) && $feature_image_options['type'] == 'custom' ) {

					$feature_image_options['image_url']  = $feature_image_options['image_url'];
					$feature_image_options['image_html'] = $feature_image_options['custom_image_html'];

				} elseif ( is_array( $tmp_url ) && count( $tmp_url ) > 0 && isset( $tmp_url[0] ) ) {
					$feature_image_options['image_url'] = $tmp_url[0];
				}

			}


			$image_position_cls    = 'wfob_img_position_left';
			$image_width           = '96';
			$img_position          = 'center';
			$image_url             = WFOB_PLUGIN_URL . '/admin/assets/img/product_default_icon.jpg';
			$image_html            = '<img src=' . $image_url . ' alt="">';
			$active_image_settings = true;
			if ( false !== strpos( $_SERVER['REQUEST_URI'], '/skins/all/' ) ) {
				$active_image_settings = false;
			}

			if ( $active_image_settings !== false && is_array( $feature_image_options ) && count( $feature_image_options ) > 0 ) {
				if ( isset( $feature_image_options['img_position'] ) ) {
					$img_position = $feature_image_options['img_position'];
				}

				if ( isset( $feature_image_options['image_position_cls'] ) ) {
					$image_position_cls = $feature_image_options['image_position_cls'];
				}

				if ( isset( $feature_image_options['image_width'] ) ) {
					$image_width = $feature_image_options['image_width'];
				}
				if ( isset( $feature_image_options['image_html'] ) ) {
					$image_html = $feature_image_options['image_html'];
				}
				if ( isset( $feature_image_options['image_url'] ) ) {
					$image_url = $feature_image_options['image_url'];
				}


			}


			return [ $image_html, $image_position_cls, $image_width, $img_position, $featured_image, $image_url ];
		}

		/*----------------------------------------Get Bump Heading--------------------------------------------  */
		public function get_bump_heading( $product_key, $wc_product, $cart_item_key, $data, $selected_layout, $skin_type ) {
			$product_title = '';
			if ( ! isset( $design_data["product_{$product_key}_title"] ) || '' == $design_data["product_{$product_key}_title"] ) {
				$product_title = $wc_product->get_title();
				if ( in_array( $wc_product->get_type(), WFOB_Common::get_variation_product_type() ) ) {
					if ( absint( $data['parent_product_id'] ) > 0 || '' !== $cart_item_key ) {
						$product_title = $wc_product->get_name();


					}
				}
			} else {
				$product_title = $design_data["product_{$product_key}_title"];
			}


			return $product_title;

		}

		/*----------------------------------------Get Bump Description-----------------------------------------  */
		public function get_bump_description( $product_key, $parent_product, $cart_item_key, $data, $selected_layout, $skin_type ) {
			$description = '';
			if ( ! isset( $design_data["product_{$product_key}_description"] ) || '' == $design_data["product_{$product_key}_description"] ) {
				$description = $parent_product->get_short_description();
			} else {
				$description = $design_data["product_{$product_key}_description"];
			}

			return $description;

		}

		/*----------------------------------------Get Bump Feature Image----------------------------------------  */
		public function get_bump_featured_image( $product_key, $selected_layout, $skin_type ) {
			$featured_image = true;
			if ( ! isset( $design_data["product_{$product_key}_featured_image"] ) || '' == $design_data["product_{$product_key}_featured_image"] ) {
				$featured_image = true;
			} else {
				$featured_image = $design_data["product_{$product_key}_featured_image"];
			}

			return $featured_image;
		}

		/*----------------------------------------Get Bump Printed Price------------------------------------  */
		public function get_bump_printed_price( $product_key, $wc_product, $cart_item_key, $price_data, $selected_layout, $skin_type, $cart_item, $design_data, $data ) {


			$printed_price = '';

			if ( apply_filters( 'wfob_show_product_price', true, $wc_product, $cart_item_key, $price_data ) ) {
				$printed_price = WFOB_Common::decode_merge_tags( "{{price}}", $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );


			} else {
				$printed_price = apply_filters( 'wfob_show_product_price_placeholder', $printed_price, $wc_product, $cart_item_key, $price_data );
			}


			return $printed_price;
		}

		/**
		 * Print all bump ui at checkout page
		 */
		public function print_bump( $print_bump = true, $products_key = [] ) {


			if ( ! $this->have_bumps() ) {
				return '';
			}
			$this->wfob_default_model = $this->get_default_models();


			$max_bumps = '';

			$max_bumps = WFOB_Bump_Fc::maximum_bump_print();


			$print_css    = false;
			$bump_id      = $this->get_id();
			$preview_bump = false;

			$design_data = $this->get_design_data();

			$this->wfob_default_model['exclusive_content_color'] = "#002565";
			$design_data['exclusive_content_color']              = "#666756";


			$final_data           = [];
			$image_position_style = [];
			$dynamic_temp_style   = [];


			foreach ( $this->products as $product_key => $data ) {

				$this->products_key_data( $product_key, $design_data );


				if ( is_array( $this->wfob_bump_products ) && count( $this->wfob_bump_products ) > 0 ) {

					$design_data = array_merge( $design_data, $this->wfob_bump_products );

				}


				if ( '' !== $max_bumps && $max_bumps > 0 && count( WFOB_Bump_Fc::$number_of_bump_print ) >= $max_bumps ) {
					break;
				}


				// Product not in stock then do not print bump
				if ( ! isset( $data['stock'] ) || false == $data['stock'] && $print_bump == true ) {

					continue;
				}


				$data['item_key'] = $product_key;
				$print_css        = true;


				$cart_item_key       = '';
				$cart_item           = [];
				$result              = $this->get_bump_cart_item_details( $product_key );
				$is_variable_product = $this->is_variable_product();
				$cart_variation_id   = $this->is_cart_variation_id();;

				/*---------------------------------Parent ID--------------------------------*/

				$parent_id = absint( $data['id'] );
				if ( $data['parent_product_id'] && $data['parent_product_id'] > 0 ) {
					$parent_id = absint( $data['parent_product_id'] );
				}

				if ( ! is_null( $result ) ) {
					$cart_item_key = $this->get_bump_cart_item_key();
					$cart_item     = $this->get_bump_cart_item();
				}

				if ( isset( $data['quantity'] ) ) {
					$qty = absint( $data['quantity'] );
				}


				/*---------------------------------Product Object--------------------------------*/


				if ( ! empty( $cart_item ) && ! is_null( $cart_item ) ) {
					$qty        = $cart_item['quantity'];
					$wc_product = $cart_item['data'];
					if ( isset( $cart_item['variation_id'] ) ) {
						$cart_variation_id = $cart_item['variation_id'];
					}

				} else {

					if ( isset( $data['variable'] ) ) {

						$is_variable_product = true;
						$wc_product          = WFOB_Common::wc_get_product( $data['id'] );
						if ( isset( $data['default_variation'] ) ) {
							$variation_id = absint( $data['default_variation'] );
							$wc_product   = WFOB_Common::wc_get_product( $variation_id );
						}


					} else {
						$wc_product = WFOB_Common::wc_get_product( $data['id'] );
					}
				}
				$this->wc_product_object = $wc_product;


				if ( ! $wc_product instanceof WC_Product || ( ! $wc_product->is_purchasable() && '' == $cart_item_key ) ) {
					break;
				}


				$wc_product = $this->set_product_price( $wc_product, $data, $cart_item_key );

				$parent_product = $this->get_bump_parent_product( $parent_id );


				/*------------------------------Get Product Title--------------------------------------- */

				$product_title    = $this->get_bump_product_title( $bump_id, $product_key, $design_data );
				$wc_product_name  = $wc_product->get_name();
				$wc_product_price = $wc_product->get_price();

				/*---------------------------------------Title Heading---------------------------- */


				$titleHeading = $product_title;

				if ( ! empty( $product_title ) ) {
					$final_data[ $product_key ]['title'] = $product_title;
				}

				/*------------------------------Get Product Description---------------------------------- */

				$description = $this->get_bump_product_description( $bump_id, $product_key, $design_data, $parent_product );


				if ( ! empty( $description ) ) {
					$final_data[ $product_key ]['description'] = $description;
				}


				/*-----------------------------------Variable Checkbox---------------------------- */

				$variable_checkbox = '';
				if ( isset( $data['variable'] ) && $cart_variation_id == 0 ) {
					$variable_checkbox                                   = 'wfob_choose_variation';
					$final_data[ $product_key ]['wfob_choose_variation'] = $variable_checkbox;
				}


				/*--------------------------------------- Product Price Data--------------------------------- */

				$price_data = $this->get_bump_product_price_data( $wc_product, $qty );

				$price_data['regular_org'] *= $qty;
				$price_data['price']       *= $qty;
				$price_data['quantity']    = $qty;


				if ( is_array( $price_data ) && count( $price_data ) > 0 ) {
					$final_data[ $product_key ]['price_data'] = $price_data;
				}


				/*---------------------------------------Enable Product Price--------------------------------- */

				$enable_price = $this->is_enable_bump_product_price( $bump_id, $product_key, $design_data );

				if ( ! empty( $enable_price ) ) {
					$final_data[ $product_key ]['enable_price'] = $enable_price;
				}


				/*---------------------------------------Product Attributes--------------------------------- */

				$product_attributes = $this->get_product_attributes( $cart_item, $wc_product );

				/*---------------------------------------is Enable Pointer------------------------------ */

				$enable_pointer = $this->is_enable_pointer( $bump_id, $design_data );

				if ( ! empty( $enable_pointer ) ) {
					$final_data[ $product_key ]['enable_pointer'] = $enable_pointer;
				}
				/*---------------------------------------Print Price------------------------------ */
				$printed_price = $this->get_price_html( $wc_product, $cart_item_key, $price_data, $qty );


				/* ----------------------------------------Tax label with price------------------------- */

				$tax_label = '';
				if ( true === $print_bump && apply_filters( 'wfob_display_tax_label_with_price', false ) ) {
					$tax_label = WFOB_Common::get_tax_label( $wc_product );
					if ( ! empty( $tax_label ) ) {
						$printed_price .= $tax_label;
					}
				}


				if ( ! empty( $printed_price ) ) {
					$final_data[ $product_key ]['printed_price'] = $printed_price;
				}


				/*---------------------------------------Output Response ----------------------- */

				$output_response    = WFOB_AJAX_Controller::output_resp();
				$output_response    = isset( $output_response['response'] ) && isset( $output_response['response'][ $product_key ] ) ? $output_response['response'][ $product_key ] : $output_response;
				$wfob_error_message = '';
				if ( ! empty( $output_response ) && false == $output_response['status'] && isset( $output_response['error'] ) && $output_response['wfob_id'] == $this->get_id() && $output_response['wfob_product_key'] == $product_key ) {
					$wfob_error_message = $output_response['error'];
				}


				/*-----------------------------------Get Feature Image-------------------------------------- */
				list( $image_html, $image_position_cls, $image_width, $img_position, $featured_image, $image_url ) = $this->get_featured_image( $product_key, $wc_product, $parent_product, $design_data, $print_bump );


				if ( isset( $image_url ) && ! empty( $image_url ) ) {
					if ( isset( $this->wfob_bump_products[ "product_" . $product_key . "_featured_image_options" ]['image_url'] ) ) {
						$this->wfob_bump_products[ "product_" . $product_key . "_featured_image_options" ]['image_url'] = $image_url;

					}
				}


				/*---------------------------------------Selected Layout -------------------------------------- */

				$selected_layout = $this->design_data['layout'];

				/*---------------------------------------header Enable Pointing Arrow -------------------------- */

				$header_enable_pointing_arrow = $this->get_header_enable_pointing_arrow();

				/*---------------------------------------Other Classes ----------------------------------------- */

				$checkbox_class       = 'wfob_bump_product';
				$allow_choose_options = apply_filters( 'wfob_allow_choose_options', isset( $data['variable'] ), $wc_product, $cart_item_key );

				if ( ( true == $allow_choose_options || ( isset( $data['variable'] ) && $cart_variation_id == 0 ) ) && empty( $cart_item_key ) ) {
					$checkbox_class = 'wfob_choose_variation';
				}


				/*---------------------------------------Disabled --------------------------------------------- */

				$disabled = '';
				if ( true === apply_filters( 'wfob_disabled_checkbox', false, $product_key ) ) {
					$disabled = "disabled";
				}

				/*---------------------------------------Wrapper  Class -------------------------------------- */

				$css_class = [
					'wfob_bump',
					'wfob_bump_section',
					'wfob_clear',
					"wfob_" . $selected_layout,
				];
				$skin_type = '';


				if ( $selected_layout == 'layout_1' || $selected_layout == 'layout_2' || $selected_layout == 'layout_8' ) {
					$css_class[] = 'bump_skin_type_1';
					$skin_type   = 'bump_skin_type_1';
				} elseif ( $selected_layout == 'layout_3' || $selected_layout == 'layout_4' || $selected_layout == 'layout_9' || $selected_layout == 'layout_10' ) {
					$css_class[] = 'bump_skin_type_2';
					$skin_type   = 'bump_skin_type_2';
					$css_class[] = 'wfob_bump_r_outer_wrap';

				} elseif ( $selected_layout == 'layout_5' || $selected_layout == 'layout_6' ) {
					$css_class[] = 'bump_skin_type_3';
					$skin_type   = 'bump_skin_type_3';
				} elseif ( $selected_layout == 'layout_7' || $selected_layout == 'layout_11' ) {
					$css_class[] = 'wfob_bump_r_outer_wrap';
					$css_class[] = 'bump_skin_type_4';
					$skin_type   = 'bump_skin_type_4';
				}

				if ( $selected_layout == 'layout_6' ) {
					$css_class[] = 'bwf_bump_toggle_checkout';
				}

				$css_class[] = 'wfob_bump_price_on';
				if ( $wc_product->is_on_sale() ) {
					$css_class[] = 'wfob_bump_price_on_sale';
				}


				if ( ! empty( $tax_label ) ) {
					$css_class[] = 'wfob_display_tax_label';
				}

				if ( true === wc_string_to_bool( $featured_image ) ) {
					$css_class[] = 'wfob_enable_image';
				}
				$css_class[] = $image_position_cls;


				$small_description = $this->get_bump_product_other_fields( $bump_id, $product_key, $design_data, 'small_description', 'small_description' );
				$sub_title         = $this->get_bump_product_other_fields( $bump_id, $product_key, $design_data, 'small_title', 'sub_title' );
				$add_btn_text      = $this->get_bump_product_other_fields( $bump_id, $product_key, $design_data, 'add_button_text', 'add_btn_text' );
				$added_btn_text    = $this->get_bump_product_other_fields( $bump_id, $product_key, $design_data, 'added_button_text', 'added_btn_text' );
				$remove_btn_text   = $this->get_bump_product_other_fields( $bump_id, $product_key, $design_data, 'remove_button_text', 'remove_btn_text' );

				$icon_on_button = isset( $design_data['icon_on_button'] ) ? $design_data['icon_on_button'] : '';

				if ( isset( $icon_on_button ) ) {
					$css_class[] = $icon_on_button;
				}

				if ( strpos( $sub_title, '{{more}}' ) !== false ) {
					$css_class[] = 'wfob_merge_tag_active';
				}


				if ( ! empty( $cart_item_key ) ) {
					$css_class[] = 'wfob_product_added_to_cart';
				}

				/*------------------------------------------Merge Tag----------------------------------------- */

				$description_display_none = '';
				if ( false !== strpos( $product_title, '{{more}}' ) || false !== strpos( $sub_title, '{{more}}' ) || false !== strpos( $small_description, '{{more}}' ) ) {
					$description_display_none = 'display:none';
				}

				if ( true === $print_bump ) {

					$decode_merge_tags = WFOB_Common::decode_merge_tags( $description, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
					$product_title     = WFOB_Common::decode_merge_tags( $product_title, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
					$sub_title         = WFOB_Common::decode_merge_tags( $sub_title, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
					$small_description = WFOB_Common::decode_merge_tags( $small_description, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
					$description       = WFOB_Common::decode_merge_tags( $description, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
					$titleHeading      = WFOB_Common::decode_merge_tags( $product_title, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );

				} else {
					$decode_merge_tags = $description;
					$titleHeading      = str_replace( '{{product_name}}', 'Complete Skin & Hair Care Pack', $product_title );
				}

				if ( ! empty( $titleHeading ) ) {
					$final_data[ $product_key ]['titleHeading'] = $titleHeading;
				}

				/* Product Image CSS */
				if ( ! empty( $image_width ) ) {
					$image_position_style[ $bump_id ]['desktop'][] = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]  .wfob_pro_image_wrap{ max-width: ' . $image_width . 'px;}';
					$image_position_style[ $bump_id ]['desktop'][] = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]:not(.wfob_img_position_top).wfob_enable_image  .bwf_display_col_flex.wfob_pro_txt_wrap{flex:1;}';


					$this->dynamic_css[ $product_key ]['mobile'][] = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]:not(.wfob_img_position_top).wfob_enable_image #wfob_wrapper_' . $bump_id . ' .bwf_display_col_flex.wfob_pro_txt_wrap{width: 100%;}';
				}


				if ( isset( $design_data['header_enable_pointing_arrow'] ) && wc_string_to_bool( $design_data['header_enable_pointing_arrow'] ) ) {
					if ( '1' == $design_data['point_animation'] ) {
						$css_class[] = 'wfob_point_animation';
						$css_class[] = 'wfob_pointer_animation_action';
					}
					$css_class[]                  = 'wfob_pointer_active';
					$css_class[]                  = 'wfob_header_enable_pointing_arrow';
					$header_enable_pointing_arrow = wc_string_to_bool( $design_data['header_enable_pointing_arrow'] );
				}


				if ( isset( $design_data['enable_price'] ) && wc_string_to_bool( $design_data['enable_price'] ) ) {
					$css_class[] = 'wfob_enable_price';
				}

				$css_class[] = 'wfob_enable_featured_image_border';

				$html = '';
				ob_start();
				include $this->layout_template();
				$html = ob_get_clean();

				$status = false;
				if ( ! empty( $html ) ) {
					$status = true;
				}

				if ( true === $print_bump ) {
					echo $html;
				}

				$this->bumps_html[ $product_key ] = $html;
				$this->single_bump_html           = $html;


				if ( true == $status ) {
					WFOB_Bump_Fc::$number_of_bump_print[ $product_key ] = 1;
				}
			}


			/*--------------------------------------------Dynamic Style------------------------------------ */


			if ( WFOB_Bump_Fc::$number_of_bump_print >= 1 && apply_filters( 'wfacp_disabled_order_bump_css_printing', true, $this ) && ! empty( $selected_layout ) ) {
				$this->dynamic_css = $this->generate_bump_css( $bump_id, $design_data, $skin_type );


				$bump_inline_css = apply_filters( 'wfob_bump_inline_css', $this->dynamic_css, $this );

				$dynamic_inline_css = '';


				if ( count( $image_position_style ) > 0 ) {
					$bump_inline_css = array_merge( $bump_inline_css, $image_position_style );
				}


				if ( is_array( $bump_inline_css ) && count( $bump_inline_css ) > 0 ) {
					foreach ( $bump_inline_css as $dynamic_css_key => $dynamic_css_val ) {
						/*-------------------------------Dynamic CSS for Desktop---------------------------------------*/
						if ( isset( $dynamic_css_val['desktop'] ) && is_array( $dynamic_css_val['desktop'] ) && count( $dynamic_css_val['desktop'] ) > 0 ) {
							$dynamic_inline_css .= "<style>";
							foreach ( $dynamic_css_val['desktop'] as $desktop_key => $desktop_css ) {
								$dynamic_inline_css .= $desktop_css;
							}
							$dynamic_inline_css .= "</style>";
						}
						/*-------------------------------Dynamic CSS for Mobile---------------------------------------*/

						if ( isset( $dynamic_css_val['mobile'] ) && is_array( $dynamic_css_val['mobile'] ) && count( $dynamic_css_val['mobile'] ) > 0 ) {

							$dynamic_inline_css .= "<style>@media (max-width: 767px) {";
							foreach ( $dynamic_css_val['mobile'] as $mobile_key => $mobile_css ) {
								$dynamic_inline_css .= $mobile_css;
							}
							$dynamic_inline_css .= "}</style>";
						}

						if ( isset( $dynamic_css_val['min-media'] ) && is_array( $dynamic_css_val['min-media'] ) && count( $dynamic_css_val['min-media'] ) > 0 ) {

							foreach ( $dynamic_css_val['min-media'] as $min_css_key => $min_css_val ) {
								$dynamic_inline_css .= "<style>@media (min-width: " . $min_css_key . "px) {";
								foreach ( $min_css_val as $inner_mobile_key => $inner_mobile_css ) {
									$dynamic_inline_css .= $inner_mobile_css;
								}

								$dynamic_inline_css .= "}</style>";

							}
						}
					}
				}

				if ( ! empty( $dynamic_inline_css ) ) {
					$this->dynamic_inline_css = $dynamic_inline_css;
					if ( true === $print_bump ) {
						echo $this->dynamic_inline_css;
					}


				}

				if ( true === $print_bump ) {
					/* Print Custom Css */
					include WFOB_SKIN_DIR . '/style.php';
				}


			}


		}

		public function print_preview_bump( $print_bump = true, $products_key = [] ) {

			if ( ! $this->have_bumps() ) {
				return '';
			}


			$design_data = $this->get_default_models();

			$this->wfob_default_model = $this->override_design_data_keys( $design_data, $design_data['layout'] );


			$max_bumps   = 1;
			$print_css   = false;
			$bump_id     = $this->get_id();
			$design_data = $this->wfob_default_model;


			$wfob_error_message   = '';
			$disabled             = '';
			$final_data           = [];
			$image_position_style = [];
			$dynamic_temp_style   = [];


			foreach ( $this->products as $product_key => $data ) {


				if ( '' !== $max_bumps && $max_bumps > 0 && count( WFOB_Bump_Fc::$number_of_bump_print ) >= $max_bumps ) {
					break;
				}


				$data['item_key'] = $product_key;

				$print_css = true;


				$cart_item_key       = '';
				$cart_item           = [];
				$result              = $this->get_bump_cart_item_details( $product_key );
				$is_variable_product = $this->is_variable_product();
				$cart_variation_id   = $this->is_cart_variation_id();

				$qty = 1;


				/*---------------------------------------Title Heading---------------------------- */
				$titleHeading     = $this->wfob_default_model['product_title'];
				$titleHeading     = $this->wfob_default_model['product_title'];
				$wc_product_name  = $titleHeading;
				$wc_product_price = '';
				if ( isset( $this->wfob_default_model['product_price_numeric'] ) ) {
					$wc_product_price = $this->wfob_default_model['product_price_numeric'];
				}

				if ( ! empty( $product_title ) ) {
					$final_data[ $product_key ]['title'] = $product_title;
				}

				/*------------------------------Get Product Description---------------------------------- */

				$description = $this->wfob_default_model['product_description'];


				if ( ! empty( $description ) ) {
					$final_data[ $product_key ]['description'] = $description;
				}

				/*------------------------------Get Product Description---------------------------------- */

				$exclusive_content_enable   = $this->wfob_default_model['exclusive_content_enable'];
				$exclusive_content          = $this->wfob_default_model['exclusive_content'];
				$exclusive_content_position = $this->wfob_default_model['exclusive_content_position'];


				/*------------------------------Get Product Description---------------------------------- */

				$social_proof_enable  = $this->wfob_default_model['social_proof_enable'];
				$social_proof_heading = $this->wfob_default_model['social_proof_heading'];
				$social_proof_content = $this->wfob_default_model['social_proof_content'];


				/*-----------------------------------Variable Checkbox---------------------------- */

				$variable_checkbox = '';


				/*--------------------------------------- Product Price Data--------------------------------- */


				if ( isset( $this->wfob_default_model['product_price'] ) ) {
					$price_data['price'] = $this->wfob_default_model['product_price'];
				}

				$price_data['quantity'] = $qty;


				if ( is_array( $price_data ) && count( $price_data ) > 0 ) {
					$final_data[ $product_key ]['price_data'] = $price_data;
				}


				/*---------------------------------------Enable Product Price--------------------------------- */


				$enable_price = $this->is_enable_bump_product_price( $bump_id, $product_key, $design_data );

				if ( ! empty( $enable_price ) ) {
					$final_data[ $product_key ]['enable_price'] = $enable_price;
				}


				/*---------------------------------------is Enable Pointer------------------------------ */

				$enable_pointer = $this->is_enable_pointer( $bump_id, $design_data );

				if ( ! empty( $enable_pointer ) ) {
					$final_data[ $product_key ]['enable_pointer'] = $enable_pointer;
				}
				/*---------------------------------------Print Price------------------------------ */
				$printed_price = '';
				if ( isset( $this->wfob_default_model['product_price'] ) ) {
					$printed_price = $this->wfob_default_model['product_price'];
				}


				if ( ! empty( $printed_price ) ) {
					$final_data[ $product_key ]['printed_price'] = $printed_price;
				}


				/*-----------------------------------Get Feature Image-------------------------------------- */

				$featured_image     = $this->wfob_default_model['product_featured_image'];
				$image_position_cls = $this->wfob_default_model['product_image_position_class'];
				$image_width        = $this->wfob_default_model['product_image_position_width'];
				$img_position       = $this->wfob_default_model['product_image_position'];
				$image_url          = $this->wfob_default_model['product_image_url'];


				$image_html = '';
				if ( $featured_image == true ) {
					$image_html = '<img src=' . $image_url . ' alt="">';

				}


				/*---------------------------------------Selected Layout -------------------------------------- */

				$selected_layout = $this->wfob_default_model['layout'];

				/*---------------------------------------header Enable Pointing Arrow -------------------------- */

				$header_enable_pointing_arrow = $this->get_header_enable_pointing_arrow();

				/*---------------------------------------Other Classes ----------------------------------------- */

				$checkbox_class = 'wfob_bump_product';


				/*---------------------------------------Wrapper  Class -------------------------------------- */
				$skin_type = '';
				$css_class = [
					'wfob_bump',
					'wfob_bump_section',
					'wfob_preview_bump_active',
					'wfob_clear',
					"wfob_" . $selected_layout,
				];

				if ( $selected_layout == 'layout_1' || $selected_layout == 'layout_2' || $selected_layout == 'layout_8' ) {
					$css_class[] = 'bump_skin_type_1';
					$skin_type   = 'bump_skin_type_1';
				} elseif ( $selected_layout == 'layout_3' || $selected_layout == 'layout_4' || $selected_layout == 'layout_9' || $selected_layout == 'layout_10' ) {
					$css_class[] = 'bump_skin_type_2';
					$skin_type   = 'bump_skin_type_2';
					$css_class[] = 'wfob_bump_r_outer_wrap';

				} elseif ( $selected_layout == 'layout_5' || $selected_layout == 'layout_6' ) {
					$css_class[] = 'bump_skin_type_3';
					$skin_type   = 'bump_skin_type_3';
				} elseif ( $selected_layout == 'layout_7' || $selected_layout == 'layout_11' ) {
					$css_class[] = 'wfob_bump_r_outer_wrap';
					$css_class[] = 'bump_skin_type_4';
					$skin_type   = 'bump_skin_type_4';
				}

				if ( $selected_layout == 'layout_6' ) {
					$css_class[] = 'bwf_bump_toggle_checkout';
				}

				if ( $featured_image == true ) {

					$css_class[] = 'wfob_enable_image';
					$css_class[] = $image_position_cls;
				}


				$css_class[] = 'wfob_bump_price_on';

				$css_class[] = 'wfob_bump_price_on_sale';


				$small_description = '';
				$sub_title         = '';
				$add_btn_text      = '';
				$added_btn_text    = '';
				$remove_btn_text   = '';

				if ( isset( $this->wfob_default_model['product_small_description'] ) ) {
					$small_description = $this->wfob_default_model['product_small_description'];
				}

				if ( isset( $this->wfob_default_model['product_small_title'] ) ) {
					$sub_title = $this->wfob_default_model['product_small_title'];
				}

				if ( isset( $this->wfob_default_model['product_add_button_text'] ) ) {
					$add_btn_text = $this->wfob_default_model['product_add_button_text'];
				}

				if ( isset( $this->wfob_default_model['product_added_button_text'] ) ) {
					$added_btn_text = $this->wfob_default_model['product_added_button_text'];
				}
				if ( isset( $this->wfob_default_model['product_remove_button_text'] ) ) {
					$remove_btn_text = $this->wfob_default_model['product_remove_button_text'];
				}
				if ( isset( $this->wfob_default_model['icon_on_button'] ) ) {
					$icon_on_button = $this->wfob_default_model['icon_on_button'];
				}


				/*------------------------------------------Merge Tag----------------------------------------- */

				$description_display_none = '';
				if ( false !== strpos( $titleHeading, '{{more}}' ) || false !== strpos( $sub_title, '{{more}}' ) || false !== strpos( $small_description, '{{more}}' ) ) {
					$description_display_none = 'display:none';
				}

				$decode_merge_tags = $description;

				if ( ! empty( $titleHeading ) ) {
					$final_data[ $product_key ]['titleHeading'] = $titleHeading;
				}

				/* Product Image CSS */

				if ( ! empty( $image_width ) ) {
					$image_position_style[ $bump_id ]['desktop'][] = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]  .wfob_pro_image_wrap{ max-width: ' . $image_width . 'px;}';
					$image_position_style[ $bump_id ]['desktop'][] = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]:not(.wfob_img_position_top).wfob_enable_image  .bwf_display_col_flex.wfob_pro_txt_wrap{flex:1;}';


					$this->dynamic_css[ $product_key ]['mobile'][] = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]:not(.wfob_img_position_top).wfob_enable_image #wfob_wrapper_' . $bump_id . ' .bwf_display_col_flex.wfob_pro_txt_wrap{width: 100%;}';
				}


				if ( isset( $design_data['header_enable_pointing_arrow'] ) && wc_string_to_bool( $design_data['header_enable_pointing_arrow'] ) ) {
					if ( '1' == $design_data['point_animation'] ) {
						$css_class[] = 'wfob_pointer_animation_action';
					}
					$css_class[]                  = 'wfob_pointer_active';
					$css_class[]                  = 'wfob_header_enable_pointing_arrow';
					$header_enable_pointing_arrow = wc_string_to_bool( $design_data['header_enable_pointing_arrow'] );
				}


				if ( isset( $design_data['enable_price'] ) && wc_string_to_bool( $design_data['enable_price'] ) ) {
					$css_class[] = 'wfob_enable_price';
				}

				$css_class[] = 'wfob_enable_featured_image_border';


				$inner_wrapper_class = implode( ' ', $css_class );

				$preview_bump = true;

				$html = '';
				ob_start();
				include $this->layout_template();
				$html = ob_get_clean();

				$status = false;
				if ( ! empty( $html ) ) {
					$status = true;
				}

				if ( true === $print_bump ) {
					echo $html;
				}


				$this->bumps_html[ $product_key ] = $html;
				$this->single_bump_html           = $html;


				if ( true == $status ) {
					WFOB_Bump_Fc::$number_of_bump_print[ $product_key ] = 1;
				}
			}


			/*--------------------------------------------Dynamic Style------------------------------------ */


			if ( WFOB_Bump_Fc::$number_of_bump_print >= 1 && apply_filters( 'wfacp_disabled_order_bump_css_printing', true, $this ) ) {


				$this->dynamic_css = $this->generate_bump_css( $bump_id, $design_data, $skin_type );


				$bump_inline_css = apply_filters( 'wfob_bump_inline_css', $this->dynamic_css, $this );


				$dynamic_inline_css = '';


				if ( count( $image_position_style ) > 0 ) {
					$bump_inline_css = array_merge( $bump_inline_css, $image_position_style );
				}


				if ( is_array( $bump_inline_css ) && count( $bump_inline_css ) > 0 ) {
					foreach ( $bump_inline_css as $dynamic_css_key => $dynamic_css_val ) {
						/*-------------------------------Dynamic CSS for Desktop---------------------------------------*/
						if ( isset( $dynamic_css_val['desktop'] ) && is_array( $dynamic_css_val['desktop'] ) && count( $dynamic_css_val['desktop'] ) > 0 ) {
							$dynamic_inline_css .= "<style>";
							foreach ( $dynamic_css_val['desktop'] as $desktop_key => $desktop_css ) {
								$dynamic_inline_css .= $desktop_css;
							}
							$dynamic_inline_css .= "</style>";
						}
						/*-------------------------------Dynamic CSS for Mobile---------------------------------------*/

						if ( isset( $dynamic_css_val['mobile'] ) && is_array( $dynamic_css_val['mobile'] ) && count( $dynamic_css_val['mobile'] ) > 0 ) {

							$dynamic_inline_css .= "<style>@media (max-width: 767px) {";
							foreach ( $dynamic_css_val['mobile'] as $mobile_key => $mobile_css ) {
								$dynamic_inline_css .= $mobile_css;
							}
							$dynamic_inline_css .= "}</style>";
						}

						if ( isset( $dynamic_css_val['min-media'] ) && is_array( $dynamic_css_val['min-media'] ) && count( $dynamic_css_val['min-media'] ) > 0 ) {

							foreach ( $dynamic_css_val['min-media'] as $min_css_key => $min_css_val ) {
								$dynamic_inline_css .= "<style>@media (min-width: " . $min_css_key . "px) {";
								foreach ( $min_css_val as $inner_mobile_key => $inner_mobile_css ) {
									$dynamic_inline_css .= $inner_mobile_css;
								}

								$dynamic_inline_css .= "}</style>";

							}
						}
					}
				}

				if ( ! empty( $dynamic_inline_css ) ) {
					$this->dynamic_inline_css = $dynamic_inline_css;


					$this->wfob_dynamic_css[] = $dynamic_inline_css;


				}


			}


		}

		public function generate_bump_css( $bump_id, $design_data = [], $skin_type = 'bump_skin_type_1' ) {


			if ( empty( $bump_id ) || count( $design_data ) == 0 ) {
				return [];
			}
			$selected_layout = '';
			if ( isset( $design_data['layout'] ) && ! empty( $design_data['layout'] ) ) {
				$selected_layout = $design_data['layout'];
			}
			$this->set_bump_design_selectors( $bump_id, $selected_layout, $skin_type );

			$css_arr = include WFOB_SKIN_DIR . '/global-inline-css.php';

			if ( ! is_array( $css_arr ) || count( $css_arr ) == 0 ) {
				return $this->dynamic_css;
			}


			$this->dynamic_css[ $bump_id ]                = $css_arr['dynamic_css'];
			$this->field_changes['field_changes']         = [];
			$this->field_changes['merged_array']          = [];
			$this->field_changes['new_key_value_updated'] = [];

			if ( isset( $css_arr['field_changes'] ) ) {
				$this->field_changes['field_changes'] = $css_arr['field_changes'];
			}

			if ( isset( $css_arr['merged_array'] ) ) {
				$this->field_changes['merged_array'] = $css_arr['merged_array'];
			}

			if ( isset( $css_arr['new_key_value_updated'] ) ) {
				$this->field_changes['new_key_value_updated'] = $css_arr['new_key_value_updated'];
			}


			if ( isset( $css_arr['temp_dynamic_css'] ) ) {

				$this->temp_dynamic_css = $css_arr['temp_dynamic_css'];
			}


			return $this->dynamic_css;
		}


		public function layout_template() {
			return WFOB_SKIN_DIR . '/layout-default.php';
		}

		public function get_bumps_html() {
			return $this->bumps_html;
		}

		public function get_single_bump_html() {
			return $this->single_bump_html;
		}

		public function set_bump_design_selectors( $bump_id, $selected_layout, $skin_type ) {


			$bump_selector_section = 'body #wfob_wrap .wfob_wrapper[data-wfob-id="' . $bump_id . '"] .wfob_bump.wfob_' . $selected_layout . '.wfob_bump_section';

			$bump_selector_wrapper = $bump_selector_section . ' #wfob_wrapper_' . $bump_id;

			$title = [
				'heading_background'           => [
					'label'    => __( 'Background', 'woofunnels-order-bump' ),
					'type'     => 'color',
					'key'      => 'heading_background',
					'stylekey' => 'background-color',

					'selectors' => [
						$bump_selector_wrapper . ' .wfob_bgBox_table'
					],
					'value'     => 'background-color:{{value}}',
				],
				'heading_hover_background'     => [
					'label'    => __( 'Background Hover', 'woofunnels-order-bump' ),
					'type'     => 'color',
					'key'      => 'heading_hover_background',
					'stylekey' => 'background-color',

					'selectors' => [
						$bump_selector_wrapper . ' .wfob_bgBox_table:hover'
					],
					'value'     => 'background-color:{{value}}',

				],
				'heading_color'                => [
					'label'    => __( 'Text', 'woofunnels-order-bump' ),
					'type'     => 'color',
					'key'      => 'heading_color',
					'stylekey' => 'color',

					'selectors' => [
						$bump_selector_wrapper . '  .wfob_title'
					],
					'value'     => 'color:{{value}}',

				],
				'heading_hover_color'          => [
					'label'     => __( 'Text Hover Color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'heading_hover_color',
					'stylekey'  => 'color',
					'selectors' => [
						$bump_selector_wrapper . '  .wfob_title:hover'
					],
					'value'     => 'color:{{value}}',

				],
				'heading_font_size'            => [
					'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'heading_font_size',
					'stylekey'  => 'font-size',
					'styleUnit' => 'px',
					'min'       => 0,
					'selectors' => [
						$bump_selector_wrapper . '  .wfob_title',
						$bump_selector_wrapper . '  .wfob_title *',
						$bump_selector_wrapper . '  .wfob_title label',
						$bump_selector_wrapper . '  .wfob_title span',
						$bump_selector_wrapper . '  .wfob_title span.amount',
					],
					'value'     => 'font-size:{{value}}px',

				],
				'heading_box_padding'          => [
					'label'     => __( 'Padding', 'woofunnels-order-bump' ),
					'type'      => 'dimension-control',
					'key'       => 'heading_box_padding',
					'stylekey'  => 'padding',
					'styleUnit' => 'px',

					'selectors' => [
						$bump_selector_wrapper . ' .wfob_bgBox_table'
					],
					'value'     => 'padding:{{value}}px',

				],
				'heading_box_border_style'     => [
					'label'     => __( 'Border Style', 'woofunnels-order-bump' ),
					'type'      => 'select',
					'key'       => 'heading_box_border_style',
					'stylekey'  => 'border-style',
					'class'     => 'bwf-field-one-half',
					'options'   => [
						[
							'label' => 'None',
							'value' => 'none',
							'key'   => 'none',
						],
						[
							'label' => 'Solid',
							'value' => 'solid',
							'key'   => 'solid',
						],
						[
							'label' => 'Dotted',
							'value' => 'dotted',
							'key'   => 'dotted',
						],
						[
							'label' => 'Dashed',
							'value' => 'dashed',
							'key'   => 'dashed',
						],

					],
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_bgBox_table'
					],
					'value'     => 'border-style:{{value}}',

				],
				'heading_box_border_color'     => [
					'label'    => __( 'Border Color', 'woofunnels-order-bump' ),
					'type'     => 'color',
					'key'      => 'heading_box_border_color',
					'stylekey' => 'border-color',
					'class'    => 'bwf-field-one-half',

					'selectors' => [
						$bump_selector_wrapper . ' .wfob_bgBox_table'
					],
					'value'     => 'border-color:{{value}}',

				],
				'heading_box_border_width'     => [
					'label'     => __( 'Border Width', 'woofunnels-order-bump' ),
					'type'      => 'dimension-control',
					'key'       => 'heading_box_border_width',
					'stylekey'  => 'border-width',
					'class'     => 'bwf-field-one-full',
					'styleUnit' => 'px',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_bgBox_table'
					],
					'value'     => 'border-width:{{value}}px',
				],
				'heading_box_border_radius'    => [
					'label'     => __( 'Corner Radius', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'heading_box_border_radius',
					'stylekey'  => 'border-radius',
					'min'       => 0,
					'styleUnit' => 'px',

					'selectors' => [
						$bump_selector_wrapper . ' .wfob_bgBox_table'
					],
					'value'     => 'border-radius:{{value}}px',
				],
				'header_enable_pointing_arrow' => [
					'label'        => __( 'Enable Arrow', 'woofunnels-order-bump' ),
					'type'         => 'toggle',
					'key'          => 'header_enable_pointing_arrow',
					'contentClass' => 'wfob_header_enable_pointing_arrow',
					'selectors'    => [
						$bump_selector_section
					],
					'value'        => '',

				],
				'point_animation'              => [

					'label'        => __( 'Arrow Animation', 'woofunnels-order-bump' ),
					'type'         => 'toggle',
					'key'          => 'point_animation',
					'contentClass' => 'wfob_point_animation',
					'toggler'      => [
						'key'   => 'header_enable_pointing_arrow',
						'value' => true
					],
					'selectors'    => [
						$bump_selector_section
					],
					'value'        => '',

				],

				'point_animation_color' => [
					'label'     => __( 'Pointer Animation color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'point_animation_color',
					'stylekey'  => 'fill',
					'toggler'   => [
						'key'   => 'header_enable_pointing_arrow',
						'value' => true
					],
					'selectors' => [
						$bump_selector_section . ' .wfob_checkbox_input_wrap span.wfob_blink_img_wrap svg path',
					],
					'value'     => 'fill:{{value}}',
				],
			];

			$image   = [
				'enable_featured_image_border' => [
					'label'        => __( 'Enable Image Border', 'woofunnels-order-bump' ),
					'type'         => 'toggle',
					'key'          => 'enable_featured_image_border',
					'contentClass' => 'wfob_enable_featured_image_border',
					'class'        => 'bwf-field-hide',
					'selectors'    => [
						$bump_selector_section
					],
					'value'        => '',


				],
				'featured_image_border_style'  => [
					'label'    => __( 'Border Style', 'woofunnels-order-bump' ),
					'type'     => 'select',
					'key'      => 'featured_image_border_style',
					'stylekey' => 'border-style',
					'class'    => 'bwf-field-one-half',

					'options'   => [
						[
							'label' => 'None',
							'value' => 'none',
							'key'   => 'none',
						],
						[
							'label' => 'Solid',
							'value' => 'solid',
							'key'   => 'solid',
						],
						[
							'label' => 'Dotted',
							'value' => 'dotted',
							'key'   => 'dotted',
						],
						[
							'label' => 'Dashed',
							'value' => 'dashed',
							'key'   => 'dashed',
						]

					],
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_pro_img_wrap img'
					],
					'value'     => 'border-style:{{value}}',
				],
				'featured_image_border_color'  => [

					'label'    => __( 'Border Color', 'woofunnels-order-bump' ),
					'type'     => 'color',
					'key'      => 'featured_image_border_color',
					'stylekey' => 'border-color',
					'class'    => 'bwf-field-one-half',

					'selectors' => [
						$bump_selector_wrapper . ' .wfob_pro_img_wrap img'
					],
					'value'     => 'border-color:{{value}}',
				],
				'featured_image_border_width'  => [
					'label'     => __( 'Border Width', 'woofunnels-order-bump' ),
					'type'      => 'dimension-control',
					'stylekey'  => 'border-width',
					'styleUnit' => 'px',
					'key'       => 'featured_image_border_width',
					'class'     => 'bwf-field-one-full',

					'selectors' => [
						$bump_selector_wrapper . ' .wfob_pro_img_wrap img'
					],
					'value'     => 'border-width:{{value}}px',
				],
				'featured_image_border_radius' => [
					'label'     => __( 'Corner Radius', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'featured_image_border_radius',
					'stylekey'  => 'border-radius',
					'styleUnit' => 'px',
					'min'       => 0,
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_pro_img_wrap img'
					],

					'value' => 'border-radius:{{value}}px',
				],
			];
			$content = [
				'content_font_size'                  => [
					'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'content_font_size',
					'stylekey'  => 'font-size',
					'styleUnit' => 'px',
					'min'       => 0,
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_text_inner',
						$bump_selector_wrapper . ' .wfob_text_inner *',
						$bump_selector_wrapper . ' .wfob_text_inner p',
						$bump_selector_wrapper . ' .wfob_text_inner span',
						$bump_selector_wrapper . ' .wfob_text_inner span.amount',
						$bump_selector_wrapper . ' .wfob_text_inner span bdi',
						$bump_selector_wrapper . ' .wfob_description_wrap a:not(.wfob_qv-button)',
						$bump_selector_wrapper . ' .wfob_description_wrap .wfob_selected_attributes',
						$bump_selector_wrapper . ' .wfob_description_wrap .wfob_selected_attributes *',
						$bump_selector_wrapper . ' .wfob_text_inner ul',
						$bump_selector_wrapper . ' .wfob_text_inner ul li',
						$bump_selector_wrapper . ' .wfob_text_inner ol',
						$bump_selector_wrapper . ' .wfob_text_inner ol li',

					],
					'class'     => 'bwf-field-one-half',
					'value'     => 'font-size:{{value}}px',
				],
				'content_color'                      => [
					'label'     => __( 'Text Color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'content_color',
					'stylekey'  => 'color',
					'class'     => 'bwf-field-one-half',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_text_inner',
						$bump_selector_wrapper . ' .wfob_text_inner p',
						$bump_selector_wrapper . ' .wfob_text_inner span',
						$bump_selector_wrapper . ' .wfob_text_inner span.amount',
						$bump_selector_wrapper . ' .wfob_text_inner span bdi',
						$bump_selector_wrapper . ' .wfob_description_wrap a:not(.wfob_qv-button)',
						$bump_selector_wrapper . ' .wfob_description_wrap .wfob_selected_attributes',
						$bump_selector_wrapper . ' .wfob_description_wrap .wfob_selected_attributes *',
						$bump_selector_wrapper . ' .wfob_text_inner ul',
						$bump_selector_wrapper . ' .wfob_text_inner ul li',
						$bump_selector_wrapper . ' .wfob_text_inner ol',
						$bump_selector_wrapper . ' .wfob_text_inner ol li',

					],
					'value'     => 'color:{{value}}',
				],
				'content_variation_link_color'       => [
					'label'     => __( 'Variant Link', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'content_variation_link_color',
					'stylekey'  => 'color',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_qv-button',
					],
					'value'     => 'color:{{value}}',
				],
				'content_variation_link_hover_color' => [
					'label'     => __( 'Link Hover Color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'content_variation_link_hover_color',
					'stylekey'  => 'color',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_qv-button:hover',
					],
					'value'     => 'color:{{value}}',
				],
				'content_box_padding'                => [
					'label'     => __( 'Padding', 'woofunnels-order-bump' ),
					'type'      => 'dimension-control',
					'key'       => 'content_box_padding',
					'stylekey'  => 'padding',
					'styleUnit' => 'px',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_text_inner',
					],
					'value'     => 'padding:{{value}}px',
				],
			];
			$price   = [
				'enable_price'             => [
					'label'        => __( 'Enable Price', 'woofunnels-order-bump' ),
					'type'         => 'toggle',
					'key'          => 'enable_price',
					'contentClass' => 'wfob_enable_price',
					'selectors'    => [
						$bump_selector_section
					],
					'value'        => '',

				],
				'regular_price_label_text' => [
					'label'   => __( 'Regular Price', 'woofunnels-order-bump' ),
					'type'    => 'bwf-label',
					'key'     => 'regular_price_label_text',
					'class'   => 'has-border',
					'value'   => '',
					'toggler' => [
						'key'   => 'enable_price',
						'value' => true
					],

				],
				'price_font_size'          => [
					'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'price_font_size',
					'stylekey'  => 'font-size',
					'class'     => 'bwf-field-one-half',
					'min'       => 0,
					'styleUnit' => 'px',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del bdi',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del span *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del span.amount',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount bdi',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount span',


					],
					'value'     => 'font-size:{{value}}px',
					'toggler'   => [
						'key'   => 'enable_price',
						'value' => true
					],
				],
				'price_color'              => [
					'label'     => __( 'Text', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'price_color',
					'class'     => 'bwf-field-one-half',
					'stylekey'  => 'color',
					'selectors' => [

						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del bdi',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del span *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price del span.amount',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount bdi',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price > .woocommerce-Price-amount span',


					],
					'value'     => 'color:{{value}}',
					'toggler'   => [
						'key'   => 'enable_price',
						'value' => true
					],
				],
				'sale_price_label_text'    => [
					'label'   => __( 'Sale Price', 'woofunnels-order-bump' ),
					'type'    => 'bwf-label',
					'key'     => 'sale_price_label_text',
					'class'   => 'has-border',
					'value'   => '',
					'toggler' => [
						'key'   => 'enable_price',
						'value' => true
					],

				],
				'price_sale_font_size'     => [
					'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'price_sale_font_size',
					'stylekey'  => 'font-size',
					'class'     => 'bwf-field-one-half',
					'min'       => 0,
					'styleUnit' => 'px',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins bdi',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins span *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins span.amount',
					],
					'value'     => 'font-size:{{value}}px',
					'toggler'   => [
						'key'   => 'enable_price',
						'value' => true
					],
				],
				'price_sale_color'         => [
					'label'     => __( 'Text', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'price_sale_color',
					'class'     => 'bwf-field-one-half',
					'stylekey'  => 'color',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins bdi',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins span *',
						$bump_selector_wrapper . ' .wfob_price_container .wfob_price ins span.amount',
					],
					'value'     => 'color:{{value}}',
					'toggler'   => [
						'key'   => 'enable_price',
						'value' => true
					],
				],
			];
			$layout  = [
				'box_background'       => [
					'label'     => __( 'Background Color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'box_background',
					'stylekey'  => 'background-color',
					'selectors' => [
						$bump_selector_section,
					],
					'value'     => 'background:{{value}}',
				],
				'box_background_hover' => [
					'label'     => __( 'Background Hover Color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'box_background_hover',
					'stylekey'  => 'background-color',
					'selectors' => [
						$bump_selector_section . ':hover',
					],
					'value'     => 'background:{{value}}',
				],
				'box_padding'          => [
					'label'     => __( 'Padding', 'woofunnels-order-bump' ),
					'type'      => 'dimension-control',
					'key'       => 'box_padding',
					'stylekey'  => 'padding',
					'styleUnit' => 'px',
					'selectors' => [
						$bump_selector_section,
					],
					'value'     => 'padding:{{value}}px',
				],
				'enable_box_border'    => [
					'label'        => __( 'Enable Box Border', 'woofunnels-order-bump' ),
					'type'         => 'toggle',
					'key'          => 'enable_box_border',
					'class'        => 'bwf-field-hide',
					'contentClass' => 'wfob_enable_box_border',
					'selectors'    => [
						$bump_selector_section
					],
					'value'        => '',
					'ref_key'      => [
						'key'   => 'enable_box_border',
						'value' => 'border-style:none',
					],
				],
				'border_style'         => [
					'label'    => __( 'Border Style', 'woofunnels-order-bump' ),
					'type'     => 'select',
					'key'      => 'border_style',
					'stylekey' => 'border-style',
					'class'    => 'bwf-field-one-half',

					'options'   => [
						[
							'label' => 'None',
							'value' => 'none',
							'key'   => 'none',
						],
						[
							'label' => 'Solid',
							'value' => 'solid',
							'key'   => 'solid',
						],
						[
							'label' => 'Dotted',
							'value' => 'dotted',
							'key'   => 'dotted',
						],
						[
							'label' => 'Dashed',
							'value' => 'dashed',
							'key'   => 'dashed',
						]

					],
					'selectors' => [
						$bump_selector_section,
					],
					'value'     => 'border-style:{{value}}',
					'ref_key'   => 'enable_box_border',
				],
				'border_color'         => [
					'label'    => __( 'Border Color', 'woofunnels-order-bump' ),
					'type'     => 'color',
					'key'      => 'border_color',
					'stylekey' => 'border-color',
					'class'    => 'bwf-field-one-half',

					'selectors' => [
						$bump_selector_section,
					],
					'value'     => 'border-color:{{value}}',
					'ref_key'   => 'enable_box_border',
				],
				'border_width'         => [
					'label'     => __( 'Border Width', 'woofunnels-order-bump' ),
					'type'      => 'dimension-control',
					'key'       => 'border_width',
					'stylekey'  => 'border-width',
					'styleUnit' => 'px',
					'class'     => 'bwf-field-one-full',

					'selectors' => [
						$bump_selector_section,
					],
					'value'     => 'border-width:{{value}}px',
					'ref_key'   => 'enable_box_border',
				],
				'box_border_radius'    => [
					'label'     => __( 'Corner Radius', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'box_border_radius',
					'stylekey'  => 'border-radius',
					'styleUnit' => 'px',
					'min'       => 0,
					'selectors' => [
						$bump_selector_section,
					],
					'value'     => 'border-radius:{{value}}px',
				],
				'bump_max_width'       => [
					'label'              => __( 'Width', 'woofunnels-order-bump' ),
					'type'               => 'width',
					'key'                => 'bump_max_width',
					'stylekey'           => 'max-width',
					'defaultCustomValue' => 540,
					'styleUnit'          => 'px',
					'selectors'          => [
						$bump_selector_section,
					],
					'value'              => 'max-width:{{value}}px',
				],
			];


			/*--------------------------------Exclusive Content------------------------------*/

			$exlusive_content_position_list = [
				[
					'label' => __( 'Outside - Top Left', 'woofunnels-order-bump' ),
					'value' => 'wfob_exclusive_outside_top_left',
					'key'   => 'wfob_exclusive_outside_top_left',
				],
				[
					'label' => __( 'Outside - Top Right', 'woofunnels-order-bump' ),
					'value' => 'wfob_exclusive_outside_top_right',
					'key'   => 'wfob_exclusive_outside_top_right',
				],
				[
					'label' => __( 'Inside - Above Description', 'woofunnels-order-bump' ),
					'value' => 'wfob_exclusive_above_description',
					'key'   => 'wfob_exclusive_above_description',
				],
				[
					'label' => __( 'Inside - Below Description', 'woofunnels-order-bump' ),
					'value' => 'wfob_exclusive_below_description',
					'key'   => 'wfob_exclusive_below_description',
				]
			];

			/*--------------------------------Exclusive Content------------------------------*/
			$sub_heading       = [];
			$short_description = [];

			$exclude_keys = [];


			if ( $skin_type != 'bump_skin_type_1' && $skin_type != 'bump_skin_type_3' && $selected_layout != 'layout_7' && $selected_layout != 'layout_11' ) {
				$exlusive_content_position_list[] = [
					'label' => 'Above Title',
					'value' => 'wfob_exclusive_above_title',
					'key'   => 'wfob_exclusive_above_title',
				];
			}


			$exclusive = [
				'exclusive_content_bg_color'  => [
					'label'     => __( 'Background', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'exclusive_content_bg_color',
					'stylekey'  => 'background-color',
					'class'     => 'bwf-field-one-full',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_exclusive_content span',
					],

					'value' => 'background-color:{{value}}',
				],
				'exclusive_content_font_size' => [
					'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'exclusive_content_font_size',
					'stylekey'  => 'font-size',
					'styleUnit' => 'px',
					'min'       => 0,
					'class'     => 'bwf-field-one-half',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_exclusive_content',
						$bump_selector_wrapper . ' .wfob_exclusive_content *',
						$bump_selector_wrapper . ' .wfob_exclusive_content span',
					],
					'value'     => 'font-size:{{value}}px',
				],
				'exclusive_content_color'     => [

					'label'     => __( 'Text', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'exclusive_content_color',
					'stylekey'  => 'color',
					'class'     => 'bwf-field-one-half',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob_exclusive_content',
						$bump_selector_wrapper . ' .wfob_exclusive_content *',
						$bump_selector_wrapper . ' .wfob_exclusive_content span',
					],

					'value' => 'color:{{value}}',
				],
				'exclusive_content_position'  => [
					'label'      => __( 'Exclusive Content Position', 'woofunnels-order-bump' ),
					'type'       => 'select',
					'allClasses' => [
						'wfob_exclusive_above_description',
						'wfob_exclusive_below_description',
						'wfob_exclusive_above_title',
						'wfob_exclusive_outside_top_right',
						'wfob_exclusive_outside_top_left',
					],
					'key'        => 'exclusive_content_position',
					'class'      => 'bwf-field-one-full',
					'options'    => $exlusive_content_position_list,
					'selectors'  => [
						$bump_selector_section
					],
				],
			];


			/*--------------------------------Social proof Tool tip---------------------------------------- */

			$social_proof_tooltip = [
				'social_proof_tooltip_layout_label'      => [
					'label' => __( 'Layout', 'woofunnels-order-bump' ),
					'type'  => 'bwf-label',
					'key'   => 'social_proof_tooltip_layout_label',
					'class' => 'has-border',
					'value' => '',

				],
				'social_proof_tooltip_bg_color'          => [
					'label'     => __( 'Background', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'social_proof_tooltip_bg_color',
					'stylekey'  => 'background-color',
					'class'     => 'bwf-field-one-full',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip',
					],

					'value' => 'background-color:{{value}}',
				],
				'social_proof_tooltip_font_size'         => [
					'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'social_proof_tooltip_font_size',
					'stylekey'  => 'font-size',
					'styleUnit' => 'px',
					'min'       => 0,
					'class'     => 'bwf-field-one-half',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-content',
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-content *',
					],
					'value'     => 'font-size:{{value}}px',
				],
				'social_proof_tooltip_color'             => [

					'label'     => __( 'Color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'social_proof_tooltip_color',
					'stylekey'  => 'color',
					'class'     => 'bwf-field-one-half',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-content',
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-content *',
					],

					'value' => 'color:{{value}}',
				],
				'social_proof_tooltip_heading_label'     => [
					'label' => __( 'Heading', 'woofunnels-order-bump' ),
					'type'  => 'bwf-label',
					'key'   => 'social_proof_tooltip_heading_label',
					'class' => 'has-border',
					'value' => '',

				],
				'social_proof_tooltip_heading_bg_color'  => [
					'label'     => __( 'Background', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'social_proof_tooltip_heading_bg_color',
					'stylekey'  => 'background-color',
					'class'     => 'bwf-field-one-full',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip .wfob-social-proof-tooltip-header',
					],

					'value' => 'background-color:{{value}}',
				],
				'social_proof_tooltip_heading_font_size' => [
					'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
					'type'      => 'number',
					'key'       => 'social_proof_tooltip_heading_font_size',
					'stylekey'  => 'font-size',
					'styleUnit' => 'px',
					'min'       => 0,
					'class'     => 'bwf-field-one-half',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-header',
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-header *',
					],
					'value'     => 'font-size:{{value}}px',
				],
				'social_proof_tooltip_heading_color'     => [

					'label'     => __( 'Color', 'woofunnels-order-bump' ),
					'type'      => 'color',
					'key'       => 'social_proof_tooltip_heading_color',
					'stylekey'  => 'color',
					'class'     => 'bwf-field-one-half',
					'selectors' => [
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-header',
						$bump_selector_wrapper . ' .wfob-social-proof-tooltip-header *',
					],

					'value' => 'color:{{value}}',
				],
			];


			/*--------------------------------Old Skins--------------------------------------------- */


			if ( $selected_layout == 'layout_3' || $selected_layout == 'layout_4' ) {
				$exclusive         = [];
				$sub_heading       = [
					'sub_heading_font_size'   => [
						'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
						'type'      => 'number',
						'key'       => 'sub_heading_font_size',
						'stylekey'  => 'font-size',
						'min'       => 0,
						'styleUnit' => 'px',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head span',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head span bdi',
						],
						'value'     => 'font-size:{{value}}px',

					],
					'sub_heading_color'       => [
						'label'     => __( 'Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'sub_heading_color',
						'stylekey'  => 'color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head span',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head span bdi',
						],
						'value'     => 'color:{{value}}',

					],
					'sub_heading_hover_color' => [
						'label'     => __( 'Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'sub_heading_hover_color',
						'stylekey'  => 'color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head:hover',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head:hover span',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_head:hover span bdi',
						],
						'value'     => 'color:{{value}}',

					],
				];
				$short_description = [
					'sub_content_font_size' => [
						'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
						'type'      => 'number',
						'key'       => 'sub_content_font_size',
						'stylekey'  => 'font-size',
						'styleUnit' => 'px',
						'min'       => 0,
						'selectors' => [
							$bump_selector_wrapper . ' .wfob_l3_c_sub_desc',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_desc *',
						],
						'value'     => 'font-size:{{value}}px',

					],
					'sub_content_color'     => [
						'label'     => __( 'Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'sub_content_color',
						'stylekey'  => 'color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob_l3_c_sub_desc',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_desc span',
							$bump_selector_wrapper . ' .wfob_l3_c_sub_desc a:not(.wfob_read_more_link)',
						],
						'value'     => 'color:{{value}}',

					],
				];
			}


			if ( $skin_type == 'bump_skin_type_2' || $skin_type == 'bump_skin_type_4' ) {

				$add_to_button = [
					'add_button_bg_color'       => [
						'label'     => __( 'Background Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'add_button_bg_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-full',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add',

						],
						'value'     => 'background-color:{{value}}',

					],
					'add_button_hover_bg_color' => [
						'label'     => __( 'Background Hover Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'add_button_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-full',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add:hover',
						],
						'value'     => 'background-color:{{value}}',

					],
					'add_button_color'          => [
						'label'     => __( 'Text', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'add_button_color',
						'stylekey'  => 'color',
						'class'     => 'bwf-field-one-full',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add',
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add span',
						],
						'value'     => 'color:{{value}}',

					],
					'add_button_hover_color'    => [
						'label'     => __( 'Hover Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'add_button_hover_color',
						'stylekey'  => 'color',
						'class'     => 'bwf-field-one-full',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add:hover',
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add:hover > span',
						],
						'value'     => 'color:{{value}}',

					],

					'add_button_font_size'     => [
						'label'     => __( 'Font Size', 'woofunnels-order-bump' ),
						'type'      => 'number',
						'key'       => 'add_button_font_size',
						'stylekey'  => 'font-size',
						'min'       => 0,
						'styleUnit' => 'px',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add',
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add span',
						],
						'value'     => 'font-size:{{value}}px',

					],
					'add_button_padding'       => [
						'label'     => __( 'Padding', 'woofunnels-order-bump' ),
						'type'      => 'dimension-control',
						'key'       => 'add_button_padding',
						'stylekey'  => 'padding',
						'styleUnit' => 'px',

						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add'
						],
						'value'     => 'padding:{{value}}px',

					],
					'add_button_border_radius' => [
						'label'     => __( 'Corner Radius', 'woofunnels-order-bump' ),
						'type'      => 'number',
						'key'       => 'add_button_border_radius',
						'stylekey'  => 'border-radius',
						'styleUnit' => 'px',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add'
						],
						'value'     => 'border-radius:{{value}}px',
					],

					'add_button_border_style' => [
						'label'    => __( 'Border Style', 'woofunnels-order-bump' ),
						'type'     => 'select',
						'key'      => 'add_button_border_style',
						'stylekey' => 'border-style',
						'class'    => 'bwf-field-one-half',

						'options'   => [
							[
								'label' => 'None',
								'value' => 'none',
								'key'   => 'none',
							],
							[
								'label' => 'Solid',
								'value' => 'solid',
								'key'   => 'solid',
							],
							[
								'label' => 'Dotted',
								'value' => 'dotted',
								'key'   => 'dotted',
							],
							[
								'label' => 'Dashed',
								'value' => 'dashed',
								'key'   => 'dashed',
							]

						],
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add',
						],
						'value'     => 'border-style:{{value}}',
					],
					'add_button_border_color' => [
						'label'    => __( 'Border Color', 'woofunnels-order-bump' ),
						'type'     => 'color',
						'key'      => 'add_button_border_color',
						'stylekey' => 'border-color',
						'class'    => 'bwf-field-one-half',

						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add',
						],
						'value'     => 'border-color:{{value}}',
					],
					'add_button_border_width' => [
						'label'     => __( 'Border Width', 'woofunnels-order-bump' ),
						'type'      => 'dimension-control',
						'key'       => 'add_button_border_width',
						'stylekey'  => 'border-width',
						'styleUnit' => 'px',
						'class'     => 'bwf-field-one-full',

						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add',
						],
						'value'     => 'border-width:{{value}}px',
					],
					'add_button_width'        => [
						'label'     => __( 'Width', 'woofunnels-order-bump' ),
						'type'      => 'number',
						'key'       => 'add_button_width',
						'stylekey'  => 'min-width',
						'styleUnit' => 'px',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_add',

						],
						'value'     => 'min-width:{{value}}px',
					],


					'added_button_label_text' => [
						'label' => __( 'Added To Cart Button', 'woofunnels-order-bump' ),
						'type'  => 'bwf-label',
						'key'   => 'added_button_label_text',
						'class' => 'has-border',
						'value' => '',

					],
					'added_button_color'      => [
						'label'     => __( 'Added Button Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'added_button_color',
						'stylekey'  => 'color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present',
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present span',
						],
						'value'     => 'color:{{value}}',

					],
					'added_button_bg_color'   => [
						'label'     => __( 'Background Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'added_button_bg_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present',

						],
						'value'     => 'background-color:{{value}}',

					],

					'remove_button_label_text' => [
						'label' => __( 'Removed from Cart Button', 'woofunnels-order-bump' ),
						'type'  => 'bwf-label',
						'key'   => 'remove_button_label_text',
						'class' => 'has-border',
						'value' => '',

					],
					'remove_button_color'      => [
						'label'     => __( 'Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'remove_button_color',
						'stylekey'  => 'color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob_l3_s_btn a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present:hover',
							$bump_selector_wrapper . ' .wfob_l3_s_btn a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present:hover span',
						],
						'value'     => 'color:{{value}}',

					],
					'remove_button_bg_color'   => [
						'label'     => __( 'Background Color', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'remove_button_bg_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob_l3_s_btn a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present:hover',
						],
						'value'     => 'background-color:{{value}}',

					],

					'icon_on_button' => [
						'label'     => __( 'Show Icon', 'woofunnels-order-bump' ),
						'type'      => 'select',
						'key'       => 'icon_on_button',
						'class'     => 'bwf-field-one-full',
						'options'   => [
							[
								'label' => 'None',
								'value' => 'none',
								'key'   => 'none',
							],
							[
								'label' => 'Cursor',
								'value' => 'wfob_cta_cursor',
								'key'   => 'cursor',
								'icon'  => '<svg width="16" height="16" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path id="Vector" fill-rule="evenodd" clip-rule="evenodd" d="M9.31421 6.90121L13.509 11.096C13.7188 11.3058 13.7188 11.6728 13.509 11.8826L11.8835 13.5081C11.6738 13.7178 11.3068 13.7178 11.097 13.5081L6.90219 9.31323L4.59503 12.5642C4.22799 13.0361 3.49389 12.9313 3.33659 12.3545L0.347774 1.18576C0.242904 0.713845 0.714821 0.241927 1.18674 0.346798L12.3555 3.33561C12.9322 3.49292 13.0371 4.22701 12.5652 4.59406L9.31421 6.90121Z" fill="#353030"/>
							</svg>'
							],
							[
								'label' => 'Cart',
								'value' => 'wfob_cta_cart',
								'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 18 18" fill="none">
							<path id="Shape" d="M0 0.6427C0 0.287747 0.287747 0 0.6427 0H1.20617C2.14058 0 2.67708 0.608092 2.98785 1.21426C3.20081 1.62963 3.35417 2.13638 3.48088 2.57519H16.7138C17.5667 2.57519 18.1831 3.39066 17.9505 4.21123L16.0281 10.9911C15.7145 12.0972 14.7046 12.8606 13.5548 12.8606H7.02598C5.8668 12.8606 4.851 12.0848 4.54585 10.9665L3.72009 7.94029C3.71501 7.92654 3.71036 7.91252 3.70615 7.89824L2.38304 3.40221C2.33764 3.25304 2.29586 3.10848 2.25553 2.9689C2.12718 2.52476 2.01344 2.13115 1.84402 1.80068C1.63918 1.40113 1.45109 1.2854 1.20617 1.2854H0.6427C0.287747 1.2854 0 0.997654 0 0.6427ZM7.07347 18C8.13833 18 9.00157 17.1368 9.00157 16.0719C9.00157 15.007 8.13833 14.1438 7.07347 14.1438C6.00861 14.1438 5.14537 15.007 5.14537 16.0719C5.14537 17.1368 6.00861 18 7.07347 18ZM13.5005 18C14.5653 18 15.4286 17.1368 15.4286 16.0719C15.4286 15.007 14.5653 14.1438 13.5005 14.1438C12.4356 14.1438 11.5724 15.007 11.5724 16.0719C11.5724 17.1368 12.4356 18 13.5005 18Z" fill="#353030"/>
							</svg>'

							],

						],
						'selectors' => [
							$bump_selector_section,
						],
						'classList' => [
							"none"            => "",
							"wfob_cta_cursor" => "wfob_cta_cursor",
							"wfob_cta_cart"   => "wfob_cta_cart"
						],
					]
				];


				$final_fields = $layout + $title + $sub_heading + $short_description + $add_to_button + $image + $content + $price + $exclusive;

			} elseif ( $selected_layout == 'layout_6' ) {
				$toggle_color = [
					'unselect_line_color'     => [
						'label'     => __( 'Line Color (Inactive)', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'unselect_line_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob-switch + label span.sw',
						],
						'value'     => 'background-color:{{value}}',

					],
					'select_line_color'       => [
						'label'     => __( 'Line Color (Active)', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'select_line_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob-switch:checked + label span.sw',
						],
						'value'     => 'background-color:{{value}}',
					],
					'switch_color_label_text' => [
						'label' => __( 'Switch Color', 'woofunnels-order-bump' ),
						'type'  => 'bwf-label',
						'key'   => 'switch_color_label_text',
						'class' => 'has-border',
						'value' => '',

					],
					'unselect_switch_color'   => [
						'label'     => __( 'Circle Color (Inactive)', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'unselect_switch_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob-switch + label span.sw:before',
						],
						'value'     => 'background-color:{{value}}',

					],
					'select_switch_color'     => [
						'label'     => __( 'Circle Color (Active)', 'woofunnels-order-bump' ),
						'type'      => 'color',
						'key'       => 'select_switch_color',
						'stylekey'  => 'background-color',
						'class'     => 'bwf-field-one-half',
						'selectors' => [
							$bump_selector_wrapper . ' .wfob-switch:checked + label span:before',
						],
						'value'     => 'background-color:{{value}}',
					],

				];

				$final_fields = $layout + $title + $toggle_color + $image + $content + $price + $exclusive;

			} else {
				$final_fields = $layout + $title + $image + $content + $price + $exclusive;
			}


			/**
			 * Add Social Proof setting
			 */
			//$final_fields = $social_proof_tooltip;

			if ( is_array( $social_proof_tooltip ) && count( $social_proof_tooltip ) > 0 ) {
				$final_fields = array_merge( $final_fields, $social_proof_tooltip );

			}


			/*---------------------------------Remove Keys from template ------------------------------------------*/

			if ( $selected_layout == 'layout_3' || $selected_layout == 'layout_4' || $selected_layout == 'layout_6' || $selected_layout == 'layout_9' || $selected_layout == 'layout_10' ) {
				$exclude_keys[] = 'heading_background';
				$exclude_keys[] = 'heading_hover_background';
				$exclude_keys[] = 'header_enable_pointing_arrow';
				$exclude_keys[] = 'point_animation';
				$exclude_keys[] = 'heading_box_border_style';
				$exclude_keys[] = 'heading_box_border_color';
				$exclude_keys[] = 'heading_box_border_width';
				$exclude_keys[] = 'heading_box_border_radius';
			}


			if ( $selected_layout !== 'layout_3' && $selected_layout !== 'layout_4' ) {
				$exclude_keys[] = 'remove_button_label_text';
				$exclude_keys[] = 'remove_button_color';
				$exclude_keys[] = 'remove_button_bg_color';
			}


			if ( is_array( $exclude_keys ) && count( $exclude_keys ) > 0 ) {
				foreach ( $exclude_keys as $index => $unset_key ) {
					if ( ! isset( $final_fields[ $unset_key ] ) ) {
						continue;
					}
					unset( $final_fields[ $unset_key ] );

				}
			}


			$this->bump_all_selectors = $final_fields;


		}


		public function get_bump_design_selectors() {

			return $this->bump_all_selectors;
		}

		public function get_dynamic_inline_css() {
			return $this->dynamic_inline_css;
		}

		public function get_wfob_bump_css() {

			return $this->wfob_dynamic_css;

		}

		public function override_design_data_keys( $design_data, $layout ) {


			if ( ! is_array( $this->override_layout_design_data ) || count( $this->override_layout_design_data ) == 0 ) {
				return $design_data;
			}
			$overide_keys = [];
			if ( isset( $this->override_layout_design_data[ $layout ] ) ) {
				$overide_keys = $this->override_layout_design_data[ $layout ];


			}
			if ( is_array( $overide_keys ) && count( $overide_keys ) > 0 ) {
				foreach ( $overide_keys as $k => $v ) {
					$design_data[ $k ] = $v;
				}
			}


			return $design_data;

		}

		/**
		 * @return boolean
		 */
		public function need_to_hide( $wc_product, $cart_item_key ) {
			$status = ( ! empty( $cart_item_key ) && isset( $this->settings['order_bump_auto_hide'] ) && wc_string_to_bool( $this->settings['order_bump_auto_hide'] ) );
			//backward compatibility
			$status = apply_filters( 'wfob_hide_order_bump_after_selected', $status, $this->get_id(), $cart_item_key );

			return apply_filters( 'wfob_do_not_display_order_bump_product', $status, $this->get_id(), $wc_product, $cart_item_key );
		}
	}
}