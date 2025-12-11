<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Upstroke_Timeline' ) ) {
	class WFOCU_Upstroke_Timeline {


		public static $instance;

		/**
		 * Generating instance
		 *
		 * @return WFOCU_Upstroke_Timeline
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public static function get_edit_screen_order_id() {
			global $post;

			return $_GET['id'] ?? ( $post instanceof WP_Post ? $post->ID : 0 );
		}

		/**
		 * Metabox callback function
		 * Adding upstroke metabox to show upstroke activity timeline in sidebar of order edit page
		 */
		public function wfocu_register_upstroke_reports_meta_boxes() {

			$funnel_id = WFOCU_Common::get_order_meta( wc_get_order( self::get_edit_screen_order_id() ), '_wfocu_funnel_id', false );

			if ( $funnel_id < 1 ) {
				return;
			}

			add_meta_box( 'wfocu_upstroke_reports_metabox', __( 'FunnelKit Timeline', 'woofunnels-upstroke-power-pack' ), array(
				$this,
				'wfocu_upstroke_reports_metabox_callback',
			), function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order', 'side' );

		}

		/**
		 * Displaying funnels reports on order edit page
		 */
		public function wfocu_upstroke_reports_metabox_callback() {
			$order_id = self::get_edit_screen_order_id();

			$upstroke_data = WFOCU_Core()->track->query_results( array(
				'data'         => array(
					'object_type'    => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'object_type',
					),
					'id'             => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'event_id',
					),
					'object_id'      => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'object_id',
					),
					'action_type_id' => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'action_type_id',
					),
					'timestamp'      => array(
						'type'     => 'col',
						'function' => '',
						'name'     => 'trigger_date',
					),
				),
				'where'        => array(
					array(
						'key'      => 'session.order_id',
						'value'    => $order_id,
						'operator' => '=',
					),
				),
				'query_type'   => 'get_results',
				'session_join' => true,
				'nocache'      => true,
			) );

			$template = apply_filters( 'wfocu_order_timeline_template', array(
				1  => __( 'Funnel ({{funnel_name}}) initiated.', 'woofunnels-upstroke-power-pack' ),
				2  => __( 'Offer ({{offer_name}}) viewed.', 'woofunnels-upstroke-power-pack' ),
				4  => __( 'Offer ({{offer_name}}) converted.', 'woofunnels-upstroke-power-pack' ),
				5  => __( 'Product ({{product_name}}) {{qty}} in offer ({{offer_name}}) accepted.', 'woofunnels-upstroke-power-pack' ),
				6  => __( 'Offer ({{offer_name}}) rejected.', 'woofunnels-upstroke-power-pack' ),
				7  => __( 'Offer ({{offer_name}}) expired.', 'woofunnels-upstroke-power-pack' ),
				8  => __( 'Funnel ({{funnel_name}}) Terminated.', 'woofunnels-upstroke-power-pack' ),
				9  => __( 'Funnel ({{funnel_name}}) payment failed.', 'woofunnels-upstroke-power-pack' ),
				10 => __( 'Offer ({{offer_name}}) skipped. Reason: {{invalidation_reason_html}}', 'woofunnels-upstroke-power-pack' ),
				11 => __( 'Funnel ({{funnel_name}}) Closed. Order Recieved Page Shown to User.', 'woofunnels-upstroke-power-pack' ),
				12 => __( 'Offer ({{offer_name}}) Refunded.', 'woofunnels-upstroke-power-pack' ),
			) );

			if ( count( $upstroke_data ) > 1 ) {
				echo '<ul class="wfocu-timeline">';
				foreach ( $upstroke_data as $upstroke ) {
					echo '<li class="wfocu-action-type-id-' . esc_attr( $upstroke->action_type_id ) . '" data-action_id="' . esc_attr( $upstroke->event_id ) . '">';
					echo '<div class="wfocu-o-timeline"><div class="wfocu-tm">';
					echo '<span class="wfocu-flag">' . esc_attr( $upstroke->trigger_date ) . '</span>';
					echo '</div>';
					switch ( $upstroke->action_type_id ) {
						case 1:
						case 2:
						case 3:
						case 4:
						case 5:
						case 6:
						case 7:
						case 8:
						case 9:
						case 10:
						case 11:
						case 12:
							echo '<div class="wfocu-desc">' . wp_kses_post( $this->wfocu_timeline_template_parse( $template[ $upstroke->action_type_id ], $upstroke ) ) . '</div>';
							break;

						default:
							break;
					}
					echo '</div></li>';
				}
				echo '</ul>';
			} else {
				echo '<p>' . esc_html__( 'No upsells activity', 'woofunnels-upstroke-power-pack' ) . '</p>';
			}

		}

		/**
		 * Generate timeline
		 *
		 * @param $template String to be parsed based on action type id
		 * @param $upstroke array to use in parsing
		 *
		 * @return parsed string for translations
		 */
		public function wfocu_timeline_template_parse( $template, $upstroke ) {
			if ( 5 === $upstroke->action_type_id || '5' === $upstroke->action_type_id ) {
				$offer_name = get_the_title( WFOCU_Core()->track->get_meta( $upstroke->event_id, '_offer_id' ) );
				$qty        = WFOCU_Core()->track->get_meta( $upstroke->event_id, '_qty' );
				$qty        = empty( $qty ) ? '' : 'x' . $qty;
				$template   = str_replace( '{{offer_name}}', $offer_name, $template );
				$template   = str_replace( '{{qty}}', $qty, $template );
			} else {
				$template = str_replace( '{{offer_name}}', get_the_title( $upstroke->object_id ), $template );
			}

			if ( 10 === $upstroke->action_type_id || '10' === $upstroke->action_type_id ) {
				$get_reason_id = WFOCU_Core()->track->get_meta( $upstroke->event_id, '_invalidation_reason' );

				$reason_html = is_callable( [ WFOCU_Core()->offers, 'get_invalidation_reason_string' ] ) ? WFOCU_Core()->offers->get_invalidation_reason_string( $get_reason_id ) : 'NA';

				$template = str_replace( '{{invalidation_reason_html}}', $reason_html, $template );

			}
			$template = str_replace( '{{funnel_name}}', get_the_title( $upstroke->object_id ), $template );
			$template = str_replace( '{{product_name}}', get_the_title( $upstroke->object_id ), $template );

			return $template;

		}
	}
}