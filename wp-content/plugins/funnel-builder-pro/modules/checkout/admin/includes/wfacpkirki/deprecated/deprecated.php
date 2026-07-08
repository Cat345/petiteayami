<?php
/**
 * This file contains all the deprecated functions.
 * We could easily delete all these but they are kept for backwards-compatibility purposes.
 *
 * @package     WFACPKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once wp_normalize_path( __DIR__ . '/functions.php' );
require_once wp_normalize_path( __DIR__ . '/classes.php' );
// Filters require PHP 5.3.
if ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) {
	require_once wp_normalize_path( __DIR__ . '/filters.php' );
}
