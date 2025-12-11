<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Report_Order_Bump' ) ) {
	/**
	 * This class will render Order bump tests reports
	 * Class BWFABT_Report_Order_Bump
	 */
	#[AllowDynamicProperties]
	class BWFABT_Report_Order_Bump extends BWFABT_Report {

		/**
		 * @var null $ins
		 */
		private static $ins = null;

		/**
		 * @var $table_data
		 */
		public $table_data;

		/**
		 * BWFABT_Report_Order_Bump constructor.
		 */
		public function __construct() {
			parent::__construct();
			$this->table_data = array();

			add_filter( 'bwfabt_get_supported_reports', array( $this, 'bwfabt_add_order_bump_report' ) );
		}

		/**
		 * @return BWFABT_Report_Order_Bump|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @param $reports
		 *
		 * @return mixed
		 */
		public function bwfabt_add_order_bump_report( $reports ) {
			$reports['order_bump'] = 'BWFABT_Report_Order_Bump';

			return $reports;
		}

		public function get_performance_data( $experiment, $type ) {
			$variants = $experiment->get_variants();
			global $wpdb;

			if ( count( $variants ) < 1 ) {
				esc_html_e( 'No active variants in this experiment.', 'woofunnels-ab-tests' );

				return false;
			}

			$this->set_start_and_end_date( $experiment );

			$analytics       = $table_data = $revenue_daily = $views_daily = $accepted_daily = $conversion_daily = $revenue_weekly = $views_weekly = $accepted_weekly = $conversion_weekly = array();
			$revenue_monthly = $views_monthly = $accepted_monthly = $conversion_monthly = array();

			$dates_daily   = BWFABT_Core()->admin->generate_dates_interval( date( 'Y-m-d', $this->start_date ), date( 'Y-m-d', $this->end_date ), 'day' );
			$dates_weekly  = BWFABT_Core()->admin->generate_dates_interval( date( 'Y-m-d', $this->start_date ), date( 'Y-m-d', $this->end_date ), 'week' );
			$dates_monthly = BWFABT_Core()->admin->generate_dates_interval( date( 'Y-m-d', $this->start_date ), date( 'Y-m-d', $this->end_date ), 'month' );

			$variants          = BWFABT_Core()->admin->move_controller_on_top( $variants );
			$variant_ids       = array_keys( $variants );
			$query_variant_ids = array();
			foreach ( $variant_ids as $v_key => $variant_id ) {
				$query_variant_ids[ $v_key ] = $variant_id;
				$control_data                = get_post_meta( $variant_id, '_bwf_ab_control', true );
				$control_id                  = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? intval( $control_data['control_id'] ) : 0;
				if ( $control_id > 0 ) {
					$query_variant_ids[ $v_key ] = $control_id;
				}
			}

			$get_all_dates = BWFABT_Core()->get_dataStore()->get_experiment_time_chunk( $experiment->get_id() );
			$date_query    = "";

			if ( is_array( $get_all_dates ) && count( $get_all_dates ) ) {
				foreach ( $get_all_dates as $date ) {
					$date_query .= " ( `date` >= '" . esc_sql( $date['start_date'] ) . "' AND `date` <= '" . esc_sql( $date['end_date'] ) . "' ) OR ";
				}

				$date_query = ' AND ( ' . rtrim( $date_query, " OR " ) . ') ';
			}

			$sql = "SELECT * from $wpdb->wfob_stats WHERE `bid` IN( " . esc_sql( implode( ',', $query_variant_ids ) ) . " ) " . $date_query;

			$bumps_data = $wpdb->get_results( $sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			$bump_triggered = array();
			$bump_accepted  = array();
			$bump_revenue   = array();

			foreach ( $variant_ids as $variant_id ) {
				$control_data                  = get_post_meta( $variant_id, '_bwf_ab_control', true );
				$control_id                    = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? $control_data['control_id'] : 0;
				$variant_id                    = ( ! empty( $control_id ) && $control_id > 0 ) ? intval( $control_id ) : intval( $variant_id );
				$bump_triggered[ $variant_id ] = isset( $bump_triggered[ $variant_id ] ) ? $bump_triggered[ $variant_id ] : 0;
				$bump_accepted[ $variant_id ]  = isset( $bump_accepted[ $variant_id ] ) ? $bump_accepted[ $variant_id ] : 0;
				$bump_revenue[ $variant_id ]   = isset( $bump_revenue[ $variant_id ] ) ? $bump_revenue[ $variant_id ] : floatval( 0 );

				$dates_daily_label   = wp_list_pluck( $dates_daily, 'label' );
				$dates_weekly_label  = wp_list_pluck( $dates_weekly, 'label' );
				$dates_monthly_label = wp_list_pluck( $dates_monthly, 'label' );

				$revenue_daily[ $variant_id ] = array();
				foreach ( $dates_daily_label as $daily_date ) {
					$revenue_daily[ $variant_id ][ $daily_date ]    = floatval( 0 );
					$views_daily[ $variant_id ][ $daily_date ]      = 0;
					$accepted_daily[ $variant_id ][ $daily_date ]   = 0;
					$conversion_daily[ $variant_id ][ $daily_date ] = 0;
				}

				$revenue_weekly[ $variant_id ] = array();

				foreach ( $dates_weekly as $weekly_date ) {
					$revenue_weekly[ $variant_id ][ $weekly_date['label'] ]    = floatval( 0 );
					$views_weekly[ $variant_id ][ $weekly_date['label'] ]      = 0;
					$accepted_weekly[ $variant_id ][ $weekly_date['label'] ]   = 0;
					$conversion_weekly[ $variant_id ][ $weekly_date['label'] ] = 0;
				}

				$revenue_monthly[ $variant_id ] = array();
				foreach ( $dates_monthly_label as $monthly_date ) {
					$revenue_monthly[ $variant_id ][ $monthly_date ]    = 0;
					$views_monthly[ $variant_id ][ $monthly_date ]      = 0;
					$accepted_monthly[ $variant_id ][ $monthly_date ]   = 0;
					$conversion_monthly[ $variant_id ][ $monthly_date ] = 0;
				}
			}

			foreach ( $variant_ids as $variant_id ) {
				$control_data = get_post_meta( $variant_id, '_bwf_ab_control', true );
				$control_id   = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? $control_data['control_id'] : 0;
				$variant_id   = ( ! empty( $control_id ) && $control_id > 0 ) ? intval( $control_id ) : intval( $variant_id );

				foreach ( $bumps_data as $bump_data ) {
					$bump_id                       = intval( $bump_data['bid'] );
					$bump_triggered[ $variant_id ] += ( $bump_id === $variant_id ) ? 1 : 0;
					$bump_accepted[ $variant_id ]  += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? 1 : 0;
					$bump_revenue[ $variant_id ]   += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? floatval( $bump_data['total'] ) : floatval( 0 );

					$bump_date = date( 'Y-m-d', strtotime( $bump_data['date'] ) );

					$revenue_daily[ $variant_id ][ $bump_date ] += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? floatval( $bump_data['total'] ) : floatval( 0 );
					$revenue_daily[ $variant_id ][ $bump_date ] = round( $revenue_daily[ $variant_id ][ $bump_date ], 2 );

					$views_daily[ $variant_id ][ $bump_date ]    += ( $bump_id === $variant_id ) ? 1 : 0;
					$accepted_daily[ $variant_id ][ $bump_date ] += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? 1 : 0;


					foreach ( $dates_weekly as $weekly_date ) {

						if ( ( $weekly_date['to'] > strtotime( $bump_data['date'] ) ) && ( strtotime( $bump_data['date'] ) > $weekly_date['from'] ) ) {
							$revenue_weekly[ $variant_id ][ $weekly_date['label'] ]  += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? floatval( $bump_data['total'] ) : floatval( 0 );
							$views_weekly[ $variant_id ][ $weekly_date['label'] ]    += ( $bump_id === $variant_id ) ? 1 : 0;
							$accepted_weekly[ $variant_id ][ $weekly_date['label'] ] += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? 1 : 0;
						}
					}

					foreach ( $dates_monthly as $monthly_date ) {
						if ( ( $monthly_date['to'] > strtotime( $bump_data['date'] ) ) && ( strtotime( $bump_data['date'] ) > $monthly_date['from'] ) ) {
							$revenue_monthly[ $variant_id ][ $monthly_date['label'] ]  += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? floatval( $bump_data['total'] ) : floatval( 0 );
							$views_monthly[ $variant_id ][ $monthly_date['label'] ]    += ( $bump_id === $variant_id ) ? 1 : 0;
							$accepted_monthly[ $variant_id ][ $monthly_date['label'] ] += ( $bump_id === $variant_id && '1' === $bump_data['converted'] ) ? 1 : 0;
						}
					}

				}
			}

			foreach ( $variant_ids as $variant_id ) {
				$control_data                  = get_post_meta( $variant_id, '_bwf_ab_control', true );
				$control_id                    = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? $control_data['control_id'] : 0;
				$variant                       = new BWFABT_Variant( $variant_id, $experiment );
				$new_variant_id                = intval( $variant_id );
				$variant_id                    = ( ! empty( $control_id ) && $control_id > 0 ) ? intval( $control_id ) : intval( $variant_id );
				$table_data[ $new_variant_id ] = array(
					'title'                 => get_the_title( $new_variant_id ),
					'control'               => $variant->get_control(),
					'winner'                => $variant->get_winner(),
					'traffic'               => $variant->get_traffic(),
					'bump_triggered'        => $bump_triggered[ $variant_id ],
					'bump_accepted'         => $bump_accepted[ $variant_id ],
					'avg_revenue_per_visit' => ( $bump_triggered[ $variant_id ] > 0 ) ? ( $bump_revenue[ $variant_id ] / $bump_triggered[ $variant_id ] ) : 0,
					'conversion_rate'       => ( $bump_triggered[ $variant_id ] > 0 ) ? round( ( ( $bump_accepted[ $variant_id ] / $bump_triggered[ $variant_id ] ) * 100 ), 2 ) : 0,
					'total_revenue'         => $bump_revenue[ $variant_id ],
				);

				foreach ( $dates_daily_label as $daily_date ) {
					$revenue_daily[ $variant_id ][ $daily_date ]    = round( $revenue_daily[ $variant_id ][ $daily_date ], 2 );
					$conversion_daily[ $variant_id ][ $daily_date ] = ( $views_daily[ $variant_id ][ $daily_date ] > 0 ) ? round( ( ( $accepted_daily[ $variant_id ][ $daily_date ] / $views_daily[ $variant_id ][ $daily_date ] ) * 100 ), 2 ) : 0;
				}

				foreach ( $dates_weekly_label as $weekly_date ) {
					$revenue_weekly[ $variant_id ][ $weekly_date ]    = round( $revenue_weekly[ $variant_id ][ $weekly_date ], 2 );
					$conversion_weekly[ $variant_id ][ $weekly_date ] = ( $views_weekly[ $variant_id ][ $weekly_date ] > 0 ) ? round( ( ( $accepted_weekly[ $variant_id ][ $weekly_date ] / $views_weekly[ $variant_id ][ $weekly_date ] ) * 100 ), 2 ) : 0;
				}

				foreach ( $dates_monthly_label as $monthly_date ) {
					$revenue_monthly[ $variant_id ][ $monthly_date ]    = round( $revenue_monthly[ $variant_id ][ $monthly_date ] );
					$conversion_monthly[ $variant_id ][ $monthly_date ] = ( $views_monthly[ $variant_id ][ $monthly_date ] > 0 ) ? round( ( ( $accepted_monthly[ $variant_id ][ $monthly_date ] / $views_monthly[ $variant_id ][ $monthly_date ] ) * 100 ), 2 ) : 0;
				}
			}

			$this->chart_data['dates']['daily']   = $dates_daily_label;
			$this->chart_data['dates']['weekly']  = $dates_weekly_label;
			$this->chart_data['dates']['monthly'] = $dates_monthly_label;

			$this->chart_data['revenue']['daily']    = $revenue_daily;
			$this->chart_data['views']['daily']      = $views_daily;
			$this->chart_data['accepted']['daily']   = $accepted_daily;
			$this->chart_data['conversion']['daily'] = $conversion_daily;

			$this->chart_data['revenue']['weekly']    = $revenue_weekly;
			$this->chart_data['views']['weekly']      = $views_weekly;
			$this->chart_data['accepted']['weekly']   = $accepted_weekly;
			$this->chart_data['conversion']['weekly'] = $conversion_weekly;

			$this->chart_data['revenue']['monthly']    = $revenue_monthly;
			$this->chart_data['views']['monthly']      = $views_monthly;
			$this->chart_data['accepted']['monthly']   = $accepted_monthly;
			$this->chart_data['conversion']['monthly'] = $conversion_monthly;

			$this->table_data = $table_data;

			$accepted        = array_sum( wp_list_pluck( $table_data, 'bump_accepted' ) );
			$total_triggered = array_sum( wp_list_pluck( $table_data, 'bump_triggered' ) );
			$total_revenue   = array_sum( wp_list_pluck( $table_data, 'total_revenue' ) );

			$analytics['total_views']           = $total_triggered;
			$analytics['total_conversion']      = $accepted;
			$analytics['avg_revenue_per_visit'] = ( $total_triggered > 0 ) ? wc_price( $total_revenue / $total_triggered ) : wc_price( 0 );
			$analytics['conversion_rate']       = ( $total_triggered > 0 ) ? round( ( ( $accepted / $total_triggered ) * 100 ), 2 ) . '%' : 0;
			$analytics['total_sales']           = wc_price( $total_revenue );

			return $analytics;
		}


		/**
		 * Single analtics row to represent the analytical data
		 *
		 * @param $experiment
		 * @param $data
		 * @param $id
		 * @param $color_index
		 */
		public function single_row( $experiment, $data, $id, $color_index ) {
			$variant_colors = BWFABT_Core()->admin->get_variant_colors();
			?>
            <section class="accordionWrapper <?php echo esc_attr( true === $data['winner'] ) ? ' wfabt_accordion_acts' : ''; ?>">
                <div class="accordionItem close">
					<?php if ( 2 === $experiment->get_status() && true === $data['winner'] ) { ?>
                        <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/awar.png" class="wfabt_awar">
                        <div class="wfabt_product_skew"></div>
                        <div class="wfabt_product_skew_2"></div>
					<?php } ?>
                    <div class="accordionItemHeading">
                        <div class="wfabt_full_accord" style="border-left: 4px solid <?php echo esc_attr( $variant_colors[ $color_index ] ); ?>">
                            <div class="wfabt_left_accord">
                                <div class="detailed_heading"><?php echo ( true === $data['control'] ) ? '<div class="detailed_heading_label">' . esc_html__( 'Original', 'woofunnels-ab-tests' ) . '</div>' : ''; ?>
                                    <br/>
									<?php echo esc_html( $data['title'] ); ?></div>
                                <ul>
                                    <li class="wfabt_fr"><a><?php echo sprintf( esc_html__( 'Traffic Weight: %s', 'woofunnels-ab-tests' ), esc_attr( $data['traffic'] ) . '%' ) ?></a></li>
                                </ul>
                            </div>
                            <div class="wfabt_right_accord wfabt_right_accord_bnt">
                                <ul>
                                    <li>
                                        <span><?php echo sprintf( esc_html__( '%1$s %2$s ', 'woofunnels-ab-tests' ), esc_attr( $data['bump_triggered'] ), '<span class="bwfabt-label">' . esc_html__( 'Views', 'woofunnels-ab-tests' ) . '</span>' ) ?></span>
                                    </li>
                                    <li>
                                        <span><?php echo sprintf( esc_html__( '%1$s %2$s ', 'woofunnels-ab-tests' ), esc_attr( $data['bump_accepted'] ), '<span class="bwfabt-label">' . esc_html__( 'Conversion', 'woofunnels-ab-tests' ) . '</span>' ) ?></span>
                                    </li>
                                    <li>
                                        <span><?php echo wc_price( $data['avg_revenue_per_visit'] );  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <span class="bwfabt-label"><?php esc_html_e( 'Revenue Per Visit', 'woofunnels-ab-tests' ) ?></span></span>
                                    </li>
                                    <li>
                                        <span><?php echo sprintf( esc_html__( '%1$s %2$s ', 'woofunnels-ab-tests' ), esc_attr( $data['conversion_rate'] . '%' ), '<span class="bwfabt-label">' . esc_html__( 'Conversion Rate', 'woofunnels-ab-tests' ) . '</span>' ) ?></span>
                                    </li>
                                    <li>
                                        <span class="wfabt_blue"><?php echo wc_price( $data['total_revenue'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <span class="bwfabt-label"><?php esc_html_e( 'Revenue', 'woofunnels-ab-tests' ); ?></span></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </section>

			<?php
		}

		/**
		 * @param $experiment
		 */
		public function get_choose_winner_table( $experiment ) {
			$table_data      = is_array( $this->table_data ) ? $this->table_data : array();
			$active_variants = $experiment->get_active_variants(); ?>
            <table>
                <tr>
                    <th class="left"><?php esc_html_e( 'Bumps/Variants', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Views', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Conversion', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Conversion Rate', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Total Revenue', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Choose Winner', 'woofunnels-ab-tests' ); ?></th>

                </tr>
				<?php foreach ( $table_data as $bump_id => $bump_data ) {
					if ( ! array_key_exists( $bump_id, $active_variants ) ) {
						continue;
					} ?>
                    <tr>
                        <td class="left">
							<?php echo ( true === $bump_data['control'] ) ? '<span class="bwfabt-control">' . esc_html__( 'Original', 'woofunnels-ab-tests' ) . '</span>' : ''; ?><br>
							<?php echo esc_html( $bump_data['title'] ); ?>
                        </td>
                        <td><?php echo esc_html( $bump_data['bump_triggered'] ) ?></td>
                        <td><?php echo esc_html( $bump_data['bump_accepted'] ) ?></td>
                        <td><?php echo esc_html( $bump_data['conversion_rate'] . '%' ) ?></td>
                        <td><?php echo wc_price( $bump_data['total_revenue'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td>
                            <a data-funnel_id="<?php echo esc_attr( $bump_id ); ?>" class="choose_ab_winner wfabt_bg_no_act"><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/trophy.png">
                            </a>
                        </td>

                    </tr>
				<?php } ?>
            </table>
			<?php
		}
	}

	BWFABT_Report_Order_Bump::get_instance();
}