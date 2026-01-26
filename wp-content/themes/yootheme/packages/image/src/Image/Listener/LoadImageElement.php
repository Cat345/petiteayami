<?php

namespace YOOtheme\Image\Listener;

use YOOtheme\Html\Html;
use YOOtheme\Html\HtmlElement;
use YOOtheme\Url;

/**
 * Convert image to picture or img element.
 */
class LoadImageElement
{
    /**
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     * @param callable(HtmlElement $element, array<string, mixed> $params): HtmlElement $next
     */
    public static function handle($element, array $params, callable $next): HtmlElement
    {
        $element = $next($element, $params);
        $element = $element->withAttr('src', Url::to($element->attr('src')));

        $image = Html::img($element->attrs());
        $children = $element->children();

        return $children ? Html::picture()->withChildren([...$children, $image]) : $image;
    }
}
