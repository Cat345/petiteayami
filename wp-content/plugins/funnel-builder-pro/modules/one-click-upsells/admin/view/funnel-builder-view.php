<?php

$sidebar_menu        = WFOCU_Common::get_sidebar_menu();
$funnel_sticky_line  = __( 'Now Building', 'woofunnels-upstroke-one-click-upsell' );
$funnel_sticky_title = '';
$funnel_onboarding   = true;
if ( isset( $_GET['edit'] ) && ! empty( $_GET['edit'] ) ) {   // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$funnel_sticky_title = get_the_title( wc_clean( $_GET['edit'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$funnel_onboarding_status = get_post_meta( wc_clean( $_GET['edit'] ), '_wfocu_is_rules_saved', true );  // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( 'yes' === $funnel_onboarding_status ) {
		$funnel_onboarding  = false;
		$funnel_sticky_line = '';
	}
}

$funnel_status = get_post_status( wc_clean( $_GET['edit'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.Missing
$funnel_id     = wc_clean( $_GET['edit'] );  // phpcs:ignore WordPress.Security.NonceVerification.Missing

$header_nav_data = array();
if ( is_array( $sidebar_menu ) && count( $sidebar_menu ) > 0 ) {
	ksort( $sidebar_menu );
    foreach ( $sidebar_menu as $step ) {
        $href = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( [
            'page'    => 'upstroke',
            'section' => $step['key'],
            'edit'    => WFOCU_Core()->funnels->get_funnel_id(),
        ], admin_url( 'admin.php' ) ) );

        $header_nav_data[ $step['key'] ] = array(
            'name' => $step['name'],
            'link' => $href,
        );
    }
}

if ( class_exists( 'WFFN_Header' ) ) {
    $header_ins = new WFFN_Header();
    $header_ins->set_level_1_navigation_active( 'funnels' );

   
    ob_start();
    ?>
    <div class="wffn-ellipsis-menu">
        <div class="wffn-menu__toggle">
            <span class="bwfan-tag-rounded bwfan_ml_12 <?php echo 'publish' == $funnel_status ? 'clr-green' : 'clr-orange'; ?>">
                <span class="bwfan-funnel-status"><?php echo 'publish' == $funnel_status ? 'Published' : 'Draft'; ?></span>
                
                <?php echo file_get_contents(  plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/assets/img/icons/arrow-down.svg'  ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
        </div>
        <div class="wffn-ellipsis-menu-dropdown">
            <a data-izimodal-open="#modal-update-funnel" data-izimodal-transitionin="fadeInUp" href="javascript:void(0);"  class="bwf_edt wffn-ellipsis-menu-item"><?php esc_html_e( 'Edit' ) ?></a>
            <div class="wf_funnel_card_switch">
                <label class="funnel_state_toggle wfocu_toggle_btn wffn-ellipsis-menu-item">
                    <span class="bwfan-status-toggle"><?php echo 'publish' == $funnel_status ? 'Draft' : 'Publish'; ?></span>
                    <input name="offer_state" id="state<?php echo esc_attr( $funnel_id ); ?>" data-id="<?php echo esc_attr( $funnel_id ); ?>" type="checkbox" class="wfocu-tgl wfocu-tgl-ios" <?php echo ( $funnel_status === 'publish' ) ? 'checked="checked"' : ''; ?> />
                </label>
            </div>
        </div>
    </div>
    <?php
    $funnel_actions = ob_get_contents();
    ob_end_clean();

    $get_header_data = BWF_Admin_Breadcrumbs::render_top_bar(true);
    if( is_array( $get_header_data ) ) {
        $data_count      = count($get_header_data);
        $page_title_data = $get_header_data[ $data_count - 1 ];
	    $back_link_data  = ( 1 < $data_count ) ? $get_header_data[ $data_count - 2 ] : array();
        $page_title      = $page_title_data['text'] ?? esc_html( 'Funnels' );
        $back_link       = $back_link_data['link'] ?? '#';

        if( version_compare( WFFN_VERSION, '2.0.0 beta', '>=' ) ) {
            $header_ins->set_page_back_link( $back_link );
            $header_ins->set_page_heading( "$page_title" );
            $header_ins->set_page_heading_meta($funnel_actions);
        } else {
            $header_ins->set_level_2_post_title($funnel_actions);
        }
    }


    $header_ins->set_level_2_side_navigation( $header_nav_data ); //set header 2nd level navigation
	$header_ins->set_level_2_side_navigation_active( wc_clean( $_GET['section'] ) ); // active navigation

    echo $header_ins->render();
} else {
    BWF_Admin_Breadcrumbs::render_sticky_bar();
}
?>
<div class="wrap wfocu_funnels_listing wfocu_global">
    <div id="poststuff">
        <div class="inside">
            <?php if ( ! class_exists( 'WFFN_Header' ) ) : ?>
            <div class="wfocu_fixed_header">
                <div class="bwf_breadcrumb">
                    <div class="bwf_before_bre">
                        <div class="wfocu_head_mr" data-status="<?php echo ( $funnel_status !== 'publish' ) ? 'sandbox' : 'live'; ?>">
                            <div class="funnel_state_toggle wfocu_toggle_btn">
                                <input name="offer_state" id="state<?php echo esc_attr( $funnel_id ); ?>" data-id="<?php echo esc_attr( $funnel_id ); ?>" type="checkbox" class="wfocu-tgl wfocu-tgl-ios" <?php echo ( $funnel_status === 'publish' ) ? 'checked="checked"' : ''; ?> />
                                <label for="state<?php echo esc_attr( $funnel_id ); ?>" class="wfocu-tgl-btn wfocu-tgl-btn-small"></label>
                            </div>
                        </div>
                    </div>
                    <?php echo BWF_Admin_Breadcrumbs::render(); ?>
                    <div class="bwf_after_bre">
                        <a data-izimodal-open="#modal-update-funnel" data-izimodal-transitionin="fadeInUp" href="javascript:void(0);" class="bwf_edt">
                            <i class="dashicons dashicons-edit"></i> <?php esc_html_e( 'Edit', 'woofunnels-upstroke-one-click-upsell' ); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php
			if ( is_array( $sidebar_menu ) && count( $sidebar_menu ) > 0 ) {
				ksort( $sidebar_menu );
				$funnel_data = WFOCU_Core()->funnels->get_funnel_offers_admin();
				?>

                <div class="bwf_menu_list_primary">
                    <ul>

						<?php
						foreach ( $sidebar_menu as $menu ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
							$menu_icon = ( isset( $menu['icon'] ) && ! empty( $menu['icon'] ) ) ? $menu['icon'] : 'dashicons dashicons-admin-generic';
							if ( isset( $menu['name'] ) && ! empty( $menu['name'] ) ) {

								$section_url = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
									'page'    => 'upstroke',
									'section' => $menu['key'],
									'edit'    => WFOCU_Core()->funnels->get_funnel_id(),
								), admin_url( 'admin.php' ) ) );

								$class = '';
								if ( isset( $_GET['section'] ) && $menu['key'] === wc_clean( $_GET['section'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
									$class = 'active';
								}

								global $wfocu_is_rules_saved;

								$main_url = $section_url;

								?>
                                <li class="<?php echo $class ?>">
                                    <a href="<?php echo esc_url_raw( $main_url ) ?>">
										<?php echo esc_attr( $menu['name'] ); ?>
                                    </a>
                                </li>


								<?php
							}
						}
						?>
                    </ul>
                </div>
				<?php
			}
			?>
            <?php endif; ?>

            <div class="wfocu_wrap wfocu_box_size <?php echo ( isset( $_REQUEST['section'] ) &&  $_REQUEST['section'] === 'settings' ) ? 'wfocu_wrap_inner_design ' : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing ?>">
                 <div class="wfocu_box_size">
                    <div class="wfocu_wrap_inner <?php echo ( isset( $_REQUEST['section'] ) ) ? 'wfocu_wrap_inner_' . esc_attr( wc_clean( $_REQUEST['section'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing ?>">

						<?php
						$get_keys = wp_list_pluck( $sidebar_menu, 'key' );


						/**
						 * Redirect if any unregistered action found
						 */
						if ( false === in_array( $this->section_page, $get_keys, true ) ) {
							wp_redirect( admin_url( 'admin.php?page=upstroke&section=offers&edit=' . wc_clean( $_GET['edit'] ) ) );   // phpcs:ignore WordPress.Security.NonceVerification.Missing
							exit;
						} else {

							/**
							 * Any registered section should also apply an action in order to show the content inside the tab
							 * like if action is 'stats' then add_action('wfocu_dashboard_page_stats', __FUNCTION__);
							 */
							if ( false === has_action( 'wfocu_dashboard_page_' . $this->section_page ) ) {
								include_once( $this->admin_path . '/view/' . $this->section_page . '.php' );  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

							} else {
								/**
								 * Allow other add-ons to show the content
								 */
								do_action( 'wfocu_dashboard_page_' . $this->section_page );
							}
						}


						do_action( 'wfocu_funnel_page', $this->section_page, WFOCU_Core()->funnels->get_funnel_id() );
						?>

                        <div class="wfocu_clear"></div>
                    </div>
                </div>
            </div>

            <div class="wfocu_izimodal_default" id="modal-update-funnel" style="display: none;">
                <div class="sections">
                    <form class="wfocu_forms_update_funnel" data-wfoaction="update_funnel" novalidate>
                        <div class="wfocu_vue_forms" id="part-update-funnel">
                            <div class="vue-form-generator">
                                <fieldset>
                                    <div class="form-group featured required field-input"><label for="funnel-name"><?php esc_html_e( 'Name', 'woofunnels-upstroke-one-click-upsell' ); ?><!----></label>
                                        <div class="field-wrap">
                                            <div class="wrapper"><input id="funnel-name" type="text" name="funnel_name" required="required" class="form-control"><!----></div>
                                            <!---->
                                        </div><!----><!----></div>
                                    <div class="form-group featured field-textArea"><label for="funnel-desc"><?php esc_html_e( 'Description', 'woofunnels-upstroke-one-click-upsell' ); ?><!----></label>
                                        <div class="field-wrap"><textarea id="funnel-desc" rows="3" name="funnel_desc" class="form-control"></textarea>
                                            <!----></div>
                                        <!----><!----></div>
                                </fieldset>
                            </div>
                        </div>
                        <fieldset>
                            <div class="wfocu_form_submit wfocu_swl_btn">
                                <input type="hidden" name="_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wfocu_update_funnel' ) ); ?>"/>
								<button data-iziModal-close type="submit" class="wf_cancel_btn wfocu_btn" value="cancel"><?php esc_html_e( 'Cancel', 'woofunnels-upstroke-one-click-upsell' ); ?></button>
                                <button type="submit" class="wfocu_btn_primary wfocu_btn" value="add_new"><?php esc_html_e( 'Update', 'woofunnels-upstroke-one-click-upsell' ); ?></button>
                            </div>
                            <div class="wfocu_form_response">

                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div style="display: none" id="modal-global-settings_success"></div>
</div>
