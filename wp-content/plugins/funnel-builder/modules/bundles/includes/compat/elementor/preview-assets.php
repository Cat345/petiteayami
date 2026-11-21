<?php
/**
 * Preview_Assets — load every skin asset inside the Elementor editor preview.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Skins\Assets;

/**
 * Editor-preview asset loading.
 *
 * The editor is the one place we deliberately over-enqueue: the preview product
 * can change type without a page reload, and the editor's per-widget re-render is
 * an AJAX request that prints no style or script queue, so a type-specific sheet
 * or behavior module can never arrive later.
 */
class Preview_Assets {

	/**
	 * Hook the preview enqueues.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'styles' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'scripts' ) );
	}

	/**
	 * Every skin stylesheet, plus the editor-only notice styling.
	 *
	 * @return void
	 */
	public static function styles() {
		Assets::enqueue_skin_styles();
		if ( wp_style_is( 'wffn-bundles-compat-elementor', 'registered' ) ) {
			wp_enqueue_style( 'wffn-bundles-compat-elementor' );
		}
	}

	/**
	 * Every behavior module, plus the compatibility script.
	 *
	 * @return void
	 */
	public static function scripts() {
		// null loads every module — the documented editor contract.
		Assets::enqueue_behavior( true, null );
		if ( wp_script_is( Adapter::SCRIPT, 'registered' ) ) {
			wp_enqueue_script( Adapter::SCRIPT );
		}
	}
}
