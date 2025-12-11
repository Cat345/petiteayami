<?php defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Advanced tab content
 */
?>
<div v-if="`advanced`==settings_tab" class="bwfabt-settings-advanced">
    <table class="bwfabt_table">
        <tr>
            <td><p><strong><?php esc_html_e( 'Reset experiment', 'woofunnels-ab-tests' ); ?></strong></p>
                <span><?php esc_html_e( 'This will reset all the data like traffic allocation, views, clicks, conversion rates etc. from the experiment & switch the experiment status to the draft.', 'woofunnels-ab-tests' ); ?></span>
            </td>
            <td>
                <button v-bind:class="(`1`==experiment_status||`4`==experiment_status)?`disabled`:``" v-on:click="(`1`==experiment_status||`4`==experiment_status)?``:resetStatsConsent()" class="wp-core-ui button"> <?php esc_html_e( 'Reset', 'woofunnels-ab-tests' ); ?></button>
            </td>
        </tr>
    </table>
</div>
