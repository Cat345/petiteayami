<?php
/**
 * Stripe Product Tax Codes Repository Service.
 *
 * @package Stripe\StripeTaxForWooCommerce\Utils
 */

namespace Stripe\StripeTaxForWooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '8.0.0', '>=' ) ) {
	include_once __DIR__ . '/class-readonly-object8.php';
} else {
	include_once __DIR__ . '/class-readonly-object7.php';
}
