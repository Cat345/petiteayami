<?php

namespace YOOtheme\Theme\Widgets\Listener;

use YOOtheme\Config;
use YOOtheme\Theme\Widgets\WidgetConfig;

class LoadWidgetData
{
    public Config $config;
    public WidgetConfig $widget;

    public function __construct(Config $config, WidgetConfig $widget)
    {
        $this->config = $config;
        $this->widget = $widget;
    }

    public function handle(): void
    {
        $this->config->add('customizer', ['widget' => $this->widget->getArrayCopy()]);
        $this->config->addFile('customizer', __DIR__ . '/../../config/customizer.php');
        $this->config->addFile('customizer.panels.widget', __DIR__ . '/../../config/widgets.php');
    }
}
