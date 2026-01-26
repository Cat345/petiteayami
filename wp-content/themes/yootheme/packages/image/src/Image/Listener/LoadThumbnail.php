<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\HtmlElement;

/**
 * Listener for loading image source sets.
 */
class LoadThumbnail
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        /**
         * @var array{int|string,int|string,?bool}|bool|null $thumbnail
         */
        $thumbnail = $element->attr('thumbnail');

        $source = $thumbnail ? $params['source'] : null;

        if ($source && $source->isResizable()) {
            [$width, $height] = $element->attr('width', 'height');

            $flip = false;
            if (is_array($thumbnail)) {
                [$width, $height] = $thumbnail;
                $flip = $thumbnail[2] ?? false;
            }

            $params['flip'] = $flip;

            if ($width || $height) {
                $params['target'] = $target = $source->thumbnail(
                    $width ?: '',
                    $height ?: '',
                    $flip,
                    ...$params['focal_point'] ?? [],
                );

                if ((!$width || !$height) && $target->getHeight()) {
                    if ($flip) {
                        if ($target->isPortrait() && $width > $height) {
                            [$width, $height] = [$height, $width];
                        } elseif ($target->isLandscape() && $height > $width) {
                            [$width, $height] = [$height, $width];
                        }
                    }

                    $ratio = $target->getWidth() / $target->getHeight();
                    if ($width) {
                        $height = round(((int) $width) / $ratio);
                    } else {
                        $width = round(((int) $height) * $ratio);
                    }

                    $element = $element->withAttrs([
                        'width' => (int) $width,
                        'height' => (int) $height,
                    ]);
                }
            }
        }

        $element = $next($element->withoutAttr('thumbnail'), $params);
        return empty($target) ? $element : $element->withAttr('src', (string) $target);
    }
}
