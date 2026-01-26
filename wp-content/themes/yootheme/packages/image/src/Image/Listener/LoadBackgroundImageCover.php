<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading cover images.
 */
class LoadBackgroundImageCover
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $image = $params['target'] ?? $params['source'];

        if (!$image || $image->isType('svg')) {
            return $next($element, $params);
        }

        $size = $element->attr('size');
        $minWidth = !$size ? $image->getWidth() : null;
        $element = $next($element, ['sourceSetMinWidth' => $minWidth] + $params);

        if ($size === 'cover') {
            $width = $image->getWidth();
            $height = $image->getHeight();
            if ($width && $height) {
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
