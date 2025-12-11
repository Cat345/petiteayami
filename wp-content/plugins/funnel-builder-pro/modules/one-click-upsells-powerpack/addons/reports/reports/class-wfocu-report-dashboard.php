<?php //phpcs:ignore
/**
 * Upstroke Admin Report - Dashboard Stats
 *
 * Creates the upsells admin reports area.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( 'WFOCU_Upstroke_Report_Dashboard' ) ) {
	class WFOCU_Upstroke_Report_Dashboard {

		/**
		 * Hook in additional reporting to WooCommerce dashboard widget
		 */
		public function __construct() {

			// Add the dashboard widget text
			add_action( 'woocommerce_after_dashboard_status_widget', __CLASS__ . '::add_upstroke_stats_to_dashboard' );

			// Add dashboard necessary scripts / styles
			add_action( 'admin_enqueue_scripts', __CLASS__ . '::dashboard_upstroke_scripts' );
		}

		/**
		 * Add the upstroke specific details to the bottom of the dashboard woocommerce status widget
		 *
		 * @since 1.0
		 */
		public static function add_upstroke_stats_to_dashboard() {

			$upsells_data = WFOCU_Core()->track->query_results( array(
				'data'        => array(
					'value'          => array(
						'type'     => 'col',
						'function' => 'SUM',
						'name'     => 'upsells',
					),
					'action_type_id' => array(
						'type'     => 'col',
						'function' => 'COUNT',
						'name'     => 'item_count',
					),
					'timestamp'      => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'upsells_date',
					),
				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 5,
						'operator' => '=',
					),
				),
				'query_type'  => 'get_results',
				'event_range' => true,
				'order_by'    => 'events.timestamp',
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order'       => 'ASC',
				'start_date'  => strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) ),
				'end_date'    => strtotime( 'tomorrow midnight' ),
			) );

			$total_upsells = array_sum( wp_list_pluck( $upsells_data, 'upsells' ) );
			$total_count   = array_sum( wp_list_pluck( $upsells_data, 'item_count' ) );

			?>
            <li class="upsells-total">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=upsells&report=upsells_by_date&range=month' ) ); ?>">
					<?php
					/* translators: %s: total upsells price */
					printf( wp_kses_post( __( '<strong>%s </strong> one click upsells revenue', 'woocommerce' ) ), wp_kses_post( wc_price( ( $total_upsells ) ) ) );
					?>
                </a>
            </li>
            <li class="upsells-count">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=upsells&report=upsells_by_date&range=month' ) ); ?>">
					<?php
					/* translators: %s: Offer accepted  */
					printf( wp_kses_post( _n( '<strong>%s Upsells</strong> offer accepted this month', '<strong>%s Upsells</strong> offers accepted this month', $total_count, 'woocommerce' ) ), esc_html( $total_count ) );
					?>
                </a>
            </li>
			<?php
		}

		/**
		 * Add the upstroke specific style to the bottom of the dashboard woocommerce status widget
		 *
		 * @since 1.0
		 */
		public static function dashboard_upstroke_scripts() {
			$screen = get_current_screen();
			if ( 'dashboard' === $screen->id ) {
				$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
				wp_enqueue_style( 'wfocu-dashboard-report', dirname( plugin_dir_url( __FILE__ ) ) . '/assets/css/wfocu-upstroke-reports-dashboard' . $suffix . '.css', array(), WF_UPSTROKE_POWERPACK_VERSION );
			}
		}
	}

	return new WFOCU_Upstroke_Report_Dashboard();
}