<?php
/**
 * Template file for detailed Stripe Tax diagnostics report.
 *
 * @package Stripe\StripeTaxForWooCommerce\templates
 */

defined( 'ABSPATH' ) || exit;

$allowed = array(
	'a' => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
		'class'  => array(),
	),
);
$issues  = array();
if ( isset( $health_report ) && is_array( $health_report ) && isset( $health_report['issues'] ) && is_array( $health_report['issues'] ) ) {
	$issues = $health_report['issues'];
}

?>
<div class="notice notice-error inline">
	<p>
		<strong><?php echo esc_html__( 'Stripe Tax Configuration Issues Found', 'stripe-tax-for-woocommerce' ); ?></strong>
	</p>
	<ul style="margin-left: 20px; list-style: disc;">
		<?php
		foreach ( $issues as $issue ) {
			?>
			<li>
				<?php echo wp_kses( $issue['message'], $allowed ); ?>
				<?php
				if ( ! empty( $issue['action_url'] ) && ! empty( $issue['action_label'] ) ) {
					echo ' <a href="' . esc_url( $issue['action_url'] ) . '">' . esc_html( $issue['action_label'] ) . '</a>';
				}
				?>
			</li>
			<?php
		}
		?>
	</ul>
</div>
