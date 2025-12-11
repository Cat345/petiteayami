<?php

namespace YOOtheme\Builder\Wordpress\Listener;

use YOOtheme\View;

class RenderBuilder
{
    public View\SectionHelper $sections;

    public function __construct(View $view)
    {
        $this->sections = $view['sections'];
    }

    public function handle()
    {
        // Force builder to be evaluated before header
        if ($this->sections->exists('builder')) {
            $this->sections->set('builder', $this->sections->get('builder'));
        }
    }
}
