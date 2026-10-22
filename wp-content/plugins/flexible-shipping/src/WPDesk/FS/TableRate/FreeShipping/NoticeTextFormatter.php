<?php
/**
 * Formats merchant-defined free shipping notice text.
 *
 * @package WPDesk\FS\TableRate\FreeShipping
 */

namespace WPDesk\FS\TableRate\FreeShipping;

class NoticeTextFormatter {

	/**
	 * @param string $message Notice text with placeholders.
	 * @param string $amount  Formatted amount.
	 *
	 * @return string
	 * @throws \Throwable When placeholders are invalid.
	 */
	public function format( string $message, string $amount ): string {
		return sprintf( $message, $amount );
	}
}
