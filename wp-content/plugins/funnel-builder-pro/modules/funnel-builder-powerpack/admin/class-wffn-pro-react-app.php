<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'WFFN_Pro_React_App' ) ) {
	/**
	 * Serves the admin React app from the powerpack bundle.
	 *
	 * Hooks admin_enqueue_scripts before the free plugin (priority 5 vs 99),
	 * removes WFFN_Admin::admin_enqueue_assets and enqueues the pro bundle
	 * under the same handle and mount, replicating the free enqueue flow —
	 * the same way Sublium Pro replaces the Sublium app enqueue.
	 *
	 * If the compiled bundle is missing (source checkout) the free plugin's
	 * enqueue is left untouched and keeps serving its own bundle.
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_React_App {

		private static $ins = null;

		public function __construct() {
			
		}

		/**
		 * @return WFFN_Pro_React_App|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Packaged builds rename the bundle to main-<timestamp>.* and stamp
		 * the placeholder below (build.sh); source checkouts resolve to
		 * plain 'main'.
		 */
		public function get_app_name() {
			$app_name = 'main-20260820134040'; //phpcs:ignore WordPressVIPMinimum.Security.Mustache.OutputNotation

			return str_replace( '-{{{APP_VERSION}}}', '', $app_name ); //phpcs:ignore WordPressVIPMinimum.Security.Mustache.OutputNotation
		}

		/**
		 * Dev mode uses the same constants the free plugin uses (the way
		 * Sublium Pro shares SUBLIUM_WCS_WC_REACT_ENV): point
		 * WFFN_REACT_DEV_URL at this app's dev server when working on it.
		 */
		public function is_dev_mode() {
			return defined( 'WFFN_REACT_ENVIRONMENT' ) && 0 === WFFN_REACT_ENVIRONMENT && defined( 'WFFN_REACT_DEV_URL' );
		}

		public function get_build_dir() {
			return WFFN_PRO_PLUGIN_DIR . '/admin/app/dist/';
		}

		public function get_languages_dir() {
			return WFFN_PRO_PLUGIN_DIR . '/languages';
		}

		public function get_app_dir() {
			if ( $this->is_dev_mode() ) {
				return trailingslashit( WFFN_REACT_DEV_URL );
			}

			return WFFN_PRO_PLUGIN_URL . '/admin/app/dist/';
		}

		/**
		 * Runs before the free plugin's enqueue (priority 5 vs 99): removes
		 * its admin assets action and registers the pro enqueue in its place.
		 */
		public function maybe_prevent_lite_and_enqueue_pro() {
			if ( ! function_exists( 'WFFN_Core' ) || ! is_object( WFFN_Core()->admin ) ) {
				return;
			}
			$lite_admin = WFFN_Core()->admin;

			if ( ! method_exists( $lite_admin, 'is_wffn_flex_page' ) || ! method_exists( $lite_admin, 'get_license_config' ) ) {
				return;
			}

			/** Source checkout without a compiled bundle: keep the free enqueue */
			if ( ! $this->is_dev_mode() && ! file_exists( $this->get_build_dir() . $this->get_app_name() . '.js' ) ) {
				return;
			}

			remove_action( 'admin_enqueue_scripts', array( $lite_admin, 'admin_enqueue_assets' ), 99 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pro_scripts' ), 99 );
		}

		/**
		 * Replaces WFFN_Admin::admin_enqueue_assets, serving the app from the
		 * pro bundle.
		 *
		 * @param string $hook_suffix Current admin page hook suffix.
		 */
		public function enqueue_pro_scripts( $hook_suffix ) {
			$lite_admin = WFFN_Core()->admin;

			wp_enqueue_style( 'bwf-admin-font', $lite_admin->get_admin_url() . '/assets/css/bwf-admin-font.css', array(), WFFN_VERSION_DEV );

			if ( strpos( $hook_suffix, 'woofunnels_page' ) > - 1 || strpos( $hook_suffix, 'page_woofunnels' ) > - 1 ) {
				wp_enqueue_style( 'bwf-admin-header', $lite_admin->get_admin_url() . '/assets/css/admin-global-header.css', array(), WFFN_VERSION_DEV );
			}

			if ( ! $lite_admin->is_wffn_flex_page( 'all' ) ) {
				return;
			}

			if ( WFFN_Role_Capability::get_instance()->user_access( 'funnel', 'write' ) ) {
				add_filter( 'user_can_richedit', '__return_true', 999 );
			}

			wp_enqueue_style( 'wffn-flex-admin', $lite_admin->get_admin_url() . '/assets/css/admin.css', array(), WFFN_VERSION_DEV );

			if ( $lite_admin->is_wffn_flex_page() ) {
				$this->load_react_app();

				if ( isset( $_GET['page'] ) && $_GET['page'] === 'bwf' && method_exists( 'BWF_Admin_General_Settings', 'get_localized_bwf_data' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					wp_localize_script( 'wffn-contact-admin', 'bwfAdminGen', BWF_Admin_General_Settings::get_instance()->get_localized_bwf_data() );

				} else {
					wp_localize_script( 'wffn-contact-admin', 'bwfAdminGen', BWF_Admin_General_Settings::get_instance()->get_localized_data() );

				}

				add_filter(
					'wffn_noconflict_scripts',
					function ( $scripts = array() ) {
						return array_merge( $scripts, array( 'wffn-contact-admin' ) );
					}
				);
			}

			do_action( 'wffn_admin_assets', $lite_admin );
		}

		/**
		 * Enqueues the pro bundle under the same handle the free plugin uses.
		 * Mirrors WFFN_Admin::load_react_app against this plugin's dist; the
		 * bundle here is always the unversioned main.js.
		 */
		public function load_react_app() {
			$lite_admin = WFFN_Core()->admin;
			$app_name   = $this->get_app_name();

			$min               = 60 * get_option( 'gmt_offset' );
			$sign              = $min < 0 ? '-' : '+';
			$absmin            = abs( $min );
			$tz                = sprintf( '%s%02d:%02d', $sign, $absmin / 60, $absmin % 60 );
			$contact_page_data = array(
				'is_wc_active'                => false,
				'is_wc_square_active'         => WFFN_Common::is_wc_square_active(),
				'date_format'                 => get_option( 'date_format', 'F j, Y' ),
				'time_format'                 => get_option( 'time_format', 'g:i a' ),
				'lev'                         => WFFN_License_Config::get_instance()->get_config(),
				'app_path'                    => $this->get_app_dir(),
				'timezone'                    => $tz,
				'flag_img'                    => WFFN_Core()->get_plugin_url() . '/admin/assets/img/phone/flags.png',
				'updated_pro_version'         => defined( 'WFFN_PRO_VERSION' ) && version_compare( WFFN_PRO_VERSION, '3.0.0 beta', '>=' ),
				'activation_date'             => array(
					'lite' => $lite_admin->get_lite_activation_date(),
					'pro'  => $lite_admin->get_pro_activation_date(),
				),
				/** Views tracking ships with lite 3.15.0.8; older installs have a partial history, so the views info tip is hidden for them. */
				'show_views_tip'              => version_compare( get_option( 'wffn_first_v', '0.0.0' ), '3.15.0.8', '>=' ),
				'get_pro_link'                => $lite_admin->get_pro_link(),
				'wc_add_product_url'          => admin_url( 'post-new.php?post_type=product' ),
				'admin_url'                   => admin_url(),
				'multilingual'                => $lite_admin->is_language_support_enabled(),
				'multilingual_name'           => WFFN_Plugin_Compatibilities::get_language_compatible_plugin(),
				'block_editor_priority_theme' => $lite_admin->get_editor_priority_theme_name(),
			);
			if ( class_exists( 'WooCommerce' ) ) {
				$currency                          = get_woocommerce_currency();
				$contact_page_data['currency']     = array(
					'code'              => $currency,
					'precision'         => wc_get_price_decimals(),
					'symbol'            => html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES | ENT_HTML401 ),
					'symbolPosition'    => get_option( 'woocommerce_currency_pos' ),
					'decimalSeparator'  => wc_get_price_decimal_separator(),
					'thousandSeparator' => wc_get_price_thousand_separator(),
					'priceFormat'       => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES | ENT_HTML401 ),
				);
				$contact_page_data['is_wc_active'] = true;
			}

			$frontend_dir = $this->get_app_dir();
			if ( class_exists( 'WooCommerce' ) ) {
				wp_dequeue_style( 'woocommerce_admin_styles' );
				wp_dequeue_style( 'wc-components' );
			}

			$assets_path = $this->is_dev_mode() ? $frontend_dir . "$app_name.asset.php" : $this->get_build_dir() . "$app_name.asset.php";
			$assets      = file_exists( $assets_path ) ? include $assets_path : array(
				'dependencies' => array(
					'lodash',
					'moment',
					'react',
					'react-dom',
					'wp-api-fetch',
					'wp-components',
					'wp-compose',
					'wp-date',
					'wp-deprecated',
					'wp-block-editor',
					'wp-block-library',
					'wp-dom',
					'wp-element',
					'wp-hooks',
					'wp-html-entities',
					'wp-i18n',
					'wp-keycodes',
					'wp-polyfill',
					'wp-primitives',
					'wp-url',
					'wp-viewport',
					'wp-color-picker',
					'wp-i18n',
				),
				'version'      => time(),
			);
			$deps        = ( isset( $assets['dependencies'] ) ? array_merge( $assets['dependencies'], array( 'jquery' ) ) : array( 'jquery' ) );
			$version     = $assets['version'];

			$script_deps = array_filter(
				$deps,
				function ( $dep ) {
					return false === strpos( $dep, 'css' );
				}
			);

			if ( class_exists( 'WFFN_Header' ) ) {
				$header_ins                       = new WFFN_Header();
				$contact_page_data['header_data'] = $header_ins->get_render_data();
			}

			$contact_page_data['localize_texts'] = apply_filters( 'wffn_localized_text_admin', array() );

			wp_enqueue_style( 'wp-components' );
			wp_enqueue_style( 'wffn-contact-admin', $frontend_dir . "$app_name.css", array(), $version );
			wp_register_script( 'wffn-contact-admin', $frontend_dir . "$app_name.js", $script_deps, $version, true );
			wp_localize_script( 'wffn-contact-admin', 'wffn_contacts_data', $contact_page_data );
			wp_enqueue_script( 'wffn-contact-admin' );
			wp_set_script_translations( 'wffn-contact-admin', 'funnel-builder', $this->get_languages_dir() );
			add_filter( 'pre_load_script_translations', array( $this, 'fallback_to_lite_translations' ), 10, 4 );

			/**
			 * Chunk combiner (guarded: free versions without WFFN_Translations
			 * simply fall back to the gettext-catalog fallback above).
			 */
			if ( class_exists( 'WFFN_Translations' ) ) {
				require_once __DIR__ . '/class-wffn-pro-translations.php';
				if ( class_exists( 'WFFN_Pro_Translations' ) ) {
					WFFN_Pro_Translations::get_instance();
				}
			}

			wp_enqueue_editor();
			wp_tinymce_inline_scripts();
			wp_enqueue_media();
		}

		/**
		 * Serves the free plugin's installed 'funnel-builder' translations for
		 * the app handles when no JSON translation file was found.
		 *
		 * wp.org language packs key script JSONs by the md5 of the file path
		 * inside the FREE plugin, so the pro bundle path never matches one.
		 * Hooked on pre_load_script_translations (the only filter core runs
		 * when a lookup misses) but acts solely on the final call, where
		 * $file === false because every file lookup missed — lookups for
		 * JSONs shipped in languages/ or generated on the site (Loco) pass a
		 * file path, get declined here (null) and win by loading normally.
		 * Pro-only strings stay untranslated here; they are covered by JSONs
		 * compiled from languages/funnel-builder.pot.
		 */
		public function fallback_to_lite_translations( $translations, $file, $handle, $domain ) {
			if ( 'funnel-builder' !== $domain || false !== $file ) {
				return $translations;
			}
			if ( 'wffn-contact-admin' !== $handle && 0 !== strpos( $handle, 'wffn_admin_' ) ) {
				return $translations;
			}

			static $jed = null;
			if ( null === $jed ) {
				$jed = $this->build_jed_from_lite_domain();
			}

			return false === $jed ? $translations : $jed;
		}

		/**
		 * Builds a JED 1.x payload (the shape wp.i18n consumes) from the
		 * 'funnel-builder' gettext catalog. Language-pack .mo files are
		 * compiled from the full project export, so the JS strings are in
		 * there even though WordPress normally serves them from JSONs.
		 *
		 * @return string|false JSON string, or false when no translations are loaded.
		 */
		private function build_jed_from_lite_domain() {
			$catalog = get_translations_for_domain( 'funnel-builder' );
			// Local var on purpose: entries/headers are magic __get properties on
			// WP_Translations (no __isset), so empty()/isset() on them is always false.
			$entries = $catalog ? $catalog->entries : array();
			if ( ! is_array( $entries ) || array() === $entries ) {
				return false;
			}

			$locale_data = array(
				'' => array(
					'domain' => 'messages',
					'lang'   => determine_locale(),
				),
			);

			$headers = $catalog->headers;
			if ( is_array( $headers ) && ! empty( $headers['Plural-Forms'] ) ) {
				$locale_data['']['plural-forms'] = $headers['Plural-Forms'];
			}

			foreach ( $entries as $entry ) {
				$key                 = ( null === $entry->context || '' === $entry->context ) ? $entry->singular : $entry->context . "\x04" . $entry->singular;
				$locale_data[ $key ] = $entry->translations;
			}

			return wp_json_encode(
				array(
					'domain'      => 'messages',
					'locale_data' => array( 'messages' => $locale_data ),
				)
			);
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'react_app', 'WFFN_Pro_React_App' );
	}
}
