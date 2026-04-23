<?php

namespace FilterEverything\Filter\Pro\Admin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use FilterEverything\Filter\DefaultSettings;
use FilterEverything\Filter\Pro\Admin\ExportSettings;

class ImportSettings
{
    public $import_params;

    public $redirect_url;

    public $files;
    public $temp_prefix = 'tmp_';
    protected $exportSets = [];

    protected $export;

    public $file_field_name = 'wpc_filter_import_export';

    public $file_name = 'import_file';

    public $file_data = [];

    public $importOptions = [];

    public $success_imported_params = [];

    public $paramsToImport =
        [
            'filter_set',
            'filter_seo_rule',
            'options'
        ];

    public function __construct($import_params, $files, $redirect_url)
    {
        global $flrt_plugin;
        $this->import_params = $import_params;
        $this->files = $files;
        $this->redirect_url = $redirect_url;
        $this->changeMaxExecutionTime();
        $this->validate_data();
        $this->validate_params();
        $this->getExport();
        $this->createBackup();
        $this->insertImportData();
        $this->deleteTempBackupData();
        $flrt_plugin->resetTransitions();

        if(get_transient('success_imported_params') !== false){
            delete_transient('success_imported_params');
        }
        set_transient('success_imported_params', $this->success_imported_params, 300);
        $url = add_query_arg('flrt_import_success', 'import_success', $this->redirect_url);
        wp_redirect(esc_url_raw($url));
        exit;
    }

    //create backup in DB before import file
    protected function createBackup()
    {
        $this->exportSets = $this->getExport()->getSettings();
        foreach ($this->paramsToImport as $item) {
            $function_name = 'createTemp_' . $item;
            if (method_exists($this, $function_name)) {
                $this->$function_name($item);
            }
        }
        $this->exportSets = [];
    }

    public function getExport(){
        $export_params = [
            'export_filter_set' => 'on',
            'export_options'    => 'on',
            'export_seo_rule'   => 'on'
        ];
        $export = new ExportSettings($export_params);
        return $export;
    }

    protected function deleteTempBackupData()
    {
        foreach ($this->paramsToImport as $item) {
            $function_name = 'deleteTemp_' . $item;
            if (method_exists($this, $function_name)) {
                $this->$function_name($item);
            }
        }
    }

    protected function restoreBackupData()
    {
        foreach ($this->paramsToImport as $item) {
            $function_name = 'restoreTemp_' . $item;
            if (method_exists($this, $function_name)) {
                $this->$function_name($item);
            }
        }
    }

    protected function getPostsByPostType($post_type, $post_parent = '')
    {
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => $this->getExport()->post_statuses
        );
        if (!empty($post_parent)) {
            $args['post_parent'] = $post_parent;
        }
        return get_posts($args);
    }


    protected function restoreTemp_filter_set()
    {
        $filter_set_post_type = $this->temp_prefix . FLRT_FILTERS_SET_POST_TYPE;
        $filter_field_post_type = $this->temp_prefix . FLRT_FILTERS_POST_TYPE;
        $sets = $this->getPostsByPostType($filter_set_post_type);
        if ($sets) {
            $i = 1;
            foreach ($sets as $set) {
                $this->tickImportProgress( $i);
                $this->renamePostType($set->ID, '', FLRT_FILTERS_SET_POST_TYPE);
                $fields = $this->getPostsByPostType($filter_field_post_type, $set->ID);
                if ($fields) {
                    foreach ($fields as $field) {
                        $this->renamePostType($field->ID, '', FLRT_FILTERS_POST_TYPE);
                    }
                }
            }
        }
    }

    protected function restoreTemp_filter_seo_rule()
    {
        $filter_seo_rule_post_type = $this->temp_prefix . FLRT_SEO_RULES_POST_TYPE;
        $seo_rules = $this->getPostsByPostType($filter_seo_rule_post_type);
        if ($seo_rules) {
            $i = 1;
            foreach ($seo_rules as $rule) {
                $this->tickImportProgress($i);
                $this->renamePostType($rule->ID, '', FLRT_SEO_RULES_POST_TYPE);
            }
        }
    }

    protected function restoreTemp_options()
    {
        if (!empty($this->importOptions)) {
            foreach ($this->importOptions as $option_name) {
                $this->renameOptionName($this->temp_prefix . $option_name, $option_name);
            }
        }
    }

    protected function deleteTemp_filter_set()
    {
        $filter_set_post_type = $this->temp_prefix . FLRT_FILTERS_SET_POST_TYPE;
        $filter_field_post_type = $this->temp_prefix . FLRT_FILTERS_POST_TYPE;
        $sets = $this->getPostsByPostType($filter_set_post_type);
        if ($sets) {
            $i = 1;
            foreach ($sets as $set) {
                $this->tickImportProgress( $i);
                $fields = $this->getPostsByPostType($filter_field_post_type, $set->ID);
                if ($fields) {
                    foreach ($fields as $field) {
                        wp_delete_post($field->ID, true);
                    }
                }
                wp_delete_post($set->ID, true);
            }
        }
    }

    protected function deleteTemp_filter_seo_rule()
    {
        $filter_seo_rule_post_type = $this->temp_prefix . FLRT_SEO_RULES_POST_TYPE;
        $seo_rules = $this->getPostsByPostType($filter_seo_rule_post_type);
        if ($seo_rules) {
            $i = 1;
            foreach ($seo_rules as $rule) {
                $this->tickImportProgress( $i);
                wp_delete_post($rule->ID, true);
            }
        }
    }

    protected function deleteTemp_options()
    {
        if (!empty($this->importOptions)) {
            foreach ($this->importOptions as $option_name) {
                delete_option($this->temp_prefix . $option_name);
            }
        }
    }

    protected function renamePostType($post_id, $prefix = '', $name = '')
    {

        $post = get_post($post_id);
        if (!$post) {
            $this->error_redirect('import_error');
        }

        $new_post_type = $name;
        if (empty($name)) {
            $new_post_type = $prefix . $post->post_type;
        }
        $updated_post = array(
            'ID'        => $post_id,
            'post_type' => $new_post_type,
        );

        $result = wp_update_post($updated_post, true);

        if (is_wp_error($result)) {
            $this->error_redirect('import_error');
        }

        return true;
    }

    protected function renameOptionName($old_option_name, $new_option_name)
    {

        $old_option_value = get_option($old_option_name, false);

        if ($old_option_value !== false) {
            $new_option_value = get_option($new_option_name, false);

            if ($new_option_value !== false) {
                if (!delete_option($new_option_name)) {
                    $this->error_redirect('import_error');
                }
            }

            if (!add_option($new_option_name, $old_option_value)) {
                $this->error_redirect('import_error');
            }

            if (!delete_option($old_option_name)) {
                $this->error_redirect('import_error');
            }
            return true;
        }
        return false;
    }

    protected function createTemp_filter_set($item)
    {
        if (isset($this->exportSets[$item]) && !empty($this->exportSets[$item])) {
            $i = 1;
            foreach ($this->exportSets[$item] as $filter_set) {
                $this->tickImportProgress( $i);
                $this->renamePostType($filter_set['ID'], $this->temp_prefix);
                if (!empty($filter_set['filter_field'])) {
                    foreach ($filter_set['filter_field'] as $filter_field) {
                        $this->renamePostType($filter_field['ID'], $this->temp_prefix);
                    }
                }
            }
        }
    }

    protected function createTemp_filter_seo_rule($item)
    {
        $this->createTemp_filter_set($item);
    }

    protected function createTemp_options($item)
    {
        if (isset($this->exportSets[$item]) && !empty($this->exportSets[$item])) {
            $i = 1;
            foreach ($this->exportSets[$item] as $option_name => $option_value) {
                $this->tickImportProgress( $i);
                if(in_array($option_name, $this->importOptions)){
                    if (isset($this->file_data[$item][$option_name])) {
                        $this->renameOptionName($option_name, $this->temp_prefix . $option_name);
                    }
                }
            }
        }
    }

    public function validate_data()
    {
        $this->validate_uploaded_json_file();
    }

    public function validate_uploaded_json_file()
    {
        $file_field_name = $this->file_field_name;
        $file_name = $this->file_name;

        if (
            !isset($this->files[$file_field_name]) ||
            $this->files[$file_field_name]['error'][$file_name] !== UPLOAD_ERR_OK
        ) {
            $this->error_redirect('upload_file_error');
        }

        $file = $this->files[$file_field_name];
        $tmp_path = $file['tmp_name'][$file_name];
        $original_name = $file['name'][$file_name];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_path);
        finfo_close($finfo);

        $allowed_mime_types = ['application/json', 'text/plain'];
        if (!in_array($mime_type, $allowed_mime_types)) {
            $this->error_redirect('not_json_format');
        }

        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            $this->error_redirect('invalid_extension');
        }

        $content = file_get_contents($tmp_path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->error_redirect('invalid_json');
        }

        $this->file_data = $data;
        return true;
    }


    public function validate_params()
    {

        if (isset($this->import_params['import_filter_set'])
            && $this->import_params['import_filter_set'] === 'on'
            && !empty($this->file_data['filter_set'])
            && isset($this->file_data['options']['wpc_filter_permalinks'])
        )
        {
            $this->importOptions[] = 'wpc_filter_permalinks';
        }

        if (isset($this->import_params['import_filter_seo_rule'])
            && $this->import_params['import_filter_seo_rule'] === 'on'
            && !empty($this->file_data['filter_seo_rule'])
            && isset($this->file_data['options']['wpc_seo_rules_settings'])
            && isset($this->file_data['options']['wpc_indexing_deep_settings'])
        ) {
            array_push($this->importOptions, 'wpc_seo_rules_settings', 'wpc_indexing_deep_settings');
        }


        if (isset($this->import_params['import_options'])
            && $this->import_params['import_options'] === 'on'
            && !empty($this->file_data['options']['wpc_filter_settings'])
            && !empty($this->file_data['options']['wpc_filter_experimental'])
        )
        {
            array_push($this->importOptions, 'wpc_filter_settings', 'wpc_filter_experimental');
        }

        if(!empty($this->importOptions)){
            $this->import_params['import_options'] = 'on';
        }


        foreach ($this->import_params as $param_name => $param) {
            if ($param === 'on') {
                $import_param_name = explode('import_', $param_name)[1];
                if (!empty($import_param_name)) {
                    if (in_array($import_param_name, $this->paramsToImport)) {
                        $import_params[] = $import_param_name;
                    }
                }
            }
        }
        if (empty($this->importOptions)) {
            $this->error_redirect('empty_params');
        }

        if (!empty($import_params)) {
            foreach ($import_params as $param) {
                if (empty($this->file_data[$param])) {
                    $this->error_redirect('empty_in_import_' . $param);
                }
            }
            $this->paramsToImport = $import_params;
        }
    }

    public function insertImportData()
    {
        foreach ($this->paramsToImport as $item) {
            $function_name = 'import_' . $item;
            if (method_exists($this, $function_name)) {
                $this->$function_name($item);
            }
        }
    }

    public function import_filter_seo_rule($item)
    {
        $i = 1;
        foreach ($this->file_data[$item] as $key => $seo_rule_post) {
            $this->tickImportProgress( $i);
            $post_id = wp_insert_post($this->prepareImportPost($seo_rule_post));

            if (!is_wp_error($post_id)) {
                if (!empty($seo_rule_post['post_meta'])) {
                    foreach ($seo_rule_post['post_meta'] as $post_meta) {
                        $added_post_meta = add_post_meta($post_id, $post_meta['meta_key'], $post_meta['meta_value'], true);
                        if (!$added_post_meta) {
                            $this->error_redirect('import_error');
                        }
                    }
                }
            } else {
                $this->error_redirect('import_error');
            }
        }
        $this->success_imported_params[$item] = true;
    }

    public function import_options($item)
    {
        $defaultSettings = new DefaultSettings();
        foreach ($this->file_data[$item] as $option_name => $option) {
            if (!in_array($option_name, $this->importOptions)) {
                continue;
            }
            if (method_exists($defaultSettings, $option_name)) {
                foreach ($defaultSettings->$option_name() as $def_option_name => $def_option) {
                    if (is_array($def_option)) {
                        if (!isset($option[$def_option_name])) {
                            $option[$def_option_name] = $def_option;
                        }
                        if (isset($option[$def_option_name])) {
                            foreach ($def_option as $key => $def_op) {
                                if (!in_array($def_op, $option[$def_option_name])) {
                                    array_push($option[$def_option_name], $def_op);
                                }
                            }
                        }
                    } else {
                        if (!isset($option[$def_option_name])) {
                            $option[$def_option_name] = $def_option;
                        }
                    }
                }
            }
            if (get_option($option_name, false) !== false) {
                if (!delete_option($option_name)) {
                    $this->error_redirect('import_error');
                }
            }
            $is_added = add_option($option_name, $option);
            if (!$is_added) {
                $this->error_redirect('import_error');
            }else{
                $this->success_imported_params[$item] = true;
            }
        }
    }

    public function import_filter_set($item)
    {
        add_filter('pre_wp_unique_post_slug', 'flrt_force_non_unique_slug', 10, 2);

        $i = 1;
        foreach ($this->file_data[$item] as $key => $filter_set) {
            $this->tickImportProgress( $i);
            $post_id = wp_insert_post(
                $this->prepareImportPost(
                    $filter_set,
                    [
                        'post_content' => maybe_serialize($filter_set['post_content']),
                    ]
                )
            );
            if (!is_wp_error($post_id)) {
                if (!empty($filter_set['post_meta'])) {
                    foreach ($filter_set['post_meta'] as $post_meta) {
                        $added_post_meta = add_post_meta($post_id, $post_meta['meta_key'], $post_meta['meta_value'], true);
                        if (!$added_post_meta) {
                            $this->error_redirect('import_error');
                        }
                    }
                }

                if (!empty($filter_set['filter_field'])){
                    $parent_filters = [];
                    foreach ($filter_set['filter_field'] as $filter_field) {
                        $filter_field_post_id = wp_insert_post(
                            $this->prepareImportPost(
                                $filter_field,
                                [
                                    'post_parent'  => $post_id,
                                    'post_content' => maybe_serialize($filter_field['post_content'])
                                ]
                            ));
                        $post_content = $filter_field['post_content'];
                        if (!empty($post_content['parent_filter']) && (int) $post_content['parent_filter'] > 0) {
                            $parent_filters[$filter_field_post_id]['post_name'] = $filter_field['post_name'];
                            $parent_filters[$filter_field_post_id]['has_parent_filter_post_id'] = $filter_field_post_id;
                            $parent_filters[$filter_field_post_id]['parent_filter'] = $post_content['parent_filter'];
                        }
                        if (is_wp_error($filter_field_post_id)) {
                            $this->error_redirect('import_error');
                        }
                    }
                    if (!empty($parent_filters)) {
                        foreach ($parent_filters as $parent_filter) {
                            if (!empty($parent_filter['has_parent_filter_post_id']) && !empty($filter_set['filter_field'][$parent_filter['parent_filter']]['post_name'])) {
                                    $new_query = new \WP_Query([
                                        'post_type'   => 'filter-field',
                                        'post_parent' => $post_id,
                                        'post_status' =>  $this->getExport()->post_statuses,
                                        'name'        => $filter_set['filter_field'][$parent_filter['parent_filter']]['post_name'],
                                        'numberposts' => 1
                                    ]);
                                    if ($new_query->have_posts()) {
                                        $parent_filter_info = $new_query->posts[0];
                                        $post_new = get_post($parent_filter['has_parent_filter_post_id']);
                                        $post_new->post_content = maybe_unserialize($post_new->post_content);
                                        $post_new->post_content['parent_filter'] = (string) $parent_filter_info->ID;
                                        $post_new->post_content = maybe_serialize($post_new->post_content);
                                        wp_update_post(wp_slash($post_new));
                                    }
                            }
                        }
                    }
                }
                $this->success_imported_params[$item] = true;
            } else {
                $this->error_redirect('import_error');
            }
        }

        remove_filter('pre_wp_unique_post_slug', 'flrt_force_non_unique_slug', 10);
    }

    private function prepareImportPost($post, $options = [])
    {
        if (is_array($post)) {
            $post = (object)$post;
        }
        $new_post = [
            'post_author'           => wp_get_current_user()->ID,
            'post_date'             => $post->post_date,
            'post_content'          => $post->post_content,
            'post_title'            => $post->post_title,
            'post_excerpt'          => $post->post_excerpt,
            'post_status'           => $post->post_status,
            'comment_status'        => $post->comment_status,
            'ping_status'           => $post->ping_status,
            'post_password'         => $post->post_password,
            'post_name'             => $post->post_name,
            'to_ping'               => $post->to_ping,
            'pinged'                => $post->pinged,
            'post_parent'           => $post->post_parent,
            'post_content_filtered' => $post->post_content_filtered,
            'post_type'             => $post->post_type,
            'menu_order'            => $post->menu_order,
            'post_mime_type'        => $post->post_mime_type,
        ];

        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $new_post[$key] = $value;
            }
        }
        return $new_post;
    }

    private function error_redirect($error_query)
    {
        $this->restoreBackupData();
        wp_redirect(esc_url_raw($this->query_arg($error_query)));
        exit();
    }

    public function query_arg($arg)
    {
        return add_query_arg('flrt_import_error', $arg, $this->redirect_url);
    }

    protected function changeMaxExecutionTime(){
        $current_limit = ini_get('max_execution_time');

        if ($current_limit !== false) {
            $current_limit = (int) $current_limit;

            if ($current_limit === 0 || $current_limit >= 180) {
                return true;
            }
        }

        return $this->increaseScriptTimeLimit();
    }

    protected function increaseScriptTimeLimit(){
        if (function_exists('set_time_limit')) {
            try {
                @set_time_limit(120);
                return true;
            } catch (Throwable $e) {
                $this->error_redirect('max_execution_time_error');
            }
        }
        $this->error_redirect('max_execution_time_error');
        return false;
    }

    /**
     * Increments the batch counter and, every $batchSize items,
     * sets the success flag and extends the script time limit.
     *
     * @param int    $i           Counter passed by reference
     * @param int    $batchSize   Number of items per batch (default: 10)
     */
    protected function tickImportProgress(&$i, $batchSize = 10){
        $i++;
        if ($i == $batchSize) {
            $this->increaseScriptTimeLimit();
            $i = 1;
        }
    }
}