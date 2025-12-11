<td class="rule-type">
	<?php
	$types = apply_filters( 'wfob_wfob_rule_get_rule_types', array() );
	// create field
	$args = array(
		'input'   => 'select',
		'name'    => 'wfob_rule[basic][<%= groupId %>][<%= ruleId %>][rule_type]',
		'class'   => 'rule_type',
		'choices' => $types,
	);

	wfob_Input_Builder::create_input_field( $args, 'html_always' );
	?>
</td>

<?php
WFOB_Common::render_rule_choice_template( array(
	'group_id'  => 0,
	'rule_id'   => 0,
	'rule_type' => 'general_always',
	'condition' => false,
	'operator'  => false,
	'category'  => 'basic',
) );
?>
<td class="loading" colspan="2" style="display:none;"><?php _e( 'Loading...', 'woofunnels-order-bump' ); ?></td>
<td class="add"><a href="#" class="wfob-add-rule button"><?php _e( 'AND', 'woofunnels-order-bump' ); ?></a></td>
<td class="remove"><a href="#" class="wfob-remove-rule wfob-button-remove"></a></td>
