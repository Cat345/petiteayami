<?php
if ( ! class_exists( 'WFOCU_Gutenberg' ) ) {
	class WFOCU_Gutenberg {
		private static $ins = null;
		public $modules_instance = [];
		protected $module_path = [];
		private $post = null;
		protected $widgets_json = [];
		private $url = '';

		private function __construct() {
			$this->module_path = __DIR__ . '/modules/';
			$this->url         = plugin_dir_url( __FILE__ );
			$this->define_constant();
			$this->register();

		}

		private function define_constant() {

			! defined( 'WFOCU_GUTENBURG_DIR' ) && define( 'WFOCU_GUTENBURG_DIR', plugin_dir_path( __FILE__ ) );
			! defined( 'WFOCU_GUTENBURG_URL' ) && define( 'WFOCU_GUTENBURG_URL', plugin_dir_url( __FILE__ ) );
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;

		}


		private function register() {
			add_action( 'init', [ $this, 'init_extension' ], 21 );
			add_action( 'plugins_loaded', [ $this, 'load_require_files' ], 21 );
			if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
				add_filter( 'block_categories_all', array( $this, 'add_category' ), 11, 2 );
			} else {
				add_filter( 'block_categories', array( $this, 'add_category' ), 11, 2 );
			}
			add_filter( 'admin_body_class', [ $this, 'bwf_blocks_admin_body_class' ] );

		}

		/**
		 * Add custom category
		 *
		 * @param array $categories category list.
		 * @param WP_Post $post post object.
		 */
		public function add_category( $categories ) {
			if ( false !== array_search( 'woofunnels', array_column( $categories, 'slug' ) ) ) {
				return $categories;
			} else {
				return array_merge( array(
					array(
						'slug'  => 'woofunnels',
						'title' => esc_html__( 'FunnelKit', 'woofunnels-upstroke-one-click-upsell' ),
					),
				), $categories );
			}
		}

		public function load_require_files() {
			//load necessary files
			if ( ! is_admin() ) {
				require_once __DIR__ . '/includes/functions.php';
				require_once __DIR__ . '/includes/class-bwf-blocks-css.php';
				require_once __DIR__ . '/includes/class-bwf-blocks-frontend-css.php';
				require_once __DIR__ . '/includes/class-render-blocks.php';
			}
		}

		/**
		 * Load assets for wp-admin when editor is active.
		 */
		public function admin_script_style() {

			global $pagenow, $post;

			if ( WFOCU_Common::get_offer_post_type_slug() === $post->post_type && 'post.php' === $pagenow && isset( $_GET['post'] ) && intval( $_GET['post'] ) > 0 ) { //phpcs:ignore

				defined( 'BWF_I18N' ) || define( 'BWF_I18N', 'woofunnels-guten-block' );
				$app_name     = 'upstrokeadmin';
				$frontend_dir = defined( 'BWF_UPSELL_REACT_ENVIRONMENT' ) ? BWF_UPSELL_REACT_ENVIRONMENT : $this->url . '/dist/';

				$assets_path = $frontend_dir . "/$app_name.asset.php";
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
						'jquery',
					),
					'version'      => time(),
				);

				$js_path    = '/upstrokeadmin.js';
				$style_path = '/upstrokeadmin.css';

				$deps    = ( isset( $assets['dependencies'] ) ? array_merge( $assets['dependencies'], array( 'jquery' ) ) : array( 'jquery' ) );
				$version = $assets['version'];

				$script_deps = array_filter( $deps, function ( $dep ) {
					return ! is_null( $dep ) && false === strpos( $dep, 'css' );
				} );

				wp_enqueue_script( 'wfocu-gutenberg-script', $frontend_dir . $js_path, $script_deps, $version, true );


				wp_enqueue_style( 'wfocu-default', $frontend_dir . $style_path, array(), $version );

				$system_font_path = __DIR__ . '/font/standard-fonts.php';

				wp_enqueue_script( 'web-font', 'https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js', array(), true );

				wp_enqueue_script( 'bwf-font-awesome-kit', 'https://kit.fontawesome.com/f4306c3ab0.js', // Our free kit https://fontawesome.com/kits/f4306c3ab0/settings
					null, null, true );


				wp_enqueue_style( 'bwf-fonts', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' );

				$offer_id             = WFOCU_Core()->template_loader->get_offer_id();
				$offer_settings       = get_post_meta( $offer_id, '_wfocu_setting', true );
				$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
				$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;
				$code_key             = ( ! empty( $offer_settings ) && isset( $offer_settings->products ) && count( get_object_vars( $offer_settings->products ) ) < 2 ) ? 'single' : 'multi';
				$shortcodes           = WFOCU_Core()->admin->get_shortcodes_list();

				$personalize_shortcodes = array_map( function ( $shortcode ) use ( $code_key ) {
					$shortcode['code'] = sprintf( $shortcode['code'][ $code_key ], 1 );

					return $shortcode;
				}, $shortcodes );

				$other_shortcodes = [
					[ 'label' => __( 'Customer First Name' ), 'code' => '[wfocu_order_data key="customer_first_name"]' ],
					[ 'label' => __( 'Customer Last Name' ), 'code' => '[wfocu_order_data key="customer_last_name"]' ],
					[ 'label' => __( 'Order Number' ), 'code' => '[wfocu_order_data key="order_no"]' ],
					[ 'label' => __( 'Order Date' ), 'code' => '[wfocu_order_data key="order_date"]' ],
					[ 'label' => __( 'Order Total' ), 'code' => '[wfocu_order_data key="order_total"]' ],
					[ 'label' => __( 'Order Item Count' ), 'code' => '[wfocu_order_data key="order_itemscount"]' ],
					[ 'label' => __( 'Order Shipping Method' ), 'code' => '[wfocu_order_data key="order_shipping_method"]' ],
					[ 'label' => __( 'Order Billing Country' ), 'code' => '[wfocu_order_data key="order_billing_country"]' ],
					[ 'label' => __( 'Order Shipping Country' ), 'code' => '[wfocu_order_data key="order_shipping_country"]' ],
					[ 'label' => __( 'Order Custom Meta' ), 'code' => '[wfocu_order_data key=""]' ],
				];

				wp_localize_script( 'wfocu-gutenberg-script', 'bwf_funnels_data', [
					'products'           => WFOCU_Guten_Field::get_product_lists(),
					'post_id'            => $post->ID,
					'i18n'               => BWF_I18N,
					'currency'           => html_entity_decode( get_woocommerce_currency_symbol() ),
					'qty_enabled'        => $qty_selector_enabled,
					'bwf_g_fonts'        => bwf_get_fonts_list( 'all' ),
					'bwf_g_font_names'   => bwf_get_fonts_list( 'name_only' ),
					'system_font_path'   => file_exists( $system_font_path ) ? include $system_font_path : array(),
					'product_shortcodes' => $personalize_shortcodes,
					'other_shortcodes'   => $other_shortcodes,
					'wp_version'         => $GLOBALS['wp_version'],
				] );
				wp_enqueue_script( 'bwf-jquery.flexslider', plugins_url( 'assets/js/flexslider/jquery.flexslider.min.js', WC_PLUGIN_FILE ), array(), WC_VERSION );

			}
		}

		public function init_extension() {

			$post_id = 0;
			if ( isset( $_REQUEST['post'] ) && $_REQUEST['post'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['post'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else if ( isset( $_REQUEST['bwf_post_id'] ) && $_REQUEST['bwf_post_id'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_REQUEST['bwf_post_id'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$post = get_post( $post_id );
			if ( ! is_null( $post ) && $post->post_type === WFOCU_Common::get_offer_post_type_slug() ) {

				$this->post = $post;
				$this->prepare_module();

				return;
			}

			add_action( 'wp', [ $this, 'prepare_frontend_module' ], - 5 );


		}

		public function prepare_frontend_module() {
			global $post;
			if ( is_null( $post ) ) {
				return;
			}
			$this->post = $post;


			if ( $post->post_type === WFOCU_Common::get_offer_post_type_slug() ) {

				$design = WFOCU_Core()->offers->get_offer( $post->ID, false );

				if ( isset( $design->template_group ) && 'gutenberg' !== $design->template_group ) {
					return;
				}
				add_filter( 'wfocu_valid_state_for_data_setup', '__return_true' );
				WFOCU_Core()->template_loader->offer_id = $post->ID;
				WFOCU_Core()->template_loader->setup_complete_offer_setup_manual( $post->ID );
				if ( current_action() === 'wp' ) {

					$this->register_scripts();
				}

			}

			$this->prepare_module();
		}

		public function prepare_module() {
			if ( is_null( $this->post ) ) {
				return;
			}

			$id = $this->post->ID;

			$design = WFOCU_Common::get_offer( $id );

			if ( empty( $design ) || empty( $design->template_group ) ) {
				return;
			}


			if ( 'gutenberg' !== $design->template_group ) {
				return;
			}

			register_post_meta( '', 'bwfblock_default_font', array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			) );


			add_action( 'enqueue_block_editor_assets', [ $this, 'admin_script_style' ] );
			$modules = $this->get_modules();
			if ( ! empty( $modules ) ) {

				include __DIR__ . '/class-abstract-wfocu-fields.php';

				foreach ( $modules as $module ) {
					if ( ! file_exists( $module['path'] ) ) {

						continue;
					}

					/**
					 * @var $widget_instance WFOCU_Guten_Field
					 */
					$widget_instance                        = include $module['path'];
					$widget_slug                            = $widget_instance->slug();
					$this->modules_instance[ $widget_slug ] = $widget_instance;
				}
			}
		}

		private function register_scripts() {
			$style = WFOCU_Core()->assets->get_styles();

			wp_enqueue_style( 'flickity', $style['flickity']['path'] );
			wp_enqueue_style( 'flickity-common', $style['flickity-common']['path'] );
			defined( 'BWF_I18N' ) || define( 'BWF_I18N', 'woofunnels-guten-block' );

			$app_name = 'upstrokepublic';

			$frontend_dir = defined( 'BWF_UPSELL_REACT_ENVIRONMENT' ) ? BWF_UPSELL_REACT_ENVIRONMENT : $this->url . 'dist';

			$version    = time();
			$style_path = "/$app_name.css";

			wp_enqueue_style( 'wfocu-gutenberg-style', $frontend_dir . $style_path, array(), $version );

			// load block font family
			require_once( __DIR__ . '/font/fonts.php' );

		}

		private function get_modules() {
			$modules = [
				'accept_button' => [
					'name' => __( 'WF Accept Button', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'accept-button.php',
				],
				'reject_button' => [
					'name' => __( 'WF Reject Button', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'reject-button.php',
				],
				'accept_link'   => [
					'name' => __( 'WF Accept Link', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'accept-link.php',
				],
				'reject_link'   => [
					'name' => __( 'WF Reject Link', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'reject-link.php',
				],
				'product_title' => [
					'name' => __( 'WF Product Title', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-title.php',
				],

				'product_images'     => [
					'name' => __( 'WF Product Images', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-images.php',
				],
				'product_short_desc' => [
					'name' => __( 'WF Product Short Description', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-short-desc.php',
				],
				'variation_selector' => [
					'name' => __( 'WF Variation Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'variation-selector.php',
				],
				'qty_selector'       => [
					'name' => __( 'WF Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'qty-selector.php',
				],
				'offer_price'        => [
					'name' => __( 'WF Offer Price', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'offer-price.php',
				],

			];

			return apply_filters( 'wfocu_gutenberg_modules', $modules, $this );
		}


		public function bwf_blocks_admin_body_class( $classes ) {
			$screen = get_current_screen();
			if ( 'post' === $screen->base && WFOCU_Common::get_offer_post_type_slug() === $screen->post_type ) {
				global $post;
				$template_file = get_post_meta( $post->ID, '_wp_page_template', true );
				if ( 'wfocu-canvas.php' === $template_file ) {
					$classes .= ' bwf-editor-width-canvas';
				}
				if ( 'wfocu-boxed.php' === $template_file ) {
					$classes .= ' bwf-editor-width-boxed';
				}

			}

			return $classes;

		}

		public function bwf_render_default_font() {
			global $post;
			$default_font = get_post_meta( $post->ID, 'bwfblock_default_font', true );

			if ( ! empty( $default_font ) ) {
				echo "<style id='bwfblock-default-font'>#editor .editor-styles-wrapper { font-family:$default_font; }</style>"; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		public function allow_theme_css( $is ) {

			$get_offer_meta = WFOCU_Core()->data->get( '_current_offer_data' );
			if ( ! empty( $get_offer_meta->template_group ) && $get_offer_meta->template_group === 'gutenberg' ) {
				return true;
			}

			return $is;
		}

	}

	WFOCU_Gutenberg::get_instance();
}
