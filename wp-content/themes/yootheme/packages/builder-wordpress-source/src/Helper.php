<?php

namespace YOOtheme\Builder\Wordpress\Source;

use YOOtheme\Event;

class Helper
{
    protected static array $arguments = [
        'public' => true,
        'show_ui' => true,
        'show_in_nav_menus' => true,
    ];

    public static function getBase($type): string
    {
        if (!$type->rest_base || $type->rest_base === $type->name) {
            return strtr($type->name . 's', '-', '_');
        }

        return strtr($type->rest_base, '-', '_');
    }

    public static function getPostTypes(array $arguments = []): array
    {
        return get_post_types($arguments + static::$arguments, 'objects');
    }

    public static function getTaxonomies(array $arguments = [])
    {
        return get_taxonomies($arguments + static::$arguments, 'objects');
    }

    public static function getTaxonomyPostTypes(\WP_Taxonomy $taxonomy): array
    {
        return array_filter(
            static::getPostTypes(),
            fn($type) => in_array($type->name, $taxonomy->object_type ?: []),
        );
    }

    public static function getObjectTaxonomies($object, array $arguments = [])
    {
        $taxonomies = get_object_taxonomies($object, 'objects');
        $taxonomies = wp_filter_object_list($taxonomies, $arguments + static::$arguments);

        return Event::emit('source.object.taxonomies|filter', $taxonomies, $object);
    }

    public static function orderAlphanum(array $query): \Closure
    {
        return function ($orderby) use ($query) {
            if (!str_contains((string) $orderby, ',')) {
                $replace = str_replace(
                    ':ORDER',
                    $query['order'],
                    "(SUBSTR($1, 1, 1) > '9') :ORDER, $1+0 :ORDER, $1 :ORDER",
                );
                $orderby = preg_replace('/([^\s]+).*/', $replace, $orderby, 1);
            }

            return $orderby;
        };
    }

    public static function filterOnce($tag, $callback)
    {
        add_filter(
            $tag,
            $filter = function (...$args) use ($tag, $callback, &$filter) {
                remove_filter($tag, $filter);
                return $callback(...$args);
            },
        );
    }

    public static function isPageSource($post): bool
    {
        return get_the_ID() === $post->ID;
    }

    public static function getPosts($args): array
    {
        $args += [
            'order' => 'date',
            'order_direction' => 'DESC',
            'offset' => 0,
            'limit' => 10,
            'users_operator' => 'IN',
        ];

        $query = [
            'post_status' => 'publish',
            'post_type' => $args['post_type'],
            'post_parent' => $args['post_parent'] ?? null,
            'orderby' => $args['order'],
            'order' => $args['order_direction'],
            'offset' => $args['offset'],
            'numberposts' => $args['limit'],
            'suppress_filters' => false,
        ];

        if (!empty($args['include'])) {
            $query['include'] = $args['include'];

            // Reset `posts_per_page` - `get_posts()` overrides the limit if ids are queried directly.
            static::filterOnce('pre_get_posts', function (\WP_Query $query) use ($args) {
                $query->set('posts_per_page', $args['limit']);
            });
        }

        if (!empty($args['exclude'])) {
            $query['exclude'] = $args['exclude'];
        }

        if (!empty($args['terms'])) {
            $taxonomies = [];

            foreach ($args['terms'] as $id) {
                if (($term = get_term($id)) && $term instanceof \WP_Term) {
                    $taxonomies[$term->taxonomy][] = $id;
                }
            }

            foreach ($taxonomies as $taxonomy => $terms) {
                $includeChildren = $args["{$taxonomy}_include_children"] ?? false;

                if ($includeChildren === 'only') {
                    $terms = array_merge(
                        ...array_map(
                            fn($term) => get_terms([
                                'taxonomy' => $taxonomy,
                                'parent' => $term,
                                'fields' => 'ids',
                            ]),
                            $terms,
                        ),
                    );
                }

                $query['tax_query'][] = [
                    'terms' => $terms,
                    'taxonomy' => $taxonomy,
                    'operator' => $args["{$taxonomy}_operator"] ?? 'IN',
                    'include_children' => (bool) $includeChildren,
                ];
            }
        }

        if (!empty($args['users'])) {
            $query[$args['users_operator'] === 'IN' ? 'author__in' : 'author__not_in'] =
                $args['users'];
        }

        if (str_starts_with($query['orderby'], 'field:')) {
            $query['meta_key'] = substr($query['orderby'], 6);
            $query['orderby'] = 'meta_value';
        }

        if (!empty($args['order_alphanum']) && $args['order'] !== 'rand') {
            static::filterOnce('posts_orderby', static::orderAlphanum($query));
        }

        Event::emit('source.resolve.posts', $query);

        return get_posts($query);
    }
}
