<?php
defined( 'ABSPATH' ) || exit;
?>

<div id="customize-outer-theme-controls">
    <ul class="customize-outer-pane-parent"></ul>
    <ul class="customize-pane-child accordion-section-content accordion-section control-section control-section-outer open" id="sub-accordion-section-publish_settings">
        <li id="customize-control-changeset_status" class="customize-control customize-control-radio" style="display: list-item;">
            <label for="customize-selected-changeset-status-control-input-67" class="customize-control-title"><?php _e( 'Save As New Preset', 'woofunnels-upstroke-one-click-upsell' ) ?></label>
            <div class="customize-control-notifications-container" style="display: none;">
                <ul></ul>
            </div>
        </li>

        <li id="customize-control-changeset_preview_link" class="customize-control customize-control-undefined has-notifications">
            <div class="wfocu_template_name_container">
                <label for="wfocu-save-preset"><?php _e( 'You can save existing offer data (text, styles etc) as a preset so that can be applied to other offers.', 'woofunnels-upstroke-one-click-upsell' ) ?></label>
                <input type="text" placeholder="Preset Name" id="wfocu_template_name" style="text-indent: 0px; color: #000; box-sizing: border-box;  margin-left: 0px;">
            </div>
            <p class="description customize-control-description"><?php _e( 'Note: All the settings except Product setting will be saved as a preset.', 'woofunnels-upstroke-one-click-upsell');?></p>
            <br>
            <div class="wfocu_template_save_button_container">


                <button type="button" id="save_wfocu_design" class="button button-primary wfocu_customizer_btn_save_preset"><?php _e( 'Save', 'woofunnels-upstroke-one-click-upsell' ) ?></button>
                <span class="wfocu-ajax-loader hide"><img src="<?php echo admin_url( 'images/spinner.gif' ); ?>"></span>
                <p class="wfocu-save-preset hide"><?php _e( 'Preset has been saved successfully', 'woofunnels-upstroke-one-click-upsell' ); ?></p>
            </div>
        </li>
        <li id="wfocu_template_holder" class="customize-control customize-control-radio" style="display: list-item;">
			<?php
			$template_names = get_option( 'wfocu_template_names', [] );
			if ( count( $template_names ) > 0 ) {
				?>
                <label for="apply-saved-preset-style" class="customize-control-title wfocu_av_template"><?php _e( 'Apply Preset', 'woofunnels-upstroke-one-click-upsell' ) ?></label>
				<?php
				foreach ( $template_names as $template_slug => $template ) {
					?>
                    <span class="customize-inside-control-row wfocu_template_holder">
					<input type="radio" value="<?php echo $template_slug; ?>" name="wfocu_save_templates" id="wfocu_save_templates_<?php echo $template_slug ?>" class="wfocu_template" data-customize-setting-key-link="default">
					<label for="wfocu_save_templates_<?php echo $template_slug ?>"><?php echo $template['name']; ?></label>
                        <a href="javascript:void(0);" class="wfocu_delete_template" data-slug="<?php echo $template_slug ?>"><?php _e( 'Delete', 'woofunnels-upstroke-one-click-upsell' ) ?></a>
                        <span class="wfocu-ajax-delete-loader hide"><img src="<?php echo admin_url( 'images/spinner.gif' ); ?>"></span>
		        	</span>

					<?php
				}
			} ?>
        </li>
        <p class="wfocu-delete-preset hide"><?php _e( 'Preset has been deleted successfully', 'woofunnels-upstroke-one-click-upsell' ); ?></p>
        <li>
            <label for="wfocu-apply-preset" class="wfocu_apply_preset_ins" style="display: none"><?php _e( 'Applying this preset would override the current offer data with the preset data.', 'woofunnels-upstroke-one-click-upsell' ) ?></label>
            <button type="button" class="wfocu_apply_template button" style="display: none"><?php _e( 'Apply Preset', 'woofunnels-upstroke-one-click-upsell' ) ?></button>
            <span class="wfocu-ajax-apply-preset-loader hide"><img src="<?php echo admin_url( 'images/spinner.gif' ); ?>"></span>
        </li>
    </ul>
</div>

