<?php defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Advanced tab content
 */
?>
<div v-if="`schedule`==settings_tab" class="bwfabt-settings-schedule">
    <table class="bwfabt_table">
        <tr>
            <td><p><strong><?php esc_html_e( 'Schedule Settings', 'woofunnels-ab-tests' ); ?></strong></p>
                <span><?php esc_html_e( 'Schedule settings will be here.', 'woofunnels-ab-tests' ); ?></span>
            </td>

        </tr>
    </table>
</div>
