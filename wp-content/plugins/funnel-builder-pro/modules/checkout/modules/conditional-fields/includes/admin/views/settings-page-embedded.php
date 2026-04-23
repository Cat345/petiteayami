<?php
/**
 * Settings Page View - Embedded (Design Tab Modal)
 *
 * Minimal view for iframe in Design tab "Add Condition(s)" modal.
 * No checkout dropdown - checkout_id comes from URL.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sections = ! empty( $checkout_sections ) ? $checkout_sections : array();
?>

<div class="wrap fkcf-settings-wrap fkcf-phase2 fkcf-embedded-wrap">
	<?php if ( ! $active_checkout_id ) : ?>
		<p><?php esc_html_e( 'No checkout selected.', 'woofunnels-aero-checkout' ); ?></p>
	<?php elseif ( empty( $sections ) ) : ?>
		<p><?php esc_html_e( 'No sections found for this checkout.', 'woofunnels-aero-checkout' ); ?></p>
	<?php else : ?>
		<div class="fkcf-checkout-content" data-checkout-id="<?php echo esc_attr( $active_checkout_id ); ?>">
			<div class="fkcf-loading-overlay" style="display: none;">
				<div class="fkcf-spinner"></div>
				<p><?php esc_html_e( 'Loading sections...', 'woofunnels-aero-checkout' ); ?></p>
			</div>

			<div class="fkcf-sections-container">
				<div class="fkcf-sections-header">
					<h2><?php esc_html_e( 'Checkout Sections', 'woofunnels-aero-checkout' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Configure conditional rules for entire sections or individual fields within each section.', 'woofunnels-aero-checkout' ); ?>
					</p>
				</div>

				<div class="fkcf-accordion" id="fkcf-sections-accordion">
					<?php foreach ( $sections as $section_id => $section_label ) : ?>
						<div class="fkcf-accordion-item" data-section-id="<?php echo esc_attr( $section_id ); ?>">
							<div class="fkcf-accordion-header">
								<button type="button" class="fkcf-accordion-toggle" aria-expanded="false">
									<span class="dashicons dashicons-arrow-right fkcf-accordion-icon"></span>
									<span class="fkcf-section-title"><?php echo esc_html( $section_label ); ?></span>
									<span class="fkcf-section-badge fkcf-badge-section-rule" style="display: none;">
										<span class="dashicons dashicons-admin-site-alt3"></span>
										<?php esc_html_e( 'Section Rule', 'woofunnels-aero-checkout' ); ?>
									</span>
									<span class="fkcf-section-badge fkcf-badge-field-rules" style="display: none;">
										<span class="dashicons dashicons-admin-page"></span>
										<span class="fkcf-field-rules-count">0</span>
										<?php esc_html_e( 'Field Rules', 'woofunnels-aero-checkout' ); ?>
									</span>
								</button>
							</div>

							<div class="fkcf-accordion-content" style="display: none;">
								<div class="fkcf-section-tabs">
									<button type="button" class="fkcf-tab-button fkcf-tab-active" data-tab="entire-section">
										<span class="dashicons dashicons-admin-site-alt3"></span>
										<?php esc_html_e( 'Entire Section', 'woofunnels-aero-checkout' ); ?>
									</button>
									<button type="button" class="fkcf-tab-button" data-tab="individual-fields">
										<span class="dashicons dashicons-admin-page"></span>
										<?php esc_html_e( 'Individual Fields', 'woofunnels-aero-checkout' ); ?>
									</button>
								</div>

								<div class="fkcf-tab-content fkcf-tab-entire-section" data-tab="entire-section">
									<div class="fkcf-section-rule-info">
										<p class="description">
											<?php esc_html_e( 'Configure a conditional rule for the entire section. When the section is hidden, all fields within it will be hidden regardless of their individual rules.', 'woofunnels-aero-checkout' ); ?>
										</p>
									</div>
									<div class="fkcf-section-rule-status">
										<div class="fkcf-no-section-rule">
											<p><?php esc_html_e( 'No section rule configured.', 'woofunnels-aero-checkout' ); ?></p>
											<button type="button" class="button button-primary fkcf-add-section-rule">
												<span class="dashicons dashicons-plus"></span>
												<?php esc_html_e( 'Add Section Rule', 'woofunnels-aero-checkout' ); ?>
											</button>
										</div>
										<div class="fkcf-has-section-rule" style="display: none;">
											<div class="fkcf-section-rule-summary">
												<strong><?php esc_html_e( 'Rule Summary:', 'woofunnels-aero-checkout' ); ?></strong>
												<span class="fkcf-rule-action"></span>
												<span class="fkcf-rule-conditions-count"></span>
											</div>
											<div class="fkcf-section-rule-actions">
												<button type="button" class="button fkcf-edit-section-rule">
													<?php esc_html_e( 'Edit Rule', 'woofunnels-aero-checkout' ); ?>
												</button>
												<button type="button" class="button fkcf-delete-section-rule">
													<?php esc_html_e( 'Delete Rule', 'woofunnels-aero-checkout' ); ?>
												</button>
											</div>
										</div>
									</div>
								</div>

								<div class="fkcf-tab-content fkcf-tab-individual-fields" data-tab="individual-fields" style="display: none;">
									<div class="fkcf-individual-fields-info">
										<p class="description">
											<?php esc_html_e( 'Configure conditional rules for individual fields within this section.', 'woofunnels-aero-checkout' ); ?>
										</p>
									</div>
									<table class="wp-list-table widefat fixed striped fkcf-fields-table">
										<thead>
											<tr>
												<th scope="col" class="fkcf-col-field-name"><?php esc_html_e( 'Field', 'woofunnels-aero-checkout' ); ?></th>
												<th scope="col" class="fkcf-col-type"><?php esc_html_e( 'Type', 'woofunnels-aero-checkout' ); ?></th>
												<th scope="col" class="fkcf-col-status"><?php esc_html_e( 'Rules Status', 'woofunnels-aero-checkout' ); ?></th>
												<th scope="col" class="fkcf-col-actions"><?php esc_html_e( 'Actions', 'woofunnels-aero-checkout' ); ?></th>
											</tr>
										</thead>
										<tbody class="fkcf-section-fields-list">
											<tr>
												<td colspan="4" class="fkcf-loading-message">
													<?php esc_html_e( 'Loading fields...', 'woofunnels-aero-checkout' ); ?>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- Section Rule Editor Modal -->
		<div id="fkcf-section-rule-modal" class="fkcf-modal" style="display: none;">
			<div class="fkcf-modal-overlay"></div>
			<div class="fkcf-modal-content">
				<div class="fkcf-modal-header">
					<h2><?php esc_html_e( 'Edit Section Conditional Rule', 'woofunnels-aero-checkout' ); ?></h2>
					<button type="button" class="fkcf-modal-close">&times;</button>
				</div>
				<div class="fkcf-modal-body">
					<div class="fkcf-section-info">
						<strong><?php esc_html_e( 'Section:', 'woofunnels-aero-checkout' ); ?></strong>
						<span id="fkcf-current-section-label"></span>
						<span id="fkcf-current-section-id" style="display: none;"></span>
					</div>
					<div class="fkcf-rule-settings">
						<div class="fkcf-setting-row">
							<label><strong><?php esc_html_e( 'Section Action:', 'woofunnels-aero-checkout' ); ?></strong></label>
							<label><input type="radio" name="fkcf_section_action" value="show" checked> <?php esc_html_e( 'Show', 'woofunnels-aero-checkout' ); ?></label>
							<label><input type="radio" name="fkcf_section_action" value="hide"> <?php esc_html_e( 'Hide', 'woofunnels-aero-checkout' ); ?></label>
						</div>
						<div class="fkcf-setting-row">
							<label><strong><?php esc_html_e( 'When:', 'woofunnels-aero-checkout' ); ?></strong></label>
							<label><input type="radio" name="fkcf_section_group_logic" value="AND" checked> <?php esc_html_e( 'All condition groups match', 'woofunnels-aero-checkout' ); ?></label>
							<label><input type="radio" name="fkcf_section_group_logic" value="OR"> <?php esc_html_e( 'Any condition group matches', 'woofunnels-aero-checkout' ); ?></label>
						</div>
					</div>
					<div id="fkcf-section-rule-groups-container"></div>
					<div class="fkcf-add-group-container">
						<button type="button" class="button" id="fkcf-add-section-group">
							<span class="dashicons dashicons-plus"></span>
							<?php esc_html_e( 'Add Condition Group (OR)', 'woofunnels-aero-checkout' ); ?>
						</button>
					</div>
				</div>
				<div class="fkcf-modal-footer">
					<button type="button" class="button button-secondary fkcf-modal-cancel"><?php esc_html_e( 'Cancel', 'woofunnels-aero-checkout' ); ?></button>
					<button type="button" class="button button-secondary fkcf-delete-section-rule-btn" style="margin-right: auto;"><?php esc_html_e( 'Delete Rule', 'woofunnels-aero-checkout' ); ?></button>
					<button type="button" class="button button-primary fkcf-save-section-rule"><?php esc_html_e( 'Save Section Rule', 'woofunnels-aero-checkout' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Field Rule Editor Modal -->
		<div id="fkcf-rule-modal" class="fkcf-modal" style="display: none;">
			<div class="fkcf-modal-overlay"></div>
			<div class="fkcf-modal-content">
				<div class="fkcf-modal-header">
					<h2><?php esc_html_e( 'Edit Field Conditional Rules', 'woofunnels-aero-checkout' ); ?></h2>
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
							<label><strong><?php esc_html_e( 'Field Action:', 'woofunnels-aero-checkout' ); ?></strong></label>
							<label><input type="radio" name="fkcf_action" value="show" checked> <?php esc_html_e( 'Show', 'woofunnels-aero-checkout' ); ?></label>
							<label><input type="radio" name="fkcf_action" value="hide"> <?php esc_html_e( 'Hide', 'woofunnels-aero-checkout' ); ?></label>
						</div>
						<div class="fkcf-setting-row">
							<label><strong><?php esc_html_e( 'When:', 'woofunnels-aero-checkout' ); ?></strong></label>
							<label><input type="radio" name="fkcf_group_logic" value="AND" checked> <?php esc_html_e( 'All condition groups match', 'woofunnels-aero-checkout' ); ?></label>
							<label><input type="radio" name="fkcf_group_logic" value="OR"> <?php esc_html_e( 'Any condition group matches', 'woofunnels-aero-checkout' ); ?></label>
						</div>
					</div>
					<div id="fkcf-rule-groups-container"></div>
					<div class="fkcf-add-group-container">
						<button type="button" class="button" id="fkcf-add-group">
							<span class="dashicons dashicons-plus"></span>
							<?php esc_html_e( 'Add Condition Group (OR)', 'woofunnels-aero-checkout' ); ?>
						</button>
					</div>
				</div>
				<div class="fkcf-modal-footer">
					<button type="button" class="button button-secondary fkcf-modal-cancel"><?php esc_html_e( 'Cancel', 'woofunnels-aero-checkout' ); ?></button>
					<button type="button" class="button button-secondary fkcf-delete-rules" style="margin-right: auto;"><?php esc_html_e( 'Delete Rules', 'woofunnels-aero-checkout' ); ?></button>
					<button type="button" class="button button-primary fkcf-save-rules"><?php esc_html_e( 'Save Rules', 'woofunnels-aero-checkout' ); ?></button>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
