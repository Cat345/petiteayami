<?php

namespace YOOtheme\Builder\Wordpress\Source;

use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class SourceController
{
    /**
     * @param Request  $request
     * @param Response $response
     *
     * @throws \Exception
     *
     * @return Response
     */
    public static function posts(Request $request, Response $response)
    {
        $ids = $request->getQueryParam('ids');

        $names = [];
        $posts = get_posts([
            'include' => $ids ? (array) $ids : [],
            'post_type' => 'any',
        ]);

        foreach ($posts as $post) {
            $names[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
            ];
        }

        return $response->withJson($names);
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @throws \Exception
     *
     * @return Response
     */
    public static function users(Request $request, Response $response)
    {
        $ids = $request->getQueryParam('ids');
        $search = $request->getQueryParam('search');

        $names = [];
        $users = get_users([
            'include' => $ids ? (array) $ids : [],
            'search' => $search ? "*{$search}*" : '',
            'number' => 20,
            'fields' => ['ID', 'display_name'],
            'orderby' => 'display_name',
        ]);

        foreach ($users as $user) {
            $names[] = [
                'id' => (int) $user->ID,
                'title' => $user->display_name,
            ];
        }

        return $response->withJson($names);
    }
}
