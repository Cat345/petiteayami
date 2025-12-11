<?php
if ( ! class_exists( 'WFOCU_Plugin_Compatibilities' ) ) {
	/**
	 * Class WFOCU_Plugin_Compatibilities
	 * Loads all the compatibilities files we have to provide compatibility with each plugin
	 */
	class WFOCU_Plugin_Compatibilities {

		public static $plugin_compatibilities = array();

		public static function load_all_compatibilities() {

			// load all the WFOCU_Compatibilities files automatically
			$paths = [
				'class-wfocu-compatibility-with-divi.php'                      => true,
				'class-wfocu-compatibility-with-elementor.php'                 => true,
				'class-wfocu-compatibility-with-gutenberg.php'                 => true,
				'class-wfocu-compatibility-with-oxygen.php'                    => true,
				'class-wfocu-compatibility-with-product-bundles.php'           => class_exists( 'WC_Bundles' ),
				'class-wfocu-compatibility-with-product-variation-bundles.php' => class_exists( 'WC_Bundles' ) && class_exists( 'WC_PB_Variable_Bundles' ),
				'class-wfocu-compatibility-with-subscriptions.php'             => class_exists( 'WC_Subscriptions' ) || class_exists( 'WC_Subscriptions_Core_Plugin' ),
				'class-wfocu-compatibility-with-wc-memberships.php'            => class_exists( 'WC_Memberships_Loader' ) || ( class_exists( 'WC_Memberships' ) && version_compare( WC_Memberships::VERSION, '1.9.0', '>=' ) ),
				'class-wfocu-compatibility-with-weglot.php'                    => defined( 'WEGLOT_NAME' ),
				'class-wfocu-compatibility-with-woocs.php'                     => class_exists( 'WOOCS' ),
				'class-wfocu-compatibility-with-woomulticurrency.php'          => defined( 'WOOMULTI_CURRENCY_VERSION' ),
				'class-wfocu-compatibility-with-wpml-multicurrency.php'        => class_exists( 'SitePress' ) && class_exists( 'woocommerce_wpml' ) && class_exists( 'WCML_Cart' ),
				'class-wfocu-learndash-compatibility.php'                      => class_exists( 'learndash_woocommerce' ),
				'class-wfocu-wc-compatibility.php'                             => true,
				'class-wfocu-wffn-compatibility.php'                           => class_exists( 'WFFN_Core' ),
				'class-wfocu-compatibility-with-wc-germanized.php'             => defined( 'WC_GERMANIZED_PLUGIN_FILE' ),
				'class-wfocu-compatibility-with-breakdance.php'                => defined( 'BREAKDANCE_WOO_DIR' ),
				'class-wfocu-compatibility-with-pys.php'                       => class_exists( 'PixelYourSite\EventsManager' ),
			];
			self::add_files( $paths );
			add_action( 'after_setup_theme', [ __CLASS__, 'themes' ] );
		}

		public static function themes() {
			$paths = [
				'class-wfocu-affiliate-wp-compatibility.php'            => class_exists( 'Affiliate_WP' ),
				'class-wfocu-compatibility-with-generatepress.php'      => ( defined( 'GENERATE_VERSION' ) || class_exists( 'GeneratePress_Pro_Typography_Customize_Control' ) || class_exists( 'Generate_Typography_Customize_Control' ) ),
				'class-wfocu-compatibility-theme-bricks.php'            => defined( 'BRICKS_VERSION' ),
				'class-wfocu-compatibility-theme-nitro.php'             => class_exists( 'WR_Nitro' ),
				'class-wfocu-compatibility-theme-oceanwp.php'           => class_exists( 'OCEANWP_Theme_Class' ),
				'class-wfocu-compatibility-with-aelia-cs.php'           => class_exists( 'Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher' ),
				'class-wfocu-compatibility-with-avada.php'              => class_exists( 'FusionBuilder' ),
				'class-wfocu-compatibility-with-beaver.php'             => class_exists( 'FLBuilderLoader' ),
				'class-wfocu-themes-compatibility.php'                  => true,
				'class-wfocu-compatibility-with-cog.php'                => class_exists( 'WC_COG' ),
				'class-wfocu-compatibility-with-fba.php'                => class_exists( 'NS_FBA' ),
				'class-wfocu-compatibility-with-optimol.php'            => class_exists( 'Optml_Main' ),
				'class-wfocu-compatibility-with-theme-woodmart.php'     => defined( 'WOODMART_THEME_DIR' ),
				'class-wfocu-compatibility-with-thrive.php'             => defined( 'TVE_PLUGIN_FILE' ),
				'class-wfocu-compatibility-with-thrive-theme.php'       => function_exists( 'thrive_theme' ),
				'class-wfocu-compatibility-with-translate-press.php'    => defined( 'TRANSLATE_PRESS' ),
				'class-wfocu-compatibility-with-ux-builder.php'         => function_exists( 'add_ux_builder_post_type' ),
				'class-wfocu-compatibility-with-wc-seq.php'             => function_exists( 'wc_seq_order_number_pro' ) && class_exists( 'WC_Seq_Order_Number_Pro' ),
				'class-wfocu-compatibility-with-wcva.php'               => class_exists( 'wcva_direct_variation_link' ),
				'class-wfocu-compatibility-with-wp-seo.php'             => defined( 'WPSEO_VERSION' ),
				'class-wfocu-compatibility-with-xlwcty.php'             => function_exists( 'XLWCTY_Core' ),
				'class-wfocu-indeed-affiliate-compatibility.php'        => defined( 'UAP_PLUGIN_VER' ),
				'class-wfocu-pixel-cog.php'                             => defined( 'PIXEL_COG_VERSION' ),
				'class-wfocu-ultimate-membership-pro-compatibility.php' => defined( 'IHCACTIVATEDMODE' ),
			];
			self::add_files( $paths );
		}


		public static function register( $object, $slug ) {
			self::$plugin_compatibilities[ $slug ] = $object;
		}

		public static function get_compatibility_class( $slug ) {
			return ( isset( self::$plugin_compatibilities[ $slug ] ) ) ? self::$plugin_compatibilities[ $slug ] : false;
		}

		public static function get_fixed_currency_price( $price, $currency = null ) {

			if ( ! empty( self::$plugin_compatibilities ) ) {

				foreach ( self::$plugin_compatibilities as $plugins_class ) {

					if ( $plugins_class->is_enable() && is_callable( array( $plugins_class, 'alter_fixed_amount' ) ) ) {

						try {
							return call_user_func( array( $plugins_class, 'alter_fixed_amount' ), $price, $currency );
						} catch ( Exception|Error $e ) {
							return $price;
						}
					}
				}
			}

			return $price;
		}

		public static function get_fixed_currency_price_reverse( $price, $from = null, $to = null ) {

			if ( ! empty( self::$plugin_compatibilities ) ) {

				foreach ( self::$plugin_compatibilities as $plugins_class ) {

					if ( $plugins_class->is_enable() && is_callable( array( $plugins_class, 'get_fixed_currency_price_reverse' ) ) ) {
						try {
							return call_user_func( array( $plugins_class, 'get_fixed_currency_price_reverse' ), $price, $from, $to );
						} catch ( Exception|Error $e ) {
							return $price;
						}

					}
				}
			}

			return $price;
		}

		public static function add_files( $paths ) {
			try {
				foreach ( $paths as $file => $condition ) {
					if ( false === $condition ) {
						continue;
					}
					include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/' . $file;

				}

			} catch ( Exception|Error $e ) {
				WFOCU_Core()->log->log( $e->getMessage() );
			}

		}
	}


	WFOCU_Plugin_Compatibilities::load_all_compatibilities();

}