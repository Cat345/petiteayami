<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use function YOOtheme\app;

class CheckUserCapability
{
    public static array $themeRoutes = [
        '/builder/template' => ['GET', 'POST', 'DELETE'],
        '/builder/template/reorder' => ['POST'],
        '/cache' => ['GET'],
        '/cache/clear' => ['POST'],
        '/import' => ['POST'],
        '/styler/library' => ['GET', 'POST', 'DELETE'],
        '/systemcheck' => ['GET'],
        '/theme/style' => ['GET', 'POST'],
        '/theme/styles' => ['GET'],
    ];

    /**
     * Check capability of current user.
     *
     * @param Request $request (event parameter, not injected)
     */
    public static function handle($request, callable $next): Response
    {
        // check user capabilities
        if (!$request->getAttribute('allowed') && !static::hasPermission($request)) {
            // redirect guest user to user login
            if (
                !is_user_logged_in() &&
                str_contains($request->getHeaderLine('Accept'), 'text/html')
            ) {
                return app(Response::class)->withRedirect(
                    wp_login_url((string) $request->getUri()),
                );
            }

            $request->abort(403, 'Insufficient User Rights.');
        }

        return $next($request);
    }

    protected static function hasPermission($request): bool
    {
        if (current_user_can('edit_theme_options')) {
            return true;
        }

        $route = $request->getAttribute('route');
        if (in_array($request->getMethod(), static::$themeRoutes[$route->getPath()] ?? [])) {
            return false;
        }

        if (
            $request->getAttribute('customizer') &&
            $request->getQueryParam('section') !== 'builder'
        ) {
            return false;
        }

        return current_user_can('edit_posts');
    }
}
