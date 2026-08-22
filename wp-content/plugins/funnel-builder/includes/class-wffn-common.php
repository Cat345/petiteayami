<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Class WFFN_Common
 * Handles Common Functions For Admin as well as front end interface
 */
if ( ! class_exists( 'WFFN_Common' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Common {

		public static $start_time           = 0;
		public static $recurring_actions_db = array();

		public static function init() {
			/**
			 * schedule setup to remove expired transients
			 */
			add_action( 'fk_fb_every_day', array( __CLASS__, 'remove_wffn_logs_and_transients' ), 999999 );
			add_filter( 'bwf_fb_templates', array( __CLASS__, 'update_template_list' ), 10, 1 );
		}

		/**
		 * @param $arr
		 */
		public static function pr( $arr ) {
			echo '<pre>';
			print_r( $arr );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			echo '</pre>';
		}

		/**
		 * @return array
		 */
		public static function get_steps_data() {
			$steps        = WFFN_Core()->steps->get_supported_steps();
			$sorted_steps = self::sort_steps( $steps );
			$steps_data   = array();
			foreach ( $sorted_steps as $step ) {
				$steps_data[ $step->slug ] = $step->get_step_data();
			}

			return $steps_data;
		}

		/**
		 * @param $steps
		 *
		 * @return mixed
		 */
		public static function sort_steps( $steps ) {
			usort(
				$steps,
				function ( $a, $b ) {
					if ( $a->list_priority === $b->list_priority ) {
						return 0;
					}

					return ( $a->list_priority < $b->list_priority ) ? - 1 : 1;
				}
			);

			return $steps;
		}

		/**
		 * @return array
		 */
		public static function get_substeps_data() {
			$substeps      = WFFN_Core()->substeps->get_supported_substeps();
			$substeps_data = array();
			foreach ( $substeps as $substep ) {
				$substeps_data[ $substep->slug ] = $substep->get_substep_data();
			}

			return $substeps_data;
		}


		/**
		 * @return array
		 */
		public static function get_funnel_delete_data() {
			$funnel = new WFFN_Funnel( '0' );

			return $funnel->get_delete_data();
		}

		/**
		 * @return array
		 */
		public static function get_funnel_duplicate_data() {
			$funnel = WFFN_Core()->admin->get_funnel();

			return $funnel->get_duplicate_data();
		}


		public static function get_funnel_slug() {
			return 'funnel';
		}


		public static function is_admin_action() {
			if ( is_admin() && ( 'bwf_funnels' === filter_input( INPUT_GET, 'page' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX && 1 === filter_input( INPUT_POST, 'is_funnel_action' ) ) ) ) {
				return true;
			}

			return false;
		}


		public static function search_page( $term, $post_types_override = array() ) {
			global $wpdb;
			$like_term     = '%' . $wpdb->esc_like( $term ) . '%';
			$post_statuses = array( 'publish' );

			if ( ! empty( $post_types_override ) ) {
				$post_types = $post_types_override;
			} else {
				/**
				 * get all public post type in search
				 */
				$args          = array(
					'public' => true,
				);
				$get_all_types = get_post_types( $args, 'objects' );
				$post_types    = array( 'page' );

				if ( is_array( $get_all_types ) && count( $get_all_types ) > 0 ) {
					$post_types = array_keys( $get_all_types );
				}

				$excludes = apply_filters(
					'wffn_exclude_post_types_from_search',
					array(
						'attachment',
					),
					$post_types
				);

				$post_types = array_diff( $post_types, $excludes );

			}

			$query = $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} posts WHERE ( posts.post_title LIKE %s or posts.ID = %s ) AND posts.post_type IN ('" . implode( "','", $post_types ) . "')  AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ORDER BY posts.post_parent ASC, posts.post_title ASC", $like_term, $like_term ); //phpcs:ignore

			$post_ids = $wpdb->get_col( $query ); //phpcs:ignore

			if ( is_numeric( $term ) ) {
				$post_id    = absint( $term );
				$post_ids[] = $post_id;
			}

			return wp_parse_id_list( $post_ids );
		}

		public static function admin_user() {
			$user = array(
				'admin_email' => get_option( 'admin_email' ),
				'name'        => get_bloginfo( 'name', 'display' ),
			);

			return $user;
		}

		public static function maybe_elementor_template( $page_id, $new_page_id ) {

			if ( ! class_exists( 'WFFN_Elementor_Importer' ) ) {
				return;
			}

			$contents = get_post_meta( $page_id, '_elementor_data', true );
			$data     = array(
				'_elementor_version'       => get_post_meta( $page_id, '_elementor_version', true ),
				'_elementor_template_type' => get_post_meta( $page_id, '_elementor_template_type', true ),
				'_elementor_edit_mode'     => get_post_meta( $page_id, '_elementor_edit_mode', true ),

			);
			foreach ( $data as $meta_key => $meta_value ) {
				update_post_meta( $new_page_id, $meta_key, $meta_value );
			}

			if ( ! function_exists( 'wp_read_video_metadata' ) ) {
				require_once ABSPATH . '/wp-admin/includes/media.php';
			}
			$instance = new WFFN_Elementor_Importer();
			if ( ! is_null( $instance ) ) {
				if ( is_array( $contents ) ) {
					$contents = wp_json_encode( $contents );

				}
				$instance->import( $new_page_id, $contents );
			}
		}

		/**
		 * Remove action for without instance method  class found and return object of class
		 *
		 * @param $hook
		 * @param $cls string
		 * @param string $function
		 *
		 * @return |null
		 */
		public static function remove_actions( $hook, $cls, $function = '' ) {

			global $wp_filter;
			$object = null;
			if ( class_exists( $cls ) && isset( $wp_filter[ $hook ] ) && ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
				$hooks = $wp_filter[ $hook ]->callbacks;
				foreach ( $hooks as $priority => $reference ) {
					if ( is_array( $reference ) && count( $reference ) > 0 ) {
						foreach ( $reference as $index => $calls ) {
							if ( isset( $calls['function'] ) && is_array( $calls['function'] ) && count( $calls['function'] ) > 0 ) {
								if ( is_object( $calls['function'][0] ) ) {
									$cls_name = get_class( $calls['function'][0] );
									if ( $cls_name === $cls && $calls['function'][1] === $function ) {
										$object = $calls['function'][0];
										unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $index ] );
									}
								} elseif ( $index === $cls . '::' . $function ) {
									$object = $cls;
									unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $cls . '::' . $function ] );
								}
							}
						}
					}
				}
			} elseif ( function_exists( $cls ) && isset( $wp_filter[ $hook ] ) && ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {

				$hooks = $wp_filter[ $hook ]->callbacks;
				foreach ( $hooks as $priority => $reference ) {
					if ( is_array( $reference ) && count( $reference ) > 0 ) {
						foreach ( $reference as $index => $calls ) {
							$remove = false;
							if ( $index === $cls ) {
								$remove = true;
							} elseif ( isset( $calls['function'] ) && $cls === $calls['function'] ) {
								$remove = true;
							}
							if ( true === $remove ) {
								unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $cls ] );
							}
						}
					}
				}
			}

			return $object;
		}

		public static function get_discount_type_keys() {

			$discounted = array(
				'fixed_on_reg'          => sprintf( __( '%s Fixed Amount on Regular Price', 'funnel-builder' ), get_woocommerce_currency_symbol() ),
				'fixed_on_sale'         => sprintf( __( '%s Fixed Amount on Sale Price', 'funnel-builder' ), get_woocommerce_currency_symbol() ),
				'percentage_on_reg'     => __( '% on Regular Price', 'funnel-builder' ),
				'percentage_on_sale'    => __( '% on Sale Price', 'funnel-builder' ),
				'fixed_discount_reg'    => sprintf( __( '%s Fixed Amount on Regular Price', 'funnel-builder' ), get_woocommerce_currency_symbol() ),
				'fixed_discount_sale'   => sprintf( __( '%s Fixed Amount on Sale Price', 'funnel-builder' ), get_woocommerce_currency_symbol() ),
				'percent_discount_reg'  => __( '% on Regular Price', 'funnel-builder' ),
				'percent_discount_sale' => __( '% on Sale Price', 'funnel-builder' ),
			);

			return $discounted;
		}

		public static function get_funnel_edit_link( $funnel_id, $path = '/steps' ) {
			if ( empty( $funnel_id ) ) {
				return '#';
			}

			return add_query_arg(
				array(
					'page' => 'bwf',
					'path' => "/funnels/$funnel_id$path",
				),
				admin_url( 'admin.php' )
			);
		}

		public static function get_store_checkout_edit_link( $path = '' ) {
			return add_query_arg(
				array(
					'page' => 'bwf',
					'path' => '/store-checkout' . $path,
				),
				admin_url( 'admin.php' )
			);
		}

		public static function get_step_edit_link( $step_id, $type, $funnel_id = '', $is_timeline = false ) {

			if ( empty( $step_id ) || empty( $type ) ) {
				return '#';
			}

			if ( intval( $funnel_id ) > 0 ) {
				switch ( $type ) {
					case 'landing':
						$slug = '/funnel-optin/' . $step_id . '/design';
						break;
					case 'thankyou':
						$slug = '/funnel-thankyou/' . $step_id . '/design';
						break;
					case 'aero':
						$slug = '/funnel-checkout/' . $step_id . '/design';
						break;
					case 'upsell':
						$slug = '/funnel-offer/' . $step_id . '/design';
						break;
					case 'bump':
						$slug = '/funnel-bump/' . $step_id . '/product';
						break;
					case 'optin':
						$slug = ( $is_timeline ) ? '/funnels/' . $funnel_id . '/orders/optins' : '/funnel-optin/' . $step_id . '/design';
						break;
					case 'optin_ty':
						$slug = '/funnel-optin-confirmation/' . $step_id . '/design';
						break;
					default:
						$slug = '';
						break;
				}

				if ( empty( $slug ) ) {
					return '#';
				}

				return add_query_arg(
					array(
						'page'      => 'bwf',
						'path'      => $slug,
						'funnel_id' => $funnel_id,
					),
					admin_url( 'admin.php' )
				);

			} else {
				switch ( $type ) {
					case 'aero':
						// Standalone (non-funnel) checkout: page=wfacp removed; fall back to the WP post edit screen (post type is show_ui=true). (#9188)
						return add_query_arg(
							array(
								'post'   => $step_id,
								'action' => 'edit',
							),
							admin_url( 'post.php' )
						);
					case 'upsell':
						$step_args = array(
							'page'    => 'upstroke',
							'section' => 'offers',
							'edit'    => $step_id,
						);
						break;
					case 'bump':
						$step_args = array(
							'page'    => 'wfob',
							'section' => 'products',
							'wfob_id' => $step_id,
						);
						break;
					default:
						$step_args = '';
						break;
				}
				if ( empty( $step_args ) ) {
					return '#';
				}

				return add_query_arg( $step_args, admin_url( 'admin.php' ) );
			}
		}

		public static function get_experiment_edit_link( $funnel_id, $step_id, $path = '/experiments' ) {
			if ( empty( $step_id ) ) {
				return '#';
			}

			return add_query_arg(
				array(
					'page' => 'bwf',
					'path' => "/funnels/$funnel_id$path/$step_id",
				),
				admin_url( 'admin.php' )
			);
		}

		public static function modify_content_emogrifier( $content ) {
			if ( empty( $content ) ) {
				return $content;
			}

			$content = self::prepare_email_content( $content );

			if ( false === self::supports_emogrifier() ) {
				return $content;
			}

			ob_start();
			include WFFN_PLUGIN_DIR . '/includes/libraries/email-styles.php'; //phpcs:ignore
			$css = ob_get_clean();

			$emogrifier_class = '\\Pelago\\Emogrifier';
			if ( ! class_exists( $emogrifier_class ) ) {
				include_once WFFN_PLUGIN_DIR . '/includes/libraries/class-emogrifier.php'; //phpcs:ignore
			}
			try {
				/** @var \Pelago\Emogrifier $emogrifier */
				$emogrifier = new $emogrifier_class( $content, $css );
				$content    = $emogrifier->emogrify();

				return $content;
			} catch ( Exception $e ) {
				BWF_Logger::get_instance()->log( 'Optin test email failure. Message: ' . $e->getMessage(), 'send_email_emogrifier' );
			}

			return $content;
		}

		/**
		 * Return if emogrifier library is supported.
		 *
		 * @return bool
		 * @since 3.5.0
		 */
		public static function supports_emogrifier() {
			return class_exists( 'DOMDocument' ) && version_compare( PHP_VERSION, '5.5', '>=' );
		}

		public static function prepare_email_content( $content ) {
			$has_body = stripos( $content, '<body' ) !== false;

			/** Check if body tag exists */
			if ( ! $has_body ) {
				return '<html><head></head><body><div id="body_content">' . $content . '</div></body></html>';
			}

			$pattern     = '/<body(.*?)>(.*?)<\/body>/is';
			$replacement = '<body$1><div id="body_content">$2</div></body>';

			return preg_replace( $pattern, $replacement, $content );
		}

		/**
		 * Check if funnel builder PRO version is active and license is active
		 *
		 * @return mixed|void
		 */
		public static function wffn_is_funnel_pro_active() {
			return defined( 'WFFN_PRO_FILE' ) && WFFN_Core()->admin->get_license_status();
		}


		/**
		 * @return string
		 */
		public static function get_wffn_container_attrs() {

			$attributes   = apply_filters( 'wffn_container_attrs', array() );
			$attrs_string = '';

			foreach ( $attributes as $key => $value ) {

				if ( ! $value ) {
					continue;
				}

				if ( true === $value ) {
					$attrs_string .= esc_html( $key ) . ' ';
				} else {
					$attrs_string .= sprintf( '%s=%s ', esc_html( $key ), esc_attr( $value ) );
				}
			}

			return $attrs_string;
		}

		/**
		 * @param $step_id
		 *
		 * @return false|mixed
		 */
		public static function maybe_override_tracking( $step_id ) {
			$funnel = WFFN_Core()->data->get_session_funnel();

			if ( WFFN_Core()->data->has_valid_session() && $step_id > 0 && wffn_is_valid_funnel( $funnel ) ) {
				$steps = $funnel->get_steps();

				$search     = array_search( intval( $step_id ), array_map( 'intval', wp_list_pluck( $steps, 'id' ) ), true );
				$is_variant = get_post_meta( $step_id, '_bwf_ab_variation_of', true );

				if ( false !== $search || $is_variant > 0 ) {
					$settings = WFFN_Core()->get_dB()->get_meta( $funnel->get_id(), '_settings' );
					if ( is_array( $settings ) && count( $settings ) > 0 ) {
						return ( isset( $settings['override_tracking_ids'] ) && $settings['override_tracking_ids'] === true ) ? $settings : false;
					}
				}
			}

			return false;
		}

		public static function maybe_wpdb_error( $wpdb ) {
			$status = array(
				'db_error' => false,
			);

			if ( ! empty( $wpdb->last_error ) ) {
				$status = array(
					'db_error'  => true,
					'msg'       => $wpdb->last_error,
					'query'     => $wpdb->last_query,
					'backtrace' => wp_debug_backtrace_summary(), //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
				);

				WFFN_Core()->logger->log( "Get wpdb last error for query : " . print_r( $status, true ), 'woofunnel-failed-actions', true ); // phpcs:ignore
			}

			return $status;
		}

		/**
		 *  Check if page builder preview mode is showing up.
		 */
		public static function is_page_builder_preview() {

			if ( self::is_elementor_preview_mode() || self::is_divi_builder_preview() || self::is_oxy_builder_preview() || apply_filters( 'wffn_maybe_preview_mode', false ) ) {
				return true;
			}

			return false;
		}

		/**
		 *  Check if page builder preview mode is showing up.
		 */
		public static function is_page_builder_editor() {
			$elementor = self::check_builder_status( 'elementor' );
			if ( true === $elementor['found'] && class_exists( '\Elementor\Plugin' ) && ! is_null( \Elementor\Plugin::instance()->editor ) && \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
				return true;
			}

			$divi = self::check_builder_status( 'divi' );
			if ( true === $divi['found'] && isset( $_REQUEST['et_load_builder_modules'] ) && '1' === $_REQUEST['et_load_builder_modules'] ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended , FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				return true;
			}

			$oxy = self::check_builder_status( 'oxy' );
			if ( true === $oxy['found'] && isset( $_REQUEST['action'] ) && false !== strpos( $_REQUEST['action'], 'oxy_render' ) ) {//phpcs:ignore
				return true;
			}

			return false;
		}

		/**
		 *  Check if elementor preview mode is showing.
		 */
		public static function is_elementor_preview_mode() {
			if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview instanceof \Elementor\Preview && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				return true;
			}

			return false;
		}


		/**
		 *  Check if divi builder page is showing.
		 */
		public static function is_divi_builder_preview() {

			if ( '1' === filter_input( INPUT_GET, 'et_fb' ) ) {
				return true;
			}

			return false;
		}

		/**
		 *  Check if oxygen builder page is showing.
		 */
		public static function is_oxy_builder_preview() {

			if ( '1' === filter_input( INPUT_GET, 'ct_builder' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Determine whether the current request is an Oxygen editor context.
		 *
		 * Presence-based so it catches BOTH FunnelKit editor-entry URLs:
		 * `ct_builder=1` (edit-template button) and `ct_builder=true`
		 * (admin-bar link). A value compare (`'1' ===` / `=== 'true'`) would
		 * match only one and wrongly clear Oxygen's own unsaved-changes
		 * warning in the other editor session. Also covers editor AJAX
		 * (save / render-inner / oxy_render*) and any admin/ajax request so a
		 * frontend-only suppression can never leak into the real editor.
		 *
		 * @since 3.15.1
		 *
		 * @return bool True in any Oxygen editor / admin / editor-AJAX context.
		 */
		public static function is_oxy_editor_context() {
			if ( is_admin() || wp_doing_ajax() ) {
				return true;
			}

			// Presence check — catches both `ct_builder=1` and `ct_builder=true`.
			if ( null !== filter_input( INPUT_GET, 'ct_builder' ) ) {
				return true;
			}

			// Oxygen is an optional dependency — guard the core call.
			if ( class_exists( 'WFFN_OXYGEN' ) && WFFN_OXYGEN::is_template_editor() ) {
				return true;
			}

			return self::is_page_builder_editor();
		}

		/**
		 * Replace the duplicate http from a string.
		 *
		 * @param $string
		 *
		 * @return mixed
		 */
		public static function wffn_correct_protocol_url( $string ) {
			$string = str_replace( 'http://https://', 'https://', $string );
			$string = str_replace( 'https://http://', 'http://', $string );
			$string = str_replace( 'https://https://', 'https://', $string );
			$string = str_replace( 'http://http://', 'http://', $string );

			return $string;
		}



		/**
		 * Check if Divi 5 Visual Builder is active.
		 *
		 * @return bool
		 */
		public static function is_divi5_active() {
			if ( did_action( 'after_setup_theme' ) ) {
				return function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled();
			}
			return false;
		}

		/**
		 * Get the effective Divi builder key for template lookups.
		 *
		 * - Divi 5 enabled → 'divi5'
		 * - Divi installed, D5 not enabled → 'divi'
		 * - Divi not installed → 'divi5' (future-facing default)
		 *
		 * @return string 'divi' or 'divi5'
		 */
		public static function get_effective_divi_builder_key() {
			if ( self::is_divi5_active() ) {
				return 'divi5';
			}
			$status = self::check_builder_status( 'divi' );
			if ( ! empty( $status['found'] ) ) {
				return 'divi';
			}

			return 'divi5';
		}

		/**
		 * Translate a builder key to its remote server equivalent.
		 *
		 * The remote template server does not know about 'divi5'. This method
		 * maps 'divi5' → 'divi' so all remote API calls use the correct key.
		 *
		 * Use this single method everywhere a builder key is sent to the
		 * remote server (template fetch, builder version, file paths, etc.).
		 *
		 * @param string $builder The builder key (e.g. 'divi5', 'elementor').
		 *
		 * @return string The remote-safe builder key.
		 */
		public static function get_remote_builder_key( $builder ) {
			if ( 'divi5' === $builder ) {
				return 'divi';
			}

			return $builder;
		}

		public static function check_builder_status( $builder = '' ) {
			// divi5 uses the same builder dependency as divi
			if ( 'divi5' === $builder ) {
				return self::check_builder_status( 'divi' );
			}

			// Divi Builder Plugin Exists
			$response = array(
				'found'          => false,
				'error'          => '',
				'is_old_version' => 'no',
				'version'        => '',
			);
			if ( empty( $builder ) ) {
				$response['error'] = __( 'No Builder Specified', 'funnel-builder' );
			} elseif ( 'oxy' === $builder ) {
				$supported_version   = '3.7';
				$oxy_exist           = false;
				$oxy_builder_version = '1.0';
				if ( class_exists( 'CT_Component' ) ) {
					$oxy_exist = true;
					if ( defined( 'CT_VERSION' ) ) {
						$oxy_builder_version = CT_VERSION;
					}
				}

				if ( true === $oxy_exist ) {
					$response['found'] = true;
					if ( ! version_compare( $oxy_builder_version, $supported_version, '>=' ) ) {
						$response['is_old_version'] = 'yes';
						$response['version']        = $oxy_builder_version;
						$response['error']          = sprintf( __( 'Site has an older version of Oxygen Classic Builder. Templates are supported for v%s or greater.<br /> Please update.', 'funnel-builder' ), $supported_version );
					}
				}
			} elseif ( 'divi' === $builder ) {
				$supported_version    = '4.1';
				$divi_exist           = false;
				$divi_builder_version = 0;
				// Detect Divi Builder Plugin is Active
				if ( class_exists( 'ET_Builder_Plugin' ) ) {
					$divi_exist = true;

					if ( defined( 'ET_BUILDER_PLUGIN_VERSION' ) ) {
						$divi_builder_version = ET_BUILDER_PLUGIN_VERSION;
					}
				} elseif ( function_exists( 'et_setup_theme' ) ) { // Detect Theme Active
					$divi_exist = true;
					$theme      = wp_get_theme();
					if ( $theme instanceof WP_Theme ) {
						$parent = $theme->parent();
						if ( $parent instanceof WP_Theme ) {
							$divi_builder_version = $parent->get( 'Version' );
						} else {
							$divi_builder_version = $theme->get( 'Version' );
						}
					}
				}
				// available in Both Theme & Plugin
				if ( 0 === $divi_builder_version && defined( 'ET_BUILDER_PRODUCT_VERSION' ) ) {
					$divi_builder_version = ET_BUILDER_PRODUCT_VERSION;
				}

				// ET_Builder_Plugin
				if ( true === $divi_exist && class_exists( 'ET_Core_Portability' ) ) {
					$response['found']   = true;
					$response['version'] = $divi_builder_version;
					if ( ! version_compare( $divi_builder_version, $supported_version, '>=' ) ) {
						$response['is_old_version'] = 'yes';
						$response['error']          = sprintf( __( 'Site has an older version of Divi Builder. Templates are supported for v%s or greater.<br /> Please update.', 'funnel-builder' ), $supported_version );
					}
				}
			} elseif ( 'elementor' ) {
				if ( defined( 'ELEMENTOR_VERSION' ) ) {
					$response['found']   = true;
					$response['version'] = ELEMENTOR_VERSION;
				}
			}

			return $response;
		}


		/**
		 * Remove logs files and orphaned transients
		 *
		 * @return void
		 */
		public static function remove_wffn_logs_and_transients() {

			try {
				if ( ! class_exists( 'WooFunnels_File_Api' ) ) {
					return;
				}
				clearstatcache();
				$file_api            = new WooFunnels_File_Api( 'funnel-builder-logs' );// Define directories to process
				$log_directories     = array(
					$file_api->woofunnels_core_dir . '/funnel-builder-logs',
				);
				$transient_directory = $file_api->woofunnels_core_dir . '/wffn-transient';
				$upload              = wp_upload_dir();
				$folder_path         = $upload['basedir'] . '/woofunnels';// Delete old woofunnels folder
				if ( is_dir( $folder_path ) ) {
					$file_api->delete_folder( $folder_path, true );
				}
				$log_cutoff_time       = strtotime( '-4 days' );
				$transient_cutoff_time = strtotime( '-2 hours' );
				self::$start_time      = time();// Remove old log files
				foreach ( $log_directories as $log_dir ) {
					if ( is_dir( $log_dir ) ) {
						$dir = @opendir( $log_dir . '/' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden

						if ( empty( $dir ) ) {
							continue;
						}

						while ( false !== ( $file = @readdir( $dir ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition,Generic.PHP.NoSilencedErrors.Forbidden, WordPress.CodeAnalysis.AssignmentInCondition

							if ( $file === '.' || $file === '..' ) {
								continue;
							}

							$file_path = $log_dir . '/' . $file;
							if ( @filemtime( $file_path ) <= $log_cutoff_time ) { // phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden
								$file_api->delete( $file_path );
							}

							if ( true === self::time_exceeded() || true === self::memory_exceeded() ) {
								break;
							}
						}
						closedir( $dir );
					}
				}// Remove orphaned transients
				if ( is_dir( $transient_directory ) ) {
					$dir = @opendir( $transient_directory . '/' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden

					if ( ! empty( $dir ) ) {
						while ( false !== ( $file = @readdir( $dir ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition,Generic.PHP.NoSilencedErrors.Forbidden, WordPress.CodeAnalysis.AssignmentInCondition

							if ( $file === '.' || $file === '..' ) {
								continue;
							}

							$file_path = $transient_directory . '/' . $file;
							if ( @filemtime( $file_path ) <= $transient_cutoff_time ) { // phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden
								$file_api->delete( $file_path );
							}

							if ( true === self::time_exceeded() || true === self::memory_exceeded() ) {
								break;
							}
						}
						closedir( $dir );
					}
				}
				if ( class_exists( 'WFOCU_Common' ) ) {
					WFOCU_Common::remove_orphaned_transients();
				}
			} catch ( Exception | Error $e ) {

			}
		}


		public static function time_exceeded() {
			$finish = self::$start_time + 20; // 20 seconds
			$return = false;

			if ( time() >= $finish ) {
				$return = true;
			}

			return $return;
		}

		public static function memory_exceeded() {
			$memory_limit = self::get_memory_limit() * 0.9; // 90% of max memory

			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			return $return;
		}

		public static function get_memory_limit() {
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				// Sensible default.
				$memory_limit = '128M';
			}

			if ( ! $memory_limit || - 1 === $memory_limit || '-1' === $memory_limit ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32G';
			}

			return self::convert_hr_to_bytes( $memory_limit ) * 1024 * 1024;
		}

		/**
		 * Converts a shorthand byte value to an integer byte value.
		 *
		 * Wrapper for wp_convert_hr_to_bytes(), moved to load.php in WordPress 4.6 from media.php
		 *
		 * @link https://secure.php.net/manual/en/function.ini-get.php
		 * @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
		 *
		 * @param string $value A (PHP ini) byte value, either shorthand or ordinary.
		 *
		 * @return int An integer byte value.
		 */
		public static function convert_hr_to_bytes( $value ) {
			if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
				return wp_convert_hr_to_bytes( $value );
			}

			$value = strtolower( trim( $value ) );
			$bytes = (int) $value;

			if ( false !== strpos( $value, 'g' ) ) {
				$bytes *= GB_IN_BYTES;
			} elseif ( false !== strpos( $value, 'm' ) ) {
				$bytes *= MB_IN_BYTES;
			} elseif ( false !== strpos( $value, 'k' ) ) {
				$bytes *= KB_IN_BYTES;
			}

			// Deal with large (float) values which run into the maximum integer size.
			return min( $bytes, PHP_INT_MAX );
		}

		public static function generate_hash_key() {

			return bin2hex( bwf_generate_random_bytes( 4 ) );
		}

		/**
		 * Get Format for Success Response
		 *
		 * @param $result_array
		 * @param string $message
		 * @param int    $response_code
		 *
		 * @return array
		 */
		public static function format_success_response( $result_array, $message = '', $response_code = 200 ) {
			return array(
				'code'    => $response_code,
				'message' => $message,
				'result'  => $result_array,
			);
		}

		/**
		 * @param $templates
		 * update template list data to be compatible with old pro version
		 *
		 * @return array|mixed
		 */
		public static function update_template_list( $templates ) {
			if ( empty( $templates ) || ( defined( 'WFFN_PRO_VERSION' ) && version_compare( WFFN_PRO_VERSION, '2.4.0', '>=' ) ) ) {
				return $templates;
			}
			if ( isset( $templates['divi']['divi_funnel_1']['import_button_text'] ) ) {
				$templates = WooFunnels_Dashboard::get_all_templates( true );
				$templates = isset( $templates['funnel'] ) ? $templates['funnel'] : array();
			}
			if ( isset( $templates['wc_checkout'] ) ) {
				foreach ( $templates['wc_checkout'] as &$checkout_data ) {
					if ( is_array( $checkout_data ) ) {
						foreach ( $checkout_data as &$ch_val ) {
							if ( isset( $ch_val['build_from_scratch'] ) ) {
								$ch_val['build_from_scratch'] = ( true === $ch_val['build_from_scratch'] ) ? 'yes' : 'no';
							}
							$ch_val['import'] = 'yes';
						}
					}
				}
			}
			if ( isset( $templates['upsell'] ) ) {
				foreach ( $templates['upsell'] as $k => &$upsell_data ) {
					if ( 'customizer' !== $k && is_array( $upsell_data ) ) {
						foreach ( $upsell_data as &$up_val ) {
							if ( ! isset( $up_val['build_from_scratch'] ) ) {
								$up_val['preview_url']    = 'test_preview';
								$up_val['import_allowed'] = true;
							}
						}
					}
				}
			}

			return $templates;
		}

		/**
		 * update store checkout meta and option key
		 *
		 * @param $funnel_id
		 * @param $status
		 *
		 * @return void
		 */
		public static function update_store_checkout_meta( $funnel_id, $status = 0 ) {
			update_option( '_bwf_global_funnel', $funnel_id, true );
			WFFN_Core()->get_dB()->update_meta( $funnel_id, '_is_global', 'yes' );
			WFFN_Core()->get_dB()->update_meta( $funnel_id, 'status', $status );
		}

		/**
		 *  override checkout id in aero global setting
		 *
		 * @param $funnel_id
		 *
		 * @return true|void
		 */
		public static function override_store_checkout_option( $funnel_id ) {
			$funnel = new WFFN_Funnel( $funnel_id );
			if ( ! $funnel instanceof WFFN_Funnel ) {
				return;
			}
			if ( absint( $funnel->get_id() ) !== self::get_store_checkout_id() ) {
				return;
			}
			if ( is_array( $funnel->get_steps() ) && count( $funnel->get_steps() ) > 0 ) {
				$steps = $funnel->get_steps();
				foreach ( $steps as &$step ) {
					if ( isset( $step['type'] ) && 'wc_checkout' === $step['type'] ) {
						/** restore global substeps */
						$bumps = self::get_store_checkout_global_substeps( $funnel_id );
						if ( is_array( $bumps ) && count( $bumps ) > 0 ) {
							$step['substeps'] = $bumps;
							WFFN_Core()->get_dB()->update_meta( $funnel_id, '_is_global_substeps', array() );
						}
					}
				}
				$funnel->set_steps( $steps );
				$funnel->save();

				return true;
			}
		}

		/**
		 * get store checkout substeps
		 *
		 * @param $store_checkout_id
		 *
		 * @return array
		 */
		public static function get_store_checkout_global_substeps( $store_checkout_id ) {
			$bumps = WFFN_Core()->get_dB()->get_meta( $store_checkout_id, '_is_global_substeps' );

			return is_array( $bumps ) ? $bumps : array();
		}

		/**
		 * Save order bump in funnel meta after delete checkout step
		 *
		 * @param $store_checkout_id
		 * @param $bumps
		 *
		 * @return void
		 */
		public static function update_substeps_store_checkout_meta( $store_checkout_id, $bumps ) {
			WFFN_Core()->get_dB()->update_meta( $store_checkout_id, '_is_global_substeps', $bumps );
		}

		/**
		 * Get store checkout funnel id by option value
		 *
		 * @return int
		 */
		public static function get_store_checkout_id() {
			$global_funnel_id = get_option( '_bwf_global_funnel', false );
			if ( absint( $global_funnel_id ) > 0 ) {
				return absint( $global_funnel_id );
			}

			return 0;
		}

		/**
		 * Get Store checkout funnel native checkout step slug
		 *
		 * @return string
		 */
		public static function store_native_checkout_slug() {
			return 'wc_native';
		}

		/**
		 * Join a string with a natural language conjunction at the end.
		 */
		public static function natural_language_join( array $list, $conjunction = 'and' ) {
			$last = array_pop( $list );
			if ( $list ) {
				return implode( ', ', $list ) . ' ' . $conjunction . ' ' . $last;
			}

			return $last;
		}

		/**
		 * Create facebook advanced matching data
		 *
		 * @return mixed|null
		 */
		public static function pixel_advanced_matching_data( $fetch_contact = false ) {
			$args = array();

			if ( ! class_exists( 'BWF_Admin_General_Settings' ) ) {
				return $args;
			}

			$advanced_tracking = BWF_Admin_General_Settings::get_instance()->get_option( 'is_fb_advanced_event' );

			if ( ! is_array( $advanced_tracking ) || count( $advanced_tracking ) === 0 || 'yes' !== $advanced_tracking[0] ) {
				return $args;
			}

			$params = self::advanced_matching_data( $fetch_contact );

			if ( ! is_array( $params ) || 0 === count( $params ) ) {
				return $args;
			}

			foreach ( $params as $key => &$value ) {
				if ( ! empty( $value ) ) {
					$params[ $key ] = self::sanitize_advanced_matching_param( $value, $key );
				}
			}

			return $params;
		}

		/**
		 * Normalize email for TikTok Pixel only
		 * Requirements: trimmed, lowercase, free of placeholder values
		 *
		 * @param string $email Email address to normalize
		 * @return string|false Normalized email or false if invalid/placeholder
		 */
		public static function normalize_tiktok_email( $email ) {
			if ( empty( $email ) || ! is_string( $email ) ) {
				return false;
			}

			$email = strtolower( trim( $email ) );

			// Check placeholders and validate
			$placeholders = array( 'null', 'undefined', 'redacted', 'mail@example.com', 'example@example.com', 'test@test.com', 'user@example.com' );
			if ( in_array( $email, $placeholders, true ) || ! is_email( $email ) ) {
				return false;
			}

			return $email;
		}

		/**
		 * Normalize phone number for TikTok Pixel only
		 * Requirements: E.164 format (e.g., "+12133734253"), free of placeholder values
		 *
		 * @param string $phone Phone number to normalize
		 * @param string $country_code Optional country code
		 * @return string|false Normalized phone in E.164 format or false if invalid/placeholder
		 */
		public static function normalize_tiktok_phone( $phone, $country_code = '' ) {
			if ( empty( $phone ) || ! is_string( $phone ) ) {
				return false;
			}

			$phone       = trim( $phone );
			$phone_clean = preg_replace( '/[^0-9+]/', '', $phone );

			// Check placeholders
			$placeholders = array( 'null', 'undefined', '000-000-0000', '123-456-7890', '0000000000', '1234567890', '999-999-9999', '111-111-1111', '000000000', '123456789' );
			if ( in_array( $phone, $placeholders, true ) || in_array( $phone_clean, $placeholders, true ) ) {
				return false;
			}

			$phone = $phone_clean;
			if ( 0 !== strpos( $phone, '+' ) ) {
				$phone = ltrim( $phone, '0' );
				if ( empty( $country_code ) && class_exists( 'WooCommerce' ) ) {
					$country_code = WC()->countries->get_base_country();
				}
				if ( class_exists( 'BWFAN_Phone_Numbers' ) ) {
					$phone = BWFAN_Phone_Numbers::add_country_code( $phone, $country_code );
				} elseif ( strlen( $phone ) >= 10 && strlen( $phone ) <= 15 ) {
					$phone = '+' . $phone;
				} else {
					return false;
				}
			}

			// Validate E.164: + followed by 1-15 digits
			return preg_match( '/^\+[1-9]\d{1,14}$/', $phone ) ? $phone : false;
		}

		/**
		 * Create tiktok advanced matching data
		 *
		 * @return mixed|null
		 */
		public static function tiktok_advanced_matching_data() {
			$args = array();

			$params = self::advanced_matching_data();

			if ( ! is_array( $params ) || 0 === count( $params ) ) {
				return $args;
			}

			// Normalize and hash email
			if ( isset( $params['em'] ) && $params['em'] !== '' ) {
				$normalized_email = self::normalize_tiktok_email( $params['em'] );
				if ( false !== $normalized_email ) {
					$args['sha256_email'] = hash( 'sha256', $normalized_email );
				}
			}

			// Normalize and hash phone
			if ( isset( $params['ph'] ) && $params['ph'] !== '' ) {
				$country_code = '';
				if ( class_exists( 'WooCommerce' ) && isset( $params['country'] ) ) {
					$country_code = $params['country'];
				}
				$normalized_phone = self::normalize_tiktok_phone( $params['ph'], $country_code );
				if ( false !== $normalized_phone ) {
					$args['sha256_phone_number'] = hash( 'sha256', $normalized_phone );
				}
			}

			return $args;
		}

		public static function advanced_matching_data( $fetch_contact = false ) {
			try {
				$params = array();

				$user = wp_get_current_user();

				if ( ! empty( $user ) && $user->ID !== 0 ) {
					// get user regular data
					$params['fn']          = $user->get( 'user_firstname' );
					$params['ln']          = $user->get( 'user_lastname' );
					$params['em']          = $user->get( 'user_email' );
					$params['ph']          = get_user_meta( $user->ID, 'user_phone', true );
					$params['external_id'] = $user->ID;
				}

				/**
				 * Add common WooCommerce Advanced Matching params
				 */

				if ( class_exists( 'woocommerce' ) ) {

					if ( ! empty( $user ) && $user->ID !== 0 ) {
						// if first name is not set in regular wp user meta
						if ( empty( $params['fn'] ) ) {
							$params['fn'] = $user->get( 'billing_first_name' );
						}

						// if last name is not set in regular wp user meta
						if ( empty( $params['ln'] ) ) {
							$params['ln'] = $user->get( 'billing_last_name' );
						}

						$params['ph'] = $user->get( 'billing_phone' );
						$params['ct'] = $user->get( 'billing_city' );
						$params['st'] = $user->get( 'billing_state' );

						$params['country'] = $user->get( 'billing_country' );
					}
					/**
					 * Add purchase WooCommerce Advanced Matching params
					 */

					if ( is_order_received_page() ) {

						$order_id = self::get_woo_order_id();
						$order    = wc_get_order( $order_id );

						if ( $order instanceof WC_Order ) {
							$params = array(
								'em'          => $order->get_billing_email(),
								'ph'          => $order->get_billing_phone(),
								'fn'          => $order->get_billing_first_name(),
								'ln'          => $order->get_billing_last_name(),
								'ct'          => $order->get_billing_city(),
								'st'          => $order->get_billing_state(),
								'country'     => $order->get_billing_country(),
								'external_id' => $order->get_customer_id(),
							);
						}
					}
				}

				if ( empty( $params['external_id'] ) && ! empty( $_COOKIE['wffn_flt'] ) ) {
					$params['external_id'] = bwf_clean( wp_unslash( $_COOKIE['wffn_flt'] ) );
				}
				// Custom: Fill missing fields from wp_bwf_contact table using UID from _fk_contact_uid cookie
				if ( $fetch_contact ) {
					global $wpdb;
					$fields_map = array(
						'em'      => 'email',
						'fn'      => 'f_name',
						'ln'      => 'l_name',
						'ph'      => 'contact_no',
						'st'      => 'state',
						'country' => 'country',
					);

					$need_contact = false;
					foreach ( $fields_map as $param_key => $db_col ) {
						if ( empty( $params[ $param_key ] ) ) {
							$need_contact = true;
							break;
						}
					}
					if ( $need_contact && ! empty( $_COOKIE['_fk_contact_uid'] ) ) {
						$uid     = sanitize_text_field( wp_unslash( $_COOKIE['_fk_contact_uid'] ) );
						$table   = $wpdb->prefix . 'bwf_contact';
						$contact = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT email, f_name, l_name, contact_no, state, country FROM {$table} WHERE uid = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$uid
							)
						);
						if ( $contact ) {
							foreach ( $fields_map as $param_key => $db_col ) {
								if ( empty( $params[ $param_key ] ) && ! empty( $contact->$db_col ) ) {
									$params[ $param_key ] = $contact->$db_col;
								}
							}
						}
					}
				}

				// Get country from WooCommerce geo location if setting is enabled
				if ( class_exists( 'BWF_Admin_General_Settings' ) ) {
					$use_geo_location = BWF_Admin_General_Settings::get_instance()->get_option( 'is_fb_use_geo_location_country' );
					if ( is_array( $use_geo_location ) && count( $use_geo_location ) > 0 && 'yes' === $use_geo_location[0] ) {
						// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
						$geo_hash_cookie = isset( $_COOKIE['woocommerce_geo_hash'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_geo_hash'] ) ) : '';
						if ( ! empty( $geo_hash_cookie ) ) {
							$billing_country = WC()->customer->get_billing_country();

							if ( ! empty( $billing_country ) ) {
								$params['country'] = $billing_country;
							}
						}
					}
				}
				$params = apply_filters( 'wffn_advanced_matching_data', $params );

				if ( ! is_array( $params ) || count( $params ) === 0 ) {
					return array();
				}

				return $params;
			} catch ( \Throwable $e ) {
				error_log( 'Error in advanced_matching_data: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return array();
			}
		}

		public static function get_woo_order_id() {
			// @codingStandardsIgnoreStart
			if ( isset( $_REQUEST['key'] ) && $_REQUEST['key'] != "" ) {
				$order_key = sanitize_key( $_REQUEST['key'] );
				$order_id  = (int) wc_get_order_id_by_order_key( $order_key );

				return $order_id;
			}
			if ( isset( $_REQUEST['referenceCode'] ) && $_REQUEST['referenceCode'] != "" ) {
				return (int) $_REQUEST['referenceCode'];
			}
			if ( isset( $_REQUEST['ref_venta'] ) && $_REQUEST['ref_venta'] != "" ) {
				return (int) $_REQUEST['ref_venta'];
			}
			if ( ! empty( $_REQUEST['wfty_source'] ) && ! empty( $_REQUEST['order_id'] ) ) {
				return (int) $_REQUEST['order_id'];
			}

			if ( ! empty( $_REQUEST['wfty_source'] ) && ! empty( $_REQUEST['order'] ) ) {
				return (int) $_REQUEST['order'];
			}

			if ( ! empty( $_REQUEST['wcf-order'] ) ) {
				return (int) $_REQUEST['wcf-order'];
			}

			// @codingStandardsIgnoreEnd
			return - 1;
		}

		public static function sanitize_advanced_matching_param( $value, $key ) {
			$value = strtolower( $value );
			if ( $key === 'ph' ) {
				$value = preg_replace( '/\D/', '', $value );
			} elseif ( $key === 'em' ) {
				$value = preg_replace( '/[^a-z0-9._+-@]+/i', '', $value );
			} else {
				// only letters with unicode support
				$value = preg_replace( '/[^\w\p{L}]/u', '', $value );
			}

			return $value;
		}


		/**
		 * Check if automation page is skipped
		 *
		 * @return bool
		 */
		public static function skip_automation_page() {
			$fb_site_options = get_option( 'fb_site_options', array() );
			if ( ! isset( $fb_site_options['skip_automation_page'] ) || 1 !== intval( $fb_site_options['skip_automation_page'] ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Check if subscriptions page should be skipped (dismissed by user).
		 *
		 * @return bool
		 */
		public static function skip_subscriptions_page() {
			$fb_site_options = get_option( 'fb_site_options', array() );
			if ( ! isset( $fb_site_options['skip_subscriptions_page'] ) || 1 !== intval( $fb_site_options['skip_subscriptions_page'] ) ) {
				return false;
			}

			return true;
		}

		public static function wffn_round( $value, $precision = 2 ) {
			if ( ! is_numeric( $value ) ) {
				$value = floatval( $value );
			}

			return round( $value, $precision );
		}


		public static function get_plugin_status( $plugin_init_file ) {
			$plugins = get_plugins();

			if ( ! is_array( $plugins ) || ! isset( $plugins[ $plugin_init_file ] ) ) {
				return 'install';
			}

			if ( ! is_plugin_active( $plugin_init_file ) ) {
				return 'activate';
			}

			if ( isset( $plugins[ $plugin_init_file ] ) ) {
				return 'activated';
			}

			return '';
		}

		public static function install_plugin( $plugin_slug ) {

			$resp = array(
				'status' => false,
				'msg'    => __( 'Unable to install plugin', 'funnel-builder' ),
			);

			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			include_once ABSPATH . '/wp-admin/includes/admin.php';
			include_once ABSPATH . '/wp-admin/includes/plugin-install.php';
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
			include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . '/wp-admin/includes/class-plugin-upgrader.php';

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $plugin_slug,
					'fields' => array(
						'sections' => false,
					),
				)
			);

			if ( is_wp_error( $api ) ) {
				$resp['msg'] = $api->get_error_message();

				return $resp;
			}

			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );

			if ( ! empty( $api->download_link ) && ! empty( $api->package_hash ) ) {
				add_filter(
					'upgrader_post_download',
					function ( $reply, $package, $upgrader_instance ) use ( $api ) {
						if ( is_wp_error( $reply ) || empty( $reply ) ) {
							return $reply;
						}
						$hash = hash_file( 'sha256', $reply );
						if ( ! hash_equals( $api->package_hash, $hash ) ) {
							return new \WP_Error( 'package_hash_mismatch', __( 'Package integrity check failed — hash mismatch.', 'funnel-builder' ) );
						}

						return $reply;
					},
					10,
					3
				);
			}

			$result = $upgrader->install( $api->download_link );

			if ( is_wp_error( $result ) ) {
				$resp['msg'] = $result->get_error_message();

				return $resp;
			}

			if ( is_null( $result ) ) {
				global $wp_filesystem;
				$resp['msg'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.', 'funnel-builder' );

				// Pass through the error from WP_Filesystem if one was raised.
				if ( $wp_filesystem instanceof WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
					$resp['msg'] = esc_html( $wp_filesystem->errors->get_error_message() );
				}

				return $resp;
			}

			$resp = install_plugin_install_status( $api );

			return $resp;
		}

		/**
		 * Runs WooCommerce's own installer after a silent activation.
		 *
		 * The wizard activates WooCommerce with activate_plugin( …, $silent = true ),
		 * which suppresses WooCommerce's activation hook, so WC_Install::install()
		 * never runs and its tables, pages, and db-version marker are never created.
		 * Calling this immediately after the silent activation makes WooCommerce
		 * finish installing before any follow-up request queries its tables. It is
		 * idempotent — WC_Install::install() no-ops once woocommerce_db_version is
		 * set — and never fatals if WooCommerce's files are unavailable.
		 *
		 * @since 3.15.0.9
		 *
		 * @param string $plugin_init Basename of the plugin that was just activated.
		 *
		 * @return void
		 */
		public static function maybe_install_woocommerce( $plugin_init ) {
			// Key strictly off WooCommerce's basename so other plugin activations are untouched.
			if ( 'woocommerce/woocommerce.php' !== $plugin_init ) {
				return;
			}

			// Include-before-use: WC_Install may not be loaded during a silent activation.
			if ( ! class_exists( 'WC_Install' ) ) {
				$wc_install_file = WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-install.php';
				if ( file_exists( $wc_install_file ) ) {
					include_once $wc_install_file;
				}
			}

			// Fail safe: if WooCommerce's installer is still unavailable, skip without fatal.
			if ( ! class_exists( 'WC_Install' ) ) {
				return;
			}

			WC_Install::install();
			// Mirror the Elementor precedent: clear the redirect transient so WooCommerce
			// does not try to redirect the headless REST request.
			delete_transient( '_wc_activation_redirect' );
		}

		public static function get_compare_operator_amount( $operand ) {
			$operator = '';
			switch ( $operand ) {
				case 'eq':
					$operator = '=';
					break;
				case 'ge':
					$operator = '>=';
					break;
				case 'le':
					$operator = '<=';
					break;
				case 'gt':
					$operator = '>';
					break;
				case 'lt':
					$operator = '<';
					break;
				default:
					break;

			}

			return $operator;
		}

		public static function get_refs( $all = false, $filter = false ) {
			$refs = array(
				'Facebook'  => array(
					'://fb.com',
					'://m.me',
					'messenger.com',
					'facebook.com',
					'l.facebook.com',
					'meta.com',
				),
				'Google'    => array( 'google' ),
				'Instagram' => array(
					'instagram.com',
					'l.instagram.com',
					'://ig.me',
				),
				'YouTube'   => array( 'youtube' ),
				'Tiktok'    => array( 'tiktok.com' ),
				'Pinterest' => array( 'pinterest.com' ),
				'SnapChat'  => array( 'snapchat.com' ),
				'Yahoo'     => array( 'yahoo' ),
				'X/Twitter' => array(
					'://t.co',
					'twitter.com',
				),

			);

			if ( true === $all ) {
				$refs           = array_merge( array( 'direct' => array() ), $refs );
				$refs['others'] = array();
			}
			if ( true === $filter ) {
				$filters = array();
				foreach ( $refs as $key => $ref ) {

					$filters[ $key ] = ucwords( $key );
				}
				$filters['direct'] = __( 'Direct', 'funnel-builder' );

				return $filters;
			}

			return $refs;
		}

		/**
		 * Check if wc HPOS custom order table enabled.
		 *
		 * @return bool
		 */
		public static function is_wc_hpos_enabled() {
			return ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) ? true : false;
		}


		public static function get_step_type( $post_type ) {
			switch ( $post_type ) {
				case 'wfacp_checkout':
					$type = 'wc_checkout';
					break;
				case 'wfocu_offer':
					$type = 'wc_upsells';
					break;
				case 'wfocu_funnel':
					$type = 'wc_upsells';
					break;
				case 'wffn_landing':
					$type = 'landing';
					break;
				case 'wffn_ty':
					$type = 'wc_thankyou';
					break;
				case 'wffn_optin':
					$type = 'optin';
					break;
				case 'wffn_oty':
					$type = 'optin_ty';
					break;
				default:
					$type = $post_type;
			}

			return $type;
		}

		public static function oxy_get_meta_prefix( $key ) {
			if ( function_exists( 'oxy_get_meta_prefix' ) ) {
				$key = oxy_get_meta_prefix( $key );
			}

			return $key;
		}

		/**
		 * Re-sign an imported Oxygen template with this site's oxygen_private_key.
		 *
		 * Oxygen signs every shortcode with a per-site key, so a template exported from
		 * another site carries foreign signatures and is rejected on sites that have
		 * signature validation switched on. Running the template through Oxygen's own
		 * parse_shortcodes() -> parse_components_tree() round trip re-emits it signed
		 * with the local key.
		 *
		 * That round trip is lossy though: parse_shortcodes() matches tags with
		 * get_shortcode_regex(), which only knows *registered* shortcodes. During a
		 * template import no FunnelKit Oxygen element is registered (they only boot on
		 * their own step pages and inside the builder), so those shortcodes are parsed
		 * as plain text and come back out of parse_components_tree() bracket-escaped to
		 * _OXY_OPENING_BRACKET_ — the widget is destroyed and renders as literal text on
		 * the front end. Third-party Oxygen add-on elements have the exact same problem.
		 *
		 * So register every tag the template actually uses for the duration of the round
		 * trip, and refuse the result outright if it still came back mangled.
		 *
		 * @param string $content Oxygen shortcode string as exported.
		 *
		 * @return string Re-signed content, or the untouched input when the round trip is
		 *                unavailable or would lose data.
		 */
		public static function oxy_resign_shortcodes( $content ) {
			if ( ! is_string( $content ) || '' === $content ) {
				return $content;
			}

			if ( ! function_exists( 'parse_shortcodes' ) || ! function_exists( 'parse_components_tree' ) ) {
				return $content;
			}

			$registered = self::oxy_register_template_shortcodes( $content );

			$reemit = '';
			$parse  = parse_shortcodes( $content, false, false );

			if ( is_array( $parse ) && ! empty( $parse['is_shortcode'] ) && ! empty( $parse['content'] ) && is_array( $parse['content'] ) ) {
				$reemit = parse_components_tree( $parse['content'] );
			}

			foreach ( $registered as $tag ) {
				remove_shortcode( $tag );
			}

			if ( ! is_string( $reemit ) || '' === $reemit ) {
				return $content;
			}

			/**
			 * Never accept a round trip that escaped brackets the source did not already
			 * contain, or that lost an element entirely. A template left with foreign
			 * signatures still recovers the moment signature validation is turned off (it
			 * is off by default); a mangled template never recovers.
			 */
			if ( substr_count( $reemit, '_OXY_OPENING_BRACKET_' ) > substr_count( $content, '_OXY_OPENING_BRACKET_' ) ) {
				return $content;
			}

			$lost = array_diff( self::oxy_get_shortcode_tags( $content ), self::oxy_get_shortcode_tags( $reemit ) );
			if ( count( $lost ) > 0 ) {
				return $content;
			}

			return $reemit;
		}

		/**
		 * Register every shortcode tag used by an Oxygen template that is not registered
		 * yet, so Oxygen's parser treats all of them as tree nodes.
		 *
		 * The callback is irrelevant — the tags are only ever matched by
		 * get_shortcode_regex(), never executed — and every tag added here is removed
		 * again by the caller as soon as the round trip finishes.
		 *
		 * @param string $content Oxygen shortcode string.
		 *
		 * @return array Tags that were added and must be removed afterwards.
		 */
		private static function oxy_register_template_shortcodes( $content ) {
			$added = array();

			foreach ( self::oxy_get_shortcode_tags( $content ) as $tag ) {
				if ( ! shortcode_exists( $tag ) ) {
					add_shortcode( $tag, '__return_empty_string' );
					$added[] = $tag;
				}
			}

			return $added;
		}

		/**
		 * Collect the distinct opening shortcode tag names used in an Oxygen template.
		 *
		 * @param string $content Oxygen shortcode string.
		 *
		 * @return array
		 */
		private static function oxy_get_shortcode_tags( $content ) {
			if ( ! is_string( $content ) || '' === $content ) {
				return array();
			}

			// Opening tags only — `[/name]` cannot match because `/` is not a name start.
			if ( ! preg_match_all( '/\[([a-z][a-z0-9_-]*)[\s\]]/i', $content, $matches ) ) {
				return array();
			}

			return array_values( array_unique( $matches[1] ) );
		}

		/**
		 * @param $array
		 *
		 * @return array|false
		 */
		public static function array_flatten( $array ) {
			if ( ! is_array( $array ) ) {
				return $array;
			}

			return iterator_to_array( new RecursiveIteratorIterator( new RecursiveArrayIterator( $array ) ), false );
		}

		/**
		 * Get bi-weekly date range
		 *
		 * @return array
		 */
		public static function get_notification_biweekly_range() {
			$dates = array();

			$date = new DateTime( current_time( 'mysql', true ) );
			$date->setTimezone( wp_timezone() );
			if ( 1 !== intval( $date->format( 'N' ) ) ) {
				$date->modify( 'last Monday' );
			}
			$date->setTime( 0, 0, 0 );

			$date->modify( '-14 days' );
			$dates['from_date'] = $date->format( 'Y-m-d' );

			$date->modify( '+13 days' );
			$dates['to_date'] = $date->format( 'Y-m-d' );

			$date->modify( '-14 days' );
			$dates['to_date_previous'] = $date->format( 'Y-m-d' );

			$date->modify( '-13 days' );
			$dates['from_date_previous'] = $date->format( 'Y-m-d' );

			return $dates;
		}

		/**
		 * Get bi-annual (6-monthly) date range
		 *
		 * @return array
		 */
		public static function get_notification_biannually_range() {
			$dates = array();

			$date = new DateTime( current_time( 'mysql', true ) );
			$date->setTimezone( wp_timezone() );
			$current_month = $date->format( 'n' );
			$start_month   = ( $current_month <= 6 ) ? 1 : 7;
			$date->setDate( $date->format( 'Y' ), $start_month, 1 );
			$date->setTime( 0, 0, 0 );

			$dates['from_date'] = $date->format( 'Y-m-d' );
			$date->modify( '+5 months' )->modify( 'last day of this month' );
			$dates['to_date'] = $date->format( 'Y-m-d' );

			$date->modify( '-6 months' )->modify( 'first day of this month' );
			$dates['from_date_previous'] = $date->format( 'Y-m-d' );
			$date->modify( 'last day of this month' );
			$dates['to_date_previous'] = $date->format( 'Y-m-d' );

			return $dates;
		}

		/**
		 * Get yearly date range
		 *
		 * @return array
		 */
		public static function get_notification_yearly_range() {
			$dates = array();

			$date = new DateTime( current_time( 'mysql', true ) );
			$date->setTimezone( wp_timezone() );
			$current_year = $date->format( 'Y' );
			$date->setDate( $current_year, 1, 1 );
			$date->setTime( 0, 0, 0 );

			$dates['from_date'] = $date->format( 'Y-m-d' );
			$date->modify( 'last day of December' );
			$dates['to_date'] = $date->format( 'Y-m-d' );

			$date->modify( '-1 year' )->setDate( $date->format( 'Y' ), 1, 1 );
			$dates['from_date_previous'] = $date->format( 'Y-m-d' );
			$date->modify( 'last day of December' );
			$dates['to_date_previous'] = $date->format( 'Y-m-d' );

			return $dates;
		}

		/**
		 * Get weekly date range
		 *
		 * @return array
		 */
		public static function get_notification_week_range() {
			$dates = array();

			$date = new DateTime( current_time( 'mysql', true ) );
			$date->setTimezone( wp_timezone() );
			if ( 1 !== intval( $date->format( 'N' ) ) ) {
				$date->modify( 'last Monday' );
			}
			$date->setTime( 0, 0, 0 );

			$date = $date->modify( '-7 day' );

			$dates['from_date'] = $date->format( 'Y-m-d' );

			$date = $date->modify( '+6 day' );

			$dates['to_date'] = $date->format( 'Y-m-d' );

			$date = $date->modify( '-7 day' );

			$dates['to_date_previous'] = $date->format( 'Y-m-d' );

			$date = $date->modify( '-6 day' );

			$dates['from_date_previous'] = $date->format( 'Y-m-d' );

			return $dates;
		}

		/**
		 * Get last month date range
		 *
		 * @return array
		 * @throws DateMalformedStringException
		 */
		public static function get_notification_month_range() {
			$dates = array();

			$date = new DateTime( current_time( 'mysql', true ) );
			$date->setTimezone( wp_timezone() );
			$date->modify( 'last Month' );
			$date->setDate( $date->format( 'Y' ), $date->format( 'm' ), 1 );
			$date->setTime( 0, 0, 0 );

			$dates['from_date'] = $date->format( 'Y-m-d' );

			$last_month = clone $date;

			$date->modify( 'last day of this month' );
			$dates['to_date'] = $date->format( 'Y-m-d' );

			$last_month->modify( 'last Month' );
			$last_month->setDate( $last_month->format( 'Y' ), $last_month->format( 'm' ), 1 );

			$dates['from_date_previous'] = $last_month->format( 'Y-m-d' );

			$last_month->modify( 'last day of this month' );
			$dates['to_date_previous'] = $last_month->format( 'Y-m-d' );

			return $dates;
		}
		/**
		 * Get last day date range
		 *
		 * @return array
		 * @throws DateMalformedStringException
		 */
		public static function get_notification_day_range() {
			$dates = array();

			$date = new DateTime( current_time( 'mysql', true ) );
			$date->setTimezone( wp_timezone() );
			$date->modify( '-1 day' );
			$date->setTime( 0, 0, 0 );

			$dates['from_date'] = $date->format( 'Y-m-d' );
			$dates['to_date']   = $date->format( 'Y-m-d' );

			$date->modify( '-1 day' );

			$dates['from_date_previous'] = $date->format( 'Y-m-d' );
			$dates['to_date_previous']   = $date->format( 'Y-m-d' );

			return $dates;
		}

		/**
		 * get desired store time
		 *
		 * @param $hours
		 * @param $mins
		 * @param $sec
		 *
		 * @return int
		 */
		public static function get_store_time( $hours = 0, $mins = 0, $sec = 0 ) {
			$timezone = new DateTimeZone( wp_timezone_string() );
			$date     = new DateTime();

			$date->setTimezone( $timezone );
			$date->setTime( $hours, $mins, $sec );

			if ( time() > $date->getTimestamp() ) {
				$date->modify( '+1 days' );
			}
			return $date->getTimestamp();
		}
		/**
		 * get Stripe State
		 *
		 * @return array
		 */
		public static function stripe_state() {
			if ( self::is_wc_square_active() ) {
				return array(
					'status'     => 'connected',
					'show_pitch' => false,
				);
			}

			$all_plugins = get_plugins();

			$other_stripe_exists = ( defined( 'WC_STRIPE_VERSION' ) || defined( 'WC_STRIPE_PLUGIN_FILE_PATH' ) );

			if ( isset( $all_plugins['funnelkit-stripe-woo-payment-gateway/funnelkit-stripe-woo-payment-gateway.php'] ) ) {

				if ( is_plugin_active( 'woocommerce/woocommerce.php' ) && is_plugin_active( 'funnelkit-stripe-woo-payment-gateway/funnelkit-stripe-woo-payment-gateway.php' ) && class_exists( '\FKWCS\Gateway\Stripe\Admin' ) ) {
					if ( \FKWCS\Gateway\Stripe\Admin::get_instance()->is_stripe_connected() ) {
						return array( 'status' => 'connected' );

					} else {
						return array(
							'status'       => 'not_connected',
							'link'         => \FKWCS\Gateway\Stripe\Admin::get_instance()->get_connect_url(),
							'other_exists' => $other_stripe_exists,
						);

					}
				} else {
					return array(
						'status'       => 'not_activated',
						'other_exists' => $other_stripe_exists,
					);

				}
			} else {
				return array(
					'status'       => 'not_installed',
					'other_exists' => $other_stripe_exists,
				);
			}
		}

		/**
		 * Check if WooCommerce Square is active.
		 *
		 * @return bool
		 */
		public static function is_wc_square_active(): bool {
			return wffn_is_plugin_active( 'woocommerce-square/woocommerce-square.php' ) || class_exists( 'WooCommerce_Square_Loader' );
		}

		/**
		 * get Square State
		 *
		 * @return array
		 */
		public static function square_state() {
			if ( ! self::is_wc_square_active() ) {
				return array(
					'status'     => 'gate_not_met',
					'show_pitch' => false,
				);
			}

			$all_plugins = get_plugins();

			if ( isset( $all_plugins['funnelkit-payment-gateway-square-for-woocommerce/funnelkit-square.php'] ) ) {
				if ( is_plugin_active( 'woocommerce/woocommerce.php' ) && is_plugin_active( 'funnelkit-payment-gateway-square-for-woocommerce/funnelkit-square.php' ) ) {
					if ( class_exists( '\FKWCSQ\Square' ) && \FKWCSQ\Square::is_configured() ) {
						return array( 'status' => 'connected' );
					}

					$link = admin_url( 'admin.php?page=bwf&path=/square-connect' );
					if ( class_exists( '\FKWCSQ\Includes\Modules\Connection\MiddleWare' ) ) {
						$middleware = \FKWCSQ\Includes\Modules\Connection\MiddleWare::get_instance();
						if ( method_exists( $middleware, 'get_connection_url' ) ) {
							$middleware_link = $middleware->get_connection_url();
							if ( ! empty( $middleware_link ) ) {
								$link = $middleware_link;
							}
						}
					}

					return array(
						'status' => 'not_connected',
						'link'   => $link,
					);
				}

				return array( 'status' => 'not_activated' );
			}

			return array( 'status' => 'not_installed' );
		}

		/**
		 * Add order URL for single order ID
		 *
		 * @param int $order_id Order ID
		 * @return string Order edit URL
		 */
		public static function add_order_urls( $order_id ): string {
			if ( empty( $order_id ) ) {
				return '';
			}

			$order_id = absint( $order_id );

			if ( $order_id <= 0 ) {
				return '';
			}

			$hpos_enabled = BWF_WC_Compatibility::is_hpos_enabled();

			if ( $hpos_enabled ) {
				return admin_url( "admin.php?page=wc-orders&action=edit&id={$order_id}" );
			} else {
				return admin_url( "post.php?post={$order_id}&action=edit" );
			}
		}

		/**
		 * Returns the current version of the specified builder plugin.
		 *
		 * For all builder plugins that support templates, this method returns the current version of the builder plugin.
		 *
		 * @param string $builder The builder plugin identifier (e.g., 'elementor', 'divi', 'oxy').
		 * @return string The version of the builder plugin, or an empty string if not found.
		 */
		public static function get_builder_version( $builder ) {
			try {
				if ( $builder === 'elementor' ) {
					return ELEMENTOR_VERSION;
				}

				if ( $builder === 'divi' || $builder === 'divi5' ) {
					return defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? ET_BUILDER_PRODUCT_VERSION : '';
				}

				if ( $builder === 'oxy' ) {
					return CT_VERSION;
				}
			} catch ( Throwable $e ) {
				// Silently fail to prevent breaking main functionality
			}

			return '';
		}

		/**
		 * Check if Elementor Container feature is active
		 *
		 * @return bool True if Container is active, false otherwise
		 */
		public static function is_elementor_container_active() {
			try {
				if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->experiments ) ) {
					return false;
				}

				$experiments = \Elementor\Plugin::$instance->experiments;

				return $experiments->is_feature_active( 'container' );
			} catch ( Throwable $e ) {
				return false;
			}
		}

		public static function sanitize_global_css( $css ) {
			if ( ! is_string( $css ) || '' === $css ) {
				return '';
			}

			// Unwrap, don't delete, <style> wrappers a merchant may have pasted around their CSS —
			// strip only the literal tags and keep the declarations inside (wp_strip_all_tags used to
			// wipe the whole block, silently destroying legitimate CSS).
			$css = preg_replace( '#</?\s*style\b[^>]*>#i', '', $css );

			// Remove only tag openers (`<` followed by a letter, `!` or `/`) so a `</style><script>…`
			// breakout is neutralized without eating `>` child combinators or a literal `<` in a value.
			$css = preg_replace( '#<\s*(?=[a-z!/])#i', '', $css );

			// Legacy CSS-context vectors, scoped so they never touch selector/identifier names.
			$css = preg_replace( '/expression\s*\(/i', '', $css );   // IE expression().
			$css = preg_replace( '/-moz-binding\s*:/i', '', $css );  // XBL binding.

			// Strip javascript:/vbscript: only when used as a protocol inside url( … ) — a bare
			// `javascript:` elsewhere in CSS is inert, and stripping it globally mangled selectors.
			$css = preg_replace_callback(
				'#url\(\s*([\'"]?)(.*?)\1\s*\)#is',
				static function ( $matches ) {
					$clean = preg_replace( '/(?:javascript|vbscript)\s*:/i', '', $matches[2] );
					if ( $clean === $matches[2] ) {
						return $matches[0]; // No dangerous protocol — keep the url() token byte-identical.
					}

					return 'url(' . $matches[1] . $clean . $matches[1] . ')';
				},
				$css
			);

			// `behavior:` only as a declaration (start of file, or after `;`/`{`), never inside a
			// selector such as `.behavior:hover`.
			$css = preg_replace( '/(^|[;{]\s*)behavior\s*:/i', '$1', $css );

			return $css;
		}

		/**
		 * Allow-listed iframe src origins permitted inside script fields.
		 *
		 * Matched case-insensitively as a URL prefix against each iframe's src by
		 * sanitize_global_script(). Add trusted embed origins to the array
		 * (keep in sync with the JS sanitizer's ALLOWED_IFRAME_SRC).
		 *
		 * @return string[]
		 */
		public static function get_allowed_iframe_src() {
			// Iframe src origins allowed inside script fields (case-insensitive prefix
			// match). Add future trusted embed origins to this array.
			return array(
				'https://www.googletagmanager.com',
			);
		}

		public static function sanitize_global_script( $script ) {
			// (1) Dangerous standalone tags.
			$script = preg_replace( '#<\s*/?\s*base\b[^>]*>#i', '', $script );
			$script = preg_replace( '#<\s*/?\s*link\b[^>]*>#i', '', $script );
			$script = preg_replace( '#<\s*meta\b[^>]*http-equiv\s*=\s*["\']?\s*refresh[^>]*>#i', '', $script );
			$script = preg_replace( '#<\s*/?\s*(?:object|embed)\b[^>]*>#i', '', $script );
			// Strip iframes EXCEPT those whose src is explicitly allow-listed (e.g. the
			// Google Tag Manager <noscript> fallback). Closing </iframe> tags are left
			// as-is (harmless: kept for allowed iframes, orphaned for removed ones).
			$allowed_iframe_src = self::get_allowed_iframe_src();
			$script             = preg_replace_callback(
				'#<\s*iframe\b[^>]*>#i',
				static function ( $m ) use ( $allowed_iframe_src ) {
					if ( is_array( $allowed_iframe_src ) && count( $allowed_iframe_src ) > 0 && preg_match( '#\ssrc\s*=\s*["\']?\s*([^"\'\s>]+)#i', $m[0], $src_m ) ) {
						$src        = strtolower( $src_m[1] );
						$src_scheme = wp_parse_url( $src, PHP_URL_SCHEME ) ?: '';
						$src_host   = wp_parse_url( $src, PHP_URL_HOST ) ?: '';
						$src_port   = wp_parse_url( $src, PHP_URL_PORT );
						foreach ( $allowed_iframe_src as $prefix ) {
							$pfx      = strtolower( $prefix );
							$pfx_host = wp_parse_url( $pfx, PHP_URL_HOST ) ?: '';
							if ( '' !== $pfx_host && $src_host === $pfx_host && $src_scheme === ( wp_parse_url( $pfx, PHP_URL_SCHEME ) ?: '' ) && $src_port === wp_parse_url( $pfx, PHP_URL_PORT ) ) {
								return $m[0];
							}
						}
					}

					return '';
				},
				$script
			);
			$script             = preg_replace( '#<\s*(?:set|animate[a-z]*)\b[^>]*\battributeName\s*=\s*["\']?\s*(?:on|href|xlink)[^>]*>#i', '', $script );

			// (2) Dangerous attributes / inline-URI protocols.
			$script = preg_replace( '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $script );
			$script = preg_replace( '#\bsrcdoc\s*=#i', ' data-blocked-srcdoc=', $script );
			$script = preg_replace( '#\s(?:href|src|action|formaction|xlink:href)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data:\s*text/html)[^\s>"\']*\1?#i', '', $script );

			// (3) Inline JS sink signatures.
			$sinks = array(
				'#\bimport\s*\(\s*[^)]*[\'"`]\s*\+#i',
				'#\b(?:import|fetch)\s*\(\s*[\'"`][^\'"`]*\\\\(?:x[0-9a-f]{2}|u[0-9a-f]{4}|[0-7]{1,3})#i',
				'#\bimport\s*\(\s*[^)]*\b(?:location|document|window|self|top|name|atob|unescape|decodeURI|localStorage|sessionStorage)\b#i',
				'#\b(?:import|fetch|eval|Function)\s*\(\s*atob\s*\(#i',
				'#(?:\.\s*then\s*\(\s*|=>\s*)(?:eval|Function|new\s+Function)\b#i',
				'#\bdocument\s*\.\s*cookie\b#i',
				'#\blocation(?:\s*\.\s*(?:href|replace|assign))?\s*(?:=(?!=)|\(\s*)\s*(?:atob|unescape|decodeURI|decodeURIComponent|String\s*\.\s*fromCharCode)\s*\(#i',
				'#\bnew\s+Function\s*\(#i',
				'#\b(?:setTimeout|setInterval)\s*\(\s*[\'"`]#i',
				'#\bnew\s+WebSocket\s*\(#i',
				'#\bnew\s+Blob\s*\([^)]*(?:java|ecma)script#i',
				'#createObjectURL#i',
				'#data:\s*(?:text|application)/(?:java|ecma)script[^,]*;\s*base64\s*,[A-Za-z0-9+/=]*#i',
				'#fromCharCode[\'"\]\s)]*\([^)]*\^#i',
				'#(?:0x[0-9a-f]{1,2}\s*,\s*){8,}#i',
				'#(?:window|top|self|globalThis|document)\s*\[\s*[\'"][^\'"]{1,8}[\'"]\s*\+#i',
				'#(?:\\\\x[0-9a-f]{2}){16,}#i',
			);

			// (4) Remove whole <script> blocks that decode-and-execute, or carry a sink.
			$script = preg_replace_callback(
				'#<script[^>]*>(.*?)</script>#is',
				static function ( $m ) use ( $sinks ) {
					$b = $m[1];
					for ( $i = 0; $i < 5 && ( $d = html_entity_decode( $b, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) !== $b; $i++ ) {
						$b = $d;
					}

					$decode = preg_match( '#\batob\s*\(#i', $b ) + preg_match( '#fromCharCode#i', $b )
						+ preg_match( '#\^\s*0x?[0-9a-f]+#i', $b ) + preg_match( '#(?:0x[0-9a-f]{1,2}\s*,\s*){8,}#i', $b )
						+ preg_match( '#\bunescape\s*\(#i', $b ) + preg_match( '#decodeURIComponent\s*\([^)]{40,}#i', $b );
					$exec   = preg_match( '#\beval\s*\(#i', $b ) + preg_match( '#\bnew\s+Function\s*\(#i', $b )
						+ preg_match( '#createObjectURL#i', $b ) + preg_match( '#\bnew\s+WebSocket\s*\(#i', $b )
						+ preg_match( '#createElement[\'"\]\s]*\(?\s*[\'"]script#i', $b ) + preg_match( '#\bimport\s*\(#i', $b )
						+ preg_match( '#\b(?:setTimeout|setInterval)\s*\(\s*[\'"`]#i', $b );
					$reach  = preg_match( '#(?:appendChild|insertBefore|\.append|\[\s*[\'"]append)#i', $b )
						+ preg_match( '#[\.\[][\'"]?\s*src\s*[\'"\]]*\s*=#i', $b ) + preg_match( '#document\s*\.\s*write#i', $b );

					if ( ( $decode >= 1 && $exec >= 1 ) || ( $exec >= 2 && $reach >= 1 ) ) {
						return '';
					}
					// atob() + any script-injection scaffold (catches createElement(variable), .src = atob(...), etc.).
					if (
						preg_match( '#\batob\s*\(#i', $b ) &&
						(
							preg_match( '#\bcreateElement\s*\(#i', $b ) ||
							preg_match( '#[\.\[][\'"]?\s*src\s*[\'"\]]*\s*=#i', $b ) ||
							preg_match( '#(?:appendChild|insertBefore|\.append)\s*\(#i', $b )
						)
					) {
						return '';
					}
					foreach ( $sinks as $re ) {
						if ( preg_match( $re, $b ) ) {
							return '';
						}
					}
					return $m[0];
				},
				$script
			);

			// (5) Final sweep for sinks left outside <script>.
			foreach ( $sinks as $re ) {
				$script = preg_replace( $re, '', $script );
			}

			return $script;
		}

		/**
		 * Enqueue the WebFont loader used by the block editors' font pickers.
		 *
		 * @param string $webfont_handle Handle to register/enqueue the WebFont loader script under.
		 */
		public static function enqueue_block_editor_webfont_loader( $webfont_handle = 'bwf-web-font' ) {
			wp_register_script( $webfont_handle, WFFN_PLUGIN_URL . '/assets/js/webfont-loader.js', array(), WFFN_VERSION_DEV, true );
			wp_enqueue_script( $webfont_handle );
		}
	}

}
