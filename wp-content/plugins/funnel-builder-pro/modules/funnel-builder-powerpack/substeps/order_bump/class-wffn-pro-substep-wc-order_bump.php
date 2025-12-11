<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class contains all the Order bump related funnel functionality
 * Class WFFN_Pro_Substep_WC_Order_Bump
 */
if ( ! class_exists( 'WFFN_Pro_Substep_WC_Order_Bump' ) ) {
	class WFFN_Pro_Substep_WC_Order_Bump extends WFFN_Pro_Substep {

		private static $ins = null;
		public $slug = 'wc_order_bump';

		/**
		 * WFFN_Pro_Substep_WC_Order_Bump constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Pro_Substep_WC_Order_Bump|null
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
				'post_type'      => WFOB_Common::get_bump_post_type_slug(),
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

	if ( class_exists( 'WFFN_Pro_Core' ) && class_exists( 'WFOB_Core' ) ) {
		WFFN_Pro_Core()->substeps->register( WFFN_Pro_Substep_WC_Order_Bump::get_instance() );
	}
}