<?php

defined( 'ABSPATH' ) || exit;

/**
 * JSON Schema builders for ability output_schema definitions.
 *
 * Core validates every ability result against its output_schema
 * (WP_Ability::validate_output) and turns a mismatch into an error the agent
 * sees instead of data, so these builders err on the permissive side: objects
 * accept extra properties, numbers accept the numeric strings the legacy
 * analytics code returns, and anything the code may leave empty is nullable.
 * Type and description are what the agent needs; strictness is not.
 */
final class BWFAN_MCP_Schema {

	/**
	 * Object with named properties. Extra properties are allowed unless $closed.
	 *
	 * @param array    $properties name => schema
	 * @param string   $description
	 * @param string[] $required
	 * @param bool     $closed
	 */
	public static function obj( array $properties, $description = '', array $required = array(), $closed = false ) {
		$schema = array( 'type' => 'object' );

		if ( '' !== $description ) {
			$schema['description'] = $description;
		}
		if ( ! empty( $properties ) ) {
			$schema['properties'] = $properties;
		}
		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}
		if ( $closed ) {
			$schema['additionalProperties'] = false;
		}

		return $schema;
	}

	/** Object whose keys are ids/slugs and whose values all share one schema. */
	public static function map( array $value_schema, $description = '' ) {
		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => $value_schema,
		);
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		return $schema;
	}

	/** Object of unspecified shape (settings blobs, raw builder data). */
	public static function any_obj( $description = '' ) {
		return self::obj( array(), $description );
	}

	public static function arr( array $items, $description = '' ) {
		$schema = array(
			'type'  => 'array',
			'items' => $items,
		);
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		return $schema;
	}

	public static function str( $description = '', $nullable = false ) {
		return self::scalar( 'string', $description, $nullable );
	}

	public static function int( $description = '', $nullable = false ) {
		return self::scalar( 'integer', $description, $nullable );
	}

	/** Number that may arrive as a numeric string (legacy analytics rows). */
	public static function num( $description = '', $nullable = false ) {
		$schema = self::scalar( array( 'number', 'string' ), $description, $nullable );

		return $schema;
	}

	/** Truthy flag as the legacy editors store it: bool, 1/0, or "true"/"false"/"" strings. */
	public static function flag( $description = '' ) {
		return self::scalar( array( 'boolean', 'integer', 'string', 'null' ), '' !== $description ? $description : 'Truthy flag: bool, 1/0 or "true"/"false"/"".', false );
	}

	public static function bool( $description = '', $nullable = false ) {
		return self::scalar( 'boolean', $description, $nullable );
	}

	/** Id that may arrive as int or numeric string. */
	public static function id( $description = '', $nullable = false ) {
		return self::scalar( array( 'integer', 'string' ), $description, $nullable );
	}

	/** Y-m-d H:i:s (site time) unless the description says otherwise. */
	public static function datetime( $description = '', $nullable = true ) {
		return self::scalar( 'string', '' !== $description ? $description : 'Y-m-d H:i:s, site timezone.', $nullable );
	}

	public static function url( $description = '', $nullable = false ) {
		return self::scalar( 'string', '' !== $description ? $description : 'Absolute URL.', $nullable );
	}

	/** Any JSON value. */
	public static function any( $description = '' ) {
		$schema = array( 'type' => array( 'string', 'number', 'integer', 'boolean', 'object', 'array', 'null' ) );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		return $schema;
	}

	public static function enum( array $values, $description = '', $nullable = false ) {
		$schema = self::scalar( 'string', $description, $nullable );
		// A nullable enum must list null too: `enum` is checked on its own, so ["string","null"] alone still rejects null.
		$schema['enum'] = $nullable ? array_merge( $values, array( null ) ) : $values;

		return $schema;
	}

	/** Standard paginated list: { items: [...], total } plus any extra top-level keys. */
	public static function paged( array $item, $description = '', array $extra = array() ) {
		return self::obj(
			array_merge(
				array(
					'items' => self::arr( $item ),
					'total' => self::int( 'Total matching rows, ignoring pagination; null when the source does not report one.', true ),
				),
				$extra
			),
			$description,
			array( 'items' )
		);
	}

	/**
	 * Analytics window echo: {} when all-time, { after, before } when windowed,
	 * or { default: "last 7 days" }.
	 */
	public static function window() {
		return self::obj(
			array(
				'after'    => self::datetime( 'Window start.' ),
				'before'   => self::datetime( 'Window end.' ),
				'default'  => self::str( 'Set when no window was given, e.g. "last 7 days".' ),
				'overall'  => self::bool( 'true when all-time was requested.' ),
				'interval' => self::str( 'Series granularity when one was requested: day, week or month.' ),
			),
			'Reporting window applied. Empty object = all-time.'
		);
	}

	/** Entity link block as returned by context tools: { ids: [...], urls: { name: template }, note? }. */
	public static function link_template() {
		return self::obj(
			array(
				'ids'  => self::arr( self::str(), 'Result keys that hold this entity\'s id.' ),
				'urls' => self::map( self::str( 'URL template with {placeholders}.' ) ),
				'note' => self::str( 'How to fill the template.', true ),
			)
		);
	}

	/**
	 * Pro tools may answer with an upgrade notice instead of data: accept
	 * either shape.
	 */
	public static function pro( array $data_schema ) {
		return array(
			'type'  => 'object',
			'anyOf' => array(
				$data_schema,
				self::obj(
					array(
						'available'  => self::bool( 'false: the tool is unavailable until Pro is active.' ),
						'reason'     => self::str( 'Why the tool is unavailable, e.g. pro_required.' ),
						'message'    => self::str( 'Human-readable explanation to relay to the user.' ),
						'action_url' => self::url( 'Where to fix it.' ),
					),
					'Pro availability notice.',
					array( 'available', 'reason', 'message' )
				),
			),
		);
	}

	/*
	------------------------------------------------------------------ */
	/*
	Input builders - strict: closed objects, real types, bounds         */
	/* ------------------------------------------------------------------ */

	/**
	 * input_schema root: a closed object.
	 *
	 * @param array    $properties name => schema
	 * @param string[] $required
	 * @param array    $extra      further keywords (anyOf, description ...)
	 */
	public static function input( array $properties = array(), array $required = array(), array $extra = array() ) {
		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
		);

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		} else {
			// A REST GET with no input arrives as null; core's normalize_input() swaps in this default.
			// An array, not (object) array(): core hands the default to the callback as is, and callbacks read $input as an array.
			$schema['default'] = array();
		}

		$schema['properties'] = $properties;

		return array_merge( $schema, $extra );
	}

	/** String argument. $rules: minLength, maxLength, pattern, format, enum, default. */
	public static function in_str( $description = '', array $rules = array() ) {
		return self::in_scalar( 'string', $description, $rules );
	}

	/** Integer argument. $rules: minimum, maximum, default. */
	public static function in_int( $description = '', array $rules = array() ) {
		return self::in_scalar( 'integer', $description, $rules );
	}

	/** Number argument. $rules: minimum, maximum, default. */
	public static function in_num( $description = '', array $rules = array() ) {
		return self::in_scalar( 'number', $description, $rules );
	}

	/** Boolean argument. */
	public static function in_bool( $description = '', array $rules = array() ) {
		return self::in_scalar( 'boolean', $description, $rules );
	}

	/** One of a fixed set of strings. */
	public static function in_enum( array $values, $description = '', array $rules = array() ) {
		return self::in_scalar( 'string', $description, array_merge( array( 'enum' => $values ), $rules ) );
	}

	/** List argument. $rules: minItems, maxItems, default. */
	public static function in_arr( array $items, $description = '', array $rules = array() ) {
		return array_merge( self::in_scalar( 'array', $description, $rules ), array( 'items' => $items ) );
	}

	/** Nested object argument with named properties; closed unless $rules say otherwise. */
	public static function in_obj( array $properties, $description = '', array $required = array(), array $rules = array() ) {
		$schema = self::in_scalar( 'object', $description, $rules );

		if ( ! isset( $schema['additionalProperties'] ) ) {
			$schema['additionalProperties'] = false;
		}
		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		$schema['properties'] = $properties;

		return $schema;
	}

	/** Object argument whose keys are free and whose values share one schema. $rules: minProperties. */
	public static function in_map( array $value_schema, $description = '', array $rules = array() ) {
		return array_merge( self::in_scalar( 'object', $description, $rules ), array( 'additionalProperties' => $value_schema ) );
	}

	/** Positive integer id (funnel_id, step_id, contact_id ...). */
	public static function in_id( $description = '' ) {
		return self::in_int( $description, array( 'minimum' => 1 ) );
	}

	/** limit + offset arguments. */
	public static function in_paging( $max = 100, $default = 20, $limit_description = '', $offset_description = '' ) {
		return array(
			'limit'  => self::in_int(
				$limit_description,
				array(
					'minimum' => 1,
					'maximum' => $max,
					'default' => $default,
				)
			),
			'offset' => self::in_int(
				$offset_description,
				array(
					'minimum' => 0,
					'default' => 0,
				)
			),
		);
	}

	/** Free-text search argument. */
	public static function in_search( $description = '', $max_length = 200 ) {
		return self::in_str( $description, array( 'maxLength' => $max_length ) );
	}

	/** Y-m-d or Y-m-d H:i:s argument. */
	public static function in_date( $description = '' ) {
		return self::in_str( $description, array( 'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}( [0-9]{2}:[0-9]{2}:[0-9]{2})?$' ) );
	}

	/**
	 * after / before (+ overall) reporting-window arguments.
	 *
	 * @param string      $after   description
	 * @param string      $before  description
	 * @param string|null $overall description; null omits the argument
	 */
	public static function in_window( $after, $before, $overall = null ) {
		$args = array(
			'after'  => self::in_date( $after ),
			'before' => self::in_date( $before ),
		);

		if ( null !== $overall ) {
			$args['overall'] = self::in_bool( $overall );
		}

		return $args;
	}

	private static function in_scalar( $type, $description, array $rules ) {
		$schema = array( 'type' => $type );

		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		return array_merge( $schema, $rules );
	}

	private static function scalar( $type, $description, $nullable ) {
		if ( $nullable ) {
			$type = array_merge( (array) $type, array( 'null' ) );
		}

		$schema = array( 'type' => $type );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		return $schema;
	}
}
