<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Handles the operations and usage of actions in optin pages
 * Class WFFN_Optin_Actions
 */
if ( ! class_exists( 'WFFN_Exporter' ) ) {
	class WFFN_Exporter {

		/**
		 * @var null
		 */
		public static $ins = null;

		/**
		 * @var WFFN_Exporter[]
		 */
		public $exporters = array();

		/**
		 * Step classes prefix
		 * @var string
		 */
		public $class_prefix = 'WFFN_Export_';

		/**
		 * WFFN_Optin_Actions constructor.
		 */
		public function __construct() {
			add_action( 'wffn_pro_loaded', array( $this, 'load_exporters' ) );
		}

		/**
		 * @return WFFN_Exporter|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		/**
		 * @param $export_action_class
		 *
		 * @return false|WFFN_Exporter
		 */
		public function get_integration_object( $export_action_class ) {

			if ( isset( $this->exporters[ $export_action_class ] ) ) {
				return $this->exporters[ $export_action_class ];
			}

			return false;
		}

		/**
		 * @param $export_action
		 *
		 * @return void
		 * @throws Exception
		 */
		public function register( $export_action ) {

			if ( empty( $export_action::get_slug() ) ) {
				throw new Exception( 'The  action type must be set' );
			}
			if ( isset( $this->exporters[ $export_action::get_slug() ] ) ) {
				throw new Exception( 'Optin action type already registered: ' . $export_action::get_slug() );
			}
			if ( false === $export_action->should_register() ) {
				return;
			}

			$this->exporters[ $export_action::get_slug() ] = $export_action;

		}

		/**
		 * Includes optin actions files
		 */
		public function load_exporters() {
			// load all the trigger files automatically
			foreach ( glob( plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'includes/exporter/export-actions/class-*.php' ) as $export_action_file_name ) {
				require_once( $export_action_file_name ); //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}

		public function get_post_type_slug() {
			return 'fk_export';
		}

		public function get_export_import( $funnel_id, $limit = '', $offset = '' ) {
			$args = array(
				'post_type'   => $this->get_post_type_slug(),
				'post_status' => 'any',
				'meta_query'  => array(
					array(
						'key'     => 'fid',
						'value'   => $funnel_id,
						'compare' => '='
					)
				)
			);
			if ( '' !== $limit ) {
				$args['posts_per_page'] = $limit;
			}
			if ( '' !== $offset ) {
				$args['offset'] = $offset;
			}
			$query_result = new WP_Query( $args );

			$result = [];
			if ( $query_result->have_posts() ) {
				if ( $query_result->have_posts() ) {
					foreach ( $query_result->posts as $p ) {
						$result[] = $this->get_export_post_meta( $p->ID );
					}
				}
			}

			return [
				'data'  => $result,
				'total' => $query_result->found_posts,
			];
		}

		public function get_export_post_meta( $export_id, $is_export_status = false ) {
			$export_post = get_post( $export_id );
			if ( ! $export_post instanceof WP_Post || ( $this->get_post_type_slug() !== $export_post->post_type ) ) {
				return false;
			}
			$type   = get_post_meta( $export_id, 'export_type', true );
			$status = get_post_meta( $export_id, 'status', true );


			if ( true === $is_export_status && isset( $status ) && true === wffn_string_to_bool( $status ) ) {
				$exporter = WFFN_Pro_Core()->exporter->get_integration_object( $type );
				$exporter->wffn_export( $export_id );
			}
			$result    = [];
			$post_meta = get_post_meta( $export_id );

			if ( is_array( $post_meta ) && count( $post_meta ) > 0 ) {
				$result['export_id']     = $export_id;
				$result['title']         = $export_post->post_title;
				$result['last_modified'] = $export_post->post_modified;
				foreach ( $post_meta as $meta_key => $value ) {
					$result[ $meta_key ] = isset( $value[0] ) ? $value[0] : '';
				}

			}

			return $result;
		}

		/**
		 * Delete export entry
		 *
		 * @param $export_id
		 *
		 * @return bool
		 */
		public function delete_export_entry( $export_id ) {
			$response = false;
			$data     = $this->get_export_post_meta( $export_id );

			$exporter = WFFN_Pro_Core()->exporter->get_integration_object( $data['export_type'] );
			if ( ! $exporter instanceof WFFN_Abstract_Exporter ) {
				return;
			}

			if ( ! empty( $data ) ) {
				$stat = $this->delete_export( $export_id );
				$temp = ! empty( $data['meta'] ) ? json_decode( $data['meta'], true ) : [];
				if ( isset( $temp['file'] ) && file_exists( WFFN_PRO_EXPORT_DIR . '/' . $temp['file'] ) ) {
					wp_delete_file( WFFN_PRO_EXPORT_DIR . '/' . $temp['file'] );
				}
				if ( $stat ) {
					$response = true;
				}
			}

			return $response;
		}

		public function delete_export( $export_id ) {
			$delete = false;
			if ( ! is_null( get_post( $export_id ) ) ) {
				$delete = wp_delete_post( $export_id );
			}

			return ! empty( $delete ) ? true : false;
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'exporter', 'WFFN_Exporter' );
	}
}