<?php

namespace FilterEverything\Filter\Pro\Admin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ExportSettings
{

    protected $params;

    protected $redirect_url;

    public $exportOptions = [
        'wpc_filter_settings',
        'wpc_indexing_deep_settings',
        'wpc_filter_permalinks',
        'wpc_seo_rules_settings',
        'wpc_filter_experimental'
    ];


    public $post_statuses = array('publish', 'pending', 'draft', 'future', 'private', 'trash', 'inherit');

    protected $settings = [];

    public function __construct($export_params = [], $redirect_url = false)
    {
        $this->params = $export_params;
        $this->redirect_url = $redirect_url;
    }

    protected function export()
    {
        $export_params = [];
        if (isset($this->params['export_filter_set']) && $this->params['export_filter_set'] === 'on') {
            $this->exportFilterSets();
            $post_filter_set = flrt_post_type_underline_transform(FLRT_FILTERS_SET_POST_TYPE);
            if (isset($this->settings[$post_filter_set]) && !empty($this->settings[$post_filter_set])) {
                $export_params[] = 'wpc_filter_permalinks';
            }
        }
        if (isset($this->params['export_seo_rule']) && $this->params['export_seo_rule'] === 'on') {
            $this->exportSeoRules();
            $post_filter_seo_rules = flrt_post_type_underline_transform(FLRT_SEO_RULES_POST_TYPE);
            if (isset($this->settings[$post_filter_seo_rules]) && !empty($this->settings[$post_filter_seo_rules])) {
                array_push($export_params, 'wpc_seo_rules_settings', 'wpc_indexing_deep_settings');
            }
        }
        if (isset($this->params['export_options']) && $this->params['export_options'] === 'on') {
            array_push($export_params, 'wpc_filter_settings', 'wpc_filter_experimental');
        }
        if (!empty($export_params)) {
            $this->exportOptions = $export_params;
            $this->exportSettings();
        }
    }

    protected function exportSettings()
    {
        foreach ($this->exportOptions as $option_name) {
            $option = get_option($option_name, false);
            if ($option !== false) {
                $this->settings['options'][$option_name] = $option;
            } else {
                $this->settings['options'][$option_name] = new \stdClass();
            }
        }
    }

    public function getFilterSets()
    {
        return get_posts(array(
            'post_type'      => FLRT_FILTERS_SET_POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => $this->post_statuses,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ));
    }

    protected function exportFilterSets()
    {

        $sets = $this->getFilterSets();


        if (empty($sets) && $this->redirect_url !== false) {
            $redirect_url = add_query_arg('flrt_export_error', 'empty_filter_set', $this->redirect_url);
            wp_redirect(esc_url_raw($redirect_url));
            exit();
        }

        $post_filter_set = flrt_post_type_underline_transform(FLRT_FILTERS_SET_POST_TYPE);
        $post_filter_field = flrt_post_type_underline_transform(FLRT_FILTERS_POST_TYPE);
        if ($sets) {
            foreach ($sets as $set) {
                $set->post_content = maybe_unserialize($set->post_content);
                $this->settings[$post_filter_set][$set->ID] = (array)$set;
                $this->settings[$post_filter_set][$set->ID]['post_meta'] = $this->exportPostMeta($set->ID, 'wpc_filter');
                $fields = get_posts(array(
                    'post_type'      => FLRT_FILTERS_POST_TYPE,
                    'posts_per_page' => -1,
                    'post_status'    => $this->post_statuses,
                    'post_parent'    => $set->ID,
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                ));
                foreach ($fields as $field) {
                    $field->post_content = maybe_unserialize($field->post_content);
                    $this->settings[$post_filter_set][$set->ID][$post_filter_field][$field->ID] = (array)$field;
                }
            }
        }
    }

    public function getSeoRules()
    {
        return get_posts(array(
            'post_type'      => FLRT_SEO_RULES_POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => $this->post_statuses,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ));
    }

    protected function exportSeoRules()
    {
        $seo_rules = $this->getSeoRules();

        if (empty($seo_rules) && $this->redirect_url !== false) {
            $redirect_url = add_query_arg('flrt_export_error', 'empty_seo_rules', $this->redirect_url);
            wp_redirect(esc_url_raw($redirect_url));
            exit();
        }

        $post_filter_seo_rules = flrt_post_type_underline_transform(FLRT_SEO_RULES_POST_TYPE);
        if ($seo_rules) {
            foreach ($seo_rules as $rule) {
                $this->settings[$post_filter_seo_rules][$rule->ID] = (array)$rule;
                $this->settings[$post_filter_seo_rules][$rule->ID]['post_meta'] = $this->exportPostMeta($rule->ID, 'wpc_seo_rule');
            }
        }
    }

    protected function exportPostMeta($post_id, $prefix)
    {
        global $wpdb;

        $sql = "SELECT meta_key, meta_value 
                    FROM {$wpdb->postmeta}
                    WHERE post_id = %d AND meta_key LIKE %s";

        $results = $wpdb->get_results($wpdb->prepare($sql, $post_id, $wpdb->esc_like($prefix) . '%'), ARRAY_A);


        if (!empty($results)) {
            foreach ($results as $key => $value) {
                $results[$key]['meta_value'] = maybe_unserialize($value['meta_value']);
            }
            return $results;
        }
        return [];
    }

    public function submit_download()
    {

        $this->export();

        $json = $this->settings;

        if (empty($json)) {
            return false;
        }

        $file_name = 'fe_';
        $file_name .= date('Y-m-d');
        if (isset($json['filter_set'])) {
            $file_name .= '_filters';
        }

        if (isset($json['filter_seo_rule'])) {
            $file_name .= '_seo';
        }

        if (isset($json['options'])) {
            $file_name .= '_settings';
        }
        $file_name .= '.json';

        header('Content-Description: File Transfer');
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$file_name}\"");
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\r\n";
        die;
    }

    public function getSettings()
    {
        $this->export();
        return $this->settings;
    }
}