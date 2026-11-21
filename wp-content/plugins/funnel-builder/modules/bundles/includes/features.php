<?php
/**
 * Features this plugin does half of, for an add-on to compare against.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shared features this plugin publishes, each with a name and description.
 *
 * The keys are what an add-on compares; the text is how it names a feature it
 * has never heard of. This plugin publishes the list and never asks back.
 */
class Features {

	/**
	 * Every shared feature, keyed the way an add-on refers to it.
	 *
	 * @return array<string,array{name:string,description:string}>
	 */
	public static function all() {
		return array(
			'abilities'      => array(
				'name'        => __( 'AI abilities', 'funnel-builder' ),
				'description' => __( 'Bundle tools an AI app can use through FunnelKit AI.', 'funnel-builder' ),
			),
			'bundle_rules' => array(
				'name'        => __( 'Validation rules', 'funnel-builder' ),
				'description' => __( 'Checks a bundle before it can go live.', 'funnel-builder' ),
			),
			'cart_addons'    => array(
				'name'        => __( 'Cart add-ons', 'funnel-builder' ),
				'description' => __( 'Extra offer rows that join a bundle in the cart.', 'funnel-builder' ),
			),
			'frequently_bought_together' => array(
				'name'        => __( 'Frequently Bought Together', 'funnel-builder' ),
				'description' => __( 'Products shown with the viewed one, added together at a discount.', 'funnel-builder' ),
			),
			'offer_images'   => array(
				'name'        => __( 'Offer images', 'funnel-builder' ),
				'description' => __( 'Product images on each offer row.', 'funnel-builder' ),
			),
			'skins'          => array(
				'name'        => __( 'Skins', 'funnel-builder' ),
				'description' => __( 'Layouts that decide how a bundle looks on the product page.', 'funnel-builder' ),
			),
			'targeting'      => array(
				'name'        => __( 'Targeting', 'funnel-builder' ),
				'description' => __( 'Which products show a bundle, and when.', 'funnel-builder' ),
			),
		);
	}
}
