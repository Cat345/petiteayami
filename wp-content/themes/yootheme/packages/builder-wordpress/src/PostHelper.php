<?php

namespace YOOtheme\Builder\Wordpress;

class PostHelper
{
    public const PATTERN = '/<!--\s?(\{.*})\s?-->/';

    public static function matchContent($content)
    {
        return str_contains((string) $content, '<!--') &&
            preg_match(static::PATTERN, $content, $matches)
            ? $matches[1]
            : null;
    }

    public static function getCollision($post)
    {
        $userData = get_userdata(
            get_post_meta($post->ID, '_edit_last', true) ?:
            static::getUserIdFromPostRevisions($post),
        );

        return [
            'contentHash' => md5($post->post_content),
            'modifiedBy' => $userData ? $userData->display_name : '',
        ];
    }

    protected static function getUserIdFromPostRevisions($post)
    {
        $revs = wp_get_post_revisions($post->ID);

        if ($lastRev = end($revs)) {
            return $lastRev->post_author;
        }
    }
}
