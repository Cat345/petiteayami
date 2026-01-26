<?php

namespace YOOtheme\Theme;

use YOOtheme\Config;
use YOOtheme\Path;
use function YOOtheme\trans;

abstract class SystemCheck
{
    protected Config $config;

    /**
     * Gets the requirements.
     *
     * @return list<string>
     */
    public function getRequirements(): array
    {
        $res = [];

        if (!extension_loaded('JSON')) {
            $res[] = trans(
                '<code>json_encode()</code> must be available. Install and enable the <a href="https://php.net/manual/en/book.json.php" target="_blank">JSON</a> extension.',
            );
        }

        if (
            !extension_loaded('GD') ||
            !function_exists('imagegif') ||
            !function_exists('imagejpeg') ||
            !function_exists('imagepng')
        ) {
            $res[] = trans(
                'No image library is available to process images. Install and enable the <a href="https://php.net/manual/en/book.image.php" target="_blank">GD</a> extension.',
            );
        }

        if (!extension_loaded('mbstring')) {
            $res[] = trans(
                'Multibyte encoded strings such as UTF-8 can\'t be processed. Install and enable the <a href="https://php.net/manual/en/book.mbstring.php" target="_blank">Multibyte String extension</a>.',
            );
        }

        return $res;
    }

    /**
     * Gets the recommendations.
     *
     * @return list<string>
     */
    public function getRecommendations(): array
    {
        $res = [];

        if (!$this->hasApiKey()) {
            $res[] = trans(
                'The YOOtheme API key, which enables <span class="uk-text-nowrap">1-click</span> updates and access to the layout library, is missing. Create an API Key in your <a href="https://yootheme.com/shop/my-account/websites/" target="_blank">Account settings</a>.',
            );
        }

        if (version_compare('8.1', PHP_VERSION, '>')) {
            $res[] = trans(
                'The current PHP version %version% is outdated. Upgrade the installation, preferably to the <a href="https://php.net" target="_blank">latest PHP version</a>.',
                ['%version%' => PHP_VERSION],
            );
        }

        if (extension_loaded('GD') && !function_exists('imagewebp')) {
            $res[] = trans(
                'WebP image format isn\'t supported. Enable WebP support in the <a href="https://php.net/manual/en/image.installation.php" target="_blank">GD extension</a>.',
            );
        }

        if (!is_writable(Path::get('~theme/cache'))) {
            $res[] = trans(
                'Cache files can\'t be written. <a href="https://yootheme.com/support/yootheme-pro/joomla/file-permission-issues" target="_blank">Change the permissions</a> of the <code>%cache%</code> folder, so that the web server can write into it.',
                ['%cache%' => Path::get('~theme/cache')],
            );
        }

        if (!is_writable($this->config->get('image.cacheDir'))) {
            $res[] = trans(
                'Images can\'t be cached. <a href="https://yootheme.com/support/yootheme-pro/joomla/file-permission-issues" target="_blank">Change the permissions</a> of the <code>%cache%</code> folder, so that the web server can write into it.',
                ['%cache%' => $this->config->get('image.cacheDir')],
            );
        }

        $post_max_size = $this->parseSize((string) ini_get('post_max_size'));
        if ($post_max_size < $this->parseSize('8M')) {
            $res[] = trans(
                'A higher upload limit is recommended. Set the <code>post_max_size</code> to 8M in the <a href="https://php.net/manual/en/ini.core.php" target="_blank">PHP configuration</a>.',
            );
        }

        $upload_max_filesize = $this->parseSize((string) ini_get('upload_max_filesize'));
        if ($upload_max_filesize < $this->parseSize('8M')) {
            $res[] = trans(
                'A higher upload limit is recommended. Set the <code>upload_max_filesize</code> to 8M in the <a href="https://php.net/manual/en/ini.core.php" target="_blank">PHP configuration</a>.',
            );
        }

        $memory_limit = $this->parseSize((string) ini_get('memory_limit'));
        if ($memory_limit > 0 && $memory_limit < $this->parseSize('128M')) {
            $res[] = trans(
                'A higher memory limit is recommended. Set the <code>memory_limit</code> to 128M in the <a href="https://php.net/manual/en/ini.core.php" target="_blank">PHP configuration</a>.',
            );
        }

        if (!ini_get('allow_url_fopen')) {
            $res[] = trans(
                'YouTube poster images can not be fetched and stored locally. Set the <code>allow_url_fopen</code> directive to true in the <a href="https://php.net/manual/en/ini.core.php" target="_blank">PHP configuration</a>.',
            );
        }

        if (
            function_exists('apache_get_modules') &&
            in_array('mod_pagespeed', apache_get_modules())
        ) {
            $res[] = trans(
                'The Apache module <code>mod_pagespeed</code> can lead to display issues in the map element with OpenStreetMap.',
            );
        }

        return $res;
    }

    protected function parseSize(string $size): float
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = (float) preg_replace('/[^0-9.\-]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

    abstract protected function hasApiKey(): bool;
}
