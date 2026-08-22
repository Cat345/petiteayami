<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Theme\Consent\ConsentHelper;
use YOOtheme\View;

class LoadConsent
{
    protected View $view;
    protected Config $config;
    protected ConsentHelper $consent;

    public function __construct(View $view, Config $config, ConsentHelper $consent)
    {
        $this->view = $view;
        $this->config = $config;
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
            if (!$this->config->get('~theme.consent.privacy_policy_link')) {
                $this->config->set('~theme.consent.privacy_policy_link', get_privacy_policy_url());
            }

            echo $this->view->render('~theme/templates/consent');
        }

        foreach ($this->consent->getScripts('body') as $script) {
            echo $script;
        }
    }
}
