<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Url;

class LoadPostScript
{
    /**
     * Fires before a particular screen is loaded.
     *
     * @link https://developer.wordpress.org/reference/hooks/load-page_hook/
     */
    public static function handle()
    {
        add_action('edit_form_after_title', function ($post) {
            printf(
                '<div class="tm-editor" hidden><a href="%s" class="tm-button">%s</a><a href class="tm-link">%s</a></div>',
                static::getLink(),
                __('YOOtheme Builder', 'yootheme'),
                __('&#8592; Back to Classic Editor', 'yootheme'),
            );
        });

        add_action('media_buttons', function ($editor_id) {
            if ($editor_id === 'content') {
                printf(
                    '<a href="%s" class="button button-primary">%s</a>',
                    static::getLink(),
                    __('YOOtheme Builder', 'yootheme'),
                );
            }
        });

        add_filter('wp_editor_settings', function ($settings) {
            if (preg_match('/<!--\s?{/', get_post_field('post_content'))) {
                $settings['default_editor'] = 'html';
            }

            return $settings;
        });

        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_script(
                'posts-builder',
                get_template_directory_uri() . '/packages/theme-wordpress-posts/app/posts.min.js',
                [],
                false,
                true,
            );

            wp_add_inline_script(
                'posts-builder',
                sprintf('var $customizer = %s;', json_encode(['link' => static::getLink()])),
                'before',
            );
        });
    }

    protected static function getLink()
    {
        return Url::route('customizer', [
            'site' => get_permalink(),
            'return' => get_edit_post_link(0, ''),
            'section' => 'builder',
        ]);
    }
}
