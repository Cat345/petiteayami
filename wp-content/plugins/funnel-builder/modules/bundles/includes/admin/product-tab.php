<?php
/**
 * The Bundles tab on the WooCommerce product edit screen.
 *
 * A miniature of the admin app's editor: list the bundles that target this
 * product, and create a single-offer one without leaving the product. Every read
 * and write goes through the `wffn-bundles-admin/v1` REST routes the app itself
 * uses, so the same validation applies and nothing is stored a second way.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Admin;

use WFFN\Bundles\Includes\Products\Repository;
use WFFN\Bundles\Includes\Skins\Frequently_Bought_Together;
use WFFN\Bundles\Includes\Storage\Bundle_Rules;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Storage\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the tab, its panel mount point and its script.
 */
class Product_Tab {

	/** Product data tab / panel id. */
	const TAB = 'wffn_bundles_bundles';

	/** Type id the add-on stores its box bundles under; the pitch names bundles of it. */
	const PITCH_BOX_TYPE = 3;

	/** Type id the add-on stores its quantity tiers under; the pitch names bundles of it. */
	const PITCH_TIERS_TYPE = 1;

	/** Plugin header an add-on declares, so an inactive copy can be offered for activation. */
	const ADDON_HEADER = 'Bundles Add-on';

	/** Where the pitch's upgrade button goes. */
	const UPGRADE_URL = 'https://funnelkit.com/exclusive-offer/?utm_source=WordPress&utm_bundle=Bundles+Plugin&utm_medium=Mix+Match+Product+Tab';

	/**
	 * Hook the tab in. Admin only.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_wffn_bundles_product_tab_bundles', array( __CLASS__, 'ajax_bundles' ) );
		add_action( 'wp_ajax_wffn_bundles_product_tab_assign', array( __CLASS__, 'ajax_assign' ) );
		add_action( 'wp_ajax_wffn_bundles_product_tab_unassign', array( __CLASS__, 'ajax_unassign' ) );
		add_action( 'wp_ajax_wffn_bundles_product_tab_activate_pro', array( __CLASS__, 'ajax_activate_pro' ) );
		add_filter( 'extra_plugin_headers', array( __CLASS__, 'addon_header' ) );
	}

	/**
	 * Have WordPress read the add-on header in its own plugin scan.
	 *
	 * @param string[] $headers Extra plugin headers.
	 * @return string[]
	 */
	public static function addon_header( $headers ) {
		$headers   = (array) $headers;
		$headers[] = self::ADDON_HEADER;
		return $headers;
	}

	/**
	 * Whether the current user may use the tab.
	 *
	 * @return bool
	 */
	private static function allowed() {
		/**
		 * Filters whether the product edit screen shows the Bundles tab.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $enabled Whether the current user may use the tab.
		 */
		return (bool) apply_filters( 'wffn_bundles_product_tab_enabled', Navigation::can_manage() );
	}

	/**
	 * Add the tab to the product data box.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		if ( ! self::allowed() ) {
			return $tabs;
		}
		$tabs[ self::TAB ] = array(
			'label'    => __( 'Bundles', 'funnel-builder' ),
			'target'   => 'wffn_bundles_product_data',
			'class'    => array(),
			'priority' => 85,
		);
		return $tabs;
	}

	/**
	 * Print the panel; the script fills it.
	 *
	 * @return void
	 */
	public static function render_panel() {
		if ( ! self::allowed() ) {
			return;
		}
		// Not `woocommerce_options_panel`: its rules float every label into the tab column.
		echo '<div id="wffn_bundles_product_data" class="panel hidden"><div class="wffn-bundles-pt" id="wffn-bundles-pt-root"></div></div>';
	}

	/**
	 * Re-read the bundles on a product after one is created or toggled.
	 *
	 * @return void
	 */
	public static function ajax_bundles() {
		check_ajax_referer( self::TAB, '_wpnonce' );
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! self::allowed() || ! $product_id || ! current_user_can( 'edit_product', $product_id ) ) {
			wp_send_json_error( null, 403 );
		}
		// A site that has never saved a bundle has none to list.
		$in_use = \WFFN\Bundles\Includes\Storage\Install::is_in_use();
		$out    = array(
			'bundles'    => $in_use ? self::bundles_for( $product_id ) : array(),
			'assignable' => $in_use ? self::assignable_for( $product_id ) : array(),
		);
		if ( ! empty( $_POST['variants'] ) ) {
			$out['variants'] = self::variable_shape( $product_id );
			$out['linked']   = self::linked( $product_id );
		}
		wp_send_json_success( $out );
	}

	/**
	 * Activate an installed but inactive add-on from the pitch, without leaving the product.
	 *
	 * @return void
	 */
	public static function ajax_activate_pro() {
		check_ajax_referer( self::TAB, '_wpnonce' );
		if ( ! self::allowed() || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( null, 403 );
		}
		$addon = self::inactive_addon();
		if ( null === $addon ) {
			wp_send_json_error( null, 404 );
		}
		$result = activate_plugin( $addon['file'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		wp_send_json_success();
	}

	/**
	 * An installed, inactive plugin that declares itself an add-on of this one.
	 *
	 * @return array{file:string,name:string}|null
	 */
	private static function inactive_addon() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! has_filter( 'extra_plugin_headers', array( __CLASS__, 'addon_header' ) ) ) {
			add_filter( 'extra_plugin_headers', array( __CLASS__, 'addon_header' ) );
		}
		$plugins = get_plugins();
		// A list cached before addon_header() was hooked carries no such key.
		if ( $plugins && ! array_key_exists( self::ADDON_HEADER, (array) reset( $plugins ) ) ) {
			wp_cache_delete( 'plugins', 'plugins' );
			$plugins = get_plugins();
		}
		foreach ( $plugins as $file => $plugin ) {
			if ( is_plugin_active( $file ) ) {
				continue;
			}
			$addon = (string) ( $plugin[ self::ADDON_HEADER ] ?? '' );
			$name  = (string) ( $plugin['Name'] ?? '' );
			if ( '' !== trim( $addon ) ) {
				return array(
					'file' => $file,
					'name' => $name,
				);
			}
		}
		return null;
	}

	/**
	 * What the pitch offers: buy the add-on, or activate the copy already installed.
	 *
	 * Only reached while the add-on is inactive, since an active one replaces the pitch.
	 *
	 * @param bool $pitched Whether the tab still shows the pitch; without it the step is never read.
	 * @return array{step:string,name:string} Step is upgrade, activate, or ask when the user may not activate plugins.
	 */
	private static function pro_step( $pitched = true ) {
		$addon = $pitched ? self::inactive_addon() : null;
		if ( null === $addon ) {
			return array(
				'step' => 'upgrade',
				'name' => '',
			);
		}
		return array(
			'step' => current_user_can( 'activate_plugins' ) ? 'activate' : 'ask',
			'name' => $addon['name'],
		);
	}

	/**
	 * Enqueue on the product edit screen only.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( ! self::allowed() || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		global $post;
		if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
			return;
		}

		$css = self::asset_file( 'assets/admin/product-tab.css' );
		$js  = self::asset_file( 'assets/admin/product-tab.js' );
		wp_enqueue_style( 'wffn-bundles-product-tab', WFFN_BUNDLES_URL . $css, array(), self::asset_version( $css ) );
		wp_enqueue_script( 'wffn-bundles-product-tab', WFFN_BUNDLES_URL . $js, array( 'jquery', 'jquery-ui-sortable', 'jquery-touch-punch', 'selectWoo' ), self::asset_version( $js ), true );
		wp_add_inline_script( 'wffn-bundles-product-tab', 'window.wffnBundlesProductTabData = ' . wp_json_encode( self::data( $post ) ) . ';', 'before' );

		/**
		 * Fires after the product tab script is enqueued.
		 *
		 * An add-on enqueues its own script here, depending on `wffn-bundles-product-tab`,
		 * to register more bundle types with `window.wffnBundlesProductTab.registerType()`.
		 *
		 * @since 1.1.0
		 *
		 * @param int $product_id The product being edited.
		 */
		do_action( 'wffn_bundles_product_tab_enqueue', $post->ID );
	}

	/**
	 * The build's minified copy of a tab file, or the file itself.
	 *
	 * The copy is used only when it is at least as new as its source, so an edited
	 * source is never shadowed by an older build, and never under SCRIPT_DEBUG.
	 *
	 * @param string $file Path relative to the plugin root.
	 * @return string Path relative to the plugin root.
	 */
	private static function asset_file( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $file;
		}
		$min    = preg_replace( '/\.(js|css)$/', '.min.$1', $file );
		$source = file_exists( WFFN_BUNDLES_DIR . '/' . $file ) ? filemtime( WFFN_BUNDLES_DIR . '/' . $file ) : false;
		// A missing copy is the normal case in a checkout.
		$built = file_exists( WFFN_BUNDLES_DIR . '/' . $min ) ? filemtime( WFFN_BUNDLES_DIR . '/' . $min ) : false;
		return ( $built && $source && $built >= $source ) ? $min : $file;
	}

	/**
	 * Cache-busting version for one of the tab's files.
	 *
	 * The plugin version stays put between builds, so browsers would keep the
	 * previous script; the file's modified time changes with every build.
	 *
	 * @param string $file Path relative to the plugin root.
	 * @return string
	 */
	private static function asset_version( $file ) {
		$mtime = file_exists( WFFN_BUNDLES_DIR . '/' . $file ) ? filemtime( WFFN_BUNDLES_DIR . '/' . $file ) : false;
		return $mtime ? WFFN_BUNDLES_VERSION . '.' . $mtime : WFFN_BUNDLES_VERSION;
	}

	/**
	 * A saved variable product's attributes and variation prices, for the preview's dropdowns.
	 *
	 * @param int $product_id Product id.
	 * @return array{attributes?: array[], variations?: array[]} Empty for any other product.
	 */
	private static function variable_shape( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return array();
		}
		$shape = Repository::find( $product_id );
		if ( ! is_array( $shape ) || 'variable' !== ( $shape['type'] ?? '' ) ) {
			return array();
		}
		$variations = array();
		foreach ( (array) ( $shape['variations'] ?? array() ) as $variation ) {
			$variations[] = array(
				'attributes'  => (array) ( $variation['attributes'] ?? array() ),
				'priceRaw'    => (float) ( $variation['priceRaw'] ?? 0 ),
				'purchasable' => ! empty( $variation['purchasable'] ),
			);
		}
		return array(
			'attributes' => array_values( (array) ( $shape['attributes'] ?? array() ) ),
			'variations' => $variations,
		);
	}

	/**
	 * The products a bought-together set shows when it lists none of its own.
	 *
	 * Resolved by the storefront's own rule, so the preview names what the page will show.
	 *
	 * @param int $product_id Product id.
	 * @return array<string,array[]> Product rows per fallback: `cross_sells`, `cross_sells_upsells`.
	 */
	private static function linked( $product_id ) {
		$host = wc_get_product( $product_id );
		$out  = array();
		foreach ( array( 'cross_sells', 'cross_sells_upsells' ) as $fallback ) {
			$out[ $fallback ] = array();
			$ids              = Frequently_Bought_Together::companion_ids(
				array(
					'fbtFallback' => $fallback,
					'fbtMaxItems' => Bundle_Rules::FBT_MAX_COMPANIONS,
				),
				$host
			);
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				$out[ $fallback ][] = array(
					'id'       => (string) $id,
					'name'     => wp_strip_all_tags( $product->get_name() ),
					'type'     => $product->get_type(),
					'priceRaw' => (float) $product->get_price(),
					'image'    => (string) wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Bootstrap data for the script.
	 *
	 * @param \WP_Post $post Product being edited.
	 * @return array
	 */
	private static function data( \WP_Post $post ) {
		$product_id = (int) $post->ID;
		$product    = wc_get_product( $product_id );
		$saved      = 'auto-draft' !== $post->post_status;

		$types = array(
			array(
				'id'  => Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER,
				'key' => 'fbt',
			),
			array(
				'id'  => Bundle_Store::TYPE_FIXED_BUNDLES,
				'key' => 'fixed',
			),
			array(
				'id'    => self::PITCH_TIERS_TYPE,
				'key'   => 'qty',
				'label' => __( 'Quantity Breaks', 'funnel-builder' ),
				'pitch' => true,
				'mock'  => 'tiers',
				'copy'  => array(
					'text'     => __( 'Reward customers for buying more. Set discounts so the per-unit price drops as the quantity goes up.', 'funnel-builder' ),
					'points'   => array(
						__( 'Each tier sells the product at a larger quantity for less per item.', 'funnel-builder' ),
						__( 'The tier follows the quantity in the cart, so a shopper never overpays.', 'funnel-builder' ),
					),
					'heading'  => __( 'Buy more & save more', 'funnel-builder' ),
					'note'     => __( 'Quantity Breaks comes with the premium plan.', 'funnel-builder' ),
					/* translators: %s: add-on plugin name. */
					'inactive' => __( '%s is installed but not active. Activate it to create Quantity Breaks bundles.', 'funnel-builder' ),
				),
			),
			array(
				'id'    => self::PITCH_BOX_TYPE,
				'key'   => 'mix',
				'label' => __( 'Mix & Match', 'funnel-builder' ),
				'pitch' => true,
			),
		);

		/**
		 * Filters the bundle types the product tab offers, in display order.
		 *
		 * The `qty` and `mix` entries are static pitches; an add-on that provides
		 * the type replaces it with its own entry and registers the form for that
		 * key. A pitch may carry its own `copy` and a `mock` preview.
		 *
		 * @since 1.1.0
		 *
		 * @param array[] $types Each `{ id, key, label?, pitch?, copy?, mock? }`; `key` names the form registered client-side.
		 */
		$types   = array_values( (array) apply_filters( 'wffn_bundles_product_tab_types', $types ) );
		$pitched = false;
		foreach ( $types as $i => $type ) {
			$types[ $i ]['label'] = self::type_label( (int) ( $type['id'] ?? 0 ), (string) ( $type['label'] ?? '' ) );
			$pitched              = $pitched || ! empty( $type['pitch'] );
		}

		return array(
			'restRoot'  => rest_url( WFFN_BUNDLES_ADMIN_NAMESPACE . '/' . WFFN_BUNDLES_API_VERSION ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'listNonce' => wp_create_nonce( self::TAB ),
			'listUrl'   => Navigation::admin_url(),
			// Null until the merchant activates or dismisses Bundles; the tab holds its form only while switched off.
			'state'     => Install::get_state(),
			'upgradeUrl' => self::UPGRADE_URL,
			'pro'       => self::pro_step( $pitched ),
			'types'     => $types,
			'fbtMax'    => Bundle_Rules::FBT_MAX_COMPANIONS,
			'product'   => array(
				'id'       => (string) $product_id,
				'saved'    => $saved,
				'name'     => $product && $saved ? wp_strip_all_tags( $product->get_name() ) : '',
				'priceRaw' => $product ? (float) $product->get_price() : 0.0,
				'image'    => $product ? (string) wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '',
			),
			'currency'  => array(
				'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES ),
				'decimals' => wc_get_price_decimals(),
				'decSep'   => wc_get_price_decimal_separator(),
				'thouSep'  => wc_get_price_thousand_separator(),
				'position' => get_option( 'woocommerce_currency_pos', 'left' ),
			),
			'permalink' => $product ? get_permalink( $product_id ) : '',
			'i18n'      => self::strings(),
		);
	}

	/**
	 * Display name of a bundle type, falling back to the caller's own label.
	 *
	 * @param int    $type     Bundle type id.
	 * @param string $fallback Label to use when no display name is registered.
	 * @return string
	 */
	private static function type_label( $type, $fallback = '' ) {
		return Bundle_Store::type_label( $type, '' !== $fallback ? $fallback : null );
	}

	/**
	 * Every bundle that names this product as a placement target.
	 *
	 * `state` is what a shopper sees: `live` for the one bundle the product page
	 * renders (a product shows a single bundle, of whatever type), `hidden`
	 * for an active one a higher-ranked bundle outranks,
	 * `upcoming`, `ended` or `nodates` for an active one its schedule keeps off,
	 * `scheduled` for one off for another reason, `unsupported` for an active one
	 * of a type no active plugin renders, `draft` otherwise.
	 *
	 * @param int $product_id Product id.
	 * @return array[]
	 */
	public static function bundles_for( $product_id ) {
		$ids = Bundle_Store::placed_on_product( $product_id );
		Bundle_Store::prime_configs( $ids );

		$rows    = array();
		$showing = null;
		$types   = Bundle_Store::accepted_types();
		foreach ( $ids as $id ) {
			$id     = (string) $id;
			$config = Bundle_Store::get_config( $id );
			if ( ! is_array( $config ) || ! Bundle_Store::targets_product( $config, (string) $product_id ) ) {
				continue;
			}
			$name    = (string) ( $config['name'] ?? '' );
			$type    = (int) ( $config['discountType'] ?? 0 );
			$active  = 1 === (int) ( $config['status'] ?? 0 );
			$running = $active && in_array( $type, $types, true ) && Bundle_Store::is_live( $config );
			$window  = self::window( $config );

			// The first running bundle in rank order is the only one the page shows.
			if ( $running && null === $showing ) {
				$showing = $name;
				$state   = 'live';
			} elseif ( $running ) {
				$state = 'hidden';
			} elseif ( $active && ! in_array( $type, $types, true ) ) {
				$state = 'unsupported';
			} else {
				$state = $active ? self::off_schedule_state( $window ) : 'draft';
			}

			$rows[] = array(
				'id'          => $id,
				'name'        => $name,
				'type'        => $type,
				'typeLabel'   => self::type_label( $type, __( 'Other bundle type', 'funnel-builder' ) ),
				'state'       => $state,
				'outrankedBy' => 'hidden' === $state ? (string) $showing : '',
				'when'        => self::when( $state, $window ),
				'scope'       => self::scope( $config ),
				'editUrl'     => Navigation::admin_url( '/bundles/' . rawurlencode( $id ) ),
			);
		}
		return $rows;
	}

	/**
	 * Bundles this product could be added to: ones that list their products by hand and do not list it yet.
	 *
	 * Category-placed bundles are left out, since a single product cannot be added to a category.
	 *
	 * @param int $product_id Product id.
	 * @return array[] Each `{ id, name, type, typeLabel, draft }`.
	 */
	private static function assignable_for( $product_id ) {
		$ids = array_slice( array_keys( Bundle_Store::rank_map() ), 0, 200 );
		Bundle_Store::prime_configs( $ids );

		$types = Bundle_Store::accepted_types();
		$out   = array();
		foreach ( $ids as $id ) {
			$id     = (string) $id;
			$config = Bundle_Store::get_config( $id );
			if ( ! is_array( $config ) ) {
				continue;
			}
			$type = (int) ( $config['discountType'] ?? 0 );
			$mode = (int) ( $config['productSelection']['mode'] ?? Bundle_Store::MODE_NONE );
			if ( ! in_array( $type, $types, true ) || Bundle_Store::MODE_SPECIFIC_CATEGORIES === $mode || Bundle_Store::targets_product( $config, (string) $product_id ) ) {
				continue;
			}
			$out[] = array(
				'id'        => $id,
				'name'      => (string) ( $config['name'] ?? '' ),
				'type'      => $type,
				'typeLabel' => self::type_label( $type, __( 'Other bundle type', 'funnel-builder' ) ),
				'draft'     => 1 !== (int) ( $config['status'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Add this product to, or take it off, the products a bundle is placed on.
	 *
	 * The change goes through the same REST route the bundle editor saves with, so
	 * every rule that applies there applies here. A live bundle left with no product
	 * cannot stay live, so it is set back to a draft in the same save.
	 *
	 * @param string $bundle_id Bundle id.
	 * @param int    $product_id  Product id.
	 * @param bool   $add         True to add the product, false to remove it.
	 * @return array{ok:bool,message:string,draft:bool,name:string}
	 */
	private static function place( $bundle_id, $product_id, $add ) {
		$fail   = function ( $message, $name = '' ) {
			return array( 'ok' => false, 'message' => $message, 'draft' => false, 'name' => $name );
		};
		$config = Bundle_Store::get_config( $bundle_id );
		if ( ! is_array( $config ) ) {
			return $fail( __( 'That bundle no longer exists.', 'funnel-builder' ) );
		}
		$name      = (string) ( $config['name'] ?? '' );
		$selection = is_array( $config['productSelection'] ?? null ) ? $config['productSelection'] : array();
		$mode      = (int) ( $selection['mode'] ?? Bundle_Store::MODE_NONE );
		if ( Bundle_Store::MODE_SPECIFIC_CATEGORIES === $mode ) {
			return $fail( __( 'This bundle is placed by category, so edit it to change where it shows.', 'funnel-builder' ), $name );
		}

		$pid      = (string) $product_id;
		$products = array();
		$listed   = false;
		foreach ( (array) ( $selection['products'] ?? array() ) as $row ) {
			$id = (string) ( is_array( $row ) ? ( $row['id'] ?? '' ) : $row );
			if ( '' === $id ) {
				continue;
			}
			if ( $id === $pid ) {
				$listed = true;
				if ( ! $add ) {
					continue;
				}
			}
			$products[] = array( 'id' => $id );
		}
		if ( $add && ! $listed ) {
			$products[] = array( 'id' => $pid );
		}

		$patch = array(
			'productSelection' => array_merge(
				$selection,
				array(
					'mode'     => Bundle_Store::MODE_SPECIFIC_PRODUCTS,
					'products' => $products,
				)
			),
		);
		$draft = false;
		if ( ! $add && empty( $products ) && 1 === (int) ( $config['status'] ?? 0 ) ) {
			$patch['status'] = 0;
			$draft           = true;
		}

		$request = new \WP_REST_Request( 'PUT', '/' . self::rest_namespace() . '/bundles/' . rawurlencode( (string) $bundle_id ) );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $patch ) );
		$response = rest_do_request( $request );
		$data     = $response->get_data();
		// This plugin's routes answer a refusal with HTTP 200 and the real code in the body (Rest\Base::err()).
		if ( $response->is_error() || (int) $response->get_status() >= 400 || ( is_array( $data ) && (int) ( $data['code'] ?? 0 ) >= 400 ) ) {
			return $fail( is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : __( 'Could not save that bundle.', 'funnel-builder' ), $name );
		}
		return array( 'ok' => true, 'message' => '', 'draft' => $draft, 'name' => $name );
	}

	/**
	 * The REST namespace the bundle editor saves through.
	 *
	 * @return string
	 */
	private static function rest_namespace() {
		return WFFN_BUNDLES_ADMIN_NAMESPACE . '/' . WFFN_BUNDLES_API_VERSION;
	}

	/**
	 * Add this product to the bundles the merchant picked.
	 *
	 * @return void
	 */
	public static function ajax_assign() {
		check_ajax_referer( self::TAB, '_wpnonce' );
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! self::allowed() || ! $product_id || ! current_user_can( 'edit_product', $product_id ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! Navigation::can_write() ) {
			wp_send_json_error( null, 403 );
		}
		$ids = isset( $_POST['bundle_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['bundle_ids'] ) ) : array();
		$ids = array_slice( array_values( array_unique( array_filter( $ids ) ) ), 0, 50 );

		$added  = array();
		$failed = array();
		foreach ( $ids as $id ) {
			$result = self::place( (string) $id, $product_id, true );
			if ( $result['ok'] ) {
				$added[] = (string) $id;
			} else {
				$failed[] = array(
					'id'      => (string) $id,
					'name'    => $result['name'],
					'message' => $result['message'],
				);
			}
		}
		wp_send_json_success(
			array(
				'added'  => $added,
				'failed' => $failed,
			)
		);
	}

	/**
	 * Take this product off one bundle's placements; the bundle itself is kept.
	 *
	 * @return void
	 */
	public static function ajax_unassign() {
		check_ajax_referer( self::TAB, '_wpnonce' );
		$product_id  = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$bundle_id = isset( $_POST['bundle_id'] ) ? absint( wp_unslash( $_POST['bundle_id'] ) ) : 0;
		if ( ! self::allowed() || ! Navigation::can_write() || ! $product_id || ! $bundle_id || ! current_user_can( 'edit_product', $product_id ) ) {
			wp_send_json_error( null, 403 );
		}
		$result = self::place( (string) $bundle_id, $product_id, false );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}
		wp_send_json_success( array( 'draft' => $result['draft'] ) );
	}

	/**
	 * A bundle's schedule window, read in the site timezone as the running check reads it.
	 *
	 * Empty unless the bundle has a schedule; every edition enforces it (Bundle_Store::is_live()).
	 *
	 * @param array $config Raw bundle config.
	 * @return array{0:int,1:int,2:bool} Start and end timestamps (0 for none), and whether a window applies.
	 */
	private static function window( array $config ) {
		$schedule = $config['schedule'] ?? null;
		if ( ! is_array( $schedule ) || ! empty( $schedule['alwaysAvailable'] ) ) {
			return array( 0, 0, false );
		}
		return array( Bundle_Store::schedule_ts( $schedule['startDate'] ?? '' ), Bundle_Store::schedule_ts( $schedule['endDate'] ?? '' ), true );
	}

	/**
	 * Why an active bundle is not running: before its start, past its end, or no dates at all.
	 *
	 * @param array $window From window().
	 * @return string upcoming, ended, nodates or scheduled.
	 */
	private static function off_schedule_state( array $window ) {
		list( $start, $end, $scheduled ) = $window;
		$now                             = time();
		if ( $start && $now < $start ) {
			return 'upcoming';
		}
		if ( $end && $now > $end ) {
			return 'ended';
		}
		return $scheduled && ! $start && ! $end ? 'nodates' : 'scheduled';
	}

	/**
	 * The date a row's schedule line names, in the site's own format.
	 *
	 * @param string $state  Row state.
	 * @param array  $window From window().
	 * @return string
	 */
	private static function when( $state, array $window ) {
		list( $start, $end ) = $window;
		$ts                  = 0;
		if ( 'upcoming' === $state ) {
			$ts = $start;
		} elseif ( 'ended' === $state ) {
			$ts = $end;
		} elseif ( in_array( $state, array( 'live', 'hidden' ), true ) && $end > time() ) {
			$ts = $end;
		}
		return $ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '';
	}

	/**
	 * Where a bundle is placed: only on this product, by category, or on a list.
	 *
	 * @param array $config Raw bundle config.
	 * @return string
	 */
	private static function scope( array $config ) {
		$selection = is_array( $config['productSelection'] ?? null ) ? $config['productSelection'] : array();
		$mode      = (int) ( $selection['mode'] ?? Bundle_Store::MODE_NONE );
		if ( Bundle_Store::MODE_SPECIFIC_CATEGORIES === $mode ) {
			return 'category';
		}
		return 1 === count( (array) ( $selection['products'] ?? array() ) ) ? 'product' : 'shared';
	}

	/**
	 * Every string the script draws.
	 *
	 * @return array<string,string>
	 */
	private static function strings() {
		return array(
			'activating'     => __( 'Activating…', 'funnel-builder' ),
			'activated'      => __( 'Bundles is now on.', 'funnel-builder' ),
			'offText'        => __( 'Bundles is turned off on your store', 'funnel-builder' ),
			'offDesc'        => __( 'You can access Bundles easily by activating it.', 'funnel-builder' ),
			'turnOn'         => __( 'Activate', 'funnel-builder' ),
			'decline'        => __( "I don't want bundles", 'funnel-builder' ),
			'declining'      => __( 'Turning off…', 'funnel-builder' ),
			'declined'       => __( 'Bundles is turned off. You can turn it back on at any time.', 'funnel-builder' ),
			'bundles'        => __( 'Bundles', 'funnel-builder' ),
			'bundle'         => __( 'Bundle', 'funnel-builder' ),
			'type'           => __( 'Type', 'funnel-builder' ),
			'bundleType'     => __( 'Bundle type', 'funnel-builder' ),
			'status'         => __( 'Status', 'funnel-builder' ),
			'actions'        => __( 'Actions', 'funnel-builder' ),
			'scopeProduct'   => __( 'Only this product', 'funnel-builder' ),
			'scopeShared'    => __( 'Shared with other products', 'funnel-builder' ),
			'scopeCategory'  => __( 'Placed by product category', 'funnel-builder' ),
			/* translators: %s: name of the bundle the product page shows instead. */
			'live'           => __( 'Live', 'funnel-builder' ),
			'draft'          => __( 'Draft', 'funnel-builder' ),
			'scheduled'      => __( 'Not running', 'funnel-builder' ),
			'offSchedule'    => __( 'Outside its schedule', 'funnel-builder' ),
			'upcoming'       => __( 'Scheduled', 'funnel-builder' ),
			'ended'          => __( 'Expired', 'funnel-builder' ),
			/* translators: %s: date and time the schedule starts. */
			'startsOn'       => __( 'Starts %s', 'funnel-builder' ),
			/* translators: %s: date and time the schedule ended. */
			'endedOn'        => __( 'Ended %s', 'funnel-builder' ),
			/* translators: %s: date and time the schedule ends. */
			'runsUntil'      => __( 'Runs until %s', 'funnel-builder' ),
			'noDates'        => __( 'Scheduled, but no start or end date is set', 'funnel-builder' ),
			'typeUnavailable' => __( 'Its bundle type is not available on this site', 'funnel-builder' ),
			'preview'        => __( 'Preview', 'funnel-builder' ),
			'edit'           => __( 'Edit', 'funnel-builder' ),
			'pause'          => __( 'Pause', 'funnel-builder' ),
			'goLive'         => __( 'Go live', 'funnel-builder' ),
			'remove'         => __( 'Remove', 'funnel-builder' ),
			'actionCol'      => __( 'Action', 'funnel-builder' ),
			/* translators: %s: bundle name. */
			'removeConfirm'  => __( 'Remove this product from "%s"? The bundle is kept and stops showing on this product.', 'funnel-builder' ),
			'removed'        => __( 'Removed from the bundle.', 'funnel-builder' ),
			'removedDraft'   => __( 'Removed. This was the bundle\'s only product, so it is now a draft.', 'funnel-builder' ),
			'tagShowing'     => __( 'Visible', 'funnel-builder' ),
			'tagNotShowing'  => __( 'Hidden', 'funnel-builder' ),
			'tagDraftWhy'    => __( 'A draft: drag it to the top to show it.', 'funnel-builder' ),
			'assignTitle'    => __( 'Add this product to existing bundles', 'funnel-builder' ),
			'assignHelp'     => __( 'Pick bundles you have already created. This product becomes one of the products each one shows on.', 'funnel-builder' ),
			'assignPlaceholder' => __( 'Choose bundles…', 'funnel-builder' ),
			'assignButton'   => __( 'Add to bundles', 'funnel-builder' ),
			'assignDraft'    => __( 'draft', 'funnel-builder' ),
			/* translators: %d: number of bundles. */
			'assignedMany'   => __( 'Added to %d bundles.', 'funnel-builder' ),
			'assignedOne'    => __( 'Added to the bundle.', 'funnel-builder' ),
			/* translators: 1: bundle name, 2: reason. */
			'assignFailed'   => __( '%1$s: %2$s', 'funnel-builder' ),
			'addBundle'      => __( 'Add bundle', 'funnel-builder' ),
			/* translators: %s: link to the bundles list. */
			'manageAll'      => __( 'Manage every bundle under %s.', 'funnel-builder' ),
			'manageLink'     => __( 'FunnelKit → Bundles', 'funnel-builder' ),
			'back'           => __( 'Bundles on this product', 'funnel-builder' ),
			'newBundle'      => __( 'New bundle', 'funnel-builder' ),
			'bundleName'     => __( 'Title', 'funnel-builder' ),
			'products'       => __( 'Products', 'funnel-builder' ),
			'thisProduct'    => __( 'This product', 'funnel-builder' ),
			'searchProducts' => __( 'Search products to add…', 'funnel-builder' ),
			'discount'       => __( 'Discount', 'funnel-builder' ),
			'discountType'   => __( 'Discount type', 'funnel-builder' ),
			'percentage'     => __( 'Percentage', 'funnel-builder' ),
			'fixedAmount'    => __( 'Fixed amount', 'funnel-builder' ),
			'noDiscount'     => __( 'No Discount', 'funnel-builder' ),
			'heading'        => __( 'Heading', 'funnel-builder' ),
			'preview_h'      => __( 'Preview', 'funnel-builder' ),
			/* translators: %s: product or tier name. */
			'removeItem'     => __( 'Remove %s', 'funnel-builder' ),
			'create'         => __( 'Create Bundle', 'funnel-builder' ),
			'creating'       => __( 'Creating…', 'funnel-builder' ),
			'created'        => __( 'Bundle created and live.', 'funnel-builder' ),
			'refreshFailed'  => __( 'The bundle was created, but this list could not refresh. Reload the page to see it.', 'funnel-builder' ),
			'empty'          => __( 'No bundles on this product yet.', 'funnel-builder' ),
			'cancel'         => __( 'Cancel', 'funnel-builder' ),
			'saveFirst'      => __( 'Save or publish the product first, then create the bundle.', 'funnel-builder' ),
			'error'          => __( 'Something went wrong. Please try again.', 'funnel-builder' ),
			'loading'        => __( 'Loading bundles…', 'funnel-builder' ),
			'retry'          => __( 'Try again', 'funnel-builder' ),
			'needName'       => __( 'Give the bundle a title.', 'funnel-builder' ),
			'needProducts'   => __( 'Add at least one product.', 'funnel-builder' ),
			'needOneMore'    => __( 'Add 1 more product to make a bundle.', 'funnel-builder' ),
			/* translators: %d: number of products still needed, 2 or more. */
			'needMore'       => __( 'Add %d more products to make a bundle.', 'funnel-builder' ),
			'addSelfBack'    => __( 'Add this product back', 'funnel-builder' ),
			'activatePro'    => __( 'Activate Pro', 'funnel-builder' ),
			'activatingPro'  => __( 'Activating…', 'funnel-builder' ),
			/* translators: %s: add-on plugin name. */
			'proInactive'    => __( '%s is installed but not active. Activate it to create Mix & Match bundles.', 'funnel-builder' ),
			/* translators: %s: add-on plugin name. */
			'proAskAdmin'    => __( '%s is installed but not active. Ask a site administrator to activate it.', 'funnel-builder' ),
			'badPercent'     => __( 'Enter a discount from 0 to 100%.', 'funnel-builder' ),
			'badAmount'      => __( 'Enter a discount of 0 or more.', 'funnel-builder' ),
			'noResults'      => __( 'No products found.', 'funnel-builder' ),
			'searching'      => __( 'Searching…', 'funnel-builder' ),
			'typeOneMore'    => __( 'Type 1 more character to search.', 'funnel-builder' ),
			/* translators: %d: number of characters still to type, 2 or more. */
			'typeMore'       => __( 'Type %d more characters to search.', 'funnel-builder' ),
			'nextProduct'    => __( 'Next product', 'funnel-builder' ),
			'add'            => __( '+ Add', 'funnel-builder' ),
			'added'          => __( 'Added', 'funnel-builder' ),
			'setPrice'       => __( 'Set price', 'funnel-builder' ),
			'addToCart'      => __( 'Add bundle to cart', 'funnel-builder' ),
			/* translators: %d: quantity. */
			'buyN'           => __( 'Buy %d', 'funnel-builder' ),
			/* translators: %s: discount, such as "20%" or "$5.00". */
			'saveX'          => __( 'Save %s', 'funnel-builder' ),
			'regularPrice'   => __( 'Regular price', 'funnel-builder' ),
			/* translators: %d: number of bundle items not drawn in the preview. */
			'moreUnits'      => __( '+%d more', 'funnel-builder' ),
			/* translators: %s: product name. */
			'kitName'        => __( '%s Kit', 'funnel-builder' ),
			/* translators: 1: product name, 2: bundle type. */
			'typedName'      => __( '%1$s - %2$s', 'funnel-builder' ),
			'fixedHeading'   => __( 'Buy together & save', 'funnel-builder' ),
			'fbtHeading'     => __( 'Frequently bought together', 'funnel-builder' ),
			'fbtScope'       => __( 'Apply discount on', 'funnel-builder' ),
			'fbtCompanions'  => __( 'Added items only', 'funnel-builder' ),
			'fbtSet'         => __( 'The whole set', 'funnel-builder' ),
			'fbtNoDiscount'  => __( 'No discount', 'funnel-builder' ),
			'discountAmount' => __( 'Discount amount', 'funnel-builder' ),
			/* translators: %d: the most products a set shows beside the one being edited. */
			'fbtFull'        => __( 'A set shows up to %d products with this one.', 'funnel-builder' ),
			'fbtFallback'    => __( 'Which products to show', 'funnel-builder' ),
			'fbtManual'      => __( 'Choose products manually', 'funnel-builder' ),
			'fbtCross'       => __( 'Use its cross-sells', 'funnel-builder' ),
			'fbtCrossUp'     => __( 'Use its cross-sells, then upsells', 'funnel-builder' ),
			'fbtNothing'     => __( 'Show nothing', 'funnel-builder' ),
			'fbtMax'         => __( 'Show at most', 'funnel-builder' ),
			'fbtProducts'    => __( 'products', 'funnel-builder' ),
			/* translators: %d: the most products the widget can show. */
			'fbtBadMax'      => __( 'Show between 1 and %d products.', 'funnel-builder' ),
			'fbtNeedProduct' => __( 'Add at least one product', 'funnel-builder' ),
			/* translators: %s: product name. */
			'fbtNoLinked'    => __( '%s has no cross-sells set', 'funnel-builder' ),
			/* translators: %s: product name. */
			'fbtNoLinkedUp'  => __( '%s has no cross-sells or upsells set', 'funnel-builder' ),
			'fbtThis'        => __( 'This item:', 'funnel-builder' ),
			'fbtTotal'       => __( 'Total:', 'funnel-builder' ),
			/* translators: %s: the amount saved on the set. */
			'fbtSave'        => __( 'You save %s', 'funnel-builder' ),
			/* translators: %d: number of products the button adds. */
			'fbtAddOne'      => _n( 'Add %d item to cart', 'Add %d items to cart', 1, 'funnel-builder' ),
			/* translators: %d: number of products the button adds. */
			'fbtAddMany'     => _n( 'Add %d item to cart', 'Add %d items to cart', 2, 'funnel-builder' ),
			'pro'            => __( 'Pro', 'funnel-builder' ),
			'upgrade'        => __( 'Upgrade to Pro', 'funnel-builder' ),
			'pitchText'      => __( 'Let customers build their own bundle by choosing products from a selection you curate.', 'funnel-builder' ),
			'pitchPoint1'    => __( 'Shoppers pick their own items from a pool you choose.', 'funnel-builder' ),
			'pitchPoint2'    => __( 'One discount unlocks when the box is full.', 'funnel-builder' ),
			'pitchNote'      => __( 'Mix & Match comes with the premium plan.', 'funnel-builder' ),
			'pitchHeading'   => __( 'Pick any 3 & save', 'funnel-builder' ),
			'needsPro'       => __( 'Needs Pro', 'funnel-builder' ),
			'needsProSub'    => __( 'Shows on your store once Pro is active', 'funnel-builder' ),
			'priority'       => __( 'Priority', 'funnel-builder' ),
			/* translators: %s: bundle name. */
			'moveRow'        => __( 'Reorder %s', 'funnel-builder' ),
			'sortHelp'       => __( 'Drag the handles, or use the arrow keys on one, to set the order. The highest live bundle is the one this product shows.', 'funnel-builder' ),
			/* translators: 1: bundle name, 2: its new position, 3: number of bundles. */
			'movedTo'        => __( '%1$s moved to position %2$d of %3$d.', 'funnel-builder' ),
			/* translators: %s: bundle name. */
			'nowShows'       => __( '%s now shows on this product.', 'funnel-builder' ),
		);
	}
}
