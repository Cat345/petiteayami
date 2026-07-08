<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use WP_HTML_Tag_Processor;

class FilterUikitAttributes
{
    /**
     * Removes UIkit data attributes from post HTML before KSES validates it.
     * Can be removed once UIkit itself does sanitization.
     *
     * @param string $content
     * @param mixed $allowedHtml
     *
     * @return string
     *
     * @link https://developer.wordpress.org/reference/hooks/pre_kses/
     */
    public static function handle(string $content, $allowedHtml): string
    {
        if (
            $content === '' ||
            $allowedHtml !== 'post' ||
            current_user_can('unfiltered_html') ||
            !class_exists(WP_HTML_Tag_Processor::class)
        ) {
            return $content;
        }

        $processor = new WP_HTML_Tag_Processor($content);

        while ($processor->next_tag()) {
            foreach ($processor->get_attribute_names_with_prefix('data-uk-') as $attribute) {
                $processor->remove_attribute($attribute);
            }
        }

        return $processor->get_updated_html();
    }
}
