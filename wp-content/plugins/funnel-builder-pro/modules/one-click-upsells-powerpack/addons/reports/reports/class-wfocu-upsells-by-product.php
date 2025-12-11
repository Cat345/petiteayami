<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WC_Report_Upsells_By_Product' ) ) {
	/**
	 * Upstroke Admin Report - upstroke by date
	 *
	 * Find the upsells by product accepted between given dates
	 *
	 */
	class WC_Report_Upsells_By_Product extends WC_Admin_Report {

		/**
		 * Chart colors.
		 *
		 * @var array
		 */
		public $chart_colours = array();

		/**
		 * Product ids.
		 *
		 * @var array
		 */
		public $product_ids = array();

		/**
		 * Product ids with titles.
		 *
		 * @var array
		 */
		public $product_ids_titles = array();

		/**
		 * WC_Report_Upsells_By_Product constructor.
		 */
		public function __construct() {
			if ( isset( $_GET['product_ids'] ) && is_array( $_GET['product_ids'] ) ) {
				$product_ids       = array_map( 'absint', $_GET['product_ids'] );
				$this->product_ids = array_filter( $product_ids );
			} elseif ( isset( $_GET['product_ids'] ) ) {
				$this->product_ids = array_filter( array( absint( $_GET['product_ids'] ) ) );
			}
		}

		/**
		 * Get the legend for the main chart sidebar.
		 *
		 * @return array
		 */
		public function get_chart_legend() {

			if ( empty( $this->product_ids ) ) {
				return array();
			}
			$legend = array();

			$total_upsells = WFOCU_Core()->track->query_results( array(
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
				),
				'where'       => array(
					array(
						'key'      => 'events.action_type_id',
						'value'    => 5,
						'operator' => '=',
					),
					array(
						'key'      => 'events.object_id',
						'value'    => $this->product_ids,
						'operator' => 'IN',
					),
				),
				'query_type'  => 'get_results',
				'event_range' => true,
				'start_date'  => $this->start_date,
				'end_date'    => $this->end_date,
			) );

			$item_upsells = $total_upsells[0]->upsells;
			$item_count   = $total_upsells[0]->item_count;


			if ( count( $this->product_ids ) == 1 && get_post_type( $this->product_ids[0] ) == 'wfocu_funnel' ) {
				/* translators: %s: total upsells */
				$upsell_legend_title = sprintf( __( '%s Upsells for all the items from this funnel.', 'woofunnels-upstroke-power-pack' ), '<strong>' . wc_price( $item_upsells ) . '</strong>' );
				/* translators: %s: total items accepted as offer */
				$accepted_legend_title = sprintf( __( '%s offer accepted for the products from this funnel', 'woofunnels-upstroke-power-pack' ), '<strong>' . ( $item_count ) . '</strong>' );
			} else {
				/* translators: %s: total upsells */
				$upsell_legend_title = sprintf( __( '%s Upsells for the selected items', 'woofunnels-upstroke-power-pack' ), '<strong>' . wc_price( $item_upsells ) . '</strong>' );
				/* translators: %s: total items accepted as offer */
				$accepted_legend_title = sprintf( __( '%s offer accepted for the selected product', 'woofunnels-upstroke-power-pack' ), '<strong>' . ( $item_count ) . '</strong>' );
			}

			$legend[] = array(
				/* translators: %s: total items sold */
				'title'            => $upsell_legend_title,
				'color'            => $this->chart_colours['upsells_amount'],
				'highlight_series' => 1,
			);

			$legend[] = array(
				'title'            => $accepted_legend_title,
				'color'            => $this->chart_colours['item_count'],
				'highlight_series' => 0,
			);

			return $legend;
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
				'upsells_amount' => '#3498db',
				'item_count'     => '#d4d9dc',
			);

			$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '7day';

			if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) ) {
				$current_range = '7day';
			}

			$this->check_current_range_nonce( $current_range );
			$this->calculate_current_range( $current_range );

			include WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php';
		}

		/**
		 * Get chart widgets.
		 *
		 * @return array
		 */
		public function get_chart_widgets() {

			$widgets = array();

			if ( ! empty( $this->product_ids ) ) {
				$widgets[] = array(
					'title'    => __( 'Showing reports for:', 'woocommerce' ),
					'callback' => array( $this, 'current_filters' ),
				);
			}

			$widgets[] = array(
				'title'    => '',
				'callback' => array( $this, 'products_widget' ),
			);

			return $widgets;
		}

		/**
		 * Output products widget.
		 */
		public function products_widget() {
			?>
            <h4 class="section_title"><span><?php esc_html_e( 'Product search', 'woocommerce' ); ?></span></h4>
            <div class="section">
                <form method="GET">
                    <div>
                        <select class="wc-product-search" style="width:203px;" multiple="multiple" id="product_ids" name="product_ids[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="wfocu_accepted_product_search"></select>
                        <button type="submit" class="submit button" value="<?php esc_attr_e( 'Show', 'woocommerce' ); ?>"><?php esc_html_e( 'Show', 'woocommerce' ); ?></button>
                        <input type="hidden" name="range" value="<?php echo ( ! empty( $_GET['range'] ) ) ? esc_attr( wc_clean( $_GET['range'] ) ) : ''; ?>"/>
                        <input type="hidden" name="start_date" value="<?php echo ( ! empty( $_GET['start_date'] ) ) ? esc_attr( wc_clean( $_GET['start_date'] ) ) : ''; ?>"/>
                        <input type="hidden" name="end_date" value="<?php echo ( ! empty( $_GET['end_date'] ) ) ? esc_attr( wc_clean( $_GET['end_date'] ) ) : ''; ?>"/>
                        <input type="hidden" name="page" value="<?php echo ( ! empty( $_GET['page'] ) ) ? esc_attr( wc_clean( $_GET['page'] ) ) : ''; ?>"/>
                        <input type="hidden" name="tab" value="<?php echo ( ! empty( $_GET['tab'] ) ) ? esc_attr( wc_clean( $_GET['tab'] ) ) : ''; ?>"/>
                        <input type="hidden" name="report" value="<?php echo ( ! empty( $_GET['report'] ) ) ? esc_attr( wc_clean( $_GET['report'] ) ) : ''; ?>"/>
						<?php wp_nonce_field( 'wcfou_accepted_product_search', 'wcfou_reports_nonce', false ); ?>

                    </div>
                </form>
            </div>
            <h4 class="section_title"><span><?php esc_html_e( 'Top Upsells', 'woofunnels-upstroke-power-pack' ); ?></span></h4>
            <div class="section">
                <table cellspacing="0">
					<?php
					$top_upsells = WFOCU_Core()->track->query_results( array(
						'data'        => array(
							'object_id'      => array(
								'type'     => 'col',
								'function' => '',
								'name'     => 'product_id',
							),
							'action_type_id' => array(
								'type'     => 'col',
								'function' => 'COUNT',
								'name'     => 'item_count',
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
						'limit'       => 5,
						'group_by'    => 'events.object_id',
						'order_by'    => 'item_count',
						'order'       => 'DESC',
						'start_date'  => $this->start_date,
						'end_date'    => $this->end_date,
					) );
					if ( count( $top_upsells ) > 0 ) {
						usort( $top_upsells, function ( $item1, $item2 ) {
							if ( $item1->item_count == $item2->item_count ) {
								return 0;
							}

							return $item1->item_count > $item2->item_count ? - 1 : 1;
						} );
						foreach ( $top_upsells as $top_upsell ) {
							echo '<tr class="' . ( in_array( absint( $top_upsell->product_id ), $this->product_ids, true ) ? 'active' : '' ) . '">
							<td class="count">' . esc_html( $top_upsell->item_count ) . '</td>
							<td class="name"><a href="' . esc_url( add_query_arg( 'product_ids', $top_upsell->product_id ) ) . '">' . esc_html( get_the_title( $top_upsell->product_id ) ) . '</a></td>
							<td class="sparkline">' . wp_kses_post( $this->upsells_sparkline( $top_upsell->product_id, 7, 'count' ) ) . '</td>
						</tr>';
						}

					} else {
						echo '<tr><td colspan="3">' . esc_html__( 'No products found in range', 'woocommerce' ) . '</td></tr>';
					}
					?>
                </table>
            </div>
            <h4 class="section_title"><span><?php esc_html_e( 'Top earners', 'woocommerce' ); ?></span></h4>
            <div class="section">
                <table cellspacing="0">
					<?php
					$top_earners = WFOCU_Core()->track->query_results( array(
						'data'        => array(
							'object_id' => array(
								'type'     => 'col',
								'function' => '',
								'name'     => 'product_id',
							),
							'value'     => array(
								'type'     => 'col',
								'function' => 'SUM',
								'name'     => 'upsells',
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
						'limit'       => 5,
						'group_by'    => 'events.object_id',
						'order_by'    => 'upsells',
						'order'       => 'DESC',
						'start_date'  => $this->start_date,
						'end_date'    => $this->end_date,
					) );

					if ( count( $top_earners ) > 0 ) {
						usort( $top_earners, function ( $item1, $item2 ) {
							if ( $item1->upsells == $item2->upsells ) {
								return 0;
							}

							return $item1->upsells > $item2->upsells ? - 1 : 1;
						} );
						foreach ( $top_earners as $top_earner ) {
							echo '<tr class="' . ( in_array( $top_earner->product_id, $this->product_ids ) ? 'active' : '' ) . '">
							<td class="count">' . wp_kses_post( wc_price( $top_earner->upsells ) ) . '</td>
							<td class="name"><a href="' . esc_url( add_query_arg( 'product_ids', $top_earner->product_id ) ) . '">' . esc_html( get_the_title( $top_earner->product_id ) ) . '</a></td>
							<td class="sparkline">' . wp_kses_post( $this->upsells_sparkline( $top_earner->product_id, 7, 'upsells' ) ) . '</td>
						</tr>';
						}

					} else {
						echo '<tr><td colspan="3">' . esc_html__( 'No products found in range', 'woocommerce' ) . '</td></tr>';
					}
					?>
                </table>
            </div>

            <script type="text/javascript">
                jQuery('.section_title').click(function () {
                    var next_section = jQuery(this).next('.section');

                    if (jQuery(next_section).is(':visible'))
                        return false;

                    jQuery('.section:visible').slideUp();
                    jQuery('.section_title').removeClass('open');
                    jQuery(this).addClass('open').next('.section').slideDown();

                    return false;
                });
                jQuery('.section').slideUp(100, function () {
					<?php if ( empty( $this->product_ids ) ) : ?>
                    jQuery('.section_title:eq(1)').click();
					<?php endif; ?>
                });
            </script>
			<?php
		}

		/**
		 * Prepares a sparkline to show upsells in the last X days.
		 *
		 * @param string $object_id ID of the product to show. Blank to get all orders.
		 * @param int $days Days of stats to get.
		 * @param string $type Type of sparkline to get. Ignored if ID is not set.
		 *
		 * @return string
		 */
		public function upsells_sparkline( $object_id = '', $days = 7, $type = 'upsells' ) {

			$sparks_data = WFOCU_Core()->track->query_results( array(
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
					array(
						'key'      => 'events.object_id',
						'value'    => $object_id,
						'operator' => '=',
					),
				),
				'query_type'  => 'get_results',
				'event_range' => true,
				'order_by'    => 'events.timestamp',
				'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
				'order'       => 'ASC',
				'start_date'  => strtotime( '-6 days', strtotime( 'midnight', current_time( 'timestamp' ) ) ),
				'end_date'    => strtotime( 'midnight', current_time( 'timestamp' ) ),
			) );

			$total_upsells = array_sum( wp_list_pluck( $sparks_data, 'upsells' ) );
			$total_count   = array_sum( wp_list_pluck( $sparks_data, 'item_count' ) );

			if ( 'upsells' === $type ) {
				/* translators: 1: total upsells 2: days */
				$tooltip        = sprintf( __( 'Upsells %1$s worth in the last %2$d days', 'woofunnels-upstroke-power-pack' ), strip_tags( wc_price( $total_upsells ) ), $days );
				$sparkline_data = array_values( $this->prepare_chart_data( $sparks_data, 'upsells_date', 'upsells', $days - 1, strtotime( 'midnight -' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ), 'day' ) );
			} else {
				/* translators: 1: total items sold 2: days */
				$tooltip        = sprintf( _n( 'Accepted %1$d product as offer in the last %2$d days', 'Accepted %1$d products as offers in the last %2$d days', $total_count, 'woofunnels-upstroke-power-pack' ), $total_count, $days );
				$sparkline_data = array_values( $this->prepare_chart_data( $sparks_data, 'upsells_date', 'item_count', $days - 1, strtotime( 'midnight -' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ), 'day' ) );
			}

			return '<span class="wc_sparkline ' . ( ( 'upsells' === $type ) ? 'lines' : 'bars' ) . ' tips" data-color="#777" data-tip="' . esc_attr( $tooltip ) . '" data-barwidth="' . 60 * 60 * 16 * 1000 . '" data-sparkline="' . esc_attr( json_encode( $sparkline_data ) ) . '"></span>';
		}

		/**
		 * Output current filters.
		 */
		public function current_filters() {

			$this->product_ids_titles = array();

			foreach ( $this->product_ids as $product_id ) {

				$product = wc_get_product( $product_id );

				if ( $product ) {
					$this->product_ids_titles[] = $product->get_formatted_name();
				} elseif ( 'wfocu_funnel' === get_post_type( $product_id ) ) {
					$this->product_ids_titles[] = get_the_title( $product_id );
				} else {
					$this->product_ids_titles[] = '#' . $product_id;
				}
			}

			echo '<p><strong>' . wp_kses_post( implode( ', ', $this->product_ids_titles ) ) . '</strong></p>';
			echo '<p><a class="button" href="' . esc_url( remove_query_arg( 'product_ids' ) ) . '">' . esc_html__( 'Reset', 'woocommerce' ) . '</a></p>';
		}

		/**
		 * Output an export link to export report in CSV format
		 */
		public function get_export_button() {
			$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';
			?>
            <a href="#" download="report-<?php echo esc_attr( $current_range ); ?>-<?php echo esc_attr( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>.csv" class="export_csv" data-export="chart" data-xaxes="<?php esc_attr_e( 'Date', 'woofunnels-upstroke-power-pack' ); ?>" data-exclude_series="" data-groupby="<?php echo esc_attr( $this->chart_groupby ); ?>">
				<?php esc_html_e( 'Export CSV', 'woofunnels-upstroke-power-pack' ); ?>
            </a>
			<?php
		}

		/**
		 * Get the main chart.
		 */
		public function get_main_chart() {
			global $wp_locale;

			if ( empty( $this->product_ids ) ) {
				?>
                <div class="chart-container">
                    <p class="chart-prompt"><?php esc_html_e( 'Choose a product/funnel to view stats', 'woofunnels-upstroke-power-pack' ); ?></p>
                </div>
				<?php
				//If a funnel is selected then providing upsells data for all products ids in each offer in this funnel for data calculation
			} else {
				// Get upsells and upsells date in range - we want the sum of upsells, COUNT of product accepted, COUNT of upsells
				$upsells_data = WFOCU_Core()->track->query_results( array(
					'data'        => array(
						'object_id'      => array(
							'type'     => 'col',
							'function' => '',
							'name'     => 'product_id',
						),
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
						array(
							'key'      => 'events.object_id',
							'value'    => $this->product_ids,
							'operator' => 'IN',
						),
					),
					'query_type'  => 'get_results',
					'event_range' => true,
					'group_by'    => 'YEAR(events.timestamp), MONTH(events.timestamp), DAY(events.timestamp)',
					'order_by'    => 'events.timestamp',
					'order'       => 'ASC',
					'start_date'  => $this->start_date,
					'end_date'    => $this->end_date,
				) );

				if ( count( $upsells_data ) > 0 ) {
					foreach ( $upsells_data as $key => $upsells ) {
						$upsells_data[ $key ]->upsells = wc_format_decimal( $upsells->upsells, 2 );
					}
				}

				// Prepare data for report.
				$product_accepted_counts  = $this->prepare_chart_data( $upsells_data, 'upsells_date', 'item_count', $this->chart_interval, $this->start_date, $this->chart_groupby );
				$product_accepted_upsells = $this->prepare_chart_data( $upsells_data, 'upsells_date', 'upsells', $this->chart_interval, $this->start_date, $this->chart_groupby );

				// Encode in json format.
				$chart_data = json_encode( array(
					'product_accepted_counts'  => array_values( $product_accepted_counts ),
					'product_accepted_upsells' => array_values( $product_accepted_upsells ),
				) );

				?>
                <div class="chart-container">
                    <div class="chart-placeholder main"></div>
                </div>
                <script type="text/javascript">
                    var main_chart;

                    jQuery(function () {
                        var upsells_data = jQuery.parseJSON('<?php echo( $chart_data ); ?>');
                        var drawGraph = function (highlight) {

                            var series = [
                                {
                                    label: "<?php echo esc_js( __( 'Number of items sold', 'woocommerce' ) ); ?>",
                                    data: upsells_data.product_accepted_counts,
                                    color: '<?php echo( $this->chart_colours['item_count'] ); ?>',
                                    bars: {
                                        fillColor: '<?php echo( $this->chart_colours['item_count'] ); ?>',
                                        fill: true,
                                        show: true,
                                        lineWidth: 0,
                                        barWidth: <?php echo( $this->barwidth ); ?>
                                        * 0.5,
                                        align: 'center'
                                    },
                                    shadowSize: 0,
                                    hoverable: false
                                },
                                {
                                    label: "<?php echo esc_js( __( 'Upsells amount', 'woofunnels-upstroke-power-pack' ) ); ?>",
                                    data: upsells_data.product_accepted_upsells,
                                    yaxis: 2,
                                    color: '<?php echo esc_js( $this->chart_colours['upsells_amount'] ); ?>',
                                    points: {show: true, radius: 5, lineWidth: 3, fillColor: '#fff', fill: true},
                                    lines: {show: true, lineWidth: 4, fill: false},
                                    shadowSize: 0,
									<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
                                }
                            ];

                            if (highlight !== 'undefined' && series[highlight]) {
                                highlight_series = series[highlight];

                                highlight_series.color = '#9c5d90';

                                if (highlight_series.bars)
                                    highlight_series.bars.fillColor = '#9c5d90';

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
                                        monthNames: <?php echo( json_encode( array_values( $wp_locale->month_abbrev ) ) ); ?>,
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
                                            color: '#ecf0f1',
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

                        jQuery('.highlight_series').hover(function () {
                                drawGraph(jQuery(this).data('series'));
                            },
                            function () {
                                drawGraph();
                            });
                    });
                </script>
				<?php
			}

		}
	}
}