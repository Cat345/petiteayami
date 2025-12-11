<?php
if ( ! class_exists( 'WFOCU_Product_Image' ) ) {
	class WFOCU_Product_Image extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			$this->ajax = true;
			parent::__construct();
		}

		public function setup_data() {

			$tab_id = $this->add_tab( __( 'Offer Product Images', 'woofunnels-upstroke-one-click-upsell' ), 5 );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );
			$this->add_switcher( $tab_id, 'slider_enabled', __( 'Enable Slider', 'woofunnels-upstroke-one-click-upsell' ), 'on', [], '<i>Note: The Slider will show on frontend only</i>' );

			$this->add_text_alignments( $tab_id, 'text_align', '%%order_class%% .wfocu-product-gallery .wfocu-carousel-cell>a' );
			$this->style_field();
		}

		public function style_field() {
			$tab_id = $this->add_tab( __( 'Featured Image', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$this->add_border( $tab_id, 'image_border', '%%order_class%% .wfocu-product-gallery img' );
			$this->add_margin( $tab_id, 'image_border_margin', '%%order_class%%  .wfocu-product-gallery' );
			$this->add_width( $tab_id, 'width', '%%order_class%%  .wfocu-product-gallery .wfocu-carousel-cell > a > img', '', '', [], true );
			// Need Max Width


			$tab_id = $this->add_tab( __( 'Thumbnails', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_border( $tab_id, 'thumbs_border', ' %%order_class%% .wfocu-product-thumbnails .wfocu-thumb-col a' );
			$this->add_margin( $tab_id, 'spacing_thumbs_margin', [
				'%%order_class%% .wfocu-product-thumbnails ',
			] );

		}

		public function html( $attrs, $content = null, $render_slug = '' ) {

			$settings = $this->props;
			$main_img = '';
			/** Gallery */
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}
			ob_start();
			if ( wp_doing_ajax() ) {
				?>
                <link rel="stylesheet" id="flickity-css" href="<?php echo plugin_dir_url( WFOCU_PLUGIN_FILE ); ?>/assets/flickity/flickity.css" type="text/css" media="all">
                <link rel="stylesheet" id="flickity-common-css" href="<?php echo plugin_dir_url( WFOCU_PLUGIN_FILE ); ?>/assets/css/flickity-common.css" type="text/css" media="all">
				<?php
			}

			$sel_product = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			if ( isset( $product_key ) && ! empty( $product_key ) ) {


				/**
				 * If the selected product is not present in the current set of products then assign the first
				 */
				if ( ! isset( WFOCU_Core()->template_loader->product_data->products->{$product_key} ) ) {
					$key = key( (array) WFOCU_Core()->template_loader->product_data->products );

					$product_key = $key;
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
					<?php if ( false && isset( $settings['slider_enabled'] ) && 'on' === $settings['slider_enabled'] ) { ?>
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
			if ( ! wp_doing_ajax() ) {
				wp_print_styles( array( 'flickity', 'flickity-common' ) );
			}

			return ob_get_clean();
		}
	}

	return new WFOCU_Product_Image;
}