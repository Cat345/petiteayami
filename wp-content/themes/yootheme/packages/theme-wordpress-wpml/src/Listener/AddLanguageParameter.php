<?php

namespace YOOtheme\Theme\Wordpress\WPML\Listener;

use YOOtheme\Http\Uri;

/**
 * Fix WPML_Locale::locale because it uses the wrong language for ajax requests.
 */
class AddLanguageParameter
{
    public static function handle($path, $parameters, $secure, callable $next)
    {
        /** @var Uri $uri */
        $uri = $next($path, $parameters, $secure);

        if (!class_exists('SitePress', false)) {
            return $uri;
        }

        // Add language query parameter to customizer route
        if ($uri->getQueryParam('p') === 'customizer') {
            $query = $uri->getQueryParams();
            $query['lang'] = get_user_locale();

            $uri = $uri->withQueryParams($query);
        }

        return $uri;
    }
}
