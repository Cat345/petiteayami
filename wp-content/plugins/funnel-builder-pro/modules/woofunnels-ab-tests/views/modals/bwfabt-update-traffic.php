<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Update traffic model
 */
?>
<div id="modal_update_traffic_vue" class="bwfabt-pop-common">

    <!-- Step-1 Update traffic -->
    <div v-if="`1` == state" class=" ">
        <div class="wfabt_popup_header">

            <div class="bwfabt_row">
                <div class="wfabt_pop_title">
					<?php esc_html_e( 'Variants Traffic Weight Distribution', 'woofunnels-ab-tests' ); ?>

                </div>
                <a v-if="`1` == state" v-on:click="set_equal_traffic();" class="wfabt_equal wfabt_btn wfabt_btn_grey_border" href="javascript:void(0);">
                    <span class="animate_btn"></span><?php esc_html_e( 'Make Equal Weight', 'woofunnels-ab-tests' ); ?>
                </a>
            </div>

        </div>
    </div>
    <div class="bwfabt-loading" v-bind:class="`1`===is_initialized?'bwfabt-hide':''">
        <div class="bwfabt_row">
            <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
        </div>
    </div>

    <div class="bwfabt-update-traffic-wrap bwfabt_pop_body">
        <div v-if="`1`==state" v-bind:class="(`1`==state)?` bwfabt_disp_show`:``" class="wfabt_range_part bwfabt_disp_none">
            <div v-for="(variant, variant_id) in variants" >
                <label>{{decodeHtml(variant.title)}} <strong><span class="value">{{decodeHtml(variant.traffic)}}</span> <span>%</span> </strong></label>

                <div class="slideContainer">
                    <input type="range" min="0" max="100" v-bind:value="variant.traffic" class="incr_slider" v-bind:style="{ width: variant.traffic+'%', background: 'linear-gradient(90deg,'+getVariantColor(variant_id)+' 100%, rgb(214, 214,214) 100%)' }">
                </div>

                <div class="slideContainerright">
                    <input v-on:keyup="controlTraffic(variant_id, event)" v-on:focusout="fixTraffic(variant_id, event)" v-bind:class="0==variant.traffic?`invalid_traffic_inp`:``" type="text" v-model="variant.traffic">
                </div>
            </div>

            <div class="bwfabt_row">

                <div class="wfabt_total_amount">
                    <label><?php esc_html_e( 'Total Traffic Distribution', 'woofunnels-ab-tests' ); ?></label>
                    <div class="slideContainer"><input type="range" min="0" max="100" class="incr_slider"></div>
                    <div class="slideContainerright">
                        <input readonly v-bind:class="2==update_traffic.InValid_traffic?`invalid_traffic_inp`:``" class="wfabt_grey_trf" type="text" v-bind:value="update_traffic.total_trf_value+` %`">
                    </div>
                </div>

                <div class="bwfabt_clear_20"></div>

                <div class="bwfabt_row bwfabt_text_center">
                    <a v-bind:class="false==update_traffic.InValid_traffic?` `:`invalid_traffic`" v-on:click="update_variant_traffic()" class="updt wfabt_btn wfabt_btn_success" href="javascript:void(0)">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Update', 'woofunnels-ab-tests' ); ?>
                    </a>
                    <a v-on:click="close_update_traffic()" class="close wfabt_btn wfabt_btn_grey" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Close', 'woofunnels-ab-tests' ); ?>
                    </a>
                </div>

            </div>
        </div>

        <!-- Step-2 Updating traffic -->
        <div v-if="`2`== state" class="main bwfabt_disp_none bwfabt_text_center" v-bind:class="(`2`==state)?` bwfabt_disp_show`:``">

            <div class="bwfabt_row">
                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
            </div>
        </div>

        <!-- Step-3 Traffic Updated -->
        <div v-if="`3` == state" class="bwfabt_disp_none" v-bind:class="(`3`==state)?` bwfabt_disp_show`:``">
            <div class="wfabt_check_readiness bwfabt_row">
                <div id="modal-ajax5" style="padding: 0px;">
                    <div class="bwfabt_row">
                        <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                            <circle class="path circle" fill="none" stroke="#b7e6bb" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                            <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                        </svg>
                    </div>
                    <div class="bwfabt_row wfabt_txt_center">
                        <div class="wfabt_h3"><?php esc_html_e( 'Traffic updated!', 'woofunnels-ab-tests' ); ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
