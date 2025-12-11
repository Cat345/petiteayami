<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WC_Report_Upsells_By_Offer' ) ) {
	/**
	 * Upstroke Admin Report - upstroke by offer
	 *
	 * Find the offers accepted from funnels
	 *
	 */
	class WC_Report_Upsells_By_Offer extends WP_List_Table {

		private $totals;
		private $funnel_id;
		private $offers_deleted = false;
		private $filter_date = '';
		private $no_of_days = '';
		private $start_date = '';
		private $end_date = '';

		/**
		 * WC_Report_Upsells_By_Offer constructor.
		 */
		public function __construct( $funnel_id ) {
			$this->funnel_id = $funnel_id;
			$this->detect_no_days();

			parent::__construct( array(
				'singular' => __( 'Offer', 'woofunnels-upstroke-power-pack' ),
				'plural'   => __( 'Offers', 'woofunnels-upstroke-power-pack' ),
			) );
		}

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
		 * No offer found or empty funnel_id text.
		 */
		public function no_items() {
			esc_html_e( 'No offer found.', 'woofunnels-upstroke-power-pack' );
		}

		/**
		 * Output the report.
		 */
		public function output_report() {
			$this->prepare_items();
			echo '<div id="poststuff" class="woocommerce-reports-wide">';
			$this->display();
			if ( $this->offers_deleted ) {
				$this->offers_deleted();
			}
			echo '</div>';
		}

		/**
		 * Prepare funnels list items.
		 */
		public function prepare_items() {
			$funnel_id             = $this->funnel_id;
			$this->items           = array();
			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

			if ( empty( $funnel_id ) || $funnel_id < 1 ) {
				return;
			}

			$steps      = get_post_meta( $funnel_id, '_funnel_steps', true );
			$steps      = ( ! empty( $steps ) && is_array( $steps ) ) ? $steps : array();
			$offers     = wp_list_pluck( $steps, 'id' );
			$query_args = array(
				'data'       => array(
					'action_type_id' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'action_id',
					),
					'object_id'      => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'objects',
					),
					'value'          => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'value',
					),
				),
				'where'      => array(),
				'where_meta' => array(
					array(
						'type'       => 'meta',
						'meta_key'   => '_funnel_id',
						'meta_value' => $funnel_id,
						'operator'   => '=',
					),
				),
				'query_type' => 'get_results',
			);

			if ( $this->start_date !== '' && $this->end_date !== '' ) {
				$query_args['event_range'] = true;
				$query_args['start_date']  = strtotime( $this->start_date );
				$query_args['end_date']    = strtotime( $this->end_date . ' +1 day' );
			}

			$funnel_events = WFOCU_Core()->track->query_results( $query_args );

			$criteria      = array( 'action_id' => '2' );
			$funnel_offers = array_unique( array_merge( wp_list_pluck( wp_list_filter( $funnel_events, $criteria ), 'objects' ), $offers ) );
			sort( $funnel_offers );

			foreach ( is_array( $funnel_offers ) ? $funnel_offers : array() as $key => $offer_id ) {
				$this->items[ $key ]['offer_id'] = $offer_id;

				$title         = get_the_title( $offer_id );
				$offer_deleted = empty( $title );
				if ( $offer_deleted ) {
					$this->offers_deleted = true;
				}
				$this->items[ $key ]['offer_title'] = $offer_deleted ? /* translators: %s: average gross accepted */
					sprintf( __( 'Offer ID: %s*', 'woofunnels-upstroke-power-pack' ), $offer_id ) : get_the_title( $offer_id );

				$viewed   = array(
					'action_id' => '2',
					'objects'   => $offer_id,
				);
				$accepted = array(
					'action_id' => '4',
					'objects'   => $offer_id,
				);
				$rejected = array(
					'action_id' => '6',
					'objects'   => $offer_id,
				);
				$failed   = array(
					'action_id' => '9',
					'objects'   => $offer_id,
				);
				$expired  = array(
					'action_id' => '7',
					'objects'   => $offer_id,
				);
				$upsell   = array(
					'action_id' => '4',
					'objects'   => $offer_id,
				);

				$this->items[ $key ]['offer_viewed']   = count( wp_list_filter( $funnel_events, $viewed ) );
				$this->items[ $key ]['offer_accepted'] = count( wp_list_filter( $funnel_events, $accepted ) );
				$this->items[ $key ]['offer_rejected'] = count( wp_list_filter( $funnel_events, $rejected ) );
				$this->items[ $key ]['offer_failed']   = count( wp_list_filter( $funnel_events, $failed ) );
				$this->items[ $key ]['offer_expired']  = count( wp_list_filter( $funnel_events, $expired ) );
				$this->items[ $key ]['upsells']        = array_sum( wp_list_pluck( wp_list_filter( $funnel_events, $upsell ), 'value' ) );

				$this->items[ $key ]['offer_pending']   = $this->items[ $key ]['offer_expired'] + $this->items[ $key ]['offer_failed'];
				$divisor                                = $this->items[ $key ]['offer_accepted'] + $this->items[ $key ]['offer_pending'] + $this->items[ $key ]['offer_rejected'];
				$this->items[ $key ]['conversion_rate'] = ( $divisor > 0 ) ? wc_format_decimal( ( $this->items[ $key ]['offer_accepted'] / ( $divisor ) ) * 100, 2 ) . '%' : wc_format_decimal( '0.00' ) . '%';
			}
		}

		/**
		 * Get columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			$columns = array(
				'offer_name'      => __( 'Offers', 'woofunnels-upstroke-power-pack' ),
				/* translators: %s: average gross accepted */
				'offer_viewed'    => sprintf( __( 'Offer Viewed %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers viewed from this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'offer_accepted'  => sprintf( __( 'Offer Accepted %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers accepted from this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'offer_rejected'  => sprintf( __( 'Offer Rejected %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers rejected from this funnel', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'offer_pending'   => sprintf( __( 'Offer Pending  %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total offers pending (Failed + Expired) from this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'conversion_rate' => sprintf( __( 'Conversion Rate %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Conversion Rate.', 'woofunnels-upstroke-power-pack' ) ) ),
				/* translators: %s: average gross accepted */
				'upsells'         => sprintf( __( 'Total Upsells  %s', 'woofunnels-upstroke-power-pack' ), WFOCU_Admin_Reports::wfocu_help_tip( __( 'Total upsells through this funnel.', 'woofunnels-upstroke-power-pack' ) ) ),
			);

			return $columns;
		}

		/**
		 * No offer found or empty funnel_id text.
		 */
		public function offers_deleted() {
			esc_html_e( '*These offer(s) is/are deleted.', 'woofunnels-upstroke-power-pack' );
		}

		/**
		 * Get all column default value
		 *
		 * @param object $funnel_data
		 * @param string $column_name
		 *
		 * @return string|void
		 */
		public function column_default( $funnel_data, $column_name ) {

			switch ( $column_name ) {

				case 'offer_name':
					return $funnel_data['offer_title'];

				case 'offer_viewed':
					return $funnel_data['offer_viewed'];

				case 'offer_accepted':
					return $funnel_data['offer_accepted'];

				case 'offer_rejected':
					return $funnel_data['offer_rejected'];

				case 'offer_pending':
					return $funnel_data['offer_pending'];

				case 'conversion_rate':
					return $funnel_data['conversion_rate'];

				case 'upsells':
					return wc_price( $funnel_data['upsells'] );
			}

			return '';
		}

		/**
		 * Generate the table navigation above or below the table
		 *
		 * @param string $which
		 *
		 * @since 3.1.0
		 */
		public function display_tablenav( $which ) {
			if ( 'top' === $which ) {
				wp_nonce_field( 'bulk-' . $this->_args['plural'] );
			}
			?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				$funnel_name = get_the_title( $this->funnel_id );
				$funnel_name = empty( $funnel_name ) ? $this->funnel_id : get_the_title( $this->funnel_id );

				if ( 'top' === $which ) {
					?>
                    <span class="wfocu-funnel_name">
					<?php
					/* translators: %s: Funnels name */
					printf( '<strong>' . esc_html__( 'Funnel Name: %s', 'woofunnels-upstroke-power-pack' ) . '</strong>', esc_html( $funnel_name ) );
					?>
				</span>

                    <div class="wfocu_abandoned_filter" style="margin: 15px 0px">
						<?php
						$menus = $this->get_filter_menu();
						foreach ( $menus as $menu ) {
							$default_menu_class = ( isset( $menu['current'] ) ) ? 'wfocur_btn_selected' : '';
							$class              = $menu['class'] . ' ' . $default_menu_class;
							echo '<a class="wfocur_design_btn ' . esc_attr__( $class ) . '" href="' . $menu['link'] . '">' . esc_html__( $menu['name'] ) . '</a>'; //phpcs:ignore WordPress.Security.EscapeOutput
						}
						$show_range = isset( $_GET['wfocu_date_range_nonce'] ) ? 'wfocu_show_date_range_search_form' : 'wfocu_hide_date_range_search_form';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
						?>

                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=upsells&report=upsells_by_funnel' ) ); ?>" class="button button-right right"><span class="dashicons dashicons-arrow-left-alt"></span><?php esc_html_e( 'Back to funnel reports', 'woofunnels-upstroke-power-pack' ); ?>
                        </a>

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

                        .wfocu_abandoned_filter .button {
                            display: flex;
                            align-items: center
                        }

                        .wfocu_abandoned_filter .button:hover {
                            background: #ffffff;
                            color: #000;
                            border-color: #000
                        }

                        .wfocu_abandoned_filter .button .dashicons {
                            margin: 6px
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
						'page'      => 'wc-reports',
						'tab'       => 'upsells',
						'report'    => 'upsells_by_funnel',
						'funnel_id' => $this->funnel_id,
					], admin_url( 'admin.php' ) ),
				],
				'7'      => [
					'name'  => '7 Days',
					'class' => 'wfocur_default_7',
					'link'  => add_query_arg( [
						'page'       => 'wc-reports',
						'tab'        => 'upsells',
						'report'     => 'upsells_by_funnel',
						'funnel_id'  => $this->funnel_id,
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
						'funnel_id'  => $this->funnel_id,
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
						'funnel_id'  => $this->funnel_id,
						'no_of_days' => 30,
					], admin_url( 'admin.php' ) ),
				],
				'custom' => [
					'name'  => 'Custom',
					'class' => 'wfocur_default_custom',
					'link'  => add_query_arg( [
						'page'      => 'wc-reports',
						'tab'       => 'upsells',
						'report'    => 'upsells_by_funnel',
						'funnel_id' => $this->funnel_id,
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
