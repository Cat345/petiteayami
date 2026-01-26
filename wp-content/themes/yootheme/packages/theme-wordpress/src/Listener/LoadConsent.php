<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Theme\Consent\ConsentHelper;
use YOOtheme\View;

class LoadConsent
{
    protected View $view;
    protected ConsentHelper $consent;

    public function __construct(View $view, ConsentHelper $consent)
    {
        $this->view = $view;
        $this->consent = $consent;
    }

    public function handle(): void
    {
        $this->consent->load();
    }

    public function handleHead(): void
    {
        foreach ($this->consent->getScripts('head') as $script) {
            echo $script;
        }
    }

    public function handleBody(): void
    {
        if ($this->consent->isEnabled) {
            echo $this->view->render('~theme/templates/consent');
        }

        foreach ($this->consent->getScripts('body') as $script) {
            echo $script;
        }
    }
}
