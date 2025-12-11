<?php
if ( ! function_exists( 'wfocu_update_fullwidth_page_template' ) ) {
	function wfocu_update_fullwidth_page_template() {
		$args   = array(
			'post_type'        => WFOCU_Common::get_offer_post_type_slug(),
			'posts_per_page'   => - 1,
			'fields'           => 'ids',
			'suppress_filters' => false
		);
		$offers = get_posts( $args ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts

		foreach ( $offers as $offer_id ) {
			$wfocu_settings = get_post_meta( $offer_id, '_wfocu_setting', true );
			if ( ! empty( $wfocu_settings ) && isset( $wfocu_settings->template_group ) && 'elementor' === $wfocu_settings->template_group ) {
				update_post_meta( $offer_id, '_wp_page_template', 'wfocu-canvas.php' );
			}
		}
	}
}

if ( ! function_exists( 'wfocu_update_general_setting_fields' ) ) {

	function wfocu_update_general_setting_fields() {

		$db_setting = array(
			'fb_pixel_key'            => 'fb_pixel_key',
			'ga_key'                  => 'ga_key',
			'gad_key'                 => 'gad_key',
			'gad_conversion_label'    => 'gad_conversion_label',
			'is_fb_purchase_event'    => 'is_fb_purchase_event',
			'is_fb_advanced_event'    => 'is_fb_advanced_event',
			'content_id_value'        => 'content_id_value',
			'content_id_variable'     => 'content_id_variable',
			'content_id_prefix'       => 'content_id_prefix',
			'content_id_suffix'       => 'content_id_suffix',
			'track_traffic_source'    => 'track_traffic_source',
			'exclude_from_total'      => 'exclude_from_total',
			'enable_general_event'    => 'enable_general_event',
			'general_event_name'      => 'GeneralEvent',
			'is_ga_purchase_event'    => 'is_ga_purchase_event',
			'is_gad_purchase_event'   => 'is_gad_purchase_event',
			'ga_track_traffic_source' => 'ga_track_traffic_source',
			'gad_exclude_from_total'  => 'gad_exclude_from_total',
			'id_prefix_gad'           => 'id_prefix_gad',
			'id_suffix_gad'           => 'id_suffix_gad',
			'offer_post_type_slug'    => 'wfocu_page_base',
		);

		$db_setting = apply_filters( 'wfocu_migrate_general_setting_field', $db_setting );

		$global_op  = get_option( 'wfocu_global_settings', [] );
		$general_op = get_option( 'bwf_gen_config', [] );

		foreach ( $db_setting as $key => $value ) {
			if ( isset( $global_op[ $key ] ) && ( ! isset( $general_op[ $value ] ) || empty( $general_op[ $value ] ) ) ) {
				$general_op[ $value ] = $global_op[ $key ];

			}
		}

		update_option( 'bwf_gen_config', $general_op, true );
	}

}


if ( ! function_exists( 'wfocu_update_general_setting_fields_3_5' ) ) {

	function wfocu_update_general_setting_fields_3_5() {


		$db_options = get_option( 'bwf_gen_config', [] );


		if ( ( isset( $db_options['track_traffic_source'] ) && 'yes' === $db_options['track_traffic_source'] ) || ( isset( $db_options['ga_track_traffic_source'] ) && 'yes' === $db_options['ga_track_traffic_source'] ) ) {
			$db_options['track_utms'] = '1';
			unset( $db_options['track_traffic_source'] );
			unset( $db_options['ga_track_traffic_source'] );
		}

		if ( ( isset( $db_options['content_id_prefix'] ) && '' !== $db_options['content_id_prefix'] ) ) {
			$db_options['pixel_content_id_prefix'] = $db_options['content_id_prefix'];
		}
		if ( ( isset( $db_options['content_id_variable'] ) && is_array( $db_options['content_id_variable'] ) && 'yes' === $db_options['content_id_variable'][0] ) ) {
			$db_options['pixel_variable_as_simple'] = '1';
		}

		if ( ( isset( $db_options['content_id_suffix'] ) && '' !== $db_options['content_id_suffix'] ) ) {
			$db_options['pixel_content_id_suffix'] = $db_options['content_id_suffix'];
		}

		if ( ( isset( $db_options['content_id_value'] ) && '' !== $db_options['content_id_value'] ) ) {
			$db_options['pixel_content_id_type'] = $db_options['content_id_value'];
		}


		update_option( 'bwf_gen_config', $db_options, true );
	}

}

// Method to check and set up the recurring schedule
if ( ! function_exists( 'wfocu_update_delete_duplicate_comments_3_6' ) ) {
	function wfocu_update_delete_duplicate_comments_3_6() {
		global $wpdb;

		// Select query to check if any rows match the criteria
		$select_query = "
            SELECT wc.comment_ID
            FROM {$wpdb->comments} wc
            JOIN (
                SELECT comment_post_ID, MIN(comment_ID) AS retained_comment_ID
                FROM {$wpdb->comments}
                WHERE comment_content LIKE '%Order charge successful in Stripe%'
                GROUP BY comment_post_ID
                HAVING COUNT(*) > 1
            ) AS subquery
            ON wc.comment_post_ID = subquery.comment_post_ID
            WHERE wc.comment_ID != subquery.retained_comment_ID
            AND wc.comment_content LIKE '%Order charge successful in Stripe%'
            LIMIT 1
        ";

		// Run the select query
		$rows = $wpdb->get_results( $select_query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// If no rows are found, return early
		if ( empty( $rows ) ) {
			WFOCU_Core()->log->log( 'No duplicate comments found. Recurring schedule not set up.' );

			return;
		}

		// Schedule the action if not already scheduled
		if ( ! wp_next_scheduled( 'wfocu_fkwcs_delete_duplicate_comments' ) ) {
			wp_schedule_event( time(), 'hourly', 'wfocu_fkwcs_delete_duplicate_comments' );
			WFOCU_Core()->log->log( 'Recurring schedule for deleting duplicate comments has been set up.' );


		}
	}
}

if ( ! function_exists( 'wfocu_update_sepa_trans_key_3_8' ) ) {

	function wfocu_update_sepa_trans_key_3_8() {

		$active_plugins = (array) get_option( 'active_plugins', [] );

		if ( is_multisite() ) {
			$active_sitewide_plugins = (array) get_site_option( 'active_sitewide_plugins', [] );
			$active_plugins          = array_merge( $active_plugins, array_keys( $active_sitewide_plugins ) );
		}

		$mollie_plugin_slug       = 'upstroke-woocommerce-one-click-upsell-mollie/upstroke-woocommerce-one-click-upsell-mollie.php';
		$mollie_plugin_not_active = in_array( $mollie_plugin_slug, $active_plugins, true );

		if ( $mollie_plugin_not_active ) {
			WFOCU_Core()->log->log( "Mollie plugin is active, updating options." );
			$db_options                       = get_option( 'wfocu_global_settings', [] );
			$db_options['sepa_gateway_trans'] = 'yes';
			$update_options                   = wp_parse_args( $db_options, WFOCU_Core()->data->get_options_defaults( $db_options ) );
			update_option( 'wfocu_global_settings', $update_options );
		} else {

			WFOCU_Core()->log->log( "not entered" );
		}


	}

}

if ( ! function_exists( 'wfocu_set_default_value_in_autoload_option' ) ) {
	function wfocu_set_default_value_in_autoload_option() {
		try {

			/**
			 * Return id option already set
			 */
			$g_setting = get_option( 'wfocu_global_settings', [] );
			if ( is_array( $g_setting ) && count( $g_setting ) > 0 ) {
				return;
			}

			$options = WFOCU_Core()->data->get_option( '' );
			if ( is_array( $options ) && isset( $options['gateways'] ) ) {
				$options = WFOCU_Core()->gateways->add_default_gateways_enable( $options );
			}
			WFOCU_Core()->data->update_options( $options );
		} catch ( Exception|Error $e ) {
			WFOCU_Core()->log->log( 'Default options did not set ' . $e->getMessage() );

		}
	}

}



