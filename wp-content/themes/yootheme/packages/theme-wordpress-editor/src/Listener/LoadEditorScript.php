<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Url;

class LoadEditorScript
{
    public static function handle(): void
    {
        wp_enqueue_script('yoo-editor', Url::to('~assets/admin/js/editor.js', [], is_ssl()));
        wp_print_scripts('yoo-editor');
    }
}
