<?php
if ( ! class_exists( 'WFOCU_Assets_Loader' ) ) {
	class WFOCU_Assets_Loader {

		private static $ins = null;
		public $environment = 'customizer-preview';
		private $scripts = array();

		private $styles = array();

		public function __construct() {
			add_action( 'wp', [ $this, 'maybe_register_assets_on_load' ] );
			add_filter( 'bwf_general_settings_default_config', [ $this, 'migrate_modify_allowed_theme_settings' ], 99, 1 );

		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function localize_script( $handle, $object, $data ) {
			if ( ! isset( $this->scripts[ $handle ] ) ) {
				return;
			}

			if ( ! isset( $this->scripts[ $handle ]['data'] ) ) {
				$this->scripts[ $handle ]['data'] = array();
			}

			$this->scripts[ $handle ]['data'][ $object ] = $data;
		}

		public function setup_assets( $environment ) {
			$get_scripts       = $this->get_scripts();
			$this->environment = $environment;
			foreach ( $get_scripts as $handle => $scripts ) {

				if ( in_array( $environment, $scripts['supports'], true ) ) {

					$this->add_scripts( $handle, $scripts['path'], $scripts['version'], $scripts['in_footer'] );
				}
			}

			$get_styles = $this->get_styles();

			foreach ( $get_styles as $handle => $styles ) {

				if ( in_array( $environment, $styles['supports'], true ) ) {
					$this->add_styles( $handle, $styles['path'], $styles['version'], $styles['in_footer'] );
				}
			}

		}

		public function get_scripts() {

			$live_or_dev = 'live';

			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
				$live_or_dev = 'dev';
				$suffix      = '';
			} else {
				$suffix = '.min';
			}

			return apply_filters( 'wfocu_assets_scripts', array(
				'jquery'     => array(
					'path'      => includes_url() . 'js/jquery/jquery.js',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-page',
						'offer-single',
					),
				),
				'underscore' => array(
					'path'      => includes_url() . 'js/underscore.min.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-page',
						'offer-single',
					),
				),
				'wp-util'    => array(
					'path'      => includes_url() . 'js/wp-util.min.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-page',
						'offer-single',
					),
				),

				'accounting' => array(
					'path'      => WC()->plugin_url() . '/assets/js/accounting/accounting.min.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'offer',
						'offer-page',
						'customizer-preview',
						'offer-single',
					),
				),

				'flickity' => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/flickity/flickity.pkgd.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-single',
						'offer-page'
					),
				),

				'wfocu-product'               => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/' . $live_or_dev . '/js/wfocu-product' . $suffix . '.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-page',
						'offer-single',
					),
				),
				'wfocu-jquery-countdown'      => array(
					'path' => WFOCU_PLUGIN_URL . '/assets/' . $live_or_dev . '/js/jquery.countdown.min.js',

					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-single',
					),
				),
				'wfocu-polyfill'              => array(
					'path'      => WFOCU_PLUGIN_URL . '/admin/assets/js/wfocu-polyfill.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'offer',
						'offer-page',
						'offer-single',
					),
				),
				'wfocu-swal'                  => array(
					'path'      => WFOCU_PLUGIN_URL . '/admin/assets/js/wfocu-sweetalert.min.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'offer',
						'offer-page',
						'offer-single',
					),
				),
				'wfocu-global'                => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/' . $live_or_dev . '/js/wfocu-public' . $suffix . '.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'offer',
						'offer-page',
						'customizer-preview',
						'offer-single',
					),
				),
				'customize-base'              => array(
					'path'      => includes_url() . 'js/customize-base.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',

					),
				),
				'customize-preview'           => array(
					'path'      => includes_url() . 'js/customize-preview.min.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
					),
				),
				'wfocu_customizer_live'       => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/' . $live_or_dev . '/js/customizer' . $suffix . '.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
					),
				),
				'customize-selective-refresh' => array(
					'path'      => includes_url() . 'js/customize-selective-refresh.min.js',
					'version'   => null,
					'in_footer' => true,
					'supports'  => array(
						'customizer',
						'customizer-preview',
					),
				),
			) );
		}

		public function add_scripts( $handle, $src, $version = null, $is_footer = false ) {
			if ( isset( $this->scripts[ $handle ] ) ) {
				return;
			}
			$this->scripts[ $handle ] = array(
				'src'     => $src,
				'version' => ( is_null( $version ) ) ? WFOCU_VERSION_DEV : $version,
				'foot'    => $is_footer,
			);
		}

		public function get_styles() {

			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {

				$suffix = '';
			} else {
				$suffix = '.min';
			}

			return apply_filters( 'wfocu_assets_styles', array(
				'wfocu-grid-css'               => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/css/grid.min.css',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
					),
				),
				'wfocu-global-css'             => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/css/style' . $suffix . '.css',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
					),
				),
				'wfocu-offer-confirmation-css' => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/css/style-offer-confirmation' . $suffix . '.css',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-page',
						'offer-single',
					),
				),
				'flickity'                     => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/flickity/flickity.css',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-single',
					),
				),
				'flickity-common'              => array(
					'path'      => WFOCU_PLUGIN_URL . '/assets/css/flickity-common.css',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(),
				),
				'customize-preview'            => array(
					'path'      => includes_url() . 'css/customize-preview.min.css',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(
						'customizer',
						'customizer-preview',
					),
				),
				'dashicons'                    => array(
					'path'      => includes_url() . 'css/dashicons.min.css',
					'version'   => null,
					'in_footer' => false,
					'supports'  => array(
						'customizer',
						'customizer-preview',
						'offer',
						'offer-page',
						'offer-single',
					),
				),

			) );
		}

		public function add_styles( $handle, $src, $version = null, $is_footer = false ) {
			if ( isset( $this->styles[ $handle ] ) ) {
				return;
			}

			$this->styles[ $handle ] = array(
				'src'     => $src,
				'version' => ( is_null( $version ) ) ? WFOCU_VERSION_DEV : $version,
				'foot'    => $is_footer,
			);

		}

		public function print_scripts( $is_head = false ) {
			$script = '';

			if ( true === $is_head ) {
				foreach ( $this->scripts as $handle => $data ) {
					if ( false === $data['foot'] ) {
						if ( isset( $data['data'] ) ) {
							foreach ( $data['data'] as $var => $data_loc ) {
								$script .= 'var ' . $var . ' = ' . wp_json_encode( $data_loc ) . ';';
							}
							printf( ' <script type="text/javascript" >%s</script>', $script ); // phpcs:ignore WordPress.Security.EscapeOutput
						}

						/**
						 * Ensuring if script has been enqueued already
						 */
						if ( true === apply_filters( 'wfocu_should_render_script_' . $handle, true ) ) {
							printf( ' <!--suppress ALL -->
<script type="text/javascript" id="%s" src="%s"></script>', esc_attr( 'script_' . $handle ), esc_url( $data['src'] . '?v=' . $data['version'] ) );//phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

							unset( $this->scripts[ $handle ] );
						}
					}
				}
			} else {

				foreach ( $this->scripts as $handle => $data ) {
					$script = '';
					if ( isset( $data['data'] ) ) {
						foreach ( $data['data'] as $var => $data_loc ) {
							$script .= 'var ' . $var . ' = ' . wp_json_encode( $data_loc ) . ';';
						}
						printf( ' <script type="text/javascript" >%s</script>', $script ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					/**
					 * Ensuring if script has been enqueued already
					 */
					if ( true === apply_filters( 'wfocu_should_render_script_' . $handle, true ) ) {
						printf( ' <script type="text/javascript" id="%s" src="%s"></script>', esc_attr( 'script_' . $handle ), esc_url( $data['src'] . '?v=' . $data['version'] ) ); //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

						unset( $this->scripts[ $handle ] );
					}
				}
			}
		}

		public function print_styles( $is_head = false ) {
			if ( true === $is_head ) {
				foreach ( $this->styles as $handle => $data ) {
					if ( false === $data['foot'] ) {
						printf( ' <link rel="stylesheet" type="text/css" media="all" id="%s" href="%s"/>', esc_attr( 'style_' . $handle ), esc_attr( $data['src'] . '?v=' . $data['version'] ) ); //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
						unset( $this->styles[ $handle ] );
					}
				}
			} else {
				foreach ( $this->styles as $handle => $data ) {
					printf( ' <link rel="stylesheet" type="text/css" media="all" id="%s" href="%s"/>', esc_attr( 'style_' . $handle ), esc_attr( $data['src'] . '?v=' . $data['version'] ) ); //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
					unset( $this->styles[ $handle ] );
				}
			}
			do_action( 'wfocu_print_all_styles' );
		}

		public function maybe_register_assets_on_load() {
			global $post;


			$canvas_template = WFOCU_Common::get_canvas_template();
			$boxed_template  = WFOCU_Common::get_boxed_template();
			$page_template   = isset( $post->ID ) ? get_post_meta( $post->ID, '_wp_page_template', true ) : '';

			if ( $post instanceof WP_Post && 'wfocu_offer' === $post->post_type ) {
				$should_register = apply_filters( 'wfocu_should_register_assets', true );

				if ( true === $should_register ) {
					$this->maybe_register_assets( [], '', true );
				}

			if ( ( $canvas_template === $page_template ) || ( $boxed_template === $page_template ) ) {
				if ( $this->maybe_theme_script_enable( $post ) ) {

					/**
					 * handle case for boxed template and manage width
					 */
					if ( $boxed_template === $page_template ) {
						add_action( 'wp_enqueue_scripts', array( $this, 'wfocu_add_upsell_frontend_styles' ), 30 );
					}

					return;
				}
				add_action( 'wp_enqueue_scripts', array( $this, 'wfocu_remove_conflicted_themes_styles' ), 9999 );
				add_action( 'wp_enqueue_scripts', array( $this, 'wfocu_add_upsell_frontend_styles' ), 30 );
			}
		}
	}

		public function maybe_register_assets( $handles = [], $environment = '', $force_environment = false ) {

			$this->maybe_register_styles( $handles, $environment, $force_environment );
			$this->maybe_register_scripts( $handles, $environment, $force_environment );

		}

		public function maybe_register_styles( $handles = [], $environment = '', $force_environment = false ) {

			$styles = $this->get_styles();

			foreach ( $styles as $handle => $style ) {

				if ( ! empty( $handles ) && false === in_array( $handle, $handles, true ) ) {
					continue;
				}

				if ( false === $force_environment && ! empty( $environment ) && false === in_array( $environment, $style['supports'], true ) ) {
					continue;
				}

				wp_register_style( $handle, $style['path'], [], $style['version'] );
			}

		}

		public function maybe_register_scripts( $handles = [], $environment = '', $force_environment = false ) {
			$scripts = $this->get_scripts();

			foreach ( $scripts as $handle => $script ) {
				if ( ! empty( $handles ) && false === in_array( $handle, $handles, true ) ) {
					continue;
				}

				if ( false === $force_environment && ! empty( $environment ) && false === in_array( $environment, $script['supports'], true ) ) {
					continue;
				}
				wp_register_script( $handle, $script['path'], [], $script['version'], $script['in_footer'] );
			}
		}

		public function wfocu_add_upsell_frontend_styles() {
			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {

				$suffix = '';
			} else {
				$suffix = '.min';
			}
			wp_enqueue_style( 'wfocu-upsell-fontend', WFOCU_PLUGIN_URL . '/assets/css/frontend' . $suffix . '.css', array(), WFOCU_VERSION );
		}

		public function wfocu_remove_conflicted_themes_styles() {

		//globally registered styles and scripts
		global $wp_styles;
		global $wp_scripts;

			$get_stylesheet = 'themes/' . get_stylesheet() . '/';
			$get_template   = 'themes/' . get_template() . '/';

		if ( 'flatsome' === get_template() ) {
			remove_action( 'wp_head', 'flatsome_custom_css', 100 );
		}

		wp_enqueue_style( 'dashicons' );

			// Dequeue and deregister all of the registered styles
			foreach ( $wp_styles->registered as $handle => $data ) {

				if ( false !== strpos( $data->src, $get_template ) || false !== strpos( $data->src, $get_stylesheet ) ) {
					wp_deregister_style( $handle );
					wp_dequeue_style( $handle );
				}
			}

			// Dequeue and deregister all of the registered scripts
			foreach ( $wp_scripts->registered as $handle => $data ) {
				if ( false !== strpos( $data->src, $get_stylesheet ) || false !== strpos( $data->src, $get_template ) ) {
					wp_deregister_script( $handle );
					wp_dequeue_script( $handle );
				}
			}
			if ( 'bb-theme' === get_template() && class_exists( 'FLCustomizer' ) ) {
				wp_dequeue_style( 'fl-automator-skin', FLCustomizer::css_url(), array(), FL_THEME_VERSION );
			}
			if ( 'oceanwp' === strtolower( get_template() ) ) {
				$enqu_fa = apply_filters( 'wfocu_enqueue_fa_style', true );
				if ( $enqu_fa ) {
					wp_enqueue_style( 'wfocu-font-awesome', OCEANWP_CSS_DIR_URI . 'third/font-awesome.min.css', false );
				}
			}
			if ( 'porto' === strtolower( get_template() ) ) {
				wp_deregister_script( 'porto-shortcodes' );
				wp_deregister_script( 'porto-bootstrap' );
				wp_deregister_script( 'porto-dynamic-style' );
				wp_dequeue_style( 'porto-shortcodes' );
				wp_dequeue_style( 'porto-bootstrap' );
				wp_dequeue_style( 'porto-dynamic-style' );
				if ( is_rtl() ) { //font-awesome css is written in this css in porto theme
					wp_register_style( 'porto-plugins', PORTO_URI . '/css/plugins_rtl.css?ver=' . PORTO_VERSION );
				} else {
					wp_register_style( 'porto-plugins', PORTO_URI . '/css/plugins.css?ver=' . PORTO_VERSION );
				}
				wp_enqueue_style( 'porto-plugins' );
			}
		}


	public function maybe_theme_script_enable( $post ) {
		if ( empty( $post ) ) {
			return false;
		}
		if ( class_exists( 'BWF_Admin_General_Settings' ) ) {
			$allowed_steps = BWF_Admin_General_Settings::get_instance()->get_option( 'allow_theme_css' );
			if ( ( is_array( $allowed_steps ) && in_array( $post->post_type, $allowed_steps, true ) ) || $this->maybe_save_allowed_theme_settings() ) {
				add_filter( 'wfocu_allow_externals_on_customizer', '__return_true' );

				return true;
			}
		}

		return false;

	}

	/**
	 * @param $args
	 *
	 * @return mixed
	 */
	public function migrate_modify_allowed_theme_settings( $args ) {
		$db_options = get_option( 'bwf_gen_config', [] );

			if ( ! empty( $db_options ) && ! empty( $db_options['allow_theme_css'] ) ) {
				return $args;
			}

			if ( ! isset( $args['allow_theme_css'] ) ) {
				$args['allow_theme_css'] = [];
			}

			/**
			 * Allow default theme script if user use any snippet
			 */
			if ( true === apply_filters( 'wfocu_allow_themes_css', false, $this ) ) {
				$args['allow_theme_css'][] = 'wfocu_offer';

				return $args;
			}

			return $args;
		}

		/**
		 * Save allow theme script settings
		 * And it's a one time process
		 * @return bool
		 */
		public function maybe_save_allowed_theme_settings() {

			$is_updated = false;
			$db_options = get_option( 'bwf_gen_config', [] );


			/**
			 * check if db options  contains allow_theme_css key, then no need to update any settings, we must respect custom choice here
			 */
			if ( ! empty( $db_options ) && isset( $db_options['allow_theme_css'] ) ) {
				return $is_updated;
			}


			/**
			 * Allow default theme script if user use any snippet
			 */
			$allowed_themes = apply_filters( 'wffn_allowed_themes', [ 'flatsome', 'Extra', 'divi', 'Divi', 'astra', 'jupiterx', 'kadence' ] );

			$allowed_for_upsells_themes = apply_filters( 'wfocu_allowed_themes', [ 'flatsome', 'Extra', 'divi', 'Divi', 'jupiterx', 'kadence' ] );

			$general_settings = BWF_Admin_General_Settings::get_instance();

			if ( function_exists( 'WFFN_Core' ) && ( in_array( get_template(), $allowed_themes, true ) || WFFN_Core()->page_builders->is_divi_theme_enabled() ) ) {
				$db_options['allow_theme_css'] = array(
					'wfacp_checkout',
					'wffn_ty',
					'wffn_landing',
					'wffn_optin',
					'wffn_oty'
				);

				$is_updated = true;
			}
			if ( function_exists( 'WFFN_Core' ) && ( in_array( get_template(), $allowed_for_upsells_themes, true ) || WFFN_Core()->page_builders->is_divi_theme_enabled() ) ) {
				if ( ! empty( $db_options['allow_theme_css'] ) ) {
					$db_options['allow_theme_css'][] = 'wfocu_offer';
				} else {
					$db_options['allow_theme_css'] = array(
						'wfocu_offer',
					);
				}


				$is_updated = true;
			}

			if ( $is_updated ) {
				$general_settings->update_global_settings_fields( $db_options );

			}

			return $is_updated;

		}
	}


	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'assets', 'WFOCU_Assets_Loader' );
	}
}