<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- legacy filename kept for back-compat
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * This class is a mail loader class for dashboard page , controls and sets up all the necessary actions
 *
 * @author woofunnels
 * @package WooFunnels
 */


define( 'BWF_VERSION', '1.10.12.84' );
define( 'BWF_DB_VERSION', '1.0.7' );

/** Kernel: classmap autoloader + request-context detection + eager-load policy */
require_once __DIR__ . '/class-bwf-kernel.php';

/**
 * Legacy folder (dashboard UI, its tab views, license glue) removed in the WooFunnels
 * dismantle. The WooFunnels_Dashboard_UI / bwf_legacy_* shims below stay guarded by
 * class_exists()/function_exists() and simply no-op now that the folder is gone.
 */

if ( ! class_exists( 'WooFunnels_Dashboard' ) ) {
	#[AllowDynamicProperties]
	class WooFunnels_Dashboard {

		public static $currentPage;
		public static $parent;
		public static $selected = '';
		public static $pagefullurl = '';
		public static $is_dashboard_page = false;
		public static $loader_url = '';
		public static $is_core_menu = false;
		public static $classes = [];
		protected static $expectedurl;
		protected static $expectedslug;


		/**
		 * Dashboard-page UI (WooFunnels_Dashboard_UI) lived in the legacy/ folder,
		 * removed in the WooFunnels dismantle. These shims stay class_exists-guarded
		 * to keep this class's public surface intact for consumers; they now no-op.
		 */
		public static function load_page() {
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				WooFunnels_Dashboard_UI::load_page();
			}
		}

		public static function register_dashboard() {
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				WooFunnels_Dashboard_UI::register_dashboard();
			}
		}

		public static function woofunnels_dashboard_scripts() {
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				WooFunnels_Dashboard_UI::woofunnels_dashboard_scripts();
			}
		}

		/**
		 * Init function hooked on `admin_init`
		 * Set the required variables and register some important hooks
		 */
		public static function init() {


			self::$loader_url = WooFunnel_Loader::$ultimate_path;
			$selected         = null !== sanitize_text_field( filter_input( INPUT_GET, 'tab', FILTER_DEFAULT ) ) ? sanitize_text_field( filter_input( INPUT_GET, 'tab', FILTER_DEFAULT ) ) : 'plugins'; // phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter

			self::$selected = $selected;

			/**
			 * Function to trigger error message at WordPress plugins page when we have update but license invalid
			 */
			self::add_notice_unlicensed_product();

			/**
			 * Initialize Localization.
			 * Called directly: this runs on admin_init, which fires after init,
			 * so hooking init here never executed (textdomain silently never loaded).
			 */
			self::localization();


			add_action( 'admin_head', [ __CLASS__, 'apply_scroll_fix_css' ] );
		}

		/**
		 * Getting and parsing all our licensing products and checking if there update is available
		 */
		public static function add_notice_unlicensed_product() {
			/** Implementation lives in legacy-license-module.php (excluded from the free build) */
			if ( function_exists( 'bwf_legacy_add_notice_unlicensed_product' ) ) {
				bwf_legacy_add_notice_unlicensed_product();
			}
		}

		public static function localization() {
			/**
			 * No textdomain load here any more. These strings now live under the
			 * host plugin's domain so WordPress.org can expose them to translators
			 * -- GlotPress only builds a project for the plugin slug, so anything
			 * under a separate domain was untranslatable through the official
			 * channel. WordPress loads the plugin's own language pack just in time.
			 */
		}

		/**
		 * Message displayed if license not activated. <br/>
		 *
		 * @param array $plugin_data
		 * @param object $r
		 *
		 * @return void
		 */
		public static function need_license_message( $plugin_data, $r ) {
			if ( empty( $r->package ) ) {
				echo wp_kses_post( '<div class="woofunnels-updater-plugin-upgrade-notice">' . __( 'To enable this update please activate your FunnelKit license by visiting the Dashboard Page.', 'funnel-builder' ) . '</div>' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			}
		}


		public static function woofunnels_licenses_data() {
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				return WooFunnels_Dashboard_UI::woofunnels_licenses_data();
			}
		}

		public static function woofunnels_tools_data() {
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				return WooFunnels_Dashboard_UI::woofunnels_tools_data();
			}
		}

		public static function woofunnels_support_data( $data ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				return WooFunnels_Dashboard_UI::woofunnels_support_data( $data );
			}

			return $data;
		}

		public static function woofunnels_logs_data( $data ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				return WooFunnels_Dashboard_UI::woofunnels_logs_data( $data );
			}

			return $data;
		}

		public static function show_right_area() {
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				WooFunnels_Dashboard_UI::show_right_area();
			}
		}

		public static function add_logs_tabs( $tabs ) {
			if ( class_exists( 'WooFunnels_Dashboard_UI' ) ) {
				return WooFunnels_Dashboard_UI::add_logs_tabs( $tabs );
			}

			return $tabs;
		}


		public static function autoloader( $class_name ) {
			if ( 0 === strpos( $class_name, 'WooFunnels_' ) || 0 === strpos( $class_name, 'BWF_' ) ) {
				$path          = WooFunnel_Loader::$ultimate_path . 'includes/class-' . self::slugify_classname( $class_name ) . '.php';
				$usage_path    = WooFunnel_Loader::$ultimate_path . 'usage-tracking/class-' . self::slugify_classname( $class_name ) . '.php';
				$contact_path  = WooFunnel_Loader::$ultimate_path . 'contact/class-' . self::slugify_classname( $class_name ) . '.php';
				$abstract_path = WooFunnel_Loader::$ultimate_path . 'usage-tracking/abstracts/class-' . self::slugify_classname( $class_name ) . '.php';

				if ( is_file( $path ) ) {
					require_once $path;
				}
				if ( is_file( $usage_path ) ) {
					require_once $usage_path;
				}
				if ( is_file( $contact_path ) ) {
					require_once $contact_path;
				}
				if ( is_file( $abstract_path ) ) {
					require_once $abstract_path;
				}

				/**
				 * loading compatibility classes
				 */
				if ( false !== strpos( $class_name, 'Compatibility' ) || false !== strpos( $class_name, 'Compatibilities' ) ) {
					$path = WooFunnel_Loader::$ultimate_path . 'compatibilities/class-' . self::slugify_classname( $class_name ) . '.php';

					if ( is_file( $path ) ) {
						require_once $path;
					}

				}
			}
			if ( 0 === strpos( $class_name, 'WFCO_' ) ) {
				$model_path = WooFunnel_Loader::$ultimate_path . 'includes/class-' . self::slugify_classname( $class_name ) . '.php';

				if ( is_file( $model_path ) ) {
					require_once $model_path;
				}
			}
		}

		/**
		 * Slug-ify the class name and remove underscores and convert it to filename
		 * Helper function for the auto-loading
		 *
		 * @param $class_name
		 *
		 *
		 * @return mixed|string
		 * @see WooFunnels_Dashboard::autoloader();
		 *
		 */
		public static function slugify_classname( $class_name ) {
			$classname = self::custom_sanitize_title( $class_name );
			$classname = str_replace( '_', '-', $classname );


			return $classname;
		}

		/**
		 * Custom sanitize title method to avoid conflicts with WordPress hooks on sanitize_title
		 *
		 * @param string $title The title to sanitize
		 * @return string The sanitized title
		 */
		private static function custom_sanitize_title( $title ) {
			$title = remove_accents( $title );
			$title = sanitize_title_with_dashes( $title );

			return $title;
		}

		public static function load_core_classes() {
			require dirname( __DIR__ ) . '/includes/bwf-functions.php';
			// Customer wrappers merged into woofunnels-contact-functions.php — one fewer frontend file load.
			require dirname( __DIR__ ) . '/contact/woofunnels-contact-functions.php';
			/**
			 * Batch-indexing wrappers (bwf_create_update_contact_customer / bwf_contacts_v1_0_init_db_setup)
			 * are only ever invoked by the background updater — pushed to its queue and executed in the
			 * background admin-ajax / cron / worker request (is_backend_context). Never called on a plain
			 * frontend page view, so skip the require there.
			 */
			if ( BWF_Kernel::is_backend_context() ) {
				require dirname( __DIR__ ) . '/contact/woofunnels-db-updater-functions.php';
			}

			// Tracking classes will be autoloaded on demand via autoloader
			// No need to load them here

			/** Context-gated lists — see BWF_Kernel; skipped classes stay reachable via autoloader */
			foreach ( BWF_Kernel::get_eager_classes() as $class ) {
				self::$classes[ $class ] = $class::get_instance();
			}

			BWF_Kernel::wire_lazy_classes();

			foreach ( BWF_Kernel::get_static_init_classes() as $class ) {
				$class::init();
			}


			add_action( 'shutdown', [ __CLASS__, 'fetch_template_json' ] );
			add_action( 'admin_init', array( __CLASS__, 'index_templates' ) );
		}

		public static function apply_scroll_fix_css() {
			global $current_screen;
			if ( $current_screen instanceof WP_Screen && strpos( $current_screen->base, 'woofunnels' ) !== false ) {
				/** Allow for woofunnels pages */
			} elseif ( false === apply_filters( 'bwf_scroll_fix_allow', false ) ) {
				return;
			}
			ob_start();
			?>
			<style>
                #adminmenuwrap {
                    position: relative !important;
                    top: 0 !important;
                }

                body, html {
                    height: auto;
                    position: relative
                }
			</style>
			<?php
			echo ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}


		public static function index_templates() {
			$get_templates = filter_input( INPUT_GET, 'wffn_parse_template', FILTER_DEFAULT ); // phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter

			if ( 'yes' === $get_templates && current_user_can( 'manage_options' ) ) {
				self::get_all_templates( true );
				wp_safe_redirect( admin_url( 'admin.php?page=woofunnels&tab=tools' ) );
				exit;
			}
		}

		public static function get_all_templates( $force = false ) {
			$woofunnels_cache_object = WooFunnels_Cache::get_instance();
			$get_transient_instance  = WooFunnels_Transient::get_instance();
			$transient               = $woofunnels_cache_object->get_cache( '_bwf_fb_templates' );

			if ( empty( $transient ) ) {
				$transient = $get_transient_instance->get_transient( '_bwf_fb_templates' );
			}

			if ( empty( $transient ) || true === $force ) {
				$templates = self::get_remote_templates();
				if ( is_array( $templates ) ) {
					$get_transient_instance->set_transient( '_bwf_fb_templates', count( $templates ), HOUR_IN_SECONDS * 12 );
				}

				return $templates;
			}

			return apply_filters( 'bwf_fb_templates', get_option( '_bwf_fb_templates' ) );
		}

		public static function get_remote_templates() {
			$json_templates = [];
			$endpoint_url   = self::get_template_api_url();
			$request_args   = array(
				'timeout'   => 30, //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
				'sslverify' => false
			);
			$response       = wp_safe_remote_get( $endpoint_url . 'templatesv3.json', $request_args );
			BWF_Logger::get_instance()->log( 'API Call templates.json response::: ' . print_r( $response, true ), 'wffn_template_import' ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			if ( ! is_wp_error( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
				if ( ! empty( $body ) ) {
					$json_templates = json_decode( $body, true );
					if ( is_array( $json_templates ) ) {
						update_option( '_bwf_fb_templates', $json_templates, false );
					}
				}
			}

			return $json_templates;
		}

		public static function get_template_api_url() {
			return 'https://gettemplates.funnelkit.com/';
		}

		public static function fetch_template_json() {
			if ( 'yes' === filter_input( INPUT_GET, 'activated', FILTER_DEFAULT ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter
				flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
				self::get_all_templates();
			}
		}


	}
}
spl_autoload_register( array( 'WooFunnels_dashboard', 'autoloader' ) );
WooFunnels_dashboard::load_core_classes();

//Initialize the instance so that some necessary hooks can run on each load
add_action( 'admin_init', array( 'WooFunnels_dashboard', 'init' ), 11 );
