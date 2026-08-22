<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class contains all the Aero related funnel functionality
 * Class WFFN_Pro_Step_WC_Checkout
 */
if ( ! class_exists( 'WFFN_Pro_Step_WC_Checkout' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Step_WC_Checkout extends WFFN_Pro_Step {

		private static $ins = null;
		public $slug = 'wc_checkout';
		public $substeps = [ 'wc_order_bump' ];
		public $list_priority = 20;

		/**
		 * WFFN_Step_WC_Checkout constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'wfacp_update_report_views', array( $this, 'maybe_already_recoded_views' ), 10, 2 );

		}

		/**
		 * @return WFFN_Pro_Step_WC_Checkout|null
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
				'post_type'      => WFACP_Common::get_post_type_slug(),
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

		/**
		 * check if funnel checkout already recoded views
		 *
		 * @param $is
		 * @param $aero_id
		 *
		 * @return bool|mixed
		 */
		public function maybe_already_recoded_views($is, $aero_id) {
			if (0 === absint($aero_id)) {
				return $is;
			}
			WFFN_Core()->data->get_transient_key();
			if (empty(WFFN_Core()->data->get_transient_key())) {
				return $is;
			}
			$key          = 'wffn_ay_' . WFFN_Core()->data->get_transient_key();
			$cookie_value = isset($_COOKIE[$key]) ? wffn_clean(wp_unslash($_COOKIE[$key])) : ''; //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			$cookie_value = explode(',', str_replace(array('[', ']'), '', $cookie_value));
			if (is_array($cookie_value) && in_array($aero_id, $cookie_value)) { //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				$is = true;
			}

			return $is;
		}


	}

	if ( class_exists( 'WFFN_Pro_Core' ) && class_exists( 'WFACP_Core' ) && wffn_is_wc_active() ) {
		WFFN_Pro_Core()->steps->register( WFFN_Pro_Step_WC_Checkout::get_instance() );
	}
}
