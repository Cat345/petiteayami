<?php
/**
 * Admin
 *
 * Handles admin-specific functionality for file upload field.
 *
 * @package FunnelKit\Checkout\Modules\File_Upload
 */

namespace FunnelKit\Checkout\Modules\File_Upload;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Admin
	 */
	private static $instance = null;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->plugin_name = 'wfacp-file-upload';
		$this->version     = defined( 'FKFU_VERSION' ) ? FKFU_VERSION : '1.0.0';

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Enqueue admin scripts when FunnelKit admin JS is loaded.
		add_action( 'wfacp_admin_js_enqueued', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts for field settings.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_admin_scripts() {
		wp_enqueue_script(
			$this->plugin_name . '-admin',
			FKFU_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localize script with i18n strings.
		wp_localize_script(
			$this->plugin_name . '-admin',
			'wfacpFileUploadAdmin',
			array(
				'i18n'     => array(
					'maxsize'               => __( 'Maxsize(in MB)', 'woofunnels-aero-checkout' ),
					'acceptedFileTypes'     => __( 'Accepted File Types', 'woofunnels-aero-checkout' ),
					'acceptedFileTypesHint' => __( 'eg: png,jpg,docx,pdf', 'woofunnels-aero-checkout' ),
					'maxFiles'              => __( 'Max Files', 'woofunnels-aero-checkout' ),
					'customUploadButton'    => __( 'Custom File Upload Button', 'woofunnels-aero-checkout' ),
					'orderMetaData'         => __( 'Order Meta Data', 'woofunnels-aero-checkout' ),
					'userMetaData'          => __( 'User Meta Data', 'woofunnels-aero-checkout' ),
				),
				'defaults' => array(
					'maxsize'             => 5,
					'accepted_file_types' => 'pdf,jpg,jpeg,png,doc,docx',
					'max_files'           => 5,
					'order_meta_data'     => true,
					'user_meta_data'      => false,
				),
			)
		);
	}
}
