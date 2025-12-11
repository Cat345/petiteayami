<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Url;
use YOOtheme\Http\Request;

class AddAdminBarButton
{
    protected Config $config;
    protected Request $request;

    public function __construct(Config $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Fires after WP_Admin_Bar is initialized.
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_bar_init/
     */
    public function init(): void
    {
        add_action('wp_head', fn() => $this->printStyle());
    }

    /**
     * Loads all necessary admin bar items.
     *
     * @param \WP_Admin_Bar $admin_bar
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_bar_menu/
     */
    public function menu($admin_bar): void
    {
        $icon = '<span class="ab-icon" aria-hidden="true"></span>';
        $title = "<span class=\"ab-label\" aria-hidden=\"true\">{$this->config->get(
            'theme.name',
            '',
        )}</span>";
        $title .= "<span class=\"screen-reader-text\">{$this->config->get(
            'theme.name',
            '',
        )}</span>";

        $site = (string) $this->request->getUri();

        $admin_bar->add_node([
            'id' => 'yootheme',
            'title' => $icon . $title,
            'href' => Url::route('customizer', [
                'site' => $site,
                'return' => $site,
            ]),
            'meta' => ['title' => $this->config->get('theme.name', '')],
        ]);
    }

    protected function printStyle(): void
    {
        $font = get_template_directory_uri() . '/packages/theme-wordpress/assets/icon.ttf';
        $style = <<<CSS
            @font-face {
                font-family: YOOtheme;
                font-display: swap;
                src: url('{$font}') format('truetype');
                font-weight: 400;
                font-style: normal;
            }

            #wp-admin-bar-yootheme .ab-icon:before {
                font-family: YOOtheme;
                content: "\\f111";
                top: 2px;
            }
        CSS;

        echo preg_replace('/\s+/', ' ', '<style>' . trim($style) . '</style>') . "\n";
    }
}
