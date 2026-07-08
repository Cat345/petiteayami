<?php
/**
 * Pro Feature Adoption Tracking
 *
 * Collects 18 Pro-specific keys related to Pro feature usage
 *
 * @package FunnelKit Funnel Builder Pro
 * @since 3.13.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Feature_Pro' ) ) {

	/**
	 * Class WFFN_Feature_Pro
	 */
	#[\AllowDynamicProperties]
	class WFFN_Feature_Pro {

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

			$post_ids = array_unique( array_map( 'intval', $post_ids ) );

			$meta_key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT post_id, meta_key, meta_value FROM '
					. $wpdb->postmeta
					. ' WHERE post_id IN ('
					. implode( ',', array_fill( 0, count( $post_ids ), '%d' ) )
					. ') AND meta_key IN ('
					. $meta_key_placeholders //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $meta_key_placeholders contains only %s placeholders built via array_fill
					. ') ORDER BY post_id, meta_id ASC',
					...array_merge( array_values( $post_ids ), array_values( $meta_keys ) )
				),
				ARRAY_A
			);

			// Organize results by post_id.
			$meta_data = array();
			if ( ! empty( $results ) ) {
				foreach ( $results as $row ) {
					$post_id = (int) $row['post_id'];
					if ( ! isset( $meta_data[ $post_id ] ) ) {
						$meta_data[ $post_id ] = array();
					}
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
		 * Collect Pro feature adoption data
		 * OPTIMIZED: Pre-loads all checkout meta in single batch to avoid duplicate queries
		 *
		 * @return array
		 */
		public function collect() {
			$data = array();

			// Order Bumps (3 keys)
			$data = array_merge( $data, $this->get_order_bumps_data() );

			// Upsells (3 keys)
			$data = array_merge( $data, $this->get_upsells_data() );

			// A/B Testing (4 keys)
			$data = array_merge( $data, $this->get_ab_testing_data() );

			// OPTIMIZATION: Pre-load all checkout meta keys in one batch query
			// This ensures both multi-step and optimizations methods reuse the same data
			$checkouts = get_posts(
				array(
					'post_type'      => 'wfacp_checkout',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $checkouts ) ) {
				// Pre-load all checkout meta keys that will be needed
				$all_checkout_meta_keys = array(
					'_wfacp_page_layout',
					'_wfacp_page_settings',
				);
				$this->batch_load_postmeta( $checkouts, $all_checkout_meta_keys, 'pro_checkouts_all_meta' );
			}

			// Multi-Step Checkout (2 keys)
			$data = array_merge( $data, $this->get_multi_step_checkout_data( $checkouts ) );

			// Pro Checkout Optimizations (8 keys)
			$data = array_merge( $data, $this->get_pro_checkout_optimizations_data( $checkouts ) );

			// Rules and Advanced Features (5 keys)
			$data = array_merge( $data, $this->get_rules_and_advanced_features_data() );

			// Legacy admin interface usage and standalone post counts (7 keys)
			$data = array_merge( $data, $this->get_legacy_admin_data() );

			return $data;
		}

		/**
		 * Get order bumps data (3 keys)
		 *
		 * @return array
		 */
		private function get_order_bumps_data() {
			global $wpdb;

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						SUM(CASE WHEN post_status != %s THEN 1 ELSE 0 END) as total,
						SUM(CASE WHEN post_status = %s THEN 1 ELSE 0 END) as active
					FROM {$wpdb->posts}
					WHERE post_type = %s",
					'trash',
					'publish',
					'wfob_bump'
				),
				ARRAY_A
			);

			$total  = isset( $row['total'] ) ? (int) $row['total'] : 0;
			$active = isset( $row['active'] ) ? (int) $row['active'] : 0;

			// Most used template (only from published bumps)
			$most_used_template = $this->get_most_used_template( 'wfob_bump' );

			return array(
				'order_bumps/total'              => $total,
				'order_bumps/active'             => $active,
				'order_bumps/most_used_template' => $most_used_template,
			);
		}

		/**
		 * Get upsells data (3 keys)
		 *
		 * @return array
		 */
		private function get_upsells_data() {
			global $wpdb;

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						SUM(CASE WHEN post_status != %s THEN 1 ELSE 0 END) as total,
						SUM(CASE WHEN post_status = %s THEN 1 ELSE 0 END) as active
					FROM {$wpdb->posts}
					WHERE post_type = %s",
					'trash',
					'publish',
					'wfocu_offer'
				),
				ARRAY_A
			);

			$total  = isset( $row['total'] ) ? (int) $row['total'] : 0;
			$active = isset( $row['active'] ) ? (int) $row['active'] : 0;

			// Most used template (only from published upsells)
			$most_used_template = $this->get_most_used_template( 'wfocu_offer' );

			return array(
				'upsells/total'              => $total,
				'upsells/active'             => $active,
				'upsells/most_used_template' => $most_used_template,
			);
		}

		/**
		 * Get A/B testing data (4 keys)
		 *
		 * @return array
		 */
		private function get_ab_testing_data() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'bwf_ab_experiments';

			// Check if table exists
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
				return array(
					'ab_testing/total_experiments'     => 0,
					'ab_testing/active_experiments'    => 0,
					'ab_testing/completed_experiments' => 0,
					'ab_testing/steps_with_ab_tests'   => 0,
				);
			}

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as active,
						SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as completed,
						COUNT(DISTINCT control) as steps_with_tests
					FROM {$wpdb->prefix}bwf_ab_experiments",
					'2',
					'4'
				),
				ARRAY_A
			);

			return array(
				'ab_testing/total_experiments'     => isset( $row['total'] ) ? (int) $row['total'] : 0,
				'ab_testing/active_experiments'    => isset( $row['active'] ) ? (int) $row['active'] : 0,
				'ab_testing/completed_experiments' => isset( $row['completed'] ) ? (int) $row['completed'] : 0,
				'ab_testing/steps_with_ab_tests'   => isset( $row['steps_with_tests'] ) ? (int) $row['steps_with_tests'] : 0,
			);
		}

		/**
		 * Get multi-step checkout data (2 keys)
		 * OPTIMIZED: Uses pre-loaded batch postmeta data (avoids WFACP_Common::get_page_layout which triggers individual queries)
		 *
		 * @param array $checkouts Optional: Pre-fetched checkout IDs to avoid duplicate get_posts() call
		 *
		 * @return array
		 */
		private function get_multi_step_checkout_data( $checkouts = null ) {
			// Get all checkouts if not provided
			if ( null === $checkouts ) {
				$checkouts = get_posts(
					array(
						'post_type'      => 'wfacp_checkout',
						'posts_per_page' => -1,
						'post_status'    => 'publish',
						'fields'         => 'ids',
					)
				);
			}

			if ( empty( $checkouts ) ) {
				return array(
					'multi_step_checkout/total_multi_step' => 0,
					'multi_step_checkout/avg_steps_count'  => 0,
				);
			}

			$multi_step_count = 0;
			$total_steps      = 0;

			// OPTIMIZATION: Use pre-loaded batch data (loaded in collect() method)
			// CRITICAL: Use batch-loaded data directly, NOT WFACP_Common::get_page_layout() which triggers individual queries
			$meta_data = $this->batch_load_postmeta( $checkouts, array( '_wfacp_page_layout' ), 'pro_checkouts_all_meta' );

			foreach ( $checkouts as $checkout_id ) {
				// Use batch-loaded data directly (WFACP_Common::get_page_layout() would trigger individual get_post_meta() calls)
				$page_layout = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_page_layout' );

				if ( ! is_array( $page_layout ) || ! isset( $page_layout['steps'] ) ) {
					continue;
				}

				// Count active steps
				$active_steps = 0;
				foreach ( $page_layout['steps'] as $step_key => $step_data ) {
					if ( is_array( $step_data ) && isset( $step_data['active'] ) ) {
						// Check if step is active (yes, true, 1, '1')
						$is_active = ( 'yes' === $step_data['active'] || 'true' === $step_data['active'] || true === $step_data['active'] || 1 === $step_data['active'] || '1' === $step_data['active'] );
						if ( $is_active ) {
							++$active_steps;
						}
					}
				}

				// If more than 1 step is active, it's multi-step
				if ( $active_steps > 1 ) {
					++$multi_step_count;
					$total_steps += $active_steps;
				}
			}

			$avg_steps = $multi_step_count > 0 ? round( $total_steps / $multi_step_count, 2 ) : 0;

			return array(
				'multi_step_checkout/total_multi_step' => $multi_step_count,
				'multi_step_checkout/avg_steps_count'  => $avg_steps,
			);
		}

		/**
		 * Get Pro checkout optimizations data (8 keys)
		 * OPTIMIZED: Uses pre-loaded batch postmeta data to avoid duplicate queries
		 *
		 * @param array $checkouts Optional: Pre-fetched checkout IDs to avoid duplicate get_posts() call
		 *
		 * @return array
		 */
		private function get_pro_checkout_optimizations_data( $checkouts = null ) {
			// Get all checkouts if not provided
			if ( null === $checkouts ) {
				$checkouts = get_posts(
					array(
						'post_type'      => 'wfacp_checkout',
						'posts_per_page' => -1,
						'post_status'    => 'publish',
						'fields'         => 'ids',
					)
				);
			}

			if ( empty( $checkouts ) ) {
				return $this->get_empty_pro_optimizations();
			}

			$counts = array(
				'with_smart_login'           => 0,
				'with_address_autocomplete'  => 0,
				'with_multi_step_preview'    => 0,
				'with_preferred_countries'   => 0,
				'with_checkout_expiry'       => 0,
				'with_prefill_thankyou'      => 0,
				'with_autofill_state_zip'    => 0,
				'with_generate_populate_url' => 0,
			);

			// OPTIMIZATION: Use pre-loaded batch data (loaded in collect() method)
			$meta_data = $this->batch_load_postmeta( $checkouts, array( '_wfacp_page_settings' ), 'pro_checkouts_all_meta' );

			foreach ( $checkouts as $checkout_id ) {
				// All Pro optimizations are stored in _wfacp_page_settings array
				$page_settings = $this->get_meta_value( $meta_data, $checkout_id, '_wfacp_page_settings' );

				if ( ! is_array( $page_settings ) ) {
					continue;
				}

				// Smart login - Check if there's a smart login feature (may need to check for specific key)
				// Note: Smart login might be a separate feature, checking common patterns
				if ( isset( $page_settings['enable_smart_login'] ) && ( 'yes' === $page_settings['enable_smart_login'] || 'true' === $page_settings['enable_smart_login'] || true === $page_settings['enable_smart_login'] ) ) {
					++$counts['with_smart_login'];
				}

				// Address autocomplete
				if ( isset( $page_settings['autocomplete_enable'] ) && ( 'yes' === $page_settings['autocomplete_enable'] || 'true' === $page_settings['autocomplete_enable'] || true === $page_settings['autocomplete_enable'] ) ) {
					++$counts['with_address_autocomplete'];
				}

				// Multi-step preview - Check if show_on_next_step has any enabled fields
				if ( isset( $page_settings['show_on_next_step'] ) && is_array( $page_settings['show_on_next_step'] ) && ! empty( $page_settings['show_on_next_step'] ) ) {
					$has_enabled = false;
					foreach ( $page_settings['show_on_next_step'] as $step_fields ) {
						if ( is_array( $step_fields ) ) {
							foreach ( $step_fields as $field_value ) {
								if ( 'true' === $field_value || true === $field_value ) {
									$has_enabled = true;
									break 2;
								}
							}
						}
					}
					if ( $has_enabled ) {
						++$counts['with_multi_step_preview'];
					}
				}

				// Preferred countries
				if ( isset( $page_settings['preferred_countries_enable'] ) && ( 'yes' === $page_settings['preferred_countries_enable'] || 'true' === $page_settings['preferred_countries_enable'] || true === $page_settings['preferred_countries_enable'] ) ) {
					++$counts['with_preferred_countries'];
				} elseif ( isset( $page_settings['preferred_countries'] ) && ! empty( $page_settings['preferred_countries'] ) ) {
					++$counts['with_preferred_countries'];
				}

				// Checkout expiry
				if ( isset( $page_settings['close_checkout_after_date'] ) && ( 'yes' === $page_settings['close_checkout_after_date'] || 'true' === $page_settings['close_checkout_after_date'] || true === $page_settings['close_checkout_after_date'] ) ) {
					++$counts['with_checkout_expiry'];
				} elseif ( isset( $page_settings['close_checkout_on'] ) && ! empty( $page_settings['close_checkout_on'] ) ) {
					++$counts['with_checkout_expiry'];
				}

				// Prefill thankyou - Check enable_autopopulate_fields
				if ( isset( $page_settings['enable_autopopulate_fields'] ) && ( 'yes' === $page_settings['enable_autopopulate_fields'] || 'true' === $page_settings['enable_autopopulate_fields'] || true === $page_settings['enable_autopopulate_fields'] ) ) {
					++$counts['with_prefill_thankyou'];
				}

				// Autofill state zip
				if ( isset( $page_settings['enable_autopopulate_state'] ) && ( 'yes' === $page_settings['enable_autopopulate_state'] || 'true' === $page_settings['enable_autopopulate_state'] || true === $page_settings['enable_autopopulate_state'] ) ) {
					++$counts['with_autofill_state_zip'];
				}

				// Generate populate URL - Check auto_fill_url_autoresponder
				if ( isset( $page_settings['auto_fill_url_autoresponder'] ) && ! empty( $page_settings['auto_fill_url_autoresponder'] ) && 'select_email_provider' !== $page_settings['auto_fill_url_autoresponder'] ) {
					++$counts['with_generate_populate_url'];
				}
			}

			return array(
				'pro_checkouts/pro_optimizations/with_smart_login'          => $counts['with_smart_login'],
				'pro_checkouts/pro_optimizations/with_address_autocomplete' => $counts['with_address_autocomplete'],
				'pro_checkouts/pro_optimizations/with_multi_step_preview'  => $counts['with_multi_step_preview'],
				'pro_checkouts/pro_optimizations/with_preferred_countries' => $counts['with_preferred_countries'],
				'pro_checkouts/pro_optimizations/with_checkout_expiry'     => $counts['with_checkout_expiry'],
				'pro_checkouts/pro_optimizations/with_prefill_thankyou'  => $counts['with_prefill_thankyou'],
				'pro_checkouts/pro_optimizations/with_autofill_state_zip' => $counts['with_autofill_state_zip'],
				'pro_checkouts/pro_optimizations/with_generate_populate_url' => $counts['with_generate_populate_url'],
			);
		}

		/**
		 * Get empty Pro optimizations data
		 *
		 * @return array
		 */
		private function get_empty_pro_optimizations() {
			return array(
				'pro_checkouts/pro_optimizations/with_smart_login'          => 0,
				'pro_checkouts/pro_optimizations/with_address_autocomplete' => 0,
				'pro_checkouts/pro_optimizations/with_multi_step_preview'  => 0,
				'pro_checkouts/pro_optimizations/with_preferred_countries' => 0,
				'pro_checkouts/pro_optimizations/with_checkout_expiry'     => 0,
				'pro_checkouts/pro_optimizations/with_prefill_thankyou'    => 0,
				'pro_checkouts/pro_optimizations/with_autofill_state_zip'   => 0,
				'pro_checkouts/pro_optimizations/with_generate_populate_url' => 0,
			);
		}

		/**
		 * Get most used template for a post type
		 *
		 * @param string $post_type Post type (wfob_bump or wfocu_offer)
		 *
		 * @return string Template slug
		 */
		private function get_most_used_template( $post_type ) {
			$templates = array();

			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
				)
			);

			if ( empty( $posts ) ) {
				return '';
			}

			$meta_keys = array();
			if ( 'wfob_bump' === $post_type ) {
				$meta_keys = array( '_wfob_design_data' );
			} elseif ( 'wfocu_offer' === $post_type ) {
				$meta_keys = array( '_wfocu_setting' );
			}

			if ( empty( $meta_keys ) ) {
				return '';
			}

			// Batch load postmeta for all posts
			$meta_data = $this->batch_load_postmeta( $posts, $meta_keys, 'template_' . $post_type );

			foreach ( $posts as $post_id ) {
				$template = '';

				if ( 'wfob_bump' === $post_type ) {
					// Order bumps: template is in _wfob_design_data['layout']
					$design_data = $this->get_meta_value( $meta_data, $post_id, '_wfob_design_data' );
					if ( is_array( $design_data ) && isset( $design_data['layout'] ) && ! empty( $design_data['layout'] ) ) {
						$template = $design_data['layout'];
					}
				} elseif ( 'wfocu_offer' === $post_type ) {
					// Upsells: template is in _wfocu_setting['template'] or _wfocu_setting->template
					$offer_setting = $this->get_meta_value( $meta_data, $post_id, '_wfocu_setting' );
					if ( is_object( $offer_setting ) && isset( $offer_setting->template ) && ! empty( $offer_setting->template ) ) {
						$template = $offer_setting->template;
					} elseif ( is_array( $offer_setting ) && isset( $offer_setting['template'] ) && ! empty( $offer_setting['template'] ) ) {
						$template = $offer_setting['template'];
					}
				}

				if ( ! empty( $template ) ) {
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
		 * Get rules and advanced features data (5 keys)
		 * - Count of thank you pages with rules
		 * - Count of upsell pages with rules
		 * - Count of bumps with rules
		 * - Maximum steps in any funnel
		 * - Custom CSS enabled for checkout (global setting)
		 *
		 * @return array
		 */
		private function get_rules_and_advanced_features_data() {
			$data = array();

			// OPTIMIZATION: Combine all rules queries into a single query (eliminates 3 queries)
			$rules_data                        = $this->get_all_rules_counts();
			$data['thankyou_pages/with_rules'] = $rules_data['thankyou'];
			$data['upsells/with_rules']        = $rules_data['upsells'];
			$data['bumps/with_rules']          = $rules_data['bumps'];

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
		 * Get all rules counts in a single query (optimization)
		 * Combines thank you pages, upsells, and bumps rules queries into one
		 * This is Pro-only functionality
		 *
		 * @return array Array with 'thankyou', 'upsells', 'bumps' keys (each 0 or 1)
		 */
		private function get_all_rules_counts() {
			global $wpdb;

			$result = array(
				'thankyou' => 0,
				'upsells'  => 0,
				'bumps'    => 0,
			);

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						SUM(CASE WHEN p.post_type = %s AND pm.meta_key = %s THEN 1 ELSE 0 END) as thankyou,
						SUM(CASE WHEN p.post_type = %s AND pm.meta_key = %s THEN 1 ELSE 0 END) as upsells,
						SUM(CASE WHEN p.post_type = %s AND pm.meta_key = %s THEN 1 ELSE 0 END) as bumps
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_status != %s
					AND (
						(p.post_type = %s AND pm.meta_key = %s)
						OR (p.post_type = %s AND pm.meta_key = %s)
						OR (p.post_type = %s AND pm.meta_key = %s)
					)
					AND pm.meta_value != %s
					AND pm.meta_value != %s",
					'wffn_ty',
					'_wfty_rules',
					'wfocu_offer',
					'_wfocu_rules',
					'wfob_bump',
					'_wfob_rules',
					'trash',
					'wffn_ty',
					'_wfty_rules',
					'wfocu_offer',
					'_wfocu_rules',
					'wfob_bump',
					'_wfob_rules',
					'',
					'a:0:{}'
				),
				ARRAY_A
			);

			if ( $row ) {
				$result['thankyou'] = (int) $row['thankyou'] > 0 ? 1 : 0;
				$result['upsells']  = (int) $row['upsells'] > 0 ? 1 : 0;
				$result['bumps']    = (int) $row['bumps'] > 0 ? 1 : 0;
			}

			return $result;
		}

		/**
		 * Get maximum number of steps in any funnel
		 *
		 * @return int
		 */
		private function get_max_funnel_steps() {
			global $wpdb;

			$funnels = $wpdb->get_results( "SELECT id, steps FROM {$wpdb->prefix}bwf_funnels", ARRAY_A );

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
					// See: modules/checkout/includes/class-wfacp-template.php::global_css()
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

		/**
		 * Get legacy admin interface usage and standalone post counts (7 keys)
		 *
		 * For each module:
		 * - modules/{key}/is_legacy_admin_enabled  — 1 if the old admin menu is accessible
		 * - modules/{key}/standalone_post_count     — posts not associated with any funnel
		 *   (bump, upsells, checkout only; ab_tests has no post type)
		 *
		 * @return array
		 */
		private function get_legacy_admin_data() {
			global $wpdb;

			$data = array();

			$module_class_map = array(
				'bump'     => 'WFFN_Pro_Bump_Support',
				'upsells'  => 'WFFN_Pro_Upsells_Support',
				'checkout' => 'WFFN_Pro_Checkout_Support',
				'ab_tests' => 'WFFN_Pro_AB_Support',
			);

			foreach ( $module_class_map as $module_key => $class ) {
				$is_enabled = ( class_exists( $class ) && method_exists( $class, 'is_admin_enabled' ) && $class::is_admin_enabled() ) ? 1 : 0;
				$data[ "modules/{$module_key}/is_legacy_admin_enabled" ] = $is_enabled;
			}

			// Standalone post counts — posts not inside any funnel (no _bwf_in_funnel meta)
			$standalone_post_types = array(
				'bump'     => 'wfob_bump',
				'upsells'  => 'wfocu_offer',
				'checkout' => 'wfacp_checkout',
			);

			$rows = $wpdb->get_results(
				"SELECT p.post_type, COUNT(*) as count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_bwf_in_funnel'
				WHERE p.post_type IN ('wfob_bump', 'wfocu_funnel', 'wfacp_checkout')
				AND p.post_status != 'trash'
				AND pm.post_id IS NULL
				GROUP BY p.post_type",
				ARRAY_A
			);

			$standalone_counts = array();
			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$standalone_counts[ $row['post_type'] ] = (int) $row['count'];
				}
			}

			foreach ( $standalone_post_types as $module_key => $post_type ) {
				$data[ "modules/{$module_key}/standalone_post_count" ] = isset( $standalone_counts[ $post_type ] ) ? $standalone_counts[ $post_type ] : 0;
			}

			return $data;
		}
	}
}
