<?php
/**
 * Class MethodLogoSettingsField
 *
 * @package WPDesk\FS\TableRate\ShippingMethod
 */

namespace WPDesk\FS\TableRate\ShippingMethod;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/**
 * Renders the shipping method logo picker field.
 */
class MethodLogoSettingsField implements Hookable {

	const FIELD_TYPE = 'method_logo';

	/**
	 * @var bool
	 */
	private static $script_rendered = false;

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_filter( 'woocommerce_generate_' . self::FIELD_TYPE . '_html', [ $this, 'generate_field_html' ], 10, 4 );
		add_action( 'flexible_shipping_method_script', [ $this, 'print_media_script' ], 10, 2 );
	}

	/**
	 * @param string              $field_html      .
	 * @param string              $key             .
	 * @param array               $data            .
	 * @param \WC_Shipping_Method $shipping_method .
	 *
	 * @return string
	 */
	public function generate_field_html( $field_html, $key, $data, $shipping_method ) {
		$field_key     = $shipping_method->get_field_key( $key );
		$attachment_id = absint( $data['default'] ?? 0 );
		$image_url     = '';
		$image_alt     = '';

		if ( 0 !== $attachment_id ) {
			$image_data = MethodLogo::get_logo_data(
				[
					CommonMethodSettings::METHOD_LOGO_ID => $attachment_id,
					CommonMethodSettings::METHOD_TITLE   => $data[ CommonMethodSettings::METHOD_TITLE ] ?? '',
				]
			);
			$image_url  = $image_data['url'];
			$image_alt  = $image_data['alt'];
		}

		return $this->render_view(
			'method-logo-settings-field.php',
			[
				'field_key'        => $field_key,
				'attachment_id'    => $attachment_id,
				'image_url'        => $image_url,
				'image_alt'        => $image_alt,
				'field_title'      => $data['title'] ?? '',
				'description'      => $data['description'] ?? '',
				'select_label'     => 0 === $attachment_id ? __( 'Choose logo', 'flexible-shipping' ) : __( 'Change logo', 'flexible-shipping' ),
				'remove_label'     => __( 'Remove logo', 'flexible-shipping' ),
				'is_logo_selected' => 0 !== $attachment_id,
			]
		);
	}

	/**
	 * @param string $method_id   .
	 * @param int    $instance_id .
	 */
	public function print_media_script( $method_id, $instance_id ) {
		if ( self::$script_rendered ) {
			return;
		}

		wp_enqueue_media();
		self::$script_rendered = true;
		$choose_label          = __( 'Choose logo', 'flexible-shipping' );
		$change_label          = __( 'Change logo', 'flexible-shipping' );
		$script_html           = $this->render_view(
			'method-logo-settings-field-script.php',
			[
				'choose_label' => $choose_label,
				'change_label' => $change_label,
			]
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $script_html;
	}

	/**
	 * @param string $view_file .
	 * @param array  $data      .
	 *
	 * @return string
	 */
	private function render_view( $view_file, array $data = [] ) {
		$view_path = __DIR__ . '/views/' . $view_file;
		$view_data = $data;

		ob_start();
		include $view_path;

		return (string) ob_get_clean();
	}
}
