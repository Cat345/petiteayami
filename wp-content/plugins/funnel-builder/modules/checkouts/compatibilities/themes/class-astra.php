<?php
if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Astra' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Astra {

		public function __construct() {
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'remove_actions' ) );
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_actions' ) );
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_astra_addon' ) );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
			/*--------------------Remove Action of Astra Addon-------------------- */
			add_action( 'wp', array( $this, 'actions' ), 8 );
			add_action( 'wp_footer', array( $this, 'remove_script' ), 9999 );
			/* Strip Astra's dark-mode body class on FunnelKit checkout so body.ast-dark-mode cascades stop applying. */
			add_filter( 'body_class', array( $this, 'remove_dark_mode_body_class' ), 999 );
		}

		public function actions() {

			if ( ! class_exists( 'ASTRA_Ext_WooCommerce_Markup' ) || ! class_exists( 'WFACP_Common' ) || ! is_checkout() ) {
				return;
			}
			$id = WFACP_Common::get_id();
			if ( absint( $id ) <= 0 ) {
				return;
			}

			WFACP_Common::remove_actions( 'wp', 'ASTRA_Ext_WooCommerce_Markup', 'modern_checkout' );
			WFACP_Common::remove_actions( 'wp', 'ASTRA_Ext_WooCommerce_Markup', 'multistep_checkout' );
			WFACP_Common::remove_actions( 'woocommerce_before_checkout_form', 'ASTRA_Ext_WooCommerce_Markup', 'checkout_collapsible_order_review' );
		}

		public function remove_actions() {
			remove_action( 'woocommerce_checkout_before_customer_details', 'astra_two_step_checkout_form_wrapper_div', 1 );
			remove_action( 'woocommerce_checkout_before_customer_details', 'astra_two_step_checkout_form_ul_wrapper', 2 );
			remove_action( 'woocommerce_checkout_order_review', 'astra_woocommerce_div_wrapper_close', 30 );
			remove_action( 'woocommerce_checkout_order_review', 'astra_woocommerce_ul_close', 30 );
			remove_action( 'woocommerce_checkout_before_customer_details', 'astra_two_step_checkout_address_li_wrapper', 5 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'astra_woocommerce_li_close' );
			if ( class_exists( 'ASTRA_Ext_WooCommerce_Markup' ) ) {
				WFACP_Common::remove_actions( 'woocommerce_cart_item_name', 'ASTRA_Ext_WooCommerce_Markup', 'add_cart_product_image' );
			}
		}


		public function remove_astra_addon() {

			$template = wfacp_template();

			if ( is_null( $template ) ) {
				return;
			}

			if ( class_exists( 'Astra_Ext_Nav_Menu_Loader' ) ) {
				WFACP_Common::remove_actions( 'wp_nav_menu_args', 'Astra_Ext_Nav_Menu_Loader', 'modify_nav_menu_args' );
				WFACP_Common::remove_actions( 'astra_theme_defaults', 'Astra_Ext_Nav_Menu_Loader', 'theme_defaults' );
				WFACP_Common::remove_actions( 'wp_enqueue_scripts', 'Astra_Ext_Nav_Menu_Loader', 'load_scripts' );
				WFACP_Common::remove_actions( 'customize_register', 'Astra_Ext_Nav_Menu_Loader', 'customize_register' );
				WFACP_Common::remove_actions( 'wp_footer', 'Astra_Ext_Nav_Menu_Loader', 'megamenu_style' );
				WFACP_Common::remove_actions( 'customize_preview_init', 'Astra_Ext_Nav_Menu_Loader', 'customize_preview_init' );
			}

			/*
			 * Only strip Astra's stylesheets on PRE-BUILT checkout templates.
			 * On non-pre-built (Aero) templates this strip removes Astra's form
			 * styling that positions checkbox inputs — so once a checkbox row is
			 * revealed (e.g. by the colour reclaim below, or a third-party
			 * opt-in injected into the checkout) it renders mangled/overlapping.
			 * Before this compat was un-gated, the strip never ran on non-pre-
			 * built checkouts, which is why the layout only breaks after
			 * updating. Dark mode is neutralised for EVERY template type by
			 * internal_css() (scoped `--ast-global-color-*` reset + colour
			 * reclaim), so the aggressive stylesheet strip isn't needed here.
			 */
			if ( 'pre_built' === $template->get_template_type() ) {
				add_action( 'wp_print_styles', array( $this, 'remove_theme_css_and_scripts' ), 100 );
			}
		}


		public function remove_theme_css_and_scripts() {
			global $wp_scripts, $wp_styles;

			/* Unwanted folder for dequeue css and js */
			$us = array( '/astra-addon/', 'astra-' );

			$registered_script = $wp_scripts->registered;
			if ( ! empty( $registered_script ) ) {

				foreach ( $registered_script as $handle => $data ) {

					$src = is_string( $data->src ) ? $data->src : '';
					if ( false !== strpos( $src, $us[0] ) || ( false !== strpos( $src, $us[1] ) ) ) {

						unset( $wp_scripts->registered[ $handle ] );
						wp_dequeue_script( $handle );
					}
				}
			}

			$registered_style = $wp_styles->registered;

			if ( ! empty( $registered_style ) ) {
				foreach ( $registered_style as $handle => $data ) {

					$src             = is_string( $data->src ) ? $data->src : '';
					$is_astra_src    = ( false !== strpos( $src, $us[0] ) || false !== strpos( $src, $us[1] ) );
					$is_astra_handle = ( false !== strpos( (string) $handle, 'astra' ) || false !== strpos( (string) $handle, 'ast-' ) );

					/* Dequeue file-based Astra stylesheets and inline-only Astra handles (src === false). */
					if ( $is_astra_src || ( '' === $src && $is_astra_handle ) ) {

						unset( $wp_styles->registered[ $handle ] );
						wp_dequeue_style( $handle );
						continue;
					}

					/* Astra ships its dark-mode custom properties as inline CSS attached to the core theme handle; drop that inline block. */
					if ( $is_astra_handle && isset( $data->extra['after'] ) ) {
						unset( $wp_styles->registered[ $handle ]->extra['after'] );
					}
				}
			}
		}

		public function remove_dark_mode_body_class( $classes ) {
			if ( ! is_array( $classes ) ) {
				return $classes;
			}
			if ( ! class_exists( 'WFACP_Common' ) || absint( WFACP_Common::get_id() ) <= 0 ) {
				return $classes;
			}
			foreach ( $classes as $index => $class ) {
				if ( false !== strpos( $class, 'ast-dark-mode' ) || ( false !== strpos( $class, 'ast-' ) && false !== strpos( $class, 'dark' ) ) ) {
					unset( $classes[ $index ] );
				}
			}

			return array_values( $classes );
		}

		public function internal_css() {

			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}

			$bodyClass = 'body ';
			if ( 'pre_built' !== $instance->get_template_type() ) {
				$bodyClass = 'body #wfacp-e-form ';
			}

			$cssHtml  = '<style>';
			$cssHtml .= 'body{overflow-x: visible;}';
			$cssHtml .= '.ast-separate-container .ast-article-post {padding: 0 !important;}';
			$cssHtml .= $bodyClass . '.wfacp_main_form .woocommerce-checkout #payment ul.payment_methods li .form-row.form-row-last{clear: none;}';
			$cssHtml .= $bodyClass . '.wfacp_main_form .woocommerce-checkout #payment ul.payment_methods li .form-row.form-row-first{clear: none;}';
			$cssHtml .= $bodyClass . '.woocommerce-page form .form-row-quart-first, .woocommerce form .form-row-quart-first {margin-right: 0 !important;}';
			$cssHtml .= $bodyClass . '.wfacp_main_form .woocommerce-form-coupon-toggle{display: block;}';
			$cssHtml .= 'body:not(.cartflows-canvas):not(.cartflows-default) .woocommerce form .form-row label:not(.checkbox):not(.woocommerce-form__label-for-checkbox){opacity: inherit;max-width: 100%;position: relative;margin: 0;padding: 0;white-space: inherit;line-height: 1.5;}';
			$cssHtml .= 'body:not(.cartflows-canvas):not(.cartflows-default) .woocommerce form .form-row .select2-container--default .select2-selection--single .select2-selection__arrow b{display:initial;}';

			/*
			 * Neutralize Astra's global dark-mode palette on the FunnelKit checkout.
			 * Astra emits --ast-global-color-* !important vars plus white color rules that otherwise win
			 * the cascade and paint the form white-on-white. Reset the vars and reclaim readable colors,
			 * scoped tightly to the checkout wrapper so nothing leaks site-wide.
			 */
			$scope = trim( $bodyClass ) . ' .wfacp_main_form';

			$var_reset = '';
			for ( $i = 0; $i <= 8; $i++ ) {
				$var_reset .= '--ast-global-color-' . $i . ':initial !important;';
			}
			// Astra uses NAMED palette vars (-primary / -secondary) as the PRIMARY
			// value in var(), with the numbered ones only as the fallback — e.g.
			// background-color:var(--ast-global-color-secondary,--ast-global-color-5).
			// Resetting only 0..8 is bypassed because -secondary still holds a dark
			// value, so the focus background stayed dark. Reset the named vars too.
			$var_reset .= '--ast-global-color-primary:initial !important;--ast-global-color-secondary:initial !important;';
			// Reset the vars across the ENTIRE checkout container AND the coupon
			// box (which renders in the order summary, outside .wfacp_main_form).
			// This is what actually kills Astra's dark palette on the coupon field
			// in every state — the :focus background is var-driven, so a plain
			// white reclaim wasn't enough on its own.
			$cssHtml .= trim( $bodyClass ) . '{' . $var_reset . '}';
			$cssHtml .= '.wfacp_coupon_field_box,.wfacp_coupon_field_box .wfacp-form-control-wrapper{' . $var_reset . '}';
			$cssHtml .= $scope . '{' . $var_reset . '}';

			/*
			 * Form controls — base + :hover + :focus so the field never flips to
			 * Astra's dark hover/focus palette (the coupon field went black on
			 * hover). A broad `input` selector (minus non-text types) covers the
			 * coupon field and any custom field without listing each one.
			 */
			// Scope form controls to the WHOLE checkout container (form + order
			// summary), not just .wfacp_main_form — the coupon field lives in the
			// order summary and was being missed (stayed dark).
			$field_scope  = trim( $bodyClass );
			$fk_fields    = array(
				'input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="image"]):not([type="hidden"])',
				'textarea',
				'select',
				'.select2-selection',
				'.select2-selection__rendered',
			);
			$fk_field_sel = array();
			foreach ( $fk_fields as $fk_f ) {
				foreach ( array( '', ':hover', ':focus' ) as $fk_st ) {
					$fk_field_sel[] = $field_scope . ' ' . $fk_f . $fk_st;
				}
			}
			// Coupon input targeted by id (high specificity, belt-and-braces) — it
			// renders in the order summary and can sit outside #wfacp-e-form.
			foreach ( array( '', ':hover', ':focus' ) as $fk_st ) {
				$fk_field_sel[] = '#wfacp_coupon_code_field' . $fk_st;
			}
			$cssHtml .= implode( ',', $fk_field_sel ) . '{background-color:#ffffff !important;color:#2c2f36 !important;}';
			$cssHtml .= $field_scope . ' ::placeholder,#wfacp_coupon_code_field::placeholder{color:#6b7280 !important;}';

			// Directly neutralize Astra's dark :hover/:focus input rule by mirroring
			// its OWN selector (`.astra-dark-mode-enable input[type=...]:focus` etc.)
			// verbatim — equal specificity, printed later, so it wins the cascade no
			// matter where the field sits (coupon in the summary included). Only
			// matches when Astra dark mode is on; this <style> only prints on the
			// checkout, so it can't leak to other pages.
			$ast_dm     = '.astra-dark-mode-enable';
			$ast_inputs = array(
				'input[type=email]',
				'input[type=number]',
				'input[type=password]',
				'input[type=reset]',
				'input[type=search]',
				'input[type=tel]',
				'input[type=text]',
				'input[type=url]',
			);
			$ast_sel    = array();
			foreach ( array( ':hover', ':focus' ) as $ast_st ) {
				foreach ( $ast_inputs as $ast_i ) {
					$ast_sel[] = $ast_dm . ' ' . $ast_i . $ast_st;
				}
				// Astra scopes textarea via `form .form-row` (higher specificity) —
				// mirror both so we match it whichever wins.
				$ast_sel[] = $ast_dm . ' form .form-row textarea' . $ast_st;
				$ast_sel[] = $ast_dm . ' textarea' . $ast_st;
			}
			$cssHtml .= implode( ',', $ast_sel ) . '{background-color:#ffffff !important;border-color:#dcdcde !important;color:#2c2f36 !important;}';

			/*
			 * Select2 dropdowns are appended to <body> (outside the form), so the
			 * scoped rules above can't reach them. Target them globally — safe
			 * because this <style> only prints on the checkout, so it can't leak
			 * to other pages' Select2.
			 */
			$cssHtml .= '.select2-container .select2-dropdown,.select2-container .select2-results,.select2-container .select2-results__option,.select2-container .select2-search__field{background-color:#ffffff !important;color:#2c2f36 !important;}';
			$cssHtml .= '.select2-container .select2-results__option--highlighted,.select2-container .select2-results__option[aria-selected="true"]{background-color:#2271b1 !important;color:#ffffff !important;}';

			$cssHtml .= $scope . ' label,' . $scope . ' .form-row label,' . $scope . ' .wfacp-section-title,' . $scope . ' .wfacp_heading,' . $scope . ' h1,' . $scope . ' h2,' . $scope . ' h3,' . $scope . ' h4,' . $scope . ' h5,' . $scope . ' h6,' . $scope . ' p{color:#2c2f36 !important;}';

			/* Shipping section specifically — same dark-mode cascade lands here. */
			$cssHtml .= $scope . ' .wfacp_shipping_table,' . $scope . ' .wfacp_shipping_table th,' . $scope . ' .wfacp_shipping_table td,' . $scope . ' .wfacp_border,' . $scope . ' label.label_shiping,' . $scope . ' #shipping_method,' . $scope . ' #shipping_method label,' . $scope . ' .shipping_method,' . $scope . ' .shipping_method label{color:#2c2f36 !important;}';
			$cssHtml .= $scope . ' .wfacp_shipping_table{background-color:transparent !important;}';

			$cssHtml .= '</style>';
			echo $cssHtml; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		public function remove_script() {
			if ( ! class_exists( 'WFACP_Common' ) || WFACP_Common::get_id() == 0 ) {
				return;
			}
			?>
			<script>
				if (typeof modernLayoutInputs == "function") {
					function modernLayoutInputs() {

					}
				}

			</script>
			<?php
		}
	}


	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Astra(), 'Astra' );
}
