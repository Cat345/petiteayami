<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Theme\Listener\SetFavicons;

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
    public function handle(array $tags)
    {
        $icons = SetFavicons::load($this->config);

        if (!empty(array_filter($icons))) {
            $tags = [];
        }

        if ($icons['favicon']) {
            $tags[] = "<link rel=\"icon\" href=\"{$icons['favicon']}\" sizes=\"any\">";
        }

        if ($icons['favicon_svg']) {
            $tags[] = "<link rel=\"icon\" href=\"{$icons['favicon_svg']}\" type=\"image/svg+xml\">";
        }

        if ($icons['touchicon']) {
            $tags[] = "<link rel=\"apple-touch-icon\" href=\"{$icons['touchicon']}\">";
        }

        return $tags;
    }
}
