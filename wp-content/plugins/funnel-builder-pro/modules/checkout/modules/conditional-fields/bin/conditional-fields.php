<?php
/**
 * Generate FKCF Conditional Fields Test Data (Web)
 *
 * Run via browser: /wp-content/plugins/funnel-builder-pro/modules/checkout/modules/conditional-fields/bin/conditional-fields.php?checkout_id=3359
 *
 * Requires admin login. Generates dummy sections, custom fields, and rules for every rule type.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields
 */

// Only allow in development mode.
if ( ! defined( 'ABSPATH' ) && ( ! defined( 'WFACP_IS_DEV' ) || ! WFACP_IS_DEV ) ) {
	die( 'This script is only available in development mode.' );
}

// Load WordPress.
$wp_load = dirname( __DIR__, 8 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	$wp_load = dirname( __DIR__, 7 ) . '/wp-load.php';
}
if ( ! file_exists( $wp_load ) ) {
	die( 'WordPress not found.' );
}
require_once $wp_load;

// Require admin.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to run this script.', 'woofunnels-aero-checkout' ) );
}

// Get checkout ID.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin-only, checkout_id is validated
$checkout_id = isset( $_GET['checkout_id'] ) ? absint( $_GET['checkout_id'] ) : 0;
if ( ! $checkout_id ) {
	wp_die( esc_html__( 'Missing checkout_id. Use: ?checkout_id=3359', 'woofunnels-aero-checkout' ) );
}

// Verify checkout exists.
$post = get_post( $checkout_id );
if ( ! $post || 'wfacp_checkout' !== $post->post_type ) {
	wp_die( esc_html__( 'Invalid checkout ID or post type.', 'woofunnels-aero-checkout' ) );
}

// Ensure required classes exist.
if ( ! class_exists( 'WFACP_Common' ) || ! class_exists( 'WFACP_Common_Helper' ) ) {
	wp_die( esc_html__( 'Funnel Builder Checkout plugin must be active.', 'woofunnels-aero-checkout' ) );
}

/**
 * Build condition array for FKCF storage.
 *
 * @param string $type         Condition type (cart, user, field).
 * @param string $operator     FKCF operator.
 * @param string $value        Value for value-based operators.
 * @param string $operand_type Operand type (product, category, tag, coupon).
 * @param string $operand      Operand ID(s) for operand-based operators.
 * @param string $field_id     Field ID for field-based conditions.
 * @return array Condition data.
 */
function fkcf_web_build_condition( $type, $operator, $value = '', $operand_type = '', $operand = '', $field_id = '' ) {
	return array(
		'type'         => $type,
		'operator'     => $operator,
		'value'        => $value,
		'operand_type' => $operand_type,
		'operand'      => $operand,
		'field_id'     => $field_id,
	);
}

/**
 * Build FKCF section key from section display name.
 *
 * @param string $name Section name.
 * @return string FKCF key.
 */
function fkcf_web_section_key( $name ) {
	$base = preg_replace( '/-\d+$/', '', strtolower( sanitize_title( $name ) ) );
	return $base;
}

/**
 * Build a custom text field definition for layout.
 *
 * @param string $field_id Field ID.
 * @param string $label    Field label.
 * @return array Field definition.
 */
function fkcf_web_build_custom_field( $field_id, $label ) {
	return array(
		'id'             => $field_id,
		'type'           => 'text',
		'label'          => $label,
		'field_type'     => 'advanced',
		'required'       => 'false',
		'placeholder'    => '',
		'class'          => array( 'form-row-wide' ),
		'priority'       => 10,
		'is_wfacp_field' => true,
		'allow_delete'   => true,
	);
}

/**
 * Build a checkbox field definition.
 *
 * @param string $field_id Field ID.
 * @param string $label    Field label.
 * @return array Field definition.
 */
function fkcf_web_build_checkbox_field( $field_id, $label ) {
	return array(
		'id'             => $field_id,
		'type'           => 'checkbox',
		'label'          => $label,
		'field_type'     => 'advanced',
		'required'       => 'false',
		'class'          => array( 'form-row-wide' ),
		'priority'       => 10,
		'is_wfacp_field' => true,
		'allow_delete'   => true,
	);
}

// Cart rules (section-level).
$rule_definitions = array(
	'rule-cart-total-lte'      => array(
		'section_name' => 'Rule: Cart Total LTE',
		'condition'    => fkcf_web_build_condition( 'cart', 'cart_total_lte', '100' ),
	),
	'rule-cart-total-gte'      => array(
		'section_name' => 'Rule: Cart Total GTE',
		'condition'    => fkcf_web_build_condition( 'cart', 'cart_total_gte', '50' ),
	),
	'rule-cart-item-count-gte' => array(
		'section_name' => 'Rule: Cart Item Count GTE',
		'condition'    => fkcf_web_build_condition( 'cart', 'cart_item_count_gte', '1' ),
	),
	'rule-user-logged-in-eq'   => array(
		'section_name' => 'Rule: User Logged In EQ',
		'condition'    => fkcf_web_build_condition( 'user', 'user_logged_in_eq', '1' ),
	),
	'rule-user-logged-in-ne'   => array(
		'section_name' => 'Rule: User Logged In NE',
		'condition'    => fkcf_web_build_condition( 'user', 'user_logged_in_ne', '1' ),
	),
	'rule-user-role-eq'        => array(
		'section_name' => 'Rule: User Role EQ',
		'condition'    => fkcf_web_build_condition( 'user', 'user_role_eq', 'customer', '', '', '' ),
	),
	'rule-user-role-ne'        => array(
		'section_name' => 'Rule: User Role NE',
		'condition'    => fkcf_web_build_condition( 'user', 'user_role_ne', 'administrator', '', '', '' ),
	),
	'rule-field-country-us'    => array(
		'section_name' => 'Rule: Field Country US',
		'condition'    => fkcf_web_build_condition( 'field', 'value_eq', 'US', '', '', 'billing_country' ),
	),
);

// Field-based sections: Company non-empty -> GST | Country US + Company -> SSN | Checkbox -> show section.
$field_based_definitions = array(
	'rule-field-company-gst'         => array(
		'section_name' => 'Rule: Company Non-Empty → GST',
		'field_id'     => 'advanced_fkcf_gst_number',
		'field_label'  => 'GST Number',
		'field_type'   => 'text',
		'rule_groups'  => array( array( fkcf_web_build_condition( 'field', 'value_not_empty', '', '', '', 'billing_company' ) ) ),
	),
	'rule-field-country-company-ssn' => array(
		'section_name' => 'Rule: Country US + Company → SSN',
		'field_id'     => 'advanced_fkcf_ssn_number',
		'field_label'  => 'SSN Number',
		'field_type'   => 'text',
		'rule_groups'  => array(
			array(
				fkcf_web_build_condition( 'field', 'value_eq', 'US', '', '', 'billing_country' ),
				fkcf_web_build_condition( 'field', 'value_not_empty', '', '', '', 'billing_company' ),
			),
		),
	),
	'rule-checkbox-show-section'     => array(
		'section_name'   => 'Rule: Checkbox Show Section',
		'field_id'       => 'advanced_fkcf_has_company',
		'field_label'    => 'I have a company',
		'field_type'     => 'checkbox',
		'section_rule'   => array( array( fkcf_web_build_condition( 'field', 'value_not_empty', '', '', '', 'advanced_fkcf_has_company' ) ) ),
		'extra_field_id' => 'advanced_fkcf_company_details',
		'extra_label'    => 'Company Details (shown when checkbox checked)',
	),
);

// Add operand-based rules if data exists.
if ( function_exists( 'wc_get_products' ) ) {
	$products = wc_get_products(
		array(
			'limit'  => 1,
			'return' => 'ids',
		)
	);
	if ( ! empty( $products ) ) {
		$rule_definitions['rule-cart-contains-product'] = array(
			'section_name' => 'Rule: Cart Contains Product',
			'condition'    => fkcf_web_build_condition( 'cart', 'cart_contains', '', 'product', (string) $products[0], '' ),
		);
	}
}
$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'number'     => 1,
	)
);
if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
	$rule_definitions['rule-cart-contains-category'] = array(
		'section_name' => 'Rule: Cart Contains Category',
		'condition'    => fkcf_web_build_condition( 'cart', 'cart_contains', '', 'category', (string) $categories[0]->term_id, '' ),
	);
}
$tags = get_terms(
	array(
		'taxonomy'   => 'product_tag',
		'hide_empty' => false,
		'number'     => 1,
	)
);
if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
	$rule_definitions['rule-cart-contains-tag'] = array(
		'section_name' => 'Rule: Cart Contains Tag',
		'condition'    => fkcf_web_build_condition( 'cart', 'cart_contains', '', 'tag', (string) $tags[0]->term_id, '' ),
	);
}
$coupons = get_posts(
	array(
		'post_type'      => 'shop_coupon',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	)
);
if ( ! empty( $coupons ) ) {
	$coupon_code = get_the_title( $coupons[0]->ID );
	if ( ! empty( $coupon_code ) ) {
		$rule_definitions['rule-cart-coupon-contains'] = array(
			'section_name' => 'Rule: Cart Coupon Contains',
			'condition'    => fkcf_web_build_condition( 'cart', 'cart_coupon_contains', '', 'coupon', $coupon_code, '' ),
		);
	}
}

// Rules for existing billing/shipping fields (use existing field IDs).
$existing_field_rules = array(
	'billing_first_name' => array( fkcf_web_build_condition( 'cart', 'cart_total_lte', '100' ) ),
	'billing_last_name'  => array( fkcf_web_build_condition( 'user', 'user_logged_in_eq', '1' ) ),
	'shipping_address_1' => array( fkcf_web_build_condition( 'cart', 'cart_item_count_gte', '1' ) ),
);

$page_id = $checkout_id;
$layout  = WFACP_Common_Helper::get_page_layout( $page_id );

if ( empty( $layout['fieldsets']['single_step'] ) ) {
	wp_die( esc_html__( 'Layout has no single_step fieldsets.', 'woofunnels-aero-checkout' ) );
}

$custom_fields = get_post_meta( $page_id, '_wfacp_page_custom_field', true );
$custom_fields = is_array( $custom_fields ) ? $custom_fields : array( 'advanced' => array() );
if ( ! isset( $custom_fields['advanced'] ) ) {
	$custom_fields['advanced'] = array();
}

$new_sections  = array();
$section_index = 0;
$rules_data    = array(
	'version'  => '2.0',
	'sections' => array(),
);

// Admin uses single_step_fieldset_{index} as section ID - we must match for rules to display.
$single_step = $layout['fieldsets']['single_step'];
$insert_idx  = 1;
$base_index  = $insert_idx;

// 1. Company Option section (checkbox always visible) - must come before checkbox-dependent section.
$new_sections[] = array(
	'name'        => 'Company Option',
	'class'       => '',
	'is_default'  => 'no',
	'sub_heading' => 'Checkbox to show section below when checked',
	'fields'      => array( fkcf_web_build_checkbox_field( 'advanced_fkcf_has_company', 'I have a company' ) ),
);
$custom_fields['advanced']['advanced_fkcf_has_company'] = fkcf_web_build_checkbox_field( 'advanced_fkcf_has_company', 'I have a company' );
$admin_section_company                                  = 'single_step_fieldset_' . ( $base_index + $section_index );
$rules_data['sections'][ $admin_section_company ]       = array(
	'section_rule' => null,
	'field_rules'  => array(),
);
++$section_index;

// 2. Cart/User/Field section rules.
foreach ( $rule_definitions as $rule_key => $def ) {
	$section_name     = $def['section_name'];
	$field_id         = 'advanced_fkcf_' . str_replace( '-', '_', $rule_key );
	$admin_section_id = 'single_step_fieldset_' . ( $base_index + $section_index );

	$new_sections[] = array(
		'name'        => $section_name,
		'class'       => '',
		'is_default'  => 'no',
		'sub_heading' => 'Validates: ' . $section_name,
		'fields'      => array( fkcf_web_build_custom_field( $field_id, $section_name . ' (Field)' ) ),
	);

	$custom_fields['advanced'][ $field_id ] = fkcf_web_build_custom_field( $field_id, $section_name . ' (Field)' );

	$rules_data['sections'][ $admin_section_id ]                 = array(
		'section_rule' => null,
		'field_rules'  => array(),
	);
	$rules_data['sections'][ $admin_section_id ]['section_rule'] = array(
		'section_id'    => $admin_section_id,
		'section_label' => $section_name,
		'enabled'       => 1,
		'action'        => 'show',
		'group_logic'   => 'OR',
		'rule_groups'   => array( array( $def['condition'] ) ),
	);

	if ( ! isset( $rules_data['sections']['advanced'] ) ) {
		$rules_data['sections']['advanced'] = array(
			'section_rule' => null,
			'field_rules'  => array(),
		);
	}
	$rules_data['sections']['advanced']['field_rules'][ $field_id ] = array(
		'field_id'    => $field_id,
		'enabled'     => 1,
		'action'      => 'show',
		'group_logic' => 'OR',
		'rule_groups' => array( array( $def['condition'] ) ),
	);

	++$section_index;
}

// 3. Field-based sections: Company→GST, Country+Company→SSN, Checkbox→Section.
foreach ( $field_based_definitions as $key => $def ) {
	$admin_section_id = 'single_step_fieldset_' . ( $base_index + $section_index );

	if ( 'rule-checkbox-show-section' === $key ) {
		// Section rule: show when checkbox has value. Contains extra field.
		$new_sections[]                                      = array(
			'name'        => $def['section_name'],
			'class'       => '',
			'is_default'  => 'no',
			'sub_heading' => 'Shown when "I have a company" checkbox is checked',
			'fields'      => array( fkcf_web_build_custom_field( $def['extra_field_id'], $def['extra_label'] ) ),
		);
		$custom_fields['advanced'][ $def['extra_field_id'] ] = fkcf_web_build_custom_field( $def['extra_field_id'], $def['extra_label'] );

		$rules_data['sections'][ $admin_section_id ]                 = array(
			'section_rule' => null,
			'field_rules'  => array(),
		);
		$rules_data['sections'][ $admin_section_id ]['section_rule'] = array(
			'section_id'    => $admin_section_id,
			'section_label' => $def['section_name'],
			'enabled'       => 1,
			'action'        => 'show',
			'group_logic'   => 'OR',
			'rule_groups'   => $def['section_rule'],
		);
	} else {
		// Field rule: show GST/SSN when conditions met (uses existing billing_company, billing_country).
		$field_def                                     = 'text' === $def['field_type'] ? fkcf_web_build_custom_field( $def['field_id'], $def['field_label'] ) : fkcf_web_build_checkbox_field( $def['field_id'], $def['field_label'] );
		$new_sections[]                                = array(
			'name'        => $def['section_name'],
			'class'       => '',
			'is_default'  => 'no',
			'sub_heading' => 'Uses existing billing_company / billing_country',
			'fields'      => array( $field_def ),
		);
		$custom_fields['advanced'][ $def['field_id'] ] = $field_def;

		$rules_data['sections'][ $admin_section_id ] = array(
			'section_rule' => null,
			'field_rules'  => array(),
		);
		if ( ! isset( $rules_data['sections']['advanced'] ) ) {
			$rules_data['sections']['advanced'] = array(
				'section_rule' => null,
				'field_rules'  => array(),
			);
		}
		$rules_data['sections']['advanced']['field_rules'][ $def['field_id'] ] = array(
			'field_id'    => $def['field_id'],
			'enabled'     => 1,
			'action'      => 'show',
			'group_logic' => count( $def['rule_groups'][0] ) > 1 ? 'AND' : 'OR',
			'rule_groups' => $def['rule_groups'],
		);
	}
	++$section_index;
}

// 4. Add rules to existing billing/shipping fields.
foreach ( $existing_field_rules as $field_id => $conditions ) {
	if ( ! isset( $rules_data['sections']['billing'] ) ) {
		$rules_data['sections']['billing'] = array(
			'section_rule' => null,
			'field_rules'  => array(),
		);
	}
	if ( strpos( $field_id, 'shipping_' ) === 0 && ! isset( $rules_data['sections']['shipping'] ) ) {
		$rules_data['sections']['shipping'] = array(
			'section_rule' => null,
			'field_rules'  => array(),
		);
	}
	$section = strpos( $field_id, 'shipping_' ) === 0 ? 'shipping' : 'billing';
	$rules_data['sections'][ $section ]['field_rules'][ $field_id ] = array(
		'field_id'    => $field_id,
		'enabled'     => 1,
		'action'      => 'show',
		'group_logic' => 'OR',
		'rule_groups' => array( $conditions ),
	);
}

$layout['fieldsets']['single_step'] = array_merge( array_slice( $single_step, 0, $insert_idx ), $new_sections, array_slice( $single_step, $insert_idx ) );

update_post_meta( $page_id, '_wfacp_page_layout', $layout );
update_post_meta( $page_id, '_wfacp_page_custom_field', $custom_fields );

$prepared = WFACP_Common::prepare_fieldset( $layout );
if ( ! empty( $prepared['checkout_fields'] ) ) {
	update_post_meta( $page_id, '_wfacp_checkout_fields', $prepared['checkout_fields'] );
}
if ( ! empty( $prepared['fieldsets'] ) ) {
	$layout['fieldsets'] = $prepared['fieldsets'];
	update_post_meta( $page_id, '_wfacp_page_layout', $layout );
}

update_post_meta( $page_id, '_fkcf_conditional_rules', $rules_data );

if ( class_exists( '\FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Cache_Manager' ) ) {
	\FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Cache_Manager::delete_all_conditional_fields_cache( $page_id );
}

// Sync REST format for WordPress backend / admin UI.
$rest_format = apply_filters( 'wfacp_field_conditions', array(), $page_id );
if ( ! empty( $rest_format ) && is_array( $rest_format ) ) {
	update_post_meta( $page_id, '_fkcf_field_conditions_rest', $rest_format );
}

$field_rules_count = isset( $rules_data['sections']['advanced']['field_rules'] ) ? count( $rules_data['sections']['advanced']['field_rules'] ) : 0;

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>FKCF Test Data Generated</title>
	<style>
		body { font-family: system-ui, sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
		.success { color: #0a0; }
		.info { color: #666; margin-top: 1rem; }
		a { color: #0073aa; }
	</style>
</head>
<body>
	<h1 class="success">✓ FKCF test data generated</h1>
	<p>Checkout ID: <strong><?php echo esc_html( (string) $page_id ); ?></strong></p>
	<p>Sections: <?php echo esc_html( (string) count( $new_sections ) ); ?> | Field rules: <?php echo esc_html( (string) $field_rules_count ); ?></p>
	<p class="info">
		<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>">View checkout page</a>
	</p>
</body>
</html>
