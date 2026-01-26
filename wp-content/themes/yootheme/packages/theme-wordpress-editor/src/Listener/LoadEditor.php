<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class LoadEditor
{
    /**
     * Enqueue classic editor.
     */
    public static function handle(): void
    {
        wp_enqueue_editor();
    }
}
