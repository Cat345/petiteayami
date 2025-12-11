<?php
if ( ! class_exists( 'WFOCU_Shortcodes' ) ) {
	class WFOCU_Shortcodes {
		private static $ins = null;

		public function __construct() {

			$shortcodes = $this->get_shortcodes();

			foreach ( $shortcodes as $shortcode ) {

				add_shortcode( $shortcode, array( $this, $shortcode . '_output' ) );

			}
		}

		public function get_shortcodes() {
			return apply_filters( 'wfocu_shortcodes', array(
				'wfocu_yes_link',
				'wfocu_no_link',
				'wfocu_variation_selector_form',
				'wfocu_qty_selector',
				'wfocu_product_image_slider',
				'wfocu_product_title',
				'wfocu_product_short_description',
			) );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function wfocu_yes_link_output( $atts, $html = '' ) {
			$atts = shortcode_atts( array(
				'key'   => 1,
				'class' => '',
			), $atts );
			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			if ( ! isset( $data->products ) ) {
				return $this->generate_demo_yes_link( $atts, $html );
			}

			if ( ! isset( $data->products->{$atts['key']} ) ) {
				$atts['key'] = WFOCU_Core()->offers->get_product_key_by_index( $atts['key'], $data->products );
			}


			ob_start();
			WFOCU_Core()->template_loader->add_attributes_to_buy_button();
			$attributes = ob_get_clean();

			return sprintf( '<a href="javascript:void(0);" class="%s" data-key="%s" %s>%s</a>', 'wfocu_upsell ' . esc_attr( $atts['class'] ), $atts['key'], $attributes, do_shortcode( $html ) );
		}

		public function generate_demo_yes_link( $atts, $html, $attributes = '' ) {
			return sprintf( '<a href="javascript:void(0);" class="%s" %s>%s</a>', 'wfocu_yes_btn ' . esc_attr( $atts['class'] ), $attributes, do_shortcode( $html ) );

		}

		public function generate_demo_no_link( $atts, $html, $attributes = '' ) {
			return sprintf( '<a href="javascript:void(0);" class="%s" %s>%s</a>', 'wfocu_no_btn ' . esc_attr( $atts['class'] ), $attributes, do_shortcode( $html ) );

		}

		public function wfocu_no_link_output( $atts, $html = '' ) {
			$atts = shortcode_atts( array(
				'key'   => 1,
				'class' => '',
			), $atts );

			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			if ( ! isset( $data->products ) ) {
				return $this->generate_demo_no_link( $atts, $html );
			}

			if ( ! isset( $data->products->{$atts['key']} ) ) {
				$atts['key'] = WFOCU_Core()->offers->get_product_key_by_index( $atts['key'], $data->products );
			}

			return sprintf( '<a href="javascript:void(0);" class="%s" data-key="%s">%s</a>', 'wfocu_skip_offer ' . esc_attr( $atts['class'] ), $atts['key'], do_shortcode( $html ) );
		}

		public function wfocu_variation_selector_form_output( $atts ) {
			$atts = shortcode_atts( array(
				'key'     => 1,
				'label'   => __( 'No, thanks', 'woofunnels-upstroke-one-click-upsell' ),
				'display' => 'yes',
			), $atts );


			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			if ( false === $data ) {
				return '';
			}


			if ( ! isset( $data->products->{$atts['key']} ) ) {
				$atts['key'] = WFOCU_Core()->offers->get_product_key_by_index( $atts['key'], $data->products );
			}
			if ( ! isset( $data->products->{$atts['key']} ) ) {
				return '';
			}

			if ( ! isset( $data->products->{$atts['key']}->variations_data ) ) {
				return '';
			}
			$product_raw = array(
				'key'     => $atts['key'],
				'product' => $data->products->{$atts['key']},
				'display' => $atts['display'],
			);
			ob_start();
			WFOCU_Core()->template_loader->get_template_part( 'product/variation-form', $product_raw );

			return ob_get_clean();
		}

		public function wfocu_qty_selector_output( $atts ) {
			$atts = shortcode_atts( array(
				'key'   => 1,
				'label' => __( 'Quantity', 'woocommerce' ),
			), $atts );


			$data = WFOCU_Core()->data->get( '_current_offer_data' );

			if ( false === $data ) {
				return '';
			}

			if ( ! isset( $data->products->{$atts['key']} ) ) {
				$atts['key'] = WFOCU_Core()->offers->get_product_key_by_index( $atts['key'], $data->products );
			}
			if ( empty( $atts['key'] ) ) {
				return '';
			}
			$product_raw = array(
				'key'     => $atts['key'],
				'product' => $data->products->{$atts['key']},
				'label'   => $atts['label'],
			);

			ob_start();

			WFOCU_Core()->template_loader->get_template_part( 'qty-selector', $product_raw );

			return ob_get_clean();
		}

		public function wfocu_product_image_slider_output( $atts ) {
			$atts = shortcode_atts( array(
				'key'   => 1,
				'label' => __( 'Product Image Slider', 'woofunnels-upstroke-one-click-upsell' ),
			), $atts );

			$key = $atts['key'];


			/** Gallery */
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return '';
			}
			if ( ! isset( WFOCU_Core()->template_loader->product_data->products->{$key} ) ) {
				$key = WFOCU_Core()->offers->get_product_key_by_index( $key, WFOCU_Core()->template_loader->product_data->products );
			}
			if ( empty( $key ) ) {
				return '';
			}
			/**
			 * @var WC_Product $product_obj
			 */
			$product_obj = WFOCU_Core()->template_loader->product_data->products->{$key}->data;
			$product     = WFOCU_Core()->template_loader->product_data->products->{$key};

			if ( $product_obj instanceof WC_Product ) {
				$main_img     = $product_obj->get_image_id();
				$gallery_img  = $product_obj->get_gallery_image_ids();
				$gallery      = array();
				$images_taken = array();
				if ( ! empty( $main_img ) ) {
					$gallery[]['gallery'] = (int) $main_img;
					$images_taken[]       = (int) $main_img;
				}

				if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 ) {
					foreach ( $gallery_img as $gallerys ) {
						$gallery[]['gallery'] = (int) $gallerys;
						$images_taken[]       = (int) $gallerys;
					}
				}
				/**
				 * Variation images to be bunch with the other gallery images
				 */
				if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) ) {
					foreach ( $product->variations_data['images'] as $id ) {
						if ( false === in_array( $id, $images_taken, true ) ) {
							$gallery[]['gallery'] = (int) $id;
						}
					}
				}

				if ( ! empty( $main_img ) ) {
					ob_start();
					WFOCU_Core()->template_loader->get_template_part( 'product/slider', array(
						'key'     => $key,
						'gallery' => $gallery,
						'product' => $product_obj,
						'title'   => '',
						'style'   => 2,
					) );

					$scripts = WFOCU_Core()->assets->get_scripts();

					wp_register_script( 'flickity', $scripts['flickity']['path'], [], true );
					wp_enqueue_script( 'flickity' );


					wp_print_styles( array( 'flickity', 'flickity-common' ) );
					?>
                    <script>
                        jQuery(document).ready(function () {
                            jQuery('.wfocu-product-carousel').each(function () {
                                var flickity_attr = jQuery(this).attr('data-flickity');
                                if (undefined !== flickity_attr) {
                                    jQuery(this).flickity(JSON.parse(flickity_attr));
                                }
                            });
                            if (jQuery('.wfocu-product-carousel-nav').length > 0) {
                                jQuery('.wfocu-product-carousel-nav').each(function () {
                                    var flickity_attr = jQuery(this).attr('data-flickity');
                                    if (undefined !== flickity_attr) {
                                        jQuery(this).flickity(JSON.parse(flickity_attr));
                                    }
                                });
                            }
                        });

                    </script>
					<?php

					return ob_get_clean();
				}
			}

			return '';
		}

		public function wfocu_product_title_output( $atts ) {

			$atts = shortcode_atts( array(
				'key' => 1
			), $atts );

			$data = WFOCU_Core()->data->get( '_current_offer_data' );

			$product = '';
			if ( false === $data || empty( $data->products ) ) {
				return '';
			}
			if ( ! isset( $data->products->{$atts['key']} ) ) {
				$atts['key'] = WFOCU_Core()->offers->get_product_key_by_index( $atts['key'], $data->products );
			}
			if ( empty( $atts['key'] ) ) {
				return '';
			}
			if ( isset( $data->products->{$atts['key']} ) ) {
				$product = $data->products->{$atts['key']}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return;
			}

			if ( $product instanceof WC_Product ) {
				$title = $product->get_title();
			}

			if ( empty( $title ) ) {
				return;
			}


			return $title;
		}

		public function wfocu_product_short_description_output( $atts ) {
			$atts = shortcode_atts( array(
				'key' => 1
			), $atts );

			$data = WFOCU_Core()->data->get( '_current_offer_data' );

			$product = '';
			if ( false === $data || empty( $data->products ) ) {
				return '';
			}

			if ( ! isset( $data->products->{$atts['key']} ) ) {
				$atts['key'] = WFOCU_Core()->offers->get_product_key_by_index( $atts['key'], $data->products );
			}
			if ( empty( $atts['key'] ) ) {
				return '';
			}

			if ( isset( $data->products->{$atts['key']} ) ) {
				$product = $data->products->{$atts['key']}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return;
			}

			$post_object = get_post( $product->get_id() );

			$description = $post_object->post_excerpt;
			if ( 'product_variation' === $post_object->post_type ) {
				$product = wc_get_product( $product->get_id() );
				if ( $product instanceof WC_Product ) {
					$description = $product->get_description();
				}
			}

			$short_description = apply_filters( 'woocommerce_short_description', $description );

			return $short_description;
		}


	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'shortcodes', 'WFOCU_Shortcodes' );
	}
}