<?php
if ( ! class_exists( 'WFOCU_Dynamic_Merge_Tags' ) ) {
	class WFOCU_Dynamic_Merge_Tags {

		public static $threshold_to_date = 30;
		protected static $_data_shortcode = array();

		public static function init() {
			add_shortcode( 'wfocu_order_data', array( __CLASS__, 'process_order_data' ) );
		}

		/**
		 * Maybe try and parse content to found the wfocu merge tags
		 * And converts them to the standard wp shortcode way
		 * So that it can be used as do_shortcode in future
		 *
		 * @param string $content
		 *
		 * @return mixed|string
		 */
		public static function maybe_parse_merge_tags( $content = '' ) {
			$get_all      = self::get_all_tags();
			$get_all_tags = wp_list_pluck( $get_all, 'tag' );
			//iterating over all the merge tags
			if ( $get_all_tags && is_array( $get_all_tags ) && count( $get_all_tags ) > 0 ) {
				foreach ( $get_all_tags as $tag ) {

					$matches = array();
					$re      = sprintf( '/\{{%s(.*?)\}}/', $tag );
					$str     = $content;

					//trying to find match w.r.t current tag
					preg_match_all( $re, $str, $matches );


					//if match found
					if ( $matches && is_array( $matches ) && count( $matches ) > 0 ) {


						//iterate over the found matches
						foreach ( $matches[0] as $exact_match ) {

							//preserve old match
							$old_match = $exact_match;


							$single = str_replace( "{{", "", $old_match );
							$single = str_replace( "}}", "", $single );

							if ( method_exists( __CLASS__, $single ) ) {
								$get_parsed_value = call_user_func( array( __CLASS__, $single ) );
								$content          = str_replace( $old_match, $get_parsed_value, $content );
							}
						}
					}
				}
			}

			return $content;
		}

		public static function get_all_tags() {


			$tags = array(
				array(
					'name' => __( "Customer ID", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_user_id'
				),
				array(
					'name' => __( "Customer First Name", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_first_name'
				),
				array(
					'name' => __( "Customer Last Name", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_last_name'
				),
				array(
					'name' => __( "Customer Full Name Uppercase", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_full_name_cap'
				),
				array(
					'name' => __( "Customer First Name Uppercase", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_first_name_cap'
				),
				array(
					'name' => __( "Customer Email", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_email'
				),
				array(
					'name' => __( "Customer Phone", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_phone'
				),
				array(
					'name' => __( "Order Number", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_no'
				),
				array(
					'name' => __( "Order Status", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_status'
				),
				array(
					'name' => __( "Order Date", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_date'
				),
				array(
					'name' => __( "Order Total", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_total'
				),
				array(
					'name' => __( "Order Items Count", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_itemscount'
				),
				array(
					'name' => __( "Order Shipping method", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_shipping_method'
				),
				array(
					'name' => __( "Order payment method", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_payment_method'
				),
				array(
					'name' => __( "Order Billing Country", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_billing_country'
				),
				array(
					'name' => __( "Order Shipping Country", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_shipping_country'
				),
				array(
					'name' => __( "Order Billing Address", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_billing_address'
				),
				array(
					'name' => __( "Order Shipping Address", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_shipping_address'
				),
				array(
					'name' => __( "Order Customer Note", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_customer_note'
				),
				array(
					'name' => __( "Users IP Address", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'order_ip'
				),
				array(
					'name' => __( "Customer Provided Note", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'customer_provided_note'
				),

				/**
				 * array(
				 * 'name' => __( "Subscription ID", 'woofunnels-upstroke-one-click-upsell' ),
				 * 'tag'  => 'subscription_id'
				 * ),
				 * array(
				 * 'name' => __( "Subscription Status", 'woofunnels-upstroke-one-click-upsell' ),
				 * 'tag'  => 'subscription_status'
				 * ),
				 * array(
				 * 'name' => __( "Subscription Start Date", 'woofunnels-upstroke-one-click-upsell' ),
				 * 'tag'  => 'subscription_start_date'
				 * ),
				 * array(
				 * 'name' => __( "Subscription Next Payment Date", 'woofunnels-upstroke-one-click-upsell' ),
				 * 'tag'  => 'subscription_next_payment_date'
				 * ),
				 * array(
				 * 'name' => __( "Subscription End Date", 'woofunnels-upstroke-one-click-upsell' ),
				 * 'tag'  => 'subscription_end_date'
				 * ),
				 **/


			);

			return $tags;
		}

		public static function get_all_other_tags() {

			$tags = array(
				array(
					'name' => __( "Countdown Timer", 'woofunnels-upstroke-one-click-upsell' ),
					'tag'  => 'countdown_timer style="square_fill" align="left"',
					'desc' => '2 attributes allowed<br/>style: square_fill | default & align: left | center | right',
				),
			);

			return $tags;
		}

		public static function customer_email() {
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' );
		}

		public static function customer_user_id() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return $order->get_user_id();
		}

		public static function customer_first_name_cap() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{{' . __FUNCTION__ . '}}';
			}

			return strtoupper( WFOCU_WC_Compatibility::get_billing_first_name( $order ) );
		}

		public static function customer_last_name_cap() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return strtoupper( WFOCU_WC_Compatibility::get_billing_last_name( $order ) );
		}

		public static function customer_full_name() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return ucwords( self::customer_first_name() . " " . self::customer_last_name() );
		}

		public static function customer_first_name() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {

				return ! empty( self::get_fallback( __FUNCTION__ ) ) ? self::get_fallback( __FUNCTION__ ) : '{{' . __FUNCTION__ . '}}';

			}

			return ucwords( WFOCU_WC_Compatibility::get_billing_first_name( $order ) );
		}

		public static function customer_last_name() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return ucwords( WFOCU_WC_Compatibility::get_billing_last_name( $order ) );
		}

		public static function customer_full_name_cap() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return strtoupper( self::customer_first_name() . " " . self::customer_last_name() );
		}

		public static function order_no() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return $order->get_order_number();
		}

		public static function order_status() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return wc_get_order_status_name( $order->get_status() );
		}

		public static function order_date() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WFOCU_WC_Compatibility::get_formatted_date( WFOCU_WC_Compatibility::get_order_date( $order ) );
		}

		public static function order_total() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return $order->get_formatted_order_total();
		}

		public static function order_itemscount() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return $order->get_item_count();
		}

		public static function order_payment_method() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WFOCU_WC_Compatibility::get_payment_method( $order );
		}

		public static function order_shipping_method() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return $order->get_shipping_method();
		}

		public static function order_billing_country() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WC()->countries->get_formatted_address( array( 'country' => WFOCU_WC_Compatibility::get_billing_country_from_order( $order ) ) );
		}

		public static function order_shipping_country() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WC()->countries->get_formatted_address( array( 'country' => WFOCU_WC_Compatibility::get_shipping_country_from_order( $order ) ) );
		}

		public static function order_billing_address() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return $order->get_formatted_billing_address();
		}

		public static function order_shipping_address() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return $order->get_formatted_shipping_address();
		}

		public static function order_customer_note() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			$comments = array();
			if ( is_array( $order->get_customer_order_notes() ) && count( $order->get_customer_order_notes() ) > 0 ) {


				foreach ( $order->get_customer_order_notes() as $comment ) {
					$comments[] = $comment->comment_content;
				}
			}

			return implode( "<br/>", $comments );
		}

		public static function customer_provided_note() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return nl2br( esc_html( WFOCU_WC_Compatibility::get_customer_note( $order ) ) );
		}

		public static function order_ip() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WFOCU_WC_Compatibility::get_customer_ip_address( $order );
		}

		public static function customer_phone() {

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WFOCU_WC_Compatibility::get_order_data( $order, 'billing_phone' );
		}

		public static function subscription_id() {
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				return '{' . __FUNCTION__ . '}';
			}

			$subscriptions = wcs_get_subscriptions_for_order( WFOCU_WC_Compatibility::get_order_id( $order ), array( 'order_type' => 'any' ) );

			if ( is_array( $subscriptions ) && count( $subscriptions ) > 0 ) {

				$get_all_ids = array_keys( $subscriptions );

				return $get_all_ids[0];
			}

			return '{' . __FUNCTION__ . '}';
		}

		public static function subscription_status() {
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				return '{' . __FUNCTION__ . '}';
			}

			$subscriptions = wcs_get_subscriptions_for_order( WFOCU_WC_Compatibility::get_order_id( $order ), array( 'order_type' => 'any' ) );

			if ( is_array( $subscriptions ) && count( $subscriptions ) > 0 ) {

				$subscription = current( $subscriptions );

				return wcs_get_subscription_status_name( $subscription->get_status() );
			}

			return '{' . __FUNCTION__ . '}';
		}

		public static function subscription_start_date() {
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				return '{' . __FUNCTION__ . '}';
			}

			$subscriptions = wcs_get_subscriptions_for_order( WFOCU_WC_Compatibility::get_order_id( $order ), array( 'order_type' => 'any' ) );

			if ( is_array( $subscriptions ) && count( $subscriptions ) > 0 ) {

				$subscription = current( $subscriptions );

				return $subscription->get_date_to_display( 'date_created' );
			}

			return '{' . __FUNCTION__ . '}';
		}

		public static function subscription_next_payment_date() {
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				return '{' . __FUNCTION__ . '}';
			}

			$subscriptions = wcs_get_subscriptions_for_order( WFOCU_WC_Compatibility::get_order_id( $order ), array( 'order_type' => 'any' ) );

			if ( is_array( $subscriptions ) && count( $subscriptions ) > 0 ) {

				$subscription = current( $subscriptions );

				return $subscription->get_date_to_display( 'next_payment' );
			}

			return '{' . __FUNCTION__ . '}';
		}

		public static function subscription_end_date() {
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				return __return_empty_string();
			}

			$subscriptions = wcs_get_subscriptions_for_order( WFOCU_WC_Compatibility::get_order_id( $order ), array( 'order_type' => 'any' ) );

			if ( is_array( $subscriptions ) && count( $subscriptions ) > 0 ) {

				$subscription = current( $subscriptions );


				$date_type = 'end_date';

				if ( 0 === $subscription->get_time( $date_type, 'gmt' ) ) {
					return __return_empty_string();
				} else {
					return sprintf( '<time class="%s" title="%s">%s</time>', esc_attr( 'end_date' ), esc_attr( gmdate( __( 'Y/m/d g:i:s A', 'woofunnels-upstroke-one-click-upsell' ), $subscription->get_time( $date_type, 'site' ) ) ), esc_html( $subscription->get_date_to_display( $date_type ) ) );
				}
			}

			return '{' . __FUNCTION__ . '}';
		}

		public static function subscription_last_payment_date() {
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				return '{' . __FUNCTION__ . '}';
			}

			$subscriptions = wcs_get_subscriptions_for_order( WFOCU_WC_Compatibility::get_order_id( $order ), array( 'order_type' => 'any' ) );

			if ( is_array( $subscriptions ) && count( $subscriptions ) > 0 ) {

				$subscription = current( $subscriptions );


				return $subscription->get_date_to_display( 'last_order_date_created' );
			}

			return '{' . __FUNCTION__ . '}';
		}

		public static function process_order_data( $attr ) {
			$attr = shortcode_atts( array(
				'key' => '',
			), $attr );

			$all_tags     = self::get_all_tags();
			$get_all_tags = wp_list_pluck( $all_tags, 'tag' );

			if ( empty( $attr['key'] ) ) {
				return '';
			}

			if ( ! in_array( $attr['key'], $get_all_tags, true ) ) {
				if ( ! empty( $attr['key'] ) ) {
					return call_user_func( array( 'WFOCU_Dynamic_Merge_Tags', 'order_custom_field' ), $attr['key'] );
				}

				return '{' . $attr['key'] . '}';
			}

			if ( ! is_callable( array( 'WFOCU_Dynamic_Merge_Tags', $attr['key'] ) ) ) {
				return '{' . $attr['key'] . '}';
			}


			return call_user_func( array( 'WFOCU_Dynamic_Merge_Tags', $attr['key'] ) );
		}


		public static function get_fallback( $key ) {


			$user = wp_get_current_user();
			if ( ! $user instanceof WP_User || empty( $user->ID ) ) {
				return;
			}
			switch ( $key ) {
				case 'customer_user_id':
					return $user->ID;
					break;
				case 'customer_first_name':
					$name    = $user->data->display_name;
					$explode = explode( ' ', $name );

					return ( is_array( $explode ) && count( $explode ) > 0 ) ? $explode[0] : '';
					break;
				case 'customer_last_name':
					$name    = $user->data->display_name;
					$explode = explode( ' ', $name );

					return ( is_array( $explode ) && count( $explode ) > 1 ) ? $explode[1] : '';
					break;
				case 'customer_user_id':
					return $user->ID;
					break;
				case 'customer_user_id':
					return $user->ID;
					break;
			}
		}

		public static function order_custom_field( $key ) {
			if ( empty( $key ) ) {
				return '';
			}

			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return '{' . __FUNCTION__ . '}';
			}

			return WFOCU_WC_Compatibility::get_order_data( $order, $key );
		}

	}

	WFOCU_Dynamic_Merge_Tags::init();
	function wfocu_order_data( $key = '' ) {
		if ( empty( $key ) ) {
			return '';

		}

		return call_user_func( array( 'WFOCU_Dynamic_Merge_Tags', $key ) );

	}
}