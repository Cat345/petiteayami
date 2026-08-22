<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_admin' ) ) {
	#[AllowDynamicProperties]
	final class WFACP_admin {

		private static $ins  = null;
		public $wfacp_id     = 0;
		public $current_page = 'design';
		public $current_section;
		public $default_checkout_status = false;
		protected $localize_data        = array();
		protected $checkout_post_list   = array();
		protected $have_variable        = false;
		private $address_fields         = array(
			'billing'  => array(),
			'shipping' => array(),
		);
		private $wfacp_custom_fields    = array();

		protected function __construct() {
			$this->wfacp_id = WFACP_Common::get_id();
			/**
			 * GENERIC: shared hooks that must run even when the legacy checkout admin page is
			 * disabled. They fire on the live WooCommerce checkout, the order-edit screen, the
			 * Customizer, the Plugins list and the funnel-builder REST app — none need page=wfacp.
			 */
			add_action( 'admin_head', array( $this, 'open_admin_bar' ), 90 );
			add_filter( 'woocommerce_billing_fields', array( $this, 'add_css_ready_classes' ) );
			add_filter( 'woocommerce_shipping_fields', array( $this, 'add_css_ready_classes' ) );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'show_advanced_field_order' ), 99 );
			add_filter( 'wfacp_builder_merge_field_arguments', array( $this, 'wfacp_builder_merge_field_arguments' ), 10, 4 );
			add_filter( 'wfacp_address_fields_billing', array( $this, 'arrange_billing_fields' ), 9 );
			add_filter( 'wfacp_address_fields_shipping', array( $this, 'arrange_shipping_fields' ), 9 );
			add_action( 'admin_menu', array( $this, 'get_advanced_field' ), 95 );
			add_filter( 'is_protected_meta', array( $this, 'wfacp_protected_meta' ), 10, 3 );
			add_filter( 'get_pages', array( $this, 'add_pages_to_front_page_options' ), 15, 2 );

			// Checkout post-edit screen (show_ui=true) + shared checkout post list — fire independently of page=wfacp.
			$post_type = WFACP_Common::get_post_type_slug();
			add_action( 'add_meta_boxes_' . $post_type, array( $this, 'add_meta_boxes_for_shortcodes' ), 10, 2 );
			add_filter( 'wfacp_checkout_post_list', array( $this, 'append_checkout_post_list' ) );
			add_action( 'edit_form_after_title', array( $this, 'add_back_button' ) );
			add_action( 'admin_menu', array( $this, 'remove_page_attributes' ), 90 );

			add_filter( 'bwf_enable_ecommerce_integration_fb_checkout', '__return_true' );
			add_filter( 'bwf_enable_ecommerce_integration_ga_checkout', '__return_true' );
			add_filter( 'bwf_enable_ga4', '__return_true' );
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * @return array
		 */
		public function global_dependency_messages() {
			$aero_messages = array();
			if ( wc_shipping_enabled() ) {

				$shipping_location = admin_url( 'admin.php?page=wc-settings' );
				$shipping_methods  = admin_url( 'admin.php?page=wc-settings&tab=shipping' );
				$msg               = __( sprintf( 'Your store has <a href="%s">shipping location</a> enabled. Depending upon shipping method configuration, checkout may need  "Order Summary" field. Please drag "Order Summary" field to place in form. ', $shipping_location, $shipping_methods ), 'funnel-builder' );
				$aero_messages[]   = array(
					'message'     => $msg,
					'ids'         => array(
						'shipping_calculator',
						'order_summary',
					),
					'show'        => 'yes',
					'dismissible' => true,
					'is_global'   => false,
					'type'        => 'wfacp_warning',
					'call_back'   => '',
					'key'         => 'wfacp_shipping_location_enabled_warning',
				);

				if ( wc_ship_to_billing_address_only() ) {

					$msg = sprintf( __( "<a href='%s'>Shipping destination</a> is set to 'Force shipping to customer billing address'. Please remove Shipping Address field from the checkout form.", 'funnel-builder' ), $shipping_methods );

					$aero_messages[] = array(
						'message'       => $msg,
						'id'            => 'shipping_address',
						'show'          => 'yes',
						'dismissible'   => false,
						'reverse_check' => true,
						'is_global'     => false,
						'type'          => 'wfacp_error',
					);
				}
			}

			if ( get_option( 'woocommerce_ship_to_countries' ) == 'disabled' ) {
				$msg             = sprintf( __( "<a href='%s' target='_blank'>Shipping Location</a> is disabled. Please replace Shipping Address with Billing Address field or enable Shipping Location.", 'funnel-builder' ), admin_url( 'admin.php?page=wc-settings' ) );
				$aero_messages[] = array(
					'message'       => $msg,
					'id'            => 'shipping_address',
					'show'          => 'yes',
					'dismissible'   => false,
					'reverse_check' => true,
					'is_global'     => false,
					'type'          => 'wfacp_error',
					'call_back'     => '',
					'key'           => 'wfacp_wc_ship_to_countries_notice_error',
				);
			}

			$aero_messages[] = array(
				'message'     => __( 'Billing First Name & Last Name are available in the form. Please disable First Name and Last Name from Billing Address field.', 'funnel-builder' ),
				'show'        => 'yes',
				'dismissible' => false,
				'is_global'   => false,
				'type'        => 'wfacp_error',
				'call_back'   => 'wfacp_duplicate_billing_first_and_last_name',
			);

			$messages = apply_filters( 'wfacp_global_dependency_messages', array() );

			if ( ! empty( $messages ) && is_array( $messages ) ) {
				$aero_messages = array_merge( $aero_messages, $messages );
			}

			$final_messages = array();
			if ( empty( $aero_messages ) ) {
				$final_messages = new stdClass();
			} else {
				foreach ( $aero_messages as $msg ) {

					$mid = md5( $msg['message'] );
					if ( ! isset( $msg['is_global'] ) || false === $msg['is_global'] ) {
						$pageID = WFACP_Common::get_id();
						$mid    = md5( $msg['message'] . $pageID );
					}

					if ( isset( $msg['dismissible'] ) ) {
						$msg['dismissible'] = wc_string_to_bool( $msg['dismissible'] );
					} else {
						$msg['dismissible'] = false;
					}
					$final_messages[ $mid ] = $msg;
				}
			}

			return $this->hide_notification( $final_messages );
		}

		private function hide_notification( $messages ) {
			if ( empty( $messages ) ) {
				return $messages;
			}
			$hide_messages = get_option( 'wfacp_global_notifications', array() );

			$post_message = get_post_meta( WFACP_Common::get_id(), 'notifications', true );
			if ( is_array( $post_message ) ) {
				$hide_messages = array_merge( $hide_messages, $post_message );
			}
			if ( empty( $hide_messages ) ) {
				return $messages;
			}

			foreach ( $messages as $mid => $message ) {
				if ( array_key_exists( $mid, $hide_messages ) ) {
					unset( $messages[ $mid ] );
				}
			}

			return $messages;
		}

		public function open_admin_bar() {

			echo "<style>
#order_data #wfacp_admin_advanced_field input[type='radio']{width: auto;float: left;margin: 0 5px 5px 0;}
#order_data #wfacp_admin_advanced_field span.wfacp_radio_options_group{display: block;}
#order_data #wfacp_admin_advanced_field span.wfacp_radio_options_group:before, #order_data #wfacp_admin_advanced_field span.wfacp_radio_options_group:after{content: ''; display: block;}
#order_data #wfacp_admin_advanced_field span.wfacp_radio_options_group:after{    clear: both;} </style>";
		}

		public function add_css_ready_classes( $address ) {

			if ( is_array( $address ) && count( $address ) > 0 ) {
				foreach ( $address as $key => $field ) {
					$address[ $key ]['cssready'] = array();
				}
			}

			return $address;
		}


		/**
		 * this function use for display advanced field in order backend in General Tab
		 *
		 * @param $order WC_Order
		 */
		public function show_advanced_field_order( $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}
			try {
				$wfacp_id = apply_filters( 'wfacp_show_advanced_field_order', wfacp_get_order_meta( $order, '_wfacp_post_id' ) );

				if ( empty( $wfacp_id ) ) {
					return;
				}
				$title = get_the_title( $wfacp_id );
				// page=wfacp is removed; link the order-screen "Template" to the page=bwf checkout builder. (#9188)
				$wfacp_funnel_id = get_post_meta( $wfacp_id, '_bwf_in_funnel', true );
				$title_link      = add_query_arg(
					array(
						'page'      => 'bwf',
						'path'      => '/funnel-checkout/' . $wfacp_id . '/design',
						'funnel_id' => $wfacp_funnel_id,
					),
					admin_url( 'admin.php' )
				);

				$permalink = wfacp_get_order_meta( $order, '_wfacp_source' );
				if ( empty( $permalink ) ) {
					$permalink = get_the_permalink( $wfacp_id );
				}
				$display_text = str_replace( home_url(), '', $permalink );
				?>
				<div style="clear: both;">
					<style>
						#wfacp_admin_advanced_field .optional {
							display: none;
						}
					</style>
				</div>
				<div style="margin-top:15px" class="wfacp_order_backend_field_container">
					<h3 style="display: inline">Checkout</h3>
					<p><b><?php _e( 'Template', 'funnel-builder' ); ?>:</b> <a href="<?php echo $title_link; ?>" target="_blank"><?php echo $title; ?></a></p>
					<p><b><?php _e( 'Source', 'funnel-builder' ); ?>:</b> <a href="<?php echo $permalink; ?>" target="_blank"><?php echo $display_text; ?></a></p>
				</div>
				<?php
				$wfacp_id = absint( $wfacp_id );
				$cfields  = WFACP_Common::get_page_custom_fields( $wfacp_id );
				if ( ! isset( $cfields['advanced'] ) ) {
					return;
				}
				$advancedFields = $cfields['advanced'];

				if ( ! is_array( $advancedFields ) || count( $advancedFields ) == 0 ) {
					return;
				}

				$fields_html     = '';
				$checkout_fields = get_post_meta( $wfacp_id, '_wfacp_checkout_fields', true );
				foreach ( $advancedFields as $field_key => $field ) {
					if ( empty( $field ) || ! isset( $field['is_wfacp_field'] ) || false === wc_string_to_bool( $field['is_wfacp_field'] ) ) {
						continue;
					}
					// Only show fields that are actually on the checkout form
					if ( ! isset( $checkout_fields['advanced'][ $field_key ] ) ) {
						continue;
					}
					if ( ! empty( $field['options'] ) && isset( $checkout_fields['advanced'][ $field_key ] ) ) {
						$field['options'] = ! empty( $checkout_fields['advanced'][ $field_key ]['options'] ) ? $checkout_fields['advanced'][ $field_key ]['options'] : $field['options'];
					}

					$has_data = wfacp_get_order_meta( $order, $field_key );
					if ( ! is_null( $has_data ) && ! is_array( $has_data ) ) {
						if ( isset( $field['options'] ) && ! isset( $field['options'][ $has_data ] ) ) {
							$flip_options = array_flip( $field['options'] );
							$has_data     = isset( $flip_options[ $has_data ] ) ? $flip_options[ $has_data ] : $has_data;

						}
					}

					$tmp_key   = $field_key;
					$field_key = 'wfacp_' . $field_key;
					if ( isset( $field['required'] ) ) {
						unset( $field['required'] );
					}
					if ( $field['type'] == 'hidden' ) {
						$field['type'] = 'text';
					}
					if ( $field['type'] == 'select2' ) {
						$field['type'] = 'select';
					}

					if ( isset( $field['class'] ) ) {
						$field['class'] = array( 'form-field', ' form-field-wide' );
					}

					if ( $field['type'] == 'multiselect' ) {
						// $field['class'][]                       = 'wfacp_custom_field_multiselect';
						$field['type']                          = 'select';
						$field['id']                            = $field_key;
						$field_key                              = $field_key . '[]';
						$field['custom_attributes']['multiple'] = 'multiple';
						if ( is_array( $has_data ) && count( $has_data ) > 0 ) {
							$temp_data = implode( ',', $has_data );
							$has_data  = strtolower( $temp_data );
						} else {
							$field['options'] = explode( ',', $has_data );

						}
						$field['custom_attributes']['data_value'] = $has_data;
					}

					if ( $field['type'] === 'wfacp_date' ) {
						$field['type'] = 'text';
					}
					if ( isset( $field['placeholder'] ) ) {
						unset( $field['placeholder'] );
					}

					if ( 'select' === $field['type'] ) {
						// use array addition instead of array merge because array merge remove index if associative + numeric array combined
						$field['options'] = array( '' => __( 'Select options', 'woocommerce' ) ) + $field['options'];
						if ( empty( $has_data ) && isset( $advancedFields[ $tmp_key ]['placeholder'] ) ) {
							$field['placeholder'] = $advancedFields[ $tmp_key ]['placeholder'];
						}
					}

					ob_start();
					woocommerce_form_field( $field_key, apply_filters( 'wfacp_admin_order_field', $field, $field_key ), apply_filters( 'wfacp_admin_order_field_value', $has_data, $tmp_key, $order ) );
					$fields_html .= ob_get_clean();

				}
				/**
				 * Only render the "Custom Fields" block if at least one field actually produced output.
				 * In Lite the advanced custom-field types render nothing (their woocommerce_form_field
				 * handlers are Pro-only), so without this guard an empty heading + fieldset (a dead label)
				 * appeared on the order screen. The edit affordance is omitted too: its JS
				 * (wfacp_show_admin_advanced_field) is Pro-only, so fields stay read-only via the
				 * disabled fieldset.
				 */
				if ( '' !== trim( $fields_html ) ) {
					echo '<div style="clear: both;"></div><div style="margin-top:15px" class="wfacp_order_backend_field_container"><h3 style="display: inline">' . esc_html__( 'Custom Fields', 'funnel-builder' ) . '</h3><fieldset id="wfacp_admin_advanced_field" disabled>' . $fields_html . '</fieldset></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from woocommerce_form_field()
				}
			} catch ( Exception | Error $e ) {

			}
		}

		public function wfacp_builder_merge_field_arguments( $field, $id, $type, $available_fields ) {

			if ( $id == 'shipping_calculator' && isset( $available_fields[ $type ][ $id ] ) ) {
				$default = $available_fields[ $type ][ $id ];
				$field   = wp_parse_args( $field, $default );
			} elseif ( $id == 'product_switching' ) {

				$default = $available_fields[ $type ][ $id ];
				$field   = wp_parse_args( $field, $default );
			} elseif ( $id == 'vat_number' ) {
				$default = $available_fields[ $type ][ $id ];

				$field = wp_parse_args( $field, $default );
				if ( isset( $default['depend_dency_message'] ) ) {
					$field['depend_dency_message'] = $default['depend_dency_message'];
				}
			} elseif ( $id == 'address' || $id == 'shipping-address' ) {
				$field['fields_options'] = apply_filters( 'wfacp_' . $type . '_address_options', $field['fields_options'] );
			}

			if ( ! isset( $field['data_label'] ) ) {
				$field['data_label'] = $field['label'];
			}

			return $field;
		}


		public function add_meta_boxes_for_shortcodes() {

			$id     = WFACP_Common::get_id();
			$design = WFACP_Common::get_page_design( $id );
			if ( $design['selected_type'] !== 'embed_forms' ) {
				return;
			}
			$post_type = WFACP_Common::get_post_type_slug();
			add_meta_box(
				'woofunnels-aero-checkout-shortcode',
				__( 'FunnelKit Checkout', 'funnel-builder' ),
				array(
					$this,
					'render_shortcode_meta_box',
				),
				$post_type,
				'side',
				'default'
			);
		}

		public function render_shortcode_meta_box() {
			$id     = WFACP_Common::get_id();
			$normal = '[wfacp_forms]';

			if ( $id > 0 ) {
				?>
				<style>
					.wfacp_shortcode .wfacp_shortcode_inner {
						margin: 0 0 20px;
					}

					a.wfacp_copy_text {
						float: right;
					}
				</style>
				<div class="wfacp_shortcode">
					<div class="wfacp_shortcode_inner">
						<div class="wfacp_description">
							<label for='wfacp_shortcode_normal'><?php _e( 'Form Shortcode', 'funnel-builder' ); ?></label>
							<input type="text" readonly="readonly" id='wfacp_shorcode_normal' style="width: 100%;" value="<?php echo $normal; ?>">
						</div>
						<a href="javascript:void(0)" class="wfacp_copy_text">
							<svg fill="#0073aa" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20" height="20">
								<path d="M 18.5 5 C 15.480226 5 13 7.4802259 13 10.5 L 13 32.5 C 13 35.519774 15.480226 38 18.5 38 L 34.5 38 C 37.519774 38 40 35.519774 40 32.5 L 40 10.5 C 40 7.4802259 37.519774 5 34.5 5 L 18.5 5 z M 18.5 8 L 34.5 8 C 35.898226 8 37 9.1017741 37 10.5 L 37 32.5 C 37 33.898226 35.898226 35 34.5 35 L 18.5 35 C 17.101774 35 16 33.898226 16 32.5 L 16 10.5 C 16 9.1017741 17.101774 8 18.5 8 z M 11 10 L 9.78125 10.8125 C 8.66825 11.5545 8 12.803625 8 14.140625 L 8 33.5 C 8 38.747 12.253 43 17.5 43 L 30.859375 43 C 32.197375 43 33.4465 42.33175 34.1875 41.21875 L 35 40 L 17.5 40 C 13.91 40 11 37.09 11 33.5 L 11 10 z"></path>
							</svg><?php _e( 'Copy' ); ?></a>
					</div>
				</div>
				<script>
					window.addEventListener('load', function () {
						(function ($) {
							$(document).on('click', '.wfacp_copy_text', function () {
								var sibling = $(this).siblings('.wfacp_description');
								if (sibling.length > 0) {
									sibling.find('input').select();
									document.execCommand("copy");
									wfacp.show_data_save_model(wfacp_localization.global.shortcode_copy_message);
								}
							});
						})(jQuery);
					});

				</script>
				<?php
			}
		}

		public function append_checkout_post_list( $output ) {

			if ( empty( $this->checkout_post_list ) ) {
				global $wpdb;
				$page_data                = $wpdb->get_results( "SELECT posts.ID as post_id FROM `{$wpdb->prefix}postmeta` as meta INNER JOIN  `{$wpdb->prefix}posts` as posts on meta.post_id=posts.ID WHERE meta.meta_key = '_is_aero_checkout_page' and posts.post_status='publish' ORDER BY posts.post_title ASC", ARRAY_A );
				$this->checkout_post_list = $page_data;
			}
			if ( is_array( $this->checkout_post_list ) && count( $this->checkout_post_list ) > 0 ) {
				foreach ( $this->checkout_post_list as $v ) {
					$post     = get_post( $v['post_id'] );
					$output[] = array(
						'id'   => $post->ID,
						'name' => $post->post_title . ' - ' . __( 'Page', 'funnel-builder' ),
						'type' => 'page',
					);
				}
			}

			return $output;
		}

		public function arrange_billing_fields( $options ) {

			if ( ! isset( $_POST['address_order'] ) || empty( $_POST['address_order']['address'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX handler
				return $options;
			}
			$addressOrder = isset( $_POST['address_order'] ) ? map_deep( wp_unslash( $_POST['address_order'] ), 'sanitize_text_field' ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Filter callback for address field arrangement, nonce verification handled by WooCommerce checkout process

			$options = $this->arrange_order_of_address_fields( $options, $addressOrder );

			return $options;
		}

		public function arrange_shipping_fields( $options ) {
			if ( ! isset( $_POST['address_order'] ) || empty( $_POST['address_order']['shipping-address'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX handler
				return $options;
			}

			$addressOrder = isset( $_POST['address_order'] ) ? map_deep( wp_unslash( $_POST['address_order'] ), 'sanitize_text_field' ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Filter callback for address field arrangement, nonce verification handled by WooCommerce checkout process
			$options      = $this->arrange_order_of_address_fields( $options, $addressOrder, 'shipping' );

			return $options;
		}


		public function arrange_order_of_address_fields( $options, $addressOrder, $id = '', $replace_label = true ) {

			$temp_order_key = ( 'shipping' == $id ) ? 'shipping-address' : 'address';

			if ( count( $addressOrder ) > 0 && isset( $addressOrder[ $temp_order_key ] ) && count( $addressOrder[ $temp_order_key ] ) ) {
				$temp_options = array();
				$items        = $addressOrder[ $temp_order_key ];
				$same_data    = array();
				$same_key     = '';
				foreach ( $items as $item ) {
					$i_key = $item['key'];
					if ( ! isset( $options[ $i_key ] ) ) {
						continue;
					}
					if ( $i_key == 'same_as_billing' || $i_key == 'same_as_shipping' ) {
						$same_data[ $i_key ] = $item['status'];
						if ( true == $replace_label ) {
							$same_data[ $i_key . '_label' ] = trim( $item['label'] );
						} else {
							$same_data[ $i_key . '_label' ] = $options[ $i_key ][ $i_key . '_label' ];
						}
						if ( isset( $item['label_2'] ) ) {
							$same_data[ $i_key . '_label_2' ] = trim( $item['label_2'] );
						} else {
							$same_data[ $i_key . '_label_2' ] = $options[ $i_key ][ $i_key . '_label_2' ];
						}
						$same_key = $i_key;

						continue;
					}

					$temp_options[ $i_key ]                = $options[ $i_key ];
					$keys                                  = array_keys( $options[ $i_key ] );
					$status_key                            = $keys[0];
					$label_key                             = $keys[1];
					$temp_options[ $i_key ][ $status_key ] = $item['status'];
					if ( true == $replace_label ) {
						$temp_options[ $i_key ][ $label_key ] = trim( $item['label'] );
					}

					if ( isset( $keys[2] ) ) {
						$placeholder_key                            = $keys[2];
						$temp_options[ $i_key ][ $placeholder_key ] = trim( $item['placeholder'] );
					}

					if ( isset( $item['required'] ) ) {
						$temp_options[ $i_key ]['required'] = $item['required'];
					}
				}
				if ( '' !== $i_key ) {
					$temp_options = array_merge( array( $same_key => $same_data ), $temp_options );

				}

				$options = $temp_options;

			}

			return apply_filters( 'arrange_order_of_address_fields', $options );
		}

		public function add_back_button() {
			global $post;

			$wfacp_id = ( WFACP_Common::get_post_type_slug() === $post->post_type ) ? $post->ID : 0;
			if ( 0 === $wfacp_id ) {
				return;
			}
			$funnel_id = get_post_meta( $wfacp_id, '_bwf_in_funnel', true );
			if ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) {
				BWF_Admin_Breadcrumbs::register_ref( 'funnel_id', $funnel_id );
				$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs(
					add_query_arg(
						array(
							'page'      => 'bwf',
							'path'      => '/funnel-checkout/' . $wfacp_id . '/design',
							'funnel_id' => $funnel_id,
						),
						admin_url( 'admin.php' )
					)
				);
			} else {
				$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs(
					add_query_arg(
						array(
							'page'     => 'wfacp',
							'wfacp_id' => $wfacp_id,
							'section'  => 'design',
						),
						admin_url( 'admin.php' )
					)
				);
			}

			if ( use_block_editor_for_post_type( WFACP_Common::get_post_type_slug() ) ) {
				add_action( 'admin_footer', array( $this, 'render_back_to_aero_script_for_block_editor' ) );
			} else {
				?>
				<div id="wfacp-switch-mode">
					<a id="wfacp-back-button" class="button button-default button-large" href="<?php echo esc_url( $edit_link ); ?>">
						<?php esc_html_e( '&#8592; Back to Checkout Page', 'funnel-builder' ); ?>
					</a>
				</div>
				<script>
					window.addEventListener('load', function () {
						(function (window, wp) {
							var link = document.querySelector('a.components-button.edit-post-fullscreen-mode-close');
							if (link) {
                                link.setAttribute('href', "<?php echo htmlspecialchars_decode( esc_url( $edit_link ) );//phpcs:ignore ?>")
							}

						})(window, wp)
					});

				</script>
				<?php
			}
		}

		public function render_back_to_aero_script_for_block_editor() {
			global $post;

			$wfacp_type = WFACP_Common::get_post_type_slug();
			$wfacp_id   = ( $wfacp_type === $post->post_type ) ? $post->ID : 0;
			if ( $wfacp_id > 0 ) {
				$funnel_id = get_post_meta( $wfacp_id, '_bwf_in_funnel', true );
				if ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) {
					BWF_Admin_Breadcrumbs::register_ref( 'funnel_id', $funnel_id );
				}

				if ( ! empty( $funnel_id ) && abs( $funnel_id ) > 0 ) {
					BWF_Admin_Breadcrumbs::register_ref( 'funnel_id', $funnel_id );
					$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs(
						add_query_arg(
							array(
								'page'      => 'bwf',
								'path'      => '/funnel-checkout/' . $wfacp_id . '/design',
								'funnel_id' => $funnel_id,
							),
							admin_url( 'admin.php' )
						)
					);
				} else {
					$edit_link = BWF_Admin_Breadcrumbs::maybe_add_refs(
						add_query_arg(
							array(
								'page'     => 'wfacp',
								'wfacp_id' => $wfacp_id,
								'section'  => 'design',
							),
							admin_url( 'admin.php' )
						)
					);
				}

				?>

				<script id="wfacp-back-button-template" type="text/html">
					<div id="wfacp-switch-mode">
						<a id="wfacp-back-button" class="button button-default button-large" href="<?php echo esc_url( $edit_link ); ?>">
							<?php echo __( '&#8592; Back to Checkout Page', 'elementor' ); ?>
						</a>
					</div>

				</script>

				<script>
					window.addEventListener('load', function () {
						(function (window, wp) {

							const {Toolbar, ToolbarButton} = wp.components;

							var link_button = wp.element.createElement(
								ToolbarButton,
								{
									variant: 'secondary',
                                    href: "<?php echo htmlspecialchars_decode( esc_url( $edit_link ) );//phpcs:ignore ?>",
									id: 'wfacp-back-button',
									className: 'button is-secondary',
									style: {
										display: 'flex',
										height: '33px'
									},
									text: "<?php esc_html_e( '← Back to Checkout Page', 'funnel-builder' ); ?>",
									label: "<?php esc_html_e( 'Back to Checkout Page', 'funnel-builder' ); ?>"
								}
							);
							var linkWrapper = '<div id="wfacp-switch-mode"></div>';

							// check if gutenberg's editor root element is present.
							var editorEl = document.getElementById('editor');
							if (!editorEl) { // do nothing if there's no gutenberg root element on page.
								return;
							}
							wp.domReady(function () {
								var link = document.querySelector('body a.components-button.edit-post-fullscreen-mode-close');
								if (link) {
                                    link.setAttribute('href', "<?php echo htmlspecialchars_decode( esc_url( $edit_link ) );//phpcs:ignore ?>")
								}
							})

							wp.data.subscribe(function () {
								setTimeout(function () {
									if (!document.getElementById('wfacp-switch-mode')) {
										var toolbalEl = editorEl.querySelector('.editor-header__toolbar .edit-post-header-toolbar') ?? editorEl.querySelector('.edit-post-header__toolbar .edit-post-header-toolbar');
										if (toolbalEl instanceof HTMLElement) {
											toolbalEl.insertAdjacentHTML('beforeend', linkWrapper);
											setTimeout(() => {
												wp.element.render(link_button, document.getElementById('wfacp-switch-mode'));
											}, 1);
										}
									}
								}, 1)
							});
						})(window, wp)
					});

				</script>
				<?php
			}
		}

		function get_advanced_field() {
			if ( isset( $_REQUEST['post'] ) && absint( wp_unslash( $_REQUEST['post'] ) ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page identification
				$post_id = absint( wp_unslash( $_REQUEST['post'] ) );
				$order   = wc_get_order( $post_id );
				if ( ! $order instanceof WC_Order ) {
					return;
				}
				$wfacp_id = wfacp_get_order_meta( $order, '_wfacp_post_id' );
				if ( absint( $wfacp_id ) == 0 ) {
					return;
				}
				$cfields = WFACP_Common::get_page_custom_fields( $wfacp_id );

				if ( empty( $cfields['advanced'] ) ) {
					return;
				}
				$this->wfacp_custom_fields = $cfields['advanced'];
			}
		}

		function wfacp_protected_meta( $protected, $meta_key, $meta_type ) {
			if ( empty( $this->wfacp_custom_fields ) ) {
				return $protected;
			}
			if ( array_key_exists( $meta_key, $this->wfacp_custom_fields ) ) {
				return true;
			}

			return $protected;
		}

		public function add_pages_to_front_page_options( $pages, $args ) {
			global $pagenow;

			if ( 'options-reading.php' === $pagenow || ( 'customize.php' === $pagenow && is_array( $args ) && isset( $args['name'] ) && '_customize-dropdown-pages-page_on_front' === $args['name'] ) ) {
				$wfacp_pages = WFACP_Common::get_saved_pages();
				foreach ( $wfacp_pages as $page ) {
					$pages[] = get_post( $page['ID'] );
				}
			}

			return $pages;
		}




		/**
		 * On the wfacp_checkout post-edit screen (show_ui=true), hide the native WP editor / block
		 * editor / Page Attributes for pre_built checkouts, which are designed via the builder, not
		 * by editing post content. Runs independently of the (removed) page=wfacp builder screen.
		 */
		public function remove_page_attributes() {
			if ( empty( $this->wfacp_id ) || 0 === absint( $this->wfacp_id ) ) {
				return;
			}

			$page_design = WFACP_Common::get_page_design( $this->wfacp_id );
			if ( 'pre_built' !== $page_design['selected_type'] ) {
				return;
			}

			remove_post_type_support( WFACP_Common::get_post_type_slug(), 'editor' );
			add_filter( 'use_block_editor_for_post', array( $this, 'remove_block_editor' ), 10, 2 );
			$meta_box = array( 'pageparentdiv' );

			$meta_box = apply_filters( 'wfacp_remove_post_meta_boxes', $meta_box );
			if ( is_array( $meta_box ) && count( $meta_box ) > 0 ) {
				foreach ( $meta_box as $box ) {
					remove_meta_box( $box, WFACP_Common::get_post_type_slug(), 'side' );
				}
			}
		}

		public function remove_block_editor( $status, $post_type ) {
			if ( ! is_null( $post_type ) && $post_type->post_type == WFACP_Common::get_post_type_slug() ) {
				$status = false;
			}

			return $status;
		}

		/**
		 * to avoid unserialize of the current class
		 */
		public function __wakeup() {
			throw new ErrorException( 'WFACP_Core can`t converted to string' );
		}

		/**
		 * to avoid serialize of the current class
		 */
		public function __sleep() {

			throw new ErrorException( 'WFACP_Core can`t converted to string' );
		}

		/**
		 * To avoid cloning of current template class
		 */
		protected function __clone() {
		}
	}

	WFACP_admin::get_instance();
}
