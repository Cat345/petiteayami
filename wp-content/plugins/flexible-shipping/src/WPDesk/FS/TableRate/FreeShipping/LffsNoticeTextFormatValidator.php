<?php
/**
 * Class LffsNoticeTextFormatValidator
 *
 * @package WPDesk\FS\TableRate\FreeShipping
 */

declare( strict_types=1 );

namespace WPDesk\FS\TableRate\FreeShipping;

/**
 * Validates LFFS notice text placeholder format.
 */
final class LffsNoticeTextFormatValidator {

	/**
	 * @param string $message LFFS notice text.
	 *
	 * @return bool
	 */
	public function is_valid( string $message ): bool {
		if ( '' === $message ) {
			return true;
		}

		try {
			sprintf( $message, '' );
		} catch ( \Throwable $e ) {
			return false;
		}

		return true;
	}
}
