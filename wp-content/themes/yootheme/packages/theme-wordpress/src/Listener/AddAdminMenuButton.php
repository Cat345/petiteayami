<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Path;
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
        $menu_slug = Path::relative(get_admin_url(), Url::route('customizer'));

        add_action('admin_print_styles', fn() => $this->getStyle($menu_slug));
        add_menu_page(
            '',
            $this->config->get('theme.name', ''),
            'edit_theme_options',
            $menu_slug,
            '',
            '',
            59,
        );
    }

    protected function getStyle(string $menu_slug): void
    {
        $id = preg_replace('/[^\w:.]/', '-', get_plugin_page_hookname($menu_slug, ''));
        $font = get_template_directory_uri() . '/packages/theme-wordpress/assets/icon.ttf';
        $style = <<<CSS
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

        echo preg_replace('/\s+/', ' ', '<style>' . trim($style) . '</style>') . "\n";
    }
}
