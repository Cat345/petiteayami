<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WC_Report_Upsells_By_Funnel' ) ) {
	/**
	 * Upstroke Admin Report - upstroke by funnel
	 *
	 * Find the upsells accepted from funnels
	 *
	 */
	class WC_Report_Upsells_By_Funnel extends WP_List_Table {

		private $totals;
		private $funnels_deleted = false;
		private $filter_date = '';
		private $no_of_days = '';
		private $start_date = '';
		private $end_date = '';

		/**
		 * WC_Report_Upsells_By_Funnel constructor.
		 */
		public function __construct() {
			$this->detect_no_days();
			parent::__construct( array(
				'singular' => __( 'Funnel', 'woofunnels-upstroke-power-pack' ),
				'plural'   => __( 'Funnels', 'woofunnels-upstroke-power-pack' ),
			) );
		}

		/*
		* set start date and end date
		*/
		private function detect_no_days() {
			if ( isset( $_GET['no_of_days'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification
				$no_of_days       = sanitize_text_field( $_GET['no_of_days'] ); //phpcs:ignore WordPress.Security.NonceVerification
				$this->no_of_days = $no_of_days;
				if ( $no_of_days > - 1 ) {
					$this->filter_date = absint( $this->no_of_days );
					$this->start_date  = date( 'Y-m-d', strtotime( "-{$this->filter_date} days" ) );
					$this->end_date    = date( 'Y-m-d' );
				}
			}

			if ( isset( $_GET['date_range_first'] ) && isset( $_GET['date_range_second'] ) ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->start_date = sanitize_text_field( $_GET['date_range_first'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->end_date   = sanitize_text_field( $_GET['date_range_second'] );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		/**
		 * No funnels found text.
		 */
		public function no_items() {
			esc_html_e( 'No funnels found.', 'woofunnels-upstroke-power-pack' );
		}

		/**
		 * Output the report.
		 */
		public function output_report() {
			$this->prepare_items();
			echo '<div id="poststuff" class="woocommerce-reports-wide">';
			$this->display();
			if ( $this->funnels_deleted ) {
				$this->funnels_deleted();
			} ?>
            <style>
                .wp-list-table.funnels td.offer_details.column-offer_details a {
                    left: 15%;
                    position: relative;
                }
            </style>
			<?php
			echo '</div>';
		}

		/**
		 * Prepare funnels list items.
		 */
		public function prepare_items() {
			global $wpdb;

			// Prepare pagination variables
			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

			$current_page = absint( $this->get_pagenum() );
			$per_page     = absint( apply_filters( 'wcur_reports_funnels_per_page', 20 ) );
			$offset       = absint( ( $current_page - 1 ) * $per_page );
			$limit        = $per_page;

			// Fetch total funnel count first
			$this->totals = self::get_data();
			$this->items  = array();

			// Fetch all funnel IDs in one query
			$query_args = array(
				'data'           => array(
					'ID' => array(
						'type'     => 'post_data',
						'function' => 'DISTINCT',
						'name'     => 'funnel_id',
					),
				),
				'where'          => array(
					array(
						'key'      => 'posts.post_type',
						'value'    => 'wfocu_funnel',
						'operator' => '=',
					),
				),
				'query_type'     => 'get_results',
				'join_object_id' => true,
				'limit'          => $limit,
				'offset'         => $offset,
			);


			$time_query = '';

			if ( $this->start_date !== '' && $this->end_date !== '' ) {
				$query_args['event_range'] = true;
				$query_args['start_date']  = strtotime( $this->start_date );
				$query_args['end_date']    = strtotime( $this->end_date . ' +1 day' );
				$time_query                = " e.timestamp >= '" . date( 'Y-m-d H:i:s', $query_args['start_date'] ) . "' AND e.timestamp < '" . date( 'Y-m-d H:i:s', $query_args['end_date'] ) . "' AND ";
			}

			global $wpdb;
			$funnels = WFOCU_Core()->track->query_results( $query_args );

			// Pluck funnel IDs and event funnel IDs in one go
			$funnels_ids = wp_list_pluck( $funnels, 'funnel_id' );
			$ev_funnels  = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT event_meta.meta_value 
         FROM `' . $wpdb->prefix . 'wfocu_event_meta` AS event_meta  
         WHERE event_meta.meta_key = %s', '_funnel_id' ) );


			$all_funnels = array_unique( array_merge( $funnels_ids, $ev_funnels ) );
			rsort( $all_funnels );

			if ( empty( $all_funnels ) ) {
				return;
			}

			$all_funnels = array_slice( $all_funnels, $offset, $limit );

			if ( empty( $all_funnels ) ) {
				return;
			}

			// Prepare funnel event data in one batch query
			$funnel_event_data = $wpdb->get_results( $wpdb->prepare( "
            SELECT e.action_type_id AS action_id, e.value, em.meta_value AS funnel_id 
            FROM {$wpdb->prefix}wfocu_event AS e 
            INNER JOIN {$wpdb->prefix}wfocu_event_meta AS em 
            ON e.id = em.event_id
            WHERE " . $time_query . " em.meta_key = %s AND em.meta_value IN (" . implode( ',', array_fill( 0, count( $all_funnels ), '%d' ) ) . ")", '_funnel_id', ...$all_funnels ) );

			// Group events by funnel_id for easy access
			$grouped_events = [];
			foreach ( $funnel_event_data as $event ) {
				$grouped_events[ $event->funnel_id ][] = $event;
			}

			// Iterate over the funnels and process events
			foreach ( $all_funnels as $key => $funnel_id ) {
				$this->items[ $key ]['funnel_id'] = $funnel_id;
				$funnel_title                     = get_the_title( $funnel_id );
				$funnel_deleted                   = empty( $funnel_title );

				if ( $funnel_deleted ) {
					$this->funnels_deleted = true;
				}

				$this->items[ $key ]['funnel_title'] = $funnel_deleted ? sprintf( __( 'Funnel ID: %s*', 'woofunnels-upstroke-power-pack' ), $funnel_id ) : $funnel_title;

				// Prepare default metrics
				$offers_viewed   = 0.00;
				$offers_accepted = 0.00;
				$offers_rejected = 0.00;
				$offers_failed   = 0.00;
				$offers_expired  = 0.00;
				$upsells         = 0.00;

				// Fetch events for the current funnel
				if ( isset( $grouped_events[ $funnel_id ] ) ) {
					foreach ( $grouped_events[ $funnel_id ] as $event ) {
						switch ( $event->action_id ) {
							case '2':
								$offers_viewed ++;
								break;
							case '4':
								$offers_accepted ++;
								break;
							case '6':
								$offers_rejected ++;
								break;
							case '9':
								$offers_failed ++;
								break;
							case '7':
								$offers_expired ++;
								break;
							case '5':
								$upsell  = ( ! empty( $event->value ) && $event->value > 0 ) ? $event->value : 0;
								$upsells += floatval( $upsell );
								break;
							default:
								break;
						}
					}
				}

				// Store metrics in the items array
				$this->items[ $key ]['offers_viewed']   = $offers_viewed;
				$this->items[ $key ]['offers_accepted'] = $offers_accepted;
				$this->items[ $key ]['offers_rejected'] = $offers_rejected;
				$this->items[ $key ]['offers_failed']   = $offers_failed;
				$this->items[ $key ]['offers_expired']  = $offers_expired;
				$this->items[ $key ]['upsells']         = $upsells;

				// Calculate pending offers and conversion rate
				$this->items[ $key ]['offers_pending']  = $this->items[ $key ]['offers_expired'] + $this->items[ $key ]['offers_failed'];
				$divisor                                = $this->items[ $key ]['offers_accepted'] + $this->items[ $key ]['offers_pending'] + $this->items[ $key ]['offers_rejected'];
				$this->items[ $key ]['conversion_rate'] = ( $divisor > 0 ) ? wc_format_decimal( ( $this->items[ $key ]['offers_accepted'] / $divisor ) * 100, 2 ) . '%' : wc_format_decimal( '0.00' ) . '%';
			}

			// Pagination
			$this->set_pagination_args( array(
				'total_items' => $this->totals['funnel_count'],
				'per_page'    => $per_page,
				'total_pages' => ceil( $this->totals['funnel_count'] / $per_page ),
			) );
		}

		/**
		 * Get columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			$columns = array(
				'funnel_name'     => __( 'Funnels', 'woofunnels-upstroke-power-pack' ),
				/* translators: %s: average gross accepted */
				'offers_viewed'   => sprintf( __( 'Offers Viewed %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers viewed from this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'offers_accepted' => sprintf( __( 'Offers Accepted %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers accepted from this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'offers_rejected' => sprintf( __( 'Offers Rejected %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers rejected from this funnel', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'offers_pending'  => sprintf( __( 'Offers Pending  %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers pending (Failed + Expired) from this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'conversion_rate' => sprintf( __( 'Conversion Rate %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Conversion Rate.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'upsells'         => sprintf( __( 'Total Upsells  %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total upsells through this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'offer_details'   => sprintf( __( 'Offer Details  %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Offer-wise details for this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
			);

			return $columns;
		}

		/**
		 * Gather totals for funnels
		 *
		 * @param array $args
		 *
		 * @return int
		 */
		public static function get_data( $args = array() ) {
			$funnels_totals = array();
			$funnels        = WFOCU_Core()->track->query_results( array(
				'data'           => array(
					'ID' => array(
						'type'     => 'post_data',
						'function' => 'DISTINCT',
						'name'     => 'funnel_id',
					),
				),
				'where'          => array(
					array(
						'key'      => 'posts.post_type',
						'value'    => 'wfocu_funnel',
						'operator' => '=',
					),
				),
				'query_type'     => 'get_results',
				'join_object_id' => true,
			) );

			$funnels_totals['funnel_count'] = count( wp_list_pluck( $funnels, 'funnel_id' ) );

			return $funnels_totals;
		}

		/**
		 * No offer found or empty funnel_id text.
		 *
		 */
		public function funnels_deleted() {
			esc_html_e( '*These funnel(s) is/are deleted.', 'woofunnels-upstroke-power-pack' );
		}

		/**
		 * Default Column Value
		 *
		 * @param object $funnel_data
		 * @param string $column_name
		 *
		 * @return string|void
		 */
		public function column_default( $funnel_data, $column_name ) {

			switch ( $column_name ) {

				case 'funnel_name':
					return $funnel_data['funnel_title'];

				case 'offers_viewed':
					return $funnel_data['offers_viewed'];

				case 'offers_accepted':
					return $funnel_data['offers_accepted'];

				case 'offers_rejected':
					return $funnel_data['offers_rejected'];

				case 'offers_pending':
					return $funnel_data['offers_pending'];

				case 'conversion_rate':
					return $funnel_data['conversion_rate'];

				case 'upsells':
					return wc_price( $funnel_data['upsells'] );

				case 'offer_details':
					return '<a href="' . admin_url( 'admin.php?page=wc-reports&tab=upsells&report=upsells_by_funnel&funnel_id=' . $funnel_data['funnel_id'] ) . '">' . sprintf( __( 'View', 'woofunnels-upstroke-power-pack' ) ) . '</a>';
			}

			return '';
		}

		protected function display_tablenav( $which ) {
			if ( 'top' === $which ) {
				wp_nonce_field( 'bulk-' . $this->_args['plural'] );
			}
			?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				if ( 'top' === $which ) {
					?>
                    <div class="wfocu_abandoned_filter" style="margin:15px 0px -18px">
						<?php
						$menus = $this->get_filter_menu();
						foreach ( $menus as $menu ) {
							$default_menu_class = ( isset( $menu['current'] ) ) ? 'wfocur_btn_selected' : '';
							$class              = $menu['class'] . ' ' . $default_menu_class;
							echo '<a class="wfocur_design_btn ' . esc_attr__( $class ) . '" href="' . $menu['link'] . '">' . esc_html__( $menu['name'] ) . '</a>'; //phpcs:ignore WordPress.Security.EscapeOutput
						}
						$show_range = isset( $_GET['wfocu_date_range_nonce'] ) ? 'wfocu_show_date_range_search_form' : 'wfocu_hide_date_range_search_form';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
						?>

                        <div class="wfocu_date_rage_container <?php esc_html_e( $show_range ); ?>">
                            <form action="<?php esc_html_e( admin_url( 'admin.php' ) ); ?>">
                                <input type="text" name="date_range_first" id="date_range_first" value="<?php esc_attr_e( $this->start_date ); ?>" class="wfocu_date_range" autocomplete="off" placeholder="Start Date" required/>
                                <input type="text" name="date_range_second" id="date_range_second" value="<?php esc_attr_e( $this->end_date ); ?>" class="wfocu_date_range" autocomplete="off" placeholder="End Date" required/>
                                <input type="hidden" name="wfocu_date_range_nonce" value="<?php esc_attr_e( wp_create_nonce( 'wfocu_date_range_nonce' ) ); ?>"/>
                                <input type="hidden" name="page" value="wc-reports"/>
                                <input type="hidden" name="tab" value="upsells"/>
                                <input type="hidden" name="report" value="upsells_by_funnel"/>
                                <input type="hidden" name="funnel_id" value="<?php echo $this->funnel_id; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"/>
                                <input type="submit" class="button button-secondary" value="Submit"/>
                            </form>
                        </div>
                    </div>

                    <style type="text/css">
                        .wfocu_date_rage_container, .wfocu_show_date_range_search_form {
                            display: inline-block
                        }

                        .wfocu_date_range {
                            max-width: 120px
                        }

                        .wfocu_hide_date_range_search_form {
                            display: none
                        }

                        .wfocur_design_btn {
                            display: inline-block;
                            text-decoration: none;
                            font-size: 13px;
                            margin: 0 8px 0 0;
                            cursor: pointer;
                            border-width: 1px;
                            border-style: solid;
                            -webkit-appearance: none;
                            border-radius: 50px;
                            white-space: nowrap;
                            box-sizing: border-box;
                            height: 30px;
                            min-width: 72px;
                            line-height: 28px;
                            padding: 0 12px 2px;
                            color: #555;
                            border-color: #ccc;
                            text-align: center;
                            background: #e5e5e5;
                            box-shadow: none;
                            vertical-align: top
                        }

                        .wfocur_design_btn.wfocur_btn_selected {
                            background: #f7f7f7
                        }
                    </style>
                    <script type="text/javascript">
                        jQuery(window).on('load', function () {
                            var date_range = jQuery(".wfocu_date_range");
                            if (date_range.length > 0) {
                                jQuery('#date_range_first').datepicker({'dateFormat': 'yy-mm-dd', 'maxDate': 0});
                                jQuery('#date_range_second').datepicker({'dateFormat': 'yy-mm-dd', 'maxDate': 0});
                            }
                        });
                        jQuery(document).on('click', '.wfocur_default_custom', function (e) {
                            e.preventDefault();
                            jQuery('.wfocur_design_btn').removeClass('wfocur_btn_selected');
                            jQuery(this).addClass('wfocur_btn_selected');
                            jQuery(".wfocu_date_rage_container").toggleClass('wfocu_hide_date_range_search_form');
                        });
                    </script>

					<?php
				}
				?>

				<?php if ( $this->has_items() ) { ?>
                    <div class="alignleft actions bulkactions">
						<?php $this->bulk_actions( $which ); ?>
                    </div>
					<?php
				}
				$this->extra_tablenav( $which );
				$this->pagination( $which );
				?>

                <br class="clear"/>
            </div>
			<?php
		}

		public function get_filter_menu() {
			$menu = [
				'all'    => [
					'name'  => 'All',
					'class' => 'wfocur_default',
					'link'  => add_query_arg( [
						'page'   => 'wc-reports',
						'tab'    => 'upsells',
						'report' => 'upsells_by_funnel',
					], admin_url( 'admin.php' ) ),
				],
				'7'      => [
					'name'  => '7 Days',
					'class' => 'wfocur_default_7',
					'link'  => add_query_arg( [
						'page'       => 'wc-reports',
						'tab'        => 'upsells',
						'report'     => 'upsells_by_funnel',
						'no_of_days' => 7,
					], admin_url( 'admin.php' ) ),
				],
				'15'     => [
					'name'  => '15 Days',
					'class' => 'wfocur_default_15',
					'link'  => add_query_arg( [
						'page'       => 'wc-reports',
						'tab'        => 'upsells',
						'report'     => 'upsells_by_funnel',
						'no_of_days' => 15,
					], admin_url( 'admin.php' ) ),
				],
				'30'     => [
					'name'  => '30 Days',
					'class' => 'wfocur_default_30',
					'link'  => add_query_arg( [
						'page'       => 'wc-reports',
						'tab'        => 'upsells',
						'report'     => 'upsells_by_funnel',
						'no_of_days' => 30,
					], admin_url( 'admin.php' ) ),
				],
				'custom' => [
					'name'  => 'Custom',
					'class' => 'wfocur_default_custom',
					'link'  => add_query_arg( [
						'page'   => 'wc-reports',
						'tab'    => 'upsells',
						'report' => 'upsells_by_funnel',
					], admin_url( 'admin.php' ) ),
				],
			];

			if ( isset( $menu[ $this->no_of_days ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$menu[ $this->no_of_days ]['current'] = true;
			} elseif ( isset( $_GET['date_range_first'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$menu['custom']['current'] = true;
			} else {
				$menu['all']['current'] = true;
			}

			return $menu;
		}
	}
}