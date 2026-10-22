<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $instance WFACP_template_layout4
 */

$next_action     = WFACP_Common::get_previous_step_slug( $current_action );
$change_back_btn = apply_filters( 'wfacp_change_back_btn', __( 'Previous Step', 'woofunnels-aero-checkout' ), $next_action, $current_action );

if ( $change_back_btn != '' ) {
	?>
	<div class="wfacp-back-btn-wrap wfacp_back_wrap">
		<a class='wfacp_back_page_button' data-next-step="<?php echo esc_attr( $next_action ); ?>" data-current-step='<?php echo esc_attr( $current_action ); ?>' href='javascript:void(0)'>
			<?php echo esc_html( $change_back_btn ); ?>
		</a>
	</div>
	<?php
}
?>
