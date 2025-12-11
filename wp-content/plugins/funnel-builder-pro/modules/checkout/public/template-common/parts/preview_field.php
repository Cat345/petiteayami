<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
if ( false == apply_filters( 'wfacp_show_preview_field', true, $instance ) ) {
	return;
}
$count_increment = 0;

if ( 'single_step' != $step ) {
	$preview_heading    = $instance->get_preview_field_heading();
	$preview_subheading = $instance->get_preview_field_sub_heading();
	?>
    <div class="wfacp_preview_content_box wfacp-section" data-step="<?php echo $step; ?>">
		<?php
		if ( '' !== $preview_heading || '' != $preview_subheading ) {
			?>
            <div class="wfacp-comm-title none">
				<?php
				if ( '' !== $preview_heading ) {
					echo '<h2 class="wfacp_section_heading wfacp_section_title wfacp-normal">' . $preview_heading . '</h2>';
				}
				if ( '' !== $preview_subheading ) {
					echo '<h4 class="wfacp-text-left wfacp-normal">' . $preview_subheading . '</h4>';
				} ?>
            </div>
			<?php
		}
		?>
        <div class="wfacp_step_preview_wrap">
            <div class="wfacp_step_preview"></div>
        </div>

    </div>
	<?php
}
