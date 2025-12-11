<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WC_Report_Upsells_By_Customer' ) ) {
	/**
	 * Upstroke Admin Report - upstroke by date
	 *
	 * Find the number upsells accepted between by dates
	 *
	 */
	class WC_Report_Upsells_By_Customer extends WP_List_Table {

		private $totals;

		/**
		 * WC_Report_Upsells_By_Customer constructor.
		 */
		public function __construct() {
			parent::__construct( array(
				'singular' => __( 'Customer', 'woocommerce' ),
				'plural'   => __( 'Customers', 'woocommerce' ),
				'ajax'     => false,
			) );
		}

		/**
		 * No customers found text.
		 */
		public function no_items() {
			esc_html_e( 'No customers found.', 'woocommerce' );
		}

		/**
		 * Output the report header
		 */
		public function output_report() {

			$this->prepare_items();
			echo '<div id="poststuff" class="woocommerce-reports-wide">';
			echo '	<div id="postbox-container-1" class="postbox-container" style="width: 280px;"><div class="postbox" style="padding: 10px;">';
			echo '	<h3>' . esc_html__( 'Total Upsells', 'woofunnels-upstroke-power-pack' ) . '</h3>';
			echo '	<p><strong>' . esc_html__( 'Total Customers', 'woofunnels-upstroke-power-pack' ) . '</strong> : ' . esc_html( $this->totals['total_customers'] ) . '<br />';
			echo '	<p><strong>' . esc_html__( 'Total Upsells', 'woofunnels-upstroke-power-pack' ) . '</strong> : ' . wp_kses_post( wc_price( esc_html( $this->totals['total_upsells'] ) ) ) . '<br />';
			echo '</div></div>';
			$this->display();
			echo '</div>';

		}

		/**
		 * Prepare customers list items.
		 */
		public function prepare_items() {
			global $wpdb;

			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
			$current_page          = absint( $this->get_pagenum() );
			$per_page              = absint( apply_filters( 'wfocu_reports_customers_per_page', 20 ) );
			$offset                = absint( ( $current_page - 1 ) * $per_page );
			$limit                 = $per_page;

			$this->totals = self::get_data();
			$this->items  = array();

			$customer_sessions = $wpdb->get_results( $wpdb->prepare( 'SELECT MAX(s.id) as last_sess_id, s.email, CONVERT(GROUP_CONCAT(DISTINCT (s.id) ORDER BY s.id DESC SEPARATOR \',\') USING utf8) as sess_ids FROM `' . $wpdb->prefix . 'wfocu_session` as s GROUP BY s.email ORDER by last_sess_id  DESC LIMIT %d OFFSET %d', $limit, $offset ) );

			$sess_ids        = implode( ',', wp_list_pluck( $customer_sessions, 'sess_ids' ) );
			$sess_ids_arr    = explode( ',', $sess_ids );
			$customer_events = WFOCU_Core()->track->query_results( array(
				'data'       => array(
					'sess_id'        => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'sess_id',
					),
					'value'          => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'upsells',
					),
					'action_type_id' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'action_type_id',
					),
				),
				'where'      => array(
					array(
						'key'      => 'events.sess_id',
						'value'    => $sess_ids_arr,
						'operator' => 'IN',
					),
				),
				'query_type' => 'get_results',
			) );

			/**
			 * Preparing items for each customer
			 */
			foreach ( is_array( $customer_sessions ) ? $customer_sessions : array() as $key => $customer_session ) {
				$this->items[ $key ]['customer_email']    = $customer_session->email;
				$this->items[ $key ]['upsells']           = 0;
				$this->items[ $key ]['funnels_triggered'] = 0;
				$this->items[ $key ]['offers_accepted']   = 0;
				$this->items[ $key ]['offers_declined']   = 0;
				$this->items[ $key ]['offers_expired']    = 0;

				foreach ( $customer_events as $ct_evennt ) {
					if ( in_array( $ct_evennt->sess_id, explode( ',', $customer_session->sess_ids ), true ) ) {
						switch ( $ct_evennt->action_type_id ) {
							case 1:
								$this->items[ $key ]['funnels_triggered'] ++;
								break;
							case 4:
								$this->items[ $key ]['offers_accepted'] ++;
								$this->items[ $key ]['upsells'] += ( $ct_evennt->upsells > 0 ) ? $ct_evennt->upsells : 0;

								break;
							case 6:
								$this->items[ $key ]['offers_declined'] ++;
								break;
							case 7:
								$this->items[ $key ]['offers_expired'] ++;
								break;
							default:
								break;
						}
					}
				}
			}

			/**
			 * Setting Pagination.
			 */
			$this->set_pagination_args( array(
				'total_items' => $this->totals['total_customers'],
				'per_page'    => $per_page,
				'total_pages' => ceil( $this->totals['total_customers'] / $per_page ),
			) );
		}

		/**
		 * Get columns.
		 *
		 * @return array of columns
		 */
		public function get_columns() {

			$columns = array(
				'customer_email'    => __( 'Customer', 'woocommerce' ),
				/* translators: %s: help tip html */
				'upsells'           => sprintf( __( 'Total Upsells %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total upsell amount ordered by this customer.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: help tip html */
				'funnels_triggered' => sprintf( __( 'Funnels Triggers %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'The funnels veiwed/seen by this customer.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: help tip html */
				'offers_accepted'   => sprintf( __( 'Offers Accepted %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers accepted by this customer.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: help tip html */
				'offers_declined'   => sprintf( __( 'Offers Rejected %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers declined by this customer after viewing.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: help tip html */
				'offers_expired'    => sprintf( __( 'Offers Expired  %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers expires after viewing by this customer.', 'woofunnels-upstroke-power-pack' ) ) ),
			);

			return $columns;
		}

		/**
		 * Get the data
		 *
		 * @param array $args
		 *
		 * @return array of customer's totals
		 */
		public static function get_data( $args = array() ) {

			$customer_totals = array();

			$total_upsells = WFOCU_Core()->track->query_results( array(
				'data'       => array(
					'value' => array(
						'type'     => 'col',
						'function' => 'SUM',
						'name'     => 'total_upsells',
					),
				),
				'where'      => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 4,
						'operator' => '=',
					),
				),
				'query_type' => 'get_results',
			) );

			$all_customers = WFOCU_Core()->track->query_results( array(
				'data'          => array(
					'email' => array(
						'type'     => 'col',
						'function' => 'DISTINCT',
						'name'     => 'useremail',
					),
				),
				'query_type'    => 'get_results',
				'session_table' => true,
			) );

			$customer_totals['total_upsells']   = $total_upsells[0]->total_upsells;
			$customer_totals['total_customers'] = count( wp_list_pluck( $all_customers, 'useremail' ) );

			return $customer_totals;
		}

		/**
		 * Setting column default value
		 *
		 * @param object $user_data
		 * @param string $column_name
		 *
		 * @return string|void
		 */
		public function column_default( $user_data, $column_name ) {

			switch ( $column_name ) {

				case 'customer_email':
					return $user_data['customer_email'];

				case 'upsells':
					return wc_price( $user_data['upsells'] );

				case 'funnels_triggered':
					return $user_data['funnels_triggered'];

				case 'offers_accepted':
					return $user_data['offers_accepted'];

				case 'offers_declined':
					return $user_data['offers_declined'];

				case 'offers_expired':
					return $user_data['offers_expired'];
			}

			return '';
		}
	}
}