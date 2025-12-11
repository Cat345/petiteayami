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
     * @link https://developer.wordpress.org/reference/hooks/site_icon_meta_tags/
     */
    public function handle()
    {
        $icons = SetFavicons::load($this->config);

        $tags = ["<link rel=\"icon\" href=\"{$icons['favicon']}\" sizes=\"any\">"];

        if (!empty($icons['favicon_svg'])) {
            $tags[] = "<link rel=\"icon\" href=\"{$icons['favicon_svg']}\" type=\"image/svg+xml\">";
        }

        $tags[] = "<link rel=\"apple-touch-icon\" href=\"{$icons['touchicon']}\">";

        return $tags;
    }
}
