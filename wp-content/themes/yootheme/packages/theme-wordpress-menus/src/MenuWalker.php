<?php

namespace YOOtheme\Theme\Wordpress;

use Walker_Nav_Menu;
use YOOtheme\Config;
use YOOtheme\View;

class MenuWalker extends Walker_Nav_Menu
{
    protected View $view;
    protected Config $config;

    /**
     * @var object $item
     */
    protected $item;

    /**
     * @var list<object>
     */
    protected array $items = [];

    /**
     * @var list<object>
     */
    protected array $parents = [];

    public function __construct(View $view, Config $config)
    {
        $this->view = $view;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     *
     * @return void
     */
    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        $this->item->children = [];
        $this->parents[] = $this->item;
    }

    /**
     * @inheritdoc
     *
     * @return void
     */
    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        array_pop($this->parents);
    }

    /**
     * @inheritdoc
     *
     * @param object $item
     *
     * @return void
     */
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        // normalize menu item
        $item->id = $item->ID;
        $item->level = $depth + 1;
        $item->anchor_title = $item->attr_title;
        $item->anchor_rel = $item->xfn;
        $item->divider =
            $item->type === 'custom' && $item->url === '#' && preg_match('/---+/i', $item->title);
        $item->type = $item->type === 'custom' && $item->url === '#' ? 'heading' : $item->type;

        // set parent
        if ($this->parents) {
            array_last($this->parents)->children[] = $item;
        } else {
            $this->items[] = $item;
        }

        // Set item classes
        $item->class = implode(' ', $item->classes);

        $this->item = $item;
    }

    /**
     * @inheritdoc
     *
     * @param list<object> $elements
     * @param mixed ...$args
     *
     * @return string
     */
    public function walk($elements, $max_depth, ...$args)
    {
        parent::walk($elements, $max_depth, ...$args);

        // set menu config
        $this->config->set('~menu', (array) $args[0]);

        return $this->view->render('~theme/templates/menu/menu', ['items' => $this->items]);
    }
}
