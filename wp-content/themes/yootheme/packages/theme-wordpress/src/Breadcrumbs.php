<?php

namespace YOOtheme\Theme\Wordpress;

use WP_Post;
use WP_Post_Type;
use WP_Term;
use YOOtheme\Config;
use YOOtheme\Event;
use function YOOtheme\app;

/**
 * @phpstan-type Item array{name: string, link: string}
 * @phpstan-type Items list<Item>
 */
class Breadcrumbs
{
    /**
     * @param array<string, mixed> $options
     *
     * @return list<object>
     */
    public static function getItems(array $options = []): array
    {
        $options += [
            'show_current' => true,
            'show_home' => true,
            'home_text' => '',
        ];

        $items = [];

        if (!is_front_page()) {
            if (
                is_page() ||
                (is_home() && get_queried_object_id() == get_option('page_for_posts'))
            ) {
                $items = static::handlePage();
            } elseif (is_singular('post')) {
                $items = static::handlePost();
            } elseif (is_singular()) {
                $items = static::handleSingular();
            } elseif (is_category()) {
                $items = static::handleCategory();
            } elseif (is_tag()) {
                $items = static::handleTag();
            } elseif (is_date()) {
                $items = static::handleDate();
            } elseif (is_author()) {
                $items = static::handleAuthor();
            } elseif (is_search()) {
                $items = static::handleSearch();
            } elseif (is_tax()) {
                $items = static::handleTax();
            } elseif (is_post_type_archive()) {
                $items = static::handlePostTypeArchive();
            } elseif (is_archive()) {
                $items = static::handleArchive();
            }
        }

        $items = Event::emit('theme.breadcrumbs|filter', $items);

        if ($options['show_home']) {
            array_unshift($items, [
                'name' => $options['home_text']
                    ? __($options['home_text'], 'yootheme')
                    : __('Home'),
                'link' => app(Config::class)->get('~theme.site_url'),
            ]);
        }

        if (!$options['show_current']) {
            array_pop($items);
        } elseif ($items) {
            array_last($items)['link'] = '';
        }

        return array_map(fn($item) => (object) $item, $items);
    }

    /**
     * @param int|string $id
     * @return Items
     */
    protected static function handlePage($id = null): array
    {
        $id ??= get_queried_object_id();

        if (!$id) {
            return [];
        }

        $items = [['name' => get_the_title($id), 'link' => get_page_link($id)]];

        foreach (get_ancestors($id, 'page') as $ancestor) {
            $items[] = ['name' => get_the_title($ancestor), 'link' => get_page_link($ancestor)];
        }

        return array_reverse($items);
    }

    /**
     * @return Items
     */
    protected static function handlePost(): array
    {
        $items = [];

        if ($categories = get_the_category()) {
            $items = static::getCategories($categories[0]);
        }

        $items[] = ['name' => get_the_title(), 'link' => ''];

        return $items;
    }

    /**
     * @return Items
     */
    protected static function handleSingular(): array
    {
        $post = get_queried_object();
        $items = [];

        if ($type = static::getPostType($post->post_type)) {
            $items[] = $type;
        }

        if ($terms = static::getPostTerms($post)) {
            $items = array_merge($items, $terms);
        }

        $items[] = ['name' => get_the_title(), 'link' => ''];

        return $items;
    }

    /**
     * @return Items
     */
    protected static function handleCategory(): array
    {
        return static::getCategories(get_queried_object());
    }

    /**
     * @return Items
     */
    protected static function handleTag(): array
    {
        $items = [];

        if (get_option('show_on_front') == 'page') {
            $items = static::handlePage(get_option('page_for_posts'));
        }

        $items[] = ['name' => single_tag_title('', false), 'link' => ''];

        return $items;
    }

    /**
     * @return Items
     */
    protected static function handleDate(): array
    {
        global $post;

        $items = [];

        $month = get_the_time('m', $post);
        $year = get_the_time('Y', $post);

        switch (true) {
            case is_day():
                $day = get_the_time('d', $post);
                $items[] = [
                    'name' => $day,
                    'link' => get_day_link($year, $month, $day),
                ];
            case is_month():
                $items[] = [
                    'name' => get_the_time('F', $post),
                    'link' => get_month_link($year, $month),
                ];
            default:
                $items[] = [
                    'name' => $year,
                    'link' => get_year_link($year),
                ];
        }

        return array_reverse($items);
    }

    /**
     * @return Items
     */
    protected static function handleAuthor(): array
    {
        $user = get_queried_object();

        return [['name' => $user->display_name, 'link' => '']];
    }

    /**
     * @return Items
     */
    protected static function handleSearch(): array
    {
        return [['name' => stripslashes(strip_tags(get_search_query())), 'link' => '']];
    }

    /**
     * @return Items
     */
    protected static function handleTax(): array
    {
        $term = get_queried_object();
        $taxonomy = get_taxonomy($term->taxonomy);
        $items = static::getTermTrail($term);

        if (
            !empty($taxonomy->object_type) &&
            ($type = static::getPostType($taxonomy->object_type[0]))
        ) {
            array_unshift($items, $type);
        }

        return $items;
    }

    /**
     * @return Items
     */
    protected static function handlePostTypeArchive(): array
    {
        $item = static::getPostType(get_queried_object(), false);

        return $item ? [$item] : [];
    }

    /**
     * @return Items
     */
    protected static function handleArchive(): array
    {
        return [];
    }

    /**
     * @param Items $categories
     *
     * @return Items
     */
    protected static function getCategories(WP_Term $category, array $categories = []): array
    {
        if (!$category->parent && get_option('show_on_front') == 'page') {
            $categories = self::handlePage(get_option('page_for_posts'));
        }

        if ($category->parent) {
            $categories = static::getCategories(
                get_term($category->parent, 'category'),
                $categories,
            );
        }

        $categories[] = [
            'name' => $category->name,
            'link' => esc_url(get_category_link($category->term_id)),
        ];

        return $categories;
    }

    /**
     * @param string|WP_Post_Type $type
     *
     * @return ?Item
     */
    protected static function getPostType($type, bool $link = true): ?array
    {
        if (is_string($type)) {
            $type = get_post_type_object($type);
        }

        return $type && $type->has_archive
            ? [
                'name' => (string) apply_filters(
                    'post_type_archive_title',
                    $type->labels->name,
                    $type->name,
                ),
                'link' => $link ? (string) get_post_type_archive_link($type->name) : '',
            ]
            : null;
    }

    /**
     * @return ?Items
     */
    protected static function getPostTerms(WP_Post $post): ?array
    {
        foreach (get_object_taxonomies($post, 'object') as $taxonomy) {
            if (
                $taxonomy->public &&
                $taxonomy->show_ui &&
                $taxonomy->show_in_nav_menus &&
                ($terms = get_the_terms($post, $taxonomy->name))
            ) {
                return static::getTermTrail($terms[0]);
            }
        }

        return null;
    }

    /**
     * @return Items
     */
    protected static function getTermTrail(WP_Term $term): array
    {
        $list = [];

        foreach (get_ancestors($term->term_id, $term->taxonomy, 'taxonomy') as $termId) {
            $parent = get_term($termId);
            array_unshift($list, [
                'name' => apply_filters('single_term_title', $parent->name),
                'link' => get_term_link($parent),
            ]);
        }

        $list[] = [
            'name' => apply_filters('single_term_title', $term->name),
            'link' => get_term_link($term),
        ];

        return $list;
    }
}
