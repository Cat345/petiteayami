<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\Translator;
use YOOtheme\Url;

class LoadCustomizerData
{
    public Config $config;
    public Translator $translator;

    public function __construct(Config $config, Translator $translator)
    {
        $this->config = $config;
        $this->translator = $translator;
    }

    public function handle(): void
    {
        // add config
        $this->config->addFile('customizer', __DIR__ . '/../../config/customizer.php');

        $this->config->add('customizer', [
            'base' => Url::to($this->config->get('theme.rootDir')),
            'name' => $this->config->get('theme.name'),
            'version' => $this->config->get('theme.version'),
        ]);

        // add locale
        $locale = strtr($this->config->get('locale.code'), [
            'de_AT' => 'de_DE',
            'de_CH' => 'de_DE',
            'de_CH_informal' => 'de_DE',
            'de_DE_formal' => 'de_DE',
            'ja_JP' => 'ja',
        ]);

        $this->translator->addResource(__DIR__ . "/../../languages/{$locale}.json");
    }
}
