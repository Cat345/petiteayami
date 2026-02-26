<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\File;
use YOOtheme\Url;
use function YOOtheme\app;

class LoadBlockEditor
{
    /**
     * Fires after enqueuing block assets for both editor and front-end.
     *
     * @link https://developer.wordpress.org/reference/hooks/enqueue_block_assets/
     */
    public static function handle(): void
    {
        if (!is_admin()) {
            return;
        }

        // This needs to use the native wp_enqueue_script function. Otherwise, the script won't be added within the block editor's iframe
        wp_enqueue_script(
            'posts-builder',
            Url::to('~assets/admin/js/posts-block.js', [], is_ssl()),
            [],
            app(Config::class)->get('theme.version'),
            true,
        );

        add_action('admin_footer', function () {
            if (current_user_can('edit_theme_options')) {
                printf(
                    '<div class="tm-editor" hidden><a href="%s" class="tm-button">%s</a><a href class="tm-link">%s</a></div>',
                    static::getLink(),
                    __('YOOtheme', 'yootheme'),
                    __('&#8592; Back to Block Editor', 'yootheme'),
                );
            } else {
                printf(
                    '<div class="tm-editor" hidden><span class="tm-button tm-disabled">%s<span>%s</span></span></div>',
                    File::getContents('~assets/images/lock.svg'),
                    __('YOOtheme', 'yootheme'),
                );
            }
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
