<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading cover images.
 */
class LoadImageCover
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $image = $params['source'];

        $element = $next($element, $params);

        if ($element->attr('uk-cover') && (!$image || !$image->isType('svg'))) {
            [$width, $height] = $element->attr('width', 'height');
            if ((int) $width && (int) $height) {
                $ratio = round(($width / $height) * 100);
                $sizes = "(max-aspect-ratio: {$width}/{$height}) {$ratio}vh";
                $children = $element->children();

                $element = $children
                    ? $element->withChildren(
                        array_map(fn($child) => $child->withAttr('sizes', $sizes), $children),
                    )
                    : $element->withAttr('sizes', $sizes);
            }
        }

        return $element;
    }
}
