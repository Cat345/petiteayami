<?php

namespace YOOtheme\Theme\Wordpress\Polylang\Listener;

use YOOtheme\Http\Uri;

class AddLanguageParameter
{
    /**
     * @param array<string, mixed> $parameters
     */
    public static function handle(
        string $path,
        array $parameters,
        ?bool $secure,
        callable $next
    ): Uri {
        /** @var Uri $uri */
        $uri = $next($path, $parameters, $secure);

        if (!class_exists('Polylang', false)) {
            return $uri;
        }

        if (
            $uri->getPath() === admin_url('admin-ajax.php', 'relative') &&
            $uri->getQueryParam('action') === 'yootheme'
        ) {
            $query = $uri->getQueryParams();
            $query['pll_ajax_backend'] = true; // ensure polylang backend is used for ajax requests, prevent overwriting backend language with frontend language

            $uri = $uri->withQueryParams($query);
        }

        return $uri;
    }
}
