<?php

namespace WfocuFunnelKit;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WC_Product;

if ( ! class_exists( '\WfocuFunnelKit\Product_Images' ) ) {
	class Product_Images extends \Bricks\Element {
		public $category = 'funnelkit';
		public $name = 'wfocu-product-images';
		public $icon = 'wfocu-icon-product_gallery';
		public $scripts = array( 'runFlickityInitialization' );

		/**
		 * Retrieves the label for the "Product Images" element.
		 *
		 * @return string The label for the "Product Images" element.
		 */
		public function get_label() {
			return esc_html__( 'Product Images' );
		}

		/**
		 * Retrieves the keywords associated with the Product Images element.
		 *
		 * @return array An array of keywords related to the Product Images element.
		 */
		public function get_keywords() {
			return array( 'woocommerce', 'shop', 'store', 'image', 'product', 'gallery', 'lightbox' );
		}

		/**
		 * Enqueues the scripts required for the product images element.
		 *
		 * @return void
		 */
		public function enqueue_scripts() {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'wfocu-product' );
		}

		/**
		 * Sets the control groups for the product images element.
		 *
		 * This method initializes the control groups array for the product images element.
		 * It sets the control groups for the element's content and style tabs.
		 * It also calls the `set_common_control_groups()` method to set common control groups.
		 * Finally, it removes the `_typography` control group from the control groups array.
		 *
		 * @return void
		 */
		public function set_control_groups() {
			$this->control_groups = array();

			$this->control_groups['elementContent'] = array(
				'title' => esc_html__( 'Offer Product Images' ),
				'tab'   => 'content',
			);

			$this->control_groups['elementStyle'] = array(
				'title' => esc_html__( 'Style' ),
				'tab'   => 'style',
			);

			$this->set_common_control_groups();

			unset( $this->control_groups['_typography'] );
		}

		/**
		 * Sets the controls for the product-mages element.
		 */
		public function set_controls() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$products        = array();
			$product_options = array( '0' => esc_html__( '--No Product--' ) );
			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
			}

			$this->controls['selectedProduct'] = array(
				'group'   => 'elementContent',
				'label'   => esc_html__( 'Product' ),
				'type'    => 'select',
				'options' => $product_options,
				'default' => key( $product_options ),
			);

			$this->controls['sliderEnabled'] = array(
				'group'       => 'elementContent',
				'label'       => esc_html__( 'Enable Slider' ),
				'description' => 'Note: Slider will only show if gallary images are available.',
				'type'        => 'checkbox',
				'default'     => true,
				'required'    => array( 'selectedProduct', '!=', '' ),
			);

			$this->controls['_alignSelf']['default'] = 'stretch';

			$this->controls['textAlign'] = array(
				'group'   => 'elementContent',
				'label'   => esc_html__( 'Alignment' ),
				'type'    => 'text-align',
				'default' => 'center',
				'exclude' => array( 'justify' ),
				'css'     => array(
					array(
						'property' => 'text-align',
						'selector' => '.wfocu-product-gallery',
					),
				),
			);

			$this->controls['styleInfo'] = array(
				'group'   => 'elementStyle',
				'content' => esc_html__( 'The style of this widget is often affected by your theme and plugins. If you experience any such issue, try to switch to a basic theme and deactivate related plugins.' ),
				'type'    => 'info',
			);

			$this->controls['headingFeaturedStyle'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Featured Image' ),
				'type'  => 'separator',
			);

			$this->controls['imageBorder'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Border' ),
				'type'  => 'border',
				'css'   => array(
					array(
						'property' => 'border',
						'selector' => '.wfocu-product-gallery img',
					),
				),
			);

			$this->controls['spacing'] = array(
				'group'       => 'elementStyle',
				'label'       => esc_html__( 'Spacing' ),
				'type'        => 'slider',
				'css'         => array(
					array(
						'property' => 'margin-bottom',
						'selector' => '.wfocu-product-gallery',
					),
				),
				'default'     => '5px',
				'description' => esc_html__( 'Between main image and gallery slider(if slider available)' ),
			);

			$this->controls['width'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Width' ),
				'type'  => 'slider',
				'css'   => array(
					array(
						'property' => 'width',
						'selector' => '.wfocu-product-gallery img',
					),
				),
				'units' => array(
					'%'  => array(
						'min'  => 1,
						'max'  => 100,
						'step' => 1,
					),
					'px' => array(
						'min'  => 1,
						'max'  => 1000,
						'step' => 1,
					),
					'vw' => array(
						'min'  => 1,
						'max'  => 100,
						'step' => 1,
					),
				),
			);

			$this->controls['maxWidth'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Max Width (%)' ),
				'type'  => 'slider',
				'css'   => array(
					array(
						'property' => 'max-width',
						'selector' => '.wfocu-product-gallery img',
					),
				),
				'units' => array(
					'%' => array(
						'min'  => 1,
						'max'  => 100,
						'step' => 1,
					),
				),
			);

			$this->controls['headingThumbsStyle'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Thumbnails' ),
				'type'  => 'separator',
			);

			$this->controls['thumbsBorder'] = array(
				'group' => 'elementStyle',
				'label' => esc_html__( 'Border' ),
				'type'  => 'border',
				'css'   => array(
					array(
						'property' => 'border',
						'selector' => '.wfocu-product-thumbnails .wfocu-thumb-col a',
					),
				),
			);

			$this->controls['spacingThumbs'] = array(
				'group'   => 'elementStyle',
				'label'   => esc_html__( 'Spacing' ),
				'type'    => 'slider',
				'default' => '5px',
				'css'     => array(
					array(
						'property' => 'padding',
						'selector' => '.wfocu-product-thumbnails .wfocu-thumb-col',
					),
				),
				'units'   => array(
					'em' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
					'px' => array(
						'min'  => 2,
						'max'  => 50,
						'step' => 1,
					),
				),
			);
		}

		/**
		 * Renders the product images.
		 *
		 * @return void
		 * @since 1.0.0
		 *
		 */
		public function render() {
			$settings = $this->settings;

			$this->set_attribute( '_root', 'class', 'bricks-element' );

			?>
            <div <?php echo $this->render_attributes( '_root' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php

				if ( isset( $settings['selectedProduct'] ) ) {
					$product_key = WFOCU_Core()->template_loader->default_product_key( $settings['selectedProduct'] );

					/** Gallery */
					if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
						echo '</div>';

						return;
					}

					/**
					 * If the selected product is not present in the current set of products then assign the first
					 */
					if ( ! isset( WFOCU_Core()->template_loader->product_data->products->{$product_key} ) ) {
						$key = key( (array) WFOCU_Core()->template_loader->product_data->products );

						$product_key = $key;
					}

					if ( is_null( $product_key ) ) {
						echo '</div>';

						return;
					}

					/**
					 * @var WC_Product $product_obj
					 */
					$product_obj = WFOCU_Core()->template_loader->product_data->products->{$product_key}->data;
					$product     = WFOCU_Core()->template_loader->product_data->products->{$product_key};

					if ( $product_obj instanceof WC_Product ) {
						$main_img    = $product_obj->get_image_id();
						$gallery_img = $product_obj->get_gallery_image_ids();

						$gallery      = array();
						$images_taken = array();
						if ( ! empty( $main_img ) ) {
							$gallery[]['gallery'] = (int) $main_img;
							$images_taken[]       = (int) $main_img;
						}

						if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 && isset( $settings['sliderEnabled'] ) ) {
							foreach ( $gallery_img as $gallerys ) {
								$gallery[]['gallery'] = (int) $gallerys;
								$images_taken[]       = (int) $gallerys;
							}
						}
						/**
						 * Variation images to be bunch with the other gallery images
						 */
						if ( isset( $product->variations_data, $product->variations_data['images'], $settings['sliderEnabled'] ) ) {
							foreach ( $product->variations_data['images'] as $id ) {
								if ( false === in_array( $id, $images_taken, true ) ) {
									$gallery[]['gallery'] = (int) $id;
								}
							}
						}
						?>
						<?php
						wp_enqueue_style( 'flickity' );
						wp_enqueue_style( 'flickity-common' );
						if ( ! empty( $main_img ) ) {

							WFOCU_Core()->template_loader->get_template_part( 'product/slider', array(
								'key'     => $product_key,
								'gallery' => $gallery,
								'product' => $product_obj,
								'title'   => '',
								'style'   => 2,
							) );
						}
					}
				}

				if ( empty( $main_img ) ) {
					wp_enqueue_style( 'flickity' );
					wp_enqueue_style( 'flickity-common' );
					?>
                    <div class="bricks-widget-container">
                        <div class="wfocu-product-gallery ">
                            <div class="wfocu-product-carousel wfocu-product-image-single ">
                                <div class="wfocu-carousel-cell">
                                    <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                                </div>
                            </div>
                        </div>
						<?php if ( isset( $settings['sliderEnabled'] ) ) { ?>
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

						}
						?>
                    </div>
					<?php
				}

				?>
            </div>
			<?php
		}
	}
}