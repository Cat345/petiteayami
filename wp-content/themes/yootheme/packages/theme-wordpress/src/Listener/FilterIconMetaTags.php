<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Url;
use YOOtheme\View\HtmlElement;

class FilterIconMetaTags
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Disables the site icon meta tags in frontend, sets the site icon meta tags in admin.
     *
     * @param list<string> $tags
     *
     * @return list<string>
     *
     * @link https://developer.wordpress.org/reference/hooks/site_icon_meta_tags/
     */
    public function handle(array $tags): array
    {
        $icons = array_filter([
            $this->load('favicon', 'icon', ['sizes' => 'any']),
            $this->load('favicon_svg', 'icon', ['type' => 'image/svg+xml']),
            $this->load('touchicon', 'apple-touch-icon'),
        ]);

        return empty($icons) ? $tags : $icons;
    }

    /**
     * @param array<string, string> $attrs
     */
    protected function load(string $src, string $rel, array $attrs = []): string
    {
        $src = $this->config->get("~theme.{$src}");
        return $src
            ? HtmlElement::tag('link', ['rel' => $rel, 'href' => Url::to($src)] + $attrs)
            : '';
    }
}
