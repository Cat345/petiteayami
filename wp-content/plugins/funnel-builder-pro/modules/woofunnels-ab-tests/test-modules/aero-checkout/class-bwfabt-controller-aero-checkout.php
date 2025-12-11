<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Controller_Aero_Checkout' ) ) {
	/**
	 * Class contains all the aero checkout related ab testing functionality
	 * Class BWFABT_Controller_Aero_Checkout
	 */
	#[AllowDynamicProperties]
	class BWFABT_Controller_Aero_Checkout extends BWFABT_Controller {
		private static $ins = null;
		private $control_query;
		public $view_support = true;
		private $is_cart_restored = false;

		/**
		 * BWFABT_Controller_Aero_Checkout constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'bwfabt_get_supported_controllers', array( $this, 'bwfabt_add_aero_controller' ) );
			add_filter( 'wfacp_listing_handle_query_args', array( $this, 'wfacp_add_control_meta_query' ), 10, 1 );
			add_action( 'wp', array( $this, 'maybe_redirect_to_ab_aero' ), 4, 1 );
			add_action( 'wfacp_changed_default_woocommerce_page', [ $this, 'set_global_page_id' ], 99 );
			add_action( 'wfab_pre_abandoned_cart_restored', [ $this, 'check_if_autobot_cart_restored' ] );
			add_action( 'wfacp_view_recorded', [ $this, 'record_ab_test_views' ] );
			add_action( 'woocommerce_thankyou', [ $this, 'wfacp_clear_ab_view_session' ], 10, 1 );
			add_filter( "shortcode_atts_wfacp_forms", [ $this, 'change_wfacp_in_embed_form' ] );

			$this->control_query = false;
		}

		/**
		 * @return BWFABT_Controller_Aero_Checkout|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		/**
		 * @param $controllers
		 *
		 * @return mixed
		 */
		public function bwfabt_add_aero_controller( $controllers ) {
			$controllers['aero'] = 'BWFABT_Controller_Aero_Checkout';

			return $controllers;
		}

		/**
		 * Return title of Aero controller
		 */
		public function get_title() {
			return __( 'Checkout Page', 'woofunnels-ab-tests' );
		}

		/**
		 * Get all active aero checkout pages(exclude variants) for deciding control of the experiment
		 *
		 * @param $term
		 *
		 * @return array
		 */
		public function get_controls( $term ) {
			global $wpdb;
			$pages = [];
			if ( '' === $term ) {
				return $pages;
			}

			$like_term     = '%' . $wpdb->esc_like( $term ) . '%';
			$post_type     = WFACP_Common::get_post_type_slug();
			$post_statuses = array( 'publish', 'draft' );
			if ( 'get_all' === $term ) {
				$query = $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} as posts WHERE posts.post_type = %s AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ORDER BY posts.post_parent ASC, posts.post_title ASC", $post_type ); //phpcs:ignore
			} else {
				$query = $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} as posts WHERE ( posts.post_title LIKE %s or posts.ID = %s ) AND posts.post_type = %s AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ORDER BY posts.post_parent ASC, posts.post_title ASC", $like_term, $like_term, $post_type ); //phpcs:ignore
			}

			$post_ids = $wpdb->get_col( $query ); //phpcs:ignore

			if ( ! is_array( $post_ids ) || count( $post_ids ) === 0 ) {
				return $pages;
			}
			foreach ( $post_ids as $id ) {
				if ( get_post_meta( $id, '_bwf_ab_variation_of', true ) ) {
					continue;
				}
				$pages[] = array(
					'id'   => $id,
					'name' => html_entity_decode( get_the_title( $id ) ),
				);
			}

			return $pages;
		}

		/**
		 * @param $variant_data
		 *
		 * @return int
		 */
		public function add_variant( $variant_data ) {
			$variant_id = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;
			if ( $variant_id < 1 ) {
				$args       = [
					'post_title'  => $variant_data['variant_title'],
					'post_name'   => sanitize_title( $variant_data['variant_title'] ),
					'post_type'   => WFACP_Common::get_post_type_slug(),
					'post_status' => 'publish',
				];
				$variant_id = wp_insert_post( $args );
				if ( ! is_wp_error( $variant_id ) ) {
					update_post_meta( $variant_id, '_wfacp_version', WFACP_VERSION );
					delete_post_meta( $variant_id, '_bwf_ab_control' );
					$variant_data['variant_id'] = $variant_id;
				}
			}

			return parent::add_variant( $variant_data );
		}

		/**
		 * @param $variant_data
		 *
		 * @return int
		 */
		public function duplicate_variant( $variant_data ) {
			$variant_id                 = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;
			$control_id                 = isset( $variant_data['control_id'] ) ? $variant_data['control_id'] : 0;
			$experiment_id              = isset( $variant_data['experiment_id'] ) ? $variant_data['experiment_id'] : 0;
			$variant_data['control_id'] = ( $control_id < 0 && true === $variant_data['control'] ) ? $variant_id : $control_id;
			$new_post_id                = 0;

			if ( $variant_id > 0 && $experiment_id > 0 ) {
				$new_post_id = $this->duplicate_checkout_page( $variant_id );

				/** update post meta for gutenburg */
				if ( $new_post_id > 0 && get_post( $new_post_id ) instanceof WP_Post ) {
					$post               = get_post( $new_post_id );
					$post->post_content = get_post_field( 'post_content', $variant_id );
					wp_update_post( $post );
				}

				$this->publish_post_status( $new_post_id );
			}
			$variant_data['variant_id'] = ( $new_post_id > 0 ) ? $new_post_id : $variant_id;

			return parent::duplicate_variant( $variant_data );
		}

		public function get_inherit_supported_post_type() {
			return apply_filters( 'BWFABT_checkout_inherit_supported_post_type', array( 'cartflows_step', 'page' ) );
		}

		public function duplicate_checkout_page( $checkout_page_id ) {

			$exclude_metas = array(
				'cartflows_imported_step',
				'enable-to-import',
				'site-sidebar-layout',
				'site-content-layout',
				'theme-transparent-header-meta',
				'_uabb_lite_converted',
				'_astra_content_layout_flag',
				'site-post-title',
				'ast-title-bar-display',
				'ast-featured-img',
				'_thumbnail_id',
			);

			if ( $checkout_page_id == 0 ) {
				return 0;
			}
			$checkout_page = get_post( $checkout_page_id );

			if ( ! is_null( $checkout_page ) && ( $checkout_page->post_type === WFACP_Common::get_post_type_slug() || in_array( $checkout_page->post_type, $this->get_inherit_supported_post_type(), true ) ) ) {

				$suffix_text  = ' - ' . __( 'Copy', 'A/B Experiments for FunnelKit' );
				$args         = [
					'post_title'   => $checkout_page->post_title . $suffix_text,
					'post_content' => $checkout_page->post_content,
					'post_name'    => sanitize_title( $checkout_page->post_title . $suffix_text ),
					'post_type'    => WFACP_Common::get_post_type_slug(),
				];
				$duplicate_id = wp_insert_post( $args );
				if ( is_wp_error( $duplicate_id ) ) {
					return 0;
				}

				global $wpdb;
				$post_meta_all = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $checkout_page_id ) );
				if ( ! empty( $post_meta_all ) ) {
					$sql_query_selects = [];

					if ( in_array( $checkout_page->post_type, $this->get_inherit_supported_post_type(), true ) ) {

						foreach ( $post_meta_all as $meta_info ) {

							$meta_key   = $meta_info->meta_key;
							$meta_value = $meta_info->meta_value;

							if ( ! in_array( $meta_key, $exclude_metas, true ) ) {
								if ( strpos( $meta_key, 'wcf-' ) === false ) {

									if ( $meta_key === '_wp_page_template' ) {
										$meta_value = ( strpos( $meta_value, 'cartflows' ) !== false ) ? str_replace( 'cartflows', "wfacp", $meta_value ) : $meta_value;
									}
									$meta_key   = esc_sql( $meta_key );
									$meta_value = esc_sql( $meta_value );

									$sql_query_selects[] = "($duplicate_id, '$meta_key', '$meta_value')";//db call ok; no-cache ok; WPCS: unprepared SQL ok.
								}
							}
						}
					} else {
						update_option( WFACP_SLUG . '_c_' . $duplicate_id, get_option( WFACP_SLUG . '_c_' . $checkout_page_id, [] ), 'no' );
						foreach ( $post_meta_all as $meta_info ) {

							$meta_key = $meta_info->meta_key;

							if ( $meta_key === '_bwf_ab_variation_of' ) {
								continue;
							}

							$meta_key   = esc_sql( $meta_key );
							$meta_value = esc_sql( $meta_info->meta_value );


							$sql_query_selects[] = "($duplicate_id, '$meta_key', '$meta_value')"; //db call ok; no-cache ok; WPCS: unprepared SQL ok.
						}
					}

					$sql_query_meta_val = implode( ',', $sql_query_selects );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta ) );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared

					if ( in_array( $checkout_page->post_type, $this->get_inherit_supported_post_type(), true ) ) {
						$template = WFFN_Core()->admin->get_selected_template( $checkout_page_id, $post_meta_all );
						if ( isset( $template['selected_type'] ) && $template['selected_type'] === 'wp_editor' ) {
							$template = [
								'selected'      => 'embed_forms_4',
								'selected_type' => 'embed_forms',
							];
						}
						update_post_meta( $duplicate_id, '_wfacp_selected_design', $template );
					}
					do_action( 'wffn_step_duplicated', $duplicate_id );

					return $duplicate_id;

				}

				if ( in_array( $checkout_page->post_type, $this->get_inherit_supported_post_type(), true ) ) {
					$template = WFFN_Core()->admin->get_selected_template( $checkout_page_id, $post_meta_all );
					if ( isset( $template['selected_type'] ) && $template['selected_type'] === 'wp_editor' ) {
						$template = [
							'selected'      => 'embed_forms_4',
							'selected_type' => 'embed_forms',
						];
					}
					update_post_meta( $duplicate_id, '_wfacp_selected_design', $template );
				}
				do_action( 'wffn_step_duplicated', $duplicate_id );

				return $duplicate_id;
			}


			return 0;
		}


		/**
		 * @param $experiment
		 * @param $variant_id
		 *
		 * @return bool
		 */
		public function draft_variant( $experiment, $variant_id ) {
			$draft = false;
			if ( $variant_id > 0 ) {
				$funnel_post = get_post( $variant_id );
				if ( ! is_null( $funnel_post ) ) {
					$draft = wp_update_post( array(
						'ID'          => $variant_id,
						'post_status' => 'draft',
					) );
				}
			}

			if ( ! is_wp_error( $draft ) && absint( $draft ) === absint( $variant_id ) ) {
				parent::draft_variant( $experiment, $variant_id );

				return true;
			}

			return false;
		}


		/**
		 * @param $args
		 *
		 * @return mixed
		 */
		public function wfacp_add_control_meta_query( $args ) {
			$meta_query = array(
				'key'     => '_bwf_ab_variation_of',
				'compare' => 'NOT EXISTS',
				'value'   => ''
			);

			if ( isset( $args['meta_query'] ) ) {
				array_push( $args['meta_query'], $meta_query );
			} else {
				$args['meta_query'] = $meta_query;
			}

			if ( true === $this->control_query ) {
				$args['post_status']  = array( 'publish', 'draft' );
				$args['get_existing'] = true;
			}

			return $args;
		}

		/**
		 * Copying existing control data to new control data when a variant wins
		 *
		 * @param $control_data
		 * @param $new_variant_id
		 */
		public function copy_control_data_to_new_control( $control_data, $new_variant_id ) {
			parent::copy_control_data_to_new_control( $control_data, $new_variant_id );
		}

		/**
		 * Copying winner data to control going to live after deleting existing control data
		 *
		 * @param $control_id
		 * @param $winner_variant_id
		 */
		public function copy_winner_data_to_control( $control_id, $winner_variant_id ) {
			global $wpdb;
			$exclude_metas = array(
				'_bwf_in_funnel',
				'_bwf_ab_variation_of',
				'_wp_old_slug',
				'_wfacp_version'
			);

			$post_meta_all = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $winner_variant_id ) );
			$post_content  = get_post_field( 'post_content', $winner_variant_id );
			wp_update_post( wp_slash( [ 'ID' => $control_id, 'post_content' => $post_content ] ) );
			$control_metas = get_post_meta( $control_id );

			if ( ! empty( $post_meta_all ) ) {

				$content = '';
				$is_oxy  = false;

				foreach ( $post_meta_all as $meta_info ) {

					$meta_key = $meta_info->meta_key;

					if ( in_array( $meta_key, $exclude_metas, true ) ) {
						continue;
					}

					if ( $meta_key === '_elementor_data' ) {
						$content = $meta_info->meta_value;
					}

					if ( $meta_key === 'ct_builder_shortcodes' ) {
						$is_oxy = true;
					}

					$meta_key   = esc_sql( $meta_key );
					$meta_value = esc_sql( $meta_info->meta_value );

					if ( ! isset( $control_metas[ $meta_key ] ) ) {
						$sql_query_meta_val = "($control_id, '$meta_key', '$meta_value')";
						$sql_query_meta     = $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared
					} else {
						$sql_query_meta = $wpdb->prepare( "UPDATE %1s SET `meta_value` = '" . $meta_value . "' WHERE `post_id` = " . $control_id . " AND `meta_key` = '" . $meta_key . "'", $wpdb->postmeta );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
					}

					$wpdb->query( $sql_query_meta ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
				update_post_meta( $winner_variant_id, '_bwf_ab_variation_of', $control_id );
				update_option( 'wfacp_c_' . $control_id, get_option( 'wfacp_c_' . $winner_variant_id, [] ) );

				if ( $content !== '' && class_exists( 'WFFN_Common' ) ) {
					WFFN_Common::maybe_elementor_template( $winner_variant_id, $control_id );
				}

				if ( true === $is_oxy ) {
					$this->replace_oxygen_page_css_with_winner( $winner_variant_id, $control_id );
				}

				$wfacp_transient_obj = WooFunnels_Transient::get_instance();
				$meta_key            = 'wfacp_post_meta' . absint( $control_id );
				$wfacp_transient_obj->delete_transient( $meta_key, WFACP_SLUG );

			}

		}

		/**
		 * @param BWFABT_Variant $variant
		 * @param $experiment
		 *
		 * @return string|URL
		 */
		public function get_variant_heading_url( $variant, $experiment ) {
			return BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
				'page'     => 'wfacp',
				'wfacp_id' => $variant->get_id(),
			), admin_url( 'admin.php' ) ) );
		}


		/**
		 * @param BWFABT_Variant $variant
		 * @param BWFABT_Experiment $experiment
		 *
		 * @return array|array[]
		 */
		public function get_variant_row_actions( $variant, $experiment ) {

			$row_actions = array(
				'edit' => array(
					'text' => __( 'Edit', 'woofunnels-ab-tests' ),
					'link' => BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
						'page'     => 'wfacp',
						'wfacp_id' => $variant->get_id(),
					), admin_url( 'admin.php' ) ) ),
				)
			);

			return array_merge( $row_actions, parent::get_variant_row_actions( $variant, $experiment ) );
		}

		/**
		 * @param $variant
		 * @param $experiment
		 * @param false $force
		 *
		 * @return bool
		 */
		public function delete_variant( $variant, $experiment, $force = false ) {
			$variant_id = $variant->get_id();
			if ( true === $force ) {
				if ( ! is_null( get_post( $variant_id ) ) ) {
					wp_delete_post( $variant_id, $force );
				}
			}

			return parent::delete_variant( $variant, $experiment, $force );
		}

		/**
		 * @param $control_variant
		 * @param $experiment
		 * @param $new_control
		 *
		 * @return false|mixed
		 */
		public function transfer_control( $control_variant, $experiment, $new_control ) {
			$transferred    = false;
			$original_title = get_the_title( $control_variant->get_id() );
			$new_control_id = $new_control->get_id();
			if ( $new_control_id > 0 ) {
				$funnel_post = get_post( $new_control_id );
				if ( ! is_null( $funnel_post ) ) {
					$transferred = wp_update_post( array(
						'ID'         => $new_control_id,
						'post_title' => $original_title,
					) );
				}
			}
			if ( $new_control_id === $transferred ) {
				return parent::transfer_control( $control_variant, $experiment, $new_control );
			}

			return false;
		}

		/**
		 * @param $variant_id
		 * @param $experiment
		 *
		 * @return bool
		 */
		public function is_variant_active( $variant_id ) {
			$active = false;
			if ( $variant_id > 0 ) {
				$active = ( 'publish' === get_post_status( $variant_id ) );
			}

			return $active;
		}

		/**
		 * @param $variant_id
		 *
		 * @return array|int|string
		 */
		public function get_variant_desc( $variant_id ) {
			$desc = '';
			if ( $variant_id > 0 ) {
				$desc = get_post_field( 'post_content', $variant_id );
			}

			return $desc;
		}

		/**
		 * @param $variant_id
		 *
		 * @return mixed
		 */
		public function get_variant_title( $variant_id ) {
			$title = $variant_id;
			if ( $variant_id > 0 ) {
				$title = get_the_title( $variant_id );
			}

			return $title;
		}

		/**
		 * Redirecting to ab test decided aero page.
		 */
		public function maybe_redirect_to_ab_aero() {

			global $post;
			$wfacp_post_id = isset( $post->ID ) ? $post->ID : 0;
			if ( $wfacp_post_id > 0 && WFACP_Common::get_post_type_slug() === $post->post_type ) {

				if ( ! $this->maybe_control_has_active_experiment( $post ) ) {
					return;
				}
				$active_test_id = $this->get_running_test_id( $wfacp_post_id );
				if ( ! empty( $active_test_id ) && $active_test_id > 0 && true === $this->validate_run( $active_test_id ) ) {
					$experiment = BWFABT_Core()->admin->get_experiment( $active_test_id );

					$variation_id = $this->get_variation_to_run( $experiment );
					if ( 0 !== intval( $variation_id ) && intval( $variation_id ) !== intval( $wfacp_post_id ) ) {
						if ( $this->maybe_enable_override_permalink() ) {
							$this->override_control_content_by_variant( $variation_id );
						} else {
							$query_var = $_GET;//phpcs:ignore
							$slug      = WFACP_Common::get_post_type_slug();

							$redirect_url = get_permalink( $variation_id );
							if ( ! empty( $query_var ) ) {
								unset( $query_var[ $slug ] );
								$redirect_url = add_query_arg( $query_var, $redirect_url );
							}
							wp_safe_redirect( $redirect_url );
							exit();
						}
					}
				}
			}
		}

		public function set_global_page_id( $aero_global_page_id ) {

			if ( ! $this->maybe_control_has_active_experiment( $aero_global_page_id ) ) {
				return;
			}
			$active_test_id = $this->get_running_test_id( $aero_global_page_id );

			if ( $active_test_id > 0 ) {
				$experiment  = BWFABT_Core()->admin->get_experiment( $active_test_id );
				$new_aero_id = $this->get_variation_to_run( $experiment );
				if ( 0 !== intval( $new_aero_id ) && intval( $new_aero_id ) !== intval( $aero_global_page_id ) ) {
					WFACP_Core()->template_loader->set_override_checkout_page_id( $new_aero_id );
				}
			}


		}

		public function check_if_autobot_cart_restored() {
			$this->is_cart_restored = true;
		}

		public function record_ab_test_views( $wfacp_id ) {


			if ( $wfacp_id < 1 || ! class_exists( 'WFCO_Model_Report_views' ) ) {
				BWFABT_Core()->admin->log( "AB WFACP ID: $wfacp_id, Report views class exist: " . class_exists( 'WFCO_Model_Report_views' ) );

				return;
			}

			$running_ab_test_id = $this->get_running_test_id_on_step( $wfacp_id );

			if ( $running_ab_test_id < 1 ) {
				BWFABT_Core()->admin->log( "AB WFACP ID: $wfacp_id, Running test id: $running_ab_test_id" );

				return;
			}

			$status = WFACP_Core()->reporting->get_session_key( 'ab_' . $wfacp_id );

			/** Already captured */
			if ( true === $status ) {
				BWFABT_Core()->admin->log( "AB WFACP ID: $wfacp_id, Status: $status, Already captured. " );

				return;
			}
			/** Check if AutoBot installed and cart tracking in enabled and Cart is restored, don't require cart initiate increment */
			if ( true === $this->is_cart_restored ) {
				WFACP_Core()->reporting->update_session_key( 'ab_' . $wfacp_id );
				BWFABT_Core()->admin->log( "AB WFACP ID: $wfacp_id, Card restored. " );

				return;
			}

			WFCO_Model_Report_views::update_data( date( 'Y-m-d', current_time( 'timestamp' ) ), $wfacp_id, 12 );
			WFACP_Core()->reporting->update_session_key( 'ab_' . $wfacp_id );
		}

		public function wfacp_clear_ab_view_session( $order_id ) {
			$aero_id = ( $order_id > 0 ) ? get_post_meta( $order_id, '_wfacp_post_id', true ) : 0;
			if ( $aero_id > 0 && ! is_null( WC()->session ) && WC()->session->has_session() ) {
				WC()->session->set( 'wfacp_view_session_ab_' . $aero_id, false );
			}
		}

		/**
		 * @param BWFABT_Experiment $experiment
		 */
		public function reset_stats( $experiment ) {
			$type = 12;
			$this->delete_ab_report_views( $experiment, $type );
			$experiment->set_last_reset_date( BWFABT_Core()->get_dataStore()->now() );
			$experiment->save( array() );

		}

		/**
		 * @param $out
		 *
		 * @return mixed
		 */
		public function change_wfacp_in_embed_form( $out ) {
			$wfacp_post_id = isset( $out['id'] ) ? $out['id'] : 0;
			if ( $wfacp_post_id > 0 ) {
				if ( ! $this->maybe_control_has_active_experiment( $wfacp_post_id ) ) {
					return $out;
				}
				$active_test_id = $this->get_running_test_id( $wfacp_post_id );
				if ( $active_test_id > 0 ) {
					$experiment  = BWFABT_Core()->admin->get_experiment( $active_test_id );
					$new_aero_id = $this->get_variation_to_run( $experiment );
					if ( $new_aero_id > 0 ) {
						$out['id'] = $new_aero_id;
					}
				}
			}

			return $out;
		}

		/**
		 * @param $step_id
		 *
		 * @return mixed
		 */
		public function get_entity_view_link( $step_id ) {
			$link = parent::get_entity_view_link( $step_id );
			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$link = esc_url( get_the_permalink( $step_id ) );
				$type = get_post_type( $step_id );
				if ( 'wfacp_checkout' === $type ) {
					if ( empty( WFACP_Common::get_page_product( $step_id ) ) ) {
						$link = add_query_arg( [ 'wfacp_preview' => true ], $link );
						$link = str_replace( "#038;", "&", $link );
					}
				}
			}

			return $link;
		}

		/**
		 * @param $step_ids
		 * @param $experiment_id
		 *
		 * @return array|false[]
		 */
		public function get_analytics_data( $step_ids, $experiment_id, $is_interval = '', $int_request = '' ) {
			global $wpdb;
			$data           = [];
			$ids            = [];
			$date_col       = "date";
			$interval_query = '';
			$group_by       = ' GROUP BY object_id ';
			$cov_group_by   = ' GROUP by aero.wfacp_id ';
			$params         = '';

			if ( version_compare( WFACP_VERSION, '2.0.7', '<' ) ) {
				return $data;
			}

			if ( ! is_array( $step_ids ) || count( $step_ids ) === 0 ) {
				return $data;
			}

			foreach ( $step_ids as $step_id ) {
				$ids[]            = $step_id;
				$data[ $step_id ] = array(
					'object_id'         => $step_id,
					'object_name'       => html_entity_decode( get_the_title( $step_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'revenue'           => 0,
					'revenue_per_visit' => 0,
					'conversions'       => 0,
					'views'             => 0,
					'conversion_rate'   => 0,
				);
			}

			if ( count( $ids ) < 1 ) {
				return $data;
			}

			if ( 'interval' === $is_interval ) {
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				$interval_group = $get_interval['interval_group'];
				$group_by       = "GROUP BY " . $interval_group;
				$cov_group_by   = "GROUP BY " . $interval_group;
				$params         = ", 0 as 'revenue', 0 as 'converted' ";
			}

			$step_ids = esc_sql( implode( ',', $ids ) );

			$get_all_dates = BWFABT_Core()->get_dataStore()->get_experiment_time_chunk( $experiment_id );

			$date_query = "";
			$conv_query = "";

			if ( is_array( $get_all_dates ) && count( $get_all_dates ) ) {
				foreach ( $get_all_dates as $date ) {

					$start_date = explode( " ", $date['start_date'] );
					$end_date   = explode( " ", $date['end_date'] );
					$date_query .= " ( `date` >= '" . esc_sql( $start_date[0] ) . "' AND `date` <= '" . esc_sql( $end_date[0] ) . "' ) OR ";
					$conv_query .= " ( `date` >= '" . esc_sql( $date['start_date'] ) . "' AND `date` <= '" . esc_sql( $date['end_date'] ) . "' ) OR ";
				}

				$date_query = ' AND ( ' . rtrim( $date_query, " OR " ) . ') ';
				$conv_query = ' AND ( ' . rtrim( $conv_query, " OR " ) . ') ';
			}

			$aero_sql = "SELECT aero.wfacp_id as 'object_id', p.post_title as 'object_name',SUM(aero.total_revenue) as 'total_revenue',COUNT(aero.ID) as cn, 'checkout' as 'type' " . $interval_query . " FROM " . $wpdb->prefix . 'wfacp_stats' . " AS aero LEFT JOIN " . $wpdb->prefix . 'posts' . " as p ON aero.wfacp_id  = p.id WHERE aero.wfacp_id IN (" . $step_ids . ") " . $conv_query . " " . $cov_group_by . " ORDER BY aero.wfacp_id ASC";

			$get_all_checkout_records = $wpdb->get_results( $aero_sql, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
				$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}
			}

			if ( is_array( $get_all_checkout_records ) && count( $get_all_checkout_records ) > 0 ) {
				foreach ( $get_all_checkout_records as $item ) {
					if ( isset( $data[ $item['object_id'] ] ) ) {
						$data[ $item['object_id'] ]['object_name'] = $item['object_name'];
						$data[ $item['object_id'] ]['revenue']     = $item['total_revenue'];
						$data[ $item['object_id'] ]['conversions'] = intval( $item['cn'] );
					}
				}
			}

			$get_query = "SELECT object_id, SUM( CASE WHEN type = 12 THEN `no_of_sessions` END ) AS viewed " . $params . " " . $interval_query . " FROM " . $wpdb->prefix . 'wfco_report_views' . "  WHERE object_id IN (" . $step_ids . ") " . $date_query . " " . $group_by . " ORDER BY object_id ASC";

			$get_data = $wpdb->get_results( $get_query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
				$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}
			}

			if ( 'interval' === $is_interval ) {
				if ( is_array( $get_all_checkout_records ) && count( $get_all_checkout_records ) > 0 ) {
					foreach ( $get_all_checkout_records as $item ) {
						foreach ( $get_data as &$i ) {
							if ( isset( $item['time_interval'] ) && $i['time_interval'] && $item['object_id'] === $i['object_id'] ) {
								if ( $item['time_interval'] === $i['time_interval'] ) {
									$i['revenue']   = $item['total_revenue'];
									$i['converted'] = (int) $item['cn'];
								}
							}
						}
					}
				}

				return $get_data;
			}

			if ( is_array( $get_data ) && count( $get_data ) > 0 ) {
				foreach ( $get_data as $item ) {
					if ( isset( $data[ $item['object_id'] ] ) ) {
						$data[ $item['object_id'] ]['views']             = is_null( $item['viewed'] ) ? 0 : intval( $item['viewed'] );
						$data[ $item['object_id'] ]['conversion_rate']   = $this->get_percentage( $item['viewed'], $data[ $item['object_id'] ]['conversions'] );
						$data[ $item['object_id'] ]['revenue_per_visit'] = ( absint( $item['viewed'] ) !== 0 ) ? round( $data[ $item['object_id'] ]['revenue'] / $item['viewed'], 2 ) : 0;

					}
				}
			}

			return $data;
		}
	}

	BWFABT_Controller_Aero_Checkout::get_instance();
}