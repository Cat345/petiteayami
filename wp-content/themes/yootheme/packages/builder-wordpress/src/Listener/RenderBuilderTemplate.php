<?php

namespace YOOtheme\Builder\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\View;

class RenderBuilderTemplate
{
    public View $view;
    public Config $config;

    public function __construct(Config $config, View $view)
    {
        $this->view = $view;
        $this->config = $config;
    }

    public function handle($template)
    {
        if ($this->view['sections']->exists('builder')) {
            if ($this->config->get('app.template.type') === '_search') {
                echo $this->view['sections']->get('builder');
                return false;
            }

            $this->config->set('app.isBuilder', true);
            return Path::get('~theme/page.php');
        }

        return $template;
    }
}
