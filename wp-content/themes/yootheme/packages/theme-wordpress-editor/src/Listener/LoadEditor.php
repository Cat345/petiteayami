<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class LoadEditor
{
    /**
     * Enqueue classic editor.
     */
    public static function handle()
    {
        wp_enqueue_editor();
    }
}
