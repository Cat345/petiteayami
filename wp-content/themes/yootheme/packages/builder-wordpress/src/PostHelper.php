<?php

namespace YOOtheme\Builder\Wordpress;

use WP_Post;

class PostHelper
{
    public const PATTERN = '/<!--\s?({.*})\s?-->/';

    public static function matchContent(?string $content): ?string
    {
        return str_contains((string) $content, '<!--') &&
            preg_match(static::PATTERN, $content, $matches)
            ? $matches[1]
            : null;
    }

    public static function escapeContent(?string $content): ?string
    {
        if (!$content || !str_contains($content, '<!--')) {
            return $content;
        }

        return preg_replace_callback(
            static::PATTERN,
            fn($matches) => str_replace(
                $matches[1],
                strtr($matches[1], ['<' => '\u003C']),
                $matches[0],
            ),
            $content,
        );
    }

    /**
     * @return array{contentHash: string, modifiedBy: string}
     */
    public static function getCollision(WP_Post $post): array
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

    /**
     * @return void|int|string
     */
    protected static function getUserIdFromPostRevisions(WP_Post $post)
    {
        if ($lastRev = array_last(wp_get_post_revisions($post->ID))) {
            return $lastRev->post_author;
        }
    }
}
