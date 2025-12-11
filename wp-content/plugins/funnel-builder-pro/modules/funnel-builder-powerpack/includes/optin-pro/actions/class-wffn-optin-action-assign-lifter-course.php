<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * This class will assign a course to optin prospects if the LifterLMS plugin is installed and free course is setup to assign
 * Class WFFN_Optin_Action_Assign_LIFTER_Course
 */
if ( ! class_exists( 'WFFN_Optin_Action_Assign_LIFTER_Course' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Optin_Action_Assign_LIFTER_Course extends WFFN_Optin_Action {

		private static $slug = 'assign_lifter_course';
		private static $ins = null;
		public $priority = 30;
		public $course_id = 0;

		/**
		 * WFFN_Optin_Action_Assign_LIFTER_Course constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Optin_Action_Assign_LIFTER_Course|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @return bool
		 */
		public function should_register() {
			if ( class_exists( 'LifterLMS' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * @param $posted_data
		 * @param $fields_settings
		 * @param $optin_action_settings
		 *
		 * @return array|bool|mixed
		 */
		public function handle_action( $posted_data, $fields_settings, $optin_action_settings ) {

			$posted_data = parent::handle_action( $posted_data, $fields_settings, $optin_action_settings );
			if ( false === $this->should_register() ) {
				return $posted_data;
			}

			$optin_page_id = filter_input( INPUT_POST, 'optin_page_id', FILTER_SANITIZE_NUMBER_INT );
			$optin_page_id = isset( $optin_page_id ) ? $optin_page_id : 0;

			if ( ! isset( $optin_action_settings['lifterlms_course'] ) || 'false' === $optin_action_settings['lifterlms_course'] ) {
				return $posted_data;
			}

			$courses = $optin_action_settings[ self::$slug ];

			if ( ! is_array( $courses ) || ! isset( $courses['id'] ) ) {
				return $posted_data;
			}

			$this->course_id = $courses['id'];
			if ( empty( $this->course_id ) ) {
				return $posted_data;
			}

			$posted_data = WFOPP_Core()->optin_actions->get_integration_object( 'create_wp_user' )->maybe_insert_user( $posted_data );
			$user_id     = isset( $posted_data['user_id'] ) ? $posted_data['user_id'] : 0;

			if ( ( $user_id <= 0 ) || ( $optin_page_id <= 0 ) ) {
				return $posted_data;
			}


			if ( ! is_array( $posted_data ) || count( $posted_data ) === 0 ) {
				return $posted_data;
			}

			add_filter( 'bwf_auto_login_redirect', function ( $url ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
				return get_permalink( $this->course_id );
			} );

			llms_enroll_student( $user_id, $this->course_id );

			WFFN_Core()->logger->log( 'LifterLMS successfully course assign : user_id #' . $user_id . ', course_id #' . $this->course_id . ', optin_id' . $optin_page_id );

			return $posted_data;
		}


		/**
		 * @param $term
		 * search LifterLMS Course
		 *
		 * @return array
		 */
		public function get_courses( $term ) {

			$results = array();
			if ( empty( $term ) ) {
				return $results;
			}

			$courses = array();

			$query_params = array(
				'post_type'      => 'course',
				'posts_per_page' => - 1,
				'post_status'    => 'publish',
			);

			if ( '' !== $term ) {
				$query_params['s'] = $term;
			}

			$query = new WP_Query( $query_params );

			if ( $query->found_posts > 0 ) {
				foreach ( $query->posts as $post ) {
					$results[] = array(
						'id'   => $post->ID,
						'text' => $post->post_title,
					);
				}
			}

			return $results;

		}
	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->optin_actions->register( WFFN_Optin_Action_Assign_LIFTER_Course::get_instance() );
	}
}
