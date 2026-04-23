<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sublium Related Subscriptions section for Thank You pages
 *
 * @package FunnelKit
 * @subpackage ThankYou Pages
 * @version 1.0.0
 */

// Check if Sublium is available
if ( ! function_exists( 'sublium_init' ) ) {
	return;
}

// Get component data
$component_data = WFFN_Core()->thank_you_pages->data->component_order_details->data ?? array();

// Get Sublium-specific settings with fallbacks
$subscriptions_heading = $component_data['sublium_subscriptions_heading'] ?? __( 'Subscriptions', 'funnel-builder' );
$view_text             = $component_data['sublium_subscriptions_view_text'] ?? __( 'View', 'funnel-builder' );

// Get Sublium subscriptions data
$subscriptionDB = new Sublium\Includes\database\Subscriptions();
$is_renewal     = $order->get_meta( '_sublium_subscription_renewal' ) === 'yes' ? true : false;

if ( $is_renewal ) {
	$subscriptions = $subscriptionDB->read( array( 'id' => $order->get_meta( '_sublium_subscription_id' ) ) );
} else {
	$subscriptions = $subscriptionDB->read( array( 'parent_order_id' => $order->get_id() ) );
}

if ( empty( $subscriptions ) ) {
	return false;
}

$end_point_url = wc_get_endpoint_url( 'sublium', '', wc_get_page_permalink( 'myaccount' ) );
?>

<div class="wfty_box wfty_subscription wfty_sublium_subscriptions">
	<h2 class="woocommerce-order-details__title wfty_title">
		<?php echo esc_html( $subscriptions_heading ); ?>
	</h2>

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details" style="width:100%">
		<thead>
			<tr>
				<th><?php echo esc_html( __( 'ID', 'funnel-builder' ) ); ?></th>
				<th><?php echo esc_html( __( 'Next Payment', 'funnel-builder' ) ); ?></th>
				<th><?php echo esc_html( __( 'Total', 'funnel-builder' ) ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $subscriptions as $subscription_data ) : ?>
				<?php
				$subscription = sublium_get_subscription( $subscription_data['id'] );
				if ( ! $subscription ) {
					continue;
				}
				?>
				<tr>
					<td>#<?php echo esc_html( $subscription->get_id() ); ?></td>
					<td>
						<?php
						$next_payment_date = $subscription->get_next_payment_date();
						if ( $next_payment_date ) {
							if ( class_exists( 'Sublium\Includes\Helpers\Dates' ) ) {
								echo esc_html( Sublium\Includes\Helpers\Dates::get_date_to_display( $next_payment_date ) );
							} else {
								echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $next_payment_date ) ) );
							}
						} else {
							echo esc_html( __( 'N/A', 'funnel-builder' ) );
						}
						?>
					</td>
					<td>
						<?php
						$totals = $subscription->get_totals();
						echo wp_kses_post( $subscription->get_price( $totals ) );
						?>
					</td>
					<td>
						<div class="sublium-view-subscription-buttons">
							<a href="<?php echo esc_url( $end_point_url ); ?>" class="button view">
								<?php echo esc_html( $view_text ); ?>
							</a>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

