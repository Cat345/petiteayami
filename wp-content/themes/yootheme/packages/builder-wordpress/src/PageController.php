<?php

namespace YOOtheme\Builder\Wordpress;

use YOOtheme\Builder;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

class PageController
{
    public static function getPages(Request $request, Response $response)
    {
        $home = 0;
        $query = [
            'numberposts' => 50,
            'post_status' => ['draft', 'publish'],
            'suppress_filters' => false, // allow WPML language filters
        ];

        if ('page' === get_option('show_on_front')) {
            $home = intval(get_option('page_on_front'));
            $query['exclude'] = [get_option('page_for_posts')];
        }

        if ($search = $request->getQueryParam('search', '')) {
            $query['s'] = $search;
            $query['search_columns'] = ['post_title'];
            $query['post_type'] = 'any';
        } else {
            $query['post_type'] = 'page';
        }

        $posts = [];
        $postTypes = Helper::getPostTypes();

        foreach (get_posts($query) as $post) {
            $posts[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'url' => get_permalink($post),
                'home' => $post->ID === $home,
                'type' => [
                    'id' => ($type = $post->post_type),
                    'title' => $postTypes[$type]->label ?? $type,
                    'page' => $type === 'page',
                ],
            ];
        }

        return $response->withJson($posts);
    }

    public static function savePage(Request $request, Response $response, Builder $builder)
    {
        $request
            ->abortIf(!($page = $request->getParam('page')), 400)
            ->abortIf(!($page = base64_decode($page)), 400)
            ->abortIf(!($page = json_decode($page)), 400)
            ->abortIf(!current_user_can('edit_post', $page->id), 403, 'Insufficient User Rights.');

        $collision = PostHelper::getCollision(get_post($page->id));

        if (
            !$request->getParam('overwrite') &&
            $collision['contentHash'] !== $page->collision->contentHash
        ) {
            return $response->withJson(['hasCollision' => true, 'collision' => $collision]);
        }

        $data = [
            'ID' => $page->id,
            'post_content' => '',
            'page_template' => '', // Skip page_template (Prevents error "Invalid page template.")
        ];

        if ($page->content) {
            $content = json_encode($page->content);
            $fulltext = json_encode($builder->withParams(['context' => 'save'])->load($content));
            $introtext = $builder->withParams(['context' => 'content'])->render($content);

            $data['post_content'] = wp_slash("{$introtext}\n<!--more-->\n<!-- {$fulltext} -->");
        }

        $updated = wp_update_post($data, true);

        if (is_wp_error($updated)) {
            $request->abort(500, $updated->get_error_message());
        }

        update_post_meta($page->id, '_edit_last', get_current_user_id());

        $post = get_post($page->id);

        $result = [
            'id' => $page->id,
            'collision' => PostHelper::getCollision($post),
        ];

        if ($post->post_status == 'auto-draft') {
            wp_update_post(['ID' => $page->id, 'post_status' => 'draft'], true);
            $result['return_url'] = get_edit_post_link($post->ID, 'raw');
        }

        return $response->withJson($result);
    }
}
