<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
echo BWF_Admin_Breadcrumbs::render_sticky_bar(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Settings builder page
 */
$experiment = BWFABT_Core()->admin->get_experiment();
include_once( __DIR__ . '/commons/single-exp-head.php' );
$setting_tabs = BWFABT_Core()->admin->get_settings_tabs();
?>

	<div class="bwfabt_page_left_wrap" id="bwfabt_common_vue">
		<div class="bwfabt-loader"><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif"></div>
		<div class="wfabt_wrap wfabt_listing wfabt_global wfabt_variant">
			<?php include_once( __DIR__ . '/commons/section-head.php' ); ?>
		</div>
	</div>


	<div class="bwfabt-setting-container" id="bwfabt_settings_area">
		<div class="bwfabt_tabs">

		</div>

		<div class="bwfabt-setting-wrapper">
			<?php
			foreach ( $setting_tabs as $tab_key => $tab_label ) {
				BWFABT_Core()->admin->get_setting_tab_content( $tab_key );
			}
			do_action( 'bwfabt_after_settings_tabs' ); ?>

		</div>
	</div>

	<!-- Stop experiment -->
	<div class="bwfabt_izimodal_default" style="display: none;" id="modal_stop_experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-stop-experiment.php' ); ?>
	</div>
	<!-- Experiment Readiness -->
	<div class="bwfabt_izimodal_default" style="display: none;" id="modal_start_experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-readiness.php' ); ?>
	</div>

	<!-- Reset Stats -->
	<div class="bwfabt_izimodal_default" style="display: none;" id="modal-reset-stats">
		<?php include_once( __DIR__ . '/modals/bwfabt-reset-stats.php' ); ?>
	</div>

	<!-- Update Experiment popup izimodel -->
	<div style="display: none" id="modal-update-experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-update-experiment.php' ); ?>
	</div>
<?php include_once( __DIR__ . '/commons/single-exp-foot.php' );
