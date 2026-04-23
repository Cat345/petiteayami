<?php
/**
 * OptinFormPopup::module_classnames().
 *
 * @package WFOPP\Modules\OptinFormPopup
 * @since 1.0.0
 */

namespace WFOPP\Modules\OptinFormPopup\OptinFormPopupTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Text\TextClassnames;

trait ModuleClassnamesTrait {

	/**
	 * Module classnames function for Optin Form Popup module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * src/components/optin-form-popup/module-classnames.ts.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Instance of ET\Builder\Packages\Module\Layout\Components\Classnames.
	 *     @type array  $attrs              Block attributes data that being rendered.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Text Options.
		$text_options_classnames = TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? array() );

		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}
	}
}
