<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class DisableBlockEditor
{
    public static function handle($result)
    {
        return preg_match('/<!--\s?{/', get_post_field('post_content')) ? false : $result;
    }
}
