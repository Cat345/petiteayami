<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Application;
use YOOtheme\Arr;
use YOOtheme\Config;
use YOOtheme\Container;
use YOOtheme\Event;
use YOOtheme\Theme\Updater;

class ThemeLoader
{
    /**
     * @var array
     */
    protected static $configs = [];

    /**
     * Load theme configurations.
     *
     * @param Container $container
     * @param array     $configs
     */
    public static function load(Container $container, array $configs)
    {
        static::$configs = array_merge(static::$configs, $configs);
    }

    /**
     * Setup theme.
     *
     * @param Application $app
     * @param Config      $configuration
     */
    public static function setupTheme(Application $app, Config $configuration)
    {
        // load childtheme config
        if (is_child_theme()) {
            $app->load(get_stylesheet_directory() . '/config.php');
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
                $config = $config($configuration, $app);
            }

            $configuration->add('theme', (array) $config);
        }
    }

    /**
     * Initialize theme.
     *
     * @param Application $app
     * @param Config      $configuration
     */
    public static function initTheme(Application $app, Config $configuration)
    {
        // get config params
        $themeConfig = get_theme_mod('config', '{}');
        $themeConfig = json_decode($themeConfig, true) ?: [];

        if (empty($themeConfig)) {
            $themeConfig['version'] = $configuration('theme.version');
        }

        // merge defaults with configuration
        $configuration->set(
            '~theme',
            Arr::merge(
                $configuration('theme.defaults', []),
                static::updateConfig($app, $themeConfig),
            ),
        );

        Event::emit('theme.init');
    }

    protected static function updateConfig(Application $app, $themeConfig)
    {
        $version = $themeConfig['version'] ?? null;
        $themeConfig = $app(Updater::class)->update($themeConfig, [
            'app' => $app,
            'config' => $themeConfig,
        ]);

        if (empty($version) || $version !== $themeConfig['version']) {
            set_theme_mod('config', json_encode($themeConfig));
        }

        return $themeConfig;
    }
}
