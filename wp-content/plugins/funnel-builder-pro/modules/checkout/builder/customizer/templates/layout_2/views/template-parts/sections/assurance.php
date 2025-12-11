<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Template_Common
 */


$hide_title = '';
if ( isset( $data['assurance_data']['hide_title'] ) ) {
	$hide_title = $data['assurance_data']['hide_title'];
}
$align_text  = $data['heading_section']['heading_talign'];
$font_weight = $data['heading_section']['heading_font_weight'];

$rbox_border_type = '';
if ( isset( $data['advance_setting']['rbox_border_type'] ) && $data['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $data['advance_setting']['rbox_border_type'];
}

$borderEnableClass = '';
$enable_divider    = $data['assurance_data']['enable_divider'];


if ( isset( $enable_divider ) && $enable_divider != '' ) {
	$borderEnableClass = 'wfacp_enable_border';
}
?>
<div class="<?php echo $section_key . ' ' . $rbox_border_type; ?> div_wrap_sec">

    <!--   Assurance-->
	<?php
	if ( is_array( $data['assurance_data']['list'] ) && count( $data['assurance_data']['list'] ) > 0 ) {

		$section_mwidget_listw = $data['assurance_data']['section_mwidget_listw'];
		foreach ( $data['assurance_data']['list'] as $key_list => $val ) {

			?>

            <div class="wfacp-information-container">
                <div class="wfacp-comm-inner-inf <?php echo $borderEnableClass; ?>">
					<?php
					if ( $hide_title != 1 && isset( $val['mwidget_heading'] ) ) {
						?>
                        <h2 class=" wfacp-list-title loop_head_sec wfacp_section_title  <?php echo $align_text . ' ' . $font_weight; ?>"><?php echo $val['mwidget_heading']; ?></h2>

						<?php
					}

					?>
                    <div class="wfacp_mwidget_wrap wfacp_clearfix">
						<?php
						$wfacp_img_cls = '';
						if ( isset( $section_mwidget_listw ) && $section_mwidget_listw == 1 ) {

							$mwidget_image = $val['mwidget_image'];

							if ( is_numeric( $mwidget_image ) ) {
								$mwidget_image_src = wp_get_attachment_image_src( $mwidget_image, 'full' );
								$mwidget_image     = $mwidget_image_src[0];

							}
							if ( $mwidget_image != '' ) {
								$wfacp_img_cls = 'wfacp_assurance_active';
								?>
                                <div class="wfacp-assurance_img">
                                    <img src="<?php echo $mwidget_image; ?>" alt="">
                                </div>
								<?php
							}
						}
						?>

						<?php
						if ( isset( $val['mwidget_content'] ) ) {
							$content = $val['mwidget_content'];
							?>
                            <div class="wfacp-sidebar-text <?php echo $wfacp_img_cls; ?>">
								<?php echo apply_filters( 'wfacp_the_content', $content ); ?>
                            </div>
							<?php
						}
						?>
                    </div>


                </div>
            </div>
			<?php
		}
	}
	?>
    <!--   Assurance Closed -->
</div>





