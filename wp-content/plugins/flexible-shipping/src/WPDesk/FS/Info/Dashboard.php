<?php
/**
 * Flexible Shipping dashboard data.
 *
 * @package WPDesk\FS\Info
 */

namespace WPDesk\FS\Info;

/**
 * Selects dashboard variant and its tracked links.
 */
class Dashboard {
	const VARIANT_FREE = 'free';

	const VARIANT_PRO = 'pro';

	/**
	 * @var string
	 */
	private $variant;

	/**
	 * @var string
	 */
	private $locale;

	/**
	 * @param bool   $is_pro_active Is Flexible Shipping PRO active.
	 * @param string $locale        User locale.
	 */
	public function __construct( $is_pro_active, $locale ) {
		$this->variant = $is_pro_active ? self::VARIANT_PRO : self::VARIANT_FREE;
		$this->locale  = $locale;
	}

	/**
	 * @return string
	 */
	public function get_variant() {
		return $this->variant;
	}

	/**
	 * @return bool
	 */
	public function is_pro() {
		return self::VARIANT_PRO === $this->variant;
	}

	/**
	 * @param string $name Link name.
	 *
	 * @return string
	 */
	public function get_link( $name ) {
		$links  = $this->get_links();
		$locale = 'pl_PL' === $this->locale ? 'pl' : 'en';

		return $links[ $this->variant ][ $locale ][ $name ] ?? '';
	}

	/**
	 * @return array<string, array<string, array<string, string>>>
	 */
	private function get_links() {
		return [
			self::VARIANT_FREE => [
				'pl' => [
					'header_support'       => 'https://octol.io/fs-info-free-pl-support-header',
					'upsell_box'           => 'https://octol.io/fs-info-free-pl-box-upselling',
					'create_rule'          => 'https://octol.io/fs-info-free-pl-creator-adding-rule',
					'free_shipping'        => 'https://octol.io/fs-info-free-pl-creator-free-shipping',
					'debug_mode'           => 'https://octol.io/fs-info-free-pl-creator-debugmode',
					'video'                => 'https://octol.io/fs-info-free-pl-yt-config',
					'plugin_setup'         => 'https://octol.io/fs-konfiguracja',
					'method_setup'         => 'https://octol.io/fs-konfiguracja-metody',
					'cost_by_price'        => 'https://octol.io/fs-info-free-pl-cost-price',
					'cost_by_weight'       => 'https://octol.io/fs-info-free-pl-cost-weight',
					'cash_on_delivery'     => 'https://octol.io/fs-info-free-pl-cod',
					'demo'                 => 'https://octol.io/fs-info-free-pl-demo',
					'docs'                 => 'https://octol.io/fs-info-free-pl-docs',
					'upgrade'              => 'https://octol.io/fs-info-free-pl-or-pro',
					'review'               => 'https://octol.io/fs-info-free-pl-rate',
					'footer_docs'          => 'https://octol.io/fs-info-free-pl-docs-footer',
					'footer_support'       => 'https://octol.io/fs-info-free-pl-support-footer',
					'footer_changelog'     => 'https://octol.io/fs-info-free-pl-changelog',
					'facebook'             => 'https://octol.io/fs-info-free-pl-fb',
					'instagram'            => 'https://octol.io/fs-info-free-pl-instagram',
					'x'                    => 'https://octol.io/fs-info-free-pl-x',
					'linkedin'             => 'https://octol.io/fs-info-free-pl-linkedin',
					'youtube'              => 'https://octol.io/fs-info-free-pl-yt',
					'reddit'               => 'https://octol.io/fs-info-free-pl-reddit',
					'newsletter'           => 'https://octol.io/fs-info-free-pl-newsletter',
					'woocommerce_shipping' => 'https://octol.io/fs-info-wysylka',
					'woocommerce_zones'    => 'https://octol.io/fs-info-strefy',
					'woocommerce_classes'  => 'https://octol.io/fs-info-klasy-wysylkowe',
					'woocommerce_free'     => 'https://octol.io/fs-info-darmowa-wysylka',
					'woocommerce_articles' => 'https://octol.io/fs-info-blog-pl',
				],
				'en' => [
					'header_support'         => 'https://octol.io/fs-info-free-support-header',
					'upsell_box'             => 'https://octol.io/fs-info-free-box-upselling',
					'create_rule'            => 'https://octol.io/fs-info-free-creator-adding-rule',
					'free_shipping'          => 'https://octol.io/fs-info-free-creator-free-shipping',
					'debug_mode'             => 'https://octol.io/fs-info-free-creator-debugmode',
					'video'                  => 'https://octol.io/fs-info-free-yt-config',
					'plugin_setup'           => 'https://octol.io/fs-info-fs-new-method',
					'method_setup'           => 'https://octol.io/fs-complete-guide',
					'cost_by_price'          => 'https://octol.io/fs-info-free-cost-price',
					'cost_by_weight'         => 'https://octol.io/fs-info-free-cost-weight',
					'cash_on_delivery'       => 'https://octol.io/fs-info-free-cod',
					'demo'                   => 'https://octol.io/fs-info-free-demo',
					'upgrade'                => 'https://octol.io/fs-info-free-or-pro',
					'comparison'             => 'https://octol.io/fs-info-free-or-pro-comparison',
					'review'                 => 'https://octol.io/fs-info-free-rate',
					'footer_docs'            => 'https://octol.io/fs-info-free-docs-footer',
					'footer_support'         => 'https://octol.io/fs-info-free-support-footer',
					'footer_changelog'       => 'https://octol.io/fs-info-free-changelog',
					'facebook'               => 'https://octol.io/fs-info-free-fb',
					'instagram'              => 'https://octol.io/fs-info-free-instagram',
					'x'                      => 'https://octol.io/fs-info-free-x',
					'linkedin'               => 'https://octol.io/fs-info-free-linkedin',
					'youtube'                => 'https://octol.io/fs-info-free-yt',
					'reddit'                 => 'https://octol.io/fs-info-free-reddit',
					'newsletter'             => 'https://octol.io/fs-info-free-newsletter',
					'docs'                   => 'https://octol.io/fs-info-docs',
					'woocommerce_zones'      => 'https://octol.io/fs-info-zones',
					'woocommerce_tax'        => 'https://octol.io/fs-info-tax',
					'woocommerce_methods'    => 'https://octol.io/fs-info-methods',
					'woocommerce_classes'    => 'https://octol.io/fs-info-classes',
					'woocommerce_table_rate' => 'https://octol.io/fs-info-table-rate',
					'woocommerce_articles'   => 'https://octol.io/fs-info-blog',
				],
			],
			self::VARIANT_PRO  => [
				'pl' => [
					'header_support'       => 'https://octol.io/fs-info-pro-pl-support-header',
					'ai_assistant'         => 'https://octol.io/fs-info-pro-pl-creator-ai',
					'features'             => 'https://octol.io/fs-info-pro-pl-creator-features',
					'additional_cost'      => 'https://octol.io/fs-info-pro-pl-creator-additional-cost',
					'hide_method'          => 'https://octol.io/fs-info-pro-pl-creator-method',
					'video_ai'             => 'https://octol.io/fs-info-pro-pl-yt-ai',
					'video_table_rate'     => 'https://octol.io/fs-info-pro-pl-yt-table-rate',
					'videos'               => 'https://octol.io/fs-info-pro-pl-yt-more',
					'debug_mode'           => 'https://octol.io/fs-info-pro-pl-docs-debugmode',
					'extra_fee'            => 'https://octol.io/fs-info-pro-pl-docs-extra-fee',
					'free_shipping_items'  => 'https://octol.io/fs-info-pro-pl-docs-free-shipping-items',
					'sizes_classes'        => 'https://octol.io/fs-info-pro-pl-docs-sizes-classes',
					'multi_currency'       => 'https://octol.io/fs-info-pro-pl-docs-multi',
					'docs'                 => 'https://octol.io/fs-info-pro-pl-docs-more',
					'bundle'               => 'https://octol.io/fs-info-pro-pl-bundle',
					'about'                => 'https://octol.io/fs-info-pro-pl-about-us',
					'footer_docs'          => 'https://octol.io/fs-info-pro-pl-footer-docs',
					'footer_support'       => 'https://octol.io/fs-info-pro-pl-footer-support',
					'footer_changelog'     => 'https://octol.io/fs-info-pro-pl-changelog',
					'facebook'             => 'https://octol.io/fs-info-pro-pl-fb',
					'instagram'            => 'https://octol.io/fs-info-pro-pl-instagram',
					'x'                    => 'https://octol.io/fs-info-pro-pl-x',
					'linkedin'             => 'https://octol.io/fs-info-pro-pl-linkedin',
					'youtube'              => 'https://octol.io/fs-info-pro-pl-yt',
					'reddit'               => 'https://octol.io/fs-info-pro-pl-reddit',
					'newsletter'           => 'https://octol.io/fs-info-pro-pl-newsletter',
					'woocommerce_shipping' => 'https://octol.io/fs-info-pro-wysylka',
					'woocommerce_zones'    => 'https://octol.io/fs-info-pro-strefy',
					'woocommerce_classes'  => 'https://octol.io/fs-info-pro-klasy',
					'woocommerce_free'     => 'https://octol.io/fs-info-pro-darmowa-wysylka',
					'woocommerce_articles' => 'https://octol.io/fs-info-pro-pl-articles',
				],
				'en' => [
					'header_support'         => 'https://octol.io/fs-info-pro-support-header',
					'ai_assistant'           => 'https://octol.io/fs-info-pro-creator-ai',
					'features'               => 'https://octol.io/fs-info-pro-creator-features',
					'additional_cost'        => 'https://octol.io/fs-info-pro-creator-additional-cost',
					'hide_method'            => 'https://octol.io/fs-info-pro-creator-method',
					'video_ai'               => 'https://octol.io/fs-info-pro-yt-ai',
					'video_table_rate'       => 'https://octol.io/fs-info-pro-yt-table-rate',
					'videos'                 => 'https://octol.io/fs-info-pro-yt-more',
					'debug_mode'             => 'https://octol.io/fs-info-pro-docs-debugmode',
					'extra_fee'              => 'https://octol.io/fs-info-pro-docs-extra-fee',
					'free_shipping_items'    => 'https://octol.io/fs-info-pro-docs-free-shipping-items',
					'sizes_classes'          => 'https://octol.io/fs-info-pro-docs-sizes-classes',
					'multi_currency'         => 'https://octol.io/fs-info-pro-docs-multi',
					'bundle'                 => 'https://octol.io/fs-info-pro-bundle',
					'all_plugins_bundle'     => 'https://octol.io/fs-info-pro-all-bundle',
					'about'                  => 'https://octol.io/fs-info-pro-about-us',
					'footer_docs'            => 'https://octol.io/fs-info-pro-footer-docs',
					'footer_support'         => 'https://octol.io/fs-info-pro-footer-support',
					'footer_changelog'       => 'https://octol.io/fs-info-pro-changelog',
					'facebook'               => 'https://octol.io/fs-info-pro-fb',
					'instagram'              => 'https://octol.io/fs-info-pro-instagram',
					'x'                      => 'https://octol.io/fs-info-pro-x',
					'linkedin'               => 'https://octol.io/fs-info-pro-linkedin',
					'youtube'                => 'https://octol.io/fs-info-pro-yt',
					'reddit'                 => 'https://octol.io/fs-info-pro-reddit',
					'newsletter'             => 'https://octol.io/fs-info-pro-newsletter',
					'docs'                   => 'https://octol.io/fs-info-docs',
					'woocommerce_zones'      => 'https://octol.io/fs-info-zones',
					'woocommerce_tax'        => 'https://octol.io/fs-info-tax',
					'woocommerce_methods'    => 'https://octol.io/fs-info-methods',
					'woocommerce_classes'    => 'https://octol.io/fs-info-classes',
					'woocommerce_table_rate' => 'https://octol.io/fs-info-table-rate',
					'woocommerce_articles'   => 'https://octol.io/fs-info-blog',
				],
			],
		];
	}
}
