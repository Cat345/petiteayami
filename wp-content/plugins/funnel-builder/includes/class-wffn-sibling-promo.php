<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Sibling_Promo' ) ) {
	/**
	 * Promotes one other FunnelKit product at a time, through Funnel Builder's own page for it,
	 * in the FunnelKit submenu and in the app header. Rotation applies to products whose entry
	 * is temporary (Subscriptions, Automations): shown for a while, snoozed when ignored,
	 * dropped once the product is set up. FunnelKit Cart is not one of them: its entry is
	 * permanent and lives in WFFN_Admin::get_cart_menu_item().
	 *
	 * Which product shows is decided by WFFN_Sibling_Promo_Picker from the state kept in a single
	 * non-autoloaded option. This class only gathers the live facts (presence, set-up, a dismiss
	 * made on the product's page), persists state changes and registers the entry.
	 */
	class WFFN_Sibling_Promo {

		const OPTION = 'wffn_sibling_promo_state';

		private static $ins = null;

		/**
		 * Key of the product promoted on this request, '' when none.
		 *
		 * @var string
		 */
		public $shown = '';

		public function __construct() {
			add_action( 'activated_plugin', array( $this, 'on_activated_plugin' ) );
			add_action( 'admin_menu', array( $this, 'sort_menu' ), PHP_INT_MAX );
		}

		/**
		 * @return WFFN_Sibling_Promo
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Products in priority order. 'path' is Funnel Builder's page for the product, also its key in
		 * WFFN_Header::left_navigation(); 'skip' is the fb_site_options flag that page's dismiss button
		 * sets. Plugin files are used only to recognise an activation; presence is detected through
		 * each product's own version constant. Every product here is rotated, snoozed and dismissable.
		 *
		 * @return array
		 */
		public function get_products() {
			$products = array(
				'sublium'     => array(
					'title'    => __( 'Subscriptions', 'funnel-builder' ),
					'path'     => 'subscriptions',
					'skip'     => 'skip_subscriptions_page',
					'plugins'  => array( 'sublium-subscriptions-for-woocommerce/sublium-subscriptions-for-woocommerce.php' ),
					'constant' => 'SUBLIUM_WCS_VERSION',
					'seed'     => array(
						'option' => 'sublium_wcs_activation_date',
						'utc'    => true,
					),
					'detector' => array( $this, 'is_sublium_set_up' ),
				),
				'automations' => array(
					'title'    => __( 'Automations', 'funnel-builder' ),
					'path'     => 'automations',
					'skip'     => 'skip_automation_page',
					'plugins'  => array( 'wp-marketing-automations/wp-marketing-automations.php', 'wp-marketing-automations-pro/wp-marketing-automations-pro.php' ),
					'constant' => 'BWFAN_VERSION',
					'seed'     => array(
						'option' => 'bwfan_ver_1_0',
						'utc'    => true,
					),
					'detector' => array( $this, 'is_automations_set_up' ),
				),
			);

			$products = apply_filters( 'wffn_sibling_promo_products', $products );

			return is_array( $products ) ? array_filter( $products, array( $this, 'is_valid_product' ) ) : array();
		}

		/**
		 * @param mixed $product Product definition.
		 *
		 * @return bool
		 */
		public function is_valid_product( $product ) {
			return is_array( $product ) && ! empty( $product['title'] ) && ! empty( $product['path'] );
		}

		/**
		 * @return array
		 */
		public function get_config() {
			$picker = $this->load_picker();
			$config = apply_filters( 'wffn_sibling_promo_config', $picker::default_config() );
			$config = is_array( $config ) ? array_merge( $picker::default_config(), $config ) : $picker::default_config();

			foreach ( array( 'snooze_after', 'snooze_for' ) as $key ) {
				$config[ $key ] = absint( $config[ $key ] );
			}

			return $config;
		}

		/**
		 * @return string Picker class name.
		 */
		private function load_picker() {
			if ( ! class_exists( 'WFFN_Sibling_Promo_Picker' ) ) {
				require_once __DIR__ . '/sibling-promo/class-wffn-sibling-promo-picker.php';
			}

			return 'WFFN_Sibling_Promo_Picker';
		}

		/**
		 * Seeds activation times from each product's own install-date option the first time the state is read.
		 * A page dismissed before this feature existed was hidden for good, so it stays dismissed for good.
		 *
		 * @return array
		 */
		public function get_state() {
			$picker = $this->load_picker();
			$state  = $picker::normalize_state( get_option( self::OPTION, array() ) );

			if ( empty( $state['seeded'] ) ) {
				$options = get_option( 'fb_site_options', array() );
				foreach ( $this->get_products() as $key => $product ) {
					$time = $this->get_seed_time( $product );
					if ( $time ) {
						$state = $picker::stamp( $state, $key, 'activated_at', $time )['state'];
					}
					if ( ! empty( $product['skip'] ) && is_array( $options ) && ! empty( $options[ $product['skip'] ] ) ) {
						$state['products'][ $key ]['dismissed'] = 1;
					}
				}
				$state['seeded'] = 1;
				$this->save_state( $state );
			}

			return $state;
		}

		/**
		 * @param array $state State.
		 *
		 * @return void
		 */
		public function save_state( $state ) {
			update_option( self::OPTION, $state, false );
		}

		/**
		 * @param array $product Product definition.
		 *
		 * @return int Unix time, 0 when the product left no install date.
		 */
		public function get_seed_time( $product ) {
			if ( empty( $product['seed']['option'] ) ) {
				return 0;
			}
			$value = get_option( $product['seed']['option'], '' );
			if ( ! empty( $product['seed']['key'] ) ) {
				$value = ( is_array( $value ) && isset( $value[ $product['seed']['key'] ] ) ) ? $value[ $product['seed']['key'] ] : '';
			}
			if ( ! is_string( $value ) || '' === $value ) {
				return 0;
			}

			try {
				$timezone = empty( $product['seed']['utc'] ) ? wp_timezone() : new DateTimeZone( 'UTC' );
				$date     = new DateTimeImmutable( $value, $timezone );
			} catch ( Throwable $e ) {
				return 0;
			}

			return $date->getTimestamp();
		}

		/**
		 * @hooked activated_plugin
		 *
		 * @param string $plugin Plugin basename.
		 *
		 * @return void
		 */
		public function on_activated_plugin( $plugin ) {
			foreach ( $this->get_products() as $key => $product ) {
				if ( empty( $product['plugins'] ) || ! in_array( $plugin, (array) $product['plugins'], true ) ) {
					continue;
				}
				$picker = $this->load_picker();
				$result = $picker::stamp( $this->get_state(), $key, 'activated_at', time() );
				if ( $result['changed'] ) {
					$this->save_state( $result['state'] );
				}

				return;
			}
		}

		/**
		 * Registers the promoted product's entry. Called by WFFN_Admin::register_admin_menu()
		 * in its sibling band, after the feature entries and before Settings.
		 *
		 * @param string|false $user Capability that sees the FunnelKit menu.
		 *
		 * @return void
		 */
		public function register_menu( $user = false ) {
			if ( false === $user ) {
				$user = WFFN_Role_Capability::get_instance()->user_access( 'menu', 'read' );
			}
			if ( false === $user ) {
				return;
			}

			$products = $this->get_products();
			if ( empty( $products ) ) {
				return;
			}

			$picker  = $this->load_picker();
			$now     = time();
			$state   = $this->get_state();
			$config  = $this->get_config();
			$changed = $this->take_dismissals( $products, $state, $now, $config );

			foreach ( $products as $key => $product ) {
				$has_detector = ! empty( $product['detector'] ) && is_callable( $product['detector'] );

				if ( $this->is_present( $product ) ) {
					$result  = $picker::stamp( $state, $key, 'activated_at', $now );
					$state   = $result['state'];
					$changed = $changed || $result['changed'];

					if ( $has_detector && empty( $state['products'][ $key ]['set_up_at'] ) && true === call_user_func( $product['detector'] ) ) {
						$state['products'][ $key ]['set_up_at'] = $now;
						$changed                                = true;
					}
				}

				$config['products'][ $key ] = array(
					'eligible' => true,
					'detector' => $has_detector,
					'present'  => $this->is_present( $product ),
				);
			}

			$result = $picker::pick( $state, $now, $config );
			if ( $changed || $result['changed'] ) {
				$this->save_state( $result['state'] );
			}

			foreach ( $result['show'] as $key ) {
				$this->add_entry( $key, $products[ $key ], $user );
			}
		}

		/**
		 * A product page's dismiss button sets its fb_site_options flag. The flag becomes a snooze
		 * here and is cleared, so the product leaves the menu and the header and can rejoin later.
		 *
		 * @param array $products Product definitions.
		 * @param array $state State, snoozed in place.
		 * @param int   $now Unix time.
		 * @param array $config Picker config.
		 *
		 * @return bool Whether the state changed.
		 */
		private function take_dismissals( $products, &$state, $now, $config ) {
			$options = get_option( 'fb_site_options', array() );
			if ( ! is_array( $options ) ) {
				return false;
			}

			$picker  = $this->load_picker();
			$changed = false;
			foreach ( $products as $key => $product ) {
				if ( empty( $product['skip'] ) || empty( $options[ $product['skip'] ] ) || ! empty( $state['products'][ $key ]['dismissed'] ) ) {
					continue;
				}
				$state = $picker::dismiss( $state, $key, $now, $config );
				unset( $options[ $product['skip'] ] );
				$changed = true;
			}
			if ( $changed ) {
				update_option( 'fb_site_options', $options, true );
			}

			return $changed;
		}

		/**
		 * Orders the submenu as the header reads: Funnel Builder's own entries, then the promoted
		 * product, then Settings, then the upgrade link.
		 *
		 * @hooked admin_menu - PHP_INT_MAX, once every entry is registered
		 *
		 * @return void
		 */
		public function sort_menu() {
			global $submenu;
			if ( empty( $submenu['woofunnels'] ) || ! is_array( $submenu['woofunnels'] ) ) {
				return;
			}

			$products = $this->get_products();
			$rank     = array();
			foreach ( array_keys( $products ) as $position => $key ) {
				$rank[ 'bwf&path=/' . $products[ $key ]['path'] ] = $position;
			}

			$own      = array();
			$siblings = array();
			$settings = array();
			$upgrade  = array();
			foreach ( $submenu['woofunnels'] as $item ) {
				$slug = isset( $item[2] ) ? $item[2] : '';
				if ( isset( $rank[ $slug ] ) ) {
					$siblings[] = $item;
				} elseif ( in_array( $slug, array( 'bwf&path=/settings', 'woofunnels_settings' ), true ) ) {
					$settings[] = $item;
				} elseif ( 'wffn_upgrade_pro' === $slug ) {
					$upgrade[] = $item;
				} else {
					$own[] = $item;
				}
			}
			usort(
				$siblings,
				function ( $a, $b ) use ( $rank ) {
					return $rank[ $a[2] ] - $rank[ $b[2] ];
				}
			);

			$submenu['woofunnels'] = array_merge( $own, $siblings, $settings, $upgrade ); //phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- menu order is only settable on the global.
		}

		/**
		 * The app header shows the promoted product, as the submenu does, after Funnel Builder's own
		 * links.
		 *
		 * @param array $header_menu Header navigation keyed by slug.
		 *
		 * @return array
		 */
		public function add_header_menu( $header_menu ) {
			$products = $this->get_products();
			if ( '' !== $this->shown && isset( $products[ $this->shown ] ) ) {
				$product                         = $products[ $this->shown ];
				$header_menu[ $product['path'] ] = WFFN_Header::nav_item(
					$this->shown,
					array(
						'title' => $product['title'],
						'path'  => '/' . $product['path'],
					)
				);
			}

			$ordered = array();
			foreach ( $products as $product ) {
				if ( isset( $header_menu[ $product['path'] ] ) ) {
					$ordered[ $product['path'] ] = $header_menu[ $product['path'] ];
					unset( $header_menu[ $product['path'] ] );
				}
			}

			return array_merge( $header_menu, $ordered );
		}

		/**
		 * @param array $product Product definition.
		 *
		 * @return bool
		 */
		public function is_present( $product ) {
			return ! empty( $product['constant'] ) && defined( $product['constant'] );
		}

		/**
		 * @param string $key Product key.
		 * @param array  $product Product definition.
		 * @param string $user Capability that sees Funnel Builder's menu.
		 *
		 * @return void
		 */
		private function add_entry( $key, $product, $user ) {
			$hook = WFFN_Core()->admin->add_menu_page(
				$key,
				array(
					'title' => $product['title'],
					'path'  => '/' . $product['path'],
				),
				$user
			);
			if ( false !== $hook ) {
				$this->shown = $key;
			}
		}

		/**
		 * An active automation exists: status 1 on the automations table, the same predicate
		 * the plugin uses to load its active automations.
		 *
		 * @return bool
		 */
		public function is_automations_set_up() {
			global $wpdb;
			if ( false === get_option( 'bwfan_ver_1_0', false ) ) {
				return false;
			}

			return '1' === $wpdb->get_var( "SELECT 1 FROM {$wpdb->prefix}bwfan_automations WHERE status = 1 LIMIT 1" ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one indexed row check, run only until the product is marked set up.
		}

		/**
		 * A published plan exists (status 1; 0 is draft, 2 archived).
		 *
		 * @return bool
		 */
		public function is_sublium_set_up() {
			global $wpdb;
			if ( false === get_option( 'sublium_wcs_activation_date', false ) ) {
				return false;
			}

			return '1' === $wpdb->get_var( "SELECT 1 FROM {$wpdb->prefix}sublium_wcs_plan WHERE status = 1 LIMIT 1" ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one row check, run only until the product is marked set up.
		}
	}
}
