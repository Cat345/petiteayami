<?php
defined( 'ABSPATH' ) || exit;
$_localization        = WFACP_Common::get_builder_localization();
$switcher_settings    = WFACP_Common::get_product_switcher_data( WFACP_Common::get_id() );
$product_settings     = WFACP_Common::get_page_product_settings( WFACP_Common::get_id() );
$add_to_cart_settings = $product_settings['add_to_cart_setting'];
apply_filters( 'wfacp_enable_product_switcher_deletion_item', false );
$enable_delete_options = WFACP_Common::delete_option_enable_in_product_switcher();

?>
<div id="product_switching">
    <div class="wfacp_tabs" v-if="wfacp.tools.ol(wfacp_data.products)>0">
        <ul>
            <li><a data-tag="#product_switching_general_setting" class="wfacp_tab_link activelink"><?php _e( 'General', 'woofunnels-aero-checkout' ); ?></a></li>
            <li><a data-tag="#product_switching_additional_information" class="wfacp_tab_link"><?php _e( 'Description', 'woofunnels-aero-checkout' ); ?></a></li>
            <li><a data-tag="#product_switching_advanced_setting" class="wfacp_tab_link"><?php _e( 'Advanced', 'woofunnels-aero-checkout' ); ?></a></li>
            <li><a data-tag="#product_switching_templates" class="wfacp_tab_link" style="display: none;"><?php _e( 'Templates', 'woofunnels-aero-checkout' ); ?></a></li>
        </ul>
        <div id="product_switching_general_setting" class="vue-form-generator wfacp_tab_container">
			<?php do_action( 'wfacp_product_switcher_before_general_setting' ); ?>
            <div class="product_switching_fields">
                <fieldset>
                    <div class="form-group required field-input">
                        <label for="label">{{wfacp_localization.fields.label_field_label}}</label>
                        <div class="field-wrap">
                            <div class="wrapper">
                                <input id="label" type="text" required="required" class="form-control" v-model="model.label" placeholder="Label">
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
			<?php
			$field_type = '';
			if ( $add_to_cart_settings == '2' ) {
				$field_type = 'radio';

			} elseif ( $add_to_cart_settings == '3' ) {
				$field_type = 'checkbox';
			}

			?>
            <div class="product_switching_products">
                <div class="product_switching_table_heading">
					<?php
					if ( '' !== $field_type ) {
						?>
                        <div class="product_switching_table_heading_col form-group"><?php _e( 'Default', 'woofunnels-aero-checkout' ); ?></div>
						<?php
					}
					?>
                    <div class="product_switching_table_heading_col form-group wfacp_pr_col_style2"><?php _e( 'Product', 'woocommerce' ); ?></div>
                    <div class="product_switching_table_heading_col form-group wfacp_pr_col_style2"><?php _e( 'Title', 'woofunnels-aero-checkout' ); ?></div>
					<?php
					if ( '' == $field_type && true == $enable_delete_options ) {
						?>
                        <div class="product_switching_table_heading_col form-group"><?php _e( 'Enable Delete', 'woofunnels-aero-checkout' ); ?></div>
					<?php } ?>

                </div>
                <div v-if="wfacp.tools.ol(products)>0" v-for="(product,index) in products" data-index="index" class="wfacp_product_switching_table_col_wrap">
                    <div class="wfacp_vue_forms product_switching_table_row <?php echo '' == $field_type ? 'product_switching_table_row_2_col' : ''; ?>">


						<?php
						if ( '' !== $field_type ) {
							?>
                            <div class=" product_switching_table_row_col product_switching_table_row_default wfacp_pr_col_style1">
                                <input type="<?php echo $field_type; ?>" name="default_product" v-model="default_products" v-bind:value="index">
                            </div>
						<?php } ?>

                        <div class="form-group product_switching_table_row_col product_switching_table_row_default wfacp_pr_col_style2 dis_flex">
                            <div class="old_product_name"> {{product.old_title}}</div>
                        </div>
                        <div class="form-group product_switching_table_row_col product_switching_table_row_default wfacp_pr_col_style2 wfacp_multi_input">
                            <input type="text" v-model="product.title" placeholder="Title" class="wfacp_product_title" v-bind:val="product.title">
                            <input type="text" v-model="product.you_save_text" placeholder="You Save Text">

                        </div>
						<?php
						if ( '' == $field_type && true == $enable_delete_options ) {
							?>
                            <div class="form-group product_switching_table_row_col product_switching_table_row_default">
                                <input type="checkbox" v-model="product.enable_delete" :disabled="!product_settings.enable_delete_item">
                            </div>
						<?php } ?>
                    </div>
                </div>

                <div style="margin: 8px 0px;display: inline-block;">{{wfacp_localization.fields.product_you_save_merge_tags}}</div>
                <div class="wfacp_ps_conditional_wrap">

                    <div class="wfacp_product_switcher_delete_options">
                        <input type="checkbox" v-model="product_settings.enable_delete_item" id="product_switcher_enable_delete_item">
                        <label for="product_switcher_enable_delete_item"><?php _e( 'Enable Product Deletion', 'woofunnels-aero-checkout' ); ?></label>

                    </div>
                    <div class="wfacp_product_switcher_delete_options">
                        <input type="checkbox" v-model="product_settings.enable_custom_name_in_order_summary" id="wfacp_product_switcher_enable_custom_name_in_order_summary">
                        <label for="wfacp_product_switcher_enable_custom_name_in_order_summary">
							<?php _e( 'Enable Custom Product Name In Order Details', 'woofunnels-aero-checkout' ); ?></label>

                        <p><?php _e( 'This option will allow you to view the product custom name on Thank you page, Customer Email & Admin Order Detail page', 'woofunnels-aero-checkout' ); ?></p>
                    </div>

                </div>
				<?php do_action( 'wfacp_product_switcher_after_general_setting' ); ?>
            </div>
        </div>
        <div id="product_switching_additional_information" class="wfacp_tab_container wfacp_tab_hide">
            <div class="wfacp_product_switcher_hide_additional_information wfacp_pr_sec">
                <p>
					<?php _e( 'Use this section to show per product custom description when the product is selected.', 'woofunnels-aero-checkout' ) ?><br/>
					<?php _e( 'Note: This feature will work if you are using product specific order forms.', 'woofunnels-aero-checkout' ) ?>
                </p>
                <input type="checkbox" v-model="product_settings.is_hide_additional_information" id="product_settings_is_hide_additional_information">
                <label for="product_settings_is_hide_additional_information"><?php _e( 'Hide Custom Product Description', 'woofunnels-aero-checkout' ); ?></label>
            </div>


            <div class="wfacp_product_switcher_additional_information_heading wfacp_pr_sec wfacp_vue_forms">
                <div class=" form-group">
                    <label type="text"><?php _e( 'Title', 'woofunnels-aero-checkout' ); ?></label>
                    <input type="text" v-model="product_settings.additional_information_title">
                </div>
            </div>

            <div class="product_switching_table_heading">
                <div class="product_switching_table_heading_col form-group wfacp_pr_col_style_first_half"><?php _e( 'Product', 'woocommerce' ); ?></div>
                <div class="product_switching_table_heading_col form-group wfacp_pr_col_style_two_third"><?php _e( 'Custom Details', 'woofunnels-aero-checkout' ); ?></div>
            </div>

            <div v-if="wfacp.tools.ol(products)>0" v-for="(product,index) in products" data-index="index" class="wfacp_product_switching_table_col_wrap">
                <div class="wfacp_whats_include_wrap">
                    <div class="wfacp_vue_forms product_switching_table_row <?php echo '' == $field_type ? 'product_switching_table_row_2_col' : ''; ?>">
                        <div class="form-group product_switching_table_row_col product_switching_table_row_default wfacp_pr_col_style_first_half">
                            <div v-if="product.title!=''">
                                {{product.title}}
                            </div>
                            <div v-else>
                                {{product.old_title}}
                            </div>
                        </div>
                        <div class="form-group product_switching_table_row_col product_switching_table_row_default wfacp_pr_col_style_two_third">
                            <textarea type="text" v-bind:id="'whats_included_'+index" cols="10" v-bind:product_id='index' rows="5" v-model="product.whats_included"></textarea>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div id="product_switching_advanced_setting" class="wfacp_tab_container wfacp_tab_hide">
            <div class="pro_mb">
                <h3><?php _e( 'Best Value Tag', 'woofunnels-aero-checkout' ) ?></h3>
                <div class="product_switching_advanced_field wfacp_pr_sec">
                    <input type="checkbox" name="hide_best_value" v-model="product_settings.hide_best_value" id="hide_best_value">
                    <label for="hide_best_value"><?php _e( 'Hide Best Value Tag', 'woofunnels-aero-checkout' ) ?></label>
                </div>
            </div>
            <div class="pro_other_info wfacp_vue_forms" v-if="!product_settings.hide_best_value">
                <div class="wfacp_pr_sec form-group">
                    <label><?php _e( 'Apply on Product', 'woofunnels-aero-checkout' ) ?></label>

                    <select v-model="product_settings.best_value_product">
                        <option value=""><?php _e( 'Select Product' ) ?></option>
                        <option v-for="(product,index) in products" v-bind:value="index">{{product.title}}</option>
                    </select>
                </div>
                <div class="wfacp_pr_sec form-group">
                    <label><?php _e( 'Label', 'woofunnels-aero-checkout' ) ?></label>
                    <input type="text" placeholder="Best Value Product" v-model="product_settings.best_value_text">
                </div>
                <div class="wfacp_pr_sec form-group">
                    <label><?php _e( 'Position', 'woofunnels-aero-checkout' ) ?></label>
                    <select v-model="product_settings.best_value_position">
                        <option value=""><?php _e( 'Default', 'woofunnels-aero-checkout' ) ?></option>
                        <option value="above"><?php _e( 'Above product title', 'woofunnels-aero-checkout' ) ?></option>
                        <option value="below"><?php _e( 'Below product title', 'woofunnels-aero-checkout' ) ?></option>
                        <option value="top_left_corner"><?php _e( 'Top left corner', 'woofunnels-aero-checkout' ) ?></option>
                        <option value="top_right_corner"><?php _e( 'Top right corner', 'woofunnels-aero-checkout' ) ?></option>
                    </select>
                </div>
            </div>
            <div class="pro_checkbox_wrap pro_mb">
                <h3><?php _e( 'Other', 'woofunnels-aero-checkout' ) ?></h3>
                <div class="product_switching_advanced_field">
                    <input type="checkbox" name="hide_quantity_switcher" v-model="product_settings.hide_quantity_switcher" id="hide_quantity_switcher">
                    <label for="hide_quantity_switcher"><?php echo $_localization['settings']['product_switching']['hide_quantity_switcher']; ?></label>
                </div>
                <div class="product_switching_advanced_field">
                    <input type="checkbox" name="hide_quick_view" v-model="product_settings.hide_quick_view" id="hide_quick_view">
                    <label for="hide_quick_view"><?php echo $_localization['settings']['product_switching']['hide_quick_view']; ?></label>
                </div>

                <div class="product_switching_advanced_field">
                    <input type="checkbox" name="hide_quick_view" v-model="product_settings.hide_product_image" id="hide_product_image">
                    <label for="hide_product_image"><?php echo $_localization['settings']['product_switching']['hide_product_image']; ?></label>
                </div>

            </div>
        </div>
        <div id="product_switching_templates" class="wfacp_tab_container wfacp_tab_hide" style="display: none;">

            <div class="design_container">
                <div class="template_wrapper">
                    <div class="wfacp_template_box" v-for="(template,slug) in wfacp_data.product_switcher_templates" v-on:data-slug="slug" v-bind:data-select="(product_settings.product_switcher_template==slug)?'selected':''" style="max-width: 100px">
                        <input type="radio" name="product_switcher_template" v-model="product_settings.product_switcher_template" v-bind:id="'wfacp_product_switcher_template_'+slug" v-bind:value="slug">
                        <label v-bind:for="'wfacp_product_switcher_template_'+slug"><img v-bind:src="template.thumbnail"></label>
                    </div>
                    <div style="clear:both"></div>
                </div>
            </div>
        </div>

    </div>
    <div class="wfacp_tabs" v-else="">
        <div class="wfacp_welcome_card">
            <div class="wfacp_clear_30"></div>
            <div class="wfacp_clear_30"></div>
            <div class="wfacp_welc_icon">
                <span class="wfacp_product_h wfacp_product_text_align"> <?php _e( 'Oops! no products found', 'woofunnels-aero-checkout' ) ?> </span>
            </div>

            <div class="wfacp_clear_10"></div>
            <div class="wfacp_product_p"><?php _e( 'To edit this field  please add atleast one product ', 'woofunnels-aero-checkout' ) ?></div>
            <div class="wfacp_clear_10"></div>
            <div class="wfacp_clear_30"></div>
            <div class="wfacp_btns_welcome_card">
                <a href="<?php echo add_query_arg( [
					'page'     => 'wfacp',
					'wfacp_id' => WFACP_Common::get_id(),
					'section'  => 'product'
				], admin_url( 'admin.php' ) ) ?>" class="wfacp_btn wfacp_btn_primary"> <?php _e( 'Add Product', 'woofunnels-aero-checkout' ) ?> </a>
                <div class="wfacp_clear_20"></div>
                <div class="wfacp_clear_20"></div>
            </div>
        </div>

    </div>
</div>
