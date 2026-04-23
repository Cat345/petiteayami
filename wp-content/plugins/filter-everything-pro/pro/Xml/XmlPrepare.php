<?php

namespace FilterEverything\Filter\Pro;

if (!defined('ABSPATH')) {
    exit;
}

use FilterEverything\Filter\Container;
use FilterEverything\Filter\Pro\WriteXml;
use FilterEverything\Filter\RequestParser;
use DOMDocument;
use DOMXPath;


if (!class_exists('XmlPrepare')):
    class XmlPrepare
    {
        protected $links = [];
        private $partOfUrl = '{any}';
        private $filter_sets = [];
        private $partSep = '-';

        public $file = '';

        public int $seo_rule_id = 0;

        public $existingSlugs = [];
        private $permalinksSettings = [];
        private $permalinksReversedSettings = [];
        public $seo_rules_settings = [];
        public $indexing_deep_settings = [];

        public $error;
        private $wpManager;

        private $pageHasQuery = [];

        private $pageHasNoQuery = [];

        public function __construct()
        {
            $this->wpManager = Container::instance()->getWpManager();
            $this->error = new \WP_Error();
            $this->deleteProgressTransient();

            if ($this->executeStep('checkSeoRulesSettings')) {
                return $this->error;
            }

            if ($this->executeStep('checkIndexingDeepSettings')) {
                return $this->error;
            }

            if ($this->executeStep('increaseScriptTimeLimit')) {
                return $this->error;
            }

            if ($this->executeStep('getAllFilterSets')) {
                return $this->error;
            }

            if ($this->executeStep('getPermalinksSettings')) {
                return $this->error;
            }

            $this->get_slugs();

            if ($this->executeStep('prepareSeoRules')) {
                return $this->error;
            }

            if ($this->executeStep('createTempTable')) {
                return $this->error;
            }

            $this->checkLinkTerms();

            if (empty($this->links) && empty($this->error->get_error_code())) {
                $this->error->add('empty_links', $this->errorNoticeTemplate(esc_html__("Error: No URLs were found for the XML sitemap. Please create filter sets and SEO rules before generating the XML sitemap again.", 'filter-everything')));
            }
        }

        protected function executeStep($methodName) {
            $this->$methodName();
            return !empty($this->error->get_error_code());
        }


        protected function checkSeoRulesSettings(){
            $this->seo_rules_settings = get_option('wpc_seo_rules_settings', false);

            if (empty($this->seo_rules_settings)) {
                $set_link = admin_url( 'edit.php?post_type=filter-set&page=filters-settings&tab=prefixes#wpc_seo_rules_post' );
                $error_text = sprintf(
                    "Error: No filters selected for indexing. Please select at least one filter for indexing in the %sIndexed Filters%s section and then try generating the XML sitemap again.",
                    '<a href="' . $set_link . '">',
                    '</a>'
                );
                $this->error->add('empty_seo_rules',
                    $this->errorNoticeTemplate(
                        wp_kses_post( __($error_text, 'filter-everything') )
                    )
                );
            }
        }

        protected function checkIndexingDeepSettings()
        {
            $indexing_deep_settings = [];
            $wpc_indexing_deep_settings = get_option('wpc_indexing_deep_settings');

            if (!empty($wpc_indexing_deep_settings)) {
                foreach ($wpc_indexing_deep_settings as $deep_setting_name => $val) {
                    if ((int)$val > 0) {
                        $indexing_deep_settings[$deep_setting_name] = (int)$val;
                    }
                }
                $this->indexing_deep_settings = $indexing_deep_settings;
            }

            if (empty($indexing_deep_settings)) {
                $set_link = admin_url( 'edit.php?post_type=filter-set&page=filters-settings&tab=prefixes#wpc_indexing_deep_post' );
                $error_text = sprintf(
                    "Error: No indexing depth settings have been configured. Please check your SEO settings and set the %sIndexing Depth%s before generating the sitemap.",
                    '<a href="' . $set_link . '">',
                    '</a>'
                );
                $this->error->add('empty_indexing_deep',
                    $this->errorNoticeTemplate(
                        wp_kses_post( __($error_text, 'filter-everything') )
                    )
                );
            }
        }
        private function checkLinkTerms()
        {
            $taxonomies = get_taxonomies([], 'objects');
            $taxonomySlugs = [];
            foreach ($taxonomies as $taxonomy_name => $taxonomy) {
                if (isset($taxonomy->rewrite) && is_array($taxonomy->rewrite) && !empty($taxonomy->rewrite['slug'])) {
                    $taxonomySlugs[$taxonomy->rewrite['slug']] = $taxonomy_name;
                }
            }


            $total_links = count($this->links);


            $step = (int) ceil(max(1, $total_links) / 10);
            $nextThreshold = $step;

            $part_number = 10;
            $i = 0;

            $this->writeProgressTransient($part_number);
            foreach ($this->links as $link => $seoRulePostId) {
                $i++;
                $requestParser = new RequestParser($link);
                if ($requestParser->detectFilterRequest()) {

                    $queryVars = $requestParser->getQueryVars();
                    $queriedFilters = $queryVars['queried_values'];
                    $indexedEnames  = [];
                    foreach ($queriedFilters as $filter) {
                        $indexedEnames[$filter['e_name']] = [];
                    }

                    $check_terms = [];

                    if (!empty($queryVars['non_filter_segments'])) {
                        if (isset($taxonomySlugs[$queryVars['non_filter_segments'][0]])) {
                            $taxonomy = $taxonomySlugs[$queryVars['non_filter_segments'][0]];
                            unset($queryVars['non_filter_segments'][0]);
                            foreach ($queryVars['non_filter_segments'] as $term) {
                                $check_terms['non_filter_segments_values'][] = [$taxonomy => $term];
                            }
                        }
                    }

                    foreach ($queriedFilters as $filter) {
                        if (!empty($filter['values'])) {
                            if(  isset( $indexedEnames[$filter['e_name']] ) ){
                                $indexedEnames[$filter['e_name']] = $filter['values'];
                            }
                            foreach ($filter['values'] as $term) {
                                $check_terms['filter_values'][] = [$filter['e_name'] => $term];
                            }
                        }
                    }

                    $has_terms = false;
                    $has_terms = $this->getTermsByUrlParams($check_terms);

                    if (!$has_terms) {
                        unset($this->links[$link]);
                    } else {
                        $indexedEnames = apply_filters('wpc_seo_indexed_filters_queried', $indexedEnames, $queriedFilters, $seoRulePostId);
                        foreach ($queriedFilters as $slug => $filter) {
                            foreach ($filter['values'] as $filter_val){
                                if( ! in_array( $filter_val, $indexedEnames[$filter['e_name']] ) ){
                                    unset($this->links[$link]);
                                }
                            }
                        }
                    }
                }

                if ($i >= $nextThreshold) {
                    $part_number += 10;
                    if ($part_number <= 100) {
                        $this->writeProgressTransient($part_number);
                    }
                    $nextThreshold += $step;
                }
            }

            if ($part_number < 100) {
                $this->writeProgressTransient(100);
            }
        }

        private function deleteProgressTransient(){
            if (get_transient('load_xml_progress') !== false) {
                delete_transient('load_xml_progress');
            }
        }

        private function writeProgressTransient($val)
        {
            $this->deleteProgressTransient();
            return set_transient('load_xml_progress', $val, 20 * MINUTE_IN_SECONDS);
        }


        private function getTermsByUrlParams($sqlParams)
        {

            if(empty($sqlParams['non_filter_segments_values']) && empty($sqlParams['filter_values'])){
                return false;
            }


            global $wpdb;


            $sql = "SELECT ID FROM tmp_terms";

            $temp_postsIDs = [];
            foreach ($sqlParams as $taxonomies) {
                foreach ($taxonomies as $term) {
                    $params = [];
                    $tmp_sql = $sql;
                    $tmp_sql .= ' WHERE ';
                    $e_name   = key($term);
                    $slug = reset($term);

                    if($e_name === 'author'){
                        $tmp_sql .= " user_nicename = %s";
                        $params[] = $slug;
                    }else{
                        $tmp_sql .= " (taxonomy = %s AND slug = %s)";
                        $params[] = $e_name;
                        $params[] = $slug;
                    }

                    if(!empty($temp_postsIDs)){
                        $temp_postsIDs = array_map('absint', array_unique($temp_postsIDs));

                        $placeholders = implode(', ', array_fill(0, count($temp_postsIDs), '%d'));
                        $tmp_sql .= " AND ID IN ($placeholders)";

                        array_push($params, ...$temp_postsIDs);

                    }
                    $query = $wpdb->prepare($tmp_sql, ...$params);
                    $results = $wpdb->get_results($query, ARRAY_A);
                    if(empty($results)){
                        return false;
                    }
                    $temp_postsIDs = [];

                    foreach ($results as $post) {
                        $temp_postsIDs[] = $post['ID'];
                    }

                }
                if(empty($temp_postsIDs)){
                    return false;
                }
            }
            $postsIDs = $temp_postsIDs;
            if(count($postsIDs) > 0){
                return true;
            }

            return false;

        }

        private function createTempTable()
        {
            global $wpdb;

            $createSql = "
                CREATE TEMPORARY TABLE tmp_terms (
                    ID BIGINT(20) UNSIGNED NOT NULL,
                    user_nicename VARCHAR(60) NOT NULL,
                    taxonomy VARCHAR(32) NOT NULL,
                    slug VARCHAR(200) NOT NULL,
                    PRIMARY KEY (ID, user_nicename, taxonomy, slug),
                    KEY idx_id (ID),
                    KEY idx_user (user_nicename),
                    KEY idx_tax (taxonomy),
                    KEY idx_slug (slug)
                ) ENGINE=MEMORY
            ";
            $wpdb->query($createSql);
            if ($wpdb->last_error) {
                $this->error->add('tmp_terms_create_failed', $this->errorNoticeTemplate(esc_html__('Failed to generate the XML sitemap. Please try again or contact support if the issue persists.', 'filter-everything')));
            }

            $insertSql = "
                INSERT INTO tmp_terms (ID, user_nicename, taxonomy, slug)
                SELECT DISTINCT p.ID, u.user_nicename, tt.taxonomy, t.slug
                FROM {$wpdb->posts} p
                JOIN {$wpdb->users} u ON p.post_author = u.ID
                JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                WHERE p.post_status = 'publish'
                
                UNION
                
                SELECT DISTINCT p.ID, u.user_nicename, tt_parent.taxonomy, t_parent.slug
                FROM {$wpdb->posts} p
                JOIN {$wpdb->users} u ON p.post_author = u.ID
                JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN {$wpdb->term_taxonomy} tt_parent ON tt.parent = tt_parent.term_id
                JOIN {$wpdb->terms} t_parent ON t_parent.term_id = tt_parent.term_id
                WHERE p.post_status = 'publish'
            ";
            $wpdb->query($insertSql);
            if ($wpdb->last_error) {
                $this->error->add('tmp_terms_create_failed', $this->errorNoticeTemplate(esc_html__('Failed to generate the XML sitemap. Please try again or contact support if the issue persists.', 'filter-everything')));
            }
        }


        public function createXml()
        {
            $xml = new WriteXml();
            $xml->createXmlFiles($this->links);
            if (!empty($xml->error->get_error_code())) {
                foreach ($xml->error->errors as $code => $xml_file_error) {
                    $this->error->add($code, $this->errorNoticeTemplate($xml_file_error[0]));
                }
            }
            if(get_transient('load_xml_progress') !== false){
                delete_transient('load_xml_progress');
            }
            return $xml->file;
        }

        protected function getAllFilterSets()
        {
            global $wpdb;
            $sql = "SELECT ID, post_title, post_content, post_excerpt, post_name
                FROM {$wpdb->posts}
                WHERE post_type = '%s'
                AND post_status = 'publish'";

            $query = $wpdb->prepare($sql, FLRT_FILTERS_SET_POST_TYPE);
            $filter_sets = $wpdb->get_results($query, ARRAY_A);


            if (!empty($filter_sets)) {
                foreach ($filter_sets as $set) {
                    $set['post_content'] = maybe_unserialize($set['post_content']);
                    if((empty($set['post_content']['wp_filter_query']) || $set['post_content']['wp_filter_query'] === '-1')
                        &&
                        (!empty($set['post_content']['wp_page_type']) && $set['post_content']['wp_page_type'] === 'post_type___page')){
                        continue;
                    }
                    $this->filter_sets[$set['ID']] = $set;
                    $filter_fields = $this->getFilterFields($set['ID']);
                    if (!empty($filter_fields)) {
                        foreach ($filter_fields as $field) {
                            $field['post_content'] = unserialize($field['post_content']);
                            $this->filter_sets[$set['ID']]['filter_fields'][$field['ID']] = $field;
                        }
                    }
                }
            } else {
                $this->error->add(
                    'empty_filter_sets',
                    $this->errorNoticeTemplate(esc_html__("Error: No Filter Sets found. Please create at least one Filter Set and SEO Rule, then try generating the XML sitemap again.", 'filter-everything')));
            }
        }


        private function getFilterFields($post_id)
        {
            global $wpdb;
            $sql = "SELECT ID, post_title, post_content, post_excerpt, post_name
                    FROM {$wpdb->posts}
                    WHERE post_parent = %d AND post_type = '%s'
                    AND post_status = 'publish'";

            $query = $wpdb->prepare($sql, $post_id, FLRT_FILTERS_POST_TYPE);
            return $wpdb->get_results($query, ARRAY_A);
        }

        private function getSeoSettings()
        {
            global $wpdb;
            $sql = "SELECT ID, post_title, post_excerpt
                FROM {$wpdb->posts}
                WHERE post_type = '%s'
                AND post_status = 'publish'";

            if($this->seo_rule_id !== 0){
                $sql .= " AND ID = %d";
            }

            $query = $wpdb->prepare($sql, FLRT_SEO_RULES_POST_TYPE, $this->seo_rule_id);
            $filter_seo_rules = $wpdb->get_results($query, ARRAY_A);

            $seo_rules = [];
            if (!empty($filter_seo_rules)) {
                foreach ($filter_seo_rules as $rule) {
                    $rule['post_excerpt'] = unserialize($rule['post_excerpt']);
                    $rule['post_excerpt_temp'] = $rule['post_excerpt'];
                    unset($rule['post_excerpt_temp']['rule_post_type']);
                    unset($rule['post_excerpt_temp']['rule_seo_title']);
                    unset($rule['post_excerpt_temp']['rule_meta_desc']);
                    unset($rule['post_excerpt_temp']['rule_h1']);
                    unset($rule['post_excerpt_temp']['wp_entity']);
                    $index_deep_name = $rule['post_excerpt']['rule_post_type'] . '_index_deep';
                    $rule['rule_post_type'] = $rule['post_excerpt']['rule_post_type'];
                    if(isset($this->indexing_deep_settings[$index_deep_name])){
                        if (count($rule['post_excerpt_temp']) <= $this->indexing_deep_settings[$index_deep_name]) {
                            $seo_rules[] = $rule;
                        }
                    }
                }
                return $seo_rules;
            } else {
                $this->error->add('empty_seo_rules', $this->errorNoticeTemplate(esc_html__("Error: No SEO rules found. Please create at least one SEO rule and then try generating the XML sitemap again.", 'filter-everything')));
            }
            return false;
        }

        protected function getPermalinksSettings()
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
            if (!empty($slug_taxonomy)) {
                $this->permalinksSettings = $slug_taxonomy;
                $this->permalinksReversedSettings = array_flip($slug_taxonomy);
            } else {
                $this->error->add('empty_seo_rules',
                    $this->errorNoticeTemplate(esc_html__("Error: No URL prefixes have been configured. Please add at least one URL prefix in the URL Prefixes section and then try generating the XML sitemap again.", 'filter-everything')));
            }
        }


        protected function prepareSeoRules()
        {
            if (!empty($this->filter_sets)) {
                $seo_rules = $this->getSeoSettings();
                if($this->error->get_error_message('empty_seo_rules')) {
                    $xml = new WriteXml();
                    wpc_clear_folder($xml->directory);
                    return false;
                }
                foreach ($seo_rules as $rule) {
                    $seoRulePostId = $rule['ID'];
                    foreach ($this->filter_sets as $filter_set) {
                        $parts = [];
                        if ($rule['post_excerpt']['rule_post_type'] === $filter_set['post_excerpt']) {
                            $check_array = [];
                            foreach ($filter_set['filter_fields'] as $filter_field) {

                                $check_seo_rules_setting_string = $filter_set['post_excerpt'] . ":" . $filter_field['post_content']['entity'] . '#' . $filter_field['post_content']['e_name'];
                                if (isset($rule['post_excerpt_temp'][$filter_field['post_content']['e_name']])) {
                                    if (!empty($this->seo_rules_settings[$check_seo_rules_setting_string])
                                        && $this->seo_rules_settings[$check_seo_rules_setting_string] === 'on'
                                    ) {
                                        $check_array[] = $filter_field['post_content']['e_name'];
                                    }
                                }
                            }

                            if (count($check_array) !== count($rule['post_excerpt_temp'])) {
                                continue;
                            }

                            if (!empty($rule['post_excerpt']['wp_entity'])
                                &&
                                !empty($filter_set['post_content']['wp_page_type'])
                                &&
                                $filter_set['post_content']['wp_page_type'] !== 'common___common')
                            {
                                $filter_set_entity = explode('___', $filter_set['post_name']);
                                $filter_set_entity_name = $filter_set_entity[0];
                                $filter_set_entity_id = $filter_set_entity[1];
                                $wp_entity = explode(':', $rule['post_excerpt']['wp_entity']);
                                $wp_entity_name = $wp_entity[0];
                                $wp_entity_id = $wp_entity[1];
                                if ($filter_set_entity_name == $wp_entity_name) {
                                    if (($wp_entity_id == '-1' || $filter_set_entity_id == '-1') || $wp_entity_id == $filter_set_entity_id) {

                                        $entity_array = [];

                                        if ($wp_entity_id == '-1' && $filter_set_entity_id == '-1') {
                                            $entity_array[$wp_entity_name] = $wp_entity_id;
                                        }

                                        if ($wp_entity_id == $filter_set_entity_id) {
                                            $entity_array[$wp_entity_name] = $wp_entity_id;
                                        }

                                        if ($wp_entity_id != '-1' || $filter_set_entity_id != '-1') {
                                            if ($wp_entity_id != '-1') {
                                                $entity_array[$wp_entity_name] = $wp_entity_id;
                                            } elseif ($filter_set_entity_id != '-1') {
                                                $entity_array[$filter_set_entity_name] = $filter_set_entity_id;
                                            }
                                        }

                                        if (empty($entity_array)) {
                                            continue;
                                        }


                                        $post_name = key($entity_array) . '___' . current($entity_array);
                                        $url_rule = array_merge($entity_array, $rule['post_excerpt_temp']);

                                        $temp_filter_set = $filter_set;
                                        $temp_filter_set['post_name'] = $post_name;
                                        $temp_parts = $this->getPageSlugs($temp_filter_set);
                                        if(empty($temp_parts)) continue;
                                        $parts[] = $temp_parts;
                                        unset($temp_parts);
                                        unset($temp_filter_set);

                                        foreach ($rule['post_excerpt_temp'] as $taxonomy_name => $taxonomy_id){
                                            $parts[] = $this->getSlugParts($taxonomy_name, $taxonomy_id);
                                        }
                                        $this->createLinksFromArray($parts, $seoRulePostId);
                                        $parts = [];
                                    }
                                }
                            }

                            if (!empty($filter_set['post_content']['wp_page_type'])
                                && $filter_set['post_content']['wp_page_type'] === 'common___common'
                                && $filter_set['post_name'] === '1'
                            ) {
                                if(!empty($rule['post_excerpt']['wp_entity'])){
                                    $wp_entity = explode(':', $rule['post_excerpt']['wp_entity']);
                                    $wp_entity_name = $wp_entity[0];
                                    $wp_entity_id = $wp_entity[1];
                                    $temp_parts = $this->getPageSlugs($filter_set, $wp_entity_name, $wp_entity_id);
                                }else{
                                    $temp_parts = $this->getPageSlugs($filter_set);
                                }
                                if(empty($temp_parts)) continue;
                                $parts[] = $temp_parts;
                                unset($temp_parts);
                                foreach ($rule['post_excerpt_temp'] as $taxonomy_name => $taxonomy_id){
                                    $parts[] = $this->getSlugParts($taxonomy_name, $taxonomy_id);
                                }
                                $this->createLinksFromArray($parts, $seoRulePostId);
                                $parts = [];
                            }

                            if (empty($rule['post_excerpt']['wp_entity'])) {
                                if((!empty($filter_set['post_content']['wp_page_type']) && mb_strpos($filter_set['post_content']['wp_page_type'], 'taxonomy') === false)
                                    ||
                                    !empty($rule['post_excerpt']['author'])
                                ){
                                    $temp_parts = $this->getPageSlugs($filter_set);
                                    if(empty($temp_parts)) continue;
                                    $parts[] = $temp_parts;
                                    unset($temp_parts);
                                    foreach ($rule['post_excerpt_temp'] as $taxonomy_name => $taxonomy_id){
                                        $parts[] = $this->getSlugParts($taxonomy_name, $taxonomy_id);
                                    }

                                    $this->createLinksFromArray($parts, $seoRulePostId);
                                    $parts = [];
                                }
                            }
                        }
                    }
                }
            }
            return false;
        }

        private function isAuthorSlug($slug)
        {
            global $wp_rewrite;
            if ($slug === $wp_rewrite->author_base) {
                return true;
            }
            return false;
        }

        private function getSlugParts($taxonomy_name, $taxonomy_id)
        {
            $slug_parts = [];

            if ($this->isAuthorSlug($taxonomy_name)) {
                $slug_parts = $this->getAuthorSlugs($taxonomy_name, $taxonomy_id);
            } else {
                $slug_parts = $this->getAllParts($taxonomy_name, $taxonomy_id);
            }

            return $slug_parts;
        }

        private function getAuthorSlugs($taxonomy_name, $author_id)
        {
            $slug_parts = [];
            if($author_id == '-1'){
                $authors = get_users(array(
                    'who'                 => 'authors',
                    'has_published_posts' => true,
                ));
                foreach ($authors as $author) {
                    if(!empty($this->permalinksReversedSettings[$taxonomy_name])){
                        $slug_parts[] = $this->permalinksReversedSettings[$taxonomy_name] . $this->partSep . $author->user_nicename;
                    }

                }
            }else{
                $author = get_user_by('id', $author_id);
                if ($author) {
                    if(!empty($this->permalinksReversedSettings[$taxonomy_name])) {
                        $slug_parts[] = $this->permalinksReversedSettings[$taxonomy_name] . $this->partSep . $author->user_nicename;
                    }
                }
            }

            return $slug_parts;
        }

        private function getAllParts($taxonomy_name, $taxonomy_id)
        {
            global $wpdb;
            $slug_parts = [];

            $sql = "SELECT t.slug
            FROM {$wpdb->term_taxonomy} tt
            LEFT JOIN {$wpdb->terms} t
            ON (tt.term_id = t.term_id)
            WHERE tt.taxonomy = %s
            AND t.slug != 'uncategorized'";

            $params = [$taxonomy_name];

            if ($taxonomy_id != '-1') {
                $sql .= " AND tt.term_id = %d";
                $params[] = (int)$taxonomy_id;
            }

            $query = $wpdb->prepare($sql, ...$params);
            $results = $wpdb->get_results($query, ARRAY_A);

            if (!empty($results)) {
                foreach ($results as $taxonomy) {
                    if(!empty($this->permalinksReversedSettings[$taxonomy_name])){
                        $slug_parts[] = $this->permalinksReversedSettings[$taxonomy_name] . $this->partSep . $taxonomy['slug'];
                    }
                }
                return $slug_parts;
            }

            return [];
        }


        private function createLinksFromArray($link_parts, $seoRulePostId)
        {
            $filtered = array_filter($link_parts, function ($item) {
                return !empty($item);
            });

            if($this->seo_rule_id !== 0 && $this->seo_rule_id == $seoRulePostId){
                if(count($this->links) > 1){
                    reset($this->links);
                    $key = key($this->links);
                    if($this->links[$key] === $seoRulePostId){
                        return;
                    }
                }
            }

            $link_parts = array_values($filtered);
            $temp_link_array = [];
            $i = 1;
            $link_deep_count = count($link_parts);
            if (!empty($link_parts)) {
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
                for ($i = $link_deep_count; $i <= count($temp_link_array); $i++) {
                    foreach ($temp_link_array['level_' . $i] as $link) {
                        $link_last_sug = explode('/', $link);
                        $link_last_sug = end($link_last_sug);
                        if (!in_array($link_last_sug, $this->existingSlugs) && !isset($this->permalinksSettings[$link_last_sug])) {
                            $link = str_replace('//', '/', $link);
                            $this->links[$link] = $seoRulePostId;
                        }
                    }
                }
            }
        }

        protected function get_slugs()
        {
            $taxonomies = get_taxonomies([], 'names');
            $all_taxonomies = [];
            foreach ($taxonomies as $taxonomy) {
                $all_taxonomies[] = $taxonomy;
            }

            $terms = get_terms(['taxonomy' => $all_taxonomies, 'exclude' => $this->removeUncategorizedTerms()]);
            foreach ($terms as $term) {
                $this->existingSlugs[] = $term->slug;
            }
        }


        private function getPageSlugs($post, $wp_entity_name = '', $wp_entity_id = '')
        {
            $post = (array)$post;
            $post['post_content'] = maybe_unserialize($post['post_content']);

            if (!empty($post['post_content']['wp_page_type'])) {
                $filter_page = explode("___", $post['post_name']);
                $filter_page_type = explode("___", $post['post_content']['wp_page_type']);
                if ($post['post_content']['wp_page_type'] == 'common___common' && empty($wp_entity_name) /*&& $post['post_name'] !== '1'*/) {
                    $common_pages = array(
                        'page_on_front',
                        'page_for_posts',
                        'show_on_front',
                        'shop_page');
                    $slug_parts = [];
                    foreach ($common_pages as $common_page) {
                        if ($common_page == 'shop_page') {
                            $page_id = wc_get_page_id('shop');
                        } else {
                            $page_id = get_option($common_page);
                        }
                        if (!empty($page_id) && (int) $page_id != 0) {
                            $post_info = get_post($page_id);
                            if ($post_info->post_name == 'shop' || $post_info->post_name == 'products') {
                                $post_type = 'product';
                            } else {
                                $post_type = $this->getPostType($post_info->post_name);
                            }

                            if(empty($post_type)){
                                $post_type = $this->getPostType($page_id);
                            }

                            if (!empty($post_type) && $post['post_excerpt'] == $post_type) {
                                $url = $this->toRelativePath( get_permalink($page_id) );
                                $slug_parts[] = $url;
                            }
                        }
                    }
                    return $slug_parts;
                }

                if($post['post_content']['wp_page_type'] == 'common___common'
                    && $post['post_name'] === '1'
                    && !empty($wp_entity_name)
                ){
                    return $this->getArchiveSlugsByPostType($post['post_excerpt'], $wp_entity_name, $wp_entity_id);
                }

                if (!empty($filter_page_type['0']) && $filter_page_type['0'] == 'post_type') {
                    if (!empty($filter_page[0]) && !empty($filter_page[1])) {
                        return $this->getPostsSlug($filter_page[0], $filter_page[1], $post);
                    }
                }

                if (!empty($filter_page_type['0']) && $filter_page_type['0'] == 'taxonomy') {
                    if (!empty($filter_page[0]) && !empty($filter_page[1])) {
                        return $this->getTaxonomySlugs($filter_page[0], $filter_page[1]);
                    }
                }

                if (!empty($filter_page_type['0']) && $filter_page_type['0'] == 'author') {
                    if (!empty($filter_page[1])) {
                        return $this->getAuthorPageSlug($filter_page_type['0'], $filter_page[1]);
                    }
                }
            }
            return false;
        }

        private function getPostsSlug($post_type, $post_id, $filter_set)
        {
            $slug_parts = [];
            if ($post_id == '-1') {
                $posts = get_posts(
                    array(
                        'numberposts' => -1,
                        'post_type'   => $post_type,
                        'post_status' => 'publish',
                    )
                );
                if ($posts) {
                    foreach ($posts as $post) {
                        $slug_parts = $this->getPageQuerySlugs($post, $filter_set, $slug_parts);
                    }
                }
            } else {
                $post = get_post($post_id);
                if(!empty($post)){
                    $slug_parts = $this->getPageQuerySlugs($post, $filter_set, $slug_parts);
                }
            }
            return $slug_parts;
        }

        private function getAuthorPageSlug($taxonomy, $author_id)
        {
            $slug_parts = [];
            if ($author_id == '-1') {
                $author_slugs = $this->getAuthorSlugs($taxonomy, $author_id);
                if (!empty($author_slugs)) $slug_parts[] = $author_slugs;
            } else {
                $author = get_user_by('id', $author_id);
                if ($author) {
                    $slug_parts[] = $this->toRelativePath( get_author_posts_url($author_id));
                }
            }
            return $slug_parts;
        }

        private function getTaxonomySlugs($taxonomy, $taxonomy_id)
        {
            $slug_parts = [];
            if ($taxonomy_id == '-1') {
                $terms = get_terms([
                    'taxonomy'   => $taxonomy,
                    'number'     => 0,
                    'hide_empty' => false,
                    'exclude' => $this->removeUncategorizedTerms()
                ]);
                if (!empty($terms)) {
                    foreach ($terms as $term) {
                        $slug_parts[] = $this->toRelativePath(get_term_link($term));
                    }
                }
            } else {
                $term = get_term($taxonomy_id, $taxonomy);
                if ($term && !is_wp_error($term)) {
                    $slug_parts[] = $this->toRelativePath( get_term_link($term) );

                }
            }
            return $slug_parts;
        }

        public function errorNoticeTemplate($text)
        {
            return '<div class="wpc-error notice notice-error is-dismissible flrt-notice">
                      <p>' . $text . '</p>
                      <button type="button" class="notice-dismiss">
                      <span class="screen-reader-text">' . esc_html__('Dismiss this notice.') . '</span>
                      </button>
                     </div>';

        }

        private function getPostType($plural)
        {
            $plural = 'posts';
            $post_types = get_post_types([], 'objects');
            foreach ($post_types as $post_type => $post_type_obj) {
                if (strtolower($post_type_obj->labels->name) === strtolower($plural)) {
                    return $post_type;
                }
            }
            return false;
        }

        protected function increaseScriptTimeLimit(){
            $disabled = ini_get('disable_functions');
            $disabledList = is_string($disabled) && $disabled !== '' ? array_map('trim', explode(',', $disabled)) : [];

            $current_limit = ini_get('max_execution_time');

            if ($current_limit === false) {
                if (isset($this->error)) {
                    $this->error->add(
                        'max_execution_time_unavailable',
                        $this->errorNoticeTemplate(
                            esc_html__("Unable to determine the server execution time limit (max_execution_time).", 'filter-everything')
                        )
                    );
                }
                return false;
            }

            $current_limit = (int) $current_limit;

            if ($current_limit === 0) {
                return true;
            }

            if ($current_limit >= 450) {
                return true;
            }

            if (!function_exists('set_time_limit') || in_array('set_time_limit', $disabledList, true)) {
                if (isset($this->error)) {
                    $message = esc_html__('Error: The XML sitemap generation took too long and was stopped by the server. Please increase the PHP max_execution_time value or contact your hosting support.', 'filter-everything');
                    $this->error->add(
                        'set_time_limit_unavailable',
                        $this->errorNoticeTemplate($message)
                    );
                }
                return false;
            }

            try {
                set_time_limit(1800);
                $new_limit = ini_get('max_execution_time');
                if ($new_limit !== false && (int)$new_limit > 0 && (int)$new_limit < 1800) {
                    if (isset($this->error)) {
                        $this->error->add(
                            'set_time_limit_restricted',
                            $this->errorNoticeTemplate(
                                sprintf(
                                    esc_html__("The XML sitemap generation exceeded the allowed execution time (%d seconds). Your server may apply stricter limits. Please increase the time limit or contact your hosting provider.", 'filter-everything'),
                                    (int)$new_limit
                                )
                            )
                        );
                    }
                }

                return true;
            } catch (\Throwable $e) {
                if (isset($this->error)) {
                    $this->error->add(
                        'set_time_limit_failed',
                        $this->errorNoticeTemplate(
                            esc_html__('Error: The XML sitemap generation took too long and was stopped by the server. Please increase the PHP max_execution_time value or contact your hosting support.', 'filter-everything'),
                        )
                    );
                }
                return false;
            }
        }

        private function has_query_block_recursive( $blocks ) {
            foreach ( $blocks as $block ) {
                if ( $block['blockName'] === 'core/query' ) {
                    return true;
                }

                if ( ! empty( $block['innerBlocks'] ) && $this->has_query_block_recursive( $block['innerBlocks'] ) ) {
                    return true;
                }
            }
            return false;
        }
        private function check_page_query_block($post_content)
        {
            $blocks = parse_blocks( $post_content );
            if ( has_shortcode( $post_content, 'products' ) ) {
                return true;
            }
            if ( $this->has_query_block_recursive( $blocks ) ) {
                return true;
            }
            return false;
        }

        /**
         * @param array|\WP_Post|null $post
         * @param array $filter_set
         * @param array $slug_parts
         * @return array
         */
        private function getPageQuerySlugs($post, $filter_set, $slug_parts)
        {

            $url = $this->toRelativePath( get_permalink($post->ID) );
            if ($post->post_type === 'page') {
                if(isset($this->pageHasNoQuery[$url])){
                    return $slug_parts;
                }

                if(isset($this->pageHasQuery[$url])){
                    $slug_parts[] = $url;
                } elseif ($this->check_page_query_block($post->post_content)) {
                    if ($post->post_status = 'publish') {
                        $slug_parts[] = $url;
                        $this->pageHasQuery[$url] = '';
                    }
                } elseif (false !== $this->fetch_wp_queries_options_from_page($url, $filter_set['post_content']['wp_page_type'], $filter_set['post_excerpt'], $filter_set['ID'])) {
                    $slug_parts[] = $url;
                    $this->pageHasQuery[$url] = '';
                }else{
                    $this->pageHasNoQuery[$url] = '';
                }
            } else {
                if ($post->post_status = 'publish') $slug_parts[] = $url;
            }
            return $slug_parts;
        }

        private function getArchiveSlugsByPostType($post_type, $taxonomy = '', $term_id = '')
        {
            $urls = [];
            $tax_objects = get_object_taxonomies($post_type, 'objects');

            foreach ($tax_objects as $tax_name => $tax) {

                if(!empty($taxonomy) && $tax_name !== $taxonomy){
                    continue;
                }

                if (empty($tax->public) || empty($tax->query_var)) {
                    continue;
                }

                $args = [
                    'taxonomy'   => $tax->name,
                    'hide_empty' => false,
                    'exclude' => $this->removeUncategorizedTerms()
                ];

                if(!empty($term_id) && $term_id !== '-1'){
                    $args['object_ids'] = [$term_id];
                }

                $terms = get_terms($args);

                if (is_wp_error($terms) || empty($terms)) {
                    continue;
                }

                foreach ($terms as $term) {
                    $term_link = $this->toRelativePath(get_term_link($term));
                    if(!empty($term_link)){
                        $urls[] = $term_link;
                    }
                }
            }

            return array_values(array_unique($urls));


        }
        public function fetch_wp_queries_options_from_page(
            string $relativePath,
            string $wpPageType,
            string $postType,
            int $postId,
            int $timeout = 15
        ) {

            $relativePath = '/' . ltrim($relativePath, '/');


            if(!is_user_logged_in()){
                return false;
            }

            $cookies = [];
            foreach ([ LOGGED_IN_COOKIE, AUTH_COOKIE, SECURE_AUTH_COOKIE ] as $name) {
                if (!empty($_COOKIE[$name])) {
                    $cookies[] = new \WP_Http_Cookie([
                        'name'   => $name,
                        'value'  => $_COOKIE[$name],
                        'path'   => COOKIEPATH ?: '/',
                        'domain' => COOKIE_DOMAIN ?: wp_parse_url(home_url(), PHP_URL_HOST),
                    ]);
                }
            }

            if (!function_exists('home_url')) {
                return false;
            }
            $url = home_url($relativePath);

            $body = [
                '_wpnonce'   => function_exists('flrt_create_filters_nonce') ? flrt_create_filters_nonce() : '',
                'wpPageType' => $wpPageType,
                'postType'   => $postType,
                'postId'     => $postId,
                'action'     => 'wpc_get_wp_queries',
            ];

            if (function_exists('wp_safe_remote_post')) {
                $response = wp_remote_post($url, [
                    'timeout' => $timeout,
                    'body'    => $body,
                    'cookies'    => $cookies,
                    'headers' => [
                        'Accept' => 'text/html,application/xhtml+xml',
                    ],
                ]);

                if (is_wp_error($response)) {
                    $html = '';
                } else {
                    $code = (int) wp_remote_retrieve_response_code($response);
                    if ($code >= 200 && $code < 300) {
                        $html = (string) wp_remote_retrieve_body($response);
                    } else {
                        $html = '';
                    }
                }
            } else {
                $html = '';
            }

            if ($html === '') {
                return false;
            }


            $prevUseErrors = libxml_use_internal_errors(true);
            $dom = new \DOMDocument();


            $loaded = $dom->loadHTML(
                $html,
                (defined('LIBXML_NOWARNING') ? LIBXML_NOWARNING : 0)
                | (defined('LIBXML_NOERROR') ? LIBXML_NOERROR : 0)
                | (defined('LIBXML_NONET') ? LIBXML_NONET : 0)
                | (defined('LIBXML_HTML_NOIMPLIED') ? LIBXML_HTML_NOIMPLIED : 0)
                | (defined('LIBXML_HTML_NODEFDTD') ? LIBXML_HTML_NODEFDTD : 0)
            );

            libxml_clear_errors();
            libxml_use_internal_errors($prevUseErrors);

            if (!$loaded) {
                return false;
            }

            $xpath = new DOMXPath($dom);

            $queriesByKey = [];
            $hiddenInputs = $xpath->query("//div[@id='wpc_query_vars']//input[@type='hidden' and starts-with(@name, 'wpc_set_fields[wp_filter_query_vars]')]");
            if ($hiddenInputs && $hiddenInputs->length > 0) {
                foreach ($hiddenInputs as $inp) {
                    $nameAttr  = $inp->getAttribute('name');
                    $valueAttr = $inp->getAttribute('value');

                    if (preg_match('/\[wp_filter_query_vars]\[([^\]]+)]/u', $nameAttr, $m)) {
                        $key = $m[1];

                        $decoded = html_entity_decode($valueAttr, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $maybe   = @unserialize($decoded);
                        if ($maybe !== false || $decoded === 'b:0;') {
                            $queriesByKey[$key] = $maybe;
                        }
                    }
                }
            }

            $selectNodes = $xpath->query("//select[contains(concat(' ', normalize-space(@class), ' '), ' wpc-field-wp-filter-query ')]");
            if (!$selectNodes || $selectNodes->length === 0) {
                return false;
            }

            $select = $selectNodes->item(0);
            $optionNodes = $xpath->query(".//option[@selected]", $select);
            if (!$optionNodes || $optionNodes->length === 0) {
                return false;
            }

            $options = [];
            foreach ($optionNodes as $opt) {
                $value = $opt->getAttribute('value');
                $label = trim($opt->textContent ?? '');

                if ($value === '-1') {
                    return false;
                }

                if ($value !== '') {
                    $item = [
                        'value' => $value,
                        'label' => $label,
                    ];
                    if (isset($queriesByKey[$value])) {
                        $item['query_vars'] = $queriesByKey[$value];
                    }
                    $options[] = $item;
                }
            }

            if (count($options) === 0) {
                return false;
            }

            unset($html);
            if(empty($options[0]['query_vars'])){
                return false;
            }

            $query = new \WP_Query( $options[0]['query_vars'] );
            if(!$query->have_posts()){
                return false;
            }
            return $options;
        }

        private function toRelativePath($url){
            if (is_wp_error($url) || $url === false || $url === null) {
                return '';
            }
            $url = (string) $url;
            if ($url === '') {
                return '';
            }
            $relative = wp_make_link_relative($url);
            return is_string($relative) ? ltrim($relative, '/') : '';
        }

        protected function removeUncategorizedTerms()
        {
            return get_terms([
                'slug' => 'uncategorized',
                'hide_empty' => false,
                'fields' => 'ids'
            ]);
        }

    }
endif;