<?php
/**
 * File Upload
 *
 * Core class handling field registration, rendering, AJAX upload/delete, and order processing.
 *
 * @package FunnelKit\Checkout\Modules\File_Upload
 */

namespace FunnelKit\Checkout\Modules\File_Upload;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File_Upload class.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class File_Upload {

	/**
	 * Singleton instance.
	 *
	 * @var File_Upload
	 */
	private static $instance = null;

	/**
	 * Upload directory name.
	 *
	 * @var string
	 */
	const UPLOAD_DIR = 'wfacp-uploads';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wfacp_file_upload_nonce';

	/**
	 * Default allowed file types.
	 *
	 * @var array
	 */
	private $default_allowed_types = array( 'pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx' );

	/**
	 * Default max file size in MB.
	 *
	 * @var int
	 */
	private $default_max_size = 5;

	/**
	 * Default max files.
	 *
	 * @var int
	 */
	private $default_max_files = 5;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return File_Upload
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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Register field type in admin dropdown.
		add_filter( 'wfacp_register_advanced_field_types', array( $this, 'register_field_type' ) );

		// Register field rendering.
		add_filter( 'woocommerce_form_field_wfacp_file_upload', array( $this, 'render_field' ), 10, 4 );

		// AJAX handlers for upload and delete.
		add_action( 'wp_ajax_wfacp_file_upload', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_nopriv_wfacp_file_upload', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_wfacp_file_delete_upload', array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_nopriv_wfacp_file_delete_upload', array( $this, 'handle_delete' ) );

		// Admin AJAX handler for deleting files from orders.
		add_action( 'wp_ajax_wfacp_admin_delete_file', array( $this, 'handle_admin_delete' ) );

		// Secure file download endpoint.
		add_action( 'wp_ajax_wfacp_file_download', array( $this, 'handle_download' ) );
		add_action( 'wp_ajax_nopriv_wfacp_file_download', array( $this, 'handle_download' ) );

		// Save uploaded files to order meta.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_uploaded_files' ), 10, 3 );

		// Enqueue frontend scripts.
		add_action( 'wfacp_after_checkout_page_found', array( $this, 'init_frontend' ) );

		// Format file upload display in emails.
		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'format_order_item_meta_value' ), 10, 3 );
	}

	/**
	 * Register file upload field type.
	 *
	 * @since 1.0.0
	 * @param array $field_types Existing field types.
	 * @return array Modified field types.
	 */
	public function register_field_type( $field_types ) {
		$field_types[] = array(
			'label' => __( 'File Upload', 'woofunnels-aero-checkout' ),
			'value' => 'wfacp_file_upload',
			'key'   => 'wfacp_file_upload',
		);

		return $field_types;
	}

	/**
	 * Render the file upload field.
	 *
	 * @since 1.0.0
	 * @param string $field Field HTML.
	 * @param string $key Field key.
	 * @param array  $args Field arguments.
	 * @param mixed  $value Field value.
	 * @return string Field HTML.
	 */
	public function render_field( $field, $key, $args, $value ) {
		// Check if we're in admin order edit context - show uploaded files instead of dropzone.
		// Support both classic (post=xxx) and HPOS (page=wc-orders&id=xxx) URLs.
		$is_classic_order_edit = is_admin() && isset( $_GET['post'] ) && isset( $_GET['action'] ) && 'edit' === $_GET['action'];
		$is_hpos_order_edit    = is_admin() && isset( $_GET['page'] ) && 'wc-orders' === $_GET['page'] && isset( $_GET['id'] ) && isset( $_GET['action'] ) && 'edit' === $_GET['action'];

		if ( $is_classic_order_edit || $is_hpos_order_edit ) {
			return $this->render_admin_field( $field, $key, $args, $value );
		}

		// Get field settings.
		$max_size      = isset( $args['maxsize'] ) && ! empty( $args['maxsize'] ) ? absint( $args['maxsize'] ) : $this->default_max_size;
		$max_files     = isset( $args['max_files'] ) && ! empty( $args['max_files'] ) ? absint( $args['max_files'] ) : $this->default_max_files;
		$allowed_types = isset( $args['accepted_file_types'] ) && ! empty( $args['accepted_file_types'] ) ? $args['accepted_file_types'] : implode( ',', $this->default_allowed_types );
		$upload_skin   = isset( $args['upload_skin'] ) && ! empty( $args['upload_skin'] ) ? $args['upload_skin'] : 'modern';

		// Normalize allowed types for accept attribute and helper text.
		$allowed_types_array = array_filter( array_map( 'trim', explode( ',', strtolower( $allowed_types ) ) ) );
		$accept_attr         = '.' . implode( ',.', $allowed_types_array );
		$accept_label        = strtoupper( implode( ', ', $allowed_types_array ) );
		$meta_label_left     = sprintf(
			/* translators: 1: accepted file types, 2: max file size in MB */
			esc_html__( 'Accepts %1$s - max %2$s MB per file', 'woofunnels-aero-checkout' ),
			esc_html( $accept_label ),
			esc_html( $max_size )
		);
		$meta_label_right = sprintf(
			/* translators: %d: number of allowed files */
			_n( '%d file allowed', '%d files allowed', $max_files, 'woofunnels-aero-checkout' ),
			absint( $max_files )
		);

		// Build classes.
		$classes   = array( 'form-row', 'wfacp-form-control-wrapper', 'wfacp-file-upload-wrapper' );
		$classes[] = 'wfacp-upload-skin-' . $upload_skin;
		$classes[] = isset( $args['class'] ) ? implode( ' ', (array) $args['class'] ) : '';

		if ( ! empty( $args['required'] ) ) {
			$classes[] = 'validate-required';
		}

		$container_class = esc_attr( implode( ' ', array_filter( $classes ) ) );
		$container_id    = esc_attr( $args['id'] ) . '_field';
		$label_id        = esc_attr( $args['id'] );
		$sort            = isset( $args['priority'] ) ? $args['priority'] : '';

		// Build required indicator.
		$required = '';
		if ( ! empty( $args['required'] ) ) {
			$required = '&nbsp;<abbr class="required" title="' . esc_attr__( 'required', 'woocommerce' ) . '">*</abbr>';
		} else {
			$required = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
		}

		// Start building HTML.
		ob_start();
		?>
		<div class="<?php echo $container_class; ?>" id="<?php echo $container_id; ?>" data-priority="<?php echo esc_attr( $sort ); ?>">
			<?php if ( ! empty( $args['label'] ) ) : ?>
				<label for="<?php echo $label_id; ?>" class="wfacp-form-control-label">
					<?php echo wp_kses_post( $args['label'] ); ?><?php echo $required; ?>
				</label>
			<?php endif; ?>

			<span class="woocommerce-input-wrapper wfacp-form-control">
				<?php if ( 'modern' === $upload_skin ) : ?>
					<!-- Modern UI - Drop Zone -->
					<div class="wfacp-upload-dropzone"
						data-field-key="<?php echo esc_attr( $key ); ?>"
						data-max-size="<?php echo esc_attr( $max_size ); ?>"
						data-max-files="<?php echo esc_attr( $max_files ); ?>"
						data-allowed-types="<?php echo esc_attr( $allowed_types ); ?>"
						data-upload-skin="<?php echo esc_attr( $upload_skin ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">

						<svg class="wfacp-upload-cloud-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 16V8M12 8L9 11M12 8L15 11" stroke="#959595" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M9 20H15C18.3137 20 21 17.3137 21 14C21 11.4275 19.3927 9.22513 17.1182 8.37565C17.0369 5.39764 14.5944 3 11.6 3C9.27477 3 7.26271 4.43281 6.42129 6.47466C3.88917 6.89667 2 9.12198 2 11.8C2 14.7611 4.23893 17.2 7.2 17.2" stroke="#959595" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span class="wfacp-upload-text"><?php esc_html_e( 'Click or Drag Files to Upload', 'woofunnels-aero-checkout' ); ?></span>
							<input type="file"
							class="wfacp-upload-input"
							multiple
							accept="<?php echo esc_attr( $accept_attr ); ?>">
					</div>
				<?php else : ?>
					<!-- Button UI - Use label with for attribute for native file dialog (more reliable than JS click) -->
					<?php $file_input_id = esc_attr( $args['id'] . '_file' ); ?>
					<div class="wfacp-upload-button-wrapper"
						data-field-key="<?php echo esc_attr( $key ); ?>"
						data-max-size="<?php echo esc_attr( $max_size ); ?>"
						data-max-files="<?php echo esc_attr( $max_files ); ?>"
						data-allowed-types="<?php echo esc_attr( $allowed_types ); ?>"
						data-upload-skin="<?php echo esc_attr( $upload_skin ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">

						<label for="<?php echo $file_input_id; ?>" class="wfacp-upload-btn">
							<svg class="wfacp-upload-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
								<polyline points="17 8 12 3 7 8"></polyline>
								<line x1="12" y1="3" x2="12" y2="15"></line>
							</svg>
							<?php esc_html_e( 'Choose Files', 'woofunnels-aero-checkout' ); ?>
						</label>

						<input type="file"
							id="<?php echo $file_input_id; ?>"
							class="wfacp-upload-input"
							multiple
							accept="<?php echo esc_attr( $accept_attr ); ?>">
					</div>
				<?php endif; ?>

				<div class="wfacp-upload-meta" aria-hidden="true">
					<span class="wfacp-upload-meta-item wfacp-upload-meta-item-left"><?php echo esc_html( $meta_label_left ); ?></span>
					<span class="wfacp-upload-meta-item wfacp-upload-meta-item-right"><?php echo esc_html( $meta_label_right ); ?></span>
				</div>

				<!-- File List -->
				<div class="wfacp-upload-file-list"></div>

				<!-- Error messages -->
				<div class="wfacp-upload-errors"></div>

				<!-- Hidden input for form submission -->
				<input type="hidden"
					name="<?php echo esc_attr( $key ); ?>"
					id="<?php echo esc_attr( $args['id'] ); ?>"
					class="wfacp-upload-hidden-input"
					value="<?php echo esc_attr( $value ); ?>">

				<?php if ( ! empty( $args['description'] ) ) : ?>
					<span class="description" id="<?php echo esc_attr( $args['id'] ); ?>-description">
						<?php echo wp_kses_post( $args['description'] ); ?>
					</span>
				<?php endif; ?>
			</span>
				</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render file upload field in admin order context.
	 *
	 * @since 1.0.0
	 * @param string $field Field HTML.
	 * @param string $key Field key.
	 * @param array  $args Field arguments.
	 * @param mixed  $value Field value.
	 * @return string Field HTML.
	 */
	public function render_admin_field( $field, $key, $args, $value ) {
		// Get order ID from classic (post) or HPOS (id) parameter.
		$order_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return $field;
		}

		// Admin prefixes field keys with 'wfacp_', but meta is saved with original key.
		$meta_key = $key;
		if ( 0 === strpos( $key, 'wfacp_' ) ) {
			$meta_key = substr( $key, 6 ); // Remove 'wfacp_' prefix.
		}

		// Get uploaded files from order meta.
		$file_data = $order->get_meta( '_wfacp_uploaded_files_' . $meta_key );

		ob_start();
		?>
		<div class="form-row wfacp-admin-file-upload-field" id="<?php echo esc_attr( $args['id'] ); ?>_field">
			<label><?php echo wp_kses_post( $args['label'] ); ?></label>
			<span class="wfacp-admin-files-wrapper" data-field-key="<?php echo esc_attr( $meta_key ); ?>" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wfacp_admin_delete_file' ) ); ?>">
				<?php if ( ! empty( $file_data ) && is_array( $file_data ) ) : ?>
					<?php echo $this->format_files_as_html( $file_data, true ); ?>
				<?php else : ?>
					<em><?php esc_html_e( 'No files uploaded', 'woofunnels-aero-checkout' ); ?></em>
				<?php endif; ?>
			</span>
		</div>
		<?php

		// Enqueue admin script for delete functionality.
		$this->enqueue_admin_scripts();

		return ob_get_clean();
	}

	/**
	 * Enqueue admin scripts for file management.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_admin_scripts() {
		static $enqueued = false;
		if ( $enqueued ) {
			return;
		}
		$enqueued = true;

		wp_enqueue_style( 'dashicons' );

		// Add inline script for admin delete functionality.
		wp_add_inline_script(
			'jquery',
			'
			jQuery(document).ready(function($) {
				$(document).on("click", ".wfacp-admin-delete-file", function(e) {
					e.preventDefault();
					if (!confirm("' . esc_js( __( 'Are you sure you want to delete this file?', 'woofunnels-aero-checkout' ) ) . '")) {
						return;
					}

					var $btn = $(this);
					var $wrapper = $btn.closest(".wfacp-admin-files-wrapper");
					var $li = $btn.closest("li");

					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action: "wfacp_admin_delete_file",
							nonce: $wrapper.data("nonce"),
							order_id: $wrapper.data("order-id"),
							field_key: $wrapper.data("field-key"),
							hash: $btn.data("hash"),
							filename: $btn.data("filename")
						},
						beforeSend: function() {
							$btn.prop("disabled", true);
						},
						success: function(response) {
							if (response.success) {
								$li.fadeOut(300, function() {
									$(this).remove();
									if ($wrapper.find("li").length === 0) {
										$wrapper.html("<em>' . esc_js( __( 'No files uploaded', 'woofunnels-aero-checkout' ) ) . '</em>");
									}
								});
							} else {
								alert(response.data.message || "' . esc_js( __( 'Failed to delete file', 'woofunnels-aero-checkout' ) ) . '");
								$btn.prop("disabled", false);
							}
						},
						error: function() {
							alert("' . esc_js( __( 'Failed to delete file', 'woofunnels-aero-checkout' ) ) . '");
							$btn.prop("disabled", false);
						}
					});
				});
			});
		'
		);
	}

	/**
	 * Initialize frontend scripts on checkout page.
	 *
	 * @since 1.0.0
	 */
	public function init_frontend() {
		// Output script data in wp_head before any scripts run.
		add_action( 'wp_head', array( $this, 'output_script_data' ), 5 );
	}

	/**
	 * Output script data in head.
	 *
	 * CSS and JS are included in the main combined files via Grunt.
	 * This outputs the localized data before scripts load.
	 *
	 * @since 1.0.0
	 */
	public function output_script_data() {
		$data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => array(
				'uploading'             => __( 'Uploading...', 'woofunnels-aero-checkout' ),
				'uploadError'           => __( 'Upload failed. Please try again.', 'woofunnels-aero-checkout' ),
				'invalidType'           => __( 'Invalid file type.', 'woofunnels-aero-checkout' ),
				'fileTooLarge'          => __( 'This file is too large.', 'woofunnels-aero-checkout' ),
				'maxFilesReached'       => __( 'Maximum file limit reached.', 'woofunnels-aero-checkout' ),
				'corruptedFile'         => __( 'This file could not be read. It may be corrupted. Try a different file.', 'woofunnels-aero-checkout' ),
				'deleteConfirm'         => __( 'Are you sure you want to remove this file?', 'woofunnels-aero-checkout' ),
				'invalidTypeDetail'     => __( 'Only %1$s files are supported. Try a different file.', 'woofunnels-aero-checkout' ),
				'fileTooLargeDetail'    => __( 'This file is too large (%1$s). Maximum allowed size is %2$s MB.', 'woofunnels-aero-checkout' ),
				'maxFilesReachedDetail' => __( 'You can only upload %s files. Remove one to add another.', 'woofunnels-aero-checkout' ),
			),
		);
		?>
		<script type="text/javascript">
			var wfacpFileUpload = <?php echo wp_json_encode( $data ); ?>;
		</script>
		<?php
	}

	/**
	 * Handle file upload AJAX request.
	 *
	 * @since 1.0.0
	 */
	public function handle_upload() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'woofunnels-aero-checkout' ) ) );
		}

		// Check if file was uploaded.
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'woofunnels-aero-checkout' ) ) );
		}

		$file = $_FILES['file'];

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			$message = __( 'This file could not be read. It may be corrupted. Try a different file.', 'woofunnels-aero-checkout' );
			$code    = 'corrupted_file';

			if ( isset( $file['error'] ) && UPLOAD_ERR_INI_SIZE === (int) $file['error'] ) {
				$message = __( 'File is too large.', 'woofunnels-aero-checkout' );
				$code    = 'file_too_large';
			}

			wp_send_json_error(
				array(
					'code'    => $code,
					'message' => $message,
				)
			);
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error(
				array(
					'code'    => 'corrupted_file',
					'message' => __( 'This file could not be read. It may be corrupted. Try a different file.', 'woofunnels-aero-checkout' ),
				)
			);
		}

		// Get allowed types and max size from server-side field config (never trust client).
		$field_key   = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';
		$checkout_id = isset( $_POST['checkout_id'] ) ? absint( $_POST['checkout_id'] ) : 0;
		if ( ! $checkout_id ) {
			$checkout_id = \WFACP_Common::get_id();
		}
		$field_config  = $this->get_field_config_from_layout( $checkout_id, $field_key );
		$allowed_types = ! empty( $field_config['accepted_file_types'] ) ? $field_config['accepted_file_types'] : implode( ',', $this->default_allowed_types );
		$max_size_mb   = ! empty( $field_config['maxsize'] ) ? absint( $field_config['maxsize'] ) : $this->default_max_size;
		$max_size      = $max_size_mb * 1024 * 1024; // Convert to bytes.

		// Validate file type.
		$allowed_types_array = array_map( 'trim', array_map( 'strtolower', explode( ',', $allowed_types ) ) );
		$file_ext            = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_ext, $allowed_types_array, true ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_type',
					'message' => __( 'Invalid file type.', 'woofunnels-aero-checkout' ),
				)
			);
		}

		// Validate file size.
		if ( $file['size'] > $max_size ) {
			wp_send_json_error(
				array(
					'code'    => 'file_too_large',
					'message' => sprintf(
						/* translators: %d: max file size in MB */
						__( 'File size exceeds the maximum allowed (%dMB).', 'woofunnels-aero-checkout' ),
						$max_size_mb
					),
				)
			);
		}

		// Validate MIME type.
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : false;
		if ( $finfo ) {
			finfo_close( $finfo );
		}

		if ( empty( $mime_type ) ) {
			wp_send_json_error(
				array(
					'code'    => 'corrupted_file',
					'message' => __( 'This file could not be read. It may be corrupted. Try a different file.', 'woofunnels-aero-checkout' ),
				)
			);
		}

		$allowed_mimes = $this->get_allowed_mimes( $allowed_types_array );
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_type',
					'message' => __( 'Invalid file type.', 'woofunnels-aero-checkout' ),
				)
			);
		}

		// Setup secure upload directory.
		$upload_dir = $this->setup_upload_directory();
		if ( is_wp_error( $upload_dir ) ) {
			wp_send_json_error( array( 'message' => $upload_dir->get_error_message() ) );
		}

		// Generate unique hash directory.
		$hash     = md5( uniqid( wp_rand(), true ) . ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) . time() );
		$hash_dir = $upload_dir['path'] . '/' . $hash;

		// Create hash directory.
		if ( ! wp_mkdir_p( $hash_dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create upload directory.', 'woofunnels-aero-checkout' ) ) );
		}

		// Sanitize filename.
		$filename = sanitize_file_name( $file['name'] );
		$filepath = $hash_dir . '/' . $filename;

		// Move uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
			wp_send_json_error(
				array(
					'code'    => 'corrupted_file',
					'message' => __( 'This file could not be read. It may be corrupted. Try a different file.', 'woofunnels-aero-checkout' ),
				)
			);
		}

		// Build secure download URL (never expose raw file path).
		$file_url = $this->get_secure_download_url( $hash, $filename );

		// Store session token for ownership verification on delete.
		$session_token = wp_generate_password( 32, false );
		$this->store_upload_session_token( $hash, $session_token );

		wp_send_json_success(
			array(
				'url'           => $file_url,
				'name'          => $filename,
				'hash'          => $hash,
				'size'          => $file['size'],
				'type'          => $file_ext,
				'mimeType'      => $mime_type,
				'session_token' => $session_token,
			)
		);
	}

	/**
	 * Handle admin file delete AJAX request.
	 *
	 * @since 1.0.0
	 */
	public function handle_admin_delete() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wfacp_admin_delete_file' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'woofunnels-aero-checkout' ) ) );
		}

		// Check admin capabilities.
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$field_key = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';
		$hash      = isset( $_POST['hash'] ) ? sanitize_file_name( wp_unslash( $_POST['hash'] ) ) : '';
		$filename  = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';

		if ( ! $order_id || ! $field_key || ! $hash ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'woofunnels-aero-checkout' ) ) );
		}

		// Validate hash format (must be a 32-char hex MD5 hash).
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'woofunnels-aero-checkout' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'woofunnels-aero-checkout' ) ) );
		}

		// Order-level authorization check.
		if ( ! current_user_can( 'edit_post', $order->get_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get current files.
		$file_data = $order->get_meta( '_wfacp_uploaded_files_' . $field_key );
		if ( empty( $file_data ) || ! is_array( $file_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No files found.', 'woofunnels-aero-checkout' ) ) );
		}

		// Find and remove the file.
		$new_file_data = array();
		$file_removed  = false;

		foreach ( $file_data as $file ) {
			if ( isset( $file['hash'] ) && $file['hash'] === $hash ) {
				// Delete the actual file.
				$upload_dir = wp_upload_dir();
				$file_path  = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR . '/' . $hash . '/' . $filename;

				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );

					// Try to remove empty directory.
					$hash_dir = dirname( $file_path );
					$files    = glob( $hash_dir . '/*' );
					if ( empty( $files ) ) {
						rmdir( $hash_dir );
					}
				}

				$file_removed = true;
			} else {
				$new_file_data[] = $file;
			}
		}

		if ( ! $file_removed ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'woofunnels-aero-checkout' ) ) );
		}

		// Update order meta.
		if ( empty( $new_file_data ) ) {
			$order->delete_meta_data( '_wfacp_uploaded_files_' . $field_key );
			$order->delete_meta_data( $field_key );
		} else {
			$order->update_meta_data( '_wfacp_uploaded_files_' . $field_key, $new_file_data );
			$order->update_meta_data( $field_key, $this->format_files_as_html( $new_file_data, false ) );
		}

		$order->save();

		wp_send_json_success( array( 'message' => __( 'File deleted.', 'woofunnels-aero-checkout' ) ) );
	}

	/**
	 * Handle file delete AJAX request.
	 *
	 * @since 1.0.0
	 */
	public function handle_delete() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get file hash and name.
		$hash     = isset( $_POST['hash'] ) ? sanitize_file_name( wp_unslash( $_POST['hash'] ) ) : '';
		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';

		if ( empty( $hash ) || empty( $filename ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file reference.', 'woofunnels-aero-checkout' ) ) );
		}

		// Validate hash format (must be a 32-char hex MD5 hash).
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file reference.', 'woofunnels-aero-checkout' ) ) );
		}

		// Verify upload ownership via WC session token.
		$session_token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
		$stored_token  = $this->get_upload_session_token( $hash );
		if ( empty( $session_token ) || empty( $stored_token ) || ! hash_equals( $stored_token, $session_token ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this file.', 'woofunnels-aero-checkout' ) ) );
		}

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		$file_path  = $base_dir . '/' . $hash . '/' . $filename;

		// Check if file exists and delete.
		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );

			// Clean up session token.
			$this->delete_upload_session_token( $hash );

			// Try to remove the hash directory if empty.
			$hash_dir = $base_dir . '/' . $hash;
			$files    = glob( $hash_dir . '/*' );
			if ( empty( $files ) ) {
				rmdir( $hash_dir );
			}
		}

		wp_send_json_success( array( 'message' => __( 'File deleted.', 'woofunnels-aero-checkout' ) ) );
	}

	/**
	 * Save uploaded files to order meta.
	 *
	 * @since 1.0.0
	 * @param int      $order_id Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order Order object.
	 */
	public function save_uploaded_files( $order_id, $posted_data, $order ) {
		// Get checkout ID from multiple sources.
		$checkout_id = isset( $posted_data['_wfacp_post_id'] ) ? absint( $posted_data['_wfacp_post_id'] ) : 0;
		if ( ! $checkout_id ) {
			$checkout_id = \WFACP_Common::get_id();
		}
		if ( ! $checkout_id && $order ) {
			$checkout_id = absint( $order->get_meta( '_wfacp_post_id' ) );
		}
		if ( ! $checkout_id ) {
			return;
		}

		// Get layout data to find file upload fields.
		$layout_data = \WFACP_Common::get_page_layout( $checkout_id );
		if ( empty( $layout_data['fieldsets'] ) ) {
			return;
		}

		// Find all file upload fields.
		foreach ( $layout_data['fieldsets'] as $step => $sections ) {
			if ( ! is_array( $sections ) ) {
				continue;
			}

			foreach ( $sections as $section_key => $section ) {
				if ( ! isset( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
					continue;
				}

				foreach ( $section['fields'] as $field ) {
					if ( ! isset( $field['type'] ) || 'wfacp_file_upload' !== $field['type'] ) {
						continue;
					}

					$field_key = isset( $field['id'] ) ? $field['id'] : '';
					if ( empty( $field_key ) || ! isset( $posted_data[ $field_key ] ) ) {
						continue;
					}

					$file_data = $posted_data[ $field_key ];
					if ( empty( $file_data ) ) {
						continue;
					}

					// Decode JSON if it's a string.
					if ( is_string( $file_data ) ) {
						$file_data = json_decode( $file_data, true, 10 );
					}

					if ( empty( $file_data ) || ! is_array( $file_data ) ) {
						continue;
					}

					// Sanitize each file entry before saving.
					$file_data = $this->sanitize_file_data( $file_data );

					// Get field label for meta.
					$field_label = isset( $field['label'] ) ? $field['label'] : $field_key;

					// Save to order meta.
					$save_to_order = ! isset( $field['order_meta_data'] ) || 'true' === $field['order_meta_data'] || true === $field['order_meta_data'];
					$save_to_user  = isset( $field['user_meta_data'] ) && ( 'true' === $field['user_meta_data'] || true === $field['user_meta_data'] );

					if ( $save_to_order ) {
						// Store raw JSON data for programmatic access.
						$order->update_meta_data( '_wfacp_uploaded_files_' . $field_key, $file_data );
						// Store HTML formatted value for display on thank you page and admin.
						$order->update_meta_data( $field_key, $this->format_files_as_html( $file_data, false ) );

						// Add order note.
						$order->add_order_note(
							sprintf(
								/* translators: 1: field label, 2: number of files */
								__( '%1$s: %2$d file(s) uploaded', 'woofunnels-aero-checkout' ),
								$field_label,
								count( $file_data )
							)
						);
					}

					if ( $save_to_user && $order->get_user_id() ) {
						update_user_meta( $order->get_user_id(), '_wfacp_uploaded_files_' . $field_key, $file_data );
					}
				}
			}
		}

		// Save the order to persist meta data.
		$order->save();
	}

	/**
	 * Format files for display in order meta.
	 *
	 * @since 1.0.0
	 * @param array $files Array of file data.
	 * @return string Formatted string.
	 */
	private function format_files_for_display( $files ) {
		$formatted = array();
		foreach ( $files as $file ) {
			if ( ! isset( $file['name'] ) ) {
				continue;
			}

			// Generate secure download URL from hash + filename; fall back to stored URL for legacy data.
			if ( ! empty( $file['hash'] ) ) {
				$url = $this->get_secure_download_url( $file['hash'], $file['name'] );
			} elseif ( isset( $file['url'] ) ) {
				$url = $file['url'];
			} else {
				continue;
			}

			$formatted[] = $file['name'] . ' (' . $url . ')';
		}
		return implode( "\n", $formatted );
	}

	/**
	 * Format order item meta value for display.
	 *
	 * @since 1.0.0
	 * @param string $display_value The display value.
	 * @param object $meta The meta object.
	 * @param object $item The order item.
	 * @return string Formatted value.
	 */
	public function format_order_item_meta_value( $display_value, $meta, $item ) {
		// Check if the value looks like JSON file data.
		if ( is_string( $display_value ) && strpos( $display_value, '"url"' ) !== false && strpos( $display_value, '"name"' ) !== false ) {
			$file_data = json_decode( $display_value, true, 10 );
			if ( is_array( $file_data ) && ! empty( $file_data ) ) {
				return $this->format_files_as_html( $file_data );
			}
		}

		return $display_value;
	}

	/**
	 * Format file data as HTML with clickable links.
	 *
	 * @since 1.0.0
	 * @param array $files Array of file data.
	 * @param bool  $show_admin_controls Whether to show admin delete controls.
	 * @return string HTML formatted file list.
	 */
	public function format_files_as_html( $files, $show_admin_controls = false ) {
		if ( empty( $files ) || ! is_array( $files ) ) {
			return '';
		}

		$output = '<ul class="wfacp-uploaded-files-list" style="margin: 0; padding: 0; list-style: none;">';

		foreach ( $files as $index => $file ) {
			if ( ! isset( $file['name'] ) ) {
				continue;
			}

			$name = esc_html( $file['name'] );

			// Generate secure download URL from hash + filename; fall back to stored URL for legacy data.
			if ( ! empty( $file['hash'] ) ) {
				$url = esc_url( $this->get_secure_download_url( $file['hash'], $file['name'] ) );
			} elseif ( isset( $file['url'] ) ) {
				$url = esc_url( $file['url'] );
			} else {
				continue;
			}
			$hash = isset( $file['hash'] ) ? esc_attr( $file['hash'] ) : '';
			$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

			// Check if it's an image for preview.
			$is_image = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true );

			$output .= '<li style="margin-bottom: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; display: flex; align-items: center; gap: 10px;">';

			if ( $is_image ) {
				$output .= '<a href="' . $url . '" target="_blank" rel="noopener" style="flex-shrink: 0;">';
				$output .= '<img src="' . $url . '" alt="' . $name . '" style="max-width: 50px; max-height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">';
				$output .= '</a>';
			} else {
				$output .= '<span class="dashicons dashicons-media-default" style="flex-shrink: 0; font-size: 24px; width: 24px; height: 24px; color: #7c3aed;"></span>';
			}

			$output .= '<a href="' . $url . '" target="_blank" rel="noopener" style="flex-grow: 1; word-break: break-all;">' . $name . '</a>';

			if ( $show_admin_controls && $hash ) {
				$output .= '<button type="button" class="button wfacp-admin-delete-file" data-hash="' . $hash . '" data-filename="' . esc_attr( $file['name'] ) . '" style="flex-shrink: 0; color: #dc2626;" title="' . esc_attr__( 'Delete file', 'woofunnels-aero-checkout' ) . '">';
				$output .= '<span class="dashicons dashicons-trash"></span>';
				$output .= '</button>';
			}

			$output .= '</li>';
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Handle secure file download request.
	 *
	 * Validates the HMAC token before serving the file, preventing
	 * direct URL access to uploaded files (WAC-002).
	 *
	 * @since 1.0.0
	 */
	public function handle_download() {
		$hash     = isset( $_GET['hash'] ) ? sanitize_file_name( wp_unslash( $_GET['hash'] ) ) : '';
		$filename = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( empty( $hash ) || empty( $filename ) || empty( $token ) ) {
			wp_die( esc_html__( 'Invalid request.', 'woofunnels-aero-checkout' ), '', array( 'response' => 403 ) );
		}

		// Validate hash format (must be a 32-char hex MD5 hash).
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
			wp_die( esc_html__( 'Invalid request.', 'woofunnels-aero-checkout' ), '', array( 'response' => 403 ) );
		}

		// Validate HMAC token.
		$expected_token = $this->generate_download_token( $hash, $filename );
		if ( ! hash_equals( $expected_token, $token ) ) {
			wp_die( esc_html__( 'Invalid or expired download link.', 'woofunnels-aero-checkout' ), '', array( 'response' => 403 ) );
		}

		// Build file path and validate it stays within upload directory.
		$upload_dir = wp_upload_dir();
		$file_path  = realpath( $upload_dir['basedir'] . '/' . self::UPLOAD_DIR . '/' . $hash . '/' . $filename );
		$base_dir   = realpath( $upload_dir['basedir'] . '/' . self::UPLOAD_DIR );

		if ( ! $file_path || ! $base_dir || 0 !== strpos( $file_path, $base_dir ) ) {
			wp_die( esc_html__( 'File not found.', 'woofunnels-aero-checkout' ), 404 );
		}

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File not found.', 'woofunnels-aero-checkout' ), 404 );
		}

		// Determine MIME type.
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file_path );
		finfo_close( $finfo );

		// Serve inline for images, attachment for everything else.
		$ext         = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$image_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		$disposition = in_array( $ext, $image_types, true ) ? 'inline' : 'attachment';

		// Clean any output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: ' . $mime_type );
		$safe_filename = str_replace( array( '"', "\r", "\n" ), '', $filename );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $safe_filename . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: private, max-age=3600' );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $file_path );
		exit;
	}

	/**
	 * Generate an HMAC download token for a file.
	 *
	 * @since 1.0.0
	 * @param string $hash     The file's hash directory name.
	 * @param string $filename The filename.
	 * @return string HMAC-SHA256 token.
	 */
	private function generate_download_token( $hash, $filename ) {
		return hash_hmac( 'sha256', $hash . ':' . $filename, wp_salt( 'auth' ) );
	}

	/**
	 * Get a secure download URL for a file.
	 *
	 * @since 1.0.0
	 * @param string $hash     The file's hash directory name.
	 * @param string $filename The filename.
	 * @return string Secure download URL.
	 */
	public function get_secure_download_url( $hash, $filename ) {
		$token = $this->generate_download_token( $hash, $filename );

		return add_query_arg(
			array(
				'action' => 'wfacp_file_download',
				'hash'   => $hash,
				'file'   => $filename,
				'token'  => $token,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Setup secure upload directory.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Upload directory info or error.
	 */
	private function setup_upload_directory() {
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR;
		$base_url   = $upload_dir['baseurl'] . '/' . self::UPLOAD_DIR;

		// Create base directory if not exists.
		if ( ! file_exists( $base_dir ) ) {
			if ( ! wp_mkdir_p( $base_dir ) ) {
				return new \WP_Error( 'mkdir_failed', __( 'Failed to create upload directory.', 'woofunnels-aero-checkout' ) );
			}
		}

		// Create .htaccess to deny direct access.
		$htaccess_file = $base_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "Order deny,allow\nDeny from all";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess_file, $htaccess_content );
		}

		// Create index.php to prevent directory listing.
		$index_file = $base_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_file, '<?php // Silence is golden' );
		}

		return array(
			'path' => $base_dir,
			'url'  => $base_url,
		);
	}

	/**
	 * Get allowed MIME types based on file extensions.
	 *
	 * @since 1.0.0
	 * @param array $extensions Array of file extensions.
	 * @return array Array of MIME types.
	 */
	private function get_allowed_mimes( $extensions ) {
		$mime_map = array(
			'pdf'  => array( 'application/pdf' ),
			'jpg'  => array( 'image/jpeg' ),
			'jpeg' => array( 'image/jpeg' ),
			'png'  => array( 'image/png' ),
			'gif'  => array( 'image/gif' ),
			'webp' => array( 'image/webp' ),
			'doc'  => array( 'application/msword' ),
			'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
			'xls'  => array( 'application/vnd.ms-excel' ),
			'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ),
			'zip'  => array( 'application/zip', 'application/x-zip-compressed' ),
			'rar'  => array( 'application/x-rar-compressed', 'application/vnd.rar' ),
			'mp4'  => array( 'video/mp4' ),
			'mp3'  => array( 'audio/mpeg' ),
			'wav'  => array( 'audio/wav' ),
		);

		$mimes = array();
		foreach ( $extensions as $ext ) {
			$ext = strtolower( trim( $ext ) );
			if ( isset( $mime_map[ $ext ] ) ) {
				$mimes = array_merge( $mimes, $mime_map[ $ext ] );
			}
		}

		return array_unique( $mimes );
	}

	/**
	 * Get file upload field config from checkout layout data.
	 *
	 * @since 1.0.0
	 * @param int    $checkout_id Checkout ID.
	 * @param string $field_key   Field key.
	 * @return array Field config or empty array.
	 */
	private function get_field_config_from_layout( $checkout_id, $field_key ) {
		if ( ! $checkout_id || empty( $field_key ) ) {
			return array();
		}

		$layout_data = \WFACP_Common::get_page_layout( $checkout_id );
		if ( empty( $layout_data['fieldsets'] ) ) {
			return array();
		}

		foreach ( $layout_data['fieldsets'] as $sections ) {
			if ( ! is_array( $sections ) ) {
				continue;
			}
			foreach ( $sections as $section ) {
				if ( ! isset( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
					continue;
				}
				foreach ( $section['fields'] as $field ) {
					if ( isset( $field['id'] ) && $field['id'] === $field_key && isset( $field['type'] ) && 'wfacp_file_upload' === $field['type'] ) {
						return $field;
					}
				}
			}
		}

		return array();
	}

	/**
	 * Store a session token for an upload hash (ownership tracking).
	 *
	 * @since 1.0.0
	 * @param string $hash          Upload hash.
	 * @param string $session_token Token to store.
	 */
	private function store_upload_session_token( $hash, $session_token ) {
		set_transient( 'wfacp_upload_' . $hash, $session_token, DAY_IN_SECONDS );
	}

	/**
	 * Get the stored session token for an upload hash.
	 *
	 * @since 1.0.0
	 * @param string $hash Upload hash.
	 * @return string|false Session token or false.
	 */
	private function get_upload_session_token( $hash ) {
		return get_transient( 'wfacp_upload_' . $hash );
	}

	/**
	 * Delete the stored session token for an upload hash.
	 *
	 * @since 1.0.0
	 * @param string $hash Upload hash.
	 */
	private function delete_upload_session_token( $hash ) {
		delete_transient( 'wfacp_upload_' . $hash );
	}

	/**
	 * Sanitize file data array before saving to order meta.
	 *
	 * @since 1.0.0
	 * @param array $files Array of file entries.
	 * @return array Sanitized file entries.
	 */
	private function sanitize_file_data( $files ) {
		$allowed_keys = array( 'name', 'hash', 'url', 'size', 'type', 'mimeType' );
		$sanitized    = array();

		foreach ( $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}

			$clean = array();
			foreach ( $allowed_keys as $key ) {
				if ( ! isset( $file[ $key ] ) ) {
					continue;
				}

				switch ( $key ) {
					case 'name':
						$clean[ $key ] = sanitize_file_name( $file[ $key ] );
						break;
					case 'hash':
						$clean[ $key ] = preg_match( '/^[a-f0-9]{32}$/', $file[ $key ] ) ? $file[ $key ] : '';
						break;
					case 'url':
						$clean[ $key ] = esc_url_raw( $file[ $key ] );
						break;
					case 'size':
						$clean[ $key ] = absint( $file[ $key ] );
						break;
					default:
						$clean[ $key ] = sanitize_text_field( $file[ $key ] );
						break;
				}
			}

			if ( ! empty( $clean['hash'] ) && ! empty( $clean['name'] ) ) {
				$sanitized[] = $clean;
			}
		}

		return $sanitized;
	}
}
