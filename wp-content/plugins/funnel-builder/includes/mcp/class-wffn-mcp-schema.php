<?php

defined( 'ABSPATH' ) || exit;

/**
 * Funnel Builder's name for the shared schema builder (fk-mcp-core).
 *
 * The name is kept because Funnel Builder, Powerpack and Bundles call it ~1,400
 * times between them, and because Powerpack has to keep working against a Lite
 * that updated before it did.
 */
class WFFN_MCP_Schema extends FKMCP_Schema {
}
