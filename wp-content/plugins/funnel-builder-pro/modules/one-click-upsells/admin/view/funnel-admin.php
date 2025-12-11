<?php
/** Registering Settings in top bar */
if ( class_exists( 'BWF_Admin_Breadcrumbs' ) ) {
	BWF_Admin_Breadcrumbs::register_node( [ 'text' => __('One Click Upsells', 'woofunnels-upstroke-one-click-upsell') ] );
}

if ( class_exists( 'WFFN_Header' ) ) {
    $header_ins = new WFFN_Header();
	$header_ins->set_level_1_navigation_active( 'funnels' );
    $header_ins->set_level_2_post_title( '<span class="bwfan_header_title">One Click Upsells</span>' );
    ob_start();
    ?>
        
        <a href="<?php echo admin_url( 'admin.php?page=upstroke&tab=export' ); ?>" class="page-title-action button button-large"> <?php esc_html_e( 'Export', 'woofunnels-upstroke-one-click-upsell' ); ?> </a>&ensp;
        <a href="<?php echo admin_url( 'admin.php?page=upstroke&tab=import' ); ?>" class="page-title-action button button-large"> <?php esc_html_e( 'Import', 'woofunnels-upstroke-one-click-upsell' ); ?> </a>&ensp;
        <a href="javascript:void(0)" class="page-title-action button button-large button-primary" data-izimodal-open="#modal-add-funnel" data-iziModal-title="Create New Offer" data-izimodal-transitionin="fadeInUp"><?php echo esc_attr__( "Add New", 'woofunnels-upstroke-one-click-upsell' ); ?></a>
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
<?php if ( ! class_exists( 'WFFN_Header' ) ) echo '<div class="wfocu_clear_30"></div><div class="wfocu_clear_30"></div>' ?>
<div class="wrap wfocu_funnels_listing wfocu_global">

    <?php if ( ! class_exists( 'WFFN_Header' ) ) : ?>
		<h1 class="wfocu_heading_inline"><?php _e('One Click Upsells', 'woofunnels-upstroke-one-click-upsell'); ?></h1>
        <a href="javascript:void(0)" class="page-title-action button button-large button-primary" data-izimodal-open="#modal-add-funnel" data-iziModal-title="Create New Offer" data-izimodal-transitionin="fadeInUp"><?php echo esc_attr__( "Add New", 'woofunnels-upstroke-one-click-upsell' ); ?></a>&ensp;
        <a href="<?php echo admin_url( 'admin.php?page=upstroke&tab=import' ); ?>" class="page-title-action button button-large"> <?php esc_html_e( 'Import', 'woofunnels-upstroke-one-click-upsell' ); ?> </a>&ensp;
        <a href="<?php echo admin_url( 'admin.php?page=upstroke&tab=export' ); ?>" class="page-title-action button button-large"> <?php esc_html_e( 'Export', 'woofunnels-upstroke-one-click-upsell' ); ?> </a>
		<hr class="wp-header-end">
	<?php endif; ?>

    <div class="wfocu_clear_10"></div>
    <div id="poststuff">
        <div class="inside">
            <div class="wfocu_page_col2_wrap wfocu_clearfix">
                <div class="wfocu_page_left_wrap">
                    <form method="GET">
                        <input type="hidden" name="page" value="upstroke"/>
                        <input type="hidden" name="status" value="<?php echo esc_attr( isset( $_GET['status'] ) ? wc_clean( $_GET['status'] ) : '' );  // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>"/>
						<?php
						$table = new WFOCU_Post_Table();
						$table->render_trigger_nav();
						$table->search_box( 'Search' );
						$table->data = WFOCU_Common::get_post_table_data();
						$table->prepare_items();
						$table->display();
						?>
                    </form>
					<?php $table->order_preview_template() ?>
                </div>
                <div class="wfocu_page_right_wrap" style="display: none;">
					<?php do_action( 'wfocu_page_right_content' ); ?>
                </div>
            </div>
        </div>
        <div style="display: none" class="wfocu_success_modal" id="modal-wfocu-state-change-success" data-iziModal-icon="icon-home">


        </div>
    </div>
</div>

<div class="wfocu_izimodal_default" style="display: none" id="modal-add-funnel">
    <div class="sections">
        <form class="wfocu_add_funnel" data-wfoaction="add_new_funnel">
            <div class="wfocu_vue_forms" id="part-add-funnel">
                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
            </div>
            <fieldset>
                <div class="wfocu_form_submit wfocu_swl_btn">
                    <input hidden name="_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wfocu_add_new_funnel' ) ); ?>"/>
					<button data-iziModal-close type="submit" class="wf_cancel_btn wfocu_btn" value="cancel"><?php esc_html_e( 'Cancel', 'woofunnels-upstroke-one-click-upsell' ); ?></button>
					<button type="submit" class="wfocu_btn wfocu_btn_primary" value="add_new"><?php echo esc_html_e( "Add", 'woofunnels-upstroke-one-click-upsell' ); ?></button>
                </div>
                <div class="wfocu_form_response">
                </div>
            </fieldset>
        </form>
        <div class="wfocu-funnel-create-success-wrap">
            <div class="wfocu-funnel-create-success-logo">
                <div class="swal2-icon swal2-success swal2-animate-success-icon">
                    <div class="swal2-success-circular-line-left"></div>
                    <span class="swal2-success-line-tip"></span> <span class="swal2-success-line-long"></span>
                    <div class="swal2-success-ring"></div>
                    <div class="swal2-success-fix"></div>
                    <div class="swal2-success-circular-line-right"></div>
                </div>
            </div>
            <div class="wfocu-funnel-create-message"><?php esc_attr_e( 'Upsell Funnel Created Successfully. Launching Funnel Editor...', 'woofunnels-upstroke-one-click-upsell' ) ?></div>
        </div>
    </div>
</div>

<div class="wfocu_izimodal_default" style="display: none" id="modal-duplicate-funnel">
    <div class="wfocu_duplicate_modal" id="modal_settings_duplicate" data-iziModal-icon="icon-home"></div>
</div>




