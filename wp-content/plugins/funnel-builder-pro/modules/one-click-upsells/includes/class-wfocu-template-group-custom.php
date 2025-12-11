<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Template_Group_Custom' ) ) {
	class WFOCU_Template_Group_Custom extends WFOCU_Template_Group {
		public $allow_empty_template = true;
		public $listing_index = 6;

		public function get_nice_name() {
			return __( 'Other (Using Shortcodes)', 'woofunnels-upstroke-one-click-upsell' );
		}

		public function get_templates() {
			return [ 'wfocu-custom-empty' ];
		}

		public function get_slug() {
			return 'custom';
		}

		public function get_edit_link() {
			return add_query_arg( [
				'post'   => '{{offer_id}}',
				'action' => 'edit',
			], admin_url( 'post.php' ) );
		}

		public function get_preview_link() {
			return add_query_arg( [
				'p' => '{{offer_id}}',
			], site_url() );
		}

		public function load_templates() {

			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );

			foreach ( $template as $temp_key => $temp_val ) {

				$temp_val = wp_parse_args( $temp_val, array(
					'path' => plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'includes/class-wfocu-template-custom.php',
				) );

				$temp_val['thumbnail'] = WFOCU_PLUGIN_URL . '/admin/assets/img/templates/' . $this->get_template_thumbnail_name( $temp_key ) . '.jpg';

				WFOCU_Core()->template_loader->register_template( $temp_key, $temp_val );
			}
			$this->maybe_register_empty( plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'includes/class-wfocu-template-custom.php' );
		}

		public function handle_remote_import( $data ) {
			return $data;
		}

	}

	WFOCU_Core()->template_loader->register_group( new WFOCU_Template_Group_Custom, 'custom' );
}