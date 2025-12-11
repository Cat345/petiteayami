<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Url;

class UrlLoader
{
    public const REGEX_URL = '/
                        \s                                      # match a space
                        (?<attr>(?:data-)?(?:href|src|poster))= # match the attribute
                        ([\"\'])                                # start with a single or double quote
                        (?!\/|\#|[a-z0-9\-\.]+\:)               # make sure it is a relative path
                        (?<url>[^\"\'>]+)                       # match the actual src value
                        \2                                      # match the previous quote
                       /xiU';

    public static function resolveRelativeUrl($name, $parameters, $next)
    {
        if (!is_string($content = $next($name, $parameters))) {
            return $content;
        }

        // Apply to root template view only
        if (empty($parameters['_root'])) {
            return $content;
        }

        return preg_replace_callback(
            static::REGEX_URL,
            fn($matches) => sprintf(
                ' %s="%s"',
                $matches['attr'],
                htmlentities(Url::to(html_entity_decode($matches['url']))),
            ),
            $content,
        );
    }
}
