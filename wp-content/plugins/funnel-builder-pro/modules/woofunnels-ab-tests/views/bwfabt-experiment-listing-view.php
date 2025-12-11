<?php defined( 'ABSPATH' ) || exit; //Exit if accessed directly
echo BWF_Admin_Breadcrumbs::render_sticky_bar(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Experiment listing page
 */
$new_experiment_url = add_query_arg( array(
	'page'   => 'bwf_ab_tests',
	'action' => 'create_new',
), admin_url( 'admin.php' ) ); ?>

<div class="wrap bwfabt-exp-heading">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Experiments', 'woofunnels-ab-tests' ); ?></h1>
    <a href="<?php echo esc_url( $new_experiment_url ) ?>" class="page-title-action bwfabt_create_experiment"><?php esc_html_e( 'Add New', 'woofunnels-ab-tests' ); ?></a>

    <hr class="wp-header-end">
    <div id="poststuff">
        <div class="inside">
            <div class="bwfabt_page_col2_wrap bwfabt_clearfix">
                <form method="GET">
                    <input type="hidden" name="page" value="bwf_ab_tests"/>
                    <input type="hidden" name="status" value="<?php esc_attr_e( isset( $_GET['status'] ) ? bwfabt_clean( $_GET['status'] ) : '' );  // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification ?>"/>
					<?php

					$table = new BWFABT_Experiment_Table();
					$table->render_trigger_nav();
					$table->search_box( 'Search' );
					$table->data = $experiments;
					$table->prepare_items();
					$table->display();
					?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Experiment Readiness -->
<div class="bwfabt_izimodal_default" style="display: none;" id="modal-delete-experiment">
	<?php include_once( __DIR__ . '/modals/bwfabt-delete-experiment.php' ); ?>
</div>
