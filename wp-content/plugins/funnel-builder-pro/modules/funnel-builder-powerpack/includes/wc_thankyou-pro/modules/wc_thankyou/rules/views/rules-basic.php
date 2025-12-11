<?php
$funnel_id = WFTY_Rules::get_instance()->get_thankyou_id();
$groups    = WFTY_Rules::get_instance()->get_funnel_rules( $funnel_id );
if ( empty( $groups ) ) {
	$default_rule_id = 'rule' . uniqid();
	$groups          = array(
		'group' . ( time() ) => array(
			$default_rule_id => array(
				'rule_type' => 'general_always',
				'operator'  => '==',
				'condition' => '',
			),
		),

	);
} ?>
<div class="wfty-rules-builder woocommerce_options_panel" data-category="basic">
	<div id="wfty-rules-groups" class="wfty_rules_common">
		<div class="wfty-rule-group-target">
			<?php if ( is_array( $groups ) ) : ?>
			<?php
			$group_counter = 0;
			foreach ( $groups as $group_id => $group ) :
				if ( empty( $group_id ) ) {
					$group_id = 'group' . $group_id;
				} ?>
				<div class="wfty-rule-group-container" data-groupid="<?php echo esc_attr( $group_id ); ?>">
					<div class="wfty-rule-group-header">
						<?php if ( $group_counter !== 0 ) : ?>

							<h4 class="rules_or"><?php esc_html_e( 'OR', 'funnel-builder-powerpack' ); ?></h4>
						<?php endif; ?>
						<a href="javascript:void(0);" class="wfty-remove-rule-group button"></a>
					</div>
					<?php
					if ( is_array( $group ) ) :


						?>
						<table class="wfty-rules" data-groupid="<?php echo esc_attr( $group_id ); ?>">
							<tbody>
							<?php
							foreach ( $group as $rule_id => $rule ) :
								if ( empty( $rule_id ) ) {
									$rule_id = 'rule' . $rule_id;
								}
								?>
							<tr data-ruleid="<?php echo esc_attr( $rule_id ); ?>" class="wfty-rule">
								<td class="rule-type">
									<?php
									// allow custom location rules
									$types = apply_filters( 'wfty_wfty_rule_get_rule_types', array() );

									// create field
									$args = array(
										'input'   => 'select',
										'name'    => 'wfty_rule[basic][' . $group_id . '][' . $rule_id . '][rule_type]',
										'class'   => 'rule_type',
										'choices' => $types,
									);
									wfty_Input_Builder::create_input_field( $args, ( isset( $rule['rule_type'] ) ? $rule['rule_type'] : 'general_always' ) );
									?>
								</td>

								<?php
								WFTY_Rules::get_instance()->ajax_render_rule_choice( array(
									'group_id'      => $group_id,
									'rule_id'       => $rule_id,
									'rule_type'     => ( isset( $rule['rule_type'] ) ? $rule['rule_type'] : 'general_always' ),
									'condition'     => isset( $rule['condition'] ) ? $rule['condition'] : false,
									'operator'      => ( isset( $rule['operator'] ) ? $rule['operator'] : '==' ),
									'rule_category' => 'basic',
								) );
								?>
								<td class="loading" colspan="2"
									style="display:none;"><?php esc_html_e( 'Loading...', 'funnel-builder-powerpack' ); ?></td>
								<td class="add">
									<a href="javascript:void(0);"
									   class="wfty-add-rule button"><?php esc_html_e( 'AND', 'funnel-builder-powerpack' ); ?></a>
								</td>
								<td class="remove">
									<a href="javascript:void(0);" class="wfty-remove-rule wfty-button-remove"
									   title="<?php esc_html_e( 'Remove condition', 'funnel-builder-powerpack' ); ?>"></a>
								</td>
								</tr><?php endforeach; ?></tbody>
						</table>
					<?php endif; ?>
				</div>
				<?php $group_counter ++; ?>
			<?php endforeach; ?>
		</div>

		<button class="button button-primary wfty-add-rule-group"
				title="<?php esc_html_e( 'Add a set of conditions', 'funnel-builder-powerpack' ); ?>"><?php esc_html_e( 'OR', 'funnel-builder-powerpack' ); ?></button>
		<?php endif; ?>
	</div>
</div>
