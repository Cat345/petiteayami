<?php
/**
 * Plugin Name: SlingBlocks – Gutenberg Blocks by FunnelKit (Formerly WooFunnels)
 * Description: A minimalist Gutenberg Block Plugin that extends Gutenberg to provide page building capabilities.
 * Version: 1.8.1
 * Text Domain: slingblocks
 * Plugin URI: https://funnelkit.com/
 * Author: FunnelKit (formerly WooFunnels)
 * Author URI: https://funnelkit.com
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 *
 * @package slingblocks
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SLINGBLOCKS' ) ) {

	/**
	 * SLINGBLOCKS
	 */
	class SLINGBLOCKS {

		const DEFAULT_FONT_MAX_LENGTH = 100;

		/**
		 * __construct
		 *
		 * @return void
		 */
		public function __construct() {
			$this->define_constant();
			add_action( 'plugins_loaded', array( $this, 'plugin_loaded' ) );
		}

		/**
		 * Define Constant
		 *
		 * @return void
		 */
		public function define_constant() {
			defined( 'SLINGBLOCKS_PLUGIN_VERSION' ) || define( 'SLINGBLOCKS_PLUGIN_VERSION', time() );
			defined( 'SLINGBLOCKS_PLUGIN_FILE' ) || define( 'SLINGBLOCKS_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
			defined( 'SLINGBLOCKS_ABSPATH_URL' ) || define( 'SLINGBLOCKS_ABSPATH_URL', plugin_dir_url( __FILE__ ) );
			defined( 'SLINGBLOCKS_I18N' ) || define( 'SLINGBLOCKS_I18N', 'slingblocks' );
			( defined( 'SLINGBLOCKS_IS_DEV' ) && true === SLINGBLOCKS_IS_DEV ) ? define( 'SLINGBLOCKS_VERSION', time() ) : define( 'SLINGBLOCKS_VERSION', SLINGBLOCKS_PLUGIN_VERSION );
		}

		/**
		 * Plugin Loaded
		 *
		 * @return void
		 */
		public function plugin_loaded() {
			$this->register_block_categories();
			$this->load_require_files();

			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
			add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_styles' ) );
			add_action( 'init', array( $this, 'init_loaded' ), 1 );
			add_filter( 'admin_body_class', array( $this, 'slb_blocks_admin_body_class' ) );

			/** Add theme classes for compatibility detection. */
			add_action( 'body_class', array( $this, 'bwf_body_class_theme_compatibility' ) );
			if ( ! is_admin() ) {
				add_action( 'wp_head', array( $this, 'load_page_template_style' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts_front' ), 9999 );
				add_filter( 'template_include', array( $this, 'wp_template_include' ), 11 /* After Plugins/WooCommerce */ );
				require_once plugin_dir_path( __FILE__ ) . 'font/fonts.php';
			}

			/** Load compatibilities */
			$this->load_all_compatibilities();
		}

		public function bwf_body_class_theme_compatibility( $classes ) {
			if ( defined( 'ASTRA_THEME_VERSION' ) ) {
				$classes[] = 'slingblocks--is-astra-theme';
			} elseif ( class_exists( 'Blocksy_Translations_Manager' ) ) {
				$classes[] = 'slingblocks--is-blocksy-theme';
			} elseif ( defined( 'NEVE_VERSION' ) ) {
				$classes[] = 'slingblocks--is-neve-theme';
			} elseif ( defined( 'KADENCE_VERSION' ) ) {
				$classes[] = 'slingblocks--is-kadence-theme';
			} elseif ( class_exists( 'Storefront' ) ) {
				$classes[] = 'slingblocks--is-storefront-theme';
			}

			return $classes;
		}

		public function load_require_files() {
			// load necessary files
			require_once SLINGBLOCKS_PLUGIN_FILE . 'includes/functions.php';
			require_once SLINGBLOCKS_PLUGIN_FILE . 'includes/class-slingblocks-css.php';
			require_once SLINGBLOCKS_PLUGIN_FILE . 'includes/class-slingblocks-frontend-css.php';
			require_once SLINGBLOCKS_PLUGIN_FILE . 'includes/class-render-blocks.php';
		}

		public function wp_template_include( $template ) {
			if ( ! is_singular() ) {
				return $template;
			}

			if ( 'slb-template-boxed' === get_page_template_slug() ) {
				return __DIR__ . '/templates/slb-template-boxed.php';
			}
			if ( 'slb-template-canvas' === get_page_template_slug() ) {
				return __DIR__ . '/templates/slb-template-canvas.php';
			}

			return $template;
		}

		public function init_loaded() {
			// Register Gutenberg Block Meta for default font
			register_post_meta(
				'',
				'bwfblock_default_font',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sanitize_default_font' ),
					'auth_callback'     => array( $this, 'auth_default_font_meta' ),
				)
			);

			// Add Custom Template
			add_filter( 'theme_post_templates', array( $this, 'wp_add_page_templates' ) );
			add_filter( 'theme_page_templates', array( $this, 'wp_add_page_templates' ) );
		}

		/**
		 * Restrict default-font meta to a safe font-name shape so the value
		 * cannot break out of the CSS `font-family:` declaration or inject
		 * extra query parameters into the Google Fonts URL.
		 */
		public function sanitize_default_font( $value ) {
			if ( ! is_scalar( $value ) ) {
				return '';
			}
			$value = preg_replace( '/[^A-Za-z0-9 ,-]/', '', trim( (string) $value ) );
			return mb_substr( $value, 0, self::DEFAULT_FONT_MAX_LENGTH );
		}

		/**
		 * Gate REST writes to the default-font meta to users who can edit the post.
		 */
		public function auth_default_font_meta( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		}

		// Add class in editor body
		public function slb_blocks_admin_body_class( $classes ) {
			$screen = get_current_screen();
			if ( 'post' == $screen->base ) {
				global $post;
				$template_file   = get_post_meta( $post->ID, '_wp_page_template', true );
				$template_canvas = array(
					'slb-template-canvas', // slingblock canvas
					'wflp-canvas.php', // funnel landing canvas
					'wfoty-canvas.php', // funnel Optin thankyou canvas
				);
				$template_boxed  = array(
					'slb-template-boxed', // slingblock boxed
					'wflp-boxed.php', // funnel landing boxed
					'wfoty-boxed.php', // funnel Optin thankyou boxed
				);

				if ( in_array( $template_file, $template_canvas ) ) {
					$classes .= ' slb-editor-width-canvas';
				}
				if ( in_array( $template_file, $template_boxed ) ) {
					$classes .= ' slb-editor-width-boxed';
				}
			}

			return $classes;
		}

		public function wp_add_page_templates( $page_templates ) {
			$slb_templates = array(
				'slb-template-boxed'  => esc_html__( 'Sling Block Boxed', SLINGBLOCKS_I18N ),
				'slb-template-canvas' => esc_html__( 'Sling Block Canvas', SLINGBLOCKS_I18N ),
			);

			return array_merge( $page_templates, $slb_templates );
		}

		public function register_block_categories() {
			if ( version_compare( $GLOBALS['wp_version'], '5.8-alpha-1', '<' ) ) {
				add_filter( 'block_categories', array( $this, 'add_block_categories' ), 10, 2 );

				return;
			}
			add_filter( 'block_categories_all', array( $this, 'add_block_categories' ), 10, 2 );
		}

		/**
		 * Add Block Category.
		 *
		 * @param array $categories Block categories.
		 *
		 * @return array updated categories.
		 */
		public function add_block_categories( $categories ) {
			return array_merge(
				array(
					array(
						'slug'  => 'slingblocks',
						'title' => esc_html__( 'SlingBlocks', SLINGBLOCKS_I18N ),
					),
				),
				$categories
			);
		}

		/**
		 * Enqueue assets for block.
		 */
		public function enqueue_block_editor_assets() {
			$app_name    = 'slingblocks-editor';
			$editor_dir  = ( defined( 'SLINGBLOCKS_REACT_ENVIRONMENT' ) && 0 == SLINGBLOCKS_REACT_ENVIRONMENT ) ? SLINGBLOCKS_REACT_DEV_URL : plugin_dir_url( __FILE__ ) . 'dist';
			$assets_path = defined( 'SLINGBLOCKS_REACT_ENVIRONMENT' ) && 1 === SLINGBLOCKS_REACT_ENVIRONMENT ? plugin_dir_url( __FILE__ ) . "dist/$app_name.asset.php" : __DIR__ . "/dist/$app_name.asset.php";
			$assets      = file_exists( $assets_path ) ? include $assets_path : array(
				'dependencies' => array(
					'wp-plugins',
					'wp-element',
					'wp-edit-post',
					'wp-i18n',
					'wp-api-request',
					'wp-data',
					'wp-hooks',
					'wp-plugins',
					'wp-components',
					'wp-blocks',
					'wp-editor',
					'wp-compose',
					'lodash',
				),
				'version'      => SLINGBLOCKS_VERSION,
			);

			$js_path    = "/$app_name.js";
			$style_path = "/$app_name.css";
			$deps       = ( isset( $assets['dependencies'] ) ? array_merge( $assets['dependencies'], array( 'jquery' ) ) : array( 'jquery' ) );
			$deps       = array_merge( $deps, array( 'fontawesome-shims-js', 'fontawesome-svg-js' ) );
			$version    = $assets['version'];

			$script_deps = array_filter(
				$deps,
				function ( $dep ) {
					return ! is_null( $dep ) && false === strpos( $dep, 'css' );
				}
			);

			wp_enqueue_script(
				'fontawesome-shims-js',
				plugin_dir_url( __FILE__ ) . 'font/fontawesome-v4-shims.min.js', // mapping between fa-v4 and fa-v5 naming
				null,
				null,
				true
			);
			wp_enqueue_script(
				'fontawesome-svg-js',
				plugin_dir_url( __FILE__ ) . 'font/fontawesome.min.js', // get fontawesome svg from js by FontAwesome.icon object
				null,
				null,
				true
			);

			wp_enqueue_script( 'slingblocks-editor', $editor_dir . $js_path, $script_deps, $version, true );
			$slingblocksfonts_path       = __DIR__ . '/font/gfonts-array.php';
			$slingblocksfont_names_path  = __DIR__ . '/font/gfonts-names-array.php';
			$slingblockssystem_font_path = __DIR__ . '/font/standard-fonts.php';
			wp_enqueue_script( 'web-font', plugin_dir_url( __FILE__ ) . 'font/webfont.js', array(), true );
			wp_localize_script(
				'slingblocks-editor',
				'slingblocks',
				array(
					'i18n'                        => SLINGBLOCKS_I18N,
					'slingblocks_g_fonts'         => file_exists( $slingblocksfonts_path ) ? include $slingblocksfonts_path : array(),
					'slingblocks_g_font_names'    => file_exists( $slingblocksfont_names_path ) ? include $slingblocksfont_names_path : array(),
					'slingblockssystem_font_path' => file_exists( $slingblockssystem_font_path ) ? include $slingblockssystem_font_path : array(),
					'wp_version'                  => $GLOBALS['wp_version'],
				)
			);
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'slingblocks-editor', SLINGBLOCKS_I18N );
			}
		}

		/**
		 * Enqueue editor styles via enqueue_block_assets so they load
		 * properly inside the iframe editor (API v3 / WordPress 6.3+).
		 */
		public function enqueue_block_editor_styles() {
			if ( ! is_admin() ) {
				return;
			}

			$app_name   = 'slingblocks-editor';
			$editor_dir = ( defined( 'SLINGBLOCKS_REACT_ENVIRONMENT' ) && 0 == SLINGBLOCKS_REACT_ENVIRONMENT ) ? SLINGBLOCKS_REACT_DEV_URL : plugin_dir_url( __FILE__ ) . 'dist';
			$style_path = "/$app_name.css";

			wp_enqueue_style( 'slingblocks-editor', $editor_dir . $style_path, array(), SLINGBLOCKS_VERSION );

			/**
			 * default : 980px
			 */
			$tmpt_boxed_width = apply_filters( 'slb_template_boxed_width', '' );
			if ( ! empty( $tmpt_boxed_width ) ) {
				$boxed_tmpt_width = "body.slb-editor-width-boxed .editor-post-title__block, body.slb-editor-width-boxed .editor-default-block-appender, body.slb-editor-width-boxed .block-editor-block-list__block, body.slb-editor-width-boxed .block-editor-default-block-appender, body.slb-editor-width-boxed .wp-block{ max-width: $tmpt_boxed_width !important}";
				wp_add_inline_style( 'slingblocks-editor', $boxed_tmpt_width );
			}

			/**
			 * Default font for the editor — injected as inline style so it works
			 * inside the iframe editor (WordPress 6.3+).
			 */
			global $post;
			if ( $post instanceof WP_Post ) {
				$default_font = get_post_meta( $post->ID, 'bwfblock_default_font', true );
				if ( ! empty( $default_font ) ) {
					$font_css = '.editor-styles-wrapper, .editor-styles-wrapper p, .editor-styles-wrapper h1, .editor-styles-wrapper h2, .editor-styles-wrapper h3, .editor-styles-wrapper h4, .editor-styles-wrapper h5, .editor-styles-wrapper h6, .editor-styles-wrapper ul, .editor-styles-wrapper li { font-family:' . esc_html( $default_font ) . ' }';
					wp_add_inline_style( 'slingblocks-editor', $font_css );

					// Enqueue Google Fonts stylesheet — this gets baked into the iframe's
					// __unstableResolvedAssets so it persists without JS re-injection.
					$system_fonts = file_exists( __DIR__ . '/font/standard-fonts.php' ) ? include __DIR__ . '/font/standard-fonts.php' : array();
					$is_system    = false;
					foreach ( $system_fonts as $sf ) {
						if ( isset( $sf['value'] ) && $sf['value'] === $default_font ) {
							$is_system = true;
							break;
						}
					}
					if ( ! $is_system ) {
						$font_param = str_replace( ' ', '+', $default_font );
						wp_enqueue_style( 'slingblocks-gfont-default', 'https://fonts.googleapis.com/css?family=' . $font_param . '&display=swap', array(), null );
					}
				}
			}
		}

		/**
		 * Enqueue Front Style.
		 */
		public function wp_enqueue_scripts_front() {
			// Check if page is is_singular
			if ( ! is_singular() ) {
				return;
			}

			// return if slingblocks is not available in post content
			if ( empty( SLINGBLOCKS_Frontend_CSS::$slingblocks ) ) {
				return;
			}

			// Enqueue our plugin Css.
			$slingblocks_front_dir = ( defined( 'SLINGBLOCKS_REACT_ENVIRONMENT' ) && 0 == SLINGBLOCKS_REACT_ENVIRONMENT ) ? SLINGBLOCKS_REACT_DEV_URL : plugin_dir_url( __FILE__ ) . 'dist';

			$stylesheet_file = '/slingblocks.css';
			$script_file     = '/slingblocks.js';

			wp_enqueue_style( 'slingblocks', $slingblocks_front_dir . $stylesheet_file, array(), SLINGBLOCKS_VERSION );
			wp_enqueue_script( 'slingblocks', $slingblocks_front_dir . $script_file, array( 'jquery' ), SLINGBLOCKS_VERSION, true );

			if ( in_array( 'sling-block/countdown', SLINGBLOCKS_Frontend_CSS::$slingblocks ) ) {
				wp_enqueue_script( 'slingblocks-countdown-js', $slingblocks_front_dir . '/slingblocks-countdown.js', array(), SLINGBLOCKS_VERSION, true );
			}
		}

		/**
		 * Load slingblock template inline style
		 */
		public function load_page_template_style() {
			// Check if page is is_singular
			if ( ! is_singular() ) {
				return;
			}

			$page_template = get_page_template_slug();
			if ( '' === $page_template || ! ( 'slb-template-canvas' === $page_template || 'slb-template-boxed' === $page_template ) ) {
				return;
			}

			$template_style = '.slb-boxed{max-width:1200px;width:100%;margin:0 auto}.slb-container:not(.slb-boxed)>*{padding-left:15px;padding-right:15px}@media screen and (max-width:1220px){.slb-boxed>*{padding-left:15px;padding-right:15px}}';

			$tmpt_boxed_width = apply_filters( 'slb_template_boxed_width', '' ); // default is 1200px
			if ( ! empty( $tmpt_boxed_width ) ) {
				$template_style .= ".slb-container.slb-boxed{max-width:$tmpt_boxed_width}";
			}

			wp_register_style( 'slingblocks-template', false );
			wp_enqueue_style( 'slingblocks-template' );
			wp_add_inline_style( 'slingblocks-template', $template_style );
		}

		public function load_all_compatibilities() {
			/** Compatibilities folder */
			$dir = plugin_dir_path( __FILE__ ) . 'compatibilities';

			/** Plugins loaded hook */
			foreach ( glob( $dir . '/plugins_loaded/class-*.php' ) as $_field_filename ) {
				require_once $_field_filename;
			}

			/** After Setup theme hook */
			add_action(
				'after_setup_theme',
				function () use ( $dir ) {
					foreach ( glob( $dir . '/after_setup_theme/class-*.php' ) as $_field_filename ) {
						require_once $_field_filename;
					}
				}
			);
		}
	}

	new SLINGBLOCKS();
}
