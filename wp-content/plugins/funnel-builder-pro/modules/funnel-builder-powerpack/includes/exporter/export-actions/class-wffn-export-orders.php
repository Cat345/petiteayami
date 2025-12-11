<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFFN_Export_Contact
 */
if ( ! class_exists( 'WFFN_Export_Orders' ) ) {
	class WFFN_Export_Orders extends WFFN_Abstract_Exporter {
		protected static $slug = 'orders';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_orders';

		public function get_title() {
			return __( 'Orders', 'funnel-builder-powerpack' );
		}

		public function action_hook() {
			return self::$ACTION_HOOK;
		}

		public function __construct() {
			parent::__construct();
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public static function get_slug() {
			return self::$slug;
		}

		public function get_columns() {
			return [
				'order_id'     => __( 'Order Id', 'funnel-builder-powerpack' ),
				'name'         => __( 'Name', 'funnel-builder-powerpack' ),
				'email'        => __( 'Email', 'funnel-builder-powerpack' ),
				'phone'        => __( 'Phone', 'funnel-builder-powerpack' ),
				'funnel_title' => __( 'Funnel', 'funnel-builder-powerpack' ),
				'date'         => __( 'Date', 'funnel-builder-powerpack' ),

				'total_spent'       => __( 'Total Spent', 'funnel-builder-powerpack' ),
				'checkout_name'     => __( 'Checkout Name', 'funnel-builder-powerpack' ),
				'checkout_total'    => __( 'Checkout Total', 'funnel-builder-powerpack' ),
				'checkout_products' => __( 'Checkout Product', 'funnel-builder-powerpack' ),
				'checkout_coupons'  => __( 'Checkout Coupons', 'funnel-builder-powerpack' ),

				'bump_accepted' => __( 'Bump Accepted', 'funnel-builder-powerpack' ),
				'bump_rejected' => __( 'Bump Rejected', 'funnel-builder-powerpack' ),
				'bump_products' => __( 'Bump Products', 'funnel-builder-powerpack' ),
				'bump_total'    => __( 'Bump Total', 'funnel-builder-powerpack' ),

				'offer_accepted' => __( 'Offer Accepted', 'funnel-builder-powerpack' ),
				'offer_rejected' => __( 'Offer Rejected', 'funnel-builder-powerpack' ),
				'offer_products' => __( 'Offer Products', 'funnel-builder-powerpack' ),
				'offer_total'    => __( 'Offer Total', 'funnel-builder-powerpack' ),

				'device'       => __( 'Device', 'funnel-builder-powerpack' ),
				'referrers'    => __( 'Referrers', 'funnel-builder-powerpack' ),
				'utm_campaign' => __( 'Utm Campaign', 'funnel-builder-powerpack' ),
				'utm_source'   => __( 'Utm Source', 'funnel-builder-powerpack' ),
				'utm_medium'   => __( 'Utm Medium', 'funnel-builder-powerpack' ),
				'utm_term'     => __( 'Utm Term', 'funnel-builder-powerpack' ),
				'convert_time' => __( 'Time to Convert', 'funnel-builder-powerpack' ),
			];

		}

		public function total_rows( $args ) {
			$args['total_count'] = true;
			$rest_endpoints      = WFFN_Funnel_Orders::get_instance();
			$get_conversions     = $rest_endpoints->get_orders( [ 'total_count' => 'yes', 'filters' => $args['filters'], 'funnel_id' => $args['funnel_id'] ?? 0 ], true );

			if ( isset( $get_conversions['status'] ) && false === $get_conversions['status'] ) {
				return false;
			}
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				return false;
			}
			if ( isset( $get_conversions['total_count'] ) ) {
				$count = $get_conversions['total_count'];
			}

			return absint( $count );
		}


		/**
		 * Export data to CSV function
		 */
		public function export_data() {
			$funnel_id = isset( $this->db_export_row['fid'] ) ? absint( $this->db_export_row['fid'] ) : 0;

			$args = array(
				'funnel_id' => $funnel_id,
				'offset'    => $this->current_pos,
				'limit'     => get_option( 'posts_per_page' ),
				'filters'   => $this->export_meta['filters']
			);

			$rest_endpoints  = WFFN_Funnel_Orders::get_instance();
			$get_conversions = $rest_endpoints->get_orders( $args, true );

			if ( isset( $get_conversions['status'] ) && false === $get_conversions['status'] ) {
				WFFN_Core()->logger->log( "Something Went Wrong " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				WFFN_Core()->logger->log( "db error " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}

			$get_conversions['records'] = array_map( function ( $item ) {
				$item = array_merge( $item, $this->get_order_data( $item ) );

				return $this->map_columns( $item );
			}, $get_conversions['records'] );

			unset( $get_conversions['total_count'] );
			$this->data_populated_in_csv( '', $get_conversions['records'] );

		}

		/* prepared and import data in csv
		*
		* @param $funnel_id
		* @param $data
		*
		* @return void
		*/
		public function data_populated_in_csv( $funnel_id, $data ) {
			/* prepared data for csv header get maximum columns data using funnel data **/
			$file  = fopen( WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file'], "a" );
			$count = 0;
			foreach ( $data as $subdata ) {
				fputcsv( $file, $subdata );
				$count ++;
			}
			fclose( $file );
			$this->current_pos = $this->current_pos + $count;
		}

		protected function map_columns( $data ) {
			$return_data = [];
			foreach ( $this->get_columns() as $key => $column_name ) {
				$return_data[ $key ] = $data[ $key ] ?? '';
			}

			return $return_data;
		}

		public function get_product_name_by_order_id( $order_ids, $type = 'checkout' ) {
			$result    = [];
			$order_ids = ! is_array( $order_ids ) ? explode( ',', $order_ids ) : $order_ids;
			if ( empty( $order_ids ) ) {
				return $result;
			}
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( abs( $order_id ) );
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$items = $order->get_items();
				foreach ( $items as $item ) {
					if ( 'checkout' !== $type ) {
						if ( 'yes' === $item->get_meta( $type ) && ! in_array( $item->get_name(), $result, true ) ) {
							$result[] = $item->get_name();
						}
					} else {
						$_bump_purchase     = $item->get_meta( '_bump_purchase' );
						$_upstroke_purchase = $item->get_meta( '_upstroke_purchase' );
						if ( ! in_array( $item->get_name(), $result, true ) && ( '' === $_bump_purchase ) && ( '' === $_upstroke_purchase ) ) {
							$result[] = $item->get_name();
						}
					}
				}
			}

			return $result;
		}

		/**
		 * @param $order_ids
		 *
		 * @return array
		 */
		public function get_coupon_code_by_order_id( $order_ids ) {
			$coupons   = [];
			$order_ids = ! is_array( $order_ids ) ? explode( ',', $order_ids ) : $order_ids;
			if ( empty( $order_ids ) ) {
				return $coupons;
			}
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( abs( $order_id ) );
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$coupons = array_merge( $coupons, $order->get_coupon_codes() );
			}

			return array_unique( $coupons );
		}


		/**
		 *Return Normalize Array of Given Array
		 *
		 * @param $final_data
		 *
		 * @return array|string[]
		 */
		public function map_item_keys( $final_data ) {
			return array_map( function ( $item ) {
				if ( is_array( $item ) ) {
					return implode( ',', array_unique( $item ) );
				}

				return trim( $item, ',' );

			}, $final_data );
		}


		public function get_order_data( $data ) {
			$results = [];

			if ( function_exists( 'wffn_conversion_tracking_migrator' ) && in_array( absint( wffn_conversion_tracking_migrator()->get_upgrade_state() ), [ 3, 4 ] ) ) {
				$results = [
					'checkout_name'     => '',
					'checkout_products' => '',
					'checkout_coupon'   => '',
					'bump_products'     => '',
					'offer_products'    => ''
				];

				if ( isset( $data['order_id'] ) && absint( $data['order_id'] ) > 0 ) {
					$results['checkout_products'] = array_unique( $this->get_product_name_by_order_id( $data['order_id'] ) );
					$results['checkout_coupon']   = array_unique( $this->get_coupon_code_by_order_id( $data['order_id'] ) );
					$results['bump_products']     = array_unique( $this->get_product_name_by_order_id( $data['order_id'], '_bump_purchase' ) );
					$results['offer_products']    = array_unique( $this->get_product_name_by_order_id( $data['order_id'], '_upstroke_purchase' ) );
				}

				if ( isset( $data['step_id'] ) ) {
					$results['checkout_name'] = $this->get_step_name( $data['step_id'] );
				}

				if ( isset( $data['bump_accepted'] ) ) {
					$results['bump_accepted'] = $this->get_step_name( $data['bump_accepted'] );
				}

				if ( isset( $data['bump_rejected'] ) ) {
					$results['bump_rejected'] = $this->get_step_name( $data['bump_rejected'] );
				}

				if ( isset( $data['offer_accepted'] ) ) {
					$results['offer_accepted'] = $this->get_step_name( $data['offer_accepted'] );
				}

				if ( isset( $data['offer_rejected'] ) ) {
					$results['offer_rejected'] = $this->get_step_name( $data['offer_rejected'] );
				}

				$results = $this->map_item_keys( $results );

			} else {
				/*
				 * if new ui
				 */

				$order_id = $data['order_id'];

				if ( class_exists( 'WFACP_Contacts_Analytics' ) ) {
					$aero_obj         = WFACP_Contacts_Analytics::get_instance();
					$checkout_records = $aero_obj->export_aero_data_order_id( $order_id );
					if ( ! empty( $checkout_records ) ) {
						$results = array_merge( $results, $this->prepare_checkout_data( $checkout_records ) );
					}
				}

				if ( class_exists( 'WFOB_Contacts_Analytics' ) ) {
					$bump_obj     = WFOB_Contacts_Analytics::get_instance();
					$bump_records = $bump_obj->get_bumps_by_order_id( $order_id );

					if ( ! empty( $bump_records ) ) {
						$results = array_merge( $results, $this->prepare_bump_data( $bump_records ) );
					}
				}

				if ( class_exists( 'WFOCU_Contacts_Analytics' ) ) {
					$upsell_obj     = WFOCU_Contacts_Analytics::get_instance();
					$upsell_records = $upsell_obj->export_upsell_offer_by_order_id( $order_id );
					if ( ! empty( $upsell_records ) ) {
						$results = array_merge( $results, $this->prepare_upsell_offer_data( $upsell_records ) );
					}
				}
			}

			return $results;
		}

		public function get_step_name( $ids ) {
			$get_name = '';
			$ids      = is_array( $ids ) ? implode( ',', $ids ) : str_replace( [ '"', '[', ']' ], '', $ids );

			if ( empty( $ids ) ) {
				return $get_name;
			}

			global $wpdb;

			$result = $wpdb->get_row( $wpdb->prepare( "SELECT GROUP_CONCAT( post_title ) as 'post_title' FROM {$wpdb->prefix}posts WHERE 1 = 1 AND ID IN (%1s)", $ids ), ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! empty( $result['post_title'] ) ) {
				$get_name = $result['post_title'];
			}

			return $get_name;
		}

		/**
		 * Prepare Global Export Bump Data
		 *
		 * @param $bump_data
		 *
		 * @return array
		 *
		 */
		public function prepare_bump_data( $bump_data ) {
			$final_data = [ 'bump_accepted' => [], 'bump_rejected' => [], 'bump_total' => 0 ];
			$products   = [];
			foreach ( $bump_data as $data ) {
				if ( empty( $data['bump_name'] ) ) {
					continue;
				}
				$final_data['bump_name'][] = $data['bump_name'];
				if ( 'Yes' == $data['bump_converted'] ) {
					$final_data['bump_accepted'][] = $data['bump_name'];
					$final_data['bump_total']      += $data['bump_total'];
				}
				if ( 'No' == $data['bump_converted'] ) {
					$final_data['bump_rejected'][] = $data['bump_name'];
				}

				$products = array_merge( $products, $this->get_product_name_by_order_id( $data['bump_order_id'], '_bump_purchase' ) );
			}
			$final_data['bump_products'] = array_unique( $products );
			unset( $products, $bump_data );

			return $this->map_item_keys( $final_data );
		}

		/**
		 * Prepare Global export checkout data
		 *
		 * @param $checkout_data
		 *
		 * @return array|string[]
		 *
		 */
		public function prepare_checkout_data( $checkout_data ) {
			$final_data = [ 'checkout_name' => [], 'checkout_order_id' => [], 'checkout_total' => 0 ];
			$products   = [];
			$coupons    = [];
			foreach ( $checkout_data as $data ) {
				if ( empty( $data['checkout_name'] ) ) {
					continue;
				}
				$final_data['checkout_total']      += $data['checkout_total'];
				$final_data['checkout_order_id'][] = $data['checkout_order_id'];
				$final_data['checkout_name'][]     = $data['checkout_name'];
				$products                          = array_merge( $products, $this->get_product_name_by_order_id( $data['checkout_order_id'] ) );
				$coupons                           = array_merge( $coupons, $this->get_coupon_code_by_order_id( $data['checkout_order_id'] ) );

			}
			$final_data['checkout_products'] = $products;
			$final_data['checkout_coupon']   = $coupons;
			unset( $coupons, $products, $checkout_data );

			return $this->map_item_keys( $final_data );
		}

		/**
		 * Prepare Global export Upsell data
		 *
		 * @param $upsell_data
		 *
		 * @return array
		 *
		 */
		public function prepare_upsell_offer_data( $upsell_data ) {
			$final_data = [ 'offer_accepted' => [], 'offer_rejected' => [], 'offer_total' => 0 ];
			$products   = [];
			foreach ( $upsell_data as $data ) {
				if ( empty( $data['offer_name'] ) ) {
					continue;
				}
				$final_data['offer_name'][] = $data['offer_name'];

				if ( 'Yes' == $data['offer_converted'] ) {
					$final_data['offer_accepted'][] = $data['offer_name'];
					$final_data['offer_total']      += $data['offer_total'];
				}
				if ( 'No' == $data['offer_converted'] ) {
					$final_data['offer_rejected'][] = $data['offer_name'];
				}

				$products = array_merge( $products, $this->get_product_name_by_order_id( $data['order_id'], '_upstroke_purchase' ) );

			}

			$final_data['offer_products'] = array_unique( $products );
			unset( $products, $upsell_data );

			return $this->map_item_keys( $final_data );
		}

	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Orders::get_instance() );
	}
}