<?php
/**
 * Admin_Pages
 *
 * Handles admin page creation and rendering.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Admin
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin_Pages class.
 *
 * @since 2.0.0
 */
class Admin_Pages {

	/**
	 * Singleton instance.
	 *
	 * @var Admin_Pages
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Admin_Pages
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
	}

	/**
	 * Add plugin admin menu.
	 *
	 * @since 2.0.0
	 */
	public function add_plugin_admin_menu() {
		// For administrators only - WordPress will handle the capability check.
		add_options_page(
			__( 'FunnelKit Checkout Field Editor', 'woofunnels-aero-checkout' ),
			__( 'FunnelKit Checkout Fields', 'woofunnels-aero-checkout' ),
			'manage_options',  // Standard WordPress administrator capability.
			'fkcf-settings',
			array( $this, 'display_settings_page' )
		);
	}

	/**
	 * Display settings page.
	 *
	 * @since 2.0.0
	 */
	public function display_settings_page() {
		// Check user capability - must be Administrator.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woofunnels-aero-checkout' ) );
		}

		// Get all checkout pages.
		$checkouts = $this->get_checkout_pages();

		// Get active checkout (from GET parameter or first checkout).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_checkout_id = isset( $_GET['checkout_id'] ) ? absint( $_GET['checkout_id'] ) : 0;

		if ( ! $active_checkout_id && ! empty( $checkouts ) ) {
			$active_checkout_id = $checkouts[0]->ID;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$embedded = isset( $_GET['embedded'] ) && '1' === $_GET['embedded'];

		// Get sections for this checkout.
		$checkout_sections = $this->get_checkout_sections( $active_checkout_id );

		if ( $embedded ) {
			include_once FKCF_PLUGIN_DIR . 'includes/admin/views/settings-page-embedded.php';
		} else {
			// Load view - Phase 2 Accordion UI.
			include_once FKCF_PLUGIN_DIR . 'includes/admin/views/settings-page-v2.php';
		}
	}

	/**
	 * Get sections for a checkout page.
	 *
	 * @since 2.3.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array Array of sections with IDs and labels.
	 */
	public function get_checkout_sections( $checkout_id ) {
		if ( ! $checkout_id ) {
			return array();
		}

		// Get page layout which contains custom section names in fieldsets.
		$page_layout = get_post_meta( $checkout_id, '_wfacp_page_layout', true );

		$this->debug_log( 'get_checkout_sections: checkout_id=' . $checkout_id );

		if ( empty( $page_layout ) || ! is_array( $page_layout ) || ! isset( $page_layout['fieldsets'] ) ) {
			// Return default sections if no layout data.
			$this->debug_log( 'get_checkout_sections: No page layout data, using defaults' );
			return array(
				'billing'  => __( 'Billing Information', 'woofunnels-aero-checkout' ),
				'shipping' => __( 'Shipping Address', 'woofunnels-aero-checkout' ),
				'account'  => __( 'Account Details', 'woofunnels-aero-checkout' ),
				'order'    => __( 'Order Notes', 'woofunnels-aero-checkout' ),
				'advanced' => __( 'Additional Fields', 'woofunnels-aero-checkout' ),
			);
		}

		// Iterate over all steps so every step's sections are shown (Step 1, Step 2, Step2.1, etc.).
		$sections = array();
		$this->debug_log( 'get_checkout_sections: steps=' . implode( ', ', array_keys( $page_layout['fieldsets'] ) ) );

		foreach ( $page_layout['fieldsets'] as $step_key => $fieldsets ) {
			if ( ! is_array( $fieldsets ) ) {
				continue;
			}

			$this->debug_log( 'get_checkout_sections: step "' . $step_key . '" has ' . count( $fieldsets ) . ' fieldsets' );

			// Each fieldset is a separate section in the accordion.
			// Use step_key + index so section IDs are unique across all steps.
			foreach ( $fieldsets as $index => $fieldset ) {
				if ( ! is_array( $fieldset ) ) {
					continue;
				}

				// Get custom section name.
				$section_name = isset( $fieldset['name'] ) ? $fieldset['name'] : 'Section ' . ( $index + 1 );

				// Unique key across all steps (e.g. single_step_fieldset_0, third_step_fieldset_1).
				$section_key = $step_key . '_fieldset_' . $index;

				$this->debug_log( 'get_checkout_sections: Fieldset ' . $section_key . ': name="' . $section_name . '"' );

				$sections[ $section_key ] = $section_name;
			}
		}

		$this->debug_log( 'get_checkout_sections: Returning ' . count( $sections ) . ' sections' );
		return $sections;
	}

	/**
	 * Get all checkout pages.
	 *
	 * @since 2.0.0
	 * @return array Array of checkout posts.
	 */
	private function get_checkout_pages() {
		$args = array(
			'post_type'      => 'wfacp_checkout',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$checkouts = get_posts( $args );

		return $checkouts ? $checkouts : array();
	}

	/**
	 * Log debug message to plugin debug file.
	 *
	 * @since 2.1.0
	 * @param string $message Message to log.
	 */
	private function debug_log( $message ) {
		// Debug logging disabled.
	}

	/**
	 * Get checkout fields for a checkout page.
	 *
	 * @since 2.0.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array Array of fields.
	 */
	public function get_checkout_fields( $checkout_id ) {
		if ( ! $checkout_id ) {
			$this->debug_log( 'get_checkout_fields called with no checkout_id' );
			return array();
		}

		$this->debug_log( 'get_checkout_fields called with checkout_id: ' . $checkout_id );

		// Get page layout which contains fieldsets with fields.
		$page_layout = get_post_meta( $checkout_id, '_wfacp_page_layout', true );

		if ( empty( $page_layout ) || ! is_array( $page_layout ) || ! isset( $page_layout['fieldsets'] ) ) {
			$this->debug_log( 'No page layout data, falling back to default fields' );
			return $this->get_default_checkout_fields();
		}

		// Iterate all steps so fields from every section (all steps) are included.
		$all_fields = array();

		foreach ( $page_layout['fieldsets'] as $step_key => $fieldsets ) {
			if ( ! is_array( $fieldsets ) ) {
				continue;
			}

			$this->debug_log( 'get_checkout_fields: step "' . $step_key . '" has ' . count( $fieldsets ) . ' fieldsets' );

			foreach ( $fieldsets as $index => $fieldset ) {
				if ( ! is_array( $fieldset ) || ! isset( $fieldset['fields'] ) || ! is_array( $fieldset['fields'] ) ) {
					continue;
				}

				// Match section key format used in get_checkout_sections().
				$section_key = $step_key . '_fieldset_' . $index;

				$this->debug_log( 'Processing ' . $section_key . ' with ' . count( $fieldset['fields'] ) . ' fields' );

				foreach ( $fieldset['fields'] as $field ) {
					if ( ! is_array( $field ) || ! isset( $field['id'] ) ) {
						continue;
					}

					$field_id = $field['id'];
					$label    = $this->get_field_label( $field_id, $field );
					$type     = isset( $field['type'] ) ? $field['type'] : 'text';

					$all_fields[] = array(
						'id'      => $field_id,
						'label'   => $label,
						'section' => $section_key,
						'type'    => $type,
					);

					$this->debug_log( 'Added field ' . $field_id . ' to section ' . $section_key );
				}
			}
		}

		$this->debug_log( 'get_checkout_fields: Returning ' . count( $all_fields ) . ' fields' );
		return $all_fields;
	}

	/**
	 * Parse WFACP fields structure into our format.
	 *
	 * @since 2.0.7
	 * @param array $wfacp_fields Fields from WFACP.
	 * @return array Parsed fields.
	 */
	private function parse_wfacp_fields( $wfacp_fields ) {
		$all_fields = array();

		$this->debug_log( 'parse_wfacp_fields received ' . count( $wfacp_fields ) . ' sections: ' . implode( ', ', array_keys( $wfacp_fields ) ) );

		// Parse all sections (billing, shipping, advanced, etc.).
		foreach ( $wfacp_fields as $section_key => $section_fields ) {
			if ( ! is_array( $section_fields ) ) {
				$this->debug_log( "Section $section_key is not an array, skipping" );
				continue;
			}

			$this->debug_log( "Processing section $section_key with " . count( $section_fields ) . ' items' );

			$section_name = $this->get_section_label( $section_key );

			// Fields are stored directly in the section.
			foreach ( $section_fields as $field_id => $field_data ) {
				// Skip if not array or if it's metadata.
				if ( ! is_array( $field_data ) ) {
					$this->debug_log( "Field $field_id in section $section_key is not an array, skipping" );
					continue;
				}

				if ( ! isset( $field_data['type'] ) ) {
					$this->debug_log( "Field $field_id in section $section_key has no type, skipping" );
					continue;
				}

				// Get field label and type.
				$label = $this->get_field_label( $field_id, $field_data );
				$type  = isset( $field_data['type'] ) ? $field_data['type'] : 'text';

				$all_fields[] = array(
					'id'      => $field_id,
					'label'   => $label,
					'section' => $section_key,
					'type'    => $type,
				);

				$this->debug_log( "Added field $field_id (type: $type, label: $label)" );
			}
		}

		$this->debug_log( 'parse_wfacp_fields total fields parsed: ' . count( $all_fields ) );

		return $all_fields;
	}

	/**
	 * Get label for a field.
	 *
	 * @since 2.0.5
	 * @param string $field_id Field ID.
	 * @param array  $field_data Field data.
	 * @return string Field label.
	 */
	private function get_field_label( $field_id, $field_data ) {
		if ( isset( $field_data['label'] ) && ! empty( $field_data['label'] ) ) {
			return $field_data['label'];
		}
		if ( isset( $field_data['field_label'] ) && ! empty( $field_data['field_label'] ) ) {
			return $field_data['field_label'];
		}
		if ( isset( $field_data['placeholder'] ) && ! empty( $field_data['placeholder'] ) ) {
			return $field_data['placeholder'];
		}

		return ucwords( str_replace( array( '_', '-' ), ' ', $field_id ) );
	}

	/**
	 * Check if field is in checkout's saved fields.
	 *
	 * @since 2.0.5
	 * @param string $field_id Field ID.
	 * @param array  $saved_fields Saved fields array.
	 * @return bool True if field is in checkout.
	 */
	private function is_field_in_checkout( $field_id, $saved_fields ) {
		if ( empty( $saved_fields ) || ! is_array( $saved_fields ) ) {
			return false;
		}

		foreach ( $saved_fields as $section_fields ) {
			if ( is_array( $section_fields ) && isset( $section_fields[ $field_id ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all available fields from WFACP global registry.
	 *
	 * @since 2.0.5
	 * @return array Array of all available fields.
	 */
	private function get_wfacp_global_fields() {
		$all_fields = array();

		if ( ! class_exists( 'WFACP_Common' ) ) {
			return $all_fields;
		}

		// Try different methods to get field registry.
		$methods_to_try = array(
			'get_fieldsets',
			'get_advanced_fields',
			'get_single_step_fields',
			'get_default_fields',
		);

		foreach ( $methods_to_try as $method ) {
			if ( method_exists( 'WFACP_Common', $method ) ) {
				$fields = call_user_func( array( 'WFACP_Common', $method ) );

				if ( is_array( $fields ) ) {
					foreach ( $fields as $field_key => $field_data ) {
						if ( is_array( $field_data ) ) {
							$all_fields[] = array(
								'id'      => $field_key,
								'label'   => $this->get_field_label( $field_key, $field_data ),
								'section' => isset( $field_data['section'] ) ? $field_data['section'] : 'general',
								'type'    => isset( $field_data['type'] ) ? $field_data['type'] : 'text',
							);
						}
					}
				}
			}
		}

		return $all_fields;
	}

	/**
	 * Get readable section label.
	 *
	 * @since 2.0.4
	 * @param string $section_key Section key.
	 * @return string Readable section label.
	 */
	private function get_section_label( $section_key ) {
		$section_labels = array(
			'single_step' => __( 'Single Step', 'woofunnels-aero-checkout' ),
			'two_step'    => __( 'Step 1', 'woofunnels-aero-checkout' ),
			'third_step'  => __( 'Step 2', 'woofunnels-aero-checkout' ),
			'billing'     => __( 'Billing', 'woofunnels-aero-checkout' ),
			'shipping'    => __( 'Shipping', 'woofunnels-aero-checkout' ),
			'advanced'    => __( 'Advanced', 'woofunnels-aero-checkout' ),
			'order'       => __( 'Order', 'woofunnels-aero-checkout' ),
			'custom'      => __( 'Custom Fields', 'woofunnels-aero-checkout' ),
		);

		return isset( $section_labels[ $section_key ] ) ? $section_labels[ $section_key ] : ucwords( str_replace( '_', ' ', $section_key ) );
	}

	/**
	 * Get default checkout fields.
	 *
	 * @since 2.0.0
	 * @return array Array of default fields.
	 */
	private function get_default_checkout_fields() {
		return array(
			array(
				'id'      => 'billing_first_name',
				'label'   => __( 'First Name', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_last_name',
				'label'   => __( 'Last Name', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_company',
				'label'   => __( 'Company Name', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_email',
				'label'   => __( 'Email Address', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'email',
			),
			array(
				'id'      => 'billing_phone',
				'label'   => __( 'Phone', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'tel',
			),
			array(
				'id'      => 'billing_country',
				'label'   => __( 'Country', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'select',
			),
			array(
				'id'      => 'billing_address_1',
				'label'   => __( 'Street Address', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_city',
				'label'   => __( 'Town / City', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
			array(
				'id'      => 'billing_state',
				'label'   => __( 'State', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'select',
			),
			array(
				'id'      => 'billing_postcode',
				'label'   => __( 'ZIP / Postcode', 'woofunnels-aero-checkout' ),
				'section' => 'billing',
				'type'    => 'text',
			),
		);
	}
}
