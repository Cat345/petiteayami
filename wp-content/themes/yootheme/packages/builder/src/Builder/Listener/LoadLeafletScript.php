<?php

namespace YOOtheme\Builder\Listener;

use YOOtheme\Config;
use YOOtheme\Theme\I18nConfig;
use YOOtheme\Theme\ThemeConfig;
use YOOtheme\Url;
use function YOOtheme\app;
use function YOOtheme\trans;

class LoadLeafletScript
{
    protected const TYPE = 'script-maps-openstreetmap';

    protected Config $config;
    protected ThemeConfig $theme;

    public function __construct(Config $config, ThemeConfig $theme)
    {
        $this->config = $config;
        $this->theme = $theme;
    }

    /**
     * @param ThemeConfig $theme
     */
    public static function config($theme): void
    {
        foreach ($theme->scripts as &$script) {
            if ($script['type'] !== self::TYPE) {
                continue;
            }

            /** @var I18nConfig $i18n */
            $i18n = app(I18nConfig::class);

            $script['category'] = 'preferences';
            $script['service'] = 'openstreetmap';
            $script['service_title'] = $i18n->get('consent.service_openstreetmap');

            $script['element']['consent_icon'] = '~assets/images/consent_icon_openstreetmap.svg';

            if (empty($script['element']['consent_content'])) {
                $script['element']['consent_content'] = $i18n->get('consent.text_openstreetmap');
            }

            break;
        }
    }

    public function body(): void
    {
        foreach ($this->theme->scripts as &$script) {
            if ($script['type'] !== self::TYPE) {
                continue;
            }

            if (!empty($script['active'])) {
                $script['body'] = $this->renderScript();
            }

            break;
        }
    }

    protected function renderScript(): string
    {
        return sprintf(
            '<script src="%s" type="module" data-map></script>',
            Url::to('~assets/site/js/map-leaflet.js'),
        );
    }

    public function handle(): void
    {
        $this->config->update('customizer.script.types', function ($types = []): array {
            return [...$types, ['text' => trans('OpenStreetMap'), 'value' => self::TYPE]];
        });

        $this->config->add('customizer.panels', [
            self::TYPE => [
                'fields' => [
                    'element.consent_content' => [
                        'label' => trans('Placeholder Content'),
                        'description' => trans(
                            'Set an alternative text for the placeholder shown in the element before consent is given.',
                        ),
                        'type' => 'editor',
                        'editor' => 'code',
                    ],

                    'status' => [
                        'label' => 'Status',
                        'description' => 'Disable the script and publish it later.',
                        'type' => 'checkbox',
                        'text' => 'Disable script',
                        'attrs' => [
                            'true-value' => 'disabled',
                            'false-value' => '',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
