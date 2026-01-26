<?php

namespace YOOtheme\Theme\Consent;

use Generator;
use YOOtheme\Config;
use YOOtheme\Html\Attributes;
use YOOtheme\Metadata;
use YOOtheme\Theme\ThemeConfig;

/**
 * There are 3 types of consent:
 *  - 'none': scripts are loaded without consent management
 *  - 'opt-in','opt-out': scripts are deferred with 'text/plain' until user gives consent
 *  - '': scripts are managed by 3rd party consent solution and deferred with 'text/plain'
 */
class ConsentHelper
{
    public const CATEGORIES = ['functional', 'preferences', 'statistics', 'marketing'];

    public bool $isEnabled = false;
    protected bool $isCustomizer = false;
    protected string $prefix = '~theme.consent.';
    protected string $dataCategory = 'data-category';

    protected Config $config;
    protected Metadata $metadata;
    protected ThemeConfig $theme;

    /**
     * @var list<string>
     */
    protected array $categories = ['functional'];

    /**
     * @var array<string, array<string, array{title: string}>>
     */
    protected array $services = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $scripts = [];

    public function __construct(Config $config, Metadata $metadata, ThemeConfig $theme)
    {
        $this->config = $config;
        $this->metadata = $metadata;
        $this->theme = $theme;

        $this->isCustomizer = $config->get('app.isCustomizer', false);

        $type = $config->get("{$this->prefix}type");
        $this->isEnabled = ($type && $type !== 'none') || $this->isCustomizer;
    }

    public function load(): void
    {
        $config = $this->getConfig();

        // unset type, customizer initializes consent
        if ($this->isCustomizer) {
            $config['type'] = '';
        }

        // skip consent initialization, if type is none
        if ($config['type'] === 'none') {
            return;
        }

        $this->metadata->set('script:consent', [
            'src' => '~assets/site/js/consent.js',
            'type' => 'module',
        ]);

        $this->metadata->set(
            'script:consent-data',
            sprintf('window.yootheme ||= {}; yootheme.consent = %s;', json_encode($config)),
            $this->isCustomizer ? ['data-preview' => 'diff'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $config = [];

        foreach (['type', 'banner_layout'] as $key) {
            $config[$key] = $this->config->get($this->prefix . $key, '');
        }

        foreach ($this->theme->scripts as $script) {
            $category = $script['category'] ?? 'functional';
            $service = $script['service'] ?? '';

            $this->categories[] = $category;

            if ($service) {
                $this->services[$category][$service] ??= [
                    'title' => $script['service_title'] ?? $service,
                ];
            }
        }

        foreach ($this->getCategories() as $category) {
            $config['categories'][$category] = array_keys($this->getServices($category));
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return array_intersect(self::CATEGORIES, $this->categories);
    }

    /**
     * @return array<string, array{title: string}>
     */
    public function getServices(string $category): array
    {
        return $this->services[$category] ?? [];
    }

    /**
     * @return array<string>
     */
    public function getScripts(string $type): array
    {
        $update = $this->config->get("{$this->prefix}type") !== 'none' && !$this->isCustomizer;

        if (!isset($this->scripts[$type])) {
            foreach ($this->theme->scripts as $script) {
                if (empty($script[$type])) {
                    continue;
                }

                $this->scripts[$type][] = $update
                    ? $this->updateScript(
                        $script[$type],
                        $script['category'] ?? 'functional',
                        $script['service'] ?? '',
                    )
                    : $script[$type];
            }
        }

        return $this->scripts[$type] ?? [];
    }

    protected function updateScript(string $script, string $category, string $service): string
    {
        return preg_replace_callback(
            '#<script(.*?)>#i',
            function ($matches) use ($category, $service) {
                $attributes = [
                    'type' => 'text/plain',
                    $this->dataCategory => $category . ($service ? ".{$service}" : ''),
                ];

                // set parsed attributes and rename `type` to `data-type`
                foreach ($this->parseAttributes($matches[1]) as $name => $value) {
                    $attributes[$name === 'type' ? 'data-type' : $name] = $value;
                }

                // if category is `functional`, return it as is
                if (str_starts_with($attributes[$this->dataCategory], 'functional')) {
                    return $matches[0];
                }

                return '<script ' . (new Attributes())->merge($attributes) . '>';
            },
            stripslashes($script),
        );
    }

    /**
     * @return Generator<string, ?string>
     */
    protected function parseAttributes(string $input): Generator
    {
        $regex = '/([\w-]+)(?:\s*=\s*(?:(["\'])(.*?)\2|([^\s"\'=<>`]+)))?/';

        preg_match_all($regex, $input, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            yield $match[1] => $match[3] ?? '' ?: $match[4] ?? '';
        }
    }
}
