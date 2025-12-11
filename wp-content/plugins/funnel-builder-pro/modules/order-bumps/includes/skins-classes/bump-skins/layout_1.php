<?php
if ( ! class_exists( 'WFOB_Layout_1' ) ) {
	class WFOB_Layout_1 extends WFOB_Bump {
		protected static $slug = 'layout_1';

		public function __construct( $wfob_id ) {
			parent::__construct( $wfob_id );


			add_action( 'wfob_title_bottom', [ $this, 'add_price' ], 11 );
		}

		/**
		 * Get Default Setting of bump
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * Get preview image url
		 * @return string
		 */
		public static function get_preview_image_url() {
			return WFOB_PLUGIN_URL . '/assets/img/skin-1.jpg';
		}


		public static function get_default_models() {

			return [
				'heading_background'           => '#ffff92',
				'heading_hover_background'     => '',
				'heading_font_size'            => '14',
				'heading_color'                => '#009900',
				'heading_hover_color'          => '',
				'heading_box_padding'          => '10 12 10 12',
				'heading_box_border_style'     => 'none',
				'heading_box_border_color'     => '',
				'heading_box_border_width'     => '0 0 0 0',
				'heading_box_border_radius'    => '8',
				'header_enable_pointing_arrow' => 'true',
				'point_animation_color'        => '#D80027',
				'point_animation'              => '1',
				'error_color'                  => '#e15334',

				'enable_featured_image_border' => 'true',
				'featured_image_border_style'  => 'solid',
				'featured_image_border_color'  => '#ECECEC',
				'featured_image_border_width'  => '1 1 1 1',
				'featured_image_border_radius' => '8',

				'content_font_size'                  => '14',
				'content_color'                      => '#353030',
				'content_variation_link_color'       => '#e15334',
				'content_variation_link_hover_color' => '',
				'content_box_padding'                => '16 16 16 16',

				'enable_price'         => "true",
				'price_font_size'      => '12',
				'price_color'          => '#353030',
				'price_sale_font_size' => '',
				'price_sale_color'     => '',

				'box_background'       => '#FFFFFF',
				'box_background_hover' => '',
				'box_padding'          => '1',
				'enable_box_border'    => 'true',
				'border_style'         => 'dashed',
				'border_color'         => '#353030',
				'border_width'         => '2',
				'box_border_radius'    => '8',
				'bump_max_width'       => '',

				'exclusive_content_bg_color'  => '',
				'exclusive_content_font_size' => '12',
				'exclusive_content_color'     => '#09B29C',
				'exclusive_content_enable'    => 'false',
				'exclusive_content'           => __( 'Special Offer', 'woofunnels-order-bump' ),
				'exclusive_content_position'  => 'wfob_exclusive_above_description',

				'social_proof_enable'  => 'false',
				'social_proof_heading' => __( '30% of Our Customers Choose this Upgrade', 'woofunnels-order-bump' ),
				'social_proof_content' => __( 'This is by far the most popular option with over 30% of customers choosing this value offer. {{product_regular_price}} worth but today only {{price}} ({{saving_value}} / {{saving_percentage}} savings).', 'woofunnels-order-bump' ),

				'social_proof_tooltip_bg_color'          => '#ffffff',
				'social_proof_tooltip_font_size'         => '12',
				'social_proof_tooltip_color'             => '#353030',
				'social_proof_tooltip_heading_bg_color'  => '#FFFF99',
				'social_proof_tooltip_heading_font_size' => '14',
				'social_proof_tooltip_heading_color'     => '#353030',

				'layout'      => 'layout_1',
				'layout_name' => __( 'Skin 1', 'woofunnels-order-bump' ),
				'class_name'  => 'WFOB_Layout_1',

				'product_title'                => __( "<span style='color:#E15334'>Yes!</span> Add ", 'woofunnels-order-bump' ) . '{{product_name}}' . __( ' to my order', 'woofunnels-order-bump' ),
				'product_preview_title'        => __( "<span style='color:#E15334'>Yes!</span>" . ' Add Complete Skin Care Pack to my order', 'woofunnels-order-bump' ),
				'product_featured_image'       => true,
				'product_image_url'            => WFOB_PLUGIN_URL . '/admin/assets/img/preview_bump_product_icon.jpg',
				'product_image_position_class' => 'wfob_img_position_left',
				'product_image_position_width' => '96',
				'product_image_position'       => 'center',
				'product_description'          => __( "Aperiam consecttur quisquam Aperiam consectetur. Lorem Ipsum is simply dummy text of the printing and typesetting industry.", 'woofunnels-order-bump' ),
				'product_price'                => wc_format_sale_price( 39.97, 35.67 ),
				'product_price_numeric'        => '35.67',

			];
		}


		public function get_default_design_data() {
			return WFOB_Common::add_product_details_default_layout( $this->products, self::get_default_models() );

		}

		public function print_bump_price( $final_data = [], $product_key = '' ) {

			if ( isset( $final_data[ $product_key ]['printed_price'] ) ) {
				$printed_price = $final_data[ $product_key ]['printed_price'];
			}


			include WFOB_SKIN_DIR . "/template-parts/wfob-price.php";


		}

	}


	WFOB_Bump_Fc::register( 'WFOB_Layout_1' );
}