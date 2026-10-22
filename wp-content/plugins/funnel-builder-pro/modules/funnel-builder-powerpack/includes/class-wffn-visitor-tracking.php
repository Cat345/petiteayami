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
			 * Newer framework copies register the same visitor pipeline, which would
			 * render the tracker twice, migrate the journey column twice, append the
			 * upsell view twice and add a second meta box. This class owns that work,
			 * so stand the framework's copy down instead of standing down ourselves.
			 */
			add_action( 'wp_loaded', array( $this, 'unhook_framework_tracking' ), 0 );

			add_action( 'wp_enqueue_scripts', array( $this, 'render_utm_tracker' ), 1 );
			/** Upsell offer pages render a custom template head — enqueue there too (idempotent by handle) */
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_utm_tracker' ), 10 );

			add_filter( 'bwf_tracking_visitor_data', array( $this, 'get_tracking_visitor_data' ), 10, 2 );
			add_filter( 'bwf_tracking_data_before_insert', array( $this, 'maybe_add_thankyou_data' ), 10, 1 );
			add_action( 'bwf_tracking_order_inserted', array( $this, 'update_upsell_order_journey_data' ), 10, 2 );
			add_filter( 'bwf_insert_conversion_tracking_data', array( $this, 'maybe_populate_referrer_from_utm' ), 10, 1 );

			add_action( 'wc_ajax_wfocu_front_register_views', array( __CLASS__, 'update_upsell_journey' ) );
			add_action( 'wffn_optin_ty_page_setup_completed', array( $this, 'append_optin_ty_to_journey' ), 10, 1 );
			add_action( 'admin_init', array( $this, 'maybe_update_conversion_journey_column' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_single_order_meta_box' ), 50, 2 );
		}

		/**
		 * Detach the framework copy from the hooks this class handles.
		 *
		 * Runs on wp_loaded: the framework registers at load time, and every hook
		 * removed here fires later, so the removal always lands in between. Each
		 * callback is addressed exactly as it was added — same instance, same
		 * priority — and a copy that never registered simply does not match.
		 *
		 * @since 3.16.1
		 *
		 * @return void
		 */
		public function unhook_framework_tracking() {
			if ( ! class_exists( 'BWF_Ecomm_Tracking_Common' ) || ! method_exists( 'BWF_Ecomm_Tracking_Common', 'get_instance' ) ) {
				return;
			}

			$framework = BWF_Ecomm_Tracking_Common::get_instance();
			if ( ! $framework instanceof BWF_Ecomm_Tracking_Common ) {
				return;
			}

			remove_action( 'wp_enqueue_scripts', array( $framework, 'render' ), 1 );
			remove_action( 'add_meta_boxes', array( $framework, 'add_single_order_meta_box' ), 50 );
			remove_action( 'admin_init', array( $framework, 'maybe_update_conversion_journey_column' ) );
			remove_action( 'wc_ajax_wfocu_front_register_views', array( 'BWF_Ecomm_Tracking_Common', 'update_upsell_journey' ) );
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
		 * Whether customer-journey collection is enabled.
		 *
		 * Self-contained (reads the options directly) and memoised per request so it
		 * is safe to call from the static write points even when the Pro visitor
		 * pipeline has stood down for a legacy framework. AND-combines track_utms and
		 * track_journey: when UTM tracking is off the tracker never loads, so journey
		 * is implicitly off (decision D2).
		 *
		 * @since 3.16.1
		 *
		 * @return bool
		 */
		/**
		 * Whether either tracking option is on.
		 *
		 * The order meta box carries the UTM/insight data and the journey, so it
		 * has nothing to show once both are switched off.
		 *
		 * @since 3.16.1
		 *
		 * @return bool
		 */
		public static function is_tracking_meta_enabled() {
			$settings = BWF_Admin_General_Settings::get_instance();

			return wffn_string_to_bool( $settings->get_option( 'track_utms' ) ) || wffn_string_to_bool( $settings->get_option( 'track_journey' ) );
		}

		public static function is_journey_enabled() {
			static $enabled = null;
			if ( null !== $enabled ) {
				return $enabled;
			}

			$settings = BWF_Admin_General_Settings::get_instance();
			$enabled  = wffn_string_to_bool( $settings->get_option( 'track_utms' ) ) && wffn_string_to_bool( $settings->get_option( 'track_journey' ) );

			return $enabled;
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
			if ( class_exists( 'WooCommerce' ) && class_exists( 'WFFN_Common' ) && WFFN_Common::wffn_is_funnel_pro_active() && function_exists( 'WFOCU_Core' ) && WFOCU_Core()->public->if_is_offer() ) {
				$journey_control = 'disable';
			}

			/**
			 * Administrators and shop managers are not funnel traffic, and the
			 * journey cookie they accumulate rides into wp-admin with the auth
			 * cookies, where it can push the request headers past the web server's
			 * limit (Apache LimitRequestFieldSize, nginx large_client_header_buffers)
			 * and cause intermittent HTTP 400s. 'remove' additionally makes the JS
			 * drop any journey cookie already recorded, unlike 'disable' which must
			 * leave the cookie intact for offer pages.
			 *
			 * Filter `wffn_journey_skip_for_user` to widen or narrow who is skipped.
			 */
			$skip_journey = current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
			if ( true === apply_filters( 'wffn_journey_skip_for_user', $skip_journey ) ) {
				$journey_control = 'remove';
			}

			/**
			 * When journey recording is turned off the tracker still loads for UTMs,
			 * but any existing cookie must be dropped on the next view. 'remove' wins
			 * over the offer-page 'disable' so the cookie does not linger while off.
			 */
			if ( ! self::is_journey_enabled() ) {
				$journey_control = 'remove';
			}

			/**
			 * On the thank-you page the journey has already been persisted onto the order,
			 * so the tracker drops the cookie to start the next journey clean.
			 */
			$is_thankyou_page = ( class_exists( 'WooCommerce' ) && class_exists( 'WFFN_Core' ) && isset( WFFN_Core()->thank_you_pages ) && is_callable( array( WFFN_Core()->thank_you_pages, 'is_wfty_page' ) ) && true === WFFN_Core()->thank_you_pages->is_wfty_page() ) ? 1 : 0;

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
					/**
					 * Journey cookie budget. The oldest entry is dropped as soon as either
					 * bound is exceeded. 2000 bytes stays well under the ~8 KB Cookie-header
					 * limit most web servers enforce (clamped below the 4 KB per-cookie
					 * browser limit); 20 entries is the path depth the metabox shows.
					 */
					'journeyMaxBytes'    => min( absint( apply_filters( 'wffn_journey_cookie_max_bytes', 2000 ) ), 3800 ),
					'journeyMaxEntries'  => absint( apply_filters( 'wffn_journey_max_entries', 20 ) ),
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
				$wpdb->query( "ALTER TABLE `{$table_name}` ADD `journey` longtext AFTER `referrer`" ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $table_name is $wpdb->prefix + an internal constant, never user input; table/column identifiers can't be passed through $wpdb->prepare() placeholders.
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
				$store = $data;
			} else {
				$store = array(
					'j' => is_array( $data ) ? $data : array(),
					's' => array(),
				);
			}

			/**
			 * Titles are not persisted: the metabox resolves them from the entry's
			 * page id at render time. Strip any left by an older tracker so neither
			 * the order meta nor the conversion row carries them.
			 */
			foreach ( $store['j'] as $key => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				if ( array_key_exists( 't', $entry ) ) {
					unset( $store['j'][ $key ]['t'] );
				}
				// Normalise the page id to an int on every write so new rows are
				// uniform (the browser writer stores it as a string).
				if ( isset( $entry['i'] ) ) {
					$store['j'][ $key ]['i'] = absint( $entry['i'] );
				}
			}

			/**
			 * The 2000-byte/20-entry cap in utm-tracker.js only bounds what a
			 * well-behaved browser sends -- a forged Cookie header reaches this
			 * method directly, bypassing it entirely. Every other cookie field in
			 * get_common_tracking_data() is clamped server-side via string_length();
			 * journey needs the same treatment, enforced here so it applies
			 * uniformly on every write path that funnels through this method
			 * (cookie decode, thank-you/offer/optin appends), not just one of them.
			 * Same filters the JS tracker's own cap already uses, so both sides
			 * agree by default. Sort oldest-first once so both caps below can drop
			 * from the front and reliably mean "drop the oldest entry".
			 */
			ksort( $store['j'], SORT_NUMERIC );

			$max_entries = absint( apply_filters( 'wffn_journey_max_entries', 20 ) );
			if ( $max_entries > 0 && count( $store['j'] ) > $max_entries ) {
				$store['j'] = array_slice( $store['j'], -$max_entries, null, true );
			}

			$max_bytes = min( absint( apply_filters( 'wffn_journey_cookie_max_bytes', 2000 ) ), 3800 );
			while ( $max_bytes > 0 && count( $store['j'] ) > 1 && strlen( wp_json_encode( $store, JSON_UNESCAPED_SLASHES ) ) > $max_bytes ) {
				// Still oversized after the entry-count cap (e.g. an inflated
				// forged path/title per entry) -- drop the oldest entry and
				// re-check. Numeric-string timestamp keys are cast to real int
				// keys by PHP, so array_shift() would silently renumber every
				// remaining entry from 0 -- unset() by key leaves them intact.
				unset( $store['j'][ array_key_first( $store['j'] ) ] );
			}

			return $store;
		}

		/**
		 * Human-readable label for a journey entry, resolved through WordPress
		 * rather than read from the stored journey.
		 *
		 * The entry's page id is trusted only when the post it names lives on this
		 * site and its permalink path matches the recorded path; get_queried_object_id()
		 * also yields term and author ids on archives, which must not be mistaken
		 * for a post. Falls back to a legacy stored title, then to the path itself.
		 *
		 * @param array  $entry    journey entry (u, i, s and, for legacy rows, t)
		 * @param string $path     decoded path from entry['u'], query stripped — it is
		 *                         the last-resort label, so a query here would surface
		 *                         raw UTM parameters as the step title
		 * @param mixed  $site_idx entry['s'] (v2) or null
		 * @param array  $sites    the journey 's' map
		 * @return string
		 */
		public static function journey_entry_title($entry, $path, $all_titles_by_id = [])
		{
			if (! empty($entry['t'])) {
				return rawurldecode($entry['t']);
			}
			$post_id  = (isset($entry['i']) && is_numeric($entry['i'])) ? absint($entry['i']) : 0;
			if (array_key_exists($post_id, $all_titles_by_id)) {
				return $all_titles_by_id[$post_id];
			}

			return $path;
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


		public static function removeScheme($url)
		{
			// Check if the URL has a scheme (// format or scheme://)
			if (preg_match('#^([a-z][a-z0-9+\-.]*:)?//#i', $url)) {
				return preg_replace('#^([a-z][a-z0-9+\-.]*:)?//#i', '', $url);
			}
			return $url;
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
			// Journey recording turned off — do not append the offer view. Static
			// gate call because this method is callable even when the constructor
			// stood down for a legacy framework.
			if ( ! self::is_journey_enabled() ) {
				return;
			}

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

			$store  = self::journey_normalize( json_decode( $tracking_data['journey'], true ) );
			$origin = self::site_origin();
			$idx    = self::journey_site_index( $store, $origin );

			$store['j'][ (int) round( microtime( true ) * 1000 ) ] = array(
				'u' => '/' . ltrim( $relative_path, '/' ),
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
			if ( ! self::is_journey_enabled() ) {
				return;
			}
			if ( ! $order instanceof WC_Order || ! is_array( $tracking_data ) || ! isset( $tracking_data['journey'] ) ) {
				return;
			}

			$orders   = ( true === $upsell_order ) ? array( $order->get_id() ) : array();
			$get_meta = $order->get_meta( '_wfocu_sibling_order', false );
			if ( is_array( $get_meta ) && ! empty( $get_meta ) ) {
				foreach ( $get_meta as $meta ) {
					$value    = $meta->get_data()['value'];
					$orders[] = ( $value instanceof WC_Order ) ? $value->get_id() : absint( $value );
				}
			}

			if ( 0 === count( $orders ) ) {
				return;
			}

			global $wpdb;
			foreach ( $orders as $order_id ) {
				$wpdb->update(
					$wpdb->prefix . $this->conv_table,
					array( 'journey' => $tracking_data['journey'] ),
					array(
						'type'   => 2,
						'source' => $order_id,
					)
				); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
			if ( ! self::is_journey_enabled() ) {
				return $tracking_data;
			}
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
				'i' => get_the_ID(),
				's' => $idx,
			);

			$tracking_data['journey'] = wp_json_encode( $store, JSON_UNESCAPED_SLASHES );

			return $tracking_data;
		}

		/**
		 * Append the optin confirmation page to the optin's stored journey.
		 *
		 * An order's thank-you page joins the journey through
		 * maybe_add_thankyou_data(), because the row is written while that page
		 * renders. An optin row is written on wffn_optin_form_submit, one request
		 * earlier, so its confirmation page has nowhere to land and the
		 * "Pages Visited" filter can never match it. Append it here instead, the
		 * same way an upsell offer view is appended after the fact.
		 *
		 * @since 3.16.1
		 *
		 * @param int $page_id Optin confirmation page id.
		 *
		 * @return void
		 */
		public function append_optin_ty_to_journey( $page_id ) {
			if ( ! self::is_journey_enabled() ) {
				return;
			}

			$page_id = absint( $page_id );
			if ( $page_id <= 0 ) {
				return;
			}

			$row = $this->get_optin_tracking_row();
			if ( empty( $row ) ) {
				return;
			}

			$store = self::journey_normalize( json_decode( $row['journey'], true ) );

			// The page is reachable on refresh and on a back-navigation, so a row
			// that already names it is left alone.
			foreach ( $store['j'] as $entry ) {
				if ( is_array( $entry ) && isset( $entry['i'] ) && absint( $entry['i'] ) === $page_id ) {
					return;
				}
			}

			$permalink = get_permalink( $page_id );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				return;
			}

			$idx = self::journey_site_index( $store, self::site_origin() );

			$store['j'][ (int) round( microtime( true ) * 1000 ) ] = array(
				'u' => '/' . ltrim( substr( $permalink, strlen( home_url( '/' ) ) ), '/' ),
				'i' => $page_id,
				's' => $idx,
			);

			global $wpdb;
			$wpdb->update( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prefix . $this->conv_table,
				array( 'journey' => wp_json_encode( $store, JSON_UNESCAPED_SLASHES ) ),
				array( 'id' => absint( $row['id'] ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		/**
		 * The conversion row for the optin submission that led to this page.
		 *
		 * The submission's `opid` travels to the confirmation page in the query
		 * string, and falls back to the funnel session when a template links to the
		 * page without it. It names an entry in the optin table, whose id the
		 * conversion row carries as its `source`.
		 *
		 * @since 3.16.1
		 *
		 * @return array Row with `id` and `journey`, or empty when it cannot be identified.
		 */
		private function get_optin_tracking_row() {
			$opid = sanitize_text_field( (string) filter_input( INPUT_GET, 'opid', FILTER_UNSAFE_RAW ) ); //phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter

			if ( '' === $opid && class_exists( 'WFFN_Core' ) ) {
				$opid = (string) WFFN_Core()->data->get( 'opid' );
			}

			if ( '' === $opid ) {
				return array();
			}

			global $wpdb;

			$entry_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}bwf_optin_entries WHERE opid = %s ORDER BY id DESC LIMIT 1", $opid ) ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $entry_id <= 0 ) {
				return array();
			}

			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, journey FROM {$wpdb->prefix}{$this->conv_table} WHERE type = %d AND source = %d ORDER BY id DESC LIMIT 1", 1, $entry_id ), ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			return ( is_array( $row ) && ! empty( $row['journey'] ) ) ? $row : array();
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
			 *
			 * journey_normalize()'s own entry/byte caps trim a *decoded* journey down to
			 * size, but a single forged entry whose own field (e.g. `u`) is huge slips
			 * through them entirely: the trimming loop stops once one entry remains, so
			 * that lone entry's size is never checked. Reject an oversized *raw* cookie
			 * value outright, before decoding it at all -- this bounds every entry count,
			 * including one, and avoids json_decode() on an arbitrarily large forged
			 * string. Checked against the outer safety ceiling (3800), not the tighter
			 * 2000-byte target the client aims for -- the raw value carries addslashes()
			 * escaping overhead on top of the JSON the client actually measured against
			 * its own cap, so a legitimate cookie can land above 2000 raw bytes without
			 * being oversized.
			 */
			$journey_raw   = isset( $get_data['wffn_journey'] ) ? $get_data['wffn_journey'] : '';
			$journey_limit = 3800;
			$journey_data  = ( '' !== $journey_raw && strlen( $journey_raw ) <= $journey_limit ) ? json_decode( stripcslashes( $journey_raw ), true ) : array();
			$journey_data  = is_array( $journey_data ) ? $journey_data : array();
			$journey_data  = self::journey_normalize( $journey_data );

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
				'journey'           => ( self::is_journey_enabled() && ! empty( $journey_data['j'] ) ) ? wp_json_encode( $journey_data, JSON_UNESCAPED_SLASHES ) : '',
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

			// Nothing left to show once both tracking options are off.
			if ( ! self::is_tracking_meta_enabled() ) {
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
			$query    = $wpdb->prepare( 'SELECT * from ' . $wpdb->prefix . $this->conv_table . ' WHERE type = %s AND source = %d', 2, $order_id ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is $wpdb->prefix + an internal constant, never user input; the %s/%d placeholders cover every actual value.
			$get_data = $wpdb->get_row( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $query was already built via $wpdb->prepare() on the line above.
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
				// $first_click is a bwf_conversion_tracking column, already a
				// site-local MySQL string (written via current_time('mysql')).
				// strtotime() has no timezone context of its own and would parse
				// it as UTC, then wp_date() shifts it to site-local again on top
				// -- double-converting. mysql2date() parses it in the site's own
				// timezone first, so there is nothing left to shift.
				$first_click_formatted = function_exists( 'mysql2date' )
					? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $first_click )
					: $first_click;
				$data['first_click']   = array(
					'name'  => __( 'First Interaction', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $first_click_formatted ? $first_click_formatted : $first_click,
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
					'name'  => __( 'Source', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ucfirst( $get_data['utm_source'] ),
					'group' => 'utm',
				);
			}
			if ( isset( $get_data['utm_medium'] ) && '' !== $get_data['utm_medium'] ) {
				$data['utm_medium'] = array(
					'name'  => __( 'Medium', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ucfirst( $get_data['utm_medium'] ),
					'group' => 'utm',
				);
			}
			if ( isset( $get_data['utm_campaign'] ) && '' !== $get_data['utm_campaign'] ) {
				$data['utm_campaign'] = array(
					'name'  => __( 'Campaign', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $get_data['utm_campaign'],
					'group' => 'utm',
				);
			}
			if ( isset( $get_data['utm_term'] ) && '' !== $get_data['utm_term'] ) {
				$data['utm_term'] = array(
					'name'  => __( 'Term', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $get_data['utm_term'],
					'group' => 'utm',
				);
			}
			if ( isset( $get_data['utm_content'] ) && '' !== $get_data['utm_content'] ) {
				$data['utm_content'] = array(
					'name'  => __( 'Content', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => $get_data['utm_content'],
					'group' => 'utm',
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
			if ( isset( $get_data['referrer'] ) && '' !== $get_data['referrer'] ) {
				$ref_url   = ( is_array( $ref ) && isset( $ref[0] ) ) ? $ref[0] : '';
				$ref_host  = ( '' !== $ref_url ) ? wp_parse_url( $ref_url, PHP_URL_HOST ) : '';
				$ref_label = ( is_string( $ref_host ) && '' !== $ref_host ) ? $ref_host : $ref_url;

				$data['referrer'] = array(
					'name'  => __( 'Referrer', 'woofunnels' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'value' => ( '' !== $ref_url ) ? '<a href="' . esc_url( $ref_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $ref_label ) . '</a>' : '',
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
			 * Shown whenever a journey exists. The wffn_show_customer_journey_meta
			 * filter remains as an override, now defaulting to true.
			 */
			if ( ! empty( $get_data['journey'] ) && self::is_journey_enabled() && true === apply_filters( 'wffn_show_customer_journey_meta', true, $post, $meta_data ) ) {

				$all_found_ids = [];



				$single_journey = WFFN_Journey::decode($get_data['journey']);

				// Check if decoded journey exists and contains valid 'j' data
				if (!empty($single_journey['j']) && is_array($single_journey['j'])) {
					$extracted_ids = wp_list_pluck($single_journey['j'], 'i');

					// Filter out any null, empty, or non-numeric IDs
					$clean_ids = array_filter($extracted_ids, fn($id) => !empty($id) && is_numeric($id));

					if (!empty($clean_ids)) {
						$all_found_ids = array_merge($all_found_ids, array_values($clean_ids));
					}
				}

			// Remove duplicate IDs and sanitize to integer values
			$all_found_ids = array_values(array_unique(array_map('intval', $all_found_ids)));
			$titles_by_id = [];

			// Query database ONLY if we have valid IDs to prevent SQL errors
			if (!empty($all_found_ids)) {
				global $wpdb;

				$placeholders = implode(',', array_fill(0, count($all_found_ids), '%d'));



					$db_results = $wpdb->get_results($wpdb->prepare(
						"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_status = 'publish'",
						$all_found_ids
					), OBJECT_K);

					if (!empty($db_results) && is_array($db_results)) {
						$titles_by_id = array_map(fn($item) => $item->post_title ?? '', $db_results);
					}
				}




			$journey_steps = WFFN_Journey::resolve( $get_data['journey'],$titles_by_id );
				if ( ! empty( $journey_steps ) ) {
					$date_format = get_option( 'date_format' );
					$time_format = get_option( 'time_format' );
					$has_wp_date = function_exists( 'wp_date' );

					$journey_html = '<ol class="bwf-ct-journey">';
					$step_count   = count( $journey_steps );
					$current_day  = '';



					foreach ( $journey_steps as $index => $step ) {
						// A heading whenever the visit crosses into another day.
						$day = ( $step['ts'] > 0 && $has_wp_date ) ? wp_date( $date_format, $step['ts'] ) : '';
						if ( '' !== $day && $day !== $current_day ) {
							$journey_html .= '<li class="bwf-ct-journey-day">' . esc_html( $day ) . '</li>';
							$current_day   = $day;
						}

						$when = ( $step['ts'] > 0 && $has_wp_date ) ? wp_date( $time_format, $step['ts'] ) : '';

						// Time on this page is the gap until the next visit; the final
						// page has no following visit to measure against.
						$next  = ( $index + 1 < $step_count ) ? $journey_steps[ $index + 1 ] : null;
						$spent = ( is_array( $next ) && ! empty( $next['ts'] ) && ! empty( $step['ts'] ) && $next['ts'] > $step['ts'] ) ? human_time_diff( $step['ts'], $next['ts'] ) : '';

						// Non-local steps carry a muted host suffix so the origin is clear.
						$host_suffix = '';
						if ( empty( $step['is_local'] ) && ! empty( $step['site'] ) ) {
							$host        = wp_parse_url( $step['site'], PHP_URL_HOST );
							$host_suffix = is_string( $host ) ? ' <span class="bwf-ct-journey-host">(' . esc_html( $host ) . ')</span>' : '';
						}

						$journey_html .= '<li>';
						$journey_html .= '<span class="bwf-ct-journey-time">' . esc_html( $when ) . '</span>';
						$journey_html .= '<span class="bwf-ct-journey-body">';
						$journey_html .= '<i class="bwf-ct-journey-dot"></i>';
						$journey_html .= '<a href="' . esc_url( $step['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $step['title'] ) . '</a>' . $host_suffix;
						$journey_html .= '<span class="bwf-ct-journey-path"> - ' . esc_html( $step['path'] ) . '</span>';
						if ( '' !== $spent ) {
							$journey_html .= '<span class="bwf-ct-journey-spent">' . esc_html( $spent ) . '</span>';
						}
						$journey_html .= '</span></li>';
					}

					// Final non-link node marking the conversion itself. $timestamp
					// is bwf_conversion_tracking.timestamp -- already a site-local
					// MySQL string (current_time('mysql')). mysql2date() parses it
					// in the site's own timezone, unlike strtotime()+wp_date(),
					// which double-converts (see the first_click fix above).
					$converted_at  = ( ! empty( $timestamp ) && function_exists( 'mysql2date' ) ) ? mysql2date( $time_format, $timestamp ) : '';
					$journey_html .= '<li class="bwf-ct-journey-done">';
					$journey_html .= '<span class="bwf-ct-journey-time">' . esc_html( $converted_at ) . '</span>';
					$journey_html .= '<span class="bwf-ct-journey-body">';
					$journey_html .= '<i class="bwf-ct-journey-dot bwf-ct-journey-tick"></i>';
					$journey_html .= esc_html__( 'Conversion completed', 'funnel-builder-pro' );
					$journey_html .= '</span></li></ol>';

					$data['journey'] = array(
						// Pro feature string — use the Pro text domain (phpcs-allowed, no suppression needed).
						'name'  => __( 'Customer Journey', 'funnel-builder-pro' ),
						'value' => $journey_html,
						'group' => 'journey',
						'count' => count( $journey_steps ),
					);
				}
			}

			$data = apply_filters( 'bwf_utm_tracking_meta_box', $data, $meta_data, $post );
			if ( empty( $data ) ) {
				return;
			}
			$groups = array(
				'details' => array(),
				'utm'     => array(),
				'journey' => array(),
			);
			foreach ( $data as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['name'] ) ) {
					continue;
				}
				// Rows added through the filter carry no group and read as details.
				$group = ( isset( $item['group'] ) && isset( $groups[ $item['group'] ] ) ) ? $item['group'] : 'details';

				$groups[ $group ][] = $item;
			}
			?>
			<style>
				.bwf-ct-box {
					margin: 6px 0 0;
					color: #353030;
					font-size: 13px;
					line-height: 20px;
				}

				/* Dragged into a full-width column the box would stretch, stranding
					the page count against the far edge. With a journey to show
					there is enough room to sit it beside the insights instead. */
				#normal-sortables .bwf-ct-box,
				#advanced-sortables .bwf-ct-box {
					max-width: 420px;
				}

				#normal-sortables .bwf-ct-box.bwf-ct-has-journey,
				#advanced-sortables .bwf-ct-box.bwf-ct-has-journey {
					display: grid;
					grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
					gap: 0 32px;
					max-width: 880px;
				}

				/* Divider between the columns. Drawn rather than bordered so it can
					start level with the content instead of the heading; the offset
					is the heading's line-height plus its bottom margin. */
				#normal-sortables .bwf-ct-has-journey .bwf-ct-col-insights,
				#advanced-sortables .bwf-ct-has-journey .bwf-ct-col-insights {
					position: relative;
					padding-left: 32px;
				}

				#normal-sortables .bwf-ct-has-journey .bwf-ct-col-insights:before,
				#advanced-sortables .bwf-ct-has-journey .bwf-ct-col-insights:before {
					content: "";
					position: absolute;
					left: 0;
					top: 34px;
					bottom: 0;
					width: 1px;
					background: #dedfea;
				}

				#normal-sortables .bwf-ct-has-journey .bwf-ct-col-journey,
				#advanced-sortables .bwf-ct-has-journey .bwf-ct-col-journey {
					order: -1;
				}

				/* The rule only separates the stacked sections. */
				#normal-sortables .bwf-ct-has-journey .bwf-ct-sep-journey,
				#advanced-sortables .bwf-ct-has-journey .bwf-ct-sep-journey {
					display: none;
				}

				.bwf-ct-row {
					display: grid;
					grid-template-columns: 104px 1fr;
					gap: 4px 12px;
					margin-bottom: 8px;
					word-break: break-word;
				}

				.bwf-ct-label {
					font-weight: 500;
				}

				.bwf-ct-box a {
					color: #0073aa;
					text-decoration: none;
				}

				.bwf-ct-sep {
					height: 1px;
					background: #dedfea;
					margin: 14px 0;
				}

				.bwf-ct-section {
					display: flex;
					align-items: baseline;
					justify-content: space-between;
					gap: 12px;
					margin-bottom: 10px;
					color: #353030;
					font-size: 11px;
					font-weight: 500;
					letter-spacing: .04em;
					text-transform: uppercase;
				}

				.bwf-ct-heading {
					margin: 0;
					padding: 0;
					color: #353030;
					font-size: 14px;
					line-height: 22px;
					font-weight: 500;
					text-transform: none;
					letter-spacing: 0;
				}

				.bwf-ct-col-insights .bwf-ct-heading {
					margin-bottom: 12px;
				}

				/* One grid for the whole list, so every row shares the same time
					column and the connector stays on a single vertical line. */
				.bwf-ct-journey {
					display: grid;
					grid-template-columns: max-content minmax(0, 1fr);
					column-gap: 8px;
					margin: 0;
					padding: 0;
					list-style: none;
				}

				.bwf-ct-journey li {
					display: contents;
				}

				.bwf-ct-journey li.bwf-ct-journey-day {
					display: block;
					grid-column: 1 / -1;
					margin-bottom: 10px;
					color: #353030;
					font-size: 12px;
				}

				.bwf-ct-journey-time {
					color: #82838e;
					font-size: 11px;
					line-height: 20px;
					white-space: nowrap;
				}

				.bwf-ct-journey-body {
					position: relative;
					min-width: 0;
					margin-left: 5px;
					padding: 0 0 14px 18px;
					word-break: break-word;
				}

				/* Runs from this row's dot centre to the next one's, so the last
					segment reaches the closing dot instead of stopping at the row. */
				.bwf-ct-journey-body:before {
					content: "";
					position: absolute;
					left: 0;
					top: 10px;
					bottom: -10px;
					width: 1px;
					background: #dedfea;
				}

				.bwf-ct-journey li:last-child .bwf-ct-journey-body {
					padding-bottom: 0;
				}

				/* A day heading closes the group above it, so the connector stops
					there rather than running through the date. */
				.bwf-ct-journey li:last-child .bwf-ct-journey-body:before,
				.bwf-ct-journey li:has(+ .bwf-ct-journey-day) .bwf-ct-journey-body:before {
					display: none;
				}

				.bwf-ct-journey-dot {
					position: absolute;
					left: -2.5px;
					top: 7px;
					width: 6px;
					height: 6px;
					border-radius: 50%;
					background: #82838e;
					/* Halo, so the dot reads above the connector running behind it.
						Core plus ring measures 12px across. */
					box-shadow: 0 0 0 3px #dedfea;
				}

				.bwf-ct-journey-tick {
					background: #09b29c;
					box-shadow: 0 0 0 3px #dcf2ed;
				}

				.bwf-ct-journey-spent {
					display: block;
					color: #82838e;
					font-size: 11px;
					font-style: italic;
				}

				.bwf-ct-journey-path,
				.bwf-ct-journey-host {
					color: #82838e;
				}

			</style>
			<div class="bwf-ct-box<?php echo ! empty( $groups['journey'] ) ? ' bwf-ct-has-journey' : ''; ?>">
				<div class="bwf-ct-col bwf-ct-col-insights">
					<h3 class="bwf-ct-heading"><?php esc_html_e( 'Insights', 'funnel-builder-pro' ); ?></h3>
					<?php foreach ( $groups['details'] as $item ) { ?>
						<div class="bwf-ct-row">
							<span class="bwf-ct-label"><?php echo esc_html( $item['name'] ); ?></span>
							<span class="bwf-ct-value"><?php echo wp_kses_post( $item['value'] ); ?></span>
						</div>
					<?php } ?>

					<?php if ( ! empty( $groups['utm'] ) ) { ?>
						<div class="bwf-ct-sep"></div>
						<div class="bwf-ct-section"><span><?php esc_html_e( 'UTM', 'funnel-builder-pro' ); ?></span></div>
						<?php foreach ( $groups['utm'] as $item ) { ?>
							<div class="bwf-ct-row">
								<span class="bwf-ct-label"><?php echo esc_html( $item['name'] ); ?></span>
								<span class="bwf-ct-value"><?php echo wp_kses_post( $item['value'] ); ?></span>
							</div>
						<?php } ?>
					<?php } ?>
				</div>

				<?php
				foreach ( $groups['journey'] as $item ) {
					?>
					<div class="bwf-ct-col bwf-ct-col-journey">
						<div class="bwf-ct-sep bwf-ct-sep-journey"></div>
						<div class="bwf-ct-section">
							<h3 class="bwf-ct-heading"><?php echo esc_html( $item['name'] ); ?></h3>
						</div>
						<?php echo wp_kses_post( $item['value'] ); ?>
					</div>
				<?php } ?>
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
