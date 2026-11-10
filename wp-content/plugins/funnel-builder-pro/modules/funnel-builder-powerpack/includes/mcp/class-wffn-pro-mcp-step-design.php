<?php
/**
 * Upsell Offers for the FunnelKit MCP step-design tools.
 *
 * @package funnel-builder-powerpack
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Pro_MCP_Step_Design' ) ) {
	/**
	 * The free plugin's design tools resolve the Step types they may design
	 * through fkmcp_step_design_types, and an Offer is the Upsell page they
	 * design. The filter runs only when those tools do.
	 */
	class WFFN_Pro_MCP_Step_Design {

		/**
		 * Hooks the Offer into the design types.
		 */
		public static function init() {
			add_filter( 'fkmcp_step_design_types', array( __CLASS__, 'add_offer' ) );
		}

		/**
		 * Adds the Upsell Offer when the Upsells module is active.
		 *
		 * @param array $types Step post type => design type entry.
		 *
		 * @return array
		 */
		public static function add_offer( $types ) {
			if ( ! class_exists( 'WFOCU_Core' ) ) {
				return $types;
			}

			$types = is_array( $types ) ? $types : array();

			$types['wfocu_offer'] = array(
				'type'        => 'offer',
				'design_type' => 'offer',
				'catalog'     => 'upsell',
				'label'       => 'Upsell Offer',
				'widgets'     => array(
					'wfocu-accept-offer-button' => 'Accept Offer Button',
					'wfocu-offer-reject-link'   => 'Decline Offer Link',
				),
				// Every offer widget shows live offer data or makes the offer work.
				'keep'        => array(
					'wfocu-accept-offer-button',
					'wfocu-accept-offer-link',
					'wfocu-offer-reject-button',
					'wfocu-offer-reject-link',
					'wfocu-offer-price',
					'wfocu-offer-product-title',
					'wfocu-product-images',
					'wfocu-short-description',
					'wfocu-qty-selector',
					'wfocu-variation-selector',
				),
				'brand'       => array( 'wfocu-accept-offer-button' => array( 'background_color' ) ),
				'design'      => array( __CLASS__, 'offer_design' ),
				'funnel'      => array( __CLASS__, 'offer_funnel' ),
			);

			return $types;
		}

		/**
		 * The design an Offer shows, from its settings object.
		 *
		 * @param int $offer_id Offer id.
		 *
		 * @return array
		 */
		public static function offer_design( $offer_id ) {
			$settings = get_post_meta( $offer_id, '_wfocu_setting', true );
			$settings = is_object( $settings ) ? get_object_vars( $settings ) : (array) $settings;

			return array(
				'slug'    => isset( $settings['template'] ) ? (string) $settings['template'] : '',
				'builder' => isset( $settings['template_group'] ) ? (string) $settings['template_group'] : '',
			);
		}

		/**
		 * The Funnel an Offer belongs to. The Offer records its upsell step,
		 * and the upsell step records the Funnel.
		 *
		 * @param int $offer_id Offer id.
		 *
		 * @return int
		 */
		public static function offer_funnel( $offer_id ) {
			$upsell_id = (int) get_post_meta( $offer_id, '_funnel_id', true );

			return $upsell_id > 0 ? (int) get_post_meta( $upsell_id, '_bwf_in_funnel', true ) : 0;
		}
	}

	WFFN_Pro_MCP_Step_Design::init();
}
