<?php

namespace YOOtheme\Theme\Highlight\Listener;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Document\HtmlDocument;
use YOOtheme\Config;
use YOOtheme\Metadata;
use YOOtheme\Path;
use function YOOtheme\app;

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
                'href' => Path::get("../../assets/styles/{$highlight}.css", __DIR__),
                'defer' => true,
            ]);

            $this->metadata->set('script:highlight', [
                'src' => Path::get('../../assets/highlight.js', __DIR__),
                'defer' => true,
            ]);

            $this->metadata->set(
                'script:highlight-init',
                'document.addEventListener("DOMContentLoaded", function() {hljs.initHighlightingOnLoad()});',
            );
        }

        return $content;
    }

    public function beforeRender(): void
    {
        $document = app(Document::class);

        if ($document instanceof HtmlDocument) {
            $this->handle($document->getBuffer('component') ?? '');
        }
    }
}
