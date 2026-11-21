<?php
/** Markup: the one place already-built template markup is printed. */
namespace WFFN\Bundles\Includes\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints markup this plugin has already built and escaped.
 *
 * Skin templates are stamped by Bindings, which escapes every value it writes
 * (text through DOM text nodes, attributes through the DOM, html through
 * wp_kses_post). Escaping the finished string again would strip the data-*
 * attributes the storefront scripts read, so it is printed as is. Keeping that
 * in one method means the exception is stated, and reviewed, once.
 */
class Markup {

	/**
	 * Print markup the plugin built and escaped when it stamped the template.
	 *
	 * @param string $html Finished markup from Registry or Bindings.
	 * @return void
	 */
	public static function output( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-built template markup, escaped when stamped; escaping again would strip its data attributes.
	}
}
