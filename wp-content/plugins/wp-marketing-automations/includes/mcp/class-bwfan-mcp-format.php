<?php

defined( 'ABSPATH' ) || exit;

/** Trims controller payloads to what an agent needs - every field costs tokens. */
class BWFAN_MCP_Format {
	public static function pick( array $row, array $keys ) {
		$out = array();

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $row ) ) {
				$out[ $key ] = $row[ $key ];
			}
		}

		return $out;
	}

	public static function collection( $response, array $keys, $items_key = 'data' ) {
		$total = null;
		$rows  = $response;

		if ( is_array( $response ) && isset( $response[ $items_key ] ) && is_array( $response[ $items_key ] ) ) {
			$rows = $response[ $items_key ];

			foreach ( array( 'total_count', 'total', 'count' ) as $candidate ) {
				if ( isset( $response[ $candidate ] ) && is_numeric( $response[ $candidate ] ) ) {
					$total = (int) $response[ $candidate ];
					break;
				}
			}
		}

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$items = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$items[] = self::pick( $row, $keys );
			}
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
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
