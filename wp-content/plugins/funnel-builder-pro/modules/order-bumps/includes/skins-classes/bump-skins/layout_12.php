<?php
if ( ! class_exists( 'WFOB_Layout_12' ) ) {

	class WFOB_Layout_12 extends WFOB_Bump {
		protected static $slug = 'layout_12';

		public function __construct( $wfob_id ) {
			parent::__construct( $wfob_id );

			add_filter( 'wfob_bump_inline_css', array( $this, 'override_dynamic_css' ), 10, 2 );
		}

		/**
		 * Get Default Setting of bump
		 *
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * Get preview image url
		 *
		 * @return string
		 */
		public static function get_preview_image_url() {
			return WFOB_PLUGIN_URL . '/assets/img/skin-12.jpg';
		}

		public static function get_skin_label() {
			return __( 'Multi Product Carousel', 'woofunnels-order-bump' );
		}

		public static function get_skin_description() {
			return __( 'Centred product card - eyebrow label such as MOST POPULAR, name, description, price, pill ADD button. With multiple products the cards become a horizontal slider with prev/next arrows; the only skin that does.', 'woofunnels-order-bump' );
		}

		protected function get_product_content_schema( $product, $product_key ) {

			/*
			Every field in this skin is scoped to one product card, so the card selector and the
				per-product key prefix are built once here instead of being re-concatenated per field. */
			$card = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]';
			$key  = 'product_' . $product_key . '_';

			return array(
				array(
					'type'      => 'text',
					'key'       => $key . 'title',
					'label'     => __( 'Title', 'woofunnels-order-bump' ),
					'selectors' => $card . ' .wfob_title',
					'hint'      => __( 'Use merge tag {{product_name}} to show product name dynamically.', 'woofunnels-order-bump' ),
				),
				array(
					'type'      => 'richeditor',
					'key'       => $key . 'description',
					'label'     => __( 'Description', 'woofunnels-order-bump' ),
					'selectors' => $card . ' .wfob_skin_description',
					'default'   => __( 'Lorem Ipsum dolor siet consectur', 'woofunnels-order-bump' ),
					'hint'      => __( 'Use merge tag {{quantity_incrementer}} to show the quantity changer.', 'woofunnels-order-bump' ),
				),
				array(
					'type'         => 'checkbox',
					'key'          => $key . 'exclusive_content_enable',
					'label'        => __( 'Add Product Tag', 'woofunnels-order-bump' ),
					'contentClass' => 'wfob_active_exclusive',
					'selectors'    => $card,
				),
				array(
					'type'      => 'text',
					'key'       => $key . 'exclusive_content',
					'label'     => '',
					'selectors' => $card . ' .wfob_exclusive_content span',
					'toggler'   => array(
						'key'   => $key . 'exclusive_content_enable',
						'value' => true,
					),
					'hint'      => __( 'Shown as a small badge above the title, e.g. MOST POPULAR.', 'woofunnels-order-bump' ),
				),
				array(
					'type'         => 'checkbox',
					'key'          => $key . 'social_proof_enable',
					'label'        => __( 'Enable Social Proof Tool Tip', 'woofunnels-order-bump' ),
					'contentClass' => 'wfob_active_social_proof',
					'selectors'    => $card,
				),
				array(
					'type'      => 'text',
					'key'       => $key . 'social_proof_heading',
					'label'     => '',
					'selectors' => $card . ' .wfob-social-proof-tooltip .wfob-social-proof-tooltip-header',
					'toggler'   => array(
						'key'   => $key . 'social_proof_enable',
						'value' => true,
					),
				),
				array(
					'type'      => 'richeditor',
					'key'       => $key . 'social_proof_content',
					'label'     => '',
					'selectors' => $card . ' .wfob-social-proof-tooltip .wfob-social-proof-tooltip-content',
					'toggler'   => array(
						'key'   => $key . 'social_proof_enable',
						'value' => true,
					),
				),
				array(
					'type'      => 'text',
					'key'       => $key . 'add_btn_text',
					'label'     => __( 'Add Button', 'woofunnels-order-bump' ),
					'selectors' => $card . ' .wfob_btn_add span',
					'default'   => __( 'ADD', 'woofunnels-order-bump' ),
					'hint'      => '',
					'class'     => 'bwf-field-one-half',
				),
				array(
					'type'      => 'text',
					'key'       => $key . 'added_btn_text',
					'label'     => __( 'Added Button', 'woofunnels-order-bump' ),
					'selectors' => $card . ' .wfob_btn_add.wfob_btn_remove .wfob_btn_text_added',
					'default'   => __( 'ADDED', 'woofunnels-order-bump' ),
					'hint'      => '',
					'class'     => 'bwf-field-one-half',
				),
			);
		}

		/**
		 * Skin 12 is a vertical, center-aligned card where the image is a tile above the content.
		 * It uses the shared Left / Right / Top control, defaulting to Top — the only value that
		 * centres the tile over the card. Left and Right shift it to either edge.
		 *
		 * @param array  $product
		 * @param string $product_key
		 *
		 * @return array
		 */
		protected function admin_product_image_field( $product, $product_key ) {
			$card  = 'body #wfob_wrap .wfob_bump[data-product-key="' . $product_key . '"]';
			$image = $card . ' .wfob_pro_image_wrap';
			$key   = 'product_' . $product_key . '_featured_image';

			return array(
				array(
					'type'         => 'toggle',
					'key'          => $key,
					'label'        => __( 'Product Image', 'woofunnels-order-bump' ),
					'selectors'    => $card,
					'contentClass' => 'wfob_enable_image',
				),
				array(
					'type'               => 'image',
					'key'                => $key . '_options',
					'label'              => '',
					'selectors'          => $image,
					'alignmentSelectors' => $card,
					'alignmentClassList' => array(
						'left'  => 'wfob_img_position_left',
						'right' => 'wfob_img_position_right',
						'top'   => 'wfob_img_position_top',
					),
					'widthSelectors'     => $image,
					'toggler'            => array(
						'key'   => $key,
						'value' => true,
					),
				),
			);
		}

		public static function get_default_models() {
			return array(
				'heading_background'                     => 'transparent',
				'heading_hover_background'               => '',
				'heading_color'                          => '#23272A',
				'heading_hover_color'                    => '',
				'heading_font_size'                      => '14',
				'heading_box_padding'                    => '0 0 0 0',

				'heading_box_border_style'               => 'none',
				'heading_box_border_color'               => '',
				'heading_box_border_width'               => '0 0 0 0',
				'heading_box_border_radius'              => '0',

				'header_enable_pointing_arrow'           => 'false',
				'point_animation'                        => '1',
				'point_animation_color'                  => '#D80027',

				'error_color'                            => '#e15334',

				'enable_featured_image_border'           => 'true',
				'featured_image_border_style'            => 'solid',
				'featured_image_border_color'            => '#FFFFFF',
				'featured_image_border_width'            => '1 1 1 1',
				'featured_image_border_radius'           => '8',

				'content_font_size'                      => '14',
				'content_color'                          => '#6B7280',
				'content_variation_link_color'           => '#2E9E5B',
				'content_variation_link_hover_color'     => '',
				'content_box_padding'                    => '0',

				'enable_price'                           => 'true',
				'price_font_size'                        => '12',
				'price_color'                            => '#9CA3AF',
				'price_sale_font_size'                   => '14',
				'price_sale_color'                       => '#2E9E5B',

				'add_button_font_size'                   => '15',
				'add_button_enable_box_border'           => 'true',
				'add_button_border_style'                => 'solid',
				'add_button_border_color'                => '#23272A',
				'add_button_border_width'                => '1 1 1 1',
				'add_button_padding'                     => '8 16 8 16',
				'add_button_border_radius'               => '40',
				'add_button_width'                       => '70',

				'add_button_color'                       => '#23272A',
				'add_button_hover_color'                 => '#ffffff',
				/*
				The outlined pill reads as transparent because it is painted with the card's own
					background — NOT with the `transparent` keyword. See get_design_data(). */
				'add_button_bg_color'                    => '#F5F3EF',
				'add_button_hover_bg_color'              => '#23272A',
				'icon_on_button'                         => 'none',

				'added_button_color'                     => '#ffffff',
				'added_button_bg_color'                  => '#23272A',

				'box_background'                         => '#F5F3EF',
				'box_background_hover'                   => '',
				'box_padding'                            => '24 10 24 10',
				/*
				The shared "Enable Box Border" toggle has no UI (bwf-field-hide), so this must ship
					open like every other skin — see get_design_data(). The card still renders borderless
					by default because border_width is 0; the merchant's Border Width is what decides. */
				'enable_box_border'                      => 'true',
				'border_style'                           => 'solid',
				'border_color'                           => '#EDEAE4',
				'border_width'                           => '0',
				'box_border_radius'                      => '16',

				'exclusive_content_bg_color'             => '#F5F3EF',
				'exclusive_content_font_size'            => '12',
				'exclusive_content_color'                => '#2E6E7E',
				'exclusive_content_enable'               => 'true',
				'exclusive_content'                      => __( 'MOST POPULAR', 'woofunnels-order-bump' ),
				'exclusive_content_position'             => 'wfob_exclusive_above_title',

				'social_proof_enable'                    => 'true',
				'social_proof_heading'                   => __( '30% of Our Customers Choose this Upgrade', 'woofunnels-order-bump' ),
				'social_proof_content'                   => __( 'This is by far the most popular option with over 30% of customers choosing this value offer. Add it to your order in one click, with no extra steps at checkout.', 'woofunnels-order-bump' ),
				'social_proof_tooltip_bg_color'          => '#ffffff',
				'social_proof_tooltip_font_size'         => '12',
				'social_proof_tooltip_color'             => '#353030',
				'social_proof_tooltip_heading_bg_color'  => '#23272A',
				'social_proof_tooltip_heading_font_size' => '14',
				'social_proof_tooltip_heading_color'     => '#ffffff',

				'bump_max_width'                         => '',

				/*
				Prev/next chevrons of the multi-product slider, one switch per breakpoint. Both ship
					on: the arrows are the only cue on a pointer device that the row scrolls at all, and
					they still only paint when the products actually overflow the track. */
				'slider_arrow_desktop'                   => 'true',
				'slider_arrow_mobile'                    => 'true',

				'layout'                                 => 'layout_12',
				'layout_name'                            => __( 'Skin 12', 'woofunnels-order-bump' ),
				'class_name'                             => 'WFOB_Layout_12',

				'product_title'                          => '{{product_name}}',
				'product_preview_title'                  => __( 'Hair Serum', 'woofunnels-order-bump' ),

				'product_featured_image'                 => 'true',
				'product_description'                    => __( 'Lorem Ipsum dolor siet consectur', 'woofunnels-order-bump' ),
				'product_add_button_text'                => __( 'ADD', 'woofunnels-order-bump' ),
				'product_added_button_text'              => __( 'ADDED', 'woofunnels-order-bump' ),
				'product_remove_button_text'             => __( 'REMOVE', 'woofunnels-order-bump' ),
				'product_add_btn_text'                   => __( 'ADD', 'woofunnels-order-bump' ),
				'add_btn_text'                           => __( 'ADD', 'woofunnels-order-bump' ),
				'product_added_btn_text'                 => __( 'ADDED', 'woofunnels-order-bump' ),
				'product_remove_btn_text'                => __( 'REMOVE', 'woofunnels-order-bump' ),
				'product_read_more'                      => __( ' more...', 'woofunnels-order-bump' ),

				'product_image_url'                      => WFOB_PLUGIN_URL . '/admin/assets/img/preview_bump_product_icon.jpg',
				'product_image_position_class'           => 'wfob_img_position_top',
				'product_image_position_width'           => '96',
				'product_image_position'                 => 'top',
				'price_numeric'                          => '29.00',
				'product_price'                          => wc_format_sale_price( 79.00, 29.00 ),
				'product_price_numeric'                  => '29.00',

			);
		}


		/**
		 * Skin 12 used to ship the literal keyword `transparent` as the stored value of the colour
		 * controls that sit flush on the card. The design panel's colour picker seeds its alpha
		 * channel from the stored value, so a picker that opens on `transparent` (alpha 0) emits
		 * every colour the merchant then picks as a fully transparent 8-digit hex (#RRGGBB00) —
		 * the swatch changes but nothing repaints, neither in the editor nor on the frontend.
		 *
		 * Swap the keyword for the card's own background: these elements sit directly on the card,
		 * so the rendering is byte-for-byte what `transparent` produced, but the picker now opens
		 * fully opaque and the control works. Bumps saved before this fix are normalised on read.
		 *
		 * @return array
		 */
		public function get_design_data() {
			$design_data = parent::get_design_data();

			if ( ! is_array( $design_data ) || 0 === count( $design_data ) ) {
				return $design_data;
			}

			$card_background = ( isset( $design_data['box_background'] ) && '' !== $design_data['box_background'] && 'transparent' !== $design_data['box_background'] ) ? $design_data['box_background'] : '#F5F3EF';

			foreach ( array( 'add_button_bg_color', 'exclusive_content_bg_color' ) as $color_key ) {
				if ( isset( $design_data[ $color_key ] ) && 'transparent' === $design_data[ $color_key ] ) {
					$design_data[ $color_key ] = $card_background;
				}
			}

			/**
			 * The design panel exposes Border Style / Colour / Width for this skin but NOT the
			 * `enable_box_border` toggle that gates them — the shared field carries `bwf-field-hide`.
			 * The panel seeds that hidden field from the skin default and saves whatever it seeded, so
			 * every bump built on the old 'false' default has it persisted as an empty string, which the
			 * renderer reads as "off" twice over: global-inline-css.php rewrites border_width to
			 * `0 0 0 0`, and the field's own ref_key emits `border-style:none !important`. The border then
			 * paints in the design panel (which applies the three controls directly) and is invisible at
			 * checkout — the merchant sets a 5px dotted border and nothing reaches the card.
			 *
			 * Since the gate is unreachable in this skin's UI, a closed value never expresses merchant
			 * intent — only the absent control. Assert it open and let Border Width, which defaults to 0
			 * here, decide whether a border actually shows. Bumps saved before this fix are normalised
			 * on read, so none of them needs a re-save.
			 */
			$design_data['enable_box_border'] = 'true';

			$this->design_data = $design_data;

			return $design_data;
		}

		public function get_bump_product_other_fields( $bump_id, $product_key, $design_data = array(), $key = '', $old_key = '' ) {
			if ( is_array( $design_data ) && count( $design_data ) == 0 ) {
				$design_data = $this->get_design_data( $bump_id );
			}
			$default_data = WFOB_Common::get_default_model_data( $bump_id );

			$default_data = $default_data[ $design_data['layout'] ];

			$default_value = isset( $default_data[ "product_{$key}" ] ) ? $default_data[ "product_{$key}" ] : '';

			$text = isset( $design_data[ "product_{$product_key}_{$old_key}" ] ) ? $design_data[ "product_{$product_key}_{$old_key}" ] : $default_value;

			return $text;
		}

		public function override_dynamic_css( $dynamic_style, $object ) {

			$bump_id = $object->get_id();

			/* Both overrides below target this bump's own card, so the (long) scope is built once. */
			$scope = 'body #wfob_wrap .wfob_wrapper[data-wfob-id="' . $bump_id . '"] .wfob_bump.wfob_layout_12.wfob_bump_section #wfob_wrapper_' . $bump_id . ' ';

			/**
			 * The shared renderer force-sets a global `exclusive_content_color` just before printing,
			 * which would repaint this card's "MOST POPULAR" eyebrow. Re-assert the skin's configured
			 * colour last (same specificity, emitted after) so it wins while staying editable from the
			 * design panel — we read the real stored/edited value, not the forced one.
			 */
			$design_data   = $this->get_design_data();
			$eyebrow_color = ( isset( $design_data['exclusive_content_color'] ) && '' !== $design_data['exclusive_content_color'] ) ? $design_data['exclusive_content_color'] : '#2E6E7E';

			$selector = $scope . '.wfob_exclusive_content';

			$dynamic_style[ $bump_id ]['desktop'][] = $selector . ',' . $selector . ' span,' . $selector . ' *{color:' . esc_attr( $eyebrow_color ) . '}';

			/**
			 * Once added, the shared renderer shows the ADDED/REMOVE anchor as `display:inline-block`
			 * (via a 2-id selector), whose inline-block text span adds baseline/descender space and makes
			 * the button taller than the flex ADD button. Re-assert it as a centered flex box last so the
			 * ADD → ADDED toggle keeps the exact same height.
			 */
			$dynamic_style[ $bump_id ]['desktop'][] = $scope . 'a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present{display:inline-flex;align-items:center;justify-content:center;line-height:1;}';

			return $dynamic_style;
		}

		public function print_bump_price( $final_data = array(), $product_key = '' ) {

			/*
			wfob-price.php echoes $printed_price, so it must always be defined — a product whose
				price row hasn't been prepared would otherwise raise an undefined-variable warning. */
			$printed_price = isset( $final_data[ $product_key ]['printed_price'] ) ? $final_data[ $product_key ]['printed_price'] : '';

			include WFOB_SKIN_DIR . '/template-parts/wfob-price.php';
		}
	}


	WFOB_Bump_Fc::register( 'WFOB_Layout_12' );
}
