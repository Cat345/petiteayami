<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Config;
use YOOtheme\Html\Html;
use YOOtheme\Html\HtmlElement;
use YOOtheme\Image\ImageResizable;
use YOOtheme\Url;
use function YOOtheme\app;

/**
 * Listener for loading image source sets.
 */
class LoadSourceSet
{
    public static int $threshold = 100;

    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $source = $params['source'];

        $formats = $element->attr('formats') ?? true;
        $element = $next($element->withoutAttr('formats'), $params);

        if (!$source || !$source->isResizable() || $source->isRemote()) {
            return $element;
        }

        $target = $params['target'] ?? $source;

        $minWidth = $params['sourceSetMinWidth'] ?? null;
        $args = [(string) ($params['flip'] ?? ''), ...$params['focal_point'] ?? []];
        $images = static::getImages($source, $target, $minWidth, $args);

        if ($formats && ($formats = static::getFormat($source->getType()))) {
            foreach ($formats as $type => $quality) {
                $source = Html::source(['type' => "image/{$type}"]);

                if ($images) {
                    $srcset = [];

                    foreach ($images as $image) {
                        $image = $image->type($type, $quality);
                        $srcset[] = Url::to($image) . " {$image->getWidth()}w";
                    }

                    $source = $source->withAttrs([
                        'srcset' => implode(', ', $srcset),
                        'sizes' => "(min-width: {$target->getWidth()}px) {$target->getWidth()}px",
                    ]);
                }

                $element = $element->append($source);
            }
        } elseif ($images) {
            $srcset = array_map(fn($image) => Url::to($image) . " {$image->getWidth()}w", $images);

            $element = $element->withAttrs([
                'srcset' => implode(', ', $srcset),
                'sizes' => "(min-width: {$target->getWidth()}px) {$target->getWidth()}px",
            ]);
        }

        return $element;
    }

    /**
     * Get images for the source set.
     *
     * @param list<mixed> $args
     *
     * @return array<int, ImageResizable> An array of resized images.
     */
    protected static function getImages(
        ImageResizable $source,
        ImageResizable $target,
        ?int $minWidth,
        array $args
    ): array {
        $images = [];
        $targetWidth = $target->getWidth(10);
        $targetHeight = $target->getHeight(10);

        if (!$targetWidth || !$targetHeight) {
            return [];
        }

        $maxWidth = min(max($source->getWidth(), $targetWidth), $targetWidth * 2);
        $maxHeight = min(max($source->getHeight(), $targetHeight), $targetHeight * 2);

        $maxWidth = min($maxWidth, $targetWidth * ($maxHeight / $targetHeight));

        foreach ([768, 1024, 1366, 1600, 1920, $targetWidth * 2] as $width) {
            if ($minWidth && $width < $minWidth) {
                continue;
            }

            $width = min($width, $maxWidth);

            $images[round($width)] ??= $source->thumbnail(
                $width,
                $targetHeight * ($width / $targetWidth),
                ...$args,
            );
        }

        // needed?
        $images[$target->getWidth()] = $target;

        ksort($images);

        foreach ($keys = array_keys($images) as $i => $width) {
            if ($i && $width - $keys[$i - 1] <= static::$threshold) {
                unset($images[$keys[$i - 1]]);
            }
        }

        return $images;
    }

    /**
     * @return array<string, string|int>
     */
    protected static function getFormat(?string $type): array
    {
        static $formats;

        if (!isset($formats)) {
            $config = app(Config::class);

            // supports image avif?
            if ($config('~theme.avif') && function_exists('imageavif') && PHP_VERSION_ID >= 80100) {
                $png = (int) $config('~theme.image_quality_png_avif');
                $jpg = (int) $config('~theme.image_quality_jpg_avif');

                $formats['png']['avif'] = $png ?: 85;
                $formats['jpeg']['avif'] = $jpg ?: 75;
            }

            // supports image webp?
            if ($config('~theme.webp') && function_exists('imagewebp')) {
                $png = (int) $config('~theme.image_quality_png_webp');
                $jpg = (int) $config('~theme.image_quality_jpg_webp');

                $formats['png']['webp'] = $png ?: 100;
                $formats['jpeg']['webp'] = $jpg ?: 85;
            }
        }

        return $formats[$type] ?? [];
    }
}
