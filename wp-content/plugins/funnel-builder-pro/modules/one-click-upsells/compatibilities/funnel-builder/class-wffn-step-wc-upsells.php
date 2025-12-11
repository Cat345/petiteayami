<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class contains all the upstroke related funnel functionality
 * Class WFFN_Step_WC_Upsells
 */
if ( ! class_exists( 'WFFN_Step_WC_Upsells' ) ) {


	class WFFN_Step_WC_Upsells extends WFFN_Step {

		private static $ins = null;
		public $slug = 'wc_upsells';
		public $list_priority = 30;


		/**
		 * WFFN_Step_WC_Upsells constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_action( 'wfocu_funnels_from_external_base', array( $this, 'maybe_filter_upsells' ) );
			add_filter( 'wfocu_session_db_insert_data', array( $this, 'funnel_id_recorded' ), 10, 2 );
			add_filter( 'maybe_setup_funnel_for_breadcrumb', [ $this, 'maybe_funnel_breadcrumb' ] );
			add_filter( 'wfocu_fb_pixel_ids', array( $this, 'override_pixel_key' ) );
			add_filter( 'wfocu_get_ga_key', array( $this, 'override_ga_key' ) );
			add_filter( 'wfocu_get_gad_key', array( $this, 'override_gad_key' ) );
			add_filter( 'wfocu_get_pint_key', array( $this, 'override_pint_key' ) );
			add_filter( 'wfocu_get_conversion_label', array( $this, 'override_conversion_key' ) );
			add_filter( 'wfocu_tracking_conversion_api_test_event_code', array( $this, 'override_conversion_api_test_event_code' ) );
			add_filter( 'wfocu_tracking_conversion_api_access_token', array( $this, 'override_conversion_api_access_token' ) );

			add_action( 'wfocu_before_cancelling_order', array( $this, 'maybe_report_checkout' ) );
			add_filter( 'wffn_rest_get_templates', array( $this, 'add_customizer_templates' ) );

		}

		/**
		 * @return WFFN_Step_WC_Upsells|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @param $steps
		 *
		 * @return array
		 */
		public function get_step_data() {
			return array(
				'type'        => $this->slug,
				'title'       => $this->get_title(),
				'popup_title' => sprintf( __( 'Add %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_title() ),
				'dashicons'   => 'dashicons-tag',
				'icon'        => 'tags',
				'label_class' => 'bwf-st-c-badge-green',
				'substeps'    => array(),
			);
		}

		/**
		 * Return title of upstroke step
		 */
		public function get_title() {
			return __( 'One Click Upsells', 'woofunnels-upstroke-one-click-upsell' );
		}

		/**
		 * @param $type
		 *
		 * @return array
		 */
		public function get_step_designs( $term, $funnel_id = 0 ) {
			remove_all_filters( 'wfocu_add_control_meta_query' );
			$this->funnel_id = $funnel_id;
			add_filter( 'wfocu_add_control_meta_query', [ $this, 'search_any_post_status' ], 9 );
			$get_upstroke_posts = WFOCU_Core()->funnels->setup_funnels();
			$get_all_ids        = wp_list_pluck( $get_upstroke_posts, 'id' );

			$get_upstroke_posts = array_map( 'get_post', $get_all_ids );
			$inside_funnels     = [];
			$outside_funnels    = [];
			if ( is_array( $get_upstroke_posts ) && count( $get_upstroke_posts ) > 0 ) {

				foreach ( $get_upstroke_posts as $post ) {
					if ( ! empty( $term ) && false === strpos( strtolower( $post->post_title ), strtolower( $term ) ) && ! is_numeric( $term ) ) {
						continue;
					}
					$post_type     = get_post_type( $post->ID );
					$bwf_funnel_id = get_post_meta( $post->ID, '_bwf_in_funnel', true );
					$data          = [];
					if ( 'cartflows_step' === $post_type ) {
						$meta = get_post_meta( $post->ID, 'wcf-step-type', true );
						if ( 'upsell' === $meta ) {
							$data = array(
								'id'   => $post->ID,
								'name' => $post->post_title,
							);
						}
					} else {
						$data = array(
							'id'   => $post->ID,
							'name' => $post->post_title,
						);
					}


					if ( empty( $data ) ) {
						continue;
					}

					$funnel = new WFFN_Funnel( $bwf_funnel_id );
					if ( absint( $bwf_funnel_id ) > 0 && ! empty( $funnel->get_title() ) ) {
						if ( ! isset( $inside_funnels[ $bwf_funnel_id ] ) ) {
							$inside_funnels[ $bwf_funnel_id ] = [ 'name' => $funnel->get_title(), 'id' => $bwf_funnel_id, "steps" => [] ];
						}
						$inside_funnels[ $bwf_funnel_id ]['steps'][] = $data;
					} else {
						$outside_funnels[] = $data;
					}

				}
			}

			if ( ! empty( $outside_funnels ) ) {
				$outside_funnels = [ [ 'name' => __( 'Other Pages', 'woofunnels-upstroke-one-click-upsell' ), 'id' => 0, 'steps' => $outside_funnels ] ];
			}

			return array_merge( $inside_funnels, $outside_funnels );
		}

		public function search_any_post_status( $existing_args ) {
			$existing_args                = is_array( $existing_args ) ? $existing_args : [];
			$existing_args['post_type']   = array( WFOCU_Common::get_funnel_post_type_slug(), 'cartflows_step', 'page' );
			$existing_args['post_status'] = 'any';
			if ( $this->funnel_id > 0 ) {
				$existing_args['meta_query'] = [
					[
						'key'   => '_bwf_in_funnel',
						'value' => $this->funnel_id,
					]
				];
			}

			return $existing_args;
		}


		/**
		 * @param $funnel_id
		 * @param $type
		 * @param $posted_data
		 *
		 * @return stdClass
		 */
		public function add_step( $funnel_id, $posted_data ) {
			$title            = isset( $posted_data['title'] ) ? $posted_data['title'] : '';
			$funnel_to_create = array(
				'title'           => $title,
				'status'          => 'publish',
				'offers_override' => array(
					0 => array( 'meta_override' => array( '_wfocu_setting_override' => array( 'products' => new stdClass(), 'fields' => new stdClass() ) ) ),
				),
			);

			if ( isset( $posted_data['offer_title'] ) ) {
				$funnel_to_create['offer_title'] = $posted_data['offer_title'];
			}
			if ( isset( $posted_data['offer_inherit'] ) ) {
				$funnel_to_create['offer_inherit'] = $posted_data['offer_inherit'];
			}

			$step_id           = WFOCU_Core()->funnels->generate_preset_funnel_data( $funnel_to_create );
			$posted_data['id'] = ( $step_id > 0 ) ? $step_id : 0;

			return parent::add_step( $funnel_id, $posted_data );
		}

		/**
		 * @param $funnel_id
		 * @param $upsell_step_id
		 * @param $type
		 * @param $posted_data
		 *
		 * @return stdClass
		 */
		public function duplicate_step( $funnel_id, $upsell_step_id, $posted_data ) {
			global $wpdb;
			$duplicate_upsell_id = 0;
			if ( $upsell_step_id > 0 && $funnel_id > 0 ) {
				$suffix_text = ' - ' . __( 'Copy', 'woofunnels-upstroke-one-click-upsell' );
				if ( did_action( 'wffn_duplicate_funnel' ) > 0 ) {
					$suffix_text = '';
				}

				$offers    = array();
				$post_type = get_post_type( $upsell_step_id );

				$post_status = ( isset( $posted_data['original_id'] ) && $posted_data['original_id'] > 0 ) ? get_post_status( $posted_data['original_id'] ) : 'publish';


				if ( 'cartflows_step' === $post_type || 'page' === $post_type ) {
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

					$meta_selects = array();

					$post_meta_all = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$upsell_step_id" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					if ( is_array( $post_meta_all ) && count( $post_meta_all ) > 0 ) {
						$meta_selects[] = (object) [ 'meta_key' => '_offer_type', 'meta_value' => 'upsell' ];
						foreach ( $post_meta_all as $meta_info ) {
							$meta_key   = $meta_info->meta_key;
							$meta_value = $meta_info->meta_value;
							if ( ! in_array( $meta_key, $exclude_metas, true ) ) {
								if ( ( strpos( $meta_key, 'wcf-' ) === false ) ) {
									if ( $meta_key === '_wp_page_template' ) {
										$meta_value = ( strpos( $meta_value, 'cartflows' ) !== false ) ? str_replace( 'cartflows', "wfocu", $meta_value ) : $meta_value;
									}
									$meta_selects[] = (object) [ 'meta_key' => $meta_key, 'meta_value' => $meta_value ];

								}
							}
						}


					}
					$meta_settings             = new stdClass();
					$meta_vals                 = new stdClass();
					$meta_vals->template       = 'wfocu-custom-empty';
					$meta_vals->template_group = 'custom';
					$meta_settings->meta_key   = '_wfocu_setting';
					$meta_settings->meta_value = maybe_serialize( $meta_vals );
					array_push( $meta_selects, $meta_settings );

					$offers[] = array(
						'id'               => $upsell_step_id,
						'name'             => get_the_title( $upsell_step_id ) . $suffix_text,
						'type'             => 'upsell',
						'state'            => 0,
						'parent_meta'      => $meta_selects,
						'_customizer_data' => get_option( 'wfocu_c_' . $upsell_step_id, '' ),
						'post_content'     => get_post_field( 'post_content', $upsell_step_id ),
					);

					$funnel_to_create = array(
						'title'       => get_the_title( $upsell_step_id ) . $suffix_text,
						'description' => get_post_field( 'post_content', $upsell_step_id ),
						'status'      => $post_status,
						'priority'    => WFOCU_Common::get_next_funnel_priority(),
						'offers'      => $offers,
						'meta'        => array(
							'_wfocu_is_rules_saved'   => get_post_meta( $upsell_step_id, '_wfocu_is_rules_saved', true ),
							'_wfocu_rules'            => get_post_meta( $upsell_step_id, '_wfocu_rules', true ),
							'_funnel_steps'           => array(),
							'_funnel_upsell_downsell' => array(),
						),
					);

					$duplicate_upsell_id = WFOCU_Core()->funnels->generate_preset_funnel_data( $funnel_to_create );

					if ( $duplicate_upsell_id > 0 ) {
						$posted_data['id'] = $duplicate_upsell_id;
						$funnel_settings   = get_post_meta( $upsell_step_id, '_wfocu_settings', true );
						$funnel_settings   = is_array( $funnel_settings ) ? $funnel_settings : array();

						$funnel_steps = get_post_meta( $duplicate_upsell_id, '_funnel_steps', true );

						if ( is_array( $funnel_steps ) && count( $funnel_steps ) > 0 ) {
							foreach ( $funnel_steps as $step ) {
								$post_template = get_post_meta( $step['id'], '_funnel_steps', true );
								if ( strpos( $post_template, 'canvas' ) !== false || strpos( $post_template, 'boxed' ) !== false ) {
									update_post_meta( $step['id'], '_wp_page_template', $post_template . '.php' );
								}
							}
						}

						$funnel_settings['funnel_priority'] = '100';

						update_post_meta( $duplicate_upsell_id, '_wfocu_settings', $funnel_settings );
					}
				} else {


					$resp                = WFOCU_AJAX_Controller::duplicating_funnel( $upsell_step_id, array(
						'msg'    => '',
						'status' => true,
					) );
					$duplicate_upsell_id = $resp['duplicate_id'];
					$posted_data['id']   = $duplicate_upsell_id;
				}


			}

			if ( isset ( $posted_data['id'] ) && $posted_data['id'] > 0 ) {
				$new_title = isset( $posted_data['existing'] ) && isset( $posted_data['title'] ) ? $posted_data['title'] : '';
				if ( ! empty( $new_title ) ) {
					$arr = [ 'ID' => $posted_data['id'], 'post_title' => $new_title ];
					wp_update_post( $arr );
				}
			}

			return parent::duplicate_step( $funnel_id, $upsell_step_id, $posted_data );
		}

		/**
		 * @param $step_id
		 *
		 * @return mixed
		 */
		public function get_entity_edit_link( $step_id ) {
			$link = parent::get_entity_edit_link( $step_id );
			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$link = esc_url( BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
					'page'    => 'upstroke',
					'section' => 'offers',
					'edit'    => $step_id,
				), admin_url( 'admin.php' ) ) ) );
			}

			return $link;
		}

		/**
		 * @param $step_id
		 *
		 * @return mixed
		 */
		public function get_entity_view_link( $step_id ) {
			$link = parent::get_entity_view_link( $step_id );
			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$steps = WFOCU_Core()->funnels->get_funnel_steps( $step_id );

				if ( ( is_array( $steps ) && count( $steps ) > 0 ) && isset( $steps[0]['id'] ) ) {
					$offer_data = WFOCU_Core()->offers->get_offer( $steps[0]['id'] );

					if ( is_object( $offer_data ) && 'custom-page' === $offer_data->template ) {
						$custom_page = get_post_meta( $steps[0]['id'], '_wfocu_custom_page', true );
						$link        = ( $custom_page !== '' ) ? get_permalink( $custom_page ) : get_permalink( $steps[0]['id'] );
					} else {
						$link = get_permalink( $steps[0]['id'] );
					}
				}
			}

			return $link;
		}

		public function get_entity_tags( $step_id, $funnel_id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter


			$funnel_rules = get_post_meta( $step_id, '_wfocu_rules', true );
			$has_rules    = $no_product = $no_offers = false;
			foreach ( is_array( $funnel_rules ) ? $funnel_rules : array() as $rule_groups ) {
				foreach ( is_array( $rule_groups ) ? $rule_groups : array() as $rules_data ) {
					foreach ( is_array( $rules_data ) ? $rules_data : array() as $rules_arr ) {
						if ( isset( $rules_arr['rule_type'] ) && ( 'general_always' !== $rules_arr['rule_type'] && 'general_always_2' !== $rules_arr['rule_type'] ) ) {
							$has_rules = true;
							break 3;
						}
					}
				}
			}
			$funnel_steps = WFOCU_Core()->funnels->get_funnel_steps( $step_id );
			if ( ! is_array( $funnel_steps ) || count( $funnel_steps ) < 1 ) {
				$no_offers = true;
			} else {
				$funnel_statuses = wp_list_pluck( $funnel_steps, 'state' );
				if ( ! in_array( '1', $funnel_statuses, true ) ) {
					$no_product = true;
				}
				if ( ! $no_product ) {
					$offer_id   = wp_list_pluck( $funnel_steps, 'id', true );
					$offer_id   = is_array( $offer_id ) && count( $offer_id ) > 0 ? $offer_id[0] : 0;
					$offer_meta = get_post_meta( $offer_id, '_wfocu_setting', true );
					if ( ! empty( $offer_meta ) && isset( $offer_meta->products ) ) {
						$products = (array) $offer_meta->products;
						if ( is_array( $products ) && count( $products ) === 0 ) {
							$no_product = true;
						}
					}
				}
			}
			$flags = array();
			if ( $has_rules ) {
				$flags['has_rules'] = array(
					'label'       => __( 'Has Rules', 'woofunnels-upstroke-one-click-upsell' ),
					'label_class' => 'bwf-st-c-badge-green',
					'edit'        => function_exists( 'wffn_rest_api_helpers' ) ? wffn_rest_api_helpers()->get_entity_url( 'upsell', 'rules', $step_id ) : '',
				);
			}
			if ( $no_offers ) {
				$flags['no_offers'] = array(
					'label'       => __( 'No offers', 'woofunnels-upstroke-one-click-upsell' ),
					'label_class' => 'bwf-st-c-badge-red',
				);
			}

			return $flags;
		}

		/**
		 * @return array|void
		 */
		public function get_supports() {
			return array_unique( array_merge( parent::get_supports(), [ 'expand' ] ) );
		}


		/**
		 * @param $funnels
		 *
		 * @return array
		 */
		public function maybe_filter_upsells( $funnels ) {
			$funnel          = WFFN_Core()->data->get_session_funnel();
			$current_step    = WFFN_Core()->data->get_current_step();
			$current_step_id = isset( $current_step['id'] ) ? $current_step['id'] : 0;


			/**
			 * Check if the respective method exists to go further
			 */
			if ( method_exists( WFFN_Core()->admin, 'get_license_config' ) ) {
				$License = WooFunnels_licenses::get_instance();
				$License->get_plugins_list();
				$state          = $this->get_current_app_state();


				if ( in_array( $state, [ 'pro_without_license', 'license_expired' ], true ) ) {
					WFOCU_Core()->session_db->set_skip_id( 12 );
					WFOCU_Core()->log->log( 'Upsell is not allowed due to license issue' );
					return [];
				}
			}

			if ( WFFN_Core()->data->has_valid_session() && ! empty( $current_step ) && wffn_is_valid_funnel( $funnel ) && $this->validate_environment( $current_step ) ) {

				/**
				 * Allow AB test to pass control ID instead of variant so that we could find the correct thankyou pages
				 */
				if ( $current_step_id > 0 ) {
					$current_step['id'] = apply_filters( 'wffn_maybe_get_ab_control', $current_step_id );
				}

				$all_upsells = $this->maybe_get_upsells($current_step, $funnel);

				return apply_filters('wffn_filter_upsells', $all_upsells, $current_step);
			} else {

				$upsells = $this->get_setup_global_upsells_from_funnel( $funnels );

				$upsells = apply_filters( 'wffn_upsells_open_without_funnel', $upsells, $funnel, $current_step );

				if ( ! empty( $upsells ) ) {
					if ( is_string( $upsells ) ) {
						$all_ids = explode( ',', $upsells );
						$all_ids = array_map( function ( $val ) {
							return [ 'id' => intval( $val ) ];
						}, $all_ids );

						return $all_ids;
					}


					return $upsells;
				}


				WFFN_Core()->logger->log( 'WFFN upsell funnel details for skip given below ' . print_r( array( /* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r */
						'funnels'              => $funnels,
						'has_valid_session'    => ( WFFN_Core()->data->has_valid_session() ),
						'current_step'         => $current_step,
						'wffn_is_valid_funnel' => ( wffn_is_valid_funnel( $funnel ) ),
						'validate_environment' => ( $this->validate_environment( $current_step ) ),

					), true ), 'wffn', true );
			}

			return $funnels;
		}

		/**
		 * @param $current_step
		 *
		 * @return bool
		 */
		public function validate_environment( $current_step ) {

			if ( empty( $current_step ) ) {
				return false;
			}

			$wfacp_id = WFOCU_Core()->data->get_posted( 'wfacp_embed_form_page_id', 0 );

			if ( empty( $wfacp_id ) ) {
				// For Dedicated and Global checkout
				$wfacp_id = WFOCU_Core()->data->get_posted( '_wfacp_post_id', 0 );
			}
			if ( empty( $wfacp_id ) ) {
				// For Dedicated and Global checkout
				$wfacp_id = WFOCU_Core()->data->get_posted( 'wfacp_post_id', 0 );
			}

			if ( empty( $wfacp_id ) ) {
				$orderID = WFFN_Core()->data->get( 'wc_order' );
				$order   = wc_get_order( $orderID );
				if (! $order instanceof WC_Order) {

					$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
					$order    = wc_get_order( $order_id );
					if (! $order instanceof WC_Order) {
						WFFN_Core()->logger->log('No Order found.');

						return false;
					}
				}
				$get_checkout_id = $order->get_meta( '_wfacp_post_id', true );
				$wfacp_id        = $get_checkout_id;

			}

			if ( absint( $current_step['id'] ) === absint( $wfacp_id ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @param $current_step
		 * @param $funnel
		 *
		 * @return array
		 */
		public function maybe_get_upsells( $current_step, $funnel ) {
			$this->front_end_request = true;
			$found_step              = false;
			$all_upsells_funnels     = [];
			$targets_step_found      = false;
			foreach ( $funnel->steps as $key => $step ) {

				/**
				 * continue till we found the current step
				 */
				if ( false !== $current_step && absint( $current_step['id'] ) === absint( $step['id'] ) ) {
					$found_step = $key;
					continue;
				}
				/**
				 * Continue if we have not found the current step yet
				 */
				if ( false !== $current_step && false === $found_step ) {
					continue;
				}

				/**
				 * if step is not the type after the current step then break the loop
				 */
				if ( $this->slug !== $step['type'] && true === $targets_step_found ) {
					break;
				}
				if ( $this->slug !== $step['type'] ) {
					continue;
				}

				if ( $this->is_disabled( $this->get_entity_status( $step['id'] ) ) ) {
					WFOCU_Core()->session_db->set_skip_id( 1 );
					continue;
				}
				array_push( $all_upsells_funnels, [ 'id' => $step['id'] ] );
				$targets_step_found = true;


			}

			return $all_upsells_funnels;
		}

		/**
		 * @param $type
		 * @param $step_id
		 * @param $new_status
		 *
		 * @return bool
		 */
		public function switch_status( $step_id, $new_status ) {
			$switched = false;
			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$updated_id  = 0;
				$post_status = get_post_status( $step_id );
				$newStatus   = ( 1 === absint( $new_status ) ) ? 'publish' : WFOCU_SLUG . '-disabled';
				if ( $newStatus !== $post_status ) {
					$updated_id = wp_update_post( array(
						'ID'          => $step_id,
						'post_status' => $newStatus,
					) );
				}
				if ( intval( $step_id ) === intval( $updated_id ) ) {
					$switched = true;
				}
			}

			return $switched;
		}


		public function _get_export_metadata( $step ) {
			return WFOCU_Core()->export->export_a_funnel( $step['id'] );
		}

		public function _process_import( $funnel_id, $step_data ) {
			$ids         = WFOCU_Core()->import->import_from_json_data( array(
				array_merge( $step_data['meta'], array(
					'title'  => $step_data['title'],
					'status' => ( isset( $step_data['status'] ) ? $step_data['status'] : 0 )
				) )
			) );
			$posted_data = [ 'title' => $step_data['title'], 'id' => $ids[0] ];
			parent::add_step( $funnel_id, $posted_data );
		}

		public function has_import_scheduled( $id ) {

			$get_steps = WFOCU_Core()->funnels->get_funnel_steps( $id );
			foreach ( $get_steps as $step ) {
				$template = get_post_meta( $step['id'], '_tobe_import_template', true );
				if ( ! empty( $template ) ) {
					return array(
						'template'      => $template,
						'template_type' => get_post_meta( $step['id'], '_tobe_import_template_type', true )

					);
				}
			}

			return false;
		}

		public function do_import( $id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			return true;
		}

		public function update_template_data( $id ) {
			$get_steps = WFOCU_Core()->funnels->get_funnel_steps( $id );
			foreach ( $get_steps as $step ) {

				$template      = get_post_meta( $step['id'], '_tobe_import_template', true );
				$template_type = get_post_meta( $step['id'], '_tobe_import_template_type', true );

				if ( empty( $template ) || empty( $template_type ) ) {
					continue;
				}
				$meta = get_post_meta( $step['id'], '_wfocu_setting', true );

				if ( is_object( $meta ) ) {
					$meta->template       = $template;
					$meta->template_group = $template_type;
					update_post_meta( $step['id'], '_wfocu_setting', $meta );
					WFOCU_Core()->importer->maybe_import_data( $meta->template_group, $meta->template, $step['id'], $meta );
				}
				if ( '' !== $id ) {
					WFOCU_Common::update_funnel_time( $id );
				}
				delete_post_meta( $step['id'], '_tobe_import_template' );
				delete_post_meta( $step['id'], '_tobe_import_template_type' );
			}
		}

		public function funnel_id_recorded( $args, $upsell_funnel_id ) {

			$fid = get_post_meta( $upsell_funnel_id, '_bwf_in_funnel', true );
			if ( absint( $fid ) > 0 ) {
				$args['fid'] = $fid;
			}

			return $args;

		}

		/**
		 * @param $get_ref
		 *
		 * @return mixed
		 */
		public function maybe_funnel_breadcrumb( $get_ref ) {
			$step_id = filter_input( INPUT_GET, 'edit', FILTER_UNSAFE_RAW );
			if ( empty( $get_ref ) && ! empty( $step_id ) ) {
				$funnel_id = get_post_meta( $step_id, '_bwf_in_funnel', true );
				if ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) {
					return $funnel_id;
				}
			}

			return $get_ref;
		}

		public function override_pixel_key( $key ) {
			$step_id = WFOCU_Core()->data->get_funnel_id();
			$step_id = apply_filters( 'wfocu_print_tracking_script', $step_id );

			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$setting = WFFN_Common::maybe_override_tracking( $step_id );
				if ( is_array( $setting ) ) {
					$key = ( isset( $setting['fb_pixel_key'] ) && ! empty( $setting['fb_pixel_key'] ) ) ? $setting['fb_pixel_key'] : $key;
				}
			}

			return $key;
		}

		/**
		 * @param $key
		 *
		 * @return mixed
		 */
		public function override_ga_key( $key ) {
			$step_id = WFOCU_Core()->data->get_funnel_id();
			$step_id = apply_filters( 'wfocu_print_tracking_script', $step_id );

			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$setting = WFFN_Common::maybe_override_tracking( $step_id );
				if ( is_array( $setting ) ) {
					$key = ( isset( $setting['ga_key'] ) && ! empty( $setting['ga_key'] ) ) ? $setting['ga_key'] : $key;
				}
			}

			return $key;
		}

		/**
		 * @param $key
		 *
		 * @return mixed
		 */
		public function override_gad_key( $key ) {
			$step_id = WFOCU_Core()->data->get_funnel_id();
			$step_id = apply_filters( 'wfocu_print_tracking_script', $step_id );

			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$setting = WFFN_Common::maybe_override_tracking( $step_id );
				if ( is_array( $setting ) ) {
					$key = ( isset( $setting['gad_key'] ) && ! empty( $setting['gad_key'] ) ) ? $setting['gad_key'] : $key;
				}
			}

			return $key;
		}

		/**
		 * @param $key
		 *
		 * @return mixed
		 */
		public function override_pint_key( $key ) {
			$step_id = WFOCU_Core()->data->get_funnel_id();
			$step_id = apply_filters( 'wfocu_print_tracking_script', $step_id );

			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$setting = WFFN_Common::maybe_override_tracking( $step_id );
				if ( is_array( $setting ) ) {
					$key = ( isset( $setting['pint_key'] ) && ! empty( $setting['pint_key'] ) ) ? $setting['pint_key'] : $key;
				}
			}

			return $key;
		}

		/**
		 * @param $key
		 *
		 * @return mixed
		 */
		public function override_conversion_key( $key ) {
			$step_id = WFOCU_Core()->data->get_funnel_id();
			$step_id = apply_filters( 'wfocu_print_tracking_script', $step_id );

			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$setting = WFFN_Common::maybe_override_tracking( $step_id );
				if ( is_array( $setting ) ) {
					$key = ( isset( $setting['gad_conversion_label'] ) && ! empty( $setting['gad_conversion_label'] ) ) ? $setting['gad_conversion_label'] : $key;
				}
			}

			return $key;
		}

		/**
		 * @param $key
		 *
		 * @return mixed
		 */
		public function override_conversion_api_access_token( $key ) {
			$step_id = WFOCU_Core()->data->get_funnel_id();
			$step_id = apply_filters( 'wfocu_print_tracking_script', $step_id );

			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$setting = WFFN_Common::maybe_override_tracking( $step_id );
				if ( is_array( $setting ) ) {
					$key = ( isset( $setting['conversion_api_access_token'] ) && ! empty( $setting['conversion_api_access_token'] ) ) ? $setting['conversion_api_access_token'] : $key;
				}
			}

			return $key;
		}

		/**
		 * @param $key
		 *
		 * @return mixed
		 */
		public function override_conversion_api_test_event_code( $key ) {
			$step_id = WFOCU_Core()->data->get_funnel_id();
			$step_id = apply_filters( 'wfocu_print_tracking_script', $step_id );

			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$setting = WFFN_Common::maybe_override_tracking( $step_id );
				if ( is_array( $setting ) ) {
					$key = ( isset( $setting['conversion_api_test_event_code'] ) && ! empty( $setting['conversion_api_test_event_code'] ) ) ? $setting['conversion_api_test_event_code'] : $key;
				}
			}

			return $key;
		}

		public function maybe_report_checkout( $order ) {
			WFACP_Core()->reporting->updating_reports_from_orders( $order->get_id() );
		}

		public function add_customizer_templates( $templates ) {

			if ( ! class_exists( 'WFOCU_MultiProductCore' ) ) {
				unset( $templates['upsell']['customizer']['mp-list'] );
				unset( $templates['upsell']['customizer']['mp-grid'] );
			}

			return $templates;

		}

		public function get_setup_global_upsells_from_funnel( $upsells ) {

			if ( ! method_exists( 'WFFN_Common', 'get_store_checkout_id' ) ) {
				return $upsells;
			}

			$store_checkout_id = WFFN_Common::get_store_checkout_id();

			if ( ! empty( $store_checkout_id ) ) {
				if ( wffn_string_to_bool( WFFN_Core()->get_dB()->get_meta( $store_checkout_id, 'status' ) ) ) {
					$funnel = new WFFN_Funnel( $store_checkout_id );
					if ( wffn_is_valid_funnel( $funnel ) ) {
						$upsells = $this->maybe_get_upsells( false, $funnel );
					}
				}
			}

			return $upsells;
		}

		public function get_upsell_offers( $wfocu_id ) {
			$offers = array();
			if ( ! empty( $wfocu_id ) && absint( $wfocu_id ) ) {
				$funnel_offers = WFOCU_Core()->funnels->get_funnel_steps( $wfocu_id, false );
				if ( ! empty( $funnel_offers ) ) {
					foreach ( $funnel_offers as $offer ) {
						$offer['state'] = wc_string_to_bool( $offer['state'] );
						$offer['id']    = ( string ) $offer['id'];
						$offer['url']   = ( ! empty( $offer['slug'] ) && ! empty( $offer['url'] ) ) ? str_replace( $offer['slug'] . '/', '', $offer['url'] ) : '';
						$offers[]       = $offer;
					}
				}
			}

			return $offers;
		}

		public function populate_upsell_offer_data_properties( $wfocu_id ) {


			$wc_offer     = $offer_ids = array();
			$wfocu_offers = $this->get_upsell_offers( $wfocu_id );
			if ( ! empty( $wfocu_offers ) && count( $wfocu_offers ) > 0 ) {
				$offer_ids = wp_list_pluck( $wfocu_offers, 'id' );


				foreach ( $offer_ids as $offer_id ) {
					$offer         = [];
					$offer['id']   = $offer_id;
					$offer['tags'] = $this->get_substep_entity_tags( $offer_id );
					$product_count = 0;
					if ( class_exists( 'WFOCU_Core' ) ) {
						$offer_data = WFOCU_Core()->offers->get_offer( $offer_id );
						if ( isset( $offer_data->products ) ) {
							$product_count = count( (array) $offer_data->products );
						}
					}

					$offer['_data']                = new stdClass();
					$offer['_data']->title         = $this->get_entity_title( $offer_id );
					$offer['_data']->edit          = $this->get_entity_edit_link( $wfocu_id );
					$offer['_data']->view          = get_the_permalink( $offer_id );
					$offer['_data']->status        = $this->get_entity_status( $offer_id );
					$offer['_data']->product_count = $product_count;
					$wc_offer[]                    = $offer;
				}
			}

			$offers['offer'] = $wc_offer;

			return $offers;
		}

		public function get_substep_entity_tags( $substep_id ) {

			$product_meta = get_post_meta( $substep_id, '_wfocu_setting', true );
			$flags        = [];

			if ( empty( $product_meta ) || ! isset( $product_meta->products ) || count( (array) $product_meta->products ) < 1 ) {
				$flags['no_product'] = [
					'label'       => __( 'No Products', 'woofunnels-upstroke-one-click-upsell' ),
					'label_class' => 'bwf-st-c-badge-red',
					'edit'        => function_exists( 'wffn_rest_api_helpers' ) ? wffn_rest_api_helpers()->get_entity_url( 'offer', 'product', $substep_id ) : ''
				];
			}


			return $flags;

		}

		/**
		 * @param $funnel_id
		 * @param $step_id
		 * @param $duplicate_id
		 *
		 * @return mixed
		 */
		public function duplicate_substep( $funnel_id, $step_id, $duplicate_id ) {
			$duplicated_substeps = array();
			$duplicate_step_id   = self::make_duplicate_substep( $duplicate_id );
			if ( absint( $duplicate_step_id ) > 0 ) {
				$offer           = WFOCU_Core()->offers->get_offer( $duplicate_id, false );
				$duplicated_data = get_post( $duplicate_step_id );

				if ( isset( $funnel_id ) && ! empty( $funnel_id ) && isset( $duplicated_data->post_title ) && ! empty( $duplicated_data->post_title ) ) {  // Input var okay.

					$funnel_id = absint( $funnel_id );  // Input var okay.

					if ( isset( $duplicated_data->step_type ) && '' !== $duplicated_data->step_type ) {  // Input var okay.
						$offer_type = wc_clean( wp_unslash( $duplicated_data->step_type ) );  // Input var okay.
					} else {
						$offer_type = 'upsell';
					}

					$offer_settings = array(
						'funnel_id' => $funnel_id,
						'type'      => $offer_type,
						'products'  => ! empty( $offer->products ) ? $offer->products : array(),
						'fields'    => ! empty( $offer->fields ) ? $offer->fields : array(),
						'settings'  => ! empty( $offer->settings ) ? $offer->settings : array(),
					);

					update_post_meta( $duplicate_step_id, '_funnel_id', $funnel_id );
					update_post_meta( $duplicate_step_id, '_offer_type', $offer_type );
					update_post_meta( $duplicate_step_id, '_wfocu_setting', $offer_settings );

					WFOCU_Common::update_funnel_time( $funnel_id );

					$duplicated_substeps['id'] = $step_id;
				}

			}

			return $duplicated_substeps;

		}

		public function make_duplicate_substep( $post_id ) {

			if ( $post_id > 0 ) {

				$post = get_post( $post_id );
				if ( ! is_null( $post ) && WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) {

					$args        = [
						'post_title'   => $post->post_title . ' - ' . __( 'Copy', 'woofunnels-aero-checkout' ),
						'post_content' => $post->post_content,
						'post_name'    => sanitize_title( $post->post_title . ' - ' . __( 'Copy', 'woofunnels-aero-checkout' ) ),
						'post_type'    => WFOCU_Common::get_offer_post_type_slug(),
						'post_status'  => 'draft',
					];
					$new_post_id = wp_insert_post( $args );

					if ( ! is_wp_error( $new_post_id ) ) {
						return $new_post_id;
					}
				}
			}

			return null;

		}

		/**
		 * @param $step
		 *
		 * @return array
		 */
		public function get_substep_designs( $term, $funnel_id = 0 ) {
			$get_all_ids = [];
			if ( $funnel_id > 0 ) {
				$this->funnel_id = $funnel_id;
				remove_all_filters( 'wfocu_add_control_meta_query' );
				add_filter( 'wfocu_add_control_meta_query', [ $this, 'search_any_post_status' ], 9 );
				$get_upstroke_posts = WFOCU_Core()->funnels->setup_funnels();
				$get_all_ids        = wp_list_pluck( $get_upstroke_posts, 'id' );
			}
			$args = array(
				'post_type'      => array( WFOCU_Common::get_offer_post_type_slug(), 'cartflows_step', 'page' ),
				'post_status'    => 'any',
				'posts_per_page' => WFOCU_Common::posts_per_page(),
			);

			if ( ! empty( $term ) ) {
				if ( is_numeric( $term ) ) {
					$args['p'] = $term;
				} else {
					$args['s'] = $term;
				}
			}
			if ( ! empty( $get_all_ids ) ) {
				$args['meta_query'] = [
					[
						'key'     => '_funnel_id',
						'value'   => $get_all_ids,
						'compare' => 'in'
					]
				];

			}
			$inside_funnels  = [];
			$outside_funnels = [];

			$q = new WP_Query( $args );
			if ( $q->found_posts > 0 ) {
				foreach ( $q->posts as $active_page ) {
					$upstroke_id   = get_post_meta( $active_page->ID, '_funnel_id', true );
					$bwf_funnel_id = get_post_meta( $upstroke_id, '_bwf_in_funnel', true );
					$data          = array(
						'id'   => $active_page->ID,
						'name' => $active_page->post_title,
					);
					$funnel        = new WFFN_Funnel( $bwf_funnel_id );
					if ( absint( $bwf_funnel_id ) > 0 && ! empty( $funnel->get_title() ) ) {
						if ( ! isset( $inside_funnels[ $bwf_funnel_id ] ) ) {
							$inside_funnels[ $bwf_funnel_id ] = [ 'name' => $funnel->get_title(), 'id' => $bwf_funnel_id, "steps" => [] ];
						}
						$inside_funnels[ $bwf_funnel_id ]['steps'][] = $data;
					} else {
						$outside_funnels[] = $data;
					}

				}
			}

			if ( ! empty( $outside_funnels ) ) {
				$outside_funnels = [ [ 'name' => __( 'Others Pages', 'woofunnels-upstroke-one-click-upsell' ), 'id' => '0', 'steps' => $outside_funnels ] ];
			}

			return array_merge( $inside_funnels, $outside_funnels );
		}

		public function populate_substep_data_properties( $step ) {
			return $this->populate_upsell_offer_data_properties( $step['id'] );
		}

		/**
		 *
		 * Check offer state and update post status for show tag on wffn 3.0
		 *
		 * @param $step_id integer offer ID or Upsell ID
		 *
		 * @return int|string
		 */
		public function get_entity_status( $step_id ) {
			$post_status          = '';
			$post_offer_or_upsell = get_post( $step_id );
			if ( $step_id > 0 && $post_offer_or_upsell instanceof WP_Post ) {
				$post_status = $post_offer_or_upsell->post_status;

				/**
				 * update offer post status base on current offer state for handle 3.0 ui tags
				 * This is a backward compatibility check and here we are making sure it will only run when there is a mismatch
				 */
				if ( WFOCU_Common::get_offer_post_type_slug() === $post_offer_or_upsell->post_type ) {
					$upsell_id = get_post_meta( $step_id, '_funnel_id', true );
					$offers    = get_post_meta( $upsell_id, '_funnel_steps', true );

					if ( is_array( $offers ) && count( $offers ) > 0 ) {
						foreach ( $offers as $offer ) {
							if ( isset( $offer['id'] ) && isset( $offer['state'] ) && $step_id === $offer['id'] ) {

								$post_status_tobe = ( 1 === absint( $offer['state'] ) ) ? 'publish' : 'draft';

								if ( $post_status_tobe !== $post_status ) {
									$post_status = $post_status_tobe;
									wp_update_post( array(
										'ID'          => $offer['id'],
										'post_status' => $post_status
									) );
								}

								break;
							}
						}
					}
				}
			}

			return ( 'publish' === $post_status ) ? 1 : '0';
		}

		/**
		 * This method runs when we need to migrate our downsell property to the settings, since we already have setting to jump offers we do not need downsell property
		 *
		 * @param $steps
		 *
		 * @return mixed
		 */
		public function maybe_migrate_downsells( $steps ) {
			$upsell_offers = [];


			foreach ( $steps as $value ) {

				/**
				 * get upsell step
				 */
				if ( isset( $value['type'] ) && 'wc_upsells' === $value['type'] ) {
					if ( is_array( $value['substeps'] ) && count( $value['substeps'] ) > 0 ) {
						if ( isset( $value['substeps']['offer'] ) ) {
							$get_offers = [];
							foreach ( $value['substeps']['offer'] as $offer ) {
								if ( isset( $offer['id'] ) && absint( $offer['id'] ) > 0 ) {
									$offer_id       = absint( $offer['id'] );
									$offer['_data'] = (array) $offer['_data'];

									/**
									 * divide offers in upsell and downsell based
									 */
									if ( 'downsell' === get_post_meta( $offer_id, '_offer_type', true ) ) {
										$get_offers[] = array(
											'id'    => $offer['id'],
											'type'  => 'downsell',
											'state' => $offer['_data']['status'],
										);
									} else {
										$get_offers[] = array(
											'id'    => $offer['id'],
											'type'  => 'upsell',
											'state' => $offer['_data']['status'],
										);
									}

								}
							}
							$upsell_offers[ $value['id'] ] = $get_offers;
						}
					}

					if ( ! is_array( $upsell_offers ) || 0 === count( $upsell_offers ) ) {
						return $steps;
					}

					foreach ( $upsell_offers as $offers ) {
						foreach ( $offers as $key => $offer ) {

							if ( 'downsell' === $offer['type'] ) {

								$next_offer  = $this->get_offer_in_list( $offers, $key, 'next_offer' ); //0
								$next_upsell = $this->get_offer_in_list( $offers, $key, 'next_upsell' ); //0
								$prev_upsell = $this->get_offer_in_list( $offers, $key, 'prev_upsell' ); //0

								/**
								 * update previous find upsell setting
								 */
								$prev_meta = get_post_meta( $prev_upsell, '_wfocu_setting', true );

								if ( ! empty( $prev_meta ) && is_object( $prev_meta ) ) {
									if ( is_array( $prev_meta->settings ) ) {
										$prev_meta->settings = (object) $prev_meta->settings;
									}

									if ( empty( $prev_meta->settings ) ) {
										$prev_meta->settings = WFOCU_Core()->offers->get_default_offer_setting();
									}

									$prev_meta->settings->jump_on_accepted = true;
									$prev_meta->settings->jump_on_rejected = true;

									if ( empty( $prev_meta->settings->jump_to_offer_on_accepted ) || 'automatic' === $prev_meta->settings->jump_to_offer_on_accepted ) {
										$prev_meta->settings->jump_to_offer_on_accepted = ! empty( $next_upsell ) ? $next_upsell : 'terminate';

									}
									if ( empty( $prev_meta->settings->jump_to_offer_on_rejected ) || 'automatic' === $prev_meta->settings->jump_to_offer_on_rejected ) {
										$prev_meta->settings->jump_to_offer_on_rejected = $offer['id'];

									}


									update_post_meta( $prev_upsell, '_wfocu_setting', $prev_meta );
								}

								/**
								 * update current key upsell setting
								 */
								$current_meta = get_post_meta( $offer['id'], '_wfocu_setting', true );

								if ( ! empty( $current_meta ) && is_object( $current_meta ) ) {
									if ( is_array( $current_meta->settings ) ) {
										$current_meta->settings = (object) $current_meta->settings;
									}

									if ( empty( $current_meta->settings ) ) {
										$current_meta->settings = WFOCU_Core()->offers->get_default_offer_setting();

									}

									$current_meta->settings->jump_on_accepted = true;
									$current_meta->settings->jump_on_rejected = true;
									if ( empty( $current_meta->settings->jump_to_offer_on_accepted ) || 'automatic' === $current_meta->settings->jump_to_offer_on_accepted ) {
										$current_meta->settings->jump_to_offer_on_accepted = $next_upsell;

									}
									if ( empty( $current_meta->settings->jump_to_offer_on_rejected ) || 'automatic' === $current_meta->settings->jump_to_offer_on_rejected ) {
										$current_meta->settings->jump_to_offer_on_rejected = $next_offer;

									}
									update_post_meta( $offer['id'], '_wfocu_setting', $current_meta );
								}
							}

							$get_offer_data = WFOCU_Core()->offers->get_offer( $offer['id'] );

							if ( ! empty( $get_offer_data->settings ) && ! empty( $get_offer_data->settings->terminate_if_accepted ) && true === $get_offer_data->settings->terminate_if_accepted ) {
								$get_offer_data->settings->jump_on_accepted          = true;
								$get_offer_data->settings->jump_to_offer_on_accepted = 'terminate';
								$get_offer_data->settings->terminate_if_accepted     = false;
								update_post_meta( $offer['id'], '_wfocu_setting', $get_offer_data );

							}
							if ( ! empty( $get_offer_data->settings ) && ! empty( $get_offer_data->settings->terminate_if_declined ) && true === $get_offer_data->settings->terminate_if_declined ) {
								$get_offer_data->settings->jump_on_rejected          = true;
								$get_offer_data->settings->jump_to_offer_on_rejected = 'terminate';
								$get_offer_data->settings->terminate_if_declined     = false;
								update_post_meta( $offer['id'], '_wfocu_setting', $get_offer_data );

							}

						}


					}
				}

			}

			return $steps;

		}

		public function get_offer_in_list( $steps, $key, $offer = '' ) {
			$out = 0;

			if ( ! empty( $steps ) && '' !== $key ) {
				foreach ( $steps as $k => $step ) {

					if ( 'next_offer' === $offer && $k > $key ) {
						if ( 1 === $step['state'] ) {
							return $step['id'];
						}
					} elseif ( 'next_upsell' === $offer && $k > $key ) {
						if ( $step['type'] === 'upsell' && 1 === $step['state'] ) {
							return $step['id'];
						}
					} elseif ( 'prev_upsell' === $offer && $k < $key ) {
						if ( $step['type'] === 'upsell' && 1 === $step['state'] ) {
							$out = $step['id'];
						}
					}
				}
			}

			return $out;
		}

		public function sanitize_custom( $data ) {

			return json_decode( $data, true );
		}

		function get_current_app_state() {
			$license_config = WFFN_Core()->admin->get_license_config( true );


			if ( isset( $license_config['ul']['ed'] ) && $license_config['ul']['ed'] ) {
				$ed = $license_config['ul']['ed'];

				if ( strtotime( 'now' ) > strtotime( $ed ) ) {
					if ( strtotime( 'now' ) - strtotime( $ed ) < $license_config['gp'][0] * DAY_IN_SECONDS ) {
						return 'license_expired_on_grace_period';
					}

					return 'license_expired';
				}
			}
			if ( defined( 'WFFN_VERSION' ) && version_compare( WFFN_VERSION, '3.9.1', '>' ) ) {
				$license_config = WFFN_Core()->admin->get_license_config( false, false );

			} else {
				$license_config = WFFN_Core()->admin->get_license_config();
			}

			if ( isset( $license_config['ul']['la'] ) && $license_config['ul']['la'] === true ) {
				return 'pro';
			}
			$license_config = WFFN_Core()->admin->get_license_config();

			if ( isset( $license_config['ul']['ad'] ) && $license_config['ul']['ad'] ) {
				$ad = $license_config['ul']['ad'];

				if ( strtotime( 'now' ) - strtotime( $ad ) < $license_config['gp'][1] * DAY_IN_SECONDS ) {
					return 'pro_without_license_on_grace_period';
				}
			}

			return 'pro_without_license';
		}


	}

	WFFN_Core()->steps->register( WFFN_Step_WC_Upsells::get_instance() );
}

