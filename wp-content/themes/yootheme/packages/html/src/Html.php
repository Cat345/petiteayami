<?php

namespace YOOtheme\Html;

class Html
{
    /**
     * @param array<mixed> $attributes
     */
    public static function tag(string $name, array $attributes = []): HtmlElement
    {
        return (new Element($name))->withAttrs($attributes);
    }

    /**
     * @param array<mixed> $attributes
     */
    public static function img(array $attributes = []): HtmlElement
    {
        return static::tag('img', $attributes);
    }

    /**
     * @param array<mixed> $attributes
     */
    public static function picture(array $attributes = []): HtmlElement
    {
        return static::tag('picture', $attributes);
    }
    /**
     * @param array<mixed> $attributes
     */
    public static function source(array $attributes = []): HtmlElement
    {
        return static::tag('source', $attributes);
    }

    public static function esc(string $text): string
    {
        return htmlentities($text, ENT_QUOTES, 'UTF-8', false);
    }
}
