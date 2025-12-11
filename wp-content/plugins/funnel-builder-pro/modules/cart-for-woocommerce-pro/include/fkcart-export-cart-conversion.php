<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class FKCART_Export_Cart_Conversion
 */
if ( class_exists( 'WFFN_Abstract_Exporter' ) && ! class_exists( 'FKCART_Export_Cart_Conversion' ) ) {
	#[AllowDynamicProperties]
	class FKCART_Export_Cart_Conversion extends WFFN_Abstract_Exporter {
		protected static $slug = 'cart_conversion';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_cart_conversion';

		public function get_title() {
			return __( 'Cart', 'funnel-builder-powerpack' );
		}

		public function action_hook() {
			return self::$ACTION_HOOK;
		}

		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return self|null
		 */
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
				'order_id'              => __( 'Order ID', 'funnel-builder-powerpack' ),
				'order_number'              => __( 'Order Number', 'funnel-builder-powerpack' ),
				'cart_upsell'           => __( 'Cart Upsell', 'funnel-builder-powerpack' ),
				'upsell_revenue'        => __( 'Cart Upsell Revenue', 'funnel-builder-powerpack' ),
				'special_addon'         => __( 'Special Addon', 'funnel-builder-powerpack' ),
				'special_addon_revenue' => __( 'Special Addon Revenue', 'funnel-builder-powerpack' ),
				'free_shipping_orders'  => __( 'Free Shipping Orders', 'funnel-builder-powerpack' ),
				'free_gift'             => __( 'Free Gift Rewards', 'funnel-builder-powerpack' ),
				'discount'              => __( 'Discount', 'funnel-builder-powerpack' ),
				'date'                  => __( 'Date', 'funnel-builder-powerpack' ),
			];
		}

		public function total_rows( $args ) {
			$args['total_count'] = true;
			$rest_endpoints      = FKCart\Pro\Rest\Conversions::get_instance();
			$get_conversions     = $rest_endpoints->get_cart_upsell_data( [ 'total_count' => 'yes', 'filters' => $args['filters'] ], true );

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
			$args = array(
				'offset'  => $this->current_pos,
				'limit'   => get_option( 'posts_per_page' ),
				'filters' => $this->export_meta['filters']
			);

			$rest_endpoints  = FKCart\Pro\Rest\Conversions::get_instance();
			$get_conversions = $rest_endpoints->get_cart_upsell_data( $args, true );

			if ( isset( $get_conversions['status'] ) && false === $get_conversions['status'] ) {
				WFFN_Core()->logger->log( "Something Went Wrong " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				WFFN_Core()->logger->log( "db error " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}

			$get_conversions = array_map( function ( $item ) {
				$return_data = [];
				foreach ( $this->get_columns() as $key => $column_name ) {
					$value               = is_array( $item[ $key ] ) ? implode( ',', $item[ $key ] ) : $item[ $key ];
					$return_data[ $key ] = $value ?? '';
				}

				return $return_data;
			}, $get_conversions['records'] );


			$this->data_populated_in_csv( '', $get_conversions );

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

	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( FKCART_Export_Cart_Conversion::get_instance() );
	}
}