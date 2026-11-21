<?php
/**
 * Preview_Assets — load every skin asset inside the Bricks builder canvas.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Skins\Assets;

/**
 * Builder-canvas asset loading.
 *
 * The canvas is the one place we deliberately over-enqueue: the merchant can
 * switch skin, layout or preview product without a page reload, and Bricks
 * re-renders the element over REST, a request that prints no style or script
 * queue, so a type-specific sheet or behavior module can never arrive later.
 *
 * The iframe leg only: the outer builder chrome renders no product markup, and
 * Bricks itself skips its WooCommerce assets there.
 */
class Preview_Assets {

	/**
	 * Hook the canvas enqueue.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Every skin sheet and behavior module, inside the builder iframe only.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! function_exists( 'bricks_is_builder_iframe' ) || ! bricks_is_builder_iframe() ) {
			return;
		}
		Assets::enqueue_skin_styles();
		// null loads every module — the documented editor contract.
		Assets::enqueue_behavior( true, null );
	}
}
