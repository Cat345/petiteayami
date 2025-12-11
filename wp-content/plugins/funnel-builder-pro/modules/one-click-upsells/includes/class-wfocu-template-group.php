<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Template_Group' ) ) {
	class WFOCU_Template_Group {
		public $current_template;
		public $allow_empty_template = false;
		public $prefix = '';

		public function __construct() {
			$this->process_url();
			add_action( 'wfocu_offer_duplicated', [ $this, 'maybe_cleanup_template_cache' ], 10 );
			add_action( 'wfocu_offer_updated', [ $this, 'maybe_cleanup_template_cache_on_update' ], 10, 3 );
		}

		public function process_url() {
			if ( isset( $_REQUEST['page'] ) && 'upstroke' === $_REQUEST['page'] && isset( $_REQUEST['edit'] ) && $_REQUEST['edit'] > 0 ) {//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				add_action( 'init', [ $this, 'load_templates' ] );
			}
		}

		/**
		 * Get all the templates and registers them to the loader
		 */
		public function load_templates() {

			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );
			foreach ( $template as $temp_key => $temp_val ) {

				WFOCU_Core()->template_loader->register_template( $temp_key, $temp_val );
			}

		}

		public function get_remote_templates() {

			$templates       = WFOCU_Core()->template_retriever->get_detailed_template( $this->get_slug() );
			$group_templates = [];
			if ( is_array( $templates ) && count( $templates ) > 0 ) {
				$group_templates = $templates;
			}

			return $group_templates;
		}

		public function get_slug() {
			return '';
		}

		public function local_templates() {
			return [];
		}

		/**
		 * Sets up template instance and associate data to it.
		 * @return mixed
		 */
		public function set_up_template() {
			$offer_data = WFOCU_Core()->template_loader->offer_data;
			if ( ! empty( $offer_data ) ) {

				if ( count( get_object_vars( $offer_data ) ) > 0 ) {

					WFOCU_Core()->template_loader->offer_data = $offer_data;
					WFOCU_Core()->template_loader->template   = $offer_data->template;

					if ( WFOCU_Core()->template_loader->template !== '' ) {

						if ( isset( WFOCU_Core()->template_loader->template_ins ) && WFOCU_Core()->template_loader->template_ins instanceof WFOCU_Template_Common ) {

							return WFOCU_Core()->template_loader->template_ins;
						}
						$locate_template = $this->get_template_path( WFOCU_Core()->template_loader->template, WFOCU_Core()->template_loader->offer_data );


						if ( ! empty( $locate_template ) && file_exists( $locate_template ) ) {
							WFOCU_Core()->template_loader->template_ins     = include_once $locate_template;    // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
							WFOCU_Core()->template_loader->current_template = WFOCU_Core()->template_loader->template_ins;

							WFOCU_Core()->template_loader->template_ins->set_offer_id( WFOCU_Core()->template_loader->offer_id );
							WFOCU_Core()->template_loader->template_ins->set_offer_data( WFOCU_Core()->template_loader->offer_data );
							WFOCU_Core()->template_loader->template_ins->load_hooks();

							return WFOCU_Core()->template_loader->template_ins;

						}
					}
				}
			}
		}

		/**
		 * Decides WordPress Template during front end calls.
		 *
		 * @param string $template
		 *
		 * @return string
		 */
		public function maybe_get_template( $template = '' ) {

			return $template;
		}

		public function body_class( $classes ) {
			array_push( $classes, 'wfocu-default' );

			return $classes;
		}

		public function update_template( $template, $offer, $meta ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			return true;
		}

		public function maybe_import() {
			//do something
		}

		/**
		 * Collect all the templates and return their names as list to the caller
		 * @return array
		 */
		public function get_templates() {
			$template = array_merge( $this->get_remote_templates(), $this->local_templates() );

			if ( $this->allow_empty_template ) {
				$template = array_merge( $this->get_empty_template(), $template );
			}

			return array_keys( $template );
		}

		/**
		 * Get empty template configuration
		 * @return array
		 */
		public function get_empty_template() {
			return array(
				'wfocu-' . $this->get_slug() . '-empty' => array(
					'name'               => __( 'Start from scratch', 'woofunnels-upstroke-one-click-upsell' ),
					'thumbnail'          => WFOCU_PLUGIN_URL . '/admin/assets/img/start-from-scratch.svg',
					'build_from_scratch' => true,
				),
			);
		}

		/**
		 * register empty template if supported by the template group
		 *
		 * @param $template_path the template class file path needs to be provided by the template group to handle rendering functions
		 */
		public function maybe_register_empty( $template_path ) {
			$get_empty_template                      = $this->get_empty_template();
			$get_slug                                = key( $get_empty_template );
			$get_empty_template[ $get_slug ]['path'] = $template_path;
			WFOCU_Core()->template_loader->register_template( $get_slug, $get_empty_template[ $get_slug ] );
		}

		/**
		 * Check if the current template is empty template or not
		 *
		 * @param $template template name to check
		 *
		 * @return bool true if its a empty template, false otherwise
		 */
		public function if_current_template_is_empty( $template ) {
			if ( false === $this->allow_empty_template ) {
				return false;
			}
			$get_empty_template = $this->get_empty_template();
			$get_slug           = key( $get_empty_template );
			if ( $template === $get_slug ) {
				return true;
			}

			return false;
		}

		public function maybe_cleanup_template_cache( $offer_id_new ) {
			$get_offer_meta = WFOCU_Core()->offers->get_offer_meta( $offer_id_new );
			if ( ! empty( $get_offer_meta->template_group ) && $this->get_slug() === $get_offer_meta->template_group ) {
				$this->clear_cache( $offer_id_new );
			}
		}

		public function clear_cache( $offer_id_new ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			return;
		}

		public function maybe_cleanup_template_cache_on_update( $data, $offer_id ) {
			$get_offer_meta = WFOCU_Core()->offers->get_offer_meta( $offer_id );
			if ( ! empty( $get_offer_meta->template_group ) && $this->get_slug() === $get_offer_meta->template_group ) {
				$this->clear_cache( $offer_id );
			}
		}

		public function get_template_thumbnail_name( $name ) {
			return str_replace( $this->prefix . '-', '', $name );
		}

		public function get_template_path() {
			return false;
		}


	}
}