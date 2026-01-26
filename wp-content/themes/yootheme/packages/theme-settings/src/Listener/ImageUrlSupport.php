<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\Html\Element;
use YOOtheme\Html\Html;
use YOOtheme\Path;

class ImageUrlSupport
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        if (!$this->config->get('~theme.image_urls') && !$this->config->get('app.sef')) {
            $this->config->set(
                'customizer.panels.advanced.fields.image_urls.attrs.disabled',
                'true',
            );
        }
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function systemcheck(array $extra): array
    {
        if ($this->config->get('app.sef')) {
            $this->config->set('~theme.image_urls', true);
            $this->config->set('app.isCustomizer', true);

            /** @var Element $element */
            $element = Event::emit(
                'html.image|middleware',
                fn($element) => $element,
                Html::tag('Image', [
                    'src' => Path::relative('~', '~assets/images/element-image-placeholder.png'),
                    'formats' => false,
                    'thumbnail' => true,
                    'width' => 1,
                    'height' => 1,
                ]),
                [],
            );

            $extra['image_urls'] = $element->attr('src');
        }

        return $extra;
    }
}
