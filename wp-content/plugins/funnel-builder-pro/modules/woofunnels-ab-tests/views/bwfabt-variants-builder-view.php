<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
echo BWF_Admin_Breadcrumbs::render_sticky_bar(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Variant listing and builder page
 */
$experiment = BWFABT_Core()->admin->get_experiment();
include_once( __DIR__ . '/commons/single-exp-head.php' );
?>
    <div class="bwfabt_page_left_wrap" id="bwfabt_common_vue">
        <div class="bwfabt-loader"><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif"></div>

        <div class="wfabt_wrap wfabt_listing wfabt_global wfabt_variant">

			<?php include_once( __DIR__ . '/commons/section-head.php' ); ?>

            <div class="wfabt_variants">

                <!--Original Variant -->
				<div v-if="(`undefined`!==typeof update_traffic.valid_traffic && `4`!=experiment_status) && false === update_traffic.valid_traffic" class="update_traffic_error">
					<?php esc_html_e( 'Sum of all the traffic percentage should be 100%', 'woofunnels-ab-tests' ); ?><span v-on:click="update_traffics()"><?php esc_html_e( 'Update Weight', 'woofunnels-ab-tests' ); ?></span>
				</div>
                <div v-for="(variant, variant_id) in variants" v-if="true==variant.control" v-bind:class="true==variant.winner?` wfabt_products_closed`:``" class="wfabt_product clearfix" v-bind:style="{ 'border-left': '4px solid '+getVariantColor(variant_id) }">
                    <img v-if="true==variant.winner" src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/awar.png" class="wfabt_awar">
                    <div v-if="true==variant.winner" class="wfabt_product_skew"></div>
                    <div v-if="true==variant.winner" class="wfabt_product_skew_2"></div>

                    <div class="wfabt_product_name">
                        <div class="variant_name" style="display: table-cell; vertical-align: middle; ">
                            <div class="wfabt_badge wfabt_badge_primary" v-if="true == variant.control"><?php esc_html_e( 'Original', 'woofunnels-ab-tests' ); ?></div>
                            <a v-bind:href="`4`!=experiment_status?variant.edit:`javascript:void(0);`">{{decodeHtml(variant.title)}} </a>
                        </div>
                    </div>
                    <div v-on:click="(`4`!=experiment_status)?update_traffics():``" class="wfabt_product_traffic" v-bind:class="(4===experiment_status)?`no-cursor`:`cursor`">

                        <div class="circle" v-bind:data-v-id="variant_id">
                            <div class="update_para">{{decodeHtml(variant.traffic)}}<?php esc_html_e( '% Weight', 'woofunnels-ab-tests' ); ?></div>
                        </div>
                    </div>
                    <div v-if="`4`!=experiment_status" class="wfabt_product_links">

                        <ul>
                            <li v-bind:class="(`draft`==action_name || `delete`==action_name)?` wfabt_remo`:``" v-for="(action,action_name) in variant.row_actions" v-if="(false === wfabtHP(action,'invisible') || (true ===  wfabtHP(action,'invisible') && 'no' === action.invisible)  )">

                                <a v-if="( (  (`duplicate`==action_name) || (`delete`==action_name) )  )" v-on:click="(`duplicate`==action_name)?duplicateVariant(variant_id):deleteVarntConsent(variant_id)" v-bind:href="action.link">{{decodeHtml(action.text)}}</a>

                                <a v-else-if="((`draft`==action_name || `edit`==action_name) )" v-on:click="(`draft`==action_name)?draftVariantConsent(variant_id):``" v-bind:href="action.link">{{decodeHtml(action.text)}}</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!--Active Variant(Not Original) -->
                <div v-for="(variant, variant_id) in variants" v-if="true!==variant.control" v-bind:class="true==variant.winner?` wfabt_products_closed`:``" class="wfabt_product clearfix" v-bind:style="{ 'border-left': '4px solid '+getVariantColor(variant_id) }">
                    <img v-if="true==variant.winner" src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/awar.png" class="wfabt_awar">
                    <div v-if="true==variant.winner" class="wfabt_product_skew"></div>
                    <div v-if="true==variant.winner" class="wfabt_product_skew_2"></div>

                    <div class="wfabt_product_name">
                        <div class="variant_name" style="display: table-cell; vertical-align: middle; ">
                            <div class="wfabt_badge wfabt_badge_primary" v-if="true == variant.control"><?php esc_html_e( 'Original', 'woofunnels-ab-tests' ); ?></div>
                            <a v-bind:href="`4`!=experiment_status?variant.edit:`javascript:void(0);`">{{decodeHtml(variant.title)}}</a>
                        </div>
                    </div>
                    <div v-on:click="(`4`!=experiment_status)?update_traffics():``" class="wfabt_product_traffic" v-bind:class="(4===experiment_status)?`no-cursor`:`cursor`">

                        <div class="circle" v-bind:data-v-id="variant_id">
                            <div class="update_para">{{decodeHtml(variant.traffic)}}<?php esc_html_e( '% Weight', 'woofunnels-ab-tests' ); ?></div>
                        </div>
                    </div>
                    <div v-if="`4`!=experiment_status" class="wfabt_product_links ss">
                        <ul>
							<li v-if="(`undefined`!==typeof variant.row_actions.edit) && _.size(variant.row_actions.edit)>0" >
								<a v-bind:href="variant.row_actions.edit.link">{{decodeHtml(variant.row_actions.edit.text)}}</a>
							</li>
							<li v-if="(`undefined`!==typeof variant.row_actions.duplicate) && _.size(variant.row_actions.duplicate)>0">
								<a v-bind:href="variant.row_actions.duplicate.link" v-on:click="duplicateVariant(variant_id)" >{{decodeHtml(variant.row_actions.duplicate.text)}}</a>
							</li>
							<li v-if="(`undefined`!==typeof variant.row_actions.draft) && _.size(variant.row_actions.draft)>0">
								<a v-bind:href="variant.row_actions.draft.link" v-on:click="draftVariantConsent(variant_id)">{{decodeHtml(variant.row_actions.draft.text)}}</a>
							</li>
							<li v-if="(`undefined`!==typeof variant.row_actions.publish) && _.size(variant.row_actions.publish)>0">
								<a v-bind:href="variant.row_actions.publish.link" v-on:click="publishVariantConsent(variant_id)">{{decodeHtml(variant.row_actions.publish.text)}}</a>
							</li>
							<li  v-if="(`undefined`!==typeof variant.row_actions.delete) && _.size(variant.row_actions.delete)>0" class="wfabt_remo">
								<a v-bind:href="variant.row_actions.delete.link" v-on:click="deleteVarntConsent(variant_id)">{{decodeHtml(variant.row_actions.delete.text)}}</a>
							</li>
                        </ul>
                    </div>
                </div>



            </div>

            <div v-if="getTotalVariantCount() > 1" class="wfabt_variant_add_bttn">
                <a v-if="1===experiment_status" v-on:click="addVariant()" href="javascript:void(0);" class="wfabt_variant_new wfabt_btn wfabt_btn_grey_border">
                    <span class="animate_btn"></span>
                    <span>+</span> <?php esc_html_e( 'New Variant', 'woofunnels-ab-tests' ); ?></a>
            </div>

            <div v-if="getTotalVariantCount() < 2" class="wfabt_new_variant bwfabt_disp_none" v-bind:class="(getTotalVariantCount() < 2)?` bwfabt_disp_show`:``">
                <svg
                        xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink"
                        width="93px" height="93px">
                    <path fill-rule="evenodd" fill="rgb(87, 87, 87)"
                          d="M46.499,-0.000 C72.181,-0.000 93.000,20.818 93.000,46.500 C93.000,72.181 72.181,93.000 46.499,93.000 C20.819,93.000 -0.000,72.181 -0.000,46.500 C-0.000,20.818 20.819,-0.000 46.499,-0.000 Z"/>
                    <path fill-rule="evenodd" fill="rgb(87, 87, 87)"
                          d="M36.000,25.000 L39.000,25.000 L39.000,63.000 L36.000,63.000 L36.000,25.000 Z"/>
                    <path fill-rule="evenodd" stroke-width="3px" stroke="rgb(255, 255, 255)" fill="rgb(87, 87, 87)"
                          d="M52.000,61.000 L38.000,61.000 L24.000,61.000 L24.000,27.000 L38.000,27.000 L52.000,27.000 C52.471,27.000 52.941,27.000 53.409,27.000 L66.000,39.820 C66.000,49.235 66.000,61.000 66.000,61.000 L52.000,61.000 Z"/>
                    <path fill-rule="evenodd" fill="rgb(87, 87, 87)"
                          d="M37.000,27.000 L41.000,27.000 L41.000,61.000 L37.000,61.000 L37.000,27.000 Z"/>
                    <path fill-rule="evenodd" fill="rgb(255, 255, 255)"
                          d="M38.000,27.000 L42.000,27.000 L42.000,61.000 L38.000,61.000 L38.000,27.000 Z"/>
                    <path fill-rule="evenodd" stroke-width="1px" stroke="rgb(87, 87, 87)" fill="rgb(255, 255, 255)"
                          d="M30.995,48.799 L48.953,48.799 L43.673,53.998 L47.405,53.998 L54.005,47.500 L47.405,41.002 L43.673,41.002 L48.953,46.201 L30.995,46.201 L30.995,48.799 Z"/>
                </svg>

                <div class="bwfabt_clear_20"></div>

                <div class="wfabt_h3"><?php esc_html_e( 'Add another variant to this test!', 'woofunnels-ab-tests' ); ?></div>
                <div class="wfabt_p"><?php esc_html_e( 'Choose one of the option: either duplicate your original variant or add a new variant.', 'woofunnels-ab-tests' ); ?> </div>

                <div class="bwfabt_clear_40"></div>

                <a v-on:click="duplicateVariant(control_id);" class="dup wfabt_btn wfabt_btn_primary" href="javascript:void(0);">
                    <span class="animate_btn"></span>
					<?php esc_html_e( 'Duplicate variant', 'woofunnels-ab-tests' ); ?></a>
                <a v-if="1===experiment_status" v-on:click="addVariant()" class="add trigger-ajax2 wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                    <span class="animate_btn"></span>
					<?php esc_html_e( 'New variant', 'woofunnels-ab-tests' ); ?></a>
            </div>
        </div>
    </div> <!-- bwfabt_common_vue vue ends here  -->


    <!-- Update Experiment popup izimodel -->
    <div style="display: none" id="modal-update-experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-update-experiment.php' ); ?>
    </div>

    <!-- Add new variant popup izimodel -->
    <div style="display: none" id="modal-add-variant">
		<?php include_once( __DIR__ . '/modals/bwfabt-add-variant.php' ); ?>
    </div>

    <!-- duplicate variant popup izimodel -->
    <div style="display: none" id="modal-duplicate-variant">
		<?php include_once( __DIR__ . '/modals/bwfabt-duplicate-variant.php' ); ?>
    </div>

    <!-- draft variant popup izimodel -->
    <div style="display: none" id="modal-draft-variant">
		<?php include_once( __DIR__ . '/modals/bwfabt-draft-variant.php' ); ?>
    </div>

	<!-- publish variant popup izimodel -->
    <div style="display: none" id="modal-publish-variant">
		<?php include_once( __DIR__ . '/modals/bwfabt-publish-variant.php' ); ?>
    </div>

    <!-- duplicate variant popup izimodel -->
    <div style="display: none" id="modal-delete-variant">
		<?php include_once( __DIR__ . '/modals/bwfabt-delete-variant.php' ); ?>
    </div>

    <!-- Update traffic pop izimodal -->
    <div class="bwfabt_izimodal_default" style="display: none;" id="modal_update_traffic">
		<?php include_once( __DIR__ . '/modals/bwfabt-update-traffic.php' ); ?>
    </div>

    <!-- Experiment Readiness -->
    <div class="bwfabt_izimodal_default" style="display: none;" id="modal_start_experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-readiness.php' ); ?>
    </div>

    <!-- Stop experiment -->
    <div class="bwfabt_izimodal_default" style="display: none;" id="modal_stop_experiment">
		<?php include_once( __DIR__ . '/modals/bwfabt-stop-experiment.php' ); ?>
    </div>
<?php
include_once( __DIR__ . '/commons/single-exp-foot.php' );
