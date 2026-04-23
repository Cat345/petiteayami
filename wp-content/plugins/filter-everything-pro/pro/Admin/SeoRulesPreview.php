<?php

namespace FilterEverything\Filter\Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use FilterEverything\Filter\Pro\XmlPrepare;

class SeoRulesPreview extends XmlPrepare
{
    public function __construct(int $seo_rule_id)
    {
        $this->seo_rule_id = $seo_rule_id;
        $this->error = new \WP_Error();

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

        if (empty($this->links) && empty($this->error->get_error_code())) {
            $this->error->add('empty_links', $this->errorNoticeTemplate(esc_html__("Error: No URLs were found for the XML sitemap. Please create filter sets and SEO rules before generating the XML sitemap again.", 'filter-everything')));
        }

        if(!empty($this->error->get_error_code())){
            return $this->error;
        }
    }

    public function getFirstLink(){
        reset($this->links);
        return key($this->links);
    }
}