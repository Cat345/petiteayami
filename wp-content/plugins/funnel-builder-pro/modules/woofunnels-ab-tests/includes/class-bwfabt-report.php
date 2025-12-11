<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Report' ) ) {
	/**
	 * This class will be extended by all all single reports(like upstroke, aero etc) to render their individual reports
	 * Class BWFABT_Report
	 */
	#[AllowDynamicProperties]
	abstract class BWFABT_Report {

		public $chart_data = array();


		/**
		 * @var $stat_date
		 */
		protected $start_date;

		/**
		 * @var $end_date
		 */
		protected $end_date;

		/**
		 * BWFABT_Report constructor.
		 */
		public function __construct() {
		}

		/**
		 * @param BWFABT_Experiment $experiment
		 * @param $type
		 */
		public function get_performance_overview( $experiment, $type ) {
			$performance_heads = $this->get_performance_heads( $experiment, $type );
			$analytics         = $this->get_performance_data( $experiment, $type );
			foreach ( $performance_heads as $pkey => $head_title ) { ?>
                <div class="wfabt_cards">
                    <div class="wfabt_mid">
                    
                    <span><?php echo $analytics[ $pkey ]; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>

                        <p><?php echo esc_html( $head_title ); ?> </p>

                    </div>
                </div>
			<?php }
		}

		/**
		 * @param BWFABT_Experiment $experiment
		 * @param $type
		 */
		public function localize_chart_data_default( $experiment, $type ) {
			$this->chart_data['stats_head_chart_labels']        = $this->get_stats_head_chart_labels();
			$this->chart_data['chart_frequencies_chart_labels'] = $this->get_chart_frequencies_chart_labels();
			$this->chart_data['defaults']                       = [];
			$this->chart_data['defaults']['stats']              = $this->get_default_stats();
			$this->chart_data['defaults']['frequency']          = $this->get_default_frequency();
			$this->chart_data['defaults']['options']            = $this->get_chart_options();
		}


		/**
		 * @param $experiment_id
		 *
		 * @return string
		 */
		public function get_choose_winner_table( $experiment_id ) {
			return '';
		}

		/**
		 * @return array
		 */
		public function get_performance_heads( $experiment, $type ) {
			$heads = array(
				'total_views'           => __( 'Total Views', 'woofunnels-ab-tests' ),
				'total_conversion'      => __( 'Total Conversions', 'woofunnels-ab-tests' ),
				'avg_revenue_per_visit' => __( 'Avg. Revenue Per Visit', 'woofunnels-ab-tests' ),
				'conversion_rate'       => __( 'Conversion Rate', 'woofunnels-ab-tests' ),
				'total_sales'           => __( 'Total Revenue', 'woofunnels-ab-tests' ),
			);

			return apply_filters( 'bwabt_performance_heads', $heads, $experiment, $type );
		}

		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return array
		 */
		public function get_performance_data( $experiment, $type ) {
			return array();
		}

		public function get_chart_frequencies( $experiment, $type ) {
			$frequencies = array(
				'daily'   => __( 'Daily', 'woofunnels-ab-tests' ),
				'weekly'  => __( 'Weekly', 'woofunnels-ab-tests' ),
				'monthly' => __( 'Monthly', 'woofunnels-ab-tests' ),
			);

			return apply_filters( 'bwfabt_chart_frequencies', $frequencies, $experiment, $type );
		}

		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return mixed|void
		 */
		public function get_stats_head( $experiment, $type ) {
			$stats_heads = array(
				'views'      => __( 'By Total Views', 'woofunnels-ab-tests' ),
				'revenue'    => __( 'By Total Revenue', 'woofunnels-ab-tests' ),
				'accepted'   => __( 'By Total Conversion', 'woofunnels-ab-tests' ),
				'conversion' => __( 'By Conversion Rate', 'woofunnels-ab-tests' ),
			);

			return apply_filters( 'bwfabt_stats_head', $stats_heads, $experiment, $type );
		}


		public function get_chart_frequencies_chart_labels() {
			return array(
				'daily'   => __( 'Date (YYYY-MM-DD)', 'woofunnels-ab-tests' ),
				'weekly'  => __( 'Weeks of the year', 'woofunnels-ab-tests' ),
				'monthly' => __( 'Months', 'woofunnels-ab-tests' ),
			);
		}

		public function get_currency_symbol() {

			if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
				return get_woocommerce_currency_symbol();
			} elseif ( function_exists( 'wffn_currency_symbols' ) ) {
				$symbols         = wffn_currency_symbols();
				$currency        = 'USD';
				$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';

				return $currency_symbol;
			}

			return '$';
		}

		/**
		 * @return array
		 */
		public function get_stats_head_chart_labels() {
			return array(
				'revenue'    => sprintf( __( 'Revenue in %s', 'woofunnels-ab-tests' ), html_entity_decode( $this->get_currency_symbol() ) ),
				'views'      => __( 'Views', 'woofunnels-ab-tests' ),
				'accepted'   => __( 'Conversions', 'woofunnels-ab-tests' ),
				'conversion' => __( 'Conversion Rate (%)', 'woofunnels-ab-tests' ),
			);
		}

		public function get_default_frequency() {
			return 'daily';


		}

		public function get_default_stats() {
			return 'views';
		}

		public function get_chart_options() {
			return [
				'title'      => [
					'display' => true,
					'text'    => $this->get_chart_title(),
				],
				'responsive' => true,
				'scales'     => [
					'xAxes' => [
						[
							'display'    => true,
							'scaleLabel' => [
								'display'     => true,
								'labelString' => $this->get_chart_frequencies_chart_labels()[ $this->get_default_frequency() ],
							]
						]
					],
					'yAxes' => [
						[
							'display'    => true,
							'scaleLabel' => [
								'display'     => true,
								'labelString' => $this->get_stats_head_chart_labels()[ $this->get_default_stats() ],
							]
						]
					]
				]
			];
		}

		public function get_chart_title() {

			return __( 'A/B Test Result', 'woofunnels-ab-tests' );
		}

		/**
		 * @param BWFABT_Experiment $experiment
		 */
		public function set_start_and_end_date( $experiment ) {
			$this->start_date = $experiment->get_report_start_date();
			$this->end_date   = $experiment->get_report_end_date();
		}

		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return bool|void
		 */
		public function localize_chart_data( $experiment, $type ) {
			$this->localize_chart_data_default( $experiment, $type );
			$colors      = BWFABT_Core()->admin->get_variant_colors();
			$color_index = 0;

			$variants = $experiment->get_variants();
			$variants = BWFABT_Core()->admin->move_controller_on_top( $variants );


			/**
			 * We have to rearrange the chart data when experiment is ended so that the replace variant will always be on top, since it replaced the control
			 * So we need to modify an array a bit for th desired result
			 */
			if ( 4 === $experiment->get_status() ) {
				$all_variants = array_keys( $variants );

				$last_index = end( $all_variants );


				$control_data                                      = get_post_meta( $last_index, '_bwf_ab_control', true );
				$control_id                                        = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? intval( $control_data['control_id'] ) : 0;
				$variant_id                                        = ( ! empty( $control_id ) && $control_id > 0 ) ? intval( $control_id ) : intval( $last_index );
				$this->chart_data['variant_title'][ $variant_id ]  = get_the_title( $variant_id );
				$this->chart_data['variant_colors'][ $variant_id ] = $colors[ $color_index ];
				$color_index ++;
				unset( $variants[ $last_index ] );
				foreach ( array_keys( $variants ) as $variant_id ) {
					$control_data                                      = get_post_meta( $variant_id, '_bwf_ab_control', true );
					$control_id                                        = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? intval( $control_data['control_id'] ) : 0;
					$variant_id                                        = ( ! empty( $control_id ) && $control_id > 0 ) ? intval( $control_id ) : intval( $variant_id );
					$this->chart_data['variant_title'][ $variant_id ]  = get_the_title( $variant_id );
					$this->chart_data['variant_colors'][ $variant_id ] = $colors[ $color_index ];
					$color_index ++;
				}
			} else {
				foreach ( array_keys( $variants ) as $variant_id ) {
					$control_data                                      = get_post_meta( $variant_id, '_bwf_ab_control', true );
					$control_id                                        = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? intval( $control_data['control_id'] ) : 0;
					$variant_id                                        = ( ! empty( $control_id ) && $control_id > 0 ) ? intval( $control_id ) : intval( $variant_id );
					$this->chart_data['variant_title'][ $variant_id ]  = get_the_title( $variant_id );
					$this->chart_data['variant_colors'][ $variant_id ] = $colors[ $color_index ];
					$color_index ++;
				}
			}


			?>
            <script>window.bwfabtChart = <?php echo wp_json_encode( $this->chart_data )?>;</script>
			<?php
		}

		/**
		 * @return array
		 */
		public function get_analytics( $experiment ) {
			$table_data = $this->table_data;

			$color_index = 0;

			/**
			 * We have to rearrange the row data when experiment is ended so that the replace variant will always be on top, since it replaced the control
			 * So we need to modify an array a bit for th desired result
			 */
			if ( 4 === $experiment->get_status() ) {
				$all_variants = array_keys( $table_data );

				/**
				 * Keep the last element seperately
				 */
				$last_elem  = end( $table_data );
				$last_index = end( $all_variants );

				/**
				 * render the row, last as first
				 */
				$this->single_row( $experiment, $last_elem, $last_index, 0 );

				/**
				 * unset that row from the original data so that it will not render again
				 */
				unset( $table_data[ $last_index ] );
				$color_index ++;

				/**
				 * Loop over rest of variants and render rows
				 */
				foreach ( $table_data as $id => $data ) {
					$this->single_row( $experiment, $data, $id, $color_index );
					$color_index ++;
				}
			} else {
				foreach ( $table_data as $optin_id => $data ) {
					$this->single_row( $experiment, $data, $optin_id, $color_index );
					$color_index ++;
				}
			}


		}
	}
}