<?php 

// If WPML is installed
if( defined('ICL_SITEPRESS_VERSION') ){
    add_filter( 'ubermenu_submenu_type', 'ubermenu_wpml_langswitcher_submenu_type', 10, 4 );

    // Menu Sync for v4.5.1 or later
    if( version_compare( ICL_SITEPRESS_VERSION, '4.5.1' ) >= 0 ){
        add_filter( 'wpml_sync_custom_field_copied_value', 'ubermenu_wpml_sync_custom_field_copied_value', 10, 5 );
    }
}

/**
 * Switch the submenu type for the auto-added WPML languague switcher item to Flyout
 */
function ubermenu_wpml_langswitcher_submenu_type( $submenu_type, $item, $depth, $args ){
    if( $item->type === 'wpml_ls_menu_item' ){
        $submenu_type = 'flyout';
    }
    return $submenu_type;
}


/**
 * Control which menu item settings fields get overridden during the WPML Menu Sync process
 */
function ubermenu_wpml_sync_custom_field_copied_value( $copied_value, $post_id_from, $post_id_to, $meta_key, $args ){
    if( $meta_key === UBERMENU_MENU_ITEM_META_KEY ){

        $values_to = $args['values_to'];
        $to_settings = maybe_unserialize( $values_to[0] );  // With UberMenu, all data will always be in [0] as there is only one piece of metadata associated with the key

        // List of fields that will *not* get copied over during the sync
        $excluded_settings = apply_filters( 'ubermenu_wpml_sync_excluded_menu_item_settings', [

            // General
            'custom_content',               // Custom Content
            'custom_url',                   // Custom URL
            'icon_title',                   // Icon Title
            'badge_content',                // Badge Content
            'submenu_footer_content',       // Submenu Footer Content
            'shiftnav_target',              // ShiftNav Toggle

            // Dynamic Posts
            'dp_post_parent',               // Post Parent
            'dp_exclude',                   // Exclude
            'dp_subcontent',                // Sub Content
            'dp_view_all_text',             // View All Text
            
            // Dynamic Terms
            'dt_parent',                    // Parent Term
            'dt_child_of',                  // Ancestor Term
            'dt_exclude',                   // Exclude Terms
            'dt_view_all_text',             // View All Text

            'empty_results_message',        // Empty Results Message
        ], $post_id_from, $post_id_to );

        $defaults = ubermenu_menu_item_setting_defaults();

        // error_log( print_r( $excluded_settings, true ) );

        // The $copied_values array contains all the values from the source language
        // For the Excluded fields above, we will replace the copied valued with the original
        // values from the destination language.  If values don't already exist, we'll replace with
        // the default values for those fields.
        foreach( $excluded_settings as $setting_id ){
            if( isset( $to_settings[$setting_id] ) ){
                $copied_value[$setting_id] = $to_settings[$setting_id];
            }
            else{
                $copied_value[$setting_id] = $defaults[$setting_id];
            }
        }


    }
    return $copied_value;
}
