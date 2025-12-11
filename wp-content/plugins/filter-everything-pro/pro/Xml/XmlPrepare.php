<?php

namespace FilterEverything\Filter\Pro;

if (!defined('ABSPATH')) {
    exit;
}

use FilterEverything\Filter\Pro\WriteXml;

class XmlPrepare
{

    private $links = [];

    private $partOfUrl = '{any}';

    private $filter_sets = [];

    private $partSep = '-';

    public $file = '';


    private $permalinksSettings = [];


    public function __construct(){
        $this->getAllFilterSets();
        $this->getPermalinksSettings();
        $this->createLinksArray();
    }

    public function createXml(){
        $xml = new \FilterEverything\Filter\Pro\WriteXml($this->links);
        return $xml->file;
    }

    private function getAllFilterSets()
    {
        global $wpdb;
        $sql = "SELECT ID, post_title, post_content, post_excerpt, post_name
                FROM {$wpdb->posts}
                WHERE post_type = '%s'
                AND post_status = 'publish'";

        $query = $wpdb->prepare($sql, FLRT_FILTERS_SET_POST_TYPE);
        $filter_sets = $wpdb->get_results($query, ARRAY_A);
        $seo_rules_settings = get_option('wpc_seo_rules_settings');

        foreach ($filter_sets as $set) {
            $filter_fields = $this->getFilterSet($set['ID']);
            foreach ($filter_fields as $field) {
                $field['post_content'] = unserialize($field['post_content']);
                $check_param_string = $set['post_excerpt'] . ':' . $field['post_content']['entity'] . '#' . $field['post_content']['e_name'];
                if (isset($seo_rules_settings[$check_param_string]) &&
                    $seo_rules_settings[$check_param_string] === 'on') {
                    $this->filter_sets[$set['post_excerpt']][$set['ID']][] = $field['post_content']['e_name'];
                }
            }
        }
    }


    private function getFilterSet($post_id)
    {
        global $wpdb;
        $results = [];
        $sql = "SELECT ID, post_title, post_content, post_excerpt, post_name
                FROM {$wpdb->posts}
                WHERE post_parent = %d AND post_type = '%s'
                AND post_status = 'publish'";

        $query = $wpdb->prepare($sql, $post_id, FLRT_FILTERS_POST_TYPE);
        $results = $wpdb->get_results($query, ARRAY_A);
        return $results;
    }

    private function getSeoSettings()
    {
        global $wpdb;
        $sql = "SELECT ID, post_title, post_excerpt
                FROM {$wpdb->posts}
                WHERE post_type = '%s'
                AND post_status = 'publish'";

        $query = $wpdb->prepare($sql, FLRT_SEO_RULES_POST_TYPE);
        $filter_seo_rules = $wpdb->get_results($query, ARRAY_A);

        $url_settings = [];
        if (!empty($filter_seo_rules)) {
            foreach ($filter_seo_rules as $rule) {
                $rule['post_excerpt'] = unserialize($rule['post_excerpt']);
                $rule['rule_post_type'] = $rule['post_excerpt']['rule_post_type'];
                $url_settings[] = $rule;
            }
            return $url_settings;
        }
        return false;
    }

    private function getPermalinksSettings()
    {
        $allPermalinksSettings = get_option('wpc_filter_permalinks', []);
        $slug_taxonomy = [];
        if (!empty($allPermalinksSettings)) {
            foreach ($allPermalinksSettings as $taxonomy_param => $permalink_slug) {
                //explode taxonomy string. for example 'taxonomy#pa_brand'
                $taxonomy = explode('#', $taxonomy_param);
                if (isset($taxonomy[1])) $slug_taxonomy[$permalink_slug] = $taxonomy[1];
            }
        }
        if (flrt_is_woocommerce()) {
            $wc_permalink_structure = wc_get_permalink_structure();
            if (!empty($wc_permalink_structure)) {
                $wc_to_replace = array(
                    'category_base' => 'product_cat',
                    'tag_base'      => 'product_tag'
                );
                foreach ($wc_permalink_structure as $key => $wc_structure) {
                    if (!empty($wc_structure) && isset($wc_to_replace[$key])) {
                        $slug_taxonomy[$wc_structure] = $wc_to_replace[$key];
                    }

                }
            }
        }
        $this->permalinksSettings = $slug_taxonomy;
    }


    private function prepeareSeoRules()
    {
        $url_settings = $this->getSeoSettings();
        if ($url_settings) {
            foreach ($url_settings as $key => $seo_set) {
                foreach ($seo_set['post_excerpt'] as $param_key => $seo_param) {
                    foreach ($this->filter_sets[$seo_set['post_excerpt']['rule_post_type']] as $filter_id => $filter_set) {
                        if (in_array($param_key, $filter_set)) {
                            $url_settings[$key]['filter_sets'][$filter_id][] = $param_key;
                        }
                    }
                }
            }
            return $url_settings;
        }
        return false;
    }


    private function createLinksArray()
    {

        $seo_rules = $this->prepeareSeoRules();
        foreach ($seo_rules as $rule) {
            //var_dump($rule);
            if (mb_strpos($rule['post_title'], $this->partOfUrl) !== false) {
                foreach ($rule['filter_sets'] as $post_id => $filter_set) {
                    $post_id = (int)$post_id;
                    $temp[$post_id] = [];
                    $link_parts = explode('/', $rule['post_title']);
                    //remove from setting part '.../'
                    unset($link_parts[0]);
                    if (!empty($link_parts)) {
                        for ($i = 1; $i <= count($link_parts); $i++) {
                            $parts_of_array[$i] = [];
                        }
                    }
                    $check_array = [];
                    foreach ($link_parts as $key_part => $part) {
                        if (mb_strpos($part, $this->partOfUrl) !== false) {
                            if ($part === $this->partOfUrl) {
                                if(isset($this->permalinksSettings[$part])){
                                    $check_array[] = $this->permalinksSettings[$part];
                                }
                            }else{
                                $slug_first_part = explode($this->partSep . $this->partOfUrl, $part);
                                if (isset($slug_first_part[0])) {
                                    $check_array[] = $this->permalinksSettings[$slug_first_part[0]];
                                }
                            }
                        }
                    }
                    if($filter_set !== $check_array){
                        continue;
                    }

                    $level_parse_link = 0;
                    foreach ($link_parts as $key_part => $part) {
                        if (mb_strpos($part, $this->partOfUrl) !== false) {
                            if (!$level_parse_link) {
                                $level_parse_link = $key_part;
                            }
                            if ($part === $this->partOfUrl) {
                                $slug_for_any = $link_parts[$key_part - 1];
                                $post = get_post($post_id);
                                if ($post->post_name !== '1') {
                                    $category = explode('___', $post->post_name);
                                    if (isset($category[1])) {
                                        $id = (int)$category[1];
                                        if ($rule['rule_post_type'] == 'product' &&
                                            $this->permalinksSettings[$slug_for_any] == $category[0]) {
                                            $term = get_term($id, $category[0]);
                                            if (!is_wp_error($term) && $term) {
                                                $parts_of_array[$key_part] = $term->slug;
                                                // $temp[$post_id][] = $term->slug;
                                            }
                                        } else if ($category[0] === 'page' &&
                                            $this->permalinksSettings[$slug_for_any] == $category[0]) {
                                            $page_post = get_post($id);
                                            if (!is_wp_error($page_post) && $page_post) {
                                                $parts_of_array[$key_part] = $page_post->post_name;
                                                // $temp[$post_id][] = $page_post->post_name;
                                                var_dump($page_post->post_name);
                                                var_dump('$page_post->post_name');
                                            }
                                        }
                                    }
                                } else {
                                    $slug_parts = $this->getSlugParts($slug_for_any);
                                    if (!empty($slug_parts)) {
                                        $parts_of_array[$key_part] = $slug_parts;
                                    }
                                }
                            } else {
                                $slug_first_part = explode($this->partSep . $this->partOfUrl, $part);
                                if (isset($slug_first_part[0])) {
                                    if (in_array($this->permalinksSettings[$slug_first_part[0]], $rule['filter_sets'][$post_id])) {
                                        $slug_parts = $this->getSlugParts($slug_first_part[0], true);
                                        if (!empty($slug_parts)) {
                                            $parts_of_array[$key_part] = $slug_parts;
                                            //$temp[$post_id][] = $slug_parts;
                                        }
                                    }
                                }
                            }
                        } else {
                            $parts_of_array[$key_part] = $part;
                        }
                        if (count($parts_of_array) == $key_part) {
                            $this->createLinksFromArray($parts_of_array, $level_parse_link);
                            $parts_of_array = [];
                        }
                    }
                }
            } else {
                $link = $this->remove_three_dots($rule['post_title']);
                $this->links[$link] = '';
            }
        }
    }

    private function isAuthorSlug($slug)
    {
        global $wp_rewrite;
        if ($slug === $wp_rewrite->author_base) {
            return true;
        }
        return false;
    }

    private function getSlugParts($part, $prefix = false)
    {
        $slug_parts = [];

        if ($this->isAuthorSlug($part)) {
            $slug_parts = $this->getAuthorSlugs();
        } else {
            $slug_parts = $this->getAllParts($part, $prefix);
        }

        return $slug_parts;
    }

    private function getAuthorSlugs()
    {
        $slug_parts = [];
        $authors = get_users(array(
            'who'                 => 'authors',
            'has_published_posts' => true,
        ));
        foreach ($authors as $author) {
            $slug_parts[] = $author->user_nicename;
        }
        return $slug_parts;
    }

    private function getAllParts($part, $prefix)
    {
        // var_dump($part);
        global $wpdb;
        $slug_parts = [];
        $taxonomy_part = $this->permalinksSettings[$part];
        //var_dump($taxonomy);
        $sql = "SELECT t.slug
                FROM {$wpdb->term_taxonomy} tt
                LEFT JOIN {$wpdb->terms} t
                ON (tt.term_id = t.term_id)
                WHERE tt.taxonomy = '%s'
                AND t.slug != 'uncategorized'";

        $query = $wpdb->prepare($sql, $taxonomy_part);
        $results = $wpdb->get_results($query, ARRAY_A);

        if (!empty($results)) {
            foreach ($results as $taxonomy) {
                $slug_parts[] = ($prefix) ? $part . $this->partSep . $taxonomy['slug'] : $taxonomy['slug'];
            }
        }
        return $slug_parts;
    }


    private function createLinksFromArray($link_parts, $level_parse_link)
    {
        $hasEmpty = false;
        foreach ($link_parts as $item) {
            if (empty($item)) {
                $hasEmpty = true;
                break;
            }
        }
        $temp_link_array = [];
        $i = 0;
        if (!$hasEmpty) {
            foreach ($link_parts as $part) {
                $prev_level = $i - 1;
                if (is_array($part)) {
                    if (isset($temp_link_array['level_' . $prev_level])) {
                        foreach ($part as $p_slug) {
                            foreach ($temp_link_array['level_' . $prev_level] as $prev_slug) {
                                $temp_link_array['level_' . $i][] = '' . $prev_slug . '/' . $p_slug;
                            }
                        }
                    } else {
                        foreach ($part as $p_slug) {
                            $temp_link_array['level_' . $i][] = '/' . $p_slug;
                        }
                    }
                } else {
                    if (isset($temp_link_array['level_' . $prev_level])) {
                        foreach ($temp_link_array['level_' . $prev_level] as $prev_slug) {
                            $temp_link_array['level_' . $i][] = '' . $prev_slug . '/' . $part;
                        }
                    } else {
                        $temp_link_array['level_' . $i][] = '/' . $part;
                    }
                }
                $i++;
            }
            for ($i = $level_parse_link; $i <= count($temp_link_array); $i++) {
                foreach ($temp_link_array['level_' . $i] as $link) {
                    $this->links[$link] = '';
                }
            }
        }
    }

    private function remove_three_dots($string)
    {
        if (substr($string, 0, 3) === '...') {
            return substr($string, 3);
        }
        return $string;
    }
}