<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Report_Upstroke' ) ) {
	/**
	 * This class will render upstroke tests reports
	 * Class BWFABT_Report_Upstroke
	 */
	#[AllowDynamicProperties]
	class BWFABT_Report_Upstroke extends BWFABT_Report {
		/**
		 * @var null $ins
		 */
		private static $ins = null;

		/**
		 * @var $table_data
		 */
		public $table_data;

		/**
		 * BWFABT_Report_Upstroke constructor.
		 */
		public function __construct() {
			parent::__construct();
			$this->table_data = array();

			add_filter( 'bwfabt_get_supported_reports', array( $this, 'bwfabt_add_upstroke_report' ) );
		}

		/**
		 * @return BWFABT_Report_Upstroke|null
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
		public function bwfabt_add_upstroke_report( $reports ) {
			$reports['upstroke'] = 'BWFABT_Report_Upstroke';

			return $reports;
		}

		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return array|void
		 */
		public function get_performance_data( $experiment, $type ) {
			global $wpdb;

			$variants = $experiment->get_variants();
			$variants = BWFABT_Core()->admin->move_controller_on_top( $variants );

			if ( count( $variants ) < 1 ) {
				esc_html_e( 'No active variants in this experiment.', 'woofunnels-ab-tests' );

				return;
			}

			$this->set_start_and_end_date( $experiment );
			$analytics       = $offer_analytics = $table_data = $revenue_daily = $views_daily = $accepted_daily = $conversion_daily = $revenue_weekly = $views_weekly = $accepted_weekly = $conversion_weekly = array();
			$revenue_monthly = $views_monthly = $accepted_monthly = $conversion_monthly = array();

			$dates_daily   = BWFABT_Core()->admin->generate_dates_interval( date( 'Y-m-d', $this->start_date ), date( 'Y-m-d', $this->end_date ), 'day' );
			$dates_weekly  = BWFABT_Core()->admin->generate_dates_interval( date( 'Y-m-d', $this->start_date ), date( 'Y-m-d', $this->end_date ), 'week' );
			$dates_monthly = BWFABT_Core()->admin->generate_dates_interval( date( 'Y-m-d', $this->start_date ), date( 'Y-m-d', $this->end_date ), 'month' );

			foreach ( array_keys( $variants ) as $funnel_id ) {

				$variant = new BWFABT_Variant( $funnel_id, $experiment );

				$steps         = get_post_meta( $funnel_id, '_funnel_steps', true );
				$steps         = ( ! empty( $steps ) && is_array( $steps ) ) ? $steps : array( array( 'id' => 0 ) );
				$funnel_offers = wp_list_pluck( $steps, 'id' );

				$bwf_ab_control  = get_post_meta( $funnel_id, '_bwf_ab_control', true );
				$query_funnel_id = $funnel_id;
				if ( ! empty( $bwf_ab_control ) && is_array( $bwf_ab_control ) && count( $bwf_ab_control ) > 0 ) {
					$query_funnel_id = ( isset( $bwf_ab_control['control_id'] ) && $bwf_ab_control['control_id'] > 0 ) ? $bwf_ab_control['control_id'] : $funnel_id;
					$funnel_offers   = ( isset( $bwf_ab_control['offers'] ) && is_array( $bwf_ab_control['offers'] ) ) ? $bwf_ab_control['offers'] : array();
				}

				$funnel_id = ( ! empty( $query_funnel_id ) && $query_funnel_id > 0 ) ? intval( $query_funnel_id ) : intval( $funnel_id );

				$get_all_dates = BWFABT_Core()->get_dataStore()->get_experiment_time_chunk( $experiment->get_id() );
				$date_query    = "";

				if ( is_array( $get_all_dates ) && count( $get_all_dates ) ) {
					foreach ( $get_all_dates as $date ) {
						$date_query .= " ( events.timestamp >= '" . esc_sql( $date['start_date'] ) . "' AND events.timestamp <= '" . esc_sql( $date['end_date'] ). "' ) OR ";
					}
					$date_query = ' AND ( ' . rtrim( $date_query, " OR " ) . ') ';
				}

				$sql   = "SELECT count( events.action_type_id) as trigger_count FROM " . $wpdb->prefix . "wfocu_event AS events WHERE 1=1 AND events.object_id IN (" . esc_sql( $query_funnel_id ) . ") AND events.action_type_id = '1' " . $date_query;
				$query = "SELECT events.action_type_id as action_id, events.object_id as objects, events.timestamp as time, events.value as value FROM  " . $wpdb->prefix . "wfocu_event AS events INNER JOIN  " . $wpdb->prefix . "wfocu_event_meta AS events_meta__funnel_id ON ( events.ID = events_meta__funnel_id.event_id ) AND ( ( events_meta__funnel_id.meta_key = '_funnel_id' AND events_meta__funnel_id.meta_value = '" . $query_funnel_id . "' )) " . $date_query;


				$sql_result       = $wpdb->get_row( $sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$funnel_triggered = ( is_array( $sql_result ) && isset( $sql_result['trigger_count'] ) ) ? $sql_result['trigger_count'] : 0;

				$funnel_events = $wpdb->get_results( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

				foreach ( is_array( $funnel_offers ) ? $funnel_offers : array() as $may_be_offer_title => $offer_id ) {
					$offer_title   = get_the_title( $offer_id );
					$offer_deleted = empty( $offer_title );

					if ( $offer_deleted && ! empty( $bwf_ab_control ) && is_array( $bwf_ab_control ) && count( $bwf_ab_control ) > 0 && isset( $bwf_ab_control['offers'] ) ) {
						$offer_title   = $may_be_offer_title;
						$offer_deleted = false;
					}

					$offer_analytics[ $funnel_id ][ $offer_id ]['offer_title'] = $offer_deleted ? sprintf( __( 'Offer ID: %s*', 'woofunnels-ab-tests' ), $offer_id ) : $offer_title;

					$viewed   = array( 'action_id' => '2', 'objects' => $offer_id );
					$accepted = array( 'action_id' => '4', 'objects' => $offer_id );
					$upsell   = array( 'action_id' => '4', 'objects' => $offer_id );

					$offer_viewd    = count( wp_list_filter( $funnel_events, $viewed ) );
					$offer_accepted = count( wp_list_filter( $funnel_events, $accepted ) );
					$offer_upsells  = array_sum( wp_list_pluck( wp_list_filter( $funnel_events, $upsell ), 'value' ) );

					$offer_analytics[ $funnel_id ][ $offer_id ]['offer_viewed']      = $offer_viewd;
					$offer_analytics[ $funnel_id ][ $offer_id ]['offer_accepted']    = $offer_accepted;
					$offer_analytics[ $funnel_id ][ $offer_id ]['revenue_per_visit'] = $offer_viewd > 0 ? ( $offer_upsells / $offer_viewd ) : 0;
					$offer_analytics[ $funnel_id ][ $offer_id ]['conversion_rate']   = $offer_viewd > 0 ? round( ( ( $offer_accepted / $offer_viewd ) * 100 ), 2 ) : 0;
					$offer_analytics[ $funnel_id ][ $offer_id ]['upsells']           = $offer_upsells;
				}

				$total_views    = array_sum( wp_list_pluck( $offer_analytics[ $funnel_id ], 'offer_viewed' ) );
				$total_accepted = array_sum( wp_list_pluck( $offer_analytics[ $funnel_id ], 'offer_accepted' ) );
				$total_upsells  = array_sum( wp_list_pluck( $offer_analytics[ $funnel_id ], 'upsells' ) );

				$table_data[ $funnel_id ] = array(
					'title'                 => get_the_title( $funnel_id ),
					'control'               => $variant->get_control(),
					'winner'                => $variant->get_winner(),
					'traffic'               => $variant->get_traffic(),
					'funnel_triggered'      => $funnel_triggered,
					'offers_count'          => count( $funnel_offers ),
					'total_views'           => $total_views,
					'total_accepted'        => $total_accepted,
					'avg_revenue_per_visit' => ( absint( $total_views ) !== 0 ) ? round( $total_upsells / $total_views, 2 ) : 0,
					'conversion_rate'       => ( $total_views > 0 ) ? round( ( ( $total_accepted / $total_views ) * 100 ), 2 ) : 0,
					'total_upsells'         => $total_upsells,
					'offer_data'            => $offer_analytics[ $funnel_id ],
				);

				$dates_daily_label   = wp_list_pluck( $dates_daily, 'label' );
				$dates_weekly_label  = wp_list_pluck( $dates_weekly, 'label' );
				$dates_monthly_label = wp_list_pluck( $dates_monthly, 'label' );
				foreach ( $dates_daily_label as $daily_date ) {
					$revenue_daily[ $funnel_id ][ $daily_date ]    = floatval( 0 );
					$views_daily[ $funnel_id ][ $daily_date ]      = 0;
					$accepted_daily[ $funnel_id ][ $daily_date ]   = 0;
					$conversion_daily[ $funnel_id ][ $daily_date ] = 0;
				}

				$revenue_weekly[ $funnel_id ] = array();

				foreach ( $dates_weekly as $weekly_date ) {
					$revenue_weekly[ $funnel_id ][ $weekly_date['label'] ]    = floatval( 0 );
					$views_weekly[ $funnel_id ][ $weekly_date['label'] ]      = 0;
					$accepted_weekly[ $funnel_id ][ $weekly_date['label'] ]   = 0;
					$conversion_weekly[ $funnel_id ][ $weekly_date['label'] ] = 0;
				}

				$revenue_monthly[ $funnel_id ] = array();
				foreach ( $dates_monthly_label as $monthly_date ) {
					$revenue_monthly[ $funnel_id ][ $monthly_date ]    = 0;
					$views_monthly[ $funnel_id ][ $monthly_date ]      = 0;
					$accepted_monthly[ $funnel_id ][ $monthly_date ]   = 0;
					$conversion_monthly[ $funnel_id ][ $monthly_date ] = 0;
				}

				foreach ( $funnel_events as $funnel_event ) {

					$event_date = date( 'Y-m-d', strtotime( $funnel_event->time ) );

					$revenue_daily[ $funnel_id ][ $event_date ] += ( '5' === $funnel_event->action_id ) ? floatval( $funnel_event->value ) : floatval( 0 );
					$revenue_daily[ $funnel_id ][ $event_date ] = round( $revenue_daily[ $funnel_id ][ $event_date ], 2 );

					$views_daily[ $funnel_id ][ $event_date ]    += ( '2' === $funnel_event->action_id ) ? 1 : 0;
					$accepted_daily[ $funnel_id ][ $event_date ] += ( '4' === $funnel_event->action_id ) ? 1 : 0;

					foreach ( $dates_weekly as $weekly_date ) {

						if ( ( $weekly_date['to'] > strtotime( $funnel_event->time ) ) && ( strtotime( $funnel_event->time ) > $weekly_date['from'] ) ) {
							$revenue_weekly[ $funnel_id ][ $weekly_date['label'] ]  += ( '5' === $funnel_event->action_id ) ? floatval( $funnel_event->value ) : floatval( 0 );
							$views_weekly[ $funnel_id ][ $weekly_date['label'] ]    += ( '2' === $funnel_event->action_id ) ? 1 : 0;
							$accepted_weekly[ $funnel_id ][ $weekly_date['label'] ] += ( '4' === $funnel_event->action_id ) ? 1 : 0;
						}
					}

					foreach ( $dates_monthly as $monthly_date ) {
						if ( ( $monthly_date['to'] > strtotime( $funnel_event->time ) ) && ( strtotime( $funnel_event->time ) > $monthly_date['from'] ) ) {
							$revenue_monthly[ $funnel_id ][ $monthly_date['label'] ]  += ( '5' === $funnel_event->action_id ) ? floatval( $funnel_event->value ) : floatval( 0 );
							$views_monthly[ $funnel_id ][ $monthly_date['label'] ]    += ( '2' === $funnel_event->action_id ) ? 1 : 0;
							$accepted_monthly[ $funnel_id ][ $monthly_date['label'] ] += ( '4' === $funnel_event->action_id ) ? 1 : 0;
						}
					}
				}

				foreach ( $dates_daily_label as $daily_date ) {
					$revenue_daily[ $funnel_id ][ $daily_date ]    = round( $revenue_daily[ $funnel_id ][ $daily_date ], 2 );
					$conversion_daily[ $funnel_id ][ $daily_date ] = ( $views_daily[ $funnel_id ][ $daily_date ] > 0 ) ? round( ( ( $accepted_daily[ $funnel_id ][ $daily_date ] / $views_daily[ $funnel_id ][ $daily_date ] ) * 100 ), 2 ) : 0;
				}

				foreach ( $dates_weekly_label as $weekly_date ) {
					$revenue_weekly[ $funnel_id ][ $weekly_date ]    = round( $revenue_weekly[ $funnel_id ][ $weekly_date ], 2 );
					$conversion_weekly[ $funnel_id ][ $weekly_date ] = ( $views_weekly[ $funnel_id ][ $weekly_date ] > 0 ) ? round( ( ( $accepted_weekly[ $funnel_id ][ $weekly_date ] / $views_weekly[ $funnel_id ][ $weekly_date ] ) * 100 ), 2 ) : 0;
				}

				foreach ( $dates_monthly_label as $monthly_date ) {
					$revenue_monthly[ $funnel_id ][ $monthly_date ]    = round( $revenue_monthly[ $funnel_id ][ $monthly_date ] );
					$conversion_monthly[ $funnel_id ][ $monthly_date ] = ( $views_monthly[ $funnel_id ][ $monthly_date ] > 0 ) ? round( ( ( $accepted_monthly[ $funnel_id ][ $monthly_date ] / $views_monthly[ $funnel_id ][ $monthly_date ] ) * 100 ), 2 ) : 0;
				}
			}
			/**foreach ( array_keys( $variants ) as $funnel_id ) ends here */

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

			$accepted        = array_sum( wp_list_pluck( $table_data, 'total_accepted' ) );
			$viewed          = array_sum( wp_list_pluck( $table_data, 'total_views' ) );
			$total_triggered = array_sum( wp_list_pluck( $table_data, 'funnel_triggered' ) );
			$upsell_value    = array_sum( wp_list_pluck( $table_data, 'total_upsells' ) );

			$analytics['total_views']           = $viewed;
			$analytics['total_conversion']      = $accepted;
			$analytics['avg_revenue_per_visit'] = ( $total_triggered > 0 ) ? wc_price( $upsell_value / $total_triggered ) : wc_price( 0 );
			$analytics['conversion_rate']       = ( $viewed > 0 ) ? round( ( ( $accepted / $viewed ) * 100 ), 2 ) . '%' : 0;
			$analytics['total_sales']           = wc_price( $upsell_value );

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
            <section class="accordionWrapper<?php echo esc_attr( true === $data['winner'] ) ? ' wfabt_accordion_acts' : ''; ?>">
                <div class="accordionItem close">
					<?php if ( 4 === $experiment->get_status() && true === $data['winner'] ) { ?>
                        <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/awar.png" class="wfabt_awar">
                        <div class="wfabt_product_skew"></div>
                        <div class="wfabt_product_skew_2"></div>
					<?php } ?>
                    <div class="accordionItemHeading cursor">
                        <div data-color-index="<?php echo esc_attr( $color_index ) ?>" class="wfabt_full_accord" style="border-left: 4px solid <?php echo esc_attr( $variant_colors[ $color_index ] ); ?>">
                            <div class="wfabt_left_accord">
                                <div class="detailed_heading"><?php echo ( true === $data['control'] ) ? '<div class="detailed_heading_label">' . esc_html__( 'Original', 'woofunnels-ab-tests' ) . '</div>' : ''; ?>
                                    <br> <?php echo esc_html( $data['title'] ); ?></div>
                                <ul>
                                    <li><a><?php echo sprintf( esc_html__( 'Offers: %s', 'woofunnels-ab-tests' ), esc_attr( $data['offers_count'] ) ) ?></a></li>
                                    <li class="wfabt_fr"><a><?php echo sprintf( esc_html__( 'Traffic Weight: %s', 'woofunnels-ab-tests' ), esc_attr( $data['traffic'] ) . '%' ) ?></a></li>
                                </ul>
                            </div>
                            <div class="wfabt_right_accord wfabt_right_accord_bnt">
                                <ul>
                                    <li>
                                        <span><?php echo sprintf( esc_html__( '%1$s %2$s ', 'woofunnels-ab-tests' ), esc_attr( $data['total_views'] ), '<span class="bwfabt-label">' . esc_html__( 'Views', 'woofunnels-ab-tests' ) . '</span>' ) ?></span>
                                    </li>
                                    <li>
                                        <span><?php echo sprintf( esc_html__( '%1$s %2$s ', 'woofunnels-ab-tests' ), esc_attr( $data['total_accepted'] ), '<span class="bwfabt-label">' . esc_html__( 'Conversion', 'woofunnels-ab-tests' ) . '</span>' ) ?></span>
                                    </li>
                                    <li>
                                        <span><?php echo wc_price( $data['avg_revenue_per_visit'] );  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <span class="bwfabt-label"><?php esc_html_e( 'Revenue Per Visit', 'woofunnels-ab-tests' ) ?></span></span>
                                    </li>
                                    <li>
                                        <span><?php echo sprintf( esc_html__( '%1$s %2$s ', 'woofunnels-ab-tests' ), esc_attr( $data['conversion_rate'] . '%' ), '<span class="bwfabt-label">' . esc_html__( 'Conversion Rate', 'woofunnels-ab-tests' ) . '</span>' ) ?></span>
                                    </li>
                                    <li>
                                        <span class="wfabt_blue"><?php echo wc_price( $data['total_upsells'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <span class="bwfabt-label"><?php esc_html_e( 'Total Revenue', 'woofunnels-ab-tests' ); ?></span></span>
                                    </li>
                                    <li class="wfabt_card_toggle"><span class="dashicons dashicons-arrow-down-alt2"></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordionItemContent close">
                        <div class="wfabt_details">
                            <table>
                                <thead>
                                <tr>
                                    <th class="fx"><?php esc_html_e( 'Funnel Offers', 'woofunnels-ab-tests' ) ?></th>
                                    <th><?php esc_html_e( 'Views', 'woofunnels-ab-tests' ) ?></th>
                                    <th><?php esc_html_e( 'Conversion', 'woofunnels-ab-tests' ) ?></th>
                                    <th><?php esc_html_e( 'Revenue Per Visit', 'woofunnels-ab-tests' ) ?></th>
                                    <th><?php esc_html_e( 'Conversion Rate', 'woofunnels-ab-tests' ) ?></th>
                                    <th><?php esc_html_e( 'Total Revenue', 'woofunnels-ab-tests' ) ?></th>
                                </tr>
                                </thead>
                                <tbody>

								<?php
								foreach ( $data['offer_data'] as $offer_id => $offer_data ) { ?>
                                    <tr>
                                        <td class="fx">
											<?php echo esc_html( $offer_data['offer_title'] ); ?>
                                        </td>
                                        <td><?php echo esc_html( $offer_data['offer_viewed'] ); ?></td>
                                        <td><?php echo esc_html( $offer_data['offer_accepted'] ); ?></td>
                                        <td><?php echo wc_price( $offer_data['revenue_per_visit'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                        <td><?php echo esc_html( $offer_data['conversion_rate'] ); ?>%</td>
                                        <td><?php echo wc_price( $offer_data['upsells'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    </tr>
								<?php } ?>
                                </tbody>
                            </table>
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
                    <th class="left"><?php esc_html_e( 'Funnels/Variants', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Views', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Conversion', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Conversion Rate', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Total Revenue', 'woofunnels-ab-tests' ); ?></th>
                    <th><?php esc_html_e( 'Choose Winner', 'woofunnels-ab-tests' ); ?></th>

                </tr>
				<?php foreach ( $table_data as $funnel_id => $funnel_data ) {
					if ( ! array_key_exists( $funnel_id, $active_variants ) ) {
						continue;
					} ?>
                    <tr>
                        <td class="left">
							<?php echo ( true === $funnel_data['control'] ) ? '<span class="bwfabt-control">' . esc_html__( 'Original', 'woofunnels-ab-tests' ) . '</span>' : ''; ?><br>
							<?php echo esc_html( $funnel_data['title'] ); ?>
                        </td>
                        <td><?php echo esc_html( $funnel_data['total_views'] ) ?></td>
                        <td><?php echo esc_html( $funnel_data['total_accepted'] ) ?></td>
                        <td><?php echo esc_html( $funnel_data['conversion_rate'] . '%' ) ?></td>
                        <td><?php echo wc_price( $funnel_data['total_upsells'] ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                        <td>
                            <a data-funnel_id="<?php echo esc_attr( $funnel_id ); ?>" class="choose_ab_winner wfabt_bg_no_act"><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/trophy.png">
                            </a>
                        </td>

                    </tr>
				<?php } ?>
            </table>
			<?php
		}
	}

	BWFABT_Report_Upstroke::get_instance();
}