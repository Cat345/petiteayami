<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\Theme\Wordpress\Breadcrumbs;
use YOOtheme\View;

class LoadThemeHead
{
    public View $view;
    public Config $config;

    public function __construct(Config $config, View $view)
    {
        $this->view = $view;
        $this->config = $config;
    }

    /**
     * Fires before the header template file is loaded.
     *
     * @link https://developer.wordpress.org/reference/hooks/get_header/
     */
    public function handle()
    {
        $this->config->add('~theme', [
            'site_url' => trailingslashit(home_url()), // eventually update site_url with language parameter (Polylang)
            'direction' => is_rtl() ? 'rtl' : 'lrt',
            'page_class' => '', // TODO: implement page class builder
        ]);

        if ($this->config->get('~theme.disable_wpautop')) {
            remove_filter('the_content', 'wpautop');
            remove_filter('the_excerpt', 'wpautop');
        }

        if ($this->config->get('~theme.disable_emojis')) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('admin_print_scripts', 'print_emoji_detection_script');
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_action('admin_print_styles', 'print_emoji_styles');
            remove_filter('the_content_feed', 'wp_staticize_emoji');
            remove_filter('comment_text_rss', 'wp_staticize_emoji');
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        }

        $this->view['sections']->add(
            'breadcrumbs',
            fn() => $this->view->render('~theme/templates/breadcrumbs', [
                'items' => Breadcrumbs::getItems([
                    'show_current' => $this->config->get('~theme.site.breadcrumbs_show_current'),
                    'show_home' => $this->config->get('~theme.site.breadcrumbs_show_home'),
                    'home_text' => $this->config->get('~theme.site.breadcrumbs_home_text'),
                ]),
            ]),
        );

        Event::emit('theme.head');
    }
}
