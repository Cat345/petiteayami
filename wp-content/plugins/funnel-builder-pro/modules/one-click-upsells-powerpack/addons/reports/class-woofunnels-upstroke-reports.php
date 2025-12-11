<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class WFOCU_Admin_Reports
 */
if ( ! class_exists( 'WFOCU_Admin_Reports' ) ) {


	class WFOCU_Admin_Reports {


		public static $instance;

		public static $funnel_id;

		/**
		 * WFOCU_Admin_Reports constructor.
		 */
		public function __construct() {
			$this->init_constants();
			$this->init_hooks();
		}

		/**
		 * Initializing contents
		 */
		public function init_constants() {
			self::$funnel_id = filter_input( INPUT_GET, 'funnel_id', FILTER_SANITIZE_NUMBER_INT );
		}

		/** Creating instance
		 *
		 * @return instance|WFOCU_Admin_Reports
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Adding functions on hooks
		 */
		public function init_hooks() {

			// Add the reports layout to the WooCommerce -> Reports admin section
			add_filter( 'woocommerce_admin_reports', __CLASS__ . '::initialize_woofunnels_upstroke_reports', 12, 1 );

			// Add the reports layout to the WooCommerce -> Reports admin section
			add_filter( 'wc_admin_reports_path', __CLASS__ . '::initialize_woofunnels_upstroke_reports_path', 12, 3 );

			// Add any necessary scripts
			add_action( 'admin_enqueue_scripts', __CLASS__ . '::woofunnels_upstroke_reports_scripts' );

			//Providing only accepted products array when product is being searched for funnel reporting
			add_action( 'wp_ajax_wfocu_accepted_product_search', array( $this, 'wfocu_accepted_product_search' ), 10, 2 );

			// Add any actions we need based on the screen
			add_action( 'current_screen', __CLASS__ . '::conditional_upstroke_reports_includes' );

		}

		/**
		 * Add the 'UpStroke' report tab to the WooCommerce reports screen.
		 *
		 * @param array Array of Report types & their labels
		 *
		 * @return array Array of Report types & their labels
		 * @since 1.0
		 */
		public static function initialize_woofunnels_upstroke_reports( $reports ) {

			$reports['upsells'] = array(
				'title'   => __( 'Upsells', 'woofunnels-upstroke-power-pack' ),
				'reports' => array(
					'upsells_by_date'     => array(
						'title'       => __( 'Upsells by Date', 'woofunnels-upstroke-power-pack' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( 'WC_Admin_Reports', 'get_report' ),
					),
					'upsells_by_funnel'   => array(
						'title'       => __( 'Upsells by Funnel', 'woofunnels-upstroke-power-pack' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( 'WFOCU_Admin_Reports', 'get_funnel_report' ),
					),
					'upsells_by_product'  => array(
						'title'       => __( 'Upsells by Product', 'woofunnels-upstroke-power-pack' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( 'WC_Admin_Reports', 'get_report' ),
					),
					'upsells_by_customer' => array(
						'title'       => __( 'Upsells by Customer', 'woofunnels-upstroke-power-pack' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( 'WC_Admin_Reports', 'get_report' ),
					),
				),
			);

			return $reports;
		}


		public static function get_funnel_report() {
			if ( empty( self::$funnel_id ) || self::$funnel_id < 1 ) {
				WC_Admin_Reports::get_report( 'upsells_by_funnel' );
			} else {
				include_once dirname( __FILE__ ) . '/reports/class-wfocu-upsells-by-offer.php';
				$report = new WC_Report_Upsells_By_Offer( self::$funnel_id );
				$report->output_report();
			}
		}

		/**
		 * If we hit one of our reports in the WC get_report function, change the path to our dir.
		 *
		 * @param report_path the path to the report.
		 * @param name the name of the report.
		 * @param class the class of the report.
		 *
		 * @return string  path to the report template.
		 * @since 1.0
		 */
		public static function initialize_woofunnels_upstroke_reports_path( $reporting_path, $name, $class ) {

			if ( in_array( strtolower( $class ), array(
				'wc_report_upsells_by_date',
				'wc_report_upsells_by_product',
				'wc_report_upsells_by_customer',
				'wc_report_upsells_by_funnel',
			), true ) ) {
				$reporting_path = dirname( __FILE__ ) . '/reports/class-wfocu-' . $name . '.php';
			}

			return $reporting_path;
		}

		/**
		 * Add any upstroke report javascript to the admin pages.
		 *
		 * @since 1.0
		 */
		public static function woofunnels_upstroke_reports_scripts() {

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			$screen       = get_current_screen();
			$wc_screen_id = sanitize_title( __( 'WooCommerce', 'woofunnels-upstroke-power-pack' ) );

			// Reports Upsells Pages
			if ( in_array( $screen->id, apply_filters( 'woocommerce_reports_screen_ids', array(
					$wc_screen_id . '_page_wc-reports',
					'toplevel_page_wc-reports',
					'dashboard',
				) ), true ) && ( 'upsells' === filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW ) ) ) {

				// Add currency localisation params for axis label
				wp_localize_script( 'wfocu-upstroke-reports', 'wfocu_upstroke_reports', array(
					'currency_format_num_decimals' => wc_get_price_decimals(),
					'currency_format_symbol'       => get_woocommerce_currency_symbol(),
					'currency_format_decimal_sep'  => esc_js( wc_get_price_decimal_separator() ),
					'currency_format_thousand_sep' => esc_js( wc_get_price_thousand_separator() ),
					'currency_format'              => esc_js( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ) ), // For accounting JS
				) );

				wp_enqueue_script( 'flot-order', plugin_dir_url( __FILE__ ) . 'assets/js/jquery.flot.orderBars' . $suffix . '.js', array(
					'jquery',
					'flot',
				), WF_UPSTROKE_POWERPACK_VERSION );
				wp_enqueue_script( 'flot-axis-labels', plugin_dir_url( __FILE__ ) . 'assets/js/jquery.flot.axislabels' . $suffix . '.js', array(
					'jquery',
					'flot',
				), WF_UPSTROKE_POWERPACK_VERSION );
			}
			if ( 'shop_order' === get_current_screen()->post_type ) {
				wp_enqueue_style( 'wfocu-timeline-style', plugin_dir_url( __FILE__ ) . 'assets/css/wfocu-timeline' . $suffix . '.css', array(), WF_UPSTROKE_POWERPACK_VERSION );
			}
		}

		/**
		 * Display a tooltip in the WordPress administration area.
		 *
		 * Uses wfocu_help_tip() when WooCommerce 2.5+ is active, otherwise it manually prints the HTML for a tooltip.
		 *
		 * @param string $tip The content to display in the tooltip.
		 *
		 * @return string
		 * @since  1.0.0
		 */
		public static function wfocu_help_tip( $tip, $allow_html = false ) {

			if ( function_exists( 'wc_help_tip' ) ) {

				$help_tip = wc_help_tip( $tip, $allow_html );

			} else {

				if ( $allow_html ) {
					$tip = wc_sanitize_tooltip( $tip );
				} else {
					$tip = esc_attr( $tip );
				}

				$help_tip = sprintf( '<img class="help_tip" data-tip="%s" src="%s/assets/images/help.png" height="16" width="16" />', $tip, esc_url( WC()->plugin_url() ) );
			}

			return $help_tip;
		}

		/**
		 * Hooked on ajax action  'wp_ajax_wfocu_accepted_product_search'
		 * Filtering product to show only accepted products in search hint on "Upsells by Product" Screen.
		 *
		 * @param $products
		 *
		 * @return array of products to be appeared in search
		 */
		public function wfocu_accepted_product_search( $term = false, $return = false ) {
			$term = ( ( empty( $term ) && isset( $_GET['term'] ) ) ? stripslashes( wc_clean( $_GET['term'] ) ) : wc_clean( $term ) );

			if ( empty( $term ) ) {
				wp_die();
			}

			$variations = true;
			if ( isset( $_GET['variations'] ) && 'true' !== $_GET['variations'] ) {
				$variations = false;
			}
			$ids = WFOCU_Common::search_products( $term, $variations );

			/**
			 * Products types that are allowed in the offers
			 */
			$allowed_types   = apply_filters( 'wfocu_offer_product_types', array(
				'simple',
				'variable',
				'variation',
			) );
			$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_editable' );
			$product_objects = array_filter( $product_objects, function ( $arr ) use ( $allowed_types ) {

				return $arr && is_a( $arr, 'WC_Product' ) && in_array( $arr->get_type(), $allowed_types, true );

			} );
			$products        = array();
			foreach ( $product_objects as $product_object ) {
				$products[] = array(
					'id'      => $product_object->get_id(),
					'product' => rawurldecode( WFOCU_Common::get_formatted_product_name( $product_object ) ),
				);
			}

			$all_offer_products = WFOCU_Core()->track->query_results( array(
				'data'       => array(
					'object_id' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'product_id',
					),
				),
				'where'      => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 5,
						'operator' => '=',
					),
				),
				'query_type' => 'get_results',
				'group_by'   => 'events.object_id',
			) );
			$accepted_products  = wp_list_pluck( $all_offer_products, 'product_id' );
			$final_result       = array();

			foreach ( ( is_array( $products ) && count( $products ) > 0 ) ? $products : array() as $key => $product ) {
				if ( ! in_array( $product['id'], $accepted_products, true ) ) {
					unset( $products[ $key ] );
				} else {
					$final_result[ $product['id'] ] = $product['product'];
				}
			}
			wp_send_json( apply_filters( 'wfocu_json_search_found_products', $final_result ) );
		}

		/**
		 * Add any reporting files we may need conditionally
		 *
		 * @since 1.0
		 */
		public static function conditional_upstroke_reports_includes() {

			$screen = get_current_screen();

			switch ( $screen->id ) {
				case 'dashboard':
					include_once __DIR__ . '/reports/class-wfocu-report-dashboard.php';
					break;
			}
		}
	}
}

if ( class_exists( 'WFOCU_Admin_Reports' ) ) {
	new WFOCU_Admin_Reports();
}

