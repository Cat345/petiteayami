<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading SVG images and setting their dimensions.
 */
class LoadImageSvg
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $image = $params['target'] ?? $params['source'];
        [$width, $height] = $element->attr('width', 'height');

        $element = $next($element, $params);

        if (!$image || !$image->isType('svg')) {
            return $element->withoutAttr('uk-svg');
        }

        if ($image->getWidth() && $image->getHeight()) {
            if ($width xor $height) {
                $element = $element->withAttrs(
                    $image->ratio(['width' => $width, 'height' => $height]),
                );
            }
        }

        return $element;
    }
}
