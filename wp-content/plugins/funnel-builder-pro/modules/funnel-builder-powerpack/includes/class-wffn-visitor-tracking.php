<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class WFFN_Visitor_Tracking
 *
 * Pro owns visitor data collection end to end: the utm-tracker.js enqueue,
 * the wffn_* cookies payload, referrer/journey handling, and the order
 * Conversion Tracking meta box. The free plugin only persists server-side
 * base data; this class injects the visitor-collected fields through the
 * neutral seams the framework exposes (bwf_tracking_visitor_data,
 * bwf_tracking_data_before_insert, bwf_tracking_order_inserted,
 * bwf_insert_conversion_tracking_data).
 */
if ( ! class_exists( 'WFFN_Visitor_Tracking' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Visitor_Tracking {
		private static $ins = null;

		private $conv_table = 'bwf_conversion_tracking';

		public function __construct() {
			/**
			 * The localize merge must run even on legacy frameworks — their own
			 * renderer applies this filter and needs the utm_* cookie keys.
			 */
			add_filter( 'wffn_conversion_tracking_localize_data', array( $this, 'update_data_localize_data' ) );

			/**
			 * Legacy framework (Lite not yet updated) still contains and runs the
			 * whole visitor pipeline itself — stand down entirely to avoid double
			 * rendering, double journey writes or a second meta box. Detected by
			 * feature (method presence), never by version or constant.
			 */
			if ( method_exists( 'BWF_Ecomm_Tracking_Common', 'get_common_tracking_data' ) ) {
				return;
			}

			add_action( 'wp_enqueue_scripts', array( $this, 'render_utm_tracker' ), 1 );
			/** Upsell offer pages render a custom template head — enqueue there too (idempotent by handle) */
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_utm_tracker' ), 10 );

			add_filter( 'bwf_tracking_visitor_data', array( $this, 'get_tracking_visitor_data' ), 10, 2 );
			add_filter( 'bwf_tracking_data_before_insert', array( $this, 'maybe_add_thankyou_data' ), 10, 1 );
			add_action( 'bwf_tracking_order_inserted', array( $this, 'update_upsell_order_journey_data' ), 10, 2 );
			add_filter( 'bwf_insert_conversion_tracking_data', array( $this, 'maybe_populate_referrer_from_utm' ), 10, 1 );

			add_action( 'wc_ajax_wfocu_front_register_views', array( __CLASS__, 'update_upsell_journey' ) );
			add_action( 'admin_init', array( $this, 'maybe_update_conversion_journey_column' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_single_order_meta_box' ), 50, 2 );
		}

		/**
		 * @return WFFN_Visitor_Tracking|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Supply the visitor-collected payload to the framework's persistence
		 * pipeline (order meta, optin rows, webhook fields).
		 *
		 * @param array  $data
		 * @param string $context order|optin|webhook
		 *
		 * @return array
		 */
		public function get_tracking_visitor_data( $data, $context = 'order' ) {
			$data = is_array( $data ) ? $data : array();

			return array_merge( $data, $this->get_common_tracking_data( 'optin' === $context ) );
		}

		/**
		 * Render the first-party tracker (utm-tracker.js). Controlled by the
		 * track_utms setting; never loads twice (handle guard covers this
		 * method's two hooks firing on the same request).
		 *
		 * @return void
		 */
		public function render_utm_tracker() {
			if ( ! wffn_string_to_bool( BWF_Admin_General_Settings::get_instance()->get_option( 'track_utms' ) ) ) {
				return;
			}

			if ( function_exists( 'wp_script_is' ) && ( wp_script_is( 'wfco-utm-tracking', 'enqueued' ) || wp_script_is( 'wfco-utm-tracking', 'registered' ) ) ) {
				return;
			}

			if ( class_exists( 'WFFN_Common' ) && WFFN_Common::is_page_builder_preview() ) {
				return;
			}

			/**
			 * Disable client-side journey recording on one-click upsell offer pages.
			 * The offer view is appended to the journey server-side in update_upsell_journey().
			 */
			$journey_control = 'enable';
			if ( class_exists('WooCommerce') && class_exists( 'WFFN_Common' ) && WFFN_Common::wffn_is_funnel_pro_active() && function_exists( 'WFOCU_Core' ) && WFOCU_Core()->public->if_is_offer() ) {
				$journey_control = 'disable';
			}

			/**
			 * On the thank-you page the journey has already been persisted onto the order,
			 * so the tracker drops the cookie to start the next journey clean.
			 */
			$is_thankyou_page = ( class_exists('WooCommerce') && class_exists( 'WFFN_Core' ) && isset( WFFN_Core()->thank_you_pages ) && is_callable( array( WFFN_Core()->thank_you_pages, 'is_wfty_page' ) ) && true === WFFN_Core()->thank_you_pages->is_wfty_page() ) ? 1 : 0;

			/**
			 * Cookie domain for the tracking/journey cookies. Empty by default → the JS
			 * scopes them to the current host. Set the COOKIE_DOMAIN constant or hook the
			 * wffn_tracking_cookie_domain filter to share the journey across sub-sites.
			 */
			$cookie_domain = apply_filters( 'wffn_tracking_cookie_domain', ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '' );

			$data = apply_filters(
				'wffn_conversion_tracking_localize_data',
				array(
					'journeyControl'     => $journey_control,
					'cookie_domain'      => $cookie_domain,
					'is_thankyou_page'   => $is_thankyou_page,
					'page_id'            => function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0,
					'utc_offset'         => esc_attr( $this->get_timezone_offset() ),
					'site_url'           => esc_url( site_url() ),
					'genericParamEvents' => wp_json_encode( $this->get_generic_event_params() ),
					'cookieKeys'         => array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'flt', 'timezone', 'is_mobile', 'browser', 'fbclid', 'gclid', 'referrer', 'referrer_last', 'fl_url', 'utm_source_last', 'utm_medium_last', 'utm_campaign_last', 'utm_term_last', 'utm_content_last' ),
					'excludeDomain'      => array( 'paypal.com', 'klarna.com', 'quickpay.net' ),
				)
			);

			$min = ( defined( 'WFFN_IS_DEV' ) && true === WFFN_IS_DEV ) ? '' : '.min';

			wp_enqueue_script(
				'wfco-utm-tracking',
				WFFN_PRO_PLUGIN_URL . '/assets/js/utm-tracker' . $min . '.js',
				array(),
				WFFN_PRO_VERSION,
				array(
					'in_footer' => false,
					'strategy'  => 'defer',
				)
			);
			wp_localize_script( 'wfco-utm-tracking', 'wffnUtm', $data );
		}

		/**
		 * Cross-version safety: legacy frameworks render the tracker themselves but
		 * their default cookieKeys lacks the utm_* keys. Merge them idempotently.
		 *
		 * @param array $args
		 *
		 * @return array
		 */
		public function update_data_localize_data( $args ) {
			if ( ! is_array( $args ) || ! isset( $args['cookieKeys'] ) || ! is_array( $args['cookieKeys'] ) ) {
				return $args;
			}

			$args['cookieKeys'] = array_values( array_unique( array_merge( $args['cookieKeys'], array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) ) ) );

			return $args;
		}

		/**
		 * Generic event params carried on every tracked pixel event.
		 *
		 * @return array
		 */
		public function get_generic_event_params() {
			$user = wp_get_current_user();
			if ( $user->ID !== 0 ) {
				$user_roles = implode( ',', $user->roles );
			} else {
				$user_roles = 'guest';
			}

			return array(
				'user_roles' => $user_roles,
				'plugin'     => 'Funnel Builder',
			);
		}

		/**
		 * Timezone offset in minutes.
		 *
		 * @return float|int
		 */
		public function get_timezone_offset() {
			$offset                 = 0;
			$offset_diff_in_seconds = current_time( 'timestamp' ) - time(); //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			if ( absint( $offset_diff_in_seconds ) > 0 ) {
				$offset = $offset_diff_in_seconds / 60;
			}

			return $offset;
		}

		/**
		 * Filter referrer to be saved inside the database
		 *
		 * @param string $url
		 *
		 * @return string
		 */
		public function filter_referrer( $url ) {
			$domain = get_site_url();

			$url    = str_replace( array( 'http://', 'https://' ), '', $url );
			$domain = str_replace( array( 'http://', 'https://' ), '', $domain );

			/**
			 * if its a same site referrer then return empty
			 */
			if ( false !== strpos( $url, $domain ) ) {
				return '';
			}

			/**
			 * Remove trailing slash from the end of the url
			 */
			$url = rtrim( $url, '/' );

			return $this->parse_url_query_param( $url );
		}

		/**
		 * One-time conversion-table `journey` column migration.
		 *
		 * Hooked on admin_init and gated by the `wffn_conversion_tracking_db_updater`
		 * option (default 1.0). While below 1.1 it adds the `journey` column when missing
		 * (matching the conversion table schema — nullable longtext) and then bumps the
		 * option, so the schema lookup/ALTER runs once; every later request is a single
		 * autoloaded option read. The option is only advanced once the column is confirmed
		 * present, so a failed ALTER is retried on the next request.
		 *
		 * @return void
		 */
		public function maybe_update_conversion_journey_column() {
			if ( version_compare( get_option( 'wffn_conversion_tracking_db_updater', '1.0' ), '1.1', '>=' ) ) {
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . $this->conv_table;
			$is_col     = $wpdb->get_col( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND table_name = %s AND column_name = 'journey'", $wpdb->dbname, $table_name ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( empty( $is_col ) ) {
				$wpdb->query( "ALTER TABLE `{$table_name}` ADD `journey` longtext AFTER `referrer`" ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( ! empty( $wpdb->last_error ) ) {
					return;
				}
			}

			update_option( 'wffn_conversion_tracking_db_updater', '1.1', true );
		}

		/**
		 * Normalize any decoded journey value into the v2 { j, s } shape.
		 * Legacy flat journeys ({ ts => { u,t,i } }) are wrapped as { j: <legacy>, s: [] }.
		 *
		 * @param mixed $data
		 * @return array
		 */
		public static function journey_normalize( $data ) {
			if ( is_array( $data ) && isset( $data['j'] ) && is_array( $data['j'] ) ) {
				if ( ! isset( $data['s'] ) || ! is_array( $data['s'] ) ) {
					$data['s'] = array();
				}
				return $data;
			}
			return array( 'j' => is_array( $data ) ? $data : array(), 's' => array() );
		}

		/**
		 * Index of $origin in $store['s'], assigning the next integer (from 1) if absent.
		 *
		 * @param array  $store passed by reference; its 's' map may be extended.
		 * @param string $origin scheme://host
		 * @return int
		 */
		public static function journey_site_index( &$store, $origin ) {
			foreach ( $store['s'] as $idx => $val ) {
				if ( $val === $origin ) {
					return (int) $idx;
				}
			}
			$next = 1;
			foreach ( array_keys( $store['s'] ) as $idx ) {
				if ( (int) $idx >= $next ) {
					$next = (int) $idx + 1;
				}
			}
			$store['s'][ $next ] = $origin;
			return $next;
		}

		/**
		 * Origin (scheme://host[:port]) of the current site.
		 *
		 * @return string
		 */
		public static function site_origin() {
			$parts  = wp_parse_url( home_url() );
			$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
			$host   = isset( $parts['host'] ) ? $parts['host'] : '';
			$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
			return $scheme . '://' . $host . $port;
		}

		/**
		 * Reconstruct a full URL for a journey entry across the three supported shapes.
		 *
		 * @param string $path     decoded relative-or-absolute path from entry['u']
		 * @param mixed  $site_idx entry['s'] (v2) or null
		 * @param array  $sites    the journey 's' map
		 * @param string $home_url home_url('/') of the rendering site
		 * @return string
		 */
		public static function journey_resolve_url( $path, $site_idx, $sites, $home_url ) {
			if ( null !== $site_idx && isset( $sites[ $site_idx ] ) ) {
				return rtrim( $sites[ $site_idx ], '/' ) . '/' . ltrim( $path, '/' );
			}
			if ( 0 === strpos( $path, 'http' ) ) {
				return $path;
			}
			return rtrim( $home_url, '/' ) . '/' . ltrim( $path, '/' );
		}

		/**
		 * Append the one-click upsell offer view to the parent order journey.
		 *
		 * Hooked on wc_ajax_wfocu_front_register_views; the offer page is recorded
		 * server-side because client journey tracking is disabled on offer pages.
		 *
		 * @return void
		 */
		public static function update_upsell_journey() {
			if ( ! isset( $_POST['data'] ) || ! function_exists( 'WFOCU_Core' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
				return;
			}

			$get_order = WFOCU_Core()->data->get_parent_order();
			if ( ! $get_order instanceof WC_Order ) {
				return;
			}

			$tracking_data = BWF_WC_Compatibility::get_order_meta( $get_order, '_wffn_tracking_data' );
			if ( empty( $tracking_data ) || ! is_array( $tracking_data ) || empty( $tracking_data['journey'] ) ) {
				return;
			}

			$get_current_offer = WFOCU_Core()->data->get_current_offer();
			$link              = WFOCU_Core()->offers->get_the_link( $get_current_offer );
			$parsed_url        = wp_parse_url( $link );
			$relative_path     = ( is_array( $parsed_url ) && ! empty( $parsed_url['path'] ) ) ? ltrim( $parsed_url['path'], '/' ) : '';

			$data     = wc_clean( wp_unslash( $_POST['data'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$products = ( isset( $data['products'] ) && is_array( $data['products'] ) ) ? array_values( $data['products'] ) : array();
			$name     = ( ! empty( $products ) && isset( $products[0]['name'] ) ) ? $products[0]['name'] : '';

			$store  = self::journey_normalize( json_decode( $tracking_data['journey'], true ) );
			$origin = self::site_origin();
			$idx    = self::journey_site_index( $store, $origin );

			$store['j'][ (int) round( microtime( true ) * 1000 ) ] = array(
				'u' => '/' . ltrim( $relative_path, '/' ),
				't' => $name,
				'i' => $get_current_offer,
				's' => $idx,
			);

			$tracking_data['journey'] = wp_json_encode( $store, JSON_UNESCAPED_SLASHES );
			$get_order->update_meta_data( '_wffn_tracking_data', $tracking_data );
			$get_order->save_meta_data();
		}

		/**
		 * Copy the journey onto the conversion rows of sibling/upsell orders so the
		 * order metabox shows the full path on those orders too.
		 *
		 * @param WC_Order $order
		 * @param array    $tracking_data
		 * @param bool     $upsell_order whether $order itself is an upsell order
		 *
		 * @return void
		 */
		public function update_upsell_order_journey_data( $order, $tracking_data, $upsell_order = false ) {
			if ( ! $order instanceof WC_Order || ! is_array( $tracking_data ) || ! isset( $tracking_data['journey'] ) ) {
				return;
			}

			$orders   = ( true === $upsell_order ) ? array( $order->get_id() ) : array();
			$get_meta = $order->get_meta( '_wfocu_sibling_order', false );
			if ( is_array( $get_meta ) && ! empty( $get_meta ) ) {
				foreach ( $get_meta as $meta ) {
					$value = $meta->get_data()['value'];
					$orders[] = ( $value instanceof WC_Order ) ? $value->get_id() : absint( $value );
				}
			}

			if ( 0 === count( $orders ) ) {
				return;
			}

			global $wpdb;
			foreach ( $orders as $order_id ) {
				$wpdb->update( $wpdb->prefix . $this->conv_table, array( 'journey' => $tracking_data['journey'] ), array( 'type' => 2, 'source' => $order_id ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}

		/**
		 * Append the current page (thank-you page) to the journey.
		 * Only runs while rendering a page; a no-op during cron/background recovery.
		 *
		 * @param array $tracking_data
		 *
		 * @return array
		 */
		public function maybe_add_thankyou_data( $tracking_data ) {
			if ( ! is_array( $tracking_data ) || empty( $tracking_data['journey'] ) ) {
				return $tracking_data;
			}
			if ( ! function_exists( 'get_the_ID' ) || empty( get_the_ID() ) ) {
				return $tracking_data;
			}

			$store  = self::journey_normalize( json_decode( $tracking_data['journey'], true ) );
			$origin = self::site_origin();
			$idx    = self::journey_site_index( $store, $origin );

			$store['j'][ (int) round( microtime( true ) * 1000 ) ] = array(
				'u' => '/' . ltrim( substr( get_permalink(), strlen( home_url( '/' ) ) ), '/' ),
				't' => get_the_title(),
				'i' => get_the_ID(),
				's' => $idx,
			);

			$tracking_data['journey'] = wp_json_encode( $store, JSON_UNESCAPED_SLASHES );

			return $tracking_data;
		}

		public function get_common_tracking_data( $is_optin = false ) {
			$click_id = '';
			$get_data = $_COOKIE; //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			if ( isset( $get_data['wffn_fbclid'] ) ) {
				$click_id = $get_data['wffn_fbclid'];
			} elseif ( isset( $get_data['wffn_gclid'] ) ) {
				$click_id = $get_data['wffn_gclid'];
			}

			/**
			 * Insert step landing and optin source id for checkout revenue
			 * Source id insert only for create order row on optin submit is always insert 0
			 */
			$source_id = class_exists( 'WFFN_Core' ) ? WFFN_Core()->data->get( 'source_id', 0 ) : 0;

			/**
			 * The journey cookie holds the page-by-page path as JSON. The browser stores it
			 * with addslashes() applied (see utm-tracker.js), so strip those before decoding.
			 * Stored in the existing `journey` column — no schema change.
			 */
			$journey_data = isset( $get_data['wffn_journey'] ) ? json_decode( stripcslashes( $get_data['wffn_journey'] ), true ) : array();
			$journey_data = is_array( $journey_data ) ? $journey_data : array();
			$journey_data = self::journey_normalize( $journey_data );

			$args = array(
				'utm_source'        => isset( $get_data['wffn_utm_source'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_source'] ) ) ) : '',
				'utm_medium'        => isset( $get_data['wffn_utm_medium'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_medium'] ) ) ) : '',
				'utm_campaign'      => isset( $get_data['wffn_utm_campaign'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_campaign'] ) ) ) : '',
				'utm_term'          => isset( $get_data['wffn_utm_term'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_term'] ) ) ) : '',
				'utm_content'       => isset( $get_data['wffn_utm_content'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_content'] ) ) ) : '',
				'utm_source_last'   => isset( $get_data['wffn_utm_source_last'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_source_last'] ) ) ) : '',
				'utm_medium_last'   => isset( $get_data['wffn_utm_medium_last'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_medium_last'] ) ) ) : '',
				'utm_campaign_last' => isset( $get_data['wffn_utm_campaign_last'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_campaign_last'] ) ) ) : '',
				'utm_term_last'     => isset( $get_data['wffn_utm_term_last'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_term_last'] ) ) ) : '',
				'utm_content_last'  => isset( $get_data['wffn_utm_content_last'] ) ? $this->string_length( $this->strip_emojis( bwf_clean( $get_data['wffn_utm_content_last'] ) ) ) : '',
				'first_landing_url' => isset( $get_data['wffn_fl_url'] ) ? bwf_clean( $get_data['wffn_fl_url'] ) : '',
				'browser'           => isset( $get_data['wffn_browser'] ) ? bwf_clean( $get_data['wffn_browser'] ) : '',
				'first_click'       => isset( $get_data['wffn_flt'] ) ? bwf_clean( $get_data['wffn_flt'] ) : '',
				'device'            => isset( $get_data['wffn_is_mobile'] ) ? ( true === bwf_string_to_bool( $get_data['wffn_is_mobile'] ) ? 'mobile' : 'desktop' ) : '',
				'click_id'          => $click_id,
				'referrer'          => isset( $get_data['wffn_referrer'] ) ? $this->filter_referrer( $get_data['wffn_referrer'] ) : '',
				'referrer_last'     => isset( $get_data['wffn_referrer_last'] ) ? $this->filter_referrer( $get_data['wffn_referrer_last'] ) : '',
				'journey'           => ( ! empty( $journey_data['j'] ) ) ? wp_json_encode( $journey_data, JSON_UNESCAPED_SLASHES ) : '',
				'source_id'         => $source_id,
			);

			if ( true === $is_optin ) {
				$timezone        = isset( $get_data['wffn_timezone'] ) ? $this->string_length( bwf_clean( $get_data['wffn_timezone'] ) ) : '';
				$country_data    = $this->get_country_and_timezone( $timezone );
				$args['country'] = ( is_array( $country_data ) && isset( $country_data['country_code'] ) ) ? $country_data['country_code'] : '';
			}

			return $args;
		}

		/**
		 * Get referrer domain based on known UTM sources
		 *
		 * This method maps common UTM sources to their corresponding referrer domains.
		 * It helps populate the referrer field when document.referrer is unavailable
		 * but we have UTM source information from tracking parameters.
		 *
		 * @param string $utm_source The UTM source parameter
		 *
		 * @return string The corresponding referrer domain or empty string if no match
		 */
		public function get_referrer_from_utm_source( $utm_source ) {
			if ( empty( $utm_source ) ) {
				return '';
			}

			$utm_source = strtolower( trim( $utm_source ) );

			// Map of known UTM sources to their referrer domains (only known URLs)
			// Split into short keys (≤4 letters) and long keys (>4 letters) for different matching logic
			$utm_to_referrer_map_short = array(
				// Short keys (≤4 letters): Allow partial matches with delimiters
				'fb'    => 'facebook.com',
				'ig'    => 'instagram.com',
				'tw'    => 'twitter.com',
				'yt'    => 'youtube.com',
				'pin'   => 'pinterest.com',
				'snap'  => 'snapchat.com',
				'gclid' => 'google.com',
				'bing'  => 'bing.com',
			);

			$utm_to_referrer_map_long = array(
				// Long keys (>4 letters): Must be exact whole word match
				'google'     => 'google.com',
				'googleads'  => 'google.com',
				'facebook'   => 'facebook.com',
				'fbclid'     => 'facebook.com',
				'instagram'  => 'instagram.com',
				'twitter'    => 'twitter.com',
				'linkedin'   => 'linkedin.com',
				'youtube'    => 'youtube.com',
				'tiktok'     => 'tiktok.com',
				'pinterest'  => 'pinterest.com',
				'snapchat'   => 'snapchat.com',
				'reddit'     => 'reddit.com',
				'quora'      => 'quora.com',
				'yahoo'      => 'yahoo.com',
				'duckduckgo' => 'duckduckgo.com',
				'whatsapp'   => 'whatsapp.com',
			);

			// Check for exact match first in both maps
			if ( isset( $utm_to_referrer_map_short[ $utm_source ] ) ) {
				return $utm_to_referrer_map_short[ $utm_source ];
			}
			if ( isset( $utm_to_referrer_map_long[ $utm_source ] ) ) {
				return $utm_to_referrer_map_long[ $utm_source ];
			}

			// For short keys (≤4 letters): Allow partial matches with delimiters
			// Match if key appears at start, end, or surrounded by delimiters (hyphen, underscore, space, or word boundary)
			// This prevents false matches (e.g., "activecampaign" matching "ig") while allowing valid cases like "fb_mob"
			foreach ( $utm_to_referrer_map_short as $utm_key => $referrer ) {
				$escaped_key = preg_quote( $utm_key, '/' );
				// Match: (start of string OR delimiter OR word boundary) + key + (delimiter OR word boundary OR end of string)
				// Delimiters: hyphen, underscore, space
				$pattern = '/(?:^|[-_\s]|\b)' . $escaped_key . '(?:[-_\s]|\b|$)/';
				if ( preg_match( $pattern, $utm_source ) ) {
					return $referrer;
				}
			}

			// For long keys (>4 letters): Must be exact whole word match
			// Match only if key is surrounded by spaces or at start/end of string
			// This allows "mobile facebook" but prevents "facebook-ads" or "facebook_ads"
			foreach ( $utm_to_referrer_map_long as $utm_key => $referrer ) {
				$escaped_key = preg_quote( $utm_key, '/' );
				// Match: (start of string OR space) + key + (space OR end of string)
				// This ensures it's a standalone word, not part of a compound with delimiters
				$pattern = '/(?:^|\s)' . $escaped_key . '(?:\s|$)/';
				if ( preg_match( $pattern, $utm_source ) ) {
					return $referrer;
				}
			}

			return '';
		}

		/**
		 * Populate referrer from UTM source if referrer is empty
		 *
		 * This method automatically populates the referrer field based on UTM source
		 * when the original referrer is empty. This helps improve analytics data
		 * when document.referrer is unavailable but UTM tracking is present.
		 *
		 * @param array $args The tracking data array
		 *
		 * @return array Modified tracking data array
		 */
		public function maybe_populate_referrer_from_utm( $args ) {
			// Only populate if referrer is empty and we have a UTM source
			if ( empty( $args['referrer'] ) && ! empty( $args['utm_source'] ) ) {
				$referrer_from_utm = $this->get_referrer_from_utm_source( $args['utm_source'] );

				if ( ! empty( $referrer_from_utm ) ) {
					$args['referrer'] = $referrer_from_utm;
				}
			}

			return $args;
		}

		/**
		 * @param $timezone
		 *
		 * @return array|string
		 */
		public function get_country_and_timezone( $timezone ) {
			$result = '';
			if ( '' === $timezone ) {
				return $result;
			}

			$list = bwf_get_country_timezone_list();

			$country_list = wp_list_pluck( $list, 'timezone' );

			// check valid timezone
			foreach ( $country_list as $key => $item ) {
				if ( false !== array_search( $timezone, $item, true ) ) {
					$result = array(
						'country_code' => $key,
						'timezone'     => $timezone,
					);
					break;
				}
			}

			return $result;
		}

		public function add_single_order_meta_box( $post_type, $post ) {

			if ( ! class_exists( 'WFFN_Common' ) ) {
				return;
			}

			if ( ! WFFN_Common::wffn_is_funnel_pro_active() ) {
				return;
			}

			if ( 'shop_order' !== $post_type && 'woocommerce_page_wc-orders' !== $post_type ) {
				return;
			}
			$order_id = 0;

			if ( isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page identification
				$order_id = absint( wp_unslash( $_GET['id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page identification
			}

			if ( 0 === absint( $order_id ) && $post instanceof WP_Post ) {
				$order_id = $post->ID;
			}

			if ( 0 === absint( $order_id ) ) {
				return;
			}

			/**
			 * @todo we will update code showing for funnel meta box currently not have exact mata for check order create by funnel
			 * so we run query in conversion table and check order created by funnel
			 */ global $wpdb;
			$query    = $wpdb->prepare( 'SELECT * from ' . $wpdb->prefix . $this->conv_table . ' WHERE type = %s AND source = %d', 2, $order_id );
			$get_data = $wpdb->get_row( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL
			if ( empty( $get_data ) ) {
				return;
			}

			$data = array(
				'bwf_meta_data' => $get_data,
			);

			add_meta_box(
				'bwfan_utm_info_box',
				__( 'Conversion Tracking', 'woofunnels' ),
				array( // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				$this,
				'order_meta_box_data',
				),
				function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order',
				'side',
				'default',
				$data
			);
		}

		public function order_meta_box_data( $post, $meta_data ) {

			if ( ! is_array( $meta_data ) || ! isset( $meta_data['args'] ) || ! isset( $meta_data['args']['bwf_meta_data'] ) ) {
				return;
			}
			$get_data = $meta_data['args']['bwf_meta_data'];

			$first_click = ( isset( $get_data['first_click'] ) && '0000-00-00 00:00:00' !== $get_data['first_click'] ) ? $get_data['first_click'] : '';
			$timestamp   = isset( $get_data['timestamp'] ) ? $get_data['timestamp'] : '';

			$funnel_id = isset( $get_data['funnel_id'] ) ? $get_data['funnel_id'] : 0;
			$funnel    = $funnel_id;

			if ( class_exists( 'WFFN_Funnel' ) && class_exists( 'WFFN_Common' ) ) {
				$funnel_obj = new WFFN_Funnel( $funnel_id );
				if ( $funnel_obj instanceof WFFN_Funnel && $funnel_obj->get_id() > 0 ) {
					$link         = WFFN_Common::get_funnel_edit_link( $funnel_obj->get_id() );
					$funnel_title = ! empty( $funnel_obj->get_title() ) ? $funnel_obj->get_title() : $funnel_obj->get_id();
					$funnel       = '<a href="' . $link . '" target="_blank">' . $funnel_title . '</a>';
				}
			}
			$diff = '';
			$ref  = '';
			$data = array();
			if ( ! empty( $first_click ) ) {
				$d1   = strtotime( $timestamp );
				$d2   = strtotime( $first_click );
				$diff = human_time_diff( $d1, $d2 );
			}

			if ( isset( $get_data['referrer'] ) && $get_data['referrer'] !== '' ) {
				$ref = explode( '?', $get_data['referrer'] );
			}
			$data['funnel'] = array(
				'name'  => __( 'Funnel', 'woofunnels' ),  // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'value' => $funnel,
			);
			if ( '' !== $first_click ) {
				$data['first_click'] = array(
					'name'  => __( 'First Interaction', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $first_click,
				);
			}
			if ( '' !== $diff ) {
				$data['convert'] = array(
					'name'  => __( 'Conversion Time', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $diff,
				);
			}
			if ( isset( $get_data['utm_source'] ) && '' !== $get_data['utm_source'] ) {
				$data['utm_source'] = array(
					'name'  => __( 'UTM Source', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ucfirst( $get_data['utm_source'] ),
				);
			}
			if ( isset( $get_data['utm_medium'] ) && '' !== $get_data['utm_medium'] ) {
				$data['utm_medium'] = array(
					'name'  => __( 'UTM Medium', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ucfirst( $get_data['utm_medium'] ),
				);
			}
			if ( isset( $get_data['utm_campaign'] ) && '' !== $get_data['utm_campaign'] ) {
				$data['utm_campaign'] = array(
					'name'  => __( 'UTM Campaign', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $get_data['utm_campaign'],
				);
			}
			if ( isset( $get_data['utm_term'] ) && '' !== $get_data['utm_term'] ) {
				$data['utm_term'] = array(
					'name'  => __( 'UTM Term', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $get_data['utm_term'],
				);
			}
			if ( isset( $get_data['utm_content'] ) && '' !== $get_data['utm_content'] ) {
				$data['utm_content'] = array(
					'name'  => __( 'UTM Content', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $get_data['utm_content'],
				);
			}
			if ( isset( $get_data['referrer'] ) && '' !== $get_data['referrer'] ) {
				$data['referrer'] = array(
					'name'  => __( 'Referrer', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ( is_array( $ref ) && isset( $ref[0] ) ) ? '<a href="' . esc_url( $ref[0] ) . '" target="_blank">' . esc_html( $ref[0] ) . '</a>' : '',
				);
			}
			if ( isset( $get_data['click_id'] ) ) {
				$data['click_id'] = array(
					'name'  => __( 'Click ID', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ( '' !== $get_data['click_id'] ) ? __( 'Yes', 'woofunnels' ) : __( 'No', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				);
			}
			if ( isset( $get_data['device'] ) && '' !== $get_data['device'] ) {
				$data['device'] = array(
					'name'  => __( 'Device', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ucfirst( $get_data['device'] ),
				);
			}
			if ( isset( $get_data['browser'] ) && '' !== $get_data['browser'] ) {
				$data['browser'] = array(
					'name'  => __( 'Browser', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $get_data['browser'],
				);
			}

			/**
			 * First-touch UTM / referrer — stored in the existing *_last columns.
			 */
			$first_touch_labels = array(
				'utm_source_last'   => __( 'First UTM Source', 'woofunnels' ),   // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'utm_medium_last'   => __( 'First UTM Medium', 'woofunnels' ),   // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'utm_campaign_last' => __( 'First UTM Campaign', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'utm_term_last'     => __( 'First UTM Term', 'woofunnels' ),     // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'utm_content_last'  => __( 'First UTM Content', 'woofunnels' ),  // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'referrer_last'     => __( 'First Referrer', 'woofunnels' ),     // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			);
			foreach ( $first_touch_labels as $first_key => $first_label ) {
				if ( empty( $get_data[ $first_key ] ) ) {
					continue;
				}
				if ( 'referrer_last' === $first_key ) {
					$first_ref   = explode( '?', $get_data[ $first_key ] );
					$first_value = isset( $first_ref[0] ) ? '<a href="' . esc_url( $first_ref[0] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $first_ref[0] ) . '</a>' : '';
				} else {
					$first_value = esc_html( $get_data[ $first_key ] );
				}
				$data[ $first_key ] = array(
					'name'  => $first_label,
					'value' => $first_value,
				);
			}

			/**
			 * Customer journey — the page-by-page path that led to the conversion.
			 *
			 * Hidden by default. Return true from the wffn_show_customer_journey_meta
			 * filter to surface the Customer Journey row on the order UTM metabox.
			 */
			if ( ! empty( $get_data['journey'] ) && true === apply_filters( 'wffn_show_customer_journey_meta', false, $post, $meta_data ) ) {
				$journey = json_decode( $get_data['journey'], true );
				if ( is_array( $journey ) && ! empty( $journey ) ) {
					$entries  = ( isset( $journey['j'] ) && is_array( $journey['j'] ) ) ? $journey['j'] : $journey;
					$sites    = ( isset( $journey['s'] ) && is_array( $journey['s'] ) ) ? $journey['s'] : array();
					$home_url = home_url( '/' );
					ksort( $entries, SORT_NUMERIC );
					$journey_count = 0;
					$journey_html  = '<ol class="bwf-journey-list">';
					foreach ( $entries as $entry ) {
						if ( ! is_array( $entry ) || empty( $entry['u'] ) ) {
							continue;
						}
						$path     = stripslashes( rawurldecode( $entry['u'] ) );
						$title    = ! empty( $entry['t'] ) ? rawurldecode( $entry['t'] ) : $path;
						$site_idx = isset( $entry['s'] ) ? $entry['s'] : null;
						$url      = self::journey_resolve_url( $path, $site_idx, $sites, $home_url );

						$journey_html .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a></li>';
						++$journey_count;
					}
					$journey_html .= '</ol>';

					// Only surface the Customer Journey row when at least one entry was rendered;
					// a normalized-but-empty journey ({"j":[],"s":[]}) must not show an empty list.
					if ( $journey_count > 0 ) {
						$data['journey'] = array(
							'name'  => __( 'Customer Journey', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
							'value' => $journey_html,
						);
					}
				}
			}

			$data = apply_filters( 'bwf_utm_tracking_meta_box', $data, $meta_data, $post );
			if ( empty( $data ) ) {
				return;
			}
			?>
			<style>
				.bwf-utm-box-data {
					margin: 10px 0;
				}

				.bwf-utm-box-data > div > span:nth-child(1) {
					font-weight: 500;
					width: 80px;
					display: inline-block;
					min-width: 105px;
				}

				.bwf-utm-box-data > div {
					margin-bottom: 8px;
					display: flex;
					word-break: break-all;
				}

				.bwf-utm-box-data .bwf-utm-data-gap {
					display: block;
					clear: both;
					height: 1px;
					border-bottom: 1px solid #eee;
					margin-bottom: 10px;
				}

				.bwf-utm-box-data .bwf-journey-list {
					margin: 4px 0 0;
					padding-left: 18px;
				}

				.bwf-utm-box-data .bwf-journey-list li {
					margin-bottom: 4px;
				}
			</style>
			<div class="bwf-utm-box-data">
				<div class="bwf-utm-data-gap"></div>
				<?php
				foreach ( $data as $item ) {
					?>

					<div>
						<span class="bwf-utm-lable"><?php echo esc_html( $item['name'] ) . ': '; ?></span>
						<span class="bwf-utm-text"><?php echo wp_kses_post( $item['value'] ); ?></span>
					</div>

					<?php
				}
				?>
			</div>
			<?php
		}

		public function string_length( $string, $length = 255 ) {
			return ( strlen( $string ) > $length ) ? substr( $string, 0, $length ) : $string;
		}

		/**
		 * Remove emojis and 4-byte UTF-8 characters from string
		 * Uses a single efficient regex pattern
		 *
		 * @param string $string The string to clean
		 * @return string Cleaned string without emojis
		 */
		public function strip_emojis( $string ) {
			if ( empty( $string ) ) {
				return $string;
			}

			// Remove emojis and 4-byte UTF-8 characters in one go
			$string = preg_replace( '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1FA70}-\x{1FAFF}\x{10000}-\x{10FFFF}]/u', '', $string );

			// Clean up extra spaces
			$string = preg_replace( '/\s+/', ' ', trim( $string ) );

			return $string;
		}

		/**
		 * remove query param from url
		 *
		 * @param $url
		 * @param $is_journey
		 *
		 * @return mixed|string
		 */
		public function parse_url_query_param( $url, $is_journey = false ) {
			$get_referrer = ! empty( $url ) ? wp_parse_url( $url ) : '';
			$referrer_url = '';
			if ( is_array( $get_referrer ) ) {
				if ( ! $is_journey && isset( $get_referrer['host'] ) ) {
					$referrer_url = $get_referrer['host'] . ( isset( $get_referrer['path'] ) ? $get_referrer['path'] : '' );
				} elseif ( isset( $get_referrer['path'] ) ) {
					$referrer_url = $get_referrer['path'];

				}
			}

			return $this->string_length( $referrer_url );
		}
	}

	WFFN_Visitor_Tracking::get_instance();
}
