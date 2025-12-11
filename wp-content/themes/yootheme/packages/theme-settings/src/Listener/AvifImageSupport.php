<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;

class AvifImageSupport
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        if (!$this->config->get('~theme.avif') && !$this->imageAvif()) {
            $this->config->set('customizer.panels.advanced.fields.avif.attrs.disabled', 'true');
        }
    }

    protected function imageAvif(): bool
    {
        if (function_exists('imageavif') && PHP_VERSION_ID >= 80100) {
            $image = imagecreatetruecolor(1, 1);
            $resource = fopen('php://temp', 'rw+');

            // check image size, because libgd will return true even when is compiled without avif support
            return @imageavif($image, $resource) && fstat($resource)['size'] > 0;
        }

        return false;
    }
}
