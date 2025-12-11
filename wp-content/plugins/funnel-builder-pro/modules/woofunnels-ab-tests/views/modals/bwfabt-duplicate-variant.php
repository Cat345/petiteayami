<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Add Variant model
 */
?>
<div id="part_duplicate_variant_vue" class="bwfabt-pop-common">
    <div class="wfabt_check_readiness bwfabt_pop_body">
        <div id="modal-ajax5" style="padding: 0px;">

            <!-- Step-1 Duplicating variant -->
            <div v-if="`duplicating`==duplicate_status">
                <div class="bwfabt-updating-experiment bwfabt_text_center">
                    <div class="bwfabt_row">
                        <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
                    </div>
                </div>
            </div>

            <!-- Step-2 Duplicated variant -->
            <div v-if="`duplicated`==duplicate_status" v-bind:class="(`duplicated`==duplicate_status)?` bwfabt_disp_show`:``" class="bwfabt_row bwfabt_text_center bwfabt_disp_none">
                <div class="bwfabt_row">
                    <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                        <circle class="path circle" fill="none" stroke="#b7e6bb" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                        <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                    </svg>
                </div>

                <div class="bwfabt_row">
                    <div class="wfabt_h3"><?php esc_html_e( 'Duplicate variant successfully created!', 'woofunnels-ab-tests' ); ?></div>
                    <div class="wfabt_p"><?php esc_html_e( 'You need to set traffic for this variant', 'woofunnels-ab-tests' ); ?></div>
                </div>


                <div class="bwfabt_row wfabt_txt_center ">
                    <a v-on:click="openTrafficpop('#modal-duplicate-variant')" class="wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Configure Traffic', 'woofunnels-ab-tests' ); ?>
                    </a>

                    <a data-izimodal-close="" href="javascript:void(0);" class="wfabt_btn wfabt_btn_grey"><span class="animate_btn"></span>
						<?php esc_html_e( 'Close', 'woofunnels-ab-tests' ); ?> </a>
                </div>
            </div>
        </div>
    </div>
</div>
