<?php
if ( ! class_exists( 'WFOCU_Guten_Product_Image' ) ) {
	class WFOCU_Guten_Product_Image extends WFOCU_Guten_Field {
		public $slug = 'wfocu_product_Image';
		protected $id = 'wfocu_product_Image';

		public function __construct() {
			$this->ajax = true;
			$this->name = __( "WF Product Image" );
			parent::__construct();
		}


		public function html( $settings ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$main_img = '';
			/** Gallery */
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$sel_product_key = isset( $settings['product'] ) ? $settings['product'] : '';
			$product_key     = WFOCU_Common::default_selected_product_key( $sel_product_key );
			$product_key     = ( $product_key !== false ) ? $product_key : $sel_product_key;

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
					$slider_enabled = wc_string_to_bool( $settings['slider_enabled'] );
					$main_img       = $product_obj->get_image_id();
					$gallery_img    = $product_obj->get_gallery_image_ids();

					$gallery      = array();
					$images_taken = array();
					if ( ! empty( $main_img ) ) {
						$gallery[]['gallery'] = (int) $main_img;
						$images_taken[]       = (int) $main_img;
					}

					if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 && $slider_enabled ) {
						foreach ( $gallery_img as $gallerys ) {
							$gallery[]['gallery'] = (int) $gallerys;
							$images_taken[]       = (int) $gallerys;
						}
					}

					/**
					 * Variation images to be bunch with the other gallery images
					 */

					if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) && $slider_enabled ) {
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
                    .wfocu-carousel-cell:first-child a {
                        display: inline-block;
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
					<?php if ( false && isset( $settings['slider_enabled'] ) && $slider_enabled ) { ?>
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

		}

	}

	return new WFOCU_Guten_Product_Image;
}
