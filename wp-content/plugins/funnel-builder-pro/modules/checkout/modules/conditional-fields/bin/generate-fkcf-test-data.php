<?php
/**
 * Generate FKCF Conditional Fields Test Data
 *
 * Creates a checkout page with sections and custom fields for every rule type,
 * plus section-level and field-level rules for comprehensive testing.
 *
 * Run via: wp eval-file wp-content/plugins/funnel-builder-pro/modules/checkout/modules/conditional-fields/bin/generate-fkcf-test-data.php
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields
 */

// Load WordPress if not already loaded (for wp eval-file or direct PHP).
if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( __DIR__, 8 ) . '/wp-load.php';
	if ( ! file_exists( $wp_load ) ) {
		$wp_load = dirname( __DIR__, 7 ) . '/wp-load.php';
	}
	if ( file_exists( $wp_load ) ) {
		require_once $wp_load;
	}
}

// Output helper for CLI and non-CLI contexts.
$log     = function ( $msg, $is_error = false ) {
	if ( class_exists( 'WP_CLI' ) ) {
		$is_error ? WP_CLI::error( $msg, false ) : WP_CLI::log( $msg );
	} else {
		echo ( $is_error ? 'ERROR: ' : '' ) . $msg . "\n";
		if ( $is_error ) {
			exit( 1 );
		}
	}
};
$success = function ( $msg ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::success( $msg );
	} else {
		echo "SUCCESS: $msg\n";
	}
};

// Ensure required classes exist.
if ( ! class_exists( 'WFACP_Common' ) || ! class_exists( 'WFACP_Common_Helper' ) ) {
	$log( 'Funnel Builder Checkout plugin must be active.', true );
}


/**
 * Build condition array for FKCF storage.
 *
 * @param string $type        Condition type (cart, user, field).
 * @param string $operator    FKCF operator.
 * @param string $value       Value for value-based operators.
 * @param string $operand_type Operand type (product, category, tag, coupon).
 * @param string $operand     Operand ID(s) for operand-based operators.
 * @param string $field_id    Field ID for field-based conditions.
 * @return array Condition data.
 */
function fkcf_build_condition( $type, $operator, $value = '', $operand_type = '', $operand = '', $field_id = '' ) {
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
function fkcf_section_key( $name ) {
	$base = preg_replace( '/-\d+$/', '', strtolower( sanitize_title( $name ) ) );
	return $base;
}

/**
 * Build a custom text field definition for layout.
 *
 * @param string $field_id   Field ID.
 * @param string $label      Field label.
 * @return array Field definition.
 */
function fkcf_build_custom_field( $field_id, $label ) {
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

function fkcf_build_checkbox_field( $field_id, $label ) {
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

// Cart, User, Field section rules.
$rule_definitions = array(
	'rule-cart-total-lte'      => array(
		'section_name' => 'Rule: Cart Total LTE',
		'condition'    => fkcf_build_condition( 'cart', 'cart_total_lte', '100' ),
	),
	'rule-cart-total-gte'      => array(
		'section_name' => 'Rule: Cart Total GTE',
		'condition'    => fkcf_build_condition( 'cart', 'cart_total_gte', '50' ),
	),
	'rule-cart-item-count-gte' => array(
		'section_name' => 'Rule: Cart Item Count GTE',
		'condition'    => fkcf_build_condition( 'cart', 'cart_item_count_gte', '1' ),
	),
	'rule-user-logged-in-eq'   => array(
		'section_name' => 'Rule: User Logged In EQ',
		'condition'    => fkcf_build_condition( 'user', 'user_logged_in_eq', '1' ),
	),
	'rule-user-logged-in-ne'   => array(
		'section_name' => 'Rule: User Logged In NE',
		'condition'    => fkcf_build_condition( 'user', 'user_logged_in_ne', '1' ),
	),
	'rule-user-role-eq'        => array(
		'section_name' => 'Rule: User Role EQ',
		'condition'    => fkcf_build_condition( 'user', 'user_role_eq', 'customer', '', '', '' ),
	),
	'rule-user-role-ne'        => array(
		'section_name' => 'Rule: User Role NE',
		'condition'    => fkcf_build_condition( 'user', 'user_role_ne', 'administrator', '', '', '' ),
	),
	'rule-field-country-us'    => array(
		'section_name' => 'Rule: Field Country US',
		'condition'    => fkcf_build_condition( 'field', 'value_eq', 'US', '', '', 'billing_country' ),
	),
);

// Field-based: Company→GST, Country+Company→SSN, Checkbox→Section.
$field_based_definitions = array(
	'rule-field-company-gst'         => array(
		'section_name' => 'Rule: Company Non-Empty → GST',
		'field_id'     => 'advanced_fkcf_gst_number',
		'field_label'  => 'GST Number',
		'field_type'   => 'text',
		'rule_groups'  => array( array( fkcf_build_condition( 'field', 'value_not_empty', '', '', '', 'billing_company' ) ) ),
	),
	'rule-field-country-company-ssn' => array(
		'section_name' => 'Rule: Country US + Company → SSN',
		'field_id'     => 'advanced_fkcf_ssn_number',
		'field_label'  => 'SSN Number',
		'field_type'   => 'text',
		'rule_groups'  => array(
			array(
				fkcf_build_condition( 'field', 'value_eq', 'US', '', '', 'billing_country' ),
				fkcf_build_condition( 'field', 'value_not_empty', '', '', '', 'billing_company' ),
			),
		),
	),
	'rule-checkbox-show-section'     => array(
		'section_name'   => 'Rule: Checkbox Show Section',
		'field_id'       => 'advanced_fkcf_has_company',
		'field_label'    => 'I have a company',
		'field_type'     => 'checkbox',
		'section_rule'   => array( array( fkcf_build_condition( 'field', 'value_not_empty', '', '', '', 'advanced_fkcf_has_company' ) ) ),
		'extra_field_id' => 'advanced_fkcf_company_details',
		'extra_label'    => 'Company Details (shown when checkbox checked)',
	),
);

// Rules for existing billing/shipping fields.
$existing_field_rules = array(
	'billing_first_name' => array( fkcf_build_condition( 'cart', 'cart_total_lte', '100' ) ),
	'billing_last_name'  => array( fkcf_build_condition( 'user', 'user_logged_in_eq', '1' ) ),
	'shipping_address_1' => array( fkcf_build_condition( 'cart', 'cart_item_count_gte', '1' ) ),
);

// Operand-based rules (need product/category/tag/coupon IDs - add dynamically).
$operand_rules = array();

// Get first product ID for cart_contains product.
$products = wc_get_products(
	array(
		'limit'  => 1,
		'return' => 'ids',
	)
);
if ( ! empty( $products ) ) {
	$operand_rules['rule-cart-contains-product'] = array(
		'section_name' => 'Rule: Cart Contains Product',
		'condition'    => fkcf_build_condition( 'cart', 'cart_contains', '', 'product', (string) $products[0], '' ),
	);
}

// Get first product category.
$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'number'     => 1,
	)
);
if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
	$operand_rules['rule-cart-contains-category'] = array(
		'section_name' => 'Rule: Cart Contains Category',
		'condition'    => fkcf_build_condition( 'cart', 'cart_contains', '', 'category', (string) $categories[0]->term_id, '' ),
	);
}

// Get first product tag.
$tags = get_terms(
	array(
		'taxonomy'   => 'product_tag',
		'hide_empty' => false,
		'number'     => 1,
	)
);
if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
	$operand_rules['rule-cart-contains-tag'] = array(
		'section_name' => 'Rule: Cart Contains Tag',
		'condition'    => fkcf_build_condition( 'cart', 'cart_contains', '', 'tag', (string) $tags[0]->term_id, '' ),
	);
}

// Get first coupon code for cart_coupon_contains (operand is coupon code, not ID).
$coupons = get_posts(
	array(
		'post_type'      => 'shop_coupon',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	)
);
if ( ! empty( $coupons ) && function_exists( 'wc_get_coupon_id_by_code' ) ) {
	$coupon_code = get_the_title( $coupons[0]->ID );
	if ( ! empty( $coupon_code ) ) {
		$operand_rules['rule-cart-coupon-contains'] = array(
			'section_name' => 'Rule: Cart Coupon Contains',
			'condition'    => fkcf_build_condition( 'cart', 'cart_coupon_contains', '', 'coupon', $coupon_code, '' ),
		);
	}
}

$rule_definitions = array_merge( $rule_definitions, $operand_rules );

// Use CHECKOUT_ID from env if set; otherwise find or create.
$env_checkout_id = getenv( 'CHECKOUT_ID' );
if ( ! empty( $env_checkout_id ) && absint( $env_checkout_id ) > 0 ) {
	$post = get_post( absint( $env_checkout_id ) );
	if ( $post && 'wfacp_checkout' === $post->post_type ) {
		$page_id = $post->ID;
		$log( 'Using checkout ID from CHECKOUT_ID: ' . $page_id );
	} else {
		$log( 'Invalid CHECKOUT_ID or post type. Falling back to find/create.' );
		$page_id = null;
	}
} else {
	$page_id = null;
}

if ( ! $page_id ) {
	$checkout_pages = get_posts(
		array(
			'post_type'      => 'wfacp_checkout',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		)
	);

	if ( empty( $checkout_pages ) ) {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'FKCF Test Checkout - Conditional Fields',
				'post_type'    => 'wfacp_checkout',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		if ( is_wp_error( $page_id ) ) {
			$log( 'Failed to create checkout page: ' . $page_id->get_error_message(), true );
		}
		$log( 'Created new checkout page ID: ' . $page_id );
	} else {
		$page_id = $checkout_pages[0]->ID;
		$log( 'Using existing checkout page ID: ' . $page_id );
	}
}

// Get default layout.
$layout = WFACP_Common_Helper::get_page_layout( $page_id );

if ( empty( $layout['fieldsets']['single_step'] ) ) {
	$log( 'Layout has no single_step fieldsets.', true );
}

// Build custom field definitions for _wfacp_page_custom_field.
$custom_fields = get_post_meta( $page_id, '_wfacp_page_custom_field', true );
if ( ! is_array( $custom_fields ) ) {
	$custom_fields = array( 'advanced' => array() );
}
if ( ! isset( $custom_fields['advanced'] ) ) {
	$custom_fields['advanced'] = array();
}

// New sections and fields to add.
$new_sections  = array();
$section_index = 0;
$rules_data    = array(
	'version'  => '2.0',
	'sections' => array(),
);

// Admin uses single_step_fieldset_{index} as section ID.
$single_step = $layout['fieldsets']['single_step'];
$insert_idx  = 1;
$base_index  = $insert_idx;

// 1. Company Option section (checkbox always visible).
$new_sections[] = array(
	'name'        => 'Company Option',
	'class'       => '',
	'is_default'  => 'no',
	'sub_heading' => 'Checkbox to show section below when checked',
	'fields'      => array( fkcf_build_checkbox_field( 'advanced_fkcf_has_company', 'I have a company' ) ),
);
$custom_fields['advanced']['advanced_fkcf_has_company']                               = fkcf_build_checkbox_field( 'advanced_fkcf_has_company', 'I have a company' );
$rules_data['sections'][ 'single_step_fieldset_' . ( $base_index + $section_index ) ] = array(
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
		'fields'      => array( fkcf_build_custom_field( $field_id, $section_name . ' (Field)' ) ),
	);

	$custom_fields['advanced'][ $field_id ] = fkcf_build_custom_field( $field_id, $section_name . ' (Field)' );

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
		$new_sections[]                                      = array(
			'name'        => $def['section_name'],
			'class'       => '',
			'is_default'  => 'no',
			'sub_heading' => 'Shown when "I have a company" checkbox is checked',
			'fields'      => array( fkcf_build_custom_field( $def['extra_field_id'], $def['extra_label'] ) ),
		);
		$custom_fields['advanced'][ $def['extra_field_id'] ] = fkcf_build_custom_field( $def['extra_field_id'], $def['extra_label'] );

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
		$field_def                                     = 'text' === $def['field_type'] ? fkcf_build_custom_field( $def['field_id'], $def['field_label'] ) : fkcf_build_checkbox_field( $def['field_id'], $def['field_label'] );
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
	$section = strpos( $field_id, 'shipping_' ) === 0 ? 'shipping' : 'billing';
	if ( ! isset( $rules_data['sections'][ $section ] ) ) {
		$rules_data['sections'][ $section ] = array(
			'section_rule' => null,
			'field_rules'  => array(),
		);
	}
	$rules_data['sections'][ $section ]['field_rules'][ $field_id ] = array(
		'field_id'    => $field_id,
		'enabled'     => 1,
		'action'      => 'show',
		'group_logic' => 'OR',
		'rule_groups' => array( $conditions ),
	);
}

// Insert new sections after Contact Information, before Shipping Method.
$before                             = array_slice( $single_step, 0, $insert_idx );
$after                              = array_slice( $single_step, $insert_idx );
$layout['fieldsets']['single_step'] = array_merge( $before, $new_sections, $after );

// Update layout meta.
update_post_meta( $page_id, '_wfacp_page_layout', $layout );

// Update custom fields meta.
update_post_meta( $page_id, '_wfacp_page_custom_field', $custom_fields );

// Regenerate checkout fields (prepare_fieldset output).
$prepared = WFACP_Common::prepare_fieldset( $layout );
if ( ! empty( $prepared['checkout_fields'] ) ) {
	update_post_meta( $page_id, '_wfacp_checkout_fields', $prepared['checkout_fields'] );
}
if ( ! empty( $prepared['fieldsets'] ) ) {
	$layout['fieldsets'] = $prepared['fieldsets'];
	update_post_meta( $page_id, '_wfacp_page_layout', $layout );
}

// Save rules.
update_post_meta( $page_id, '_fkcf_conditional_rules', $rules_data );

// Clear conditional fields cache.
if ( class_exists( '\FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Cache_Manager' ) ) {
	\FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Cache_Manager::delete_all_conditional_fields_cache( $page_id );
}

// Sync REST format for WordPress backend / admin UI.
$rest_format = apply_filters( 'wfacp_field_conditions', array(), $page_id );
if ( ! empty( $rest_format ) && is_array( $rest_format ) ) {
	update_post_meta( $page_id, '_fkcf_field_conditions_rest', $rest_format );
}

$success(
	sprintf(
		'Generated FKCF test data: %d sections, %d field rules, %d section rules. Checkout page ID: %d',
		count( $new_sections ),
		count( $rules_data['sections']['advanced']['field_rules'] ?? array() ),
		count( $rule_definitions ),
		$page_id
	)
);
