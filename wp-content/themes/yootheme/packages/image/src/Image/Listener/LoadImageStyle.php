<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading image object cover class & styles.
 */
class LoadImageStyle
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $element = $next($element, $params);

        [$width, $height] = $element->attr('width', 'height');

        $image = $params['source'];

        if ($width && $height && (!$image || (!$image->isResizable() && !$image->isType('svg')))) {
            $element = $element->withStyle(['aspect-ratio' => "{$width} / {$height}"]);

            if ($focalPoint = $params['focal_point'] ?? []) {
                $element = $element->withStyle(['object-position' => implode(' ', $focalPoint)]);
            }
        }

        return $element;
    }
}
