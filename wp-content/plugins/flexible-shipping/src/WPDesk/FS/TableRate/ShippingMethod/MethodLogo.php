<?php
/**
 * Class MethodLogo
 *
 * @package WPDesk\FS\TableRate\ShippingMethod
 */

namespace WPDesk\FS\TableRate\ShippingMethod;

/**
 * Resolves shipping method logo data from method settings.
 */
class MethodLogo {

	/**
	 * @param array $method_settings .
	 *
	 * @return int
	 */
	public static function get_attachment_id( array $method_settings ) {
		return absint( $method_settings[ CommonMethodSettings::METHOD_LOGO_ID ] ?? 0 );
	}

	/**
	 * @param array $method_settings .
	 *
	 * @return array{url:string,alt:string}
	 */
	public static function get_logo_data( array $method_settings ) {
		$attachment_id = self::get_attachment_id( $method_settings );

		if ( 0 === $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return [
				'url' => '',
				'alt' => '',
			];
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );

		if ( ! is_array( $image ) || empty( $image[0] ) ) {
			return [
				'url' => '',
				'alt' => '',
			];
		}

		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		if ( '' === $alt ) {
			$alt = wp_strip_all_tags(
				wpdesk__( $method_settings[ CommonMethodSettings::METHOD_TITLE ] ?? '', 'flexible-shipping' )
			);
		}

		return [
			'url' => (string) $image[0],
			'alt' => $alt,
		];
	}
}
