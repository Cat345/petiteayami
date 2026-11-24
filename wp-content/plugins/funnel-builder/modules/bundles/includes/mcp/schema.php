<?php
/**
 * Bundles' name for Funnel Builder's MCP schema builder.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Bundles ships inside Funnel Builder and uses its MCP layer, the same way
 * Powerpack does. This was a copy of that builder; it had fallen behind on the
 * nullable-enum fix, the empty-input default and the empty-properties guard.
 *
 * The name is kept because Bundles calls it close to 400 times.
 */
class Schema extends \WFFN_MCP_Schema {
}
