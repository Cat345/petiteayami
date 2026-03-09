<?php
// phpcs:disable WordPress.Files.ClassFileName.NotHyphenatedLowercase
// phpcs:ignoreFile WordPress.Files.ClassFileName
/**
 * Stripe Product Tax Codes Repository Service.
 *
 * @package Stripe\StripeTaxForWooCommerce\Utils
 */

namespace Stripe\StripeTaxForWooCommerce\Utils;

defined( 'ABSPATH' ) || exit;

use ArrayAccess;
use Iterator;
use Serializable;
use Countable;
use JsonSerializable;
use Exception;

/**
 * Base class for array-like readonly objects
 */
abstract class Readonly_Object implements ArrayAccess, Iterator, Countable, JsonSerializable {
	/**
	 * Internal storage
	 *
	 * @var array $internal_array Internal storage array
	 */
	protected $internal_array;

	/**
	 * Creates a readonly object
	 *
	 * @param string $values Array of readonly values.
	 */
	public function __construct( $values = array() ) {
		$this->internal_array = (array) $values;
	}

	/**
	 * Required by ArrayAccess interface
	 *
	 * @param string $offset Offset.
	 */
	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->internal_array );
	}

	/**
	 * Required by ArrayAccess interface
	 *
	 * @param string $offset Offset.
	 *
	 * @throws Exception Throws an excception if the key / property name does not exist.
	 */
	public function offsetGet( $offset ) {
		if ( ! array_key_exists( $offset, $this->internal_array ) ) {
			throw new Exception( 'Property does not exist' );
		}

		return $this->internal_array[ $offset ];
	}

	/**
	 * Required by ArrayAccess interface
	 *
	 * @param string $offset Offset.
	 * @param mixed  $value  Value.
	 *
	 * @throws Exception The object is immutable.
	 */
	public function offsetSet( $offset, $value ): void {
		throw new Exception( 'The object is readonly' );
	}

	/**
	 * Required by ArrayAccess interface
	 *
	 * @param string $offset Offset.
	 *
	 * @throws Exception The object is immutable.
	 */
	public function offsetUnset( $offset ): void {
		throw new Exception( 'The object is readonly' );
	}

	/**
	 * Required by Iterator interface
	 */
	public function current() {
		return current( $this->internal_array );
	}

	/**
	 * Required by Iterator interface
	 */
	public function key() {
		return key( $this->internal_array );
	}

	/**
	 * Required by Iterator interface
	 */
	public function next() {
		next( $this->internal_array );
	}

	/**
	 * Required by Iterator interface
	 */
	public function rewind() {
		reset( $this->internal_array );
	}

	/**
	 * Required by Iterator interface
	 */
	public function valid() {
		return null !== key( $this->internal_array );
	}

	/**
	 * Magic getter
	 *
	 * @param string $offset Offset.
	 */
	public function __get( $offset ) {
		return $this->offsetGet( $offset );
	}

	/**
	 * Magic setter
	 *
	 * @param string $offset Offset.
	 * @param mixed  $value Value.
	 */
	public function __set( $offset, $value ) {
		return $this->offsetSet( $offset, $value );
	}

	/**
	 * Required by Countable interface
	 */
	public function count(): int {
		return count( $this->internal_array );
	}

	/**
	 * Required by JsonSerializable interface
	 */
	public function jsonSerialize() {
		return $this->getArrayCopy();
	}

	/**
	 * Returns a copy of the internal array
	 */
	public function getArrayCopy() {
		$property_names = array_keys( $this->internal_array );

		$array_copy = array();

		foreach ( $property_names as $offset ) {
			$array_copy[ $offset ] = static::copy_value( $this[ $offset ] );
		}

		return $array_copy;
	}

	/**
	 * Returns a copy of a given value
	 *
	 * @param mixed $value The value.
	 */
	private static function copy_value( $value ) {
		if ( is_array( $value ) ) {
			$array_copy = array();

			foreach ( $value as $key => $value_item ) {
				$array_copy[ $key ] = static::copy_value( $value_item );
			}

			return $array_copy;
		}

		if ( is_object( $value ) ) {
			$class = self::class;
			if ( $value instanceof $class ) {
				return $value->getArrayCopy();
			}

			return (array) $value;
		}

		return $value;
	}
}
