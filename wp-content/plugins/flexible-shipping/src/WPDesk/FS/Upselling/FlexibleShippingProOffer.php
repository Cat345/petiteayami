<?php

declare( strict_types=1 );

namespace WPDesk\FS\Upselling;

/**
 * Provides the Flexible Shipping PRO offer for free plugin settings.
 */
final class FlexibleShippingProOffer {

	/**
	 * @return array<string, mixed>
	 */
	public function create( string $url ): array {
		return [
			'eyebrow'      => __( 'Flexible Shipping PRO', 'flexible-shipping' ),
			'headline'     => __( 'Stop charging the same shipping for every product', 'flexible-shipping' ),
			'description'  => __( 'The free version calculates shipping cost based on cart weight and value. PRO lets you also vary rates by shipping class, product category and customer role.', 'flexible-shipping' ),
			'current'      => [
				'label' => __( 'One rate for the whole cart', 'flexible-shipping' ),
				'badge' => __( 'NOW', 'flexible-shipping' ),
			],
			'upgrade'      => [
				'label' => __( 'Different rates per shipping class', 'flexible-shipping' ),
				'badge' => __( 'PRO', 'flexible-shipping' ),
			],
			'benefits'     => [
				[
					'icon' => 'box',
					'text' => __( 'Set different shipping costs for specific shipping classes, categories or product tags', 'flexible-shipping' ),
				],
				[
					'icon' => 'calendar',
					'text' => __( 'Enable or disable the shipping method based on the day of week and time', 'flexible-shipping' ),
				],
				[
					'icon' => 'percent',
					'text' => __( 'Configure shipping rules automatically with the built-in AI Assistant', 'flexible-shipping' ),
				],
			],
			'social_proof' => __( 'Trusted by 250,000+ WooCommerce stores', 'flexible-shipping' ),
			'guarantee'    => __( '30-day money-back guarantee - risk-free', 'flexible-shipping' ),
			'cta_label'    => __( 'Unlock shipping classes', 'flexible-shipping' ),
			'cta_url'      => $url,
		];
	}
}
