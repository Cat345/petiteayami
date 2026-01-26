<?php

namespace YOOtheme\Builder\Wordpress\Listener;

use YOOtheme\Str;
use YOOtheme\Theme\Wordpress\FilterHelper;

class ApplyContentFilter
{
    /**
     * @param array<string, mixed> $parameters
     */
    public static function handle(string $content, array $parameters): string
    {
        $parameters += ['prefix' => ''];

        // Ensure `the_content` filter is only applied on main page content
        if (!Str::startsWith($parameters['prefix'], ['page', 'template-'])) {
            return do_shortcode($content);
        }

        // Ignore wpautop filter
        $restore = FilterHelper::remove('the_content', 'wpautop');
        $content = apply_filters('the_content', $content);
        $content = str_replace(']]>', ']]&gt;', $content);
        $restore();

        return $content;
    }
}
