<?php

namespace YOOtheme\Theme\Consent\Listener;

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Theme\I18nConfig;
use YOOtheme\Theme\ThemeConfig;
use YOOtheme\View;
use function YOOtheme\app;

class LoadGoogleAnalyticsScript
{
    protected const TYPE = 'script-google-analytics';

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
        foreach ($theme->scripts as $script) {
            if ($script['type'] === self::TYPE) {
                array_push($theme->scripts, ...static::getMetadata());
                break;
            }
        }
    }

    public function head(): void
    {
        foreach ($this->theme->scripts as &$script) {
            if ($script['type'] !== self::TYPE) {
                continue;
            }

            $template = Path::join(__DIR__, '../../templates/google-analytics.php');
            $script['head'] = (new View())->render($template, ['script' => $script]);

            break;
        }
    }

    public function handle(): void
    {
        $this->config->update('customizer.script.types', function ($types = []): array {
            return [...$types, ['text' => 'Google Analytics', 'value' => self::TYPE]];
        });

        $this->config->add('customizer.panels', [
            self::TYPE => [
                'fields' => [
                    'api_key' => [
                        'label' => 'API Key',
                        'attrs' => [
                            'placeholder' => 'GT-XXXXX or G-XXXXX',
                        ],
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

    /**
     * @return array<array<string, string>>
     */
    protected static function getMetadata(): array
    {
        /** @var I18nConfig $i18n */
        $i18n = app(I18nConfig::class);

        return [
            [
                'type' => 'service-google-analytics',
                'category' => 'statistics',
                'service' => 'google_analytics',
                'service_title' => $i18n->get('consent.service_google_analytics'),
            ],
            [
                'type' => 'service-google-advertising',
                'category' => 'marketing',
                'service' => 'google_ads',
                'service_title' => $i18n->get('consent.service_google_advertising'),
            ],
        ];
    }
}
