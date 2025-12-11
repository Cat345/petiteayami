<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Delete Variant model
 */
?>
<div id="part_delete_variant_vue" class="bwfabt-pop bwfabt-pop-common">
    <div class="wfabt_delete_variant bwfabt_pop_body">
        <div id="modal-ajax5" style="padding: 0px;">
            <div class="bwfabt-loading" v-bind:class="`1`===is_initialized?'bwfabt-hide':''">
                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
            </div>
            <!-- Step-1 Take consent -->
            <div v-if="`delete`===delete_status" class="bwfabt-confirmation main wfabt_main_2 wfabt_main_3 bwfabt_disp_none" v-bind:class="(`delete`===delete_status)?` bwfabt_disp_show`:``">

                <div class="bwfabt_row">
                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512; width:80px" xml:space="preserve" class="">
                            <g>
                                <g>
                                    <g>
                                        <path d="M505.403,406.394L295.389,58.102c-8.274-13.721-23.367-22.245-39.39-22.245c-16.023,0-31.116,8.524-39.391,22.246    L6.595,406.394c-8.551,14.182-8.804,31.95-0.661,46.37c8.145,14.42,23.491,23.378,40.051,23.378h420.028    c16.56,0,31.907-8.958,40.052-23.379C514.208,438.342,513.955,420.574,505.403,406.394z M477.039,436.372    c-2.242,3.969-6.467,6.436-11.026,6.436H45.985c-4.559,0-8.784-2.466-11.025-6.435c-2.242-3.97-2.172-8.862,0.181-12.765    L245.156,75.316c2.278-3.777,6.433-6.124,10.844-6.124c4.41,0,8.565,2.347,10.843,6.124l210.013,348.292    C479.211,427.512,479.281,432.403,477.039,436.372z" data-original="#000000" class="active-path" data-old_color="#000000" fill="#F43829"></path>
                                    </g>
                                </g>
                                <g>
                                    <g>
                                        <path d="M256.154,173.005c-12.68,0-22.576,6.804-22.576,18.866c0,36.802,4.329,89.686,4.329,126.489    c0.001,9.587,8.352,13.607,18.248,13.607c7.422,0,17.937-4.02,17.937-13.607c0-36.802,4.329-89.686,4.329-126.489    C278.421,179.81,268.216,173.005,256.154,173.005z" data-original="#000000" class="active-path" data-old_color="#000000" fill="#F43829"></path>
                                    </g>
                                </g>
                                <g>
                                    <g>
                                        <path d="M256.465,353.306c-13.607,0-23.814,10.824-23.814,23.814c0,12.68,10.206,23.814,23.814,23.814    c12.68,0,23.505-11.134,23.505-23.814C279.97,364.13,269.144,353.306,256.465,353.306z" data-original="#000000" class="active-path" data-old_color="#000000" fill="#F43829"></path>
                                    </g>
                                </g>
                            </g>
                        </svg>
                </div>

                <div class="bwfabt_row wfabt_txt_center">
                    <div class="wfabt_h3">
						<?php esc_html_e( 'Are you sure you want to delete?', 'woofunnels-ab-tests' ); ?></div>
                    <div class="wfabt_p"><?php esc_html_e( ' This action cannot be undone.', 'woofunnels-ab-tests' ); ?></div>
                </div>

                <div class="bwfabt_clear_20"></div>

                <div class="bwfabt_row wfabt_txt_center">
                    <a v-if="`` !== variantID" v-on:click="deleteVariant()" class="wfabt_btn wfabt_btn_red" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Yes, Delete', 'woofunnels-ab-tests' ); ?>
                    </a>
                    <a class="wfabt_btn wfabt_btn_grey" data-izimodal-close="" href="javascript:void(0);">
                        <span class="animate_btn"></span> <?php esc_html_e( 'Close', 'woofunnels-ab-tests' ); ?>
                    </a>
                </div>
            </div>

            <!-- Step-2 Deleting -->
            <div v-if="`deleting`===delete_status" class="bwfabt_disp_none bwfabt_text_center" v-bind:class="(`deleting`===delete_status)?` bwfabt_disp_show`:``">

                <div class="bwfabt_row">

                    <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
                </div>
            </div>

            <!-- Step-3 Deleted -->
            <div v-if="`deleted`===delete_status" class="bwfabt-success bwfabt_disp_none" v-bind:class="(`deleted`===delete_status)?` bwfabt_disp_show`:``">
                <div class="bwfabt_row">
                    <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                        <circle class="path circle" fill="none" stroke="#b7e6bb" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                        <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                    </svg>

                </div>

                <div class="bwfabt_row wfabt_txt_center">
                    <div class="wfabt_h3"><?php esc_html_e( 'Variant deleted successfully!', 'woofunnels-ab-tests' ); ?> </div>
                    <div v-if="false===control_only" class="wfabt_p"><?php esc_html_e( 'You need to re-arrange traffic', 'woofunnels-ab-tests' ); ?></div>
                    <div class="bwfabt_clear_20"></div>
                </div>

                <div v-if="false===control_only" class="bwfabt_row wfabt_txt_center">
                    <a v-on:click="openTrafficpop('#modal-delete-variant')" class="wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Configure Traffic', 'woofunnels-ab-tests' ); ?>
                    </a>
                    <a class="wfabt_btn wfabt_btn_grey" data-izimodal-close="" href="javascript:void(0);">
                        <span class="animate_btn"></span><?php esc_html_e( 'Close', 'woofunnels-ab-tests' ); ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
