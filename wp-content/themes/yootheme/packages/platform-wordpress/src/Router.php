<?php

namespace YOOtheme\Wordpress;

use WP_REST_Request;
use YOOtheme\Event;
use YOOtheme\Url;
use function YOOtheme\app;

class Router
{
    public static function register()
    {
        register_rest_route('yootheme', '/(?P<route>[a-z0-9-]+)', [
            'methods' => ['GET', 'POST'],
            'callback' => [static::class, 'handle'],
            'permission_callback' => fn() => true,
        ]);
    }

    public static function handle(WP_REST_Request $request)
    {
        $route = $request->get_param('route');

        Event::on(
            'app.request',
            fn($request, callable $next) => $next(
                $request->withQueryParams(array_merge($request->getQueryParams(), ['p' => $route])),
            ),
            50,
        );

        Platform::handleRoute(app());
    }

    public static function generate($pattern = '', array $parameters = [], $secure = null)
    {
        if ($pattern === 'image') {
            return Url::to(rest_url("yootheme/{$pattern}"), $parameters, $secure);
        }

        if ($pattern) {
            $parameters = ['p' => $pattern] + $parameters;
        }

        return Url::to(admin_url('admin-ajax.php'), ['action' => 'kernel'] + $parameters, $secure);
    }
}
