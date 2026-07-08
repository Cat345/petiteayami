<?php

if ( ! class_exists( 'WFFN_Template_Importer' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Template_Importer {

		private static $instance = null;
		private static $importer = array();

		public function __construct() {
			require __DIR__ . '/remote/class-wffn-remote-template-importer.php';

			if ( class_exists( 'WFFN_Remote_Template_Importer' ) ) {
				WFFN_Core()->remote_importer = WFFN_Remote_Template_Importer::get_instance();
			}
			add_action( 'wffn_step_duplicated', array( $this, 'maybe_clear_cache' ) );
		}

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public static function register( $builder, $importer ) {
			if ( ! isset( self::$importer[ $builder ] ) && $importer instanceof WFFN_Import_Export ) {
				self::$importer[ $builder ] = $importer;
			}
		}

		public function is_empty_template( $builder, $slug, $type ) {

			if ( 'wp_editor' === $builder ) {
				return true;
			}

			$templates = WooFunnels_Dashboard::get_all_templates();

			// divi5 fallback to divi if divi5 data not available
			$lookup_builder = $builder;
			if ( 'divi5' === $builder && ! isset( $templates[ $type ]['divi5'] ) ) {
				$lookup_builder = 'divi';
			}

			$template_data = $templates[ $type ][ $lookup_builder ][ $slug ];

			if ( ! isset( $template_data['build_from_scratch'] ) ) {
				return false;
			}

			return wffn_string_to_bool( $template_data['build_from_scratch'] );
		}

		/**
		 * @param $module_id
		 * @param $builder
		 * @param $slug
		 * @param $step
		 *
		 * @return array
		 */
		public function import_remote( $module_id, $builder, $slug, $step ) {
			$result = array(
				'success' => false,
				'error'   => __( 'We are having trouble importing this template, Please contact support.', 'funnel-builder' ),
			);

			do_action( 'wffn_template_import_remote', $module_id, $builder, $slug, $step );

			$safe_builder = sanitize_file_name( $builder );
			$safe_step    = sanitize_file_name( $step );
			$safe_slug    = sanitize_file_name( $slug );

			$template_file_path = $safe_builder . '/' . $safe_step . '/' . $safe_slug;
			$full_path          = WFFN_TEMPLATE_UPLOAD_DIR . $template_file_path . '.json';

			if ( ! file_exists( $full_path ) ) {
				$content = WFFN_Core()->remote_importer->get_remote_template( $step, $slug, $builder );

			} else {
				$real_upload_dir = realpath( WFFN_TEMPLATE_UPLOAD_DIR );
				$real_file_path  = realpath( $full_path );
				if ( false === $real_upload_dir || false === $real_file_path || 0 !== strpos( $real_file_path, $real_upload_dir ) ) {
					return $result;
				}
				$content = file_get_contents( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
				wp_delete_file( $full_path );
			}

			if ( empty( $content ) ) {
				return $result;
			}

			if ( is_array( $content ) && isset( $content['error'] ) ) {
				$result['error'] = $content['error'];

				return $result;
			}
			$content = apply_filters( 'wffn_imported_template_content', $content, $module_id );
			$status  = $this->import( $module_id, $builder, $slug, $content );

			if ( is_array( $status ) ) {
				$result['success'] = ( isset( $status['success'] ) ) ? $status['success'] : $result['success'];
				$result['error']   = ( isset( $status['error'] ) ) ? $status['error'] : $result['error'];
			} else {
				$result['success'] = $status;
			}

			do_action( 'wffn_import_completed', $module_id, $step, $builder, $slug );

			return $result;
		}

		/**
		 * @param $module_id
		 * @param $builder
		 * @param $slug
		 *
		 * @return bool
		 */
		public function import( $module_id, $builder, $slug, $content = '' ) {

			if ( $builder === 'elementor' ) {
				if ( ( ! version_compare( get_bloginfo( 'version' ), '5.0', '>=' ) && ( version_compare( ELEMENTOR_VERSION, '2.8.0', '>=' ) ) ) ) {
					$message = sprintf( esc_html__( 'Elementor requires WordPress version %s+. please update the WordPress version to import the template.', 'funnel-builder' ), '5.0' );

					return array( 'error' => $message );
				}
			}
			if ( $builder === 'divi' || $builder === 'divi5' ) {
				$response = WFFN_Common::check_builder_status( 'divi' );
				if ( ! empty( $response['error'] ) ) {
					return array( 'error' => $response['error'] );
				}
			}

			// divi5 falls back to divi importer if no dedicated divi5 importer is registered
			$importer_key = $builder;
			if ( 'divi5' === $builder && ! isset( self::$importer['divi5'] ) && isset( self::$importer['divi'] ) ) {
				$importer_key = 'divi';
			}

			if ( isset( self::$importer[ $importer_key ] ) && self::$importer[ $importer_key ] instanceof WFFN_Import_Export && ! empty( $content ) ) {

				$importer = self::$importer[ $importer_key ];
				BWF_Logger::get_instance()->log( 'Importing the ' . $module_id, 'wffn_template_import' );
				BWF_Logger::get_instance()->log( 'Content length the ' . strlen( $content ), 'wffn_template_import' );
				$status = $importer->import( $module_id, $content );
				delete_post_meta( $module_id, '_tobe_import_template_type' );
				delete_post_meta( $module_id, '_tobe_import_template' );

				return $status;
			} else {
				BWF_Logger::get_instance()->log( 'failed importing for ' . $module_id . '-- builder' . $builder, 'wffn_template_import' );
			}

			return false;
		}

		/**
		 * @param $module_id
		 * @param $builder
		 * @param $slug
		 *
		 * @return array||null
		 */
		public function export( $module_id, $builder, $slug ) {
			if ( isset( self::$importer[ $builder ] ) && self::$importer[ $builder ] instanceof WFFN_Import_Export ) {
				$importer    = self::$importer[ $builder ];
				$export_data = $importer->export( $module_id, $builder, $slug );

				return $export_data;
			}

			return null;
		}

		public function maybe_clear_cache() {
			if ( class_exists( '\Elementor\Plugin' ) ) {
				$this->generate_kit();
				Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		}

		public function generate_kit() {
			if ( is_null( Elementor\Plugin::$instance ) || ! Elementor\Plugin::$instance->kits_manager instanceof Elementor\Core\Kits\Manager ) {
				return;
			}
			$kit = Elementor\Plugin::$instance->kits_manager->get_active_kit();
			if ( $kit->get_id() ) {
				return;
			}
			$created_default_kit = Elementor\Plugin::$instance->kits_manager->create_default();
			if ( ! $created_default_kit ) {
				return;
			}
			update_option( Elementor\Core\Kits\Manager::OPTION_ACTIVE, $created_default_kit );
		}
	}

	WFFN_Core::register( 'importer', 'WFFN_Template_Importer' );
}
