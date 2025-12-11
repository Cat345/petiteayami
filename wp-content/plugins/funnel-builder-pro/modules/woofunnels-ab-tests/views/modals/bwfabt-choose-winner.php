<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Choose Winner
 */
?>
<div id="choose_winner" class="popup popup_wfabt_2">
    <div class="wfabt_popup_header">
        <div class="wfabt_pop_title"><?php esc_html_e( 'Choose Winner', 'woofunnels-ab-tests' ); ?></div>
        <a class="wfabt_pop_close_btn" data-izimodal-close="" href="javascript:void(0);">
            <span class="dashicons dashicons-no-alt"></span>
        </a>
    </div>

    <div class="bwfabt_clear_20"></div>

    <div class="bwfabt_pop_body wfabt_main_2">
		<?php BWFABT_Core()->admin->get_choose_winner_table( $experiment ); ?>
        <div class="bwfabt_clear_20"></div>
        <div class="wfabt_txt_center bwfabt_row">
            <div class="wfabt_make_winner_help"><?php echo wp_kses_post( 'Please select a winner by clicking on trophy icon.', 'woofunnels-ab-tests' ); ?></div>
            <a data-experiment_id="<?php echo esc_attr( $experiment->get_id() ); ?>" href="javascript:void(0);" class="wfabt_make_winner wfabt_btn wfabt_btn_success wfab_winner_disabled">
                <span class="animate_btn"></span>
				<?php esc_html_e( 'Declare winner', 'woofunnels-ab-tests' ); ?>
            </a>
        </div>
    </div>
</div>

<div id="confirm_winner" class="wfabt_announce bwfabt-hide">
    <div class="iziModal-content" style="padding: 0px;">
        <div class="wfabt_frst wfabt_color_bg">
            <span><?php esc_html_e( 'Want to set ', 'woofunnels-ab-tests' ); ?>
                <b>"<span class="winner-name"><?php esc_html_e( '{{variant-title}} ', 'woofunnels-ab-tests' ); ?></span>"</b><?php esc_html_e( ' as the winner?', 'woofunnels-ab-tests' ); ?>
            </span>
        </div>
        <div class="bwfabt_pop_body wfabt_main_2">
            <p><?php esc_html_e( 'After you set this variant as the winner -', 'woofunnels-ab-tests' ); ?></p>
            <ul>
                <li><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/right.png"> <?php esc_html_e( 'Winner variant will be live on your site.', 'woofunnels-ab-tests' ); ?></li>
                <li>
                    <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/right.png"> <?php esc_html_e( 'All the loosing variants will be draft & no longer available to the site visitors. ', 'woofunnels-ab-tests' ); ?>
                </li>
                <li>
                    <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/right.png"> <?php esc_html_e( 'You\'ll always have access to this experiment untill you delete it.  ', 'woofunnels-ab-tests' ); ?>
                </li>
                <li><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/right.png"> <?php esc_html_e( 'This expriment will be marked as completed.', 'woofunnels-ab-tests' ); ?>
                </li>
            </ul>
            <div class="bwfabt_clear_40"></div>

            <div class="bwfabt_row wfabt_txt_center">

                <a class="wfabt_make_confirm wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                    <span class="animate_btn"></span>
					<?php esc_html_e( 'Declare it the winner', 'woofunnels-ab-tests' ); ?></a>

                <a class="close_p wfabt_btn wfabt_btn_grey" href="javascript:void(0);">
                    <span class="animate_btn"></span>
					<?php esc_html_e( 'Go back', 'woofunnels-ab-tests' ); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div id="choosing_winner" class="wfabt_announce choosing-winner bwfabt-pop popup_wfabt_2  bwfabt-hide">
    <div class="bwfabt_pop_body ">
        <div class="bwfabt_row wfabt_txt_center">
            <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
        </div>
        <div class="bwfabt_row wfabt_txt_center">
            <div class="wfabt_h3"><?php esc_html_e( 'Please wait for a few moments! ', 'woofunnels-ab-tests' ); ?></div>
            <div class="wfabt_p"><?php esc_html_e( 'Declaring your winner...', 'woofunnels-ab-tests' ); ?></div>
        </div>
    </div>
</div>

<div id="winner_not_selected" class="wfabt_announce bwfabt-pop popup_wfabt_2 bwfabt-hide">
    <div class="bwfabt_pop_body">
        <div id="modal-ajax5">
            <div class="bwfabt_row wfabt_txt_center">
                <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                    <circle class="path circle" fill="none" stroke="#ffb7bf" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
                    <line class="path line" fill="none" stroke="#e64155" stroke-width="8" stroke-linecap="round" stroke-miterlimit="10" x1="34.4" y1="37.9" x2="95.8" y2="92.3"/>
                    <line class="path line" fill="none" stroke="#e64155" stroke-width="8" stroke-linecap="round" stroke-miterlimit="10" x1="95.8" y1="38" x2="34.4" y2="92.2"/>
                </svg>
            </div>

            <div class="bwfabt_row wfabt_txt_center">
                <div class="wfabt_h3"><?php esc_html_e( 'Unable to choose winner!!', 'woofunnels-ab-tests' ); ?></div>
                <div class="wfabt_p"><?php esc_html_e( 'There may be some problem, Please try later!!', 'woofunnels-ab-tests' ); ?></div>
            </div>
        </div>
    </div>
</div>

<div id="real_winner" class=" bwfabt-hide">
    <div class="iziModal-content" style="padding: 0px;">
        <div class="bwfabt_pop_body">
            <div class="bwfabt_row wfabt_txt_center">
                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/winner.png">
            </div>
            <div class="bwfabt_row wfabt_txt_center">
                <div class="wfabt_h3"><?php esc_html_e( 'Congratulations! This experiment is now complete.', 'woofunnels-ab-tests' ); ?> </div>
                <div class="wfabt_p"><?php esc_html_e( 'The winner of this experiment is ', 'woofunnels-ab-tests' ) ?>
                    <b><span class="deleclared-winner"></span></b>
                </div>
            </div>

        </div>
    </div>
</div>
