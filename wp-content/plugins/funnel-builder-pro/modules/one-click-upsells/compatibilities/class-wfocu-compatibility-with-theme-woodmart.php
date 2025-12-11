<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WFOCU_Compatibility_With_WoodMart_Theme
 */
if ( ! class_exists( 'WFOCU_Compatibility_With_WoodMart_Theme' ) ) {
	class WFOCU_Compatibility_With_WoodMart_Theme {

		public function __construct() {
			add_action( 'wp', [ $this, 'register_elementor_widget' ], 150 );
		}

		public function is_enable() {
			if ( defined( 'WOODMART_THEME_DIR' ) ) {
				return true;
			}

			return false;
		}

		public function register_elementor_widget() {

			if ( true !== $this->is_enable() || is_admin() ) {
				return;
			}

			global $post;
			if ( ! is_object( $post ) || ! $post instanceof WP_Post || 'wfocu_offer' !== $post->post_type ) {
				return;
			}

			if ( 'wfocu-canvas.php' === $post->page_template ) {

				if ( class_exists( 'BWF_Admin_General_Settings' ) ) {
					$allowed_steps = BWF_Admin_General_Settings::get_instance()->get_option( 'allow_theme_css' );
					if ( ! is_array( $allowed_steps ) || ! in_array( $post->post_type, $allowed_steps, true ) ) {
						remove_action( 'wp_enqueue_scripts', 'woodmart_enqueue_base_styles', 10000 );
						remove_action( 'wp_enqueue_scripts', 'woodmart_dequeue_elementor_frontend', 6 );
					}
				}
				remove_action( 'wp_footer', 'woodmart_sticky_toolbar_template' );
				if ( function_exists( 'woodmart_search_full_screen' ) ) {
					remove_action( 'wp_footer', 'woodmart_search_full_screen', 1 );
				}

				if ( function_exists( 'woodmart_get_opt' ) && function_exists( 'woodmart_get_theme_info' ) ) {
					// load woodmart theme typekit fonts.
					$typekit_id = woodmart_get_opt( 'typekit_id' );
					$version    = woodmart_get_theme_info( 'Version' );

					if ( ! empty ( $typekit_id ) ) {
						$project_ids = explode( ',', $typekit_id );
						foreach ( $project_ids as $id ) {
							wp_enqueue_style( 'woodmart-typekit-' . $id, 'https://use.typekit.net/' . esc_attr( $id ) . '.css', array(), $version );
						}
					}
				}
			}
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WoodMart_Theme(), 'woodmart_theme' );
}