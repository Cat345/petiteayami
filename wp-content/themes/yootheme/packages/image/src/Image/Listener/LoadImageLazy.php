<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading images with lazy or eager attribute.
 */
class LoadImageLazy
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $element = $next($element, $params);

        $loading = $element->attr('loading') ?? 'lazy';

        return $element->withAttr('loading', $loading === 'lazy' ? 'lazy' : false);
    }
}
