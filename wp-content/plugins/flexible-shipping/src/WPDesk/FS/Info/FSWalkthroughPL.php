<?php
/**
 * Class FSWalkthroughPL.
 *
 * @package WPDesk\FS\Info
 */

namespace WPDesk\FS\Info;

use WPDesk\FS\Info\Metabox\Links;

/**
 * Metabox Flexible Shipping walkthrough.
 */
class FSWalkthroughPL extends Links {
	/**
	 * FSWalkthrough constructor.
	 */
	public function __construct() {
		$title        = __( 'Flexible Shipping walkthrough', 'flexible-shipping' );
		$footer_label = __( 'Learn more', 'flexible-shipping' );
		$footer_url   = 'https://octol.io/fs-info-docs';

		parent::__construct( 'fs-walkthrough', $title, $this->generate_footer( $footer_url, $footer_label ) );
	}

	/**
	 * @return array[]
	 */
	protected function get_links() {
		return [
			[
				'label' => __( 'How to add a new shipping method handled by Flexible Shipping?', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-konfiguracja',
			],
			[
				'label' => __( 'A complete guide to shipping methods', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-konfiguracja-metody',
			],
			[
				'label' => __( 'Disable or hide the shipping method', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-wylaczanie-metody',
			],
			[
				'label' => __( 'Advanced options and customization', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-zaawansowane',
			],
			[
				'label' => __( 'Combine shipping classes in Flexible Shipping', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-laczenie-klas-wysylkowych',
			],
		];
	}
}
