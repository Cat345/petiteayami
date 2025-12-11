<?php defined( 'ABSPATH' ) || exit; //Exit if accessed directly
echo BWF_Admin_Breadcrumbs::render_sticky_bar(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Experiment listing page
 */
$reg_controller_objs  = BWFABT_Core()->admin->get_correct_steps_order();
?>
<div class="wrap">

    <hr class="wp-header-end">
    <div id="poststuff">
        <div class="inside">
            <div class="bwfabt_page_left_wrap" id="bwfabt_experiments_area">
                <div class="bwfabt-loader"><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif"></div>

                <div v-bind:data-step="exp_step" class="wfabt_wrap wfabt_listing wfabt_global">
                    <div v-if="exp_step==`1`" class="wfabt_first_experiment_box bwfabt_hide">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="331px" height="232px">
                            <path fill-rule="evenodd" opacity="0.4" fill="rgb(227, 239, 255)"
                                  d="M162.000,146.000 C152.696,143.530 145.203,145.630 135.000,148.000 C120.996,151.253 111.154,152.060 106.000,149.000 C97.151,143.746 93.586,131.242 94.000,121.500 C94.548,108.573 103.745,99.100 107.000,89.000 C110.739,77.396 110.972,66.112 110.000,62.000 C107.682,52.196 97.435,43.608 99.000,34.000 C100.849,22.648 115.681,16.114 123.000,14.000 C138.054,9.650 153.088,16.699 161.000,17.000 C188.257,18.035 200.807,-4.400 226.000,1.000 C244.367,4.937 246.700,19.131 266.000,27.000 C279.725,32.596 290.613,31.055 305.000,38.000 C319.815,45.151 327.260,54.582 329.000,60.000 C332.920,72.206 326.611,85.966 321.000,94.500 C318.240,98.697 312.993,103.394 310.000,110.000 C307.892,114.652 306.303,123.851 306.000,134.000 C305.741,142.669 306.310,148.059 303.000,152.000 C297.623,158.400 287.665,157.190 276.000,157.000 C262.925,156.787 249.994,158.044 243.000,160.000 C235.790,162.015 230.374,165.811 222.000,167.000 C206.649,169.180 191.799,163.953 182.000,158.000 C174.144,153.227 170.823,148.342 162.000,146.000 Z"/>
                            <path fill-rule="evenodd" opacity="0.4" fill="rgb(233, 243, 255)"
                                  d="M69.000,210.000 C59.696,207.530 52.203,209.630 42.000,212.000 C27.995,215.253 18.154,216.060 13.000,213.000 C4.151,207.746 0.587,195.242 1.000,185.500 C1.548,172.573 10.745,163.101 14.000,153.000 C17.739,141.396 17.972,130.111 17.000,126.000 C14.682,116.197 4.435,107.608 6.000,98.000 C7.849,86.648 22.682,80.114 30.000,78.000 C45.054,73.650 60.088,80.699 68.000,81.000 C95.257,82.036 107.807,59.600 133.000,65.000 C151.367,68.936 153.700,83.131 173.000,91.000 C186.725,96.596 197.613,95.055 212.000,102.000 C226.815,109.152 234.260,118.582 236.000,124.000 C239.920,136.207 233.611,149.966 228.000,158.500 C225.240,162.697 219.993,167.394 217.000,174.000 C214.891,178.652 213.303,187.851 213.000,198.000 C212.741,206.669 213.310,212.059 210.000,216.000 C204.623,222.400 194.665,221.190 183.000,221.000 C169.925,220.787 156.994,222.044 150.000,224.000 C142.790,226.016 137.374,229.811 129.000,231.000 C113.649,233.180 98.799,227.953 89.000,222.000 C81.144,217.227 77.823,212.342 69.000,210.000 Z"/>
                            <path fill-rule="evenodd" fill="rgb(230, 241, 255)"
                                  d="M160.500,10.000 C220.423,10.000 269.000,58.577 269.000,118.500 C269.000,178.423 220.423,227.000 160.500,227.000 C100.577,227.000 52.000,178.423 52.000,118.500 C52.000,58.577 100.577,10.000 160.500,10.000 Z"/>
                            <text font-family="Open Sans" fill="rgb(12, 130, 223)" font-weight="bold" font-size="62px" x="94px" y="138px">A</text>
                            <text font-family="Open Sans" fill="rgb(12, 130, 223)" font-weight="bold" font-size="62px" x="188px" y="138px">B</text>
                            <text font-family="Open Sans" fill="rgb(84, 200, 103)" font-weight="bold" font-size="100px" x="136px" y="156px">&#63;</text>
                        </svg>
                        <div class="bwfabt_clear_10"></div>
                        <div class="wfabt_h3"><?php esc_html_e( 'Create Your First Experiment', 'woofunnels-ab-tests' ); ?></div>
                        <div class="wfabt_p">
							<?php esc_html_e( 'Click on the below button to create first Experiment', 'woofunnels-ab-tests' ); ?>
                        </div>
                        <div class="bwfabt_clear_40"></div>
                        <a class="add wfabt_btn wfabt_btn_primary" v-on:click="move_to_next_step(exp_step)" href="javascript:void(0);">
                            <span class="animate_btn"></span><?php esc_html_e( 'Create Experiment', 'woofunnels-ab-tests' ); ?><i class="dashicons dashicons-arrow-right-alt2"></i>
                        </a>
                        <div class="wfabt_bnt_learn">
                            <a target="_blank" href="<?php echo esc_url( 'https://buildwoofunnels.com/docs/a-b-testing-experiments/' ) ?>" class="learn wfabt_btn wfabt_btn_grey_border">
                                <span class="animate_btn"></span><?php esc_html_e( 'Learn How it Works', 'woofunnels-ab-tests' ); ?>
                            </a>
                        </div>
                    </div>
                    <div v-if="exp_step!=`1`" class="wfabt_new_expr_sec bwfabt_hide">
                        <div class="wfabt_head_new_exp" v-if="exp_step!=`0`">
                            <div class="wfabt_header" v-if="exp_step==`2`">
                                <div class="wfabt_h4"><?php esc_html_e( 'What do you want A/B test?', 'woofunnels-ab-tests' ); ?></div>
                            </div>
                            <div class="wfabt_header" v-if="exp_step!=`2` && `` != control_title">
                                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/check.png"/>
                                <span><?php esc_html_e( 'Experiment Type', 'woofunnels-ab-tests' ); ?> > <b>{{decodeHtml(control_title)}}</b></span>
                            </div>
                        </div>
                        <div class="wfabt_exp_wrapper">
                            <div v-if="exp_step==`0`" class="wfabt_exp_wrap">
                                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
                            </div>
                            <div v-if="exp_step==`2`" class="wfabt_exp_wrap">
                                <p class="bwfabt-type-error bwfabt-hidden" style="color: red;"><?php esc_html_e( 'Select an experiment type.', 'woofunnels-ab-tests' ); ?></p>
                                <div class="wfabt_row">
									<?php
									foreach ( $reg_controller_objs as $experiment_type => $controller ) { ?>
                                        <div class="wfabt_column-3" v-on:click="set_controller_type($event,`<?php echo esc_attr( $experiment_type ) ?>`)">
                                            <a v-bind:class="experiment_type == `<?php echo esc_attr( $experiment_type ); ?>`?` wfabt_bnt_selected_cl`:``" href="javascript:void(0);" class="wfabt_exp_btn">
                                                <div class="wfabt_list_box">
                                                    <img v-bind:style="experiment_type == `<?php echo esc_attr( $experiment_type ); ?>`?` opacity:1`:`opacity:0`" class="wfabt_im" src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/check.png">
                                                    <span class="wfabt_logo_mid"> <?php echo esc_html( $controller->get_title() ); ?> </span>
                                                </div>
                                            </a>
                                        </div>
									<?php } ?>
                                </div>
                                <div class="bwfabt_clear_50"></div>
                                <div class="wfabt_bnts_aheads">
                                    <a href="javascript:void(0);" class="wfabt_ahd_btn wfabt_btn wfabt_btn_primary" v-on:click="getEntitiesForType()">
                                        <span class="animate_btn"></span><?php esc_html_e( 'Save & Continue', 'woofunnls' ); ?> <i class="dashicons dashicons-arrow-right-alt2"></i></a>
                                </div>
                            </div>
                            <div v-if="exp_step==`3`" class="wfabt_exp_wrap">
                                <form class="bwfabt_add_experiment" data-bwfabtaction="add_new_experiment">
                                    <fieldset>
                                        <vue-form-generator ref="vfg" :schema="schema" :model="model" :options="formOptions">
                                        </vue-form-generator>
                                    </fieldset>
                                </form>
                                <div class="wfabt_second_btns">
                                    <a v-on:click="move_to_previous_step(exp_step)" href="javascript:void(0);" class="back wfabt_btn wfabt_btn_grey_border">
                                        <span class="animate_btn"></span>
                                        <i class="dashicons dashicons-arrow-left-alt2"></i> <?php esc_html_e( 'Back', 'woofunnels-ab-tests' ); ?></a>
                                    <a href="javascript:void(0);" v-on:click="createExperiment()" class="next wfabt_disabled_bac wfabt_btn wfabt_btn_primary">
                                        <span class="animate_btn"></span>
										<?php esc_html_e( 'Create Experiment', 'woofunnels-ab-tests' ); ?> <i class="dashicons dashicons-arrow-right-alt2"></i></a>
                                </div>
                            </div>
                            <div v-if="exp_step==`4`" class="wfabt_exp_wrap bwfabt_pop_body ">

                                <div class="bwfabt_row wfabt_txt_center">
                                    <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
                                </div>
                                <div class="bwfabt_clear_20"></div>
                            </div>
                            <div v-if="exp_step==`5`" class="wfabt_exp_wrap bwfabt_pop_body">

                                <div class="bwfabt_row wfabt_txt_center">
                                    <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                                        <circle class="path circle" fill="none" stroke="#39c359" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                                        <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                                    </svg>
                                </div>
                                <div class="bwfabt_clear_20"></div>
                                <div class="bwfabt_row wfabt_txt_center">
                                    <div class="wfabt_h3"><?php esc_html_e( 'Great! your experiment has been succesfully created!', 'woofunnels-ab-tests' ); ?></div>
                                    <div class="wfabt_p"><?php esc_html_e( 'Now redirecting you to create the variants and settings...', 'woofunnels-ab-tests' ); ?></div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
