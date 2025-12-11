<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** Registering Settings in top bar */
if ( class_exists( 'BWF_Admin_Breadcrumbs' ) ) {
	BWF_Admin_Breadcrumbs::register_node( [ 'text' => __( 'OrderBumps', 'woofunnels-order-bump' ) ] );
}

if ( class_exists( 'WFFN_Header' ) ) {
    $header_ins = new WFFN_Header();
	$header_ins->set_level_1_navigation_active( 'funnels' );
    $header_ins->set_level_2_post_title( '<span class="bwfan_header_title">Order Bumps</span>' );
    ob_start();
    ?>
        
        <a href="<?php echo admin_url( 'admin.php?page=wfob&section=export' ); ?>" class="page-title-action button button-large"><?php echo __( 'Export', 'woofunnels-order-bump' ); ?></a>&ensp;
        <a href="<?php echo admin_url( 'admin.php?page=wfob&section=import' ); ?>" class="page-title-action button button-large"><?php echo __( 'Import', 'woofunnels-order-bump' ); ?></a>&ensp;
        <a href="javascript:void(0)" class="page-title-action button button-large button-primary" data-izimodal-open="#modal-add-bump" data-iziModal-title="Create New Offer" data-izimodal-transitionin="fadeInDown"><?php echo __( 'Add New', 'woofunnels-order-bump' ); ?></a>
    <?php
    $checkout_actions = ob_get_contents();
    ob_end_clean();
    $header_ins->set_level_2_side_type('html');
    $header_ins->set_level_2_right_html( $checkout_actions );
    echo $header_ins->render();
} else {
    BWF_Admin_Breadcrumbs::render_sticky_bar();
}
?>
    <div class="wrap wfob_funnels_listing wfob_global" id="wfob_admin_post_table">
        <div class="wfob_clear_10"></div>
        <?php if ( ! class_exists( 'WFFN_Header' ) ) echo '<div class="wfob_clear_30"></div><div class="wfob_clear_30"></div>' ?>
        <div class="wfob_head_bar">
            <div class="wfob_bar_head"><?php _e( 'OrderBumps', 'woofunnels-order-bump' ); ?></div>
           <?php if ( ! class_exists( 'WFFN_Header' ) ) : ?>
                <a href="javascript:void(0)" class="page-title-action button button-large button-primary" data-izimodal-open="#modal-add-bump" data-iziModal-title="Create New Offer" data-izimodal-transitionin="fadeInDown"><?php echo __( 'Add New', 'woofunnels-order-bump' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=wfob&section=import' ); ?>" class="page-title-action button button-large"><?php echo __( 'Import', 'woofunnels-order-bump' ); ?></a>&ensp;
                <a href="<?php echo admin_url( 'admin.php?page=wfob&section=export' ); ?>" class="page-title-action button button-large"><?php echo __( 'Export', 'woofunnels-order-bump' ); ?></a>&ensp;
           <?php endif; ?>
        </div>
        <div id="poststuff">
            <div class="inside">
                <div class="wfob_page_col2_wrap wfob_clearfix">
                    <div class="wfob_page_left_wrap">
                        <form method="GET">
                            <input type="hidden" name="page" value="wfob"/>
                            <input type="hidden" name="status" value="<?php echo( isset( $_GET['status'] ) ? $_GET['status'] : '' ); ?>"/>
							<?php
							$table = new WFOB_Post_Table();
							$table->render_trigger_nav();
							$table->search_box( 'Search' );
							$table->data = WFOB_Common::get_post_table_data();
							$table->prepare_items();
							$table->display();
							?>
                        </form>
						<?php $table->order_preview_template(); ?>
                    </div>
                    <div class="wfob_page_right_wrap">
						<?php do_action( 'wfob_page_right_content' ); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
do_action( 'wfob_admin_footer' );

?>
<?php include __DIR__ . '/global/model.php';
