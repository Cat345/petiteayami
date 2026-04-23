<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WFFN_Compatibility_With_Etch
 * Provides compatibility with the Etch theme (etchwp.com) for FunnelKit
 * landing pages, thank you pages, optins, and optin thank you pages.
 *
 * The Etch theme uses a block-based CSS rendering pipeline that requires the default
 * theme template to be active. FunnelKit's custom templates (wflp-boxed, wflp-canvas)
 * bypass this pipeline, causing Etch theme styles to be stripped from the frontend.
 */
if ( ! class_exists( 'WFFN_Compatibility_With_Etch' ) ) {
	class WFFN_Compatibility_With_Etch {

		public function __construct() {
			add_filter( 'wffn_allowed_themes', array( $this, 'allow_theme_css' ) );
			add_action( 'theme_templates', array( $this, 'maybe_remove_templates' ), 10, 4 );
			add_action( 'wffn_import_completed', array( $this, 'setup_default_template' ), 10, 3 );
			add_action( 'wflp_page_design_updated', array( $this, 'update_page_template' ), 99, 2 );
			add_action( 'wfop_page_design_updated', array( $this, 'update_page_template' ), 99, 2 );
			add_action( 'wfoty_page_design_updated', array( $this, 'update_page_template' ), 99, 2 );
			add_action( 'wfty_page_design_updated', array( $this, 'update_page_template' ), 99, 2 );
		}

		/**
		 * Add the active WordPress theme slug to the allowed themes list so that
		 * remove_conflicted_themes_styles() does not strip theme CSS when Etch is active.
		 *
		 * @param array $args Allowed theme slugs.
		 *
		 * @return array
		 */
		public function allow_theme_css( $args ) {
			global $post;

			if ( ! empty( $post ) && in_array(
				$post->post_type,
				array(
					'wffn_landing',
					'wffn_ty',
					'wffn_optin',
					'wffn_oty',
				),
				true
			) ) {
				$args[] = get_template();
			}

			return $args;
		}

		/**
		 * Remove FunnelKit's custom page templates from the template dropdown
		 * for FunnelKit post types when Etch is active.
		 *
		 * @param array     $post_templates Array of page templates.
		 * @param \WP_Theme $theme          The current theme object.
		 * @param \WP_Post  $post           The current post object.
		 * @param string    $post_type      The current post type.
		 *
		 * @return array
		 */
		public function maybe_remove_templates( $post_templates, $theme, $post, $post_type ) {
			$prefix = '';
			switch ( $post_type ) {
				case 'wffn_landing':
					remove_filter( "theme_{$post_type}_templates", array( \WFFN_Core()->landing_pages, 'registered_page_templates' ), 99 );
					$prefix = 'wflp-';
					break;
				case 'wffn_ty':
					remove_filter( "theme_{$post_type}_templates", array( \WFFN_Core()->thank_you_pages, 'registered_page_templates' ), 99 );
					$prefix = 'wftp-';
					break;
				case 'wffn_optin':
					if ( function_exists( 'WFOPP_Core' ) ) {
						remove_filter( "theme_{$post_type}_templates", array( \WFOPP_Core()->optin_pages, 'registered_page_templates' ), 99 );
					}
					$prefix = 'wfop-';
					break;
				case 'wffn_oty':
					if ( function_exists( 'WFOPP_Core' ) ) {
						remove_filter( "theme_{$post_type}_templates", array( \WFOPP_Core()->optin_ty_pages, 'registered_page_templates' ), 99 );
					}
					$prefix = 'wfoty-';
					break;
			}

			if ( '' !== $prefix ) {
				foreach ( array_keys( $post_templates ) as $template_key ) {
					if ( strpos( $template_key, $prefix ) === 0 ) {
						unset( $post_templates[ $template_key ] );
					}
				}
			}

			return $post_templates;
		}

		/**
		 * Force the default theme template on newly imported/created pages
		 * so Etch's rendering pipeline is used.
		 *
		 * Only applies to wp_editor builder type (Gutenberg blocks, which Etch uses)
		 * to avoid side effects with other page builders.
		 *
		 * @param int    $module_id The post ID of the imported step.
		 * @param object $step      The step object.
		 * @param string $builder   The builder slug used for the import.
		 *
		 * @return void
		 */
		public function setup_default_template( $module_id, $step, $builder ) {
			if ( 'wp_editor' === $builder || empty( $builder ) ) {
				update_post_meta( $module_id, '_wp_page_template', 'default' );
			}
		}

		/**
		 * Set default template when a page design is updated with Etch active.
		 *
		 * Only applies when the selected builder is wp_editor (Gutenberg/Etch) to
		 * avoid overriding templates set by other page builders.
		 *
		 * @param int          $page_id The post ID.
		 * @param string|array $builder The selected builder slug, or an array containing 'selected_type'.
		 *
		 * @return void
		 */
		public function update_page_template( $page_id, $builder = null ) {
			$selected_type = '';
			if ( is_array( $builder ) && isset( $builder['selected_type'] ) ) {
				$selected_type = $builder['selected_type'];
			} elseif ( is_string( $builder ) ) {
				$selected_type = $builder;
			}

			if ( 'wp_editor' === $selected_type || empty( $selected_type ) ) {
				update_post_meta( $page_id, '_wp_page_template', 'default' );
			}
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Compatibility_With_Etch(), 'etch' );
}
