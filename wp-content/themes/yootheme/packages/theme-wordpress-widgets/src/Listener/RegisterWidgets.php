<?php

namespace YOOtheme\Theme\Widgets\Listener;

use BuilderWidget;
use YOOtheme\Config;
use YOOtheme\Theme\Widgets\BreadcrumbsWidget;
use YOOtheme\Theme\Widgets\Sidebar;

class RegisterWidgets
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @link https://developer.wordpress.org/reference/hooks/widgets_init/
     */
    public function handle(): void
    {
        register_widget(BuilderWidget::class);
        register_widget(BreadcrumbsWidget::class);

        foreach ($this->config->get('theme.positions') as $id => $name) {
            Sidebar::register($id, $name);
        }
    }
}
