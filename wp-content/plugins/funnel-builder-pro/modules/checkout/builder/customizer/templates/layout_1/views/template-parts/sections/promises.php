<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Template_Common
 */


if ( ( is_array( $data ) && count( $data ) <= 0 ) || is_null( $data ) ) {
	return;
}
$default_structure_cls = 'wfacp-three-cols';
if ( isset( $data['promises_data']['select_badge_structure'] ) && $data['promises_data']['select_badge_structure'] != '' ) {
	$default_structure_cls = $data['promises_data']['select_badge_structure'];
}


if ( ! is_array( $data['promises_data'] ) && count( $data['promises_data'] ) > 0 ) {
	return;
}

$rbox_border_type = '';
if ( isset( $data['advance_setting']['rbox_border_type'] ) && $data['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $data['advance_setting']['rbox_border_type'];
}

$enable_border = 'no_border';
if ( isset( $data['promises_data']['show_divider'] ) && $data['promises_data']['show_divider'] == 1 ) {
	$enable_border = '';
}


?>
<div class="<?php echo $section_key . ' ' . $rbox_border_type; ?> div_wrap_sec" data-scrollto="<?php echo $section_key; ?>_section">
    <!--   Promises  -->
    <div class="wfacp-permission-icon clearfix">

        <!-- PRIVACY ICONS -->
        <ul>
            <!-- GUARANTEE -->
			<?php

			$count_of_icons = sizeof( $data['promises_data']['icon_text'] );
			$class_of_count = 'wfacp_odd';
			if ( $count_of_icons % 2 == 0 ) {
				$class_of_count = 'wfacp_even';
			}

			foreach ( $data['promises_data']['icon_text'] as $icon_key => $icon_val ) {
				$picon = $icon_val['promises_icon'];
				//	echo $picon;
				if ( is_numeric( $icon_val['promises_icon'] ) ) {
					$icon_src = wp_get_attachment_image_src( $picon, 'full' );
					$picon    = $icon_src[0];
				}


				?>

                <li class="wfacp-cell01 <?php echo $default_structure_cls . ' ' . $enable_border . ' ' . $class_of_count; ?> ">
                    <div class="wfacp-relative-wrapper">
						<?php

						if ( isset( $picon ) && $picon != '' ) {

							printf( '<img src=%s alt="">', $picon );
						}
						$text_alignment = '';


						if ( isset( $icon_val['promises_text'] ) && $data['promises_data']['hide_text'] != 1 ) {

							printf( '<p class="%s">%s</p>', $text_alignment, esc_js( $icon_val['promises_text'] ) );
						}
						?>


                    </div>
                </li>

				<?php
			}
			?>
            <!-- END GUARANTEE -->


        </ul>
        <!-- END PRIVACY ICONS -->
    </div>
</div>
