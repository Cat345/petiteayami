<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * This class contain data for experiments
 * Class WFFN_Category_DB
 */
if ( ! class_exists( 'WFFN_Category_DB' ) ) {
	class WFFN_Category_DB {

		public static $category_option_key = 'wffn_funnel_category';
		public static $funnel_meta_key = 'wffn_funnel_category';

		private static $message = '';

		/**
		 * Set the message to be returned.
		 *
		 * @param string $msg The message to set.
		 */
		private static function set_message( $msg ) {
			self::$message = $msg;
		}

		/**
		 * Get the message.
		 *
		 * @return string The current message.
		 */
		public static function get_message() {
			return self::$message;
		}

		/**
		 * Get the dynamic table name for funnel meta
		 *
		 * @return string Full table name with WordPress prefix
		 */
		private static function get_funnel_meta_table_name() {
			global $wpdb;

			return $wpdb->prefix . 'bwf_funnelmeta';
		}

		/**
		 * Retrieve all categories.
		 *
		 * @return array
		 */
		public static function get_categories() {
			return get_option( self::$category_option_key, [] );
		}

		/**
		 * Check if the category exists.
		 *
		 * @param string $slug The category slug.
		 *
		 * @return bool
		 */
		public static function category_exists( $slug ) {
			return array_key_exists( $slug, self::get_categories() );
		}

		/**
		 * Add or update category in the options.
		 *
		 * @param string $name Category name.
		 * @param string $slug Category slug.
		 *
		 * @return bool
		 */
		public static function add_or_update_category( $name, $slug ) {
			$categories          = self::get_categories();
			$categories[ $slug ] = $name;

			return update_option( self::$category_option_key, $categories, 'no' );
		}

		/**
		 * Delete category from options.
		 *
		 * @param string $slug Category slug.
		 *
		 * @return bool
		 */
		public static function delete_category( $slug ) {
			$categories = self::get_categories();
			if ( ! isset( $categories[ $slug ] ) ) {
				self::set_message( "Failed to delete category." );

				return false;
			}
			unset( $categories[ $slug ] );

			return update_option( self::$category_option_key, $categories, 'no' );
		}

		/**
		 * Rename category in both the options and funnel meta.
		 *
		 * @param string $old_slug The old category slug.
		 * @param string $new_name The new category name.
		 *
		 * @return bool True if the rename was successful.
		 */
		public static function rename_category_in_funnels( $old_slug, $new_name ) {
			if ( ! self::category_exists( $old_slug ) ) {
				self::set_message( "Category '$old_slug' does not exist." );

				return false;
			}
			$new_slug = sanitize_title( $new_name );
			$new_slug = str_replace( '-', '_', $new_slug );
			if ( self::category_exists( $new_slug ) ) {
				self::set_message( "The category you want to rename already exists." );

				return false;
			}
			if ( ! self::update_category_in_options( $old_slug, $new_name ) ) {
				self::set_message( "Failed to update category in options." );

				return false;
			}

			return self::update_category_in_funnels( $old_slug, $new_slug );
		}

		private static function update_category_in_options( $old_slug, $new_name ) {
			$categories = self::get_categories();
			$new_slug   = sanitize_title( $new_name );
			$new_slug   = str_replace( '-', '_', $new_slug );

			$updated_categories = [];
			foreach ( $categories as $slug => $name ) {
				if ( $slug === $old_slug ) {
					$updated_categories[$new_slug] = $new_name;
				} else {
					$updated_categories[$slug] = $name;
				}
			}

			return update_option( self::$category_option_key, $updated_categories, 'no' );
		}

		private static function update_category_in_funnels( $old_slug, $new_slug ) {
			global $wpdb;
			$funnels = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::get_funnel_meta_table_name() . " WHERE meta_key = %s", self::$funnel_meta_key ) ); // phpcs:ignore

			foreach ( $funnels as $funnel ) {
				$categories = json_decode( $funnel->meta_value, true );

				if ( in_array( $old_slug, $categories, true ) ) {
					$updated_categories = array_map( function ( $category ) use ( $old_slug, $new_slug ) {
						return $category === $old_slug ? $new_slug : $category;
					}, $categories );

					$meta_value = wp_json_encode( $updated_categories );
					$wpdb->update( self::get_funnel_meta_table_name(), [ 'meta_value' => $meta_value ], [ 'meta_id' => $funnel->meta_id ], [ '%s' ], [ '%d' ] );
				}
			}

			return true;
		}

		/**
		 * Assign categories to funnels.
		 *
		 * @param array $funnel_ids Funnel IDs.
		 * @param array $categories Categories slugs.
		 *
		 * @return bool
		 */
		public static function assign_categories_to_funnels( $funnel_ids, $categories ) {
			foreach ( $funnel_ids as $funnel_id ) {
				$existing_meta        = self::get_funnel_meta( $funnel_id, self::$funnel_meta_key );
				$categories_to_update = self::merge_categories( $existing_meta, $categories );

				if ( $existing_meta ) {
					self::update_funnel_meta( $existing_meta->meta_id, wp_json_encode( $categories_to_update ) );

				} else {
					self::insert_funnel_meta( $funnel_id, self::$funnel_meta_key, wp_json_encode( $categories_to_update ) );

				}
			}

			return true;
		}


		/**
		 * Merge existing categories with new ones (to avoid duplicates).
		 *
		 * @param object|null $existing_meta Existing meta for the funnel.
		 * @param array $new_categories New categories to add.
		 *
		 * @return array Merged categories.
		 */
		private static function merge_categories( $existing_meta, $new_categories ) {
			$existing_categories = $existing_meta ? json_decode( $existing_meta->meta_value, true ) : [];

			return array_unique( array_merge( $existing_categories, $new_categories ) );
		}

		/**
		 * Get funnel meta data.
		 *
		 * @param int $funnel_id Funnel ID.
		 * @param string $meta_key Meta key.
		 *
		 * @return object|null Funnel meta or null.
		 */
		public static function get_funnel_meta( $funnel_id, $meta_key ) {
			global $wpdb;

			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::get_funnel_meta_table_name() . " WHERE bwf_funnel_id = %d AND meta_key = %s", $funnel_id, $meta_key ) );//phpcs:ignore
		}

		/**
		 * Insert new funnel meta data.
		 *
		 * @param int $funnel_id Funnel ID.
		 * @param string $meta_key Meta key.
		 * @param string $meta_value Meta value.
		 *
		 * @return bool
		 */
		public static function insert_funnel_meta( $funnel_id, $meta_key, $meta_value ) {
			global $wpdb;

			$existing_row = self::get_funnel_meta( $funnel_id, $meta_key );

			if ( $existing_row ) {
				$wpdb->delete(
					self::get_funnel_meta_table_name(),
					[ 'bwf_funnel_id' => $funnel_id, 'meta_key' => $meta_key ],
					[ '%d', '%s' ]
				);
			}

			return $wpdb->insert(
				self::get_funnel_meta_table_name(),
				[
					'bwf_funnel_id' => $funnel_id,
					'meta_key'      => $meta_key,
					'meta_value'    => $meta_value,
				],
				[ '%d', '%s', '%s' ]
			);
		}

		/**
		 * Update existing funnel meta data.
		 *
		 * @param int $meta_id Meta ID.
		 * @param string $meta_value Meta value.
		 *
		 * @return bool
		 */
		public static function update_funnel_meta( $meta_id, $meta_value ) {
			global $wpdb;

			return $wpdb->update( self::get_funnel_meta_table_name(), [ 'meta_value' => $meta_value ], [ 'meta_id' => $meta_id ], [ '%s' ], [ '%d' ] );
		}

		/**
		 * Remove category from funnels.
		 *
		 * @param string $category_slug The category slug to remove.
		 *
		 * @return bool True if category was successfully removed from all funnels.
		 */
		public static function remove_category_from_funnels( $category_slug ) {
			global $wpdb;

			$funnels = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::get_funnel_meta_table_name() . " WHERE meta_key = %s", 'wffn_funnel_category' ) );//phpcs:ignore

			foreach ( $funnels as $funnel ) {
				$categories = json_decode( $funnel->meta_value, true );

				if ( in_array( $category_slug, $categories, true ) ) {
					$updated_categories = array_diff( $categories, [ $category_slug ] );
					$updated_categories = array_values( $updated_categories );

					if ( ! empty( $updated_categories ) ) {
						$meta_value = wp_json_encode( $updated_categories );
						$wpdb->update( self::get_funnel_meta_table_name(), [ 'meta_value' => $meta_value ], [ 'meta_id' => $funnel->meta_id ], [ '%s' ], [ '%d' ] );
					} else {
						$wpdb->delete( self::get_funnel_meta_table_name(), [ 'meta_id' => $funnel->meta_id ], [ '%d' ] );
					}
				}
			}

			return true;
		}

		public static function get_category_funnel_count( $category_slug ) {
			global $wpdb;
			return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::get_funnel_meta_table_name() . " WHERE meta_key = %s AND meta_value LIKE %s",self::$funnel_meta_key, '%"' . $category_slug . '"%' ) );//phpcs:ignore
		}
	}

}
