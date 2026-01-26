<?php

namespace YOOtheme\Theme\Highlight\Listener;

use YOOtheme\Config;
use YOOtheme\Metadata;

class LoadHighlightScript
{
    public Config $config;
    public Metadata $metadata;

    public function __construct(Config $config, Metadata $metadata)
    {
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public function handle(string $content): string
    {
        $highlight = $this->config->get('~theme.highlight');

        if ($highlight && str_contains($content, '</code>')) {
            $this->metadata->set('style:highlight', [
                'href' => "~assets/highlight.js/css/{$highlight}.css",
                'defer' => true,
            ]);

            $this->metadata->set('script:highlight', [
                'src' => '~assets/highlight.js/highlight.js',
                'defer' => true,
            ]);

            $this->metadata->set(
                'script:highlight-init',
                'document.addEventListener("DOMContentLoaded", function() {hljs.initHighlightingOnLoad()});',
            );
        }

        return $content;
    }
}
