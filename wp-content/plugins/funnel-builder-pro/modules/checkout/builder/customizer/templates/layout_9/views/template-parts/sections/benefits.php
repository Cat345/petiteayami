<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Template_Common
 */


$hide_list_icon = $data['benefit_content']['hide_list_icon'];

$list_icon                 = $data['benefit_content']['list_icon'];
$display_list_bold_heading = $data['benefit_content']['display_list_bold_heading'];

$rbox_border_type = '';
if ( isset( $data['advance_setting']['rbox_border_type'] ) && $data['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $data['advance_setting']['rbox_border_type'];
}

?>


<!--   Benefit Section-->
<div class="<?php echo $section_key . ' ' . $rbox_border_type; ?> div_wrap_sec">
    <div class="wfacp-list-panel">
		<?php
		if ( ( isset( $data['heading_section']['heading'] ) && $data['heading_section']['heading'] != '' ) && ( isset( $data['heading_section']['enable_heading'] ) && $data['heading_section']['enable_heading'] == true ) ) {
			$align_text         = $data['heading_section']['heading_talign'];
			$font_weight        = $data['heading_section']['heading_font_weight'];
			$heading_fs_desktop = $data['heading_section']['heading_fs']['desktop'];
			$heading_fs_tablet  = $data['heading_section']['heading_fs']['tablet'];
			$heading_fs_mobile  = $data['heading_section']['heading_fs']['mobile'];
			?>
            <h2 class="wfacp-list-title wfacp_section_title <?php echo $align_text . ' ' . $font_weight; ?>">
				<?php echo $data['heading_section']['heading']; ?>
            </h2>
			<?php
		}
		if ( isset( $data['benefit_content'] ) && is_array( $data['benefit_content'] ) && count( $data['benefit_content'] ) > 0 ) {
			?>

			<?php
		}
		?>


        <ul class="wfacp-sidebar-list clearfix">

			<?php

			foreach ( $data['benefit_content']['icon_text'] as $key_list => $val ) {

				?>
                <li>

					<?php

					if ( isset( $hide_list_icon ) && $hide_list_icon != 1 ) {

						echo sprintf( '<span class="wfacp-icon-list %s pull-left "></span>', $list_icon );
					}
					?>


                    <div class="wfacp-sidebar-list-txt">
						<?php
						$list_heading = $val['heading'];
						$list_message = $val['message'];

						$classWeight = '';
						if ( $display_list_bold_heading != 1 ) {
							$classWeight = 'wfacp-normal';
						}

						if ( isset( $list_heading ) && $list_heading != '' && $data['benefit_content']['display_list_heading'] == 1 ) {
							echo "<span class='loop_head_sec $classWeight'>$list_heading</span>";
						}


						if ( isset( $data['benefit_content']['show_list_description'] ) && $data['benefit_content']['show_list_description'] == 1 ) {
							echo apply_filters( 'wfacp_the_content', $list_message );
						}


						?>
                    </div>

                </li>
				<?php
			}
			?>

        </ul>
    </div>

</div>
<!--   Benefit Section Closed-->
