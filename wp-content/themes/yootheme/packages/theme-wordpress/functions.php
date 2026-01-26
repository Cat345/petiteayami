<?php

namespace YOOtheme;

// Helper functions.
use WP_Post;

/**
 * @param mixed ...$args
 */
function get_view(string $name, ...$args): string
{
    /** @var View $view */
    $view = app(View::class);
    return $view->render($name, ...$args);
}

/**
 * @param mixed ...$args
 */
function get_attrs(...$args): string
{
    /** @var View $view */
    $view = app(View::class);
    return $view->attrs(...$args);
}

/**
 * @param mixed ...$args
 *
 * @return string|false
 */
function get_section(...$args)
{
    /** @var View $view */
    $view = app(View::class);
    return $view->section(...$args);
}

/**
 * @param mixed $node
 * @param mixed $params
 */
function get_builder($node, $params = []): ?string
{
    // support old builder arguments
    if (!is_string($node)) {
        $node = json_encode($node);
    }

    if (is_string($params)) {
        $params = ['prefix' => $params];
    }

    return app(Builder::class)->render($node, $params);
}

/**
 * @param int|WP_Post $post
 * @param string      $format
 */
function get_post_date($post = null, $format = ''): string
{
    return '<time datetime="' .
        esc_attr(get_the_date('c', $post)) .
        '">' .
        esc_html(get_the_date($format, $post)) .
        '</time>';
}

/**
 * @param ?WP_Post $post
 */
function get_post_author($post = null): string
{
    if ($post) {
        $authordata = get_userdata((int) $post->post_author);
    } else {
        global $authordata;
    }

    return '<a href="' .
        esc_url(get_author_posts_url($authordata->ID)) .
        '">' .
        esc_html(apply_filters('the_author', $authordata->display_name)) .
        '</a>';
}

function link_pages(): string
{
    global $page, $numpages, $multipage, $more;

    return $multipage
        ? app(View::class)('~theme/templates/pagebreak', compact('page', 'numpages', 'more'))
        : '';
}
