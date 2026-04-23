<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Theme_Enfold' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Theme_Enfold {

		public function __construct() {

			/* checkout page */
			add_action( 'wfacp_checkout_page_found', array( $this, 'dequeue_actions' ) );

			add_action( 'wfacp_do_not_allow_shortcode_printing', array( $this, 'avia_do_not_allow_shortcode' ) );

			add_action( 'wfacp_none_checkout_pages', array( $this, 'force_execute_embed_shortcode' ), - 1 );
			add_action( 'wfacp_internal_css', array( $this, 'add_internal_css' ) );
		}

		public function force_execute_embed_shortcode() {
			if ( class_exists( 'WFACP_Template_loader' ) && $this->is_enabled() ) {
				global $post;
				if ( is_null( $post ) ) {
					return;
				}
				$shortcodes                = $post->post_content;
				$_aviaLayoutBuilder_active = get_post_meta( $post->ID, '_aviaLayoutBuilder_active', true );

				$start_position = strpos( $shortcodes, '[wfacp_forms' );

				if ( false !== $start_position && $_aviaLayoutBuilder_active === 'active' ) {
					$shortcode_string = substr( $shortcodes, $start_position );
					$closing_position = strpos( $shortcode_string, ']', 1 );
					if ( false !== $closing_position ) {
						$shortcode_string                          = substr( $shortcodes, $start_position, $closing_position + 1 );
						WFACP_Core()->embed_forms->current_page_id = $post->ID;
						if ( strlen( $shortcode_string ) > 0 ) {
							do_shortcode( $shortcode_string );
						}
					}
				}
			}
		}

		public function is_enabled() {
			if ( class_exists( 'AviaBuilder' ) ) {
				return true;
			}

			return false;
		}

		public function avia_do_not_allow_shortcode( $status ) {

			if ( ! $this->is_enabled() ) {
				return $status;
			}

			if ( isset( $_REQUEST['avia-save-nonce'] ) && ! empty( $_REQUEST['avia-save-nonce'] ) ) {
				return true;
			}

			return $status;
		}

		public function dequeue_actions() {
			if ( class_exists( 'aviaAssetManager' ) ) {
				$instance = WFACP_Common::remove_actions( 'wp_enqueue_scripts', 'aviaAssetManager', 'try_minifying_scripts' );
				add_action( 'wp_enqueue_scripts', array( $instance, 'try_minifying_scripts' ), 11 );
			}
		}

		public function add_internal_css() {
			if ( ! $this->is_enabled() ) {
				return;
			}

			$css = '';

			/**
			 * Enfold "Archives" template prints `.tabcontainer.top_tab` (with `.tab_titles` in newer Enfold) after the_content().
			 * Do not tie this only to body.wfacp_main_wrapper.woocommerce-checkout — some stores miss that class; do not require
			 * wfacp_template() instanceof — early return there previously prevented this CSS from loading at all.
			 *
			 * Primary: same `.entry-content-wrapper` as `#wfacp_checkout_form` (Archives layout). :has() needs a recent browser.
			 * Fallback: body classes. Exclude `.border_tabs` (Enfold Combo widget).
			 */
			if ( apply_filters( 'wfacp_enfold_hide_archives_template_tabs', true ) ) {
				$css .= '#top main.template-archives .entry-content-wrapper:has(#wfacp_checkout_form) .tabcontainer.top_tab:not(.border_tabs),';
				$css .= '#top .entry-content-wrapper:has(#wfacp_checkout_form) .tabcontainer.top_tab:not(.border_tabs),';
				$css .= 'body.wfacp_main_wrapper main.template-archives .tabcontainer.top_tab:not(.border_tabs),';
				$css .= 'body.wfacp_main_wrapper.woocommerce-checkout .tabcontainer.top_tab:not(.border_tabs),';
				$css .= 'body.wfacp_main_wrapper .template-archives .tabcontainer.top_tab:not(.border_tabs)';
				$css .= '{display:none!important;}';
			}

			$instance = wfacp_template();
			if ( $instance instanceof WFACP_Template_Common ) {
				$css .= '#top #wfacp-e-form form-row {padding: 0 7px;;margin: 0 0 15px;}';
				$css .= '#top label {font-weight: normal;}';
				$css .= '#top .wfacp_mini_cart_start_h .woocommerce-info {border: none !important;}';
			}

			if ( '' === $css ) {
				return;
			}

			echo '<style id="wfacp-enfold-compat">' . $css . '</style>';
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Theme_Enfold(), 'wfacp-enfold' );
}
