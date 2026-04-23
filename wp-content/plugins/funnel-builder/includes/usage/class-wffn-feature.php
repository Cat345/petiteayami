<?php
/**
 * Feature Adoption Tracking
 *
 * Collects 32 keys related to feature usage and adoption
 *
 * @package FunnelKit Funnel Builder
 * @since 3.13.1.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Feature' ) ) {

	/**
	 * Class WFFN_Feature
	 */
	class WFFN_Feature {

		/**
		 * Cache for batch-loaded postmeta data
		 *
		 * @var array
		 */
		private $postmeta_cache = array();

		/**
		 * Batch load postmeta for multiple posts
		 * This reduces individual queries from N queries to 1 query per post type
		 *
		 * @param array  $post_ids Array of post IDs
		 * @param array  $meta_keys Array of meta keys to load
		 * @param string $cache_key Unique cache key for this batch
		 *
		 * @return array Array of post_id => array( meta_key => meta_value )
		 */
		private function batch_load_postmeta( $post_ids, $meta_keys, $cache_key = '' ) {
			if ( empty( $post_ids ) || empty( $meta_keys ) ) {
				return array();
			}

			// Use cache key if provided to avoid duplicate queries
			if ( ! empty( $cache_key ) && isset( $this->postmeta_cache[ $cache_key ] ) ) {
				return $this->postmeta_cache[ $cache_key ];
			}

			global $wpdb;

			$post_ids = array_map( 'intval', $post_ids );
			$post_ids = array_unique( $post_ids );

			$meta_keys = array_map( 'esc_sql', $meta_keys );

			// OPTIMIZATION: Use a single query instead of looping queries (MySQL best practice)
			// MySQL can handle large IN clauses efficiently. Process results in PHP to avoid memory issues.
			$post_ids_escaped = array_map( 'absint', $post_ids );
			$post_ids_string  = implode( ',', $post_ids_escaped );
			$meta_keys_string = "'" . implode( "','", $meta_keys ) . "'";

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			// $post_ids_string and $meta_keys_string are already sanitized with absint() and esc_sql()
			$query = "SELECT post_id, meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN ($post_ids_string)
				AND meta_key IN ($meta_keys_string)
				ORDER BY post_id, meta_id ASC";
			// phpcs:enable

			$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

			// Organize by post_id
			// OPTIMIZATION: Store raw meta_value first, only unserialize when actually accessed
			// This prevents memory exhaustion from large serialized data (like page builder layouts)
			$meta_data = array();
			if ( ! empty( $results ) ) {
				foreach ( $results as $row ) {
					$post_id = (int) $row['post_id'];
					if ( ! isset( $meta_data[ $post_id ] ) ) {
						$meta_data[ $post_id ] = array();
					}
					// Store raw value - will be unserialized on-demand when accessed
					$meta_data[ $post_id ][ $row['meta_key'] ] = $row['meta_value'];
				}
			}

			// Cache the results if cache key provided
			if ( ! empty( $cache_key ) ) {
				$this->postmeta_cache[ $cache_key ] = $meta_data;
			}

			return $meta_data;
		}

		/**
		 * Get and unserialize meta value on-demand (lazy unserialization)
		 * Prevents memory exhaustion from large serialized data
		 *
		 * @param array  $meta_data Meta data array (raw values)
		 * @param int    $post_id Post ID
		 * @param string $meta_key Meta key
		 * @return mixed Unserialized value or null if not found
		 */
		private function get_meta_value( $meta_data, $post_id, $meta_key ) {
			if ( ! isset( $meta_data[ $post_id ][ $meta_key ] ) ) {
				return null;
			}
			$value = $meta_data[ $post_id ][ $meta_key ];
			// Unserialize on-demand only when accessed
			return maybe_unserialize( $value );
		}

		/**
		 * Get total count and published post IDs for a post type in a single query
		 * OPTIMIZATION: Combines get_posts() + get_published_post_ids() into one query
		 *
		 * @param string $post_type Post type
		 *
		 * @return array Array with 'total' (int) and 'published_ids' (array)
		 */
		private function get_post_type_data( $post_type ) {
			global $wpdb;

			// Get total count and published IDs in a single query
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					GROUP_CONCAT(CASE WHEN post_status = 'publish' THEN ID END) as published_ids
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status != 'trash'
				AND post_status != 'auto-draft'
				AND post_status != 'wc-wfocu-pri-order'",
				$post_type
			);
			// phpcs:enable

			$result = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

			$total         = isset( $result['total'] ) ? (int) $result['total'] : 0;
			$published_ids = array();

			if ( ! empty( $result['published_ids'] ) ) {
				$published_ids = array_map( 'intval', explode( ',', $result['published_ids'] ) );
			}

			return array(
				'total'         => $total,
				'published_ids' => $published_ids,
			);
		}

		/**
		 * Get published post IDs from a list of post IDs using a single SQL query
		 * OPTIMIZATION: Avoids individual get_post() calls that trigger full post object loading
		 * NOTE: This method is kept for backward compatibility but get_post_type_data() is preferred
		 *
		 * @param array  $post_ids Array of post IDs
		 * @param string $post_type Post type (for validation)
		 *
		 * @return array Array of published post IDs
		 */
		private function get_published_post_ids( $post_ids, $post_type = '' ) {
			if ( empty( $post_ids ) ) {
				return array();
			}

			global $wpdb;

			$post_ids        = array_map( 'intval', $post_ids );
			$post_ids        = array_unique( $post_ids );
			$ids_placeholder = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

			$where_clause = "ID IN ($ids_placeholder) AND post_status = 'publish'";
			$prepare_args = $post_ids;

			if ( ! empty( $post_type ) ) {
				$where_clause  .= ' AND post_type = %s';
				$prepare_args[] = $post_type;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			// $where_clause contains properly sanitized placeholders built with array_fill()
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE $where_clause",
				$prepare_args
			);
			// phpcs:enable

			$results = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

			return array_map( 'intval', $results );
		}

		/**
		 * Collect feature adoption data
		 *
		 * @return array
		 */
		public function collect() {
			$data = array();

			// Funnels (3 keys)
			$data = array_merge( $data, $this->get_funnels_data() );

			// Checkouts (18 keys)
			$data = array_merge( $data, $this->get_checkouts_data() );

			// Landing Pages (4 keys)
			$data = array_merge( $data, $this->get_landing_pages_data() );

			// Thank You Pages (3 keys)
			$data = array_merge( $data, $this->get_thankyou_pages_data() );

			// Opt-in Pages (4 keys)
			$data = array_merge( $data, $this->get_optin_pages_data() );

			// Opt-in Thank You (3 keys)
			$data = array_merge( $data, $this->get_optin_thankyou_data() );

			// Advanced Features (max steps and custom CSS only - rules are Pro-only)
			$data = array_merge( $data, $this->get_advanced_features_data() );

			return $data;
		}

		/**
		 * Get funnels data (1 key)
		 * Funnels don't have status; their steps have status. Only total count is needed.
		 *
		 * @return array
		 */
		private function get_funnels_data() {
			global $wpdb;

			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bwf_funnels" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			return array(
				'funnels/total' => $total,
			);
		}

		/**
		 * Get checkouts data (18 keys)
		 *
		 * @return array
		 */
		private function get_checkouts_data() {
			// OPTIMIZATION: Get total count and published IDs in a single query (eliminates 2 queries)
			$checkout_data       = $this->get_post_type_data( 'wfacp_checkout' );
			$total               = $checkout_data['total'];
			$checkouts_published = $checkout_data['published_ids'];

			if ( 0 === $total ) {
				return $this->get_empty_checkouts_data();
			}

			// OPTIMIZATION: Load ALL checkout meta keys in a single batch query
			$all_checkout_meta_keys = array(
				'_wfacp_selected_design',
				'_wfacp_selected_products_settings',
				'_wfacp_sell_all_mode',
				'_wfacp_page_custom_field',
				'_wfacp_page_layout',
				'_wfacp_page_settings',
			);
			$this->batch_load_postmeta( $checkouts_published, $all_checkout_meta_keys, 'checkouts_all_meta' );

			$data = array(
				'checkouts/total'                 => $total,
				'checkouts/active'                => $this->get_active_checkouts( $checkouts_published ),
				'checkouts/store_checkout_status' => $this->get_store_checkout_status(),
				'checkouts/most_used_template'    => $this->get_most_used_template( $checkouts_published ),
			);

			// Product selection settings (track which setting is used, not product counts)
			$product_setting_data = $this->get_checkout_product_selection_settings( $checkouts_published );
			$data                 = array_merge( $data, $product_setting_data );

			// Field settings
			$field_data = $this->get_checkout_field_settings( $checkouts_published );
			$data       = array_merge( $data, $field_data );

			// Lite optimizations
			$optimization_data = $this->get_checkout_optimizations( $checkouts_published );
			$data              = array_merge( $data, $optimization_data );

			return $data;
		}

		/**
		 * Get empty checkouts data structure
		 *
		 * @return array
		 */
		private function get_empty_checkouts_data() {
			return array(
				'checkouts/total'                 => 0,
				'checkouts/active'                => 0,
				'checkouts/store_checkout_status' => 'not_created',
				'checkouts/most_used_template'    => '',
				'checkouts/product_settings/sell_all_together' => 0,
				'checkouts/product_settings/single_selection' => 0,
				'checkouts/product_settings/multiple_selection' => 0,
				'checkouts/field_settings/with_custom_fields' => 0,
				'checkouts/field_settings/with_third_party_fields' => 0,
				'checkouts/lite_optimizations/express_checkout' => 0,
				'checkouts/lite_optimizations/with_inline_validation' => 0,
				'checkouts/lite_optimizations/with_collapsible_fields' => 0,
				'checkouts/lite_optimizations/with_enhanced_phone_field' => 0,
				'checkouts/lite_optimizations/with_address_validation' => 0,
			);
		}

		/**
		 * Get active checkouts (published pages)
		 *
		 * @param array $checkout_ids Checkout IDs (already filtered to published)
		 *
		 * @return int
		 */
		private function get_active_checkouts( $checkout_ids ) {
			if ( empty( $checkout_ids ) ) {
				return 0;
			}

			// OPTIMIZATION: Since we already filter for published pages before calling this method,
			// active = count of published pages (no need to check status again)
			return count( $checkout_ids );
		}

		/**
		 * Get store checkout status
		 *
		 * @return string
		 */
		private function get_store_checkout_status() {
			if ( ! class_exists( 'WFFN_Common' ) ) {
				return 'not_created';
			}

			$store_checkout_id = WFFN_Common::get_store_checkout_id();

			if ( empty( $store_checkout_id ) ) {
				return 'not_created';
			}

			// OPTIMIZATION: Use direct SQL query to get post status (avoids loading full post object)
			global $wpdb;
			$status = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $store_checkout_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			return 'publish' === $status ? 'enabled' : 'disabled';
		}

		/**
		 * Get most used template
		 *
		 * @param array $checkout_ids Checkout IDs
		 *
		 * @return string
		 */
		private function get_most_used_template( $checkout_ids ) {
			$templates = array();

			// OPTIMIZATION: Use pre-loaded batch data (loaded in get_checkouts_data)
			$meta_data = $this->batch_load_postmeta( $checkout_ids, array( '_wfacp_selected_design' ), 'checkouts_all_meta' );

			foreach ( $checkout_ids as $checkout_id ) {
				// Template is stored in _wfacp_selected_design as array with 'selected' key
				$design_data = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_selected_design' );
				if ( is_array( $design_data ) && isset( $design_data['selected'] ) && ! empty( $design_data['selected'] ) ) {
					$template               = $design_data['selected'];
					$templates[ $template ] = isset( $templates[ $template ] ) ? $templates[ $template ] + 1 : 1;
				}
			}

			if ( empty( $templates ) ) {
				return '';
			}

			arsort( $templates );

			return key( $templates );
		}

		/**
		 * Get checkout product selection settings
		 * Tracks which product selection setting is used (not product counts)
		 *
		 * @param array $checkout_ids Checkout IDs
		 *
		 * @return array
		 */
		private function get_checkout_product_selection_settings( $checkout_ids ) {
			$sell_all_together  = 0; // add_to_cart_setting = 1
			$single_selection   = 0; // add_to_cart_setting = 2
			$multiple_selection = 0; // add_to_cart_setting = 3

			// OPTIMIZATION: Use pre-loaded batch data (loaded in get_checkouts_data)
			$meta_data = $this->batch_load_postmeta( $checkout_ids, array( '_wfacp_selected_products_settings', '_wfacp_sell_all_mode' ), 'checkouts_all_meta' );

			foreach ( $checkout_ids as $checkout_id ) {
				$settings = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_selected_products_settings' );

				if ( is_array( $settings ) && isset( $settings['add_to_cart_setting'] ) ) {
					$setting_value = $settings['add_to_cart_setting'];

					// 1 = All products listed will be sold together at checkout
					if ( '1' === $setting_value || 1 === $setting_value ) {
						++$sell_all_together;
					}
					// 2 = Allow only one product selection at checkout
					elseif ( '2' === $setting_value || 2 === $setting_value ) {
						++$single_selection;
					}
					// 3 = Allow multiple product selections at checkout
					elseif ( '3' === $setting_value || 3 === $setting_value ) {
						++$multiple_selection;
					}
				} else {
					// Default behavior when setting is not set (legacy checkouts)
					// Check old _wfacp_sell_all_mode meta for backward compatibility
					$sell_all = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_sell_all_mode' );
					if ( 'yes' === $sell_all ) {
						++$sell_all_together;
					} else {
						// Default to single selection if no setting found
						++$single_selection;
					}
				}
			}

			return array(
				'checkouts/product_settings/sell_all_together' => $sell_all_together,
				'checkouts/product_settings/single_selection' => $single_selection,
				'checkouts/product_settings/multiple_selection' => $multiple_selection,
			);
		}

		/**
		 * Get checkout field settings
		 *
		 * @param array $checkout_ids Checkout IDs
		 *
		 * @return array
		 */
		private function get_checkout_field_settings( $checkout_ids ) {
			$custom_fields      = 0;
			$third_party_fields = 0;

			// Known third-party field identifiers that should be excluded from custom fields count
			$third_party_field_identifiers = array(
				'wc_advanced_order_field',      // WooCommerce Advanced Order Field compatibility
				'billing_wc_custom_field',       // Third-party billing fields
				'shipping_wc_custom_field',      // Third-party shipping fields
				'bwfan_birthday_date',           // FunnelKit Automations (AutomateWoo) birthday field
				'wffn_test_hello_field',         // Test field for verification (mu-plugin)
			);

			// OPTIMIZATION: Use pre-loaded batch data (loaded in get_checkouts_data)
			$meta_data = $this->batch_load_postmeta( $checkout_ids, array( '_wfacp_page_custom_field', '_wfacp_page_layout' ), 'checkouts_all_meta' );

			foreach ( $checkout_ids as $checkout_id ) {
				// Custom fields are stored in _wfacp_page_custom_field meta key
				$custom_fields_data = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_page_custom_field' );

				$has_custom_field      = false;
				$has_third_party_field = false;

				// Check for custom fields in _wfacp_page_custom_field
				if ( is_array( $custom_fields_data ) && ! empty( $custom_fields_data ) ) {
					// Check for custom fields in all sections (advanced, billing, shipping, etc.)
					foreach ( $custom_fields_data as $section_type => $section_fields ) {
						if ( is_array( $section_fields ) && ! empty( $section_fields ) ) {
							foreach ( $section_fields as $field_key => $field_data ) {
								// Check if field has is_wfacp_field set to true
								if ( isset( $field_data['is_wfacp_field'] ) && ( true === $field_data['is_wfacp_field'] || 'true' === $field_data['is_wfacp_field'] || 'yes' === $field_data['is_wfacp_field'] || 1 === $field_data['is_wfacp_field'] ) ) {
									// Exclude known third-party field identifiers from custom fields count
									if ( ! in_array( $field_key, $third_party_field_identifiers, true ) ) {
										$has_custom_field = true;
									}
									// Note: We don't count third-party fields from _wfacp_page_custom_field
									// Third-party fields are only counted when they're dragged into the layout
								}
							}
						}
					}
				}

				$page_layout = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_page_layout' );
				if ( is_array( $page_layout ) && isset( $page_layout['fieldsets'] ) ) {
					foreach ( $page_layout['fieldsets'] as $step_key => $step_fieldsets ) {
						// $step_fieldsets is an array of fieldsets for this step
						if ( is_array( $step_fieldsets ) ) {
							foreach ( $step_fieldsets as $fieldset ) {
								if ( isset( $fieldset['fields'] ) && is_array( $fieldset['fields'] ) ) {
									foreach ( $fieldset['fields'] as $field ) {
										// Field can be a string (field key) or an array/object with 'id' property
										$field_key = is_array( $field ) && isset( $field['id'] ) ? $field['id'] : ( is_string( $field ) ? $field : '' );
										if ( ! empty( $field_key ) && in_array( $field_key, $third_party_field_identifiers, true ) ) {
											$has_third_party_field = true;
											break 3; // Break out of all loops
										}
									}
								}
							}
						}
					}
				}

				if ( $has_custom_field ) {
					++$custom_fields;
				}

				if ( $has_third_party_field ) {
					++$third_party_fields;
				}
			}

			return array(
				'checkouts/field_settings/with_custom_fields'       => $custom_fields,
				'checkouts/field_settings/with_third_party_fields'  => $third_party_fields,
			);
		}

		/**
		 * Get checkout optimizations
		 *
		 * @param array $checkout_ids Checkout IDs
		 *
		 * @return array
		 */
		private function get_checkout_optimizations( $checkout_ids ) {
			$express_checkout     = 0;
			$inline_validation    = 0;
			$collapsible_fields   = 0;
			$enhanced_phone_field = 0;
			$address_validation   = 0;

			// OPTIMIZATION: Use pre-loaded batch data (loaded in get_checkouts_data)
			$meta_data = $this->batch_load_postmeta( $checkout_ids, array( '_wfacp_page_settings' ), 'checkouts_all_meta' );

			foreach ( $checkout_ids as $checkout_id ) {
				// All optimizations are stored in _wfacp_page_settings array
				$page_settings = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_page_settings' );

				if ( ! is_array( $page_settings ) ) {
					continue;
				}

				// Express checkout (smart buttons)
				if ( isset( $page_settings['enable_smart_buttons'] ) && ( 'yes' === $page_settings['enable_smart_buttons'] || 'true' === $page_settings['enable_smart_buttons'] || true === $page_settings['enable_smart_buttons'] ) ) {
					++$express_checkout;
				}

				// Inline validation
				if ( isset( $page_settings['enable_live_validation'] ) && ( 'yes' === $page_settings['enable_live_validation'] || 'true' === $page_settings['enable_live_validation'] || true === $page_settings['enable_live_validation'] ) ) {
					++$inline_validation;
				}

				// Collapsible fields (check if collapsible_optional_fields array exists and has any enabled fields)
				if ( isset( $page_settings['collapsible_optional_fields'] ) && is_array( $page_settings['collapsible_optional_fields'] ) && ! empty( $page_settings['collapsible_optional_fields'] ) ) {
					// Check if any field has 'true' value
					$has_enabled = false;
					foreach ( $page_settings['collapsible_optional_fields'] as $field_value ) {
						if ( 'true' === $field_value || true === $field_value ) {
							$has_enabled = true;
							break;
						}
					}
					if ( $has_enabled ) {
						++$collapsible_fields;
					}
				}

				// Enhanced phone field (phone flag + validation)
				if ( ( isset( $page_settings['enable_phone_flag'] ) && ( 'yes' === $page_settings['enable_phone_flag'] || 'true' === $page_settings['enable_phone_flag'] || true === $page_settings['enable_phone_flag'] ) ) ||
					( isset( $page_settings['enable_phone_validation'] ) && ( 'yes' === $page_settings['enable_phone_validation'] || 'true' === $page_settings['enable_phone_validation'] || true === $page_settings['enable_phone_validation'] ) ) ) {
					++$enhanced_phone_field;
				}

				// Address validation
				if ( isset( $page_settings['enable_address_field_number_validation'] ) && ( 'yes' === $page_settings['enable_address_field_number_validation'] || 'true' === $page_settings['enable_address_field_number_validation'] || true === $page_settings['enable_address_field_number_validation'] ) ) {
					++$address_validation;
				}
			}

			return array(
				'checkouts/lite_optimizations/express_checkout'         => $express_checkout,
				'checkouts/lite_optimizations/with_inline_validation'   => $inline_validation,
				'checkouts/lite_optimizations/with_collapsible_fields'  => $collapsible_fields,
				'checkouts/lite_optimizations/with_enhanced_phone_field' => $enhanced_phone_field,
				'checkouts/lite_optimizations/with_address_validation'  => $address_validation,
			);
		}

		/**
		 * Get landing pages data (4 keys)
		 *
		 * @return array
		 */
		private function get_landing_pages_data() {
			// OPTIMIZATION: Get total count and published IDs in a single query (eliminates 2 queries)
			$landing_data            = $this->get_post_type_data( 'wffn_landing' );
			$total                   = $landing_data['total'];
			$landing_pages_published = $landing_data['published_ids'];

			if ( 0 === $total ) {
				return array(
					'landing_pages/total'              => 0,
					'landing_pages/active'             => 0,
					'landing_pages/with_page_builder'  => 0,
					'landing_pages/most_used_template' => '',
				);
			}

			// OPTIMIZATION: Pre-load all landing page meta keys in one batch query
			$all_landing_meta_keys = array(
				'_elementor_data',
				'_et_pb_use_builder',
				'ct_other_template',
				'_bricks_page_content',
				'_wflp_selected_design',
			);
			$this->batch_load_postmeta( $landing_pages_published, $all_landing_meta_keys, 'landing_pages_all_meta' );

			$active        = $this->get_active_pages( $landing_pages_published, 'landing' );
			$with_builder  = $this->count_pages_with_builder( $landing_pages_published );
			$most_template = $this->get_most_used_template_for_pages( $landing_pages_published, '_wflp_selected_design' );

			return array(
				'landing_pages/total'              => $total,
				'landing_pages/active'             => $active,
				'landing_pages/with_page_builder'  => $with_builder,
				'landing_pages/most_used_template' => $most_template,
			);
		}

		/**
		 * Get thank you pages data (3 keys)
		 *
		 * @return array
		 */
		private function get_thankyou_pages_data() {
			// OPTIMIZATION: Get total count and published IDs in a single query (eliminates 2 queries)
			$ty_data                  = $this->get_post_type_data( 'wffn_ty' );
			$total                    = $ty_data['total'];
			$thankyou_pages_published = $ty_data['published_ids'];

			if ( 0 === $total ) {
				return array(
					'thankyou_pages/total'              => 0,
					'thankyou_pages/active'             => 0,
					'thankyou_pages/most_used_template' => '',
				);
			}

			// OPTIMIZATION: Pre-load thank you page meta key in batch query
			$this->batch_load_postmeta( $thankyou_pages_published, array( '_wftp_selected_design' ), 'thankyou_pages_all_meta' );

			$active        = $this->get_active_pages( $thankyou_pages_published, 'thankyou' );
			$most_template = $this->get_most_used_template_for_pages( $thankyou_pages_published, '_wftp_selected_design' );

			return array(
				'thankyou_pages/total'              => $total,
				'thankyou_pages/active'             => $active,
				'thankyou_pages/most_used_template' => $most_template,
			);
		}

		/**
		 * Get opt-in pages data (4 keys)
		 *
		 * @return array
		 */
		private function get_optin_pages_data() {
			// OPTIMIZATION: Get total count and published IDs in a single query (eliminates 2 queries)
			$optin_data            = $this->get_post_type_data( 'wffn_optin' );
			$total                 = $optin_data['total'];
			$optin_pages_published = $optin_data['published_ids'];

			if ( 0 === $total ) {
				return array(
					'optin_pages/total'              => 0,
					'optin_pages/active'             => 0,
					'optin_pages/most_used_template' => '',
					'optin_pages/settings_stats/with_email_enabled' => 0,
					'optin_pages/settings_stats/with_recaptcha' => 0,
				);
			}

			// OPTIMIZATION: Pre-load all opt-in page meta keys in one batch query
			$all_optin_meta_keys = array(
				'_wfop_selected_design',
				'wffn_actions_custom_settings',
			);
			$this->batch_load_postmeta( $optin_pages_published, $all_optin_meta_keys, 'optin_pages_all_meta' );

			$active        = $this->get_active_optin_pages( $optin_pages_published );
			$most_template = $this->get_most_used_template_for_pages( $optin_pages_published, '_wfop_selected_design' );
			$settings      = $this->get_optin_settings( $optin_pages_published );

			return array(
				'optin_pages/total'              => $total,
				'optin_pages/active'             => $active,
				'optin_pages/most_used_template' => $most_template,
				'optin_pages/settings_stats/with_email_enabled' => $settings['email_enabled'],
				'optin_pages/settings_stats/with_recaptcha' => $settings['recaptcha'],
			);
		}

		/**
		 * Get opt-in thank you data (3 keys)
		 *
		 * @return array
		 */
		private function get_optin_thankyou_data() {
			// OPTIMIZATION: Get total count and published IDs in a single query (eliminates 2 queries)
			$optin_ty_data            = $this->get_post_type_data( 'wffn_oty' );
			$total                    = $optin_ty_data['total'];
			$optin_ty_pages_published = $optin_ty_data['published_ids'];

			if ( 0 === $total ) {
				return array(
					'optin_thankyou/total'              => 0,
					'optin_thankyou/active'             => 0,
					'optin_thankyou/most_used_template' => '',
				);
			}

			// OPTIMIZATION: Pre-load opt-in thank you page meta key in batch query
			$this->batch_load_postmeta( $optin_ty_pages_published, array( '_wfoty_selected_design' ), 'optin_ty_pages_all_meta' );

			$active        = $this->get_active_optin_thankyou_pages( $optin_ty_pages_published );
			$most_template = $this->get_most_used_template_for_pages( $optin_ty_pages_published, '_wfoty_selected_design' );

			return array(
				'optin_thankyou/total'              => $total,
				'optin_thankyou/active'             => $active,
				'optin_thankyou/most_used_template' => $most_template,
			);
		}

		/**
		 * Get active pages (published pages)
		 *
		 * @param array  $page_ids Page IDs (already filtered to published)
		 * @param string $type Page type
		 *
		 * @return int
		 */
		private function get_active_pages( $page_ids, $type ) {
			if ( empty( $page_ids ) ) {
				return 0;
			}

			// OPTIMIZATION: Since we already filter for published pages before calling this method,
			// active = count of published pages (no need to check status again)
			return count( $page_ids );
		}

		/**
		 * Get active opt-in pages (published pages)
		 *
		 * @param array $page_ids Page IDs (already filtered to published)
		 *
		 * @return int
		 */
		private function get_active_optin_pages( $page_ids ) {
			if ( empty( $page_ids ) ) {
				return 0;
			}

			// OPTIMIZATION: Since we already filter for published pages before calling this method,
			// active = count of published pages (no need to check status again)
			return count( $page_ids );
		}

		/**
		 * Get active opt-in thank you pages (published pages)
		 *
		 * @param array $page_ids Page IDs
		 *
		 * @return int
		 */
		private function get_active_optin_thankyou_pages( $page_ids ) {
			if ( empty( $page_ids ) ) {
				return 0;
			}

			// OPTIMIZATION: Since we already filter for published pages before calling this method,
			// active = count of published pages (no need to check status again)
			return count( $page_ids );
		}

		/**
		 * Check if content has Gutenberg blocks using simple pattern matching
		 * OPTIMIZATION: Avoids has_blocks() which may trigger post object loading
		 *
		 * @param string $content Post content
		 *
		 * @return bool
		 */
		private function has_gutenberg_blocks( $content ) {
			if ( empty( $content ) ) {
				return false;
			}

			// Gutenberg blocks are wrapped in HTML comments like <!-- wp:block-name -->
			// This is a simple and fast pattern check that doesn't require loading post objects
			return (bool) preg_match( '/<!--\s*wp:/', $content );
		}

		/**
		 * Count pages with page builder
		 *
		 * @param array $page_ids Page IDs
		 *
		 * @return int
		 */
		private function count_pages_with_builder( $page_ids ) {
			if ( empty( $page_ids ) ) {
				return 0;
			}

			$builder_meta_keys = array(
				'_elementor_data',
				'_et_pb_use_builder',
				'ct_other_template',
				'_bricks_page_content',
			);

			// OPTIMIZATION: Use pre-loaded batch data if available (for landing pages), otherwise load separately
			$meta_data = $this->batch_load_postmeta( $page_ids, $builder_meta_keys, 'landing_pages_all_meta' );

			$pages_with_builder = array();

			// Check meta-based builders first
			foreach ( $page_ids as $page_id ) {
				// Check for Elementor
				if ( isset( $meta_data[ $page_id ]['_elementor_data'] ) && ! empty( $meta_data[ $page_id ]['_elementor_data'] ) ) {
					$pages_with_builder[] = $page_id;
					continue;
				}

				// Check for Divi Builder
				$et_pb_value = $this->get_meta_value( $meta_data, $page_id, '_et_pb_use_builder' );
				if ( 'on' === $et_pb_value ) {
					$pages_with_builder[] = $page_id;
					continue;
				}

				// Check for Oxygen Builder
				if ( isset( $meta_data[ $page_id ]['ct_other_template'] ) && ! empty( $meta_data[ $page_id ]['ct_other_template'] ) ) {
					$pages_with_builder[] = $page_id;
					continue;
				}

				// Check for Bricks Builder
				if ( isset( $meta_data[ $page_id ]['_bricks_page_content'] ) && ! empty( $meta_data[ $page_id ]['_bricks_page_content'] ) ) {
					$pages_with_builder[] = $page_id;
					continue;
				}
			}

			// Check remaining pages for Gutenberg blocks (only if not already found)
			// OPTIMIZATION: Use direct SQL query to get post_content for remaining pages in a single batch query
			// OPTIMIZATION: Use simple string pattern matching instead of has_blocks() to avoid triggering post object loads
			$remaining_ids = array_diff( $page_ids, $pages_with_builder );
			if ( ! empty( $remaining_ids ) ) {
				global $wpdb;
				$ids_placeholder = implode( ',', array_fill( 0, count( $remaining_ids ), '%d' ) );
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				// $ids_placeholder is already a sanitized placeholder built with array_fill()
				$query = $wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ($ids_placeholder) AND post_content IS NOT NULL AND post_content != ''",
					$remaining_ids
				);
				// phpcs:enable
				$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

				foreach ( $results as $row ) {
					if ( ! empty( $row['post_content'] ) && $this->has_gutenberg_blocks( $row['post_content'] ) ) {
						$pages_with_builder[] = (int) $row['ID'];
					}
				}
			}

			return count( $pages_with_builder );
		}

		/**
		 * Get most used template for pages
		 *
		 * @param array  $page_ids Page IDs
		 * @param string $meta_key Meta key for template design (e.g., '_wflp_selected_design', '_wftp_selected_design', etc.)
		 *
		 * @return string
		 */
		private function get_most_used_template_for_pages( $page_ids, $meta_key ) {
			$templates = array();

			// OPTIMIZATION: Use pre-loaded batch data if available, otherwise load separately
			// For landing pages, use 'landing_pages_all_meta', for opt-in pages use 'optin_pages_all_meta', for thank you pages use 'thankyou_pages_all_meta', for opt-in thank you use 'optin_ty_pages_all_meta', for others use specific cache key
			if ( '_wflp_selected_design' === $meta_key ) {
				$cache_key = 'landing_pages_all_meta';
			} elseif ( '_wfop_selected_design' === $meta_key ) {
				$cache_key = 'optin_pages_all_meta';
			} elseif ( '_wftp_selected_design' === $meta_key ) {
				$cache_key = 'thankyou_pages_all_meta';
			} elseif ( '_wfoty_selected_design' === $meta_key ) {
				$cache_key = 'optin_ty_pages_all_meta';
			} else {
				$cache_key = 'template_' . $meta_key;
			}
			$meta_data = $this->batch_load_postmeta( $page_ids, array( $meta_key ), $cache_key );

			foreach ( $page_ids as $page_id ) {
				$design_data = $this->get_meta_value( $meta_data, $page_id, $meta_key );

				// All template designs are stored as arrays with 'selected' key
				if ( is_array( $design_data ) && isset( $design_data['selected'] ) && ! empty( $design_data['selected'] ) ) {
					$template               = $design_data['selected'];
					$templates[ $template ] = isset( $templates[ $template ] ) ? $templates[ $template ] + 1 : 1;
				} elseif ( ! empty( $design_data ) && is_string( $design_data ) ) {
					// Fallback: if it's stored as a simple string (legacy data)
					$templates[ $design_data ] = isset( $templates[ $design_data ] ) ? $templates[ $design_data ] + 1 : 1;
				}
			}

			if ( empty( $templates ) ) {
				return '';
			}

			arsort( $templates );

			return key( $templates );
		}

		/**
		 * Get opt-in settings
		 *
		 * @param array $page_ids Page IDs
		 *
		 * @return array
		 */
		private function get_optin_settings( $page_ids ) {
			$email_enabled = 0;
			$recaptcha     = 0;

			// Get global recaptcha setting
			$global_op_settings       = get_option( 'wffn_op_settings', array() );
			$global_recaptcha_enabled = false;
			if ( is_array( $global_op_settings ) && isset( $global_op_settings['op_recaptcha'] ) ) {
				$global_recaptcha_enabled = ( 'true' === $global_op_settings['op_recaptcha'] || '1' === $global_op_settings['op_recaptcha'] || true === $global_op_settings['op_recaptcha'] );
			}

			// OPTIMIZATION: Use pre-loaded batch data (loaded in get_optin_pages_data)
			$meta_data = $this->batch_load_postmeta( $page_ids, array( 'wffn_actions_custom_settings' ), 'optin_pages_all_meta' );

			foreach ( $page_ids as $page_id ) {
				// Check if email/form integration is enabled
				// Email is enabled if optin_service_form has optin_form_enable = 'true' OR if user_login action is enabled
				$actions_settings  = $this->get_meta_value( $meta_data, $page_id, 'wffn_actions_custom_settings' );
				$has_email_enabled = false;

				if ( is_array( $actions_settings ) ) {
					// Check for form integration
					if ( isset( $actions_settings['optin_service_form'] ) && is_array( $actions_settings['optin_service_form'] ) ) {
						$form_settings = $actions_settings['optin_service_form'];
						if ( isset( $form_settings['optin_form_enable'] ) && ( 'true' === $form_settings['optin_form_enable'] || true === $form_settings['optin_form_enable'] ) ) {
							$has_email_enabled = true;
						}
					}

					// Check for user email action (user_login = 'true')
					if ( ! $has_email_enabled && isset( $actions_settings['user_login'] ) && ( 'true' === $actions_settings['user_login'] || true === $actions_settings['user_login'] ) ) {
						$has_email_enabled = true;
					}
				}

				if ( $has_email_enabled ) {
					++$email_enabled;
				}

				// Recaptcha is a global setting, so if it's enabled globally, count this page
				if ( $global_recaptcha_enabled ) {
					++$recaptcha;
				}
			}

			return array(
				'email_enabled' => $email_enabled,
				'recaptcha'     => $recaptcha,
			);
		}

		/**
		 * Get advanced features data (max steps and custom CSS only)
		 * NOTE: Rules queries are Pro-only and handled in Pro version
		 *
		 * @return array
		 */
		private function get_advanced_features_data() {
			$data = array();

			// Maximum steps in any funnel
			$max_steps                 = $this->get_max_funnel_steps();
			$data['funnels/max_steps'] = $max_steps;

			// Custom CSS enabled for each step type (global settings)
			$checkout_css                         = $this->is_step_custom_css_enabled( 'checkout' );
			$data['checkouts/custom_css_enabled'] = $checkout_css ? 1 : 0;

			$upsell_css                         = $this->is_step_custom_css_enabled( 'upsell' );
			$data['upsells/custom_css_enabled'] = $upsell_css ? 1 : 0;

			$landing_page_css                         = $this->is_step_custom_css_enabled( 'landing_page' );
			$data['landing_pages/custom_css_enabled'] = $landing_page_css ? 1 : 0;

			$optin_page_css                         = $this->is_step_custom_css_enabled( 'optin_page' );
			$data['optin_pages/custom_css_enabled'] = $optin_page_css ? 1 : 0;

			$thankyou_page_css                         = $this->is_step_custom_css_enabled( 'thankyou_page' );
			$data['thankyou_pages/custom_css_enabled'] = $thankyou_page_css ? 1 : 0;

			return $data;
		}

		/**
		 * Get maximum number of steps in any funnel
		 *
		 * @return int
		 */
		private function get_max_funnel_steps() {
			global $wpdb;

			// Get all funnels
			$funnels = $wpdb->get_results( "SELECT id, steps FROM {$wpdb->prefix}bwf_funnels", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( empty( $funnels ) ) {
				return 0;
			}

			$max_steps = 0;
			foreach ( $funnels as $funnel ) {
				$steps = maybe_unserialize( $funnel['steps'] );
				if ( is_array( $steps ) ) {
					$step_count = count( $steps );
					if ( $step_count > $max_steps ) {
						$max_steps = $step_count;
					}
				}
			}

			return $max_steps;
		}

		/**
		 * Check if custom CSS is enabled for a specific step type (global setting)
		 * Based on actual CSS rendering methods in the codebase
		 *
		 * @param string $step_type Step type: 'checkout', 'upsell', 'landing_page', 'optin_page', 'thankyou_page'
		 *
		 * @return bool
		 */
		private function is_step_custom_css_enabled( $step_type ) {
			switch ( $step_type ) {
				case 'checkout':
					// Checkout uses _wfacp_global_settings option with wfacp_checkout_global_css key
					// See: modules/checkouts/includes/class-wfacp-template.php::global_css()
					$global_settings = get_option( '_wfacp_global_settings', array() );
					if ( ! is_array( $global_settings ) ) {
						return false;
					}
					if ( isset( $global_settings['wfacp_checkout_global_css'] ) && ! empty( trim( $global_settings['wfacp_checkout_global_css'] ) ) ) {
						return true;
					}
					return false;

				case 'upsell':
					// Upsells use wfocu_global_settings option with scripts key for CSS
					// See: modules/one-click-upsells/includes/class-wfocu-ecomm-tracking.php::render_global_external_scripts()
					$global_settings = get_option( 'wfocu_global_settings', array() );
					if ( ! is_array( $global_settings ) ) {
						return false;
					}
					if ( isset( $global_settings['scripts'] ) && ! empty( trim( $global_settings['scripts'] ) ) ) {
						return true;
					}
					return false;

				case 'landing_page':
					// Landing pages use wffn_lp_settings option with css key
					// See: includes/class-wffn-module-common.php::print_custom_css_in_head()
					$global_settings = get_option( 'wffn_lp_settings', array() );
					if ( ! is_array( $global_settings ) ) {
						return false;
					}
					if ( isset( $global_settings['css'] ) && ! empty( trim( $global_settings['css'] ) ) ) {
						return true;
					}
					return false;

				case 'optin_page':
					// Opt-in pages use wffn_op_settings option with css key
					// See: includes/class-wffn-module-common.php::print_custom_css_in_head()
					$global_settings = get_option( 'wffn_op_settings', array() );
					if ( ! is_array( $global_settings ) ) {
						return false;
					}
					if ( isset( $global_settings['css'] ) && ! empty( trim( $global_settings['css'] ) ) ) {
						return true;
					}
					return false;

				case 'thankyou_page':
					// Thank you pages use wffn_tp_settings option with css key
					// See: includes/class-wffn-module-common.php::print_custom_css_in_head()
					$global_settings = get_option( 'wffn_tp_settings', array() );
					if ( ! is_array( $global_settings ) ) {
						return false;
					}
					if ( isset( $global_settings['css'] ) && ! empty( trim( $global_settings['css'] ) ) ) {
						return true;
					}
					return false;

				default:
					return false;
			}
		}
	}
}
