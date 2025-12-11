<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
echo BWF_Admin_Breadcrumbs::render_sticky_bar(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Analytics page
 */
$experiment = BWFABT_Core()->admin->get_experiment();
include_once( __DIR__ . '/commons/single-exp-head.php' ); ?>
	<div class="bwfabt_page_left_wrap" id="bwfabt_common_vue">
		<div class="bwfabt-loader"><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif"></div>

		<div class="wfabt_wrap wfabt_listing wfabt_global wfabt_variant">

			<?php include_once( __DIR__ . '/commons/section-head.php' ); ?>

			<div class="wfabt_performance">
				<div class="wfabt_section_heading">
					<div class="wfabt_sec_head"><?php esc_attr_e( 'Performance', 'woofunnels-ab-tests' ) ?></div>
				</div>
				<div class="wfabt_section_data">
					<div class="wfabt_flex">
						<?php BWFABT_Core()->admin->get_performance_overview( $experiment ); ?>
					</div>
				</div>
			</div>

			<!-------  GRAPH   ----->
			<?php
			$frequencies = BWFABT_Core()->admin->get_chart_frequencies( $experiment );
			$stats_heads = BWFABT_Core()->admin->get_stats_head( $experiment );

			BWFABT_Core()->admin->localize_chart_data( $experiment ); ?>

			<div class="wfabt_graph wfabt_head_graph">
				<div class="wfabt_section_heading">
					<div class="wfabt_sec_head"> <?php esc_html_e( 'Graphs', 'woofunnels-ab-tests' ) ?> </div>
					<div class="wfabt_controls">
						<select class="bwfabt-params" id="bwfabt_frequency">
							<?php foreach ( $frequencies as $fkey => $frequency ) {
								echo '<option value="' . esc_attr( $fkey ) . '">' . esc_html( $frequency ) . '</option>';
							} ?>
						</select>

						<select class="bwfabt-params" id="bwfabt_stats">
							<?php foreach ( $stats_heads as $skey => $head_title ) {
								echo '<option value="' . esc_attr( $skey ) . '">' . esc_html( $head_title ) . '</option>';
							} ?>
						</select>
					</div>
				</div>

				<!-------  LINE GRAPH  ------->
				<div class="wfabt_section_data">
					<div class="wfabt_graphs_points">
						<canvas id="bwfabt_chart" width="1500" height="600"></canvas>
					</div>
				</div>
			</div><!-------  wfabt_graph ENDS  ----->

			<!-----  Detailed performance  ------>
			<div class="wfabt_graph wfabt_head_graph">
				<div class="wfabt_section_heading">
					<div class="wfabt_sec_head"> <?php esc_html_e( 'Detailed Performance', 'woofunnels-ab-tests' ) ?> </div>
				</div>
				<div class="all_varaiants wfabt_performance">

					<?php BWFABT_Core()->admin->get_analytics( $experiment ); ?>

				</div> <!-- all_variants wfabt_performance -->
			</div> <!-- wfabt_graph wfabt_head_graph  Detailed permformance -->

			<?php if ( $experiment->is_started() && false === $experiment->is_completed() ) { ?>
				<div class="wfabt_bl_btn_award">
					<a data-izimodal-open="#modal_choose_winner" data-izimodal-transitionin="fadeInDown" href="javascript:void(0);" class="wfabt_btn wfabt_btn_primary">
						<span class="animate_btn"></span>
						<img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/awarsd.png"><?php esc_html_e( 'Declare a winner', 'woofunnels-ab-tests' ); ?></a>
				</div>

			<?php } ?>
		</div>

	</div>

	<!-- Update Experiment popup izimodel -->
	<div style="display: none" id="modal-update-experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-update-experiment.php' ); ?>
	</div>

	<!-- Experiment Readiness -->
	<div class="bwfabt_izimodal_default" style="display: none;" id="modal_start_experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-readiness.php' ); ?>
	</div>

	<!-- Choose winner -->
	<div class="bwfabt_izimodal_default" style="display: none;" id="modal_choose_winner">
		<?php include_once( __DIR__ . '/modals/bwfabt-choose-winner.php' ); ?>
	</div>

	<!-- Stop experiment -->
	<div class="bwfabt_izimodal_default" style="display: none;" id="modal_stop_experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-stop-experiment.php' ); ?>
	</div>
<?php include_once( __DIR__ . '/commons/single-exp-foot.php' );
