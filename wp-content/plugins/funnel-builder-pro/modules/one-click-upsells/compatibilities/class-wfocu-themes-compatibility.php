<?php
if ( ! class_exists( 'WFOCU_Plugins_Compatibility' ) ) {
	class WFOCU_Plugins_Compatibility {

		public function __construct() {
			add_action( 'wfocu_loaded', array( $this, 'register_fake_kirki' ) );

			add_action( 'customize_controls_enqueue_scripts', array( $this, 'override_theme_customizer_functionality' ), 999 );
			add_action( 'customize_controls_enqueue_scripts', array( $this, 'override_theme_customizer_js_code' ), 999 );

			/**
			 * Customizer compatibility for buzzstorepro theme
			 */
			if ( function_exists( 'WFOCU_Core' ) && is_object( WFOCU_Core()->template_loader ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {
			add_action( 'after_setup_theme', function () {


					remove_action( 'customize_register', 'buzzstorepro_customize_register' );

					if ( function_exists( 'generate_default_fonts_customize_register' ) ) {
						remove_action( 'customize_register', 'generate_default_fonts_customize_register' );
					}

                } );
			}
			/**
			 * Customizer compatibility for Easy Google Fonts plugin
			 */
			add_action( 'wfocu_loaded', function () {

				if ( function_exists( 'WFOCU_Core' ) && is_object( WFOCU_Core()->template_loader ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {

					if ( class_exists( 'EGF_Customize_Manager' ) ) {
						remove_action( 'customize_register', array( EGF_Customize_Manager::get_instance(), 'register_font_control_type' ) );

					}
				}
			}, 9999 );

			/**
			 * Customizer compatibility for Google Fonts for WordPress  */
			add_action( 'wfocu_loaded', function () {

				if ( function_exists( 'WFOCU_Core' ) && is_object( WFOCU_Core()->template_loader ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {

					if ( function_exists( 'ogf_customize_register' ) ) {

						remove_action( 'customize_register', 'ogf_customize_register' );

					}
				}
			}, 9999 );

			add_action( 'wp_loaded', function () {

				if ( class_exists( 'MK_Customizer' ) && is_object( WFOCU_Core()->template_loader ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {
					global $wp_filter;
					foreach ( $wp_filter['customize_register']->callbacks as $key => $val ) {

						if ( 10 !== $key ) {
							continue;
						}

						foreach ( $val as $innerval ) {
							if ( isset( $innerval['function'] ) && is_array( $innerval['function'] ) ) {
								if ( is_a( $innerval['function']['0'], 'MK_Customizer' ) ) {
									$mk_customizer = $innerval['function']['0'];
									remove_action( 'customize_register', array( $mk_customizer, 'register_settings' ) );
									break;
								}
							}
						}
					}
				}
			}, 0 );


			/**
			 * Customizer compatibility with the 'porto' theme, prevent theme control to load on our customizer pages
			 */
			add_action( 'wp_loaded', function () {

				/**
				 * Check if theme class exists
				 */
				if ( class_exists( 'VI_WOOCOMMERCE_THANK_YOU_PAGE_Admin_Design' ) && is_object( WFOCU_Core()->template_loader ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {
					global $wp_filter;
					foreach ( $wp_filter['customize_controls_print_scripts']->callbacks as $key => $val ) {

						if ( 99 !== $key ) {
							continue;
						}

						foreach ( $val as $innerval ) {
							if ( isset( $innerval['function'] ) && is_array( $innerval['function'] ) ) {
								if ( is_a( $innerval['function']['0'], 'VI_WOOCOMMERCE_THANK_YOU_PAGE_Admin_Design' ) ) {
									$class = $innerval['function']['0'];
									remove_action( 'customize_controls_print_scripts', array( $class, 'customize_controls_print_scripts' ), 99 );
									break;
								}
							}
						}
					}
				}
			}, 0 );


			add_filter( 'do_rocket_lazyload', function ( $should_load ) {

				if ( 0 < did_action( 'setup_complete_offer_setup_manual' ) && ( function_exists( 'WFOCU_Core' ) && WFOCU_Core()->public->if_is_offer() ) ) {
					return false;
				}

				return $should_load;
			} );


			/**
			 * Provide lazy loading compatibility with autoptimize plugin
			 */
			add_action( 'wfocu_header_print_in_head', function () {
				add_filter( 'autoptimize_filter_imgopt_should_lazyload', '__return_false', 999 );
			} );

			if ( WFOCU_Common::is_load_admin_assets( 'builder' ) ) {
                add_action( 'admin_print_styles', function () {
                        ?>
                        <style> #query-monitor-main {
                                display: none;
                            }</style>
                        <?php
                    } );
            }

			/**
			 * infusion admin css conflict resolution
			 */
			if ( WFOCU_Core()->admin->is_upstroke_page() ) {

				add_action( 'admin_enqueue_scripts', function () {
					wp_dequeue_style( 'infusion-admin-css' );

				} );
			}
		}

		public function register_fake_kirki() {
			$status = apply_filters( 'wfocu_customizer_i10_error', false );
			if ( false === $status || ! is_object( WFOCU_Core()->template_loader ) ) {
				return;
			}
			$is_wfocu_customizer = WFOCU_Core()->template_loader->is_customizer_preview();
			if ( false === $is_wfocu_customizer ) {
				return;
			}
			include_once __DIR__ . '/class-fake-kirki.php';
			add_action( 'customize_controls_init', array( $this, 'remove_actions_filters' ) );
		}

		public function remove_actions_filters() {
			$is_wfocu_customizer = WFOCU_Core()->template_loader->is_customizer_preview();
			if ( false === $is_wfocu_customizer ) {
				return;
			}
			remove_action( 'customize_controls_print_styles', 'flatsome_enqueue_customizer_stylesheet' );
		}

		public function override_theme_customizer_functionality() {

			if ( ! is_object( WFOCU_Core()->template_loader ) ) {
				return;
			}
			$is_wfocu_customizer = WFOCU_Core()->template_loader->is_customizer_preview();
			if ( false === $is_wfocu_customizer ) {
				return;
			}

			/** Astra */
			if ( defined( 'ASTRA_THEME_VERSION' ) ) {
				wp_dequeue_script( 'astra-color-alpha' );
				wp_dequeue_script( 'astra-responsive-slider' );
				wp_dequeue_style( 'astra-responsive-slider' );
				wp_dequeue_style( 'astra-responsive-css' );
			}
		}

		public function override_theme_customizer_js_code() {
			if ( ! is_object( WFOCU_Core()->template_loader ) ) {
				return;
			}
			$is_wfocu_customizer = WFOCU_Core()->template_loader->is_customizer_preview();
			if ( false === $is_wfocu_customizer ) {
				return;
			}

			if ( class_exists( 'Astra_Customizer' ) ) {
				$astra_inst = Astra_Customizer::get_instance();
				remove_action( 'customize_controls_print_footer_scripts', array( $astra_inst, 'print_footer_scripts' ) );
			}
		}
	}

	new WFOCU_Plugins_Compatibility();
}