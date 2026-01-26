<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\Html;
use YOOtheme\Html\HtmlElement;
use YOOtheme\Url;

/**
 * Convert image to picture or img element.
 */
class LoadBackgroundImageElement
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $element = $next($element, $params);

        $image = Html::tag('div');

        $children = $element->children();

        if ($children) {
            $sources = json_encode(array_map(fn($child) => $child->attrs(), $children));
            $image = $image->withAttr('data-sources', $sources);
        } else {
            $image = $image->withAttrs([
                'data-srcset' => $element->attr('srcset'),
                'data-sizes' => $element->attr('sizes'),
            ]);
        }

        return $image->withAttrs([
            'data-src' => Url::to($element->attr('src')),
            'loading' => $element->attr('loading'),
            'style' => $element->attr('style'),
            'uk-img' => true,
        ]);
    }
}
