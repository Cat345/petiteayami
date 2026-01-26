<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Http\Request;
use YOOtheme\Http\Response;
use function YOOtheme\app;

class CheckUserCapability
{
    /**
     * Check capability of current user.
     *
     * @param Request $request (event parameter, not injected)
     */
    public static function handle($request, callable $next): Response
    {
        // check user capabilities
        if (!$request->getAttribute('allowed') && !current_user_can('edit_theme_options')) {
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
}
