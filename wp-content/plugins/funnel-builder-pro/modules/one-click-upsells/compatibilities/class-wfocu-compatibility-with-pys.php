<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_PYS' ) ) {
	class WFOCU_Compatibility_With_PYS {
		public function __construct() {
			add_action( 'wp_head', array( $this, 'maybe_unhook' ), - 1 );
			add_action( 'template_redirect', array( $this, 'maybe_unhook' ), - 1 );
		}

		public function is_enable() {
			return class_exists( 'PixelYourSite\EventsManager' );
		}

		/**
		 * Unhook PYS override datalayer script
		 * @return void
		 */
		public function maybe_unhook() {
			if ( true !== $this->is_enable() ) {
				return;
			}

			if ( ! class_exists( 'PixelYourSite\GATags' ) ) {
				return;
			}

			global $post;
			if ( is_null( $post ) || empty( $post->post_type ) ) {
				return;
			}

			if ( ( 'wfocu_offer' === $post->post_type ) || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) {
				/**
				 * Check if datalayer override setting enabled
				 */
				if ( empty( PixelYourSite\GATags() ) || PixelYourSite\GATags()->getOption( 'gtag_datalayer_type' ) === 'disable' ) {
					return;
				}

				if ( ! class_exists( 'BWF_Admin_General_Settings' ) ) {
					return;
				}

				$instance = BWF_Admin_General_Settings::get_instance();

				/**
				 * Check if any ga or gads enabled
				 */
				if ( empty( $instance->get_option( 'ga_key' ) ) && empty( $instance->get_option( 'gad_key' ) ) ) {
					return;
				}

				WFOCU_Common::remove_actions( 'wp_head', 'PixelYourSite\GATags', 'start_output_buffer' );
				WFOCU_Common::remove_actions( 'template_redirect', 'PixelYourSite\GATags', 'start_output_buffer' );

			}

		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_PYS(), 'wfocu_pys' );
}