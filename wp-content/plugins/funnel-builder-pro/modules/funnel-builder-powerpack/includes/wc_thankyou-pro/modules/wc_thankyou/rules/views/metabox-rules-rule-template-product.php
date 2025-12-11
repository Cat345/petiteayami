<td class="rule-type">
	<?php
	$types = apply_filters( 'wfty_wfty_rule_get_rule_types_product', array() );
	// create field
	$args = array(
		'input'   => 'select',
		'name'    => 'wfty_rule[product][<%= groupId %>][<%= ruleId %>][rule_type]', //phpcs:ignore WordPressVIPMinimum.Security.Underscorejs.OutputNotation
		'class'   => 'rule_type',
		'choices' => $types,
	);

	wfty_Input_Builder::create_input_field( $args, 'html_always' );
	?>
</td>


<?php
WFTY_Rules::get_instance()->render_rule_choice_template( array(
	'group_id'  => 0,
	'rule_id'   => 0,
	'rule_type' => 'general_always_2',
	'condition' => false,
	'operator'  => false,
	'category'  => 'product',
) );
?>
<td class="loading" colspan="2" style="display:none;"><?php esc_html_e( 'Loading...', 'funnel-builder-powerpack' ); ?></td>
<td class="add"><a href="javascript:void(0);" class="wfty-add-rule button"><?php esc_html_e( "AND", 'funnel-builder-powerpack' ); ?></a></td>
<td class="remove"><a href="javascript:void(0);" class="wfty-remove-rule wfty-button-remove"></a></td>