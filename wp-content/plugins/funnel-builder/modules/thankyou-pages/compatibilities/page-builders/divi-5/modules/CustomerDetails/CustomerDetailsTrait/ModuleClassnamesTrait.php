<?php
/**
 * CustomerDetails::module_classnames()
 *
 * @package WFTY\Modules\CustomerDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\CustomerDetails\CustomerDetailsTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Text\TextClassnames;

trait ModuleClassnamesTrait {

	public static function module_classnames( array $args ): void {
		$classnames_instance     = $args['classnamesInstance'];
		$attrs                   = $args['attrs'];
		$text_options_classnames = TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? array() );
		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}
	}
}
