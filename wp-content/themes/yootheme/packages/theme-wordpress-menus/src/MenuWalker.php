<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Config;
use YOOtheme\View;

class MenuWalker extends \Walker_Nav_Menu
{
    /**
     * @var View
     */
    protected $view;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \WP_Post
     */
    protected $item;

    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var array
     */
    protected $parents = [];

    /**
     * Constructor.
     *
     * @param View   $view
     * @param Config $config
     */
    public function __construct(View $view, Config $config)
    {
        $this->view = $view;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        $this->item->children = [];
        $this->parents[] = $this->item;
    }

    /**
     * @inheritdoc
     */
    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        array_splice($this->parents, -1);
    }

    /**
     * @inheritdoc
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
        if (count($this->parents)) {
            $this->parents[count($this->parents) - 1]->children[] = $item;
        } else {
            $this->items[] = $item;
        }

        // Set item classes
        $item->class = implode(' ', $item->classes);

        $this->item = $item;
    }

    /**
     * @inheritdoc
     */
    public function walk($elements, $max_depth, ...$args)
    {
        parent::walk($elements, $max_depth, ...$args);

        // set menu config
        $this->config->set('~menu', (array) $args[0]);

        return $this->view->render('~theme/templates/menu/menu', ['items' => $this->items]);
    }
}
