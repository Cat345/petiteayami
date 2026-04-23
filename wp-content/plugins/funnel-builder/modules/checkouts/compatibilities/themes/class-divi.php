<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Divi' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Divi {
		public $hooks = array(
			'et_pb_section_css_selector',
			'et_pb_row_css_selector',
			'et_pb_column_css_selector',
			'et_pb_image_css_selector',
			'et_pb_text_css_selector',
			'et_pb_blurb_css_selector',
			'et_pb_accordion_item_css_selector',
			'wfacp_checkout_form_css_selector',
			'wfacp_checkout_form_summary_css_selector',
			'et_pb_menu_css_selector',
			'et_pb_social_media_follow_css_selector',
		);

		public function __construct() {

			add_action(
				'after_setup_theme',
				function () {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking for page parameter
					if ( isset( $_GET['page'] ) && $_GET['page'] == 'wfacp' ) {
						remove_action( 'init', 'et_add_divi_support_center' );
					}
				}
			);

			add_action( 'init', array( $this, 'remove_actions' ), 0 );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
			/* Disabled override the body layout on FunnelKit Checkout  */
			add_action( 'wfacp_after_template_found', array( $this, 'maybe_disable_theme_builder' ), 8 );
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'add_hook' ) );
			add_filter( 'et_theme_builder_template_layouts', array( $this, 'disable_header_footer' ), 99 );

			add_action( 'template_redirect', array( $this, 'change_template_include_hook' ) );

			/*
			Swap global $post to FunnelKit checkout post before Divi 5's DynamicAssets::pre_initial_setup()
			 * which captures $post->ID at wp:0. This ensures Divi generates dynamic CSS for the
			 * FunnelKit checkout post instead of the WC checkout page. */
			add_action( 'wp', array( $this, 'maybe_early_post_swap_for_divi5' ), -1 );
		}


		public function disable_header_footer( $layouts ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking for Divi builder parameter
			if ( ! isset( $_GET['et_fb'] ) || ! defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' ) || ! WFACP_Common::is_theme_builder() ) {
				return $layouts;
			}

			global $post;
			if ( is_null( $post ) || $post->post_type !== WFACP_Common::get_post_type_slug() ) {
				return $layouts;
			}

			$my_template = get_post_meta( $post->ID, '_wp_page_template', true );
			if ( ( 'wfacp-canvas.php' == $my_template || 'wfacp-full-width.php' == $my_template ) && isset( $layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ] ) ) {
				$layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['id']       = 0;
				$layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['enabled']  = false;
				$layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['override'] = false;
				$layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['id']       = 0;
				$layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['enabled']  = false;
				$layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['override'] = false;
			}

			return $layouts;
		}

		public function remove_actions() {
			if ( wfacp_elementor_edit_mode() ) {
				remove_action( 'init', 'et_sync_custom_css_options' );
			}
		}

		public function maybe_disable_theme_builder() {

			if ( ! function_exists( 'et_setup_theme' ) || ! defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' ) ) {
				return;
			}
			$is_global_checkout = WFACP_Core()->public->is_checkout_override();
			if ( $is_global_checkout == true ) {
				$template_array = array(
					'wfacp-canvas.php',
					'template-default-boxed.php',
					'page-template-blank.php',
				);
				$disable_for    = apply_filters( 'et_builder_compatibility_wfacp_checkout_templates_without_theme_builder', $template_array );
				$template       = get_post_meta( get_the_ID(), '_wp_page_template', true );
				if ( in_array( $template, $disable_for, true ) ) {
					add_filter( 'et_theme_builder_template_layouts', array( $this, 'disable_theme_builder' ) );
				}
			}
		}

		public function disable_theme_builder( $layouts ) {
			if ( isset( $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] ) ) {
				$layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] = false;
			}

			return $layouts;
		}

		public function internal_css() {

			if ( ! defined( 'ET_CORE_VERSION' ) || ! function_exists( 'et_setup_theme' ) ) {
				return;
			}
			?>

			<style>

				#wfacp-e-form .wfacp-form .woocommerce-form-login-toggle .woocommerce-info a.showlogin,
				#wfacp-e-form .wfacp-form .woocommerce-form-login-toggle .woocommerce-info a {
					color: #dd7575 !important;;

				}

				#wfacp-e-form .wfacp_main_form .woocommerce-form-login-toggle .woocommerce-info a:hover,
				#wfacp-e-form .wfacp_main_form a span:hover,
				#wfacp-e-form .wfacp_main_form label a:not(.woocommerce-terms-and-conditions-link):hover,
				#wfacp-e-form .wfacp_main_form table tr td a:hover,
				body:not(.wfacpef_page)
				#wfacp-e-form .wfacp_main_form a:not(.wfacp_breadcrumb_link):hover:not(.wfob_btn_add),
				body:not(.wfacpef_page) #wfacp-e-form .wfacp_main_form ul li a:not(.wfacp_breadcrumb_link):hover {
					color: #965d5d !important;
				}

				body {
					line-height: 1.5 !important;
				}

				body .woocommerce #respond input#submit,
				body .woocommerce-page #respond input#submit,
				body .woocommerce #content input.button,
				body .woocommerce-page #content input.button,
				body .woocommerce-message, .woocommerce-error,
				body .woocommerce-info {
					background: transparent !important;
				}

				body table.shop_table {
					margin-bottom: 0px !important;
				}

				.woocommerce-form-login-toggle .woocommerce-info {
					color: #353030 !important;
				}

				/* Smart Login divi css */
				.wfacp-quickv-login-active #et-boc .et-l .et_pb_column {
					z-index: inherit;
				}

				.et-db #et-boc .et-l #funnelkitLoginModal .wfacp_btn_clicked:before,
				.et-db #et-boc .et-l #wfacp_checkout_form .wfacp-coupon-field-btn.wfacp_btn_clicked:before,
				.et-db #et-boc .et-l .wfacp-coupon-btn.wfacp_btn_clicked:before {

					opacity: 1;

				}
			</style>
			<?php
		}


		public function add_hook() {
			/**
			 * Stop printing multiple checkout
			 */
			add_action( 'wp_head', array( $this, 'remove_hook' ), 9999 );
			$is_global_checkout = WFACP_Core()->public->is_checkout_override();

			if ( $is_global_checkout === false ) {
				return;
			}
			if ( is_array( $this->hooks ) && count( $this->hooks ) > 0 ) {
				foreach ( $this->hooks as $key => $hook_name ) {
					add_filter( $hook_name, array( $this, 'add_selector' ) );

				}
			}
		}

		public function add_selector( $selector ) {
			return 'body.et-db #et-boc .et-l ' . $selector;
		}


		public function change_template_include_hook() {
			if ( ! is_checkout() ) {
				return;
			}
			$design = WFACP_Common::get_page_design( WFACP_Common::get_id() );

			if ( in_array( $design['selected_type'], array( 'divi', 'divi5' ), true ) ) {
				$instance = WFACP_Template_loader::get_instance();

				remove_action( 'template_include', array( $instance, 'assign_template' ), 95 );
				add_action( 'template_include', array( $instance, 'assign_template' ), 99 );
			}
		}

		public function maybe_early_post_swap_for_divi5() {
			try {
				if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
					return;
				}

				if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
					return;
				}

				// Only run on store checkout (WC checkout page overridden by FunnelKit),
				// not on direct FunnelKit checkout pages which already have the correct post.
				global $post;
				if ( $post instanceof WP_Post && WFACP_Common::get_post_type_slug() === $post->post_type ) {
					return;
				}

				$checkout_page_id = WFACP_Common::get_checkout_page_id();
				if ( absint( $checkout_page_id ) <= 0 ) {
					return;
				}

				$checkout_post = get_post( $checkout_page_id );
				if ( ! $checkout_post || 'publish' !== $checkout_post->post_status ) {
					return;
				}

				$design = WFACP_Common::get_page_design( $checkout_page_id );
				if ( ! isset( $design['selected_type'] ) || ! in_array( $design['selected_type'], array( 'divi', 'divi5' ), true ) ) {
					return;
				}

				global $post, $wp_query;
				$post = $checkout_post;
				setup_postdata( $post );

				// Also update the main query so Divi's get_the_ID(), is_singular(),
				// and et_builder_should_wrap_styles() detect the correct CPT post type.
				if ( $wp_query instanceof WP_Query ) {
					$wp_query->queried_object    = $checkout_post;
					$wp_query->queried_object_id = $checkout_post->ID;
					$wp_query->post              = $checkout_post;
					$wp_query->posts             = array( $checkout_post );
				}
			} catch ( \Error $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		public function remove_hook() {
			/**
			 * Stop multiple form printing due to Divi menu and Divi Bars plugin
			 */
			if ( 0 === did_action( 'wp_body_open' ) ) {
				if ( class_exists( 'DiviMegaPro_Controller' ) ) {
					remove_action( 'wp_body_open', array( 'DiviMegaPro_Controller', 'addDiviMegaPro' ), 10 );
				}
				if ( class_exists( 'DiviBars_Controller' ) ) {
					remove_action( 'wp_body_open', array( 'DiviBars_Controller', 'addDiviBars' ), 10 );
				}
			}
		}
	}


	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Divi(), 'divi' );

}
