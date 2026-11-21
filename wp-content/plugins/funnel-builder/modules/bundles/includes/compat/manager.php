<?php
/** Manager — instantiate and boot the third-party compatibility adapters. */
namespace WFFN\Bundles\Includes\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the third-party compatibility adapters.
 */
class Manager {

	/**
	 * Adapter class names, as strings.
	 *
	 * Strings rather than instances: the autoloader must not be asked to load a
	 * class whose parent lives in a plugin that may be inactive.
	 *
	 * @var string[]
	 */
	private static $integrations = array(
		Elementor\Adapter::class,
		Divi\Adapter::class,
		Bricks\Adapter::class,
		Blocks\Adapter::class,
		Funnel_Builder\Adapter::class,
		Funnel_Builder\Summary::class,
		Funnel_Builder\Abilities::class,
		Fkcart\Adapter::class,
		Fkcart\Menu::class,
		One_Click_Upsells\Adapter::class,
		Paypal_Payments\Adapter::class,
		Funnelkit_Paypal\Adapter::class,
	);

	/**
	 * Adapters that only add to admin screens.
	 *
	 * @var string[]
	 */
	const ADMIN_ONLY = array( Fkcart\Menu::class );

	/**
	 * Load and init() the adapters whose plugin is present; the rest are never loaded.
	 *
	 * @return void
	 */
	public static function boot() {
		foreach ( self::registered() as $class ) {
			if ( ! self::present( $class ) ) {
				continue;
			}
			// An admin-screen adapter is not loaded on a storefront request; a REST call may still need it.
			if ( in_array( $class, self::ADMIN_ONLY, true ) && ! is_admin() ) {
				add_action(
					'rest_api_init',
					function () use ( $class ) {
						self::start( $class );
					}
				);
				continue;
			}
			self::start( $class );
		}
	}

	/**
	 * Load and init() one adapter, when its own is_enable() agrees.
	 *
	 * @param string $adapter_class Adapter class name.
	 * @return void
	 */
	private static function start( $adapter_class ) {
		if ( ! class_exists( $adapter_class ) ) {
			return;
		}
		$adapter = new $adapter_class();
		if ( $adapter instanceof Integration && $adapter->is_enable() ) {
			$adapter->init();
		}
	}

	/**
	 * Is the plugin an adapter targets loaded? Asked before the adapter's file is.
	 *
	 * Each test repeats the adapter's own is_enable(), which keeps the final say.
	 * An adapter with no test here is always loaded and asked.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Adapter class name.
	 * @return bool
	 */
	private static function present( $class ) {
		switch ( $class ) {
			case Elementor\Adapter::class:
				return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
			case Divi\Adapter::class:
				return defined( 'ET_BUILDER_PLUGIN_VERSION' ) || defined( 'ET_BUILDER_THEME' ) || function_exists( 'et_builder_wc_init' );
			case Bricks\Adapter::class:
				return 'bricks' === get_template() || defined( 'BRICKS_VERSION' );
			case Fkcart\Adapter::class:
			case Fkcart\Menu::class:
				return defined( 'FKCART_VERSION' );
			case One_Click_Upsells\Adapter::class:
				return class_exists( 'WFOCU_Core' );
			case Paypal_Payments\Adapter::class:
				return class_exists( '\WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway' );
			case Funnelkit_Paypal\Adapter::class:
				return class_exists( '\FunnelKit\PayPal\Main' );
		}
		return true;
	}

	/**
	 * The compatibility adapters that boot on this request.
	 *
	 * @return string[] Adapter class names.
	 */
	private static function registered() {
		return (array) apply_filters( 'wffn_bundles_compat_integrations', self::$integrations );
	}
}
