<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<style>
    .wfob_wrap_r {
        float: right;
        width: 100%;

    }

    .wfob_funnel_setting_inner .product_settings {
        padding: 25px;
    }

    .wfob_funnel_setting_inner .product_settings:before, .product_settings:after {
        content: "";
        display: table;
    }

    .wfob_funnel_setting_inner .product_settings:after {
        clear: both;
    }

    .wfob_funnel_setting_inner .product_settings .product_settings_title {
        font-size: 16px;
        line-height: 27px;
        border-bottom: 1px solid #dedede;
        font-weight: bold;
        padding: 8px 12px 15px;
        margin: 0px 0 5px 0px;
    }

    .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour {
        margin-top: 12px;
        padding: 0 15px 20px 15px;
    }

    .wfob_funnel_setting_inner .product_settings_checkout_behavior_heading {
        float: left;
        width: 9%;
        display: block;
        margin-right: 3%;
        font-size: 14px;
        font-weight: 500;
        font-style: italic;
    }

    .wfob_funnel_setting_inner .product_settings_checkout_behavior_setting {
        width: 88%;
        float: left;
    }

    .product_settings_checkout_behavior_setting div.wfob_radio_option {
        font-size: 13px;
        line-height: 1.5;
        margin: 1em 0;
    }

    #swap_product_search {
        padding-left: 15px;
    }

    /* Product Seclection style */
    #wfob_product_settings .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour:after,
    #wfob_product_settings .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour:before {
        content: '';
        display: block;
    }

    #wfob_product_settings .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour:after {
        clear: both;
    }

    #wfob_product_settings .product_settings_checkout_behavior_setting div.wfob_radio_option {
        margin: 0 0 1em 0;
    }

    #wfob_product_settings #swap_product_search .wfob_pro_label_wrap {
        margin-bottom: 1em;
    }

    #wfob_product_settings .wfob_select_pro_wrap label {
        font-size: 13px;
        line-height: 1.4em;
        color: #3c434a;
        display: block;
        margin-bottom: 7px;
        cursor: default;
    }


    #wfob_product_settings .multiselect__tags {
        min-height: 44px;
        border-radius: 0px;
        box-shadow: none;
        font-size: 16px;
        line-height: 24px;
        padding: 9px 40px 7px 12px;
        background-color: #fff;
        border: 1px solid #d5d5d5;
        display: block;
    }

    #wfob_product_settings input#swap_product_ajax {
        border: none;
        box-shadow: none;
        font-size: 13px;
        margin: 0;
        padding: 0;
        position: relative;
        background: #fff;
        width: 100%;
        transition: border .1s ease;
        box-sizing: border-box;
        margin-bottom: 0 !important;
        border-radius: 0px;
        line-height: 30px;
        padding: 0;
        background-color: #fff;
        color: #444444;
        font-weight: normal;
        resize: none;
        min-height: 30px;
    }

    #wfob_product_settings .multiselect__option--highlight {

        color: #000 !important;
    }

    /* run time testing purpose */
    b {
        content: '';
        display: block;
    }

    .wfob_position_wrap:after {
    }

    .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour:after, .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour:before {
        content: '';
        display: block;
    }

    .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour:after {
        clear: both;
    }

    .wfob_funnel_setting_inner .product_settings .product_settings_checkout_behaviour {
        padding-bottom: 0;
    }

    .wfob_position_wrap {
        margin-bottom: 28px;
    }

    .wfob_position_wrap:last-child {
        margin: 0;
    }

    div#wfob_positions label span {
        font-size: 13px;
        line-height: 1.4em;
        color: #3c434a;
        display: block;
        margin-bottom: 7px;
        cursor: default;
    }

    div#wfob_positions {
        text-align: left;
    }

    div#wfob_positions .hint {
        text-align: left;
        display: block;
        padding: 0;
    }

    div#wfob_positions select {
        min-height: 36px;
        border-radius: 0px;
        box-shadow: none;
        font-size: 13px;
        line-height: 1.5;
        padding: 9px 40px 7px 12px;
        background-color: #fff;
        border: 1px solid #d5d5d5;
        display: block;
        width: 100%;
        max-width: 100%;
        color: #353030;
    }

    #wfob_product_settings .setting_save_buttons.wfob_form_submit {
        display: inline-block;
    }

    #wfob_product_settings .setting_save_buttons.wfob_form_submit .spinner {
        float: right;
        margin-top: 8px;
        margin-bottom: 8px;
    }

    .wfob_funnel_setting_inner #wfob_positions .product_settings_checkout_behavior_heading {
        line-height: 44px;
    }
</style>

<div class="wfob_funnel_setting" id="wfob_product_container">
    <div class="wfob_funnel_setting_inner">
        <div class="wfob_product_container" v-if="!isEmpty()">
            <div class="wfob_fsetting_table_head" v-if="!isEmpty()">
                <div class="wfob_fsetting_table_head_in">
                    <div class="wfob_fsetting_table_title "><?php echo __( '<strong>Products</strong>', 'woofunnels-order-bump' ); ?>
                    </div>
                    <div class="setting_save_buttons wfob_form_submit">
                        <span class="wfob_save_funnel_setting_ajax_loader wfob_spinner spinner spinner"></span>
                        <button class="wfob_btn wfob_btn_primary" v-on:click="save_products()"><?php _e( 'Save Changes', 'woofunnels-order-bump' ); ?></button>
                    </div>
                </div>
            </div>

            <div class="products_container" v-if="!isEmpty()">
				<?php include_once __DIR__ . '/products/table.php'; ?>
            </div>
            <div class="wfob_clear"></div>
            <div class="product_settings_wrap" id="wfob_product_settings">
                <div class="product_settings_inner_container">
                    <div class="product_settings" style="background: #fff">
                        <div class="wfob_position_wrap">
                            <div class="product_settings_title"><?php _e( 'Order Bump Settings' ); ?></div>
                            <div class="product_settings_checkout_behaviour">
                                <div class="product_settings_checkout_behavior_heading">
                                    <span><?php _e( 'Behaviour', 'woofunnels-aero-checkout' ); ?></span>
                                </div>
                                <div class="product_settings_checkout_behavior_setting">
                                    <div class="wfob_radio_option">
                                        <label>
                                            <input type="radio" v-model="bump_action_type" value="1"><?php _e( 'Add Order Bumps to Cart Items', 'woofunnels-aero-checkout' ); ?>
                                        </label>
                                    </div>

                                    <div class="wfob_radio_option">
                                        <label>
                                            <input type="radio" v-model="bump_action_type" value="2"><?php _e( 'Replace Order Bumps with a Cart Item (used for upgrades)', 'woofunnels-aero-checkout' ); ?>
                                        </label>
                                    </div>
                                    <div id="swap_product_search" v-if="2==bump_action_type">

                                        <div class="wfob_radio_option">
                                            <label>
                                                <input type="radio" v-model="bump_replace_type" value="specific"><?php _e( 'Replace Specific Product(s)', 'woofunnels-aero-checkout' ); ?>
                                            </label>
                                        </div>
                                        <div class="wfob_pro_label_wrap wfob_clearfix" v-if="bump_replace_type=='specific'">
                                            <div class="wfob_select_pro_wrap"><label><?php _e( 'Select Product(s)', 'woofunnels-order-bump' ); ?></label></div>
                                            <multiselect v-model="selected_replace_product" id="swap_product_ajax" label="product" track-by="product" placeholder="Type to search" open-direction="top" :options="replace_product" :multiple="true" :searchable="true" :loading="isLoading" :internal-search="true" :clear-on-select="false" :close-on-select="true" :options-limit="10" :limit="3" :max-height="600" :show-no-results="true" :hide-selected="true" @search-change="asyncFind">
                                                <template slot="clear" slot-scope="props">
                                                </template>
                                                <span slot="noResult"><?php echo __( 'Oops! No elements found. Consider changing the search query.', 'woofunnels-order-bump' ); ?></span>
                                            </multiselect>
                                        </div>

                                        <div class="wfob_radio_option">
                                            <label>
                                                <input type="radio" v-model="bump_replace_type" value="all"><?php _e( 'Replace All Products', 'woofunnels-aero-checkout' ); ?>
                                            </label>
                                        </div>


                                    </div>

                                </div>
                            </div>
                        </div>
						<?php
						$bump_position = WFOB_Common::get_bump_position();
						?>
                        <div class="wfob_position_wrap">
                            <div class="product_settings_checkout_behaviour" id="wfob_positions">
                                <div class="product_settings_checkout_behavior_heading">
                                    <span><?php _e( 'Display Position', 'woofunnels-aero-checkout' ); ?></span>
                                </div>
                                <div class="product_settings_checkout_behavior_setting">
                                    <div class="form-group valid wfob_pointer_animation field-select">
                                        <div class="field-wrap">
                                            <select v-model="bump_position_hooks" id="order_bump_position_hooks" class="form-control" name="order_bump_position_hooks">
												<?php
												foreach ( $bump_position as $bump_key => $bump_val ) {
													?>
                                                    <option value="<?php echo $bump_val['id']; ?>"><?php echo $bump_val['name']; ?></option>
													<?php
												}
												?>
                                            </select>
                                        </div>
                                    </div>
                                    <div v-if="'woocommerce_checkout_order_review_below_order_summary'==bump_position_hooks || 'woocommerce_checkout_order_review_above_order_summary'==bump_position_hooks">
                                        <b class="wfob-note"><?php _e( 'Note: Order Summary field should be present', 'woofunnels-order-bump' ) ?></b></div>
                                    <div v-if="'wfacp_below_mini_cart_items'==bump_position_hooks">
                                        <b class="wfob-note"><?php _e( 'Note: Mini Cart widget should be present', 'woofunnels-order-bump' ) ?></b></div>

                                </div>
                            </div>
                        </div>

                        <div class="setting_save_buttons wfob_form_submit">
                            <button class="wfob_btn wfob_btn_primary" v-on:click="save_products()"><?php _e( 'Save changes', 'woofunnels-order-bump' ); ?></button>
                            <span class="wfob_save_funnel_setting_ajax_loader wfob_spinner spinner spinner"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wfob_clear"></div>
        </div>
		<?php include_once __DIR__ . '/products/add-new.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/products/models.php'; ?>
