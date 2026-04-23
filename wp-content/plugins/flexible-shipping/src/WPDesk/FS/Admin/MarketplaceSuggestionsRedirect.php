<?php
/**
 * Class MarketplaceSuggestionsRedirect
 *
 * @package WPDesk\FS\Admin
 */

namespace WPDesk\FS\Admin;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use FSVendor\WPDesk\ShowDecision\ShouldShowStrategy;

class MarketplaceSuggestionsRedirect implements Hookable {

	/**
	 * @var ShouldShowStrategy
	 */
	private $show_strategy;

	public function __construct( ShouldShowStrategy $show_strategy ) {
		$this->show_strategy = $show_strategy;
	}

	public function hooks() {
		add_filter( 'woo_com_base_url', [ $this, 'get_shipping_extension_url' ] );
	}

	/**
	 * @internal
	 */
	public function get_shipping_extension_url( $should_allow_marketplace_suggestions ): string {
		if ( $this->show_strategy->shouldDisplay() ) {
			return admin_url( 'admin.php?page=octolize-shipping-extensions&' );
		}

		return $should_allow_marketplace_suggestions;
	}
}
