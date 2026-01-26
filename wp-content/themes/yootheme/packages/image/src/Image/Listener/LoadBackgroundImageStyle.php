<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading background image styles.
 */
class LoadBackgroundImageStyle
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $image = $params['target'] ?? $params['source'];

        $element = $next($element, $params);

        [$width, $height, $size] = $element->attr('width', 'height', 'size');

        if ($size) {
            return $element;
        }

        if (($width || $height) && (!$image || !$image->isResizable())) {
            $element = $element->withStyle([
                'background-size' =>
                    ($width ? "{$width}px" : 'auto') . ' ' . ($height ? "{$height}px" : 'auto'),
            ]);

            if ($focalPoint = $params['focal_point'] ?? []) {
                $element = $element->withStyle([
                    'background-position' => implode(' ', $focalPoint),
                ]);
            }
        }

        if ($image && !$image->isType('svg')) {
            $element = $element->withStyle([
                'background-size' => "{$image->getWidth()}px {$image->getHeight()}px",
            ]);
        }

        return $element;
    }
}
