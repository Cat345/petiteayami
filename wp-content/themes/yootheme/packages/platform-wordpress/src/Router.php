<?php

namespace YOOtheme\Wordpress;

use YOOtheme\Config;
use YOOtheme\Url;
use function YOOtheme\app;

class Router
{
    public const ROUTE_NAME = 'yootheme';

    public static function parse(): void
    {
        global $wp;

        if (empty($wp->query_vars[static::ROUTE_NAME])) {
            return;
        }

        // set route as query parameter
        $_GET[static::ROUTE_NAME] = $wp->query_vars[static::ROUTE_NAME];

        Platform::handleRoute(app());
    }

    public static function rewrite(): void
    {
        global $wp_rewrite;

        $rules = get_option('rewrite_rules', []);
        $query = 'index.php?' . static::ROUTE_NAME . '=$matches[1]';
        $route = static::getUploadDir('/' . static::ROUTE_NAME . '(/.*)');

        // add rewrite rules for routes
        $wp_rewrite->add_rule("^{$route}$", $query, 'top');
        $wp_rewrite->add_rule("^{$wp_rewrite->index}/{$route}$", $query, 'top');

        // maybe flush rewrite rules if it was not previously in the option
        if (empty($rules["^{$route}$"])) {
            $wp_rewrite->flush_rules(false);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return string|false
     */
    public static function generate(
        string $pattern = '',
        array $parameters = [],
        ?bool $secure = null
    ) {
        if (
            get_option('permalink_structure') &&
            str_starts_with($pattern, 'cache/') &&
            app(Config::class)->get('~theme.image_urls')
        ) {
            return Url::to(
                static::getUploadDir('/' . static::ROUTE_NAME . "/{$pattern}"),
                $parameters,
                $secure,
            );
        }

        if (is_admin() || $pattern === 'customizer') {
            return Url::to(
                admin_url('admin-ajax.php'),
                ['action' => static::ROUTE_NAME, static::ROUTE_NAME => $pattern ?: null] +
                    $parameters,
                $secure,
            );
        }

        if ($pattern) {
            $parameters = [static::ROUTE_NAME => $pattern] + $parameters;
        }

        return Url::to(site_url('index.php'), $parameters, $secure);
    }

    /**
     * @param array<string> $vars
     *
     * @return array<string>
     */
    public static function addQueryVars(array $vars): array
    {
        return [...$vars, static::ROUTE_NAME];
    }

    protected static function getUploadDir(string $path = ''): string
    {
        $upload = wp_get_upload_dir()['baseurl'];
        $prefix = substr($upload, strlen(site_url()) + 1);

        return $prefix . $path;
    }
}
