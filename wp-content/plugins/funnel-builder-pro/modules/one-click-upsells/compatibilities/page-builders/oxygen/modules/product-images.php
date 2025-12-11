<?php
if ( ! class_exists( 'WFOCU_Oxy_Product_Image' ) ) {

	class WFOCU_Oxy_Product_Image extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_product_Image';
		protected $id = 'wfocu_product_Image';

		public function __construct() {
			$this->ajax = true;
			$this->name = __( "WF Product Image" );
			parent::__construct();
		}


		public function setup_data() {

			$tab_id = $this->add_tab( __( 'Offer Product Images', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );
			$this->add_switcher( $tab_id, 'slider_enabled', __( 'Enable Slider', 'woofunnels-upstroke-one-click-upsell' ), 'on' );
			$this->add_text_alignments( $tab_id, 'text_align', '.wfocu-product-gallery .wfocu-carousel-cell>a' );
			$this->style_field();
		}

		public function style_field() {
			$tab_id = $this->add_tab( __( 'Featured Image', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_border( $tab_id, 'image_border', '.wfocu-product-gallery img' );
			$this->add_margin( $tab_id, 'image_border_margin', ' .wfocu-product-gallery' );
			$width_default = [ 'default' => "100" ];

			$this->add_width( $tab_id, 'width', ' .wfocu-product-gallery .wfocu-carousel-cell > a > img', '', $width_default );
			// Need Max Width


			$tab_id = $this->add_tab( __( 'Thumbnails', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_border( $tab_id, 'thumbs_border', ' .wfocu-product-thumbnails .wfocu-thumb-col a' );
			$this->add_margin( $tab_id, 'spacing_thumbs_margin', '.wfocu-product-thumbnails ' );

		}


		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			wp_enqueue_style( 'flickity' );
			wp_enqueue_style( 'flickity-common' );
			$main_img = '';
			/** Gallery */

			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product     = WFOCU_Common::default_selected_product( $sel_product );
			$product_key = WFOCU_Common::default_selected_product_key( $sel_product );
			$product_key = ( $product_key !== false ) ? $product_key : '';

			if ( '' !== $product_key ) {

				/**
				 * @var WC_Product $product_obj
				 */
				$product_obj = WFOCU_Core()->template_loader->product_data->products->{$product_key}->data;
				if ( $product_obj instanceof WC_Product ) {
					$main_img    = $product_obj->get_image_id();
					$gallery_img = $product_obj->get_gallery_image_ids();

					$gallery      = array();
					$images_taken = array();
					if ( ! empty( $main_img ) ) {
						$gallery[]['gallery'] = (int) $main_img;
						$images_taken[]       = (int) $main_img;
					}

					if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 && 'on' === $settings['slider_enabled'] ) {
						foreach ( $gallery_img as $gallerys ) {
							$gallery[]['gallery'] = (int) $gallerys;
							$images_taken[]       = (int) $gallerys;
						}
					}

					/**
					 * Variation images to be bunch with the other gallery images
					 */
					if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) && 'on' === $settings['slider_enabled'] ) {
						foreach ( $product->variations_data['images'] as $id ) {
							if ( false === in_array( $id, $images_taken, true ) ) {
								$gallery[]['gallery'] = (int) $id;
							}
						}
					}
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
				?>
                <style>
                    .oxy-product-image {
                        position: relative;;
                    }

                    .oxy-product-image .wfocu-product-carousel-container img {
                        height: auto;
                        display: inline-block;
                        vertical-align: middle;
                    }

                    /* editior css  */
                    .ct-component .wfocu-product-thumbnails img {
                        margin: 0;
                        border: none;
                    }

                    .ct-component .wfocu-product-thumbnails .wfocu-thumb-col {
                        margin: auto;
                        width: 100px;
                        display: inline-block;
                    }

                    .ct-component .wfocu-product-thumbnails {
                        clear: both;
                        width: 100%;
                    }


                    .ct-component .wfocu-product-thumbnails:after,
                    .ct-component .wfocu-product-thumbnails:before {
                        content: '';
                        display: block;
                    }

                    .ct-component .wfocu-product-thumbnails:after {
                        clear: both;
                    }

                    .ct-component .wfocu-product-thumbnails {
                        text-align: center;
                        margin-top: 20px;
                    }

                    .ct-component .wfocu-product-thumbnails .wfocu-carousel-cell {
                        text-align: center;
                        margin-top: 20px;
                    }

                    .ct-component .wfocu-product-thumbnails .wfocu-thumb-col a {
                        display: inline-block;
                    }

                    /* Big Image */

                    .ct-component .wfocu-product-thumbnails .wfocu-carousel-cell {
                        display: none;
                    }

                    .ct-component .wfocu-carousel-cell {
                        display: none;
                    }

                    .ct-component .wfocu-carousel-cell:first-child {
                        display: block;
                        width: auto;
                        text-align: center;
                    }

                    .wfocu-carousel-cell:first-child a {
                        display: inline-block;
                    }

                    .oxy-wfocu-product-image {
                        width: 100%;
                    }
                </style>
				<?php

			}

			if ( empty( $main_img ) ) { ?>
                <div class="wfocu-widget-container">
                    <div class="wfocu-product-gallery ">
                        <div class="wfocu-product-carousel wfocu-product-image-single ">
                            <div class="wfocu-carousel-cell">
                                <a><img src="<?php echo esc_url( wc_placeholder_img_src('thumbnail') ); ?>" alt="" title=""></a>
                            </div>
                        </div>
                    </div>
					<?php if ( isset( $settings['slider_enabled'] ) && 'on' === $settings['slider_enabled'] ) { ?>
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

			<?php


		}

		public function defaultCSS() {

			$defaultCSS = "
			  .wfocu-product-gallery .wfocu-carousel-cell > a > img {
                width: 100%;
                display: block;
                margin: auto;
            }

            .wfocu-product-gallery img,
            .wfocu-product-thumbnails .wfocu-thumb-col a {
                border: 1px solid #dddddd;
            }
            .wfocu-product-gallery .wfocu-carousel-cell > a {
                text-align: left;
                display: block;
            }

            .wfocu-thumb-col img {
                display: block;
                max-width: 100%;
                margin: 0 auto;
            }

		";

			return $defaultCSS;


		}


	}

	return new WFOCU_Oxy_Product_Image;
}
