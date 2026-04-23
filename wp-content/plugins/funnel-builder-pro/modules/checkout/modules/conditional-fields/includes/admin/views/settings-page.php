<?php
/**
 * Settings Page View
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap fkcf-settings-wrap">
	<h1><?php esc_html_e( 'FunnelKit Checkout Conditional Fields', 'woofunnels-aero-checkout' ); ?></h1>

	<?php if ( empty( $checkouts ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: link to create checkout */
					esc_html__( 'No checkout pages found. Please %s first.', 'woofunnels-aero-checkout' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=bwf&path=/funnels/templates' ) ) . '">' . esc_html__( 'create a checkout page', 'woofunnels-aero-checkout' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php else : ?>

		<!-- Checkout Tabs -->
		<div class="fkcf-tabs-wrapper">
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $checkouts as $checkout ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'checkout_id', $checkout->ID ) ); ?>"
						class="nav-tab <?php echo absint( $active_checkout_id ) === absint( $checkout->ID ) ? 'nav-tab-active' : ''; ?>"
						data-checkout-id="<?php echo esc_attr( $checkout->ID ); ?>">
						<?php echo esc_html( $checkout->post_title ); ?>
						<span class="fkcf-checkout-id">(ID: <?php echo esc_html( $checkout->ID ); ?>)</span>
					</a>
				<?php endforeach; ?>
			</h2>
		</div>

		<!-- Active Checkout Content -->
		<?php if ( $active_checkout_id ) : ?>
			<div class="fkcf-checkout-content" data-checkout-id="<?php echo esc_attr( $active_checkout_id ); ?>">
				<div class="fkcf-loading-overlay" style="display: none;">
					<div class="fkcf-spinner"></div>
					<p><?php esc_html_e( 'Loading fields...', 'woofunnels-aero-checkout' ); ?></p>
				</div>

				<div class="fkcf-fields-container">
					<div class="fkcf-fields-header">
						<h2><?php esc_html_e( 'Checkout Fields', 'woofunnels-aero-checkout' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Click "Edit Rules" to configure conditional logic for any field.', 'woofunnels-aero-checkout' ); ?>
						</p>
					</div>

					<table class="wp-list-table widefat fixed striped fkcf-fields-table">
						<thead>
							<tr>
								<th scope="col" class="fkcf-col-field-name"><?php esc_html_e( 'Field', 'woofunnels-aero-checkout' ); ?></th>
								<th scope="col" class="fkcf-col-section"><?php esc_html_e( 'Section', 'woofunnels-aero-checkout' ); ?></th>
								<th scope="col" class="fkcf-col-type"><?php esc_html_e( 'Type', 'woofunnels-aero-checkout' ); ?></th>
								<th scope="col" class="fkcf-col-status"><?php esc_html_e( 'Rules Status', 'woofunnels-aero-checkout' ); ?></th>
								<th scope="col" class="fkcf-col-actions"><?php esc_html_e( 'Actions', 'woofunnels-aero-checkout' ); ?></th>
							</tr>
						</thead>
						<tbody id="fkcf-fields-list">
							<tr>
								<td colspan="5" class="fkcf-loading-message">
									<?php esc_html_e( 'Loading fields...', 'woofunnels-aero-checkout' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; ?>
</div>

<!-- Rule Editor Modal -->
<div id="fkcf-rule-modal" class="fkcf-modal" style="display: none;">
	<div class="fkcf-modal-overlay"></div>
	<div class="fkcf-modal-content">
		<div class="fkcf-modal-header">
			<h2><?php esc_html_e( 'Edit Conditional Rules', 'woofunnels-aero-checkout' ); ?></h2>
			<button type="button" class="fkcf-modal-close">&times;</button>
		</div>

		<div class="fkcf-modal-body">
			<div class="fkcf-field-info">
				<strong><?php esc_html_e( 'Field:', 'woofunnels-aero-checkout' ); ?></strong>
				<span id="fkcf-current-field-label"></span>
				<span id="fkcf-current-field-id" style="display: none;"></span>
			</div>

			<div class="fkcf-rule-settings">
				<div class="fkcf-setting-row">
					<label>
						<strong><?php esc_html_e( 'Field Action:', 'woofunnels-aero-checkout' ); ?></strong>
					</label>
					<label>
						<input type="radio" name="fkcf_action" value="show" checked>
						<?php esc_html_e( 'Show', 'woofunnels-aero-checkout' ); ?>
					</label>
					<label>
						<input type="radio" name="fkcf_action" value="hide">
						<?php esc_html_e( 'Hide', 'woofunnels-aero-checkout' ); ?>
					</label>
				</div>

				<div class="fkcf-setting-row">
					<label>
						<strong><?php esc_html_e( 'When:', 'woofunnels-aero-checkout' ); ?></strong>
					</label>
					<label>
						<input type="radio" name="fkcf_group_logic" value="and" checked>
						<?php esc_html_e( 'All condition groups match', 'woofunnels-aero-checkout' ); ?>
					</label>
					<label>
						<input type="radio" name="fkcf_group_logic" value="or">
						<?php esc_html_e( 'Any condition group matches', 'woofunnels-aero-checkout' ); ?>
					</label>
				</div>
			</div>

			<div id="fkcf-rule-groups-container">
				<!-- Rule groups will be added here dynamically -->
			</div>

			<div class="fkcf-add-group-container">
				<button type="button" class="button" id="fkcf-add-group">
					<span class="dashicons dashicons-plus"></span>
					<?php esc_html_e( 'Add Condition Group (OR)', 'woofunnels-aero-checkout' ); ?>
				</button>
			</div>
		</div>

		<div class="fkcf-modal-footer">
			<button type="button" class="button button-secondary fkcf-modal-cancel">
				<?php esc_html_e( 'Cancel', 'woofunnels-aero-checkout' ); ?>
			</button>
			<button type="button" class="button button-secondary fkcf-delete-rules" style="margin-right: auto;">
				<?php esc_html_e( 'Delete Rules', 'woofunnels-aero-checkout' ); ?>
			</button>
			<button type="button" class="button button-primary fkcf-save-rules">
				<?php esc_html_e( 'Save Rules', 'woofunnels-aero-checkout' ); ?>
			</button>
		</div>
	</div>
</div>
