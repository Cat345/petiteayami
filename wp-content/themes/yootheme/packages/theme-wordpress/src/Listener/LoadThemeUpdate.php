<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;
use YOOtheme\Wordpress\ThemeUpdate;

class LoadThemeUpdate
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle()
    {
        // register theme update
        $update = new ThemeUpdate('yootheme');
        $update->setQuery(['key' => $this->config->get('~theme.yootheme_apikey')]);
        $update->setStability($this->config->get('~theme.minimum_stability', ''));

        // @link https://developer.wordpress.org/reference/hooks/upgrader_pre_install/
        // @link https://developer.wordpress.org/reference/hooks/upgrader_post_install/
        add_filter('upgrader_pre_install', [$this, 'backupFiles'], 10, 2);
        add_filter('upgrader_post_install', [$this, 'backupFiles'], 10, 2);
    }

    public function backupFiles($return, $package)
    {
        $theme = wp_get_theme();

        if ($theme->get_template() !== ($package['theme'] ?? '') || is_wp_error($return)) {
            return $return;
        }

        $paths = [$theme->get_template_directory(), WP_CONTENT_DIR . '/upgrade'];
        $reverse = current_action() === 'upgrader_post_install';
        [$src, $dest] = $reverse ? array_reverse($paths) : $paths;

        // copy theme css and font files
        $this->copyFiles("{$src}/css", "{$dest}/css", '/\.update\.css$/', $reverse);
        $this->copyFiles("{$src}/fonts", "{$dest}/fonts", '', $reverse);

        // move cache files
        $this->moveFolder("{$src}/cache", "{$dest}/cache");

        return $return;
    }

    protected function copyFiles($src, $dest, $ignoreFile = '', $deleteDir = false)
    {
        /** @var \WP_Filesystem_Base $wp_filesystem */
        global $wp_filesystem;

        if (!$wp_filesystem->is_dir($dest)) {
            $wp_filesystem->mkdir($dest);
        }

        foreach (glob("{$src}/*") as $file) {
            $filename = basename($file);

            if ($ignoreFile && preg_match($ignoreFile, $filename)) {
                continue;
            }

            $wp_filesystem->copy($file, "{$dest}/{$filename}", true);
        }

        if ($deleteDir) {
            $wp_filesystem->delete($src, true);
        }
    }

    protected function moveFolder($src, $dest)
    {
        /** @var \WP_Filesystem_Base $wp_filesystem */
        global $wp_filesystem;

        $wp_filesystem->move($src, $dest, true);
    }
}
