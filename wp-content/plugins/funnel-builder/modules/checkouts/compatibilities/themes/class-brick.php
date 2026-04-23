<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Compatibility_With_Theme_Brick' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Theme_Brick {
		private $shortcode_content = '';

		public function __construct() {
			/* checkout page */

			add_filter( 'wfacp_shortcode_exist', array( $this, 'is_shortcode_exists' ), 10, 2 );
			add_filter( 'wfacp_detect_shortcode', array( $this, 'send_brick_content' ) );
			add_action( 'wp_ajax_bricks_get_element_html', array( $this, 'remove_our_shortcode' ), 9 );
			add_action( 'wfacp_update_page_design', array( $this, 'update_page_template' ), 99, 1 );
			add_filter( 'rest_dispatch_request', array( $this, 'remove_our_shortcode' ), 20 );

			add_filter( 'wfacp_do_not_execute_shortcode', array( $this, 'do_not_execute_shortcode' ), 20 );
			add_filter( 'wfacp_do_not_allow_shortcode_printing', array( $this, 'do_not_execute_shortcode' ), 20 );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );

			// Fix Bricks global header template overriding checkout page post context
			add_action( 'wfacp_template_load', array( $this, 'fix_bricks_header_post_override' ) );
		}

		/**
		 * Do Not execute shortcode when bricks builder is open (sometime Session expired message displayed)
		 *
		 * @param mixed $status Current status.
		 * @return bool|mixed
		 */
		public function do_not_execute_shortcode( $status ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for builder context
			if ( isset( $_GET['bricks'] ) && 'run' === sanitize_text_field( wp_unslash( $_GET['bricks'] ) ) ) {
				$status = true;
			}

			return $status;
		}

		public function is_shortcode_exists( $status, $post ) {
			if ( true === $status ) {
				return $status;
			}

			$content = $this->get_shortcode_content( $post );
			if ( false !== $content ) {
				$this->shortcode_content = $content;
				$status                  = true;
			}

			return $status;
		}

		public function get_shortcode_content( $post ) {
			if ( is_null( $post ) || ! $post instanceof WP_Post ) {
				return false;
			}

			$panels_data = get_post_meta( $post->ID, BRICKS_DB_PAGE_CONTENT, true );

			if ( empty( $panels_data ) ) {
				return false;
			}
			$shortcodes     = json_encode( $panels_data );
			$start_position = strpos( $shortcodes, '[wfacp_forms' );
			if ( false === $start_position ) {
				return false;
			}
			$shortcode_string = substr( $shortcodes, $start_position );
			$closing_position = strpos( $shortcode_string, ']', 1 );
			if ( false === $closing_position ) {
				return false;
			}
			$shortcode_string = substr( $shortcodes, $start_position, $closing_position + 1 );
			if ( strlen( $shortcode_string ) <= 0 ) {
				return false;
			}

			return $shortcode_string;
		}

		public function send_brick_content( $post_content ) {
			return ! empty( $this->shortcode_content ) ? $this->shortcode_content : $post_content;
		}

		public function remove_our_shortcode( $request ) {
			if ( ! function_exists( 'bricks_is_rest_call' ) || ! function_exists( 'bricks_is_ajax_call' ) ) {
				return $request;
			}

			if ( bricks_is_rest_call() || bricks_is_ajax_call() ) {
				remove_shortcode( 'wfacp_forms' );
				remove_shortcode( 'wfacp_mini_cart' );
			}

			return $request;
		}

		/**
		 * Set default template when bricks theme activated
		 *
		 * @param int $page_id The page ID.
		 * @return void
		 */
		public function update_page_template( $page_id ) {
			if ( true === $this->is_enabled() && 'bricks' === get_template() ) {
				update_post_meta( $page_id, '_wp_page_template', '' );
			}
		}

		public function is_enabled() {
			return function_exists( 'bricks_is_builder' );
		}

		public function internal_css() {
			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}

			$bodyClass = 'body ';
			$px        = $instance->get_template_type_px() . 'px';
			if ( 'pre_built' !== $instance->get_template_type() ) {
				$bodyClass = 'body #wfacp-e-form ';
				$px        = '7px';
			}

			$cssHtml  = '<style>';
			$cssHtml .= $bodyClass . '.woocommerce-checkout #payment{padding: 0;}';
			$cssHtml .= $bodyClass . '.woocommerce-form__input-checkbox {display: inline-block !important;}';
			$cssHtml .= 'body :not(.woocommerce-checkout) [class*="woocommerce"] * + p > * + * {margin-block-start: inherit;margin-block-end: 0;}';
			$cssHtml .= '</style>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS output is safe
			echo $cssHtml;
		}

		/**
		 * Fix Bricks global header template overriding checkout page post context
		 *
		 * @since 3.22.1
		 * @return void
		 */
		public function fix_bricks_header_post_override() {
			if ( is_admin() ) {
				return;
			}

			if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
				return;
			}

			if ( function_exists( 'bricks_is_ajax_call' ) && bricks_is_ajax_call() ) {
				return;
			}

			if ( function_exists( 'bricks_is_rest_call' ) && bricks_is_rest_call() ) {
				return;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			}

			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return;
			}

			add_action( 'bricks_after_header', array( $this, 'restore_checkout_post_context' ), 1 );
		}

		/**
		 * Restore checkout post context after Bricks header renders
		 *
		 * @since 3.22.1
		 * @return void
		 */
		public function restore_checkout_post_context() {
			if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
				return;
			}

			if ( ! class_exists( 'WFACP_Core' ) || ! isset( WFACP_Core()->public ) ) {
				return;
			}

			if ( ! class_exists( 'WFACP_Common' ) ) {
				return;
			}

			$checkout_page_id = WFACP_Common::get_id();

			if ( absint( $checkout_page_id ) <= 0 ) {
				return;
			}

			global $post;

			if ( $post && $post->ID === $checkout_page_id ) {
				return;
			}

			$checkout_post = get_post( $checkout_page_id );

			if ( ! $checkout_post ) {
				return;
			}

			$post = $checkout_post;
			setup_postdata( $post );

			// Only add filter if not already registered (prevents duplicate registration)
			if ( ! has_filter( 'the_content', array( $this, 'force_checkout_content' ) ) ) {
				add_filter( 'the_content', array( $this, 'force_checkout_content' ), 10 );
			}
		}

		/**
		 * Force checkout content in main loop
		 *
		 * @since 3.22.1
		 * @param string $content The content.
		 * @return string
		 */
		public function force_checkout_content( $content ) {
			if ( ! in_the_loop() || ! is_main_query() ) {
				return $content;
			}

			if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
				return $content;
			}

			if ( ! class_exists( 'WFACP_Common' ) ) {
				return $content;
			}

			$checkout_page_id = WFACP_Common::get_id();

			if ( absint( $checkout_page_id ) <= 0 ) {
				return $content;
			}

			$checkout_post = get_post( $checkout_page_id );

			if ( ! $checkout_post instanceof WP_Post || 'publish' !== $checkout_post->post_status ) {
				return $content;
			}

			// Remove the filter after first use to prevent stacking
			remove_filter( 'the_content', array( $this, 'force_checkout_content' ), 10 );

			return isset( $checkout_post->post_content ) ? $checkout_post->post_content : $content;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Theme_Brick(), 'wfacp-brick' );
}
