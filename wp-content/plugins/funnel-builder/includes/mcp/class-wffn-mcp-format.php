<?php

defined( 'ABSPATH' ) || exit;

/** Trims controller payloads to what an agent needs - every field costs tokens. */
class WFFN_MCP_Format {
	public static function pick( array $row, array $keys ) {
		$out = array();

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $row ) ) {
				$out[ $key ] = $row[ $key ];
			}
		}

		return $out;
	}

	public static function paginate( array $input, $default = 20, $max = 100 ) {
		$limit = isset( $input['limit'] ) ? (int) $input['limit'] : $default;
		$limit = max( 1, min( $max, $limit ) );

		$offset = isset( $input['offset'] ) ? (int) $input['offset'] : 0;
		$offset = max( 0, $offset );

		return array(
			'offset' => $offset,
			'limit'  => $limit,
		);
	}
}
