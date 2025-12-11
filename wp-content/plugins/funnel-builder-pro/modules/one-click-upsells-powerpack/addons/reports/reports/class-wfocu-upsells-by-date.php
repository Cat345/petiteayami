<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WC_Report_Upsells_By_Date' ) ) {
	/**
	 * Upstroke Admin Report - Upsells by date
	 *
	 * Find the upsells accepted between given dates
	 *
	 */
	class WC_Report_Upsells_By_Date extends WC_Admin_Report {

		/**
		 * Chart colors.
		 *
		 * @var array
		 */
		public $chart_colours = array();

		/**
		 * The report data.
		 *
		 * @var stdClass
		 */
		private $report_data;

		/**
		 * Get the legend for the main chart sidebar.
		 *
		 * @return array
		 */
		public function get_chart_legend() {
			$legend = array();
			$data   = $this->get_report_data();

			switch ( $this->chart_groupby ) {
				case 'day':
					/* translators: %s: average gross daily upsells */ $average_upsell_title = sprintf( __( '%s average gross daily upsells', 'woofunnels-upstroke-power-pack' ), '<strong>' . wc_price( $data->average_gross_upsells ) . '</strong>' );
					break;
				case 'month':
				default:
					/* translators: %s: average gross monthly upsells */ $average_upsell_title = sprintf( __( '%s average gross monthly upsells', 'woofunnels-upstroke-power-pack' ), '<strong>' . wc_price( $data->average_gross_upsells ) . '</strong>' );
					break;
			}

			$legend[] = array(
				/* translators: %s: average gross monthly upsells */
				'title'            => sprintf( __( '%s Gross Upsells in this period', 'woofunnels-upstroke-power-pack' ), '<strong>' . wc_price( $data->gross_upsells ) . '</strong>' ),
				'placeholder'      => __( 'The sum of revenue genereted through upsells product.', 'woofunnels-upstroke-power-pack' ),
				'color'            => $this->chart_colours['gross_upsells'],
				'highlight_series' => 3,
			);

			$legend[] = array(
				'title'            => $average_upsell_title,
				'placeholder'      => __( 'Average revenue genereted though upsells product.', 'woofunnels-upstroke-power-pack' ),
				'color'            => $this->chart_colours['average_gross_upsells'],
				'highlight_series' => 4,
			);

			$legend[] = array(
				/* translators: %s: average gross triggered */
				'title'            => sprintf( __( '%s Total number of times funnels are triggered in this period', 'woofunnels-upstroke-power-pack' ), '<strong>' . $data->total_funnels_triggered . '</strong>' ),
				'placeholder'      => __( 'The number of funnels initiated.', 'woofunnels-upstroke-power-pack' ),
				'color'            => $this->chart_colours['funnels_triggered'],
				'highlight_series' => 0,
			);

			$legend[] = array(
				/* translators: %s: average gross accepted */
				'title'            => sprintf( __( '%s Total Offers Accepted in this period', 'woofunnels-upstroke-power-pack' ), '<strong>' . $data->total_offers_accepted . '</strong>' ),
				'color'            => $this->chart_colours['offers_accepted'],
				'placeholder'      => __( 'Total offers accepted.', 'woofunnels-upstroke-power-pack' ),
				'highlight_series' => 2,
			);

			$legend[] = array(
				/* translators: %s: average gross accepted */
				'title'            => sprintf( __( '%s Total Offers Rejected in this period', 'woofunnels-upstroke-power-pack' ), '<strong>' . $data->total_offers_declined . '</strong>' ),
				'color'            => $this->chart_colours['offers_declined'],
				'placeholder'      => __( 'Total offers desclined.', 'woofunnels-upstroke-power-pack' ),
				'highlight_series' => 1,
			);

			$legend[] = array(
				/* translators: %s: Overall conversion Rate */
				'title'            => sprintf( __( '%s Overall conversion rate', 'woofunnels-upstroke-power-pack' ), '<strong>' . $data->overall_conversion_rate . '%</strong>' ),
				'color'            => $this->chart_colours['conversion_rate'],
				'placeholder'      => __( 'Overall conversion rate.', 'woofunnels-upstroke-power-pack' ),
				'highlight_series' => '5',
			);

			return $legend;
		}

		/**
		 * Get report data.
		 *
		 * @return stdClass
		 */
		public function get_report_data() {
			if ( empty( $this->report_data ) ) {
				$this->query_report_data();
			}

			return $this->report_data;
		}

		/**
		 * Get all data needed for this report and store in the class.
		 */
		private function query_report_data() {
			$this->report_data = new stdClass();

			$this->end_date = strtotime( '+1 Day', $this->end_date );

			$this->report_data->upsells = WFOCU_Core()->track->query_results( array(
				'data'        => array(
					'value'     => array(
						'type'     => 'col',
						'function' => 'SUM',
						'name'     => 'total_upsells',
					),
					'timestamp' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'upsells_date',
					),
				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 4,
						'operator' => '=',
					),
				),
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order_by'    => 'events.id DESC',
				'query_type'  => 'get_results',
				'event_range' => true,
				'start_date'  => $this->start_date,
				'end_date'    => $this->end_date,
			) );

			$this->report_data->funnels_triggered = WFOCU_Core()->track->query_results( array(
				'data'        => array(
					'action_type_id' => array(
						'type'     => 'col',
						'function' => 'COUNT',
						'name'     => 'total_triggers',
					),
					'timestamp'      => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'trigger_date',
					),
				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 1,
						'operator' => '=',
					),
				),
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order_by'    => 'events.id DESC',
				'query_type'  => 'get_results',
				'event_range' => true,
				'start_date'  => $this->start_date,
				'end_date'    => $this->end_date,
			) );

			$this->report_data->offered_viewed = WFOCU_Core()->track->query_results( array(
				'data'        => array(
					'action_type_id' => array(
						'type'     => 'col',
						'function' => 'COUNT',
						'name'     => 'offered_viewed',
					),
					'timestamp'      => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'trigger_date',
					),
				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 2,
						'operator' => '=',
					),
				),
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order_by'    => 'events.id DESC',
				'query_type'  => 'get_results',
				'event_range' => true,
				'start_date'  => $this->start_date,
				'end_date'    => $this->end_date,
			) );

			$this->report_data->offers_accepted = WFOCU_Core()->track->query_results( array(
				'data'        => array(
					'value'     => array(
						'type'     => 'col',
						'function' => 'COUNT',
						'name'     => 'offers_count',
					),
					'timestamp' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'offer_date',
					),

				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 4,
						'operator' => '=',
					),
				),
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order_by'    => 'events.id DESC',
				'query_type'  => 'get_results',
				'event_range' => true,
				'start_date'  => $this->start_date,
				'end_date'    => $this->end_date,
			) );

			$this->report_data->offers_declined = WFOCU_Core()->track->query_results( array(
				'data'        => array(
					'value'     => array(
						'type'     => 'col',
						'function' => 'COUNT',
						'name'     => 'offers_declined',
					),
					'timestamp' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'declined_date',
					),
				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 6,
						'operator' => '=',
					),
				),
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order_by'    => 'events.id DESC',
				'query_type'  => 'get_results',
				'event_range' => true,
				'start_date'  => $this->start_date,
				'end_date'    => $this->end_date,
			) );

			$this->report_data->offers_expired = WFOCU_Core()->track->query_results( array(
				'data'        => array(
					'value'     => array(
						'type'     => 'col',
						'function' => 'COUNT',
						'name'     => 'offers_expired',
					),
					'timestamp' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'expired_date',
					),
				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 7,
						'operator' => '=',
					),
				),
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order_by'    => 'events.id DESC',
				'query_type'  => 'get_results',
				'event_range' => true,
				'start_date'  => $this->start_date,
				'end_date'    => $this->end_date,
			) );

			$this->report_data->gross_upsells           = array_sum( wp_list_pluck( $this->report_data->upsells, 'total_upsells' ) );
			$this->report_data->average_gross_upsells   = $this->report_data->gross_upsells / ( $this->chart_interval + 1 );
			$this->report_data->total_funnels_triggered = array_sum( wp_list_pluck( $this->report_data->funnels_triggered, 'total_triggers' ) );
			$this->report_data->offered_viewed          = array_sum( wp_list_pluck( $this->report_data->offered_viewed, 'offered_viewed' ) );
			$this->report_data->total_offers_accepted   = array_sum( wp_list_pluck( $this->report_data->offers_accepted, 'offers_count' ) );
			$this->report_data->total_offers_declined   = array_sum( wp_list_pluck( $this->report_data->offers_declined, 'offers_declined' ) );
			$this->report_data->total_offers_expired    = array_sum( wp_list_pluck( $this->report_data->offers_expired, 'offers_expired' ) );
			$this->report_data->overall_conversion_rate = ( $this->report_data->offered_viewed > 0 ) ? wc_format_decimal( ( $this->report_data->total_offers_accepted / $this->report_data->offered_viewed ) * 100, 2 ) : wc_format_decimal( '0.00', 2 );

			// 3rd party filtering of report data
			$this->report_data = apply_filters( 'woocommerce_admin_upstroke_report_data', $this->report_data );

		}

		/**
		 * Output the report.
		 */
		public function output_report() {

			$ranges = array( 'year'       => __( 'Year', 'woocommerce' ),
			                 'last_month' => __( 'Last month', 'woocommerce' ),
			                 'month'      => __( 'This month', 'woocommerce' ),
			                 '7day'       => __( 'Last 7 days', 'woocommerce' )
			);

			$this->chart_colours = array(
				'gross_upsells'         => '#4D94D9',
				'average_gross_upsells' => '#f1c40f',
				'funnels_triggered'     => '#F6DDCC',
				'offers_accepted'       => '#DBE0E2',
				'offers_declined'       => '#ABB2B9',
				'offers_expired'        => '#b9e6cc',
				'conversion_rate'       => '#422b35',
			);

			$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';

			if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) ) {
				$current_range = '7day';
			}

			$this->check_current_range_nonce( $current_range );
			$this->calculate_current_range( $current_range );

			include WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php';
		}

		/**
		 * Output an export link to export reports in csv file
		 */
		public function get_export_button() {
			$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';
			?>
            <a href="#" download="report-<?php echo esc_attr( $current_range ); ?>-<?php echo esc_attr( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>.csv" class="export_csv" data-export="chart" data-xaxes="<?php esc_attr_e( 'Date', 'woofunnels-upstroke-power-pack' ); ?>" data-exclude_series="" data-groupby="<?php echo esc_attr( $this->chart_groupby ); ?>">
				<?php esc_html_e( 'Export CSV', 'woocommerce' ); ?>
            </a>
			<?php
		}

		/**
		 * Get the main chart.
		 */
		public function get_main_chart() {
			global $wp_locale;

			$upsells           = $this->prepare_chart_data( $this->report_data->upsells, 'upsells_date', 'total_upsells', $this->chart_interval, $this->start_date, $this->chart_groupby );
			$funnels_triggered = $this->prepare_chart_data( $this->report_data->funnels_triggered, 'trigger_date', 'total_triggers', $this->chart_interval, $this->start_date, $this->chart_groupby );
			$offers_accepted   = $this->prepare_chart_data( $this->report_data->offers_accepted, 'offer_date', 'offers_count', $this->chart_interval, $this->start_date, $this->chart_groupby );
			$offers_declined   = $this->prepare_chart_data( $this->report_data->offers_declined, 'declined_date', 'offers_declined', $this->chart_interval, $this->start_date, $this->chart_groupby );
			$offers_expired    = $this->prepare_chart_data( $this->report_data->offers_expired, 'expired_date', 'offers_expired', $this->chart_interval, $this->start_date, $this->chart_groupby );

			// Encode in json format
			$chart_data = array(
				'upsells'           => array_map( array( $this, 'round_chart_totals' ), array_values( $upsells ) ),
				'funnels_triggered' => array_values( $funnels_triggered ),
				'offers_accepted'   => array_values( $offers_accepted ),
				'offers_declined'   => array_values( $offers_declined ),
				'offers_expired'    => array_values( $offers_expired ),
			);

			// 3rd party filtering of report data
			$chart_data = apply_filters( 'woocommerce_admin_upstroke_report_chart_data', $chart_data );

			?>
            <div class="chart-container">
                <div class="chart-placeholder main"></div>
            </div>
            <script type="text/javascript">

                var main_chart;

                jQuery(function () {
                    var upsells_data = jQuery.parseJSON('<?php echo json_encode( $chart_data ); ?>');
                    var drawGraph = function (highlight) {
                        var series = [
                            {
                                label: "<?php echo esc_js( __( 'Funnels Triggered', 'woofunnels-upstroke-power-pack' ) ); ?>",
                                data: upsells_data.funnels_triggered,
                                color: '<?php echo esc_js( $this->chart_colours['funnels_triggered'] ); ?>',
                                bars: {
                                    fillColor: '<?php echo esc_js( $this->chart_colours['funnels_triggered'] ); ?>',
                                    fill: true,
                                    show: true,
                                    lineWidth: 6,
                                    order: 0,
                                    barWidth: <?php echo esc_js( $this->barwidth ); ?>* 0.25,
                                    align: 'center'
                                },
                                shadowSize: 0,
                                hoverable: true,
                            },
                            {
                                label: "<?php echo esc_js( __( 'Offers Declined', 'woofunnels-upstroke-power-pack' ) ); ?>",
                                data: upsells_data.offers_declined,
                                color: '<?php echo esc_js( $this->chart_colours['offers_declined'] ); ?>',
                                bars: {
                                    fillColor: '<?php echo esc_js( $this->chart_colours['offers_declined'] ); ?>',
                                    fill: true,
                                    order: 1,
                                    show: true,
                                    lineWidth: 5,
                                    barWidth: <?php echo esc_js( $this->barwidth ); ?>
                                    * 0.25,
                                    align: 'center'
                                },
                                shadowSize: 0,
                                hoverable: false,
                            },
                            {
                                label: "<?php echo esc_js( __( 'Offers Accepted', 'woofunnels-upstroke-power-pack' ) ); ?>",
                                data: upsells_data.offers_accepted,
                                color: '<?php echo esc_js( $this->chart_colours['offers_accepted'] ); ?>',
                                bars: {
                                    fillColor: '<?php echo esc_js( $this->chart_colours['offers_accepted'] ); ?>',
                                    fill: true,
                                    show: true,
                                    order: 2,
                                    lineWidth: 3,
                                    barWidth: <?php echo esc_js( $this->barwidth ); ?>
                                    * 0.25,
                                    align: 'center'
                                },
                                shadowSize: 0,
                                hoverable: false,
                            },

                            {
                                label: "<?php echo esc_js( __( 'Upsells', 'woofunnels-upstroke-power-pack' ) ); ?>",
                                data: upsells_data.upsells,
                                yaxis: 2,
                                color: '<?php echo esc_js( $this->chart_colours['gross_upsells'] ); ?>',
                                points: {show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true},
                                lines: {show: true, lineWidth: 5, fill: false},
                                shadowSize: 0,
								<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
                            },
                            {
                                label: "<?php echo esc_js( __( 'Average gross upsells', 'woofunnels-upstroke-power-pack' ) ); ?>",
                                data: [[ <?php echo esc_js( min( array_keys( $upsells ) ) ); ?>, <?php echo esc_js( $this->report_data->average_gross_upsells ); ?> ], [ <?php echo esc_js( max( array_keys( $upsells ) ) ); ?>, <?php echo esc_js( $this->report_data->average_gross_upsells ); ?> ]],
                                yaxis: 2,
                                color: '<?php echo esc_js( $this->chart_colours['average_gross_upsells'] ); ?>',
                                points: {show: false},
                                lines: {show: true, lineWidth: 4, fill: false},
                                shadowSize: 0,
                                hoverable: false
                            },
                        ];

                        if (highlight !== 'undefined' && series[highlight]) {
                            highlight_series = series[highlight];

                            highlight_series.color = '#9c5d90';

                            if (highlight_series.bars) {
                                highlight_series.bars.fillColor = '#9c5d90';
                            }

                            if (highlight_series.lines) {
                                highlight_series.lines.lineWidth = 5;
                            }
                        }

                        main_chart = jQuery.plot(
                            jQuery('.chart-placeholder.main'),
                            series,
                            {
                                legend: {
                                    show: false
                                },
                                grid: {
                                    color: '#aaa',
                                    borderColor: 'transparent',
                                    borderWidth: 0,
                                    hoverable: true
                                },
                                xaxes: [{
                                    color: '#aaa',
                                    position: "bottom",
                                    tickColor: 'transparent',
                                    mode: "time",
                                    timeformat: "<?php echo ( 'day' === $this->chart_groupby ) ? '%d %b' : '%b'; ?>",
                                    monthNames: <?php echo json_encode( array_values( $wp_locale->month_abbrev ) ); ?>,
                                    tickLength: 1,
                                    minTickSize: [1, "<?php echo esc_js( $this->chart_groupby ); ?>"],
                                    font: {
                                        color: "#aaa"
                                    }
                                }],
                                yaxes: [
                                    {
                                        min: 0,
                                        minTickSize: 1,
                                        tickDecimals: 0,
                                        color: '#d4d9dc',
                                        font: {color: "#aaa"}
                                    },
                                    {
                                        position: "right",
                                        min: 0,
                                        tickDecimals: 2,
                                        alignTicksWithAxis: 1,
                                        color: 'transparent',
                                        font: {color: "#aaa"}
                                    }
                                ],
                            }
                        );

                        jQuery('.chart-placeholder').resize();
                    }

                    drawGraph();

                    jQuery('.highlight_series').hover(
                        function () {
                            drawGraph(jQuery(this).data('series'));
                        },
                        function () {
                            drawGraph();
                        }
                    );
                });
            </script>
			<?php
		}

		/**
		 * Round our totals correctly.
		 *
		 * @param array|string $amount
		 *
		 * @return array|string
		 */
		private function round_chart_totals( $amount ) {
			if ( is_array( $amount ) ) {
				return array( $amount[0], wc_format_decimal( $amount[1], wc_get_price_decimals() ) );
			} else {
				return wc_format_decimal( $amount, wc_get_price_decimals() );
			}
		}
	}
}
