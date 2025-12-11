<?php
$bump_id = WFOB_Common::get_id();
$groups  = WFOB_Common::get_bump_rules( $bump_id );
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
}
?>
<div class="wfob-rules-builder woocommerce_options_panel" data-category="basic">
    <div id="wfob-rules-groups" class="wfob_rules_common">
        <div class="wfob-rule-group-target">
			<?php
			if ( is_array( $groups ) ) :
			$group_counter = 0;
			foreach ( $groups as $group_id => $group ) :
				if ( empty( $group_id ) ) {
					$group_id = 'group' . $group_id;
				}
				?>

                <div class="wfob-rule-group-container" data-groupid="<?php echo $group_id; ?>">
                    <div class="wfob-rule-group-header">
						<?php if ( $group_counter !== 0 ) : ?>

                            <h4 class="rules_or"><?php _e( 'or', 'woofunnels-order-bump' ); ?></h4>
						<?php endif; ?>
                        <a href="#" class="wfob-remove-rule-group button"></a>
                    </div>
					<?php
					if ( is_array( $group ) ) :
						?>
                        <table class="wfob-rules" data-groupid="<?php echo $group_id; ?>">
                            <tbody>
							<?php
							foreach ( $group as $rule_id => $rule ) :
								if ( empty( $rule_id ) ) {
									$rule_id = 'rule' . $rule_id;
								}
								?>
                            <tr data-ruleid="<?php echo $rule_id; ?>" class="wfob-rule">
                                <td class="rule-type">
									<?php
									// allow custom location rules
									$types = apply_filters( 'wfob_wfob_rule_get_rule_types', array() );

									// create field
									$args = array(
										'input'   => 'select',
										'name'    => 'wfob_rule[basic][' . $group_id . '][' . $rule_id . '][rule_type]',
										'class'   => 'rule_type',
										'choices' => $types,
									);
									wfob_Input_Builder::create_input_field( $args, $rule['rule_type'] );
									?>
                                </td>

								<?php

								WFOB_Common::ajax_render_rule_choice( array(
									'group_id'      => $group_id,
									'rule_id'       => $rule_id,
									'rule_type'     => $rule['rule_type'],
									'condition'     => isset( $rule['condition'] ) ? $rule['condition'] : false,
									'operator'      => $rule['operator'],
									'rule_category' => 'basic',
								) );
								?>
                                <td class="loading" colspan="2" style="display:none;"><?php _e( 'Loading...', 'woofunnels-order-bump' ); ?></td>
                                <td class="add">
                                    <a href="#" class="wfob-add-rule button"><?php _e( 'AND', 'woofunnels-order-bump' ); ?></a>
                                </td>
                                <td class="remove">
                                    <a href="#" class="wfob-remove-rule wfob-button-remove" title="<?php _e( 'Remove condition', 'woofunnels-order-bump' ); ?>"></a>
                                </td>
                                </tr><?php endforeach; ?></tbody>
                        </table>
					<?php endif; ?>
                </div>
				<?php $group_counter ++; ?>
			<?php endforeach; ?>
        </div>

         <button class="button button-primary wfob-add-rule-group" title="<?php _e( 'Add a set of conditions', 'woofunnels-order-bump' ); ?>"><?php _e( 'OR', 'woofunnels-order-bump' ); ?></button>
		<?php endif; ?>
    </div>
</div>
