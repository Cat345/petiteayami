<?php
/*
 * Plugin Name: WPForms / WPForms Lite v.2.0.0.5
 * Plugin Path : https://wpforms.com
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_WPForms' ) ) {

	/**
	 * WPForms ships its own copy of the intl-tel-input library for the "Smart" phone field,
	 * and its stylesheet is not scoped to WPForms markup — every rule is duplicated under a
	 * bare `body ` prefix (`body .iti__flag`, `body .iti__country-container`,
	 * `body .iti input.iti__tel-input`, ...), and the Divi build repeats them again under
	 * `.et-db #et-boc .et-l .et_pb_module body .iti...`. Those selectors outrank the plugin's
	 * own `.iti__*` rules, so wherever the stylesheet loads it takes over the checkout phone
	 * field: the sprite is swapped for WPForms' own flags file, so the offsets no longer line
	 * up and every country renders the same image, and the input / country-container stacking
	 * is re-declared, which leaves a transparent overlay over the field — clicking it focuses
	 * the country button rather than the input, so typing goes nowhere even though the field
	 * is neither disabled nor readonly and still takes a value set from script.
	 *
	 * The stylesheet reaches far more pages than the ones holding a form: WPForms' Divi
	 * integration enqueues it on every singular page as soon as "Load assets globally" is on,
	 * which is why the failure shows up on Divi sites and not on Elementor ones.
	 *
	 * So drop the phone-field assets on checkout pages that render their own intl-tel-input,
	 * unless the page really does embed a WPForms form — then the form needs them and we
	 * leave everything alone.
	 *
	 * FB #9459
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_WPForms {

		/**
		 * Phone-field handles WPForms registers on the frontend.
		 * The stylesheet and the library script share the `wpforms-smart-phone-field` handle.
		 */
		private $styles = array( 'wpforms-smart-phone-field' );

		private $scripts = array( 'wpforms-smart-phone-field', 'wpforms-smart-phone-field-core' );

		/**
		 * Whether a WPForms form may render on this page. Resolved once, while the queried
		 * post is still the current one.
		 *
		 * @var bool
		 */
		private $page_has_form = false;

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'actions' ) );
		}

		public function actions() {
			$this->page_has_form = $this->detect_wpforms_content();

			if ( true === $this->page_has_form ) {
				return;
			}

			// WPForms' Divi integration enqueues at wp_enqueue_scripts:12 and the checkout
			// enqueues its own intl-tel-input at 100, so run after both.
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_phone_field_assets' ), 999 );

			// A form rendered mid-content enqueues once wp_enqueue_scripts is long done, and
			// those late assets print with the footer — take a second pass before they do.
			add_action( 'wp_print_footer_scripts', array( $this, 'dequeue_phone_field_assets' ), 0 );
		}

		public function dequeue_phone_field_assets() {
			// Nothing to protect unless the checkout is rendering its own intl-tel-input.
			// By footer time the handle has moved out of the queue and into `done`.
			if ( ! wp_style_is( 'wfacp-intl-css', 'enqueued' ) && ! wp_style_is( 'wfacp-intl-css', 'done' ) ) {
				return;
			}

			foreach ( $this->styles as $handle ) {
				wp_dequeue_style( $handle );
			}

			foreach ( $this->scripts as $handle ) {
				wp_dequeue_script( $handle );
			}
		}

		/**
		 * A WPForms form can reach the page as a shortcode, a block or a Divi module, and a
		 * Divi layout keeps its markup in post meta rather than post_content. Rather than
		 * enumerate every wrapper, treat any mention of WPForms in the page as "a form may
		 * render here" and leave the assets alone — a checkout carrying a stray form is far
		 * rarer than one without, and the cost of guessing wrong is a broken form.
		 *
		 * @return bool
		 */
		private function detect_wpforms_content() {
			$post_ids = array( get_queried_object_id(), WFACP_Common::get_id() );

			foreach ( array_filter( array_unique( $post_ids ) ) as $post_id ) {
				$post = get_post( $post_id );

				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$content = $post->post_content;

				if ( false !== stripos( $content, 'wpforms' ) ) {
					return true;
				}

				// Divi stores the rendered layout in meta once the builder has been used.
				$divi_layout = get_post_meta( $post_id, '_et_pb_old_content', true );

				if ( is_string( $divi_layout ) && '' !== $divi_layout && false !== stripos( $divi_layout, 'wpforms' ) ) {
					return true;
				}
			}

			return false;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_WPForms(), 'wpforms' );
}
