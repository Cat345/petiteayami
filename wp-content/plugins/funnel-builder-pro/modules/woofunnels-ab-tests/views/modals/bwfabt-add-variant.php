<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Add Variant model
 */
?>
<div id="part_add_variant_vue" class="bwfabt-pop bwfabt-pop-common">
    <div v-if="`1`==state" class="wfabt_popup_header">
        <div class="wfabt_pop_title"><?php esc_attr_e( 'Add Variant', 'woofunnels-ab-tests' ); ?></div>
        <a class="wfabt_pop_close_btn" data-izimodal-close="" href="javascript:void(0);"><span class="dashicons dashicons-no-alt"></span></a>
    </div>
    <div class="wfabt_after_remove bwfabt_row bwfabt_clear">
        <div class="bwfabt_pop_body">
            <div class="bwfabt-loading" v-bind:class="`1`===is_initialized?'bwfabt-hide':''">
                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
            </div>
            <!-- Step-1 Add variant -->
            <form v-if="`1`==state" class="bwfabt_add_variant bwfabt_disp_none" data-bwfabtaction="add_variant" v-bind:class="(`1`==state)?` bwfabt_disp_show`:``">
                <div class="bwfabt_vue_forms">
                    <vue-form-generator :schema="schema" ref="add_variant_ref" :model="model" :options="formOptions"></vue-form-generator>
                </div>

                <div class="wfabt_bnt_rem wfabt_txt_center bwfabt_row bwfabt-hide">
                    <a v-on:click="create_variant()" class="updt wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_attr_e( "Add variant", 'woofunnels-ab-tests' ); ?>
                    </a>
                </div>
            </form>

            <!-- Step-2 Adding variant -->
            <div v-if="`2`==state" v-bind:class="(`2`==state)?` bwfabt_disp_show`:``" class="bwfabt_disp_none">

                <div class="bwfabt-updating-experiment bwfabt_pop_body">
                    <div class="bwfabt_row bwfabt_text_center">
                        <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
                    </div>
                </div>
            </div>

            <!-- Step-2 Variant added-->
            <div v-if="`3`==state" class="wfabt_add_variant wfabt_announce bwfabt_disp_none" v-bind:class="(`3`==state)?` bwfabt_disp_show`:``">
                <div id="modal-ajax5" style="padding: 0px;">
                    <div class="bwfabt_pop_body">

                        <div class="bwfabt_row bwfabt_text_center">
                            <svg class="wfabt_loader wfabt_loader_ok" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                                <circle class="path circle" fill="none" stroke="#baeac5" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                                <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                            </svg>
                        </div>
                        <div class="bwfabt_row bwfabt_text_center">
                            <div class="wfabt_h3"><?php esc_html_e( 'A new variant successfully created!', 'woofunnels-ab-tests' ); ?></div>
                            <div class="wfabt_p"><?php esc_html_e( 'You need to set traffic for this variant', 'woofunnels-ab-tests' ); ?></div>
                        </div>
                        <div class="bwfabt_row bwfabt_text_center">
                            <a v-on:click="openTrafficpop('#modal-add-variant')" class="wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                                <span class="animate_btn"></span>
								<?php esc_html_e( 'Configure Traffic', 'woofunnels-ab-tests' ); ?>
                            </a>
                            <a class="wfabt_btn wfabt_btn_grey" data-izimodal-close="" href="javascript:void(0);"> Close </a>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
