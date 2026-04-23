<?php
/*
 * Plugin Name: Speed Optimizer by SiteGround v.7.4.6
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Compatibility_With_Sg_optimizer' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Sg_optimizer {

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_sg_optiomizer_hook' ) );
			add_filter( 'sgo_exclude_urls_from_cache', array( $this, 'exclude_urls_from_cache' ), 10, 1 );
		}

		public function remove_sg_optiomizer_hook() {
			if ( class_exists( 'SiteGround_Optimizer\Combinator\Combinator' ) && class_exists( 'WFACP_Common' ) ) {
				WFACP_Common::remove_actions( 'wp_print_styles', 'SiteGround_Optimizer\Combinator\Combinator', 'pre_combine_header_styles' );
			}

			add_filter( 'sgo_js_minify_exclude', array( $this, 'exclude_javascript' ), 999 );
			add_filter( 'sgo_javascript_combine_exclude', array( $this, 'exclude_javascript' ), 999 );
			add_filter( 'sgo_javascript_combine_excluded_inline_content', array( $this, 'exclude_javascript' ), 999 );
			add_filter( 'sgo_js_async_exclude', array( $this, 'exclude_js_async_exclude' ), 999 );
		}

		public function exclude_javascript( $excluded_handles ) {
			if ( ! is_array( $excluded_handles ) ) {
				$excluded_handles = array();
			}
			$excluded_handles[] = 'wfacp_checkout_js';

			return $excluded_handles;
		}

		public function exclude_js_async_exclude( $excluded_handles ) {
			if ( ! is_array( $excluded_handles ) ) {
				$excluded_handles = array();
			}
			$excluded_handles[] = 'wfacp_checkout_js';
			$excluded_handles[] = 'wfacp-intlTelInput-js';
			$excluded_handles[] = 'jquery-core';
			$excluded_handles[] = 'jquery-migrate';

			return $excluded_handles;
		}

		/**
		 * Exclude FunnelKit checkout pages and API endpoints from SiteGround Optimiser cache
		 *
		 * @param array $excluded_urls Array of excluded URL patterns
		 * @return array Modified array with FunnelKit URLs added
		 */
		public function exclude_urls_from_cache( $excluded_urls ) {
			if ( ! is_array( $excluded_urls ) ) {
				$excluded_urls = array();
			}

			// Get FunnelKit checkout post type slug and exclude all checkout pages
			$checkout_post_types = array( 'wfacp_checkout' ); // Default post type name

			if ( class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'get_post_type_slug' ) ) {
				$post_type_slug = WFACP_Common::get_post_type_slug();
				if ( ! empty( $post_type_slug ) ) {
					$checkout_post_types[] = $post_type_slug;
					// Exclude by post type slug pattern
					$checkout_url_pattern = '/' . $post_type_slug . '/*';
					if ( ! in_array( $checkout_url_pattern, $excluded_urls, true ) ) {
						$excluded_urls[] = $checkout_url_pattern;
					}
				}
			}

			// Also exclude by post type name (wfacp_checkout) in case URL pattern uses post type name
			$post_type_name_pattern = '/wfacp_checkout/*';
			if ( ! in_array( $post_type_name_pattern, $excluded_urls, true ) ) {
				$excluded_urls[] = $post_type_name_pattern;
			}

			// Check if current page/post is of checkout post type and exclude its URL dynamically
			global $post;
			if ( isset( $post ) && is_object( $post ) && isset( $post->post_type ) && in_array( $post->post_type, $checkout_post_types, true ) ) {
				$current_url = get_permalink( $post->ID );
				if ( $current_url ) {
					$parsed_url = wp_parse_url( $current_url );
					$url_path   = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
					if ( ! empty( $url_path ) && ! in_array( $url_path, $excluded_urls, true ) ) {
						$excluded_urls[] = $url_path;
					}
				}
			}

			// Exclude FunnelKit API endpoints
			$api_endpoints = array(
				'/wp-json/funnelkit-app/*',
				'/wp-json/woofunnels-analytics/*',
				'/checkouts/*',
				'/checkout',
			);

			foreach ( $api_endpoints as $endpoint ) {
				if ( ! in_array( $endpoint, $excluded_urls, true ) ) {
					$excluded_urls[] = $endpoint;
				}
			}

			return $excluded_urls;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Sg_optimizer(), 'sg_optimizer' );
}
