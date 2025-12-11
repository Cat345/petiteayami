<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Path;
use YOOtheme\Url;

class LoadEditorScript
{
    public static function handle()
    {
        wp_enqueue_script(
            'yoo-editor',
            Url::to(Path::get('../../app/editor.min.js', __DIR__), [], is_ssl()),
        );
        wp_print_scripts('yoo-editor');
    }
}
