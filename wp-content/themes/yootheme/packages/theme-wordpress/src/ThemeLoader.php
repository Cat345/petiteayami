<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Arr;
use YOOtheme\Config;
use YOOtheme\Container;
use YOOtheme\Event;
use YOOtheme\Theme\Updater;
use function YOOtheme\app;

/**
 * @phpstan-type Configs list<callable|array<string, mixed>>
 */
class ThemeLoader
{
    /**
     * @var Configs
     */
    protected static array $configs = [];

    /**
     * Load theme configurations.
     *
     * @param Container $container
     * @param Configs   $configs
     */
    public static function load(Container $container, array $configs): void
    {
        static::$configs = array_merge(static::$configs, $configs);
    }

    /**
     * Setup theme.
     */
    public static function setupTheme(): void
    {
        /** @var Config $configuration */
        $configuration = app(Config::class);

        // load childtheme config
        if (is_child_theme()) {
            app()->load(get_stylesheet_directory() . '/config.php');
        }

        $configuration->add('theme', [
            'id' => get_current_blog_id(),
            'active' => true,
            'default' => is_main_site(),
            'template' => get_template(),
        ]);

        // add configurations
        foreach (static::$configs as $config) {
            if ($config instanceof \Closure) {
                $config = $config($configuration, app());
            }

            $configuration->add('theme', (array) $config);
        }
    }

    /**
     * Initialize theme.
     */
    public static function initTheme(): void
    {
        /** @var Config $configuration */
        $configuration = app(Config::class);

        // get config params
        $themeConfig = get_theme_mod('config', '{}');
        $themeConfig = json_decode($themeConfig, true) ?: [];

        // merge defaults with configuration
        $configuration->set(
            '~theme',
            Arr::merge($configuration('theme.defaults', []), static::updateConfig($themeConfig)),
        );

        Event::emit('theme.init');
    }

    /**
     * @param array<string, mixed> $themeConfig
     *
     * @return array<string, mixed>
     */
    protected static function updateConfig(array $themeConfig): array
    {
        $version = $themeConfig['version'] ?? null;

        if (empty($themeConfig)) {
            /** @var Config $config */
            $config = app(Config::class);
            $themeConfig['version'] = $config('theme.version');
        }

        $themeConfig = app(Updater::class)->update($themeConfig, [
            'app' => app(),
            'config' => $themeConfig,
        ]);

        if ($version !== $themeConfig['version']) {
            set_theme_mod('config', json_encode($themeConfig));
        }

        return $themeConfig;
    }
}
