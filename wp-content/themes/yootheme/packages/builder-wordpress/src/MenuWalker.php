<?php

namespace YOOtheme\Builder\Wordpress;

use WP_Post;
use WP_Term;

/**
 * @phpstan-type Args array<string, mixed>
 */
class MenuWalker extends \Walker
{
    /**
     * @var list<WP_Post|WP_Term>
     */
    protected array $items = [];

    /**
     * @var list<WP_Post|WP_Term>
     */
    protected array $parents = [];

    /**
     * Constructor.
     *
     * @param array{id: string, parent: string} $db_fields
     */
    public function __construct(array $db_fields)
    {
        $this->db_fields = $db_fields;
    }

    /**
     * Walk items recursively.
     *
     * @param list<WP_Post|WP_Term> $items
     * @param array<string, mixed> $args
     *
     * @return list<WP_Post|WP_Term>
     */
    public function __invoke(array $items, array $args = []): array
    {
        $args += [
            'filter' => null,
            'base_item' => null,
            'start_level' => 1,
            'end_level' => 0,
            'show_all_children' => true,
        ];

        if ($args['base_item']) {
            $this->set_base($items, $args['base_item']);
        }

        $args['start_level'] = max(1, (int) $args['start_level']);

        $this->walk($items, 0, $args);

        return $this->items;
    }

    /**
     * Set base item and its parents.
     *
     * @param list<WP_Post|WP_Term> $items
     * @param string|int $item_id
     */
    public function set_base(array $items, $item_id): void
    {
        ['id' => $id, 'parent' => $parent] = $this->db_fields;

        $items = array_column($items, null, $id);

        while ($item_id && ($item = $items[$item_id] ?? null)) {
            $item_id = $item->$parent;
            $item->base_item = true; // @phpstan-ignore-line
        }
    }

    /**
     * @inheritdoc
     *
     * @param ?Args $args
     *
     * @return void
     */
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $parent = end($this->parents);

        if (is_callable($args['filter'])) {
            $item = $args['filter']($item, $depth, $args, $id);
        }

        // add start level items
        // @phpstan-ignore-next-line
        if ((!$parent || $parent->base_item) && $item->level === $args['start_level']) {
            $this->items[] = $item;
        }

        // add child items
        if ($parent && (!$args['end_level'] || $item->level <= $args['end_level'])) {
            $parent->children[] = $item; // @phpstan-ignore-line
        }

        // add parent item
        if ($this->has_children) {
            $this->parents[] = $item;
            $item->children = [];
        }
    }

    /**
     * @inheritdoc
     *
     * @param ?Args $args
     *
     * @return void
     */
    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        $parent = array_pop($this->parents);

        if (!$args['show_all_children'] && empty($parent->base_item)) {
            $parent->children = []; // @phpstan-ignore-line
        }
    }
}
