<?php
/**
 * Class FSWalkthrough.
 *
 * @package WPDesk\FS\Info
 */

namespace WPDesk\FS\Info;

use WPDesk\FS\Info\Metabox\Links;

/**
 * Metabox Flexible Shipping walkthrough.
 */
class FSWalkthrough extends Links {
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
				'href'  => 'https://octol.io/fs-info-fs-new-method',
			],
			[
				'label' => __( 'A complete guide to shipping methods', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-complete-guide',
			],
			[
				'label' => __( 'Disable or hide the shipping method', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-disable-method',
			],
			[
				'label' => __( 'Advanced options and customization', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-advanced-options',
			],
			[
				'label' => __( 'Combine shipping classes in Flexible Shipping', 'flexible-shipping' ),
				'href'  => 'https://octol.io/fs-combine-shipping-classes',
			],
		];
	}
}
