<?php

/**
 * Bump Factory Class
 * Class WFOB_Bump_Fc
 */
if ( ! class_exists( 'WFOB_Bump_Fc' ) ) {
	abstract class WFOB_Bump_Fc {

		public static $number_of_bump_print = [];
		public static $maximum_bump_print = '';
		private static $wfob_data = [];
		private static $layouts = [];
		private static $default_models = [];
		private static $layouts_info = [];


		public static function register( $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'get_slug' ) ) {
				self::$layouts[ $class::get_slug() ]        = $class;
				self::$default_models[ $class::get_slug() ] = $class::get_default_models();
				self::$layouts_info[ $class::get_slug() ]   = [ 'id' => $class::get_slug(), 'preview' => $class::get_preview_image_url() ];
			}
		}

		public static function get_layouts() {
			return self::$layouts;
		}

		/*
		 * Return array of available layout with id & name $preview url
		 */
		public static function get_layouts_info() {
			return self::$layouts_info;
		}

		public static function reset_bumps() {
			self::$wfob_data = [];
		}

		/**
		 * @param $wfob_id
		 *
		 * @return WFOB_Bump|null
		 */
		public static function create( $wfob_id ) {

			if ( $wfob_id == 0 ) {
				return null;
			}
			$design_data = WFOB_Common::get_design_data_meta( $wfob_id );

			if ( ! isset( $design_data['layout'] ) || empty( $design_data['layout'] ) ) {
				return null;
			}
			$layout = $design_data['layout'];
			if ( ! isset( self::$layouts[ $layout ] ) ) {
				return null;
			}


			self::$wfob_data[ $wfob_id ] = new self::$layouts[ $layout ]( $wfob_id );
			self::$wfob_data[ $wfob_id ]->prepare_frontend_data();

			return self::$wfob_data[ $wfob_id ];
		}

		/**
		 *
		 */
		public static function maximum_bump_print() {

			if ( '' != self::$maximum_bump_print ) {
				return self::$maximum_bump_print;
			}
			$data = WFOB_Common::get_global_setting();

			self::$maximum_bump_print = isset( $data['number_bump_per_checkout'] ) ? absint( $data['number_bump_per_checkout'] ) : 0;

			return apply_filters( 'wfob_maximum_bump_print', self::$maximum_bump_print );
		}


		public static function get_default_models( $layout = '' ) {
			if ( ! empty( $layout ) && isset( self::$default_models[ $layout ] ) ) {
				return self::$default_models[ $layout ];
			}


			return self::$default_models;

		}

		public static function get_bumps() {
			return self::$wfob_data;
		}

		public static function get_bump( $wfob_id ) {
			return isset( self::$wfob_data[ $wfob_id ] ) ? self::$wfob_data[ $wfob_id ] : null;
		}

		public static function get_all_bump_css() {


		}


	}
}