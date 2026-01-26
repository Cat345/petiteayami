<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Builder\Wordpress\PostHelper;
use YOOtheme\File;
use YOOtheme\Url;

class LoadClassicEditor
{
    /**
     * Fires before a particular screen is loaded.
     *
     * @link https://developer.wordpress.org/reference/hooks/load-page_hook/
     */
    public static function handle(): void
    {
        if (get_current_screen()->is_block_editor()) {
            return;
        }

        wp_enqueue_script(
            'posts-builder',
            Url::to('~assets/admin/js/posts-classic.js', [], is_ssl()),
            [],
            false,
            true,
        );

        add_action('edit_form_after_title', function ($post) {
            if (current_user_can('edit_theme_options')) {
                printf(
                    '<div class="tm-editor" hidden><a href="%s" class="tm-button">%s</a><a href class="tm-link">%s</a></div>',
                    static::getLink(),
                    __('YOOtheme', 'yootheme'),
                    __('&#8592; Back to Classic Editor', 'yootheme'),
                );
            } else {
                printf(
                    '<div class="tm-editor" hidden><span class="tm-button tm-disabled">%s<span>%s</span></span></div>',
                    File::getContents('~assets/images/lock.svg'),
                    __('YOOtheme', 'yootheme'),
                );
            }
        });

        add_action('media_buttons', function ($editor_id) {
            if ($editor_id === 'content' && current_user_can('edit_theme_options')) {
                printf(
                    '<a href="%s" class="button button-primary">%s</a>',
                    static::getLink(),
                    __('YOOtheme', 'yootheme'),
                );
            }
        });

        add_filter('wp_editor_settings', function ($settings) {
            if (PostHelper::matchContent(get_post_field('post_content'))) {
                $settings['default_editor'] = 'html';
            }

            return $settings;
        });
    }

    protected static function getLink(): string
    {
        return Url::route('customizer', [
            'site' => get_permalink(),
            'return' => get_edit_post_link(0, ''),
            'section' => 'builder',
        ]);
    }
}
