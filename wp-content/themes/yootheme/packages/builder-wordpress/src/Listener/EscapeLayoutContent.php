<?php

namespace YOOtheme\Builder\Wordpress\Listener;

use WP_REST_Request;
use WP_REST_Response;
use WP_Post;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Builder\Wordpress\PostHelper;

/**
 * Prevents the Block Editor's `wp.autop()` from modifying builder layout JSON.
 *
 * Builder layouts are stored as JSON inside an HTML comment. Gutenberg's freeform parser applies
 * `wp.autop()` to that content and can insert line breaks around HTML tags within the JSON.
 */
class EscapeLayoutContent
{
    public static function register(): void
    {
        foreach (Helper::getPostTypes(['show_in_rest' => true]) as $postType) {
            add_filter("rest_prepare_{$postType->name}", [static::class, 'handle'], 10, 3);
        }
    }

    public static function handle(
        WP_REST_Response $response,
        WP_Post $post,
        WP_REST_Request $request
    ): WP_REST_Response {
        if ($request->get_param('context') !== 'edit') {
            return $response;
        }

        $data = $response->get_data();

        if (!isset($data['content']['raw'])) {
            return $response;
        }

        $data['content']['raw'] = PostHelper::escapeContent($data['content']['raw']);
        $response->set_data($data);

        return $response;
    }
}
