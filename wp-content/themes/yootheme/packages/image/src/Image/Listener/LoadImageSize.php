<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;
use YOOtheme\Image;

/**
 * Listener for loading images and setting their dimensions.
 */
class LoadImageSize
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $src = $element->attr('src');
        $image = $params['source'] = $src ? Image::create($src) : null;

        $element = $next($element->withAttr('src', $image ?: $src), $params);

        foreach (['width', 'height'] as $attr) {
            $value = $element->attr($attr);
            if (!$value && $value !== false) {
                $element = $element->withAttr(
                    $attr,
                    !empty($image) ? $image->{"get{$attr}"}() : null,
                );
            }
        }

        return $element;
    }
}
