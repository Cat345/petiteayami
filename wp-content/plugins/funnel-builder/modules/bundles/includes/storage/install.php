<?php
/**
 * Activation, upgrade and deactivation scaffolding for the data layer.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the plugin tables, owns the bundle container product and stamps the versions.
 *
 * The stamps are re-checked on every `plugins_loaded`, so updates that never fire
 * the activation hook still converge on the current schema. Option and transient
 * keys are defined here as constants and never written as literals at a call site.
 */
class Install {

	/** Installed plugin-lifecycle version, covering the container product. */
	const OPTION_DB_VERSION = 'wffn_bundles_db_version';

	/**
	 * Installed table schema version, gated on WFFN_BUNDLES_DB_VERSION, not the plugin version.
	 *
	 * Written unconditionally, unlike OPTION_DB_VERSION: tables do not depend on
	 * WooCommerce being active.
	 */
	const OPTION_SCHEMA_VERSION = 'wffn_bundles_schema_version';

	/** Container product id; written here only, and read by the cart. */
	const OPTION_CONTAINER_ID = 'wffn_bundles_bundle_container_id';

	/**
	 * Autoloaded `yes`/`no`: whether this site has ever saved a bundle.
	 *
	 * Until it has, the plugin stays dormant: no tables on public requests, no
	 * container product and no storefront hooks. Never reset once `yes`.
	 */
	const OPTION_IN_USE = 'wffn_bundles_in_use';

	/** @var bool|null Request memo for is_in_use(). */
	private static $in_use = null;

	/**
	 * Autoloaded `enabled`/`disabled`: whether the merchant has switched Bundles on or off.
	 *
	 * Absent until the merchant chooses, which is its own state; never deleted once set.
	 */
	const OPTION_STATE = 'wffn_bundles_state';

	/** The merchant switched Bundles on. */
	const STATE_ENABLED = 'enabled';

	/** The merchant switched Bundles off. */
	const STATE_DISABLED = 'disabled';

	/** Settings key carrying the state; stored in OPTION_STATE, never in the settings blob. */
	const SETTINGS_STATE = 'bundlesState';

	/** AdminSettings JSON blob, read and written by the settings controller. */
	const OPTION_SETTINGS = 'wffn_bundles_settings';

	/**
	 * Rendered skin-preview HTML documents.
	 *
	 * Self-invalidating: the payload carries a plugin-version and price-format
	 * signature and is rebuilt on any mismatch.
	 */
	const TRANSIENT_SKINS = 'wffn_bundles_skin_previews';

	/** Key inside `wffn_bundles_settings` holding the global product-options display mode (auto|inline|modal). */
	const SETTINGS_VARIATION_OPTIONS_DISPLAY = 'variationOptionsDisplay';

	/** Key inside `wffn_bundles_settings` naming where the widget renders on a product page. */
	const SETTINGS_PLACEMENT = 'productPlacement';

	/**
	 * Postmeta key backing up a checkout page's original product rows across a
	 * deactivate/activate cycle. Set only while this plugin is inactive.
	 */
	const TAGGED_ROWS_BACKUP = '_wffn_bundles_tagged_products_backup';

	/**
	 * Post type + product-list meta key pairs this plugin tags rows on: Funnel
	 * Builder's checkout pages' own Product Switcher, whose rows carry
	 * `products_meta_data[1]` with wffn_bundles_bundle_id etc.
	 */
	const TAGGED_ROW_SOURCES = array(
		array(
			'post_type'    => 'wfacp_checkout',
			'products_key' => '_wfacp_selected_products',
		),
	);

	/**
	 * Activation hook: install on this site, or on every site when network-activated.
	 *
	 * @param bool $network_wide Whether the plugin was network-activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Network-wide activation sets up each site's own tables; there is no cross-site API for it.
				switch_to_blog( (int) $blog_id );
				self::activate_site();
				restore_current_blog();
			}
			return;
		}
		self::activate_site();
	}

	/**
	 * The current site's half of activate(); a site with no bundle yet installs when it opens Bundles.
	 *
	 * @return void
	 */
	private static function activate_site() {
		self::$in_use = null;
		if ( ! self::is_in_use() ) {
			return;
		}
		// Nothing dropped a product's index or its shaped rows while the host plugin was off.
		Product_Placement::forget_all();
		\WFFN\Bundles\Includes\Products\Repository::forget_all_shapes();
		self::install();
		self::restore_tagged_row_associations();
	}

	/**
	 * `plugins_loaded` check: re-run the installer when the stored version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		self::maybe_create_tables();

		// The stamp below waits for the container, which a site gets with its first bundle.
		if ( WFFN_BUNDLES_VERSION === get_option( self::OPTION_DB_VERSION ) || ! self::is_in_use() ) {
			return;
		}
		self::install();
	}

	/**
	 * Create or diff the tables when the schema stamp is behind.
	 *
	 * @return void
	 */
	private static function maybe_create_tables() {
		if ( WFFN_BUNDLES_DB_VERSION === get_option( self::OPTION_SCHEMA_VERSION ) ) {
			return;
		}
		self::create_tables();
		update_option( self::OPTION_SCHEMA_VERSION, WFFN_BUNDLES_DB_VERSION, true );
	}

	/**
	 * Deactivation: keep all data and drop no tables; only stale transients go.
	 *
	 * @param bool $network_wide Whether the plugin is being network-deactivated.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Network-wide deactivation cleans up each site's own data; there is no cross-site API for it.
				switch_to_blog( (int) $blog_id );
				self::deactivate_site();
				restore_current_blog();
			}
			return;
		}
		self::deactivate_site();
	}

	/**
	 * The current site's half of deactivate().
	 *
	 * @return void
	 */
	private static function deactivate_site() {
		self::$in_use = null;
		delete_transient( self::TRANSIENT_SKINS );
		if ( ! self::is_in_use() ) {
			return;
		}
		self::strip_tagged_row_associations();
		self::strip_bundle_cart_lines();
		self::hide_container_product();
	}

	/**
	 * Take the $0 container product off sale while nothing guards it.
	 *
	 * Dropping the version stamp makes maybe_ensure_container_product() re-check it on the next load.
	 *
	 * @return void
	 */
	private static function hide_container_product() {
		$id = (int) get_option( self::OPTION_CONTAINER_ID, 0 );
		if ( $id > 0 && 'product' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'private',
				)
			);
		}
		delete_option( self::OPTION_DB_VERSION );
	}

	/**
	 * Hide this plugin's own footprint from the Product Switcher's stored
	 * product rows, after saving each original so activate()
	 * can put it back unchanged.
	 *
	 * A row tagged with wffn_bundles_bundle_id/wffn_bundles_offer only means something while this
	 * plugin is there to explain it (Adapter::describe_row() in the Funnel Builder
	 * compat); left behind, Funnel
	 * Builder's own native handling misreads it instead — a container-backed row
	 * shows the shared $0 "Bundle" placeholder as a purchasable option, and a
	 * variable row's leftover `variable` flag routes it to a native "choose an
	 * option" popup for a bundle it cannot render. That is what left the
	 * checkout showing broken/duplicated rows once this plugin stopped explaining
	 * them. The merchant's configuration must survive the round trip, so nothing
	 * here is deleted: the exact original array is kept in TAGGED_ROWS_BACKUP
	 * and restored verbatim on the next activate(). The live copy is what Funnel
	 * Builder reads meanwhile — a row pinned to the shared container placeholder
	 * sells nothing on its own without a bundle, so it is dropped from that
	 * copy only; any other row keeps selling its real product, just as a plain
	 * row again.
	 *
	 * @return void
	 */
	private static function strip_tagged_row_associations() {
		$container_id = (int) get_option( self::OPTION_CONTAINER_ID, 0 );
		foreach ( self::TAGGED_ROW_SOURCES as $source ) {
			// The same arguments get_posts() would add: no filters, no sticky posts, no row count, newest first.
			$posts = ( new \WP_Query(
				array(
					'post_type'           => $source['post_type'],
					'post_status'         => 'any',
					'posts_per_page'      => -1,
					'fields'              => 'ids',
					'suppress_filters'    => true,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'date',
					'order'               => 'DESC',
				)
			) )->posts;
			foreach ( $posts as $post_id ) {
				self::strip_tagged_rows( $post_id, $source['products_key'], $container_id );
			}
		}
	}

	/**
	 * Back up and strip one post's tagged product rows. Shared by every source
	 * in TAGGED_ROW_SOURCES.
	 *
	 * @param int    $post_id      Checkout post id.
	 * @param string $meta_key     Meta key holding that post's product rows.
	 * @param int    $container_id This plugin's shared container placeholder id.
	 * @return void
	 */
	private static function strip_tagged_rows( $post_id, $meta_key, $container_id ) {
		// A second deactivate before the next activate must not clobber the
		// backup with the already-stripped copy.
		if ( '' !== get_post_meta( $post_id, self::TAGGED_ROWS_BACKUP, true ) ) {
			return;
		}
		$products = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_array( $products ) || array() === $products ) {
			return;
		}
		$tagged = false;
		foreach ( $products as $row ) {
			$meta = is_array( $row ) && isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
			if ( isset( $meta['wffn_bundles_bundle_id'] ) || isset( $meta['wffn_bundles_offer'] ) || isset( $meta['wffn_bundles_anchor'] ) ) {
				$tagged = true;
				break;
			}
		}
		if ( ! $tagged ) {
			return;
		}
		update_post_meta( $post_id, self::TAGGED_ROWS_BACKUP, $products );
		update_post_meta( $post_id, $meta_key, self::stripped_rows( $products, $container_id ) );
	}

	/**
	 * A checkout page's product rows without this plugin's tagging.
	 *
	 * @param array $products     Product rows as stored.
	 * @param int   $container_id This plugin's shared container placeholder id.
	 * @return array
	 */
	private static function stripped_rows( array $products, $container_id ) {
		foreach ( $products as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$meta = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
			if ( ! isset( $meta['wffn_bundles_bundle_id'] ) && ! isset( $meta['wffn_bundles_offer'] ) && ! isset( $meta['wffn_bundles_anchor'] ) ) {
				continue;
			}
			if ( $container_id > 0 && absint( $row['id'] ?? 0 ) === $container_id ) {
				unset( $products[ $key ] );
				continue;
			}
			$products[ $key ]['products_meta_data'] = array();
		}
		return $products;
	}

	/**
	 * Put back every checkout page's original product rows that strip_tagged_row_associations()
	 * hid while this plugin was off. A row edited, removed or added since is left as it now is.
	 *
	 * @return void
	 */
	private static function restore_tagged_row_associations() {
		foreach ( self::TAGGED_ROW_SOURCES as $source ) {
			// The same arguments get_posts() would add: no filters, no sticky posts, no row count, newest first.
			$posts = ( new \WP_Query(
				array(
					'post_type'           => $source['post_type'],
					'post_status'         => 'any',
					'posts_per_page'      => -1,
					'fields'              => 'ids',
					'meta_key'            => self::TAGGED_ROWS_BACKUP,
					'suppress_filters'    => true,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'date',
					'order'               => 'DESC',
				)
			) )->posts;
			foreach ( $posts as $post_id ) {
				$original = get_post_meta( $post_id, self::TAGGED_ROWS_BACKUP, true );
				if ( is_array( $original ) ) {
					$current  = get_post_meta( $post_id, $source['products_key'], true );
					$current  = is_array( $current ) ? $current : array();
					$stripped = self::stripped_rows( $original, (int) get_option( self::OPTION_CONTAINER_ID, 0 ) );
					$restored = array();
					foreach ( $original as $key => $row ) {
						if ( array_key_exists( $key, $current ) ) {
							$restored[ $key ] = array_key_exists( $key, $stripped ) && $current[ $key ] === $stripped[ $key ] ? $row : $current[ $key ];
						} elseif ( ! array_key_exists( $key, $stripped ) ) {
							$restored[ $key ] = $row;
						}
					}
					update_post_meta( $post_id, $source['products_key'], $restored + $current );
				}
				delete_post_meta( $post_id, self::TAGGED_ROWS_BACKUP );
			}
		}
	}

	/**
	 * Drop every cart line this plugin priced, from every stored session and every
	 * logged-in customer's persistent cart.
	 *
	 * `Cart::apply_pricing()` is the only thing that zeroes a bundle's child lines
	 * and prices its container; gone with this plugin, WooCommerce reprices a
	 * leftover line at the product's own catalogue price instead, silently, for
	 * whoever already has one in their cart. Stripped rather than left, so no
	 * total is ever wrong; the shopper can add the bundle back once this plugin
	 * is active again.
	 *
	 * @return void
	 */
	private static function strip_bundle_cart_lines() {
		global $wpdb;

		$sessions = $wpdb->get_results( $wpdb->prepare( 'SELECT session_id, session_value FROM %i WHERE session_value LIKE %s', $wpdb->prefix . 'woocommerce_sessions', '%' . $wpdb->esc_like( 'wffn_bundles_bundle' ) . '%' ) );
		foreach ( (array) $sessions as $row ) {
			$data = maybe_unserialize( $row->session_value );
			if ( ! is_array( $data ) || ! isset( $data['cart'] ) ) {
				continue;
			}
			$cart = maybe_unserialize( $data['cart'] );
			if ( ! is_array( $cart ) || array() === $cart ) {
				continue;
			}
			$clean = self::without_bundle_lines( $cart );
			if ( count( $clean ) === count( $cart ) ) {
				continue;
			}
			$data['cart'] = array() === $clean ? null : maybe_serialize( $clean );
			$wpdb->update(
				$wpdb->prefix . 'woocommerce_sessions',
				array( 'session_value' => maybe_serialize( $data ) ),
				array( 'session_id' => $row->session_id )
			);
		}

		self::strip_persistent_carts();
	}

	/**
	 * Drop every bundle line from a logged-in customer's persistent cart.
	 *
	 * WooCommerce restores from this user meta when a session has already expired,
	 * so it needs the same stripping strip_bundle_cart_lines() does for the live
	 * session table, or a returning customer would get the stale line back.
	 *
	 * @return void
	 */
	private static function strip_persistent_carts() {
		$meta_key = '_woocommerce_persistent_cart_' . get_current_blog_id();
		// blog_id 0: every user holding the key, as user meta is network-wide.
		$user_ids = get_users(
			array(
				'blog_id'      => 0,
				'fields'       => 'ID',
				'meta_key'     => $meta_key,
				'meta_value'   => 'wffn_bundles_bundle',
				'meta_compare' => 'LIKE',
			)
		);
		foreach ( (array) $user_ids as $user_id ) {
			$value = get_user_meta( (int) $user_id, $meta_key, true );
			if ( ! is_array( $value ) || ! isset( $value['cart'] ) || ! is_array( $value['cart'] ) || array() === $value['cart'] ) {
				continue;
			}
			$clean = self::without_bundle_lines( $value['cart'] );
			if ( count( $clean ) === count( $value['cart'] ) ) {
				continue;
			}
			$value['cart'] = $clean;
			update_user_meta( (int) $user_id, $meta_key, $value );
		}
	}

	/**
	 * A cart contents array with every `wffn_bundles_bundle`-tagged line removed.
	 *
	 * Every role a bundle line can hold (container, child, anchor, member, an
	 * add-on riding the same group) carries this one key, so dropping by its
	 * presence needs no role list to stay in sync with.
	 *
	 * @param array $cart Cart contents, keyed by cart item key.
	 * @return array
	 */
	private static function without_bundle_lines( array $cart ) {
		foreach ( $cart as $key => $item ) {
			if ( is_array( $item ) && isset( $item['wffn_bundles_bundle'] ) ) {
				unset( $cart[ $key ] );
			}
		}

		return $cart;
	}

	/**
	 * Idempotent installer: dbDelta the tables, stamp the schema version.
	 *
	 * The container product is deliberately not created here: this runs from
	 * `plugins_loaded`/the activation hook, both ahead of `init`, where
	 * WooCommerce registers the `product_visibility` taxonomy the container
	 * needs for `set_catalog_visibility( 'hidden' )` to actually apply. See
	 * maybe_ensure_container_product().
	 *
	 * @return void
	 */
	private static function install() {
		self::create_tables();
		// Unconditional: tables do not depend on WooCommerce.
		update_option( self::OPTION_SCHEMA_VERSION, WFFN_BUNDLES_DB_VERSION, true );
		self::autoload_settings();
		// Read on every product page; a missing option is a query each time.
		add_option( Bundle_Repository::OPTION_VERSION, 1 );
	}

	/**
	 * Whether this site has saved a bundle, so the plugin runs in full.
	 *
	 * @return bool
	 */
	public static function is_in_use() {
		if ( null !== self::$in_use ) {
			return self::$in_use;
		}

		$flag = get_option( self::OPTION_IN_USE );
		if ( 'yes' !== $flag && 'no' !== $flag ) {
			// Stored, autoloaded, so a site that has not saved a bundle yet does not query for the flag on every request.
			$flag = 'no';
			add_option( self::OPTION_IN_USE, $flag, '', true );
		}

		/**
		 * Filters whether Bundles runs in full on this request.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $in_use True once the site has saved a bundle.
		 */
		self::$in_use = (bool) apply_filters( 'wffn_bundles_in_use', 'yes' === $flag );
		return self::$in_use;
	}

	/**
	 * Whether the merchant has switched Bundles on or off.
	 *
	 * @return string|null STATE_ENABLED, STATE_DISABLED, or null when never chosen.
	 */
	public static function get_state() {
		$state = get_option( self::OPTION_STATE );
		if ( self::STATE_ENABLED === $state || self::STATE_DISABLED === $state ) {
			return $state;
		}
		return null;
	}

	/**
	 * Store the merchant's choice; anything but a known state is ignored.
	 *
	 * @param string $state STATE_ENABLED or STATE_DISABLED.
	 * @return bool Whether the value was a known state.
	 */
	public static function set_state( $state ) {
		if ( self::STATE_ENABLED !== $state && self::STATE_DISABLED !== $state ) {
			return false;
		}
		$was = get_option( self::OPTION_STATE );
		if ( $was !== $state ) {
			update_option( self::OPTION_STATE, $state, true );
		}
		// Switching on is when the tables are first made; a site already holding bundles has them.
		if ( self::STATE_ENABLED === $state && self::STATE_ENABLED !== $was ) {
			self::maybe_create_tables();
		}
		// Nothing dropped a product's index while switched off.
		if ( self::STATE_ENABLED === $state && self::STATE_DISABLED === $was && self::is_in_use() ) {
			Product_Placement::forget_all();
			\WFFN\Bundles\Includes\Products\Repository::forget_all_shapes();
		}
		return true;
	}

	/**
	 * Switch the plugin to full mode after the first bundle is saved.
	 *
	 * @return void
	 */
	public static function mark_in_use() {
		// A bundle saved before the merchant chose switches Bundles on; a choice of off stands.
		if ( null === self::get_state() ) {
			self::set_state( self::STATE_ENABLED );
		}
		if ( 'yes' === get_option( self::OPTION_IN_USE ) ) {
			return;
		}
		update_option( self::OPTION_IN_USE, 'yes', true );
		self::$in_use = null;
		// The first save made only the tables; the stamp below would skip the rest of the install.
		self::install();
		if ( did_action( 'init' ) ) {
			self::maybe_ensure_container_product();
		}
	}

	/**
	 * Keep the settings option in the autoloaded set, saved or not.
	 *
	 * A missing option is a query on every request it is read on, and so is one
	 * stored outside the autoloaded set.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function autoload_settings() {
		if ( false === get_option( self::OPTION_SETTINGS ) ) {
			add_option( self::OPTION_SETTINGS, array() );
			return;
		}
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION_SETTINGS, true );
		}
	}

	/**
	 * `init` check: ensure the container product once WooCommerce's taxonomies
	 * are registered, then stamp the version that gates this retry loop.
	 *
	 * OPTION_DB_VERSION is withheld while WooCommerce is inactive (or its
	 * taxonomies aren't ready yet), so this keeps retrying on every `init`
	 * until the container product exists with the right visibility term.
	 *
	 * @return void
	 */
	public static function maybe_ensure_container_product() {
		if ( ! self::is_in_use() || WFFN_BUNDLES_VERSION === get_option( self::OPTION_DB_VERSION ) ) {
			return;
		}
		if ( self::ensure_container_product() > 0 ) {
			update_option( self::OPTION_DB_VERSION, WFFN_BUNDLES_VERSION, true );
			// Deactivation drops the stamp; a module coming back gets no activation hook to restore them.
			self::restore_tagged_row_associations();
		}
	}

	/**
	 * Create or diff every plugin table; dbDelta is idempotent.
	 *
	 * @return void
	 */
	private static function create_tables() {
		Bundles_Table::create_table();

		/**
		 * Fires after this plugin has created or diffed its tables.
		 *
		 * An add-on keeping tables beside these creates its own here.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wffn_bundles_tables_created' );
	}

	/**
	 * Ensure the hidden placeholder product used as a bundle's container line exists.
	 *
	 * The product carries no price of its own; the bundle total is stored per cart
	 * item. Never created on a public request. Kept private: only the running cart
	 * makes it purchasable (Cart::container_purchasable()), so it cannot be sold
	 * on its own once this plugin stops loading.
	 *
	 * @return int Container product id, or 0 on failure.
	 */
	public static function ensure_container_product() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 0;
		}

		$id = (int) get_option( self::OPTION_CONTAINER_ID, 0 );
		if ( $id > 0 && 'product' === get_post_type( $id ) ) {
			// Read on most cart, checkout and AJAX requests; an install from before kept it out of the autoloaded set.
			if ( function_exists( 'wp_set_option_autoload' ) ) {
				wp_set_option_autoload( self::OPTION_CONTAINER_ID, true );
			}
			if ( 'private' !== get_post_status( $id ) ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'private',
					)
				);
			}
			return $id;
		}

		$product = new \WC_Product_Simple();
		$product->set_name( __( 'Bundle', 'funnel-builder' ) );
		$product->set_status( 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( false );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$id = (int) $product->save();

		if ( $id > 0 ) {
			update_option( self::OPTION_CONTAINER_ID, $id, true );
		}
		return $id;
	}
}
