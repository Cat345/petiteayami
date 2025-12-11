<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * @var $this WFOB_Admin;
 */

$bump_id = $this->get_bump_id();

if ( false === $bump_id ) {
	wp_die( __( 'Something is wrong with the URL, ID is required', 'woofunnels-order-bump' ) );
}

$sidebar_menu      = WFOB_Common::get_sidebar_menu();
$bump_sticky_line  = __( 'OrderBumps', 'woofunnels-order-bump' );
$bump_sticky_title = '';
$bump_onboarding   = true;
if ( isset( $bump_id ) && ! empty( $bump_id ) ) {
	$bump_sticky_title = get_the_title( $bump_id );

	$bump_onboarding_status = get_post_meta( $bump_id, '_wfob_is_rules_saved', true );

	if ( 'yes' == $bump_onboarding_status ) {
		$bump_onboarding = false;
	}
}

$status  = get_post_status( $bump_id );

?>
	<style>
        .wfob_loader {
            position: absolute;
            width: calc(100% - 40px);
            text-align: center;
            background: #fff;
            z-index: 100;
            min-height: 1100px;
        }

        .wfob_loader .spinner {
            visibility: visible;
            margin: auto;
            width: 50px;
            float: none;
            margin-top: 25%;
        }
	</style>

<?php include_once __DIR__ . '/global/model.php'; ?>
<?php

$header_nav_data = array();
if ( is_array( $sidebar_menu ) && count( $sidebar_menu ) > 0 ) {
	ksort( $sidebar_menu );
	foreach ( $sidebar_menu as $step ) {
		$href = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( [
			'page'    => 'wfob',
			'section' => $step['key'],
			'wfob_id' => $bump_id,
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
            <span class="bwfan-tag-rounded bwfan_ml_12 <?php echo 'publish' == $status ? 'clr-green' : 'clr-orange'; ?>">
                <span class="bwfan-funnel-status"><?php echo 'publish' == $status ? 'Published' : 'Draft'; ?></span>
                
                <?php echo file_get_contents(  plugin_dir_path( WFOB_PLUGIN_FILE ) . 'admin/assets/img/icons/arrow-down.svg'  ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
        </div>
        <div class="wffn-ellipsis-menu-dropdown">
            <a data-izimodal-open="#modal-add-bump" data-izimodal-transitionin="fadeInDown" href="javascript:void(0);" class="bwf_edt wffn-ellipsis-menu-item"><?php esc_html_e( 'Edit' ) ?></a>
            <div class="wf_funnel_card_switch">
                <label class="bump_state_toggle wfob_toggle_btn wffn-ellipsis-menu-item">
                    <span class="bwfan-status-toggle"><?php echo 'draft' == $status ? 'Publish' : 'Draft'; ?></span>
                    <input name="offer_state" id="state<?php echo $bump_id; ?>" data-id="<?php echo $bump_id; ?>" type="checkbox" class="wfob-tgl wfob-tgl-ios wfob_checkout_page_status" <?php echo ( $status == 'publish' ) ? 'checked="checked"' : ''; ?> />
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
	$header_ins->set_level_2_side_navigation_active( $this->get_bump_section() ); // active navigation

	echo $header_ins->render();
} else {
	BWF_Admin_Breadcrumbs::render_sticky_bar();
}
?>
	<div class="wfob_body">
		<?php if ( ! class_exists( 'WFFN_Header' ) ) : ?>
			<div class="wfob_fixed_header">
				<div class="wfob_box_size wfob_table">
					<div class="bwf_breadcrumb">
						<div class="bwf_before_bre">
							<div class="wfob_head_mr" data-status="<?php echo ( $status !== 'publish' ) ? 'sandbox' : 'live'; ?>">
								<div class="bump_state_toggle wfob_toggle_btn">
									<input name="offer_state" id="state<?php echo $bump_id; ?>" data-id="<?php echo $bump_id; ?>" type="checkbox" class="wfob-tgl wfob-tgl-ios wfob_checkout_page_status" <?php echo ( $status == 'publish' ) ? 'checked="checked"' : ''; ?> />
									<label for="state<?php echo $bump_id; ?>" class="wfob-tgl-btn wfob-tgl-btn-small"></label>
								</div>
								<span class="wfob_head_bump_state_on" <?php echo ( $status !== 'publish' ) ? ' style="display:none"' : ''; ?>><?php _e( 'Live', 'woofunnels-order-bump' ); ?></span>
								<span class="wfob_head_bump_state_off" <?php echo ( $status == 'publish' ) ? 'style="display:none"' : ''; ?>> <?php _e( 'Sandbox', 'woofunnels-order-bump' ); ?></span>
							</div>
						</div>
						<?php echo BWF_Admin_Breadcrumbs::render(); ?>
						<div class="bwf_after_bre">
							<div class="wfob_bump_actions_btn">
								<a class="wfob_bump_edt" href="javascript:void()" data-izimodal-open="#modal-add-bump" data-izimodal-transitionin="fadeInDown">
									<i class="dashicons dashicons-edit"></i>Edit</a>
							</div>
						</div>
					</div>
					<div style="display: none;" id="wfob_bump_description">

					</div>
				</div>
			</div>
			<?php
			if ( is_array( $sidebar_menu ) && count( $sidebar_menu ) > 0 ) {
				ksort( $sidebar_menu );
				?>

				<div class="bwf_menu_list_primary">
					<ul>

						<?php
						foreach ( $sidebar_menu as $menu ) {
							$menu_icon = ( isset( $menu['icon'] ) && ! empty( $menu['icon'] ) ) ? $menu['icon'] : 'dashicons dashicons-admin-generic';
							if ( isset( $menu['name'] ) && ! empty( $menu['name'] ) ) {

								$section_url = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
									'page'    => 'wfob',
									'section' => $menu['key'],
									'wfob_id' => $bump_id,
								), admin_url( 'admin.php' ) ) );

								$class = '';
								if ( $menu['key'] === $this->get_bump_section() ) {
									$class = 'active';
								}

								$main_url = $section_url;
								?>


								<li class="<?php echo $class ?>">
									<a href="<?php echo esc_url_raw( $main_url ) ?>">
										<?php echo esc_attr( $menu['name'] ); ?>
									</a>
								</li>
								<?php
							}
						} ?>
					</ul>
				</div>
				<?php
			}

		endif;

		$wrap_class     = '';
		$wrapperSection = $this->get_bump_section();
		if ( ! empty( $wrapperSection ) ) {
			$wrap_class = ' wfob_wrap_inner_' . $this->get_bump_section();
		}
		?>

		<div class="wfob_wrap wfob_box_size">
			<div class="wfob_loader"><span class="spinner"></span></div>
			<div class="wfob_box_size wfob_no_padd_left_right">
				<div class="wfob_wrap_inner <?php echo $wrap_class; ?>">

					<?php
					$get_keys = wp_list_pluck( $sidebar_menu, 'key' );

					/**
					 * Redirect if any unregistered section found
					 */

					/**
					 * Any registered section should also apply an action in order to show the content inside the tab
					 * like if action is 'stats' then add_action('wfob_dashboard_page_stats', __FUNCTION__);
					 */
					if ( false === has_action( 'wfob_dashboard_page_' . $this->get_bump_section() ) ) {
						include_once( $this->admin_path . '/view/section-' . $this->get_bump_section() . '.php' );
					} else {
						/**
						 * Allow other add-ons to show the content
						 */
						do_action( 'wfob_dashboard_page_' . $this->get_bump_section() );
					}

					do_action( 'wfob_bump_page', $this->get_bump_section(), $bump_id );
					?>
					<div class="wfob_clear"></div>
				</div>
			</div>
		</div>
	</div>
	<style>
		<?php include_once __DIR__ . '/global/wfob-swal-model.css'; ?>
	</style>
<?php
do_action('wfob_admin_footer');
