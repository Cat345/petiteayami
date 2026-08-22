<?php

namespace YOOtheme\Builder\Wordpress\Source;

use Closure;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Taxonomy;
use WP_Term;
use YOOtheme\Builder\DateHelper;
use YOOtheme\Config;
use YOOtheme\Event;
use function YOOtheme\app;

class Helper
{
    /**
     * @var array<string, bool>
     */
    protected static array $arguments = [
        'public' => true,
        'show_in_nav_menus' => true,
    ];

    /**
     * @param WP_Taxonomy|WP_Post_Type $type
     */
    public static function getBase($type): string
    {
        if (!$type->rest_base || $type->rest_base === $type->name) {
            return strtr($type->name . 's', '-', '_');
        }

        return strtr($type->rest_base, '-', '_');
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, WP_Post_Type>
     */
    public static function getPostTypes(array $arguments = []): array
    {
        return get_post_types($arguments + static::$arguments, 'objects');
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, WP_Taxonomy>
     */
    public static function getTaxonomies(array $arguments = []): array
    {
        $taxonomies = get_taxonomies($arguments + static::$arguments, 'objects');

        // Ignore built-in post_format taxonomy
        // https://developer.wordpress.org/advanced-administration/wordpress/post-formats/
        unset($taxonomies['post_format']);

        return $taxonomies;
    }

    /**
     * @return array<string, WP_Post_Type>
     */
    public static function getTaxonomyPostTypes(WP_Taxonomy $taxonomy): array
    {
        return array_filter(
            static::getPostTypes(),
            fn($type) => in_array($type->name, $taxonomy->object_type ?: []),
        );
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, WP_Taxonomy>
     */
    public static function getObjectTaxonomies(string $object, array $arguments = []): array
    {
        $taxonomies = get_object_taxonomies($object, 'objects');
        $taxonomies = wp_filter_object_list($taxonomies, $arguments + static::$arguments);

        return Event::emit('source.object.taxonomies|filter', $taxonomies, $object);
    }

    /**
     * @param array<string, string> $query
     */
    public static function orderAlphanum(array $query): Closure
    {
        $direction = strtoupper($query['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        return function ($orderby) use ($direction) {
            if (!str_contains((string) $orderby, ',')) {
                $replace = str_replace(
                    ':ORDER',
                    $direction,
                    "(SUBSTR($1, 1, 1) > '9') :ORDER, $1+0 :ORDER, $1 :ORDER",
                );
                $orderby = preg_replace('/([^\s]+).*/', $replace, $orderby, 1);
            }

            return $orderby;
        };
    }

    public static function filterOnce(string $tag, callable $callback): void
    {
        add_filter(
            $tag,
            $filter = function (...$args) use ($tag, $callback, &$filter) {
                remove_filter($tag, $filter);
                return $callback(...$args);
            },
        );
    }

    public static function isPageSource(WP_Post $post): bool
    {
        return get_the_ID() === $post->ID;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return list<WP_Post>
     */
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
            static::filterOnce('pre_get_posts', function (WP_Query $query) use ($args) {
                $query->set('posts_per_page', $args['limit']);
            });
        }

        if (!empty($args['exclude'])) {
            $query['exclude'] = $args['exclude'];
        }

        if (!empty($args['terms'])) {
            $taxonomies = [];

            foreach ($args['terms'] as $id) {
                if (($term = get_term($id)) && $term instanceof WP_Term) {
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

        if (!empty($args['date_column'])) {
            $timezone = wp_timezone();
            $args = DateHelper::parseStartEndArguments($args, $timezone);

            $args['date_start'] = DateHelper::toSql($args['date_start'] ?? null, $timezone);
            $args['date_end'] = DateHelper::toSql($args['date_end'] ?? null, $timezone);

            if ($args['date_start'] || $args['date_end']) {
                if (str_starts_with($args['date_column'], 'field:')) {
                    $type = 'DATETIME';
                    $key = substr($args['date_column'], 6);

                    // Toolset
                    if (str_starts_with($key, 'wpcf-')) {
                        $args['date_start'] = DateHelper::toTimestamp($args['date_start']);
                        $args['date_end'] = DateHelper::toTimestamp($args['date_end']);
                        $type = 'NUMERIC';
                    }

                    if (!empty($args['date_start']) && !empty($args['date_end'])) {
                        $value = [$args['date_start'], $args['date_end']];
                        $compare = 'BETWEEN';
                    } elseif (!empty($args['date_start'])) {
                        $value = $args['date_start'];
                        $compare = '>=';
                    } else {
                        $value = $args['date_end'];
                        $compare = '<=';
                    }

                    $query['meta_query'] = [
                        ['key' => $key, 'value' => $value, 'compare' => $compare, 'type' => $type],
                    ];
                } else {
                    $query['date_query'] = [
                        [
                            'after' => $args['date_start'],
                            'before' => $args['date_end'],
                            'inclusive' => true,
                            'column' => $args['date_column'],
                        ],
                    ];
                }
            }
        }

        Event::emit('source.resolve.posts', $query);

        $posts = get_posts($query);

        return empty($args['order_reverse']) ? $posts : array_reverse($posts);
    }

    public static function applyAutoP(string $content): string
    {
        if (app(Config::class)('~theme.disable_wpautop')) {
            return $content;
        }

        // trim leading whitespace, because ` </div>` results in `</p></div>
        return wpautop(preg_replace('/^\s+<\//m', '</', $content));
    }
}
