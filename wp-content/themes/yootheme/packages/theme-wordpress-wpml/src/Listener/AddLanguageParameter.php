<?php

namespace YOOtheme\Theme\Wordpress\WPML\Listener;

use YOOtheme\Http\Uri;
use YOOtheme\Wordpress\Router;

/**
 * Fix WPML_Locale::locale because it uses the wrong language for ajax requests.
 */
class AddLanguageParameter
{
    /**
     * @param array<string, mixed>  $parameters
     *
     * @return Uri
     */
    public static function handle(string $path, array $parameters, ?bool $secure, callable $next)
    {
        /** @var Uri $uri */
        $uri = $next($path, $parameters, $secure);

        if (!class_exists('SitePress', false)) {
            return $uri;
        }

        // Add language query parameter to customizer route
        if ($uri->getQueryParam(Router::ROUTE_NAME) === 'customizer') {
            $query = $uri->getQueryParams();
            $query['lang'] = get_user_locale();

            $uri = $uri->withQueryParams($query);
        }

        return $uri;
    }
}
