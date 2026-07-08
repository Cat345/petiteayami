<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Url;

class AddAdminMenuButton
{
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Fires before the administration menu loads in the admin.
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_menu/
     */
    public function handle(): void
    {
        $menu_slug = Url::relative(
            wp_make_link_relative(Url::route('customizer')),
            wp_make_link_relative(get_admin_url()),
        );

        add_menu_page(
            '',
            $this->config->get('theme.name', ''),
            'edit_theme_options',
            $menu_slug,
            '', // @phpstan-ignore argument.type
            '',
            59,
        );

        wp_add_inline_style('admin-bar', $this->getStyle($menu_slug));
    }

    protected function getStyle(string $menu_slug): string
    {
        $id = preg_replace('/[^\w:.]/', '-', get_plugin_page_hookname($menu_slug, ''));
        $font = Url::to('~theme/packages/theme-wordpress/fonts/icon.ttf');
        return <<<CSS
            @font-face {
                font-family: YOOtheme;
                font-display: swap;
                src: url('{$font}') format('truetype');
                font-weight: 400;
                font-style: normal;
            }

            #{$id} div.wp-menu-image:before {
                font-family: YOOtheme;
            }
        CSS;
    }
}
