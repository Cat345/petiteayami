<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\File;
use YOOtheme\Metadata;
use YOOtheme\Path;

class LoadThemeHead
{
    public Config $config;
    public Metadata $metadata;

    public function __construct(Config $config, Metadata $metadata)
    {
        $this->config = $config;
        $this->metadata = $metadata;
    }

    public function handle(): void
    {
        $rtl = $this->config->get('~theme.direction') == 'rtl' ? '{.rtl,}' : '';
        $href = File::find("~theme/css/theme{.{$this->config->get('theme.id')},}{$rtl}.css");
        $debug = $this->config->get('app.debug') ? '' : '.min';
        $version = filectime($href);

        [$style] = explode(':', $this->config->get('~theme.style'));

        $this->metadata->set(
            'style:theme',
            compact('href', 'version') +
                ($this->config->get('app.isCustomizer') ? ['id' => 'theme-style'] : []),
        );

        if (filectime(__FILE__) >= $version) {
            $this->metadata->set('style:theme-update', ['href' => '~theme/css/theme.update.css']);
        }

        $this->metadata->set('script:theme-uikit', [
            'src' => "~assets/uikit/dist/js/uikit{$debug}.js",
        ]);

        $this->metadata->set('script:theme-uikit-icons', [
            'src' => File::find("~assets/uikit/dist/js/uikit-icons{-{$style},}.min.js"),
        ]);

        $this->metadata->set('script:theme', ['src' => '~theme/js/theme.js']);
        $this->metadata->set(
            'script:theme-data',
            sprintf(
                'window.yootheme ||= {}; var $theme = yootheme.theme = %s;',
                json_encode($this->config->get('theme.data', (object) [])),
            ),
            $this->config->get('app.isCustomizer') ? ['data-preview' => 'diff'] : [],
        );

        if ($this->config->get('app.isCustomizer')) {
            $this->metadata->set('script:customizer-site', [
                'src' => Path::get('../../assets/js/customizer.min.js', __DIR__),
            ]);
        }

        if ($custom = File::get('~theme/css/custom.css')) {
            $this->metadata->set('style:theme-custom', ['href' => $custom]);
        }

        if ($custom = File::get('~theme/js/custom.js')) {
            $this->metadata->set('script:theme-custom', ['src' => $custom]);
        }
    }
}
