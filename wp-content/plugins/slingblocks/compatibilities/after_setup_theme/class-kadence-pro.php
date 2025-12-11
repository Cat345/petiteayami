<?php

namespace SlingBlocks\Compatibility;

/**
 * Kadence Pro
 */
if ( ! class_exists( 'Kadence_Pro' ) ) {
	class Kadence_Pro {

		public function __construct() {
			add_action( 'wp', array( $this, 'init_frontend_hooks' ), 100 );
		}

		/**
		 * Loop through elements and hook items in where needed.
		 */
		public function init_frontend_hooks() {
			if ( ! class_exists( '\Kadence_Pro\Elements_Post_Type_Controller' ) ) {
				return;
			}

			$slug = \Kadence_Pro\Elements_Post_Type_Controller::SLUG;
			if ( is_admin() || is_singular( $slug ) ) {
				return;
			}

			$args  = array(
				'post_type'              => $slug,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'post_status'            => 'publish',
				'numberposts'            => 333,
				'order'                  => 'ASC',
				'orderby'                => 'menu_order',
				'suppress_filters'       => false,
			);
			$posts = get_posts( $args );
			if ( empty( $posts ) ) {
				return;
			}

			$elem_ins = \Kadence_Pro\Elements_Post_Type_Controller::get_instance();
			foreach ( $posts as $post ) {
				$meta = $elem_ins->get_post_meta_array( $post );
				if ( ! isset( $meta['hook'] ) || empty( $meta['hook'] ) ) {
					continue;
				}
				$display = apply_filters( 'kadence_element_display', $elem_ins->check_element_conditionals( $post, $meta ), $post, $meta );
				if ( false === $display ) {
					continue;
				}

				/** Display CSS */
				if ( 'fixed' !== substr( $meta['hook'], 0, 5 ) && 'custom' !== $meta['hook'] && 'replace_header' !== $meta['hook'] && 'replace_404' !== $meta['hook'] && 'replace_footer' !== $meta['hook'] && 'kadence_before_wrapper' !== $meta['hook'] && 'replace_login_modal' !== $meta['hook'] && 'replace_hero_header' !== $meta['hook'] && 'replace_single_content' !== $meta['hook'] && 'replace_archive_content' !== $meta['hook'] && 'replace_loop_content' !== $meta['hook'] && 'replace_meta' !== $meta['hook'] && 'woocommerce_before_single_product_image' !== $meta['hook'] && 'woocommerce_after_single_product_image' !== $meta['hook'] && 'kadence_inside_the_content_before_h1' !== $meta['hook'] && 'kadence_inside_the_content_after_h1' !== $meta['hook'] && 'kadence_inside_the_content_after_p1' !== $meta['hook'] && 'kadence_inside_the_content_after_p2' !== $meta['hook'] && 'kadence_inside_the_content_after_p3' !== $meta['hook'] && 'kadence_inside_the_content_after_p4' !== $meta['hook'] && 'kadence_replace_sidebar' !== $meta['hook'] ) {
					$this->enqueue_element_styles( $post, $meta );
				} else if ( 'custom' === $meta['hook'] && isset( $meta['custom'] ) && ! empty( $meta['custom'] ) ) {
					$this->enqueue_element_styles( $post, $meta );
				} else if ( 'replace_404' === $meta['hook'] ) {
					if ( is_404() ) {
						$this->enqueue_element_styles( $post, $meta );
					}
				} elseif ( strlen( $meta['hook'] ) > 26 && 'kadence_inside_the_content' === substr( $meta['hook'], 0, 26 ) ) {
					$this->enqueue_element_styles( $post, $meta );
				} elseif ( 'fixed' === substr( $meta['hook'], 0, 5 ) ) {
					$this->enqueue_element_styles( $post, $meta );
				} else {
					$hooks = [
						'kadence_before_wrapper',
						'replace_header',
						'replace_footer',
						'replace_hero_header',
						'kadence_replace_sidebar',
						'replace_single_content',
						'replace_loop_content',
						'woocommerce_before_single_product_image',
						'woocommerce_after_single_product_image',
						'replace_login_modal'
					];
					if ( in_array( $meta['hook'], $hooks, '' ) ) {
						$this->enqueue_element_styles( $post, $meta );
					}
				}
			}
		}

		public function enqueue_element_styles( $post, $meta ) {
			$content = $post->post_content;
			if ( ! $content ) {
				return;
			}

			if ( ! has_blocks( $content ) ) {
				return;
			}

			if ( ! class_exists( '\SLINGBLOCKS_Frontend_CSS' ) ) {
				return;
			}

			$slingblock_styles = \SLINGBLOCKS_Frontend_CSS::get_instance();
			if ( ! method_exists( $slingblock_styles, 'frontend_build_css' ) ) {
				return;
			}

			$slingblock_styles->frontend_build_css( $post );
		}
	}

	if ( defined( 'KADENCE_BLOCKS_VERSION' ) && class_exists( 'Kadence_Theme_Pro' ) ) {
		new Kadence_Pro();
	}
}
