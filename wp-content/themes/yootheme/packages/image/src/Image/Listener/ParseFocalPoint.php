<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for parsing the focal point attribute.
 */
class ParseFocalPoint
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        /**
         * @var string|null $focalPoint
         */
        $focalPoint = $element->attr('focal_point');

        if ($focalPoint) {
            $params['focal_point'] = array_reverse(array_filter(explode('-', $focalPoint)));
        }

        return $next($element->withoutAttr('focal_point'), $params);
    }
}
