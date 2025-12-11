<?php
if ( ! class_exists( 'WFOCU_DB_Track' ) ) {
	class WFOCU_DB_Track extends WFOCU_DB_Base {

		const FUNNEL_INIT_ACTION_ID = 1;
		const FUNNEL_ENDED_ACTION_ID = 8;
		const OFFER_VIEWED_ACTION_ID = 2;
		const OFFER_ACCEPTED_ACTION_ID = 4;
		const OFFER_REJECTED_ACTION_ID = 6;
		const OFFER_EXPIRED_ACTION_ID = 7;
		const OFFER_PAYMENT_FAILED_ACTION_ID = 9;
		const OFFER_SKIPPED_ACTION_ID = 10;
		const OFFER_REFUNDED_ACTION_ID = 12;
		const PRODUCT_VIEWED_ACTION_ID = 3;
		const PRODUCT_ACCEPTED_ACTION_ID = 5;
		/**
		 * Primary fields
		 *
		 * @since 1.3.5
		 *
		 * @var array
		 */
		protected $primary_fields = array(
			'sess_id' => array(
				'%s',
				'strip_tags',
			),

			'object_type'    => array(
				'%s',
				'strip_tags',
			),
			'object_id'      => array(
				'%s',
				'strip_tags',
			),
			'action_type_id' => array(
				'%s',
				'strip_tags',
			),
			'value'          => array(
				'%s',
				'strip_tags',
			),
			'timestamp'      => array(
				'%s',
				'strip_tags',
			),

		);

		/**
		 * Meta fields
		 *
		 * @since 1.3.5
		 *
		 * @var array
		 */
		protected $meta_fields = array(
			'event_id'   => array(
				'%d',
				'absint',
			),
			'meta_key'   => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'%s',
				'strip_tags',
			),
			'meta_value' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'%s',
				'strip_tags',
			),
		);

		/**
		 * Meta keys
		 *
		 * @since 1.3.5
		 *
		 * @var array
		 */
		protected $meta_keys = array(
			'_funnel_id'           => array(
				'%s',
				'strip_tags',
			),
			'_offer_type'          => array(
				'%s',
				'strip_tags',
			),
			'_offer_id'            => array(
				'%s',
				'strip_tags',
			),
			'_qty'                 => array(
				'%s',
				'esc_url_raw',
			),
			'_new_order'           => array(
				'%s',
				'esc_url_raw',
			),
			'_transaction_id'      => array(
				'%s',
				'esc_url_raw',
			),
			'_invalidation_reason' => array(
				'%d',
				'esc_url_raw',
			),
			'_items_added'         => array(
				'%s',
				'strip_tags',
			),
			'_total_shipping'      => array(
				'%s',
				'strip_tags',
			),
			'_shipping_batch_id'   => array(
				'%s',
				'strip_tags',
			),

		);

		/**
		 * Name of primary index
		 *
		 * @since 1.3.5
		 *
		 * @var string
		 */
		protected $index = 'event_id';

		/**
		 * Name of table
		 *
		 * @since 1.3.5
		 *
		 * @var string
		 */
		protected $table_name = 'wfocu_event';

		/**
		 * Time to record against events
		 */
		protected $time_to_record;

		/**
		 * Class instance
		 *
		 * @since 1.3.5
		 *
		 * @var WFOCU_DB_Track
		 */
		private static $instance;

		/**
		 * Setup the actions to track upon
		 *
		 * @since 1.3.5
		 */
		protected function __construct() {

			add_action( 'wfocu_funnel_init_event', array( $this, 'funnel_start' ), 999, 3 );
			add_action( 'wfocu_offer_viewed_event', array( $this, 'offer_viewed' ), 999, 6 );
			add_action( 'wfocu_offer_skipped_event', array( $this, 'offer_skipped' ), 999, 7 );
			add_action( 'wfocu_offer_accepted_event', array( $this, 'offer_accepted' ), 999 );
			add_action( 'wfocu_offer_accepted_event', array( $this, 'add_to_order_meta' ), 990 );
			add_action( 'wfocu_product_accepted_event', array( $this, 'product_accepted' ), 999 );
			add_action( 'wfocu_offer_rejected_event', array( $this, 'offer_rejected' ), 999 );
			add_action( 'wfocu_offer_expired_event', array( $this, 'offer_expired' ), 999 );
			add_action( 'wfocu_funnel_ended_event', array( $this, 'funnel_ended' ), 999, 3 );
			add_action( 'wfocu_offer_payment_failed_event', array( $this, 'offer_payment_failed' ), 999 );
			add_action( 'wfocu_offer_refunded_event', array( $this, 'offer_refunded' ), 999, 6 );
			add_action( 'wfocu_offer_refunded_event', array( $this, 'analytics_refunded_process' ), 999, 7 );
			add_action( 'woocommerce_order_fully_refunded', array( $this, 'fully_refunded_process' ), 10, 2 );
			add_action( 'woocommerce_order_partially_refunded', array( $this, 'partially_refunded_process' ), 9, 2 );

			$this->time_to_record = current_time( 'mysql' );
			/** date( 'Y-m-d H:i:s', ( strtotime( '-14 day', strtotime( current_time( 'mysql' ) ) ) ) );*/

		}


		/**
		 * Get class instance
		 *
		 * @return \WFOCU_DB_Track
		 * @since 1.3.5
		 *
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;

		}


		public function funnel_start( $funnel_id, $order_id, $email ) {

			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'funnel',
				'object_id'      => $funnel_id,
				'action_type_id' => $this->get_action_type( 'funnel', 'start' ),
				'timestamp'      => $this->time_to_record,
			) );
		}

		public function offer_viewed( $offer_id, $order_id, $funnel_id, $get_type_of_offer, $get_type_index_of_offer, $email ) {

			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'offer',
				'object_id'      => $offer_id,
				'action_type_id' => $this->get_action_type( 'offer', 'viewed' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'  => $funnel_id,
					'_offer_type' => $this->get_upsell_type_index( $get_type_of_offer ) . ':' . $get_type_index_of_offer,
				),
			) );

		}

		public function offer_skipped( $offer_id, $order_id, $funnel_id, $get_type_of_offer, $get_type_index_of_offer, $email, $invalidation_reason ) {

			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'offer',
				'object_id'      => $offer_id,
				'action_type_id' => $this->get_action_type( 'offer', 'skipped' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'           => $funnel_id,
					'_offer_type'          => $this->get_upsell_type_index( $get_type_of_offer ) . ':' . $get_type_index_of_offer,
					'_invalidation_reason' => $invalidation_reason,
				),
			) );

		}

		public function create_session_id( $order_id, $email ) {

		}


		public function offer_payment_failed( $args ) {

			$args = wp_parse_args( $args, array(
				'offer_id'          => '',
				'product_id'        => '',
				'product_title'     => '',
				'value'             => '',
				'funnel_unique_id'  => '',
				'offer_product_key' => '',
				'offer_type'        => '',
				'offer_index'       => '',
				'email'             => '',
			) );
			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'offer',
				'object_id'      => $args['offer_id'],
				'value'          => $args['value'],
				'action_type_id' => $this->get_action_type( 'offer', 'payment_failed' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'  => $args['funnel_id'],
					'_offer_type' => $this->get_upsell_type_index( $args['offer_type'] ) . ':' . $args['offer_index'],

				),
			) );

		}

		public function product_viewed( $args = array() ) {

			$args = wp_parse_args( $args, array(
				'offer_id'          => '',
				'product_id'        => '',
				'product_title'     => '',
				'funnel_unique_id'  => '',
				'offer_product_key' => '',
				'offer_type'        => '',
				'offer_index'       => '',
				'email'             => '',
			) );
			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'product',
				'object_id'      => $args['product_id'],
				'action_type_id' => $this->get_action_type( 'product', 'viewed' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'  => $args['funnel_id'],
					'_offer_id'   => $args['offer_id'],
					'_offer_type' => $this->get_upsell_type_index( $args['offer_type'] ) . ':' . $args['offer_index'],

				),
			) );

		}

		public function offer_rejected( $args = array() ) {

			$args = wp_parse_args( $args, array(
				'order_id'         => '',
				'funnel_id'        => '',
				'offer_id'         => '',
				'funnel_unique_id' => '',
				'offer_type'       => '',
				'offer_index'      => '',
			) );
			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'offer',
				'object_id'      => $args['offer_id'],
				'action_type_id' => $this->get_action_type( 'offer', 'rejected' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'  => $args['funnel_id'],
					'_offer_type' => $this->get_upsell_type_index( $args['offer_type'] ) . ':' . $args['offer_index'],

				),
			) );

		}

		public function add_to_order_meta( $args ) {
			global $wpdb;
			$get_order = WFOCU_Core()->data->get_parent_order();

			if ( $get_order instanceof WC_Order ) {
				$upsell_amount          = $get_order->get_meta( '_wfocu_upsell_amount', true );
				$upsell_amount_currency = $get_order->get_meta( '_wfocu_upsell_amount_currency', true );

				if ( empty( $upsell_amount ) ) {
					$upsell_amount = 0.00;
				}
				if ( empty( $upsell_amount_currency ) ) {
					$upsell_amount_currency = 0.00;
				}

				$total_sum          = floatval( $upsell_amount ) + floatval( $args['value'] );
				$total_sum_currency = floatval( $upsell_amount_currency ) + floatval( $args['value_in_currency'] );

				if ( 0 < $total_sum ) {
					$get_order->update_meta_data( '_wfocu_upsell_amount', $total_sum );
					$get_order->save_meta_data();
				}
				if ( 0 < $total_sum_currency ) {
					$get_order->update_meta_data( '_wfocu_upsell_amount_currency', $total_sum_currency );
					$get_order->save_meta_data();
				}
				$query = $wpdb->prepare( 'UPDATE`' . $wpdb->prefix . 'wfocu_session` SET  `total` = %s WHERE `order_id` = %s', $total_sum, WFOCU_WC_Compatibility::get_order_id( $get_order ) );
				$wpdb->query( $query );  //phpcs:ignore
				// db call ok; no-cache ok; unprepared SQL ok.
			}
		}


		public function offer_accepted( $args = array() ) {

			$args             = wp_parse_args( $args, array(
				'funnel_id'        => '',
				'offer_id'         => '',
				'funnel_unique_id' => '',
				'value'            => '',
				'offer_type'       => '',
				'offer_index'      => '',
				'payment_data'     => '',
				'transaction_id'   => '',
				'new_order'        => '',
			) );
			$get_event_row_id = $this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'offer',
				'value'          => $args['value'],
				'object_id'      => $args['offer_id'],
				'action_type_id' => $this->get_action_type( 'offer', 'accepted' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'       => $args['funnel_id'],
					'_offer_type'      => $this->get_upsell_type_index( $args['offer_type'] ) . ':' . $args['offer_index'],
					'_transaction_id'  => $args['transaction_id'],
					'_new_order'       => $args['new_order'],
					'_total_charged'   => $args['payment_data']['_total_charged'],
					'_total_tax'       => $args['payment_data']['_total_tax'],
					'_total_shipping'  => $args['payment_data']['_total_shipping'],
					'_total_items'     => $args['payment_data']['_total_items'],
					'_currency'        => $args['payment_data']['_currency'],
					'_is_diff_charged' => $args['payment_data']['_is_diff_charged'],
					'_items_added'     => wp_json_encode( $args['items_added'] ),

				),
			) );
			do_action( 'wfocu_db_event_row_created_' . self::OFFER_ACCEPTED_ACTION_ID, $get_event_row_id );

		}


		public function product_accepted( $args = array() ) {

			$args = wp_parse_args( $args, array(

				'funnel_id'         => '',
				'offer_id'          => '',
				'product_id'        => '',
				'product_title'     => '',
				'value'             => '',
				'funnel_unique_id'  => '',
				'offer_product_key' => '',
				'offer_type'        => '',
				'offer_index'       => '',

			) );
			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'product',
				'value'          => $args['value'],
				'object_id'      => $args['product_id'],
				'action_type_id' => $this->get_action_type( 'product', 'accepted' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'  => $args['funnel_id'],
					'_offer_id'   => $args['offer_id'],
					'_offer_type' => $this->get_upsell_type_index( $args['offer_type'] ) . ':' . $args['offer_index'],
					'_qty'        => $args['qty'],
					'_value'      => $args['raw_value'],

				),
			) );

		}


		public function offer_expired( $args = array() ) {

			if ( isset( $args['next_action'] ) && 'redirect_to_next' === $args['next_action'] ) {


				$args = wp_parse_args( $args, array(
					'funnel_id'        => '',
					'offer_id'         => '',
					'funnel_unique_id' => '',
					'offer_type'       => '',
					'offer_index'      => '',
				) );
				$this->create( array(
					'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
					'order_id'       => $args['order_id'],
					'useremail'      => $args['email'],
					'object_type'    => 'offer',
					'object_id'      => $args['offer_id'],
					'action_type_id' => $this->get_action_type( 'offer', 'expired' ),
					'timestamp'      => $this->time_to_record,
					'meta'           => array(
						'_funnel_id'  => $args['funnel_id'],
						'_offer_type' => $this->get_upsell_type_index( $args['offer_type'] ) . ':' . $args['offer_index'],

					),
				) );
			}
		}

		public function funnel_ended( $funnel_id, $order_id, $email ) {

			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id(),
				'object_type'    => 'funnel',
				'object_id'      => $funnel_id,
				'action_type_id' => $this->get_action_type( 'funnel', 'ended' ),
				'timestamp'      => $this->time_to_record,
			) );

		}

		public function offer_refunded( $order_id, $funnel_id, $offer_id, $refund_txn_id, $txn_id, $amount ) {

			$this->create( array(
				'sess_id'        => WFOCU_Core()->session_db->get_session_id_by_order_id( $order_id ),
				'object_type'    => 'offer',
				'object_id'      => $offer_id,
				'value'          => $amount,
				'action_type_id' => $this->get_action_type( 'offer', 'refunded' ),
				'timestamp'      => $this->time_to_record,
				'meta'           => array(
					'_funnel_id'     => $funnel_id,
					'_refund_txn_id' => $refund_txn_id,
				),
			) );

		}

		/**
		 * Get tracking data by event name
		 *
		 * @param string $event Event name
		 * @param bool $return_forms Optional. If true, the default, form IDs are returned. If false, event meta data is returned.
		 *
		 * @return array|null
		 * @since 1.4.5
		 *
		 */
		public function by_event( $event, $return_forms = true ) {
			$metas = $this->query_meta( 'event', $event );
			if ( ! empty( $metas ) ) {

				if ( $return_forms ) {
					$event_ids = array_unique( wp_list_pluck( $metas, 'event_id' ) );
					$forms     = $this->form_ids_for_events( $event_ids );
					if ( ! empty( $forms ) ) {
						return wp_list_pluck( $forms, 'form_id' );
					}
				} else {
					return $metas;
				}
			}

			return array();

		}

		/**
		 * Get form IDs or an array of event IDs
		 *
		 * @param array $event_ids Event IDs to find form IDs for
		 *
		 * @return array|null
		 * @since 1.4.5
		 *
		 */
		protected function form_ids_for_events( $event_ids ) {
			global $wpdb;
			$table = $this->get_table_name( false );

			return $wpdb->get_results( $wpdb->prepare( "SELECT `form_id` FROM {$table} WHERE `ID` IN( '%s' )", implode( ',', $event_ids ) ), ARRAY_A ); //phpcs:ignore
			//db call ok; no-cache ok; unprepared
			// SQL ok.
		}

		public function get_action_config() {

			$action_config = apply_filters( 'wfocu_db_action_config', array(
				'funnel'  => array(
					'start' => 1,
					'ended' => 8,
				),
				'offer'   => array(
					'viewed'         => 2,
					'accepted'       => 4,
					'rejected'       => 6,
					'expired'        => 7,
					'payment_failed' => 9,
					'skipped'        => 10,
					'refunded'       => 12,
				),
				'product' => array(
					'viewed'   => 3,
					'accepted' => 5,
				),
			) );

			return $action_config;
		}

		public function get_action_type( $object_base, $action_slug ) {

			$action_config = $this->get_action_config();

			return isset( $action_config[ $object_base ][ $action_slug ] ) ? $action_config[ $object_base ][ $action_slug ] : '';

		}

		public function action_nice_name( $action_id ) {
			$action_config = $this->get_action_config();

			foreach ( $action_config as $object => $actions ) {

				foreach ( $actions as $action => $ids ) {

					if ( $action_id === $ids ) {
						return $object . '-' . $action;
					}
				}
			}

			return 'unknown';
		}

		public function _upsell_type() {
			$action_config = array(
				'upsell'   => 1,
				'downsell' => 2,
			);

			return $action_config;
		}

		public function get_upsell_type_nice_name( $action_id = 1 ) {
			$action_config = $this->_upsell_type();

			$get_key = array_search( $action_id, $action_config, true );

			return $get_key;
		}

		public function get_upsell_type_index( $type = 'upsell' ) {
			$action_config = $this->_upsell_type();

			return $action_config[ $type ];
		}

		public function delete( $sess_id ) {

			global $wpdb;

			$all_event_ids = WFOCU_Core()->track->query_results( array(
				'data'         => array(
					'id' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'upsells',
					),
				),
				'where'        => array(

					array(
						'key'      => 'events.sess_id',
						'value'    => $sess_id,
						'operator' => '=',
					),
				),
				'query_type'   => 'get_col',
				'session_join' => true,
				'debug'        => false,
			) );

			if ( count( $all_event_ids ) > 0 ) {
				$wpdb->query( "DELETE FROM `" . $this->get_table_name( true ) . "` WHERE event_id IN( '" . implode( "','", $all_event_ids ) . "' )" ); //db call ok; no-cache ok; phpcs:ignore unprepared SQL ok.
			}

			$wpdb->delete( $this->get_table_name(), array( 'sess_id' => $sess_id ) ); //db call ok; no-cache ok; WPCS: unprepared SQL ok.


		}

		/**
		 * @param $order_id
		 * @param $funnel_id
		 * @param $offer_id
		 * @param $refund_txn_id
		 * @param $txn_id
		 * @param $amount
		 * @param $event_id
		 * Refund process when upsell in main order for analytics
		 */
		public function analytics_refunded_process( $order_id, $funnel_id, $offer_id, $refund_txn_id, $txn_id, $amount, $event_id ) {
			$this->update_analytics_after_refund( $event_id, $amount );
		}

		/**
		 * @param $order_id
		 *
		 * Full refunded process for analytics
		 */
		public function fully_refunded_process( $order_id ) {
			$event_id = WFOCU_Core()->track->query_results( array(
				'data'       => array(),
				'where_meta' => array(
					array(
						'type'       => 'meta',
						'meta_key'   => '_new_order',
						'meta_value' => $order_id,
						'operator'   => '=',
					),
				),
				'order_by'   => 'events.id DESC',
				'query_type' => 'get_var',
			) );

			if ( absint( $event_id ) > 0 ) {
				$this->update_analytics_after_refund( $event_id, 0, true );
			}
		}

		/**
		 * @param $order_id
		 * @param $refund_id
		 * Partially refunded process for analytics
		 */
		public function partially_refunded_process( $order_id, $refund_id ) {
			$order         = wc_get_order( $order_id );
			$refund        = wc_get_order( $refund_id );
			$refund_amount = 0;

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( ! $refund instanceof WC_Order_Refund ) {
				return;
			}

			/**
			 * Check whether the order in refund process is a new order by upstroke?
			 */
			$event_id = WFOCU_Core()->track->query_results( array(
				'data'       => array(),
				'where_meta' => array(
					array(
						'type'       => 'meta',
						'meta_key'   => '_new_order',
						'meta_value' => $order_id,
						'operator'   => '=',
					),
				),
				'order_by'   => 'events.id DESC',
				'query_type' => 'get_var',
			) );

			/**
			 * IF not event ID then check if the partial refund is from the upstroke items in batching
			 */
			if ( absint( $event_id ) === 0 && 0 < count( $refund->get_items() ) ) {
				foreach ( $refund->get_items() as $refund_item ) {
					$item_id            = $refund_item->get_meta( '_refunded_item_id', true );
					$item               = $order->get_item( $item_id );
					$_upstroke_purchase = $item->get_meta( '_upstroke_purchase' );

					/**
					 * Check if its from the upstroke purchase
					 */
					if ( '' !== $_upstroke_purchase ) {
						$refund_amount += abs( $refund_item->get_total() );

						$inline_event_id = WFOCU_Core()->track->query_results( array(
							'data'       => array(),
							'where_meta' => array(
								array(
									'type'       => 'meta',
									'meta_key'   => '_items_added',
									'meta_value' => '%' . $item->get_id() . '%',
									'operator'   => 'LIKE',
								),
							),
							'order_by'   => 'events.id DESC',
							'query_type' => 'get_var',
						) );
						/**
						 * If we get the event ID for the refund attempted then reduce amt from that event
						 */
						if ( absint( $inline_event_id ) > 0 && $refund_amount > 0 ) {
							$this->update_analytics_after_refund( $inline_event_id, $refund_amount );
						}


					}
				}
			}

			/**
			 * in this case we are sure we have a new order refund to process
			 */
			if ( absint( $event_id ) > 0 ) {
				$refund_amount = $refund->get_total();
				$this->update_analytics_after_refund( $event_id, $refund_amount );
			}
		}

		public function update_analytics_after_refund( $event_id, $refund_amount, $full_refund = false ) {
			global $wpdb;
			$event = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "wfocu_event WHERE id = " . $event_id . " AND object_type = 'offer'" );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! empty( $event ) ) {
				$event_refund = ( ( $event->value ) <= $refund_amount ) ? 0 : $event->value - $refund_amount;
				$sess_total   = $wpdb->get_var( "SELECT total FROM " . $wpdb->prefix . "wfocu_session WHERE id = " . $event->sess_id ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sess_refund  = ( $sess_total <= $refund_amount ) ? 0 : $sess_total - $refund_amount;

				if ( $full_refund ) {
					$event_refund = 0;
					$sess_refund  = ( $sess_total <= $event->value ) ? 0 : $sess_total - $event->value;
				}

				$wpdb->update( $wpdb->prefix . "wfocu_event", [ 'value' => $event_refund ], [ 'id' => $event_id, 'object_type' => 'offer' ] );
				$wpdb->update( $wpdb->prefix . "wfocu_session", [ 'total' => $sess_refund ], [ 'id' => $event->sess_id ] );
			}

		}

	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'track', 'WFOCU_DB_Track' );
	}
}