<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class contains all the thank you page related functionality
 * Class WFFN_Step_WC_Thankyou
 */
if ( ! class_exists( 'WFFN_Pro_Step_WC_Thankyou' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Step_WC_Thankyou extends WFFN_Pro_Step {

		private static $ins = null;
		public $slug = 'wc_thankyou';
		public $list_priority = 40;

		/**
		 * WFFN_Pro_Step_WC_Thankyou constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Pro_Step_WC_Thankyou|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @param $control_id
		 *
		 * @return array
		 */
		public function maybe_get_ab_variants( $control_id ) {
			$variants = [];
			$args     = array(
				'post_type'      => WFFN_Core()->thank_you_pages->get_post_type_slug(),
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => '-1',
				'meta_query'     => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_bwf_ab_variation_of',
						'compare' => '=',
						'value'   => $control_id
					)
				)
			);
			$q        = new WP_Query( $args );
			if ( $q->found_posts > 0 ) {
				foreach ( $q->posts as $variant ) {
					$variants[] = $variant->ID;
				}
			}

			return $variants;
		}


	}

	if ( class_exists( 'WFFN_Pro_Core' ) && ! empty( WFFN_Core()->thank_you_pages ) ) {
		WFFN_Pro_Core()->steps->register( WFFN_Pro_Step_WC_Thankyou::get_instance() );
	}
}