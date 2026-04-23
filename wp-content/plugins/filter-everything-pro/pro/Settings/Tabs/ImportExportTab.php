<?php

namespace FilterEverything\Filter\Pro\Settings\Tabs;

if ( ! defined('ABSPATH') ) {
    exit;
}

use FilterEverything\Filter\BaseSettings;

class ImportExportTab extends BaseSettings
{
    protected $page = 'wpc-filter-import-export-settings';

    protected $group = 'wpc_filter_import_export';

    protected $optionName = 'wpc_filter_import_export';

    public function init()
    {
        add_action('admin_init', array($this, 'initSettings'));
        add_action('admin_notices', array($this, 'adminExportErrorNotice'));
        add_action('admin_notices', array($this, 'adminImportErrorNotice'));
        add_action( 'wpc_after_import_button_info', array( $this, 'backupMessage' ) );
    }

    public function initSettings()
    {

        $settings = array(
            'wpc_export_settings' => array(
                    'label'  => esc_html__('Export', 'filter-everything'),
                    'fields' => array(
                        'export_all'        => array(
                            'type'    => 'checkbox',
                            'id'      => 'export_all',
                            'label'   => esc_html__('All', 'filter-everything'),
                        ),
                        'export_seo_rule'    => array(
                            'type'  => 'checkbox',
                            'id'    => 'export_seo_rule',
                            'label' => esc_html__('SEO rules', 'filter-everything')
                        ),
                        'export_filter_set' => array(
                            'type'    => 'checkbox',
                            'id'      => 'export_filter_set',
                            'label'   => esc_html__('Filters and filter sets', 'filter-everything'),
                        ),
                        'export_options'    => array(
                            'type'    => 'checkbox',
                            'id'      => 'export_settings',
                            'label'   => esc_html__('Plugin settings', 'filter-everything'),
                            'checked' => true,
                        ),
                    )
                ),
            'wpc_import_settings' => array(
                'label'  => esc_html__('Import', 'filter-everything'),
                'fields' => array(
                    'import_file' => array(
                        'type'  => 'file',
                        'title' => esc_html__('Select File', 'filter-everything'),
                        'id'    => 'import_file',
                        'required' => 'required'
                    ),
                    'import_all' => array(
                        'type'  => 'checkbox',
                        'id'    => 'import_all',
                        'label' => esc_html__('All', 'filter-everything'),
                    ),
                    'import_filter_seo_rule' => array(
                        'type'  => 'checkbox',
                        'id'    => 'import_filter_seo_rule',
                        'label' => esc_html__('SEO rules', 'filter-everything')
                    ),
                    'import_filter_set' => array(
                        'type'  => 'checkbox',
                        'id'    => 'import_filter_set',
                        'label' => esc_html__('Filters and filter sets', 'filter-everything'),
                    ),
                    'import_options' => array(
                        'type'  => 'checkbox',
                        'id'    => 'import_options',
                        'label' => esc_html__('Plugin settings', 'filter-everything'),
                    ),
                )
            ),
        );

        $settings = apply_filters('wpc_import_export_filters_settings', $settings);

        $this->registerSettings($settings, $this->page, $this->optionName);
    }
    public function getLabel()
    {
        return esc_html__('Import/Export', 'filter-everything');
    }

    public function getName()
    {
        return 'import_export';
    }

    public function valid()
    {
        return true;
    }
    public function render()
    {

        settings_errors();

        do_action('wpc_before_import_export_sections_settings_fields', $this->page );

        echo '<div id="import-export-tab">';

        $this->doSettingsSections($this->page);

        echo "</div>";

        do_action('wpc_after_import_export_sections_settings_fields', $this->page );
    }
    public function doSettingsSections( $page ) {
        global $wp_settings_sections, $wp_settings_fields;

        if ( ! isset( $wp_settings_sections[ $page ] ) ) {
            return;
        }

        foreach ( (array) $wp_settings_sections[ $page ] as $section ) {


            do_action('wpc_import_export_before_settings_fields_title', $page );

            echo '<div class="import-export-block">';
            $icon = '';

            $tooltip = '';


            if ($section['id'] === 'wpc_export_settings') {
                $icon = flrt_export_setting_icon();
                $tooltip = flrt_tooltip(array(
                        'tooltip' => esc_html__('Export plugin data to a JSON file that you can import later.', 'filter-everything')
                    )
                );
            }

            if ($section['id'] === 'wpc_import_settings') {
                $icon = flrt_import_setting_icon();
                $tooltip = flrt_tooltip(array(
                        'tooltip' => esc_html__('Import plugin data from a previously exported JSON file.', 'filter-everything')
                    )
                );
            }
            if ( $section['title'] ) {
                echo "<div class='wpc-import-export-title'><h2>". $icon . wp_kses( $section['title'], array( 'span' => array( 'class' => true ) )) . "</h2>" . $tooltip ."</div>\n";
            }

            do_action('wpc_import_export_after_settings_fields_title', $page );

            if($section['id'] === 'wpc_import_settings') do_action('wpc_import_after_settings_fields_title', $page );

            if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
                continue;
            }

            $form_url = admin_url('admin.php?action=' . $section['id']);

            $enctype = '';
            if ($section['id'] == 'wpc_import_settings'){
                $enctype = 'enctype="multipart/form-data"';
            }
            print('<form method="post" class="form_' . $section['id'] . '" action="' . $form_url . '"' . $enctype . '>');
            wp_nonce_field($section['id'], $section['id']);
            $button_text = ($section['id'] === 'wpc_export_settings') ? esc_html__('Export data', 'filter-everything') : esc_html__('Import data', 'filter-everything');

            echo '<div class="wpc-import-export-block">';
            $this->doSettingsFields( $page, $section['id'] );
            echo '</div>';

            if($section['id'] === 'wpc_import_settings') do_action('wpc_import_button_info', $page );

            if( apply_filters('wpc_settings_submit_button', true ) ){
                submit_button($button_text);
            }
            if($section['id'] === 'wpc_import_settings') do_action('wpc_after_import_button_info', $page );
            print('</form>');
            echo "</div>";
        }
    }

    public function doSettingsFields( $page, $section ) {
        global $wp_settings_fields;

        if ( ! isset( $wp_settings_fields[ $page ][ $section ] ) ) {
            return;
        }

        $i = 1;

        if (($section === 'wpc_export_settings')){
            echo '<div class="wpc-export-file-setting-block"><span class="wpc-export-import-desc">' . esc_html__('Choose what to export', 'filter-everything') . '</span></div>';
        }

        if (($section === 'wpc_import_settings')) {
            $import_file_field = $wp_settings_fields[$page][$section]['import_file'];
            echo '<div class="wpc-import-file-setting-block"><span class="wpc-export-import-desc">' . esc_html($import_file_field['title']) . '</span><div>';
            call_user_func($import_file_field['callback'], $import_file_field['args']);
            echo '</div></div>';
            unset($wp_settings_fields[$page][$section]['import_file']);
        }


        echo '<div class="wpc-import-export-checkboxes">';
        foreach ( (array) $wp_settings_fields[ $page ][ $section ] as $field ) {
            $class = '';

            if( $field['id'] === 'bottom_widget_compatibility' ){
                if( flrt_get_option('mobile_filter_settings') === 'show_bottom_widget' ){
                    $field['args']['class'] .= ' wpc-opened';
                }
            }

            if( $field['id'] === 'color_swatches_taxonomies' || $field['id'] === 'rounded_swatches' ) {
                if( flrt_get_experimental_option('use_color_swatches') === 'on' ) {
                    $field['args']['class'] .= ' wpc-opened';
                }
            }

            if ( ! empty( $field['args']['class'] ) ) {
                $class = ' class="' . esc_attr( $field['args']['class'] ) . '"';
            }

            do_action('wpc_before_settings_field', $field );


            $tooltip = isset( $field['args']['tooltip'] ) ? flrt_tooltip( array( 'tooltip' => $field['args']['tooltip'] ) ) : '';
            $tooltip = wp_kses(
                $tooltip,
                array(
                    'strong' => array(),
                    'br'     => array(),
                    'a'      => array( 'href'=>true, 'title'=>true, 'class'=>true ),
                    'span'   => array( 'class'=>true, 'data-tip'=>true)
                )
            );

            echo '<div>';
            call_user_func( $field['callback'], $field['args'] );
            echo '</div>';

            do_action('wpc_after_settings_field', $field );
        }
        echo '</div>';
    }

    public function adminExportErrorNotice()
    {
        if (isset($_GET['flrt_export_error']) && !empty($_GET['flrt_export_error'])) {
            $error = $_GET['flrt_export_error'];
            switch ($error) {
                case 'empty':
                    flrt_view_admin_error(esc_html__('Error: No export options selected. Please choose at least one option before exporting data.', 'filter-everything'));
                    break;
                case 'empty_set':
                    flrt_view_admin_error(esc_html__("Error: No settings have been modified yet. There is nothing to export.", 'filter-everything'));
                    break;
                case 'no_edit_right':
                    flrt_view_admin_error(esc_html__('Error: Insufficient permissions to perform this action. Please check your user role or contact the site administrator.', 'filter-everything'));
                    break;
                case 'empty_seo_rules':
                    flrt_view_admin_error(esc_html__("Error: No SEO rules have been created yet. There is nothing to export.", 'filter-everything'));
                    break;
                case 'empty_filter_set':
                    flrt_view_admin_error(esc_html__("Error: No filter sets have been created yet. There is nothing to export.", 'filter-everything'));
                    break;
            }
        }
    }

    public function adminImportErrorNotice()
    {
        if (isset($_GET['flrt_import_error']) && !empty($_GET['flrt_import_error'])) {
            $error = $_GET['flrt_import_error'];
            switch ($error) {
                case 'empty_params':
                    flrt_view_admin_error(esc_html__('Error: No import options selected. Please choose at least one item to import.', 'filter-everything'));
                    break;
                case 'empty_file_input':
                    flrt_view_admin_error(esc_html__('Error: No file selected for import. Please choose a JSON file and try again.', 'filter-everything'));
                    break;
                case 'upload_file_error':
                    flrt_view_admin_error(esc_html__("Error: Import file upload failed. Please try again or check your server's file upload settings.", 'filter-everything'));
                    break;
                case 'not_json_format':
                    flrt_view_admin_error(esc_html__('Error: Unable to import data. The selected file is not a valid JSON export from this plugin.', 'filter-everything'));
                    break;
                case 'no_edit_right':
                    flrt_view_admin_error(esc_html__('Error: Insufficient permissions to perform this action. Please check your user role or contact the site administrator.', 'filter-everything'));
                    break;
                case 'invalid_extension':
                    flrt_view_admin_error(esc_html__('Error: The file must have the .json extension. Please select a valid JSON file and try again.', 'filter-everything'));
                    break;
                case 'invalid_json':
                    flrt_view_admin_error(esc_html__('Error: The file contains invalid JSON. Please check the file contents or export it again.', 'filter-everything'));
                    break;
                case 'empty_in_import_filter_set':
                    flrt_view_admin_error(esc_html__("Error: The import file doesn't contain any filter sets data or options. Please make sure you selected a valid export file that includes this data.", 'filter-everything'));
                    break;
                case 'empty_in_import_options':
                    flrt_view_admin_error(esc_html__("Error: The import file doesn't contain any settings data. Please make sure you selected a valid export file that includes plugin settings.", 'filter-everything'));
                    break;
                case 'empty_in_import_filter_seo_rule':
                    flrt_view_admin_error(esc_html__("Error: The import file doesn't contain any SEO rules data or options. Please make sure you selected a valid export file that includes SEO rules.", 'filter-everything'));
                    break;
                case 'import_error':
                    flrt_view_admin_error(esc_html__("Error: An unexpected error occurred. Please try again or contact support if the problem persists.", 'filter-everything'));
                    break;
                case 'max_execution_time_error':
                    flrt_view_admin_error(esc_html__("Error: The import process took too long and was stopped by the server. Please increase the PHP max_execution_time value or contact your hosting support.", 'filter-everything'));
                    break;
            }
        }
        if (!empty($_GET['flrt_import_success'])) {
            if($_GET['flrt_import_success'] == 'import_success'){
                $notice_str = '<div class="notice notice-success is-dismissible"><p>%s</p></div>';
                $success_imported_params = get_transient('success_imported_params');
                if($success_imported_params !== false){
                    delete_transient('success_imported_params');
                    $success_strings = [];
                    if(isset($success_imported_params['filter_set'])){
                        $success_strings[] = esc_html__("Filter sets", 'filter-everything');
                    }
                    if(isset($success_imported_params['filter_seo_rule'])){
                        $success_strings[] = esc_html__("SEO Rules", 'filter-everything');
                    }
                    if(isset($success_imported_params['options'])){
                        $success_strings[] = esc_html__("Settings", 'filter-everything');
                    }
                    if(!empty($success_strings)){
                        $success_text = implode(", ", $success_strings) . " " . esc_html__("were imported", 'filter-everything');
                        printf(
                            $notice_str,
                            $success_text
                        );
                    }
                }else{
                    printf(
                        $notice_str,
                        esc_html__("Import completed successfully.", 'filter-everything')
                    );
                }
            }

        }
    }
    public function backupMessage( $page )
    {
        if( $page === $this->page ){
            echo '<div class="wpc_before_import_export_blocks"><span class="wpc-alert-emoji">⚠️</span>' .esc_html__( 'Before importing, make a backup of your database to avoid data loss.', 'filter-everything' ).'</span></span></div>';
        }
    }
}
