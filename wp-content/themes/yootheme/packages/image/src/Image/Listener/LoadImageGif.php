<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading GIF images and setting their dimensions.
 */
class LoadImageGif
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

        [$width, $height] = $element->attr('width', 'height');

        if ((!$width || !$height) && $image && $image->isType('gif') && $image->getWidth()) {
            if ($width || $height) {
                $image = $image->thumbnail($width, $height);
            }

            return $element->withAttrs([
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
            ]);
        }

        return $element;
    }
}
