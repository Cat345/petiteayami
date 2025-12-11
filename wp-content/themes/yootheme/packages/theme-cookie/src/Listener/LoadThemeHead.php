<?php

namespace YOOtheme\Theme\Cookie\Listener;

use YOOtheme\Config;
use YOOtheme\Metadata;
use YOOtheme\Path;
use YOOtheme\View;

class LoadThemeHead
{
    public View $view;
    public Config $config;
    public Metadata $metadata;

    public function __construct(Config $config, Metadata $metadata, View $view)
    {
        $this->view = $view;
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public function handle()
    {
        if (!($mode = $this->config->get('~theme.cookie.mode'))) {
            return;
        }

        $this->config->set('theme.data.cookie', [
            'mode' => $mode,
            'template' => trim($this->view->render('~theme/templates/cookie')),
            'position' => $this->config->get('~theme.cookie.bar_position'),
        ]);

        if (!$this->config->get('app.isCustomizer')) {
            if ($custom = $this->config->get('~theme.cookie.custom_js')) {
                $this->metadata->set(
                    'script:cookie-custom',
                    "(window.\$load ||= []).push(function(c,n) {try {{$custom}\n} catch (e) {console.error(e)} n()});\n",
                );
            }

            $this->metadata->set('script:cookie', [
                'src' => Path::get('../../app/cookie.min.js', __DIR__),
                'defer' => true,
            ]);
        }
    }
}
