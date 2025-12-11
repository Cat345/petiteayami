<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Utils;

if ( ! class_exists( 'WFOCU_Product_Images_Widget' ) ) {
	/**
	 * Class WFOCU_Product_Images_Widget
	 */
	class WFOCU_Product_Images_Widget extends \Elementor\Widget_Base {

		/**
		 * Get widget name.
		 * @return string
		 */
		public function get_name() {
			return 'wfocu-product-images';
		}

		/**
		 * Get widget title.
		 * @return string|void
		 */
		public function get_title() {
			return __( 'Product Images', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * Get widget icon.
		 * @return string
		 */
		public function get_icon() {
			return 'wfocu-icon-product_gallery';
		}

		/**
		 * Get widget categories.
		 *
		 * Retrieve the list of categories the widget belongs to.
		 * @access public
		 *
		 * @return array Widget categories.
		 */
		public function get_categories() {
			return [ 'upstroke' ];
		}

		public function get_script_depends() {
			return [ 'jquery', 'wfocu-product' ];
		}

		public function get_keywords() {
			return [ 'woocommerce', 'shop', 'store', 'image', 'product', 'gallery', 'lightbox' ];
		}

		/**
		 * Register widget controls.
		 *
		 * Adds different input fields to allow the user to change and customize the widget settings.
		 *
		 * @access protected
		 */
		protected function register_controls() {

			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => '--No Product--' );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			$this->start_controls_section( 'section_button', [
				'label' => __( 'Offer Product Images', 'woofunnels-upstroke-one-click-upsell' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			] );

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
			}
			$this->add_control( 'selected_product', [
				'label'   => __( 'Product', 'woofunnels-upstroke-one-click-upsell' ),
				'type'    => Controls_Manager::SELECT,
				'default' => key( $product_options ),
				'options' => $product_options,
			] );

			do_action( 'wfocu_add_elementor_controls', $this, $offer_id, $products );

			$this->add_control( 'slider_enabled', [
				'label'        => __( 'Enable Slider', 'elementor-pro' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => [
					'selected_product!' => '',
				],
				'description'  => "Note: Slider will only show if gallary images are available.",
			] );

			$this->add_responsive_control( 'text_align', [
				'label'     => __( 'Alignment', 'elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'none',
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'woofunnels-upstroke-one-click-upsell' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'selectors' => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-gallery img' => 'float: {{VALUE}}; margin: 0 auto;',

				],
			] );

			$this->end_controls_section();

			$this->start_controls_section( 'section_product_gallery_style', [
				'label' => __( 'Style', 'elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			] );

			$this->add_control( 'wc_style_warning', [
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'The style of this widget is often affected by your theme and plugins. If you experience any such issue, try to switch to a basic theme and deactivate related plugins.', 'elementor' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			] );

			$this->add_control( 'heading_featured_style', [
				'label'     => __( 'Featured Image', 'elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );

			$this->add_group_control( Group_Control_Border::get_type(), [
				'name'     => 'image_border',
				'selector' => '.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-gallery img',
			] );

			$this->add_responsive_control( 'image_border_radius', [
				'label'      => __( 'Border Radius', 'elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-gallery img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			] );

			$this->add_control( 'spacing', [
				'label'       => __( 'Spacing', 'elementor' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => [ 'px', 'em' ],
				'description' => __( 'Between main image and gallery slider(if slider available)', 'elementor' ),
				'default'     => [
					'size' => 5,
					'unit' => 'px',
				],
				'selectors'   => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-gallery' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				],
			] );
			$this->add_responsive_control( 'width', [
				'label'          => __( 'Width', 'elementor' ),
				'type'           => Controls_Manager::SLIDER,
				'default'        => [
					'unit' => '%',
				],
				'tablet_default' => [
					'unit' => '%',
					'size' => '300'
				],
				'mobile_default' => [
					'unit' => '%',
				],
				'size_units'     => [ '%', 'px', 'vw' ],
				'range'          => [
					'%'  => [
						'min' => 1,
						'max' => 100,
					],
					'px' => [
						'min' => 1,
						'max' => 1000,
					],
					'vw' => [
						'min' => 1,
						'max' => 100,
					],
				],
				'selectors'      => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-gallery img' => 'width: {{SIZE}}{{UNIT}}; height: auto;',
				],
			] );

			$this->add_responsive_control( 'max_width', [
				'label'          => __( 'Max Width', 'elementor' ) . ' (%)',
				'type'           => Controls_Manager::SLIDER,
				'default'        => [
					'unit' => '%',
				],
				'tablet_default' => [
					'unit' => '%',
				],
				'mobile_default' => [
					'unit' => '%',
				],
				'size_units'     => [ '%' ],
				'range'          => [
					'%' => [
						'min' => 1,
						'max' => 100,
					],
				],
				'selectors'      => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-gallery img' => 'max-width: {{SIZE}}{{UNIT}};',
				],
			] );

			$this->add_control( 'heading_thumbs_style', [
				'label'     => __( 'Thumbnails', 'elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			] );

			$this->add_group_control( Group_Control_Border::get_type(), [
				'name'     => 'thumbs_border',
				'selector' => '.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-thumbnails .wfocu-thumb-col a',
			] );

			$this->add_responsive_control( 'thumbs_border_radius', [
				'label'      => __( 'Border Radius', 'elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors'  => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-thumbnails .wfocu-thumb-col a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				],
			] );

			$this->add_control( 'spacing_thumbs', [
				'label'      => __( 'Spacing', 'elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [
					'em' => [
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					],
					'px' => [
						'min' => 2,
						'max' => 50,
					],
				],
				'default'    => [
					'size' => 5,
					'unit' => 'px',
				],
				'selectors'  => [
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .wfocu-product-thumbnails .wfocu-thumb-col' => 'padding: {{SIZE}}{{UNIT}};',
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .flickity-prev-next-button'                 => 'width: 36px; height: 36px; padding: 0; top: 50%; -webkit-transform: translateY(0%); transform: translateY(0%); margin-top: -18px;',
					'.single-wfocu_offer {{WRAPPER}} .elementor-widget-container .flickity-prev-next-button svg'             => '-moz-transform: none; -webkit-transform:none; transform: none; padding: 0',
				],
			] );

			$this->end_controls_section();
		}

		/**
		 * Used to determine whether the reload preview is required.
		 */
		public function is_reload_preview_required() {
			return true;
		}

		public function render() {
			$settings = $this->get_settings_for_display();
			$main_img = '';
			if ( isset( $settings['selected_product'] ) && ! empty( $settings['selected_product'] ) ) {

				/** Gallery */
				if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
					return;
				}
				/**
				 * If the selected product is not present in the current set of products then assign the first
				 */
				if ( ! isset( WFOCU_Core()->template_loader->product_data->products->{$settings['selected_product']} ) ) {
					$key = key( (array) WFOCU_Core()->template_loader->product_data->products );

					$settings['selected_product'] = $key;
				}
				/**
				 * @var WC_Product $product_obj
				 */
				$product_obj = WFOCU_Core()->template_loader->product_data->products->{$settings['selected_product']}->data;
				$product     = WFOCU_Core()->template_loader->product_data->products->{$settings['selected_product']};

				if ( $product_obj instanceof WC_Product ) {
					$main_img    = $product_obj->get_image_id();
					$gallery_img = $product_obj->get_gallery_image_ids();

					$gallery      = array();
					$images_taken = array();
					if ( ! empty( $main_img ) ) {
						$gallery[]['gallery'] = (int) $main_img;
						$images_taken[]       = (int) $main_img;
					}

					if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 && 'yes' === $settings['slider_enabled'] ) {
						foreach ( $gallery_img as $gallerys ) {
							$gallery[]['gallery'] = (int) $gallerys;
							$images_taken[]       = (int) $gallerys;
						}
					}
					/**
					 * Variation images to be bunch with the other gallery images
					 */
					if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) && 'yes' === $settings['slider_enabled'] ) {
						foreach ( $product->variations_data['images'] as $id ) {
							if ( false === in_array( $id, $images_taken, true ) ) {
								$gallery[]['gallery'] = (int) $id;
							}
						}
					} ?>
                    <link rel="stylesheet" id="flickity-css" href="<?php echo plugin_dir_url( WFOCU_PLUGIN_FILE ); ?>/assets/flickity/flickity.css" type="text/css" media="all">
                    <link rel="stylesheet" id="flickity-common-css" href="<?php echo plugin_dir_url( WFOCU_PLUGIN_FILE ); ?>/assets/css/flickity-common.css" type="text/css" media="all">
					<?php
					if ( ! empty( $main_img ) ) {
						WFOCU_Core()->template_loader->get_template_part( 'product/slider', array(
							'key'     => $settings['selected_product'],
							'gallery' => $gallery,
							'product' => $product_obj,
							'title'   => '',
							'style'   => 2,
						) );
					}
				}

			}
			if ( empty( $main_img ) ) { ?>
                <link rel="stylesheet" id="flickity-css" href="<?php echo plugin_dir_url( WFOCU_PLUGIN_FILE ); ?>/assets/flickity/flickity.css" type="text/css" media="all">
                <link rel="stylesheet" id="flickity-common-css" href="<?php echo plugin_dir_url( WFOCU_PLUGIN_FILE ); ?>/assets/css/flickity-common.css" type="text/css" media="all">
                <div class="elementor-widget-container">
                    <div class="wfocu-product-gallery ">
                        <div class="wfocu-product-carousel wfocu-product-image-single ">
                            <div class="wfocu-carousel-cell">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                        </div>
                    </div>
					<?php if ( false && isset( $settings['slider_enabled'] ) && 'yes' === $settings['slider_enabled'] ) { ?>
                        <div class="wfocu-product-carousel-nav wfocu-product-thumbnails" data-flickity='{"asNavFor":".wfocu-product-carousel-nav","contain":true,"pageDots":false,"imagesLoaded":true}'>
                            <div class="wfocu-thumb-col is-nav-selected">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                            <div class="wfocu-thumb-col">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                            <div class="wfocu-thumb-col">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                            <div class="wfocu-thumb-col">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                            <div class="wfocu-thumb-col">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                            <div class="wfocu-thumb-col">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                        </div>
						<?php

					} ?>
                </div>
				<?php
			}

		}
	}
}