<?php

namespace YOOtheme\Builder\Wordpress\Source\Listener;

use YOOtheme\Http\Request;

class AddPostType
{
    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Filters the link query arguments.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_link_query_args/
     */
    public function handle(array $query): array
    {
        if ($post_type = $this->request->getParam('post_type')) {
            return ['post_type' => [$post_type]] + $query;
        }

        return $query;
    }
}
