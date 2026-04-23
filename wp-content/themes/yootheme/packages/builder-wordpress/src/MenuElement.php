<?php

namespace YOOtheme\Builder\Wordpress;

use WP_Error;
use WP_Post;
use WP_Term;
use YOOtheme\Arr;
use YOOtheme\Config;
use YOOtheme\View;
use function YOOtheme\app;

/**
 * @phpstan-type Props array<string, mixed>
 */
class MenuElement
{
    /**
     * @var list<string>
     */
    protected static array $menuProps = [
        'type',
        'divider',
        'style',
        'size',
        'image_width',
        'image_height',
        'image_svg_inline',
        'image_margin',
        'image_align',
        'text_align',
        'text_align_breakpoint',
        'text_align_fallback',
    ];

    public static function render(object $node): bool
    {
        $items = !$node->props['taxonomy']
            ? static::getMenu($node->props)
            : static::getTerms($node->props);

        if ($items) {
            // set menu config
            app(Config::class)->set('~menu', Arr::pick($node->props, static::$menuProps));

            // render menu template
            $node->content = app(View::class)->render('~theme/templates/menu/menu', [
                'items' => $items,
            ]);
        }

        return $node->content != '';
    }

    /**
     * @param Props $props
     * @return list<WP_Post>
     */
    protected static function getMenu(array $props): array
    {
        $items = wp_get_nav_menu_items($props['menu'] ?? (wp_get_nav_menus()[0] ?? null)) ?: [];

        $walker = new MenuWalker([
            'id' => 'db_id',
            'parent' => 'menu_item_parent',
        ]);

        $props += [
            'filter' => function (object $item, int $depth) {
                $item->id = $item->ID;
                $item->level = $depth + 1;
                $item->anchor_title = $item->attr_title;
                $item->anchor_rel = $item->xfn;
                $item->divider =
                    $item->type === 'custom' &&
                    $item->url === '#' &&
                    preg_match('/---+/i', $item->title);
                $item->type =
                    $item->type === 'custom' && $item->url === '#' ? 'heading' : $item->type;
                $item->class = implode(' ', $item->classes);

                return $item;
            },

            'base_item' => $props['menu_base_item'],
        ];

        if ($items) {
            _wp_menu_item_classes_by_context($items);
            apply_filters('wp_nav_menu_objects', $items, []);
        }

        if (empty($props['base_item'])) {
            $active = null;
            foreach ($items as $item) {
                if ($active && $active->db_id !== (int) $item->menu_item_parent) {
                    break;
                }

                if ($item->active ?? null) {
                    $active = $item;
                }
            }

            $props['base_item'] = $active ? $active->db_id : null;
        }

        return $walker($items, $props);
    }

    /**
     * @param Props $props
     * @return list<WP_Term>
     */
    protected static function getTerms(array $props): array
    {
        $terms = get_terms([
            'taxonomy' => $props['taxonomy'],

            // `term_order` will work for the 3rd party plugin `Category Order and Taxonomy Terms Order` only, falls back to term_id otherwise
            'orderby' => $props['taxonomy'] === 'product_cat' ? 'menu_order' : 'term_order',
        ]);

        $walker = new MenuWalker([
            'id' => 'term_id',
            'parent' => 'parent',
        ]);

        $activeTerm = static::getTermId($props['taxonomy']);

        $props += [
            'filter' => function (object $item, int $depth) {
                $item->id = $item->term_id;
                $item->level = $depth + 1;
                $item->title = $item->name;
                $item->url = get_term_link($item);
                $item->class = '';

                return $item;
            },

            'base_item' => $props['taxonomy_base_item'] ?: $activeTerm,
        ];

        if ($terms instanceof WP_Error) {
            $terms = [];
        }

        if ($terms) {
            while (
                $activeTerm &&
                ($term = array_find($terms, fn($term) => $term->term_id === $activeTerm))
            ) {
                $term->active = true;
                $activeTerm = $term->parent;
            }
        }

        return $walker($terms, $props);
    }

    protected static function getTermId(string $taxonomy): ?int
    {
        $object = get_queried_object();

        if (is_tax($taxonomy)) {
            return $object->term_id;
        }

        if (is_single()) {
            $terms = wp_get_object_terms($object->ID, $taxonomy, [
                'fields' => 'ids',
                'number' => 1,
            ]);

            return $terms[0] ?? null;
        }

        return null;
    }
}
