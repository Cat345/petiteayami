<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\Metadata;
use YOOtheme\Translator;
use YOOtheme\Url;

class LoadConfigData
{
    public Config $config;
    public Metadata $metadata;
    public Translator $translator;

    public function __construct(Config $config, Metadata $metadata, Translator $translator)
    {
        $this->config = $config;
        $this->metadata = $metadata;
        $this->translator = $translator;
    }

    public function handle(): void
    {
        $values = [
            'api' => 'https://api.yootheme.com',
            'url' => Url::base(),
            'route' => Url::route(),
            'csrf' => $this->config->get('session.token'),
            'base' => Url::to($this->config->get('theme.rootDir')),
            'assets' => Url::to('~assets'),
            'locale' => $this->config->get('locale.code'),
            'locales' => $this->translator->getResources(),
            'timezone' => $this->config->get('locale.timezone'),
            'platform' => $this->config->get('app.platform'),
        ];

        $this->metadata->set(
            'script:config',
            sprintf('window.yootheme ||= {}; yootheme.config = %s;', json_encode($values)),
        );
    }
}
